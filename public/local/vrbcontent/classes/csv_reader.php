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
 * Mechanical CSV -> rows reader, shared by the Book and Quiz parsers. Pure
 * PHP, no Moodle dependency - deliberately knows nothing about template
 * fields or the fixed quiz column format, only how to turn CSV text into a
 * header array + row arrays, using fgetcsv() (not a naive line-split) so
 * quoted fields containing commas/newlines are handled correctly.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_reader {
    /**
     * @param string $content raw CSV file content.
     * @return array{header: string[], rows: array<int, array<string,string>>, error: string|null}
     *         error is null on success, otherwise one of:
     *         'empty_file', 'no_data_rows', or 'malformed_row:<row number>'
     *         (row number is 1-indexed including the header row).
     */
    public static function read(string $content): array {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return ['header' => [], 'rows' => [], 'error' => 'empty_file'];
        }
        $header = array_map('trim', $header);

        $rows = [];
        $rownum = 1;
        while (($cells = fgetcsv($handle)) !== false) {
            $rownum++;
            if (count($cells) === 1 && $cells[0] === null) {
                // A fully blank line - fgetcsv() returns [null] for these, not [].
                continue;
            }
            if (count($cells) !== count($header)) {
                fclose($handle);
                return ['header' => $header, 'rows' => [], 'error' => "malformed_row:$rownum"];
            }
            $rows[] = array_combine($header, $cells);
        }
        fclose($handle);

        if (empty($rows)) {
            return ['header' => $header, 'rows' => [], 'error' => 'no_data_rows'];
        }

        return ['header' => $header, 'rows' => $rows, 'error' => null];
    }

    /**
     * Shared human-readable description for a parse error, reused by both
     * the Book and Quiz validators so the wording stays consistent.
     */
    public static function describe_error(string $error): string {
        if ($error === 'empty_file') {
            return 'The uploaded file is empty.';
        }
        if ($error === 'no_data_rows') {
            return 'The file has a header row but no data rows.';
        }
        if (str_starts_with($error, 'malformed_row:')) {
            $rownum = substr($error, strlen('malformed_row:'));
            return "Row $rownum has a different number of columns than the header row.";
        }
        return 'The file could not be parsed as CSV.';
    }
}
