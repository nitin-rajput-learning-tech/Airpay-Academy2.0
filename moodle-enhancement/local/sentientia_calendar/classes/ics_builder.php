<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * RFC 5545 (iCalendar) builder for a user's personal Sentientia LMS calendar.
 *
 * Generates one VCALENDAR with N VEVENTs covering everything the user
 * needs to see in their external calendar:
 *
 *   1. Course completion deadlines — derived from
 *      `enrolment.timestart + course.open_coursecompletiondays * 86400`
 *      for every active enrolment where the user hasn't yet completed.
 *
 *   2. Classroom (ILT) sessions — every row in
 *      local_airpay_classroom_sessions for a classroom the user is
 *      enrolled in (local_airpay_classroom_users).
 *
 *   3. Exam close-dates — every quiz.timeclose > now for a course the
 *      user is enrolled in, exposed via local_airpay_exams.
 *
 * Tenancy: all queries scope by userid only — the user authenticated via
 * the token. We surface only events the user themselves is associated
 * with, so no cross-tenant leakage is possible.
 *
 * Timezone: every VEVENT uses TZID=Asia/Kolkata (Airpay customer
 * default; customer-configurable in Phase 1.x via a per-customer
 * setting once the customer-config layer is fleshed out).
 *
 * Companion to {@see local_airpay_classroom\ics_builder} which produces
 * a single-session .ics download. This builder produces the full
 * subscription feed.
 *
 * @package local_sentientia_calendar
 */

namespace local_sentientia_calendar;

defined('MOODLE_INTERNAL') || die();

class ics_builder {

    /** Timezone embedded in every VEVENT. */
    public const DEFAULT_TZID = 'Asia/Kolkata';

    /** Product identifier for the PRODID header. */
    public const PRODID = '-//Sentientia LMS//Sentientia Calendar Sync 1.0//EN';

    /** UID hostname suffix. Stable per-deployment identifier per RFC 5545 §3.8.4.7. */
    public const UID_HOST = 'sentientia.local';

    /**
     * Build the full iCalendar feed for one user.
     *
     * @param int $userid     The user whose calendar to build
     * @param int|null $now   Override for "now" (test seam). Defaults to time().
     * @return string CRLF-separated iCalendar text, ready to write to HTTP body
     */
    public static function build_for_user(int $userid, ?int $now = null): string {
        $now = $now ?? time();

        $events = [];
        $events = array_merge($events, self::collect_course_deadlines($userid, $now));
        $events = array_merge($events, self::collect_classroom_sessions($userid, $now));
        $events = array_merge($events, self::collect_exam_closes($userid, $now));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Sentientia LMS — My learning',
            'X-WR-TIMEZONE:' . self::DEFAULT_TZID,
            'X-WR-CALDESC:Course deadlines, classroom sessions, and exam close-dates from Sentientia LMS.',
        ];

        // VTIMEZONE block — RFC 5545 requires this when DTSTART carries TZID.
        // IST has no DST, so this is a single STANDARD definition.
        $lines = array_merge($lines, self::vtimezone_block());

        foreach ($events as $event) {
            $lines = array_merge($lines, self::render_vevent($event, $now));
        }

        $lines[] = 'END:VCALENDAR';

