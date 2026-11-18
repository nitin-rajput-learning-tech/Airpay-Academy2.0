<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_compliance_report';
$plugin->version   = 2026111800;  // 11-18 walk: scorecard tree + "No assignments" (one query), matrix pager + Department name, empty states, rate rounded, page heading
// 2026111702 - :export goes to the org-admin role by shortname, not role id 9; upgrade revokes the misdirected grant
// 2026111701 - courseid-0 exclusions honoured by rebuild_snapshot() (parallel change, no upgrade step); merged version stays 2026111702
// 2026092401 - line managers see their reporting tree only; configuration is site-admin only; no-tenant viewers refused
// 2026092400 - N5: refusals render a real message (was raw "error/nopermission")
// 2026092201: real privacy provider: 4 tables (was null_provider)
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.9';  // 11-18 surface-walk fixes (scorecard, matrix pager, empty states)
// 1.0.8: export grant keyed on the org-admin shortname, not role id 9
// 1.0.7: courseid-0 exclusions honoured by the snapshot rebuild (parallel change)
// 1.0.5: team scope for line managers (viewer_scope)
// 1.0.4: tenant-clamped drill-down, tenant-scoped course columns, /-bounded BU list, report chrome localised (en+hi)
