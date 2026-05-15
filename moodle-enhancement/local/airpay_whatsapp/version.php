<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Plugin version — Airpay WhatsApp + SMS engagement channel.
 *
 * Phase A1 iter 1 (2026-05-15) — opt-in + preference UI only. No
 * external API integration yet — that's iter 3 with DLT compliance
 * and provider sign-off.
 *
 * @package    local_airpay_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_whatsapp';
$plugin->version   = 2026051501;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // mock-mode only — [CONFIRM] required before live
$plugin->release   = '0.2.0-alpha';
$plugin->dependencies = [
    'local_airpay_core' => 2026051401,  // feature_flags resolver
];
// Release history
// 0.1.0-alpha  Phase A1 iter 1: opt-in + preference UI
//                + local_airpay_user_channel_prefs + audit tables
//                + classes/preference_manager + privacy/provider
//                + 13 PHPUnit tests
//              No external sending yet.
// 0.2.0-alpha  Phase A1 iters 2-5 scaffolded in mock mode:
//                + local_airpay_dlt_templates table + registry class
//                + local_airpay_send_log table + send-log class
//                + classes/whatsapp_client + sms_client (mock-mode default)
//                + classes/channel_router (cadence-engine integration)
//                + 5 seeded DLT templates (enrolment / completion /
//                  deadline 7d/3d/1d / overdue / streak)
//                + admin/templates.php template management UI
//                + classes/analytics for channel-mix reporting
//                + Hi/Kn/Mr/Sw translations
//              External provider sending still [CONFIRM]-gated — flipping
//              from mock to live mode requires Switchboard flag toggle +
//              DLT registration + Karix/MSG91 credentials.
