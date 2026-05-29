<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Capabilities for Sentientia LMS Live engagement (Phase E.0).
 *
 *   create     — trainer creates a new session (allowed for teacher+, incl. BizLMS trainer role)
 *   run        — trainer starts/advances/ends a session they own
 *                  (allowed for teacher+; also enforced by ownership
 *                  check at runtime — Phase E.1's run.php gates on
 *                  $session->ownerid === $USER->id || manage_all)
 *   join       — audience joins a session by code (allowed for any user;
 *                  also allowed for guests when session->allow_anonymous=1
 *                  per per-session setting — Phase E.2 gate)
 *   respond    — audience submits a response to a slide
 *                  (allowed for any user; same anonymous gate as join)
 *   manage_all — admin override; view/manage every session across tenants
 *
 * @package local_sentientia_live
 */

$capabilities = [

    'local/sentientia_live:create' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            // T-01 (QA Walk 2026-05-29): the BizLMS `trainer` role is
            // archetype=teacher, NOT editingteacher. Grant the non-editing
            // teacher archetype so trainer-role users reach trainer/index.php.
            // db/upgrade.php back-fills this default onto existing
            // teacher-archetype roles — Moodle only auto-applies archetype
            // defaults the first time a capability is installed, never on a
            // later upgrade that merely adds an archetype to an existing cap.
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_live:run' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            // T-01 (QA Walk 2026-05-29): the BizLMS `trainer` role is
            // archetype=teacher, NOT editingteacher. Grant the non-editing
            // teacher archetype so trainer-role users reach trainer/index.php.
            // db/upgrade.php back-fills this default onto existing
            // teacher-archetype roles — Moodle only auto-applies archetype
            // defaults the first time a capability is installed, never on a
            // later upgrade that merely adds an archetype to an existing cap.
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_live:join' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_live:respond' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'user'           => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    'local/sentientia_live:manage_all' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
