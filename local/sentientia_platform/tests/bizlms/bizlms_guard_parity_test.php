<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use core\check\result;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\guard_permit;
use local_sentientia_platform\bizlms\guard_refused;
use local_sentientia_platform\bizlms\legacy_tables;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\check\bizlms_import;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_importer;
use local_sentientia_platform\tests\bizlms\toy_seed;

/**
 * The CLI guard, the parity hooks and the core status check of the BizLMS
 * import (ADR-032, "Gating", "Parity hooks", "Transactions").
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\guard
 * @covers     \local_sentientia_platform\bizlms\parity
 * @covers     \local_sentientia_platform\check\bizlms_import
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_guard_parity_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use toy_seed;

    private const COMPONENT = 'local_sentientia_platform';

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    protected function tearDown(): void {
        registry::set_testing_importers(null);
        toy_importer::reset();
        $this->cli_maintenance(false);
        parent::tearDown();
    }

    /**
     * Turn CLI maintenance mode (admin/cli/maintenance.php --enable) on or off: the climaintenance.html file.
     *
     * @param bool $on
     * @return void
     */
    private function cli_maintenance(bool $on): void {
        global $CFG;
        $file = $CFG->dataroot . '/climaintenance.html';
        if ($on) {
            file_put_contents($file, 'maintenance');
        } else if (file_exists($file)) {
            unlink($file);
        }
    }

    /**
     * Put the site in the state every guard wants: armed, in maintenance, no mail, no cron.
     *
     * @return array The options that satisfy guard::refusals_for_apply().
     */
    private function guarded(): array {
        global $CFG;
        set_config('bizlms_import_armed_until', time() + 3600, self::COMPONENT);
        set_config('bizlms_production', 0, self::COMPONENT);
        set_config('cron_enabled', 0);
        $this->cli_maintenance(true);
        $CFG->noemailever = true;
        return ['confirm' => fingerprint::install(), 'decisions_hash' => 'abc', 'expect_hash' => ''];
    }

    /**
     * Run a real apply of the seeded toy data.
     *
     * @return void
     */
    private function imported_toy(): void {
        registry::set_testing_importers([new toy_importer()]);
        $result = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'batch' => 2, 'atomic_threshold' => 0]))->run([]);
        $this->assertContains($result['exit'], [0, 2]);
    }

    // The guard.

    public function test_apply_is_refused_until_every_guard_holds(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->cli_maintenance(false);
        $CFG->noemailever = false;
        set_config('cron_enabled', 1);

        $refusals = implode(' | ', guard::refusals_for_apply([]));
        foreach (['confirm_does_not_match', 'guard_not_armed', 'maintenance_mode_is_off', 'noemailever_is_off',
                  'scheduled_task_runner_is_on'] as $missing) {
            $this->assertStringContainsString($missing, $refusals);
        }

        $options = $this->guarded();
        $this->assertSame([], guard::refusals_for_apply($options), 'every guard satisfied');
    }

    public function test_the_cron_guard_fails_closed_when_the_setting_was_never_written(): void {
        $this->resetAfterTest();
        $options = $this->guarded();
        unset_config('cron_enabled');
        // The admin setting defaults to ON, so an absent row means cron is running.
        $this->assertStringContainsString('explicitly 0', implode(' ', guard::refusals_for_apply($options)));
        set_config('cron_enabled', 0);
        $this->assertSame([], guard::refusals_for_apply($options));
    }

    public function test_a_command_copied_from_a_rehearsal_cannot_run_on_production(): void {
        $this->resetAfterTest();
        $options = $this->guarded();
        $options['confirm'] = 'deadbeef0000';
        $this->assertStringContainsString('confirm_does_not_match', implode(' ', guard::refusals_for_apply($options)));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', fingerprint::install());
    }

    public function test_the_arming_expires_on_its_own(): void {
        $this->resetAfterTest();
        $options = $this->guarded();
        set_config('bizlms_import_armed_until', time() - 1, self::COMPONENT);
        $this->assertStringContainsString('guard_not_armed', implode(' ', guard::refusals_for_apply($options)));
    }

    public function test_online_runs_are_refused_on_production_and_cutover_needs_the_rehearsed_decisions(): void {
        $this->resetAfterTest();
        global $CFG;
        $options = $this->guarded();
        $this->cli_maintenance(false);
        $this->assertStringContainsString('maintenance_mode_is_off', implode(' ', guard::refusals_for_apply($options)));
        $this->assertSame([], guard::refusals_for_apply($options + ['allow_online' => true]), 'rehearsal may run online');

        set_config('bizlms_production', 1, self::COMPONENT);
        $refusals = implode(' | ', guard::refusals_for_apply($options + ['allow_online' => true]));
        $this->assertStringContainsString('allow_online_is_refused_when_bizlms_production_is_1', $refusals);
        $this->assertStringContainsString('expect_decisions_hash_is_required', $refusals);

        $this->cli_maintenance(true);
        $production = ['decisions_hash' => 'abc', 'expect_hash' => 'abc'] + $options;
        $this->assertSame([], guard::refusals_for_apply($production));
        $production['expect_hash'] = 'different';
        $this->assertStringContainsString('decisions_hash_differs', implode(' ', guard::refusals_for_apply($production)));
    }

    public function test_web_maintenance_mode_is_not_the_maintenance_the_import_needs(): void {
        global $CFG;
        $this->resetAfterTest();
        $options = $this->guarded();
        $this->cli_maintenance(false);
        // Web maintenance still lets administrators log in and edit the tables the import is writing.
        $CFG->maintenance_enabled = 1;
        $this->assertFalse(guard::maintenance_on());
        $this->assertStringContainsString('maintenance_mode_is_off', implode(' ', guard::refusals_for_apply($options)));

        $CFG->maintenance_enabled = 0;
        $this->cli_maintenance(true);
        $this->assertTrue(guard::maintenance_on());
        $this->assertSame([], guard::refusals_for_apply($options));
    }

    public function test_the_runner_writes_nothing_without_a_guard_permit(): void {
        global $DB;
        $this->resetAfterTest();
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        try {
            (new runner(['apply' => true]))->run([]);
            $this->fail('an apply run started without a permit');
        } catch (guard_refused $e) {
            $this->assertStringContainsString('runner_needs_a_guard_permit:apply', $e->getMessage());
        }
        // A purge permit does not open an apply run, and an apply permit does not open a purge.
        try {
            (new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]))->run([]);
            $this->fail('a purge permit started an apply run');
        } catch (guard_refused $e) {
            $this->assertStringContainsString('runner_needs_a_guard_permit:apply', $e->getMessage());
        }
        try {
            (new runner(['apply' => true, 'permit' => guard::test_permit()]))->purge('toy');
            $this->fail('an apply permit started a purge');
        } catch (guard_refused $e) {
            $this->assertStringContainsString('runner_needs_a_guard_permit:purge', $e->getMessage());
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_legacyrun'), 'nothing was written');
    }

    public function test_only_the_guard_issues_a_permit_and_it_needs_no_refusals(): void {
        try {
            guard_permit::issue(guard_permit::APPLY);
            $this->fail('a test class issued a permit');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('only the guard issues', $e->getMessage());
        }
        $this->expectException(guard_refused::class);
        guard::permit(guard_permit::APPLY, ['guard_not_armed']);
    }

    public function test_repair_needs_the_fingerprint_and_maintenance(): void {
        $this->resetAfterTest();
        $this->guarded();
        $this->cli_maintenance(false);
        $refusals = implode(' | ', guard::refusals_for_repair([]));
        $this->assertStringContainsString('confirm_does_not_match', $refusals);
        $this->assertStringContainsString('maintenance_mode_is_off', $refusals);

        $ok = ['confirm' => fingerprint::install(), 'allow_online' => true];
        $this->assertSame([], guard::refusals_for_repair($ok), 'a rehearsal may run online');
        set_config('bizlms_production', 1, self::COMPONENT);
        $this->assertStringContainsString('allow_online_is_refused_when_bizlms_production_is_1',
            implode(' ', guard::refusals_for_repair($ok)));
        $this->cli_maintenance(true);
        $this->assertSame([], guard::refusals_for_repair(['allow_online' => false] + $ok));
    }

    public function test_purge_is_a_rehearsal_operation(): void {
        $this->resetAfterTest();
        $this->guarded();
        $ok = ['confirm' => fingerprint::install(), 'understood' => true];
        $this->assertSame([], guard::refusals_for_purge($ok));
        $this->assertStringContainsString('i_understand_this_deletes_is_missing',
            implode(' ', guard::refusals_for_purge(['understood' => false] + $ok)));
        $this->assertStringContainsString('confirm_does_not_match',
            implode(' ', guard::refusals_for_purge(['confirm' => 'nope'] + $ok)));
        set_config('bizlms_production', 1, self::COMPONENT);
        $this->assertStringContainsString('purge_is_refused_when_bizlms_production_is_1',
            implode(' ', guard::refusals_for_purge($ok)));
    }

    public function test_only_one_import_holds_the_lock(): void {
        $this->resetAfterTest();
        $factory = \core\lock\lock_config::get_lock_factory(self::COMPONENT);
        if ($factory->supports_recursion()) {
            $this->markTestSkipped('this lock factory lets one process take a lock twice');
        }
        $lock = guard::acquire_lock();
        try {
            guard::acquire_lock();
            $this->fail('a second import took the lock');
        } catch (guard_refused $e) {
            $this->assertStringContainsString('another_bizlms_import_holds_the_lock', $e->getMessage());
        } finally {
            $lock->release();
        }
        guard::acquire_lock()->release();
    }

    public function test_status_facts(): void {
        $this->resetAfterTest();
        $this->guarded();
        $state = guard::state();
        $this->assertSame(fingerprint::install(), $state['fingerprint']);
        $this->assertGreaterThan(0, $state['armed_seconds_left']);
        $this->assertTrue($state['maintenance']);
        $this->assertFalse($state['production']);
    }

    // Parity.

    public function test_legacy_fingerprints_detect_drift_and_a_missing_table(): void {
        global $DB;
        $this->resetAfterTest();
        $this->seed_toy_data();
        legacy_tables::reset();
        $baseline = parity::legacy_fingerprints();
        $this->assertArrayHasKey('local_toy_org', $baseline);
        $this->assertSame(5, $baseline['local_toy_org']['count']);
        $this->assertArrayNotHasKey('local_sentientia_toy_org', $baseline);

        $same = parity::compare_fingerprints($baseline, parity::legacy_fingerprints());
        $this->assertSame([], $same['drift']);
        $this->assertSame([], $same['missing']);
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->assertContains('local_toy_org', $same['skipped'], 'no CRC on this engine: reported, not passed');
        }

        $DB->import_record('local_toy_org', (object) ['id' => 70, 'name' => 'Extra', 'parentid' => 0, 'path' => null,
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        self::drop_legacy_table('local_toy_unused');
        $after = parity::compare_fingerprints($baseline, parity::legacy_fingerprints());
        $this->assertNotEmpty(preg_grep('/^local_toy_org rows 5->6/', $after['drift']));
        $this->assertSame(['local_toy_unused'], $after['missing'], 'a missing legacy table is drift');
    }

    public function test_the_bizlms_import_invariant(): void {
        global $DB;
        $this->resetAfterTest();
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        legacy_tables::reset();

        $this->assertContains('feature_not_complete:toy', parity::invariant_problems(),
            'an applicable feature without its completion marker is a hard problem');

        $this->imported_toy();
        $this->assertSame([], parity::invariant_problems(), 'a complete import holds every invariant');

        // A target row deleted by hand.
        $DB->delete_records('local_sentientia_toy_org', ['id' => 1]);
        $problems = implode(' | ', parity::invariant_problems());
        $this->assertStringContainsString('missing_target_rows:toy:local_sentientia_toy_org', $problems);
        set_config('bizlms_production_open', time(), self::COMPONENT);
        $this->assertStringNotContainsString('missing_target_rows', implode(' | ', parity::invariant_problems()),
            'once the site is open admins may delete rows');
        unset_config('bizlms_production_open', self::COMPONENT);
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 1, 'name' => 'Alpha', 'path' => '/1', 'visible' => 1,
            'timecreated' => self::$toyt0 + 1, 'timemodified' => self::$toyt0 + 101]);
        $this->assertSame([], parity::invariant_problems());

        // A tenant value that is not a path with a registered root.
        $itemid = (new bizlms\legacymap())->resolve('local_toy_item', 1);
        $DB->set_field('local_sentientia_toy_item', 'tenantpath', '/999/5', ['id' => $itemid]);
        $this->assertStringContainsString('invalid_tenant_value:toy:local_sentientia_toy_item.tenantpath=/999/5',
            implode(' | ', parity::invariant_problems()));
        $DB->set_field('local_sentientia_toy_item', 'tenantpath', '/1', ['id' => $itemid]);
        $this->assertSame([], parity::invariant_problems());

        // A legacy table mutated after import.
        $DB->import_record('local_toy_org', (object) ['id' => 80, 'name' => 'Late', 'parentid' => 0, 'path' => null,
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        $problems = implode(' | ', parity::invariant_problems());
        $this->assertStringContainsString('source_mutated_after_import:toy:toy.org', $problems);
        $this->assertStringContainsString('accounting:toy:local_toy_org', $problems);
        $this->assertStringContainsString('unmapped_source_rows:toy:local_toy_org: ids=80', $problems);
    }

    public function test_the_invariant_is_silent_on_a_database_with_no_legacy_tables(): void {
        $this->resetAfterTest();
        registry::set_testing_importers([new toy_importer()]);
        foreach (['local_toy_org', 'local_toy_item', 'local_toy_dup', 'local_toy_event', 'local_toy_fan', 'local_toy_unused'] as $table) {
            self::drop_legacy_table($table);
        }
        legacy_tables::reset();
        $this->assertSame([], parity::invariant_problems(), 'a fresh install has nothing to prove');
    }

    // The core status check.

    public function test_status_check_is_critical_while_an_import_has_started_but_not_finished(): void {
        $this->resetAfterTest();
        registry::set_testing_importers([]);
        $this->assertSame(result::NA, (new bizlms_import())->get_result()->get_status(), 'no importers registered');

        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        $this->assertSame(result::OK, (new bizlms_import())->get_result()->get_status(), 'not started is not half done');

        $failpoint = function (string $key, int $batch): void {
            if ($batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        (new runner(['apply' => true, 'permit' => guard::test_permit(), 'batch' => 2, 'atomic_threshold' => 0, 'failpoint' => $failpoint]))->run([]);
        $check = new bizlms_import();
        $this->assertSame(result::CRITICAL, $check->get_result()->get_status());
        $this->assertStringContainsString('toy', $check->get_result()->get_summary());

        (new runner(['apply' => true, 'permit' => guard::test_permit(), 'resume' => true, 'batch' => 2, 'atomic_threshold' => 0]))->run([]);
        $this->assertSame(result::OK, (new bizlms_import())->get_result()->get_status(), 'the marker makes it clean');
    }

    public function test_status_check_sees_a_feature_mode_failure_whose_step_rows_were_rolled_back(): void {
        $this->resetAfterTest();
        toy_importer::reset();
        toy_importer::$atomic = true;
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        (new runner(['apply' => true, 'permit' => guard::test_permit(), 'batch' => 2, 'atomic_threshold' => 50000, 'failpoint' => $failpoint]))->run([]);
        $this->assertSame(result::CRITICAL, (new bizlms_import())->get_result()->get_status(),
            'feature mode rolled every step row back, but the failure marker keeps the check red');
    }

    public function test_once_the_runbook_declares_production_an_unimported_feature_is_critical(): void {
        $this->resetAfterTest();
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        $this->assertSame(result::OK, (new bizlms_import())->get_result()->get_status());
        set_config('bizlms_production', 1, self::COMPONENT);
        $this->assertSame(result::CRITICAL, (new bizlms_import())->get_result()->get_status(),
            'the site must not open on legacy history nobody imported');
    }

    public function test_parity_hands_the_run_decisions_to_each_importers_verify(): void {
        $this->resetAfterTest();
        toy_importer::reset();
        toy_importer::$requiredecision = true;
        registry::set_testing_importers([new toy_importer()]);
        $this->seed_toy_data();
        $decisions = \local_sentientia_platform\bizlms\decisions::from_array(['toy.mandatory' => 'yes']);
        $result = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'batch' => 2, 'atomic_threshold' => 0, 'decisions' => $decisions]))->run([]);
        $this->assertContains($result['exit'], [0, 2]);

        $this->assertSame([], parity::invariant_problems($decisions));
        $this->assertStringContainsString('verify_error:toy:missing_decision:toy.mandatory',
            implode(' ', parity::invariant_problems()), 'without the decisions verify() cannot run, and says so');
    }

    public function test_status_check_is_registered_through_lib_php(): void {
        global $CFG;
        require_once(__DIR__ . '/../../lib.php');
        $checks = local_sentientia_platform_status_checks();
        $this->assertCount(1, $checks);
        $this->assertInstanceOf(bizlms_import::class, $checks[0]);
    }
}
