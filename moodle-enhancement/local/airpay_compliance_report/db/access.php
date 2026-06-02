<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities — Airpay Compliance Report.
 *
 * @package    local_airpay_compliance_report
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Authorises exporting the full compliance matrix (every employee's
    // compliance status — name, email, employee id, department) as Excel/CSV
    // via export.php. RISK_PERSONAL because it releases bulk PII.
    //
    // The 'manager' archetype covers admin-tier roles out of the box (and
    // gives a sane default for other Sentientia customers). On the Airpay
    // deployment the install/upgrade step (see classes/permission.php
    // ::grant_export_to_default_roles) additionally grants this to every role
    // that already holds local/courses:manage — preserving the exact set of
    // users who could export before this capability existed — and to the
    // BizLMS Compliance Officer role (id 9). Line managers can VIEW the
    // dashboard (see index.php) but are deliberately NOT granted export.
    'local/airpay_compliance_report:export' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
