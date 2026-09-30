<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\feature_flags;
use local_sentientia_recompletion\task\run_rules;

/**
 * The daily task is behind a default-OFF flag (ADR-032, mapping doc section 12 code fix 6).
 *
 * The engine runs every ENABLED rule with no other switch and deletes completions, grades and quiz attempts. The
 * import creates every rule disabled, but a restored database must also be unable to start resetting learners on
 * its first 03:15 run whatever it contains, so the task itself is gated. With the flag off it does nothing and
 * says so; with the flag on it is exactly the task it always was.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\task\run_rules
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class run_rules_flag_test extends \advanced_testcase {

    /** @var \stdClass A learner whose completion has outlived an enabled rule. */
    private $user;
    /** @var int Course id. */
    private $courseid;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
        feature_flags::invalidate_caches();

        $g = $this->getDataGenerator();
        $this->user = $g->create_user();
        $this->courseid = (int) $g->create_course(['enablecompletion' => 1])->id;
        $DB->insert_record('course_completions', (object) ['userid' => $this->user->id, 'course' => $this->courseid,
            'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => time() - 400 * DAYSECS, 'reaggregate' => 0]);
        $now = time();
        $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name' => 'Annual', 'courseid' => $this->courseid, 'period_days' => 365, 'trigger_type' => 'completion',
            'reset_grades' => 0, 'reset_attempts' => 0, 'enabled' => 1, 'costcenterid' => 0,
            'timecreated' => $now, 'timemodified' => $now,
        ]);
    }

    /**
     * Run the task and return what it printed.
     *
     * @return string
     */
    private function run_task(): string {
        ob_start();
        try {
            (new run_rules())->execute();
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    public function test_the_flag_is_registered_and_off_by_default(): void {
        $registry = feature_flags::load_registry();
        $this->assertArrayHasKey(run_rules::FLAG, $registry);
        $this->assertFalse($registry[run_rules::FLAG]['default']);
        $this->assertSame('sentientia.recompletion.run_rules', run_rules::FLAG);
        $this->assertFalse(feature_flags::is_enabled(run_rules::FLAG));
    }

    public function test_with_the_flag_off_the_task_evaluates_no_rule_and_says_so(): void {
        global $DB;
        $output = $this->run_task();

        $this->assertStringContainsString('skipped', $output);
        $this->assertStringContainsString(run_rules::FLAG, $output);
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $this->user->id]),
            'an enabled rule with a due learner is left alone');
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_history'));
        $this->assertNull($DB->get_field('local_sentientia_recompletion_rules', 'last_run_at', ['courseid' => $this->courseid]),
            'the engine did not even look at the rule');
    }

    public function test_with_the_flag_on_the_task_runs_every_enabled_rule_as_it_always_did(): void {
        global $DB;
        feature_flags::set(run_rules::FLAG, 0, true);
        $sink = $this->redirectMessages();

        $output = $this->run_task();

        $this->assertStringContainsString('rules=1 reset=1', $output);
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $this->user->id]));
        $this->assertSame(1, $DB->count_records('local_sentientia_recompletion_history', ['userid' => $this->user->id]));
        $this->assertSame(1, $sink->count());
    }

    public function test_a_disabled_rule_stays_idle_with_the_flag_on(): void {
        global $DB;
        feature_flags::set(run_rules::FLAG, 0, true);
        $DB->set_field('local_sentientia_recompletion_rules', 'enabled', 0, ['courseid' => $this->courseid]);
        $this->redirectMessages();

        $output = $this->run_task();

        $this->assertStringContainsString('rules=0 reset=0', $output, 'turning the task on does not enable an imported rule');
        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $this->user->id]));
    }
}
