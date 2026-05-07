<?php
/**
 * Microsoft Teams notification sender.
 * Sends adaptive card messages to a Teams channel via incoming webhook.
 * Only active when teams_enable = 1 and webhook URL is configured.
 *
 * @package    local_airpay_integrations
 * @copyright  2026 Airpay Payment Services
 */

namespace local_airpay_integrations;

defined('MOODLE_INTERNAL') || die();

class teams_notifier {

    /**
     * Check if Teams notifications are enabled and configured.
     */
    public static function is_enabled(): bool {
        $enabled = get_config('local_airpay_integrations', 'teams_enable');
        $webhook = get_config('local_airpay_integrations', 'teams_webhook_url');
        return !empty($enabled) && !empty($webhook);
    }

    /**
     * Send a notification to Teams.
     *
     * @param string $title Card title
     * @param string $message Card body text
     * @param string $color Card accent color (good=green, warning=amber, attention=red)
     * @param string $actionurl Optional button URL
     * @param string $actionlabel Optional button label
     * @return bool Success
     */
    public static function send(string $title, string $message, string $color = 'good',
                                string $actionurl = '', string $actionlabel = ''): bool {
        if (!self::is_enabled()) {
            return false;
        }

        $webhook = get_config('local_airpay_integrations', 'teams_webhook_url');

        // Build adaptive card payload.
        $card = [
            'type' => 'message',
            'attachments' => [[
                'contentType' => 'application/vnd.microsoft.card.adaptive',
                'contentUrl' => null,
                'content' => [
                    '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                    'type' => 'AdaptiveCard',
                    'version' => '1.4',
                    'body' => [
                        [
                            'type' => 'TextBlock',
                            'text' => $title,
                            'weight' => 'bolder',
                            'size' => 'medium',
                            'color' => $color === 'attention' ? 'attention' : ($color === 'warning' ? 'warning' : 'good'),
                        ],
                        [
                            'type' => 'TextBlock',
                            'text' => $message,
                            'wrap' => true,
                        ],
                    ],
                ],
            ]],
        ];

        if (!empty($actionurl)) {
            $card['attachments'][0]['content']['actions'] = [[
                'type' => 'Action.OpenUrl',
                'title' => $actionlabel ?: 'View',
                'url' => $actionurl,
            ]];
        }

        // Send via HTTP POST.
        $ch = curl_init($webhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($card),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpcode >= 200 && $httpcode < 300);
    }

    /**
     * Send enrolment notification.
     */
    public static function notify_enrolment(string $username, string $coursename): bool {
        return self::send(
            '📚 New Enrolment',
            "**{$username}** was enrolled in **{$coursename}**",
            'good'
        );
    }

    /**
     * Send completion notification.
     */
    public static function notify_completion(string $username, string $coursename): bool {
        return self::send(
            '✅ Course Completed',
            "**{$username}** completed **{$coursename}**",
            'good'
        );
    }

    /**
     * Send deadline warning.
     */
    public static function notify_deadline(string $username, string $coursename, int $daysremaining): bool {
        $color = $daysremaining <= 3 ? 'attention' : 'warning';
        return self::send(
            '⏰ Deadline Approaching',
            "**{$username}** has {$daysremaining} days to complete **{$coursename}**",
            $color
        );
    }

    /**
     * Send compliance alert.
     */
    public static function notify_compliance_overdue(string $coursename, int $overduecount): bool {
        return self::send(
            '🚨 Compliance Alert',
            "{$overduecount} employees are overdue on **{$coursename}**",
            'attention'
        );
    }
}
