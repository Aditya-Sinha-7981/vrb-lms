<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared base for strategies that reduce to "pick one attempt per quiz,
 * then average the percentages of the attempts picked." Best-attempt and
 * first-attempt only differ in which attempt gets picked per quiz.
 *
 * How quizzes the employee hasn't attempted yet are treated is a separate,
 * site-wide setting (see strategy_manager::get_unattempted_policy()), so it
 * applies the same way to every strategy:
 *   - 'zero'   - an unattempted quiz scores 0%, so the average is over every
 *                quiz the employee is expected to take. Someone who has done
 *                one quiz can't outrank someone who has done them all.
 *   - 'ignore' - the original behaviour: average only the quizzes attempted.
 */
abstract class attempt_selection_strategy implements ranking_strategy {

    /**
     * @param \stdClass[] $attemptsforonequiz finished attempts, all for
     *        the same quiz, for one user.
     * @return \stdClass the one attempt this strategy uses to score that quiz.
     */
    abstract protected function select_attempt(array $attemptsforonequiz): \stdClass;

    public function evaluate(array $attempts, array $quizmeta): \stdClass {
        $byquiz = [];
        foreach ($attempts as $attempt) {
            $byquiz[$attempt->quiz][] = $attempt;
        }

        // Quizzes with no max grade to compare against (e.g. misconfigured)
        // are left out of both the numerator and the denominator, rather
        // than corrupting the average with a divide-by-zero.
        $scorable = array_filter($quizmeta, fn($quiz) => (float) $quiz->sumgrades > 0);

        $percentagesum = 0.0;
        $completed = 0;
        $totaltime = 0;

        foreach ($byquiz as $quizid => $attemptsforquiz) {
            if (!isset($scorable[$quizid])) {
                continue;
            }

            $chosen = $this->select_attempt($attemptsforquiz);
            $percent = ((float) $chosen->sumgrades / (float) $scorable[$quizid]->sumgrades) * 100;

            $percentagesum += $percent;
            $completed++;
            $totaltime += max(0, (int) $chosen->timefinish - (int) $chosen->timestart);
        }

        $divisor = strategy_manager::get_unattempted_policy() === strategy_manager::UNATTEMPTED_IGNORE
            ? $completed
            : count($scorable);

        $result = new \stdClass();
        $result->score_percent = $divisor > 0 ? round($percentagesum / $divisor, 2) : 0.0;
        $result->quizzes_completed = $completed;
        $result->quizzes_total = count($quizmeta);
        $result->total_time_seconds = $totaltime;

        return $result;
    }
}
