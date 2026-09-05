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
 * Step 1 of both the Book and Quiz import wizards, rendered in three
 * stages via $customdata['stage'] so one class covers "pick a course",
 * then either "pick a section + existing-or-new Book activity" or "pick a
 * section + existing-or-new Quiz activity" - no automatic discovery, the
 * admin picks every level manually.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class select_destination_form extends \moodleform {
    protected function definition() {
        $mform = $this->_form;
        $stage = $this->_customdata['stage'];

        if ($stage === 'course') {
            $mform->addElement(
                'select',
                'courseid',
                get_string('selectcourse', 'local_vrbcontent'),
                $this->_customdata['courses']
            );
            $mform->setType('courseid', PARAM_INT);
            $this->add_action_buttons(true, get_string('continue'));
            return;
        }

        // Stage 'target' (Book) or 'quiztarget' (Quiz): section + existing-or-new
        // activity, for the course chosen in stage 'course'.
        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement(
            'select',
            'sectionnum',
            get_string('selectsection', 'local_vrbcontent'),
            $this->_customdata['sections']
        );
        $mform->setType('sectionnum', PARAM_INT);

        if ($stage === 'quiztarget') {
            $quizoptions = [0 => get_string('createnewquiz', 'local_vrbcontent')] + $this->_customdata['quizzes'];
            $mform->addElement('select', 'cmid', get_string('selectquiz', 'local_vrbcontent'), $quizoptions);
            $mform->setType('cmid', PARAM_INT);

            $mform->addElement('text', 'newquiztitle', get_string('newquiztitle', 'local_vrbcontent'), ['size' => 50]);
            $mform->setType('newquiztitle', PARAM_TEXT);
        } else {
            $bookoptions = [0 => get_string('createnewbook', 'local_vrbcontent')] + $this->_customdata['books'];
            $mform->addElement('select', 'cmid', get_string('selectbook', 'local_vrbcontent'), $bookoptions);
            $mform->setType('cmid', PARAM_INT);

            $mform->addElement('text', 'newbooktitle', get_string('newbooktitle', 'local_vrbcontent'), ['size' => 50]);
            $mform->setType('newbooktitle', PARAM_TEXT);
        }

        $this->add_action_buttons(true, get_string('continue'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $stage = $this->_customdata['stage'] ?? '';

        if ($stage === 'quiztarget' && (int) $data['cmid'] === 0 && trim($data['newquiztitle'] ?? '') === '') {
            $errors['newquiztitle'] = get_string('required');
        }
        if ($stage === 'target' && (int) $data['cmid'] === 0 && trim($data['newbooktitle'] ?? '') === '') {
            $errors['newbooktitle'] = get_string('required');
        }

        return $errors;
    }
}
