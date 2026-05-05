<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_exams';
$plugin->version   = 2026041910;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0'; // list_exams WS for shared datatable
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
