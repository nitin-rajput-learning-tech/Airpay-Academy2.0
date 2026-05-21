<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Web Push sender — Phase B.2.5.
 *
 * Phase B.2 shipped this as a STUB that just logged payloads. Phase B.2.5
 * (this version) does real delivery: encrypts the payload per RFC 8291,
 * signs a VAPID JWT per RFC 8292, and POSTs to the push service endpoint.
 *
 * Wire diagram (per push):
 *
 *   plaintext (JSON {title,body,url,tag,requireInteraction})
 *        │
 *        ▼
 *   payload_encrypter::encrypt_for_subscription()
 *        │  uses ephemeral P-256 keypair + ECDH + HKDF + AES-128-GCM
 *        ▼
 *   binary ciphertext  (salt || rs || idlen || keyid || ct || tag)
 *        │
 *        │  ┌─ jwt_signer::sign_for_endpoint() ── VAPID JWT
 *        │  │
 *        ▼  ▼
 *   POST  endpoint
 *         Authorization: vapid t=<jwt>,k=<vapid_public>
 *         Content-Encoding: aes128gcm
 *         Content-Type: application/octet-stream
 *         TTL: <ttl_seconds>
 *
 * Result handling:
 *   201/204 → success → subscription_manager::record_success()
 *   404/410 → subscription gone → delete (forces user to re-subscribe)
 *   400/403 → bad request / bad JWT → record_failure (we have a bug)
 *   413     → payload too large → log + record_failure
 *   429/5xx → transient → record_failure (auto-purges after MAX_FAILURES)
 *
 * SECURITY: see SECURITY NOTE in jwt_signer.php. Phase B.2.5 ALPHA — must
 * pass cli/test_crypto_vectors before flipping sentientia.pwa.push.enabled
 * ON in production.
 *
 * @package local_sentientia_pwa
 */
class push_sender {

    public const FLAG_KEY = 'sentientia.pwa.push.enabled';

    /** Default TTL — push service holds the message this long if device offline. */
    public const DEFAULT_TTL = 86400;

    /** Per-request HTTP timeout (seconds). Push services should respond fast. */
    public const HTTP_TIMEOUT = 15;

    /** Urgency values per RFC 8030 §5.3. */
    public const URGENCY_VERY_LOW = 'very-low';
    public const URGENCY_LOW = 'low';
    public const URGENCY_NORMAL = 'normal';
    public const URGENCY_HIGH = 'high';

    /**
     * Send a push notification to every subscription a user has.
     *
     * @param int    $userid    Recipient
     * @param string $title     Notification title
     * @param string $body      Notification body
     * @param string $url       URL to open when user clicks (default: /my/)
     * @param string $tag       Optional collapse tag
     * @param bool   $require_interaction True = stays until dismissed
     * @return int Number of subscriptions successfully delivered to
     */
    public static function send(int $userid, string $title, string $body,
                                 string $url = '', string $tag = '',
                                 bool $require_interaction = false): int {
        if (!self::is_enabled()) {
            return 0;
        }

        $subs = subscription_manager::for_user($userid);
        if (empty($subs)) {
            return 0;
        }

        $payload = [
            'title' => $title,
            'body'  => $body,
        ];
        if ($url !== '') {
            $payload['url'] = $url;
        }
        if ($tag !== '') {
            $payload['tag'] = $tag;
        }
        if ($require_interaction) {
            $payload['requireInteraction'] = true;
        }

        $delivered = 0;
        foreach ($subs as $sub) {
            try {
                $result = self::deliver_one($sub, $payload);
                if ($result === true) {
                    $delivered++;
                    subscription_manager::record_success((int) $sub->id);
                } elseif ($result === 'gone') {
                    // Subscription revoked client-side — delete the row so
                    // future sends don't retry.
                    subscription_manager::delete((int) $sub->userid, $sub->endpoint);
                } else {
                    subscription_manager::record_failure((int) $sub->id);
                }
            } catch (\Throwable $e) {
                debugging(sprintf(
                    '[sentientia_pwa] push delivery threw for sub %d: %s',
                    (int) $sub->id,
                    $e->getMessage()
                ), DEBUG_DEVELOPER);
                subscription_manager::record_failure((int) $sub->id);
            }
        }
        return $delivered;
    }

