<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace theme_sentientia\local;

defined('MOODLE_INTERNAL') || die();

/**
 * The enrolment-date countdown of the course banner ("Due In : N days" / "Overdue by : N days").
 *
 * core_renderer::full_header() prints it for a non-admin viewer on a course whose BizLMS column
 * {course}.open_coursecompletiondays is set and not 0 (the number of days a learner has to complete the course,
 * counted from their enrolment). The banner HTML is still built in full_header(); this class holds the two parts
 * that can be wrong: whose enrolment date it reads, and the day arithmetic.
 *
 * WHY THIS IS A CLASS
 * -------------------
 * The block came in with the BizLMS import (2026-04-02) and read the enrolment date like this:
 *
 *     SELECT max(ue.timecreated) as enrolldate
 *       FROM {course} course
 *       JOIN {enrol} e ON e.courseid = course.id
 *       JOIN {user_enrolments} ue ON ue.enrolid = e.id
 *       JOIN {user} u ON u.id = $USER->id AND course.id = $COURSE->id
 *
 * Two defects. The ids were interpolated into the SQL text (the project rule is bound :named params only; the values
 * were ints from the session and the course record, so this was a rule breach, not an injection). And nothing tied
 * ue.userid to the viewer: the {user} join only checked that the viewer exists, so the result was the latest enrolment
 * of ANY user in the course. Every learner saw the same countdown, measured from somebody else's enrolment, and a
 * viewer who is not enrolled at all (a manager, a tenant admin, the guest user) saw one too. On the local copy of the
 * production data, 9,693 of the 9,776 enrolled (course, user) pairs on courses with the column set were shown a date
 * that was not theirs.
 *
 * latest_enrolment_time() is the fixed lookup: bound params, scoped to the viewer. due_text() is the arithmetic,
 * moved here unchanged so it can be tested on its own.
 *
 * WHICH DATE
 * ----------
 * ue.timecreated, as before, and MAX over all of the viewer's enrolments in the course, whatever the method and
 * status (a learner enrolled manually and by self enrolment counts from the later one). BizLMS's own reminder task
 * (local/courses/classes/task/course_reminder.php in the production backup) counts the deadline from each user's
 * ue.timecreated too. ue.timestart was not chosen: it is 0 on 99.7% of these enrolments locally, and where it is set
 * it equals timecreated.
 *
 * WHAT IS DELIBERATELY NOT CHANGED
 * --------------------------------
 * due_text() reproduces the old arithmetic exactly, so a viewer whose own enrolment date was shown before sees the
 * same text and number now: calendar dates in the server's PHP timezone (not the user's), the absolute day
 * difference (an enrolment in the future counts as days already passed), nothing on the enrolment day (0 days) or on
 * the due day (days = completion days), the hard-coded English text without lang strings, "1 days". Each is a known
 * gap recorded in docs/visual-evidence/2026-11-16/course-header-enrol-date/README.md, not fixed here.
 *
 * Theme code, not Moodle core: no SENTIENTIA-CORE-MOD tag and no ADR.
 *
 * @package    theme_sentientia
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolment_countdown {

    /**
     * The time the user was last enrolled in the course: MAX(ue.timecreated) over their enrolments in it.
     *
     * Any enrolment method, active or not. 0 is "not set" in Moodle, not a date, and gives null, as the old
     * !empty() check in full_header() treated it.
     *
     * @param int $courseid the course
     * @param int $userid the viewer
     * @return int|null a Unix timestamp, or null when the user has no enrolment in the course
     */
    public static function latest_enrolment_time(int $courseid, int $userid): ?int {
        global $DB;

        $time = $DB->get_field_sql(
            "SELECT MAX(ue.timecreated)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE e.courseid = :courseid
                AND ue.userid = :userid",
            ['courseid' => $courseid, 'userid' => $userid]
        );
        return empty($time) ? null : (int) $time;
    }

    /**
     * The countdown text for an enrolment time, or null when nothing is shown.
     *
     * The arithmetic full_header() has always used, unchanged: both times are reduced to calendar dates in the PHP
     * default timezone, the difference is taken in days, and
     *  - 0 days (enrolled today)                       => null
     *  - fewer days than $completiondays               => 'Due In : <strong>{completiondays - days} days </strong>'
     *  - exactly $completiondays days (the due day)    => null
     *  - more days than $completiondays                => 'Overdue by : {days - completiondays} days'
     * The text is HTML (printed raw by course_full_header.mustache) built from literals and numbers only.
     *
     * @param int $enroltime the enrolment time (Unix timestamp)
     * @param int $now the current time (Unix timestamp)
     * @param int $completiondays {course}.open_coursecompletiondays
     * @return string|null
     */
    public static function due_text(int $enroltime, int $now, int $completiondays): ?string {
        $today = date('Y-m-d', $now);
        $userenroldate = date('Y-m-d', $enroltime);
        $difference = strtotime($userenroldate) - strtotime($today);
        $days = abs($difference / (60 * 60) / 24);

        if ($days != 0 && $days < $completiondays) {
            return 'Due In : <strong>' . ($completiondays - $days) . ' days </strong>';
        } else if ($days != 0 && $days > $completiondays) {
            return 'Overdue by : ' . abs($completiondays - $days) . ' days';
        }
        return null;
    }
}
