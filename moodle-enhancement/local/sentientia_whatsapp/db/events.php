<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Event observer registrations for local_sentientia_whatsapp.
 *
 * Stream F / Wave E2 P4 (2026-05-25) — three observers for the
 * content-event WhatsApp triggers. The fourth trigger (course-due-soon)
 * is wired inline from \local_airpay_courses\task\course_reminder.
 *
 * Every observer is non-blocking (internal=false) so it doesn't run
 * inside core transactions, and uses the lowest priority so other
 * plugins' email + push observers fire first — WhatsApp is the
 * tail of the channel cascade.
 *
 * Every handler is fail-safe — a thrown exception will be caught and
 * surfaced via debugging() without poisoning the originating event.
 *
 * @package local_sentientia_whatsapp
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Course visibility 0→1 transition — new course in catalogue.
    [
        'eventname' => '\\core\\event\\course_updated',
        'callback'  => '\\local_sentientia_whatsapp\\observer::course_updated',
        'priority'  => 0,
        'internal'  => false,
    ],

    // tool_certificate cert issued.
    [
        'eventname' => '\\tool_certificate\\event\\certificate_issued',
        'callback'  => '\\local_sentientia_whatsapp\\observer::certificate_issued',
        'priority'  => 0,
        'internal'  => false,
    ],

    // Course completion → recompute path progress → maybe fire milestone.
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback'  => '\\local_sentientia_whatsapp\\observer::course_completed',
        'priority'  => 0,
        'internal'  => false,
    ],
];
