<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Capabilities — Airpay Compliance Report.
 *
 * @package    local_sentientia_compliance_report
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
    // gives a sane default for other Sentientia customers). The install step
    // (see classes/permission.php ::grant_export_to_default_roles) also grants
    // this to the BizLMS org-admin role, looked up by its shortname
    // 'administrator' (id 9 in the Airpay data), and only where that role
    // exists. Until 2026-11-17 it granted to role id 9 unconditionally, which
    // on a fresh install is the Sentientia Author role; upgrade step
    // 2026111702 takes that grant back. Its other step (holders of
    // local/courses:manage) grants nothing on a fresh install: no Sentientia
    // plugin declares that name.
    // Line managers can VIEW the dashboard (see index.php) but are
    // deliberately NOT granted export.
    'local/sentientia_compliance_report:export' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
