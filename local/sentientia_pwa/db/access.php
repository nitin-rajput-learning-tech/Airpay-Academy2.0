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
    'local/sentientia_pwa:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
