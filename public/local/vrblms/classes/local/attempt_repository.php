<?php
namespace local_vrblms\local;

defined('MOODLE_INTERNAL') || die();

/**
 * All raw SQL for local_vrblms lives here, isolated from the ranking and
 * orchestration layers. Every query here reads documented core tables
 * only ({cohort}, {cohort_members}, {enrol}, {quiz}, {quiz_attempts},
 * {user}, {user_info_data}, {user_info_field}) - no local_vrblms tables
 * exist, this plugin is read-only against existing Moodle data.
 *
 * This exists as a separate class (rather than raw SQL inline in api.php)
 * specifically so the "no pre-joined API exists for this" boundary
 * documented in ARCHITECTURE.md is contained in one obvious place.
 */
class attempt_repository {

    /** @var array shortname => fieldid cache, populated on first lookup. */
    protected static $profilefieldids = [];

    /**
     * @return \stdClass[] cohorts modelling a Brand, keyed by id.
     */
    public static function get_brand_cohorts(): array {
        global $DB;
        return $DB->get_records_select('cohort', $DB->sql_like('idnumber', '?'),
                ['brand_%'], 'name ASC', 'id, name, idnumber');
    }

    /**
     * @param string $idnumber e.g. 'brand_veeba'.
     */
    public static function get_cohort_by_idnumber(string $idnumber): ?\stdClass {
        global $DB;
        return $DB->get_record('cohort', ['idnumber' => $idnumber]) ?: null;
    }

    /**
     * Courses a brand cohort grants access to, via its enrol_cohort
     * instance(s) - this is how Brand->Course is actually wired (see
     * ARCHITECTURE.md's "Brand-gated enrolment" section), not a naming
     * convention.
     *
     * @return int[] course ids.
     */
    public static function get_courses_for_cohort(int $cohortid): array {
        global $DB;
        return array_values($DB->get_fieldset_select('enrol', 'courseid',
                'enrol = ? AND customint1 = ?', ['cohort', $cohortid]));
    }

