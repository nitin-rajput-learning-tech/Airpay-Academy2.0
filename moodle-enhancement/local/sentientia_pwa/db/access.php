<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Anyone authenticated can save / delete THEIR OWN push subscription.
    // The WS endpoint enforces userid=$USER->id, so cross-user writes are
    // impossible regardless of capability state.
    'local/sentientia_pwa:subscribe' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Admin capability to view subscription metrics + manually send test
    // pushes. Phase B.2 only — Phase B.3 ships the admin dashboard.
    // ADR-031 (2026-09-25): no archetype default. The push log is a
    // platform-operations view (VAPID keys, provider health), linked only
    // from site config, and every tenant admin holds a manager-archetype
    // role at system context. push_logger also confines any holder to
    // their own tenant. db/upgrade.php 2026092500 revokes existing grants.
    'local/sentientia_pwa:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
