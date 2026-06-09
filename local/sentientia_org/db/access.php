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
 * Capabilities — Airpay Organization Engine.
 *
 * Mirrors the BizLMS local_costcenter capabilities so existing role
 * assignments continue to work. Adds Airpay-namespaced equivalents.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Full multi-org management (siteadmin-tier).
    'local/sentientia_org:manage_multiorganizations' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // View org hierarchy (read-only).
    'local/sentientia_org:view' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Manage orgs (create/edit/delete).
    'local/sentientia_org:manage' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],

    // Manage own organization (L&D admin tier).
    'local/sentientia_org:manage_ownorganization' => [
        'riskbitmask'  => RISK_MANAGETRUST,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],
    ],

    // Manage own departments within org.
    'local/sentientia_org:manage_owndepartments' => [
        'riskbitmask'  => RISK_MANAGETRUST,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSECAT,
        'archetypes'   => [],
    ],

    // Manage tenant-level settings (logo, branding, email-from, footer,
    // hero, custom CSS). Granted to tenant admins for their OWN tenant
    // root — enforcement of "own tenant" is done in tenant_settings.php
    // by comparing the editing user's open_path with the target tenant.
    'local/sentientia_org:managetenant' => [
        'riskbitmask'  => RISK_CONFIG,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