    /**
     * @param int[] $courseids
     * @return \stdClass[] quizid => {id, courseid, name, sumgrades, grade}
     */
    public static function get_quizzes_for_courses(array $courseids): array {
        global $DB;
        if (empty($courseids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($courseids);
        return $DB->get_records_select('quiz', "course $insql", $params,
                'id ASC', 'id, course AS courseid, name, sumgrades, grade');
    }

    /**
     * Userids belonging to a brand cohort, optionally narrowed by state
     * and/or city. Filtering happens in SQL rather than in PHP after
     * fetching everyone, since this is meant to back UI-facing filters on
     * what could be a much larger roster than today's test data.
     */
    public static function get_cohort_members(int $cohortid, ?string $state = null, ?string $city = null): array {
        global $DB;

        // $joins is concatenated into the SQL text BEFORE $wheres, so its
        // placeholders must precede the WHERE clause's in the params array
        // too - tracked separately and merged join-first below, rather
        // than appended to a single $params array in code-execution order
        // (which does not match the resulting SQL text's left-to-right
        // placeholder order and silently mis-binds values).
        $joins = '';
        $joinparams = [];
        $wheres = ['cm.cohortid = ?'];
        $whereparams = [$cohortid];

        if ($state !== null && $state !== '') {
            $statefieldid = self::get_profile_field_id('state');
            if ($statefieldid !== null) {
                $joins .= ' JOIN {user_info_data} suid ON suid.userid = cm.userid AND suid.fieldid = ?';
                $joinparams[] = $statefieldid;
                $wheres[] = 'suid.data = ?';
                $whereparams[] = $state;
            } else {
                // The state field doesn't exist in this install - no member can match it.
                return [];
            }
        }

        if ($city !== null && $city !== '') {
            $joins .= ' JOIN {user} cu ON cu.id = cm.userid';
            $wheres[] = 'cu.city = ?';
            $whereparams[] = $city;
        }

        $sql = "SELECT cm.userid
                  FROM {cohort_members} cm
                       $joins
                 WHERE " . implode(' AND ', $wheres);

        return array_values($DB->get_fieldset_sql($sql, array_merge($joinparams, $whereparams)));
    }

    /**
     * Every brand_% cohort a specific user belongs to - powers an
     * employee's own "which course(s) am I on" leaderboard sections.
     *
     * @return \stdClass[] cohorts {id, name, idnumber}, keyed by id.
     */
    public static function get_user_brand_cohorts(int $userid): array {
        global $DB;
        $sql = "SELECT c.id, c.name, c.idnumber
                  FROM {cohort} c
                  JOIN {cohort_members} cm ON cm.cohortid = c.id
                 WHERE cm.userid = ? AND " . $DB->sql_like('c.idnumber', '?') . "
              ORDER BY c.name ASC";
        return $DB->get_records_sql($sql, [$userid, 'brand_%']);
    }

    /**
     * Userids belonging to ANY brand cohort (union across all brands, not
     * one) - the cross-brand "Overall" leaderboard's member set. Same
     * state/city filtering shape as get_cohort_members(), just with the
     * single-cohort match swapped for a brand-cohort subquery.
     */
    public static function get_all_brand_members(?string $state = null, ?string $city = null): array {
        global $DB;

        // See get_cohort_members() for why join/where params are tracked
        // and merged separately (join placeholders precede WHERE
        // placeholders in the final SQL text).
        $joins = '';
        $joinparams = [];
        $wheres = ["cm.cohortid IN (SELECT id FROM {cohort} WHERE " . $DB->sql_like('idnumber', '?') . ')'];
        $whereparams = ['brand_%'];

        if ($state !== null && $state !== '') {
            $statefieldid = self::get_profile_field_id('state');
            if ($statefieldid === null) {
                return [];
            }
            $joins .= ' JOIN {user_info_data} suid ON suid.userid = cm.userid AND suid.fieldid = ?';
            $joinparams[] = $statefieldid;
            $wheres[] = 'suid.data = ?';
            $whereparams[] = $state;
        }

        if ($city !== null && $city !== '') {
            $joins .= ' JOIN {user} cu ON cu.id = cm.userid';
            $wheres[] = 'cu.city = ?';
            $whereparams[] = $city;
        }

        $sql = "SELECT DISTINCT cm.userid
                  FROM {cohort_members} cm
                       $joins
                 WHERE " . implode(' AND ', $wheres);

        return array_values($DB->get_fieldset_sql($sql, array_merge($joinparams, $whereparams)));
    }

    /**
     * Quizzes across every brand's course(s) combined - the cross-brand
     * "Overall" leaderboard's quiz set. Reuses get_courses_for_cohort()
     * and get_quizzes_for_courses() rather than a new query shape.
     */
    public static function get_all_brand_quizzes(): array {
        $courseids = [];
        foreach (self::get_brand_cohorts() as $cohort) {
            $courseids = array_merge($courseids, self::get_courses_for_cohort((int) $cohort->id));
        }
        return self::get_quizzes_for_courses(array_values(array_unique($courseids)));
    }

    /**
     * Distinct, non-blank states among members of ANY brand cohort - backs
     * the admin "Overall" view's State filter dropdown.
     */
    public static function get_distinct_states_all_brands(): array {
        global $DB;
        $statefieldid = self::get_profile_field_id('state');
        if ($statefieldid === null) {
            return [];
        }
        $sql = "SELECT DISTINCT uid.data
                  FROM {cohort_members} cm
                  JOIN {user_info_data} uid ON uid.userid = cm.userid AND uid.fieldid = ?
                 WHERE cm.cohortid IN (SELECT id FROM {cohort} WHERE " . $DB->sql_like('idnumber', '?') . ")
                   AND uid.data <> ''
              ORDER BY uid.data ASC";
        return array_values($DB->get_fieldset_sql($sql, [$statefieldid, 'brand_%']));
    }

    /**
     * Distinct, non-blank cities among members of ANY brand cohort,
     * optionally narrowed to one state - backs the admin "Overall" view's
     * City filter dropdown.
     */
    public static function get_distinct_cities_all_brands(?string $state = null): array {
        global $DB;

        // See get_cohort_members() for why join/where params are tracked
        // and merged separately.
        $joins = '';
        $joinparams = [];
        $wheres = [
            "cm.cohortid IN (SELECT id FROM {cohort} WHERE " . $DB->sql_like('idnumber', '?') . ')',
            "u.city <> ''",
        ];
        $whereparams = ['brand_%'];

        if ($state !== null && $state !== '') {
            $statefieldid = self::get_profile_field_id('state');
            if ($statefieldid === null) {
                return [];
            }
            $joins .= ' JOIN {user_info_data} suid ON suid.userid = cm.userid AND suid.fieldid = ?';
            $joinparams[] = $statefieldid;
            $wheres[] = 'suid.data = ?';
            $whereparams[] = $state;
        }

        $sql = "SELECT DISTINCT u.city
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                       $joins
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY u.city ASC";

        return array_values($DB->get_fieldset_sql($sql, array_merge($joinparams, $whereparams)));
    }

    /**
     * Distinct, non-blank states among a brand cohort's members - backs
     * the leaderboard's State filter dropdown.
     */
    public static function get_distinct_states(int $cohortid): array {
        global $DB;
        $statefieldid = self::get_profile_field_id('state');
        if ($statefieldid === null) {
            return [];
        }
        $sql = "SELECT DISTINCT uid.data
                  FROM {cohort_members} cm
                  JOIN {user_info_data} uid ON uid.userid = cm.userid AND uid.fieldid = ?
                 WHERE cm.cohortid = ? AND uid.data <> ''
              ORDER BY uid.data ASC";
        return array_values($DB->get_fieldset_sql($sql, [$statefieldid, $cohortid]));
    }

    /**
     * Distinct, non-blank cities among a brand cohort's members, optionally
     * narrowed to one state - backs the leaderboard's City filter dropdown.
     */
    public static function get_distinct_cities(int $cohortid, ?string $state = null): array {
        global $DB;

        // See get_cohort_members() for why join/where params are tracked
        // and merged separately.
        $joins = '';
        $joinparams = [];
        $wheres = ['cm.cohortid = ?', "u.city <> ''"];
        $whereparams = [$cohortid];

        if ($state !== null && $state !== '') {
            $statefieldid = self::get_profile_field_id('state');
            if ($statefieldid === null) {
                return [];
            }
            $joins .= ' JOIN {user_info_data} suid ON suid.userid = cm.userid AND suid.fieldid = ?';
            $joinparams[] = $statefieldid;
            $wheres[] = 'suid.data = ?';
            $whereparams[] = $state;
        }

        $sql = "SELECT DISTINCT u.city
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                       $joins
                 WHERE " . implode(' AND ', $wheres) . "
              ORDER BY u.city ASC";

        return array_values($DB->get_fieldset_sql($sql, array_merge($joinparams, $whereparams)));
    }

    /**
     * Finished attempts only - an in-progress or abandoned attempt has no
     * settled sumgrades and shouldn't factor into a leaderboard.
     *
     * @param int[] $quizids
     * @param int[] $userids
     * @return \stdClass[] rows: id, quiz, userid, attempt, sumgrades, timestart, timefinish.
     */
    public static function get_finished_attempts(array $quizids, array $userids): array {
        global $DB;
        if (empty($quizids) || empty($userids)) {
            return [];
        }
        [$quizinsql, $quizparams] = $DB->get_in_or_equal($quizids);
        [$userinsql, $userparams] = $DB->get_in_or_equal($userids);

        $sql = "SELECT id, quiz, userid, attempt, sumgrades, timestart, timefinish
                  FROM {quiz_attempts}
                 WHERE state = ?
                   AND quiz $quizinsql
                   AND userid $userinsql";
        $params = array_merge(['finished'], $quizparams, $userparams);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Bulk profile lookup for a set of users - deliberately not
     * profile_user_record(), which loads one user at a time and would
     * mean N queries for an N-employee leaderboard.
     *
     * @param int[] $userids
     * @return \stdClass[] userid => {id, firstname, lastname, idnumber, city, state, region}
     */
    public static function get_employee_directory(array $userids): array {
        global $DB;
        if (empty($userids)) {
            return [];
        }

        $statefieldid = self::get_profile_field_id('state');
        $regionfieldid = self::get_profile_field_id('region');
        $profilefieldids = array_values(array_filter([$statefieldid, $regionfieldid], fn($id) => $id !== null));

        [$userinsql, $userparams] = $DB->get_in_or_equal($userids);

        if (!empty($profilefieldids)) {
            [$fieldinsql, $fieldparams] = $DB->get_in_or_equal($profilefieldids);
            // Fetch user rows and profile rows separately and join in PHP,
            // rather than a CASE-pivot in SQL - simpler to read and avoids
            // any cross-DB-driver aggregation quirks for two extra columns.
            $users = $DB->get_records_select('user', "id $userinsql", $userparams,
                    '', 'id, firstname, lastname, idnumber, city');

            $profilesql = "SELECT id, userid, fieldid, data
                              FROM {user_info_data}
                             WHERE fieldid $fieldinsql AND userid $userinsql";
            $profilerows = $DB->get_records_sql($profilesql, array_merge($fieldparams, $userparams));

            foreach ($users as $user) {
                $user->state = null;
                $user->region = null;
            }
            foreach ($profilerows as $row) {
                if (!isset($users[$row->userid])) {
                    continue;
                }
                if ($row->fieldid == $statefieldid) {
                    $users[$row->userid]->state = $row->data;
                } else if ($row->fieldid == $regionfieldid) {
                    $users[$row->userid]->region = $row->data;
                }
            }
            return $users;
        }

        $users = $DB->get_records_select('user', "id $userinsql", $userparams,
                '', 'id, firstname, lastname, idnumber, city');
        foreach ($users as $user) {
            $user->state = null;
            $user->region = null;
        }
        return $users;
    }

    protected static function get_profile_field_id(string $shortname): ?int {
        if (!array_key_exists($shortname, self::$profilefieldids)) {
            global $DB;
            $id = $DB->get_field('user_info_field', 'id', ['shortname' => $shortname]);
            self::$profilefieldids[$shortname] = $id !== false ? (int) $id : null;
        }
        return self::$profilefieldids[$shortname];
    }
}
