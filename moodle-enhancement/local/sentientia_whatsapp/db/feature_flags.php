<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_whatsapp.
 *
 * Stream F / Wave E2 P4 (2026-05-25) — registers the new
 * content-notification master flag introduced for Workstream F.
 * Course-content events (new course published, due-soon, certificate
 * ready, learning-path milestone) fan-out via
 * \local_sentientia_whatsapp\notification_bridge::send_*_notification(...)
 * and all of those gates require this flag to be ON.
 *
 * Default OFF — keeps Airpay's existing production behaviour identical
 * until the customer-zero opt-in flips it ON. Per-customer override is
 * supported via the 5-level resolver (ADR-002) once
 * sentientia.customer_level_flags.enabled is also ON.
 *
 * @package local_sentientia_whatsapp
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Stream F / Wave E2 P4 — content notifications ───────────────
    // Master switch for the 4 new content-event triggers introduced by
    // Workstream F. Independent of engagement.whatsapp.reminders /
    // engagement.whatsapp.overdue (Phase C.1) so admins can roll out
    // content-event nudges without re-enabling the reminder cron.
    // ALL of the per-event methods on notification_bridge gate on
    // engagement.whatsapp.enabled (master) AND this flag.
    'sentientia_whatsapp_content_notifications' => [
        'default'     => false,
        'description' => 'WhatsApp content-event notifications (Stream F).
                          Master switch for the 4 new content triggers:
                          new course published in user catalogue, course
                          due in <48h, certificate issued, learning-path
                          milestone reached. Default OFF — per-customer
                          override supported once
                          sentientia.customer_level_flags.enabled is ON.
                          Requires engagement.whatsapp.enabled to also be
                          ON for any actual send to leave mock mode.',
    ],

];
