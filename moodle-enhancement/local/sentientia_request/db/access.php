<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

// 'employee' + 'administrator' custom-role grants are handled in
// db/install.php — they aren't Moodle archetypes.

$capabilities = [
    'local/sentientia_request:request' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'    => CAP_ALLOW,
            'student' => CAP_ALLOW,
        ],
    ],

    // 2026-10-15 (visual-pass finding 7): routing picks the requester's LINE MANAGER (user.open_supervisorid)
    // as the approver, and on BizLMS data a line manager is an ordinary employee, not a holder of the Moodle
    // `manager` role (110 users have direct reports on the April copy, about 10 hold the manager archetype).
    // Without this capability the routed approver cannot open approvals.php, the list_pending / decide web
    // services refuse them, and Moodle never sends them request_pending or request_escalated (those message
    // providers are bound to this capability: message_send() drops a notification the user has no
    // capability for). So the approver was named and could neither see nor act on the request.
    //
    // Granted to the authenticated-user archetype, exactly as :request is. That does not widen anyone's
    // AUTHORITY: request_manager::decide() lets only the assigned approver (or an :overrideroute holder,
    // tenant-checked) decide, and list_pending lists only requests assigned to the caller. What every
    // employee gets is the capability, not anybody's requests. Existing sites are back-filled by upgrade
    // step 2026101501 (db/upgradelib.php), because archetype defaults apply only when a capability is first
    // registered. The choice of granting rather than routing only to holders of the capability is explained
    // in state-cards/sentientia_request-state.md.
    'local/sentientia_request:approve' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
            'user'    => CAP_ALLOW,
        ],
    ],

    'local/sentientia_request:viewall' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_request:overrideroute' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
