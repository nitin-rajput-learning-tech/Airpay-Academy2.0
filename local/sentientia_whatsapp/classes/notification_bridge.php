<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * Notification bridge — Stream C / Phase C.1 (2026-05-21)
 *                     + Stream F / Wave E2 P4 (2026-05-25).
 *
 * Mirror of \local_sentientia_pwa\notification_bridge (Phase B.3). Same
 * "also_*" pattern, soft-coupled and error-swallowing, callable from any
 * cron that has just finished its email send via message_send(). Lets
 * the cron fan out to email + push + WhatsApp/SMS in three lines:
 *
 *   \message_send($msg);
 *   \local_sentientia_pwa\notification_bridge::also_push(...);
 *   \local_sentientia_whatsapp\notification_bridge::also_send(...);
 *
 * Direct call to whatsapp_client::send_template() — NOT channel_router::
 * dispatch(), because dispatch() cascades to email and email already
 * fired via message_send() upstream. Each call here ALSO logs to
 * local_sentientia_send_log so the analytics page can see WhatsApp/SMS
 * delivery alongside email.
 *
 * Two feature flags gate this:
 *   - engagement.whatsapp.enabled   — master flag (already in registry)
 *   - $sub_flag_key                  — channel-specific (per cron type)
 *
 * Plus the whatsapp_client itself gates on:
 *   - user opt-in (preference_manager)
 *   - mobile_number on file
 *   - DLT template approved
 * — so callers don't need to duplicate any of those checks here.
 *
 * Stream F additions (Wave E2 P4, 2026-05-25)
 * -------------------------------------------
 * Four content-event helpers wrap also_send() with their own template
 * keys + variable maps + a per-(userid, template_key) throttle that
 * suppresses duplicate sends within {@see THROTTLE_WINDOW} seconds.
 * They share the master `sentientia_whatsapp_content_notifications` flag —
 * default OFF — on top of the existing engagement.whatsapp.enabled
 * master switch.
 *
 *   send_new_course_notification($userid, $courseid)
 *   send_course_due_soon($userid, $courseid, $hours_remaining)
 *   send_certificate_ready($userid, $certificateid)
 *   send_path_milestone($userid, $pathid, $milestone_label)
 *
 * Each returns one of:
 *   'sent' | 'mocked' | 'opted_out' | 'no_template' | 'no_mobile'
 *   | 'failed' | 'throttled' | 'flag_off' | 'no_user' | 'no_record'
 *
 * @package local_sentientia_whatsapp
 */
class notification_bridge {

    /**
     * Stream F / Wave E2 P4 — master flag for the 4 content-event
     * notifications. Default OFF in db/feature_flags.php.
     */
    public const CONTENT_FLAG = 'sentientia_whatsapp_content_notifications';

    /** Throttle window: suppress duplicate (userid, template_key) sends within this many seconds. */
    public const THROTTLE_WINDOW = 6 * 3600;  // 6 hours

    /** Template keys for the 4 content-event triggers. */
    public const TPL_NEW_COURSE        = 'content_new_course';
    public const TPL_COURSE_DUE_SOON   = 'content_course_due_soon';
    public const TPL_CERTIFICATE_READY = 'content_certificate_ready';
    public const TPL_PATH_MILESTONE    = 'content_path_milestone';

