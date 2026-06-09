<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Capabilities — Airpay Course Engine.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'local/sentientia_courses:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_courses:enrol' => [
        'riskbitmask'  => RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_courses:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_courses:create' => [
        'riskbitmask'  => RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_courses:update' => [
        'riskbitmask'  => RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'local/sentientia_courses:delete' => [
        'riskbitmask'  => RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // Siteadmin only by default.
    ],

    'local/sentientia_courses:visibility' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Sprint C (2026-05-13): share a course across tenants.
    // High-risk: affects what learners in OTHER tenants can see.
    // Siteadmin-only by default — admin must explicitly grant to any
    // other role via Site Admin → Users → Permissions → Define roles.
    // The capability check happens in
    // \local_sentientia_courses\sharing_manager and the share_course /
    // unshare_course external WS endpoints.
    'local/sentientia_courses:share_to_tenant' => [
        'riskbitmask'  => RISK_SPAM | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // Siteadmin only by default.
    ],

    // Sprint D (2026-05-13): request a course be shared to my tenant.
    // Granted to the `manager` archetype by default — anyone with
    // managerial responsibility in a Public/ZEEA tenant can ask the
    // Airpay Super Admin to add a specific course to their catalogue.
    // No actual catalog change happens until an admin approves.
    'local/sentientia_courses:request_course' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Sprint D: approve or reject a course-share request.
    // Sibling of :share_to_tenant — approving fires share_course();
    // rejecting just closes the request. Same trust level: siteadmin
    // only by default.
    'local/sentientia_courses:approve_request' => [
        'riskbitmask'  => RISK_SPAM | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // Siteadmin only by default.
    ],
];
