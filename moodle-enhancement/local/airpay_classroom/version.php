<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_classroom';
$plugin->version   = 2026051130;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.5.0'; // +Phase 3 B.4: waiting list + auto-promote on cancel
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
