<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Event observers for local_sentientia_proctoring (B1, ADR-038 D2, 2026-11-20).
 *
 * The quiz attempt lifecycle drives the proctoring session on the server: a started attempt claims the session the
 * learner opened in the pre-start check; a submitted or abandoned attempt closes and scores it. All four are
 * external ('internal' => false): they run after the quiz's transaction commits and can never block an attempt.
 *
 * @package local_sentientia_proctoring
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_quiz\event\attempt_started',
        'callback'  => '\local_sentientia_proctoring\observer::attempt_started',
        'internal'  => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_sentientia_proctoring\observer::attempt_submitted',
        'internal'  => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_abandoned',
        'callback'  => '\local_sentientia_proctoring\observer::attempt_abandoned',
        'internal'  => false,
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_becameoverdue',
        'callback'  => '\local_sentientia_proctoring\observer::attempt_becameoverdue',
        'internal'  => false,
    ],
];
