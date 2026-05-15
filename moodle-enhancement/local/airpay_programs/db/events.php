<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-9 (2026-05-15) — observer registration for local_airpay_programs.
 *
 * Hook into core's course_completed to detect program completion.
 */
$observers = [
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback'  => '\\local_airpay_programs\\observer::course_completed',
        'internal'  => true,
        'priority'  => 8000,
    ],
];
