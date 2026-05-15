<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_manager';
// W1-10 (2026-05-15) — multi-type allocation: classroom + program + path,
// extending the previously course-only allocation engine.
$plugin->version   = 2026051500;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0';  // W1-10: classroom/program/path allocations
