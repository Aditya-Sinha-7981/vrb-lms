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

namespace local_vrbcontent\quiz;

use local_vrbcontent\csv_reader;

/**
 * Mechanical CSV -> rows parsing for Quiz imports. The fixed quiz column
 * format (Q.No, Topic, Question, A, B, C, D, Correct Answer, Explanation)
 * is checked by quiz_row_validator, not here - this only turns the file
 * into a header + row arrays.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_row_parser {
    /**
     * @param string $csvcontent
     * @return array{header: string[], rows: array<int, array<string,string>>, error: string|null}
     */
    public static function parse(string $csvcontent): array {
        return csv_reader::read($csvcontent);
    }
}
