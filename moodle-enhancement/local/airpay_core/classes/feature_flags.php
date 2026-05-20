<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Feature flag resolver — Phase A0 (2026-05-14) + Session 2 (2026-05-20).
 *
 * The runtime API that every gated feature consults to decide whether
 * to render its UI, fire its background job, or fall back gracefully.
 *
 * Phase A0 (2026-05-14) shipped 3-level resolution
 * -------------------------------------------------
 *   1. Row in {local_airpay_feature_flags} for (key, current_tenant)
 *   2. Row in {local_airpay_feature_flags} for (key, 0)            (global override)
 *   3. Flag's registered default from db/feature_flags.php          (registered)
 *   4. false                                                         (fail-safe)
 *
 * Session 2 / ADR-002 (2026-05-20) extends to 5-level resolution
 * --------------------------------------------------------------
 * For a user in customer C, tenant T:
 *   1. (key, customer=C, tenant=T)   ← MOST SPECIFIC: tenant within customer
 *   2. (key, customer=C, tenant=0)   ← customer-wide override
 *   3. (key, customer=0, tenant=T)   ← legacy tenant override (pre-multi-customer)
 *   4. (key, customer=0, tenant=0)   ← global override
 *   5. registered default
 *   6. false                          ← fail-safe
 *
 * Steps (1) and (2) are gated behind the
 * `sentientia.customer_level_flags.enabled` flag (default OFF). When that
 * flag is OFF, the resolver short-circuits to the original 3-level logic —
 * making this an additive, behaviourally-identical change for Airpay's
 * existing production state. When the flag is ON, the full 5-level
 * precedence runs.
 *
 * Backwards compatibility
 * -----------------------
 * Every existing override row gets customer_id=0 via the Session 2
 * migration's column default. They continue to match at step (3) or (4)
 * exactly as before. All Phase A0 PHPUnit tests pass unchanged.
 *
 * Registry discovery
 * -------------------
 * Each plugin contributes a `db/feature_flags.php` that returns an
 * array of [flag_key => ['default' => bool, 'description' => str]].
 * On first call this class walks every installed plugin, reads its
 * registry file, and caches the merged registry in MUC for 60s.
 *
 * Performance
 * -----------
 * - Registry is process-cached after first build (no DB hit on
 *   subsequent calls within the same request).
 * - Override lookups are batched: a single SELECT pulls every
 *   override row at first lookup, then served from the static cache.
 *
 * @package local_airpay_core
 */
class feature_flags {

    /** Feature flag that gates Session 2's customer-level resolution. */
    public const CUSTOMER_LEVEL_FLAG = 'sentientia.customer_level_flags.enabled';

    /** @var array<string, array{default: bool, description: string}>|null Registry cache. */
    private static $registry = null;

    /**
     * Override cache. Three-level map keyed by [flag_key][customer_id][tenant_id] => bool.
     * Single batched SELECT populates the whole thing on first lookup.
     *
     * @var array<string, array<int, array<int, bool>>>|null
     */
    private static $overrides = null;

    /**
     * Is the flag enabled for the current user's customer + tenant?
     *
     * @param string $key Three-level dotted flag key (e.g. 'ai.assistant.enabled')
     * @return bool
     */
    public static function is_enabled(string $key): bool {
        return self::is_enabled_for(
            $key,
            customer::current(),
            self::current_tenant_root()
        );
    }

    /**
     * Is the flag enabled for a specific tenant under the current user's
     * customer?
     *
     * Backwards-compatible entry point — every Phase A0 caller passing only
     * (key, tenant_id) keeps working. The customer dimension defaults to
     * the current user's customer via {@see customer::current()}.
     *
     * @param string $key       Flag key
     * @param int    $tenant_id Tenant root (1, 77, 177, ...) or 0 for "all tenants in customer"
     * @return bool
     */
    public static function is_enabled_for_tenant(string $key, int $tenant_id): bool {
        return self::is_enabled_for($key, customer::current(), $tenant_id);
    }

