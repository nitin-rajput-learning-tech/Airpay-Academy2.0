<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        // Fast-path: when a course completion is recorded, immediately
        // re-evaluate any challenge attempts the user has running so the
        // leaderboard and points reflect the new state without waiting
        // for the 15-min recompute task.
        'eventname' => '\core\event\course_completed',
        'callback'  => '\local_airpay_challenge\observer::on_course_completed',
        'priority'  => 100,
        'internal'  => false,
    ],
    [
        // Phase 2 — keep streak-based attempts fresh on every login.
        'eventname' => '\core\event\user_loggedin',
        'callback'  => '\local_airpay_challenge\observer::on_user_loggedin',
        'priority'  => 100,
        'internal'  => false,
    ],
    [
        // Phase 2 — re-evaluate quiz-score-based attempts when a quiz
        // attempt is submitted.
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback'  => '\local_airpay_challenge\observer::on_quiz_attempt_submitted',
        'priority'  => 100,
        'internal'  => false,
    ],
];
