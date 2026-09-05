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

namespace local_vrbcontent;

/**
 * Wraps one local_vrbcontent_import record: duplicate-import detection via
 * a content hash of the normalized validated rows, and the child
 * local_vrbcontent_import_item rows that let a "Re-import (replace)" delete
 * exactly what a prior batch created - never a raw DELETE FROM.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_batch {
    public ?int $id;
    public int $courseid;
    public int $cmid;
    public string $importtype;
    public string $contenthash;
    public int $rowcount;
    public string $status;
    public int $userid;
    public int $timecreated;

    private function __construct() {
    }

    /**
     * Deterministic hash of the normalized validated rows, used to detect
     * "this exact content was already imported" independent of row order
     * within a template's field map (the row shape itself already comes
     * out of the validator in a stable key order).
     *
     * @param array $rows validated_result::$rows
     * @return string
     */
    public static function hash_rows(array $rows): string {
        return hash('sha256', json_encode($rows));
    }

    /**
     * The most recent 'complete' import targeting this course-module, if any.
     *
     * @param int $cmid
     * @param string $importtype 'book'|'quiz'
     * @return self|null
     */
    public static function find_latest(int $cmid, string $importtype): ?self {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT * FROM {local_vrbcontent_import}
              WHERE cmid = :cmid AND importtype = :importtype AND status = :status
           ORDER BY timecreated DESC",
            ['cmid' => $cmid, 'importtype' => $importtype, 'status' => 'complete'],
            IGNORE_MULTIPLE
        );

        return $record ? self::from_record($record) : null;
    }

    private static function from_record(\stdClass $record): self {
        $batch = new self();
        $batch->id = (int) $record->id;
        $batch->courseid = (int) $record->courseid;
        $batch->cmid = (int) $record->cmid;
        $batch->importtype = $record->importtype;
        $batch->contenthash = $record->contenthash;
        $batch->rowcount = (int) $record->rowcount;
        $batch->status = $record->status;
        $batch->userid = (int) $record->userid;
        $batch->timecreated = (int) $record->timecreated;
        return $batch;
    }

    /**
     * @param int $courseid
     * @param int $cmid
     * @param string $importtype 'book'|'quiz'
     * @param string $contenthash
     * @param int $rowcount
     * @param int $userid
     * @return self the newly created, 'complete' batch.
     */
    public static function create(int $courseid, int $cmid, string $importtype, string $contenthash, int $rowcount, int $userid): self {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->cmid = $cmid;
        $record->importtype = $importtype;
        $record->contenthash = $contenthash;
        $record->rowcount = $rowcount;
        $record->status = 'complete';
        $record->userid = $userid;
        $record->timecreated = time();

        $batch = self::from_record($record);
        $batch->id = (int) $DB->insert_record('local_vrbcontent_import', $record);
        return $batch;
    }

    /**
     * Record one Moodle object this batch created, so a later "replace" can
     * find and delete exactly these, via their own Moodle delete APIs.
     *
     * @param string $itemtype 'chapter'|'question'
     * @param int $itemid
     */
    public function add_item(string $itemtype, int $itemid): void {
        global $DB;

        $record = new \stdClass();
        $record->importid = $this->id;
        $record->itemtype = $itemtype;
        $record->itemid = $itemid;
        $DB->insert_record('local_vrbcontent_import_item', $record);
    }

    /**
     * @param string|null $itemtype filter to just this type, or null for all.
     * @return int[] itemids created by this batch.
     */
    public function get_item_ids(?string $itemtype = null): array {
        global $DB;

        $conditions = ['importid' => $this->id];
        if ($itemtype !== null) {
            $conditions['itemtype'] = $itemtype;
        }

        return array_values($DB->get_records_menu('local_vrbcontent_import_item', $conditions, 'id ASC', 'id, itemid'));
    }

    /**
     * Mark this batch superseded (a newer import replaced its content) and
     * drop its item rows - the caller is responsible for having already
     * deleted the actual Moodle objects via their own delete APIs first.
     */
    public function mark_superseded(): void {
        global $DB;

        $DB->set_field('local_vrbcontent_import', 'status', 'superseded', ['id' => $this->id]);
        $DB->delete_records('local_vrbcontent_import_item', ['importid' => $this->id]);
    }
}
