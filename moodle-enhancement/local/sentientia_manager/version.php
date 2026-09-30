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
// ADR-031 follow-up (2026-09-25) — create_allocation / bulk_allocate / typed
// allocations: a scoped manager may allocate only to a direct report in their
// own tenant, and only an item (course, classroom, program, path) whose
// open_path is in that tenant. An empty report list no longer means anybody.
// Decision 5 (2026-09-26) - allocations unique index: the UNIQUE (userid,
// courseid) index let a user hold only one classroom / program / path
// allocation (all carry courseid 0). Step 2026092600 makes it a plain index;
// idx_user_item (userid, item_type, itemid) stays the uniqueness rule.
// Persona pass D4 + D14 (2026-09-30) - Team performance for a line manager.
// The team_performance web service demanded local/sentientia_manager:view (which a
// supervisor with no Moodle manager role does not hold) and then selected
// user.open_managerid, a column that does not exist. It now uses the same
// supervisor-aware team_manager::require_manage() gate as the page and reads the
// team through team_manager::get_team() (open_supervisorid via the org seam).
// member.php sets its page context before rendering text and refuses with a
// string this plugin defines. No schema change, no capability change, no flag.
$plugin->version   = 2026093001;  // D4: team_performance gate + team query
// 2026092501: ADR-031 allocation targets tenant-bounded.
// 2026092500: ADR-031 team member pages tenant-bounded.
// 2026060200: ADR-020 W3.4 org-seam migration.
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.7';  // D4/D14: team performance works for line managers
// 1.3.6: Decision 5 - typed allocations no longer collide
// 1.3.5: ADR-031 tenant bound on allocations
// 1.3.4: ADR-031 tenant bound on member drill-down
// 1.3.3: +ADR-020 W3.4 org-seam migration of team_manager
// team_manager calls local_sentientia_platform\tenant (ADR-031).
$plugin->dependencies = [
    'local_sentientia_platform' => ANY_VERSION,
];
