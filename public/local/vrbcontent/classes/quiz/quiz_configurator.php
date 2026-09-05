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

namespace local_vrbcontent\quiz;

/**
 * Creates/selects the target Quiz activity and, on creation only, applies
 * this project's exact verified gating recipe (ARCHITECTURE.md's "Quiz
 * gating" section): completionusegrade + completionpassgrade + gradepass,
 * plus max attempts. Also writes the Restrict Access condition on a
 * separately, explicitly admin-chosen "next" section.
 *
 * Deliberate scope boundary: gating (attempts/completion/gradepass) is
 * only ever set when THIS tool creates the quiz. Reusing an existing quiz
 * activity only attaches questions - it never reconfigures its settings.
 * This is not laziness: `quiz_update_instance()` unconditionally
 * recomputes `reviewattempt`/`reviewcorrectness`/etc. from virtual
 * form-checkbox fields (`quiz_review_option_form_to_db()`,
 * `mod/quiz/lib.php`) that a programmatic caller has no way to populate
 * correctly from an existing row, so reconfiguring an existing quiz this
 * way would silently reset its review-option display settings. Restrict
 * Access, by contrast, only touches the *section's* availability column
 * and is always safe to apply regardless of new-vs-existing quiz.
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_configurator {
    /**
     * @param \stdClass $course
     * @param int $sectionnum
     * @param int $cmid > 0 to use an existing Quiz activity, 0 to create one.
     * @param string $newquiztitle used only when $cmid === 0.
     * @param float|null $passpercent out of 100; applied only when $cmid === 0.
     * @param int|null $maxattempts applied only when $cmid === 0 (0 means unlimited, matching mdl_quiz.attempts).
     * @return \stdClass {cmid, quizid, created}
     */
    public static function get_or_create_quiz(
        \stdClass $course,
        int $sectionnum,
        int $cmid,
        string $newquiztitle,
        ?float $passpercent,
        ?int $maxattempts
    ): \stdClass {
        global $CFG;

        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('quiz', $cmid, $course->id, false, MUST_EXIST);
            return (object) ['cmid' => (int) $cm->id, 'quizid' => (int) $cm->instance, 'created' => false];
        }

        require_once($CFG->dirroot . '/course/lib.php');

        // Completion tracking is a course-level feature switch
        // (course.enablecompletion) - if it's off, add_moduleinfo() silently
        // accepts completion fields but they never take effect (verified
        // Phase 3: cm->completion stayed 0 despite being set on $moduleinfo
        // until this was turned on). Gating is meaningless without it, so
        // turn it on for the course whenever gating is requested.
        if ($passpercent !== null && empty($course->enablecompletion)) {
            $coursedata = new \stdClass();
            $coursedata->id = $course->id;
            $coursedata->enablecompletion = 1;
            update_course($coursedata);
            $course->enablecompletion = 1;
        }

        // Every mdl_quiz NOT NULL column with no schema DEFAULT (verified
        // Phase 0/2, mod/quiz/db/install.xml): name, intro/introeditor,
        // preferredbehaviour, quizpassword (-> password), subnet,
        // browsersecurity - create_module() bypasses mod_form's defaults
        // entirely, so these must be supplied explicitly.
        $quizdata = new \stdClass();
        $quizdata->course = $course->id;
        $quizdata->modulename = 'quiz';
        $quizdata->section = $sectionnum;
        $quizdata->visible = 1;
        $quizdata->name = $newquiztitle;
        $quizdata->introeditor = ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0];
        $quizdata->grade = 100;
        $quizdata->preferredbehaviour = 'deferredfeedback';
        $quizdata->quizpassword = '';
        $quizdata->subnet = '';
        $quizdata->browsersecurity = '';

        if ($passpercent !== null) {
            $quizdata->attempts = $maxattempts ?? 0;
            $quizdata->completion = COMPLETION_TRACKING_AUTOMATIC;
            $quizdata->completionusegrade = 1;
            $quizdata->completionpassgrade = 1;
            // The completiongradeitemnumber gotcha (LOG.md, Phase 2): if this
            // isn't set, completionpassgrade gets silently forced back to 0.
            // 0 is the quiz's only grade item (itemnumber 0).
            $quizdata->completiongradeitemnumber = 0;
            // edit_module_post_actions() (course/modlib.php) reads a plain
            // 'gradepass' field for mod_quiz's itemnumber-0 grade item -
            // verified via component_gradeitems::get_field_name_for_itemnumber()
            // resolving to the bare fieldname (no suffix) for itemnumber 0.
            $quizdata->gradepass = $passpercent;
        }

        $quizdata = create_module($quizdata);

        return (object) ['cmid' => (int) $quizdata->coursemodule, 'quizid' => (int) $quizdata->instance, 'created' => true];
    }

    /**
     * Writes the Restrict Access condition on the section that should stay
     * locked behind $gatecmid's completion+pass state. Reuses
     * ARCHITECTURE.md's exact verified JSON shape and the documented
     * course_update_section() API (never a raw course_sections write) -
     * only ever touches the section explicitly chosen by the admin.
     *
     * @param \stdClass $course
     * @param int $gatecmid the quiz that must be completed-and-passed.
     * @param int $targetsectionnum the section to restrict.
     */
    public static function set_gating(\stdClass $course, int $gatecmid, int $targetsectionnum): void {
        $modinfo = get_fast_modinfo($course);
        $sectioninfo = $modinfo->get_section_info($targetsectionnum);

        $availability = json_encode([
            'op' => '&',
            'c' => [
                ['type' => 'completion', 'cm' => $gatecmid, 'e' => COMPLETION_COMPLETE_PASS],
            ],
            // Shown/greyed-out, not hidden - the lock reason must stay
            // visible to the learner (ARCHITECTURE.md's Phase 2 finding).
            'showc' => [true],
        ]);

        course_update_section($course, $sectioninfo, ['availability' => $availability]);
    }
}
