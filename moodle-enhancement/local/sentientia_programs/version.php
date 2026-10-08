<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_sentientia_programs';
// W1-9 + P1 #9/10/14/15 (2026-05-16) — program_completed event + window +
// rich-text + audience enroller + cohort filter + bulk-enrol modal UI + Hindi.
// P1 #45 (2026-05-20) — Hindi top-up: 65 additional strings.
// 2026093001 (2026-09-30) - ADR-032 BizLMS program import: levels.completion_rule, users.enrolledby/timemodified,
// lvlcomp + trainers + trainerfb tables, the importer (classes/bizlms), the learner "My programs" page, the
// program logo, and the engine fixes the import needs (any-course level rule, empty levels, completion_required = 0,
// persisted completion, protected imported history). Every new reader surface is behind its own default-OFF flag.
// 2026100701 (2026-10-07): ADR-032 owner decision IDN-04 - the importer implements the platform's copies_files marker (its
// program-logo copy is a declared side effect, counted in the run report). No schema change. The marker interface ships
// with local_sentientia_platform 2026100701, which this plugin therefore requires. One version for the batch (F-85).
$plugin->version   = 2026100701;  // ADR-032 IDN-04: copies_files marker (on top of 2026093001: BizLMS program importer + schema additions + reader/engine fixes)
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.9.1';  // ADR-032 IDN-04 copies_files marker (1.9.0: ADR-032 BizLMS program import; 1.8.2: ADR-031 tenant scope)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026100701,  // tenant scope + the ADR-032 import framework (classes/bizlms) + the copies_files marker (IDN-04)
];
