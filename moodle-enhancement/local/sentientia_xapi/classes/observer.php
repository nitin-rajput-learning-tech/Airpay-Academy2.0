<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_xapi;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_xapi\model\statement;
use local_sentientia_xapi\lrs\store;

/**
 * Moodle event observer — emits xAPI statements.
 *
 * Every handler silently no-ops when `sentientia.xapi.enabled` is OFF
 * (default). This is the "background job" degradation pattern from
 * CONFIGURABILITY-ARCHITECTURE.md §5.1.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Course completed — emit verb: completed.
     */
    public static function course_completed(\core\event\course_completed $event): void {
        if (!self::is_enabled()) {
            return;
        }

        global $DB, $CFG;

        $userid   = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;

        $user   = $DB->get_record('user',   ['id' => $userid,   'deleted' => 0]);
        $course = $DB->get_record('course', ['id' => $courseid]);

        if (!$user || !$course) {
            return;
        }

        $costcenterid = self::tenant_for_user($user);
        $stmt         = statement::build_course_completed($user, $course, $CFG->wwwroot);

        try {
            (new store())->put($stmt, $costcenterid, $userid, store::SOURCE_MOODLE);
        } catch (\Throwable $e) {
            // Silently log — never break the Moodle event pipeline.
            debugging('local_sentientia_xapi observer: course_completed failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Quiz attempt submitted — emit verb: passed | failed.
     */
    public static function quiz_submitted(\mod_quiz\event\attempt_submitted $event): void {
        if (!self::is_enabled()) {
            return;
        }

        global $DB, $CFG;

        $attempt_id = (int) $event->objectid;
        $userid     = (int) $event->userid;

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt_id]);
        if (!$attempt) {
            return;
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        if (!$quiz) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return;
        }

        $score_raw = (float) ($attempt->sumgrades ?? 0);
        $score_max = (float) ($quiz->sumgrades ?? 0);
        $passed    = $score_max > 0 && ($score_raw / $score_max) >= ($quiz->grade > 0 ? $quiz->gradepass / $quiz->grade : 0.5);

        $costcenterid = self::tenant_for_user($user);
        $stmt         = statement::build_quiz_submitted($user, $quiz, $score_raw, $score_max, $passed, $CFG->wwwroot);

        try {
            (new store())->put($stmt, $costcenterid, $userid, store::SOURCE_MOODLE);
        } catch (\Throwable $e) {
            debugging('local_sentientia_xapi observer: quiz_submitted failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Course module viewed — emit verb: experienced.
     * Only fires when the sub-flag sentientia.xapi.emit_module_view is ON.
     */
    public static function course_module_viewed(\core\event\course_module_viewed $event): void {
        if (!self::is_enabled()) {
            return;
        }
        if (!self::flag_on('sentientia.xapi.emit_module_view')) {
            return;
        }

        global $DB, $CFG;

        $userid = (int) $event->userid;
        $cmid   = (int) $event->contextinstanceid;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return;
        }

        // Get module display name.
        $cmname = $event->get_context()->get_context_name(false, true);

        $costcenterid = self::tenant_for_user($user);
        $stmt         = statement::build_module_viewed($user, $cm, $cmname, $CFG->wwwroot);

        try {
            (new store())->put($stmt, $costcenterid, $userid, store::SOURCE_MOODLE);
        } catch (\Throwable $e) {
            debugging('local_sentientia_xapi observer: course_module_viewed failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * User logged in — emit verb: experienced.
     * Only fires when the sub-flag sentientia.xapi.emit_login is ON.
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        if (!self::is_enabled()) {
            return;
        }
        if (!self::flag_on('sentientia.xapi.emit_login')) {
            return;
        }

        global $DB, $CFG;

        $userid = (int) $event->objectid;
        $user   = $DB->get_record('user', ['id' => $userid, 'deleted' => 0]);
        if (!$user) {
            return;
        }

        $costcenterid = self::tenant_for_user($user);
        $stmt         = statement::build_user_loggedin($user, $CFG->wwwroot);

        try {
            (new store())->put($stmt, $costcenterid, $userid, store::SOURCE_MOODLE);
        } catch (\Throwable $e) {
            debugging('local_sentientia_xapi observer: user_loggedin failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    // ─── Private helpers ──────────────────────────────────────────────

    /**
     * Is the xAPI master flag enabled?
     */
    private static function is_enabled(): bool {
        return self::flag_on('sentientia.xapi.enabled');
    }

    /**
     * Check a feature flag (graceful when platform plugin is absent).
     */
    private static function flag_on(string $key): bool {
        if (!class_exists('\local_sentientia_platform\feature_flags')) {
            return false;
        }
        return \local_sentientia_platform\feature_flags::is_enabled($key);
    }

    /**
     * Derive the tenant root from a user's open_path.
     */
    private static function tenant_for_user(\stdClass $user): int {
        if (class_exists('\local_sentientia_platform\tenant')) {
            return \local_sentientia_platform\tenant::root_for_user($user);
        }
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        return ctype_digit($parts[0] ?? '') ? (int) $parts[0] : 0;
    }
}
