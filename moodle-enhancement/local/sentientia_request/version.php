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
// contracts with the shared theme_airpayux/datatable client (accept
// `search`, return status_badge + actions). Bumping version so Moodle
// refreshes the cached external_function_parameters + return shape.
$plugin->version   = 2026052201;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.2';  // +Goal A Bug #6 WS-contract alignment
$plugin->dependencies = [
    'local_sentientia_org'         => 2026040100,
    'local_sentientia_manager'     => 2026040100,  // Approval workflow patterns reused
    'local_sentientia_platform'        => 2026051200,  // Shared tenant helper
    'local_sentientia_learningpath' => 2026051600,  // P1 #6: path enrolment on approve
];
