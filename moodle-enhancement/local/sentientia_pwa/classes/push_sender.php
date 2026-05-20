<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Web Push sender — Phase B.2 STUB.
 *
 * In Phase B.2 this class accepts payloads and LOGS them but does NOT
 * actually POST to the push service. Real delivery requires three
 * cryptographic operations (ES256 JWT signing, AES-GCM payload
 * encryption, HKDF key derivation) that are too risky to hand-roll
 * inline — we vendor `minishlink/web-push` (or equivalent) in
 * Phase B.2.5 and swap in the actual HTTP POST.
 *
 * The sender INTERFACE is final in B.2 so other plugins (notifications,
 * emails, courses reminder cron) can already call `push_sender::send()`
 * — when B.2.5 ships, those callsites start delivering for real with
 * no code change.
 *
 * Feature flag: gated on `sentientia.pwa.push.enabled` (default OFF in
 * B.2). When OFF, `send()` returns 0 without doing anything. When ON,
 * `send()` logs the payload that WOULD be sent (Phase B.2) OR actually
 * sends it (Phase B.2.5).
 *
 * @package local_sentientia_pwa
 */
class push_sender {

    public const FLAG_KEY = 'sentientia.pwa.push.enabled';

    /**
     * Send a push notification to every subscription a user has.
     *
     * @param int    $userid    Recipient
     * @param string $title     Notification title (will appear in OS notification UI)
     * @param string $body      Notification body
     * @param string $url       URL to open when user clicks the notification (default: /my/)
     * @param string $tag       Optional tag — same tag collapses sequential notifications
     * @param bool   $require_interaction True = notification stays until user dismisses
     * @return int Number of subscriptions the push WOULD reach (0 when flag OFF)
     */
    public static function send(int $userid, string $title, string $body,
                                 string $url = '', string $tag = '',
                                 bool $require_interaction = false): int {

        // Gate on the master push flag.
        if (!self::is_enabled()) {
            return 0;
        }

        $subs = subscription_manager::for_user($userid);
        if (empty($subs)) {
            return 0;
        }

        // Build the payload that ALL subscriptions for this user will receive.
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
            $ok = self::deliver_one($sub, $payload);
            if ($ok) {
                $delivered++;
                subscription_manager::record_success((int) $sub->id);
            } else {
                subscription_manager::record_failure((int) $sub->id);
            }
        }
        return $delivered;
    }

    /**
     * Is the push delivery feature enabled?
     *
     * Wraps the feature_flags resolver with a defensive try/catch so a
     * resolver-class hiccup never breaks a notification cron.
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
     * Deliver to ONE subscription. Phase B.2: stub — logs payload + returns
     * true to record success. Phase B.2.5: replace with real web-push POST.
     *
     * The interface returns bool so callers can update last_seen / fail_count
     * appropriately. When the real implementation lands, returning false on
     * 4xx (subscription gone) triggers auto-purge via record_failure().
     */
    protected static function deliver_one(\stdClass $sub, array $payload): bool {
        $payload_json = json_encode($payload);

        // Phase B.2 stub — log via Moodle's debugging() so the entry shows
        // up in /report/log/ for admin inspection during testing. Phase
        // B.2.5 replaces this block with the actual HTTP POST.
        $log_entry = sprintf(
            '[sentientia_pwa] [STUB] would-push to userid=%d via %s: %s',
            (int) $sub->userid,
            subscription_manager::endpoint_host($sub->endpoint),
            $payload_json
        );

        // Hook into local_airpay_core's structured_logger when available;
        // otherwise fall back to error_log + debugging.
        if (class_exists('\\local_airpay_core\\structured_logger')) {
            try {
                \local_airpay_core\structured_logger::log('pwa.push.stub', [
                    'subscription_id' => (int) $sub->id,
                    'userid'          => (int) $sub->userid,
                    'endpoint_host'   => subscription_manager::endpoint_host($sub->endpoint),
                    'payload_bytes'   => strlen($payload_json),
                    'phase'           => 'B.2-stub',
                ]);
            } catch (\Throwable $e) {
                // Logger error — don't fail the stub.
            }
        }

        debugging($log_entry, DEBUG_DEVELOPER);

        // Phase B.2 returns true unconditionally — the stub always
        // "succeeds" because there's no real network call to fail.
        // Phase B.2.5 returns the actual HTTP success status.
        return true;
    }

    /**
     * Convenience helper for callers who want to send to multiple users.
     * Returns the total number of subscriptions reached across all users.
     *
     * @param int[]  $user_ids
     * @param string $title
     * @param string $body
     * @param string $url
     * @return int
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
