<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\event;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_proctoring\external\flag_session;
use local_sentientia_proctoring\session_manager;

/**
 * Decision A5 (2026-11-12): a proctored session that becomes flagged triggers session_flagged, the event
 * \local_sentientia_platform\audit_log::SENSITIVE_EVENTS lists, once, after the flag is stored: from
 * session_manager::finalize() when the analyzer's verdict is not clean (source auto), and from the flag_session web
 * service when a reviewer flags a session that was not flagged (source manual).
 *
 * Each test drives the real code path with the event sink redirected, so what is asserted is what the standard
 * logstore would have been handed.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\event\session_flagged
 * @covers     \local_sentientia_proctoring\session_manager::finalize
 * @covers     \local_sentientia_proctoring\session_manager::event_context
 * @covers     \local_sentientia_proctoring\external\flag_session
 *
 * @group local_sentientia_proctoring
 */
final class session_flagged_test extends \advanced_testcase {

    use \local_sentientia_org\test\bizlms_fixture;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ensure_bizlms_schema();
    }

    /**
     * A candidate at a tenant path, re-read so the record carries it.
     *
     * @param string $path
     * @return \stdClass
     */
    private function candidate(string $path = '/1/4'): \stdClass {
        global $DB;
        $u = $this->getDataGenerator()->create_user();
        $DB->set_field('user', 'open_path', $path, ['id' => $u->id]);
        return $DB->get_record('user', ['id' => $u->id], '*', MUST_EXIST);
    }

    /**
     * A quiz in a new course.
     *
     * @return \stdClass the quiz instance, with cmid
     */
    private function quiz(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        return $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
    }

    /**
     * A session the candidate started on the quiz, as session_manager::start_session() leaves it.
     *
     * @param \stdClass $candidate
     * @param int $quizid
     * @return \stdClass
     */
    private function started_session(\stdClass $candidate, int $quizid): \stdClass {
        $this->setUser($candidate);
        return session_manager::start_session((int) $candidate->id, $quizid);
    }

    /**
     * Run $fn with events and messages redirected and return the session_flagged events it triggered.
     *
     * @param callable $fn
     * @return session_flagged[]
     */
    private function flagged_events(callable $fn): array {
        $messages = $this->redirectMessages();
        $sink = $this->redirectEvents();
        try {
            $fn();
        } finally {
            $events = $sink->get_events();
            $sink->close();
            $messages->close();
        }
        return array_values(array_filter($events, fn($e) => $e instanceof session_flagged));
    }

    /**
     * Record enough behavioural events, as the candidate, for the analyzer to fail the session (3 x 25 = 75).
     *
     * @param int $sessionid
     * @return void
     */
    private function cheat(int $sessionid): void {
        for ($i = 0; $i < 3; $i++) {
            session_manager::record_event($sessionid, 'multiple_faces', 'critical');
        }
    }

    public function test_an_analyzer_flag_triggers_session_flagged_once_in_the_quiz_context(): void {
        global $DB;
        $candidate = $this->candidate();
        $quiz = $this->quiz();
        $session = $this->started_session($candidate, (int) $quiz->id);
        $this->cheat((int) $session->id);

        $events = $this->flagged_events(fn() => session_manager::finalize((int) $session->id));

        $this->assertCount(1, $events, 'one flag, one audit event');
        $event = $events[0];
        $this->assertSame('flagged', $DB->get_field('local_sentientia_proctor_sessions', 'status',
            ['id' => $session->id]), 'the event follows the stored flag');
        $this->assertSame((int) $session->id, (int) $event->objectid);
        $this->assertSame('local_sentientia_proctor_sessions', $event->objecttable);
        $this->assertSame((int) $candidate->id, (int) $event->relateduserid);
        $this->assertSame((int) $candidate->id, (int) $event->userid, 'the candidate finished the attempt');
        $this->assertSame(\context_module::instance((int) $quiz->cmid)->id, (int) $event->contextid,
            'the most specific real context: the quiz');
        $this->assertSame((int) $quiz->course, (int) $event->courseid);
        $this->assertSame('u', $event->crud);
        $this->assertSame(\core\event\base::LEVEL_OTHER, (int) $event->edulevel);
        $this->assertSame(['quizid' => (int) $quiz->id, 'source' => 'auto'], $event->other,
            'ids and codes only: no risk score');
        $this->assertStringContainsString("'{$session->id}'", $event->get_description());
        $this->assertSame('Proctored session flagged for review', session_flagged::get_name());
        $this->assertSame('/local/sentientia_proctoring/attempt.php?id=' . $session->id,
            $event->get_url()->out_as_local_url(false));
        $this->assertEventContextNotUsed($event);
        $this->assertDebuggingNotCalled();
    }

    public function test_a_clean_finish_and_a_finalize_replay_trigger_nothing(): void {
        $quiz = $this->quiz();

        $clean = $this->started_session($this->candidate(), (int) $quiz->id);
        $this->assertSame([], $this->flagged_events(fn() => session_manager::finalize((int) $clean->id)),
            'a clean verdict is not a flag');

        $flagged = $this->started_session($this->candidate(), (int) $quiz->id);
        $this->cheat((int) $flagged->id);
        $this->assertCount(1, $this->flagged_events(fn() => session_manager::finalize((int) $flagged->id)));
        $this->assertSame([], $this->flagged_events(fn() => session_manager::finalize((int) $flagged->id)),
            'finalize() on a flagged session returns it unchanged');
    }

    public function test_finalize_by_someone_else_triggers_nothing(): void {
        $quiz = $this->quiz();
        $session = $this->started_session($this->candidate(), (int) $quiz->id);
        $this->cheat((int) $session->id);

        $this->setUser($this->candidate());
        $events = $this->flagged_events(function() use ($session): void {
            try {
                session_manager::finalize((int) $session->id);
                $this->fail('only the candidate may finalize their session');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_session_state', $e->errorcode);
            }
        });
        $this->assertSame([], $events);
    }

    public function test_a_reviewer_flag_triggers_session_flagged_once_and_a_reflag_nothing(): void {
        global $DB;
        $candidate = $this->candidate();
        $quiz = $this->quiz();
        $session = $this->started_session($candidate, (int) $quiz->id);
        session_manager::finalize((int) $session->id);  // clean: finished, not flagged
        $this->assertSame('finished', $DB->get_field('local_sentientia_proctor_sessions', 'status',
            ['id' => $session->id]));

        $this->setAdminUser();
        $events = $this->flagged_events(fn() => $this->assertTrue(flag_session::execute((int) $session->id)['success']));

        $this->assertCount(1, $events);
        $this->assertSame((int) $session->id, (int) $events[0]->objectid);
        $this->assertSame((int) $candidate->id, (int) $events[0]->relateduserid);
        $this->assertSame((int) get_admin()->id, (int) $events[0]->userid, 'the reviewer flagged it');
        $this->assertSame('manual', $events[0]->other['source']);
        $this->assertSame(\context_module::instance((int) $quiz->cmid)->id, (int) $events[0]->contextid);

        $this->assertSame([], $this->flagged_events(fn() => flag_session::execute((int) $session->id)),
            'flagging a flagged session changes nothing, so it logs nothing');
    }

    public function test_a_refused_reviewer_flag_triggers_nothing(): void {
        global $DB;
        $quiz = $this->quiz();
        $session = $this->started_session($this->candidate('/177'), (int) $quiz->id);

        // A /1 tenant admin (manager archetype: holds :review) may not flag a /177 session.
        $reviewer = $this->candidate('/1');
        $managerid = (int) $DB->get_field('role', 'id', ['shortname' => 'manager'], MUST_EXIST);
        role_assign($managerid, $reviewer->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($reviewer);

        $events = $this->flagged_events(function() use ($session): void {
            try {
                flag_session::execute((int) $session->id);
                $this->fail('ADR-031: a reviewer flags only in their own tenant');
            } catch (\moodle_exception $e) {
                $this->assertSame('error_outoftenant', $e->errorcode);
            }
        });

        $this->assertSame([], $events);
        $this->assertSame('new', $DB->get_field('local_sentientia_proctor_sessions', 'status', ['id' => $session->id]));
    }

    public function test_a_session_with_no_quiz_is_logged_in_the_system_context(): void {
        $session = $this->started_session($this->candidate(), 0);
        $this->cheat((int) $session->id);

        $events = $this->flagged_events(fn() => session_manager::finalize((int) $session->id));

        $this->assertCount(1, $events);
        $this->assertSame(\context_system::instance()->id, (int) $events[0]->contextid);
        $this->assertSame(0, $events[0]->other['quizid']);
    }

    public function test_a_session_whose_quiz_was_deleted_is_logged_in_the_system_context(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');

        $candidate = $this->candidate();
        $quiz = $this->quiz();
        $session = $this->started_session($candidate, (int) $quiz->id);
        $this->cheat((int) $session->id);

        // The quiz is deleted while the attempt is open. No recycle-bin copy, so the module and its context are gone
        // and event_context() takes its get_coursemodule_from_instance() === false branch.
        set_config('coursebinenable', 0, 'tool_recyclebin');
        $this->setAdminUser();
        course_delete_module((int) $quiz->cmid);
        $this->assertFalse(get_coursemodule_from_instance('quiz', (int) $quiz->id, 0, false, IGNORE_MISSING));
        $this->setUser($candidate);

        $events = $this->flagged_events(fn() => session_manager::finalize((int) $session->id));

        $this->assertCount(1, $events);
        $this->assertSame(\context_system::instance()->id, (int) $events[0]->contextid,
            'the quiz context no longer exists, so the system context');
        $this->assertSame((int) $quiz->id, $events[0]->other['quizid'], 'the id of the deleted quiz is still logged');
        $this->assertSame((int) $candidate->id, (int) $events[0]->relateduserid);
    }
}
