<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\decision;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\guard_permit;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
use local_sentientia_platform\tests\bizlms\toy_files_importer;
use local_sentientia_platform\tests\bizlms\toy_importer;
use local_sentientia_platform\tests\bizlms\toy_seed;

/**
 * Framework tests of the BizLMS import runner with the toy importer (ADR-032,
 * "Test approach" 3): dry run, accounting, idempotence, resume, collision and
 * adoption, sequences, tripwire, writer refusals, unknown enums, transactions,
 * dependencies, deferral, retry, purge and report hygiene.
 *
 * @package    local_sentientia_platform
 * @category   test
 * @covers     \local_sentientia_platform\bizlms\runner
 * @covers     \local_sentientia_platform\bizlms\writer
 * @covers     \local_sentientia_platform\bizlms\batch_source
 * @covers     \local_sentientia_platform\bizlms\legacymap
 *
 * @group local_sentientia_platform
 * @group bizlms_import
 */
final class bizlms_runner_test extends \advanced_testcase {
    use legacy_schema_fixture;
    use toy_seed;

    protected static function legacy_fixture_definition(): array {
        return ['xml' => __DIR__ . '/../fixtures/bizlms/toy.install.xml'];
    }

    protected function tearDown(): void {
        registry::set_testing_importers(null);
        toy_importer::reset();
        parent::tearDown();
    }

    /**
     * Register the importer(s) and reset the knobs.
     *
     * @param toy_importer[]|null $importers Defaults to one toy importer claiming every table.
     * @return void
     */
    private function begin(?array $importers = null): void {
        $this->resetAfterTest();
        toy_importer::reset();
        registry::set_testing_importers($importers ?? [new toy_importer()]);
    }

    /**
     * @param array $options Runner option overrides.
     * @param string[] $features
     * @param bool $apply False for a dry run.
     * @return array{0: array, 1: report}
     */
    private function execute(array $options = [], array $features = [], bool $apply = true): array {
        $report = new report();
        $permit = $apply ? ['permit' => guard::test_permit()] : [];
        $runner = new runner($options + $permit + ['apply' => $apply, 'report' => $report, 'batch' => 2, 'atomic_threshold' => 0]);
        return [$runner->run($features), $report];
    }

    /**
     * Counters of every step of a feature, from a report.
     *
     * @param report $report
     * @param string $feature
     * @return array<string, array>
     */
    private function counters(report $report, string $feature = 'toy'): array {
        $out = [];
        foreach ($report->to_array()['features'][$feature]['steps'] as $key => $step) {
            if (isset($step['counters'])) {
                $out[$key] = $step['counters'];
            }
        }
        return $out;
    }

    public function test_dry_run_reports_what_apply_then_does(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $writes = $DB->perf_get_writes();

        [$dry, $dryreport] = $this->execute([], [], false);
        $this->assertSame($writes, $DB->perf_get_writes(), 'a dry run writes nothing, not even bookkeeping');
        $this->assertSame('simulated', $dry['features']['toy']);
        $this->assertSame(0, $DB->count_records('local_sentientia_legacyrun'));

        [$apply, $applyreport] = $this->execute();
        $this->assertSame('complete', $apply['features']['toy']);
        $dryc = $this->counters($dryreport);
        $applyc = $this->counters($applyreport);
        unset($dryc['toy.recompute'], $applyc['toy.recompute']);
        $this->assertSame($dryc, $applyc, 'the dry run and the apply run execute the same transforms');
        $this->assertSame(['processed' => 5, 'imported' => 3, 'adopted' => 0, 'merged' => 0, 'folded' => 0,
            'archived' => 1, 'skipped' => 1, 'updated' => 0], $applyc['toy.org']);
        $this->assertSame(2, $applyc['toy.dup']['imported']);
        $this->assertSame(3, $applyc['toy.dup']['merged']);
    }

    public function test_apply_satisfies_the_accounting_identity_and_keeps_source_timestamps(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$result] = $this->execute();
        $this->assertSame(2, $result['exit'], 'orphan rows carry needs-owner reasons, so the run is unproven, not clean');

        foreach (['local_toy_org' => 5, 'local_toy_item' => 5, 'local_toy_dup' => 5, 'local_toy_fan' => 3] as $table => $count) {
            $this->assertSame($count, $DB->count_records('local_sentientia_legacymap',
                ['sourcetable' => $table, 'subkey' => '']), $table);
        }
        // A derived group is keyed by its own integer key, not by a row id or a user id.
        $carts = $DB->get_records('local_sentientia_legacymap', ['sourcetable' => '#local_toy_event.cartid'], 'sourceid');
        $this->assertSame(['10', '11', '12'], array_values(array_map(fn($r) => $r->sourceid, $carts)));

