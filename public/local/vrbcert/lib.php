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
 * Callbacks for local_vrbcert.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a "My certificates" link to the navigation drawer for logged-in users.
 * Mirrors local_vrblms/lib.php's leaderboard link - the same legacy
 * per-plugin callback, still dispatched for `local` plugins in this version.
 *
 * @param global_navigation $navigation
 */
function local_vrbcert_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!has_capability('local/vrbcert:viewown', context_system::instance())) {
        return;
    }

    $navigation->add(
        get_string('mycertificates', 'local_vrbcert'),
        new moodle_url('/local/vrbcert/mycertificates.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'vrbcertmycertificates'
    );
}

/**
 * Serve a stored certificate PDF.
 *
 * Access: the owner (with viewown), or a manager (viewall / manage).
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args [itemid, filename]
 * @param bool $forcedownload
 * @param array $options
 * @return bool false on failure; otherwise streams and exits
 */
function local_vrbcert_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'certificate') {
        return false;
    }

    require_login();

    $itemid = (int) array_shift($args);
    $filename = array_shift($args);
    if ($itemid <= 0 || $filename === null) {
        return false;
    }

    $record = $DB->get_record('local_vrbcert_issued', ['id' => $itemid]);
    if (!$record) {
        return false;
    }

    $canviewall = has_capability('local/vrbcert:viewall', $context)
        || has_capability('local/vrbcert:manage', $context);
    $isowner = ((int) $record->userid === (int) $USER->id)
        && has_capability('local/vrbcert:viewown', $context);
    if (!$canviewall && !$isowner) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_vrbcert', 'certificate', $itemid, '/', $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, true, $options);
}
