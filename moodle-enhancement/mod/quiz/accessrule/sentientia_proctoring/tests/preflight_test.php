<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/sentientia_proctoring/rule.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

use local_sentientia_platform\feature_flags;
use local_sentientia_proctoring\quiz_integration;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;

/**
 * B1 "Fix the foundation" (ADR-038 D2, 2026-11-20), driven through the quiz access rule on real quizzes and attempts.
 *
 * Before B1 a proctored quiz refused every attempt until a session was `recording`, and the consent flow that opened
 * one ran only on the attempt page, so every learner was locked out. These tests pin the replacement: the flag gates
 * the whole rule, consent and identity are pre-start check fields, the attempt claims the consented session, every
 * open-attempt page loads the monitor, and the server closes the session when the attempt ends.
 *
 * The attempt observers are external ('internal' => false): they run when no transaction is open, so the tests do not
 * run inside the rollback transaction.
 *
 * @package    quizaccess_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_sentientia_proctoring\rule
 * @covers     \local_sentientia_proctoring\quiz_integration
 * @covers     \local_sentientia_proctoring\observer
 */
final class preflight_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    /**
     * A quiz with one true/false question.
     *
     * @param bool $proctored store "Require proctoring" = Yes
     * @return \stdClass quiz instance with cmid
     */
    private function quiz(bool $proctored = true): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 1]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        if ($proctored) {
            rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_enabled' => 1]);
        }
        return $quiz;
    }

    /**
     * Switch the B1 flag globally.
     *
     * @param bool $on
     */
    private function flag(bool $on): void {
        feature_flags::set(quiz_integration::FLAG, 0, $on, (int) get_admin()->id);
    }

    /**
     * The rule as core builds it for the current user (null when it does not apply).
     *
     * @param \stdClass $quiz
     * @return rule|null
     */
    private function rule_for(\stdClass $quiz): ?rule {
        global $USER;
        $quizobj = quiz_settings::create((int) $quiz->id, (int) $USER->id);
        $rule = rule::make($quizobj, time(), false);
        return $rule instanceof rule ? $rule : null;
    }

    /**
     * A learner, logged in.
     *
     * @return \stdClass
     */
    private function learner(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        return $user;
    }

    /**
     * Start an attempt as the current user, the way startattempt.php does once the pre-start check passed.
     *
     * @param \stdClass $quiz
     * @return \stdClass quiz_attempts row
     */
    private function start_attempt(\stdClass $quiz): \stdClass {
        global $USER;
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        return $quizgenerator->create_attempt((int) $quiz->id, (int) $USER->id);
    }

    /**
     * The newest session of the user on the quiz.
     *
     * @param int $userid
     * @param int $quizid
     * @return \stdClass|null
     */
    private function session(int $userid, int $quizid): ?\stdClass {
        global $DB;
        $rows = $DB->get_records('local_sentientia_proctor_sessions', ['userid' => $userid, 'quizid' => $quizid],
            'id DESC', '*', 0, 1);
        return $rows ? reset($rows) : null;
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

    public function test_flag_off_means_a_stock_quiz(): void {
        global $DB;
        $quiz = $this->quiz();
        $this->learner();

        $this->assertNull($this->rule_for($quiz), 'flag OFF (the default): the rule does not apply');
        // The learner starts and submits as on any quiz; no session is opened.
        $attempt = $this->start_attempt($quiz);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_sessions'));
    }

    public function test_the_rule_applies_only_to_a_proctored_quiz_and_never_to_a_preview(): void {
        $this->flag(true);
        $plain = $this->quiz(false);
        $proctored = $this->quiz(true);

        $this->learner();
        $this->assertNull($this->rule_for($plain));
        $this->assertInstanceOf(rule::class, $this->rule_for($proctored));

        $this->setAdminUser();  // holds mod/quiz:preview
        $this->assertNull($this->rule_for($proctored), 'a teacher\'s or admin\'s preview is never proctored');
    }

    public function test_the_pre_start_check_needs_both_boxes(): void {
        $this->flag(true);
        $quiz = $this->quiz();
        $this->learner();
        $rule = $this->rule_for($quiz);

        $this->assertTrue($rule->is_preflight_check_required(null), 'the Start button opens the pre-start check');
        $errors = $rule->validate_preflight_check([], [], [], null);
        $this->assertArrayHasKey('sentientia_proctoring_consent', $errors);
        $this->assertArrayHasKey('sentientia_proctoring_identity', $errors);

        $errors = $rule->validate_preflight_check(['sentientia_proctoring_consent' => 1], [], [], null);
        $this->assertSame(['sentientia_proctoring_identity'], array_keys($errors));

        $this->assertSame([], $rule->validate_preflight_check(
            ['sentientia_proctoring_consent' => 1, 'sentientia_proctoring_identity' => 1], [], [], null));
    }

    public function test_the_pre_start_form_carries_the_notice_and_both_boxes(): void {
        $this->flag(true);
        $quiz = $this->quiz();
        $this->learner();
        $quizobj = quiz_settings::create((int) $quiz->id);
        $form = $quizobj->get_access_manager(time())->get_preflight_check_form($quizobj->start_attempt_url(), null);
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);
        foreach (['sentientia_proctoring_header', 'sentientia_proctoring_notice', 'sentientia_proctoring_consent',
                'sentientia_proctoring_identity'] as $name) {
            $this->assertTrue($mform->elementExists($name), $name);
        }
        $this->assertStringContainsString(s(fullname($GLOBALS['USER'])),
            $mform->getElement('sentientia_proctoring_identity')->getText());
    }

    public function test_passing_the_check_opens_a_session_the_attempt_then_claims(): void {
        $this->flag(true);
        $quiz = $this->quiz();
        $learner = $this->learner();
        $rule = $this->rule_for($quiz);

        // startattempt.php: the check passed for a new attempt (no attempt id yet).
        $rule->notify_preflight_check_passed(null);
        $session = $this->session((int) $learner->id, (int) $quiz->id);
        $this->assertNotNull($session);
        $this->assertNull($session->attemptid, 'pending until the attempt starts');
        $this->assertSame(quiz_integration::ACTIVE, $session->status);
        $this->assertGreaterThan(0, (int) $session->consent_given_at);
        $this->assertSame(['session_start', 'consent_given', 'identity_confirmed'],
            $this->event_types((int) $session->id));
        $this->assertFalse($rule->is_preflight_check_required(null), 'no second check within the hour');

        // The attempt starts: the attempt_started observer binds the session to it.
        $attempt = $this->start_attempt($quiz);
        $session = $this->session((int) $learner->id, (int) $quiz->id);
        $this->assertEquals($attempt->id, $session->attemptid);
        $this->assertContains('attempt_linked', $this->event_types((int) $session->id));

        // attempt.php: no redirect back to the check; the Start button never disappeared.
        $this->assertFalse($this->rule_for($quiz)->is_preflight_check_required((int) $attempt->id));
        $this->assertSame([], (new \mod_quiz\access_manager(quiz_settings::create((int) $quiz->id), time(), false))
            ->prevent_new_attempt(0, null), 'no lockout');
    }

    public function test_every_open_attempt_page_loads_the_monitor_and_the_review_page_does_not(): void {
        global $USER;
        $this->flag(true);
        $quiz = $this->quiz();
        $this->learner();
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);
        $session = $this->session((int) $USER->id, (int) $quiz->id);

        foreach (['/mod/quiz/attempt.php' => ['page' => 0], '/mod/quiz/summary.php' => []] as $script => $extra) {
            $page = new \moodle_page();
            $page->set_url($script, ['attempt' => $attempt->id, 'cmid' => $quiz->cmid] + $extra);
            $this->rule_for($quiz)->setup_attempt_page($page);
            $code = $this->amd_code($page);
            $this->assertStringContainsString('local_sentientia_proctoring/proctor', $code, $script);
            $this->assertStringContainsString('"sessionid":' . (int) $session->id, $code, $script);
        }

        // A second page of the attempt: the monitor starts again there.
        $page = new \moodle_page();
        $page->set_url('/mod/quiz/attempt.php', ['attempt' => $attempt->id, 'cmid' => $quiz->cmid, 'page' => 1]);
        $this->rule_for($quiz)->setup_attempt_page($page);
        $this->assertStringContainsString('"page":1', $this->amd_code($page));

        // Submitted: review.php loads nothing.
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $page = new \moodle_page();
        $page->set_url('/mod/quiz/review.php', ['attempt' => $attempt->id]);
        $this->rule_for($quiz)->setup_attempt_page($page);
        $this->assertStringNotContainsString('local_sentientia_proctoring/proctor', $this->amd_code($page));
    }

    public function test_another_learners_attempt_page_loads_nothing(): void {
        $this->flag(true);
        $quiz = $this->quiz();
        $this->learner();
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        $this->learner();
        $page = new \moodle_page();
        $page->set_url('/mod/quiz/attempt.php', ['attempt' => $attempt->id, 'cmid' => $quiz->cmid]);
        $this->rule_for($quiz)->setup_attempt_page($page);
        $this->assertStringNotContainsString('local_sentientia_proctoring/proctor', $this->amd_code($page));
    }

    public function test_submitting_closes_the_session_on_the_server(): void {
        $this->flag(true);
        $quiz = $this->quiz();
        $learner = $this->learner();
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);

        $session = $this->session((int) $learner->id, (int) $quiz->id);
        $this->assertSame('finished', $session->status, 'no browser call: the observer closed it');
        $this->assertGreaterThan(0, (int) $session->timefinished);
        $this->assertSame('clean', $session->auto_decision);
        $types = $this->event_types((int) $session->id);
        $this->assertContains('attempt_submitted', $types);
        $this->assertSame('session_end', end($types));
    }

    public function test_a_stale_consent_is_asked_again_and_then_bound_to_the_attempt(): void {
        global $DB;
        $this->flag(true);
        $quiz = $this->quiz();
        $learner = $this->learner();
        $rule = $this->rule_for($quiz);
        $rule->notify_preflight_check_passed(null);
        $pending = $this->session((int) $learner->id, (int) $quiz->id);
        $DB->set_field('local_sentientia_proctor_sessions', 'consent_given_at',
            time() - quiz_integration::PENDING_TTL - 1, ['id' => $pending->id]);

        $this->assertTrue($rule->is_preflight_check_required(null));
        $attempt = $this->start_attempt($quiz);
        $this->assertNull($DB->get_field('local_sentientia_proctor_sessions', 'attemptid', ['id' => $pending->id]),
            'an old consent is not claimed');

        // attempt.php sends the learner back to the check for this attempt; passing it binds the same row.
        $rule = $this->rule_for($quiz);
        $this->assertTrue($rule->is_preflight_check_required((int) $attempt->id));
        $rule->notify_preflight_check_passed((int) $attempt->id);
        $this->assertEquals($attempt->id,
            $DB->get_field('local_sentientia_proctor_sessions', 'attemptid', ['id' => $pending->id]));
        $this->assertFalse($rule->is_preflight_check_required((int) $attempt->id));
        $this->assertSame(1, $DB->count_records('local_sentientia_proctor_sessions', ['userid' => $learner->id]));
    }

    public function test_notify_does_nothing_when_this_rules_check_was_not_part_of_the_form(): void {
        global $DB;
        $this->flag(true);
        $quiz = $this->quiz();
        $learner = $this->learner();
        $rule = $this->rule_for($quiz);
        $rule->notify_preflight_check_passed(null);
        $before = $DB->count_records('local_sentientia_proctor_events');

        // Another rule's check (a quiz password, say) passes; ours was already done.
        $rule->notify_preflight_check_passed(null);
        $this->assertSame($before, $DB->count_records('local_sentientia_proctor_events'));
        $this->assertSame(1, $DB->count_records('local_sentientia_proctor_sessions', ['userid' => $learner->id]));
    }

    /**
     * The AMD calls queued on a page.
     *
     * @param \moodle_page $page
     * @return string
     */
    private function amd_code(\moodle_page $page): string {
        $property = new \ReflectionProperty($page->requires, 'amdjscode');
        return implode("\n", $property->getValue($page->requires));
    }
}
