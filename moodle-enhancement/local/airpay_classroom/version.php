<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
// W1-7 + W1-9 + P1 #4/13/15 (2026-05-16) — meeting URLs + dates +
// audience enroller + Hindi pack.
$plugin->version   = 2026051601;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.10.0';  // +P1 audience enroller + Hindi
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
