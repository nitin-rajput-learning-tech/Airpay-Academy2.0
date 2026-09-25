<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Scheduled task definitions for local_sentientia_learningpath.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * @package local_sentientia_learningpath
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [

    [
        // Daily velocity sweep — re-evaluates completion velocity for all
        // learners enrolled in adaptive paths. Catches inactive learners
        // who fall behind without submitting a quiz. A no-op when the
        // feature flag sentientia.learningpath.adaptive.enabled is OFF.
        'classname' => '\local_sentientia_learningpath\task\adaptive_sweep',
        'blocking'  => 0,
        'minute'    => '30',
        'hour'      => '2',     // 02:30 server time — low-traffic window
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
        'disabled'  => 0,
    ],

];
