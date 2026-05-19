<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_learningpath';
// P1 batch + P1 #8 (2026-05-16) — enrolment window dates + rich-text
// description + target-audience bulk-enrol (preview_audience +
// bulk_enrol_by_audience WS).
$plugin->version   = 2026051601;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0';  // +P1 target-audience bulk enrol
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
