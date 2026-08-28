<?php
namespace local_vrblms;

use local_vrblms\local\attempt_repository;
use local_vrblms\ranking\strategy_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Public service API for local_vrblms. This is the only class other
 * plugins (block_vrblms_leaderboard, report_vrblms, and the eventual
 * certificate-qualification task) should call into - none of them should
 * duplicate the query/ranking logic behind it.
 */
class api {

    /**
     * @return \stdClass[] {idnumber, name} for every Brand cohort, for a
     *         Brand filter dropdown.
     */
    public static function get_brands(): array {
        $cohorts = attempt_repository::get_brand_cohorts();
        $brands = [];
        foreach ($cohorts as $cohort) {
            $brand = new \stdClass();
            $brand->idnumber = $cohort->idnumber;
            $brand->name = $cohort->name;
            $brands[] = $brand;
        }
        return $brands;
    }

    /**
     * @param string $brandidnumber e.g. 'brand_veeba'.
     * @return string[] distinct states among that brand's employees.
     */
    public static function get_states(string $brandidnumber): array {
        $cohort = attempt_repository::get_cohort_by_idnumber($brandidnumber);
        if ($cohort === null) {
            return [];
        }
        return attempt_repository::get_distinct_states((int) $cohort->id);
    }

    /**
     * @param string $brandidnumber e.g. 'brand_veeba'.
     * @param string|null $state optionally narrow to one state first.
     * @return string[] distinct cities among that brand's (optionally state-filtered) employees.
     */
    public static function get_cities(string $brandidnumber, ?string $state = null): array {
        $cohort = attempt_repository::get_cohort_by_idnumber($brandidnumber);
        if ($cohort === null) {
            return [];
        }
        return attempt_repository::get_distinct_cities((int) $cohort->id, $state);
    }

    /**
     * The core query: Brand (+ optional State/City) -> ranked employees.
     *
     * @param string $brandidnumber e.g. 'brand_veeba'.
     * @param string|null $state optional State filter.
     * @param string|null $city optional City filter.
     * @param string|null $strategykey 'best'|'first'|null (site default).
     * @param int|null $limit cap on the number of rows returned, applied
     *        after ranking (e.g. "Top 10"); null returns everyone.
     * @return \stdClass[] ranked rows: rank, userid, fullname, idnumber,
     *         state, city, region, score_percent, quizzes_completed,
     *         quizzes_total, total_time_seconds.
     */
    public static function get_leaderboard(
        string $brandidnumber,
        ?string $state = null,
        ?string $city = null,
        ?string $strategykey = null,
        ?int $limit = null
    ): array {
        $cohort = attempt_repository::get_cohort_by_idnumber($brandidnumber);
        if ($cohort === null) {
            return [];
        }

        $courseids = attempt_repository::get_courses_for_cohort((int) $cohort->id);
        $quizzes = attempt_repository::get_quizzes_for_courses($courseids);
        $userids = attempt_repository::get_cohort_members((int) $cohort->id, $state, $city);

        return self::build_ranked_rows($userids, $quizzes, $strategykey, $limit);
    }

    /**
     * Distinct states across employees of ANY brand - for the admin
     * "Overall (all brands)" view's State filter dropdown.
     *
     * @return string[]
     */
    public static function get_overall_states(): array {
        return attempt_repository::get_distinct_states_all_brands();
    }

    /**
     * Distinct cities across employees of ANY brand, optionally narrowed
     * to one state - for the admin "Overall (all brands)" view's City
     * filter dropdown.
     *
     * @param string|null $state
     * @return string[]
     */
    public static function get_overall_cities(?string $state = null): array {
        return attempt_repository::get_distinct_cities_all_brands($state);
    }

