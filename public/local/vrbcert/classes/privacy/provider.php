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

namespace local_vrbcert\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_vrbcert. All data lives in the system context.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_vrbcert_issued', [
            'userid' => 'privacy:metadata:local_vrbcert_issued:userid',
            'brandname' => 'privacy:metadata:local_vrbcert_issued:brandname',
            'period' => 'privacy:metadata:local_vrbcert_issued:period',
            'certrank' => 'privacy:metadata:local_vrbcert_issued:certrank',
            'scorepercent' => 'privacy:metadata:local_vrbcert_issued:scorepercent',
            'timecreated' => 'privacy:metadata:local_vrbcert_issued:timecreated',
        ], 'privacy:metadata:local_vrbcert_issued');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:certificatefiles');

        return $collection;
    }

    /**
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::user_has_rows($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $userlist->add_from_sql('userid', "SELECT userid FROM {local_vrbcert_issued}", []);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!in_array(CONTEXT_SYSTEM, array_map(static function(context $c) {
            return $c->contextlevel;
        }, $contextlist->get_contexts()), true)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('local_vrbcert_issued', ['userid' => $userid], 'timecreated ASC');
        if (!$records) {
            return;
        }

        $context = context_system::instance();
        $data = [];
        foreach ($records as $record) {
            $data[] = (object) [
                'brand' => $record->brandname,
                'track' => $record->track,
                'period' => $record->period,
                'rank' => $record->certrank,
                'scorepercent' => $record->scorepercent,
                'issued' => transform::datetime($record->timecreated),
            ];
            writer::with_context($context)->export_area_files(
                [get_string('pluginname', 'local_vrbcert')],
                'local_vrbcert',
                'certificate',
                $record->id
            );
        }

        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_vrbcert')],
            (object) ['certificates' => $data]
        );
    }

    /**
     * @param context $context
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_system) {
            return;
        }
        self::purge_rows_and_files(null);
    }

    /**
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                self::purge_rows_and_files($contextlist->get_user()->id);
            }
        }
    }

    /**
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::purge_rows_and_files($userid);
        }
    }

    /**
     * Delete issued rows + their stored PDFs, for one user or (null) everyone.
     *
     * @param int|null $userid
     */
    private static function purge_rows_and_files(?int $userid): void {
        global $DB;
        $conditions = $userid === null ? [] : ['userid' => $userid];
        $records = $DB->get_records('local_vrbcert_issued', $conditions, '', 'id');
        foreach (array_keys($records) as $id) {
            \local_vrbcert\issued_certificate::delete((int) $id);
        }
    }

    /**
     * @param int $userid
     * @return bool
     */
    private static function user_has_rows(int $userid): bool {
        global $DB;
        return $DB->record_exists('local_vrbcert_issued', ['userid' => $userid]);
    }
}
