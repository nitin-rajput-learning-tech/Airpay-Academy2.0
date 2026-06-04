<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Install hook for local_sentientia_whatsapp.
 *
 * Seeds the 5 starter DLT templates from PHASE-A1-WHATSAPP-SMS-PLAN §2.
 * Templates start in `pending` state — they need to be submitted to the
 * DLT portal and approved before the cadence engine will send them.
 *
 * Template keys are kept stable so the cadence engine can reference
 * them by name (e.g. 'course_enrolment') without depending on numeric
 * IDs that change between installs.
 *
 * @package local_sentientia_whatsapp
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_sentientia_whatsapp_install(): void {
    seed_starter_templates();
}

function seed_starter_templates(): void {
    $now = time();
    $templates = [
        // ── Course enrolment confirmation (transactional) ────────────
        [
            'template_key' => 'course_enrolment',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Hi {{firstname}}, you've been enrolled in {{coursename}} at Airpay Academy. Start here: {{course_url}}",
        ],
        [
            'template_key' => 'course_enrolment',
            'channel'      => 'sms',
            'category'     => 'transactional',
            'body'         => "AIRPAY: {{firstname}}, you're enrolled in {{coursename}}. Start: {{course_url}}",
        ],

        // ── Course completion + certificate ready (transactional) ────
        [
            'template_key' => 'course_completed',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Congratulations {{firstname}}! You've completed {{coursename}}. Your certificate is ready: {{certificate_url}}",
        ],
        [
            'template_key' => 'course_completed',
            'channel'      => 'sms',
            'category'     => 'transactional',
            'body'         => "AIRPAY: {{firstname}}, you completed {{coursename}}. Certificate: {{certificate_url}}",
        ],

        // ── Compliance deadline reminders (transactional) ────────────
        [
            'template_key' => 'deadline_7d',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Reminder: {{coursename}} is due in 7 days ({{duedate}}). Resume: {{course_url}}",
        ],
        [
            'template_key' => 'deadline_3d',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Reminder: {{coursename}} is due in 3 days ({{duedate}}). Resume: {{course_url}}",
        ],
        [
            'template_key' => 'deadline_1d',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "URGENT: {{coursename}} is due tomorrow ({{duedate}}). Complete now: {{course_url}}",
        ],
        [
            'template_key' => 'deadline_3d',
            'channel'      => 'sms',
            'category'     => 'transactional',
            'body'         => "AIRPAY: {{coursename}} due in 3 days. {{course_url}}",
        ],
        [
            'template_key' => 'deadline_1d',
            'channel'      => 'sms',
            'category'     => 'transactional',
            'body'         => "AIRPAY URGENT: {{coursename}} due tomorrow. {{course_url}}",
        ],

        // ── Manager team alert: overdue (transactional) ─────────────
        [
            'template_key' => 'team_overdue',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Hi {{firstname}}, your team has {{overdue_count}} overdue course(s). Review: {{manager_url}}",
        ],

        // ── Streak milestone (promotional — needs explicit consent) ──
        [
            'template_key' => 'streak_milestone',
            'channel'      => 'whatsapp',
            'category'     => 'promotional',
            'body'         => "Hi {{firstname}}, congratulations on your {{streak_days}}-day learning streak! Keep going: {{dashboard_url}}",
        ],

        // ── Stream F / Wave E2 P4 (2026-05-25) — content-event templates ──
        // Four new templates for the WhatsApp content-notification triggers
        // wired by classes/notification_bridge.php + classes/observer.php.
        // All transactional (regulatory-safe — sent in response to an
        // explicit course/cert action; no consent uplift required).

        // (a) New course published in user's catalogue.
        [
            'template_key' => 'content_new_course',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Hi {{firstname}}, a new course is now available in your catalogue: {{course_name}}. Start here: {{course_url}}",
        ],

        // (b) Course due in <48h (separate from the 7d/3d/1d cadence so
        // admins can mute deadline reminders but keep the urgent <48h
        // surface, or vice versa).
        [
            'template_key' => 'content_course_due_soon',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Hi {{firstname}}, {{course_name}} is due in {{deadline}}. Complete it now: {{course_url}}",
        ],

        // (c) Certificate ready.
        [
            'template_key' => 'content_certificate_ready',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Congratulations {{firstname}}! Your certificate for {{course_name}} is ready: {{certificate_url}}",
        ],

        // (d) Learning-path milestone (25 / 50 / 75 / 100%).
        [
            'template_key' => 'content_path_milestone',
            'channel'      => 'whatsapp',
            'category'     => 'transactional',
            'body'         => "Hi {{firstname}}, you've reached {{milestone_label}} of {{path_name}}. Keep going: {{path_url}}",
        ],
    ];

    foreach ($templates as $tpl) {
        // Idempotent — install.php may be re-run via "purge caches and
        // re-install" admin recovery. Skip rows that already exist.
        $existing = \local_sentientia_whatsapp\dlt_template_registry::get(
            $tpl['template_key'],
            $tpl['channel'],
            $tpl['language'] ?? 'en'
        );
        if ($existing) {
            continue;
        }
        \local_sentientia_whatsapp\dlt_template_registry::upsert($tpl);
    }
}
