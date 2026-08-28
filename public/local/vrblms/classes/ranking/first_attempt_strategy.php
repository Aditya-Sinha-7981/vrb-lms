<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * Scores each quiz using the employee's first finished attempt only,
 * ignoring any retries. Rewards first-try understanding of the material.
 */
class first_attempt_strategy extends attempt_selection_strategy {

    protected function select_attempt(array $attemptsforonequiz): \stdClass {
        $first = $attemptsforonequiz[0];
        foreach ($attemptsforonequiz as $attempt) {
            if ((int) $attempt->attempt < (int) $first->attempt) {
                $first = $attempt;
            }
        }
        return $first;
    }
}
