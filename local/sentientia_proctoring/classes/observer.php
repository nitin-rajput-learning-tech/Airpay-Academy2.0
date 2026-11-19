<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Quiz attempt observers (B1, ADR-038 D2, 2026-11-20): the server, not the browser, ends a proctoring session.
 *
 * Until B1 a session was finalized only by proctor.stop() in the browser, which nothing called, so no session was
 * ever scored. These observers run for every quiz attempt on the site; each first does one indexed lookup and
 * returns when the attempt has no proctoring session (and its learner has none waiting), so an unproctored quiz pays
 * one or two cheap queries per attempt event.
 *
 * Registered with 'internal' => false (db/events.php): they run after the quiz's own transaction commits, so a
 * failure here can never roll back or block a quiz submission. A failure is reported with debugging() and the
 * attempt's session stays open; a later attempt event, or the reviewer, sees it.
 *
 * The feature flag is not read here: a session exists only because the flag was ON when the learner started, and it
 * must be closed whatever the flag says now.
 *
 * @package local_sentientia_proctoring
 */
class observer {

    /**
     * An attempt started: bind the learner's pending session (consent given in the pre-start check) to it.
     *
     * @param \mod_quiz\event\attempt_started $event
     * @return void
     */
    public static function attempt_started(\mod_quiz\event\attempt_started $event): void {
        self::run(function() use ($event): void {
            $userid = (int) $event->relateduserid;
            if (!quiz_integration::has_pending($userid)) {
                return;
            }
            $quizid = self::quizid($event);
            if ($quizid > 0) {
                quiz_integration::session_for_attempt($userid, $quizid, (int) $event->objectid);
            }
        });
    }

    /**
     * An attempt was submitted (by the learner, or by cron when its time ran out): close and score its session.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     * @return void
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::finish($event, 'submitted');
    }

    /**
     * An attempt was abandoned (never submitted before the grace period ended): close and score its session.
     *
     * @param \mod_quiz\event\attempt_abandoned $event
     * @return void
     */
    public static function attempt_abandoned(\mod_quiz\event\attempt_abandoned $event): void {
        self::finish($event, 'abandoned');
    }

    /**
     * An attempt went overdue. It is not finished (the learner may still submit in the grace period), so the session
     * stays open; the event is noted on it.
     *
     * @param \mod_quiz\event\attempt_becameoverdue $event
     * @return void
     */
    public static function attempt_becameoverdue(\mod_quiz\event\attempt_becameoverdue $event): void {
        self::run(function() use ($event): void {
            if (!self::is_proctored_attempt($event)) {
                return;
            }
            quiz_integration::note_overdue((int) $event->objectid, (int) $event->relateduserid, self::quizid($event));
        });
    }

    /**
     * Close the session of an attempt that ended.
     *
     * @param \core\event\base $event attempt_submitted or attempt_abandoned
     * @param string $reason submitted|abandoned
     * @return void
     */
    private static function finish(\core\event\base $event, string $reason): void {
        self::run(function() use ($event, $reason): void {
            if (!self::is_proctored_attempt($event)) {
                return;
            }
            quiz_integration::finalize_attempt((int) $event->objectid, (int) $event->relateduserid,
                self::quizid($event), $reason);
        });
    }

    /**
     * Cheap test: does the attempt have a session, or its learner one waiting to be bound?
     *
     * @param \core\event\base $event
     * @return bool
     */
    private static function is_proctored_attempt(\core\event\base $event): bool {
        global $DB;
        return $DB->record_exists('local_sentientia_proctor_sessions', ['attemptid' => (int) $event->objectid])
            || quiz_integration::has_pending((int) $event->relateduserid);
    }

    /**
     * The quiz of the event's attempt: other['quizid'] where the event carries it (the state-transition events do),
     * else the attempt row (attempt_started carries no quizid).
     *
     * @param \core\event\base $event
     * @return int
     */
    private static function quizid(\core\event\base $event): int {
        global $DB;
        if (!empty($event->other['quizid'])) {
            return (int) $event->other['quizid'];
        }
        return (int) $DB->get_field('quiz_attempts', 'quiz', ['id' => (int) $event->objectid]);
    }

    /**
     * Run an observer body; report a failure instead of letting it escape into the quiz's event dispatch.
     *
     * @param callable $fn
     * @return void
     */
    private static function run(callable $fn): void {
        try {
            $fn();
        } catch (\Throwable $e) {
            debugging('local_sentientia_proctoring observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
