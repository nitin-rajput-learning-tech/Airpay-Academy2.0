<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_privacy';
// 2026-09-24 (1.0.2): erasure asks every Sentientia privacy provider; admin panel
// Approve no longer fatals, reports partial erasures and never re-runs one.
// P1 #50 (2026-05-20) — Hindi top-up: 2 strings (message provider + privacy).
$plugin->version   = 2026092400;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.2';
