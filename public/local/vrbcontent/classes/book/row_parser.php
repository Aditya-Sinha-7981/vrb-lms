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

namespace local_vrbcontent\book;

use local_vrbcontent\csv_reader;

/**
 * Mechanical CSV -> rows parsing for Book imports. Template-agnostic - it
 * only turns the file into a header + row arrays; matching the template's
 * field labels against that header is row_validator's job.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class row_parser {
    /**
     * @param string $csvcontent
     * @return array{header: string[], rows: array<int, array<string,string>>, error: string|null}
     */
    public static function parse(string $csvcontent): array {
        return csv_reader::read($csvcontent);
    }
}
