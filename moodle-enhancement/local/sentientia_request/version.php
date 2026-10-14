<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_request';
// W1-9 + P1 #6 (2026-05-16) — request_submitted/approved/rejected events
// PLUS polymorphic item_type (course | path | classroom | program). Path
// requests use the same approval flow and enrol via path_manager.
// P1 #54 (2026-05-20) — Hindi pack: 67 strings covering navigation,
// capabilities, actions, status, SLA, routing, notifications, errors,
// settings, UI, privacy, events.
// Goal A audit Bug #6 (2026-05-22) — align list_mine + list_pending WS
// contracts with the shared theme_sentientia/datatable client (accept
// `search`, return status_badge + actions). Bumping version so Moodle
// refreshes the cached external_function_parameters + return shape.
// WF-018 + WF-019 (2026-06-11, foolproof M2) — supervisor routing read the
// non-existent open_managerid column (production BizLMS carries
// open_supervisorid), so every request silently fell through to the
// course-owner/default-approver routes; decide.min.js + request_button.min.js
// were never built, leaving approve/reject + request buttons inert under
// cached JS. Adds dataset-string escaping in the decision/request modals and
// cli/seed_qa_pending_request.php (M2 Playwright fixture).
// WF-020 + WF-021 (2026-06-15, foolproof M2 cont.) — the approvals inbox
// rendered the requester's Cancel button (list_mine::shape reused verbatim)
// instead of Approve/Reject, AND the list_pending/list_mine return shape
// declared status_badge_class as PARAM_ALPHANUMEXT while the pending badge
// value 'bg-warning text-dark' contains a space → every pending row failed
// return validation ("Invalid response value detected") → datatable hung on
// "Loading...". Both fixed: approver_actions() override + PARAM_TEXT.
// WF-022 (2026-06-15) — pending.mustache + all_requests.mustache declared the
// table region as data-region="datatable", but theme_sentientia/datatable
// init() only matches [data-airpay-table] / [data-region="airpay-datatable"],
// so the datatable never instantiated and hung on the static "Loading..."
// placeholder. Bug #12 fixed my_requests.mustache but missed these two.
// WF-023 + WF-024 (2026-06-15) — duplicate Approve/Reject buttons (datatable
// rendered row.actions as both a column AND the auto trailing cell), and the
// decision modal used the invalid 5.2 API core/modal Modal.create({modalType})
// → empty-footer base modal (no Save) → decision unsubmittable. Both fixed
// (datatable hasActionsColumn guard in theme; core/modal_save_cancel here).
// ADR-031 (2026-09-25) — list_all (All requests) is confined to the caller's
// tenant; only a cross-tenant caller may pick a tenant with filters.tenant.
// ADR-032 (2026-09-30) — BizLMS import, request feature: local_sentientia_request.legacy_source marks
// the rows the import writes; db/bizlms_import.php + classes/bizlms/ hold the importer; routing moved
// to approver_routing so the import can route legacy pending requests; the lists show every item
// type's name, imported rows only with the sentientia.request.imported_history flag (default OFF);
// decide() refuses classroom/program/certification rows; the cron jobs skip imported rows.
// 2026-10-07 owner decisions (COMMS-R1..R4): a pending request whose requester has left or whose item is gone is history
// only (request.pending_stale); a request for a gone path, classroom or program gets itemid 0; local_request_comments rows
// block preflight until the owner has reviewed them, and decide() appends to a folded comment thread instead of replacing
// it; the imported_history flag description no longer claims the lists look as before. No schema change: the bump is for
// the flag registry (cached by the platform) and so that the ledger of the batch has one value per plugin.
// 2026100801: Moodle 5.3 compat FX-06b: decide + request_button use core/modal_save_cancel with no removed-factory fallback; removeOnClose so a cancelled dialog cannot leave a stale textarea behind. No schema change.
$plugin->version   = 2026100801;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.1';  // +COMMS-R1..R4 importer rules and decide() note (was 1.5.0 +ADR-032 BizLMS import)
$plugin->dependencies = [
    'local_sentientia_org'         => 2026040100,
    'local_sentientia_manager'     => 2026040100,  // Approval workflow patterns reused
    'local_sentientia_platform'        => 2026093001,  // ADR-032 import framework (classes/bizlms); ADR-031 is_cross_tenant
    'local_sentientia_learningpath' => 2026051600,  // P1 #6: path enrolment on approve
];
