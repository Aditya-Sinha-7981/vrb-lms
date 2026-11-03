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

namespace local_vrbcert;

use local_vrblms\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Course-completion certificates: one per employee per brand course, issued
 * as soon as every quiz in the course is passed.
 *
 * Independent of the top-performer tracks in {@see issuer} - no ranking,
 * no Top N, no award period. "Complete" comes from
 * \local_vrblms\api::get_course_progress(), not Moodle's course-completion
 * criteria (none are configured on the brand courses).
 *
 * Stored in the same local_vrbcert_issued table as the other tracks, so
 * "My certificates", downloads, revoke and privacy all work unchanged:
 * brandkey = 'course_<courseid>', track = 'completion', period = 'completion'.
 *
 * @package    local_vrbcert
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_issuer {

    /** Track value stored on completion certificates. */
    const TRACK = 'completion';
    /** Fixed period value - a course is completed once, so there is no award cycle. */
    const PERIOD = 'completion';

    /**
     * @param int $courseid
     * @return string the brandkey a completion certificate for this course is stored under
     */
    public static function brandkey(int $courseid): string {
        return 'course_' . $courseid;
    }

    /**
     * @return bool both the master switch and the completion track are on
     */
    public static function is_enabled(): bool {
        $config = get_config('local_vrbcert');
        return !empty($config->enabled) && !empty($config->completion_enabled);
    }

    /**
     * Event observer for quiz grading events (see db/events.php). Runs after
     * the grading transaction commits; never throws, so a certificate problem
     * can't surface as an error on the employee's quiz submission.
     *
     * @param \core\event\base $event
     */
    public static function quiz_attempt_graded(\core\event\base $event): void {
        if (empty($event->relateduserid) || empty($event->courseid) || !self::is_enabled()) {
            return;
        }
        try {
            self::issue_for_user((int) $event->relateduserid, (int) $event->courseid);
        } catch (\Throwable $e) {
            debugging('local_vrbcert: completion certificate for user ' . $event->relateduserid
                . ' in course ' . $event->courseid . ' failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Issue one employee's certificate for one course, if they've completed it
     * and don't hold one already.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $issuedby admin userid, or 0 for automatic issuance
     * @return string 'issued' | 'exists' | 'incomplete' | 'notbrandcourse' | 'inactiveuser'
     */
    public static function issue_for_user(int $userid, int $courseid, int $issuedby = 0): string {
        $courses = api::get_brand_courses();
        if (!isset($courses[$courseid])) {
            return 'notbrandcourse';
        }
        if (issued_certificate::find($userid, self::brandkey($courseid), self::PERIOD)) {
            return 'exists';
        }
        $progress = api::get_course_progress($courseid, [$userid])[$userid] ?? null;
        if ($progress === null || !$progress->complete) {
            return 'incomplete';
        }
        $user = \core_user::get_user($userid);
        if (!$user || $user->deleted || $user->suspended) {
            return 'inactiveuser';
        }
        self::issue($user, $courses[$courseid], $progress, $issuedby);
        return 'issued';
    }

    /**
     * Catch-up sweep: issue every certificate that is due but missing, e.g.
     * for employees who completed a course before this track existed, or
     * whose last quiz was graded by a path that didn't fire an event.
     *
     * @param int $issuedby admin userid, or 0 for cron
     * @return array summary: disabled, issued, skipped, errors[]
     */
    public static function run(int $issuedby = 0): array {
        $summary = ['disabled' => false, 'issued' => 0, 'skipped' => 0, 'errors' => []];
        if (!self::is_enabled()) {
            $summary['disabled'] = true;
            return $summary;
        }

        foreach (api::get_brand_courses() as $courseid => $course) {
            foreach (api::get_course_progress($courseid) as $userid => $progress) {
                if (!$progress->complete) {
                    continue;
                }
                if (issued_certificate::find($userid, self::brandkey($courseid), self::PERIOD)) {
                    $summary['skipped']++;
                    continue;
                }
                $user = \core_user::get_user($userid);
                if (!$user || $user->deleted || $user->suspended) {
                    continue;
                }
                try {
                    self::issue($user, $course, $progress, $issuedby);
                    $summary['issued']++;
                } catch (\Throwable $e) {
                    $summary['errors'][] = "user {$userid} / course {$courseid}: " . $e->getMessage();
                }
            }
        }
        return $summary;
    }

    /**
     * @param \stdClass $user full user record
     * @param \stdClass $course row from api::get_brand_courses()
     * @param \stdClass $progress row from api::get_course_progress()
     * @param int $issuedby
     */
    private static function issue(\stdClass $user, \stdClass $course, \stdClass $progress, int $issuedby): void {
        $data = new certificate_data([
            'userid' => (int) $user->id,
            'fullname' => fullname($user),
            'empcode' => (string) $user->idnumber,
            'brandkey' => self::brandkey($course->courseid),
            'brandname' => $course->coursename,
            'displaybrandkey' => $course->brandidnumber,
            'displaybrandname' => $course->brandname,
            'coursename' => $course->coursename,
            'track' => self::TRACK,
            'rank' => null,
            'scorepercent' => (float) $progress->score_percent,
            'period' => self::PERIOD,
            'issuedate' => time(),
        ]);
        $pdf = certificate_generator::render($data);
        // No ranking strategy is involved in a completion certificate.
        issued_certificate::create($data, '', $issuedby, $pdf);
    }
}
