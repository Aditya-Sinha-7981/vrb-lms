<?php
namespace local_vrblms\ranking;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared base for strategies that reduce to "pick one attempt per quiz,
 * then average the percentages of the attempts picked." Best-attempt and
 * first-attempt only differ in which attempt gets picked per quiz.
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

        $percentagesum = 0.0;
        $completed = 0;
        $totaltime = 0;

        foreach ($byquiz as $quizid => $attemptsforquiz) {
            if (!isset($quizmeta[$quizid]) || (float) $quizmeta[$quizid]->sumgrades <= 0) {
                // Quiz has no max grade to compare against (e.g. misconfigured) - skip it
                // rather than let it corrupt the average with a divide-by-zero.
                continue;
            }

            $chosen = $this->select_attempt($attemptsforquiz);
            $percent = ((float) $chosen->sumgrades / (float) $quizmeta[$quizid]->sumgrades) * 100;

            $percentagesum += $percent;
            $completed++;
            $totaltime += max(0, (int) $chosen->timefinish - (int) $chosen->timestart);
        }

        $result = new \stdClass();
        $result->score_percent = $completed > 0 ? round($percentagesum / $completed, 2) : 0.0;
        $result->quizzes_completed = $completed;
        $result->quizzes_total = count($quizmeta);
        $result->total_time_seconds = $totaltime;

        return $result;
    }
}
