<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_classroom';
// W1-7 + W1-9 + P1 #4/13/15 (2026-05-16) — meeting URLs + dates +
// audience enroller + Hindi pack.
// P1 #44 (2026-05-20) — Hindi top-up: 74 additional strings covering
// CRUD, sessions, attendance, view tabs, privacy metadata.
$plugin->version   = 2026092500;  // ADR-031: every classroomid/sessionid/userid tenant-checked
// 2026092400: waitlist table in install.xml + created where missing
// 2026092200: count_classrooms takes a path, not a LIKE pattern
// 2026052001:
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.10.4';  // ADR-031 tenant scope (1.10.3: waitlist in install.xml + privacy coverage)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026092500,  // tenant::is_cross_tenant / scope_path
];
