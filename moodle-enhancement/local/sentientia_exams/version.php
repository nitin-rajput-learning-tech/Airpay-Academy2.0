<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// P1 #33 (2026-05-20) — learner deadline-reminder cron (quiz.timeclose
//                       source). Mirrors P1 #28's airpay_courses pattern.
// P1 #34 (2026-05-20) — overdue manager-escalation cron.
// P1 #36 (2026-05-20) — Hindi (hi) lang pack: ~65 strings translated.
$plugin->version   = 2026052003;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.1'; // + P1 #36 Hindi pack
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
