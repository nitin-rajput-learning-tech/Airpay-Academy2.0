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
     * Sprint B (2026-05-13): the optional $options array now supports
     * a `certificate_issue` key. When present (a `tool_certificate_issues`
     * row), the email channel attaches the generated PDF via
     * `email_to_user($attachment, $attachname)` — that's the only Moodle
     * API surface that supports attachments (`message_send()` cannot).
     * Other channels (popup, teams, push) ignore the attachment.
     *
     * @param object $rule      Rule definition from email_rules table
     * @param object $user      Moodle user object (recipient)
     * @param array  $context   Template context variables
     * @param int    $courseid  Optional course ID for logging
     * @param array  $options   Sprint B: ['certificate_issue' => stdClass]
     * @return array [{channel, status, log_id}] results per channel
     */
    public static function send(object $rule, object $user, array $context,
                                 int $courseid = 0, array $options = []): array {
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

        // Sprint B — materialise certificate PDF once for ALL channels in
        // this send (not per channel — that would generate the same PDF
        // multiple times). Materialisation is a no-op when no issue is
        // attached.
        $materialised = null;
        $cert_issue_id = null;
        if (!empty($options['certificate_issue'])) {
            $issue = $options['certificate_issue'];
            $materialised = certificate_helper::materialise_pdf($issue);
            $cert_issue_id = (int) $issue->id;
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
                            // Sprint B: route through email_to_user when an
                            // attachment is present (message_send can't
                            // attach files). Otherwise the existing
                            // message_send path keeps popup-mirror behaviour.
                            if ($materialised) {
                                self::send_email_with_attachment($user, $subject,
                                    $html, $materialised);
                            } else {
                                self::send_moodle_message($user, $subject, $html, $rule);
                            }
                            break;
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

            // Log the delivery attempt — Sprint B adds attachment + cert id.
            $logid = delivery_log::log([
                'rule_id'              => $rule->id ?? null,
                'userid'               => $user->id,
                'courseid'             => $courseid,
                'tenant_id'            => $tenantid,
                'channel'              => $channel,
                'subject'              => $subject,
                'template_key'         => $templatekey,
                'status'               => $status,
                'error_message'        => $error,
                // Record attachment metadata for the audit log even when
                // suppressed — so admins can answer "was this user supposed
                // to receive the certificate?" without inspecting code.
                'attachment_filename'  => ($channel === 'email' && $materialised)
                    ? $materialised['display_name']
                    : null,
                'certificate_issue_id' => ($channel === 'email')
                    ? $cert_issue_id
                    : null,
            ]);

            $results[] = ['channel' => $channel, 'status' => $status, 'log_id' => $logid];
        }

        // Clean up the temp PDF — best effort. Always run, even on failure.
        certificate_helper::cleanup_materialised($materialised);

        return $results;
    }

    /**
     * Send an HTML email with a single file attachment via `email_to_user`.
     *
     * Moodle's `message_send()` API does not carry attachments — only the
     * lower-level `email_to_user()` does. We bypass the message-send
     * pipeline ONLY for the attachment case; everything else still flows
     * through `send_moodle_message()` so popup + email stay synchronised.
     *
     * @param object $user        Recipient user object (must have ->email etc.)
     * @param string $subject
     * @param string $html        Full HTML body
     * @param array  $materialised Output of certificate_helper::materialise_pdf()
     */
    private static function send_email_with_attachment(object $user, string $subject,
                                                        string $html, array $materialised): void {
        $noreply = \core_user::get_noreply_user();
        $textbody = html_to_text($html);

        // email_to_user signature:
        //   email_to_user($user, $from, $subject, $messagetext, $messagehtml,
        //                 $attachment, $attachname, $usetrueaddress, ...)
        // `$attachment` is the RELATIVE path under $CFG->dataroot historically;
        // recent Moodles also accept absolute paths and will resolve them.
        $ok = email_to_user(
            $user, $noreply, $subject,
            $textbody, $html,
            $materialised['rel_path'],
            $materialised['display_name'],
            true   // usetrueaddress
        );
        if (!$ok) {
            throw new \moodle_exception('email_to_user_failed', 'local_airpay_emails');
        }
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
