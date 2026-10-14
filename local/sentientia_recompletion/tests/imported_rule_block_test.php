<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion;

defined('MOODLE_INTERNAL') || die();

/**
 * An imported BizLMS rule cannot be enabled (owner decision recompletion.imported_rule_enable, 2026-10-07).
 *
 * The engine does not yet reproduce what BizLMS did for an imported rule (the SCORM wipe, whole days, the
 * extra-attempt, LTI, assignment, questionnaire and custom e-mail choices), and nothing may start resetting people
 * on that basis. A warning on the form does not enforce that, so three things do: the form refuses to save such a
 * rule ENABLED, the save path refuses on its own, and run_all() never runs an enabled imported rule (the guard
 * against a direct database edit). A rule made in Sentientia is unaffected: it runs exactly as configured.
 *
 * The form and the save path ask rule_access::may_enable(); the engine asks the same, so one function is tested
 * and the engine is tested end to end.
 *
 * @package    local_sentientia_recompletion
 * @category   test
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_sentientia_recompletion\rule_access
 * @covers     \local_sentientia_recompletion\recompletion_engine
 *
 * @group local_sentientia_recompletion
 * @group bizlms_import
 */
final class imported_rule_block_test extends \advanced_testcase {

    /** @var \stdClass A learner of the course the native rule covers. */
    private $nativelearner;
    /** @var \stdClass A learner of the course the imported rule covers. */
    private $importedlearner;
    /** @var int Course of the native rule. */
    private $nativecourse;
    /** @var int Course of the imported rule. */
    private $importedcourse;

    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $g = $this->getDataGenerator();
        $this->nativelearner = $g->create_user();
        $this->importedlearner = $g->create_user();
        $this->nativecourse = (int) $g->create_course(['enablecompletion' => 1])->id;
        $this->importedcourse = (int) $g->create_course(['enablecompletion' => 1])->id;
        foreach ([[$this->nativelearner, $this->nativecourse], [$this->importedlearner, $this->importedcourse]] as [$user, $course]) {
            $DB->insert_record('course_completions', (object) ['userid' => $user->id, 'course' => $course,
                'timeenrolled' => 0, 'timestarted' => 0, 'timecompleted' => time() - 400 * DAYSECS, 'reaggregate' => 0]);
        }
    }

    /**
     * @param int $courseid
     * @param bool $imported Carry BizLMS settings in legacy_config.
     * @return \stdClass The stored rule, ENABLED (written straight to the database, as an edit would).
     */
    private function rule(int $courseid, bool $imported): \stdClass {
        global $DB;
        $now = time();
        $id = (int) $DB->insert_record('local_sentientia_recompletion_rules', (object) [
            'name' => $imported ? 'Legacy recompletion: AML' : 'Annual', 'courseid' => $courseid, 'period_days' => 365,
            'trigger_type' => 'completion', 'reset_grades' => 0, 'reset_attempts' => 0, 'enabled' => 1,
            'costcenterid' => 0, 'timecreated' => $now, 'timemodified' => $now,
            'legacy_config' => $imported ? json_encode(['enable' => '1', 'recompletionduration' => '31536000']) : null,
        ]);
        return $DB->get_record('local_sentientia_recompletion_rules', ['id' => $id], '*', MUST_EXIST);
    }

    public function test_only_a_rule_the_import_made_is_imported_and_only_a_native_one_may_be_enabled(): void {
        $imported = $this->rule($this->importedcourse, true);
        $native = $this->rule($this->nativecourse, false);

        $this->assertTrue(rule_access::is_imported($imported));
        $this->assertFalse(rule_access::may_enable($imported), 'the form and the save path refuse it');
        $this->assertFalse(rule_access::is_imported($native));
        $this->assertTrue(rule_access::may_enable($native), 'a native rule runs exactly as configured');

        // A rule being created has no row yet; a null or a bare id-only object is a native rule.
        $this->assertTrue(rule_access::may_enable(null));
        $this->assertTrue(rule_access::may_enable((object) ['id' => 0]));
        $this->assertFalse(rule_access::is_imported((object) ['id' => 5, 'legacy_config' => null]));
        // Any stored legacy settings make a rule imported, whatever they hold.
        $this->assertTrue(rule_access::is_imported((object) ['id' => 5, 'legacy_config' => '{}']));
    }

    public function test_the_engine_skips_an_enabled_imported_rule_and_counts_it(): void {
        global $DB;
        $imported = $this->rule($this->importedcourse, true);
        $this->rule($this->nativecourse, false);
        $this->redirectMessages();

        $totals = recompletion_engine::run_all(false);

        $this->assertSame(1, $totals['skipped_imported']);
        $this->assertSame(1, $totals['rules_run'], 'only the native rule ran');
        $this->assertSame(1, $totals['reset']);
        $this->assertSame(0, $totals['errors']);
        $this->assertSame(0, $totals['failed']);

        $this->assertSame(1, $DB->count_records('course_completions', ['userid' => $this->importedlearner->id]),
            'the imported rule reset nobody, though a direct edit enabled it');
        $this->assertSame(0, $DB->count_records('course_completions', ['userid' => $this->nativelearner->id]),
            'the native rule is unaffected');
        $this->assertSame(0, $DB->count_records('local_sentientia_recompletion_history',
            ['userid' => $this->importedlearner->id]));
        $this->assertNull($DB->get_field('local_sentientia_recompletion_rules', 'last_run_at', ['id' => $imported->id]),
            'the engine did not even look at the imported rule');
    }

    public function test_with_no_imported_rule_the_engine_runs_as_it_always_did(): void {
        $this->rule($this->nativecourse, false);
        $this->redirectMessages();

        $totals = recompletion_engine::run_all(false);

        $this->assertSame(['rules_run' => 1, 'reset' => 1, 'notified' => 0, 'skipped' => 0, 'errors' => 0,
            'failed' => 0, 'skipped_imported' => 0], $totals);
    }

    public function test_the_refusal_is_worded_in_english_and_hindi(): void {
        $dir = \core_component::get_component_directory('local_sentientia_recompletion');
        foreach (['en', 'hi'] as $lang) {
            $string = [];
            include($dir . '/lang/' . $lang . '/local_sentientia_recompletion.php');
            $this->assertArrayHasKey('legacy_enable_blocked', $string, $lang);
            $this->assertNotSame('', trim((string) $string['legacy_enable_blocked']), $lang);
            $this->assertArrayNotHasKey('legacy_enabled_warning', $string,
                $lang . ': the "saved ENABLED" warning is unreachable now and was removed');
        }
        $this->assertStringContainsString('BizLMS', get_string('legacy_enable_blocked', 'local_sentientia_recompletion'));
    }
}