    /**
     * The cross-brand "Overall" leaderboard: one row per employee,
     * combining their attempts across every brand course they've taken
     * (not just one brand, and not restricted to employees who share the
     * same brand combination) - so a multi-brand employee's overall score
     * reflects their whole training record, and a single-brand employee's
     * overall score is identical to their one brand's score.
     *
     * @param string|null $state optional State filter.
     * @param string|null $city optional City filter.
     * @param string|null $strategykey 'best'|'first'|null (site default).
     * @param int|null $limit cap on the number of rows returned.
     * @return \stdClass[] same row shape as get_leaderboard().
     */
    public static function get_overall_leaderboard(
        ?string $state = null,
        ?string $city = null,
        ?string $strategykey = null,
        ?int $limit = null
    ): array {
        $quizzes = attempt_repository::get_all_brand_quizzes();
        $userids = attempt_repository::get_all_brand_members($state, $city);

        return self::build_ranked_rows($userids, $quizzes, $strategykey, $limit);
    }

    /**
     * @param int $userid
     * @return \stdClass[] {idnumber, name} for every Brand cohort this
     *         user belongs to - powers an employee's own "which course(s)
     *         am I on" leaderboard sections.
     */
    public static function get_user_brands(int $userid): array {
        $cohorts = attempt_repository::get_user_brand_cohorts($userid);
        $brands = [];
        foreach ($cohorts as $cohort) {
            $brand = new \stdClass();
            $brand->idnumber = $cohort->idnumber;
            $brand->name = $cohort->name;
            $brands[] = $brand;
        }
        return $brands;
    }

    /**
     * Shared scoring/sorting/ranking pipeline for get_leaderboard() and
     * get_overall_leaderboard() - identical once you have a member list
     * and a quiz set, regardless of whether that came from one brand
     * cohort or the union of all of them.
     *
     * @param int[] $userids
     * @param \stdClass[] $quizzes quizid => {courseid, name, sumgrades, grade}
     * @param string|null $strategykey
     * @param int|null $limit
     * @return \stdClass[]
     */
    protected static function build_ranked_rows(array $userids, array $quizzes, ?string $strategykey, ?int $limit): array {
        if (empty($userids)) {
            return [];
        }

        $quizids = array_keys($quizzes);
        $attempts = attempt_repository::get_finished_attempts($quizids, $userids);
        $directory = attempt_repository::get_employee_directory($userids);

        $attemptsbyuser = [];
        foreach ($attempts as $attempt) {
            $attemptsbyuser[$attempt->userid][] = $attempt;
        }

        $strategy = strategy_manager::get_strategy($strategykey);

        $rows = [];
        foreach ($userids as $userid) {
            if (!isset($directory[$userid])) {
                // Directory lookup and membership lookup are separate queries -
                // skip defensively rather than fatal if a user record vanished
                // between the two (e.g. deleted mid-request).
                continue;
            }
            $employee = $directory[$userid];
            $userattempts = $attemptsbyuser[$userid] ?? [];

            $evaluation = $strategy->evaluate($userattempts, $quizzes);

            $row = new \stdClass();
            $row->userid = $userid;
            $row->fullname = fullname($employee);
            $row->idnumber = $employee->idnumber;
            $row->state = $employee->state;
            $row->city = $employee->city;
            $row->region = $employee->region;
            $row->score_percent = $evaluation->score_percent;
            $row->quizzes_completed = $evaluation->quizzes_completed;
            $row->quizzes_total = $evaluation->quizzes_total;
            $row->total_time_seconds = $evaluation->total_time_seconds;

            $rows[] = $row;
        }

        usort($rows, function (\stdClass $a, \stdClass $b) {
            if ($a->score_percent !== $b->score_percent) {
                return $b->score_percent <=> $a->score_percent;
            }
            if ($a->total_time_seconds !== $b->total_time_seconds) {
                // Faster completion wins the tie, but only among those who
                // completed at least one quiz - two zero-attempt employees
                // both have 0 seconds and should tie-break on name instead.
                if ($a->quizzes_completed > 0 && $b->quizzes_completed > 0) {
                    return $a->total_time_seconds <=> $b->total_time_seconds;
                }
            }
            return strcasecmp($a->fullname, $b->fullname);
        });

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        $rank = 1;
        foreach ($rows as $row) {
            $row->rank = $rank++;
        }

        return $rows;
    }
}
