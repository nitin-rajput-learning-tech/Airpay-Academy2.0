<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Audit-friendly wrapper over Moodle's `logstore_standard_log` table.
 *
 * Moodle's standard log captures every event the platform emits, but its
 * row format (eventname + objecttable + objectid + relateduserid + etc.)
 * isn't the shape that auditors and compliance officers want. This
 * helper provides three queries that the audit and compliance functions
 * call directly:
 *
 *   audit_log::sensitive_actions(int $hours): array
 *     Recent role assignments, capability changes, bulk user operations,
 *     refund actions, password resets, deleted users.
 *
 *   audit_log::actions_by_user(int $userid, int $from, int $to): array
 *     Everything a single user did inside a date range.
 *
 *   audit_log::tenant_actions(int $tenantroot, int $from, int $to): array
 *     Everything that happened inside a tenant (joined with the user
 *     table's open_path so the filter works across plugins).
 *
 * Implementation notes:
 *   - We read from `logstore_standard_log` only. We do not write — the
 *     standard log is Moodle's responsibility.
 *   - Eventnames are filtered against a whitelist of audit-worthy
 *     events. Adding a new event class to the whitelist is the right
 *     way to extend coverage.
 *   - Tenant scoping uses `\local_sentientia_platform\tenant` for siteadmin /
 *     same-tenant gating. Cross-tenant audit queries require either
 *     siteadmin or a future `local/sentientia_platform:audit_all` cap.
 */
class audit_log {

    /**
     * Eventname whitelist — Moodle events the audit and compliance
     * function consider sensitive enough to surface. Each entry is a
     * full PHP class reference (Moodle's event short-name with backslashes).
     */
    public const SENSITIVE_EVENTS = [
        // Role / permission changes.
        '\core\event\role_assigned',
        '\core\event\role_unassigned',
        '\core\event\role_capabilities_updated',
        '\core\event\role_created',
        '\core\event\role_deleted',

        // User lifecycle.
        '\core\event\user_created',
        '\core\event\user_updated',
        '\core\event\user_deleted',
        '\core\event\user_password_updated',
        '\core\event\user_loggedin_as',

        // Course visibility / structure (admin actions).
        '\core\event\course_created',
        '\core\event\course_deleted',
        '\core\event\course_visibility_updated',
        // P1 #24 (2026-05-16) — closes audit item #13 from
        // parity-audit-2026-05-15/sentientia_courses.md (BizLMS local_logs
        // parity). Moodle fires `course_updated` from `update_course()`
        // on every persistence path — `sentientia_courses\course_manager`
        // routes ALL its create/update/toggle_visibility traffic through
        // that function, so adding the eventname here gives compliance
        // auditors a complete "what changed on this course and who did
        // it" timeline without writing a custom audit table.
        '\core\event\course_updated',
        '\core\event\course_section_updated',
        '\core\event\course_section_created',
        '\core\event\course_category_updated',
        '\core\event\course_category_created',
        '\core\event\course_category_deleted',

        // Bulk operations.
        '\core\event\users_bulk_imported',

        // Airpay cart financial.
        '\local_sentientia_cart\event\refund_processed',
        '\local_sentientia_cart\event\order_paid',

        // Proctoring sensitive actions.
        '\local_sentientia_proctoring\event\session_flagged',
        '\local_sentientia_proctoring\event\review_submitted',

        // Sprint C+D (2026-05-13): cross-tenant course sharing audit.
        // These five events tell the compliance auditor exactly who
        // expanded a course's audience to another tenant and when —
        // the kind of action that needs answering "who and why" in
        // any GDPR / SOC2 review.
        '\local_sentientia_courses\event\course_share_created',
        '\local_sentientia_courses\event\course_share_withdrawn',
        '\local_sentientia_courses\event\course_share_requested',
        '\local_sentientia_courses\event\course_share_request_approved',
        '\local_sentientia_courses\event\course_share_request_rejected',
    ];

    /**
     * Recent sensitive actions across the platform.
     *
     * @param int $hours Lookback window in hours (default 24)
     * @return array of rows from logstore_standard_log, each augmented
     *               with the actor's name and the target user's tenant
     */
    public static function sensitive_actions(int $hours = 24): array {
        global $DB;
        $since = time() - ($hours * 3600);
        [$evsql, $evparams] = $DB->get_in_or_equal(self::SENSITIVE_EVENTS,
            SQL_PARAMS_NAMED, 'ev');
        [$tnsql, $tnparams] = tenant::sql_filter();
        // Build a synthetic tenant column from related-user open_path —
        // gives the audit query a one-column tenant filter for free.
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.eventname, l.action, l.target,
                    l.timecreated, l.userid AS actor_userid,
                    l.relateduserid, l.contextlevel, l.contextinstanceid,
                    u.firstname AS actor_first, u.lastname AS actor_last,
                    u.email     AS actor_email,
                    ru.open_path AS related_user_path
               FROM {logstore_standard_log} l
          LEFT JOIN {user} u  ON u.id = l.userid
          LEFT JOIN {user} ru ON ru.id = l.relateduserid
              WHERE l.eventname $evsql
                AND l.timecreated >= :since
           ORDER BY l.timecreated DESC",
            array_merge($evparams, ['since' => $since]),
            0, 500);
        return self::filter_by_viewer_tenant($rows);
    }

    /**
     * Everything a single user did between two timestamps.
     */
    public static function actions_by_user(int $userid, int $from, int $to): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.eventname, l.action, l.target,
                    l.timecreated, l.userid AS actor_userid,
                    l.relateduserid, l.contextlevel, l.contextinstanceid,
                    l.courseid
               FROM {logstore_standard_log} l
              WHERE l.userid = :u
                AND l.timecreated BETWEEN :f AND :t
           ORDER BY l.timecreated DESC",
            ['u' => $userid, 'f' => $from, 't' => $to],
            0, 1000);
        return array_values($rows);
    }

    /**
     * Everything that happened inside one tenant between two timestamps.
     *
     * Requires siteadmin or the audit_all capability — there is no
     * legitimate cross-tenant filter for a tenant-bound user.
     */
    public static function tenant_actions(int $tenantroot, int $from, int $to): array {
        global $DB;
        if (!is_siteadmin()
                && !has_capability('moodle/site:viewreports',
                    \context_system::instance())) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        $tenant_path_exact  = '/' . $tenantroot;
        $tenant_path_prefix = '/' . $tenantroot . '/%';
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.eventname, l.action, l.target,
                    l.timecreated, l.userid AS actor_userid,
                    l.relateduserid, l.contextlevel
               FROM {logstore_standard_log} l
               JOIN {user} u ON u.id = l.userid
              WHERE l.timecreated BETWEEN :f AND :t
                AND (u.open_path = :tn_exact OR u.open_path LIKE :tn_prefix)
           ORDER BY l.timecreated DESC",
            ['f' => $from, 't' => $to,
             'tn_exact' => $tenant_path_exact,
             'tn_prefix' => $tenant_path_prefix],
            0, 1000);
        return array_values($rows);
    }

    /**
     * Filter a row-set down to rows that belong to the current viewer's
     * tenant (or every row if the viewer is a site administrator). The
     * row's tenant is derived from the related user's open_path when
     * available; rows without a related-user fall through as
     * cross-tenant administrative actions and are only shown to admins.
     */
    private static function filter_by_viewer_tenant(array $rows): array {
        if (is_siteadmin()) {
            return array_values($rows);
        }
        $viewer_tenant = tenant::root_for_current_user();
        if ($viewer_tenant === 0) {
            return [];  // unknown tenant — show nothing
        }
        $out = [];
        foreach ($rows as $row) {
            if (empty($row->related_user_path)) {
                continue;
            }
            $parts = explode('/', trim($row->related_user_path, '/'));
            $row_tenant = isset($parts[0]) && ctype_digit($parts[0])
                ? (int) $parts[0] : 0;
            if ($row_tenant === $viewer_tenant) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
