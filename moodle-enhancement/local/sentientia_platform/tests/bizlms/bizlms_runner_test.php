<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\blocked;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\phpunit\legacy_schema_fixture;
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
        $runner = new runner($options + ['apply' => $apply, 'report' => $report, 'batch' => 2, 'atomic_threshold' => 0]);
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

        $mapping = ['enums.local_toy_item.kind' => ['z' => 'zeta']];
        [$result, $report] = $this->execute(['decisions' => decisions::from_array($mapping)]);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame($mapping, $report->to_array()['meta']['decisions'], 'the report lists the decisions used');
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
                  'local_sentientia_legacystep', 'logstore_standard_log'] as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table} was rolled back with the feature");
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
        foreach (['local_sentientia_toy_org', 'local_sentientia_legacymap', 'local_sentientia_legacystep'] as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table} holds nothing after a crash in feature mode");
        }
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
        $runner = new runner(['apply' => true]);
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
        (new runner(['apply' => true]))->purge('toy');
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
}
