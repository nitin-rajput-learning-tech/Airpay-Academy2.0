<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
$plugin->version   = 2026051160;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.6.0'; // +Phase 5 A.5: locations (replaces dropped BizLMS local_location)
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
