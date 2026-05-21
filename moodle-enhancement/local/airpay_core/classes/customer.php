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
     * Phase 0/1: a single hard-wired Airpay bundle (because customer
     * id 1 is the only real entry). Phase 2+ ADR-008 will replace this
     * body with a DB lookup against `local_airpay_customer_brand` —
     * same return shape, so callers won't change.
     *
     * The return shape is intentionally narrow so per-customer overrides
     * stay disciplined:
     *   - name         Display name (full)
     *   - short_name   ≤ 12 chars, for PWA app shortcuts
     *   - theme_color  Hex, used as PWA theme_color + browser-chrome tint
     *   - bg_color     Hex, PWA background_color while shell warms up
     *   - icon_192_url Absolute URL to 192×192 PNG (Android home-screen)
     *   - icon_512_url Absolute URL to 512×512 PNG (splash + adaptive)
     *   - start_url    Path the PWA launches into (relative to wwwroot)
     *   - lang         BCP-47 language code (PWA `lang` field)
     *
     * @param int|null $customer_id Defaults to {@see current()}.
     * @return array
     */
    public static function branding(?int $customer_id = null): array {
        global $CFG;
        if ($customer_id === null) {
            $customer_id = self::current();
        }
        // Phase 0/1 — single customer (Airpay). Future customers add
        // their entries by extending the match below.
        switch ($customer_id) {
            case self::AIRPAY:
            default:
                return [
                    'name'         => 'Airpay Academy',
                    'short_name'   => 'Academy',
                    'theme_color'  => '#0066A7',
                    'bg_color'     => '#F2F4FB',
                    'icon_192_url' => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-192.png',
                    'icon_512_url' => $CFG->wwwroot . '/local/airpay_core/pix/customer/1/icon-512.png',
                    'start_url'    => '/my/dashboard.php?utm_source=pwa_install',
                    'lang'         => 'en',
                ];
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
