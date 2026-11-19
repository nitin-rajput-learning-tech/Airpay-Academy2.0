<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/sentientia_proctoring/rule.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/mod_form.php');

use core_external\external_api;
use local_sentientia_platform\feature_flags;
use local_sentientia_proctoring\quiz_integration;
use local_sentientia_proctoring\secure_mode;
use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use mod_quiz_external;

/**
 * B2 "Secure assessment mode" (ADR-038 D1, D3 and D6, 2026-11-20), driven through the quiz access rule on real
 * quizzes and attempts: the settings (level, purpose, limit) and their validation, the four modes make() picks with
 * the two flags (B1 proctoring unchanged with secure mode OFF, fail closed for Secure and Proctored), the pre-start
 * check in secure mode, and what the attempt and review pages load.
 *
 * The attempt observers are external ('internal' => false): they run when no transaction is open, so the tests do not
 * run inside the rollback transaction.
 *
 * @package    quizaccess_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \quizaccess_sentientia_proctoring\rule
 * @covers     \local_sentientia_proctoring\secure_mode
 */
final class secure_level_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->preventResetByRollback();
    }

    protected function tearDown(): void {
        rule::set_web_service_request_for_testing(null);
        parent::tearDown();
    }

    /**
     * A quiz with one true/false question and the given B2 settings.
     *
     * @param string $level '' for none
     * @param string $purpose
     * @param int $limit
     * @return \stdClass quiz instance with cmid and course
     */
    private function quiz(string $level = 'standard', string $purpose = '', int $limit = 3): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance(['course' => $course->id, 'questionsperpage' => 0,
            'grade' => 100.0, 'sumgrades' => 1]);
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_level' => $level,
            'sentientia_proctoring_purpose' => $purpose, 'sentientia_proctoring_violationlimit' => $limit]);
        return $quiz;
    }

    /**
     * Switch a flag globally.
     *
     * @param string $key
     * @param bool $on
     */
    private function flag(string $key, bool $on): void {
        feature_flags::set($key, 0, $on, (int) get_admin()->id);
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
     * An enrolled learner, logged in.
     *
     * @param \stdClass $quiz
     * @param array $record user fields
     * @return \stdClass
     */
    private function learner(\stdClass $quiz, array $record = []): \stdClass {
        $user = $this->getDataGenerator()->create_user($record);
        $this->getDataGenerator()->enrol_user($user->id, $quiz->course, 'student');
        $this->setUser($user);
        return $user;
    }

    /**
     * Start an attempt as the current user.
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
     * The AMD calls queued on a page.
     *
     * @param \moodle_page $page
     * @return string
     */
    private function amd_code(\moodle_page $page): string {
        $property = new \ReflectionProperty($page->requires, 'amdjscode');
        return implode("\n", $property->getValue($page->requires));
    }

    /**
     * Run the rule's settings validation as the quiz form does.
     *
     * @param array $data
     * @return array errors
     */
    private function validate(array $data): array {
        $form = $this->getMockBuilder(\mod_quiz_mod_form::class)->disableOriginalConstructor()->getMock();
        return rule::validate_settings_form_fields([], $data, [], $form);
    }

    public function test_the_settings_are_stored_and_loaded_with_the_quiz(): void {
        global $DB;
        $quiz = $this->quiz('secure', 'skill', 5);
        $row = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => $quiz->id], '*', MUST_EXIST);
        $this->assertSame('secure', $row->securitylevel);
        $this->assertSame('skill', $row->purpose);
        $this->assertEquals(5, $row->violationlimit);
        $this->assertEquals(0, $row->enabled, '"Require proctoring" follows the level');

        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_level' => 'proctored',
            'sentientia_proctoring_purpose' => 'hiring', 'sentientia_proctoring_violationlimit' => 3]);
        $this->assertEquals(1, $DB->get_field('quizaccess_sentientia_proctor', 'enabled', ['quizid' => $quiz->id]));

        $settings = \mod_quiz\access_manager::load_settings((int) $quiz->id);
        $this->assertSame('proctored', $settings['sentientia_proctoring_level']);
        $this->assertSame('hiring', $settings['sentientia_proctoring_purpose']);
        $this->assertEquals(3, $settings['sentientia_proctoring_violationlimit']);

        // B1's setting alone (secure mode OFF for the editor): No clears Proctored.
        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_enabled' => 0]);
        $this->assertNull($DB->get_field('quizaccess_sentientia_proctor', 'securitylevel', ['quizid' => $quiz->id]));
    }

    public function test_a_b1_proctored_quiz_reads_as_proctored(): void {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_enabled' => 1]);
        $settings = \mod_quiz\access_manager::load_settings((int) $quiz->id);
        $this->assertSame('proctored', $settings['sentientia_proctoring_level'], 'ADR-038 D1: the old setting maps');
        $this->assertSame('', $settings['sentientia_proctoring_setlevel']);
        $this->assertEquals(secure_mode::DEFAULT_VIOLATION_LIMIT, $settings['sentientia_proctoring_violationlimit']);

        $plain = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $settings = \mod_quiz\access_manager::load_settings((int) $plain->id);
        $this->assertSame('', $settings['sentientia_proctoring_level']);
    }

    public function test_the_purpose_sets_the_lowest_level_except_for_a_manager(): void {
        $this->flag(quiz_integration::FLAG, true);
        $this->setUser($this->getDataGenerator()->create_user());
        $errors = $this->validate(['sentientia_proctoring_level' => 'standard', 'sentientia_proctoring_purpose' => 'hiring',
            'sentientia_proctoring_violationlimit' => 3]);
        $this->assertArrayHasKey('sentientia_proctoring_level', $errors);
        $this->assertSame([], $this->validate(['sentientia_proctoring_level' => 'proctored',
            'sentientia_proctoring_purpose' => 'hiring', 'sentientia_proctoring_violationlimit' => 3]));
        $this->assertSame([], $this->validate(['sentientia_proctoring_level' => 'secure',
            'sentientia_proctoring_purpose' => 'skill']));

        $errors = $this->validate(['sentientia_proctoring_level' => 'proctored', 'sentientia_proctoring_purpose' => 'hiring',
            'sentientia_proctoring_violationlimit' => 25]);
        $this->assertArrayHasKey('sentientia_proctoring_violationlimit', $errors);

        $this->setAdminUser();  // Holds local/sentientia_proctoring:manage.
        $this->assertSame([], $this->validate(['sentientia_proctoring_level' => 'standard',
            'sentientia_proctoring_purpose' => 'hiring', 'sentientia_proctoring_violationlimit' => 0]));
    }

    public function test_proctored_needs_proctoring_switched_on(): void {
        $this->setAdminUser();
        $errors = $this->validate(['sentientia_proctoring_level' => 'proctored', 'sentientia_proctoring_purpose' => '']);
        $this->assertSame(get_string('err_proctored_needs_flag', 'quizaccess_sentientia_proctoring'),
            $errors['sentientia_proctoring_level']);
    }

    public function test_secure_mode_off_leaves_standard_stock_and_b1_unchanged(): void {
        $standard = $this->quiz('standard');
        $this->learner($standard);
        $this->assertNull($this->rule_for($standard), 'Standard with secure mode OFF is a stock quiz');

        // A B1 proctored quiz with B1 ON: the B1 rule, exactly.
        $this->flag(quiz_integration::FLAG, true);
        $b1 = $this->quiz('');
        rule::save_settings((object) ['id' => $b1->id, 'sentientia_proctoring_enabled' => 1]);
        $this->learner($b1);
        $rule = $this->rule_for($b1);
        $this->assertInstanceOf(rule::class, $rule);
        $this->assertSame(get_string('description', 'quizaccess_sentientia_proctoring'), $rule->description());
        $this->assertFalse($rule->prevent_access());
    }

    public function test_secure_and_proctored_fail_closed(): void {
        $secure = $this->quiz('secure');
        $this->learner($secure);
        $rule = $this->rule_for($secure);
        $this->assertSame(get_string('blocked_securemode_off', 'quizaccess_sentientia_proctoring'), $rule->prevent_access());
        $this->assertFalse($rule->is_preflight_check_required(null));

        $this->flag(secure_mode::FLAG, true);
        $proctored = $this->quiz('proctored', 'hiring');
        $this->learner($proctored);
        $this->assertSame(get_string('blocked_proctoring_off', 'quizaccess_sentientia_proctoring'),
            $this->rule_for($proctored)->prevent_access(), 'Proctored never runs as Secure');
        $messages = (new \mod_quiz\access_manager(quiz_settings::create((int) $proctored->id), time(), false))
            ->prevent_access();
        $this->assertNotEmpty($messages, 'the quiz does not start');
    }

    public function test_standard_asks_for_the_rules_and_fixes_the_policy(): void {
        global $DB, $USER;
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('standard', 'compliance');
        $this->learner($quiz);
        $rule = $this->rule_for($quiz);
        $this->assertFalse($rule->prevent_access());
        $this->assertTrue($rule->is_preflight_check_required(null));
        $this->assertStringContainsString(get_string('securitylevel_standard', 'quizaccess_sentientia_proctoring'),
            $rule->description());

        $quizobj = quiz_settings::create((int) $quiz->id);
        $form = $quizobj->get_access_manager(time())->get_preflight_check_form($quizobj->start_attempt_url(), null);
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);
        foreach (['sentientia_secure_header', 'sentientia_secure_notice', 'sentientia_secure_ack'] as $name) {
            $this->assertTrue($mform->elementExists($name), $name);
        }
        $this->assertFalse($mform->elementExists('sentientia_proctoring_consent'), 'Standard asks no consent');
        $this->assertArrayHasKey('sentientia_secure_ack', $rule->validate_preflight_check([], [], [], null));
        $this->assertSame([], $rule->validate_preflight_check(['sentientia_secure_ack' => 1], [], [], null));

        $rule->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);
        $session = $DB->get_record('local_sentientia_proctor_sessions', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame('standard', $session->securitylevel);
        $this->assertEquals(0, $session->violationlimit, 'compliance: warn and flag, never submit');
        $this->assertFalse($this->rule_for($quiz)->is_preflight_check_required((int) $attempt->id));
        $this->assertEquals($USER->id, $session->userid);
    }

    public function test_a_hiring_quiz_shows_its_limit_and_asks_for_consent_at_proctored(): void {
        global $DB;
        $this->flag(secure_mode::FLAG, true);
        $this->flag(quiz_integration::FLAG, true);
        $quiz = $this->quiz('proctored', 'hiring', 4);
        $this->learner($quiz);
        $rule = $this->rule_for($quiz);

        $quizobj = quiz_settings::create((int) $quiz->id);
        $form = $quizobj->get_access_manager(time())->get_preflight_check_form($quizobj->start_attempt_url(), null);
        $mform = (new \ReflectionProperty($form, '_form'))->getValue($form);
        $this->assertTrue($mform->elementExists('sentientia_proctoring_consent'));
        $this->assertTrue($mform->elementExists('sentientia_proctoring_identity'));
        $this->assertStringContainsString(get_string('secure_notice_autosubmit', 'quizaccess_sentientia_proctoring', 4),
            $mform->getElement('sentientia_secure_notice')->toHtml());

        $rule->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);
        $session = $DB->get_record('local_sentientia_proctor_sessions', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertSame('proctored', $session->securitylevel);
        $this->assertEquals(4, $session->violationlimit);
    }

    public function test_the_attempt_pages_load_secure_mode_and_record_the_page(): void {
        global $DB;
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('secure');
        $this->learner($quiz, ['firstname' => 'Asha', 'lastname' => 'Verma']);
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        $page = new \moodle_page();
        $page->set_url('/mod/quiz/attempt.php', ['attempt' => $attempt->id, 'cmid' => $quiz->cmid, 'page' => 1]);
        $this->rule_for($quiz)->setup_attempt_page($page);
        $code = $this->amd_code($page);
        $this->assertStringContainsString('local_sentientia_proctoring/secure_mode', $code);
        $this->assertStringContainsString('"gate":true', $code);
        $this->assertStringContainsString('"page":1', $code);
        $this->assertStringContainsString('Asha Verma', $code, 'the watermark carries the name');
        $this->assertStringContainsString('sentientia-secure-gated', $page->bodyclasses,
            'hidden from the first paint, so a blocked script never shows the questions');
        $this->assertStringNotContainsString("local_sentientia_proctoring/proctor'", $code, 'not the B1 monitor too');

        $page = new \moodle_page();
        $page->set_url('/mod/quiz/summary.php', ['attempt' => $attempt->id, 'cmid' => $quiz->cmid]);
        $this->rule_for($quiz)->setup_attempt_page($page);
        $code = $this->amd_code($page);
        $this->assertStringContainsString('"gate":false', $code, 'the summary page shows no question');
        $this->assertStringNotContainsString('sentientia-secure-gated', $page->bodyclasses);
        $this->assertStringContainsString('sentientia-secure-active', $page->bodyclasses);
        $this->assertStringContainsString('"page":-1', $code);

        $session = $DB->get_record('local_sentientia_proctor_sessions', ['attemptid' => $attempt->id], '*', MUST_EXIST);
        $this->assertEquals(2, $DB->count_records('local_sentientia_proctor_events',
            ['sessionid' => $session->id, 'event_type' => 'page_served']));
    }

    public function test_a_teacher_gets_the_integrity_report_on_a_learners_review_page(): void {
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('standard');
        $this->learner($quiz);
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);

        // The learner's own review page: nothing.
        $page = new \moodle_page();
        $page->set_url('/mod/quiz/review.php', ['attempt' => $attempt->id]);
        $rule = $this->rule_for($quiz);
        if ($rule) {
            $rule->setup_attempt_page($page);
        }
        $this->assertStringNotContainsString('integrity_report', $this->amd_code($page));

        // A teacher (preview user): never protected, but the report loads.
        $this->setAdminUser();
        $rule = $this->rule_for($quiz);
        $this->assertInstanceOf(rule::class, $rule);
        $this->assertFalse($rule->is_preflight_check_required(null), 'previews are never protected');
        $this->assertFalse($rule->prevent_access());
        $this->assertSame('', $rule->description());
        $page = new \moodle_page();
        $page->set_url('/mod/quiz/review.php', ['attempt' => $attempt->id]);
        $rule->setup_attempt_page($page);
        $this->assertStringContainsString('local_sentientia_proctoring/integrity_report', $this->amd_code($page));

        // Secure mode OFF: a teacher's preview is stock again.
        $this->flag(secure_mode::FLAG, false);
        $this->assertNull($this->rule_for($quiz));
    }

    /**
     * B2 review: switching secure mode OFF mid-attempt used to block attempt.php and summary.php, so the learner could
     * neither continue nor submit. The attempt now keeps the rules the learner accepted until it ends; only new
     * attempts fail closed.
     */
    public function test_switching_secure_mode_off_mid_attempt_keeps_the_accepted_rules(): void {
        $c = 'quizaccess_sentientia_proctoring';
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('secure', 'skill');
        $this->learner($quiz);
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        $this->flag(secure_mode::FLAG, false);
        $rule = $this->rule_for($quiz);
        $this->assertInstanceOf(rule::class, $rule, 'still in secure mode');
        $this->assertFalse($rule->prevent_access());
        $this->assertFalse($rule->is_preflight_check_required((int) $attempt->id), 'the rules were accepted already');
        $this->assertStringContainsString(get_string('securitylevel_secure', $c), $rule->description());
        $manager = new \mod_quiz\access_manager(quiz_settings::create((int) $quiz->id), time(), false);
        $this->assertSame([], $manager->prevent_access(), 'attempt.php and summary.php open: the learner can submit');

        $page = new \moodle_page();
        $page->set_url('/mod/quiz/summary.php', ['attempt' => $attempt->id, 'cmid' => $quiz->cmid]);
        $rule->setup_attempt_page($page);
        $code = $this->amd_code($page);
        $this->assertStringContainsString('local_sentientia_proctoring/secure_mode', $code, 'still protected');
        $this->assertStringContainsString('"grace":' . secure_mode::GRACE_SECONDS, $code);

        // Once it is submitted, a new attempt fails closed again.
        quiz_attempt::create((int) $attempt->id)->process_submit(time(), false);
        $this->assertSame(get_string('blocked_securemode_off', $c), $this->rule_for($quiz)->prevent_access());
    }

    /**
     * B2 review: the open attempt runs with the level and limit stored on its session, not the quiz's current ones,
     * and switching proctoring OFF does not trap a Proctored attempt.
     */
    public function test_an_open_attempt_keeps_its_policy_when_the_quiz_or_proctoring_changes(): void {
        $c = 'quizaccess_sentientia_proctoring';
        $this->flag(secure_mode::FLAG, true);
        $this->flag(quiz_integration::FLAG, true);
        $quiz = $this->quiz('proctored', 'hiring', 4);
        $this->learner($quiz);
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        // The editor lowers the quiz mid-attempt; then proctoring is switched off.
        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_level' => 'standard',
            'sentientia_proctoring_purpose' => 'compliance', 'sentientia_proctoring_violationlimit' => 2]);
        $this->flag(quiz_integration::FLAG, false);

        $rule = $this->rule_for($quiz);
        $this->assertFalse($rule->prevent_access(), 'not trapped: the attempt can still be submitted');
        $this->assertFalse($rule->is_preflight_check_required((int) $attempt->id));
        $description = $rule->description();
        $this->assertStringContainsString(get_string('securitylevel_proctored', $c), $description);
        $this->assertStringContainsString(get_string('description_autosubmit', $c, 4), $description);
    }

    /**
     * B2 review: an attempt started before the quiz had a level has no accepted rules. With secure mode OFF it
     * finishes as the stock quiz it started as instead of being trapped; a new attempt still fails closed.
     */
    public function test_an_attempt_started_before_the_quiz_had_a_level_finishes_as_a_stock_quiz(): void {
        $quiz = $this->quiz('');
        $this->learner($quiz);
        $this->start_attempt($quiz);
        rule::save_settings((object) ['id' => $quiz->id, 'sentientia_proctoring_level' => 'secure',
            'sentientia_proctoring_purpose' => 'skill', 'sentientia_proctoring_violationlimit' => 3]);
        $this->assertNull($this->rule_for($quiz), 'the open attempt is not trapped');

        $this->learner($quiz);
        $this->assertSame(get_string('blocked_securemode_off', 'quizaccess_sentientia_proctoring'),
            $this->rule_for($quiz)->prevent_access(), 'a new attempt fails closed');
    }

    /**
     * B2 review: the quiz web services (the mobile service) run the pre-start check with the caller's own answers
     * but never load the attempt pages. A Secure, Proctored or B1 proctored quiz is "desktop required" there, so a
     * direct call can neither start an attempt nor open a session.
     */
    public function test_a_web_service_call_cannot_start_a_secure_or_proctored_quiz(): void {
        global $DB;
        $c = 'quizaccess_sentientia_proctoring';
        $this->flag(secure_mode::FLAG, true);
        $this->flag(quiz_integration::FLAG, true);
        rule::set_web_service_request_for_testing(true);
        $desktop = clean_text(get_string('blocked_desktop_required', $c), PARAM_TEXT);

        $secure = $this->quiz('secure', 'skill');
        $learner = $this->learner($secure);
        $result = mod_quiz_external::start_attempt((int) $secure->id, [['name' => 'sentientia_secure_ack', 'value' => '1']]);
        $result = external_api::clean_returnvalue(mod_quiz_external::start_attempt_returns(), $result);
        $this->assertEquals([], $result['attempt']);
        $this->assertCount(1, $result['warnings']);
        $this->assertSame($desktop, $result['warnings'][0]['message']);

        $proctored = $this->quiz('proctored', 'hiring');
        $candidate = $this->learner($proctored);
        $result = mod_quiz_external::start_attempt((int) $proctored->id, [
            ['name' => 'sentientia_proctoring_consent', 'value' => '1'],
            ['name' => 'sentientia_proctoring_identity', 'value' => '1'],
        ]);
        $result = external_api::clean_returnvalue(mod_quiz_external::start_attempt_returns(), $result);
        $this->assertEquals([], $result['attempt']);
        $this->assertSame($desktop, $result['warnings'][0]['message']);

        foreach ([$learner, $candidate] as $user) {
            $this->assertSame(0, $DB->count_records('local_sentientia_proctor_sessions', ['userid' => $user->id]),
                'no session was opened');
            $this->assertSame(0, $DB->count_records('quiz_attempts', ['userid' => $user->id]), 'no attempt started');
        }

        // A B1 proctored quiz (secure mode OFF, proctoring ON) as well.
        $this->flag(secure_mode::FLAG, false);
        $b1 = $this->quiz('');
        rule::save_settings((object) ['id' => $b1->id, 'sentientia_proctoring_enabled' => 1]);
        $this->learner($b1);
        $this->assertSame(get_string('blocked_desktop_required', $c), $this->rule_for($b1)->prevent_access());

        // The quiz pages' own requests (lib/ajax/service.php) are not web-service calls.
        rule::set_web_service_request_for_testing(false);
        $this->flag(secure_mode::FLAG, true);
        $this->learner($secure);
        $this->assertFalse($this->rule_for($secure)->prevent_access());
    }

    /**
     * B2 review: an attempt started on the desktop cannot be continued through the web services either.
     */
    public function test_a_web_service_call_cannot_continue_a_desktop_attempt(): void {
        $c = 'quizaccess_sentientia_proctoring';
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('secure', 'skill');
        $this->learner($quiz);
        $this->rule_for($quiz)->notify_preflight_check_passed(null);
        $attempt = $this->start_attempt($quiz);

        rule::set_web_service_request_for_testing(true);
        $manager = new \mod_quiz\access_manager(quiz_settings::create((int) $quiz->id), time(), false);
        $this->assertSame([get_string('blocked_desktop_required', $c)], $manager->prevent_access());
        try {
            mod_quiz_external::get_attempt_data((int) $attempt->id, 0, [['name' => 'sentientia_secure_ack', 'value' => '1']]);
            $this->fail('the attempt cannot be read through the web services');
        } catch (\moodle_exception $e) {
            $this->assertSame('attempterror', $e->errorcode);
        }

        // On the desktop it carries on.
        rule::set_web_service_request_for_testing(false);
        $this->assertFalse($this->rule_for($quiz)->prevent_access());
    }

    /**
     * B2 review: a Standard quiz is allowed in the app (ADR-040, plan decision D29). The app runs no secure-mode page,
     * so the rule does not apply there: the quiz is the stock quiz, with no session to review.
     */
    public function test_a_standard_quiz_runs_as_the_stock_quiz_through_the_web_services(): void {
        global $DB;
        $this->flag(secure_mode::FLAG, true);
        $quiz = $this->quiz('standard', 'compliance');
        $learner = $this->learner($quiz);
        rule::set_web_service_request_for_testing(true);
        $this->assertNull($this->rule_for($quiz));

        $result = mod_quiz_external::start_attempt((int) $quiz->id);
        $result = external_api::clean_returnvalue(mod_quiz_external::start_attempt_returns(), $result);
        $this->assertCount(0, $result['warnings']);
        $this->assertEquals($learner->id, $result['attempt']['userid']);
        $this->assertSame(0, $DB->count_records('local_sentientia_proctor_sessions', ['userid' => $learner->id]));
    }
}
