<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\event;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_proctoring\external\submit_review;
use local_sentientia_proctoring\session_manager;

/**
 * Decision A5 (2026-11-12): a reviewer's decision on a proctored session triggers review_submitted, the event
 * \local_sentientia_platform\audit_log::SENSITIVE_EVENTS lists, once, after session_manager::submit_review() has
 * stored the review and the session's reviewed status.
 *
 * Each test drives the submit_review web service, the path reviewers use, with the event sink redirected, so what is
 * asserted is what the standard logstore would have been handed.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\event\review_submitted
 * @covers     \local_sentientia_proctoring\session_manager::submit_review
 * @covers     \local_sentientia_proctoring\external\submit_review
 *
 * @group local_sentientia_proctoring
 */
final class review_submitted_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /**
     * A user at a tenant path, re-read so the record carries it.
     *
     * @param string $path
     * @return \stdClass
     */
    private function user_at(string $path): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A flagged session of $candidate on a new quiz, stored the way finalize() leaves one.
     *
     * @param \stdClass $candidate
     * @return array [session row, quiz]
     */
    private function flagged_session(\stdClass $candidate): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $parts = explode('/', trim((string) $candidate->open_path, '/'));
        $now = time();
        $id = $DB->insert_record('local_sentientia_proctor_sessions', (object) [
            'userid'        => $candidate->id,
            'quizid'        => $quiz->id,
            'costcenterid'  => ctype_digit($parts[0] ?? '') ? (int) $parts[0] : 0,
            'status'        => 'flagged',
            'risk_score'    => 75,
            'auto_decision' => 'fail',
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);
        return [$DB->get_record('local_sentientia_proctor_sessions', ['id' => $id], '*', MUST_EXIST), $quiz];
    }

    /**
     * Run $fn with events and messages redirected and return the review_submitted events it triggered.
     *
     * @param callable $fn
     * @return review_submitted[]
     */
    private function review_events(callable $fn): array {
        $messages = $this->redirectMessages();
        $sink = $this->redirectEvents();
        try {
            $fn();
        } finally {
            $events = $sink->get_events();
            $sink->close();
            $messages->close();
        }
        return array_values(array_filter($events, fn($e) => $e instanceof review_submitted));
    }

    public function test_a_review_triggers_review_submitted_once_in_the_quiz_context(): void {
        global $DB;
        $candidate = $this->user_at('/1/4');
        [$session, $quiz] = $this->flagged_session($candidate);
        $this->setAdminUser();

        $events = $this->review_events(fn() => $this->assertTrue(
            submit_review::execute((int) $session->id, 'fail', 'Second person visible at 12:04')['success']));

        $this->assertCount(1, $events, 'one review, one audit event');
        $event = $events[0];
        $review = $DB->get_record('local_sentientia_proctor_reviews', ['sessionid' => $session->id], '*', MUST_EXIST);

        $this->assertSame('reviewed', $DB->get_field('local_sentientia_proctor_sessions', 'status',
            ['id' => $session->id]), 'the event follows the stored review');
        $this->assertSame((int) $session->id, (int) $event->objectid);
        $this->assertSame('local_sentientia_proctor_sessions', $event->objecttable);
        $this->assertSame((int) $candidate->id, (int) $event->relateduserid);
        $this->assertSame((int) get_admin()->id, (int) $event->userid, 'the reviewer');
        $this->assertSame(\context_module::instance((int) $quiz->cmid)->id, (int) $event->contextid,
            'the most specific real context: the quiz');
        $this->assertSame((int) $quiz->course, (int) $event->courseid);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_OTHER, (int) $event->edulevel);
        $this->assertSame(['reviewid' => (int) $review->id, 'quizid' => (int) $quiz->id, 'decision' => 'fail'],
            $event->other);

        // Ids and codes only: the reviewer's note never reaches the log.
        $this->assertStringNotContainsString('Second person visible', json_encode($event->get_data()));
        $this->assertStringContainsString("'{$session->id}'", $event->get_description());
        $this->assertSame('Proctoring review submitted', review_submitted::get_name());
        $this->assertSame('/local/sentientia_proctoring/attempt.php?id=' . $session->id,
            $event->get_url()->out_as_local_url(false));
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_each_decision_is_carried_as_its_code(): void {
        $this->setAdminUser();
        foreach (review_submitted::DECISIONS as $decision) {
            [$session] = $this->flagged_session($this->user_at('/77'));
            $events = $this->review_events(fn() => submit_review::execute((int) $session->id, $decision, ''));
            $this->assertCount(1, $events, $decision);
            $this->assertSame($decision, $events[0]->other['decision']);
        }
    }

    public function test_an_unknown_decision_triggers_nothing_and_writes_nothing(): void {
        global $DB;
        [$session] = $this->flagged_session($this->user_at('/1/4'));
        $this->setAdminUser();

        $events = $this->review_events(function() use ($session): void {
            try {
                submit_review::execute((int) $session->id, 'maybe', '');
                $this->fail('only clean, warn and fail are decisions');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_session_state', $e->errorcode);
            }
        });

        $this->assertSame([], $events);
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_reviews', ['sessionid' => $session->id]));
        $this->assertSame('flagged', $DB->get_field('local_sentientia_proctor_sessions', 'status',
            ['id' => $session->id]));
    }

    public function test_a_refused_reviewer_triggers_nothing(): void {
        global $DB;
        [$session] = $this->flagged_session($this->user_at('/177'));

        // A /1 tenant admin (manager archetype: holds :review) may not review a /177 session.
        $reviewer = $this->user_at('/1');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $reviewer->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($reviewer);

        $events = $this->review_events(function() use ($session): void {
            try {
                submit_review::execute((int) $session->id, 'clean', '');
                $this->fail('ADR-031: a reviewer reviews only in their own tenant');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        });

        $this->assertSame([], $events);
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_reviews', ['sessionid' => $session->id]));
    }

    public function test_the_session_manager_path_is_the_one_that_triggers(): void {
        // The web service is a thin wrapper: a caller of session_manager::submit_review() (the smoke CLI) is logged too.
        [$session] = $this->flagged_session($this->user_at('/77'));
        $reviewer = $this->user_at('/77');

        $events = $this->review_events(
            fn() => session_manager::submit_review((int) $session->id, (int) $reviewer->id, 'warn', 'note'));

        $this->assertCount(1, $events);
        $this->assertSame((int) $reviewer->id, (int) $events[0]->userid,
            'the reviewer named by the caller, not whoever runs the request');
    }
}
