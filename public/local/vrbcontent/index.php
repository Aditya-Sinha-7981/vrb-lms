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
 * local_vrbcontent landing page: links into the Book and Quiz import
 * wizards. This page itself does no provisioning - it only routes.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_vrbcontent_import');

$context = context_system::instance();
require_capability('local/vrbcontent:import', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('adminpageheading', 'local_vrbcontent'));
echo html_writer::tag('p', get_string('indexintro', 'local_vrbcontent'));

$bookurl = new moodle_url('/local/vrbcontent/book_import.php', ['step' => 'course', 'restart' => 1]);

echo html_writer::start_tag('div', ['class' => 'card mb-3']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h3', html_writer::link($bookurl, get_string('importbook', 'local_vrbcontent')));
echo html_writer::tag('p', get_string('importbookdesc', 'local_vrbcontent'));
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

$quizurl = new moodle_url('/local/vrbcontent/quiz_import.php', ['step' => 'course', 'restart' => 1]);

echo html_writer::start_tag('div', ['class' => 'card mb-3']);
echo html_writer::start_tag('div', ['class' => 'card-body']);
echo html_writer::tag('h3', html_writer::link($quizurl, get_string('importquiz', 'local_vrbcontent')));
echo html_writer::tag('p', get_string('importquizdesc', 'local_vrbcontent'));
echo html_writer::end_tag('div');
echo html_writer::end_tag('div');

echo $OUTPUT->footer();