    /**
     * Is the flag enabled for a specific (customer, tenant) pair?
     *
     * The 5-level resolver. Used by the Switchboard rendering one
     * customer's view while admin is in another, and by code that needs
     * explicit customer scoping (rare today, normal tomorrow).
     *
     * @param string $key         Flag key
     * @param int    $customer_id Customer id (1=Airpay) or 0 for "global default view"
     * @param int    $tenant_id   Tenant root (1, 77, 177, ...) or 0 for "customer-wide view"
     * @return bool
     */
    public static function is_enabled_for(string $key, int $customer_id, int $tenant_id): bool {

        // Recursion guard: looking up the gate flag itself? Skip the
        // customer-aware path entirely — the gate flag has no customer
        // scope, its job is to gate OTHER flags' customer scope. Going
        // through the customer-aware path would call self::is_enabled_for()
        // recursively and stack-overflow.
        if ($key === self::CUSTOMER_LEVEL_FLAG) {
            return self::resolve_legacy($key, $tenant_id);
        }

        // Is the customer-level resolution layer enabled?
        $customer_layer_on = self::resolve_legacy(self::CUSTOMER_LEVEL_FLAG, 0);

        // Steps 1 + 2: customer-scoped resolution, only when the layer
        // is enabled AND we have a real (non-default) customer.
        if ($customer_layer_on && $customer_id > 0) {

            // Step 1: most-specific (customer + tenant) override.
            if ($tenant_id > 0) {
                $val = self::lookup_override($key, $customer_id, $tenant_id);
                if ($val !== null) {
                    return $val;
                }
            }

            // Step 2: customer-wide override.
            $val = self::lookup_override($key, $customer_id, 0);
            if ($val !== null) {
                return $val;
            }
        }

        // Steps 3 + 4 + 5 + 6 are the legacy resolution path.
        return self::resolve_legacy($key, $tenant_id);
    }

