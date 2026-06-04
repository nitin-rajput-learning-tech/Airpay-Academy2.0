<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_sentientia_whatsapp — Stream F / Wave E2 P4 (2026-05-25).
 *
 * Wires three of the four content-event triggers via the global event API:
 *
 *   \core\event\course_updated         → course visibility 0→1 transition
 *   \tool_certificate\event\certificate_issued → cert ready
 *   \core\event\course_completed       → recompute learning-path progress + milestone
 *
 * The fourth trigger (course-due-soon, <48h) fires from the existing
 * \local_airpay_courses\task\course_reminder cron — wired inline there to
 * stay alongside the other cron-based bridge calls.
 *
 * EVERY handler:
 *   - is fail-safe (try/catch swallows everything → never poisons the
 *     core event flow)
 *   - short-circuits cheaply if the content master flag is OFF
 *     (per CLAUDE.md feature-flag rule)
 *   - delegates the actual send + throttle to notification_bridge
 *
 * @package local_sentientia_whatsapp
 */
class observer {

    /**
     * Handler for \core\event\course_updated. Fires the
     * "new course in your catalogue" nudge to every actively-enrolled
     * user IF the course's visibility just flipped from 0 to 1.
     *
     * The event itself doesn't carry the old visibility value, so we
     * approximate by:
     *   - inspecting the current course.visible (must be 1)
     *   - using send_new_course_notification's per-(userid, course)
     *     per-user 6h throttle (in notification_bridge) absorbs any
     *     burst that slips past the per-course marker.
     *
     * The \core\event\course_updated event does NOT carry the previous
     * visibility value, so we can't read the 0→1 transition off the
     * event directly. Instead we keep a per-course "already announced"
     * marker (a JSON set stored in ONE plugin-config key — not a
     * per-course config row, so the cached config bag stays small). A
     * course announces exactly once, the first time it's seen visible:
     *
     *   - visible == 1 AND not previously announced → announce + mark
     *   - visible == 1 AND already announced         → skip (later edits
     *                                                   don't re-announce)
     *   - visible == 0                               → clear the marker so
     *                                                   a genuine re-publish
     *                                                   later announces again
     *
     * @param \core\event\course_updated $event
     */
    public static function course_updated(\core\event\course_updated $event): void {
        global $DB;

        try {
            if (!notification_bridge::content_flag_on()) {
                return;
            }

            $courseid = (int) $event->courseid;
            if ($courseid <= 1) {
                return;  // skip the front-page pseudo-course (id=1)
            }

            $course = $DB->get_record('course',
                ['id' => $courseid], 'id, visible');
            if (!$course) {
                return;
            }

            if ((int) $course->visible !== 1) {
                // Course is (now) hidden — forget any prior announcement so
                // a future re-publish counts as new.
                self::clear_announced($courseid);
                return;
            }

            // Visible. Announce exactly once per publish.
            if (self::already_announced($courseid)) {
                return;
            }
            self::mark_announced($courseid);

            // Fan out to every active enrolment. Best-effort — capped at
            // MAX_RECIPIENTS to keep the observer fast; overflow is logged.
            $userids = self::active_enrolled_user_ids($courseid, self::MAX_RECIPIENTS);
            foreach ($userids as $uid) {
                notification_bridge::send_new_course_notification($uid, $courseid);
            }

        } catch (\Throwable $e) {
            debugging('[sentientia_whatsapp] course_updated observer failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handler for \tool_certificate\event\certificate_issued.
     * Fires the "your certificate is ready" nudge to the user the cert
     * was issued for. relateduserid carries the recipient; objectid
     * carries the certificate-issue row id.
     *
     * @param \core\event\base $event  Use base class to keep this file
     *                                  compilable on installs without
     *                                  tool_certificate.
     */
    public static function certificate_issued(\core\event\base $event): void {
        try {
            if (!notification_bridge::content_flag_on()) {
                return;
            }

            $userid = (int) ($event->relateduserid ?? 0);
            $certid = (int) ($event->objectid ?? 0);
            if ($userid <= 0 || $certid <= 0) {
                return;
            }

            notification_bridge::send_certificate_ready($userid, $certid);

        } catch (\Throwable $e) {
            debugging('[sentientia_whatsapp] certificate_issued observer failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Handler for \core\event\course_completed. For every learning path
     * that contains this course AND that the user is enrolled in,
     * recomputes the user's completion %, and fires the milestone nudge
     * for the highest threshold reached (25 / 50 / 75 / 100%). The
     * bridge's per-(user, path, milestone) throttle ensures each distinct
     * milestone pings at most once.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;

        try {
            if (!notification_bridge::content_flag_on()) {
                return;
            }

            $userid   = (int) ($event->relateduserid ?? 0);
            $courseid = (int) ($event->courseid ?? 0);
            if ($userid <= 0 || $courseid <= 0) {
                return;
            }

            // Look up paths that contain this course. Soft-coupled —
            // skip if the learning-path table doesn't exist yet
            // (fresh install or path plugin disabled).
            if (!$DB->get_manager()->table_exists('local_airpay_learningpath_courses')) {
                return;
            }
            if (!$DB->get_manager()->table_exists('local_airpay_learningpath_users')) {
                return;
            }

            // Paths the user is enrolled in AND that include this course.
            $sql = "SELECT DISTINCT lpc.pathid
                      FROM {local_airpay_learningpath_courses} lpc
                      JOIN {local_airpay_learningpath_users}   lpu
                        ON lpu.pathid = lpc.pathid
                       AND lpu.userid = :uid
                     WHERE lpc.courseid = :cid";
            $pathids = $DB->get_fieldset_sql($sql, [
                'uid' => $userid, 'cid' => $courseid]);

            if (empty($pathids)) {
                return;
            }

            foreach ($pathids as $pathid) {
                $milestone = self::compute_milestone((int) $pathid, $userid);
                if ($milestone === null) {
                    continue;
                }
                notification_bridge::send_path_milestone(
                    $userid, (int) $pathid, $milestone);
            }

        } catch (\Throwable $e) {
            debugging('[sentientia_whatsapp] course_completed observer failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Compute the user's progress percentage in a learning path and
     * return the label of the HIGHEST milestone threshold reached
     * (25 / 50 / 75 / 100%), or null when progress is below 25%.
     *
     * The notification_bridge throttle dedupes per (user, path, label),
     * so as the learner advances they receive each new milestone once;
     * a single big jump (e.g. 0→75%) yields just the highest label.
     *
     * @param int $pathid
     * @param int $userid
     * @return string|null
     */
    private static function compute_milestone(int $pathid, int $userid): ?string {
        global $DB;

        $total = (int) $DB->count_records('local_airpay_learningpath_courses',
            ['pathid' => $pathid]);
        if ($total <= 0) {
            return null;
        }

        $completed = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT lpc.courseid)
               FROM {local_airpay_learningpath_courses} lpc
               JOIN {course_completions} cc
                 ON cc.course = lpc.courseid
                AND cc.userid = :uid
                AND cc.timecompleted > 0
              WHERE lpc.pathid = :pid",
            ['uid' => $userid, 'pid' => $pathid]
        );

        if ($completed <= 0) {
            return null;
        }

        $pct = (int) floor(($completed / $total) * 100);

        // Milestone crossing: find the highest threshold ≤ pct.
        // The throttle dedupes the (user, path, milestone) tuple, so
        // re-firing the same milestone within 6h is a no-op.
        $thresholds = [100, 75, 50, 25];
        foreach ($thresholds as $t) {
            if ($pct >= $t) {
                return $t . '%';
            }
        }
        return null;
    }

    /**
     * Get up to $cap active-enrolment user ids for a course. Skips deleted
     * + suspended users. Returns int[].
     */
    private static function active_enrolled_user_ids(int $courseid, int $cap): array {
        global $DB;
        $sql = "SELECT DISTINCT ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
                  JOIN {user}  u ON u.id = ue.userid
                                AND u.deleted = 0
                                AND u.suspended = 0
                 WHERE e.courseid = :cid
                   AND ue.status = 0";
        $rows = $DB->get_fieldset_sql($sql, ['cid' => $courseid]);
        if (count($rows) > $cap) {
            debugging('[sentientia_whatsapp] course_updated observer truncated: '
                . count($rows) . ' enrolees > cap of ' . $cap
                . ' for course ' . $courseid, DEBUG_DEVELOPER);
            $rows = array_slice($rows, 0, $cap);
        }
        return array_map('intval', $rows);
    }

    // ─── per-course "announced" marker (single config key) ─────────────
    // Stored as a JSON map {courseid: 1} under ONE config key so the
    // cached plugin-config bag stays a fixed size (one entry), unlike a
    // per-course config row which would bloat config_plugins. Bounded by
    // MAX_TRACKED — oldest ids are dropped first (FIFO) once the cap is hit.

    private const ANNOUNCED_CONFIG_KEY = 'announced_course_ids';
    private const MAX_TRACKED = 5000;

    private static function read_announced(): array {
        $raw = (string) (get_config('local_sentientia_whatsapp',
            self::ANNOUNCED_CONFIG_KEY) ?: '');
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function already_announced(int $courseid): bool {
        $set = self::read_announced();
        return isset($set[(string) $courseid]);
    }

    private static function mark_announced(int $courseid): void {
        $set = self::read_announced();
        $set[(string) $courseid] = 1;
        // FIFO trim: keep the most-recently-added MAX_TRACKED ids.
        if (count($set) > self::MAX_TRACKED) {
            $set = array_slice($set, -self::MAX_TRACKED, null, true);
        }
        set_config(self::ANNOUNCED_CONFIG_KEY, json_encode($set),
            'local_sentientia_whatsapp');
    }

    private static function clear_announced(int $courseid): void {
        $set = self::read_announced();
        if (isset($set[(string) $courseid])) {
            unset($set[(string) $courseid]);
            set_config(self::ANNOUNCED_CONFIG_KEY, json_encode($set),
                'local_sentientia_whatsapp');
        }
    }

    /** Recipient cap per course-publish event — protects against bursts. */
    private const MAX_RECIPIENTS = 500;
}
