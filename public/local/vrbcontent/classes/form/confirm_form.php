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
 * Final step: preview (rendered by the controller before this form, not
 * inside it) + confirm. The Confirm button is entirely absent whenever a
 * structural error exists for the current file - not just disabled - per
 * the plan's hard UI requirement. When a prior import of this exact
 * content already exists for the target, a Skip/Replace/Cancel radio
 * choice is shown instead of going straight to Confirm.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class confirm_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;
        $blocked = !empty($this->_customdata['blocked']);
        $duplicate = !empty($this->_customdata['duplicate']);

        if ($blocked) {
            // No Confirm button at all - the admin must go back and fix the source file.
            $mform->addElement('static', 'blockednotice', '', get_string('blockednotice', 'local_vrbcontent'));
            $cancelurl = $this->_customdata['cancelurl'];
            $mform->addElement(
                'static',
                'backlink',
                '',
                \html_writer::link($cancelurl, get_string('backtostart', 'local_vrbcontent'))
            );
            return;
        }

        if ($duplicate) {
            $mform->addElement(
                'static',
                'duplicatenotice',
                '',
                get_string('duplicatenotice', 'local_vrbcontent', $this->_customdata['priordate'])
            );

            $radiogroup = [
                $mform->createElement('radio', 'duplicateaction', '', get_string('actionskip', 'local_vrbcontent'), 'skip'),
                $mform->createElement('radio', 'duplicateaction', '', get_string('actionreplace', 'local_vrbcontent'), 'replace'),
                $mform->createElement('radio', 'duplicateaction', '', get_string('actioncancel', 'local_vrbcontent'), 'cancel'),
            ];
            $mform->addGroup(
                $radiogroup,
                'duplicateactiongroup',
                get_string('duplicateaction', 'local_vrbcontent'),
                ['<br>'],
                false
            );
            $mform->setDefault('duplicateaction', 'skip');
        }

        $this->add_action_buttons(true, get_string('confirmimport', 'local_vrbcontent'));
    }
}
