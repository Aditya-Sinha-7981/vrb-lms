<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Book import wizard controller for local_vrbcontent: destination -> field
 * template -> CSV upload -> preview/confirm. State is carried across steps
 * in $SESSION->local_vrbcontent_book (never in the URL/GET params, and
 * never assumed - each step re-validates that what it needs is present
 * before rendering).
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_vrbcontent\book\book_provisioner;
use local_vrbcontent\book\row_parser;
use local_vrbcontent\book\row_validator;
use local_vrbcontent\book\template;
use local_vrbcontent\form\confirm_form;
use local_vrbcontent\form\select_destination_form;
use local_vrbcontent\form\template_form;
use local_vrbcontent\form\upload_form;
use local_vrbcontent\import_batch;

require_login();
$context = context_system::instance();
require_capability('local/vrbcontent:import', $context);

$step = optional_param('step', 'course', PARAM_ALPHA);
$restart = optional_param('restart', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/vrbcontent/book_import.php', ['step' => $step]);
$indexurl = new moodle_url('/local/vrbcontent/index.php');

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('bookimporttitle', 'local_vrbcontent'));
$PAGE->set_heading(get_string('bookimporttitle', 'local_vrbcontent'));

if (empty($SESSION->local_vrbcontent_book) || $restart) {
    $SESSION->local_vrbcontent_book = new stdClass();
    $step = 'course';
}
$wizard = $SESSION->local_vrbcontent_book;

switch ($step) {

    case 'course':
        $courses = [];
        foreach (get_courses('all') as $course) {
            if ((int) $course->id === (int) SITEID) {
                continue;
            }
            $courses[$course->id] = format_string($course->fullname);
        }
        asort($courses);

        $form = new select_destination_form($pageurl, ['stage' => 'course', 'courses' => $courses]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->courseid = (int) $data->courseid;
            $SESSION->local_vrbcontent_book = $wizard;
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'target']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step1title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'target':
        if (empty($wizard->courseid)) {
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'course']));
        }
        $course = get_course($wizard->courseid);

        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[$section->section] = get_section_name($course, $section);
        }

        $books = [];
        foreach (get_all_instances_in_course('book', $course) as $book) {
            $books[$book->coursemodule] = format_string($book->name);
        }

        $form = new select_destination_form($pageurl, [
            'stage' => 'target',
            'courseid' => $course->id,
            'sections' => $sections,
            'books' => $books,
        ]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->sectionnum = (int) $data->sectionnum;
            $wizard->cmid = (int) $data->cmid;
            $wizard->newbooktitle = $data->newbooktitle ?? '';
            $SESSION->local_vrbcontent_book = $wizard;
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'template']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step2title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'template':
        $form = new template_form($pageurl, ['repeatno' => 6]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $templateid = (int) $data->templateid;

            if ($templateid === 0) {
                $labels = array_values(array_filter(array_map('trim', $data->fieldlabel)));
                $fields = [];
                foreach ($labels as $i => $label) {
                    $fields[] = [
                        'label' => $label,
                        'key' => preg_replace('/[^a-z0-9]+/', '_', strtolower($label)),
                        'is_title' => $i === 0,
                    ];
                }
                $newtemplate = new template($data->templatename, $fields);
                $templateid = $newtemplate->save();
            }

            $wizard->templateid = $templateid;
            $SESSION->local_vrbcontent_book = $wizard;
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'upload']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step3title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'upload':
        $form = new upload_form($pageurl);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->csvcontent = $form->get_file_content('csvfile');
            $SESSION->local_vrbcontent_book = $wizard;
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'confirm']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step4title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'confirm':
        if (empty($wizard->templateid) || !isset($wizard->csvcontent) || empty($wizard->courseid)) {
            redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'course']));
        }

        $course = get_course($wizard->courseid);
        $template = template::load($wizard->templateid);
        $parsed = row_parser::parse($wizard->csvcontent);
        $result = row_validator::validate($parsed, $template);

        $existingbatch = null;
        $duplicate = false;
        $priordate = '';
        if (!$result->is_blocked() && !empty($wizard->cmid)) {
            $existingbatch = import_batch::find_latest((int) $wizard->cmid, 'book');
            if ($existingbatch && import_batch::hash_rows($result->rows) === $existingbatch->contenthash) {
                $duplicate = true;
                $priordate = userdate($existingbatch->timecreated);
            }
        }

        $form = new confirm_form($pageurl, [
            'blocked' => $result->is_blocked(),
            'duplicate' => $duplicate,
            'priordate' => $priordate,
            'cancelurl' => new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'course', 'restart' => 1]),
        ]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $duplicateaction = $data->duplicateaction ?? null;

            if ($duplicate && $duplicateaction === 'cancel') {
                unset($SESSION->local_vrbcontent_book);
                redirect($indexurl, get_string('importcancelled', 'local_vrbcontent'));
            }
            if ($duplicate && $duplicateaction === 'skip') {
                unset($SESSION->local_vrbcontent_book);
                redirect($indexurl, get_string('importskipped', 'local_vrbcontent'));
            }

            $target = book_provisioner::get_or_create_book(
                $course,
                (int) $wizard->sectionnum,
                (int) $wizard->cmid,
                $wizard->newbooktitle ?? ''
            );

            if ($duplicate && $duplicateaction === 'replace' && $existingbatch) {
                book_provisioner::delete_chapters(
                    $target->bookid,
                    $target->cmid,
                    $existingbatch->get_item_ids('chapter')
                );
                $existingbatch->mark_superseded();
            }

            $newbatch = import_batch::create(
                $course->id,
                $target->cmid,
                'book',
                import_batch::hash_rows($result->rows),
                count($result->rows),
                $USER->id
            );
            book_provisioner::create_chapters($target->bookid, $target->cmid, $result, $newbatch);

            unset($SESSION->local_vrbcontent_book);

            redirect(
                new moodle_url('/mod/book/view.php', ['id' => $target->cmid]),
                get_string('importsuccess', 'local_vrbcontent', count($result->rows))
            );
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step5title', 'local_vrbcontent'));

        if (!empty($result->errors)) {
            echo $OUTPUT->notification(get_string('errorsheading', 'local_vrbcontent'), 'notifyproblem');
            echo html_writer::alist(array_map('s', $result->errors));
        }
        if (!empty($result->warnings)) {
            echo $OUTPUT->notification(get_string('warningsheading', 'local_vrbcontent'), 'notifywarning');
            echo html_writer::alist(array_map('s', $result->warnings));
        }
        if (!$result->is_blocked()) {
            echo $OUTPUT->notification(
                get_string('rowcount', 'local_vrbcontent', count($result->rows)),
                'notifysuccess'
            );
        }

        $form->display();
        echo $OUTPUT->footer();
        break;

    default:
        redirect(new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'course']));
}
