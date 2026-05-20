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
                          (subscription table, WS endpoint, sender) ships.',
    ],

];
