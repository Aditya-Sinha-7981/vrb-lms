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
 * An employee's own issued certificates.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/vrbcert:viewown', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/vrbcert/mycertificates.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mycertificates', 'local_vrbcert'));
$PAGE->set_heading(get_string('mycertificates', 'local_vrbcert'));

$records = \local_vrbcert\issued_certificate::get_for_user($USER->id);

echo $OUTPUT->header();

if (!$records) {
    echo $OUTPUT->notification(get_string('nocertificates', 'local_vrbcert'), 'info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable vrbcert-mycerts';
    $table->head = [
        get_string('col_brand', 'local_vrbcert'),
        get_string('col_period', 'local_vrbcert'),
        get_string('col_rank', 'local_vrbcert'),
        get_string('col_score', 'local_vrbcert'),
        get_string('col_issued', 'local_vrbcert'),
        get_string('col_actions', 'local_vrbcert'),
    ];

    foreach ($records as $record) {
        $download = html_writer::link(
            \local_vrbcert\issued_certificate::file_url($record),
            get_string('downloadpdf', 'local_vrbcert'),
            ['class' => 'btn btn-secondary btn-sm', 'target' => '_blank', 'rel' => 'noopener']
        );
        $score = $record->scorepercent === null ? '' : format_float($record->scorepercent, 1) . '%';
        $table->data[] = [
            s($record->brandname),
            s($record->period),
            $record->certrank === null ? '' : s($record->certrank),
            $score,
            userdate($record->timecreated, get_string('strftimedate', 'langconfig')),
            $download,
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
