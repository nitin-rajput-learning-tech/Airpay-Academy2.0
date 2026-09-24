<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_compliance_report';
$plugin->version   = 2026092401;  // line managers see their reporting tree only; configuration is site-admin only; no-tenant viewers refused
// 2026092400 - N5: refusals render a real message (was raw "error/nopermission")
// 2026092201: real privacy provider: 4 tables (was null_provider)
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.5';  // team scope for line managers (viewer_scope)
// 1.0.4: tenant-clamped drill-down, tenant-scoped course columns, /-bounded BU list, report chrome localised (en+hi)
