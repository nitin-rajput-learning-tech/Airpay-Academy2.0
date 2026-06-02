<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_manager';
// W1-10 (2026-05-15) — multi-type allocation: classroom + program + path,
// extending the previously course-only allocation engine.
// P1 #52 (2026-05-20) — Hindi pack: 33 strings (team dashboard, capabilities,
// request/allocation workflow, errors, privacy metadata).
// Goal A audit Bug #10 (2026-05-22) — align list_requests + list_allocations
// WS with the shared theme_airpayux/datatable contract (accept `search`).
// ADR-020 W3.4 (2026-06-02) — team_manager (get_team / can_manage /
// can_view_member) routed through the local_sentientia_core\org seam:
// behaviour-identical under org_legacy ON; auto-switches to the org model at cutover.
$plugin->version   = 2026060200;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.3';  // +ADR-020 W3.4 org-seam migration of team_manager
