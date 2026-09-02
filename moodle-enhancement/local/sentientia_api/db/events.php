<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Event observers — outbound webhooks (ADR-030 Wave A).
 *
 * Every observer is a complete no-op unless BOTH sentientia.api.enabled and
 * sentientia.api.webhooks.enabled resolve ON for the affected user's
 * customer + tenant. Observers only ENQUEUE a delivery row; the actual HTTP
 * POST happens in \local_sentientia_api\task\webhook_drain, never inline.
 *
 * Non-blocking (internal=false) so nothing here runs inside core transactions,
 * lowest priority so email/push/WhatsApp observers fire first, and every
 * handler is fail-safe (exceptions are caught and surfaced via debugging()).
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_completed',
        'callback'  => '\\local_sentientia_api\\observer::course_completed',
        'priority'  => 0,
        'internal'  => false,
    ],
    [
        'eventname' => '\\core\\event\\user_enrolment_created',
        'callback'  => '\\local_sentientia_api\\observer::user_enrolment_created',
        'priority'  => 0,
        'internal'  => false,
    ],
    [
        'eventname' => '\\tool_certificate\\event\\certificate_issued',
        'callback'  => '\\local_sentientia_api\\observer::certificate_issued',
        'priority'  => 0,
        'internal'  => false,
    ],
];
