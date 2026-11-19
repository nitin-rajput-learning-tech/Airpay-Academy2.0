<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use core_external\external_api;
use local_sentientia_proctoring\external\integrity_report;
use local_sentientia_proctoring\external\secure_report;
use mod_quiz\quiz_attempt;

/**
 * B2 "Secure assessment mode" (ADR-038 D3 and D6, post-demo plan B2, 2026-11-20), server side: the policy fixed at
 * the pre-start check, violation counting (server time, 2-second de-duplication, 10-second focus grace), the rate
 * limit, the heartbeat gap, hiring auto-submit, employees flagged and never submitted, pages that never reported,
 * the two web services and the privacy export.
 *
 * secure_mode is driven directly with explicit server times, the way secure_report drives it; the access rule's own
 * tests are quizaccess_sentientia_proctoring\secure_level_test. The attempt observers are external, so the tests do
 * not run inside the rollback transaction.
 *
 * @package    local_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_proctoring\secure_mode
 * @covers     \local_sentientia_proctoring\quiz_integration::pass_preflight
 * @covers     \local_sentientia_proctoring\quiz_integration::preflight_done
 * @covers     \local_sentientia_proctoring\session_manager::close_session
 * @covers     \local_sentientia_proctoring\external\secure_report
 * @covers     \local_sentientia_proctoring\external\integrity_report
 * @covers     \local_sentientia_proctoring\privacy\provider
 */
