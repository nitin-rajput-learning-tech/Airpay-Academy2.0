<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// P1 #33 (2026-05-20) — learner deadline-reminder cron (quiz.timeclose
//                       source). Mirrors P1 #28's airpay_courses pattern.
// P1 #34 (2026-05-20) — overdue manager-escalation cron. Reuses #33's
//                       _remind_sent table with negative bucket values.
//                       Closes audit item #17 from
//                       parity-audit-2026-05-15/airpay_exams.md.
$plugin->version   = 2026052002;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.0'; // + P1 #34 overdue escalation cron
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
