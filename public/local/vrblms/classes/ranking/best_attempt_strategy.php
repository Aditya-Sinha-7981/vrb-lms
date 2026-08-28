<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * Scores each quiz using the employee's highest-scoring finished attempt.
 * Rewards eventual mastery, including via retries.
 */
class best_attempt_strategy extends attempt_selection_strategy {

    protected function select_attempt(array $attemptsforonequiz): \stdClass {
        $best = $attemptsforonequiz[0];
        foreach ($attemptsforonequiz as $attempt) {
            if ((float) $attempt->sumgrades > (float) $best->sumgrades) {
                $best = $attempt;
            }
        }
        return $best;
    }
}
