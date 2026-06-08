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
 * @package    local_sentientia_whatsapp
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_whatsapp';
$plugin->version   = 2026052501;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_ALPHA;   // mock-mode only — [CONFIRM] required before live
$plugin->release   = '0.4.0-alpha';    // Stream F / Wave E2 P4 — content notifications
$plugin->dependencies = [
    'local_sentientia_platform' => 2026051401,  // feature_flags resolver
];
// Release history
// 0.1.0-alpha  Phase A1 iter 1: opt-in + preference UI
//                + local_sentientia_user_channel_prefs + audit tables
//                + classes/preference_manager + privacy/provider
//                + 13 PHPUnit tests
//              No external sending yet.
// 0.2.0-alpha  Phase A1 iters 2-5 scaffolded in mock mode:
//                + local_sentientia_dlt_templates table + registry class
//                + local_sentientia_send_log table + send-log class
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
// 0.3.0-alpha  Stream C / Phase C.1 — notification_bridge + cron hooks
//                + classes/notification_bridge::also_send() wired into
//                  course_reminder + course_overdue + exam_reminder
//                  + exam_overdue tasks.
// 0.4.0-alpha  Stream F / Wave E2 P4 (2026-05-25) — content notifications
//                + 4 new bridge methods (send_new_course_notification,
//                  send_course_due_soon, send_certificate_ready,
//                  send_path_milestone)
//                + classes/observer.php for course_updated +
//                  certificate_issued + course_completed
//                + db/events.php registers all 3 observers
//                + db/feature_flags.php registers new master flag
//                  sentientia_whatsapp_content_notifications (default OFF)
//                + 4 new DLT templates (content_new_course /
//                  content_course_due_soon / content_certificate_ready /
//                  content_path_milestone) seeded via install.php +
//                  upgrade.php (idempotent)
//                + 6h per-(user, template, context) throttle to suppress
//                  duplicate sends.
//                + tests/notification_bridge_content_test.php
//                + Hi lang strings appended.
