<?php
// This file is part of Sentientia LMS.

/**
 * Capabilities for local_sentientia_platform.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // ADR-031 (2026-09-25): the ONLY way besides being a site admin to act
    // across tenants. \local_sentientia_platform\tenant::is_cross_tenant() is
    // the single place it is read; no plugin capability may unscope a caller
    // on its own any more.
    //
    // NO archetype default, deliberately. Tenant admins hold manager-archetype
    // roles at system context (UAT's "administrator", id 9), so any default
    // here would hand every tenant admin every tenant - the defect the
    // 2026-09-25 sweep found in ~30 plugins. Grant it to a named role (e.g.
    // Airpay platform L&D) on purpose, and only there.
    'local/sentientia_platform:crosstenant' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_CONFIG | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
