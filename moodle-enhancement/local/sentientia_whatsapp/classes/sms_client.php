<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SMS client — Phase A1 iter 4.
 *
 * Same pattern as whatsapp_client but for SMS. Provider: MSG91
 * (recommended for Indian SME pricing). Same MOCK / LIVE gate.
 *
 * @package local_sentientia_whatsapp
 */

namespace local_sentientia_whatsapp;

defined('MOODLE_INTERNAL') || die();

class sms_client {

    public static function send_template(
        int $userid,
        string $template_key,
        array $variables = [],
        array $opts = []
    ): array {
        global $DB;

        $language = $opts['language'] ?? 'en';
        $force_mock = !empty($opts['force_mock']);

        $prefs = preference_manager::get($userid);

        // Gate 1: user opted in?
        if (empty($prefs->sms_optin)) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'sms',
                'template_key' => $template_key,
                'status'       => send_log::STATUS_OPTED_OUT,
                'failure_reason' => 'User has not opted in to SMS delivery',
            ]);
            return [
                'sent'   => false,
                'status' => 'opted_out',
                'log_id' => $log_id,
                'error'  => null,
            ];
        }

        // Gate 2: mobile number?
        if (empty($prefs->mobile_number)) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'sms',
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

        // Gate 3: DLT-approved template for SMS?
        $template = dlt_template_registry::get_approved(
            $template_key, 'sms', $language
        );
        if (!$template) {
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'sms',
                'template_key' => $template_key,
                'recipient'    => $prefs->mobile_number,
                'status'       => send_log::STATUS_FAILED,
                'failure_reason' => 'Template not in approved DLT state for channel=sms lang=' . $language,
            ]);
            return [
                'sent'   => false,
                'status' => 'no_template',
                'log_id' => $log_id,
                'error'  => 'template_not_approved',
            ];
        }

        // Gate 4: feature flag + config + dev-mode kill switch.
        $flag_on = class_exists('\\local_airpay_core\\feature_flags')
            && \local_airpay_core\feature_flags::is_enabled('engagement.sms.enabled');
        $api_key = get_config('local_sentientia_whatsapp', 'msg91_api_key');
        $noever = !empty($GLOBALS['CFG']->noemailever);
        $is_mock = $force_mock || !$flag_on || empty($api_key) || $noever;

        $rendered = dlt_template_registry::render($template->body, $variables);

        if ($is_mock) {
            $reasons = [];
            if ($force_mock)   { $reasons[] = 'force_mock'; }
            if (!$flag_on)     { $reasons[] = 'engagement.sms.enabled flag OFF'; }
            if (empty($api_key)) { $reasons[] = 'no msg91_api_key configured'; }
            if ($noever)       { $reasons[] = '$CFG->noemailever set'; }
            $log_id = send_log::record([
                'userid'       => $userid,
                'channel'      => 'sms',
                'template_key' => $template_key,
                'recipient'    => $prefs->mobile_number,
                'status'       => send_log::STATUS_MOCKED,
                'mock_mode'    => 1,
                'failure_reason' => 'MOCK: ' . implode(', ', $reasons)
                                    . ' | rendered: ' . substr($rendered, 0, 200),
            ]);
            return [
                'sent'   => true,
                'status' => 'mocked',
                'log_id' => $log_id,
                'error'  => null,
            ];
        }

        // LIVE PATH — [CONFIRM] required. Same reasoning as whatsapp_client.
        // MSG91 endpoint:
        //   POST https://control.msg91.com/api/v5/flow/
        //   Headers: authkey: $api_key
        //   Body: {
        //     "template_id": $template->dlt_id,
        //     "short_url": "0",
        //     "recipients": [{
        //       "mobiles": $prefs->mobile_number,
        //       "firstname": $variables['firstname'], ...
        //     }]
        //   }
        // Currently falls back to mock — see whatsapp_client.php for the
        // pre-flight checklist that must clear before flipping to live.

        $log_id = send_log::record([
            'userid'       => $userid,
            'channel'      => 'sms',
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
}
