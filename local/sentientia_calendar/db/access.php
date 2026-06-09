<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS Calendar Sync.
 *
 *   subscribe    — manage own subscription URL (view + regenerate)
 *                  Allowed for any authenticated user.
 *   manage_all   — admin override; view + revoke any user's tokens.
 *                  Allowed for managers only.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_calendar:subscribe' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_calendar:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
