<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Feature flag resolver — Phase A0 (2026-05-14).
 *
 * The runtime API that every gated feature consults to decide whether
 * to render its UI, fire its background job, or fall back gracefully.
 *
 * The contract (from CONFIGURABILITY-ARCHITECTURE.md)
 * ---------------------------------------------------
 * A flag is one boolean with three registered properties (key,
 * default, description). The runtime is three functions:
 *
 *   feature_flags::is_enabled('foo.bar.enabled'): bool
 *   feature_flags::is_enabled_for_tenant('foo.bar.enabled', $tid): bool
 *   feature_flags::all(): array
 *
 * Resolution order (per the architecture doc §2.5):
 *   1. Row in {local_airpay_feature_flags} for (key, current_tenant)
 *   2. Row in {local_airpay_feature_flags} for (key, 0)            (global override)
 *   3. Flag's registered default from db/feature_flags.php          (registered)
 *   4. false                                                         (fail-safe)
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

    /** @var array<string, array{default: bool, description: string}>|null Registry cache. */
    private static $registry = null;

    /** @var array<string, array<int, bool>>|null Override cache: [flag_key => [tenant_id => bool]]. */
    private static $overrides = null;

    /**
     * Is the flag enabled for the current user's tenant?
     *
     * @param string $key Three-level dotted flag key (e.g. 'ai.assistant.enabled')
     * @return bool
     */
    public static function is_enabled(string $key): bool {
        return self::is_enabled_for_tenant($key, self::current_tenant_root());
    }

    /**
     * Is the flag enabled for a specific tenant?
     *
     * Used by super-admin code looking at any tenant's state (e.g. the
     * Switchboard rendering Public's flags while admin is in Airpay).
     *
     * @param string $key       Flag key
     * @param int    $tenant_id Tenant root (1, 77, 177, ...) or 0 for "global view"
     * @return bool
     */
    public static function is_enabled_for_tenant(string $key, int $tenant_id): bool {
        // Step 1: per-tenant override (tenant_id > 0 only).
        if ($tenant_id > 0) {
            $val = self::lookup_override($key, $tenant_id);
            if ($val !== null) {
                return $val;
            }
        }

        // Step 2: global override (tenant_id = 0).
        $val = self::lookup_override($key, 0);
        if ($val !== null) {
            return $val;
        }

        // Step 3: registered default.
        $registry = self::load_registry();
        if (isset($registry[$key])) {
            return (bool) $registry[$key]['default'];
        }

        // Step 4: fail-safe. Log a developer warning so typos get
        // caught — but never throw, that would break callers.
        debugging("feature_flags: unknown key '$key' — returning false",
            DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Get the merged registry + current resolved values for every
     * known flag. Used by the Switchboard to render the toggle list.
     *
     * @param int $tenant_id View under this tenant (0 = global view)
     * @return array<string, array{
     *     key: string,
     *     default: bool,
     *     description: string,
     *     resolved: bool,
     *     has_global_override: bool,
     *     has_tenant_override: bool,
     *     category: string
     * }>
     */
    public static function all(int $tenant_id = 0): array {
        $registry = self::load_registry();
        $out = [];
        foreach ($registry as $key => $entry) {
            $has_tenant = ($tenant_id > 0)
                && self::lookup_override($key, $tenant_id) !== null;
            $has_global = self::lookup_override($key, 0) !== null;

            // Category is the first dotted segment.
            $dotpos = strpos($key, '.');
            $category = $dotpos !== false ? substr($key, 0, $dotpos) : 'other';

            $out[$key] = [
                'key'                 => $key,
                'default'             => (bool) $entry['default'],
                'description'         => (string) $entry['description'],
                'resolved'            => self::is_enabled_for_tenant($key, $tenant_id),
                'has_global_override' => $has_global,
                'has_tenant_override' => $has_tenant,
                'category'            => $category,
            ];
        }
        // Sort by category, then key, for stable Switchboard render order.
        uasort($out, fn($a, $b) => [$a['category'], $a['key']] <=> [$b['category'], $b['key']]);
        return $out;
    }

    /**
     * Set the flag's override for a tenant (or global if $tenant_id=0).
     *
     * Three branches:
     *   - $value === null and a row exists  → delete the row (revert to default)
     *   - $value !== null and no row exists → insert a row
     *   - $value !== null and a row exists  → update the row
     *
     * Every call writes an audit-log row in {local_airpay_feature_flag_audit}.
     *
     * @param string    $key       Flag key (must exist in the registry)
     * @param int       $tenant_id Tenant root or 0 for global
     * @param bool|null $value     true|false override; null = revert to default
     * @param int|null  $by_userid Defaults to $USER->id
     * @param string    $reason    Optional admin note for the audit log
     * @return void
     * @throws \moodle_exception when the key isn't in the registry
     */
    public static function set(string $key, int $tenant_id, ?bool $value,
                                ?int $by_userid = null, string $reason = ''): void {
        global $DB, $USER;
        $by_userid = $by_userid ?? (int) $USER->id;

        $registry = self::load_registry();
        if (!isset($registry[$key])) {
            throw new \moodle_exception('unknownflagkey', 'local_airpay_core',
                '', $key);
        }

        $existing = $DB->get_record('local_airpay_feature_flags',
            ['flag_key' => $key, 'tenant_id' => $tenant_id]);

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
            "SELECT a.id, a.flag_key, a.tenant_id, a.old_value, a.new_value,
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
     * Look up an override row. Returns null if no row exists (caller
     * falls through to the next resolution step).
     *
     * Internally batches all overrides for any tenant into a single
     * query on first call, served from a process-local cache thereafter.
     */
    private static function lookup_override(string $key, int $tenant_id): ?bool {
        global $DB;
        if (self::$overrides === null) {
            self::$overrides = [];
            $rows = $DB->get_records('local_airpay_feature_flags', null,
                '', 'id, flag_key, tenant_id, is_enabled');
            foreach ($rows as $r) {
                self::$overrides[$r->flag_key][(int) $r->tenant_id]
                    = (bool) $r->is_enabled;
            }
        }
        if (!isset(self::$overrides[$key])) {
            return null;
        }
        if (!array_key_exists($tenant_id, self::$overrides[$key])) {
            return null;
        }
        return self::$overrides[$key][$tenant_id];
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
