<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sentientia_learningpath';
// P1 #2/8/10/11 (2026-05-16) — enrolment window + rich-text +
// target-audience bulk-enrol + cohort filter + bulk-enrol modal UI.
// P1 #46 (2026-05-20) — Hindi top-up: 30 strings covering CRUD, confirms,
// view tabs, errors, privacy metadata.
$plugin->version   = 2026052001;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.1';  // +P1 #46 Hindi top-up
$plugin->dependencies = [
    'local_sentientia_org' => 2026041600,
];
