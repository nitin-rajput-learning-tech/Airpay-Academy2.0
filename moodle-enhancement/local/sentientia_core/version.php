<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026060105;          // YYYYMMDDNN (+ ADR-020 W3.4: flag-aware reverse seam — is_manager/direct_reports/reports_by_manager)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 W2 seam + ADR-020 W3.1/3.2/3.2b/3.3/3.4 org + ADR-021 W4 registry (all default-legacy/OFF, dormant).
$plugin->release   = '0.6.1-alpha';
// No hard dependency on local_airpay_core — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
