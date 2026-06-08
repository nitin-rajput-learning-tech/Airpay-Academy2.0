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

    /**
     * Known tenant root IDs in production.
     *
     * ADR-021 Wave 4: this is now the registry's LEGACY FALLBACK source —
     * local_sentientia_core\tenant_registry::valid_roots() returns this list
     * while tenant_registry_legacy is ON. When that flag is OFF the registry
     * reads the local_sentientia_tenant table instead.
     */
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
     *
     * ADR-021 Wave 4: delegates to the Sentientia tenant_registry when present
     * (class_exists-guarded), so flipping tenant_registry_legacy OFF routes
     * validation through the DB-backed registry. Default-ON legacy keeps this
     * byte-identical to the VALID_TENANTS membership test. Falls back to the
     * inline check if local_sentientia_core is absent (standalone airpay_core).
     */
    public static function assert_valid(int $tenantid): void {
        if (class_exists('\local_sentientia_core\tenant_registry')) {
            \local_sentientia_core\tenant_registry::assert_valid($tenantid);
            return;
        }
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
     *     $cart = $DB->get_record('local_sentientia_cart_history', ['id' => $id], '*', MUST_EXIST);
     *     require_capability('local/sentientia_cart:viewallorders', context_system::instance());
     *     \local_airpay_core\tenant::require_access((int)$cart->costcenterid);
     *     // ... safe to proceed
     */
    public static function require_access(int $resource_tenant, ?int $viewerid = null): void {
        if (!self::viewer_can_access($resource_tenant, $viewerid)) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_core');
        }
    }

    /**
     * Path-based equivalent of `require_access()` — for when the
     * resource carries an `open_path` string (e.g. `mdl_course.open_path`)
     * rather than a flat `costcenterid` integer.
     *
     * The check: viewer's tenant root must match the FIRST segment of
     * the resource's open_path, OR the resource path must descend from
     * the viewer's tenant root (`/77/x/y` allowed when viewer root is 77).
     *
     * Site admins always pass. Empty / null resource path: pass (legacy
     * unscoped rows treated as visible — matches the existing inline
     * pattern's behaviour in the back-ported callers).
     *
     * Use case: after fetching one resource record by id and verifying
     * the viewer has the right capability, call this to enforce that
     * the resource itself is in the viewer's tenant tree.
     *
     *     $course = $DB->get_record('course', ['id' => $id], 'open_path',
     *                                MUST_EXIST);
     *     \local_airpay_core\tenant::require_path_access(
     *         (string) $course->open_path);
     *
     * @param string $resource_path The resource's open_path (may be empty)
     * @param int|null $viewerid    Defaults to current $USER
     */
    public static function require_path_access(string $resource_path,
                                                ?int $viewerid = null): void {
        if ($resource_path === '') {
            return;  // legacy unscoped row — same tolerance as the inline pattern
        }
        global $DB, $USER;
        $is_admin = ($viewerid === null || $viewerid === ($USER->id ?? 0))
            ? is_siteadmin()
            : is_siteadmin($viewerid);
        if ($is_admin) {
            return;
        }
        // Derive the viewer's tenant root.
        if ($viewerid === null || $viewerid === ($USER->id ?? 0)) {
            $viewer_root = self::root_for_user($USER);
        } else {
            $viewer = $DB->get_record('user', ['id' => $viewerid], 'id, open_path');
            $viewer_root = $viewer ? self::root_for_user($viewer) : 0;
        }
        if ($viewer_root <= 0) {
            throw new \moodle_exception('error_outoftenant', 'local_airpay_core');
        }
        $viewer_path_exact  = '/' . $viewer_root;
        $viewer_path_prefix = '/' . $viewer_root . '/';
        if ($resource_path !== $viewer_path_exact
                && strpos($resource_path, $viewer_path_prefix) !== 0) {
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
     *         "SELECT * FROM {local_sentientia_cart_history} h WHERE $tenantsql",
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
            "$col = :aptenantroot",
            ['aptenantroot' => self::root_for_current_user()],
        ];
    }

    /**
     * Build the WHERE-clause fragment for tenant-scoping on a path-based
     * column (e.g. `mdl_course.open_path`, `mdl_local_sentientia_classroom.open_path`).
     *
     * The path-based pattern matches "/N" exactly OR "/N/..." as a prefix
     * where N is the caller's tenant root. Site admins always pass.
     *
     * Used when the table being filtered carries an open_path-style column
     * rather than a flat costcenterid. Replaces the inline
     * "explode('/', $USER->open_path)" pattern that was duplicated in
     * approximately 12 external WS classes prior to Phase 9.5.
     *
     * Examples:
     *
     *     [$tnsql, $tnargs] = \local_airpay_core\tenant::path_filter('c');
     *     $rows = $DB->get_records_sql(
     *         "SELECT * FROM {course} c WHERE $tnsql AND c.visible = 1",
     *         $tnargs);
     *
     *     // With NULL legacy-row tolerance:
     *     [$tnsql, $tnargs] = tenant::path_filter('c', 'open_path', true);
     *
     * @param string $alias  Table alias (defaults to no alias)
     * @param string $column Path column name (default: 'open_path')
     * @param bool   $allow_null Also match rows where the column is NULL
     *                          (legacy/unscoped rows). Default false.
     * @return array{0: string, 1: array}
     */
    public static function path_filter(string $alias = '',
                                        string $column = 'open_path',
                                        bool $allow_null = false): array {
        $col = $alias === '' ? $column : "{$alias}.{$column}";
        if (is_siteadmin()) {
            return ['1=1', []];
        }
        $root = self::root_for_current_user();
        if ($root <= 0) {
            // Unknown tenant — return a filter that matches nothing.
            // Defensive: a user without a valid tenant should not see
            // any tenant-scoped resources.
            return ['1=0', []];
        }
        $exact  = '/' . $root;
        $prefix = '/' . $root . '/%';
        $null_clause = $allow_null ? " OR {$col} IS NULL" : '';
        return [
            "({$col} = :appathexact OR {$col} LIKE :appathprefix{$null_clause})",
            [
                'appathexact'  => $exact,
                'appathprefix' => $prefix,
            ],
        ];
    }
}
