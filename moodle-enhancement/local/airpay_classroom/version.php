<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
// W1-7 + W1-9 + P1 #4/13/15 (2026-05-16) — meeting URLs + dates +
// audience enroller + Hindi pack.
// P1 #44 (2026-05-20) — Hindi top-up: 74 additional strings covering
// CRUD, sessions, attendance, view tabs, privacy metadata.
// ADR-018 Wave 2 (2026-05-30) — view.php + attendance.php + enrol_classroom_users
// migrated off inline $USER->open_path parsing onto local_sentientia_core\tenant_identity
// (root_for_current_user / path_root). Behaviour-identical; declares the seam dependency.
$plugin->version   = 2026053000;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.10.2';  // +ADR-018 W2 open_path → tenant_identity
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
    'local_sentientia_core' => 2026053002,
];
