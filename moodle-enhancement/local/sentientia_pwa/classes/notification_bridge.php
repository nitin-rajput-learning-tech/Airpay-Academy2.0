<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa;

defined('MOODLE_INTERNAL') || die();

/**
 * Notification bridge — Phase B.3.
 *
 * Shared helper that other airpay_* plugins call to "also push" after
 * they've fired an email via message_send(). One call instead of the
 * ~25 lines of class_exists + flag guards each calling site would need.
 *
 * Usage pattern from a scheduled task:
 *
 *   \message_send($msg);              // existing email path
 *   \local_sentientia_pwa\notification_bridge::also_push(
 *       $user,                         // recipient (the same one who got the email)
 *       'sentientia.pwa.push.reminders', // sub-channel flag — controls per-channel kill switch
 *       $title, $body, $url,            // push content (use short strings — see RECORD_SIZE in payload_encrypter)
 *       $tag,                           // stable collapse tag (md5 of channel + identity)
 *       false                           // require_interaction (true = stays until dismissed)
 *   );
 *
 * Soft-coupled: callers MUST check class_exists before calling this OR
 * just call it inside a try/catch that swallows MissingClass — the
 * goal is that uninstalling local_sentientia_pwa does not break any
 * other plugin's cron.
 *
 * @package local_sentientia_pwa
 */
class notification_bridge {

    /**
     * Fire a push notification, gated on:
     *   1. push_sender::is_enabled()   — master flag sentientia.pwa.push.enabled
     *   2. $sub_flag_key flag          — channel-specific flag (e.g. push.reminders)
     *   3. user has a push subscription
     *
     * All errors are caught — push delivery NEVER throws back to the
     * caller. The companion email already went through; push is gravy.
     *
     * @param \stdClass $user                 Recipient (must have ->id)
     * @param string    $sub_flag_key         Feature flag key (channel-specific)
     * @param string    $title                Push title (≤ 50 chars best)
     * @param string    $body                 Push body (≤ 120 chars best)
     * @param string    $url                  Click-through URL (absolute or root-relative)
     * @param string    $tag                  Stable collapse tag
     * @param bool      $require_interaction  Stay until user dismisses?
     * @return int Number of devices the push reached, or 0 on any short-circuit.
     */
    public static function also_push(\stdClass $user,
                                      string $sub_flag_key,
                                      string $title,
                                      string $body,
                                      string $url = '',
                                      string $tag = '',
                                      bool $require_interaction = false): int {
        try {
            if (empty($user->id)) {
                return 0;
            }
            if (!push_sender::is_enabled()) {
                return 0;
            }
            if (!self::sub_channel_on($sub_flag_key)) {
                return 0;
            }
            return push_sender::send(
                (int) $user->id,
                $title,
                $body,
                $url,
                $tag,
                $require_interaction
            );
        } catch (\Throwable $e) {
            // Don't poison the caller's run. Surface to debug log only.
            debugging(
                '[sentientia_pwa] also_push failed for user '
                . ($user->id ?? 0) . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
            return 0;
        }
    }

    /**
     * Check a sub-channel feature flag (e.g. sentientia.pwa.push.reminders).
     * Returns true if either the flag system is unavailable (fail open in
     * dev) OR the flag is explicitly ON.
     */
    private static function sub_channel_on(string $flag_key): bool {
        if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
            return true;  // dev environments without feature_flags installed
        }
        try {
            return \local_sentientia_platform\feature_flags::is_enabled($flag_key);
        } catch (\Throwable $e) {
            return false;  // err on the side of NO push if the flag system errors
        }
    }
}
