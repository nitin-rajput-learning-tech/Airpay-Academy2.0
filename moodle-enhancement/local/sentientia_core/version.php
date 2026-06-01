<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026060103;          // YYYYMMDDNN (+ ADR-020 W3.2b: org dual-write reconciler + reconcile_org task)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 W2 seam + ADR-020 W3.1/3.2 org seam + W3.2b dual-write + ADR-021 W4 registry (all default-legacy/OFF, dormant).
$plugin->release   = '0.5.0-alpha';
// No hard dependency on local_airpay_core — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
