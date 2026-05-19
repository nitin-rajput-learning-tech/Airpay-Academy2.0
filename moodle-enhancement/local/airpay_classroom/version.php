<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
// W1-7 + W1-9 + P1-dates (2026-05-16) — meeting_url/recording_url on sessions
// PLUS classroom_completed event PLUS startdate/enddate enrolment window.
$plugin->version   = 2026051600;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.9.0';  // +P1 enrolment window dates
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
