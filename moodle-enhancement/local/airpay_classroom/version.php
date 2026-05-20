<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
// W1-7 + W1-9 + P1 #4/13/15 (2026-05-16) — meeting URLs + dates +
// audience enroller + Hindi pack.
// P1 #44 (2026-05-20) — Hindi top-up: 74 additional strings covering
// CRUD, sessions, attendance, view tabs, privacy metadata.
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.10.1';  // +P1 #44 Hindi top-up
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
