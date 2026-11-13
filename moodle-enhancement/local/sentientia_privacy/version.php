<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_privacy';
// 2026-11-13 (1.1.0): ADR-035 - the privacy provider also finds, exports and
// erases the subject's logstore_standard logs, report_stats rows and course
// completions that two stock Moodle defects leave behind on a 'Deleted'
// request (contextlist iteration stops at the first duplicate; core_course
// finds completions only through criteria). Re-runs core's own helpers; own
// tables are still kept. No schema change, no feature flag (Nitin 11-13).
// 2026-09-25 (1.0.3): ADR-031 - the DPDP admin panel (every tenant's requests,
// Approve = erase) opens only for a cross-tenant caller: a site admin, or a
// :manage holder who also holds local/sentientia_platform:crosstenant.
// :manage alone no longer unscopes (privacy_manager::can_administer()).
// 2026-09-24 (1.0.2): erasure asks every Sentientia privacy provider; admin panel
// Approve no longer fatals, reports partial erasures and never re-runs one.
// P1 #50 (2026-05-20) — Hindi top-up: 2 strings (message provider + privacy).
$plugin->version   = 2026111300;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
$plugin->dependencies = [
    // tenant::is_cross_tenant() decides who may open the admin panel.
    'local_sentientia_platform' => ANY_VERSION,
];
