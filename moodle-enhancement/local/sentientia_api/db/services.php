<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Web service definitions for local_sentientia_api.
 *
 * The public REST surface is exposed through Moodle's web-service framework
 * so it inherits token auth, the REST/JSON server, and the external_api
 * parameter/return validation contract for free. Every function name is
 * versioned (`local_sentientia_api_v1_*`) so the surface can evolve
 * additively — a future v2 ships new functions without breaking v1
 * clients.
 *
 * A pre-built service ("Sentientia Public API v1") bundles all v1 functions
 * so an admin can mint a single token that grants the documented surface.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    // ─── v1 — READ endpoints ────────────────────────────────────────
    'local_sentientia_api_v1_list_courses' => [
        'classname'    => 'local_sentientia_api\external\v1\list_courses',
        'description'  => 'v1: List courses visible to the token user within their tenant.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_sentientia_api_v1_get_course' => [
        'classname'    => 'local_sentientia_api\external\v1\get_course',
        'description'  => 'v1: Fetch a single course by id, tenant-scoped.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_sentientia_api_v1_list_enrolments' => [
        'classname'    => 'local_sentientia_api\external\v1\list_enrolments',
        'description'  => 'v1: List active enrolments in a course, tenant-scoped.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_sentientia_api_v1_list_completions' => [
        'classname'    => 'local_sentientia_api\external\v1\list_completions',
        'description'  => 'v1: List course completions for a course, tenant-scoped.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_sentientia_api_v1_list_skills' => [
        'classname'    => 'local_sentientia_api\external\v1\list_skills',
        'description'  => 'v1: List the skill catalogue (global definitions).',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    // ─── v1 — WRITE endpoints ([CONFIRM] discipline) ────────────────
    // Gated additionally by sentientia.api.write.enabled inside the class.
    'local_sentientia_api_v1_create_enrolment' => [
        'classname'    => 'local_sentientia_api\external\v1\create_enrolment',
        'description'  => 'v1: Enrol a user into a course (manual enrolment), tenant-scoped. WRITE.',
        'type'         => 'write',
        'capabilities' => 'local/sentientia_api:write',
        'ajax'         => true,
        'loginrequired' => true,
    ],

    // ─── Discovery ──────────────────────────────────────────────────
    'local_sentientia_api_v1_openapi' => [
        'classname'    => 'local_sentientia_api\external\v1\openapi',
        'description'  => 'v1: Return the OpenAPI 3.0 specification document describing the v1 surface.',
        'type'         => 'read',
        'capabilities' => 'local/sentientia_api:read',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Sentientia Public API v1' => [
        'functions' => [
            'local_sentientia_api_v1_list_courses',
            'local_sentientia_api_v1_get_course',
            'local_sentientia_api_v1_list_enrolments',
            'local_sentientia_api_v1_list_completions',
            'local_sentientia_api_v1_list_skills',
            'local_sentientia_api_v1_create_enrolment',
            'local_sentientia_api_v1_openapi',
        ],
        'restrictedusers' => 1,   // tokens issued only to explicitly authorised users
        'enabled'         => 0,   // OFF until an admin enables; mirrors the feature flag
        'shortname'       => 'sentientia_api_v1',
        'downloadfiles'   => 0,
        'uploadfiles'     => 0,
    ],
];
