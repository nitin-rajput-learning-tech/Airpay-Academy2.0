<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_core;

defined('MOODLE_INTERNAL') || die();

/**
 * Customer identity helper — Session 2 (2026-05-20), per ADR-002.
 *
 * Sentientia LMS is multi-customer: each paying entity (Airpay today;
 * possibly Customer 2 tomorrow) owns 1+ tenants. This class is the single
 * source of truth for resolving "which customer does this user belong to".
 *
 * Phase 0/1 contract (today)
 * --------------------------
 * Airpay is the only customer. Every authenticated user returns
 * customer::AIRPAY from {@see current()}. The constant is hard-wired
 * because there is nothing else to consult yet — no customer table,
 * no admin UI, no mapping rows.
 *
 * Phase 2 contract (when Customer 2 imminent)
 * -------------------------------------------
 * Per future ADR-008, this class will consult a `local_airpay_customers`
 * table mapping each tenant root → customer_id. {@see current()} will
 * derive from $USER->open_path → tenant root → customer id. Code that
 * already calls {@see current()} continues to work unchanged.
 *
 * That contract preservation is the whole point of introducing this class
 * NOW rather than after Customer 2 lands.
 *
 * @package local_airpay_core
 */
class customer {

    /**
     * Airpay Payment Services — customer zero. The first paying customer
     * of Sentientia LMS, used to validate every feature against
     * real-world enterprise scale.
     */
    public const AIRPAY = 1;

    /**
     * Sentinel "no customer scope" — used by legacy feature-flag rows
     * (created before customer-level scope was added). Lookups with
     * customer_id = 0 mean "applies regardless of customer".
     */
    public const DEFAULT = 0;

    /**
     * Customer ID of the currently-authenticated user.
     *
     * Phase 0/1: returns {@see AIRPAY} for everyone (single-customer mode).
     *
     * Phase 2+: consults a tenant-to-customer mapping. The mapping table
     * does not yet exist; future ADR-008 will design it. When it exists,
     * this method changes; nothing else does.
     *
     * Site admins and unauthenticated callers return {@see AIRPAY} too
     * because Airpay-customer-zero is the only customer that can be
     * meaningfully selected today. The Switchboard exposes "All customers"
     * (sentinel 0) as a separate option for super-admins editing the
     * global default.
     */
    public static function current(): int {
        // Phase 0/1: every user is in Airpay. No mapping table to consult.
        return self::AIRPAY;
    }

    /**
     * Tenant ID of the currently-authenticated user — derived from
     * `$USER->open_path` per BizLMS convention (first segment).
     *
     * The path format is `/N/M/.../` where the first non-empty segment
     * is the costcenterid (1=Airpay, 77=Public, 177=ZEEA per CLAUDE.md).
     * Returns `null` when:
     *   - There is no authenticated user
     *   - The user is admin (no tenant scope — sees everything)
     *   - `open_path` is unset or malformed
     *
     * `null` means "no tenant scope on this caller" — downstream callers
     * use it to differentiate "skip cross-tenant gate" from "tenant 0".
     *
     * Added in audit-fix #4 (2026-05-21) to enable push_sender's
     * cross-tenant boundary check. See
     * `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #4.
     *
     * @return int|null
     */
    public static function current_tenant(): ?int {
        global $USER;
        if (empty($USER->id) || (int) $USER->id <= 1) {
            // Not logged in, or guest user (id=1).
            return null;
        }
        if (is_siteadmin($USER->id)) {
            // Site admins legitimately operate across all tenants.
            return null;
        }
        if (empty($USER->open_path)) {
            return null;
        }
        $parts = explode('/', trim((string) $USER->open_path, '/'));
        if (!empty($parts[0]) && ctype_digit($parts[0])) {
            return (int) $parts[0];
        }
        return null;
    }

