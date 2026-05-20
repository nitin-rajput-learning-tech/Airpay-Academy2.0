<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #28 (2026-05-20) — register the daily course-deadline reminder
// scheduled task. Closes audit item #14 from
// parity-audit-2026-05-15/airpay_courses.md (BizLMS
// local_courses\task\course_reminder parity).
//
// Default schedule: 09:00 daily — reminders land in inboxes during
// work hours so learners see them at the start of their day.
// Admin can change the time + frequency from Site admin ▶ Server ▶
// Scheduled tasks.
//
// `disabled => 1` because the task does NOTHING until an admin opts
// into the workflow by ticking `reminder_enabled` in the local plugin
// settings. Two-step opt-in (configure + enable) protects fresh
// installs from accidentally spamming everyone on day 1.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_airpay_courses\\task\\course_reminder',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '9',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
        'disabled'  => 1,
    ],
];
