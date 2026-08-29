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

namespace local_vrblms;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_vrblms.
 *
 * @package    local_vrblms
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add a "Leaderboard" item to the site primary navigation, right
     * after "My courses" (see \core\navigation\views\primary::initialise,
     * which dispatches this hook after adding Home / Dashboard / My
     * courses). local_vrblms/lib.php also adds it to the navigation
     * drawer tree - this is the header-bar copy, matching how core items
     * like "My courses" appear in both.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('leaderboard', 'local_vrblms'),
            new \moodle_url('/local/vrblms/leaderboard.php'),
            \navigation_node::TYPE_ROOTNODE,
            null,
            'vrblmsleaderboard'
        );
    }
}