        $org = $DB->get_record('local_sentientia_toy_org', ['id' => 2]);
        $this->assertEquals(self::$toyt0 + 2, $org->timecreated, 'the source timecreated is kept');
        $this->assertEquals(self::$toyt0 + 102, $org->timemodified, 'the source timemodified is kept');
    }

    public function test_second_apply_writes_nothing(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $maprows = $DB->count_records('local_sentientia_legacymap');
        $targets = $DB->count_records('local_sentientia_toy_item') + $DB->count_records('local_sentientia_toy_org');

        [$second, $report] = $this->execute();
        $this->assertSame($maprows, $DB->count_records('local_sentientia_legacymap'));
        $this->assertSame($targets,
            $DB->count_records('local_sentientia_toy_item') + $DB->count_records('local_sentientia_toy_org'));
        $steps = $report->to_array()['features']['toy']['steps'];
        $this->assertSame(0, $steps['toy.org']['counters']['processed']);
        $this->assertSame(5, $steps['toy.org']['already_mapped']);
        $this->assertSame('complete', $second['features']['toy']);
    }

    public function test_adopted_header_row_is_overwritten_with_the_full_mapping(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        // What migrate_all.php wrote: same id, same name, same timecreated, a thin copy of the rest.
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 2, 'name' => 'Beta', 'path' => null,
            'visible' => 0, 'timecreated' => self::$toyt0 + 2, 'timemodified' => 7]);

        [$result, $report] = $this->execute();
        $this->assertSame(1, $this->counters($report)['toy.org']['adopted']);
        $org = $DB->get_record('local_sentientia_toy_org', ['id' => 2]);
        $this->assertSame('/77', $org->path, 'the adopted row carries the full mapping');
        $this->assertEquals(self::$toyt0 + 102, $org->timemodified);
        $this->assertSame(3, $DB->count_records('local_sentientia_toy_org'), 'the header copy was adopted, not duplicated');
    }

    public function test_a_colliding_id_blocks_the_whole_feature(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 1, 'name' => 'Somebody else', 'path' => null,
            'visible' => 1, 'timecreated' => 5, 'timemodified' => 5]);

        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString('preserve_collision:toy.org:1 ids=1', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap'), 'nothing was written');
        $this->assertSame(0, $DB->count_records('local_sentientia_toy_item'), 'other steps did not run either');
        $this->assertSame('Somebody else', $DB->get_field('local_sentientia_toy_org', 'name', ['id' => 1]));
    }

    public function test_native_insert_after_finalise_gets_an_id_above_the_legacy_maximum(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $id = $DB->insert_record('local_sentientia_toy_org', (object) ['name' => 'Native', 'path' => null, 'visible' => 1,
            'timecreated' => 1, 'timemodified' => 1]);
        $this->assertGreaterThan(7, $id, 'the sequence continues after the highest legacy id that was kept');
    }

    public function test_unknown_enum_value_blocks_until_the_decisions_file_maps_it(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $DB->import_record('local_toy_item', (object) ['id' => 9, 'orgid' => 1, 'userid' => 1, 'title' => 'Odd', 'kind' => 'z',
            'path' => null, 'timecreated' => 1, 'timemodified' => 1]);

        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_toy_item.kind=z', implode(' ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap'));
        $histogram = $report->to_array()['features']['toy']['preflight']['histograms']['local_toy_item.kind'];
        $this->assertSame(1, $histogram['z'], 'preflight prints the histogram of every declared enum column');
    }

    public function test_a_decisions_file_can_map_an_unknown_enum_value_and_the_report_lists_the_decisions(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $DB->import_record('local_toy_item', (object) ['id' => 9, 'orgid' => 1, 'userid' => 1, 'title' => 'Odd', 'kind' => 'z',
            'path' => null, 'timecreated' => 1, 'timemodified' => 1]);

        $mapping = ['enums.local_toy_item.kind' => ['z' => 'zeta'], 'toy.rounding' => 'half_even'];
        [$result, $report] = $this->execute(['decisions' => decisions::from_array($mapping)]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(['toy.rounding' => ['value' => 'half_even', 'status' => 'accepted']],
            $report->to_array()['meta']['decisions'], 'the report lists the decisions used, with their status');
        $this->assertSame([], $report->to_array()['meta']['decisions_not_accepted']);
    }

    public function test_required_decision_blocks_when_missing(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$requiredecision = true;

        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('missing_decision:toy.mandatory', implode(' ', $result['blockers']));

        [$result] = $this->execute(['decisions' => decisions::from_array(['toy.mandatory' => 'yes'])]);
        $this->assertContains($result['exit'], [0, 2]);
    }

    /**
     * @dataProvider writer_refusal_provider
     * @param string $knob Static knob of the toy importer.
     * @param mixed $value
     * @param string $expected Fragment of the refusal.
     */
    public function test_writer_refuses_bad_rows_in_dry_run_and_apply(string $knob, mixed $value, string $expected): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::${$knob} = $value;

        foreach ([false, true] as $apply) {
            [$result] = $this->execute([], [], $apply);
            $this->assertSame(1, $result['exit'], $apply ? 'apply' : 'dry run');
            $this->assertStringContainsString($expected, implode(' ', $result['blockers']));
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_toy_org'), 'the refused batch was rolled back');
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    /**
     * @return array<string, array>
     */
    public static function writer_refusal_provider(): array {
        return [
            'unknown field (no silent column loss)' => ['unknownfield', 'nosuchcolumn', 'unknown_field:local_sentientia_toy_org.nosuchcolumn'],
            'missing source timestamp' => ['omittimestamp', true, 'missing_timestamp:local_sentientia_toy_org.timemodified'],
        ];
    }

    public function test_writer_refuses_an_overlong_char_that_did_not_go_through_fit(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$rawtitlelength = 41;
        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('too_long:local_sentientia_toy_item.title', implode(' ', $result['blockers']));
    }

    public function test_fit_truncates_explicitly_and_reports_it(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [, $report] = $this->execute();
        $targetid = (new legacymap())->resolve('local_toy_item', 2);
        $this->assertSame(40, \core_text::strlen($DB->get_field('local_sentientia_toy_item', 'title', ['id' => $targetid])));
        $this->assertSame(1, $report->to_array()['features']['toy']['steps']['toy.item']['warnings']['truncated:title']);
        $this->assertStringContainsString('long long',
            $DB->get_field('local_toy_item', 'title', ['id' => 2]), 'the full value stays in the legacy table');
    }

    public function test_tripwire_trips_on_a_write_outside_the_declared_tables(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$leak = true;

        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('write_outside_declared_tables:logstore_standard_log', implode(' ', $result['blockers']));
        $this->assertSame(['logstore_standard_log'], $report->to_array()['features']['toy']['tripwire']);
        $this->assertFalse(legacymap::feature_complete('toy'), 'a tripped run is never marked complete');
        $this->assertSame([], toy_importer::$finalised, 'finalise did not run');
    }

    public function test_tripwire_in_feature_mode_leaves_nothing(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = true;
        toy_importer::$leak = true;

        [$result] = $this->execute(['atomic_threshold' => 50000]);
        $this->assertSame(1, $result['exit']);
        foreach (['local_sentientia_toy_org', 'local_sentientia_toy_item', 'local_sentientia_legacymap',
                  'logstore_standard_log'] as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table} was rolled back with the feature");
        }
        $this->assert_only_a_failure_marker_survives();
    }

    /**
     * Feature mode rolls every step's progress back with the rest, so the runner leaves ONE durable failed row:
     * without it nothing says the feature started, and the status check would stay green. The step rows the run
     * wrote at its start (pending, with the source fingerprint) are not part of that transaction, so they stay,
     * back at pending and with no progress on them.
     */
    private function assert_only_a_failure_marker_survives(): void {
        global $DB;
        $rows = $DB->get_records_select('local_sentientia_legacystep', "status <> 'pending'");
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('toy.__feature', $row->stepkey);
        $this->assertSame('failed', $row->status);

        $pending = $DB->get_records('local_sentientia_legacystep', ['status' => 'pending']);
        $keys = array_values(array_map(fn($r) => $r->stepkey, $pending));
        sort($keys);
        $this->assertSame(['toy.dup', 'toy.fan', 'toy.item', 'toy.order', 'toy.org'], $keys,
            'every load step still has the row the run wrote at its start');
        foreach ($pending as $step) {
            $this->assertNull($step->watermark, $step->stepkey);
            $this->assertSame(0, (int) $step->processed, $step->stepkey);
            $this->assertGreaterThan(0, (int) $step->srccount, $step->stepkey . ' keeps the fingerprint taken at run start');
        }
    }

    public function test_marker_is_set_only_after_verify_and_finalise(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$failverify = true;
        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('verify_failed', implode(' ', $result['blockers']));
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame([], toy_importer::$finalised, 'finalise does not run for a feature that failed verify');

        toy_importer::$failverify = false;
        [$result] = $this->execute();
        $this->assertContains($result['exit'], [0, 2]);
        $this->assertSame(['pending'], toy_importer::$markerseen, 'finalise ran before the marker existed');
        $this->assertTrue(legacymap::feature_complete('toy'));
    }

    public function test_feature_mode_crash_leaves_nothing_and_a_rerun_completes(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = true;
        $failpoint = function (string $key, int $batch): void {
            if ($batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };

        [$failed, $report] = $this->execute(['atomic_threshold' => 50000, 'failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('feature', $report->to_array()['features']['toy']['mode']);
        foreach (['local_sentientia_toy_org', 'local_sentientia_legacymap'] as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table} holds nothing after a crash in feature mode");
        }
        $this->assert_only_a_failure_marker_survives();
        $this->assertSame('failed', $DB->get_field('local_sentientia_legacyrun', 'status', ['id' => $failed['runid']]));

        [$clean] = $this->execute(['atomic_threshold' => 50000]);
        $this->assertSame('complete', $clean['features']['toy']);
    }

    public function test_feature_above_the_atomic_threshold_runs_in_batch_mode_and_says_so(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = true;
        [, $report] = $this->execute(['atomic_threshold' => 1]);
        $section = $report->to_array()['features']['toy'];
        $this->assertSame('batch', $section['mode']);
        $this->assertStringContainsString('above_atomic_threshold', $section['note']);
    }

    public function test_grouped_steps_process_groups_in_order_of_their_minimum_id(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $rows = $DB->get_records('local_sentientia_legacymap', ['sourcetable' => 'local_toy_dup'], 'id', 'id, sourceid');
        $this->assertSame(['1', '3', '5', '2', '4'], array_values(array_map(fn($r) => $r->sourceid, $rows)),
            'group k1 (ids 1, 3, 5) is settled before group k2 (ids 2, 4), whatever the batch size');
        $merged = $DB->get_records('local_sentientia_legacymap', ['sourcetable' => 'local_toy_dup', 'outcome' => 'merged']);
        $this->assertCount(3, $merged);
        foreach ($merged as $row) {
            $this->assertTrue($DB->record_exists('local_sentientia_toy_dup', ['id' => $row->targetid]),
                'a merged row points at the row that won');
        }
    }

    public function test_fan_out_writes_sub_rows_under_one_primary_map_row(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $this->assertSame(3, $DB->count_records('local_sentientia_legacymap', ['sourcetable' => 'local_toy_fan', 'subkey' => '']));
        $this->assertSame(3, $DB->count_records('local_sentientia_legacymap', ['sourcetable' => 'local_toy_fan', 'subkey' => 'part:a']));
        $this->assertSame(6, $DB->count_records('local_sentientia_toy_fanout'));
    }

    public function test_recompute_only_touches_rows_this_run_imported(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 500, 'name' => 'Hand made', 'path' => null,
            'visible' => 0, 'timecreated' => 1, 'timemodified' => 1]);
        [, $report] = $this->execute();
        $this->assertSame(3, $report->to_array()['features']['toy']['steps']['toy.recompute']['counters']['updated']);
        $this->assertSame('0', (string) $DB->get_field('local_sentientia_toy_org', 'visible', ['id' => 500]),
            'a row the import did not create is left alone');
    }

    public function test_dry_run_of_one_feature_reports_unapplied_parents_as_deferred(): void {
        $this->begin([
            new toy_importer('toyorg', [], ['local_toy_org']),
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
        ]);
        $this->seed_toy_data();

        [$result, $report] = $this->execute([], ['toyitem'], false);
        $this->assertSame(0, $result['exit'], 'deferred is not a failure and not needs-owner');
        $counts = $report->to_array()['features']['toyitem']['steps']['toyitem.item']['skipped_by_reason'];
        $this->assertSame(['deferred' => 5], $counts);
    }

    public function test_all_dry_run_resolves_children_against_the_overlay(): void {
        $this->begin([
            new toy_importer('toyorg', [], ['local_toy_org']),
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
        ]);
        $this->seed_toy_data();

        [$result, $report] = $this->execute(['all' => true], [], false);
        $counters = $this->counters($report, 'toyitem')['toyitem.item'];
        $this->assertSame(3, $counters['imported'], 'parents imported earlier in the dry run resolve to virtual ids');
        $this->assertSame(2, $counters['skipped']);
        $this->assertSame(2, $result['exit']);
        $this->assertContains('unclaimed_table:local_toy_dup', $result['unproven'],
            'legacy tables no importer claims make the run unproven');
    }

    public function test_apply_adds_dependencies_and_orders_them_first(): void {
        global $DB;
        $this->begin([
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $this->seed_toy_data();

        [$result] = $this->execute([], ['toyitem']);
        $this->assertSame(['toyorg', 'toyitem'], array_keys($result['features']));
        $this->assertSame(3, $DB->count_records('local_sentientia_toy_item'));

        [$again] = $this->execute([], ['toyitem']);
        $this->assertSame('already_complete', $again['features']['toyorg'], 'a complete dependency is not run again');
    }

    public function test_retry_skipped_reprocesses_only_retryable_reasons(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $orphanuser = $DB->get_record('local_sentientia_legacymap', ['sourcetable' => 'local_toy_item', 'sourceid' => 5]);
        $this->assertSame('skipped', $orphanuser->outcome);

        // The missing user turns up; the missing org never will.
        $DB->import_record('user', (object) ['id' => 987654, 'username' => 'late', 'deleted' => 0]);
        [$result, $report] = $this->execute(['retry_reasons' => ['orphan_user', 'orphan_org']]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $after = $DB->get_record('local_sentientia_legacymap', ['sourcetable' => 'local_toy_item', 'sourceid' => 5]);
        $this->assertSame('imported', $after->outcome);
        $this->assertSame($orphanuser->id, $after->id, 'the skipped map row moved to its new outcome in place');
        $this->assertTrue($DB->record_exists('local_sentientia_toy_item', ['id' => $after->targetid]));
        $stillorphan = $DB->get_record('local_sentientia_legacymap', ['sourcetable' => 'local_toy_item', 'sourceid' => 4]);
        $this->assertSame('skipped', $stillorphan->outcome, 'orphan_org is not retryable, so it was not re-attempted');
    }

    public function test_purge_deletes_only_imported_rows_and_refuses_adopted_ones(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $runner = new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]);
        $purged = $runner->purge('toy');
        $this->assertSame(0, $purged['exit']);
        foreach (['local_sentientia_toy_org', 'local_sentientia_toy_item', 'local_sentientia_legacymap'] as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame(5, $DB->count_records('local_toy_org'), 'the legacy archive is never touched');

        // With an adopted row the purge refuses.
        $DB->import_record('local_sentientia_toy_org', (object) ['id' => 2, 'name' => 'Beta', 'path' => null,
            'visible' => 0, 'timecreated' => self::$toyt0 + 2, 'timemodified' => 7]);
        $this->execute();
        $this->expectException(blocked::class);
        $this->expectExceptionMessage('purge_refused_feature_has_adopted_rows');
        (new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]))->purge('toy');
    }

    public function test_verify_finds_a_source_row_without_a_map_row(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        $runner = new runner(['apply' => false]);
        $this->assertSame(0, $runner->verify(['toy'])['exit']);

        $DB->delete_records('local_sentientia_legacymap', ['sourcetable' => 'local_toy_fan', 'subkey' => '', 'sourceid' => 3]);
        $out = (new runner(['apply' => false]))->verify(['toy']);
        $this->assertSame(1, $out['exit']);
        $this->assertStringContainsString('accounting:local_toy_fan', implode(' ', $out['failures']['toy']));
    }

    public function test_report_and_csv_carry_ids_and_codes_only(): void {
        $this->begin();
        $this->seed_toy_data();
        $csv = tempnam(sys_get_temp_dir(), 'bizlms');
        [, $report] = $this->execute();
        $report->open_csv($csv);
        [, $report] = $this->execute(['report' => $report], [], false);
        $report->close();

        $json = json_encode($report->to_array());
        foreach (['Alpha', 'Beta', 'Delta', 'Short title', 'Orphan user', 'label 1'] as $value) {
            $this->assertStringNotContainsString($value, $json, 'a legacy value leaked into the report');
        }
        $this->assertFileExists($csv);
        unlink($csv);
    }

    public function test_a_fresh_apply_after_a_failed_one_still_runs_the_recompute_step(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.recompute' && $batch === 1) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('0', (string) $DB->get_field('local_sentientia_toy_org', 'visible', ['id' => 1]),
            'the second pass has not run: visible is derived there');

        // NOT --resume: a new run. Every load step finds its rows already mapped; the recompute step must
        // still see them, or the feature is marked complete with its second pass never run.
        [$fresh] = $this->execute();
        $this->assertContains($fresh['exit'], [0, 2], implode('; ', $fresh['blockers']));
        $this->assertNotSame($failed['runid'], $fresh['runid']);
        foreach ([1, 2, 7] as $id) {
            $this->assertSame('1', (string) $DB->get_field('local_sentientia_toy_org', 'visible', ['id' => $id]),
                "org {$id} was recomputed by the fresh run");
        }
        $this->assertTrue(legacymap::feature_complete('toy'));
    }

    public function test_a_merge_into_a_skipped_winner_is_refused(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$skipwinner = true;
        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('merge_winner_has_no_target:toy.dup:1', implode(' ', $result['blockers']),
            'the duplicates would have had their data in no target table while the accounting balanced');
    }

    public function test_a_fold_needs_a_declared_table_and_an_existing_target(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();

        toy_importer::$foldto = ['user', 1];
        [$result] = $this->execute([], [], false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('undeclared_table:user', implode(' ', $result['blockers']));

        toy_importer::$foldto = ['local_sentientia_toy_dup', 999];
        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('fold_target_missing:local_sentientia_toy_dup:999', implode(' ', $result['blockers']));

        $DB->import_record('local_sentientia_toy_dup', (object) ['id' => 999, 'natkey' => 'x', 'label' => 'x',
            'timecreated' => 1, 'timemodified' => 1]);
        [$result] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $fold = $DB->get_record('local_sentientia_legacymap', ['sourcetable' => 'local_toy_fan', 'sourceid' => 1, 'subkey' => '']);
        $this->assertSame('folded', $fold->outcome);
        $this->assertSame('999', (string) $fold->targetid);
    }

    public function test_a_dry_run_folds_into_a_row_a_preserve_step_only_simulated(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();

        // toy.org (PRESERVE) runs before toy.fan. A dry run checks org 7 but does not write it, so a fold into
        // org 7 must not look for the row in the table (the program importer's criteria rows fold into their
        // preserved program the same way). Before the fix this blocked with fold_target_missing.
        toy_importer::$foldto = ['local_sentientia_toy_org', 7];
        [$result] = $this->execute([], [], false);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(0, $DB->count_records('local_sentientia_toy_org'), 'a dry run writes no target row');
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap'), 'a dry run writes no map row');

        // An id no step simulated is still a missing target.
        toy_importer::$foldto = ['local_sentientia_toy_org', 999];
        [$result] = $this->execute([], [], false);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('fold_target_missing:local_sentientia_toy_org:999', implode(' ', $result['blockers']));
    }

    public function test_purge_refuses_a_feature_that_writes_core_tables(): void {
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        toy_importer::$corewrites = true;
        $this->expectException(blocked::class);
        $this->expectExceptionMessage('purge_refused_feature_writes_core:course');
        (new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]))->purge('toy');
    }

    public function test_resume_refuses_when_the_source_of_a_finished_step_changed(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.item' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('done', $DB->get_field('local_sentientia_legacystep', 'status', ['stepkey' => 'toy.org']));

        $DB->import_record('local_toy_org', (object) ['id' => 60, 'name' => 'New', 'parentid' => 0, 'path' => '/1',
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        [$resumed] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.org', implode(' ', $resumed['blockers']),
            'toy.org had finished before the crash, and its source still must not have changed');
    }

    public function test_resume_refuses_after_the_plugin_versions_changed(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $DB->set_field('local_sentientia_legacyrun', 'codehash', str_repeat('0', 64), ['id' => $failed['runid']]);
        [$resumed] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('plugin_versions_changed_since_the_interrupted_run', implode(' ', $resumed['blockers']));
    }

    public function test_a_declared_enum_with_too_many_distinct_values_blocks(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $rows = [];
        for ($i = 100; $i < 1101; $i++) {
            $rows[] = (object) ['id' => $i, 'orgid' => 1, 'userid' => 1, 'title' => 't', 'kind' => 'k' . $i, 'path' => null,
                'timecreated' => 1, 'timemodified' => 1];
        }
        $DB->insert_records('local_toy_item', $rows);
        [$result] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('too_many_distinct_values:local_toy_item.kind', implode(' ', $result['blockers']));
    }

    public function test_resume_meets_the_step_rows_of_a_feature_that_was_not_applicable(): void {
        global $DB;
        $this->begin([
            new toy_importer('toyfan', [], ['local_toy_fan']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $this->seed_toy_data();
        self::drop_legacy_table('local_toy_fan');
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toyorg.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('not_applicable', $DB->get_field('local_sentientia_legacystep', 'status', ['stepkey' => 'toyfan.fan']));

        [$resumed] = $this->execute(['resume' => true]);
        $this->assertContains($resumed['exit'], [0, 2], implode('; ', $resumed['blockers']));
        $this->assertSame('not_applicable', $resumed['features']['toyfan']);
        $this->assertSame('complete', $resumed['features']['toyorg']);
        $this->assertSame(1, $DB->count_records('local_sentientia_legacystep', ['stepkey' => 'toyfan.fan']),
            'the not_applicable row is written once, not once per attempt');
    }

    public function test_source_change_after_a_crash_makes_resume_refuse(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $step = $DB->get_record('local_sentientia_legacystep', ['stepkey' => 'toy.org']);
        $this->assertSame('failed', $step->status);
        $this->assertSame('RuntimeException', $step->error, 'only the class name is stored for a foreign exception');
        $this->assertGreaterThan(0, (int) $step->watermark, 'the first batch committed');

        $DB->import_record('local_toy_org', (object) ['id' => 60, 'name' => 'New', 'parentid' => 0, 'path' => '/1',
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        [$resumed] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.org', implode(' ', $resumed['blockers']));
    }

    // Run-start fingerprints: a step that had not started when the run stopped is covered too.

    /**
     * One more row in local_toy_dup, the source of toy.dup (a step that is the third of five).
     *
     * @return void
     */
    private function add_a_dup_row(): void {
        global $DB;
        $DB->import_record('local_toy_dup', (object) ['id' => 6, 'natkey' => 'k3', 'label' => 'label 6',
            'timecreated' => self::$toyt0 + 6]);
    }

    /**
     * A failpoint that stops the run in the second batch of the first step: toy.org (five rows, batches of two).
     *
     * @return \Closure
     */
    private function crash_in_the_first_step(): \Closure {
        return function (string $key, int $batch): void {
            if ($key === 'toy.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
    }

    /**
     * A step that never started has a row from the first minute of the run, with the source as it was then.
     * Fails on the old code: no row exists for toy.dup, so $row is false.
     */
    public function test_a_new_run_writes_a_pending_row_with_the_source_fingerprint_for_every_load_step(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$failed] = $this->execute(['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);

        $expected = ['toy.org' => 5, 'toy.item' => 5, 'toy.dup' => 5, 'toy.order' => 6, 'toy.fan' => 3];
        foreach ($expected as $key => $rows) {
            $row = $DB->get_record('local_sentientia_legacystep', ['runid' => $failed['runid'], 'stepkey' => $key]);
            $this->assertNotFalse($row, "{$key} has a row although the run stopped before it");
            $this->assertSame($key === 'toy.org' ? 'failed' : 'pending', $row->status, $key);
            $this->assertSame($rows, (int) $row->srccount, $key);
        }
        $dup = $DB->get_record('local_sentientia_legacystep', ['stepkey' => 'toy.dup']);
        $this->assertSame(5, (int) $dup->srcmaxid);
        $this->assertNull($dup->watermark, 'pending: not started');
        $this->assertSame(0, (int) $dup->processed);
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['stepkey' => 'toy.recompute']),
            'a recompute step has no source to fingerprint');
    }

    /**
     * Fails on the old code: the resume fingerprints toy.dup afresh when it opens, so the row inserted while the run
     * was down looks like the source the run started with, and the run completes (exit 0 or 2, not 1).
     */
    public function test_resume_refuses_when_the_source_of_a_step_that_had_not_started_changed(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$failed] = $this->execute(['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('pending', $DB->get_field('local_sentientia_legacystep', 'status', ['stepkey' => 'toy.dup']));

        $this->add_a_dup_row();
        [$resumed] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertSame('failed', $resumed['status']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.dup', implode(' ', $resumed['blockers']));
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame('pending', $DB->get_field('local_sentientia_legacystep', 'status', ['stepkey' => 'toy.dup']),
            'refused at the door: the step never started');
        $this->assertSame(0, $DB->count_records('local_sentientia_toy_dup'), 'nothing of toy.dup was imported');
    }

    /**
     * The same, in feature mode: the crash rolls the whole feature back, and only the rows written at run start
     * remember what the source looked like. Fails on the old code: the rollback removed every step row, the resume
     * fingerprinted afresh and completed.
     */
    public function test_resume_after_a_feature_mode_rollback_still_refuses_a_changed_source(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = true;
        $atomic = ['atomic_threshold' => 50000];
        [$failed] = $this->execute($atomic + ['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);
        $this->assert_only_a_failure_marker_survives();
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap'), 'the feature rolled back');

        $this->add_a_dup_row();
        [$resumed] = $this->execute($atomic + ['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.dup', implode(' ', $resumed['blockers']));
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap'), 'and rolled back again');
        $this->assertSame(1, $DB->count_records('local_sentientia_legacystep', ['stepkey' => 'toy.__feature']),
            'the failure marker is written once for the run');
    }

    /**
     * Fails on the old code (no pending rows to assert on, and nothing to compare): with no change at all a resume
     * must give exactly the rows of a clean run, with every pending row used up.
     */
    public function test_resume_with_no_source_change_gives_the_rows_of_a_clean_run(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$clean] = $this->execute();
        $this->assertContains($clean['exit'], [0, 2], implode('; ', $clean['blockers']));
        $expected = $this->import_signature();
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['status' => 'pending']),
            'a clean run uses every pending row');
        $this->clear_import();

        [$failed] = $this->execute(['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame(4, $DB->count_records('local_sentientia_legacystep', ['status' => 'pending']),
            'toy.item, toy.dup, toy.order and toy.fan had not started');

        [$resumed] = $this->execute(['resume' => true]);
        $this->assertContains($resumed['exit'], [0, 2], implode('; ', $resumed['blockers']));
        $this->assertSame('complete', $resumed['features']['toy']);
        $this->assertSame($expected, $this->import_signature(), 'resume gives the rows of a clean run');
        $this->assertSame(1, $DB->count_records('local_sentientia_legacyrun'), 'resume continues the same run');
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['status' => 'pending']));
        $rows = $DB->get_records('local_sentientia_legacystep', null, 'stepkey', 'stepkey, status, srccount');
        $this->assertSame(['toy.dup', 'toy.fan', 'toy.item', 'toy.order', 'toy.org', 'toy.recompute'], array_keys($rows));
        foreach ($rows as $row) {
            $this->assertSame('done', $row->status, $row->stepkey);
        }
        $this->assertSame(5, (int) $rows['toy.dup']->srccount, 'the fingerprint of the step is the one from run start');
    }

    /**
     * A source that changes inside one uninterrupted run, after the run started and before its step opens: the
     * failpoint stands in for a person editing the legacy table while the import is running. Fails on the old code:
     * the step fingerprints afresh when it opens, sees the new row as its source and the run completes.
     */
    public function test_a_source_changed_during_the_run_is_refused_when_its_step_opens(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $changed = false;
        $edit = function (string $key, int $batch) use (&$changed): void {
            if ($key === 'toy.org' && $batch === 1 && !$changed) {
                $changed = true;
                $this->add_a_dup_row();
            }
        };
        [$result] = $this->execute(['failpoint' => $edit]);
        $this->assertTrue($changed);
        $this->assertSame(1, $result['exit']);
        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.dup', implode(' ', $result['blockers']));
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame('failed', $DB->get_field('local_sentientia_legacyrun', 'status', ['id' => $result['runid']]));

        $states = $DB->get_records_menu('local_sentientia_legacystep', ['runid' => $result['runid']], '', 'stepkey, status');
        ksort($states);
        $this->assertSame(['toy.dup' => 'pending', 'toy.fan' => 'pending', 'toy.item' => 'done', 'toy.order' => 'pending',
            'toy.org' => 'done'], $states, 'the steps before the changed one ran, it and the later ones never started');
    }

    /**
     * An EDITED row leaves the count and the highest id as they were: only the CRC sees it. MySQL and MariaDB only
     * (fingerprint::table() returns no CRC elsewhere, and the old and new code alike then cannot see such an edit).
     * Fails on the old code, like the insert above.
     */
    public function test_an_edited_row_in_a_later_source_is_caught_by_the_crc(): void {
        global $DB;
        if ($DB->get_dbfamily() !== 'mysql') {
            $this->markTestSkipped('the CRC fingerprint exists on MySQL and MariaDB only');
        }
        $this->begin();
        $this->seed_toy_data();
        [$failed] = $this->execute(['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);

        $DB->set_field('local_toy_fan', 'title', 'fixed by hand while the run was down', ['id' => 2]);
        [$resumed] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('source_changed_since_the_run_started:toy.fan', implode(' ', $resumed['blockers']));
    }

    /**
     * Guard against the obvious wrong implementation: a run looks only at the rows of ITS OWN run. After a completed
     * run the source may have grown, and the next fresh run starts from the source as it is. (This passes on the old
     * code too; it fails on an implementation that compares with the newest stored fingerprint of the step key.)
     */
    public function test_a_fresh_run_after_a_completed_run_is_not_blocked_by_the_earlier_fingerprints(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$first] = $this->execute();
        $this->assertContains($first['exit'], [0, 2], implode('; ', $first['blockers']));

        $this->add_a_dup_row();
        [$second] = $this->execute();
        $this->assertContains($second['exit'], [0, 2], implode('; ', $second['blockers']));
        $this->assertSame('complete', $second['features']['toy']);
        $this->assertNotSame($first['runid'], $second['runid']);
        $this->assertSame(5, (int) $DB->get_field('local_sentientia_legacystep', 'srccount',
            ['runid' => $first['runid'], 'stepkey' => 'toy.dup']), 'the first run keeps what it saw');
        $this->assertSame(6, (int) $DB->get_field('local_sentientia_legacystep', 'srccount',
            ['runid' => $second['runid'], 'stepkey' => 'toy.dup']), 'the second run saw the new row from its start');
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['runid' => $second['runid'], 'status' => 'pending']));
    }

    /**
     * Same guard for a run that crashed and was abandoned: its pending rows stay behind as history, and a new run
     * (not --resume) neither reads nor reuses them.
     */
    public function test_a_fresh_run_after_an_abandoned_run_is_not_blocked_by_its_pending_rows(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        [$failed] = $this->execute(['failpoint' => $this->crash_in_the_first_step()]);
        $this->assertSame(1, $failed['exit']);

        $this->add_a_dup_row();
        [$fresh] = $this->execute();
        $this->assertContains($fresh['exit'], [0, 2], implode('; ', $fresh['blockers']));
        $this->assertSame('complete', $fresh['features']['toy']);
        $this->assertSame(4, $DB->count_records('local_sentientia_legacystep', ['runid' => $failed['runid'], 'status' => 'pending']),
            'the abandoned run keeps its rows');
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['runid' => $fresh['runid'], 'status' => 'pending']));
    }

    /**
     * A feature added to the run only as a dependency, and complete already, runs no step, so it gets no row and a
     * change in its source does not stop the run that merely needs it to be there.
     */
    public function test_a_complete_dependency_added_to_a_new_run_gets_no_fingerprint(): void {
        global $DB;
        $this->begin([
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $this->seed_toy_data();
        [$first] = $this->execute([], ['toyitem']);
        $this->assertContains($first['exit'], [0, 2], implode('; ', $first['blockers']));

        $DB->import_record('local_toy_org', (object) ['id' => 60, 'name' => 'New', 'parentid' => 0, 'path' => '/1',
            'status' => 1, 'timecreated' => 1, 'timemodified' => 1]);
        [$again, $report] = $this->execute([], ['toyitem']);
        $this->assertContains($again['exit'], [0, 2], implode('; ', $again['blockers']));
        $this->assertSame('already_complete', $again['features']['toyorg']);
        $this->assertSame(0, $DB->count_records('local_sentientia_legacystep', ['runid' => $again['runid'], 'feature' => 'toyorg']));
        $this->assertSame(1, $report->to_array()['meta']['run_start_fingerprints'], 'only toyitem.item');
    }

    /**
     * A step row that is only pending says nothing about the feature having started: the status facts and the
     * status check must not treat a run's rows for features it never reached as half-imported work.
     */
    public function test_pending_rows_do_not_count_as_a_started_feature(): void {
        global $DB;
        $this->begin([
            new toy_importer('toyorg', [], ['local_toy_org']),
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
        ]);
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toyorg.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);
        $this->assertSame('pending', $DB->get_field('local_sentientia_legacystep', 'status', ['stepkey' => 'toyitem.item']));

        $states = runner::feature_states(registry::load());
        $this->assertTrue($states['toyorg']['started']);
        $this->assertSame(0, $states['toyorg']['pending_steps']);
        $this->assertFalse($states['toyitem']['started'], 'a pending row is not a started feature');
        $this->assertSame(1, $states['toyitem']['pending_steps']);
        $this->assertSame(0, $states['toyitem']['running_steps']);

        $summary = (new \local_sentientia_platform\check\bizlms_import())->get_result()->get_summary();
        $this->assertStringContainsString('toyorg', $summary);
        $this->assertStringNotContainsString('toyitem', $summary, 'toyitem never started');
    }

    /**
     * Rows of every toy target table plus the map, as a comparable signature that leaves out ids.
     *
     * @return array
     */
    private function import_signature(): array {
        global $DB;
        $signature = ['targets' => [], 'map' => []];
        foreach ((new toy_importer())->target_tables() as $table) {
            $signature['targets'][$table] = $DB->count_records($table);
        }
        $rows = $DB->get_records('local_sentientia_legacymap', null, 'sourcetable, sourceid, subkey',
            'id, sourcetable, sourceid, subkey, targettable, outcome, reason');
        foreach ($rows as $row) {
            $signature['map'][] = implode('|', [$row->sourcetable, $row->sourceid, $row->subkey, $row->targettable,
                $row->outcome, (string) $row->reason]);
        }
        return $signature;
    }

    /**
     * Put the toy targets, the map and the run bookkeeping back to empty between two runs of one test.
     *
     * @return void
     */
    private function clear_import(): void {
        global $DB;
        foreach ((new toy_importer())->target_tables() as $table) {
            $DB->delete_records($table);
        }
        $DB->delete_records('local_sentientia_legacymap');
        $DB->delete_records('local_sentientia_legacystep');
        $DB->delete_records('local_sentientia_legacyrun');
        unset_config('bizlms_complete_toy', 'local_sentientia_platform');
    }

    public function test_a_decision_the_owner_has_not_accepted_blocks_the_feature_that_declares_it(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$extradecisions = [new decision('toy.credit', 'How a legacy balance is honoured')];

        // The importer's default must not stand in for a choice that is still open. This one has no default,
        // and would only block as "missing" if the file did not carry it at all.
        $open = decisions::from_array(['toy.credit' => 'frozen_pending_finance'], ['toy.credit' => 'finance-confirm']);
        [$result, $report] = $this->execute(['decisions' => $open]);
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('decision_not_accepted:toy.credit:finance-confirm', implode(' ', $result['blockers']));
        $this->assertStringNotContainsString('missing_decision', implode(' ', $result['blockers']));
        $this->assertSame(['toy.credit' => 'finance-confirm'], $report->to_array()['meta']['decisions_not_accepted']);

        // The same key accepted lets the run go.
        [$result] = $this->execute(['decisions' => decisions::from_array(['toy.credit' => 'frozen_pending_finance'])]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
    }

    public function test_the_owners_value_wins_over_the_importers_default(): void {
        $this->begin();
        $ctx = \local_sentientia_platform\bizlms\context::build(new toy_importer(), true, 0, decisions::none());
        $this->assertSame('half_up', $ctx->decision('toy.rounding'), 'no decision: the importer default');

        $ctx = \local_sentientia_platform\bizlms\context::build(new toy_importer(), true, 0,
            decisions::from_array(['toy.rounding' => 'half_even']));
        $this->assertSame('half_even', $ctx->decision('toy.rounding'), 'the owner chose otherwise');

        $ctx = \local_sentientia_platform\bizlms\context::build(new toy_importer(), true, 0,
            decisions::from_array(['toy.rounding' => 'half_even'], ['toy.rounding' => 'finance-confirm']));
        $this->expectException(blocked::class);
        $this->expectExceptionMessage('decision_not_accepted:toy.rounding:finance-confirm');
        $ctx->decision('toy.rounding');
    }

    public function test_enum_values_that_differ_only_in_case_are_different_values(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        // A *_ci collation would fold this into the allowed value 'a' and the histogram would show one group.
        $DB->import_record('local_toy_item', (object) ['id' => 9, 'orgid' => 1, 'userid' => 1, 'title' => 'Shouting',
            'kind' => 'A', 'path' => null, 'timecreated' => 1, 'timemodified' => 1]);
        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('unknown_enum:local_toy_item.kind=A', implode(' ', $result['blockers']));
        $histogram = $report->to_array()['features']['toy']['preflight']['histograms']['local_toy_item.kind'];
        $this->assertSame(1, $histogram['A']);
        $this->assertSame(3, $histogram['a'], 'the three seeded a items are not merged with the variant');
    }

    // Deferral, dependencies.

    public function test_deferred_is_a_dry_run_reason_and_an_apply_run_refuses_it(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$forcedeferred = true;

        [$dry] = $this->execute([], [], false);
        $this->assertSame(0, $dry['exit'], 'a dry run may report a row as deferred');

        [$apply] = $this->execute();
        $this->assertSame(1, $apply['exit'], 'an apply run stores no row as deferred for good');
        $this->assertStringContainsString('deferred_outcome_in_apply:toy.item:1', implode(' ', $apply['blockers']));
        $this->assertFalse(legacymap::feature_complete('toy'));
        $this->assertSame(0, $DB->count_records('local_sentientia_legacymap', ['reason' => 'deferred']));
    }

    public function test_a_feature_that_reads_another_features_table_must_depend_on_it(): void {
        $this->begin([
            new toy_importer('toyitem', [], ['local_toy_item']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $this->seed_toy_data();

        // toyitem resolves ids of local_toy_org, which toyorg owns, and does not depend on it. The alphabetical
        // tie-break runs toyitem first, so every parent would be missing and every row an orphan.
        foreach ([true, false] as $apply) {
            [$result] = $this->execute(['all' => true], [], $apply);
            $this->assertSame(1, $result['exit'], $apply ? 'apply' : 'dry run');
            $this->assertStringContainsString('undeclared_dependency:toyitem->toyorg:local_toy_org',
                implode(' ', $result['blockers']));
        }
        $this->assertFalse(legacymap::feature_complete('toyitem'));
    }

    public function test_a_declared_dependency_lets_the_reader_run(): void {
        $this->begin([
            new toy_importer('toyitem', ['toyorg'], ['local_toy_item']),
            new toy_importer('toyorg', [], ['local_toy_org']),
        ]);
        $this->seed_toy_data();
        [$result] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['toyitem']);
    }

    // Side effects.

    /**
     * @dataProvider run_mode_provider
     * @param bool $atomic Run the feature in one outer transaction.
     */
    public function test_tripwire_catches_an_event_fired_through_a_core_api(bool $atomic): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        // The standard log is an observer that runs after the outermost commit and buffers its rows.
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        get_log_manager(true);
        \core\event\dashboard_viewed::create(['context' => \context_system::instance()])->trigger();
        get_log_manager(true);
        if (!$DB->record_exists('logstore_standard_log', ['eventname' => '\\core\\event\\dashboard_viewed'])) {
            $this->markTestSkipped('the standard log store does not write in this environment, so the tripwire has nothing to see');
        }
        $DB->delete_records('logstore_standard_log');

        toy_importer::$fireevent = true;
        toy_importer::$atomic = $atomic;
        [$result, $report] = $this->execute($atomic ? ['atomic_threshold' => 50000] : []);
        $this->assertSame($atomic ? 'feature' : 'batch', $report->to_array()['features']['toy']['mode']);
        $this->assertSame(1, $result['exit'], 'an event fired through a core API is a side effect');
        $this->assertStringContainsString('write_outside_declared_tables:logstore_standard_log', implode(' ', $result['blockers']));
        $this->assertContains('logstore_standard_log', $report->to_array()['features']['toy']['tripwire']);
        $this->assertFalse(legacymap::feature_complete('toy'), 'a tripped feature never gets a marker');
        $this->assertSame([], toy_importer::$finalised);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function run_mode_provider(): array {
        return ['batch mode' => [false], 'feature mode' => [true]];
    }

    // Tenant resolution reads what the org importer writes.

    public function test_a_feature_that_reads_organisations_must_depend_on_the_org_feature(): void {
        $this->begin([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyfan', [], ['local_toy_fan']),
        ]);
        $this->seed_toy_data();
        // No tenant column, so the registry's rule has nothing to see; the code still asks whether organisations
        // exist. Ordered by name, org runs first here by luck: the rule is on the dependency, not on the order.
        toy_importer::$notenantcolumns = true;
        toy_importer::$readorgs = true;

        foreach ([true, false] as $apply) {
            [$result] = $this->execute(['all' => true], [], $apply);
            $this->assertSame(1, $result['exit'], $apply ? 'apply' : 'dry run');
            $this->assertStringContainsString('undeclared_dependency:toyfan->org:organisations', implode(' ', $result['blockers']));
        }
        $this->assertFalse(legacymap::feature_complete('toyfan'));
    }

    public function test_a_declared_dependency_on_the_org_feature_lets_the_reader_read_organisations(): void {
        $this->begin([
            new toy_importer('org', [], ['local_toy_org']),
            new toy_importer('toyfan', ['org'], ['local_toy_fan']),
        ]);
        $this->seed_toy_data();
        toy_importer::$notenantcolumns = true;
        toy_importer::$readorgs = true;

        [$result] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['toyfan']);
    }

    // The tripwire sticks.

    /**
     * Trip the tripwire in run 1 with a leak in verify(), then make the leak go away.
     *
     * @param bool $atomic Feature mode (the trip is inside the outer transaction, which rolls back).
     * @return int The run that tripped.
     */
    private function trip_once(bool $atomic = false): int {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = $atomic;
        toy_importer::$leak = true;
        [$tripped] = $this->execute($atomic ? ['atomic_threshold' => 50000] : []);
        $this->assertSame(1, $tripped['exit']);
        $this->assertStringContainsString('write_outside_declared_tables', implode(' ', $tripped['blockers']));
        toy_importer::$leak = false;
        return (int) $tripped['runid'];
    }

    /**
     * @dataProvider run_mode_provider
     * @param bool $atomic Run the feature in one outer transaction.
     */
    public function test_a_tripped_tripwire_is_recorded_and_a_plain_reapply_is_refused(bool $atomic): void {
        $runid = $this->trip_once($atomic);
        $this->assertGreaterThan(0, $runid);
        $this->assertSame($runid, legacymap::tripped_run('toy'), 'recorded after any rollback, so it outlives it');
        $this->assertFalse(legacymap::feature_complete('toy'));

        // The leak is gone. In batch mode every row the tripped run wrote is committed and mapped, so without the
        // record a fresh snapshot would find the tripwire clean and the feature would be marked complete.
        foreach (['a plain re-apply' => [], 'a resume' => ['resume' => true]] as $what => $options) {
            [$again] = $this->execute($options);
            $this->assertSame(1, $again['exit'], $what);
            $this->assertSame('blocked', $again['status'], $what);
            $this->assertStringContainsString("tripwire_tripped_earlier:toy:run {$runid}", implode(' ', $again['blockers']), $what);
            $this->assertFalse(legacymap::feature_complete('toy'), $what);
        }
        // A dry run shows the block too, and still writes nothing.
        [$dry] = $this->execute([], [], false);
        $this->assertSame('blocked', $dry['status']);
        $this->assertSame($runid, legacymap::tripped_run('toy'));
    }

    public function test_an_operator_can_acknowledge_a_trip_in_a_rehearsal_and_the_feature_then_completes(): void {
        $runid = $this->trip_once();

        [$wrong] = $this->execute(['acknowledge_tripwire' => $runid + 1]);
        $this->assertSame('blocked', $wrong['status'], 'only the run that tripped can be acknowledged');

        [$ok, $report] = $this->execute(['acknowledge_tripwire' => $runid]);
        $this->assertContains($ok['exit'], [0, 2], implode('; ', $ok['blockers']));
        $this->assertSame('complete', $ok['features']['toy']);
        $this->assertTrue(legacymap::feature_complete('toy'));
        $this->assertSame(0, legacymap::tripped_run('toy'), 'the trip ends with the completed feature');
        $this->assertContains("tripwire_acknowledged:toy:run {$runid}",
            $report->to_array()['features']['toy']['preflight']['warnings']);
    }

    public function test_a_trip_is_never_acknowledged_in_production(): void {
        $runid = $this->trip_once();
        set_config('bizlms_production', 1, 'local_sentientia_platform');

        [$result] = $this->execute(['acknowledge_tripwire' => $runid]);
        $this->assertSame('blocked', $result['status']);
        $this->assertStringContainsString("tripwire_tripped_earlier:toy:run {$runid}", implode(' ', $result['blockers']));
        $this->assertSame($runid, legacymap::tripped_run('toy'));
    }

    public function test_a_rehearsal_purge_clears_a_trip(): void {
        $runid = $this->trip_once();
        $this->assertSame($runid, legacymap::tripped_run('toy'));

        (new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]))->purge('toy');
        $this->assertSame(0, legacymap::tripped_run('toy'));

        [$result] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['toy']);
    }

    public function test_the_trip_shows_in_the_status_facts_and_the_status_check(): void {
        $runid = $this->trip_once();
        $states = runner::feature_states(registry::load());
        $this->assertSame($runid, $states['toy']['tripped_runid']);
        $this->assertSame(0, $states['toy']['complete_runid']);
        // A feature that started and has no marker is unfinished, so the status check is critical.
        $result = (new \local_sentientia_platform\check\bizlms_import())->get_result();
        $this->assertSame(\core\check\result::CRITICAL, $result->get_status());
    }

    public function test_finalise_side_effects_are_checked_before_the_marker(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$finaliseleak = true;

        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('write_outside_declared_tables:logstore_standard_log', implode(' ', $result['blockers']));
        $this->assertContains('logstore_standard_log', $report->to_array()['features']['toy']['tripwire']);
        $this->assertFalse(legacymap::feature_complete('toy'), 'finalise ran, its leak was seen, and no marker was written');
        $this->assertSame(['toy'], toy_importer::$finalised, 'finalise did run: this is the look after it');
        $this->assertGreaterThan(0, legacymap::tripped_run('toy'));
    }

    // File copies (IDN-04, signed key framework.file_rehome_copies).

    public function test_a_file_written_by_an_importer_without_the_marker_trips_the_tripwire(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$writefile = ['local_sentientia_platform', 'toytarget'];

        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('write_outside_declared_tables:files', implode(' ', $result['blockers']));
        $this->assertContains('files', $report->to_array()['features']['toy']['tripwire']);
        $this->assertFalse(legacymap::feature_complete('toy'), 'a tripped feature never gets a marker');
        $this->assertGreaterThan(0, legacymap::tripped_run('toy'));
        $this->assertTrue($DB->record_exists('files', ['filearea' => 'toytarget']), 'the row is committed: the snapshot is the way back');
    }

    public function test_an_importer_with_the_marker_may_copy_into_its_declared_area_and_the_report_counts_it(): void {
        $this->begin([new toy_files_importer()]);
        $this->seed_toy_data();
        toy_importer::$writefile = ['local_sentientia_platform', 'toytarget'];

        [$result, $report] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features']['toy']);
        $feature = $report->to_array()['features']['toy'];
        $this->assertSame('clean', $feature['tripwire']);
        // One file; the directory row the file API creates beside it is not counted as a copy.
        $this->assertSame(['local_sentientia_platform/toytarget' => 1], $feature['files_copied']);
    }

    public function test_an_importer_with_the_marker_trips_on_a_file_in_any_other_area(): void {
        $this->begin([new toy_files_importer()]);
        $this->seed_toy_data();
        toy_importer::$writefile = ['local_sentientia_platform', 'elsewhere'];

        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('write_outside_declared_tables:files:local_sentientia_platform/elsewhere',
            implode(' ', $result['blockers']));
        $this->assertContains('files:local_sentientia_platform/elsewhere', $report->to_array()['features']['toy']['tripwire']);
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    public function test_an_importer_with_the_marker_that_copies_nothing_reports_zero(): void {
        $this->begin([new toy_files_importer()]);
        $this->seed_toy_data();

        [$result, $report] = $this->execute();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame(['local_sentientia_platform/toytarget' => 0],
            $report->to_array()['features']['toy']['files_copied']);
    }

    public function test_a_rehearsal_purge_leaves_the_copies_in_place(): void {
        global $DB;
        $this->begin([new toy_files_importer()]);
        $this->seed_toy_data();
        toy_importer::$writefile = ['local_sentientia_platform', 'toytarget'];
        [$first] = $this->execute();
        $this->assertSame('complete', $first['features']['toy']);

        // A copy is not a core write, so the purge is allowed and does not touch it.
        (new runner(['apply' => true, 'permit' => guard::test_permit(guard_permit::PURGE)]))->purge('toy');
        $this->assertTrue($DB->record_exists('files', ['filearea' => 'toytarget', 'filename' => 'toy.txt']));
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    public function test_an_importer_with_a_malformed_file_declaration_is_refused_by_the_registry(): void {
        $this->begin([new toy_files_importer()]);
        toy_importer::$fileareas = [['local_toy', 'source', 'local_sentientia_platform']];
        $this->expectException(\local_sentientia_platform\bizlms\registry_error::class);
        $this->expectExceptionMessage('file_areas_malformed:toy');
        registry::load();
    }

    public function test_a_blocked_decision_asked_for_inside_an_importers_preflight_is_a_blocker_not_a_crash(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$extradecisions = [new decision('toy.credit', 'How a legacy balance is honoured')];
        toy_importer::$preflightdecision = true;

        // Open (finance-confirm): the decision loop blocks on it AND the importer's own ask throws blocked() with
        // the same line. Neither may escape, and the line is listed once.
        $open = decisions::from_array(['toy.credit' => 'frozen_pending_finance'], ['toy.credit' => 'finance-confirm']);
        [$result] = $this->execute(['decisions' => $open]);
        $this->assertSame(1, $result['exit']);
        $this->assertSame(1, substr_count(implode(' ', $result['blockers']), 'decision_not_accepted:toy.credit:finance-confirm'));

        // Absent from the file: required, no default, so the same.
        [$missing] = $this->execute(['decisions' => decisions::none()]);
        $this->assertSame(1, $missing['exit']);
        $this->assertSame(1, substr_count(implode(' ', $missing['blockers']), 'missing_decision:toy.credit'));
    }

    public function test_a_dry_run_reports_what_changed_under_it_but_does_not_fail(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$dryleak = true;

        [$leaky, $leakyreport] = $this->execute([], [], false);
        // A row in the log table appeared during a dry run, which must write nothing: the dry run says so.
        $this->assertContains('logstore_standard_log', $leakyreport->to_array()['features']['toy']['dry_run_tripwire']);
        $this->assertContains($leaky['exit'], [0, 2], 'reported, not fatal (2 is the orphan rows nobody accepted): an online site has other writers');

        toy_importer::$dryleak = false;
        [, $report] = $this->execute([], [], false);
        $this->assertSame('clean', $report->to_array()['features']['toy']['dry_run_tripwire']);
    }

    // Writer and steps.

    public function test_a_map_insert_into_a_table_a_preserve_step_owns_is_refused(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$mappreserve = true;

        foreach ([true, false] as $apply) {
            [$result] = $this->execute([], [], $apply);
            $this->assertSame(1, $result['exit'], $apply ? 'apply' : 'dry run');
            $this->assertStringContainsString('map_insert_into_a_preserve_table:local_sentientia_toy_org',
                implode(' ', $result['blockers']));
        }
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    public function test_retry_skipped_refuses_a_grouped_step_instead_of_skipping_it_silently(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$dupunclear = true;
        [$first] = $this->execute();
        $this->assertContains($first['exit'], [0, 2], implode('; ', $first['blockers']));

        [$retry] = $this->execute(['retry_reasons' => ['dup_unclear']]);
        $this->assertSame(1, $retry['exit']);
        $this->assertStringContainsString('retry_skipped_is_not_supported_for_a_grouped_or_derived_step:toy.dup',
            implode(' ', $retry['blockers']));
    }

    public function test_a_source_filter_that_leaves_rows_out_fails_the_accounting(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$itemfilter = true;
        [$result, $report] = $this->execute();
        $this->assertSame(1, $result['exit']);
        $this->assertStringContainsString('verify_failed', implode(' ', $result['blockers']));
        $this->assertStringContainsString('unmapped_rows:local_toy_item: table=5 mapped=4',
            implode(' ', $report->to_array()['features']['toy']['verify_failures']),
            'the filtered identity balances (4 = 4); the row it filtered away has no map row');
        $this->assertFalse(legacymap::feature_complete('toy'));
    }

    public function test_verify_hands_the_importer_a_context_that_is_not_a_dry_run(): void {
        $this->begin();
        $this->seed_toy_data();
        $this->execute();
        toy_importer::$verifyseen = [];
        (new runner(['apply' => false]))->verify(['toy']);
        $this->assertSame([false], toy_importer::$verifyseen,
            'parity builds its contexts with dryrun = false, so --verify must as well');
    }

    // Report.

    public function test_a_rolled_back_batch_leaves_no_line_in_the_csv_and_resume_lists_each_row_once(): void {
        $this->begin();
        $this->seed_toy_data();
        $csv = make_request_directory() . '/notimported.csv';
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.item' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        $report = new report();
        $report->open_csv($csv);
        $failed = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'report' => $report, 'batch' => 2,
            'atomic_threshold' => 0, 'failpoint' => $failpoint]))->run([]);
        $report->close();
        $this->assertSame(1, $failed['exit']);
        $lines = file($csv, FILE_IGNORE_NEW_LINES);
        $this->assertNotContains('toy,local_toy_item,4,,skipped,orphan_org,', $lines,
            'batch 2 (items 3 and 4) rolled back, so item 4 was not skipped by anything');
        $this->assertContains('toy,local_toy_org,3,,skipped,no_name,', $lines, 'batch 2 of toy.org committed');

        $report = new report();
        $report->open_csv($csv, true);
        $resumed = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'resume' => true, 'report' => $report,
            'batch' => 2, 'atomic_threshold' => 0]))->run([]);
        $report->close();
        $this->assertContains($resumed['exit'], [0, 2], implode('; ', $resumed['blockers']));
        $lines = file($csv, FILE_IGNORE_NEW_LINES);
        $this->assertSame(1, count(array_keys($lines, 'toy,local_toy_item,4,,skipped,orphan_org,')),
            'the resumed run listed the row once');
        $this->assertSame(1, count(array_keys($lines, 'toy,local_toy_org,3,,skipped,no_name,')), 'and no row twice');
    }

    public function test_the_csv_is_rewritten_from_the_map_when_an_apply_run_ends(): void {
        global $DB;
        $this->begin();
        $this->seed_toy_data();
        $csv = make_request_directory() . '/notimported.csv';
        // A crash between a commit and the write of its lines: whatever the streamed file holds, the map is the truth.
        $damage = function (string $key, int $batch) use ($csv): void {
            if ($key === 'toy.org' && $batch === 1) {
                file_put_contents($csv, "not a line of the report\n");
            }
        };
        $report = new report();
        $report->open_csv($csv);
        $result = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'report' => $report, 'batch' => 2,
            'atomic_threshold' => 0, 'failpoint' => $damage]))->run([]);
        $report->close();
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));

        $lines = file($csv, FILE_IGNORE_NEW_LINES);
        $this->assertSame('feature,sourcetable,sourceid,subkey,outcome,reason,detail', $lines[0]);
        $this->assertNotContains('not a line of the report', $lines);
        $expected = [];
        foreach ($DB->get_records_select('local_sentientia_legacymap', "outcome <> 'imported' AND outcome <> 'adopted'",
                null, 'id') as $row) {
            $expected[] = [$row->feature, $row->sourcetable, (string) $row->sourceid, (string) $row->subkey, $row->outcome,
                (string) $row->reason, (string) $row->detail];
        }
        $this->assertNotEmpty($expected, 'the toy data has rows that are not imported');
        $this->assertSame($expected, array_map('str_getcsv', array_slice($lines, 1)),
            'one line per map row that was not imported, in the order the map was written');
        $this->assertSame(1, count(array_keys($lines, 'toy,local_toy_org,3,,skipped,no_name,')));
    }

    public function test_a_dry_run_keeps_the_csv_it_streamed(): void {
        $this->begin();
        $this->seed_toy_data();
        $dry = make_request_directory() . '/dry.csv';
        $report = new report();
        $report->open_csv($dry);
        (new runner(['apply' => false, 'report' => $report, 'batch' => 2]))->run([]);
        $report->close();
        $this->assertGreaterThan(1, count(file($dry)), 'a dry run has no map to rebuild from, so it keeps its lines');
    }

    public function test_feature_mode_lists_nothing_for_a_feature_that_rolled_back(): void {
        $this->begin();
        $this->seed_toy_data();
        toy_importer::$atomic = true;
        toy_importer::$failverify = true;
        $csv = make_request_directory() . '/notimported.csv';
        $report = new report();
        $report->open_csv($csv);
        $result = (new runner(['apply' => true, 'permit' => guard::test_permit(), 'report' => $report, 'batch' => 2,
            'atomic_threshold' => 50000]))->run([]);
        $report->close();
        $this->assertSame(1, $result['exit']);
        $this->assertCount(1, file($csv), 'only the header: nothing the rolled-back feature did was listed');
        $this->assertSame([], $report->to_array()['features']['toy']['steps']['toy.org']['skipped_by_reason'] ?? []);
    }

    // Resume.

    public function test_resume_continues_only_the_newest_apply_run(): void {
        $this->begin();
        $this->seed_toy_data();
        $failpoint = function (string $key, int $batch): void {
            if ($key === 'toy.org' && $batch === 2) {
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->execute(['failpoint' => $failpoint]);
        $this->assertSame(1, $failed['exit']);

        // A later run completes. The interrupted one is history, and its watermarks no longer describe the tables.
        [$later] = $this->execute();
        $this->assertContains($later['exit'], [0, 2], implode('; ', $later['blockers']));

        [$resume] = $this->execute(['resume' => true]);
        $this->assertSame(1, $resume['exit']);
        $this->assertStringContainsString('nothing_to_resume', implode(' ', $resume['blockers']));
    }

    // Reading.

    public function test_the_legacy_reader_keys_rows_by_id_whatever_the_column_order(): void {
        $this->begin();
        $this->seed_toy_data();
        $reader = new \local_sentientia_platform\bizlms\legacy_reader();
        $all = $reader->page('local_toy_org', 0, 10);
        $this->assertSame([1, 2, 3, 4, 7], array_keys($all));
        foreach ($all as $id => $row) {
            $this->assertSame($id, (int) $row->id);
        }
        $this->assertSame([2, 7], array_keys($reader->fetch('local_toy_org', [7, 2, 99])));
    }
}