    /**
     * Resolve a flag using ONLY the legacy (pre-Session-2) 3-level
     * algorithm: tenant > global > registered-default > false.
     *
     * Used internally for:
     *   - the gate flag's own lookup (no recursion)
     *   - the fall-through path when the customer-layer gate is OFF
     *   - the fall-through path when the customer-layer gate is ON but
     *     no customer-scoped row matches
     *
     * Emits the "unknown key" debug warning here so {@see is_enabled_for}
     * preserves the Phase-A0 contract for callers + tests.
     *
     * @param string $key
     * @param int    $tenant_id
     * @return bool
     */
    private static function resolve_legacy(string $key, int $tenant_id): bool {
        // Step 3: legacy tenant-only override.
        if ($tenant_id > 0) {
            $val = self::lookup_override($key, 0, $tenant_id);
            if ($val !== null) {
                return $val;
            }
        }
        // Step 4: global override.
        $val = self::lookup_override($key, 0, 0);
        if ($val !== null) {
            return $val;
        }
        // Step 5: registered default.
        $registry = self::load_registry();
        if (isset($registry[$key])) {
            return (bool) $registry[$key]['default'];
        }
        // Step 6: fail-safe — unknown key.
        debugging("feature_flags: unknown key '$key' — returning false",
            DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Get the merged registry + current resolved values for every
     * known flag. Used by the Switchboard to render the toggle list.
     *
     * @param int $tenant_id   View under this tenant (0 = customer-wide view)
     * @param int $customer_id View under this customer (0 = global view).
     *                          Defaults to "all customers" when omitted to
     *                          preserve Phase A0 callsite semantics.
     * @return array<string, array{
     *     key: string,
     *     default: bool,
     *     description: string,
     *     resolved: bool,
     *     has_global_override: bool,
     *     has_customer_override: bool,
     *     has_tenant_override: bool,
     *     has_legacy_tenant_override: bool,
     *     category: string
     * }>
     */
    public static function all(int $tenant_id = 0, int $customer_id = 0): array {
        $registry = self::load_registry();
        $out = [];
        foreach ($registry as $key => $entry) {
            $has_tenant = ($customer_id > 0 && $tenant_id > 0)
                && self::lookup_override($key, $customer_id, $tenant_id) !== null;
            $has_customer = ($customer_id > 0)
                && self::lookup_override($key, $customer_id, 0) !== null;
            $has_legacy_tenant = ($tenant_id > 0)
                && self::lookup_override($key, 0, $tenant_id) !== null;
            $has_global = self::lookup_override($key, 0, 0) !== null;

            // Category is the first dotted segment.
            $dotpos = strpos($key, '.');
            $category = $dotpos !== false ? substr($key, 0, $dotpos) : 'other';

            $out[$key] = [
                'key'                        => $key,
                'default'                    => (bool) $entry['default'],
                'description'                => (string) $entry['description'],
                'resolved'                   => self::is_enabled_for($key, $customer_id, $tenant_id),
                'has_global_override'        => $has_global,
                'has_customer_override'      => $has_customer,
                'has_tenant_override'        => $has_tenant,
                'has_legacy_tenant_override' => $has_legacy_tenant,
                'category'                   => $category,
            ];
        }
        // Sort by category, then key, for stable Switchboard render order.
        uasort($out, fn($a, $b) => [$a['category'], $a['key']] <=> [$b['category'], $b['key']]);
        return $out;
    }

    /**
     * Set the flag's override for a (customer, tenant) pair.
     *
     * Three branches:
     *   - $value === null and a row exists  → delete the row (revert)
     *   - $value !== null and no row exists → insert a row
     *   - $value !== null and a row exists  → update the row
     *
     * Every call writes an audit-log row in {local_airpay_feature_flag_audit}.
     *
     * Backwards compat: callsites passing only (key, tenant_id, value, ...)
     * default customer_id to {@see customer::DEFAULT} (0) — the legacy
     * "all customers" scope. This matches Phase A0 semantics exactly.
     *
     * When the customer-layer gate is OFF and a caller tries to write
     * with customer_id > 0, we throw `customer_layer_disabled` so the UI
     * can't silently no-op a configuration intent.
     *
     * @param string    $key         Flag key (must exist in the registry)
     * @param int       $tenant_id   Tenant root or 0 for customer-wide
     * @param bool|null $value       true|false override; null = revert
     * @param int|null  $by_userid   Defaults to $USER->id
     * @param string    $reason      Optional admin note for audit log
     * @param int       $customer_id Customer id or 0 for global. New
     *                                parameter in Session 2 — defaulted at
     *                                the end so every Phase A0 callsite
     *                                continues to compile.
     * @return void
     * @throws \moodle_exception when the key isn't in the registry, or when
     *                            customer_id > 0 and the customer-layer
     *                            gate flag is OFF.
     */
    public static function set(string $key, int $tenant_id, ?bool $value,
                                ?int $by_userid = null, string $reason = '',
                                int $customer_id = 0): void {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        $registry = self::load_registry();
        if (!isset($registry[$key])) {
            throw new \moodle_exception('unknownflagkey', 'local_airpay_core',
                '', $key);
        }

        // Guard A: the gate flag itself has no customer scope. It's
        // the meta-flag that governs whether OTHER flags can be
        // customer-scoped. Setting it at customer_id > 0 would be
        // nonsensical and would never affect resolution.
        if ($key === self::CUSTOMER_LEVEL_FLAG && $customer_id > 0) {
            throw new \moodle_exception('gateflag_no_customer_scope',
                'local_airpay_core');
        }

        // Guard B: customer-scoped writes (for any other flag) require
        // the layer gate to be ON. This prevents silent configuration
        // intent — an admin clicking a disabled UI shouldn't have rows
        // accumulate in the DB that don't affect resolution.
        if ($customer_id > 0) {
            if (!self::resolve_legacy(self::CUSTOMER_LEVEL_FLAG, 0)) {
                throw new \moodle_exception('customer_layer_disabled',
                    'local_airpay_core', '', $key);
            }
        }

        $existing = $DB->get_record('local_airpay_feature_flags', [
            'flag_key'    => $key,
            'customer_id' => $customer_id,
            'tenant_id'   => $tenant_id,
        ]);

        $old_value = $existing ? (bool) $existing->is_enabled : null;
        // If $value matches the existing state, this is a no-op — skip
        // writing an audit row.
        if ($old_value === $value) {
            return;
        }

        $now = time();

        if ($value === null) {
            if ($existing) {
                $DB->delete_records('local_airpay_feature_flags',
                    ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->is_enabled   = $value ? 1 : 0;
            $existing->modified_by  = $by_userid;
            $existing->timemodified = $now;
            $DB->update_record('local_airpay_feature_flags', $existing);
        } else {
            $DB->insert_record('local_airpay_feature_flags', (object) [
                'flag_key'     => $key,
                'customer_id'  => $customer_id,
                'tenant_id'    => $tenant_id,
                'is_enabled'   => $value ? 1 : 0,
                'modified_by'  => $by_userid,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }

        // Audit row.
        $DB->insert_record('local_airpay_feature_flag_audit', (object) [
            'flag_key'    => $key,
            'customer_id' => $customer_id,
            'tenant_id'   => $tenant_id,
            'old_value'   => $old_value === null ? null : ($old_value ? 1 : 0),
            'new_value'   => $value === null ? null : ($value ? 1 : 0),
            'changed_by'  => $by_userid,
            'reason'      => $reason !== '' ? $reason : null,
            'timecreated' => $now,
        ]);

        // Invalidate caches so subsequent calls see the new value.
        self::invalidate_caches();
    }

    /**
     * Recent audit log entries — for the Switchboard's history page.
     *
     * @param int    $limit
     * @param string $key_filter Optional flag-key prefix filter (e.g. 'ai.' or full key)
     * @return array
     */
    public static function recent_audit(int $limit = 100, string $key_filter = ''): array {
        global $DB;
        $where = '1=1';
        $params = [];
        if ($key_filter !== '') {
            $where .= ' AND ' . $DB->sql_like('a.flag_key', ':kf', false);
            $params['kf'] = $DB->sql_like_escape($key_filter) . '%';
        }
        return $DB->get_records_sql(
            "SELECT a.id, a.flag_key, a.customer_id, a.tenant_id, a.old_value, a.new_value,
                    a.changed_by, a.reason, a.timecreated,
                    u.firstname, u.lastname, u.email
               FROM {local_airpay_feature_flag_audit} a
          LEFT JOIN {user} u ON u.id = a.changed_by
              WHERE $where
           ORDER BY a.timecreated DESC",
            $params, 0, $limit);
    }

    // ─── private helpers ─────────────────────────────────────────────

    /**
     * Look up an override row for a (key, customer, tenant) triple.
     * Returns null when no row exists (caller falls through to the next
     * resolution step).
     *
     * Internally batches all overrides into a single query on first
     * call, served from a process-local cache thereafter. The batched
     * structure is keyed by [flag_key][customer_id][tenant_id] so a
     * lookup is O(1) hash hits.
     */
    private static function lookup_override(string $key, int $customer_id, int $tenant_id): ?bool {
        global $DB;
        if (self::$overrides === null) {
            self::$overrides = [];
            $rows = $DB->get_records('local_airpay_feature_flags', null,
                '', 'id, flag_key, customer_id, tenant_id, is_enabled');
            foreach ($rows as $r) {
                self::$overrides[$r->flag_key][(int) $r->customer_id][(int) $r->tenant_id]
                    = (bool) $r->is_enabled;
            }
        }
        if (!isset(self::$overrides[$key][$customer_id][$tenant_id])) {
            return null;
        }
        return self::$overrides[$key][$customer_id][$tenant_id];
    }

    /**
     * Walk every installed plugin and merge their `db/feature_flags.php`
     * registries into one flat array.
     *
     * Cached in MUC for 60s and additionally process-cached for the
     * lifetime of this PHP request.
     */
    public static function load_registry(): array {
        if (self::$registry !== null) {
            return self::$registry;
        }

        $cache = \cache::make('local_airpay_core', 'feature_flags_registry');
        $cached = $cache->get('registry');
        if ($cached !== false) {
            self::$registry = $cached;
            return self::$registry;
        }

        $registry = [];
        $plugins = \core_component::get_plugin_types();
        foreach ($plugins as $type => $typedir) {
            $instances = \core_component::get_plugin_list($type);
            foreach ($instances as $name => $plugindir) {
                $candidate = $plugindir . '/db/feature_flags.php';
                if (!is_readable($candidate)) {
                    continue;
                }
                $flags = [];
                // The file sets $flags = [...]. Include in a tight scope.
                include $candidate;
                if (!is_array($flags)) {
                    continue;
                }
                foreach ($flags as $key => $entry) {
                    if (!is_string($key) || !is_array($entry)) {
                        continue;
                    }
                    if (!isset($entry['default']) || !isset($entry['description'])) {
                        debugging("feature_flags: $type/$name declared '$key' "
                            . "without default+description — skipping",
                            DEBUG_DEVELOPER);
                        continue;
                    }
                    $registry[$key] = [
                        'default'     => (bool) $entry['default'],
                        'description' => (string) $entry['description'],
                    ];
                }
            }
        }

        self::$registry = $registry;
        $cache->set('registry', $registry);
        return $registry;
    }

    /**
     * Invalidate both caches after a write. Public so admin CLI tools
     * can clear caches manually if they bypass set().
     */
    public static function invalidate_caches(): void {
        self::$registry = null;
        self::$overrides = null;
        $cache = \cache::make('local_airpay_core', 'feature_flags_registry');
        $cache->delete('registry');
    }

    /**
     * Derive the current user's tenant root from $USER->open_path.
     * Returns 0 (global view) for site admins so they see the
     * registered defaults unless they explicitly choose a tenant.
     */
    private static function current_tenant_root(): int {
        global $USER;
        if (function_exists('is_siteadmin') && is_siteadmin()) {
            return 0;
        }
        $path = $USER->open_path ?? '';
        if ($path === '') {
            return 0;
        }
        $parts = explode('/', trim($path, '/'));
        $first = $parts[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }
}
