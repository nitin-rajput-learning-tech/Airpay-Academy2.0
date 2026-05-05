<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
$plugin->version   = 2026041907;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.1.0'; // list_programs WS
$plugin->dependencies = ['local_airpay_org' => 2026041600];
