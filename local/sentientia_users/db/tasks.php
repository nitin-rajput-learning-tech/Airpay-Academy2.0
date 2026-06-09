<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #16 (2026-05-16) — register the daily HRMS sync scheduled task.
//
// BizLMS parity: closes audit item #4 from
// parity-audit-2026-05-15/sentientia_users.md (BizLMS had
// `classes/task/servicesync.php` running hourly via `db/tasks.php`).
//
// We schedule daily at 02:30 by default (post-midnight, before the
// 03:00 cron stampede most production sites hit). The admin can
// change the schedule from Site administration ▶ Server ▶
// Scheduled tasks, and disable the task entirely from the same page.
//
// The task is a no-op until `hrms_sync_mode` is set to 'url' or
// 'filesystem' on the local_sentientia_users settings page.

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_sentientia_users\\task\\hrms_sync',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '2',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
        // Disabled by default — admin must configure a source AND
        // enable from Scheduled tasks UI. This prevents an empty/bad
        // import on first install.
        'disabled'  => 1,
    ],
];
