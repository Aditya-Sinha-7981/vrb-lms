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
 * CRUD for the local_vrbcert_issued table plus the File API glue for the
 * generated PDFs (component local_vrbcert, filearea 'certificate',
 * itemid = the issued row id, system context).
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class issued_certificate {

    const TABLE = 'local_vrbcert_issued';
    const COMPONENT = 'local_vrbcert';
    const FILEAREA = 'certificate';

    /**
     * @param int $userid
     * @param string $brandkey
     * @param string $period
     * @return \stdClass|null
     */
    public static function find(int $userid, string $brandkey, string $period): ?\stdClass {
        global $DB;
        $record = $DB->get_record(self::TABLE, [
            'userid' => $userid,
            'brandkey' => $brandkey,
            'period' => $period,
        ]);
        return $record ?: null;
    }

    /**
     * Create the record and store its PDF. Returns the stored record.
     *
     * @param certificate_data $data
     * @param string $strategykey effective ranking strategy key
     * @param int $issuedby admin userid, or 0 for cron
     * @param string $pdfcontent PDF bytes
     * @return \stdClass
     */
    public static function create(certificate_data $data, string $strategykey, int $issuedby, string $pdfcontent): \stdClass {
        global $DB;

        $now = time();
        $record = (object) [
            'userid' => $data->userid,
            'brandkey' => $data->brandkey,
            'brandname' => $data->brandname,
            'period' => $data->period,
            'track' => $data->track,
            'certrank' => $data->rank,
            'scorepercent' => $data->scorepercent,
            'strategykey' => $strategykey,
            'filename' => '',
            'issuedby' => $issuedby,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        $filename = self::build_filename($data, $record->id);
        self::store_file($record->id, $filename, $pdfcontent);

        $record->filename = $filename;
        $DB->update_record(self::TABLE, (object) [
            'id' => $record->id,
            'filename' => $filename,
            'timemodified' => time(),
        ]);

        return $record;
    }

    /**
     * Delete one issued certificate: its stored file and its row.
     *
     * @param int $id
     */
    public static function delete(int $id): void {
        global $DB;
        $fs = get_file_storage();
        $fs->delete_area_files(\context_system::instance()->id, self::COMPONENT, self::FILEAREA, $id);
        $DB->delete_records(self::TABLE, ['id' => $id]);
    }

    /**
     * @param int $userid
     * @return \stdClass[] newest first
     */
    public static function get_for_user(int $userid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['userid' => $userid], 'timecreated DESC'));
    }

    /**
     * @param array $filters optional 'period', 'brandkey'
     * @return \stdClass[] newest first
     */
    public static function get_all(array $filters = []): array {
        global $DB;
        $conditions = [];
        foreach (['period', 'brandkey'] as $key) {
            if (!empty($filters[$key])) {
                $conditions[$key] = $filters[$key];
            }
        }
        return array_values($DB->get_records(self::TABLE, $conditions, 'timecreated DESC'));
    }

    /**
     * @return string[] distinct period labels present, newest-looking first
     */
    public static function distinct_periods(): array {
        global $DB;
        $values = $DB->get_fieldset_sql(
            "SELECT DISTINCT period FROM {" . self::TABLE . "} ORDER BY period DESC"
        );
        return $values ?: [];
    }

    /**
     * The pluginfile URL for a stored certificate.
     *
     * @param \stdClass $record row from this table
     * @return \moodle_url
     */
    public static function file_url(\stdClass $record): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            \context_system::instance()->id,
            self::COMPONENT,
            self::FILEAREA,
            $record->id,
            '/',
            $record->filename,
            true
        );
    }

    /**
     * @param int $itemid issued row id
     * @param string $filename
     * @param string $content
     */
    private static function store_file(int $itemid, string $filename, string $content): void {
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => \context_system::instance()->id,
            'component' => self::COMPONENT,
            'filearea' => self::FILEAREA,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => 'application/pdf',
        ];
        // Defensive: clear anything already in this itemid's area (re-issue path deletes first, but be safe).
        $fs->delete_area_files($filerecord['contextid'], self::COMPONENT, self::FILEAREA, $itemid);
        $fs->create_file_from_string($filerecord, $content);
    }

    /**
     * @param certificate_data $data
     * @param int $id issued row id
     * @return string safe filename
     */
    private static function build_filename(certificate_data $data, int $id): string {
        $brand = $data->brandkey === 'overall' ? 'overall' : preg_replace('/^brand_/', '', $data->brandkey);
        $parts = ['vrbcert', $brand, $data->period, 'emp' . $data->userid, $id];
        $slug = \core_text::strtolower(implode('-', $parts));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim(preg_replace('/-+/', '-', $slug), '-');
        return $slug . '.pdf';
    }
}
