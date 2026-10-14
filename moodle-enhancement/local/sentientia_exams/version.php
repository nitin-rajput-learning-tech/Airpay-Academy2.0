<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_exams';
// P1 #23 (2026-05-16) — exam categories (FK to course_categories).
// P1 #33 (2026-05-20) — learner deadline-reminder cron (quiz.timeclose
//                       source). Mirrors P1 #28's sentientia_courses pattern.
// P1 #34 (2026-05-20) — overdue manager-escalation cron.
// P1 #36 (2026-05-20) — Hindi (hi) lang pack: ~65 strings translated.
// ADR-032 (2026-09-30) — BizLMS exams import (mapping doc section 9). New db/bizlms_import.php and
// classes/bizlms/ (exams importer: the quizzes of the online exam courses become exam rows, and the overdue
// escalation of their past deadlines is marked as already sent so enabling exam_overdue later cannot flood
// supervisors). Reader fixes shipped with it: exam_manager no longer falls back to the legacy local_onlinetests
// table; the pass count on view.php divides by quiz.sumgrades, not by every learner's grades added together.
// No schema change (both target tables are in db/install.xml), no new lang string, no flag (no new surface).
// 2026100701 -- owner decisions of 2026-10-07 (courses cluster, doc item "exams pass figures"): the analytics tab of an exam
// (view.php) divided the learners who passed by the number of ATTEMPTS and subtracted learners from attempts for the
// failures, so a learner with three tries counted three times on one side and once on the other. exam_manager::pass_figures()
// now gives finished attempts, distinct learners, passed, failed and pass rate with learners on both sides. A fix of a wrong
// figure, so no flag; no schema change, no new lang string.
$plugin->version   = 2026100701;  // exams analytics: pass rate and failures count learners on both sides; no schema change
// 2026100100: ADR-032 exams importer + reader fixes; no schema change
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.1'; // + exams pass figures (was 1.7.0: + ADR-032 exams importer)
$plugin->dependencies = [
    'local_sentientia_org' => 2026041600,
    'local_sentientia_platform' => 2026093001,  // ADR-032 BizLMS import framework (classes/bizlms/, 3 framework tables)
];
