<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * WhatsApp Business API client — Phase A1 iter 3.
 *
 * Provider abstraction. Two operating modes:
 *
 *   MOCK MODE (default, shipped state):
 *     - send_template() logs to local_sentientia_send_log with status=mocked
 *     - No external HTTP request fires
 *     - Safe to enable on dev / staging without risking real sends
 *
 *   LIVE MODE (requires [CONFIRM]):
 *     - Requires the engagement.whatsapp.enabled feature flag ON
 *     - Requires a Karix/Meta API key in plugin config
 *     - Requires the template to be in `approved` DLT state
 *     - Mock mode is force-on if any pre-flight check fails — never
 *       silently downgrade by sending without all gates
 *
 * Provider: Karix (recommended for Indian SME pricing per the plan).
 * The Karix-specific HTTP code is documented but commented out — it
 * will be uncommented when [CONFIRM] is given to flip to live mode.
 *
 * @package    local_sentientia_whatsapp
 */

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

class whatsapp_client {

    /**
     * Send a templated WhatsApp message.
     *
     * @param int $userid             Recipient — used for opt-in check + audit
     * @param string $template_key    e.g. 'course_enrolment'
     * @param array $variables        e.g. ['firstname' => 'Sarah', 'coursename' => 'KYC']
     * @param array $opts             Optional: ['language' => 'en', 'force_mock' => true]
     * @return array {
     *   bool   $sent     Whether the send was attempted
     *   string $status   'mocked' | 'sent' | 'failed' | 'opted_out' | 'no_template'
     *   int    $log_id   ID of the local_sentientia_send_log row
     *   string|null $error  Failure reason if status === 'failed'
     * }
     */
    public static function send_template(
        int $userid,
        string $template_key,
        array $variables = [],
        array $opts = []
    ): array {
        global $DB;

        $language = $opts['language'] ?? 'en';
        $force_mock = !empty($opts['force_mock']);

        // ── Pre-flight gates ─────────────────────────────────────────
        // Any failure short-circuits with a recorded log entry + status.
        // The cadence engine reads the return value to decide whether
        // to cascade to SMS or email.

        // Gate 1: user opted in?
        $prefs = preference_manager::get($userid);
        if (empty($prefs->whatsapp_optin)) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'whatsapp',
                'template_key' => $template_key,
                'status'       => send_log::STATUS_OPTED_OUT,
                'failure_reason' => 'User has not opted in to WhatsApp delivery',
            ]);
            return [
                'sent'   => false,
                'status' => 'opted_out',
                'log_id' => $log_id,
                'error'  => null,
            ];
        }

        // Gate 2: mobile number on file?
        if (empty($prefs->mobile_number)) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'whatsapp',
                'template_key' => $template_key,
                'status'       => send_log::STATUS_FAILED,
                'failure_reason' => 'No mobile number on file',
            ]);
            return [
                'sent'   => false,
                'status' => 'failed',
                'log_id' => $log_id,
                'error'  => 'no_mobile_number',
            ];
        }

        // Gate 3: DLT-approved template?
        $template = dlt_template_registry::get_approved(
            $template_key, 'whatsapp', $language
        );
        if (!$template) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'whatsapp',
                'template_key' => $template_key,
                'recipient'    => $prefs->mobile_number,
                'status'       => send_log::STATUS_FAILED,
                'failure_reason' => 'Template not in approved DLT state for channel=whatsapp lang=' . $language,
            ]);
            return [
                'sent'   => false,
                'status' => 'no_template',
                'log_id' => $log_id,
                'error'  => 'template_not_approved',
            ];
        }

        // Gate 4: feature flag ON for the tenant?
        $flag_on = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('engagement.whatsapp.enabled');

        // Determine mock vs live. We mock if EITHER:
        //  - caller explicitly passed force_mock=true (test code)
        //  - feature flag is OFF
        //  - no Karix API key configured
        //  - $CFG->noemailever is set (preserves the dev-mode "no real
        //    external messages ever" contract that the email cadence
        //    engine already respects)
        $api_key = get_config('local_sentientia_whatsapp', 'karix_api_key');
        $noever = !empty($GLOBALS['CFG']->noemailever);
        $is_mock = $force_mock || !$flag_on || empty($api_key) || $noever;

        // Render the template body for the log (whether mock or live).
        $rendered = dlt_template_registry::render($template->body, $variables);

        if ($is_mock) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'whatsapp',
                'template_key' => $template_key,
                'recipient'    => $prefs->mobile_number,
                'status'       => send_log::STATUS_MOCKED,
                'mock_mode'    => 1,
                'failure_reason' => self::why_mock($force_mock, $flag_on, $api_key, $noever)
                                    . ' | rendered: ' . substr($rendered, 0, 200),
            ]);
            return [
                'sent'   => true,
                'status' => 'mocked',
                'log_id' => $log_id,
                'error'  => null,
            ];
        }

        // ── LIVE PATH — currently disabled. [CONFIRM] required. ──────
        // The actual Karix WhatsApp Business API call goes here. Per
        // CLAUDE.md, POSTing to external providers requires [CONFIRM]
        // from Nitin. This branch is intentionally a fallback to mock
        // — flipping to real send requires:
        //   1. L&D + Legal sign-off on the 5 DLT templates (recorded in PHASE-A1-WHATSAPP-SMS-PLAN.md pre-flight)
        //   2. DLT portal registration completed (operator-side approval)
        //   3. Karix account + API key set via plugin settings
        //   4. Phase A0 flag engagement.whatsapp.enabled toggled ON
        //   5. This block uncommented and the mock fall-through above removed
        //
        // /*
        // $endpoint = 'https://api.karix.io/whatsapp/v1/messages';
        // $payload = [
        //     'to'         => $prefs->mobile_number,
        //     'templateId' => $template->dlt_id,
        //     'params'     => array_values($variables),
        // ];
        // $response = self::http_post($endpoint, $payload, [
        //     'Authorization: Bearer ' . $api_key,
        //     'Content-Type: application/json',
        // ]);
        // ... parse response, record log, return ...
        // */

        // Until [CONFIRM], live mode falls back to mock to preserve the
        // contract that no real WhatsApp messages leave this plugin.
        $log_id = send_log::record([
            'userid'       => $userid,
            'channel'      => 'whatsapp',
            'template_key' => $template_key,
            'recipient'    => $prefs->mobile_number,
            'status'       => send_log::STATUS_MOCKED,
            'mock_mode'    => 1,
            'failure_reason' => 'Live mode not yet [CONFIRM]ed by Nitin — see PHASE-A1-WHATSAPP-SMS-PLAN.md pre-flight checklist',
        ]);
        return [
            'sent'   => true,
            'status' => 'mocked',
            'log_id' => $log_id,
            'error'  => null,
        ];
    }

    /**
     * Compose a short why-mock explanation for the log audit.
     */
    private static function why_mock(bool $force_mock, bool $flag_on,
            $api_key, bool $noever): string {
        $reasons = [];
        if ($force_mock)   { $reasons[] = 'force_mock'; }
        if (!$flag_on)     { $reasons[] = 'engagement.whatsapp.enabled flag OFF'; }
        if (empty($api_key)) { $reasons[] = 'no karix_api_key configured'; }
        if ($noever)       { $reasons[] = '$CFG->noemailever set'; }
        return 'MOCK: ' . implode(', ', $reasons);
    }
}
