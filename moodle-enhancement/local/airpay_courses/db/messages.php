<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #28 (2026-05-20) — message provider for the course-deadline
// reminder cron task. Closes audit item #14 from
// parity-audit-2026-05-15/airpay_courses.md.
//
// Users see this as a configurable channel under "Notification
// preferences" → "Airpay Courses" so they can opt out per-medium
// (email / popup / mobile push) without blocking other plugins.

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'course_reminder' => [
        // No default-locked-on enabled flag — Moodle defaults to
        // "email + popup ON, mobile OFF" which is the right behaviour
        // for compliance nudges.
        'capability' => null,
    ],

    // P1 #29 (2026-05-20) — supervisor escalation when a learner is past
    // their course deadline. Closes audit item #15. Recipient is the
    // learner's `user.open_supervisorid`, NOT the learner themselves —
    // managers benefit from email + popup defaults.
    'course_overdue_supervisor' => [
        'capability' => null,
    ],
];
