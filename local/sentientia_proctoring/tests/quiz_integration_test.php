<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use local_sentientia_proctoring\external\report_event;
use mod_quiz\quiz_attempt;

/**
 * B1 "Fix the foundation" (ADR-038 D2, 2026-11-20), server side: the attempt observers close the session, the browser
 * cannot close it early, the monitor's web service takes only its own events on an open attempt, and the supporting
 * fixes (flag registry, purge credentials, privacy export, notifications in the recipient's language).
 *
 * The flag is not switched on here: quiz_integration is driven directly, the way the access rule drives it (the
 * rule's own tests are quizaccess_sentientia_proctoring\preflight_test). The observers are external, so the tests do
 * not run inside the rollback transaction.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\quiz_integration
 * @covers     \local_sentientia_proctoring\observer
 * @covers     \local_sentientia_proctoring\session_manager::finalize
 * @covers     \local_sentientia_proctoring\session_manager::close_session
 * @covers     \local_sentientia_proctoring\external\report_event
 * @covers     \local_sentientia_proctoring\task\purge_old_recordings::credentials
 * @covers     \local_sentientia_proctoring\privacy\provider
 * @covers     \local_sentientia_proctoring\notifier
 */
final class quiz_integration_test extends \advanced_testcase {

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
     * A learner who passed the pre-start check and started an attempt: [learner, attempt, session].
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
     * The event types recorded on a session, in order.
     *
     * @param int $sessionid
     * @return string[]
     */
    private function event_types(int $sessionid): array {
        global $DB;
        return array_values($DB->get_fieldset_select('local_sentientia_proctor_events', 'event_type',
            'sessionid = :s ORDER BY id', ['s' => $sessionid]));
    }

    public function test_all_five_plan_flags_are_registered_default_off(): void {
        $registry = \local_sentientia_platform\feature_flags::load_registry();
        foreach ([quiz_integration::FLAG, 'sentientia.assessment.secure_mode.enabled',
                'sentientia.assessment.seb_preset.enabled', 'sentientia.proctoring.recording.enabled',
                'sentientia.assessment.candidates.enabled'] as $key) {
            $this->assertArrayHasKey($key, $registry, $key);
            $this->assertFalse($registry[$key]['default'], $key . ' defaults OFF');
        }
        $this->assertFalse(quiz_integration::enabled());
    }

    public function test_the_browser_cannot_close_a_session_while_its_attempt_is_open(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->proctored_attempt($quiz);

        $returned = session_manager::finalize((int) $session->id);
        $this->assertSame(quiz_integration::ACTIVE, $returned->status);
        $this->assertSame(quiz_integration::ACTIVE, $this->reload((int) $session->id)->status,
            'a learner could otherwise close it clean and then cheat unrecorded');

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame('finished', $this->reload((int) $session->id)->status, 'the observer closed it');
        // Now the old browser call is harmless: the session is closed and is not scored again.
        $this->assertSame('finished', session_manager::finalize((int) $session->id)->status);
        $this->assertSame(1, count(array_keys($this->event_types((int) $session->id), 'session_end')));
    }

    public function test_an_abandoned_attempt_closes_its_session(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->proctored_attempt($quiz);

        quiz_attempt::create((int) $attempt->id)->process_abandon(time(), false);

        $session = $this->reload((int) $session->id);
        $this->assertSame('finished', $session->status);
        $this->assertContains('attempt_abandoned', $this->event_types((int) $session->id));
    }

    public function test_an_overdue_attempt_keeps_its_session_open_until_it_is_submitted(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->proctored_attempt($quiz);
        $sink = $this->redirectMessages();  // the quiz's own overdue message

        quiz_attempt::create((int) $attempt->id)->process_going_overdue(time(), false);
        $this->assertSame(quiz_integration::ACTIVE, $this->reload((int) $session->id)->status,
            'in the grace period the learner can still submit');
        $this->assertContains('attempt_overdue', $this->event_types((int) $session->id));

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $sink->close();
        $this->assertSame('finished', $this->reload((int) $session->id)->status);
    }

    public function test_a_flagged_attempt_goes_to_review_from_the_server(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->proctored_attempt($quiz);
        // Enough monitor events for a warn verdict: 4 pastes x 10 = 40.
        for ($i = 0; $i < 4; $i++) {
            report_event::execute((int) $session->id, 'clipboard_paste');
        }
        $messages = $this->redirectMessages();
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $sent = $messages->get_messages();
        $messages->close();

        $session = $this->reload((int) $session->id);
        $this->assertSame('flagged', $session->status);
        $this->assertSame('warn', $session->auto_decision);
        $flagged = array_filter($sent, fn($m) => $m->eventtype === 'session_flagged');
        $this->assertCount(1, $flagged, 'the default reviewer (the site admin) is told, by the server');
        $this->assertEquals(get_admin()->id, reset($flagged)->useridto);
    }

