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
 * Out of scope (would need full Moodle quiz fixtures):
 * - make() — depends on quiz_settings + access_rule_base construction
 * - prevent_new_attempt() — depends on local_sentientia_proctor_sessions
 *   table that lives in a sibling plugin (local_sentientia_proctoring)
 * - setup_attempt_page() — depends on a real $PAGE
 *
 * @package    quizaccess_sentientia_proctoring
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

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
     * save_settings() must coerce missing `sentientia_proctoring_enabled`
     * to 0 (defensive against form-data shape changes).
     */
    public function test_save_settings_treats_missing_field_as_disabled(): void {
        global $DB;
        $this->resetAfterTest();

        $quiz = (object) ['id' => 4245];  // no sentientia_proctoring_enabled key
        rule::save_settings($quiz);

        $row = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => 4245]);
        $this->assertNotFalse($row);
        $this->assertEquals(0, (int) $row->enabled);
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
