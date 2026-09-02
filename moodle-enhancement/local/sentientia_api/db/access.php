<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for local_sentientia_api.
 *
 * The capability model separates READ access (granted to managers by
 * default, but assignable to a dedicated "api service" role for token
 * users) from WRITE and ADMIN. Token users authenticate as a Moodle
 * user; that user must hold the relevant capability AND be in the right
 * tenant for every call. See classes/external/v1/base.php.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Read the public API surface (courses, enrolments, completions, skills).
    'local/sentientia_api:read' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Perform WRITE operations via the public API (e.g. create enrolment).
    // RISK_DATALOSS because a bad caller could enrol/unenrol at scale.
    'local/sentientia_api:write' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage API registrations + LTI registrations + rate-limit config.
    'local/sentientia_api:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Act as an LTI provider/consumer launch endpoint (held by the
    // dedicated LTI service account user). Separate from :read so an
    // LTI launch can be authorised without exposing the full REST API.
    'local/sentientia_api:lti' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage outbound webhook subscriptions + view/retry the delivery log
    // (ADR-030 Wave A). RISK_CONFIG: a subscription points platform events at
    // an external URL — misconfiguration leaks event metadata off-platform.
    'local/sentientia_api:webhooks_manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
