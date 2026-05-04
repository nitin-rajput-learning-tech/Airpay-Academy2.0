<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_exams';
$plugin->version   = 2026041909;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0';
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
