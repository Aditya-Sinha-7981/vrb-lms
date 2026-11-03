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
 * Event observers for local_vrbcert.
 *
 * Any event that can change a quiz's final grade re-checks course completion
 * for that employee. 'internal' => false defers the observer until the
 * grading transaction has committed, so the new grade is visible and a
 * certificate failure can't roll back the attempt.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_graded',
        'callback' => '\local_vrbcert\completion_issuer::quiz_attempt_graded',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_regraded',
        'callback' => '\local_vrbcert\completion_issuer::quiz_attempt_graded',
        'internal' => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_manual_grading_completed',
        'callback' => '\local_vrbcert\completion_issuer::quiz_attempt_graded',
        'internal' => false,
    ],
];
