<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Channel router — Phase A1 iter 3+. Decides which channel to use for a
 * given (user, notification) and orchestrates the fall-back cascade.
 *
 * Fall-back chain (manifesto §5 + CONFIGURABILITY-ARCHITECTURE §5.3):
 *   1. Try the user's preferred channel
 *   2. If that fails (opted out, no mobile, no template, flag off,
 *      provider failure, bounce, etc.) — cascade down
 *   3. Email is the always-available terminal fallback
 *
 * The cascade is recorded in local_sentientia_send_log so admins can see
 * WHY a message landed on email even though the user preferred WhatsApp.
 *
 * @package local_sentientia_whatsapp
 */

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

class channel_router {

    /**
     * Send a notification through the cascade. Returns the channel that
     * actually succeeded (or 'email' if everything fell back). Tracks
     * each attempted channel in send_log.
     *
     * The email branch is currently a stub — in production, the
     * existing local_sentientia_emails cadence engine receives the call.
     * For now we record a send_log row indicating the email handoff.
     *
     * @param int $userid
     * @param string $template_key
     * @param array $variables
     * @param array $opts            Optional 'language', 'force_mock'
     * @return array {
     *   string $channel  The channel that handled it
     *   array  $attempts Sequence of channel statuses tried
     * }
     */
    public static function dispatch(
        int $userid,
        string $template_key,
        array $variables = [],
        array $opts = []
    ): array {
        $attempts = [];

        // Determine the user's preferred channel — flag- and consent-aware.
        $preferred = preference_manager::resolve_channel($userid);

        if ($preferred === 'whatsapp') {
            $result = whatsapp_client::send_template($userid, $template_key,
                $variables, $opts);
            $attempts[] = ['channel' => 'whatsapp', 'status' => $result['status']];
            // 'mocked' and 'sent' both count as success — mock is the
            // intentional dev-mode state, not a failure.
            if (in_array($result['status'], ['mocked', 'sent'], true)) {
                return ['channel' => 'whatsapp', 'attempts' => $attempts];
            }
            // Fall through to SMS.
            $preferred = 'sms';
        }

        if ($preferred === 'sms') {
            $result = sms_client::send_template($userid, $template_key,
                $variables, $opts);
            $attempts[] = ['channel' => 'sms', 'status' => $result['status']];
            if (in_array($result['status'], ['mocked', 'sent'], true)) {
                return ['channel' => 'sms', 'attempts' => $attempts];
            }
        }

        // Terminal fallback — email. Iter 5 will wire this to the
        // existing local_sentientia_emails cadence engine; for now we
        // record a log row indicating handoff.
        $log_id = send_log::record([
            'userid'       => $userid,
            'channel'      => 'email',
            'template_key' => $template_key,
            'recipient'    => '',  // resolved by the email cadence engine
            'status'       => send_log::STATUS_QUEUED,
            'failure_reason' => 'Channel router handoff to email after cascade',
        ]);
        $attempts[] = ['channel' => 'email', 'status' => 'queued'];

        return [
            'channel'  => 'email',
            'attempts' => $attempts,
            'log_id'   => $log_id,
        ];
    }
}