    /**
     * Is push delivery enabled (feature flag check)?
     */
    public static function is_enabled(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return false;
        }
        try {
            return \local_airpay_core\feature_flags::is_enabled(self::FLAG_KEY);
        } catch (\Throwable $e) {
            debugging('push_sender: feature flag lookup failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Deliver a single push.
     *
     * @param \stdClass $sub      Row from local_sentientia_push_subs.
     * @param array     $payload  Notification payload (will be JSON-encoded).
     * @return bool|string  true on success, 'gone' on 404/410 (caller deletes
     *                       the subscription), false on any other failure.
     */
    protected static function deliver_one(\stdClass $sub, array $payload): bool|string {
        if (empty(vapid_key_manager::get_public_key())) {
            debugging('[sentientia_pwa] no VAPID keypair — run cli/generate_vapid_keys.php',
                DEBUG_NORMAL);
            return false;
        }

        $plaintext = self::json_encode_safe($payload);
        if (strlen($plaintext) > self::get_max_payload_bytes()) {
            debugging(sprintf(
                '[sentientia_pwa] payload too large: %d > %d bytes — truncating',
                strlen($plaintext),
                self::get_max_payload_bytes()
            ), DEBUG_NORMAL);
            // Truncate the body so something still goes through.
            $payload['body'] = substr($payload['body'] ?? '', 0, 200) . '…';
            $plaintext = self::json_encode_safe($payload);
        }

        // 1. Encrypt payload (RFC 8291 aes128gcm).
        $enc = payload_encrypter::encrypt_for_subscription(
            $plaintext,
            $sub->p256dh,
            $sub->auth_secret
        );

        // 2. Sign VAPID JWT for this endpoint's origin (RFC 8292).
        $jwt = jwt_signer::sign_for_endpoint($sub->endpoint);

        // 3. POST to push service.
        $vapid_pub = vapid_key_manager::get_public_key();  // already b64url
        $ttl = self::get_default_ttl();

        $headers = [
            'Authorization: vapid t=' . $jwt . ',k=' . $vapid_pub,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: ' . $ttl,
            'Urgency: ' . self::URGENCY_NORMAL,
        ];

        $http_code = self::http_post_binary($sub->endpoint, $enc['ciphertext'], $headers);

        return self::classify_response($http_code);
    }

    /**
     * Map HTTP response code from a push service to our delivery outcome.
     *
     * @param int $code HTTP status code (0 = no response, e.g. network error).
     * @return bool|string  true = success, 'gone' = delete sub, false = retry/failure.
     */
    private static function classify_response(int $code): bool|string {
        if ($code === 201 || $code === 200 || $code === 202 || $code === 204) {
            return true;
        }
        if ($code === 404 || $code === 410) {
            // Subscription revoked or expired client-side. Tell caller to nuke it.
            return 'gone';
        }
        // Everything else — bad request (400), bad JWT (403), payload too big (413),
        // rate limited (429), or transient 5xx — counts as failure.
        debugging(sprintf(
            '[sentientia_pwa] push service returned HTTP %d (non-success)',
            $code
        ), DEBUG_DEVELOPER);
        return false;
    }

    /**
     * HTTP POST with binary body. Uses Moodle's curl wrapper which respects
     * proxy settings and is the recommended outbound HTTP path.
     *
     * @return int HTTP status code (0 on network failure).
     */
    private static function http_post_binary(string $endpoint, string $binary, array $headers): int {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl([
            'cache' => false,
        ]);
        // Moodle's curl class takes headers via setHeader().
        foreach ($headers as $h) {
            $curl->setHeader($h);
        }

        // Use the underlying handle for binary POST without
        // CURLOPT_POSTFIELDS string mangling.
        $options = [
            'CURLOPT_RETURNTRANSFER' => 1,
            'CURLOPT_POST'           => 1,
            'CURLOPT_POSTFIELDS'     => $binary,
            'CURLOPT_TIMEOUT'        => self::HTTP_TIMEOUT,
            'CURLOPT_FOLLOWLOCATION' => 0,  // Push services don't redirect.
        ];

        $response = $curl->post($endpoint, $binary, $options);
        $info = $curl->get_info();
        return (int) ($info['http_code'] ?? 0);
    }

    /**
     * Read the admin-configured default TTL. Falls back to DEFAULT_TTL.
     */
    public static function get_default_ttl(): int {
        $v = get_config('local_sentientia_pwa', 'default_ttl');
        if ($v === false || $v === '' || !ctype_digit((string) $v)) {
            return self::DEFAULT_TTL;
        }
        return max(0, (int) $v);
    }

    /**
     * Read the admin-configured max payload size. Falls back to 3500 bytes.
     */
    public static function get_max_payload_bytes(): int {
        $v = get_config('local_sentientia_pwa', 'max_payload_bytes');
        if ($v === false || $v === '' || !ctype_digit((string) $v)) {
            return 3500;
        }
        return max(100, (int) $v);
    }

    /**
     * JSON encode without HTML escaping (saves bytes in tight payloads).
     */
    private static function json_encode_safe(array $payload): string {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json !== false ? $json : '{"title":"Sentientia LMS","body":"Update"}';
    }

    /**
     * Send the same notification to multiple users.
     *
     * @param int[]  $user_ids
     * @return int Total deliveries across all users.
     */
    public static function send_to_many(array $user_ids, string $title,
                                         string $body, string $url = ''): int {
        $total = 0;
        foreach ($user_ids as $uid) {
            $total += self::send((int) $uid, $title, $body, $url);
        }
        return $total;
    }
}