        $folded = array_map([self::class, 'fold_line'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    /**
     * Collect course completion deadline events for the user.
     *
     * Sourced from the same SQL shape as
     * {@see local_airpay_courses\task\course_reminder} — every active
     * enrolment where the course has a non-zero
     * open_coursecompletiondays and the user has not yet completed.
     *
     * Output array shape:
     *   [
     *     'uid' => string,
     *     'summary' => string,
     *     'description' => string,
     *     'dtstart' => int (unix ts),
     *     'dtend'   => int (unix ts),
     *     'all_day' => true,
     *     'url'     => string,
     *     'category' => 'COURSE-DEADLINE',
     *   ]
     *
     * @param int $userid
     * @param int $now
     * @return array<int, array> List of event arrays
     */
    public static function collect_course_deadlines(int $userid, int $now): array {
        global $DB, $CFG;

        if (!self::flag_on('sentientia.calendar_sync.events.courses', true)) {
            return [];
        }

        // Mirror the query used by airpay_courses\task\course_reminder
        // but pivoted on the user (not the cron iteration over all users).
        $sql = "SELECT c.id          AS courseid,
                        c.fullname    AS coursename,
                        c.shortname,
                        c.open_coursecompletiondays AS days,
                        ue.timestart
                  FROM {course} c
                  JOIN {enrol} e             ON e.courseid = c.id AND e.status = 0
                  JOIN {user_enrolments} ue  ON ue.enrolid = e.id AND ue.status = 0
             LEFT JOIN {course_completions} cc ON cc.userid = ue.userid
                                              AND cc.course = c.id
                                              AND cc.timecompleted > 0
                 WHERE ue.userid = :uid
                   AND c.id > 1
                   AND c.visible = 1
                   AND c.open_coursecompletiondays > 0
                   AND cc.id IS NULL
                   AND ue.timestart > 0
              ORDER BY ue.timestart ASC";

        $rs = $DB->get_recordset_sql($sql, ['uid' => $userid]);

        $events = [];
        foreach ($rs as $row) {
            $deadline = (int) $row->timestart + ((int) $row->days * 86400);
            // Skip deadlines >180 days in the past — old enrolments that
            // were never completed but are no longer actionable. Future
            // deadlines and recent overdue ones (last 6 months) still
            // surface so the learner sees the gap.
            if ($deadline < $now - (180 * 86400)) {
                continue;
            }
            $events[] = [
                'uid'         => 'sentientia-course-' . (int) $row->courseid
                    . '-user-' . $userid . '@' . self::UID_HOST,
                'summary'     => '[Course Deadline] ' . (string) $row->coursename,
                'description' => 'Complete the course "' . (string) $row->coursename
                    . '" by the deadline. View in Sentientia LMS: '
                    . $CFG->wwwroot . '/course/view.php?id=' . (int) $row->courseid,
                'dtstart'     => $deadline,
                'dtend'       => $deadline,
                'all_day'     => true,
                'url'         => $CFG->wwwroot . '/course/view.php?id=' . (int) $row->courseid,
                'category'    => 'COURSE-DEADLINE',
            ];
        }
        $rs->close();
        return $events;
    }

    /**
     * Collect classroom (ILT) sessions the user is enrolled in.
     *
     * Sourced from local_airpay_classroom_sessions joined to
     * local_airpay_classroom_users (the roster). Both past and future
     * sessions are included — past sessions are useful for the user's
     * own training history in their calendar app's archive view.
     *
     * @param int $userid
     * @param int $now (currently unused — kept for symmetry)
     * @return array<int, array>
     */
    public static function collect_classroom_sessions(int $userid, int $now): array {
        global $DB, $CFG;

        if (!self::flag_on('sentientia.calendar_sync.events.classroom', true)) {
            return [];
        }
        // Skip silently if the airpay_classroom plugin isn't installed yet.
        if (!$DB->get_manager()->table_exists('local_airpay_classroom_sessions')) {
            return [];
        }

        $sql = "SELECT s.id AS sessionid,
                        s.classroomid,
                        s.title AS sessiontitle,
                        s.starttime,
                        s.endtime,
                        s.location AS sessionlocation,
                        s.notes,
                        s.meeting_url,
                        cl.name AS classroomname,
                        cl.location AS classroomlocation
                  FROM {local_airpay_classroom_sessions} s
                  JOIN {local_airpay_classroom} cl ON cl.id = s.classroomid
                  JOIN {local_airpay_classroom_users} u ON u.classroomid = s.classroomid
                 WHERE u.userid = :uid
                   AND cl.visible = 1
                   AND cl.status <> 0
              ORDER BY s.starttime ASC";

        $rs = $DB->get_recordset_sql($sql, ['uid' => $userid]);

        $events = [];
        foreach ($rs as $row) {
            $title = trim((string) ($row->sessiontitle ?? '')) !== ''
                ? (string) $row->sessiontitle
                : (string) $row->classroomname;
            $location = !empty($row->sessionlocation)
                ? (string) $row->sessionlocation
                : (string) ($row->classroomlocation ?? '');

            $desc_parts = [
                'Classroom: ' . trim((string) $row->classroomname),
            ];
            if (!empty($row->notes)) {
                $desc_parts[] = trim((string) $row->notes);
            }
            if (!empty($row->meeting_url)) {
                $desc_parts[] = 'Join: ' . (string) $row->meeting_url;
            }
            $desc_parts[] = 'Details: ' . $CFG->wwwroot
                . '/local/airpay_classroom/index.php?id=' . (int) $row->classroomid;

            // Defensive end-time: 60 minutes after start if end <= start.
            $end = max((int) $row->starttime + 60, (int) $row->endtime);

            $events[] = [
                'uid'         => 'sentientia-classroom-session-' . (int) $row->sessionid
                    . '-user-' . $userid . '@' . self::UID_HOST,
                'summary'     => '[ILT] ' . $title,
                'description' => implode("\n", $desc_parts),
                'dtstart'     => (int) $row->starttime,
                'dtend'       => $end,
                'all_day'     => false,
                'location'    => $location,
                'url'         => $CFG->wwwroot
                    . '/local/airpay_classroom/index.php?id=' . (int) $row->classroomid,
                'category'    => 'CLASSROOM-SESSION',
            ];
        }
        $rs->close();
        return $events;
    }

    /**
     * Collect upcoming exam close-dates for courses the user is enrolled in.
     *
     * Sourced from the join used by
     * {@see local_airpay_exams\task\exam_reminder} — every quiz with
     * timeclose > 0 in a course the user is actively enrolled in and
     * has not yet finished.
     *
     * Past close-dates are skipped (no value adding them to the
     * calendar). Within a 90-day forward window to bound the feed size.
     *
     * @param int $userid
     * @param int $now
     * @return array<int, array>
     */
    public static function collect_exam_closes(int $userid, int $now): array {
        global $DB, $CFG;

        if (!self::flag_on('sentientia.calendar_sync.events.exams', true)) {
            return [];
        }
        // Skip silently if the airpay_exams plugin isn't installed yet.
        if (!$DB->get_manager()->table_exists('local_airpay_exams')) {
            return [];
        }

        $win_end = $now + (90 * 86400);

        $sql = "SELECT e.id          AS examid,
                        e.name        AS examname,
                        q.id          AS quizid,
                        q.timeclose,
                        c.id          AS courseid,
                        c.fullname    AS coursename
                  FROM {local_airpay_exams} e
                  JOIN {quiz} q              ON q.id = e.quizid
                                            AND q.timeclose > 0
                  JOIN {course} c            ON c.id = q.course
                  JOIN {enrol} en            ON en.courseid = c.id AND en.status = 0
                  JOIN {user_enrolments} ue  ON ue.enrolid = en.id
                                            AND ue.status = 0
                                            AND ue.userid = :uid
             LEFT JOIN {quiz_attempts} qa    ON qa.quiz = q.id
                                            AND qa.userid = ue.userid
                                            AND qa.state = 'finished'
                                            AND qa.sumgrades IS NOT NULL
                 WHERE e.status = 1
                   AND e.visible = 1
                   AND qa.id IS NULL
                   AND q.timeclose BETWEEN :winstart AND :winend
              ORDER BY q.timeclose ASC";

        $rs = $DB->get_recordset_sql($sql, [
            'uid'      => $userid,
            'winstart' => $now,
            'winend'   => $win_end,
        ]);

        $events = [];
        foreach ($rs as $row) {
            $close = (int) $row->timeclose;
            $events[] = [
                'uid'         => 'sentientia-exam-' . (int) $row->examid
                    . '-user-' . $userid . '@' . self::UID_HOST,
                'summary'     => '[Exam Closes] ' . (string) $row->examname,
                'description' => 'Exam "' . (string) $row->examname . '" closes for the course "'
                    . (string) $row->coursename . '". Last chance to attempt. View in Sentientia LMS: '
                    . $CFG->wwwroot . '/mod/quiz/view.php?q=' . (int) $row->quizid,
                // We model "exam closes" as a 30-minute window leading
                // up to timeclose, so the calendar reminder rings
                // BEFORE the deadline hits, not at the deadline itself.
                'dtstart'     => max($close - 1800, $now),
                'dtend'       => $close,
                'all_day'     => false,
                'url'         => $CFG->wwwroot . '/mod/quiz/view.php?q=' . (int) $row->quizid,
                'category'    => 'EXAM-CLOSE',
            ];
        }
        $rs->close();
        return $events;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Internals
    // ─────────────────────────────────────────────────────────────────

    /**
     * Render one event array into VEVENT lines.
     *
     * Returns the lines unfolded — folding happens at the very end in
     * build_for_user() to honour RFC 5545's 75-octet line-fold rule
     * uniformly across the whole document.
     *
     * @param array $event Event array (see collect_* methods for shape)
     * @param int   $now   Unix ts for DTSTAMP
     * @return array<int, string> List of lines (no CRLFs)
     */
    private static function render_vevent(array $event, int $now): array {
        $lines = [];
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . self::escape_text((string) $event['uid']);
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z', $now);

        if (!empty($event['all_day'])) {
            // All-day VEVENTs use VALUE=DATE, no TZID, and DTEND is the
            // day AFTER the event by RFC 5545 §3.6.1 — but Outlook
            // tolerates DTSTART=DTEND for single-day events and renders
            // them correctly. We emit DTEND as DTSTART + 1 day to stay
            // strictly conformant.
            $start_date = self::format_date_local((int) $event['dtstart']);
            $end_date   = self::format_date_local((int) $event['dtstart'] + 86400);
            $lines[] = 'DTSTART;VALUE=DATE:' . $start_date;
            $lines[] = 'DTEND;VALUE=DATE:' . $end_date;
        } else {
            $lines[] = 'DTSTART;TZID=' . self::DEFAULT_TZID . ':'
                . self::format_datetime_local((int) $event['dtstart']);
            $lines[] = 'DTEND;TZID=' . self::DEFAULT_TZID . ':'
                . self::format_datetime_local((int) $event['dtend']);
        }

        $lines[] = 'SUMMARY:' . self::escape_text((string) $event['summary']);

        if (isset($event['description']) && $event['description'] !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape_text((string) $event['description']);
        }
        if (!empty($event['location'])) {
            $lines[] = 'LOCATION:' . self::escape_text((string) $event['location']);
        }
        if (!empty($event['url'])) {
            $lines[] = 'URL:' . self::escape_text((string) $event['url']);
        }
        if (!empty($event['category'])) {
            $lines[] = 'CATEGORIES:' . self::escape_text((string) $event['category']);
        }
        $lines[] = 'STATUS:CONFIRMED';
        $lines[] = 'TRANSP:OPAQUE';
        $lines[] = 'END:VEVENT';
        return $lines;
    }

    /**
     * VTIMEZONE block for IST (UTC+5:30). IST has no DST → single
     * STANDARD definition, no DAYLIGHT.
     *
     * @return array<int, string>
     */
    private static function vtimezone_block(): array {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:' . self::DEFAULT_TZID,
            'X-LIC-LOCATION:' . self::DEFAULT_TZID,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0530',
            'TZOFFSETTO:+0530',
            'TZNAME:IST',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /**
     * Format a unix ts as a local YYYYMMDDTHHMMSS string in DEFAULT_TZID
     * (no Z suffix — combined with TZID=Asia/Kolkata, this expresses
     * "this clock time in IST").
     */
    private static function format_datetime_local(int $unix_ts): string {
        $tz = new \DateTimeZone(self::DEFAULT_TZID);
        $dt = (new \DateTimeImmutable('@' . $unix_ts))->setTimezone($tz);
        return $dt->format('Ymd\THis');
    }

    /**
     * Format a unix ts as a YYYYMMDD date in DEFAULT_TZID for all-day events.
     */
    private static function format_date_local(int $unix_ts): string {
        $tz = new \DateTimeZone(self::DEFAULT_TZID);
        $dt = (new \DateTimeImmutable('@' . $unix_ts))->setTimezone($tz);
        return $dt->format('Ymd');
    }

    /**
     * Escape special chars in an iCal text value (RFC 5545 §3.3.11).
     * Backslash, comma, semicolon, newline → escaped sequences.
     */
    private static function escape_text(string $s): string {
        return str_replace(
            ["\\", ";", ",", "\r\n", "\n"],
            ["\\\\", "\\;", "\\,", "\\n", "\\n"],
            $s
        );
    }

    /**
     * Fold a single line at 75 octets (RFC 5545 §3.1).
     *
     * Continuation lines start with a single space; the space counts
     * toward the 75-octet budget per §3.1, so continuation segments
     * are 74 octets of content + 1 leading space = 75 octets total.
     *
     * Most modern calendar clients tolerate unfolded too, but Outlook
     * on Windows has historically been strict about this — so we fold.
     *
     * @param string $line One logical line (no CRLF)
     * @return string Folded line (may contain CRLFs internally)
     */
    private static function fold_line(string $line): string {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = substr($line, 0, 75);
        $rest = substr($line, 75);
        while (strlen($rest) > 74) {
            // Continuation: leading space + 74 octets of content = 75-octet line.
            $out .= "\r\n " . substr($rest, 0, 74);
            $rest = substr($rest, 74);
        }
        if (strlen($rest) > 0) {
            $out .= "\r\n " . $rest;
        }
        return $out;
    }

    /**
     * Resolve a feature flag, falling back to $default when the
     * airpay_core helper is unavailable.
     */
    private static function flag_on(string $key, bool $default): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return $default;
        }
        return \local_airpay_core\feature_flags::is_enabled($key);
    }
}
