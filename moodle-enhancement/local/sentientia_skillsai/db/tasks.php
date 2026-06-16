<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled task registration for local_sentientia_skillsai.
 *
 * The gap-feed rebuild runs nightly. It self-gates on
 * sentientia.skillsai.gap_engine (default OFF) so registering it is safe
 * even before the feature is enabled — it no-ops until the flag flips.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_sentientia_skillsai\task\rebuild_gap_feed',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '2',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
