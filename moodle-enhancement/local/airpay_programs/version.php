<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
$plugin->version   = 2026050800;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.2.0'; // G-03: levels CRUD + courses-per-level + enrol UI
$plugin->dependencies = ['local_airpay_org' => 2026041600];
