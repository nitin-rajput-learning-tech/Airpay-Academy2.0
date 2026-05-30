<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026053001;          // YYYYMMDDNN (+ ADR-020 Wave 3.1 org seam)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 Wave-2 scaffold; seam only, no callers migrated yet.
$plugin->release   = '0.1.0-alpha';
// No hard dependency on local_airpay_core — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
