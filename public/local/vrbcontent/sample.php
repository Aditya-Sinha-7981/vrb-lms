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
 * Serves the bundled sample CSV files (Book/Quiz) so an admin can download
 * one to see the expected column format, or forward it to the client as a
 * fill-in template. Same capability gate as the import wizards themselves -
 * this is admin-facing reference material, not public content.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();
require_capability('local/vrbcontent:import', context_system::instance());

$type = required_param('type', PARAM_ALPHA);
if (!in_array($type, ['book', 'quiz'], true)) {
    throw new moodle_exception('invalidparameter', 'debug');
}

$path = __DIR__ . "/samples/{$type}_sample.csv";
if (!is_readable($path)) {
    throw new moodle_exception('filenotfound', 'error');
}

send_file($path, "vrbcontent_{$type}_sample.csv", 0, 0, false, true, 'text/csv');
