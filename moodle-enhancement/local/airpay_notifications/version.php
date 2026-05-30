<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_notifications';
// P1 #48 (2026-05-20) — Hindi top-up: 53 strings covering capabilities,
// CRUD form, errors, success, and privacy metadata.
// ADR-018 Wave 2 (2026-05-30) — rule_engine new-course-notify tenant derivation
// migrated onto local_sentientia_core\tenant_identity::path_root. Behaviour-identical;
// declares the seam dependency (path_root is new in sentientia_core 2026053002).
$plugin->version   = 2026053000;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.2'; // +ADR-018 W2 open_path → tenant_identity
$plugin->dependencies = [
    'local_sentientia_core' => 2026053002,
];
