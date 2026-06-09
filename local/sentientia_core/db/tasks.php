<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled tasks for local_sentientia_core.
 *
 * The reconcile_org task is registered ENABLED but self-gates on the
 * `org_dualwrite_enabled` flag (default OFF) — it no-ops every run until an
 * admin opts in, so adding it changes nothing on deploy (ADR-020 Wave 3.2b).
 */

$tasks = [
    [
        'classname' => 'local_sentientia_core\task\reconcile_org',
        'blocking'  => 0,
        'minute'    => 'R',     // Randomised within the hour to spread load.
        'hour'      => '*/4',   // Eventually-consistent mirror — every 4 hours.
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],
];
