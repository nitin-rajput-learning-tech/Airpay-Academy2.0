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
 *     table's open_path so the filter works across plugins). A row with
 *     no real actor (userid 0 or the guest user, e.g. the payment
 *     gateway callback) belongs to its related user's tenant.
 *
 * Implementation notes:
 *   - We read from `logstore_standard_log` only. We do not write — the
 *     standard log is Moodle's responsibility.
 *   - Eventnames are filtered against a whitelist of audit-worthy
 *     events. Adding a new event class to the whitelist is the right
 *     way to extend coverage.
 *   - Tenant scoping (ADR-031, 2026-09-25): moodle/site:viewreports says a
 *     caller may read audit trails; it never says WHICH tenant's. Every
 *     query is confined to the caller's own tenant unless
 *     `tenant::is_cross_tenant()` (a site admin or a holder of
 *     local/sentientia_platform:crosstenant). A caller with no resolvable
 *     tenant gets nothing. viewreports is a core capability that defaults
 *     to the manager archetype, and tenant admins hold manager-archetype
 *     roles at system context, so it must never unscope on its own.
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
        // A role's permissions changing fires capability_assigned / capability_unassigned (since Moodle 3.8).
        // role_capabilities_updated is deprecated and never fires, so listing it audited nothing (2026-11-13).
        '\core\event\capability_assigned',
        '\core\event\capability_unassigned',
        '\core\event\role_created',
        '\core\event\role_deleted',

        // User lifecycle.
        '\core\event\user_created',
        '\core\event\user_updated',
        '\core\event\user_deleted',
        '\core\event\user_password_updated',
        // The event class is user_loggedinas (one word); the old spelling matched no log row.
        '\core\event\user_loggedinas',

        // Course visibility / structure (admin actions).
        '\core\event\course_created',
        '\core\event\course_deleted',
        // A course's visibility change fires course_updated (below); there is no course_visibility_updated event.
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
        // ADR-031: the capability is WHAT (may read audit trails);
        // filter_by_viewer_tenant() below is WHERE.
        if (!tenant::is_cross_tenant()) {
            require_capability('moodle/site:viewreports', \context_system::instance());
        }
        $since = time() - ($hours * 3600);
        [$evsql, $evparams] = $DB->get_in_or_equal(self::SENSITIVE_EVENTS,
            SQL_PARAMS_NAMED, 'ev');
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
     *
     * ADR-031: anyone may read their own trail. Reading somebody else's needs
     * moodle/site:viewreports AND the target in the caller's tenant, unless the
     * caller is cross-tenant. Until 2026-09-25 this method had no gate at all.
     *
     * A cross-tenant principal (site admin or :crosstenant holder) is never a
     * scoped caller's to read, even when their own open_path sits under the
     * caller's tenant (Airpay platform staff are expected to sit under /1):
     * their trail spans every tenant, and its courseid, relateduserid and
     * contextinstanceid values name other tenants' users and courses
     * (adversarial review S4, 2026-09-25).
     *
     * @throws \required_capability_exception without viewreports
     * @throws \moodle_exception error_outoftenant for a user outside the caller's tenant,
     *                           or for a cross-tenant principal
     */
    public static function actions_by_user(int $userid, int $from, int $to): array {
        global $DB, $USER;
        $self = isloggedin() && !isguestuser() && (int) $USER->id === $userid;
        if (!$self && !tenant::is_cross_tenant()) {
            require_capability('moodle/site:viewreports', \context_system::instance());
            tenant::require_same_tenant_user($userid);
            if (tenant::is_cross_tenant($userid)) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
        }
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
     * ADR-031: a cross-tenant caller may name any tenant. Anyone else needs
     * moodle/site:viewreports and may name only their OWN tenant. Until
     * 2026-09-25 holding viewreports (manager archetype, so every tenant admin)
     * was enough to read any tenant's trail.
     *
     * Rows are selected by the ACTOR's open_path. For a scoped caller, a row
     * whose actor is a cross-tenant principal (site admin or :crosstenant
     * holder sitting under this tenant) is kept only when its related user is
     * in this tenant too: what platform staff did to this tenant's people
     * stays visible, what they did elsewhere does not (adversarial review S4,
     * 2026-09-25). Cross-tenant callers see every row, as before.
     *
     * A row with no real actor is selected by its RELATED user's open_path
     * instead (decision A5, 2026-11-13). "No real actor" means userid 0 (a
     * request run by no one: the payment gateway's callback.php runs with
     * NO_MOODLE_COOKIES, so cart_manager::mark_paid() logs order_paid with
     * userid 0 and the buyer as relateduserid) or the guest user
     * ($CFG->siteguest: one site-wide account, so its own open_path says
     * nothing about a tenant). Such a row is in tenant N's trail when its
     * related user is at /N or below it. One with no related user, a related
     * user outside /N, or the guest as related user is in no tenant's trail.
     * For a scoped caller, one whose related user is a cross-tenant principal
     * is left out too: the related user stands in for the actor here, and a
     * principal's own activity is not a scoped caller's to read (S4, as in
     * actions_by_user()). Cross-tenant callers see those rows. (Not changed
     * here: a real-actor row in which a principal acts on themselves still
     * passes the actor rule above.)
     *
     * Before this, an inner JOIN on the actor dropped every userid 0 row (no
     * user has id 0), so an order paid through the gateway never reached its
     * tenant's trail; a guest row showed only if the guest account's own
     * open_path sat under the tenant, whoever it was about.
     *
     * Reach: there is no eventname filter, so this covers EVERY actor-less
     * row, not only order_paid. Core paths found by reading the Moodle 5.1
     * code (not seen on a running site): a forgotten-password reset logs
     * user_password_updated with userid 0 (login/lib.php sets the password
     * before complete_user_login()); a login whose stored hash is a legacy
     * bcrypt hash (password_is_legacy_hash(), as a Moodle 4.1 import would
     * carry) is rehashed inside authentication and logs the same event with
     * userid 0, once per user; self-registration logs user_created with
     * userid 0. Other paths that log with no user set (for example CLI
     * scripts) were not checked one by one. The result is capped at 1000
     * rows, newest first, so a burst of actor-less rows inside the window
     * (such as first logins after a data import) can push older real-actor
     * rows out. Whether to limit the actor-less rows to SENSITIVE_EVENTS is
     * open (for Nitin).
     *
     * @throws \required_capability_exception without viewreports
     * @throws \moodle_exception error_outoftenant for another tenant, or a caller with none
     */
    public static function tenant_actions(int $tenantroot, int $from, int $to): array {
        global $CFG, $DB;
        $actorscope = '';
        $actorparams = [];
        $crossids = [];
        if (!tenant::is_cross_tenant()) {
            require_capability('moodle/site:viewreports', \context_system::instance());
            $viewerroot = tenant::root_for_current_user();
            if ($viewerroot <= 0 || $viewerroot !== $tenantroot) {
                throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
            }
            $crossids = tenant::cross_tenant_userids();
            if ($crossids) {
                [$xsql, $xparams] = $DB->get_in_or_equal($crossids, SQL_PARAMS_NAMED, 'tnxt', false);
                [$rsql, $rparams] = tenant::path_descendant_filter('/' . $tenantroot, 'ru', 'open_path', 'tnrel');
                $actorscope = " AND (l.userid $xsql OR $rsql)";
                $actorparams = $xparams + $rparams;
            }
        }
        $tenant_path_exact  = '/' . $tenantroot;
        $tenant_path_prefix = '/' . $tenantroot . '/%';
        // Actor-less rows (see the docblock): userid 0 and the guest user. A real actor is anyone else, and
        // keeps the actor rule above unchanged. Distinct parameter prefixes: Moodle refuses a named
        // parameter used twice in one query.
        $guestid = (int) ($CFG->siteguest ?? 0);
        $noactorids = array_values(array_unique([0, $guestid]));
        [$realsql, $realparams] = $DB->get_in_or_equal($noactorids, SQL_PARAMS_NAMED, 'tnreal', false);
        [$noactsql, $noactparams] = $DB->get_in_or_equal($noactorids, SQL_PARAMS_NAMED, 'tnnoact');
        [$relsql, $relparams] = tenant::path_descendant_filter('/' . $tenantroot, 'ru', 'open_path', 'tnactless');
        // In an actor-less row the related user stands in for the actor, so it must be one of the
        // tenant's own people: never the guest (its open_path says nothing about a tenant), and for a
        // scoped caller never a cross-tenant principal, whose own activity is not a scoped caller's to
        // read (S4; $crossids stays empty for a cross-tenant caller, who sees these rows).
        $notrelated = array_values(array_unique(array_merge([$guestid], $crossids)));
        [$nrsql, $nrparams] = $DB->get_in_or_equal($notrelated, SQL_PARAMS_NAMED, 'tnxr', false);
        $rows = $DB->get_records_sql(
            "SELECT l.id, l.eventname, l.action, l.target,
                    l.timecreated, l.userid AS actor_userid,
                    l.relateduserid, l.contextlevel
               FROM {logstore_standard_log} l
          LEFT JOIN {user} u ON u.id = l.userid
          LEFT JOIN {user} ru ON ru.id = l.relateduserid
              WHERE l.timecreated BETWEEN :f AND :t
                AND ((l.userid $realsql
                      AND (u.open_path = :tn_exact OR u.open_path LIKE :tn_prefix)
                          $actorscope)
                     OR (l.userid $noactsql AND l.relateduserid $nrsql AND $relsql))
           ORDER BY l.timecreated DESC",
            array_merge(['f' => $from, 't' => $to,
             'tn_exact' => $tenant_path_exact,
             'tn_prefix' => $tenant_path_prefix], $actorparams, $realparams, $noactparams, $nrparams, $relparams),
            0, 1000);
        return array_values($rows);
    }

    /**
     * Filter a row-set down to rows that belong to the current viewer's
     * tenant (or every row if the viewer is cross-tenant, ADR-031). The
     * row's tenant is derived from the related user's open_path when
     * available; rows without a related-user fall through as
     * cross-tenant administrative actions and are only shown to
     * cross-tenant viewers.
     */
    private static function filter_by_viewer_tenant(array $rows): array {
        if (tenant::is_cross_tenant()) {
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
