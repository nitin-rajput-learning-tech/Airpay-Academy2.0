<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Tenant registry seam — ADR-021, Sentientia independence Wave 4.
 *
 * The SINGLE abstraction the rest of Sentientia calls to answer "which tenant
 * roots exist?" and "which customer owns this root?", instead of reading the
 * hardcoded `local_sentientia_platform\tenant::VALID_TENANTS = [1, 77, 177]` allow-list
 * (the last BizLMS tenancy coupling — docs/DEPRECATION-SCHEDULE.md row 9).
 *
 * Surface:
 *  - valid_roots()            — the set of recognised tenant root ids.
 *  - is_valid(int)            — membership test (replaces in_array(...VALID_TENANTS)).
 *  - customer_of(int)         — the customer that owns a tenant root.
 *  - roots_for_customer(int)  — the tenant roots a customer owns.
 *
 * Behind the default-ON `tenant_registry_legacy` flag every method returns the
 * hardcoded [1, 77, 177] allow-list (delegating to `local_sentientia_platform\tenant`
 * when present, class_exists()-guarded with an inline fallback), so behaviour is
 * byte-identical to current production. When the flag is OFF the methods read
 * the Sentientia-owned registry tables (local_sentientia_tenant / _customer).
 * Until those tables are seeded the OFF path falls back to the legacy allow-list
 * with a DEBUG_DEVELOPER note — so a premature flag flip cannot reject a valid
 * tenant and lock anyone out.
 *
 * The seam carries NO hard dependency on local_sentientia_platform: the legacy
 * delegation is class_exists()-guarded with an inline [1,77,177] fallback, so
 * local_sentientia_core can ship standalone for Enterprise N.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_registry {

    /** Sentinel returned when no customer can be resolved. */
    public const NO_CUSTOMER = 0;

    /**
     * The implicit customer for customer-zero (Airpay) in legacy mode, and the
     * id the seed CLI assigns to the Airpay customer row so the table-backed and
     * legacy customer ids align. ADR-021: "Airpay = customer 1".
     */
    public const DEFAULT_CUSTOMER = 1;

    /**
     * The legacy hardcoded tenant allow-list — the single fallback constant.
     * Mirrors local_sentientia_platform\tenant::VALID_TENANTS for the standalone case.
     */
    private const LEGACY_ROOTS = [1, 77, 177];

    /**
     * Is the legacy hardcoded allow-list active?
     *
     * Default ON: an unset config (fresh install / pre-settings) is treated as
     * enabled so production behaviour never changes implicitly.
     */
    public static function use_legacy_registry(): bool {
        $v = get_config('local_sentientia_core', 'tenant_registry_legacy');
        return $v === false ? true : (bool) $v;
    }

    /**
     * The set of recognised tenant root ids.
     *
     * @return int[] Sorted, de-duplicated tenant root ids.
     */
    public static function valid_roots(): array {
        if (self::use_legacy_registry()) {
            return self::legacy_roots();
        }
        global $DB;
        if ($DB->get_manager()->table_exists('local_sentientia_tenant')) {
            $roots = $DB->get_fieldset_select(
                'local_sentientia_tenant', 'rootid', 'status = :s', ['s' => 'active']);
            if (!empty($roots)) {
                $roots = array_values(array_unique(array_map('intval', $roots)));
                sort($roots);
                return $roots;
            }
        }
        // Registry table absent or unseeded — fall back to legacy so the OFF
        // state can be exercised without rejecting a valid tenant.
        debugging('local_sentientia_core: tenant registry empty/absent; falling '
            . 'back to the legacy allow-list. Run admin/cli/seed_tenants.php.',
            DEBUG_DEVELOPER);
        return self::legacy_roots();
    }

    /**
     * Is the given id a recognised tenant root?
     *
     * @param int $root
     * @return bool
     */
    public static function is_valid(int $root): bool {
        return in_array($root, self::valid_roots(), true);
    }

    /**
     * Validate a tenant root, throwing on an unknown one. Mirrors the old
     * local_sentientia_platform\tenant::assert_valid() contract for migrated callers.
     *
     * @param int $root
     * @throws \moodle_exception
     */
    public static function assert_valid(int $root): void {
        if (!self::is_valid($root)) {
            throw new \moodle_exception('error_invalidtenant', 'local_sentientia_core');
        }
    }

    /**
     * The customer that owns a tenant root.
     *
     * Legacy: every recognised root belongs to the implicit customer-zero
     * (DEFAULT_CUSTOMER); an unknown root resolves to NO_CUSTOMER.
     *
     * @param int $root
     * @return int Customer id, or self::NO_CUSTOMER (0) if unresolved.
     */
    public static function customer_of(int $root): int {
        if (self::use_legacy_registry()) {
            return self::is_valid($root) ? self::DEFAULT_CUSTOMER : self::NO_CUSTOMER;
        }
        global $DB;
        if ($DB->get_manager()->table_exists('local_sentientia_tenant')) {
            $cid = $DB->get_field('local_sentientia_tenant', 'customerid',
                ['rootid' => $root, 'status' => 'active']);
            if ($cid !== false) {
                return (int) $cid;
            }
        }
        return self::is_valid($root) ? self::DEFAULT_CUSTOMER : self::NO_CUSTOMER;
    }

    /**
     * The tenant roots a customer owns.
     *
     * Legacy: the implicit customer-zero owns the whole allow-list; any other
     * customer owns nothing.
     *
     * @param int $customerid
     * @return int[] Tenant root ids (possibly empty).
     */
    public static function roots_for_customer(int $customerid): array {
        if (self::use_legacy_registry()) {
            return $customerid === self::DEFAULT_CUSTOMER ? self::legacy_roots() : [];
        }
        global $DB;
        if ($DB->get_manager()->table_exists('local_sentientia_tenant')) {
            $roots = $DB->get_fieldset_select('local_sentientia_tenant', 'rootid',
                'customerid = :c AND status = :s', ['c' => $customerid, 's' => 'active']);
            if (!empty($roots)) {
                $roots = array_values(array_unique(array_map('intval', $roots)));
                sort($roots);
                return $roots;
            }
        }
        return $customerid === self::DEFAULT_CUSTOMER ? self::legacy_roots() : [];
    }

    /**
     * The legacy hardcoded allow-list.
     *
     * Delegates to `local_sentientia_platform\tenant::VALID_TENANTS` when present (single
     * source of truth); otherwise returns the inline constant so the seam carries
     * no hard dependency on local_sentientia_platform.
     *
     * @return int[]
     */
    private static function legacy_roots(): array {
        if (class_exists('\local_sentientia_platform\tenant')
                && defined('\local_sentientia_platform\tenant::VALID_TENANTS')) {
            return \local_sentientia_platform\tenant::VALID_TENANTS;
        }
        return self::LEGACY_ROOTS;
    }
}
