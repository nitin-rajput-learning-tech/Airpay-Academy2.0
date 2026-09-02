<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_api\webhooks\dispatcher;

/**
 * Event observers feeding the outbound webhook queue (ADR-030 Wave A).
 *
 * Payloads are deliberately minimal — ids and timestamps only, never names
 * or emails — so the queue table and the customer endpoint receive no PII
 * beyond opaque identifiers. Every handler is fail-safe: an exception is
 * logged via debugging() and never propagates into the originating event.
 *
 * @package local_sentientia_api
 */
class observer {

    /**
     * @param \core\event\course_completed $event
     * @return void
     */
    public static function course_completed(\core\event\course_completed $event): void {
        self::safe(function () use ($event) {
            $userid = (int) $event->relateduserid;
            dispatcher::enqueue('course.completed', dispatcher::tenant_of_user($userid), $userid, [
                'userid'        => $userid,
                'courseid'      => (int) $event->courseid,
                'timecompleted' => (int) $event->timecreated,
            ]);
        });
    }

    /**
     * @param \core\event\user_enrolment_created $event
     * @return void
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event): void {
        self::safe(function () use ($event) {
            $userid = (int) $event->relateduserid;
            $other  = (array) ($event->other ?? []);
            dispatcher::enqueue('enrolment.created', dispatcher::tenant_of_user($userid), $userid, [
                'userid'       => $userid,
                'courseid'     => (int) $event->courseid,
                'enrolmentid'  => (int) $event->objectid,
                'method'       => (string) ($other['enrol'] ?? ''),
                'timeenrolled' => (int) $event->timecreated,
            ]);
        });
    }

    /**
     * tool_certificate\event\certificate_issued — typed on the base class so
     * this plugin loads even where tool_certificate is absent.
     *
     * @param \core\event\base $event
     * @return void
     */
    public static function certificate_issued(\core\event\base $event): void {
        self::safe(function () use ($event) {
            $userid = (int) $event->relateduserid;
            $other  = (array) ($event->other ?? []);
            dispatcher::enqueue('certificate.issued', dispatcher::tenant_of_user($userid), $userid, [
                'userid'     => $userid,
                'issueid'    => (int) $event->objectid,
                'templateid' => (int) ($other['templateid'] ?? 0),
                'courseid'   => (int) ($event->courseid ?? 0),
                'timeissued' => (int) $event->timecreated,
            ]);
        });
    }

    /**
     * @param callable $fn
     * @return void
     */
    private static function safe(callable $fn): void {
        try {
            $fn();
        } catch (\Throwable $e) {
            debugging('local_sentientia_api webhook observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
