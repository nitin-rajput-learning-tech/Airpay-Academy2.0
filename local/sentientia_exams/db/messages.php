<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #33 (2026-05-20) — message provider for the exam-deadline
// reminder cron task. Mirrors P1 #28's sentientia_courses pattern but
// keyed on exams.

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'exam_reminder' => [
        'capability' => null,
    ],

    // P1 #34 (2026-05-20) — supervisor escalation when a learner misses
    // an exam's quiz.timeclose. Sister to P1 #29's sentientia_courses
    // course_overdue_supervisor. Recipient is the learner's
    // user.open_supervisorid.
    'exam_overdue_supervisor' => [
        'capability' => null,
    ],
];
