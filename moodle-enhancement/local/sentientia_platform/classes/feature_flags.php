<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

/**
 * Feature flag resolver — Phase A0 (2026-05-14) + Session 2 (2026-05-20).
 *
 * The runtime API that every gated feature consults to decide whether
 * to render its UI, fire its background job, or fall back gracefully.
 *
 * Phase A0 (2026-05-14) shipped 3-level resolution
 * -------------------------------------------------
 *   1. Row in {local_sentientia_feature_flags} for (key, current_tenant)
 *   2. Row in {local_sentientia_feature_flags} for (key, 0)            (global override)
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
 * exactly as before. Resolution is unchanged. The all() summary is not:
 * a legacy (customer 0, tenant T) row is reported as
 * has_legacy_tenant_override, not has_tenant_override (see all()).
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
 * Seeing a flag change in a long-running process (B1, 2026-10-09)
 * ---------------------------------------------------------------
 * The override snapshot is a PHP static, and a static lives as long as its
 * process. A web request is over in seconds, so it never mattered there. The
 * cron main process (cron_keepalive, 180 s by default, up to 900 s), adhoc
 * task runners, CLI scripts and SSE connections do not exit: before this
 * change they read the flag state of their FIRST lookup until they exited,
 * whatever an admin flipped meanwhile. Only the writing process was fixed
 * (set() calls invalidate_caches()).
 *
 * The snapshot now expires after SNAPSHOT_TTL seconds. The next lookup after
 * that re-reads the whole override table with one SELECT. Why a TTL on the
 * snapshot, rather than the alternatives:
 *
 *   - A revision counter (bumped by set(), checked by every process) only
 *     helps if every writer bumps it. set() is the only production writer
 *     today, but a restore of a live backup, the BizLMS importers, hand SQL
 *     and any future writer would silently bypass it and leave long-running
 *     processes stale for ever. A TTL is writer-independent: it heals
 *     whatever changed the table.
 *   - MUC with invalidation cannot work for this. A MUC cache with
 *     staticacceleration (core/config has it; so does this plugin's
 *     feature_flags_registry) keeps its static copy for the life of the
 *     process, with no TTL check (cache::static_acceleration_get()); a delete
 *     in another process never reaches it. Core knows: its own cron restart
 *     signal (task\manager::static_caches_cleared_since()) reads
 *     {config}.scheduledtaskreset straight from $DB with the comment "the
 *     caches cannot be relied on". A cache without static acceleration would
 *     put a cache read, often a file read, on every lookup instead.
 *   - Staleness is bounded by the TTL (30 s), which is also what the MUC
 *     registry TTL promised ("toggles propagate within a minute").
 *
 * Cost: a web request shorter than the TTL does exactly what it did before,
 * one SELECT on its first lookup. Every resolution takes ONE clock read and
 * works on one snapshot (a flip cannot land between the customer-layer gate
 * lookup and the key lookup). A process that lives for an hour runs two
 * SELECTs a minute against a table of a few dozen rows. The clock is
 * \core\clock, so PHPUnit moves time with mock_clock_with_frozen() instead
 * of sleeping; a clock that moves BACKWARDS also forces a reload.
 *
 * The registry is deliberately not on the TTL. It holds the defaults and
 * descriptions declared in db/feature_flags.php, which only a code change
 * alters; a deploy ends in purge_all_caches(), which reaches
 * task\manager::clear_static_caches() (via purge_other_caches()) and makes
 * core stop and restart the cron process.
 * Re-reading it here would also only reach the MUC copy, which is statically
 * accelerated, so it would not see anything new.
 *
 * invalidate_caches() still drops the snapshot at once. Tests, and any CLI
 * tool that writes the table without set(), call it.
 *
 * @package local_sentientia_platform
 */
class feature_flags {

    /** Feature flag that gates Session 2's customer-level resolution. */
    public const CUSTOMER_LEVEL_FLAG = 'sentientia.customer_level_flags.enabled';

    /**
     * Seconds a process keeps its snapshot of the override table before the
     * next lookup re-reads it. The longest a flag flip can stay invisible to a
     * long-running process (cron, adhoc runner, CLI, SSE). See the class
     * docblock for why a TTL.
     */
    public const SNAPSHOT_TTL = 30;

    /** @var array<string, array{default: bool, description: string}>|null Registry cache. */
    private static $registry = null;

    /**
     * Override cache. Three-level map keyed by [flag_key][customer_id][tenant_id] => bool.
     * Single batched SELECT populates the whole thing, and re-populates it
     * once it is older than SNAPSHOT_TTL.
     *
     * @var array<string, array<int, array<int, bool>>>|null
     */
    private static $overrides = null;

