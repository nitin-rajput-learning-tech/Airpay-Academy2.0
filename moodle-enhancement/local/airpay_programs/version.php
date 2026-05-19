<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
// W1-9 + P1 #9/10 (2026-05-16) — program_completed event + enrolment-window
// dates + rich-text + target-audience bulk-enrol + cohort filter.
$plugin->version   = 2026051601;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.7.0';  // +P1 cohort filter on audience enroller
$plugin->dependencies = ['local_airpay_org' => 2026041600];
