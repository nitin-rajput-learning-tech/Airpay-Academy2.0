<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for Sentientia LMS Microsoft 365 integration (Phase C.1).
 *
 *   use         — connect a Microsoft 365 identity to one's Moodle account
 *                 and read information (own profile, own calendar, sites
 *                 the user can see). Default false on every archetype
 *                 because no user should silently gain the ability to
 *                 link a Microsoft account; explicit role assignment is
 *                 required even when the master feature flag is ON.
 *
 *   admin       — view + revoke every user's token, configure tenant +
 *                 client ID + redirect URI + allowed scopes. Manager only.
 *
 * Phase C.1 ships read-only Graph stubs. C.2+ adds writeback capabilities
 * (calendar event creation, document upload) under separate caps.
 *
 * @package local_sentientia_m365
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_m365:use' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        // Default false on every archetype. Site admins must assign the
        // capability explicitly via a custom role when the
        // sentientia_m365_enabled feature flag is ON for the customer.
        'archetypes'   => [],
    ],

    'local/sentientia_m365:admin' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
