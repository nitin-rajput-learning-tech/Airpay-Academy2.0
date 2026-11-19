<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use local_sentientia_platform\feature_flags;
use local_sentientia_proctoring\external\give_consent;
use local_sentientia_proctoring\external\report_event;
use local_sentientia_proctoring\external\submit_identity;
use local_sentientia_proctoring\external\upload_chunk;
use mod_quiz\quiz_attempt;

/**
 * B1 review (2026-11-20): the web services of the pre-B1 browser flow stay registered (ADR-038 D2: the 12 services
 * stay) and are open to every logged-in user, so they must not change a session that the pre-start check or the
 * server owns.
 *
 *  - give_consent and submit_identity act only on the caller's own session that has no attempt and has not started
 *    (new or consenting; identity also verifying). Before this a learner could send their active session back to
 *    `consenting` mid-attempt, which stopped the monitor, or reopen a flagged or reviewed session, which left the
 *    review queue for good.
 *  - upload_chunk refuses while sentientia.proctoring.recording.enabled is OFF: nothing records before B4, and a
 *    registered key names a bucket object the retention purge later deletes.
 *
 * The sessions are driven the way the access rule drives them (quiz_integration); the attempt observers are external,
 * so the tests do not run inside the rollback transaction.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\session_manager::record_consent
 * @covers     \local_sentientia_proctoring\session_manager::submit_identity
 * @covers     \local_sentientia_proctoring\external\give_consent
 * @covers     \local_sentientia_proctoring\external\submit_identity
 * @covers     \local_sentientia_proctoring\external\upload_chunk
 */
