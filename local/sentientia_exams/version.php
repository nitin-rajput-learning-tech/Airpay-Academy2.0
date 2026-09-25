<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// P1 #33 (2026-05-20) — learner deadline-reminder cron (quiz.timeclose
//                       source). Mirrors P1 #28's sentientia_courses pattern.
// P1 #34 (2026-05-20) — overdue manager-escalation cron.
// P1 #36 (2026-05-20) — Hindi (hi) lang pack: ~65 strings translated.
$plugin->version   = 2026092500;  // ADR-031: every exam read/write checks the exam's tenant; cascade only narrows
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.3'; // + ADR-031 tenant scope
$plugin->dependencies = [
    'local_sentientia_org' => 2026041600,
    'local_sentientia_platform' => 2026092500,  // ADR-031 tenant::is_cross_tenant()
];
