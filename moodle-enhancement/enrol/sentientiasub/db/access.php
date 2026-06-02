<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities for enrol_sentientiasub (ADR-023). Mirrors enrol_fee.
 *
 * @package enrol_sentientiasub
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Configure / add a subscription enrol instance on a course.
    'enrol/sentientiasub:config' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // Manage subscribers (view list, suspend, cancel on their behalf).
    'enrol/sentientiasub:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    // Unenrol a subscriber.
    'enrol/sentientiasub:unenrol' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Cancel one's OWN subscription (granted via instance config, like enrol_self).
    'enrol/sentientiasub:unenrolself' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [],
    ],
];
