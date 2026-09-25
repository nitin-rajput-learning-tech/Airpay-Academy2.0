<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_manager';
// W1-10 (2026-05-15) — multi-type allocation: classroom + program + path,
// extending the previously course-only allocation engine.
// P1 #52 (2026-05-20) — Hindi pack: 33 strings (team dashboard, capabilities,
// request/allocation workflow, errors, privacy metadata).
// Goal A audit Bug #10 (2026-05-22) — align list_requests + list_allocations
// WS with the shared theme_sentientia/datatable contract (accept `search`).
// ADR-020 W3.4 (2026-06-02) — team_manager (get_team / can_manage /
// can_view_member) routed through the local_sentientia_core\org seam:
// behaviour-identical under org_legacy ON; auto-switches to the org model at cutover.
// ADR-031 (2026-09-25) — can_view_member(): local/sentientia_users:view opens
// member pages in the viewer's OWN tenant only (it opened every tenant's);
// index.php's ?manager= pick is tenant-bounded for non-cross-tenant callers.
$plugin->version   = 2026092500;  // ADR-031: team member pages tenant-bounded
// 2026060200: ADR-020 W3.4 org-seam migration.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.4';  // ADR-031 tenant bound on member drill-down
// 1.3.3: +ADR-020 W3.4 org-seam migration of team_manager
// team_manager calls local_sentientia_platform\tenant (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,
];