    /**
     * Fire a WhatsApp send for a single user. Falls back to SMS via the
     * client's own internal cascade (if user has SMS opt-in + no WhatsApp).
     * Email is NOT touched — the caller already sent email upstream.
     *
     * @param \stdClass $user             Recipient (must have ->id)
     * @param string    $sub_flag_key     Feature flag (e.g. engagement.whatsapp.reminders)
     * @param string    $template_key     DLT template key (must be approved for live mode)
     * @param array     $variables        Template substitution vars
     * @param array     $opts             Optional ['language' => 'en'|'hi'|...]
     * @return string|null  'sent' / 'mocked' / 'opted_out' / 'no_template' / 'no_mobile'
     *                       / 'failed', or null if gates blocked the attempt.
     */
    public static function also_send(\stdClass $user,
                                      string $sub_flag_key,
                                      string $template_key,
                                      array $variables = [],
                                      array $opts = []): ?string {
        try {
            if (empty($user->id)) {
                return null;
            }

            // Gate 1 — master flag.
            if (!self::master_flag_on()) {
                return null;
            }

            // Gate 2 — channel-specific sub-flag.
            if (!self::sub_channel_on($sub_flag_key)) {
                return null;
            }

            // Delegate to the existing client. The client handles opt-in,
            // mobile-number presence, and DLT template approval checks.
            // Mock mode is the default — no external HTTP fires until
            // engagement.whatsapp.live_mode is also ON.
            $result = whatsapp_client::send_template(
                (int) $user->id,
                $template_key,
                $variables,
                $opts
            );

            return $result['status'] ?? null;

        } catch (\Throwable $e) {
            debugging(
                '[sentientia_whatsapp] also_send failed for user '
                . ($user->id ?? 0) . ' / ' . $template_key
                . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Check the master WhatsApp flag. Returns true if either:
     *   - feature_flags class isn't loaded (fail-open for dev environments
     *     where local_sentientia_platform may not be installed)
     *   - the flag is explicitly ON
     */
    private static function master_flag_on(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return true;
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled(
                'engagement.whatsapp.enabled');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check a sub-channel feature flag (e.g. engagement.whatsapp.reminders).
     */
    private static function sub_channel_on(string $flag_key): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return true;  // dev fail-open
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled($flag_key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Pick a DLT template key based on days-remaining bucket.
     *
     * @param int $days_remaining
     * @return string template_key — one of: deadline_7d, deadline_3d, deadline_1d
     */
    public static function pick_deadline_template(int $days_remaining): string {
        if ($days_remaining >= 7) {
            return 'deadline_7d';
        }
        if ($days_remaining >= 3) {
            return 'deadline_3d';
        }
        return 'deadline_1d';
    }

    // ═════════════════════════════════════════════════════════════════
    // Stream F / Wave E2 P4 — content-event triggers (2026-05-25)
    // ═════════════════════════════════════════════════════════════════

    /**
     * Fire a "new course published in your catalogue" WhatsApp nudge.
     *
     * Wired from \local_sentientia_whatsapp\observer::course_visibility_changed
     * which observes \core\event\course_updated and detects the
     * 0→1 visible transition.
     *
     * Variables substituted in the DLT template body:
     *   {{firstname}}    user's first name
     *   {{course_name}}  format_string'd course fullname
     *   {{course_url}}   absolute URL to /course/view.php?id=$courseid
     *
     * @param int $userid
     * @param int $courseid
     * @return string  See class doc for return-value vocabulary.
     */
    public static function send_new_course_notification(int $userid, int $courseid): string {
        global $DB;

        if (!self::content_flag_on()) {
            return 'flag_off';
        }

        $user = self::get_user($userid);
        if (!$user) {
            return 'no_user';
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname, visible');
        if (!$course) {
            return 'no_record';
        }

        if (!self::can_throttle_pass($userid, self::TPL_NEW_COURSE,
                'course:' . $courseid)) {
            return 'throttled';
        }

        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))
            ->out(false);

        return self::dispatch(
            $user,
            self::TPL_NEW_COURSE,
            [
                'firstname'   => (string) ($user->firstname ?? ''),
                'course_name' => format_string($course->fullname),
                'course_url'  => $course_url,
            ],
            'course:' . $courseid
        );
    }

    /**
     * Fire a "course due in <48h" WhatsApp nudge.
     *
     * Wired from local_sentientia_courses\task\course_reminder::send_one_reminder()
     * when the computed days_remaining bucket evaluates to <48h.
     *
     * Variables substituted in the DLT template body:
     *   {{firstname}}        user's first name
     *   {{course_name}}      format_string'd course fullname
     *   {{deadline}}         friendly deadline ("12 hours")
     *   {{course_url}}       absolute URL to /course/view.php?id=$courseid
     *
     * @param int $userid
     * @param int $courseid
     * @param int $hours_remaining
     * @return string
     */
    public static function send_course_due_soon(int $userid, int $courseid,
            int $hours_remaining): string {
        global $DB;

        if (!self::content_flag_on()) {
            return 'flag_off';
        }
        if ($hours_remaining < 0) {
            return 'no_record';  // already past deadline — different surface
        }

        $user = self::get_user($userid);
        if (!$user) {
            return 'no_user';
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, fullname');
        if (!$course) {
            return 'no_record';
        }

        if (!self::can_throttle_pass($userid, self::TPL_COURSE_DUE_SOON,
                'course:' . $courseid)) {
            return 'throttled';
        }

        $course_url = (new \moodle_url('/course/view.php', ['id' => $courseid]))
            ->out(false);

        return self::dispatch(
            $user,
            self::TPL_COURSE_DUE_SOON,
            [
                'firstname'   => (string) ($user->firstname ?? ''),
                'course_name' => format_string($course->fullname),
                'deadline'    => self::format_hours($hours_remaining),
                'course_url'  => $course_url,
            ],
            'course:' . $courseid
        );
    }

    /**
     * Fire a "your certificate is ready" WhatsApp nudge.
     *
     * Wired from \local_sentientia_whatsapp\observer::certificate_issued which
     * observes \tool_certificate\event\certificate_issued.
     *
     * Variables substituted in the DLT template body:
     *   {{firstname}}        user's first name
     *   {{course_name}}      format_string'd course fullname (may be blank
     *                        if certificate is not tied to a course)
     *   {{certificate_url}}  absolute URL to the certificate-view page
     *
     * @param int $userid
     * @param int $certificateid  Row ID in {tool_certificate_issues}
     * @return string
     */
    public static function send_certificate_ready(int $userid, int $certificateid): string {
        global $DB;

        if (!self::content_flag_on()) {
            return 'flag_off';
        }

        $user = self::get_user($userid);
        if (!$user) {
            return 'no_user';
        }

        $issue = $DB->get_record('tool_certificate_issues',
            ['id' => $certificateid],
            'id, userid, code, courseid'
        );
        if (!$issue) {
            return 'no_record';
        }

        // Defensive sanity: the issue's userid must match the caller's
        // userid. We never want to send a "your cert is ready" to a user
        // whose cert wasn't actually issued.
        if ((int) $issue->userid !== (int) $userid) {
            return 'no_record';
        }

        if (!self::can_throttle_pass($userid, self::TPL_CERTIFICATE_READY,
                'cert:' . $certificateid)) {
            return 'throttled';
        }

        $course_name = '';
        if (!empty($issue->courseid)) {
            $course = $DB->get_record('course', ['id' => $issue->courseid], 'fullname');
            if ($course) {
                $course_name = format_string($course->fullname);
            }
        }

        $cert_url = (new \moodle_url('/admin/tool/certificate/view.php',
            ['code' => $issue->code]))->out(false);

        return self::dispatch(
            $user,
            self::TPL_CERTIFICATE_READY,
            [
                'firstname'       => (string) ($user->firstname ?? ''),
                'course_name'     => $course_name,
                'certificate_url' => $cert_url,
            ],
            'cert:' . $certificateid
        );
    }

    /**
     * Fire a "you reached a learning-path milestone" WhatsApp nudge.
     *
     * Wired from \local_sentientia_whatsapp\observer::course_completed which
     * observes \core\event\course_completed, looks up the paths that
     * include the completed course, recomputes path progress, and calls
     * this method with a milestone_label of "25%", "50%", "75%", or
     * "100%" when the progress crosses one of those thresholds.
     *
     * Variables substituted in the DLT template body:
     *   {{firstname}}        user's first name
     *   {{path_name}}        format_string'd path name
     *   {{milestone_label}}  the milestone string, as passed in
     *   {{path_url}}         absolute URL to /local/sentientia_learningpath/view.php?id=$pathid
     *
     * @param int $userid
     * @param int $pathid
     * @param string $milestone_label
     * @return string
     */
    public static function send_path_milestone(int $userid, int $pathid,
            string $milestone_label): string {
        global $DB;

        if (!self::content_flag_on()) {
            return 'flag_off';
        }
        if ($milestone_label === '') {
            return 'no_record';
        }

        $user = self::get_user($userid);
        if (!$user) {
            return 'no_user';
        }

        $path = $DB->get_record('local_sentientia_learningpath',
            ['id' => $pathid], 'id, name');
        if (!$path) {
            return 'no_record';
        }

        // Throttle per (user, path, milestone) — same milestone within 6h
        // is the dedupe key. Different milestones on the same path
        // (e.g. 50% then 75% an hour later) pass through.
        if (!self::can_throttle_pass($userid, self::TPL_PATH_MILESTONE,
                'path:' . $pathid . ':' . $milestone_label)) {
            return 'throttled';
        }

        $path_url = (new \moodle_url('/local/sentientia_learningpath/view.php',
            ['id' => $pathid]))->out(false);

        return self::dispatch(
            $user,
            self::TPL_PATH_MILESTONE,
            [
                'firstname'       => (string) ($user->firstname ?? ''),
                'path_name'       => format_string($path->name),
                'milestone_label' => $milestone_label,
                'path_url'        => $path_url,
            ],
            'path:' . $pathid . ':' . $milestone_label
        );
    }

    // ═════════════════════════════════════════════════════════════════
    // Private helpers (Stream F)
    // ═════════════════════════════════════════════════════════════════

    /**
     * Resolve the content master flag. Public so observers can short-
     * circuit cheaply BEFORE doing expensive course/path lookups.
     */
    public static function content_flag_on(): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return true;  // dev fail-open mirrors master_flag_on()
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled(self::CONTENT_FLAG);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Throttle check: have we already reached THIS (userid, template_key,
     * context) within the last THROTTLE_WINDOW seconds?
     *
     * The throttle key is a deterministic marker `[ctx=<context_key>]`
     * which {@see dispatch} stamps into the send_log row's failure_reason
     * column on every send attempt. The same marker is reconstructed here
     * and matched with an escaped LIKE so SQL wildcards inside the context
     * (e.g. the literal `%` in a "50%" milestone) are treated literally.
     *
     * Context-key examples:
     *   'course:42'      — new course / due-soon (per course)
     *   'cert:7'         — certificate (per issue id)
     *   'path:3:50%'     — path milestone (per milestone, NOT per path)
     *
     * Only SENT / MOCKED / DELIVERED rows count — opted_out / failed /
     * no_template did NOT reach the user, so the next attempt should be
     * allowed to retry rather than being suppressed.
     *
     * @param int    $userid
     * @param string $template_key
     * @param string $context_key
     * @return bool true when the throttle has NOT triggered (send may proceed)
     */
    private static function can_throttle_pass(int $userid, string $template_key,
            string $context_key): bool {
        global $DB;

        $cutoff = time() - self::THROTTLE_WINDOW;
        // Escape the marker so a literal '%' (e.g. '50%') is matched
        // literally rather than acting as a LIKE wildcard.
        $marker = '%' . $DB->sql_like_escape(self::marker($context_key)) . '%';

        [$insql, $params] = $DB->get_in_or_equal(
            [send_log::STATUS_SENT, send_log::STATUS_MOCKED, send_log::STATUS_DELIVERED],
            SQL_PARAMS_NAMED, 'st');
        $params['uid'] = $userid;
        $params['tpl'] = $template_key;
        $params['cut'] = $cutoff;
        $params['ctx'] = $marker;

        $select = "userid = :uid "
                . "AND template_key = :tpl "
                . "AND timecreated > :cut "
                . "AND status $insql "
                . "AND " . $DB->sql_like('failure_reason', ':ctx', false);

        return !$DB->record_exists_select('local_sentientia_send_log', $select, $params);
    }

    /**
     * Send via the existing whatsapp_client (mock-mode by default — no
     * external HTTP until engagement.whatsapp.enabled + a Karix key are
     * both set), then stamp the throttle marker into the send_log row so
     * a subsequent {@see can_throttle_pass} can find it.
     *
     * The marker is the SAME context key the caller already passed to
     * can_throttle_pass — keeping the write side and the read side in
     * lockstep, so there's no chance of a check/stamp mismatch.
     *
     * @param \stdClass $user
     * @param string $template_key
     * @param array $variables
     * @param string $context_key  Deterministic throttle key (e.g. 'course:42')
     * @return string  Status vocabulary — see class doc.
     */
    private static function dispatch(\stdClass $user, string $template_key,
            array $variables, string $context_key): string {
        global $DB;

        try {
            // No engagement.whatsapp.enabled gate here — each public
            // caller already checked the content master flag, and the
            // whatsapp_client itself decides mock-vs-live from
            // engagement.whatsapp.enabled + the Karix key + noemailever.
            // Letting the client run (and mock) when the master flag is
            // OFF means the send_log still records what WOULD have fired,
            // which the analytics page surfaces — and keeps the mock-mode
            // contract the chip requires for tests.
            $result = whatsapp_client::send_template(
                (int) $user->id,
                $template_key,
                $variables,
                []
            );

            // Stamp the throttle marker into failure_reason. On mock sends
            // the client has already written a "MOCK: ..." string there;
            // we append. On real sends it's typically empty; we set it.
            if (!empty($result['log_id'])) {
                $row = $DB->get_record('local_sentientia_send_log',
                    ['id' => (int) $result['log_id']],
                    'id, failure_reason');
                if ($row) {
                    $row->failure_reason = trim(
                        (string) ($row->failure_reason ?? '')
                        . ' ' . self::marker($context_key));
                    $row->timeupdated = time();
                    $DB->update_record('local_sentientia_send_log', $row);
                }
            }

            return $result['status'] ?? 'failed';

        } catch (\Throwable $e) {
            debugging(
                '[sentientia_whatsapp] content dispatch failed for user '
                . ($user->id ?? 0) . ' / ' . $template_key . ': '
                . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return 'failed';
        }
    }

    /**
     * Build the deterministic throttle marker stamped into send_log.
     */
    private static function marker(string $context_key): string {
        return '[ctx=' . $context_key . ']';
    }

    /**
     * Fetch user record, returning null when the user is missing or
     * deleted/suspended. The whatsapp_client does additional checks
     * (opt-in + mobile number) so this helper only filters out the
     * basic invalid-recipient cases.
     */
    private static function get_user(int $userid): ?\stdClass {
        global $DB;
        if ($userid <= 0) {
            return null;
        }
        $user = $DB->get_record('user',
            ['id' => $userid, 'deleted' => 0, 'suspended' => 0],
            'id, firstname, lastname, email'
        );
        return $user ?: null;
    }

    /**
     * Pretty-print hours for the deadline variable.
     *  ≤1   → "1 hour"
     *  <24  → "$n hours"
     *  <48  → "$n hours" (preserve hours granularity for the <48h surface)
     *  ≥48  → "$days days" (we'd normally be on a different template, but
     *           defend against caller bugs by formatting cleanly)
     */
    private static function format_hours(int $hours): string {
        if ($hours <= 1) {
            return '1 hour';
        }
        if ($hours < 48) {
            return $hours . ' hours';
        }
        $days = (int) ceil($hours / 24);
        return $days . ' days';
    }
}
