<?php
// Scheduled tasks for local_sentientia_analytics.
//
// @package    local_sentientia_analytics
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname'   => '\local_sentientia_analytics\task\refresh_predictive_cache',
        'blocking'    => 0,
        'minute'      => '5',   // 5 past each hour — avoids collision with hour-top crons
        'hour'        => '*',
        'day'         => '*',
        'month'       => '*',
        'dayofweek'   => '*',
        'disabled'    => 0,
    ],
];
