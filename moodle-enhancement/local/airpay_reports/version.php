<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_reports';
$plugin->version   = 2026041914;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0'; // list_reports WS
$plugin->dependencies = ['local_airpay_org' => 2026041600];
