<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_learningpath';
// P1 #2/8/10/11 (2026-05-16) — enrolment window + rich-text +
// target-audience bulk-enrol + cohort filter + bulk-enrol modal UI.
$plugin->version   = 2026051603;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.0';  // +P1 bulk-enrol-by-audience modal UI
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
