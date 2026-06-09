<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Event observer registrations for local_sentientia_emails.
 *
 * Sprint B (2026-05-13) — register a handler for
 * \core\event\course_completed so we can:
 *
 *   1. Send the user a polished congratulations email with their
 *      tool_certificate PDF attached (if one was issued).
 *   2. Stamp pre-existing reminder log rows for the (user, course)
 *      pair with status='suppressed_completion', so dashboards
 *      and analytics correctly show "the learner finished" without
 *      needing to re-query course_completions.
 *
 * The observer is non-blocking — it MUST NOT throw on certificate
 * lookup or PDF generation failures. The course-completion flow is
 * Moodle-core-driven and exceptions in our observer would break it.
 *
 * @package local_sentientia_emails
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback'  => '\\local_sentientia_emails\\observer::course_completed',
        'priority'  => 100,        // run before lower-priority observers
        'internal'  => false,      // run even in installation/upgrade context
    ],
];