final class legacy_services_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    /**
     * A quiz with one true/false question.
     *
     * @return \stdClass quiz instance with cmid
     */
    private function quiz(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 1]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        return $quiz;
    }

    /**
     * A learner who passed the pre-start check and started an attempt: [learner, attempt, session]. The current user
     * is the learner.
     *
     * @param \stdClass $quiz
     * @return array
     */
    private function proctored_attempt(\stdClass $quiz): array {
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $quiz->id, (int) $learner->id);
        $session = quiz_integration::session_for_attempt((int) $learner->id, (int) $quiz->id, (int) $attempt->id);
        $this->assertNotNull($session, 'the attempt claimed the consented session');
        $this->assertSame(quiz_integration::ACTIVE, $session->status);
        return [$learner, $attempt, $session];
    }

    /**
     * Reload a session.
     *
     * @param int $id
     * @return \stdClass
     */
    private function reload(int $id): \stdClass {
        global $DB;
        return $DB->get_record('local_sentientia_proctor_sessions', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A base64 "photo" that passes submit_identity's checks (PNG signature) and the mock verifier.
     *
     * @return string
     */
    private function photo(): string {
        return base64_encode("\x89PNG\r\n\x1A\n" . str_repeat("\0", 32));
    }

    /**
     * Call give_consent, then submit_identity, on the session as the current user: both must be refused with
     * error_session_state and leave the session row exactly as it was, with no identity row written.
     *
     * @param int $sessionid
     * @param string $why
     */
    private function assert_both_refused(int $sessionid, string $why): void {
        global $DB;
        $before = $this->reload($sessionid);
        $identities = $DB->count_records('local_sentientia_proctor_identity', ['sessionid' => $sessionid]);

        $this->assert_refused(fn() => give_consent::execute($sessionid), 'error_session_state', 'give_consent: ' . $why);
        $this->assertEquals($before, $this->reload($sessionid), 'give_consent left the session unchanged: ' . $why);

        $e = $this->assert_refused(fn() => submit_identity::execute($sessionid, $this->photo(), $this->photo()),
            'error_session_state', 'submit_identity: ' . $why);
        // The photo checks and the rate limit throw the same code with a reason in $a; the session check has none.
        $this->assertNull($e->a, 'refused by the session check, not by the photo checks: ' . $why);
        $this->assertEquals($before, $this->reload($sessionid), 'submit_identity left the session unchanged: ' . $why);
        $this->assertSame($identities,
            $DB->count_records('local_sentientia_proctor_identity', ['sessionid' => $sessionid]),
            'no identity check was stored: ' . $why);
    }

    /**
     * Assert the call is refused with the given error code.
     *
     * @param callable $fn
     * @param string $errorcode
     * @param string $message
     * @return \moodle_exception the refusal
     */
    private function assert_refused(callable $fn, string $errorcode, string $message = ''): \moodle_exception {
        try {
            $fn();
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode, $message);
            return $e;
        }
        $this->fail('expected ' . $errorcode . ($message !== '' ? ' (' . $message . ')' : ''));
    }

    public function test_consent_and_identity_mid_attempt_are_refused_and_the_monitor_keeps_recording(): void {
        $quiz = $this->quiz();
        [, , $session] = $this->proctored_attempt($quiz);

        $this->assert_both_refused((int) $session->id, 'session bound to an open attempt');

        // The monitor carries on: the session is still active and still takes events.
        $session = $this->reload((int) $session->id);
        $this->assertSame(quiz_integration::ACTIVE, $session->status);
        $this->assertTrue(quiz_integration::accepts_client_events($session));
        $this->assertGreaterThan(0, report_event::execute((int) $session->id, 'tab_switch')['eventid']);
    }

    public function test_a_flagged_or_reviewed_session_is_not_taken_out_of_the_review_queue(): void {
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->proctored_attempt($quiz);
        // Enough monitor events for a warn verdict: 4 pastes x 10 = 40.
        for ($i = 0; $i < 4; $i++) {
            report_event::execute((int) $session->id, 'clipboard_paste');
        }
        $messages = $this->redirectMessages();
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame('flagged', $this->reload((int) $session->id)->status);

        $this->assert_both_refused((int) $session->id, 'flagged session');
        $this->assertSame('flagged', $this->reload((int) $session->id)->status, 'still in the review queue');

        session_manager::submit_review((int) $session->id, (int) get_admin()->id, 'warn', 'Four pastes.');
        $messages->close();
        $this->setUser($learner);
        $this->assert_both_refused((int) $session->id, 'reviewed session');
        $this->assertSame('reviewed', $this->reload((int) $session->id)->status);
    }

    public function test_a_closed_or_active_session_without_an_attempt_is_not_reopened(): void {
        global $DB;
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        // A session of the old browser flow (no attempt bound), in each state the server or a reviewer leaves.
        $session = session_manager::start_session((int) $learner->id, (int) $quiz->id);
        foreach ([quiz_integration::ACTIVE, 'finished', 'flagged', 'reviewed'] as $status) {
            $DB->set_field('local_sentientia_proctor_sessions', 'status', $status, ['id' => $session->id]);
            $this->assert_both_refused((int) $session->id, 'no attempt, status ' . $status);
        }
    }

    public function test_the_pending_session_of_the_pre_start_check_is_not_reset(): void {
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $pending = quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);

        $this->assert_both_refused((int) $pending->id, 'pending session');
        $this->assertTrue(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, null),
            'the consent from the pre-start check still counts for the attempt about to start');
    }

    public function test_an_unstarted_session_of_the_old_flow_still_works_for_its_owner_only(): void {
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $session = session_manager::start_session((int) $learner->id, (int) $quiz->id);
        $this->assertSame('new', $session->status);

        $this->setUser($other);
        $this->assert_both_refused((int) $session->id, 'someone else\'s session');

        $this->setUser($learner);
        $this->assertSame('consenting', give_consent::execute((int) $session->id)['status']);
        $this->assertSame('consenting', give_consent::execute((int) $session->id)['status'], 'consenting again is fine');
        $result = submit_identity::execute((int) $session->id, $this->photo(), $this->photo());
        $this->assertTrue($result['passed'], 'the mock provider passes');
        $this->assertSame(quiz_integration::ACTIVE, $this->reload((int) $session->id)->status);
    }

    public function test_upload_chunk_is_refused_while_recording_is_off(): void {
        global $DB;
        $quiz = $this->quiz();
        [, , $session] = $this->proctored_attempt($quiz);
        $key = 'proctoring/' . $session->id . '/webcam-0.webm';
        $this->assertFalse(feature_flags::is_enabled(quiz_integration::RECORDING_FLAG), 'default OFF');

        $this->assert_refused(fn() => upload_chunk::execute((int) $session->id, 'webcam', 0, $key, 1024, 5000),
            'error_recording_disabled');
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_recordings'));

        // With recording switched on (B4) the call reaches register_chunk and its own checks again.
        feature_flags::set(quiz_integration::RECORDING_FLAG, 0, true, (int) get_admin()->id);
        $this->assertTrue(feature_flags::is_enabled(quiz_integration::RECORDING_FLAG));
        $chunkid = upload_chunk::execute((int) $session->id, 'webcam', 0, $key, 1024, 5000)['chunkid'];
        $this->assertGreaterThan(0, $chunkid);
        $this->assertSame($key, $DB->get_field('local_sentientia_proctor_recordings', 's3_key', ['id' => $chunkid]));
    }
}
