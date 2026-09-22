<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

/**
 * Access decisions for the analytics dashboard.
 *
 * Centralises three questions the three entry points used to answer with
 * three separate copies of the same gate:
 *
 *   - may this user open the dashboard at all?      can_view()
 *   - may they see orgs other than their own?       can_view_all_orgs()
 *   - may they download the per-learner CSV?        can_export()
 *
 * and one question none of them answered safely:
 *
 *   - which org subtree may they see?               visible_org_path()
 *
 * WHY visible_org_path() FAILS CLOSED
 * -----------------------------------
 * analytics_manager treats an EMPTY org path as "no filter" - i.e. the whole
 * site (see get_kpis(): if (!empty($orgpath))). Both callers used to hand it
 * an empty or guessed path on the unresolvable-tenant branch:
 *
 *   index.php   $orgpath = '/' . ($parts[1] ?? '1');           -> silently Airpay
 *   export.php  $orgpath = tenant_manager::get_tenant_path();  -> '' -> WHOLE SITE
 *
 * So a non-admin whose open_path was missing or malformed was scoped to
 * another tenant's data (index) or to every tenant at once (export). Neither
 * raised an error; the numbers were simply somebody else's. This method
 * returns null for "no scope could be established", and callers refuse.
 *
 * WHY EVERY CHECK IS TWO-STEP
 * ---------------------------
 * Moodle capabilities flow DOWN the context tree, never up. The BizLMS
 * org-admin shell is assigned at CONTEXT_COURSECAT, so a system-context
 * has_capability() alone never sees it. Mirrors
 * local_sentientia_compliance_report\permission::can_export().
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /** @var string Open the dashboard. */
    public const VIEW_CAPABILITY = 'local/sentientia_analytics:view';

    /** @var string See orgs beyond the caller's own subtree. */
    public const VIEWALL_CAPABILITY = 'local/sentientia_analytics:viewallorgs';

    /** @var string Download the per-learner CSV. */
    public const EXPORT_CAPABILITY = 'local/sentientia_analytics:export';

    /**
     * Whether the user may open the analytics dashboard.
     *
     * @param int|null $userid User to test, or null for the current $USER.
     * @return bool
     */
    public static function can_view(?int $userid = null): bool {
        return self::has_cap_anywhere(self::VIEW_CAPABILITY, $userid);
    }

    /**
     * Whether the user may see every org rather than only their own subtree.
     *
     * @param int|null $userid User to test, or null for the current $USER.
     * @return bool
     */
    public static function can_view_all_orgs(?int $userid = null): bool {
        return self::has_cap_anywhere(self::VIEWALL_CAPABILITY, $userid);
    }

    /**
     * Whether the user may download the CSV export.
     *
     * @param int|null $userid User to test, or null for the current $USER.
     * @return bool
     */
    public static function can_export(?int $userid = null): bool {
        return self::has_cap_anywhere(self::EXPORT_CAPABILITY, $userid);
    }

    /**
     * The org path this user's analytics must be clamped to.
     *
     * @param int|null $userid User to test, or null for the current $USER.
     * @return string|null Empty string for unrestricted (site-wide), a '/N'
     *                     tenant path to clamp to that subtree, or NULL when
     *                     no scope could be established - which callers must
     *                     treat as deny, never as unrestricted.
     */
    public static function visible_org_path(?int $userid = null): ?string {
        global $USER, $DB;

        if (self::can_view_all_orgs($userid)) {
            return '';
        }

        if ($userid === null || (int) $userid === (int) $USER->id) {
            $openpath = $USER->open_path ?? '';
        } else {
            $openpath = (string) $DB->get_field('user', 'open_path', ['id' => $userid]);
        }

        $tenantid = 0;
        $parts = explode('/', trim((string) $openpath, '/'));
        if (isset($parts[0]) && ctype_digit((string) $parts[0])) {
            $tenantid = (int) $parts[0];
        }

        // No parseable tenant root. Refuse rather than guess: the two guesses
        // available here are "tenant 1" and "all tenants", and both are
        // somebody else's data.
        if ($tenantid <= 0) {
            return null;
        }

        return '/' . $tenantid;
    }

    /**
     * Restrict a requested org path to what this user is allowed to see.
     *
     * Used for the ?orgid= selector: a :viewallorgs holder may pick any org,
     * anyone else is pinned to their own tenant regardless of what they ask
     * for, so hand-editing the query string cannot widen the scope.
     *
     * @param string $requested Org path from the request, empty for none.
     * @param int|null $userid User to test, or null for the current $USER.
     * @return string|null The path to query with, or NULL to deny.
     */
    public static function clamp_org_path(string $requested, ?int $userid = null): ?string {
        $allowed = self::visible_org_path($userid);

        if ($allowed === null) {
            return null;
        }

        // Unrestricted viewer: honour whatever they picked.
        if ($allowed === '') {
            return $requested;
        }

        $requested = rtrim(trim($requested), '/');
        if ($requested === '') {
            return $allowed;
        }

        // Inside their own subtree? Slash-terminated on purpose: '/1' must not
        // authorise '/177'. See local_sentientia_platform\tenant.
        if ($requested === $allowed || strpos($requested, $allowed . '/') === 0) {
            return $requested;
        }

        return $allowed;
    }

    /**
     * has_capability() at system context, then at each course-category context
     * where this user actually holds a role assignment.
     *
     * The category pass is bounded by the user's own assignments (typically
     * one to three rows), so it is not a category scan.
     *
     * @param string $capability
     * @param int|null $userid
     * @return bool
     */
    private static function has_cap_anywhere(string $capability, ?int $userid = null): bool {
        global $USER, $DB;

        $userid = $userid ?: (int) $USER->id;

        if (has_capability($capability, \context_system::instance(), $userid)) {
            return true;
        }

        $catcontextids = $DB->get_fieldset_sql(
            "SELECT DISTINCT ctx.id
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.userid = :uid
                AND ctx.contextlevel = :catlevel",
            ['uid' => $userid, 'catlevel' => CONTEXT_COURSECAT]);

        foreach ($catcontextids as $ctxid) {
            if (has_capability($capability, \context::instance_by_id($ctxid), $userid)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Grant the new capabilities to the roles that should hold them on this
     * deployment. Called from db/install.php (fresh installs) and
     * db/upgrade.php (existing installs). Idempotent.
     *
     * The archetype defaults in db/access.php already cover every role with
     * the `manager` archetype, which on the Airpay deployment means `manager`
     * (id 1) and `administrator` (id 9) - the intended audience, and a
     * superset of who could actually reach the dashboard before today.
     *
     * This method exists for customers whose admin-tier role carries no
     * archetype: it additionally grants to any role already holding
     * local/sentientia_courses:manage. It is a no-op on Airpay, where both
     * holders of that capability are archetype `manager` anyway.
     *
     * Deliberately NOT keyed on the pre-ADR-025 name local/courses:manage.
     * That capability is undefined, so keying on it grants nothing while
     * reading as though it preserves something - which is exactly what
     * local_sentientia_compliance_report::grant_export_to_default_roles()
     * does today. See docs/cutover/GAP-CLOSURE-PLAN-2026-09-22.md.
     */
    public static function grant_to_default_roles(): void {
        global $DB;

        // Register the capabilities before assigning them. Core also syncs
        // after the install/upgrade callback, but the ordering relative to
        // this method is not guaranteed. update_capabilities() is idempotent.
        update_capabilities('local_sentientia_analytics');

        $systemcontext = \context_system::instance();

        $roleids = $DB->get_fieldset_select('role_capabilities', 'DISTINCT roleid',
            'capability = :cap AND permission = :perm',
            ['cap' => 'local/sentientia_courses:manage', 'perm' => CAP_ALLOW]);

        foreach ($roleids as $roleid) {
            foreach ([self::VIEW_CAPABILITY, self::VIEWALL_CAPABILITY, self::EXPORT_CAPABILITY] as $cap) {
                assign_capability($cap, CAP_ALLOW, $roleid, $systemcontext->id, true);
            }
        }

        $systemcontext->mark_dirty();
    }
}
