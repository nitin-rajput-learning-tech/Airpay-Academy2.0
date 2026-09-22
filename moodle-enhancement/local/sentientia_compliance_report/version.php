<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_compliance_report';
$plugin->version   = 2026092201;  // real privacy provider: 4 tables (was null_provider)
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.4';  // tenant-clamped drill-down, tenant-scoped course columns, /-bounded BU list, report chrome localised (en+hi)