    /** @var int \core\clock time at which self::$overrides was read. */
    private static $overridesloadedat = 0;

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
        // One snapshot (and so one clock read) for the whole resolution: the
        // gate lookup and the key lookups below cannot straddle a reload.
        $overrides = self::overrides();

        // Recursion guard: looking up the gate flag itself? Skip the
        // customer-aware path entirely — the gate flag has no customer
        // scope, its job is to gate OTHER flags' customer scope. Going
        // through the customer-aware path would call self::is_enabled_for()
        // recursively and stack-overflow.
        if ($key === self::CUSTOMER_LEVEL_FLAG) {
            return self::resolve_legacy($key, $tenant_id, $overrides);
        }

        // Is the customer-level resolution layer enabled?
        $customer_layer_on = self::resolve_legacy(self::CUSTOMER_LEVEL_FLAG, 0, $overrides);

        // Steps 1 + 2: customer-scoped resolution, only when the layer
        // is enabled AND we have a real (non-default) customer.
        if ($customer_layer_on && $customer_id > 0) {

            // Step 1: most-specific (customer + tenant) override.
            if ($tenant_id > 0) {
                $val = self::lookup_override($overrides, $key, $customer_id, $tenant_id);
                if ($val !== null) {
                    return $val;
                }
            }

            // Step 2: customer-wide override.
            $val = self::lookup_override($overrides, $key, $customer_id, 0);
            if ($val !== null) {
                return $val;
            }
        }

