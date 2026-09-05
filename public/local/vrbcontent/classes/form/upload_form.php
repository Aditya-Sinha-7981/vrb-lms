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

/**
 * Step 3 (Book) / step 2 (Quiz): CSV upload. CSV-only for v1 - no XLSX
 * dependency, per the plan's Composer/hosting-constraint rationale.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'local_vrbcontent'), null, [
            'accepted_types' => ['.csv'],
            'maxbytes' => 5 * 1024 * 1024,
        ]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('continue'));
    }
}
