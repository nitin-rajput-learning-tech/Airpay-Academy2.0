<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
// W1-9 + P1 #9/10/14/15 (2026-05-16) — program_completed event + window +
// rich-text + audience enroller + cohort filter + bulk-enrol modal UI + Hindi.
// P1 #45 (2026-05-20) — Hindi top-up: 65 additional strings.
// ADR-018 Wave 2 (2026-05-30) — view.php + levelcourses.php migrated off inline
// $USER->open_path parsing onto local_sentientia_core\tenant_identity
// (root_for_current_user / path_root). Behaviour-identical; declares the seam dependency.
$plugin->version   = 2026053000;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.2';  // +ADR-018 W2 open_path → tenant_identity
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
    'local_sentientia_core' => 2026053002,
];
