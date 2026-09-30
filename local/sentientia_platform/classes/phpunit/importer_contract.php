<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\phpunit;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\idpolicy;
use local_sentientia_platform\bizlms\importer;
use local_sentientia_platform\bizlms\legacymap;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\bizlms\sideeffect_guard;
use local_sentientia_platform\bizlms\step;

/**
 * The importer contract (ADR-032, "Test approach" 4): the tests every feature
 * importer must pass with that feature's own seed before it may run on Stage B.
 *
 * A feature test class does:
 *
 *     final class bizlms_import_test extends \advanced_testcase {
 *         use \local_sentientia_platform\phpunit\legacy_schema_fixture;
 *         use \local_sentientia_platform\phpunit\importer_contract;
 *         // legacy_fixture_definition(), contract_importer(), contract_seed(), ...
 *     }
 *
 * and gets these tests: not applicable without tables; a dry run writes nothing;
 * apply reconciles (accounting identity, marker); a second apply is a no-op;
 * resume after an injected failure gives the same rows as a clean run; a source
 * change is detected; a colliding row blocks and an identical header row is
 * adopted; preserved ids are kept; there are no side effects (message, event and
 * e-mail sinks stay empty and the tripwire is clean); every new person column is
 * declared in the plugin's privacy provider.
 *
 * The class uses the legacy_schema_fixture trait for the tables and must call
 * $this->resetAfterTest() through contract_begin() (every test here does).
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait importer_contract {

    /**
     * A fresh importer under test.
     *
     * @return importer
     */
    abstract protected function contract_importer(): importer;

    /**
     * Put rows into the claimed legacy tables. Must give at least one step more
     * rows than contract_batch() so a run spans several batches, and should
     * include rows that are skipped or merged.
     *
     * @return void
     */
    abstract protected function contract_seed(): void;

    /**
     * Change one row of a claimed legacy table (its count, max id or CRC must differ).
     *
     * @return void
     */
    abstract protected function contract_mutate_source(): void;

    /**
     * Owner choices the importer needs, if any.
     *
     * @return decisions
     */
    protected function contract_decisions(): decisions {
        return decisions::none();
    }

    /**
     * Rows per batch: small, so a run spans several.
     *
     * @return int
     */
    protected function contract_batch(): int {
        return 2;
    }

    /**
     * A row that occupies a PRESERVE step's legacy id and is NOT an identical copy.
     *
     * @return array{table: string, row: \stdClass}|null Null when the importer has no PRESERVE step.
     */
    protected function contract_collision(): ?array {
        return null;
    }

    /**
     * An identical header copy (what migrate_all.php wrote) at a PRESERVE step's legacy id.
     *
     * @return array{table: string, row: \stdClass, sourcetable: string}|null
     */
    protected function contract_adoptable(): ?array {
        return null;
    }

    /**
     * Columns the importer added that name a person, per target table.
     *
     * @return array<string, string[]>
     */
    protected function contract_user_columns(): array {
        return [];
    }

    /**
     * Prepare one contract test: reset after the test and register the importer.
     *
     * @return importer
     */
    protected function contract_begin(): importer {
        $this->resetAfterTest();
        $importer = $this->contract_importer();
        registry::set_testing_importers([$importer]);
        return $importer;
    }

    /**
     * Forget the registered importer after every test.
     *
     * @return void
     */
    protected function tearDown(): void {
        registry::set_testing_importers(null);
        parent::tearDown();
    }

    /**
     * Run the importer.
     *
     * @param bool $apply False for a dry run.
     * @param array $options Overrides for the runner options.
     * @return array{0: array, 1: report}
     */
    protected function contract_run(bool $apply, array $options = []): array {
        $report = new report();
        $runner = new runner($options + [
            'apply' => $apply,
            'decisions' => $this->contract_decisions(),
            'report' => $report,
            'batch' => $this->contract_batch(),
            // Batch mode, so a crash leaves committed batches and a watermark to resume from.
            'atomic_threshold' => 0,
        ]);
        return [$runner->run([]), $report];
    }

    /**
     * Rows of every target table plus the map, as a comparable signature that
     * leaves out the ids of MAP targets.
     *
     * @param importer $importer
     * @return array
     */
    protected function contract_signature(importer $importer): array {
        global $DB;
        $signature = ['targets' => [], 'map' => []];
        foreach ($importer->target_tables() as $table) {
            $signature['targets'][$table] = $DB->count_records($table);
        }
        $rows = $DB->get_records(legacymap::TABLE, null, 'sourcetable, sourceid, subkey',
            'id, sourcetable, sourceid, subkey, targettable, outcome, reason');
        foreach ($rows as $row) {
            $signature['map'][] = implode('|', [$row->sourcetable, $row->sourceid, $row->subkey,
                $row->targettable, $row->outcome, (string) $row->reason]);
        }
        return $signature;
    }

    /**
     * Put the framework and target tables back to empty between two runs of one test.
     *
     * @param importer $importer
     * @return void
     */
    protected function contract_clear_import(importer $importer): void {
        global $DB;
        foreach ($importer->target_tables() as $table) {
            $DB->delete_records($table);
        }
        $DB->delete_records(legacymap::TABLE);
        $DB->delete_records('local_sentientia_legacystep');
        $DB->delete_records('local_sentientia_legacyrun');
        unset_config('bizlms_complete_' . $importer->feature(), 'local_sentientia_platform');
    }

    public function test_contract_not_applicable_without_tables(): void {
        global $DB;
        $importer = $this->contract_begin();
        foreach (array_keys($importer->sources()) as $table) {
            self::drop_legacy_table($table);
        }
        [$result] = $this->contract_run(true);
        $this->assertSame(0, $result['exit'], 'nothing applicable is not a failure');
        $this->assertSame('not_applicable', $result['features'][$importer->feature()]);
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertFalse(legacymap::feature_complete($importer->feature()), 'no marker for a feature that did nothing');
    }

    public function test_contract_dry_run_writes_nothing(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $writes = $DB->perf_get_writes();
        [$result] = $this->contract_run(false);
        $this->assertContains($result['exit'], [0, 2], 'a dry run of a valid seed finishes');
        $this->assertSame($writes, $DB->perf_get_writes(), 'a dry run writes nothing, not even bookkeeping');
        foreach ($importer->target_tables() as $table) {
            $this->assertSame(0, $DB->count_records($table), $table);
        }
        $this->assertSame(0, $DB->count_records('local_sentientia_legacyrun'));
    }

    public function test_contract_apply_reconciles(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
        $this->assertSame('complete', $result['features'][$importer->feature()]);
        $this->assertTrue(legacymap::feature_complete($importer->feature()));

        // The accounting identity: every source row has exactly one primary map row.
        foreach ($importer->steps() as $step) {
            if (!($step instanceof step) || $step->is_derived()) {
                continue;
            }
            [$sql, $params] = $step->source_filter();
            $source = $DB->count_records_select($step->physical_table(), $sql ?: '1 = 1', $params);
            $mapped = $DB->count_records(legacymap::TABLE, ['sourcetable' => $step->sourcetable(), 'subkey' => '']);
            $this->assertSame($source, $mapped, 'accounting identity for ' . $step->key());
        }
        // Every imported or adopted map row points at a target row that exists.
        $rows = $DB->get_records_select(legacymap::TABLE, "outcome IN ('imported', 'adopted')");
        foreach ($rows as $row) {
            $this->assertTrue($DB->record_exists($row->targettable, ['id' => $row->targetid]),
                "target of map row {$row->id} exists");
        }
        $this->assertSame('clean', $report->to_array()['features'][$importer->feature()]['tripwire']);
    }

    public function test_contract_second_apply_is_a_noop(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $first = $this->contract_signature($importer);
        [$result] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        $this->assertSame($first, $this->contract_signature($importer), 'a second apply writes no row');
    }

    public function test_contract_resume_after_injected_failure(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();

        [$clean] = $this->contract_run(true);
        $this->assertContains($clean['exit'], [0, 2]);
        $expected = $this->contract_signature($importer);
        $this->contract_clear_import($importer);

        $thrown = false;
        $failpoint = function (string $stepkey, int $batchno) use (&$thrown): void {
            if (!$thrown && $batchno === 2) {
                $thrown = true;
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->contract_run(true, ['failpoint' => $failpoint]);
        $this->assertTrue($thrown, 'the seed must give some step at least two batches');
        $this->assertSame(1, $failed['exit']);
        $this->assertFalse(legacymap::feature_complete($importer->feature()), 'no marker after a crash');

        [$resumed] = $this->contract_run(true, ['resume' => true]);
        $this->assertContains($resumed['exit'], [0, 2], implode('; ', $resumed['blockers']));
        $this->assertSame($expected, $this->contract_signature($importer), 'resume gives the rows of a clean run');
        $this->assertSame(1, $DB->count_records('local_sentientia_legacyrun'), 'resume continues the same run');
    }

    public function test_contract_feature_mode_reconciles_and_a_crash_leaves_nothing(): void {
        global $DB;
        $importer = $this->contract_begin();
        if (!$importer->atomic()) {
            $this->markTestSkipped('the importer is not atomic(), so it never runs in feature mode');
        }
        $this->contract_seed();
        $feature = ['atomic_threshold' => 50000];

        [$clean] = $this->contract_run(true, $feature);
        $this->assertContains($clean['exit'], [0, 2], implode('; ', $clean['blockers']));
        $expected = $this->contract_signature($importer);
        $this->contract_clear_import($importer);

        $thrown = false;
        $failpoint = function (string $stepkey, int $batchno) use (&$thrown): void {
            if (!$thrown && $batchno === 2) {
                $thrown = true;
                throw new \RuntimeException('injected failure');
            }
        };
        [$failed] = $this->contract_run(true, $feature + ['failpoint' => $failpoint]);
        $this->assertTrue($thrown, 'the seed must give some step at least two batches');
        $this->assertSame(1, $failed['exit']);
        foreach ($importer->target_tables() as $table) {
            $this->assertSame(0, $DB->count_records($table), "{$table}: a crash in feature mode leaves nothing");
        }
        $this->assertSame(0, $DB->count_records(legacymap::TABLE));
        $this->assertFalse(legacymap::feature_complete($importer->feature()));

        [$again] = $this->contract_run(true, $feature);
        $this->assertContains($again['exit'], [0, 2], implode('; ', $again['blockers']));
        $this->assertSame($expected, $this->contract_signature($importer), 'the rerun gives the rows of a clean run');
    }

    public function test_contract_source_change_is_detected(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $thrown = false;
        $failpoint = function (string $stepkey, int $batchno) use (&$thrown): void {
            if (!$thrown && $batchno === 2) {
                $thrown = true;
                throw new \RuntimeException('injected failure');
            }
        };
        $this->contract_run(true, ['failpoint' => $failpoint]);
        $this->assertTrue($thrown);
        $this->contract_mutate_source();
        [$resumed] = $this->contract_run(true, ['resume' => true]);
        $this->assertSame(1, $resumed['exit']);
        $this->assertStringContainsString('source_changed_since_the_run_started', implode(' ', $resumed['blockers']));
        $this->assertFalse(legacymap::feature_complete($importer->feature()));
    }

    public function test_contract_collision_blocks_and_header_row_is_adopted(): void {
        global $DB;
        $importer = $this->contract_begin();
        $collision = $this->contract_collision();
        $adoptable = $this->contract_adoptable();
        if ($collision === null && $adoptable === null) {
            $this->markTestSkipped('the importer has no PRESERVE step');
        }
        $this->contract_seed();

        if ($collision !== null) {
            $id = $DB->import_record($collision['table'], $collision['row']);
            [$result] = $this->contract_run(true);
            $this->assertSame(1, $result['exit'], 'an occupied legacy id that is not a copy blocks the feature');
            $this->assertStringContainsString('preserve_collision', implode(' ', $result['blockers']));
            $this->assertSame(0, $DB->count_records(legacymap::TABLE), 'a blocked run writes no map row');
            $DB->delete_records($collision['table'], ['id' => $collision['row']->id]);
            $this->assertNotNull($id);
        }

        if ($adoptable !== null) {
            $DB->import_record($adoptable['table'], $adoptable['row']);
            [$result] = $this->contract_run(true);
            $this->assertContains($result['exit'], [0, 2], implode('; ', $result['blockers']));
            $adopted = $DB->get_record(legacymap::TABLE, [
                'sourcetable' => $adoptable['sourcetable'], 'sourceid' => $adoptable['row']->id, 'subkey' => '',
            ]);
            $this->assertSame('adopted', $adopted->outcome);
        }
    }

    public function test_contract_preserved_ids(): void {
        global $DB;
        $importer = $this->contract_begin();
        $this->contract_seed();
        $this->contract_run(true);
        $checked = 0;
        foreach ($importer->steps() as $step) {
            if (!($step instanceof step) || $step->idpolicy() !== idpolicy::PRESERVE) {
                continue;
            }
            $rows = $DB->get_records_select(legacymap::TABLE,
                "sourcetable = :st AND subkey = '' AND outcome IN ('imported', 'adopted')",
                ['st' => $step->sourcetable()]);
            foreach ($rows as $row) {
                $this->assertSame((int) $row->sourceid, (int) $row->targetid, 'PRESERVE keeps the legacy id');
                $this->assertTrue($DB->record_exists($row->targettable, ['id' => $row->targetid]));
                $checked++;
            }
        }
        if ($checked === 0) {
            $this->markTestSkipped('the importer has no PRESERVE step with imported rows');
        }
    }

    public function test_contract_no_side_effects(): void {
        $importer = $this->contract_begin();
        $this->contract_seed();
        $events = $this->redirectEvents();
        $messages = $this->redirectMessages();
        $emails = $this->redirectEmails();
        $before = sideeffect_guard::snapshot();
        [$result, $report] = $this->contract_run(true);
        $this->assertContains($result['exit'], [0, 2]);
        $after = sideeffect_guard::snapshot();
        $allowed = array_merge($importer->target_tables(), array_keys($importer->core_writes()));
        $this->assertSame([], sideeffect_guard::violations($before, $after, $allowed));
        $this->assertSame('clean', $report->to_array()['features'][$importer->feature()]['tripwire']);
        $this->assertSame(0, $events->count(), 'the import fires no event');
        $this->assertSame(0, $messages->count(), 'the import sends no message');
        $this->assertSame(0, $emails->count(), 'the import sends no e-mail');
    }

    public function test_contract_privacy_declares_every_new_user_column(): void {
        $importer = $this->contract_begin();
        $columns = $this->contract_user_columns();
        if (!$columns) {
            $this->markTestSkipped('the importer adds no column that names a person');
        }
        $component = $importer->component();
        $class = '\\' . $component . '\\privacy\\provider';
        $this->assertTrue(class_exists($class), "{$component} has a privacy provider");
        $this->assertFalse(
            in_array(\core_privacy\local\metadata\null_provider::class, class_implements($class) ?: [], true),
            "{$component} must not claim it holds no personal data");
        $collection = $class::get_metadata(new \core_privacy\local\metadata\collection($component));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }
        foreach ($columns as $table => $names) {
            $this->assertArrayHasKey($table, $declared, "{$table} is declared in the privacy provider");
            foreach ($names as $name) {
                $this->assertContains($name, $declared[$table], "{$table}.{$name} is declared");
            }
        }
    }
}
