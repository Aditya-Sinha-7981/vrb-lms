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
 * Scheduled task: issue certificates to current top performers.
 *
 * Disabled by default (see db/tasks.php) - enable once the issuance cadence
 * is agreed. Runs a plain, idempotent {@see \local_vrbcert\issuer::run()}.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issue_certificates extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name() {
        return get_string('taskissuecertificates', 'local_vrbcert');
    }

    public function execute() {
        $summary = \local_vrbcert\issuer::run(null, 0, false);

        if (!empty($summary['disabled'])) {
            mtrace('local_vrbcert: issuance disabled in settings - nothing done.');
            return;
        }

        mtrace(sprintf(
            'local_vrbcert: period "%s" - %d issued, %d re-issued, %d skipped, %d error(s).',
            $summary['period'],
            $summary['issued'],
            $summary['reissued'],
            $summary['skipped'],
            count($summary['errors'])
        ));
        foreach ($summary['errors'] as $error) {
            mtrace('  ! ' . $error);
        }
    }
}
