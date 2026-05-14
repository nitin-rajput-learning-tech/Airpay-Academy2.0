<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for local_airpay_emails.
 *
 * Sprint B (2026-05-13). One handler today —
 * `\core\event\course_completed` — orchestrates two things:
 *
 *   1. Sends the user a polished congratulations email with the
 *      tool_certificate PDF attached (if one was issued for the
 *      course). The rule that controls the email body and channel
 *      list is `rule_type = 'course_completed'`, seeded by the
 *      Sprint B upgrade migration.
 *
 *   2. Stamps any pre-existing reminder log rows for the same
 *      (user, course) as `status='suppressed_completion'`, so
 *      dashboards/analytics can distinguish "user got reminders
 *      AND finished" from "user got reminders AND ignored them".
 *
 * The observer is intentionally fail-safe: course completion is a
 * Moodle-core operation we must NOT block. Every external call is
 * wrapped in try/catch and any failure becomes a debugging() trace
 * rather than a thrown exception.
 *
 * @package local_airpay_emails
 */
class observer {

    /**
     * Handler for the course-completed event.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        global $DB;

        try {
            $userid   = (int) $event->relateduserid;
            $courseid = (int) $event->courseid;

            if ($userid <= 0 || $courseid <= 0) {
                return;
            }

            // Step 2 first (cheap, no external deps) — mark reminders
            // as suppressed_completion. We do this before sending the
            // congrats email so even if email-send fails, the audit
            // trail still reflects that the user completed.
            delivery_log::mark_reminders_suppressed_on_completion($userid, $courseid);

            // Look up the user + course objects we need for the send.
            $user = $DB->get_record('user',
                ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$user || !$course) {
                return;
            }

            // Find the active course_completed rule. tenant_id=0 is the
            // global default; tenant-specific overrides (1, 77, 177) can
            // be added later via the rule manager UI.
            $rule = self::find_rule_for_user('course_completed', $user);
            if (!$rule) {
                return;
            }

            // Day-2 (2026-05-14): respect the admin toggle. Default ON.
            // When ticked OFF (e.g. tool_certificate is misbehaving),
            // the completion email still fires, just without the
            // PDF attachment. The decision is recorded in the audit
            // log via the empty `certificate_issue_id` column.
            $attach_pdf = get_config('local_airpay_emails',
                'attach_certificate_pdf');
            // Treat unconfigured (false / null) as ON to preserve
            // current behaviour for installs that haven't visited
            // the settings page.
            $attach_pdf = ($attach_pdf === false || $attach_pdf === null)
                ? true
                : (bool) $attach_pdf;

            // Look up any tool_certificate issue. May return null —
            // that's fine, we send the email without an attachment.
            // When the admin toggle is OFF, we skip the lookup
            // entirely (saves a DB hit per completion).
            $issue = $attach_pdf
                ? certificate_helper::get_issue_for_user_course($userid, $courseid)
                : null;

            // Compute completion details for the template context.
            $cc = $DB->get_record('course_completions',
                ['userid' => $userid, 'course' => $courseid]);
            $completion_ts = $cc && $cc->timecompleted ? (int) $cc->timecompleted : time();

            $context = [
                'firstname'      => format_string($user->firstname),
                'course_name'    => format_string($course->fullname),
                'course_url'     => (new \moodle_url('/course/view.php',
                    ['id' => $courseid]))->out(false),
                'completion_date' => userdate($completion_ts, '%d %B %Y'),
                'has_certificate' => $issue !== null,
                'certificate_url' => $issue
                    ? self::issue_url_for_template($issue)
                    : null,
                'subject'        => "Congratulations on completing "
                    . format_string($course->fullname),
            ];

            $opts = [];
            if ($issue) {
                $opts['certificate_issue'] = $issue;
            }

            notification_sender::send($rule, $user, $context, $courseid, $opts);

        } catch (\Throwable $e) {
            // Critical: NEVER bubble up. Course completion is a
            // Moodle-core path. Just trace and move on.
            debugging('local_airpay_emails observer failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER);
        }
    }

    /**
     * Resolve the email rule that should fire for this user's tenant.
     *
     * Precedence: a rule with `tenant_id=<user's tenant root>` wins
     * over the global rule (`tenant_id=0`). If neither exists,
     * returns null.
     *
     * @param string $rule_type
     * @param object $user
     * @return \stdClass|null
     */
    private static function find_rule_for_user(string $rule_type,
                                                 object $user): ?\stdClass {
        global $DB;

        // Derive tenant root from open_path (e.g. '/1/183/45' → 1).
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $tenant_root = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;

        // Try tenant-specific rule first.
        if ($tenant_root > 0) {
            $rule = $DB->get_record('local_airpay_email_rules', [
                'rule_type' => $rule_type,
                'tenant_id' => $tenant_root,
                'enabled'   => 1,
            ]);
            if ($rule) {
                return $rule;
            }
        }

        // Fall back to the global default (tenant_id=0).
        $rule = $DB->get_record('local_airpay_email_rules', [
            'rule_type' => $rule_type,
            'tenant_id' => 0,
            'enabled'   => 1,
        ]);
        return $rule ?: null;
    }

    /**
     * Build the public URL to view the certificate (used in the email
     * template as a "View online" link alongside the PDF attachment).
     *
     * @param \stdClass $issue tool_certificate_issues row
     * @return string
     */
    private static function issue_url_for_template(\stdClass $issue): string {
        // The verify endpoint uses the issue's `code` (UUID-like string)
        // as the public identifier — never the numeric id. This is
        // tool_certificate's published URL pattern.
        return (new \moodle_url('/admin/tool/certificate/index.php',
            ['code' => $issue->code]))->out(false);
    }
}
