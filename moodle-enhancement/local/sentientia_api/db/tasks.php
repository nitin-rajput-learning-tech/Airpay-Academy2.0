<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled tasks for local_sentientia_api.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_api\task\cleanup',
        'blocking'  => 0,
        'minute'    => '17',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
    // ADR-030 Wave A — outbound webhook drain. Registered DISABLED (triple
    // opt-in like ADR-029's reconcile task: feature flag + this task enabled +
    // at least one subscription). Enable via Site admin > Scheduled tasks
    // once the webhooks flag is ON for a customer.
    [
        'classname' => 'local_sentientia_api\task\webhook_drain',
        'blocking'  => 0,
        'minute'    => '*',
        'hour'      => '*',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 1,
    ],
];
