<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * PHPUnit coverage for quizaccess_sentientia_proctoring\rule.
 *
 * Scope (NIGHT-RUN-PLAYBOOK B2):
 * - save_settings() — insert vs update branches
 * - delete_settings() — removes by quizid
 * - is_quiz_proctored() — true/false/missing-row outcomes
 *
 * - B1 (2026-11-20): core discovery of the rule, get_settings_sql(), and
 *   save_settings() leaving the stored value alone when the field is absent
 *
 * make(), the pre-start check, setup_attempt_page() and the attempt
 * observers run on real quizzes and attempts in preflight_test.php.
 * (prevent_new_attempt() is gone: it was the B1 lockout.)
 *
 * @package    quizaccess_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
// Core loads accessrule rule.php directly (not via the classmap) — tests must
// do the same, exactly as core quizaccess rule tests do.
require_once($CFG->dirroot . '/mod/quiz/accessrule/sentientia_proctoring/rule.php');

/**
 * Tests for the quizaccess_sentientia_proctoring\rule class.
 *
 * @covers \quizaccess_sentientia_proctoring\rule
 */
final class rule_test extends \advanced_testcase {

    /**
     * save_settings() with enabled=1 on a quiz that has no existing row
     * must INSERT a new row in quizaccess_sentientia_proctor.
     */
    public function test_save_settings_inserts_new_row_when_enabled(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = (object) ['id' => 4242, 'sentientia_proctoring_enabled' => 1];
        rule::save_settings($quiz);

        $row = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => 4242]);
        $this->assertNotFalse($row);
        $this->assertEquals(1, (int) $row->enabled);
        $this->assertGreaterThan(0, (int) $row->timecreated);
        $this->assertGreaterThan(0, (int) $row->timemodified);
    }

    /**
     * save_settings() with enabled=0 on a quiz that has no existing row
     * must STILL insert a row (so admins can audit the explicit
     * "disabled" decision rather than confuse it with "never configured").
     */
    public function test_save_settings_inserts_disabled_row(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = (object) ['id' => 4243, 'sentientia_proctoring_enabled' => 0];
        rule::save_settings($quiz);

        $row = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => 4243]);
        $this->assertNotFalse($row);
        $this->assertEquals(0, (int) $row->enabled);
    }

    /**
     * save_settings() called twice on the same quiz must UPDATE the
     * existing row, not insert a duplicate (uniq_quizid index would
     * actually prevent duplicates with a constraint violation; this
     * test guards against future schema changes removing the index).
     */
    public function test_save_settings_updates_existing_row(): void {
        global $DB;
        $this->resetAfterTest();

        // First save: enabled=1.
        $quiz = (object) ['id' => 4244, 'sentientia_proctoring_enabled' => 1];
        rule::save_settings($quiz);
        $row1 = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => 4244]);
        $this->assertEquals(1, (int) $row1->enabled);

        // Move the clock so timemodified increases.
        sleep(1);

        // Second save: enabled=0.
        $quiz->sentientia_proctoring_enabled = 0;
        rule::save_settings($quiz);
        $row2 = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => 4244]);
        $this->assertEquals(0, (int) $row2->enabled);

        // Same primary key — update, not insert.
        $this->assertSame($row1->id, $row2->id);

        // timemodified moved forward.
        $this->assertGreaterThanOrEqual(
            (int) $row1->timemodified,
            (int) $row2->timemodified
        );

        // Only one row for this quiz.
        $this->assertEquals(1, $DB->count_records('quizaccess_sentientia_proctor', ['quizid' => 4244]));
    }

    /**
     * B1 (2026-11-20): a save without `sentientia_proctoring_enabled` (the
     * field is not on the form while the flag is OFF, or the quiz is saved by
     * code that does not know it) leaves the stored setting alone. Until B1
     * a missing field was saved as 0, which switched proctoring off on every
     * save made while the setting was hidden.
     */
    public function test_save_settings_leaves_the_stored_setting_when_the_field_is_absent(): void {
        global $DB;
        $this->resetAfterTest();

        rule::save_settings((object) ['id' => 4245]);  // no field, no row: nothing is written
        $this->assertFalse($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 4245]));

        rule::save_settings((object) ['id' => 4245, 'sentientia_proctoring_enabled' => 1]);
        rule::save_settings((object) ['id' => 4245]);  // hidden field: still proctored
        $this->assertTrue(rule::is_quiz_proctored(4245));
        $this->assertEquals(1, $DB->count_records('quizaccess_sentientia_proctor', ['quizid' => 4245]));
    }

    /**
     * B1: core finds an access rule only by the global class name
     * quizaccess_<plugin> (core_component::get_plugin_list_with_class()).
     * Before B1 only quizaccess_sentientia_proctoring\rule existed, so core
     * never loaded the rule at all.
     */
    public function test_core_finds_the_rule(): void {
        $classes = \core_component::get_plugin_list_with_class('quizaccess', '', 'rule.php');
        $this->assertArrayHasKey('quizaccess_sentientia_proctoring', $classes);
        $this->assertTrue(is_a($classes['quizaccess_sentientia_proctoring'], rule::class, true));
    }

    /**
     * B1: the setting loads with the quiz (one LEFT JOIN in core's settings
     * query), so the quiz form shows the stored value and make() needs no
     * query of its own. A quiz with no row reads as 0.
     */
    public function test_get_settings_sql_loads_the_setting_with_the_quiz(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $on = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $off = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        rule::save_settings((object) ['id' => $on->id, 'sentientia_proctoring_enabled' => 1]);

        $settings = \mod_quiz\access_manager::load_settings((int) $on->id);
        $this->assertEquals(1, (int) $settings['sentientia_proctoring_enabled']);
        $quiz = \mod_quiz\access_manager::load_quiz_and_settings((int) $off->id);
        $this->assertEquals(0, (int) $quiz->sentientia_proctoring_enabled);
    }

    /**
     * delete_settings() removes the row for the specified quiz only.
     */
    public function test_delete_settings_removes_only_target_quiz(): void {
        global $DB;
        $this->resetAfterTest();

        // Seed two quizzes.
        rule::save_settings((object) ['id' => 5000, 'sentientia_proctoring_enabled' => 1]);
        rule::save_settings((object) ['id' => 5001, 'sentientia_proctoring_enabled' => 1]);

        $this->assertTrue($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 5000]));
        $this->assertTrue($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 5001]));

        rule::delete_settings((object) ['id' => 5000]);

        $this->assertFalse($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 5000]));
        // Other quiz unaffected.
        $this->assertTrue($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 5001]));
    }

    /**
     * delete_settings() on a quiz that was never configured should be
     * a no-op (no exception, no side-effect on other rows).
     */
    public function test_delete_settings_is_noop_for_unconfigured_quiz(): void {
        global $DB;
        $this->resetAfterTest();

        rule::save_settings((object) ['id' => 6000, 'sentientia_proctoring_enabled' => 1]);

        rule::delete_settings((object) ['id' => 6999]);  // never configured

        // Original row unaffected.
        $this->assertTrue($DB->record_exists('quizaccess_sentientia_proctor', ['quizid' => 6000]));
    }

    /**
     * is_quiz_proctored() returns true when row exists AND enabled=1.
     */
    public function test_is_quiz_proctored_true_when_enabled(): void {
        $this->resetAfterTest();
        rule::save_settings((object) ['id' => 7001, 'sentientia_proctoring_enabled' => 1]);
        $this->assertTrue(rule::is_quiz_proctored(7001));
    }

    /**
     * is_quiz_proctored() returns false when row exists but enabled=0.
     */
    public function test_is_quiz_proctored_false_when_disabled(): void {
        $this->resetAfterTest();
        rule::save_settings((object) ['id' => 7002, 'sentientia_proctoring_enabled' => 0]);
        $this->assertFalse(rule::is_quiz_proctored(7002));
    }

    /**
     * is_quiz_proctored() returns false when row does not exist
     * (quiz was never configured).
     */
    public function test_is_quiz_proctored_false_when_no_row(): void {
        $this->resetAfterTest();
        $this->assertFalse(rule::is_quiz_proctored(99999));
    }
}
