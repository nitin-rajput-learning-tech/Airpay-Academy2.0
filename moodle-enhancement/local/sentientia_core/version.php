<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026060100;          // YYYYMMDDNN (+ ADR-021 W4: tenant_registry tables + seam + admin UI)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 W2 seam + ADR-020 W3.1 org seam + ADR-021 W4 registry (all default-legacy, dormant).
$plugin->release   = '0.3.0-alpha';
// No hard dependency on local_airpay_core — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
