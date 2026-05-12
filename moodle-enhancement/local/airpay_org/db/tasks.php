<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_airpay_org\task\sync_cohorts',
        'blocking'  => 0,
        'minute'    => '47',
        'hour'      => '2',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
