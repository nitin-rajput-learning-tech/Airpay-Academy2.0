<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_core';
$plugin->version   = 2026053002;          // YYYYMMDDNN (+ ADR-018 W2: tenant_identity caller-migration surface)
$plugin->requires  = 2024100700;          // Moodle 4.5+
$plugin->maturity  = MATURITY_ALPHA;      // ADR-019 Wave-2 seam; PR-1 extends the surface, callers migrate in PR-2+.
$plugin->release   = '0.2.0-alpha';
// No hard dependency on local_airpay_core — tenant_identity guards the
// delegation with class_exists() so the seam degrades gracefully.
