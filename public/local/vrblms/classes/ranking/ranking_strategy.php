<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * A ranking strategy turns one employee's raw finished quiz attempts, for
 * the quizzes belonging to a single brand, into a single comparable score.
 *
 * Implementations must not assume every quiz in $quizmeta has an attempt —
 * an employee who hasn't started (or only partially finished) their
 * brand's modules is still evaluated, just with fewer completed quizzes.
 */
interface ranking_strategy {

    /**
     * @param array $attempts finished {quiz_attempts} rows for one user,
     *        already restricted to their brand's quizzes. Each element has
     *        ->quiz, ->attempt, ->sumgrades, ->timestart, ->timefinish.
     * @param array $quizmeta quizid => {courseid, name, sumgrades, grade}
     *        for every quiz in the brand, not just attempted ones — used
     *        to compute quizzes_total and per-quiz percentages.
     * @return \stdClass {score_percent, quizzes_completed, quizzes_total,
     *         total_time_seconds}
     */
    public function evaluate(array $attempts, array $quizmeta): \stdClass;
}
