<?php
/**
 * Unified multi-channel notification sender.
 *
 * Respects $CFG->noemailever, user preferences, and logs all delivery attempts.
 *
 * @package    local_airpay_emails
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_emails;

defined('MOODLE_INTERNAL') || die();

class notification_sender {

    /**
     * Send a notification via one or more channels.
     *
     * @param object $rule      Rule definition from email_rules table
     * @param object $user      Moodle user object (recipient)
     * @param array  $context   Template context variables
     * @param int    $courseid  Optional course ID for logging
     * @return array [{channel, status, log_id}] results per channel
     */
    public static function send(object $rule, object $user, array $context, int $courseid = 0): array {
        global $CFG;

        $channels = array_map('trim', explode(',', $rule->channel));
        $results = [];

        // Resolve tenant for the user.
        $parts = explode('/', trim($user->open_path ?? '', '/'));
        $tenantid = (int)($parts[0] ?? 1);

        // Render the template.
        $templatekey = $rule->template_key ?? '';
        $html = '';
        $subject = $context['subject'] ?? $rule->rule_name;

        if ($templatekey) {
            $context['subject'] = $subject;
            $html = email_renderer::render('local_airpay_emails/' . $templatekey, $context, $user->id);
        }

        foreach ($channels as $channel) {
            $channel = trim($channel);
            $status = 'sent';
            $error = null;

            // Check user preferences.
            if (!self::user_wants_channel($user->id, $rule->rule_type, $channel)) {
                $status = 'suppressed';
                $error = 'User preference: channel disabled';
            } elseif ($channel === 'email' && !empty($CFG->noemailever)) {
                $status = 'suppressed';
                $error = 'Local dev: $CFG->noemailever = true';
            } else {
                try {
                    switch ($channel) {
                        case 'email':
                        case 'popup':
                            self::send_moodle_message($user, $subject, $html, $rule);
                            break;
                        case 'teams':
                            // Microsoft Teams via webhook (future).
                            $status = 'suppressed';
                            $error = 'Teams channel not yet configured';
                            break;
                        case 'push':
                            // Web push (future).
                            $status = 'suppressed';
                            $error = 'Push channel not yet configured';
                            break;
                        default:
                            $status = 'failed';
                            $error = "Unknown channel: $channel";
                    }
                } catch (\Exception $e) {
                    $status = 'failed';
                    $error = $e->getMessage();
                }
            }

            // Log the delivery attempt.
            $logid = delivery_log::log([
                'rule_id'       => $rule->id ?? null,
                'userid'        => $user->id,
                'courseid'      => $courseid,
                'tenant_id'     => $tenantid,
                'channel'       => $channel,
                'subject'       => $subject,
                'template_key'  => $templatekey,
                'status'        => $status,
                'error_message' => $error,
            ]);

            $results[] = ['channel' => $channel, 'status' => $status, 'log_id' => $logid];
        }

        return $results;
    }

    /**
     * Send via Moodle's message_send (handles both popup and email).
     */
    private static function send_moodle_message(object $user, string $subject,
                                                 string $html, object $rule): void {
        $message = new \core\message\message();
        $message->component         = 'local_airpay_emails';
        $message->name              = 'notification_alert';
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user->id;
        $message->subject           = $subject;
        $message->fullmessage       = html_to_text($html);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = $html;
        $message->smallmessage      = $subject;
        $message->notification      = 1;

        message_send($message);
    }

    /**
     * Check if a user wants notifications on a specific channel.
     *
     * @param int    $userid
     * @param string $ruletype
     * @param string $channel
     * @return bool
     */
    private static function user_wants_channel(int $userid, string $ruletype, string $channel): bool {
        global $DB;

        $field = 'channel_' . $channel;
        $validfields = ['channel_email', 'channel_popup', 'channel_teams', 'channel_push'];
        if (!in_array($field, $validfields)) {
            return true; // Unknown channel — allow by default.
        }

        // Check specific rule type preference.
        $pref = $DB->get_record('local_airpay_email_prefs', [
            'userid'    => $userid,
            'rule_type' => $ruletype,
        ]);
        if ($pref) {
            return (bool)$pref->$field;
        }

        // Check global preference.
        $pref = $DB->get_record('local_airpay_email_prefs', [
            'userid'    => $userid,
            'rule_type' => 'all',
        ]);
        if ($pref) {
            return (bool)$pref->$field;
        }

        return true; // No preference set — allow.
    }
}
