<?php
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_airpay_exams';
$plugin->version   = 2026050802;
$plugin->requires  = 2024100700;
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.3.0'; // G-06: :enrol capability + Enrol Users deep-link via quiz.course
$plugin->dependencies = [
    'local_airpay_org' => 2026041600,
];
