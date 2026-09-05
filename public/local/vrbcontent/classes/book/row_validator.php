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
use local_vrbcontent\validated_result;

/**
 * Validates parsed Book CSV rows against a field template. Pure PHP - no
 * Moodle DB calls. Source content is authoritative: never invent, normalize,
 * or "fix" a value - missing/uncertain values are surfaced as warnings and
 * preserved verbatim, never rewritten.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class row_validator {
    /** @var string[] Substrings (matched case-insensitively) that flag a cell as an uncertain/placeholder value worth surfacing, never rewriting. */
    private const PLACEHOLDER_PATTERNS = ['confirm with', 'not specified', 'tbd', 'sold out'];

    /**
     * @param array{header: string[], rows: array<int, array<string,string>>, error: string|null} $parsed
     * @param template $template
     * @return validated_result rows shaped as [{title: string, fields: array<label,value>}, ...]
     */
    public static function validate(array $parsed, template $template): validated_result {
        $result = new validated_result();

        if ($parsed['error'] !== null) {
            $result->add_error(csv_reader::describe_error($parsed['error']));
            return $result;
        }

        // Template self-check: the same source column label mapped to two different fields.
        $seenlabels = [];
        foreach ($template->fields as $field) {
            $label = strtolower(trim($field['label']));
            if (isset($seenlabels[$label])) {
                $result->add_error("Template maps the column \"{$field['label']}\" to more than one field.");
            }
            $seenlabels[$label] = true;
        }
        if ($result->is_blocked()) {
            return $result;
        }

        // Header coverage: every template field's label must exist in the CSV header
        // (matched by exact label, case-insensitive/trimmed - not by column position).
        $headerlookup = [];
        foreach ($parsed['header'] as $col) {
            $headerlookup[strtolower(trim($col))] = $col;
        }
        $missing = [];
        foreach ($template->fields as $field) {
            $key = strtolower(trim($field['label']));
            if (!array_key_exists($key, $headerlookup)) {
                $missing[] = $field['label'];
            }
        }
        if (!empty($missing)) {
            $result->add_error('CSV is missing required column(s): ' . implode(', ', $missing));
            return $result;
        }

        foreach ($parsed['rows'] as $i => $row) {
            $displayrow = $i + 2; // 1-indexed data row + header row.
            $entry = ['title' => null, 'fields' => []];

            foreach ($template->fields as $field) {
                $sourcecol = $headerlookup[strtolower(trim($field['label']))];
                $value = $row[$sourcecol] ?? '';

                if (!empty($field['is_title'])) {
                    if (trim($value) === '') {
                        $result->add_error("Row $displayrow: title field \"{$field['label']}\" is empty.");
                    }
                    $entry['title'] = $value;
                    continue;
                }

                if (trim($value) === '') {
                    $result->add_warning("Row $displayrow: \"{$field['label']}\" is empty.");
                } else if (self::looks_like_placeholder($value)) {
                    $result->add_warning(
                        "Row $displayrow: \"{$field['label']}\" contains an uncertain/placeholder value " .
                        "(\"$value\") - preserved as-is."
                    );
                }
                $entry['fields'][$field['label']] = $value;
            }

            $result->rows[] = $entry;
        }

        return $result;
    }

    private static function looks_like_placeholder(string $value): bool {
        $lower = strtolower($value);
        foreach (self::PLACEHOLDER_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
