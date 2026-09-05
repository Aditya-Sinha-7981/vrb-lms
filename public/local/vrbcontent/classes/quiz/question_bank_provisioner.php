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

use local_vrbcontent\import_batch;
use local_vrbcontent\validated_result;

/**
 * Turns validated Quiz rows into real multichoice questions, filed under a
 * `VRB Content -> <module label>` category and attached to the target
 * quiz. Question bank categories in this Moodle version are
 * CONTEXT_MODULE-only (verified Phase 0, `lib/questionlib.php:1084,1130`
 * both reject any other context level) - anchored here to the course's
 * `qbank`-type "System shared question bank" module, get-or-created via
 * `question_bank_helper`, never a course/category-context category (which
 * no longer exists in this branch).
 *
 * @package    local_vrbcontent
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_bank_provisioner {
    /** @var string The one shared top-level category name for every course this tool touches. */
    private const ROOT_CATEGORY_NAME = 'VRB Content';

    /**
     * Get-or-create the `VRB Content -> $modulelabel` category, anchored
     * to the course's qbank module context - never inferred from
     * brand/course name, always the admin-typed label.
     *
     * @param \stdClass $course
     * @param string $modulelabel
     * @return \stdClass {categoryid, contextid}
     */
    public static function get_or_create_category(\stdClass $course, string $modulelabel): \stdClass {
        global $DB;

        $qbankcm = \core_question\local\bank\question_bank_helper::get_default_open_instance_system_type($course, true);
        $qbankcontext = \context_module::instance($qbankcm->id);

        $topcategory = question_get_top_category($qbankcontext->id, true);

        $rootcategory = $DB->get_record('question_categories', [
            'contextid' => $qbankcontext->id,
            'parent' => $topcategory->id,
            'name' => self::ROOT_CATEGORY_NAME,
        ]);
        if (!$rootcategory) {
            $rootcategory = self::create_category($qbankcontext->id, (int) $topcategory->id, self::ROOT_CATEGORY_NAME);
        }

        $modulecategory = $DB->get_record('question_categories', [
            'contextid' => $qbankcontext->id,
            'parent' => $rootcategory->id,
            'name' => $modulelabel,
        ]);
        if (!$modulecategory) {
            $modulecategory = self::create_category($qbankcontext->id, (int) $rootcategory->id, $modulelabel);
        }

        return (object) ['categoryid' => (int) $modulecategory->id, 'contextid' => (int) $qbankcontext->id];
    }

    private static function create_category(int $contextid, int $parentid, string $name): \stdClass {
        global $DB;

        $record = new \stdClass();
        $record->name = $name;
        $record->contextid = $contextid;
        $record->info = '';
        $record->infoformat = FORMAT_HTML;
        $record->stamp = make_unique_id_code();
        $record->parent = $parentid;
        $record->sortorder = 999;
        $record->id = $DB->insert_record('question_categories', $record);

        return $record;
    }

    /**
     * Creates one single-answer multichoice question per validated row,
     * attaches each to the quiz, then recomputes sumgrades once for the
     * whole batch (quiz_add_quiz_question() does not do this itself -
     * verified Phase 0/2).
     *
     * @param \stdClass $categorytarget {categoryid, contextid}
     * @param \stdClass $quiz full mdl_quiz record (must have ->id, ->course; ->cmid set here to skip a lookup).
     * @param int $cmid
     * @param validated_result $result
     * @param import_batch $batch created question ids are recorded against this batch.
     * @return int[] created question ids.
     */
    public static function create_questions(
        \stdClass $categorytarget,
        \stdClass $quiz,
        int $cmid,
        validated_result $result,
        import_batch $batch
    ): array {
        global $USER;

        $quiz->cmid = $cmid;
        $qtype = \question_bank::get_qtype('multichoice');

        $createdids = [];
        foreach ($result->rows as $row) {
            $fromform = self::build_form_data($categorytarget, $row);

            $question = new \stdClass();
            $question->category = $categorytarget->categoryid;
            $question->contextid = $categorytarget->contextid;
            $question->qtype = 'multichoice';
            $question->createdby = $USER->id;
            $question->timecreated = time();
            $question->timemodified = time();
            $question->stamp = make_unique_id_code();
            $question->version = make_unique_id_code();
            $question->hidden = 0;

            $savedquestion = $qtype->save_question($question, $fromform);

            quiz_add_quiz_question((int) $savedquestion->id, $quiz, 0, 1);

            $createdids[] = (int) $savedquestion->id;
            $batch->add_item('question', (int) $savedquestion->id);
        }

        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        return $createdids;
    }

    /**
     * @param \stdClass $categorytarget {categoryid, contextid}
     * @param array{qno:string,topic:string,question:string,options:array<string,string>,correctanswer:string,explanation:string} $row
     * @return \stdClass the $fromform-shaped object save_question() expects,
     *         built from the reference shape in
     *         question/type/multichoice/tests/helper.php (verified Phase 0).
     */
    private static function build_form_data(\stdClass $categorytarget, array $row): \stdClass {
        $fromform = new \stdClass();
        $fromform->category = $categorytarget->categoryid . ',' . $categorytarget->contextid;
        $fromform->name = $row['qno'] . ': ' . $row['question'];
        $fromform->questiontext = ['text' => $row['question'], 'format' => FORMAT_HTML];
        $fromform->generalfeedback = ['text' => $row['explanation'], 'format' => FORMAT_HTML];
        $fromform->defaultmark = 1;
        $fromform->penalty = 0.3333333;
        $fromform->qtype = 'multichoice';
        $fromform->single = 1;
        $fromform->shuffleanswers = 1;
        $fromform->answernumbering = 'abc';
        $fromform->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $fromform->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $fromform->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $fromform->shownumcorrect = 1;
        $fromform->showstandardinstruction = 0;

        $answers = [];
        $fractions = [];
        $feedback = [];
        foreach ($row['options'] as $letter => $text) {
            $answers[] = ['text' => $text, 'format' => FORMAT_PLAIN];
            $fractions[] = ($letter === $row['correctanswer']) ? '1.0' : '0.0';
            $feedback[] = ['text' => '', 'format' => FORMAT_HTML];
        }
        $fromform->answer = $answers;
        $fromform->fraction = $fractions;
        $fromform->feedback = $feedback;

        return $fromform;
    }

    /**
     * Deletes questions created by a prior import batch (the "Re-import:
     * replace" path). A question still attached to a quiz is "in use", so
     * question_delete_question() alone would only hide it
     * (verified Phase 3, lib/questionlib.php:371 - core deliberately never
     * hard-deletes an in-use question) - so the slot is detached first via
     * mod_quiz\structure::remove_slot(), the same real API the quiz editing
     * UI uses, before calling question_delete_question().
     *
     * @param int $quizid
     * @param int $cmid
     * @param int[] $questionids
     */
    public static function delete_questions(int $quizid, int $cmid, array $questionids): void {
        global $DB;

        if (empty($questionids)) {
            return;
        }

        $quizsettings = \mod_quiz\quiz_settings::create($quizid);
        $structure = \mod_quiz\structure::create_for_quiz($quizsettings);
        $context = \context_module::instance($cmid);

        foreach ($questionids as $questionid) {
            $slot = $DB->get_record_sql(
                "SELECT qs.slot
                   FROM {quiz_slots} qs
                   JOIN {question_references} qr ON qr.itemid = qs.id
                        AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                   JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  WHERE qs.quizid = :quizid AND qv.questionid = :questionid AND qr.usingcontextid = :contextid",
                ['quizid' => $quizid, 'questionid' => $questionid, 'contextid' => $context->id]
            );
            if ($slot) {
                $structure->remove_slot((int) $slot->slot);
            }
            question_delete_question($questionid);
        }

        $quizsettings->get_grade_calculator()->recompute_quiz_sumgrades();
    }
}