    public function test_report_event_takes_only_the_monitors_events_with_the_servers_severity(): void {
        global $DB;
        $quiz = $this->quiz();
        [, , $session] = $this->proctored_attempt($quiz);

        $id = report_event::execute((int) $session->id, 'tab_switch', 'info', json_encode(['page' => 2, 'x' => 'y']))
            ['eventid'];
        $event = $DB->get_record('local_sentientia_proctor_events', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('warn', $event->severity, 'the server sets the severity');
        $this->assertSame(['page' => 2], json_decode($event->payload_json, true), 'only the page is kept');

        foreach (['multiple_faces', 'session_end', 'attempt_submitted', 'consent_given'] as $forged) {
            try {
                report_event::execute((int) $session->id, $forged);
                $this->fail($forged . ' must be refused from the browser');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_event_type', $e->errorcode, $forged);
            }
        }
    }

    public function test_report_event_refuses_someone_elses_session_and_a_closed_one(): void {
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->proctored_attempt($quiz);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assert_session_state(fn() => report_event::execute((int) $session->id, 'tab_switch'));

        $this->setUser($learner);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assert_session_state(fn() => report_event::execute((int) $session->id, 'tab_switch'));
    }

    public function test_report_event_stops_storing_at_the_cap(): void {
        global $DB;
        $quiz = $this->quiz();
        [, , $session] = $this->proctored_attempt($quiz);
        $existing = $DB->count_records('local_sentientia_proctor_events', ['sessionid' => $session->id]);
        $rows = [];
        for ($i = $existing; $i < report_event::MAX_EVENTS; $i++) {
            $rows[] = ['sessionid' => $session->id, 'event_type' => 'page_shown', 'severity' => 'info',
                'timecreated' => time()];
        }
        $DB->insert_records('local_sentientia_proctor_events', $rows);

        $this->assertSame(0, report_event::execute((int) $session->id, 'tab_switch')['eventid']);
        $this->assertSame(report_event::MAX_EVENTS,
            $DB->count_records('local_sentientia_proctor_events', ['sessionid' => $session->id]));
    }

    public function test_a_session_a_reviewer_flags_mid_attempt_does_not_trap_the_learner(): void {
        global $DB;
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->proctored_attempt($quiz);
        $DB->set_field('local_sentientia_proctor_sessions', 'status', 'flagged', ['id' => $session->id]);

        // No endless round trip through the pre-start check: the attempt carries on, unmonitored.
        $this->assertTrue(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, (int) $attempt->id));
        $this->assertFalse(quiz_integration::accepts_client_events($this->reload((int) $session->id)));
        // And the reviewer's flag is not overwritten when the attempt ends.
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame('flagged', $this->reload((int) $session->id)->status);
    }

    public function test_an_unproctored_attempt_is_left_alone(): void {
        global $DB;
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $quiz->id, (int) $learner->id);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_sessions'));
        $this->assertNull(quiz_integration::finalize_attempt((int) $attempt->id, (int) $learner->id,
            (int) $quiz->id, 'submitted'));
    }

    public function test_a_pending_session_is_never_claimed_by_another_quiz_or_learner(): void {
        $quiz = $this->quiz();
        $other = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $pending = quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);

        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $other->id, (int) $learner->id);
        $this->assertNull(quiz_integration::session_for_attempt((int) $learner->id, (int) $other->id,
            (int) $attempt->id));
        $this->assertNull(quiz_integration::session_for_attempt((int) $learner->id, (int) $quiz->id,
            (int) $attempt->id), 'the attempt belongs to another quiz');
        $this->assertNull($this->reload((int) $pending->id)->attemptid);
    }

    public function test_purge_reads_the_keys_settings_php_stores(): void {
        set_config('aws_key', 'AKIDEXAMPLE', 'local_sentientia_proctoring');
        set_config('aws_secret', 'secret-example', 'local_sentientia_proctoring');
        $this->assertSame(['AKIDEXAMPLE', 'secret-example'], task\purge_old_recordings::credentials());

        unset_config('aws_key', 'local_sentientia_proctoring');
        unset_config('aws_secret', 'local_sentientia_proctoring');
        set_config('aws_access_key', 'legacy-id', 'local_sentientia_proctoring');
        set_config('aws_secret_key', 'legacy-secret', 'local_sentientia_proctoring');
        $this->assertSame(['legacy-id', 'legacy-secret'], task\purge_old_recordings::credentials());
    }

    public function test_the_aws_face_match_provider_is_gone(): void {
        $this->assertInstanceOf(identity\mock_verifier::class, identity\verifier_factory::get('mock'));
        try {
            identity\verifier_factory::get('aws');
            $this->fail('decision D10: no automated ID-to-face matching');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_no_provider', $e->errorcode);
        }
    }

    public function test_the_export_shows_consent_and_every_event(): void {
        $quiz = $this->quiz();
        [$learner, , $session] = $this->proctored_attempt($quiz);
        report_event::execute((int) $session->id, 'tab_switch', 'info', json_encode(['page' => 1]));

        $contextlist = new \core_privacy\local\request\approved_contextlist($learner, 'local_sentientia_proctoring',
            [\context_system::instance()->id]);
        privacy\provider::export_user_data($contextlist);
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->get_data([get_string('pluginname', 'local_sentientia_proctoring')]);
        $exported = $data->proctored_sessions[0];
        $this->assertEquals($session->attemptid, $exported['attempt_id']);
        $this->assertNotEmpty($exported['consent_given_at']);
        $types = array_column($exported['events'], 'event');
        $this->assertContains('consent_given', $types);
        $this->assertContains('identity_confirmed', $types);
        $this->assertContains('tab_switch', $types);
        $this->assertSame(count($exported['events']), $exported['event_count']);
    }

    public function test_a_notification_is_written_in_the_recipients_language(): void {
        $learner = $this->getDataGenerator()->create_user(['lang' => 'en']);
        $sink = $this->redirectMessages();
        notifier::session_reviewed((object) ['userid' => $learner->id, 'human_decision' => 'clean']);
        $messages = $sink->get_messages();
        $sink->close();
        $this->assertCount(1, $messages);
        $this->assertSame(get_string('msg_reviewed_subject', 'local_sentientia_proctoring'), $messages[0]->subject);
        $this->assertStringContainsString('clean', $messages[0]->fullmessage);
    }

    /**
     * Assert the call is refused with error_session_state.
     *
     * @param callable $fn
     */
    private function assert_session_state(callable $fn): void {
        try {
            $fn();
            $this->fail('expected error_session_state');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_session_state', $e->errorcode);
        }
    }
}