    /**
     * Validate that a customer id is one we recognise. Throws otherwise.
     *
     * Accepts {@see DEFAULT} as the "All customers" sentinel — the
     * Switchboard's "Global default" view uses this.
     *
     * Phase 2+: accepts every id in the customers table.
     *
     * @param int $customer_id
     * @throws \moodle_exception when the id is not a known customer
     */
    public static function assert_valid(int $customer_id): void {
        // Phase 0/1: only AIRPAY and DEFAULT are valid.
        if ($customer_id !== self::AIRPAY && $customer_id !== self::DEFAULT) {
            throw new \moodle_exception('error_invalidcustomer', 'local_airpay_core',
                '', $customer_id);
        }
    }

    /**
     * Branding bundle for a customer — used by per-customer surfaces
     * like the PWA manifest, login splash, navbar logo, etc.
     *
     * ADR-008 (2026-05-22) — Phase 2 implementation. Reads from the
     * `local_airpay_customer_brand` table (single row per customer id),
     * cached via the `customer_brand` application cache (1-hour TTL,
     * static acceleration). Falls back to the hard-coded Airpay bundle
     * when:
     *   - The table has no row for the requested customer_id (defensive
     *     — production should always have at least customer=1 after the
     *     upgrade savepoint)
     *   - The DB is unavailable mid-request
     *
     * The return shape is unchanged from Phase 0/1 — all callers
     * (manifest.php, theme renderer, navbar logo) continue to work
     * without modification.
     *
     * Returned keys:
     *   - name             Display name (full)
     *   - short_name       <=12 chars, for PWA app shortcuts
     *   - theme_color      Hex, used as PWA theme_color + browser-chrome tint
     *   - bg_color         Hex, PWA background_color while shell warms up
     *   - icon_192_url     Absolute URL to 192x192 PNG
     *   - icon_512_url     Absolute URL to 512x512 PNG
     *   - start_url        Path the PWA launches into
     *   - lang             BCP-47 language code (PWA `lang` field)
     *   - status_bar_style iOS status-bar style (Phase 2 column)
     *   - categories       Comma-separated PWA categories (Phase 2 column)
     *
     * @param int|null $customer_id Defaults to {@see current()}.
     * @return array
     */
    public static function branding(?int $customer_id = null): array {
        global $DB, $CFG;

        if ($customer_id === null) {
            $customer_id = self::current();
        }
        $customer_id = (int) $customer_id;

        // Hot path — application cache hit avoids the DB round trip.
        try {
            $cache = \cache::make('local_airpay_core', 'customer_brand');
            $key   = 'brand_' . $customer_id;
            $bundle = $cache->get($key);
            if (is_array($bundle)) {
                return $bundle;
            }
        } catch (\Throwable $e) {
            // Cache backend not yet ready (very early in bootstrap) —
            // fall through to direct DB read.
            $cache = null;
        }

        // DB lookup. Wrapped in try because the table may not yet
        // exist during a multi-step upgrade where airpay_core hasn't
        // run its 2026052201 savepoint.
        $row = null;
        try {
            $row = $DB->get_record('local_airpay_customer_brand',
                ['customerid' => $customer_id]);
        } catch (\Throwable $e) {
            $row = null;
        }

        if ($row) {
            $bundle = [
                'name'             => $row->name,
                'short_name'       => $row->short_name,
                'theme_color'      => $row->theme_color,
                'bg_color'         => $row->bg_color,
                'icon_192_url'     => self::resolve_url($row->icon_192_url),
                'icon_512_url'     => self::resolve_url($row->icon_512_url),
                'start_url'        => $row->start_url,
                'lang'             => $row->lang,
                'status_bar_style' => $row->status_bar_style ?? 'default',
                'categories'       => self::parse_categories(
                    $row->categories ?? ''),
            ];
        } else {
            // Fallback — hard-coded Airpay bundle. Used when the brand
            // table is empty (e.g. fresh install before the 052201
            // savepoint runs) or for an unknown customer id.
            $bundle = self::default_brand();
        }

        if ($cache !== null) {
            try { $cache->set($key, $bundle); } catch (\Throwable $e) { /* swallow */ }
        }
        return $bundle;
    }

