<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// P1 #33 (2026-05-20) — learner deadline-reminder cron (quiz.timeclose
//                       source). Mirrors P1 #28's airpay_courses pattern.
//                       Closes audit item #16 from
//                       parity-audit-2026-05-15/airpay_exams.md.
$plugin->version   = 2026052001;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0'; // + P1 #33 deadline reminder cron
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
