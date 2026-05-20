<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_request';
// W1-9 + P1 #6 (2026-05-16) — request_submitted/approved/rejected events
// PLUS polymorphic item_type (course | path | classroom | program). Path
// requests use the same approval flow and enrol via path_manager.
// P1 #54 (2026-05-20) — Hindi pack: 67 strings covering navigation,
// capabilities, actions, status, SLA, routing, notifications, errors,
// settings, UI, privacy, events.
$plugin->version   = 2026052001;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.1';  // +P1 #54 Hindi pack
$plugin->dependencies = [
    'local_airpay_org'         => 2026040100,
    'local_airpay_manager'     => 2026040100,  // Approval workflow patterns reused
    'local_airpay_core'        => 2026051200,  // Shared tenant helper
    'local_airpay_learningpath' => 2026051600,  // P1 #6: path enrolment on approve
];
