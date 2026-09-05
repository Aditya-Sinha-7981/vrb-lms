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
 * Quiz import wizard controller for local_vrbcontent: destination -> quiz
 * settings -> CSV upload -> preview/confirm. State is carried across steps
 * in $SESSION->local_vrbcontent_quiz, mirroring book_import.php.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_vrbcontent\form\confirm_form;
use local_vrbcontent\form\quiz_settings_form;
use local_vrbcontent\form\select_destination_form;
use local_vrbcontent\form\upload_form;
use local_vrbcontent\import_batch;
use local_vrbcontent\quiz\question_bank_provisioner;
use local_vrbcontent\quiz\quiz_configurator;
use local_vrbcontent\quiz\quiz_row_parser;
use local_vrbcontent\quiz\quiz_row_validator;

require_login();
$context = context_system::instance();
require_capability('local/vrbcontent:import', $context);

$step = optional_param('step', 'course', PARAM_ALPHA);
$restart = optional_param('restart', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => $step]);
$indexurl = new moodle_url('/local/vrbcontent/index.php');

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('quizimporttitle', 'local_vrbcontent'));
$PAGE->set_heading(get_string('quizimporttitle', 'local_vrbcontent'));

if (empty($SESSION->local_vrbcontent_quiz) || $restart) {
    $SESSION->local_vrbcontent_quiz = new stdClass();
    $step = 'course';
}
$wizard = $SESSION->local_vrbcontent_quiz;

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
            $SESSION->local_vrbcontent_quiz = $wizard;
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'target']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('step1title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'target':
        if (empty($wizard->courseid)) {
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course']));
        }
        $course = get_course($wizard->courseid);

        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[$section->section] = get_section_name($course, $section);
        }

        $quizzes = [];
        foreach (get_all_instances_in_course('quiz', $course) as $quiz) {
            $quizzes[$quiz->coursemodule] = format_string($quiz->name);
        }

        $form = new select_destination_form($pageurl, [
            'stage' => 'quiztarget',
            'courseid' => $course->id,
            'sections' => $sections,
            'quizzes' => $quizzes,
        ]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->sectionnum = (int) $data->sectionnum;
            $wizard->cmid = (int) $data->cmid;
            $wizard->newquiztitle = $data->newquiztitle ?? '';
            $SESSION->local_vrbcontent_quiz = $wizard;
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'settings']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('quizstep2title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'settings':
        if (empty($wizard->courseid) || !isset($wizard->cmid)) {
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course']));
        }
        $course = get_course($wizard->courseid);

        $modinfo = get_fast_modinfo($course);
        // -1 (never a real section number) is the "no gating" sentinel -
        // section 0 ("General") is a real, selectable section and must not
        // collide with it.
        $sections = [-1 => get_string('nogating', 'local_vrbcontent')];
        foreach ($modinfo->get_section_info_all() as $section) {
            $sections[$section->section] = get_section_name($course, $section);
        }

        $creatingnewquiz = ((int) $wizard->cmid === 0);

        $form = new quiz_settings_form($pageurl, [
            'sections' => $sections,
            'creatingnewquiz' => $creatingnewquiz,
        ]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->modulelabel = $data->modulelabel;
            $wizard->passpercent = $creatingnewquiz ? (float) $data->passpercent : null;
            $wizard->maxattempts = $creatingnewquiz ? (int) $data->maxattempts : null;
            $wizard->gatesectionnum = (int) $data->gatesectionnum;
            $SESSION->local_vrbcontent_quiz = $wizard;
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'upload']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('quizstep3title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'upload':
        $form = new upload_form($pageurl);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $wizard->csvcontent = $form->get_file_content('csvfile');
            $SESSION->local_vrbcontent_quiz = $wizard;
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'confirm']));
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('quizstep4title', 'local_vrbcontent'));
        $form->display();
        echo $OUTPUT->footer();
        break;

    case 'confirm':
        if (empty($wizard->courseid) || !isset($wizard->cmid) || !isset($wizard->csvcontent)) {
            redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course']));
        }

        $course = get_course($wizard->courseid);
        $parsed = quiz_row_parser::parse($wizard->csvcontent);
        $result = quiz_row_validator::validate($parsed);

        $existingbatch = null;
        $duplicate = false;
        $priordate = '';
        if (!$result->is_blocked() && !empty($wizard->cmid)) {
            $existingbatch = import_batch::find_latest((int) $wizard->cmid, 'quiz');
            if ($existingbatch && import_batch::hash_rows($result->rows) === $existingbatch->contenthash) {
                $duplicate = true;
                $priordate = userdate($existingbatch->timecreated);
            }
        }

        $form = new confirm_form($pageurl, [
            'blocked' => $result->is_blocked(),
            'duplicate' => $duplicate,
            'priordate' => $priordate,
            'cancelurl' => new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course', 'restart' => 1]),
        ]);

        if ($form->is_cancelled()) {
            redirect($indexurl);
        } else if ($data = $form->get_data()) {
            $duplicateaction = $data->duplicateaction ?? null;

            if ($duplicate && $duplicateaction === 'cancel') {
                unset($SESSION->local_vrbcontent_quiz);
                redirect($indexurl, get_string('importcancelled', 'local_vrbcontent'));
            }
            if ($duplicate && $duplicateaction === 'skip') {
                unset($SESSION->local_vrbcontent_quiz);
                redirect($indexurl, get_string('importskipped', 'local_vrbcontent'));
            }

            $target = quiz_configurator::get_or_create_quiz(
                $course,
                (int) $wizard->sectionnum,
                (int) $wizard->cmid,
                $wizard->newquiztitle ?? '',
                $wizard->passpercent ?? null,
                $wizard->maxattempts ?? null
            );

            if ($duplicate && $duplicateaction === 'replace' && $existingbatch) {
                try {
                    question_bank_provisioner::delete_questions(
                        $target->quizid,
                        $target->cmid,
                        $existingbatch->get_item_ids('question')
                    );
                } catch (\moodle_exception $e) {
                    // mod_quiz\structure::remove_slot() refuses once the quiz has
                    // real learner attempts (verified Phase 3) - correct, not a
                    // bug: replacing questions would corrupt historical grading
                    // data. Nothing was mutated (the exception fires before any
                    // deletion), so it's safe to just stop here with a clear
                    // message rather than Moodle's raw exception page.
                    redirect(
                        new moodle_url('/mod/quiz/view.php', ['id' => $target->cmid]),
                        get_string('replaceblockedbyattempts', 'local_vrbcontent'),
                        null,
                        \core\output\notification::NOTIFY_ERROR
                    );
                }
                $existingbatch->mark_superseded();
            }

            $categorytarget = question_bank_provisioner::get_or_create_category($course, $wizard->modulelabel);

            $newbatch = import_batch::create(
                $course->id,
                $target->cmid,
                'quiz',
                import_batch::hash_rows($result->rows),
                count($result->rows),
                $USER->id
            );

            $quizrecord = $DB->get_record('quiz', ['id' => $target->quizid], '*', MUST_EXIST);
            question_bank_provisioner::create_questions($categorytarget, $quizrecord, $target->cmid, $result, $newbatch);

            if ((int) $wizard->gatesectionnum !== -1) {
                quiz_configurator::set_gating($course, $target->cmid, (int) $wizard->gatesectionnum);
            }

            unset($SESSION->local_vrbcontent_quiz);

            redirect(
                new moodle_url('/mod/quiz/view.php', ['id' => $target->cmid]),
                get_string('quizimportsuccess', 'local_vrbcontent', count($result->rows))
            );
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('quizstep5title', 'local_vrbcontent'));

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
        redirect(new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course']));
}
