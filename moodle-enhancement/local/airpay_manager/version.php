<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_manager';
// W1-10 (2026-05-15) — multi-type allocation: classroom + program + path,
// extending the previously course-only allocation engine.
// P1 #52 (2026-05-20) — Hindi pack: 33 strings (team dashboard, capabilities,
// request/allocation workflow, errors, privacy metadata).
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.1';  // +P1 #52 Hindi pack
