<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #33 (2026-05-20) — register the daily exam deadline-reminder
// scheduled task. Closes audit item #16 from
// parity-audit-2026-05-15/airpay_exams.md (exam-side mirror of P1
// #28's course reminder task).
//
// Schedule: 09:15 daily — 15 min offset from P1 #28's course-reminder
// at 09:00 so they don't fight for DB locks. Disabled by default;
// admin opts in via `reminder_enabled` setting + scheduled-task enable.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_airpay_exams\\task\\exam_reminder',
        'blocking'  => 0,
        'minute'    => '15',
        'hour'      => '9',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
        'disabled'  => 1,
    ],
    // P1 #34 (2026-05-20) — exam overdue manager escalation. 45-min
    // offset from #33's reminder so they don't fight for locks if both
    // configured.
    [
        'classname' => '\\local_airpay_exams\\task\\exam_overdue',
        'blocking'  => 0,
        'minute'    => '45',
        'hour'      => '9',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
        'disabled'  => 1,
    ],
];
