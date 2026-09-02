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

namespace local_vrbcert;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_vrbcert.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add a "My certificates" item to the site primary (header) navigation,
     * but only for a user who actually holds at least one certificate - the
     * nav-drawer link (local_vrbcert/lib.php) is always shown; the header
     * slot is reserved until there is something to see.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER, $DB;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (!has_capability('local/vrbcert:viewown', \context_system::instance())) {
            return;
        }
        if (!$DB->record_exists('local_vrbcert_issued', ['userid' => $USER->id])) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('mycertificates', 'local_vrbcert'),
            new \moodle_url('/local/vrbcert/mycertificates.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'vrbcertmycertificates'
        );
    }
}
