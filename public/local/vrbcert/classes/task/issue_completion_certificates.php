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

namespace local_vrbcert\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: catch-up sweep for course-completion certificates.
 *
 * Certificates are normally issued the moment the last quiz is passed (see
 * db/events.php); this daily sweep issues any that were missed. Idempotent.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_completion_certificates extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('taskissuecompletioncertificates', 'local_vrbcert');
    }

    public function execute() {
        $summary = \local_vrbcert\completion_issuer::run(0);

        if (!empty($summary['disabled'])) {
            mtrace('local_vrbcert: completion certificates disabled in settings - nothing done.');
            return;
        }

        mtrace(sprintf(
            'local_vrbcert: completion certificates - %d issued, %d already held, %d error(s).',
            $summary['issued'],
            $summary['skipped'],
            count($summary['errors'])
        ));
        foreach ($summary['errors'] as $error) {
            mtrace('  ! ' . $error);
        }
    }
}
