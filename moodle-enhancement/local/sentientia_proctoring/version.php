<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_proctoring';
// P1 #55 (2026-05-20) — Hindi pack: 90 strings covering consent flow,
// identity verification, live monitoring, behavioural events, review queue,
// status, settings, notifications, errors, privacy metadata (incl. AWS).
// Goal A audit Bug #10 (2026-05-22) — align list_review_queue WS with the
// shared theme_sentientia/datatable contract (accept `search`, `sort`,
// `sortdir`, `filters`).
$plugin->version   = 2026111300;  // privacy metadata names every table's fields (core debugging() broke the privacy coverage tests). Was: 2026111200;  // decision A5 (2026-11-12): the session_flagged and review_submitted audit events the platform's audit log lists (event classes only, no schema/cap change); privacy provider links the logstore plugin type. Was: 2026110300;  // user search matches a full name ("Rahul Sharma"), 2026-11-03. Was: 2026092500;  // ADR-031: badge tenant-scoped; no-tenant reviewers reach no session
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.7';  // 1.0.7: privacy metadata fields. 1.0.6: session_flagged + review_submitted audit events (decision A5). 1.0.5: full-name user search.     // +ADR-031 tenant scope
$plugin->dependencies = [
    'local_sentientia_org'     => 2026040100,
    'local_sentientia_privacy' => 2026040100,  // DSR pipeline integration
    'local_sentientia_platform'    => 2026092500,  // Shared tenant helper (ADR-031 is_cross_tenant)
];
