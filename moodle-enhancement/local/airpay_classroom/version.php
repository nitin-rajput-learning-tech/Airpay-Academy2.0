<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
$plugin->version   = 2026050800;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0'; // G-02: sessions tab + roster + attendance UI
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
