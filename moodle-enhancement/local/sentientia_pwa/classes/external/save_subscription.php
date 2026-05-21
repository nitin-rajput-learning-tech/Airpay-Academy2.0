<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Save (upsert) the current user's push subscription — WS endpoint.
 *
 * @package local_sentientia_pwa
 */
class save_subscription extends external_api {

    /**
     * Allowlist of browser push-service origins. Anything else gets
     * rejected at the WS-input gate to close the SSRF vector flagged
     * in `docs/audits/B25-CRYPTO-AUDIT-2026-05-21.md` finding #1.
     *
     * These cover the four browser families that implement Web Push:
     *   - fcm.googleapis.com — Chrome (Android + desktop), Edge ≥ 79
     *   - *.notify.windows.com — Edge legacy / WNS bridge
     *   - web.push.apple.com — Safari iOS 16.4+, macOS Ventura+
     *   - updates.push.services.mozilla.com — Firefox
     *
     * If a new browser ships a different endpoint host (rare), add it
     * here. NEVER widen this to `*` — that re-opens SSRF.
     */
    private const ALLOWED_ENDPOINT_HOST_SUFFIXES = [
        'fcm.googleapis.com',
        '.notify.windows.com',
        'web.push.apple.com',
        'updates.push.services.mozilla.com',
    ];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            // PARAM_TEXT not PARAM_URL: PARAM_URL silently strips some
            // characters (audit #5). We validate the URL shape + host
            // ourselves below.
            'endpoint' => new external_value(PARAM_TEXT, 'PushSubscription.endpoint URL'),
            // PARAM_RAW (not PARAM_ALPHANUMEXT): base64url uses `-` and
            // `_` which ALPHANUMEXT rejects (audit finding #5). We do
            // shape + length validation below.
            'p256dh'   => new external_value(PARAM_RAW, 'PushSubscription.getKey(p256dh) as base64url'),
            'auth'     => new external_value(PARAM_RAW, 'PushSubscription.getKey(auth) as base64url'),
            'user_agent' => new external_value(PARAM_TEXT, 'Browser user-agent (informational)', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Strict base64url validator. Accepts only [A-Za-z0-9_-]+ and a
     * bounded length window (RFC 7515 §C). Returns true if valid.
     *
     * @param string $value
     * @param int    $min_decoded_bytes  Minimum length after base64url decode
     * @param int    $max_decoded_bytes  Maximum length after base64url decode
     */
    private static function is_valid_base64url(string $value,
                                                int $min_decoded_bytes,
                                                int $max_decoded_bytes): bool {
        if (!preg_match('/\A[A-Za-z0-9_\-]+\z/', $value)) {
            return false;
        }
        // base64url length L encodes ceil(L*3/4) bytes
        $decoded_bytes = (int) floor(strlen($value) * 3 / 4);
        return $decoded_bytes >= $min_decoded_bytes
            && $decoded_bytes <= $max_decoded_bytes;
    }

    public static function execute(string $endpoint, string $p256dh, string $auth,
                                    string $user_agent = ''): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
            'endpoint' => $endpoint, 'p256dh' => $p256dh,
            'auth' => $auth, 'user_agent' => $user_agent,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_pwa:subscribe', $context);

        // ── Audit fix #1 — SSRF defence: enforce host allowlist on endpoint ──
        $parsed = parse_url($endpoint);
        if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
            throw new \moodle_exception('invalid_subscription_endpoint',
                'local_sentientia_pwa');
        }
        // Audit fix #2 — also gate the scheme. http:// downgrades the
        // VAPID `aud` claim and is never legitimate for browser push.
        if (strtolower($parsed['scheme']) !== 'https') {
            throw new \moodle_exception('invalid_subscription_endpoint',
                'local_sentientia_pwa');
        }
        $host = strtolower($parsed['host']);
        $host_ok = false;
        foreach (self::ALLOWED_ENDPOINT_HOST_SUFFIXES as $suffix) {
            // Exact match OR subdomain match (suffix starting with ".")
            if ($host === $suffix
                || ($suffix[0] === '.' && str_ends_with($host, $suffix))
                || (str_contains($suffix, '.') && str_ends_with($host, '.' . $suffix))
                || ($suffix === 'fcm.googleapis.com' && $host === $suffix)) {
                $host_ok = true;
                break;
            }
        }
        if (!$host_ok) {
            throw new \moodle_exception('invalid_subscription_endpoint',
                'local_sentientia_pwa');
        }

        // ── Audit fix #5 — base64url key validation (replaces broken PARAM_ALPHANUMEXT) ──
        // p256dh = uncompressed P-256 public key = 65 bytes when decoded
        // auth   = client auth secret = 16 bytes when decoded
        if (!self::is_valid_base64url($p256dh, 64, 66)) {
            throw new \moodle_exception('invalid_subscription_key_p256dh',
                'local_sentientia_pwa');
        }
        if (!self::is_valid_base64url($auth, 15, 17)) {
            throw new \moodle_exception('invalid_subscription_key_auth',
                'local_sentientia_pwa');
        }

        $id = \local_sentientia_pwa\subscription_manager::save(
            (int) $USER->id,
            $endpoint,
            $p256dh,
            $auth,
            $user_agent
        );

        return [
            'id'      => $id,
            'success' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'      => new external_value(PARAM_INT, 'Subscription row ID'),
            'success' => new external_value(PARAM_BOOL, 'Always true on success — exceptions thrown on failure'),
        ]);
    }
}
