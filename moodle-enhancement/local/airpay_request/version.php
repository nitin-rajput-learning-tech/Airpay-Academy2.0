<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_request';
// W1-9 (2026-05-15) — emit request_submitted/approved/rejected events for
// SOX audit trail. These hit mdl_logstore_standard_log on every state change.
$plugin->version   = 2026051500;
$plugin->requires  = 2024042200;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';  // W1-9: audit-trail events
$plugin->dependencies = [
    'local_airpay_org'     => 2026040100,
    'local_airpay_manager' => 2026040100,  // Approval workflow patterns reused
    'local_airpay_core'    => 2026051200,  // Shared tenant helper
];
