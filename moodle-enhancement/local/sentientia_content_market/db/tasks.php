<?php
/**
 * Scheduled tasks for local_sentientia_content_market.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\local_sentientia_content_market\task\sync_providers',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2',    // 02:00 server time — low-traffic window
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
