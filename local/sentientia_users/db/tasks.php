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
    // ADR-036 D9 (session 5, 2026-11-20): external-trainer expiry. Daily: recompute each active
    // external trainer's expiry from their sessions, remind the tenant's admins 7 days before it,
    // and suspend the accounts that reached it (never deletes). Enabled by default: it is a security
    // control, and with no external-trainer rows (the flag was never ON) it does nothing.
    [
        'classname' => '\\local_sentientia_users\\task\\external_trainer_expiry',
        'blocking'  => 0,
        'minute'    => '15',
        'hour'      => '1',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
