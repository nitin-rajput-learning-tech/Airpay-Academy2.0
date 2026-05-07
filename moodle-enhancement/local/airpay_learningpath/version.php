<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_learningpath';
$plugin->version   = 2026050701;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0'; // G-04: assign-courses + enrol-users UI + 7 new WS
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