final class secure_mode_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    /**
     * A quiz with one true/false question.
     *
     * @return \stdClass quiz instance with cmid and course
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
     * An enrolled learner who accepted the secure-mode rules and started an attempt: [learner, attempt, session].
     *
     * @param \stdClass $quiz
     * @param string $level
     * @param int $limit
     * @return array
     */
    private function secure_attempt(\stdClass $quiz, string $level = secure_mode::LEVEL_STANDARD, int $limit = 0): array {
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $quiz->course, 'student');
        $this->setUser($learner);
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null, ['level' => $level, 'limit' => $limit]);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $quiz->id, (int) $learner->id);
        $session = quiz_integration::session_for_attempt((int) $learner->id, (int) $quiz->id, (int) $attempt->id);
        $this->assertNotNull($session, 'the attempt claimed the session');
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
     * The events of a session of one type, oldest first.
     *
     * @param int $sessionid
     * @param string $type
     * @return \stdClass[]
     */
    private function events(int $sessionid, string $type): array {
        global $DB;
        return array_values($DB->get_records('local_sentientia_proctor_events',
            ['sessionid' => $sessionid, 'event_type' => $type], 'id'));
    }

    /**
     * Report events as the page would.
     *
     * @param \stdClass $session
     * @param \stdClass $attempt
     * @param string[] $types
     * @param int $now
     * @return array
     */
    private function report(\stdClass $session, \stdClass $attempt, array $types, int $now): array {
        $events = array_map(fn($type) => ['type' => $type, 'page' => 0], $types);
        return secure_mode::report($session, $attempt, $events, $now);
    }

    public function test_the_pre_start_check_fixes_the_policy_on_the_session(): void {
        $quiz = $this->quiz();
        [, , $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_STANDARD, 0);
        $this->assertSame(secure_mode::LEVEL_STANDARD, $session->securitylevel);
        $this->assertEquals(0, $session->violationlimit);
        $this->assertCount(1, $this->events((int) $session->id, 'secure_rules_accepted'));
        $this->assertCount(0, $this->events((int) $session->id, 'consent_given'), 'Standard asks for no consent');

        $quiz = $this->quiz();
        [, , $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_PROCTORED, 3);
        $this->assertSame(secure_mode::LEVEL_PROCTORED, $session->securitylevel);
        $this->assertEquals(3, $session->violationlimit);
        $this->assertCount(1, $this->events((int) $session->id, 'consent_given'), 'Proctored carries the B1 consent');
        $this->assertCount(1, $this->events((int) $session->id, 'identity_confirmed'));
        $accepted = json_decode($this->events((int) $session->id, 'secure_rules_accepted')[0]->payload_json, true);
        $this->assertSame(['notice' => secure_mode::NOTICE_VERSION, 'level' => 'proctored', 'limit' => 3], $accepted);
    }

    public function test_a_b1_session_must_accept_the_secure_rules_before_secure_mode_runs(): void {
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);
        $this->assertTrue(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, null));
        $this->assertFalse(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, null, true),
            'secure mode shows its own rules once');

        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null, ['level' => 'secure', 'limit' => 0]);
        $this->assertTrue(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, null, true));

        // The B1 check reusing that pending row drops the policy it did not show.
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);
        $this->assertFalse(quiz_integration::preflight_done((int) $learner->id, (int) $quiz->id, null, true));
    }

    public function test_violations_count_once_per_two_seconds_with_the_servers_time(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);

        // One switch fires several browser events: one violation.
        $state = $this->report($session, $attempt, ['tab_switch', 'window_blur', 'fullscreen_exit'], $t + 30);
        $this->assertSame(1, $state['counted']);
        $this->assertSame(1, $state['violations']);
        $this->assertSame(0, $state['limit']);
        $this->assertFalse($state['autosubmitted']);

        $this->assertSame(0, $this->report($session, $attempt, ['tab_switch'], $t + 31)['counted'], 'within 2 s');
        $this->assertSame(1, $this->report($session, $attempt, ['window_blur'], $t + 33)['counted']);
        $this->assertEquals(2, $this->reload((int) $session->id)->violations);

        $blur = $this->events((int) $session->id, 'window_blur');
        $this->assertEquals($t + 30, $blur[0]->timecreated, 'the server stamps the time');
        $this->assertSame(['page' => 0, 'counted' => 0, 'reason' => 'duplicate'], json_decode($blur[0]->payload_json, true));
        $this->assertSame(['page' => 0, 'counted' => 1], json_decode($blur[1]->payload_json, true));
        $this->assertSame('warn', $blur[1]->severity);
    }

    public function test_a_lost_focus_just_after_a_page_opens_is_not_counted(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 1, $t);
        $this->report($session, $attempt, ['page_shown'], $t);

        $this->assertSame(0, $this->report($session, $attempt, ['window_blur'], $t + 3)['counted']);
        $this->assertSame(['page' => 0, 'counted' => 0, 'reason' => 'grace'],
            json_decode($this->events((int) $session->id, 'window_blur')[0]->payload_json, true));
        // The grace is for a lost focus only (permission prompts and slow loads): leaving fullscreen counts.
        $this->assertSame(1, $this->report($session, $attempt, ['fullscreen_exit'], $t + 5)['counted']);
    }

    /**
     * B2 review: the grace excuses a lost focus only when it ends within the grace. A learner who clicks onto a second
     * monitor 3 seconds after a page opens and comes back after a minute has one violation, counted when the focus
     * comes back, even though the page kept beating in between.
     */
    public function test_a_lost_focus_that_outlasts_the_grace_counts_when_the_focus_comes_back(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);

        $this->assertSame(0, $this->report($session, $attempt, ['window_blur'], $t + 3)['counted'], 'in the grace');
        $this->assertSame(0, $this->report($session, $attempt, [], $t + 18)['counted'], 'a heartbeat counts nothing');
        $this->assertSame(0, $this->report($session, $attempt, [], $t + 33)['violations']);

        $state = $this->report($session, $attempt, ['window_focus'], $t + 60);
        $this->assertSame(1, $state['counted'], 'the lost focus lasted past the grace');
        $this->assertSame(1, $state['violations']);
        $blurs = $this->events((int) $session->id, 'window_blur');
        $this->assertCount(2, $blurs);
        $this->assertSame(['page' => 0, 'counted' => 0, 'reason' => 'grace'], json_decode($blurs[0]->payload_json, true));
        $this->assertSame(['page' => 0, 'counted' => 1, 'reason' => 'grace_exceeded'],
            json_decode($blurs[1]->payload_json, true));
        $this->assertEquals($t + 60, $blurs[1]->timecreated, 'counted at the server time the focus came back');
        $this->assertCount(1, $this->events((int) $session->id, 'window_focus'));

        // The same absence never counts twice.
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 70)['counted']);
        $this->assertEquals(1, $this->reload((int) $session->id)->violations);

        // An employee's counted violation sends the attempt to review.
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame('flagged', $this->reload((int) $session->id)->status);
    }

    /**
     * B2 review: when the grace is over and the focus is still lost, the page says so (window_still_away) and the
     * server counts it then, so an attempt that ends without the learner ever coming back still has the violation.
     */
    public function test_a_lost_focus_still_lost_after_the_grace_counts_when_the_page_says_so(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);
        $this->report($session, $attempt, ['window_blur'], $t + 4);

        $this->assertSame(0, $this->report($session, $attempt, [secure_mode::STILL_AWAY], $t + 9)['counted'],
            'still within the grace: nothing yet');
        $state = $this->report($session, $attempt, [secure_mode::STILL_AWAY], $t + 12);
        $this->assertSame(1, $state['counted']);
        $this->assertCount(0, $this->events((int) $session->id, secure_mode::STILL_AWAY), 'never stored itself');
        $blurs = $this->events((int) $session->id, 'window_blur');
        $this->assertSame('grace_exceeded', json_decode($blurs[1]->payload_json, true)['reason']);

        // The focus coming back later does not count the same absence again.
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 50)['counted']);
        $this->assertEquals(1, $this->reload((int) $session->id)->violations);
    }

    /**
     * B2 review: what the grace still excuses, and what never counts twice.
     */
    public function test_the_grace_still_excuses_a_short_lost_focus_and_never_counts_twice(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);

        // A permission prompt: lost at +2, back at +6. Nothing counts.
        $this->report($session, $attempt, ['window_blur'], $t + 2);
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 6)['counted']);

        // Lost and back in one report (the first send failed): over when the report arrives. Nothing counts later
        // either, whatever the page reports.
        $this->assertSame(0, $this->report($session, $attempt, ['window_blur', 'window_focus'], $t + 7)['counted']);
        $this->assertSame(0, $this->report($session, $attempt, [secure_mode::STILL_AWAY], $t + 12)['counted']);
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 40)['counted']);

        // A lost focus the server counted at once (after the grace by the server's clock, still in it by a slow
        // page's) is not counted again when that page reports it still lost.
        $this->assertSame(1, $this->report($session, $attempt, ['window_blur'], $t + 45)['counted']);
        $this->assertSame(0, $this->report($session, $attempt, [secure_mode::STILL_AWAY], $t + 49)['counted']);
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 90)['counted']);

        // A switch to another tab counted in the grace covers the lost focus that came with it.
        secure_mode::page_served($session, 1, $t + 100);
        $this->report($session, $attempt, ['window_blur'], $t + 103);
        $this->assertSame(1, $this->report($session, $attempt, ['tab_switch'], $t + 104)['counted']);
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 160)['counted']);

        // A new page sent while a lost focus was open (the learner came back and moved on; that report was lost)
        // closes it.
        secure_mode::page_served($session, 2, $t + 200);
        $this->report($session, $attempt, ['window_blur'], $t + 203);
        secure_mode::page_served($session, 3, $t + 220);
        $this->assertSame(0, $this->report($session, $attempt, ['window_focus'], $t + 260)['counted']);

        $this->assertEquals(2, $this->reload((int) $session->id)->violations);
    }

    public function test_only_the_pages_own_event_types_are_stored(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        $this->report($session, $attempt, ['session_end', 'consent_given', 'integrity_autosubmitted', 'evil', 'copy_blocked'],
            $t);
        foreach (['session_end', 'consent_given', 'integrity_autosubmitted', 'evil'] as $forged) {
            $this->assertCount(0, $this->events((int) $session->id, $forged), $forged);
        }
        $copy = $this->events((int) $session->id, 'copy_blocked');
        $this->assertCount(1, $copy);
        $this->assertSame('info', $copy[0]->severity, 'blocked actions are listed, not counted');
        $this->assertEquals(0, $this->reload((int) $session->id)->violations);
    }

    public function test_the_rate_limit_drops_events_but_still_counts_violations(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        for ($i = 0; $i < 4; $i++) {
            $this->report($session, $attempt, array_fill(0, secure_mode::MAX_BATCH, 'copy_blocked'), $t);
        }
        $this->assertLessThanOrEqual(secure_mode::RATE_MAX_EVENTS, count($this->events((int) $session->id, 'copy_blocked')));
        $this->assertCount(1, $this->events((int) $session->id, 'rate_limited'));

        $this->assertSame(1, $this->report($session, $attempt, ['tab_switch'], $t + 1)['counted'],
            'a flood cannot hide a violation');
        $this->assertCount(0, $this->events((int) $session->id, 'tab_switch'), 'but it is not stored while limited');
        $this->assertCount(1, $this->events((int) $session->id, 'rate_limited'), 'one marker per window');
    }

    public function test_a_silent_page_leaves_a_heartbeat_gap(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, [], $t + 10);
        $this->assertCount(0, $this->events((int) $session->id, 'heartbeat_gap'));

        $this->report($session, $attempt, [], $t + 100);
        $gaps = $this->events((int) $session->id, 'heartbeat_gap');
        $this->assertCount(1, $gaps);
        $this->assertSame(90, json_decode($gaps[0]->payload_json, true)['seconds']);

        // The script was blocked on the next page: the server sees it when it sends the page after that.
        secure_mode::page_served($session, 1, $t + 110);
        secure_mode::page_served($session, 2, $t + 400);
        $this->assertCount(2, $this->events((int) $session->id, 'heartbeat_gap'));
    }

    public function test_a_new_network_address_between_pages_is_listed_not_counted(): void {
        $quiz = $this->quiz();
        [, , $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t, '10.1.1.1');
        secure_mode::page_served($session, 1, $t + 5, '10.1.1.1');
        $this->assertCount(0, $this->events((int) $session->id, 'ip_changed'));

        secure_mode::page_served($session, 2, $t + 10, '172.16.0.9');
        $changed = $this->events((int) $session->id, 'ip_changed');
        $this->assertCount(1, $changed);
        $this->assertSame(['from' => '10.1.1.1', 'to' => '172.16.0.9'], json_decode($changed[0]->payload_json, true));
        $session = $this->reload((int) $session->id);
        $this->assertEquals(0, $session->violations);
        $this->assertFalse(secure_mode::needs_review($session), 'listed for the reviewer, not flagged on its own');
    }

    public function test_a_hiring_attempt_is_submitted_by_the_server_at_its_limit(): void {
        global $DB;
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_STANDARD, 3);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);

        $this->assertFalse($this->report($session, $attempt, ['tab_switch'], $t + 20)['autosubmitted']);
        $this->assertFalse($this->report($session, $attempt, ['fullscreen_exit'], $t + 30)['autosubmitted']);
        $state = $this->report($session, $attempt, ['window_blur'], $t + 40);
        $this->assertTrue($state['autosubmitted']);
        $this->assertSame(3, $state['violations']);

        $this->assertSame(quiz_attempt::FINISHED, $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->id]));
        $session = $this->reload((int) $session->id);
        $this->assertEquals($t + 40, $session->timeautosubmitted);
        $this->assertSame('flagged', $session->status, 'the observer closed it and sent it to a reviewer');
        $this->assertNotSame('clean', $session->auto_decision);
        $this->assertCount(1, $this->events((int) $session->id, 'integrity_autosubmitted'));

        // A late report from the same page changes nothing.
        $this->assertFalse(secure_mode::autosubmit($session, $attempt, $t + 41));
    }

    public function test_an_employee_is_warned_and_flagged_but_never_submitted(): void {
        global $DB;
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_SECURE, 0);
        $t = time();
        $this->report($session, $attempt, ['page_shown'], $t);
        for ($i = 1; $i <= 6; $i++) {
            $state = $this->report($session, $attempt, ['tab_switch'], $t + 20 * $i);
            $this->assertFalse($state['autosubmitted']);
        }
        $this->assertSame(6, $state['violations']);
        $this->assertSame(quiz_attempt::IN_PROGRESS, $DB->get_field('quiz_attempts', 'state', ['id' => $attempt->id]));

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $session = $this->reload((int) $session->id);
        $this->assertSame('flagged', $session->status);
        $this->assertSame('warn', $session->auto_decision);
        $this->assertNull($session->timeautosubmitted);
    }

    public function test_a_clean_secure_attempt_finishes_clean(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown', 'fullscreen_enter', 'copy_blocked'], $t);

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $session = $this->reload((int) $session->id);
        $this->assertSame('finished', $session->status);
        $this->assertSame('clean', $session->auto_decision);
    }

    public function test_pages_that_never_reported_send_the_attempt_to_review(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        secure_mode::page_served($session, 1, $t + 5);

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $session = $this->reload((int) $session->id);
        $missing = $this->events((int) $session->id, 'monitor_missing');
        $this->assertCount(1, $missing);
        $this->assertSame(['served' => 2, 'shown' => 0], json_decode($missing[0]->payload_json, true));
        $this->assertSame('flagged', $session->status);
    }

    /**
     * B2 review: an attempt the server never sent a page of (started and answered through the quiz web services, or
     * the browser closed before the first page) was never protected or monitored: it goes to review. A session
     * closed before any attempt claimed it has nothing to review.
     */
    public function test_an_attempt_the_server_never_sent_a_page_of_goes_to_review(): void {
        $quiz = $this->quiz();
        [, $attempt, $session] = $this->secure_attempt($quiz);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $session = $this->reload((int) $session->id);
        $missing = $this->events((int) $session->id, 'monitor_missing');
        $this->assertCount(1, $missing);
        $this->assertSame(['served' => 0, 'shown' => 0], json_decode($missing[0]->payload_json, true));
        $this->assertSame('flagged', $session->status);
        $this->assertSame('warn', $session->auto_decision, 'flagged for a reviewer, never failed');

        // A pending session the learner closed before starting: no attempt, nothing to review.
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        $pending = quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null,
            ['level' => secure_mode::LEVEL_STANDARD, 'limit' => 0]);
        session_manager::finalize((int) $pending->id);
        $pending = $this->reload((int) $pending->id);
        $this->assertSame('finished', $pending->status);
        $this->assertCount(0, $this->events((int) $pending->id, 'monitor_missing'));
    }

    public function test_a_b1_session_is_scored_exactly_as_before(): void {
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->setUser($learner);
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $quiz->id, (int) $learner->id);
        $session = quiz_integration::session_for_attempt((int) $learner->id, (int) $quiz->id, (int) $attempt->id);

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $session = $this->reload((int) $session->id);
        $this->assertNull($session->securitylevel);
        $this->assertSame('finished', $session->status);
        $this->assertCount(0, $this->events((int) $session->id, 'monitor_missing'));
    }

    public function test_the_report_web_service_takes_only_the_callers_open_secure_attempt(): void {
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->secure_attempt($quiz);

        $result = secure_report::execute((int) $attempt->id, [['type' => 'page_shown', 'page' => 0]]);
        $result = external_api::clean_returnvalue(secure_report::execute_returns(), $result);
        $this->assertFalse($result['closed']);
        $this->assertSame(0, $result['violations']);
        $this->assertCount(1, $this->events((int) $session->id, 'page_shown'));

        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $quiz->course, 'student');
        $this->setUser($other);
        try {
            secure_report::execute((int) $attempt->id, [['type' => 'tab_switch', 'page' => 0]]);
            $this->fail('another learner\'s attempt must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_session_state', $e->errorcode);
        }

        $this->setUser($learner);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $result = secure_report::execute((int) $attempt->id, []);
        $this->assertTrue($result['closed'], 'a submitted attempt tells the page to stop');
        $this->assertFalse($result['autosubmitted']);
    }

    public function test_the_report_web_service_refuses_an_attempt_without_a_secure_session(): void {
        $quiz = $this->quiz();
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $quiz->course, 'student');
        $this->setUser($learner);
        quiz_integration::pass_preflight((int) $learner->id, (int) $quiz->id, null);  // B1 only.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $attempt = $quizgenerator->create_attempt((int) $quiz->id, (int) $learner->id);

        $this->expectException(\moodle_exception::class);
        secure_report::execute((int) $attempt->id, [['type' => 'tab_switch', 'page' => 0]]);
    }

    public function test_the_integrity_report_is_for_teachers_of_the_sessions_tenant(): void {
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_STANDARD, 3);
        $t = time();
        secure_mode::page_served($session, 0, $t);
        $this->report($session, $attempt, ['page_shown'], $t);
        $this->report($session, $attempt, ['tab_switch'], $t + 20);
        $this->report($session, $attempt, ['window_blur'], $t + 21);

        // The learner: no mod/quiz:viewreports.
        try {
            integrity_report::execute((int) $attempt->id);
            $this->fail('the learner does not see the report');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        // A teacher with no tenant reaches no session (ADR-031); the review page is unaffected.
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $quiz->course, 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse(integrity_report::execute((int) $attempt->id)['available']);

        // The site admin (cross-tenant).
        $this->setAdminUser();
        $data = external_api::clean_returnvalue(integrity_report::execute_returns(),
            integrity_report::execute((int) $attempt->id));
        $this->assertTrue($data['available']);
        $this->assertSame(1, $data['violations']);
        $this->assertSame(3, $data['limit']);
        $this->assertTrue($data['haslimit']);
        $this->assertSame(get_string('securitylevel_standard', 'quizaccess_sentientia_proctoring'), $data['level']);
        $labels = array_column($data['timeline'], 'label');
        $this->assertContains(get_string('event_tab_switch', 'local_sentientia_proctoring'), $labels);
        $counted = array_values(array_filter($data['timeline'], fn($row) => $row['counted']));
        $this->assertCount(1, $counted);
        $notcounted = array_values(array_filter($data['timeline'], fn($row) => $row['notcounted']));
        $this->assertCount(1, $notcounted);
        $this->assertSame(get_string('report_notcounted_duplicate', 'local_sentientia_proctoring',
            secure_mode::DEDUP_SECONDS), $notcounted[0]['note']);
        $this->assertNotEmpty($data['reviewurl']);
    }

    public function test_the_export_lists_the_secure_mode_fields(): void {
        $quiz = $this->quiz();
        [$learner, $attempt, $session] = $this->secure_attempt($quiz, secure_mode::LEVEL_SECURE, 0);
        $t = time();
        $this->report($session, $attempt, ['page_shown'], $t);
        $this->report($session, $attempt, ['tab_switch'], $t + 30);

        $contextlist = new \core_privacy\local\request\approved_contextlist($learner, 'local_sentientia_proctoring',
            [\context_system::instance()->id]);
        privacy\provider::export_user_data($contextlist);
        $data = \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->get_data([get_string('pluginname', 'local_sentientia_proctoring')]);
        $exported = $data->proctored_sessions[0];
        $this->assertSame('secure', $exported['security_level']);
        $this->assertSame(1, $exported['violations_counted']);
        $this->assertNotEmpty($exported['last_report_at']);
        $this->assertContains('tab_switch', array_column($exported['events'], 'event'));

        $metadata = privacy\provider::get_metadata(new \core_privacy\local\metadata\collection('local_sentientia_proctoring'));
        foreach ($metadata->get_collection() as $item) {
            if ($item->get_name() === 'local_sentientia_proctor_sessions') {
                $this->assertArrayHasKey('violations', $item->get_privacy_fields());
                $this->assertArrayHasKey('lastheartbeat', $item->get_privacy_fields());
            }
        }
    }

    public function test_purpose_sets_the_lowest_level_and_only_hiring_auto_submits(): void {
        $this->assertSame('', secure_mode::min_level(''));
        $this->assertSame(secure_mode::LEVEL_STANDARD, secure_mode::min_level('compliance'));
        $this->assertSame(secure_mode::LEVEL_SECURE, secure_mode::min_level('skill'));
        $this->assertSame(secure_mode::LEVEL_PROCTORED, secure_mode::min_level('certification'));
        $this->assertSame(secure_mode::LEVEL_PROCTORED, secure_mode::min_level('hiring'));

        $this->assertSame(0, secure_mode::autosubmit_limit('skill', 3));
        $this->assertSame(3, secure_mode::autosubmit_limit('hiring', null));
        $this->assertSame(5, secure_mode::autosubmit_limit('hiring', 5));
        $this->assertSame(0, secure_mode::autosubmit_limit('hiring', 0));
        $this->assertSame(secure_mode::MAX_VIOLATION_LIMIT, secure_mode::autosubmit_limit('hiring', 99));

        $this->assertSame('', secure_mode::effective_level(null, false));
        $this->assertSame(secure_mode::LEVEL_PROCTORED, secure_mode::effective_level(null, true),
            'a B1 proctored quiz counts as Proctored');
        $this->assertSame(secure_mode::LEVEL_SECURE, secure_mode::effective_level('secure', true));
    }
}
