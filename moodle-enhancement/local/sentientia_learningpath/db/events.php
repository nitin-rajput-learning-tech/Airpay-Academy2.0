<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Event observer registration for local_sentientia_learningpath.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * Registers the quiz attempt observer that drives the adaptive journey
 * engine. The observer is a no-op when the feature flag
 * sentientia.learningpath.adaptive.enabled is OFF.
 *
 * @package local_sentientia_learningpath
 */

defined('MOODLE_INTERNAL') || die();

$observers = [

    [
        // Fired when a learner submits (finalises) a quiz attempt.
        // We use this to evaluate quiz scores and potentially pivot the
        // learner's path (remediate / accelerate / branch).
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => '\local_sentientia_learningpath\observer::quiz_attempt_submitted',
        'includefile' => null,
        'internal'    => true,
        'priority'    => 200,
    ],

];
