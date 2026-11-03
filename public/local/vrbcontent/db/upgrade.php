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

/**
 * Upgrade steps for local_vrbcontent.
 *
 * @package    local_vrbcontent
 * @copyright  2026 Webnoah
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade local_vrbcontent.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_vrbcontent_upgrade($oldversion) {
    global $CFG, $DB;

    if ($oldversion < 2026110300) {
        // Client request: show every quiz question on one scrollable page
        // instead of one question per page with repeated "Next".
        //
        // 1. Site default for quizzes created later through the normal UI.
        set_config('questionsperpage', 0, 'quiz');

        // 2. Existing quizzes: all slots onto page 1 (quiz sections still
        // force their own page break, same as core's repaginate).
        // Core's edit screen refuses to repaginate once a quiz has attempts;
        // that guard is about the edit UI, not data safety - each attempt
        // keeps its own page layout in quiz_attempts.layout
        // (mod_quiz\quiz_attempt reads pages from it), and reports/regrades
        // are slot-based. So existing/in-progress attempts are unaffected;
        // only new attempts get the single-page layout.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quizids = $DB->get_fieldset_select('quiz', 'id', '1 = 1');
        foreach ($quizids as $quizid) {
            $DB->set_field('quiz', 'questionsperpage', 0, ['id' => $quizid]);
            quiz_repaginate_questions($quizid, 0);
        }

        upgrade_plugin_savepoint(true, 2026110300, 'local', 'vrbcontent');
    }

    if ($oldversion < 2026110301) {
        // Attempts already in progress keep the page layout they started
        // with (quiz_attempts.layout), so after the step above they would
        // still show one question per page until submitted. Collapse their
        // layout to a single page too: same slots in the same order (keeps
        // any shuffle), only the ",0" page breaks between them removed, and
        // currentpage reset to that one page. Only for quizzes with a
        // single quiz section - a section boundary must keep its page
        // break, so multi-section quizzes are left as they are.
        // Finished attempts are never touched (their review keeps the
        // layout they were taken with).
        $sql = "SELECT a.id, a.layout
                  FROM {quiz_attempts} a
                 WHERE a.state IN ('inprogress', 'overdue')
                   AND (SELECT COUNT(1) FROM {quiz_sections} s WHERE s.quizid = a.quiz) = 1";
        $attempts = $DB->get_records_sql($sql);
        foreach ($attempts as $attempt) {
            $slots = array_values(array_filter(explode(',', $attempt->layout),
                fn($slot) => $slot !== '' && $slot !== '0'));
            if (!$slots) {
                continue;
            }
            $DB->update_record('quiz_attempts', (object) [
                'id' => $attempt->id,
                'layout' => implode(',', $slots) . ',0',
                'currentpage' => 0,
            ]);
        }

        upgrade_plugin_savepoint(true, 2026110301, 'local', 'vrbcontent');
    }

    return true;
}
