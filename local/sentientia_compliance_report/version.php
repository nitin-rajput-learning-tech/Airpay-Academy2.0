<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_compliance_report';
$plugin->version   = 2026111901;  // A1 / D4 (post-demo session 1, 2026-11-20): the hourly refresh skips a tracked course for users outside the course's tenant unless it is shared to their tenant, honours a per-row cohort (new column cohortid, upgrade step) and prunes snapshot rows that no longer apply; auto-enrolments get no end date; cli/pin_compliance_courses.php (dry run by default) pins the Airpay compliance rows, restricts POSH for IC Members to an IC cohort and can clear deadline-based end dates
// 2026111801 - 11-18 Phase C: KPI cache purged by snapshot rebuild and configuration changes; export Department is the org-unit name and the rate matches the page
// 2026111801 also covers B6 (Nitin, 2026-11-18, option 1): viewer_scope no longer admits moodle/site:viewreports to the tenant view (no upgrade step)
// 2026111801 also carries Phase C addendum PB-2 (2026-11-19): the filter bar and its selects carry the airpay-compliance-rpt__filter-bar / __filter-select classes (inline widths dropped), so a select stays inside the card at 390px.
// 2026111800 - 11-18 walk: scorecard tree + "No assignments" (one query), matrix pager + Department name, empty states, rate rounded, page heading
// 2026111702 - :export goes to the org-admin role by shortname, not role id 9; upgrade revokes the misdirected grant
// 2026111701 - courseid-0 exclusions honoured by rebuild_snapshot() (parallel change, no upgrade step); merged version stays 2026111702
// 2026092401 - line managers see their reporting tree only; configuration is site-admin only; no-tenant viewers refused
// 2026092400 - N5: refusals render a real message (was raw "error/nopermission")
// 2026092201: real privacy provider: 4 tables (was null_provider)
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.11';  // A1 / D4: tenant + share + cohort guard on the hourly refresh, no enrolment end date, pin_compliance_courses CLI
// 1.0.10: Phase C: KPI cache purge, export Department name + page rate; B6 trainer compliance scope
// 1.0.9: 11-18 surface-walk fixes (scorecard, matrix pager, empty states)
// 1.0.8: export grant keyed on the org-admin shortname, not role id 9
// 1.0.7: courseid-0 exclusions honoured by the snapshot rebuild (parallel change)
// 1.0.5: team scope for line managers (viewer_scope)
// 1.0.4: tenant-clamped drill-down, tenant-scoped course columns, /-bounded BU list, report chrome localised (en+hi)