        // Steps 3 + 4 + 5 + 6 are the legacy resolution path.
        return self::resolve_legacy($key, $tenant_id, $overrides);
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
     * @param array  $overrides The snapshot from {@see overrides()}; the caller
     *                          takes it once so a resolution reads one state.
     * @return bool
     */
    private static function resolve_legacy(string $key, int $tenant_id, array $overrides): bool {
        // Step 3: legacy tenant-only override.
        if ($tenant_id > 0) {
            $val = self::lookup_override($overrides, $key, 0, $tenant_id);
            if ($val !== null) {
                return $val;
            }
        }
        // Step 4: global override.
        $val = self::lookup_override($overrides, $key, 0, 0);
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
     * Override keys, one per resolution step:
     *   has_tenant_override        (customer C, tenant T) row, step 1.
     *                              Only when both ids are > 0.
     *   has_customer_override      (customer C, tenant 0) row, step 2.
     *   has_legacy_tenant_override (customer 0, tenant T) row, step 3.
     *                              A set($key, $tenant, ...) call without a
     *                              customer id writes this row. Phase A0
     *                              reported it as has_tenant_override.
     *   has_global_override        (customer 0, tenant 0) row, step 4.
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
        $overrides = self::overrides();
        $out = [];
        foreach ($registry as $key => $entry) {
            $has_tenant = ($customer_id > 0 && $tenant_id > 0)
                && self::lookup_override($overrides, $key, $customer_id, $tenant_id) !== null;
            $has_customer = ($customer_id > 0)
                && self::lookup_override($overrides, $key, $customer_id, 0) !== null;
            $has_legacy_tenant = ($tenant_id > 0)
                && self::lookup_override($overrides, $key, 0, $tenant_id) !== null;
            $has_global = self::lookup_override($overrides, $key, 0, 0) !== null;

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
     * Human label for a flag category, as shown on the Switchboard.
     *
     * The category is the first dotted segment of a flag key ('live' for
     * 'live.enabled'; 'other' for a key with no dot). Labels live in the
     * flag_category_<category> lang strings.
     *
     * The old inline lookup was get_string(id, component, null, true) ?: ucfirst()
     * -- with lazyload=true get_string() returns a lang_string OBJECT, which is
     * always truthy, so the ucfirst() fallback never ran and a category with no
     * string rendered as "[[FLAG_CATEGORY_LIVE]]". Ask the string manager
     * whether the string exists instead, and return a plain string either way.
     *
     * @param string $category First dotted segment of a flag key.
     * @return string Localised label, or ucfirst($category) when no string exists.
     */
    public static function category_label(string $category): string {
        $identifier = 'flag_category_' . $category;
        if (get_string_manager()->string_exists($identifier, 'local_sentientia_platform')) {
            return get_string($identifier, 'local_sentientia_platform');
        }
        return ucfirst($category);
    }

    /**
     * Set the flag's override for a (customer, tenant) pair.
     *
     * Three branches:
     *   - $value === null and a row exists  → delete the row (revert)
     *   - $value !== null and no row exists → insert a row
     *   - $value !== null and a row exists  → update the row
     *
     * Every call writes an audit-log row in {local_sentientia_feature_flag_audit}.
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
            throw new \moodle_exception('unknownflagkey', 'local_sentientia_platform',
                '', $key);
        }

        // Guard A: the gate flag itself has no customer scope. It's
        // the meta-flag that governs whether OTHER flags can be
        // customer-scoped. Setting it at customer_id > 0 would be
        // nonsensical and would never affect resolution.
        if ($key === self::CUSTOMER_LEVEL_FLAG && $customer_id > 0) {
            throw new \moodle_exception('gateflag_no_customer_scope',
                'local_sentientia_platform');
        }

        // Guard B: customer-scoped writes (for any other flag) require
        // the layer gate to be ON. This prevents silent configuration
        // intent — an admin clicking a disabled UI shouldn't have rows
        // accumulate in the DB that don't affect resolution.
        if ($customer_id > 0) {
            if (!self::resolve_legacy(self::CUSTOMER_LEVEL_FLAG, 0, self::overrides())) {
                throw new \moodle_exception('customer_layer_disabled',
                    'local_sentientia_platform', '', $key);
            }
        }

        $existing = $DB->get_record('local_sentientia_feature_flags', [
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
                $DB->delete_records('local_sentientia_feature_flags',
                    ['id' => $existing->id]);
            }
        } else if ($existing) {
            $existing->is_enabled   = $value ? 1 : 0;
            $existing->modified_by  = $by_userid;
            $existing->timemodified = $now;
            $DB->update_record('local_sentientia_feature_flags', $existing);
        } else {
            $DB->insert_record('local_sentientia_feature_flags', (object) [
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
        $DB->insert_record('local_sentientia_feature_flag_audit', (object) [
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
               FROM {local_sentientia_feature_flag_audit} a
          LEFT JOIN {user} u ON u.id = a.changed_by
              WHERE $where
           ORDER BY a.timecreated DESC",
            $params, 0, $limit);
    }

    // ─── private helpers ─────────────────────────────────────────────

    /**
     * Look up an override row for a (key, customer, tenant) triple in a
     * snapshot taken from {@see overrides()}. Returns null when no row
     * exists (caller falls through to the next resolution step).
     *
     * The snapshot is keyed by [flag_key][customer_id][tenant_id] so a
     * lookup is O(1) hash hits.
     *
     * @param array<string, array<int, array<int, bool>>> $overrides
     */
    private static function lookup_override(array $overrides, string $key, int $customer_id, int $tenant_id): ?bool {
        if (!isset($overrides[$key][$customer_id][$tenant_id])) {
            return null;
        }
        return $overrides[$key][$customer_id][$tenant_id];
    }

    /**
     * The process's snapshot of every override row, at most SNAPSHOT_TTL
     * seconds old.
     *
     * Read with ONE batched SELECT on first use and again whenever the
     * snapshot is SNAPSHOT_TTL seconds old (or the clock has gone backwards),
     * so a long-running process (cron, adhoc runner, CLI, SSE) sees a flag
     * flipped by any other process, or by anything that wrote the table
     * without set(), within the TTL. Between reloads this is a clock read and
     * a static property return. The array is returned by value (copy on
     * write), so a caller keeps a consistent view even if a later call
     * reloads.
     *
     * @return array<string, array<int, array<int, bool>>>
     */
    private static function overrides(): array {
        global $DB;
        $now = self::now();
        $age = $now - self::$overridesloadedat;
        if (self::$overrides !== null && $age >= 0 && $age < self::SNAPSHOT_TTL) {
            return self::$overrides;
        }
        $loaded = [];
        $rows = $DB->get_records('local_sentientia_feature_flags', null,
            '', 'id, flag_key, customer_id, tenant_id, is_enabled');
        foreach ($rows as $r) {
            $loaded[$r->flag_key][(int) $r->customer_id][(int) $r->tenant_id]
                = (bool) $r->is_enabled;
        }
        self::$overrides = $loaded;
        self::$overridesloadedat = $now;
        return $loaded;
    }

    /**
     * Current time, from the core clock so PHPUnit can move it
     * (mock_clock_with_frozen()) instead of sleeping.
     */
    private static function now(): int {
        return \core\di::get(\core\clock::class)->time();
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

        $cache = \cache::make('local_sentientia_platform', 'feature_flags_registry');
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
     * Invalidate both caches after a write, in THIS process at once (other
     * processes pick the change up within SNAPSHOT_TTL). Public so tests and
     * admin CLI tools can clear caches manually if they bypass set().
     */
    public static function invalidate_caches(): void {
        self::$registry = null;
        self::$overrides = null;
        self::$overridesloadedat = 0;
        $cache = \cache::make('local_sentientia_platform', 'feature_flags_registry');
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
