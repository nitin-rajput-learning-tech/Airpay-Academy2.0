<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_pwa.
 *
 * @package local_sentientia_pwa
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    // ─── Sentientia category — PWA + offline ─────────────────────
    'sentientia.pwa.enabled' => [
        'default'     => true,
        'description' => 'Sentientia LMS Progressive Web App (PWA). When ON,
                          the service worker is registered on every page —
                          unlocks the manifest-driven install prompt
                          (footer.mustache beforeinstallprompt handler)
                          AND the offline shell. When OFF, the registration
                          script short-circuits and the browser falls back
                          to standard website behaviour. Manifest itself
                          stays present (set in head.mustache); the SW is
                          what makes the install actually work.',
    ],
    'sentientia.pwa.push.enabled' => [
        'default'     => false,
        'description' => 'Phase B.2+ — Web Push notifications via the
                          service worker. Default OFF until the backend
                          (subscription table, WS endpoint, sender) ships.
                          This is the MASTER push flag — all push channels
                          (sentientia.pwa.push.*) require this to be ON too.',
    ],

    // ─── Phase B.3 — push delivery sub-channels ──────────────────────
    // Each sub-channel is independently flagable so admins can enable
    // course reminders WITHOUT enabling, say, overdue manager escalation.
    // ALL sub-channels also require sentientia.pwa.push.enabled = true.
    'sentientia.pwa.push.reminders' => [
        'default'     => false,
        'description' => 'Phase B.3 — push deadline reminders to learners.
                          Fires alongside the existing email reminder
                          from local_airpay_courses\\task\\course_reminder.
                          Only fires for users who have a push subscription
                          AND sentientia.pwa.push.enabled is ON.',
    ],
    'sentientia.pwa.push.overdue' => [
        'default'     => false,
        'description' => 'Phase B.3 — push overdue escalations to managers
                          when a learner misses a deadline. Companion to
                          the existing local_airpay_courses\\task\\course_overdue
                          email escalation.',
    ],

    // ─── Phase D.1 — PWA install UX (per ADR-005) ────────────────────
    'sentientia.pwa.install.enabled' => [
        'default'     => false,
        'description' => 'Phase D.1 (per ADR-005) — show the "Install
                          Sentientia LMS" CTA on the dashboard when the
                          browser fires beforeinstallprompt. Default OFF
                          until the per-customer icon set + manifest
                          branding is fully wired through
                          local_airpay_core::customer::branding(). The
                          manifest endpoint itself
                          (/local/sentientia_pwa/manifest.php) always
                          renders — this flag gates only the visible CTA.',
    ],

];
