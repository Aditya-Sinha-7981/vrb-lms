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
 * Shared output shape for both Book and Quiz row validators: parsed rows
 * plus structural errors (block import) and content warnings (surfaced,
 * non-blocking). Pure PHP value object - no Moodle dependency.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validated_result {
    /** @var array<int, array<string, mixed>> Parsed, valid row data - shape depends on the caller (Book vs Quiz). */
    public array $rows = [];

    /** @var string[] Structural errors. Any entry here means import must be blocked. */
    public array $errors = [];

    /** @var string[] Content warnings. Surfaced to the admin, never blocking. */
    public array $warnings = [];

    public function add_error(string $message): void {
        $this->errors[] = $message;
    }

    public function add_warning(string $message): void {
        $this->warnings[] = $message;
    }

    public function is_blocked(): bool {
        return !empty($this->errors);
    }
}
