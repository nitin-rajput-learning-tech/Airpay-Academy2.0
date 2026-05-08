<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
$plugin->version   = 2026050900;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.0'; // +Phase H.1: ICS calendar download for sessions
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
