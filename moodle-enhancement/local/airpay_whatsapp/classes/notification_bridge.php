<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_whatsapp;

defined('MOODLE_INTERNAL') || die();

/**
 * Notification bridge — Stream C / Phase C.1 (2026-05-21).
 *
 * Mirror of \local_sentientia_pwa\notification_bridge (Phase B.3). Same
 * "also_*" pattern, soft-coupled and error-swallowing, callable from any
 * cron that has just finished its email send via message_send(). Lets
 * the cron fan out to email + push + WhatsApp/SMS in three lines:
 *
 *   \message_send($msg);
 *   \local_sentientia_pwa\notification_bridge::also_push(...);
 *   \local_airpay_whatsapp\notification_bridge::also_send(...);
 *
 * Direct call to whatsapp_client::send_template() — NOT channel_router::
 * dispatch(), because dispatch() cascades to email and email already
 * fired via message_send() upstream. Each call here ALSO logs to
 * local_airpay_send_log so the analytics page can see WhatsApp/SMS
 * delivery alongside email.
 *
 * Two feature flags gate this:
 *   - engagement.whatsapp.enabled   — master flag (already in registry)
 *   - $sub_flag_key                  — channel-specific (per cron type)
 *
 * Plus the whatsapp_client itself gates on:
 *   - user opt-in (preference_manager)
 *   - mobile_number on file
 *   - DLT template approved
 * — so callers don't need to duplicate any of those checks here.
 *
 * @package local_airpay_whatsapp
 */
class notification_bridge {

    /**
     * Fire a WhatsApp send for a single user. Falls back to SMS via the
     * client's own internal cascade (if user has SMS opt-in + no WhatsApp).
     * Email is NOT touched — the caller already sent email upstream.
     *
     * @param \stdClass $user             Recipient (must have ->id)
     * @param string    $sub_flag_key     Feature flag (e.g. engagement.whatsapp.reminders)
     * @param string    $template_key     DLT template key (must be approved for live mode)
     * @param array     $variables        Template substitution vars
     * @param array     $opts             Optional ['language' => 'en'|'hi'|...]
     * @return string|null  'sent' / 'mocked' / 'opted_out' / 'no_template' / 'no_mobile'
     *                       / 'failed', or null if gates blocked the attempt.
     */
    public static function also_send(\stdClass $user,
                                      string $sub_flag_key,
                                      string $template_key,
                                      array $variables = [],
                                      array $opts = []): ?string {
        try {
            if (empty($user->id)) {
                return null;
            }

            // Gate 1 — master flag.
            if (!self::master_flag_on()) {
                return null;
            }

            // Gate 2 — channel-specific sub-flag.
            if (!self::sub_channel_on($sub_flag_key)) {
                return null;
            }

            // Delegate to the existing client. The client handles opt-in,
            // mobile-number presence, and DLT template approval checks.
            // Mock mode is the default — no external HTTP fires until
            // engagement.whatsapp.live_mode is also ON.
            $result = whatsapp_client::send_template(
                (int) $user->id,
                $template_key,
                $variables,
                $opts
            );

            return $result['status'] ?? null;

        } catch (\Throwable $e) {
            debugging(
                '[airpay_whatsapp] also_send failed for user '
                . ($user->id ?? 0) . ' / ' . $template_key
                . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return null;
        }
    }

    /**
     * Check the master WhatsApp flag. Returns true if either:
     *   - feature_flags class isn't loaded (fail-open for dev environments
     *     where local_airpay_core may not be installed)
     *   - the flag is explicitly ON
     */
    private static function master_flag_on(): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return true;
        }
        try {
            return \local_airpay_core\feature_flags::is_enabled(
                'engagement.whatsapp.enabled');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check a sub-channel feature flag (e.g. engagement.whatsapp.reminders).
     */
    private static function sub_channel_on(string $flag_key): bool {
        if (!class_exists('\\local_airpay_core\\feature_flags')) {
            return true;  // dev fail-open
        }
        try {
            return \local_airpay_core\feature_flags::is_enabled($flag_key);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Pick a DLT template key based on days-remaining bucket.
     *
     * @param int $days_remaining
     * @return string template_key — one of: deadline_7d, deadline_3d, deadline_1d
     */
    public static function pick_deadline_template(int $days_remaining): string {
        if ($days_remaining >= 7) {
            return 'deadline_7d';
        }
        if ($days_remaining >= 3) {
            return 'deadline_3d';
        }
        return 'deadline_1d';
    }
}
