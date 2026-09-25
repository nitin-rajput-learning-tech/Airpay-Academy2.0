<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_learningpath';
// P1 #2/8/10/11 (2026-05-16) — enrolment window + rich-text +
// target-audience bulk-enrol + cohort filter + bulk-enrol modal UI.
// P1 #46 (2026-05-20) — Hindi top-up: 30 strings covering CRUD, confirms,
// view tabs, errors, privacy metadata.
// P0.2 (2026-06-16) — Adaptive Learning Journeys: branch/accelerate/remediate
// on quiz scores, completion velocity, and skills-gap feed. Feature-flagged
// behind sentientia.learningpath.adaptive.enabled (default OFF).
// 2026-09-25 — adaptive log quiz_score / velocity_score widened from
// NUMBER(6,0) to NUMBER(6,2) (install.xml + upgrade step 2026092501): the
// scores were being rounded to whole numbers on insert.
$plugin->version   = 2026092501;  // adaptive log scores keep 2 decimals
// 2026092500: ADR-031: every pathid/userid/courseid tenant-checked; :view student default revoked.
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.2';  // adaptive log score precision (1.8.1: ADR-031 tenant scope; 1.8.0: +P0.2 Adaptive Learning Journeys)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026092500,  // tenant::is_cross_tenant / scope_path
];
