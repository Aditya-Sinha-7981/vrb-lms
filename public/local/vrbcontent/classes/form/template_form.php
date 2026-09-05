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

namespace local_vrbcontent\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

use local_vrbcontent\book\template;

/**
 * Step 2 of the Book import wizard (Book only): reuse an existing named
 * template, or define a new one. The first non-empty field row is always
 * the chapter title (per the brief) - no separate "which field is the
 * title" control needed. Field count is open-ended via the standard
 * moodleform repeat_elements() "Add fields" pattern, server-side only, no
 * custom JS.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        $options = [0 => get_string('definenewtemplate', 'local_vrbcontent')] + template::get_all_names();
        $mform->addElement('select', 'templateid', get_string('template', 'local_vrbcontent'), $options);
        $mform->setType('templateid', PARAM_INT);
        $mform->setDefault('templateid', 0);

        $mform->addElement('text', 'templatename', get_string('templatename', 'local_vrbcontent'), ['size' => 50]);
        $mform->setType('templatename', PARAM_TEXT);

        $mform->addElement('static', 'titlehint', '', get_string('titlehint', 'local_vrbcontent'));

        $repeatarray = [
            $mform->createElement('text', 'fieldlabel', get_string('fieldlabel', 'local_vrbcontent'), ['size' => 40]),
        ];
        $repeatoptions = [
            'fieldlabel' => ['type' => PARAM_TEXT],
        ];
        $repeatno = $this->_customdata['repeatno'] ?? 6;
        $this->repeat_elements(
            $repeatarray,
            $repeatno,
            $repeatoptions,
            'field_repeats',
            'field_add_fields',
            2,
            get_string('addfields', 'local_vrbcontent'),
            true
        );

        $this->add_action_buttons(true, get_string('continue'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if ((int) $data['templateid'] === 0) {
            if (trim($data['templatename'] ?? '') === '') {
                $errors['templatename'] = get_string('required');
            }

            $labels = array_values(array_filter(array_map('trim', $data['fieldlabel'] ?? [])));
            if (count($labels) < 2) {
                $errors['fieldlabel[0]'] = get_string('needatleasttwofields', 'local_vrbcontent');
            }

            $lower = array_map('strtolower', $labels);
            if (count($lower) !== count(array_unique($lower))) {
                $errors['fieldlabel[0]'] = get_string('duplicatefieldlabel', 'local_vrbcontent');
            }
        }

        return $errors;
    }
}
