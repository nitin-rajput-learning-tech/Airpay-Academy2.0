<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capability definitions for local_sentientia_xapi.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // View statements in the LRS admin viewer.
    'local/sentientia_xapi:viewstatements' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],

    // Delete statements from the LRS (destructive — site admin only).
    'local/sentientia_xapi:deletestatements' => [
        'riskbitmask'  => RISK_DATALOSS | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // site admin only
    ],

    // Manage LRS settings and credentials.
    'local/sentientia_xapi:managelrs' => [
        'riskbitmask'  => RISK_CONFIG | RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],  // site admin only
    ],

];
