<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// Closes audit item #12 from parity-audit-2026-05-15/airpay_exams.md.
$plugin->version   = 2026051901;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.0'; // + P1 #23 category field
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
