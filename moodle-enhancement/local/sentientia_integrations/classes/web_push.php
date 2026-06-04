<?php
/**
 * Web Push Notifications via Firebase Cloud Messaging (FCM).
 * Sends browser push notifications for deadline reminders.
 * Only active when configured with Firebase credentials.
 *
 * Configuration: Site Admin → Plugins → Airpay Integrations → Push Notifications
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 */

namespace local_sentientia_integrations;

defined('MOODLE_INTERNAL') || die();

class web_push {

    /**
     * Check if web push is enabled and configured.
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_sentientia_integrations', 'webpush_enable'))
            && !empty(get_config('local_sentientia_integrations', 'fcm_server_key'));
    }

    /**
     * Send a push notification via FCM.
     *
     * @param string $token FCM device token (stored per user)
     * @param string $title Notification title
     * @param string $body Notification body text
     * @param string $url Click-through URL
     * @param string $icon Icon URL
     * @return bool Success
     */
    public static function send(string $token, string $title, string $body,
                                string $url = '', string $icon = ''): bool {
        if (!self::is_enabled()) {
            return false;
        }

        $serverkey = get_config('local_sentientia_integrations', 'fcm_server_key');

        $payload = [
            'to' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
                'icon' => $icon ?: '/theme/airpayux/pix/brand/favicon_io/android-chrome-192x192.png',
                'click_action' => $url,
            ],
            'data' => [
                'url' => $url,
                'timestamp' => time(),
            ],
        ];

        $ch = curl_init('https://fcm.googleapis.com/fcm/send');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: key=' . $serverkey,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode >= 200 && $httpcode < 300);
    }

    /**
     * Send deadline reminder to a user.
     */
    public static function send_deadline_reminder(int $userid, string $coursename, int $daysremaining): bool {
        global $DB, $CFG;

        $token = $DB->get_field('user_preferences', 'value', [
            'userid' => $userid,
            'name' => 'airpay_fcm_token',
        ]);

        if (empty($token)) {
            return false; // User hasn't subscribed to push notifications.
        }

        return self::send(
            $token,
            "⏰ {$daysremaining} days left",
            "Complete {$coursename} before the deadline",
            $CFG->wwwroot . '/my/',
        );
    }

    /**
     * Store FCM token for a user (called from service worker registration).
     */
    public static function store_token(int $userid, string $token): void {
        global $DB;

        $existing = $DB->get_record('user_preferences', [
            'userid' => $userid,
            'name' => 'airpay_fcm_token',
        ]);

        if ($existing) {
            $existing->value = $token;
            $DB->update_record('user_preferences', $existing);
        } else {
            $pref = new \stdClass();
            $pref->userid = $userid;
            $pref->name = 'airpay_fcm_token';
            $pref->value = $token;
            $DB->insert_record('user_preferences', $pref);
        }
    }
}