    /**
     * Hard-coded fallback bundle. Used when:
     *   - The brand table is empty / missing (mid-upgrade)
     *   - An unknown customer id is requested (defensive default to
     *     Airpay since that's the only real customer today)
     *
     * Identical to the Phase 0/1 bundle returned pre-Phase-2.
     */
    private static function default_brand(): array {
        global $CFG;
        return [
            'name'             => 'Airpay Academy',
            'short_name'       => 'Academy',
            'theme_color'      => '#0066A7',
            'bg_color'         => '#F2F4FB',
            'icon_192_url'     => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-192.png',
            'icon_512_url'     => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-512.png',
            'start_url'        => '/my/dashboard.php?utm_source=pwa_install',
            'lang'             => 'en',
            'status_bar_style' => 'default',
            'categories'       => ['education', 'productivity'],
        ];
    }

    /**
     * Make a brand-asset URL absolute. The table stores both relative
     * (`/local/airpay_core/pix/...`) and absolute (`https://cdn...`)
     * URLs; relatives are prefixed with `$CFG->wwwroot`.
     */
    private static function resolve_url(string $url): string {
        global $CFG;
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return rtrim($CFG->wwwroot, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Parse the comma-separated `categories` column into an array.
     * Empty / null input returns the spec-recommended defaults.
     */
    private static function parse_categories(string $csv): array {
        $csv = trim($csv);
        if ($csv === '') {
            return ['education', 'productivity'];
        }
        $parts = array_map('trim', explode(',', $csv));
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }

    /**
     * Invalidate the per-customer branding cache.
     *
     * Call this from any admin-side write that touches a row in
     * `local_airpay_customer_brand`. Future Phase 2 admin UI will wire
     * this in; for now operators editing via DB should run
     * `php admin/cli/purge_caches.php` (which clears the application
     * cache layer along with everything else).
     *
     * @param int|null $customer_id If given, invalidate only that
     *                              customer; otherwise invalidate all.
     */
    public static function invalidate_branding_cache(?int $customer_id = null): void {
        try {
            $cache = \cache::make('local_airpay_core', 'customer_brand');
            if ($customer_id === null) {
                $cache->purge();
            } else {
                $cache->delete('brand_' . (int) $customer_id);
            }
            // Also fire the event so any cluster peers that subscribed
            // to `customer_brand_updated` invalidate their static-
            // acceleration layer (see db/caches.php).
            \cache_helper::purge_by_event('customer_brand_updated');
        } catch (\Throwable $e) {
            // Cache backend not yet ready — silent. Next read will see
            // the new DB state on its first call (within the 1-hour TTL).
        }
    }

    /**
     * The list of known customers, for Switchboard tab rendering.
     *
     * Returns [['id' => int, 'name' => string, 'is_default' => bool], ...]
     * with the {@see DEFAULT} sentinel first followed by each real customer.
     *
     * Phase 2+: this becomes a DB query against the customers table. The
     * shape of the returned array stays the same.
     */
    public static function known_customers(): array {
        return [
            [
                'id'         => self::DEFAULT,
                'name'       => get_string('customer_default_label', 'local_airpay_core'),
                'is_default' => true,
            ],
            [
                'id'         => self::AIRPAY,
                'name'       => 'Airpay Payment Services',
                'is_default' => false,
            ],
        ];
    }

    /**
     * Display label for a customer id. Falls back to "Unknown (N)" for
     * unrecognised ids so the UI never throws when rendering historical
     * audit rows pointing at customers that have been deleted.
     */
    public static function label_for(int $customer_id): string {
        foreach (self::known_customers() as $c) {
            if ($c['id'] === $customer_id) {
                return $c['name'];
            }
        }
        return 'Unknown (' . $customer_id . ')';
    }
}
