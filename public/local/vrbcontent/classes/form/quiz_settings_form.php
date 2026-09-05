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
 * Quiz-only step: question-bank module label (always required), plus pass
 * percentage / max attempts (only when this wizard run is creating a new
 * Quiz activity - reusing an existing one never touches its settings, see
 * quiz_configurator's docblock), plus an optional "gate this section"
 * target - never auto-discovered, an explicit section pick or "no gating
 * for now".
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_settings_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;
        $iscreating = !empty($this->_customdata['creatingnewquiz']);

        $mform->addElement('text', 'modulelabel', get_string('modulelabel', 'local_vrbcontent'), ['size' => 40]);
        $mform->setType('modulelabel', PARAM_TEXT);

        if ($iscreating) {
            $mform->addElement('text', 'passpercent', get_string('passpercent', 'local_vrbcontent'), ['size' => 5]);
            $mform->setType('passpercent', PARAM_FLOAT);
            $mform->setDefault('passpercent', 70);

            $mform->addElement('text', 'maxattempts', get_string('maxattempts', 'local_vrbcontent'), ['size' => 5]);
            $mform->setType('maxattempts', PARAM_INT);
            $mform->setDefault('maxattempts', 3);
        } else {
            $mform->addElement('static', 'existingquiznotice', '', get_string('existingquiznotice', 'local_vrbcontent'));
        }

        $mform->addElement(
            'select',
            'gatesectionnum',
            get_string('gatesection', 'local_vrbcontent'),
            $this->_customdata['sections']
        );
        $mform->setType('gatesectionnum', PARAM_INT);

        $this->add_action_buttons(true, get_string('continue'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (trim($data['modulelabel'] ?? '') === '') {
            $errors['modulelabel'] = get_string('required');
        }

        if (!empty($this->_customdata['creatingnewquiz'])) {
            $passpercent = $data['passpercent'] ?? null;
            if ($passpercent === null || $passpercent === '' || (float) $passpercent <= 0 || (float) $passpercent > 100) {
                $errors['passpercent'] = get_string('invalidpasspercent', 'local_vrbcontent');
            }
            $maxattempts = $data['maxattempts'] ?? null;
            if ($maxattempts === null || $maxattempts === '' || (int) $maxattempts < 0) {
                $errors['maxattempts'] = get_string('invalidmaxattempts', 'local_vrbcontent');
            }
        }

        return $errors;
    }
}
