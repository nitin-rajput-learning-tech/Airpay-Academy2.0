<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

/**
 * W1-5 (2026-05-15) — observer registration.
 *
 * Maps Moodle (and Airpay-emitted) events to the queue handlers in
 * `\local_airpay_evaluation\observer`. When one of these events fires, the
 * observer scans `local_airpay_evaluation` for ACTIVE rows whose
 * `trigger_event` matches and the user is in scope, then enqueues a row in
 * `local_airpay_evaluation_triggers` with `fire_after = event_time + (days_after * 86400)`.
 * The scheduled task `process_triggers` drains the queue when fire_after has
 * passed.
 *
 * The program_completion + classroom_end observers are listed below as
 * placeholders. They become active once W1-9 (event emission) ships matching
 * `\core\event\base` subclasses in airpay_programs + airpay_classroom. Until
 * then, only the course_completion path is exercised.
 */
$observers = [

    // ── course_completion ────────────────────────────────────────────────
    // Native Moodle event. Always emitted on course_completions row insert
    // with timecompleted > 0 (see `/lib/classes/completion/completion_completion.php`).
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback'  => '\\local_airpay_evaluation\\observer::course_completed',
        'internal'  => true,
        'priority'  => 9000,
    ],

    // ── program_completion (W1-9 dependency) ─────────────────────────────
    // Activates once `\local_airpay_programs\event\program_completed::create()`
    // is emitted from the program completion path. Until then, this entry
    // is benign — Moodle simply never invokes it because the event isn't
    // emitted anywhere yet.
    [
        'eventname' => '\\local_airpay_programs\\event\\program_completed',
        'callback'  => '\\local_airpay_evaluation\\observer::program_completed',
        'internal'  => true,
        'priority'  => 9000,
    ],

    // ── classroom_end (W1-9 wired) ────────────────────────────────────────
    // W1-9 now emits `\local_airpay_classroom\event\classroom_completed`
    // from session_manager::change_status() when an admin transitions a
    // classroom into STATUS_COMPLETED.
    [
        'eventname' => '\\local_airpay_classroom\\event\\classroom_completed',
        'callback'  => '\\local_airpay_evaluation\\observer::classroom_ended',
        'internal'  => true,
        'priority'  => 9000,
    ],

];
