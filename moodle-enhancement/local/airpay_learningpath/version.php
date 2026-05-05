<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_airpay_learningpath';
$plugin->version   = 2026041905;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0'; // list_paths WS
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
