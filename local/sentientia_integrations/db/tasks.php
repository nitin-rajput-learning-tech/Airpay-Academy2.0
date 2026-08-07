<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_integrations.
 *
 * History: the HRMS sync task that used to live here was a DUPLICATE of
 * keka_client::sync_employees — different field shapes, different status
 * normalisation, different password defaults — which created a
 * duplicate-user risk if both ran. That task class was removed in commit
 * 2026050700 (INTEGRATIONS-AUDIT.md §3.2).
 *
 * 2026-08-07: reconciliation reinstated as task\keka_reconcile. It is a
 * thin wrapper around keka_client::sync_employees() — the SAME code path
 * the webhook uses — so the §3.2 duplicate-implementation hazard cannot
 * recur. Registered DISABLED; it additionally requires the
 * sentientia.hrms.reconcile.enabled platform flag (default OFF) and the
 * hrms_enable admin setting before it touches KeKa.
 */
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_integrations\task\keka_reconcile',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '2',    // 02:30 nightly — quiet window before the 07:00 compliance check.
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1,      // Deliberate: flag + setting + task enable = triple opt-in.
    ],
];
