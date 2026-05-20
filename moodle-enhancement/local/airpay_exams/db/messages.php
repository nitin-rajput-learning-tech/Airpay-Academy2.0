<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #33 (2026-05-20) — message provider for the exam-deadline
// reminder cron task. Mirrors P1 #28's airpay_courses pattern but
// keyed on exams.

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'exam_reminder' => [
        'capability' => null,
    ],
];
