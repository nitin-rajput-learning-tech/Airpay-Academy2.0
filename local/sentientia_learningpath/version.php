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
// 2026-09-25 review — :view revoked from every learner role, not only the
// student archetype (upgrade step 2026092502, db/upgradelib.php). The
// top-level local/ copy is now this copy (it had none of the 2026061600
// adaptive schema, so a site moving from it would have skipped that step).
// 2026-09-30 (ADR-032, BizLMS import, feature learningplan): the importer
// (db/bizlms_import.php, classes/bizlms/), the columns and the lp_course_status
// table it fills (upgrade step 2026093001), the learner "My learning paths" page
// behind sentientia.learningpath.learner_paths.enabled (default OFF), the removal
// of the two legacy-table fallbacks in path_manager, imported-history protection,
// and the privacy provider for the new columns.
// 2026100701 (2026-10-07): ADR-032 owner decision IDN-04 - the importer implements the platform's copies_files marker (its
// cover copy is a declared side effect, counted in the run report). No schema change. The marker interface ships with
// local_sentientia_platform 2026100701, which this plugin therefore requires. One version for the batch (F-85).
$plugin->version   = 2026100701;  // ADR-032 IDN-04: copies_files marker (on top of 2026093001: learningplan importer + schema + learner page, flag OFF)
// 2026092502: :view revoked from learner roles without the student archetype.
// 2026092501: adaptive log scores keep 2 decimals.
// 2026092500: ADR-031: every pathid/userid/courseid tenant-checked; :view student default revoked.
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.9.1';  // ADR-032 IDN-04 copies_files marker (1.9.0: ADR-032 learningplan import; 1.8.3: learner-role :view revoke; 1.8.2: adaptive log score precision; 1.8.1: ADR-031 tenant scope; 1.8.0: +P0.2 Adaptive Learning Journeys)
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026100701,  // tenant::is_cross_tenant / scope_path; bizlms import framework (ADR-032); the copies_files marker (IDN-04)
];
