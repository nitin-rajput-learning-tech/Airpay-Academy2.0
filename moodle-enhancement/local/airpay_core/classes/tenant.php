<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant resolution + access enforcement.
 *
 * Reason this exists: 10 of 11 BLOCKING findings from the Phase 8 audit
 * had the same shape — `require_capability($cap, context_system::instance())`
 * works as a site-vs-user gate but doesn't enforce
 * `costcenterid === viewer_tenant`. A Public-tenant manager with
 * `:viewallorders` legitimately holds the cap; the second check was
 * missing in every external. Centralising the check here means every
 * caller goes through one of these helpers, no plugin gets to forget.
 *
 * @package local_airpay_core
 */
class tenant {

    /** Known tenant root IDs in production. */
    public const VALID_TENANTS = [1, 77, 177];

    /**
     * Derive the tenant root from a user's open_path.
     * "/1/2/3"  → 1
     * "/77"     → 77
     * "/177/5"  → 177
     * "" / null → 0 (invalid)
     */
    public static function root_for_user(\stdClass $user): int {
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        if (!isset($parts[0]) || !ctype_digit($parts[0])) {
            return 0;
        }
        return (int) $parts[0];
    }

    /**
     * Same, but for the current $USER global. Returns 0 if not logged
     * in or path missing.
     */
    public static function root_for_current_user(): int {
        global $USER;
        if (empty($USER->id)) {
            return 0;
        }
        return self::root_for_user($USER);
    }

    /**
     * Validate a tenant id is one we recognise. Throws otherwise.
     */
    public static function assert_valid(int $tenantid): void {
        if (!in_array($tenantid, self::VALID_TENANTS, true)) {
            throw new \moodle_exception('error_invalidtenant', 'local_airpay_core');
        }
    }

    /**
     * Can the given viewer see/operate on resources of the given tenant?
     *
     * Site admins always pass.
     * Other users: viewer's tenant root must match resource tenant exactly.
     *
     * Use this AFTER `require_capability()`. The capability check answers
     * "do they hold the right?", this answers "for the right tenant?".
     *
     * @param int $resource_tenant The costcenterid the resource is scoped to
     * @param int|null $viewerid Defaults to current $USER
     * @return bool
     */
    public static function viewer_can_access(int $resource_tenant, ?int $viewerid = null): bool {
        global $DB, $USER;
        if ($viewerid === null || $viewerid === $USER->id) {
            if (is_siteadmin()) return true;
            return self::root_for_user($USER) === $resource_tenant;
        }
        if (is_siteadmin($viewerid)) return true;
        $viewer = $DB->get_record('user', ['id' => $viewerid], 'id, open_path');
        if (!$viewer) return false;
        return self::root_for_user($viewer) === $resource_tenant;
    }

    /**
     * Enforce viewer_can_access; throw `error_outoftenant` if not.
     *
     * Use after `require_capability()` and after fetching the resource:
     *
     *     $cart = $DB->get_record('local_airpay_cart_history', ['id' => $id], '*', MUST_EXIST);
     *     require_capability('local/airpay_cart:viewallorders', context_system::instance());
     *     \local_airpay_core\tenant::require_access((int)$cart->costcenterid);
     *     // ... safe to proceed
     */
    public static function require_access(int $resource_tenant, ?int $viewerid = null): void {
        if (!self::viewer_can_access($resource_tenant, $viewerid)) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_core');
        }
    }

    /**
     * Build the WHERE-clause fragment for tenant-scoping a SELECT.
     *
     * Used in get_records_sql() when listing tenant-bound resources:
     *
     *     [$tenantsql, $tenantargs] = \local_airpay_core\tenant::sql_filter('h');
     *     $rows = $DB->get_records_sql(
     *         "SELECT * FROM {local_airpay_cart_history} h WHERE $tenantsql",
     *         $tenantargs);
     *
     * Site admins get '1=1' (no filter). Tenant-bound users get
     * 'h.costcenterid = :tenantroot'.
     *
     * @param string $alias Table alias prefix (e.g. 'h' for h.costcenterid)
     * @return array{0: string, 1: array}
     */
    public static function sql_filter(string $alias = ''): array {
        $col = $alias === '' ? 'costcenterid' : "{$alias}.costcenterid";
        if (is_siteadmin()) {
            return ['1=1', []];
        }
        return [
            "$col = :_tenantroot",
            ['_tenantroot' => self::root_for_current_user()],
        ];
    }
}
