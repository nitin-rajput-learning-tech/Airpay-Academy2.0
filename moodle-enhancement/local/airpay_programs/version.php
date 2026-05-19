<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
// W1-9 + P1 #9/10/14/15 (2026-05-16) — program_completed event + window +
// rich-text + audience enroller + cohort filter + bulk-enrol modal UI + Hindi.
$plugin->version   = 2026051602;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.8.0';  // +P1 bulk-enrol UI + Hindi
$plugin->dependencies = ['local_airpay_org' => 2026041600];
