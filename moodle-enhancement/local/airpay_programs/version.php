<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
$plugin->version   = 2026050901;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.4.0'; // +Phase F.3: mass-enrol cohort into program
$plugin->dependencies = ['local_airpay_org' => 2026041600];
