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

/**
 * A Book field template: a name plus an ordered list of
 * {label, key, is_title} entries. Exactly one field has is_title=true -
 * it becomes the chapter title, every other field becomes labeled chapter
 * content. Reusable by name across courses/brands (a "Veeba Product"
 * template shouldn't need re-entering per course), persisted to
 * local_vrbcontent_template / local_vrbcontent_template_field.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template {
    /** @var int|null null for a not-yet-persisted template. */
    public ?int $id;

    /** @var string */
    public string $name;

    /** @var array<int, array{label: string, key: string, is_title: bool}> */
    public array $fields;

    /**
     * @param string $name
     * @param array<int, array{label: string, key: string, is_title: bool}> $fields
     * @param int|null $id
     */
    public function __construct(string $name, array $fields, ?int $id = null) {
        $this->id = $id;
        $this->name = $name;
        $this->fields = $fields;
    }

    /**
     * @return array{label: string, key: string, is_title: bool} the field marked is_title,
     *         or the first field if none is explicitly marked (matches the
     *         "first field is the title by default" rule).
     */
    public function title_field(): array {
        foreach ($this->fields as $field) {
            if (!empty($field['is_title'])) {
                return $field;
            }
        }
        return $this->fields[0];
    }

    /**
     * @return array<int,string> id => name, for a "reuse existing template" selector.
     */
    public static function get_all_names(): array {
        global $DB;
        return $DB->get_records_menu('local_vrbcontent_template', null, 'name ASC', 'id, name');
    }

    /**
     * @param int $id
     * @return self|null
     */
    public static function load(int $id): ?self {
        global $DB;

        $record = $DB->get_record('local_vrbcontent_template', ['id' => $id]);
        if (!$record) {
            return null;
        }

        $fieldrecords = $DB->get_records('local_vrbcontent_template_field', ['templateid' => $id], 'sortorder ASC');
        $fields = [];
        foreach ($fieldrecords as $fieldrecord) {
            $fields[] = [
                'label' => $fieldrecord->label,
                'key' => $fieldrecord->fieldkey,
                'is_title' => (bool) $fieldrecord->istitle,
            ];
        }

        return new self($record->name, $fields, (int) $record->id);
    }

    /**
     * Persist this template (insert if $id is null, otherwise replace its
     * field rows). Field-to-column mapping is by exact label (§5), so a
     * fresh definition simply replaces the field rows in full - there is no
     * per-field update-in-place case for this tool's minimal scope.
     *
     * @return int the (possibly newly assigned) template id.
     */
    public function save(): int {
        global $DB, $USER;

        $now = time();

        if ($this->id === null) {
            $record = new \stdClass();
            $record->name = $this->name;
            $record->usermodified = $USER->id;
            $record->timecreated = $now;
            $record->timemodified = $now;
            $this->id = (int) $DB->insert_record('local_vrbcontent_template', $record);
        } else {
            $DB->set_field('local_vrbcontent_template', 'name', $this->name, ['id' => $this->id]);
            $DB->set_field('local_vrbcontent_template', 'timemodified', $now, ['id' => $this->id]);
            $DB->set_field('local_vrbcontent_template', 'usermodified', $USER->id, ['id' => $this->id]);
            $DB->delete_records('local_vrbcontent_template_field', ['templateid' => $this->id]);
        }

        $sortorder = 0;
        foreach ($this->fields as $field) {
            $fieldrecord = new \stdClass();
            $fieldrecord->templateid = $this->id;
            $fieldrecord->label = $field['label'];
            $fieldrecord->fieldkey = $field['key'];
            $fieldrecord->istitle = !empty($field['is_title']) ? 1 : 0;
            $fieldrecord->sortorder = $sortorder++;
            $DB->insert_record('local_vrbcontent_template_field', $fieldrecord);
        }

        return $this->id;
    }
}
