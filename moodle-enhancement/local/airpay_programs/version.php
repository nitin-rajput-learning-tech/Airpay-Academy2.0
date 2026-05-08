<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_programs';
$plugin->version   = 2026050900;
$plugin->requires  = 2022041900;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0'; // +Phase F.1: prereq enforcement (sequential level unlocking)
$plugin->dependencies = ['local_airpay_org' => 2026041600];
