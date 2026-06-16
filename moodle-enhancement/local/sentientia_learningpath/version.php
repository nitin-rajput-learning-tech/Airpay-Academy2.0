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
$plugin->version   = 2026061600;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.0';  // +P0.2 Adaptive Learning Journeys
$plugin->dependencies = [
    'local_sentientia_org'      => 2026041600,
    'local_sentientia_platform' => 2026051401,
];
