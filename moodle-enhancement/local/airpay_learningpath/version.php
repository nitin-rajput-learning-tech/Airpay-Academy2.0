<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_learningpath';
// P1 batch (2026-05-16) — enrolment window dates + rich-text description.
$plugin->version   = 2026051600;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.0';  // +startdate/enddate + rich-text description
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
