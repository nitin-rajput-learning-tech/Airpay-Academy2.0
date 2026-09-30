<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Lifecycle of an import run (ADR-032): discovery, preflight, steps, verify,
 * finalise, completion marker, tripwire, report.
 *
 * - A dry run (the default) writes NOTHING, not even bookkeeping: the writer
 *   refuses every write, the map is an in-memory overlay, and MAP steps get
 *   negative virtual ids so dependents resolve against upstream rows.
 * - Batch mode (default): one delegated transaction wraps a batch's target
 *   writes, its map rows and the step's watermark and counters. A crash rolls
 *   the batch back, the watermark stays, and resume continues. The map's unique
 *   key blocks a double insert if a watermark ever lags.
 * - Feature mode: when the importer is atomic() and the preflight total is at
 *   or below the atomic threshold, the whole feature runs in one outer
 *   transaction, so a crash (or a failed verify, or a tripped tripwire) leaves
 *   nothing.
 * - No DDL inside a transaction: sequences are reset after the last commit and
 *   the runner asserts no transaction is open before it calls finalise().
 * - A feature's completion marker is written only after verify, the tripwire
 *   and finalise have all passed.
 *
 * Step failures store the exception class and, for the framework's own
 * exceptions, the message (ids and codes). A database exception message can
 * hold SQL and row values, so it is never stored; the operator sees it only on
 * the console.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class runner {

    /** Step row table. */
    private const STEP_TABLE = 'local_sentientia_legacystep';

    /** Run row table. */
    private const RUN_TABLE = 'local_sentientia_legacyrun';

    /** Counters kept on the step row. */
    private const COUNTERS = ['processed', 'imported', 'adopted', 'merged', 'folded', 'archived', 'skipped', 'updated'];

    /** Most distinct values a declared enum column may show before preflight blocks (it is not an enum). */
    private const ENUM_MAX_VALUES = 1000;

    /** Rows per page when preflight pages the legacy ids against a PRESERVE target. */
    private const PRESERVE_PAGE = 2000;

    /** @var array<string, mixed> */
    private array $options;

    /** @var bool */
    private bool $dryrun;

    /** @var decisions */
    private decisions $decisions;

    /** @var report */
    private report $report;

    /** @var writer */
    private writer $writer;

    /** @var legacymap */
    private legacymap $map;

    /** @var lookups */
    private lookups $lookups;

    /** @var tenant_resolver */
    private tenant_resolver $tenant;

    /** @var legacy_reader */
    private legacy_reader $legacy;

    /** @var text */
    private text $text;

    /** @var int Run id; 0 in a dry run. */
    private int $runid = 0;

    /** @var int Counter behind the negative virtual ids of a dry run. */
    private int $virtualid = 0;

    /** @var array<string, string> Legacy table => feature that owns it. */
    private array $ownerof = [];

    /** @var array<string, bool> Features complete, not applicable, or finished earlier in this run. */
    private array $available = [];

    /** @var array<string, bool> Feature => has at least one claimed table. */
    private array $applicable = [];

    /** @var array<string, preflight> */
    private array $preflights = [];

    /** @var array<string, array<string, reason>> Feature => code => reason (includes the reserved deferred code). */
    private array $reasons = [];

    /** @var array<string, array<string, int>> Feature => reason code => rows, this run. */
    private array $reasontally = [];

    /** @var array<string, int> Dry run: primary map rows that existed per source table when the run began. */
    private array $acctbase = [];

    /** @var array<string, int> Dry run: primary outcomes recorded per source table. */
    private array $acctnew = [];

    /**
     * @param array<string, mixed> $options apply (bool), all (bool), batch, atomic_threshold, max_group_scan,
     *        crc_max_rows (rows above which a source fingerprint skips its CRC; 0 = never skip),
     *        resume (bool), retry_reasons (string[]), decisions (decisions), report (report),
     *        failpoint (callable(string $stepkey, int $batchno): void, for tests).
     */
    public function __construct(array $options = []) {
        $this->options = $options + [
            'apply' => false,
            'all' => false,
            'batch' => 500,
            'atomic_threshold' => 50000,
            'max_group_scan' => 500000,
            'crc_max_rows' => fingerprint::CRC_MAX_ROWS,
            'resume' => false,
            'retry_reasons' => [],
            'decisions' => null,
            'report' => null,
            'failpoint' => null,
        ];
        $this->dryrun = !$this->options['apply'];
        $this->decisions = $this->options['decisions'] ?? decisions::none();
        $this->report = $this->options['report'] ?? new report();
        $this->writer = new writer($this->dryrun);
        $this->map = new legacymap();
        $this->lookups = new lookups();
        $this->tenant = new tenant_resolver($this->lookups);
        $this->legacy = new legacy_reader();
        $this->text = new text();
    }

    /**
     * @return report
     */
    public function report(): report {
        return $this->report;
    }

    /**
     * Read-only preflight of the selected features.
     *
     * @param string[] $features Empty means all.
     * @return array{order: string[], applicable: array<string, bool>, preflights: array<string, preflight>}
     * @throws registry_error
     */
    public function preflight(array $features): array {
        $importers = registry::load();
        $order = $this->plan($importers, $features);
        foreach ($order as $feature) {
            $this->preflights[$feature] = $this->preflight_feature($importers[$feature]);
        }
        return ['order' => $order, 'applicable' => $this->applicable, 'preflights' => $this->preflights];
    }

    /**
     * Run the selected features: a dry run, or an apply run.
     *
     * @param string[] $features Empty means all.
     * @return array{exit: int, status: string, features: array<string, string>, blockers: string[], runid: int,
     *               unproven: string[]}
     */
    public function run(array $features): array {
        $result = ['exit' => 0, 'status' => 'complete', 'features' => [], 'blockers' => [], 'runid' => 0,
            'unproven' => []];

        try {
            $importers = registry::load();
            $order = $this->plan($importers, $features);
        } catch (registry_error $e) {
            $result['exit'] = 1;
            $result['status'] = 'registry_invalid';
            $result['blockers'] = $e->problems;
            return $result;
        }
        $requested = $features ?: $order;

        // Preflight every selected feature before anything is written.
        foreach ($order as $feature) {
            $pf = $this->preflight_feature($importers[$feature]);
            $this->preflights[$feature] = $pf;
            if ($this->applicable[$feature]) {
                foreach ($pf->blockers() as $blocker) {
                    $result['blockers'][] = $feature . ': ' . $blocker;
                }
            }
            $this->report->set_feature($feature, [
                'applicable' => $this->applicable[$feature],
                'preflight' => $pf->to_array(),
            ]);
        }
        if ($result['blockers']) {
            $result['exit'] = 1;
            $result['status'] = 'blocked';
            return $this->finish($importers, $order, $result);
        }

        try {
            if (!$this->dryrun) {
                $this->open_run($importers, $order);
                $result['runid'] = $this->runid;
            }
            foreach ($order as $feature) {
                $implicit = !in_array($feature, $requested, true);
                $result['features'][$feature] = $this->run_feature($importers[$feature], $implicit);
            }
        } catch (\Throwable $e) {
            $result['exit'] = $e instanceof bizlms_exception ? $e->exitcode() : 1;
            $result['status'] = 'failed';
            $result['blockers'][] = self::safe_message($e);
            $result['error'] = $e;
            if (!$this->dryrun && $this->runid) {
                $this->fail_run($e);
            }
            return $this->finish($importers, $order, $result);
        }

        return $this->finish($importers, $order, $result);
    }

    /**
     * Run verify and the framework checks only.
     *
     * @param string[] $features Empty means all.
     * @return array{exit: int, failures: array<string, string[]>}
     */
    public function verify(array $features): array {
        $importers = registry::load();
        $order = registry::sorted($importers, $features, false);
        $this->prepare($importers);
        $out = ['exit' => 0, 'failures' => []];
        foreach ($order as $feature) {
            $importer = $importers[$feature];
            if (!$this->feature_applicable($importer)) {
                continue;
            }
            $ctx = $this->context_for($importer, 0);
            $failures = [];
            if (!legacymap::feature_complete($feature)) {
                $failures[] = 'feature_not_complete';
            }
            $failures = array_merge($failures, $this->verify_feature($importer, $ctx, false));
            $out['failures'][$feature] = $failures;
            if ($failures) {
                $out['exit'] = 1;
            }
        }
        return $out;
    }

    /**
     * Delete the rows a feature imported (rehearsal only).
     *
     * Refuses when the feature has adopted rows, when a dependent feature is
     * complete, or when an imported row was changed after import. Deletes only
     * rows whose map outcome is imported. Never resets a sequence.
     *
     * @param string $feature
     * @return array{exit: int, deleted: array<string, int>}
     */
    public function purge(string $feature): array {
        global $DB;
        $importers = registry::load();
        if (!isset($importers[$feature])) {
            throw new blocked('unknown_feature:' . $feature);
        }
        $importer = $importers[$feature];
        $this->prepare($importers);

        foreach ($importers as $other) {
            if (in_array($feature, $other->depends(), true) && legacymap::feature_complete($other->feature())) {
                throw new blocked('dependent_feature_is_complete:' . $other->feature());
            }
        }
        if ($DB->record_exists(legacymap::TABLE, ['feature' => $feature, 'outcome' => 'adopted'])) {
            throw new blocked('purge_refused_feature_has_adopted_rows');
        }
        if ($importer->core_writes()) {
            // Core writes are in-place updates of rows that existed before the import. A purge deletes only
            // rows the import created and cannot put those back, so a re-import would meet an already-changed
            // source. The RDS snapshot is the rollback for such a feature.
            throw new blocked('purge_refused_feature_writes_core:' . implode(',', array_keys($importer->core_writes())));
        }
        foreach ($importer->target_tables() as $table) {
            if (!$DB->get_manager()->table_exists($table)
                    || !array_key_exists('timemodified', $DB->get_columns($table))) {
                continue;
            }
            $changed = $DB->count_records_sql(
                'SELECT COUNT(1) FROM {' . legacymap::TABLE . '} m
                   JOIN {' . $table . '} x ON x.id = m.targetid
                  WHERE m.feature = :f AND m.targettable = :t AND m.outcome = :o AND x.timemodified > m.timecreated',
                ['f' => $feature, 't' => $table, 'o' => 'imported']);
            if ($changed > 0) {
                throw new blocked('purge_refused_rows_changed_after_import:' . $table);
            }
        }

        $writer = $this->writer->for_importer($importer);
        $deleted = [];
        $this->runid = $this->writer->create_run((object) [
            'runmode' => 'purge', 'status' => 'running', 'features' => json_encode([$feature]),
            'decisionshash' => $this->decisions->hash(), 'codehash' => $this->code_hash($importers, [$feature]),
            'fingerprint' => fingerprint::install(), 'host' => substr((string) gethostname(), 0, 100),
            'pid' => (int) getmypid(), 'timestarted' => time(), 'heartbeat' => time(), 'timefinished' => 0,
        ]);
        $tx = $DB->start_delegated_transaction();
        try {
            foreach (array_reverse($importer->target_tables()) as $table) {
                $ids = array_keys($DB->get_records_select(legacymap::TABLE,
                    'feature = :f AND targettable = :t AND outcome = :o',
                    ['f' => $feature, 't' => $table, 'o' => 'imported'], '', 'DISTINCT targetid'));
                $ids = array_map('intval', $ids);
                if ($ids) {
                    $writer->purge_rows($table, $ids);
                }
                $deleted[$table] = count($ids);
            }
            $this->writer->delete_map_rows($feature);
            $this->writer->clear_marker($feature);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            try {
                $tx->rollback($e);
            } catch (\Throwable $rethrown) {
                $this->writer->update_run($this->runid, (object) ['status' => 'failed', 'timefinished' => time()]);
                throw $e;
            }
        }
        $this->writer->update_run($this->runid, (object) ['status' => 'complete', 'timefinished' => time()]);
        return ['exit' => 0, 'deleted' => $deleted];
    }

    /**
     * Per-feature facts for --status and --list.
     *
     * @param array<string, importer> $importers
     * @return array<string, array<string, mixed>>
     */
    public static function feature_states(array $importers): array {
        global $DB;
        $out = [];
        $dbman = $DB->get_manager();
        $haverun = $dbman->table_exists(self::STEP_TABLE);
        foreach ($importers as $feature => $importer) {
            $present = 0;
            foreach (array_keys($importer->sources()) as $table) {
                if ($dbman->table_exists($table)) {
                    $present++;
                }
            }
            $state = [
                'owner' => $importer->component(),
                'depends' => $importer->depends(),
                'sources' => count($importer->sources()),
                'sources_present' => $present,
                'complete_runid' => (int) get_config(writer::COMPONENT, 'bizlms_complete_' . $feature),
                'started' => false,
                'running_steps' => 0,
                'last_heartbeat_age' => null,
            ];
            if ($haverun) {
                $state['started'] = $DB->record_exists_select(self::STEP_TABLE,
                    "feature = :f AND status <> 'not_applicable'", ['f' => $feature]);
                $running = $DB->get_records_select(self::STEP_TABLE, "feature = :f AND status = 'running'",
                    ['f' => $feature], 'timemodified DESC', 'id, timemodified');
                $state['running_steps'] = count($running);
                if ($running) {
                    $state['last_heartbeat_age'] = time() - (int) reset($running)->timemodified;
                }
            }
            $out[$feature] = $state;
        }
        return $out;
    }

    // Planning.

    /**
     * Order the features and prepare the lookup tables every step needs.
     *
     * @param array<string, importer> $importers
     * @param string[] $features
     * @return string[]
     */
    private function plan(array $importers, array $features): array {
        $withdependencies = !$this->dryrun || $this->options['all'] || !$features;
        $order = registry::sorted($importers, $features, $withdependencies);
        $this->prepare($importers);
        return $order;
    }

    /**
     * Owner of each legacy table, available features and reason vocabularies.
     *
     * @param array<string, importer> $importers
     * @return void
     */
    private function prepare(array $importers): void {
        $this->ownerof = [];
        $this->available = [];
        foreach ($importers as $feature => $importer) {
            foreach (array_keys($importer->sources()) as $table) {
                $this->ownerof[$table] = $feature;
            }
            foreach (array_keys($importer->declined_tables()) as $table) {
                $this->ownerof[$table] = $feature;
            }
            if (legacymap::feature_complete($feature)) {
                $this->available[$feature] = true;
            }
            $vocabulary = [reason::DEFERRED => new reason(reason::DEFERRED, false, false)];
            foreach ($importer->reasons() as $reason) {
                $vocabulary[$reason->code] = $reason;
            }
            $this->reasons[$feature] = $vocabulary;
        }
    }

    /**
     * @param importer $importer
     * @return bool At least one claimed table exists.
     */
    private function feature_applicable(importer $importer): bool {
        foreach (array_keys($importer->sources()) as $table) {
            if ($this->legacy->exists($table)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build the context a feature's steps, preflight and verify receive.
     *
     * @param importer $importer
     * @param int|null $runid Defaults to the run in progress.
     * @return context
     */
    private function context_for(importer $importer, ?int $runid = null): context {
        $feature = $importer->feature();
        return context::build($importer, $this->dryrun, $runid ?? $this->runid, $this->decisions, [
            'map' => $this->map,
            'lookups' => $this->lookups,
            'tenant' => $this->tenant,
            'legacy' => $this->legacy,
            'text' => $this->text,
            'deferred' => function (string $sourcetable) use ($feature): bool {
                $owner = $this->ownerof[$sourcetable] ?? null;
                return $owner !== null && $owner !== $feature && empty($this->available[$owner]);
            },
        ]);
    }

    // Preflight.

    /**
     * Read-only checks of one feature.
     *
     * @param importer $importer
     * @return preflight
     */
    private function preflight_feature(importer $importer): preflight {
        $pf = new preflight();
        $feature = $importer->feature();

        $present = [];
        $missing = [];
        foreach ($importer->sources() as $table => $spec) {
            if ($this->legacy->exists($table)) {
                $present[$table] = $spec;
            } else if ($spec->required) {
                $missing[] = $table;
            }
        }
        if (!$present) {
            // Every claimed table is missing: nothing to import, not a blocker.
            $this->applicable[$feature] = false;
            return $pf;
        }
        $this->applicable[$feature] = true;
        foreach ($missing as $table) {
            $pf->block('missing_required_table:' . $table);
        }

        foreach ($importer->decisions() as $decision) {
            $has = $this->decisions->has($decision->key);
            if ($decision->required && $decision->default === null && !$has) {
                $pf->block('missing_decision:' . $decision->key);
            }
            if ($has && $decision->allowed !== null
                    && !in_array($this->decisions->get($decision->key), $decision->allowed, true)) {
                $pf->block('decision_value_not_allowed:' . $decision->key);
            }
        }

        foreach ($present as $table => $spec) {
            $this->preflight_enums($pf, $table, $spec);
        }

        $total = 0;
        foreach ($importer->steps() as $step) {
            if (!($step instanceof step)) {
                continue;
            }
            $source = $step->physical_table();
            if (!$this->legacy->exists($source)) {
                continue;
            }
            $spec = $importer->sources()[$source] ?? null;
            $have = $this->legacy->columns($source);
            $optional = $spec ? $spec->optionalcolumns : [];
            foreach ($step->columns() === ['*'] ? [] : $step->columns() as $column) {
                if ($column !== 'id' && !in_array($column, $have, true) && !in_array($column, $optional, true)) {
                    $pf->block('missing_column:' . $source . '.' . $column);
                }
            }
            foreach ($step->group_by() as $column) {
                if (!in_array($column, $have, true)) {
                    $pf->block('missing_group_column:' . $source . '.' . $column);
                }
            }
            $count = $this->legacy->count($source, $step->source_filter());
            $pf->count('rows:' . $step->key(), $count);
            $total += $count;
            if ($step->idpolicy() === idpolicy::PRESERVE) {
                $this->preflight_preserve($pf, $step);
            }
        }
        $pf->count('rows_total', $total);

        $pf->merge($importer->preflight($this->context_for($importer)));
        return $pf;
    }

    /**
     * Histogram every declared enum column; a value outside the declaration blocks.
     *
     * @param preflight $pf
     * @param string $table
     * @param source_spec $spec
     * @return void
     */
    private function preflight_enums(preflight $pf, string $table, source_spec $spec): void {
        global $DB;
        foreach ($spec->enums as $column => $values) {
            fingerprint::assert_identifier($column);
            if (!$this->legacy->has_column($table, $column)) {
                $pf->block('missing_enum_column:' . $table . '.' . $column);
                continue;
            }
            // Values the importer declared, plus values the owner mapped in the decisions file.
            $allowed = array_merge(array_map('strval', array_keys($values)),
                $this->decisions->mapped_enum_values($table, $column));
            $histogram = [];
            // A GROUP BY on an enum column returns a handful of rows. MIN(id) is the unique first column
            // get_records_sql() keys by, so a NULL and an empty value cannot collide.
            // One more than the cap, so "too many" is seen without reading the whole result.
            $rows = $DB->get_records_sql(
                "SELECT MIN(t.id) AS k, t.{$column} AS v, COUNT(1) AS n FROM {" . $table . "} t GROUP BY t.{$column}",
                null, 0, self::ENUM_MAX_VALUES + 1);
            if (count($rows) > self::ENUM_MAX_VALUES) {
                $pf->block('too_many_distinct_values:' . $table . '.' . $column);
                continue;
            }
            foreach ($rows as $row) {
                $value = $row->v === null ? '' : (string) $row->v;
                $histogram[$value] = (int) $row->n;
                if (!in_array($value, $allowed, true)) {
                    $pf->block('unknown_enum:' . $table . '.' . $column . '=' . \core_text::substr($value, 0, 40));
                }
            }
            $pf->histogram($table . '.' . $column, $histogram);
        }
    }

    /**
     * Page the legacy id set of a PRESERVE step against its target. An occupied
     * id that has no map row must be an adoptable copy, else the whole feature
     * is blocked: the importer never falls back to a new id.
     *
     * @param preflight $pf
     * @param step $step
     * @return void
     */
    private function preflight_preserve(preflight $pf, step $step): void {
        global $DB;
        $target = $step->targettable();
        if (!$DB->get_manager()->table_exists($target)) {
            $pf->block('missing_target_table:' . $target);
            return;
        }
        $signature = self::signature($step);
        $sourcecolumns = array_merge(['id'], array_keys($signature));
        $targetcolumns = array_values($signature);

        $after = 0;
        $adoptable = 0;
        $collisions = 0;
        $first = [];
        do {
            $rows = $this->legacy->page($step->physical_table(), $after, self::PRESERVE_PAGE,
                $sourcecolumns, $step->source_filter());
            if (!$rows) {
                break;
            }
            $ids = array_keys($rows);
            $after = (int) end($ids);
            [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'blmpid');
            $occupants = $DB->get_records_select($target, "id {$insql}", $params, '',
                implode(', ', array_unique(array_merge(['id'], $targetcolumns))));
            if (!$occupants) {
                continue;
            }
            $mapped = $this->map->entries($step->sourcetable(), array_keys($occupants));
            foreach ($occupants as $id => $occupant) {
                if ($mapped[(int) $id] !== null) {
                    continue;
                }
                if (self::matches_signature($rows[(int) $id], $occupant, $signature)) {
                    $adoptable++;
                } else {
                    $collisions++;
                    if (count($first) < 20) {
                        $first[] = (int) $id;
                    }
                }
            }
        } while (count($rows) === self::PRESERVE_PAGE);

        if ($adoptable) {
            $pf->warn('preserve_adoptable:' . $step->key() . ':' . $adoptable);
        }
        if ($collisions) {
            $pf->block('preserve_collision:' . $step->key() . ':' . $collisions . ' ids=' . implode(',', $first));
        }
    }

    // Run bookkeeping.

    /**
     * Create the run row, or reopen the latest incomplete one for --resume.
     *
     * @param array<string, importer> $importers
     * @param string[] $order
     * @return void
     */
    private function open_run(array $importers, array $order): void {
        global $DB;
        if ($this->options['resume']) {
            $runs = $DB->get_records_select(self::RUN_TABLE, "runmode = 'apply' AND status <> 'complete'",
                [], 'id DESC', '*', 0, 1);
            if (!$runs) {
                throw new blocked('nothing_to_resume');
            }
            $run = reset($runs);
            $runfeatures = json_decode((string) $run->features, true) ?: [];
            if (array_diff($order, $runfeatures)) {
                throw new blocked('resume_features_are_outside_the_interrupted_run');
            }
            if ((string) $run->decisionshash !== $this->decisions->hash()) {
                throw new blocked('decisions_changed_since_the_interrupted_run');
            }
            // The run stored the plugin versions it ran with; a deployment in between changes the code
            // the watermarks were written for.
            $known = array_values(array_intersect($runfeatures, array_keys($importers)));
            if ($known !== $runfeatures || (string) $run->codehash !== $this->code_hash($importers, $runfeatures)) {
                throw new blocked('plugin_versions_changed_since_the_interrupted_run');
            }
            $this->runid = (int) $run->id;
            $this->writer->update_run($this->runid, (object) [
                'status' => 'running', 'heartbeat' => time(), 'host' => substr((string) gethostname(), 0, 100),
                'pid' => (int) getmypid(), 'timefinished' => 0,
            ]);
            return;
        }
        $this->runid = $this->writer->create_run((object) [
            'runmode' => 'apply',
            'status' => 'running',
            'features' => json_encode($order),
            'decisionshash' => $this->decisions->hash(),
            'codehash' => $this->code_hash($importers, $order),
            'fingerprint' => fingerprint::install(),
            'host' => substr((string) gethostname(), 0, 100),
            'pid' => (int) getmypid(),
            'timestarted' => time(),
            'heartbeat' => time(),
            'timefinished' => 0,
        ]);
    }

    /**
     * sha256 over the plugin versions involved in the run.
     *
     * @param array<string, importer> $importers
     * @param string[] $order
     * @return string
     */
    private function code_hash(array $importers, array $order): string {
        $parts = [writer::COMPONENT . '=' . (int) get_config(writer::COMPONENT, 'version')];
        foreach ($order as $feature) {
            $component = $importers[$feature]->component();
            $parts[] = $component . '=' . (int) get_config($component, 'version');
        }
        sort($parts);
        return hash('sha256', implode('|', array_unique($parts)));
    }

    /**
     * @param \Throwable $e
     * @return void
     */
    private function fail_run(\Throwable $e): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            return;
        }
        $this->writer->update_run($this->runid, (object) ['status' => 'failed', 'timefinished' => time()]);
    }

    /**
     * Final bookkeeping, exit code and report.
     *
     * @param array<string, importer> $importers
     * @param string[] $order
     * @param array $result
     * @return array
     */
    private function finish(array $importers, array $order, array $result): array {
        if ($result['exit'] === 0) {
            $unproven = $this->unproven_reasons($order);
            if ($this->options['all']) {
                foreach (unclaimed::with_rows($importers) as $table) {
                    $unproven[] = 'unclaimed_table:' . $table;
                }
            }
            if ($unproven) {
                $result['exit'] = 2;
                $result['status'] = 'unproven';
                $result['unproven'] = $unproven;
            }
        }

        if (!$this->dryrun && $this->runid) {
            $clean = $result['exit'] === 0 || $result['exit'] === 2;
            if ($clean) {
                $this->writer->update_run($this->runid, (object) ['status' => 'complete', 'timefinished' => time()]);
                if ($this->options['all']) {
                    $this->writer->set_framework_config('bizlms_import_armed_until', 0);
                }
            }
        }

        $this->report->meta('exit', $result['exit']);
        $this->report->meta('status', $result['status']);
        $this->report->meta('runid', $this->runid);
        $this->report->meta('dryrun', $this->dryrun);
        $this->report->meta('decisions_hash', $this->decisions->hash());
        $this->report->meta('decisions', $this->decisions->all());
        $this->report->meta('unproven', $result['unproven']);
        $this->report->meta('blockers', $result['blockers']);
        $this->report->close();
        return $result;
    }

    /**
     * Needs-owner reasons the decisions file has not accepted.
     *
     * @param string[] $order
     * @return string[] Lines such as feature:code=rows.
     */
    private function unproven_reasons(array $order): array {
        global $DB;
        $tally = $this->reasontally;
        if (!$this->dryrun && $order) {
            [$insql, $params] = $DB->get_in_or_equal($order, SQL_PARAMS_NAMED, 'blmf');
            $rows = $DB->get_records_sql(
                'SELECT MIN(id) AS k, feature, reason, COUNT(1) AS n FROM {' . legacymap::TABLE . "}
                  WHERE feature {$insql} AND reason IS NOT NULL GROUP BY feature, reason", $params);
            $tally = [];
            foreach ($rows as $row) {
                $tally[$row->feature][$row->reason] = (int) $row->n;
            }
        }
        $out = [];
        foreach ($tally as $feature => $codes) {
            foreach ($codes as $code => $n) {
                $reason = $this->reasons[$feature][$code] ?? null;
                if ($reason !== null && $reason->needsowner && !$this->decisions->accepts($feature, $code)) {
                    $out[] = "{$feature}:{$code}={$n}";
                }
            }
        }
        sort($out);
        return $out;
    }

    // Features.

    /**
     * Run one feature: steps, verify, tripwire, finalise, marker.
     *
     * @param importer $importer
     * @param bool $implicit The feature was added as a dependency, not requested.
     * @return string not_applicable, already_complete, complete or simulated.
     */
    private function run_feature(importer $importer, bool $implicit): string {
        global $DB;
        $feature = $importer->feature();

        if (!$this->applicable[$feature]) {
            $this->report->set_feature($feature, ['status' => 'not_applicable']);
            $this->available[$feature] = true;
            foreach ($importer->steps() as $step) {
                $this->record_not_applicable_step($feature, $step->key(), $step instanceof step ? $step->sourcetable() : '');
            }
            return 'not_applicable';
        }
        if ($implicit && !$this->dryrun && !$this->options['resume'] && legacymap::feature_complete($feature)) {
            $this->report->set_feature($feature, ['status' => 'already_complete']);
            return 'already_complete';
        }

        // An earlier feature (org) writes rows this one reads (tenant resolution), and a preflight may have
        // cached an empty set before any of it existed.
        $this->lookups->refresh();
        if ($this->dryrun && $importer->tenant_columns() && !$this->lookups->has_orgs()) {
            // A dry run writes no organisation, so tenant paths are not checked against the org tree.
            $this->report->set_feature($feature, ['warning' => 'dry_run_does_not_check_tenant_paths_against_unwritten_orgs']);
        }

        $ctx = $this->context_for($importer);
        $writer = $this->writer->for_importer($importer);
        $allowed = array_merge($importer->target_tables(), array_keys($importer->core_writes()));
        $extra = $importer instanceof watches_tables ? $importer->watched_tables() : [];
        $before = $this->dryrun ? [] : sideeffect_guard::snapshot($extra);

        $total = $this->preflights[$feature]->counts()['rows_total'] ?? 0;
        $atomic = !$this->dryrun && $importer->atomic() && $total <= (int) $this->options['atomic_threshold'];
        $fields = ['status' => 'running', 'mode' => $atomic ? 'feature' : 'batch'];
        if (!$this->dryrun && $importer->atomic() && !$atomic) {
            $fields['note'] = 'above_atomic_threshold_running_in_batch_mode';
        }
        $this->report->set_feature($feature, $fields);

        $outer = null;
        try {
            if ($atomic) {
                $outer = $DB->start_delegated_transaction();
            }
            foreach ($importer->steps() as $step) {
                if ($step instanceof step) {
                    $this->run_load_step($importer, $step, $ctx, $writer);
                } else {
                    $this->run_recompute_step($importer, $step, $ctx, $writer);
                }
            }

            $failures = $this->verify_feature($importer, $ctx, $this->dryrun);
            if ($failures) {
                $this->report->set_feature($feature, ['verify_failures' => $failures]);
                throw new bizlms_exception('verify_failed:' . count($failures));
            }

            if (!$this->dryrun) {
                $violations = sideeffect_guard::violations($before, sideeffect_guard::snapshot($extra), $allowed);
                $this->report->set_feature($feature, ['tripwire' => $violations ? $violations : 'clean']);
                if ($violations) {
                    throw new tripwire_tripped('write_outside_declared_tables:' . implode(',', $violations));
                }
            }
            if ($outer) {
                $outer->allow_commit();
                $outer = null;
            }
        } catch (\Throwable $e) {
            if ($outer) {
                try {
                    $outer->rollback($e);
                } catch (\Throwable $rethrown) {
                    // The rollback rethrows the original exception; the failure is recorded below.
                    unset($rethrown);
                }
            }
            if (!$this->dryrun) {
                // An outer rollback undoes rows the cache believes exist.
                $this->map->reset();
            }
            $this->record_feature_failure($importer, $e);
            throw $e;
        }

        if (!$this->dryrun) {
            $this->finalise_feature($importer, $ctx);
            $this->writer->set_marker($feature, $this->runid);
        }
        $this->lookups->refresh();
        $this->available[$feature] = true;
        $this->report->set_feature($feature, ['status' => $this->dryrun ? 'simulated' : 'complete']);
        return $this->dryrun ? 'simulated' : 'complete';
    }

    /**
     * Reset PRESERVE sequences, then let the importer finalise. Outside any
     * transaction: reset_sequence is DDL on MySQL and commits implicitly.
     *
     * @param importer $importer
     * @param context $ctx
     * @return void
     */
    private function finalise_feature(importer $importer, context $ctx): void {
        global $DB;
        if ($DB->is_transaction_started()) {
            throw new bizlms_exception('transaction_open_before_finalise:' . $importer->feature());
        }
        $writer = $this->writer->for_importer($importer);
        $reset = [];
        foreach ($importer->steps() as $step) {
            if ($step instanceof step && $step->idpolicy() === idpolicy::PRESERVE && !isset($reset[$step->targettable()])) {
                $writer->reset_sequence($step->targettable());
                $reset[$step->targettable()] = true;
            }
        }
        $importer->finalise($ctx);
    }

    /**
     * Mark every running step of the feature failed and the feature failed.
     *
     * @param importer $importer
     * @param \Throwable $e
     * @return void
     */
    private function record_feature_failure(importer $importer, \Throwable $e): void {
        global $DB;
        $feature = $importer->feature();
        $message = self::safe_message($e);
        $this->report->set_feature($feature, ['status' => 'failed', 'error' => $message]);
        if ($this->dryrun || $DB->is_transaction_started()) {
            return;
        }
        $steps = $DB->get_records_select(self::STEP_TABLE, "runid = :r AND feature = :f AND status = 'running'",
            ['r' => $this->runid, 'f' => $feature], '', 'id');
        foreach ($steps as $row) {
            $this->writer->update_step((int) $row->id, (object) [
                'status' => 'failed', 'error' => $message, 'timemodified' => time(),
            ]);
        }
        if (!$steps && !$DB->record_exists(self::STEP_TABLE, ['runid' => $this->runid, 'feature' => $feature])) {
            // Feature mode rolled back every step row with the rest, so nothing durable says this feature
            // started and failed, and the status check would stay green. Leave a row that does.
            $row = self::new_step_row($this->runid, $feature, $feature . '.__feature', '', 'failed', 0, 0, null);
            $row['error'] = $message;
            $this->writer->create_step((object) $row);
        }
    }

    // Verify.

    /**
     * Framework verification plus the importer's own.
     *
     * @param importer $importer
     * @param context $ctx
     * @param bool $dryrun Dry run: the accounting identity uses the overlay, and target tables are not read.
     * @return string[] Failure lines.
     */
    private function verify_feature(importer $importer, context $ctx, bool $dryrun): array {
        global $DB;
        $failures = [];

        // Accounting identity: source rows (with the step filter) = primary map rows.
        $sources = [];
        foreach ($importer->steps() as $step) {
            if (!($step instanceof step) || $step->is_derived() || !$this->legacy->exists($step->physical_table())) {
                continue;
            }
            $name = $step->sourcetable();
            $sources[$name] = ($sources[$name] ?? 0) + $this->legacy->count($step->physical_table(), $step->source_filter());
        }
        foreach ($sources as $name => $count) {
            $mapped = $dryrun
                ? ($this->acctbase[$name] ?? 0) + ($this->acctnew[$name] ?? 0)
                : $this->primary_count($name);
            if ($count !== $mapped) {
                $failures[] = "accounting:{$name}: source={$count} mapped={$mapped}";
            }
        }
        if ($dryrun) {
            return $failures;
        }

        // Generic tenant verify.
        foreach ($importer->tenant_columns() as $table => $column) {
            fingerprint::assert_identifier($column);
            $rows = $DB->get_records_sql(
                "SELECT MIN(t.id) AS k, t.{$column} AS v, COUNT(1) AS n FROM {" . $table . "} t
                  WHERE t.{$column} IS NOT NULL GROUP BY t.{$column}");
            foreach ($rows as $row) {
                if (!self::is_valid_tenant_value((string) $row->v)) {
                    $failures[] = "invalid_tenant_value:{$table}.{$column}=" . \core_text::substr((string) $row->v, 0, 60)
                        . " rows={$row->n}";
                }
            }
        }

        foreach ($importer->verify($ctx) as $line) {
            $failures[] = (string) $line;
        }
        return $failures;
    }

    /**
     * A tenant value is valid when it normalises to a path whose root is registered.
     *
     * @param string $value
     * @return bool
     */
    public static function is_valid_tenant_value(string $value): bool {
        $path = tenant_resolver::normalise($value);
        if ($path === null) {
            return false;
        }
        try {
            \local_sentientia_platform\tenant::assert_valid((int) explode('/', ltrim($path, '/'))[0]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param string $sourcetable
     * @return int Primary map rows of a source table.
     */
    private function primary_count(string $sourcetable): int {
        global $DB;
        return $DB->count_records(legacymap::TABLE, ['sourcetable' => $sourcetable, 'subkey' => '']);
    }

    // Load steps.

    /**
     * Run one load step to completion (or resume it).
     *
     * @param importer $importer
     * @param step $step
     * @param context $ctx
     * @param writer $writer
     * @return void
     */
    private function run_load_step(importer $importer, step $step, context $ctx, writer $writer): void {
        $feature = $importer->feature();
        $key = $step->key();
        $source = $step->physical_table();
        $name = $step->sourcetable();

        if (!$this->legacy->exists($source)) {
            $this->record_not_applicable_step($feature, $key, $name);
            $this->report->set_step($feature, $key, ['status' => 'not_applicable']);
            return;
        }

        $fingerprint = fingerprint::table($source, $step->source_filter(), $this->crc_cap());
        $state = $this->open_step($feature, $key, $name, $fingerprint);
        if ($state['status'] === 'done') {
            $this->report->set_step($feature, $key, ['status' => 'done', 'note' => 'finished_in_the_interrupted_run']);
            return;
        }

        foreach ($step->preload() as $pair) {
            $this->map->preload((string) $pair[0], (string) ($pair[1] ?? ''));
        }
        if ($this->dryrun && !isset($this->acctbase[$name])) {
            $this->acctbase[$name] = $this->primary_count($name);
        }

        $onlyids = $this->options['retry_reasons'] ? $this->retry_ids($feature, $name) : null;
        $columns = $this->read_columns($step);
        $reader = new batch_source($this->legacy, $step, (int) $state['watermark'], (int) $this->options['batch'],
            (int) $this->options['max_group_scan'], $onlyids, $columns);

        $started = microtime(true);
        $batchno = 0;
        while (($groups = $reader->next_batch()) !== []) {
            $batchno++;
            $this->process_batch($importer, $step, $ctx, $writer, $groups, $state, $batchno);
        }

        $elapsed = max(0.001, microtime(true) - $started);
        $this->finish_step($feature, $key, $state, $fingerprint, $batchno, $elapsed, $reader->group_count());
    }

    /**
     * Rows above which a source fingerprint skips its CRC (0 or less: never skip).
     *
     * @return int
     */
    private function crc_cap(): int {
        $cap = (int) $this->options['crc_max_rows'];
        return $cap > 0 ? $cap : PHP_INT_MAX;
    }

    /**
     * Record that a step has nothing to do. Idempotent: a resumed run meets the
     * row it wrote the first time, and (runid, stepkey) is unique.
     *
     * @param string $feature
     * @param string $key
     * @param string $sourcetable
     * @return void
     */
    private function record_not_applicable_step(string $feature, string $key, string $sourcetable): void {
        global $DB;
        if ($this->dryrun || $DB->record_exists(self::STEP_TABLE, ['runid' => $this->runid, 'stepkey' => $key])) {
            return;
        }
        $this->writer->create_step((object) self::new_step_row($this->runid, $feature, $key, $sourcetable,
            'not_applicable', 0, 0, null));
    }

    /**
     * Columns to read: the step's, plus the adopt signature for a PRESERVE step.
     *
     * @param step $step
     * @return string[]
     */
    private function read_columns(step $step): array {
        $columns = $step->columns();
        if ($columns === ['*'] || $step->idpolicy() !== idpolicy::PRESERVE) {
            return $columns;
        }
        return array_values(array_unique(array_merge($columns, array_keys(self::signature($step)))));
    }

    /**
     * Source ids of rows skipped with a retryable reason the operator selected.
     *
     * @param string $feature
     * @param string $sourcetable
     * @return int[]
     */
    private function retry_ids(string $feature, string $sourcetable): array {
        global $DB;
        $codes = [];
        foreach ($this->options['retry_reasons'] as $code) {
            if (isset($this->reasons[$feature][$code]) && $this->reasons[$feature][$code]->retryable) {
                $codes[] = $code;
            }
        }
        if (!$codes) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($codes, SQL_PARAMS_NAMED, 'blmrc');
        $params['f'] = $feature;
        $params['st'] = $sourcetable;
        return array_map('intval', array_keys($DB->get_records_select(legacymap::TABLE,
            "feature = :f AND sourcetable = :st AND subkey = '' AND outcome = 'skipped' AND reason {$insql}",
            $params, 'sourceid', 'sourceid')));
    }

    /**
     * Open the step row, creating it or resuming it.
     *
     * @param string $feature
     * @param string $key
     * @param string $sourcetable
     * @param array{count: int, maxid: int, crc: ?string, columns: string[]} $fp
     * @return array The step state.
     * @throws source_drift When a resumed step's source changed.
     */
    private function open_step(string $feature, string $key, string $sourcetable, array $fp): array {
        global $DB;
        $state = [
            'id' => 0, 'status' => 'running', 'watermark' => 0, 'batches' => 0, 'already' => 0, 'deferred' => 0,
            'counters' => array_fill_keys(self::COUNTERS, 0),
        ];
        if ($this->dryrun) {
            return $state;
        }
        $existing = $DB->get_record(self::STEP_TABLE, ['runid' => $this->runid, 'stepkey' => $key]);
        if (!$existing) {
            $state['id'] = $this->writer->create_step((object) self::new_step_row($this->runid, $feature, $key,
                $sourcetable, 'running', $fp['count'], $fp['maxid'], $fp['crc']));
            return $state;
        }

        $state['id'] = (int) $existing->id;
        // A source that changed since the run started makes resume refuse, for a finished step too: the rows
        // it imported no longer match the source it saw.
        $samecrc = $existing->srccrc === null || $fp['crc'] === null || (string) $existing->srccrc === (string) $fp['crc'];
        if ((int) $existing->srccount !== $fp['count'] || (int) $existing->srcmaxid !== $fp['maxid'] || !$samecrc) {
            throw new source_drift('source_changed_since_the_run_started:' . $key);
        }
        if ($existing->status === 'done') {
            $state['status'] = 'done';
            return $state;
        }
        $state['watermark'] = (int) ($existing->watermark ?? 0);
        foreach (self::COUNTERS as $counter) {
            $state['counters'][$counter] = (int) $existing->{$counter};
        }
        $this->writer->update_step($state['id'], (object) [
            'status' => 'running', 'error' => null, 'timemodified' => time(),
        ]);
        return $state;
    }

    /**
     * Mark a step done and put its figures in the report.
     *
     * @param string $feature
     * @param string $key
     * @param array $state
     * @param array $fingerprint
     * @param int $batches
     * @param float $elapsed
     * @param int|null $groups
     * @return void
     */
    private function finish_step(string $feature, string $key, array $state, array $fingerprint, int $batches,
                                 float $elapsed, ?int $groups): void {
        if (!$this->dryrun) {
            $this->writer->update_step($state['id'], (object) [
                'status' => 'done', 'timemodified' => time(), 'timefinished' => time(),
            ]);
        }
        $this->report->set_step($feature, $key, [
            'status' => 'done',
            'source_count' => $fingerprint['count'],
            'source_max_id' => $fingerprint['maxid'],
            'source_crc' => $fingerprint['crc'],
            'groups' => $groups,
            'counters' => $state['counters'],
            'already_mapped' => $state['already'],
            'batches' => $batches,
            'rows_per_second' => round(($state['counters']['processed'] + $state['already']) / $elapsed, 1),
        ]);
    }

    /**
     * @param string $runid
     * @return array<string, mixed>
     */
    private static function new_step_row(int $runid, string $feature, string $key, string $sourcetable,
                                         string $status, int $count, int $maxid, ?string $crc): array {
        return [
            'runid' => $runid, 'feature' => $feature, 'stepkey' => $key, 'sourcetable' => $sourcetable,
            'status' => $status, 'watermark' => null, 'srccount' => $count, 'srcmaxid' => $maxid, 'srccrc' => $crc,
            'processed' => 0, 'imported' => 0, 'adopted' => 0, 'merged' => 0, 'folded' => 0, 'archived' => 0,
            'skipped' => 0, 'updated' => 0, 'error' => null,
            'timestarted' => time(), 'timemodified' => time(), 'timefinished' => 0,
        ];
    }

    /**
     * Transform and apply one batch of groups inside one transaction.
     *
     * @param importer $importer
     * @param step $step
     * @param context $ctx
     * @param writer $writer
     * @param array<int, array{key: int, rows: array<int, \stdClass>}> $groups
     * @param array $state
     * @param int $batchno
     * @return void
     */
    private function process_batch(importer $importer, step $step, context $ctx, writer $writer, array $groups,
                                   array &$state, int $batchno): void {
        global $DB;
        $tx = $this->dryrun ? null : $DB->start_delegated_transaction();
        $this->map->begin_batch();
        try {
            $maprows = [];
            $mapupdates = [];
            $counts = array_fill_keys(self::COUNTERS, 0);
            $already = 0;

            $entries = [];
            if (!$step->is_derived()) {
                $ids = [];
                foreach ($groups as $group) {
                    foreach (array_keys($group['rows']) as $id) {
                        $ids[] = $id;
                    }
                }
                $entries = $this->map->entries($step->sourcetable(), $ids);
            }

            foreach ($groups as $group) {
                $already += $this->process_group($importer, $step, $ctx, $writer, $group, $entries,
                    $maprows, $mapupdates, $counts);
            }
            if ($maprows) {
                $writer->insert_map_rows($maprows);
            }
            foreach ($mapupdates as [$mapid, $fields]) {
                $writer->update_map_row($mapid, $fields);
            }

            $last = end($groups);
            $watermark = (int) $last['key'];
            $newcounters = $state['counters'];
            foreach ($counts as $counter => $n) {
                $newcounters[$counter] += $n;
            }
            if (!$this->dryrun) {
                $fields = $newcounters + ['watermark' => $watermark, 'timemodified' => time()];
                $this->writer->update_step($state['id'], (object) $fields);
                $this->writer->update_run($this->runid, (object) ['heartbeat' => time()]);
            }
            if (is_callable($this->options['failpoint'])) {
                ($this->options['failpoint'])($step->key(), $batchno);
            }
            if ($tx) {
                $tx->allow_commit();
            }
            $this->map->commit_batch();
            if (!$this->dryrun) {
                // The rows are in the database now; keep memory bounded on a table of unknown size.
                $this->map->forget($step->sourcetable());
            }
            $state['counters'] = $newcounters;
            $state['watermark'] = $watermark;
            $state['already'] += $already;
        } catch (\Throwable $e) {
            $this->map->rollback_batch();
            if ($tx) {
                try {
                    $tx->rollback($e);
                } catch (\Throwable $rethrown) {
                    // The rollback rethrows the original exception; it is rethrown below.
                    unset($rethrown);
                }
            }
            throw $e;
        }
    }

    /**
     * Transform one group and apply its outcomes.
     *
     * @param importer $importer
     * @param step $step
     * @param context $ctx
     * @param writer $writer
     * @param array{key: int, rows: array<int, \stdClass>} $group
     * @param array<int, array|null> $entries Existing map entries of the batch's source ids.
     * @param \stdClass[] $maprows
     * @param array $mapupdates
     * @param array<string, int> $counts
     * @return int Rows skipped because they were already mapped.
     */
    private function process_group(importer $importer, step $step, context $ctx, writer $writer, array $group,
                                   array $entries, array &$maprows, array &$mapupdates, array &$counts): int {
        $feature = $importer->feature();
        $rows = $group['rows'];
        $retry = [];

        if (!$step->is_derived()) {
            $todo = [];
            $mapped = 0;
            foreach ($rows as $id => $row) {
                $entry = $entries[$id] ?? null;
                if ($entry === null) {
                    $todo[$id] = $row;
                } else if ($this->is_retry_target($feature, $entry)) {
                    $todo[$id] = $row;
                    $retry[$id] = $entry;
                } else {
                    $mapped++;
                }
            }
            if (!$todo) {
                return count($rows);
            }
            if ($mapped > 0) {
                throw new source_drift('partial_group_already_mapped:' . $step->key() . ':' . $group['key']);
            }
            $rows = $todo;
        }

        try {
            $outcomes = $step->transform(array_values($rows), $ctx);
        } catch (bizlms_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new bizlms_exception('transform_failed:' . $step->key() . ':' . get_class($e), 0, $e);
        }
        if (!is_array($outcomes)) {
            throw new bizlms_exception('transform_did_not_return_an_array:' . $step->key());
        }
        foreach ($this->text->drain() as $warning) {
            $this->report->count_warning($feature, $step->key(), $warning);
        }
        return $this->apply_outcomes($importer, $step, $rows, $outcomes, $writer, $retry, $maprows, $mapupdates,
            $counts);
    }

    /**
     * Is a mapped row a retryable skip the operator asked to re-attempt?
     *
     * @param string $feature
     * @param array $entry
     * @return bool
     */
    private function is_retry_target(string $feature, array $entry): bool {
        if (!$this->options['retry_reasons'] || $entry['outcome'] !== 'skipped' || empty($entry['id'])) {
            return false;
        }
        $code = (string) $entry['reason'];
        return in_array($code, $this->options['retry_reasons'], true)
            && ($this->reasons[$feature][$code]->retryable ?? false);
    }

    /**
     * Validate a group's outcomes and write them.
     *
     * @param importer $importer
     * @param step $step
     * @param array<int, \stdClass> $rows The source rows being settled, keyed by id.
     * @param array $outcomes
     * @param writer $writer
     * @param array<int, array> $retry Existing map entries being retried, by source id.
     * @param \stdClass[] $maprows
     * @param array $mapupdates
     * @param array<string, int> $counts
     * @return int Rows skipped because already mapped (derived groups only).
     */
    private function apply_outcomes(importer $importer, step $step, array $rows, array $outcomes, writer $writer,
                                    array $retry, array &$maprows, array &$mapupdates, array &$counts): int {
        $feature = $importer->feature();
        $key = $step->key();
        $name = $step->sourcetable();
        $derived = $step->is_derived();
        $vocabulary = $this->reasons[$feature];

        $primary = [];
        $subs = [];
        foreach ($outcomes as $o) {
            if (!($o instanceof outcome)) {
                throw new bizlms_exception('transform_returned_a_non_outcome:' . $key);
            }
            if ($o->kind === outcome::UPDATE) {
                throw new bizlms_exception('update_outcome_in_a_load_step:' . $key);
            }
            if (!$derived && !isset($rows[$o->sourceid])) {
                throw new bizlms_exception('outcome_for_a_row_outside_the_group:' . $key . ':' . $o->sourceid);
            }
            if (in_array($o->kind, [outcome::MERGE, outcome::FOLD, outcome::ARCHIVE, outcome::SKIP], true)
                    && !isset($vocabulary[$o->reason])) {
                throw new bizlms_exception('unknown_reason:' . $key . ':' . \core_text::substr($o->reason, 0, 40));
            }
            if ($o->is_primary()) {
                if (isset($primary[$o->sourceid])) {
                    throw new bizlms_exception('two_primary_outcomes:' . $key . ':' . $o->sourceid);
                }
                $primary[$o->sourceid] = $o;
            } else {
                $subkey = $o->sourceid . '|' . $o->subkey;
                if (isset($subs[$subkey])) {
                    throw new bizlms_exception('duplicate_subkey:' . $key . ':' . $o->sourceid);
                }
                $subs[$subkey] = $o;
            }
        }
        if ($derived) {
            if (count($primary) !== 1) {
                throw new bizlms_exception('a_derived_group_needs_exactly_one_primary_outcome:' . $key);
            }
        } else {
            foreach ($rows as $id => $row) {
                if (!isset($primary[$id])) {
                    throw new bizlms_exception('no_primary_outcome:' . $key . ':' . $id);
                }
            }
        }
        foreach ($subs as $o) {
            if (!isset($primary[$o->sourceid])) {
                throw new bizlms_exception('sub_row_without_a_primary_outcome:' . $key . ':' . $o->sourceid);
            }
        }
        ksort($primary);

        if ($derived) {
            $only = reset($primary);
            if ($this->map->entry($name, $only->sourceid) !== null) {
                return 1;
            }
        }

        $written = [];
        foreach ($primary as $sid => $o) {
            if ($o->kind === outcome::MERGE) {
                continue;
            }
            [$word, $table, $targetid] = $this->settle($step, $o, $rows[$sid] ?? null, $writer);
            $written[$sid] = [$table, $targetid];
            $this->record($feature, $step, $sid, '', $word, $table, $targetid, $o, $retry[$sid] ?? null,
                $maprows, $mapupdates, $counts);
        }
        foreach ($primary as $sid => $o) {
            if ($o->kind !== outcome::MERGE) {
                continue;
            }
            $winner = $written[$o->winnersourceid] ?? null;
            if ($winner === null) {
                $entry = $this->map->entry($name, $o->winnersourceid);
                $winner = $entry === null ? null : [$entry['targettable'], $entry['targetid']];
            }
            // A winner that was itself skipped or archived has no target ('' and null): the duplicate's data
            // would be in no target table while the accounting identity still balanced.
            if ($winner === null || $winner[1] === null || $winner[0] === '') {
                throw new bizlms_exception('merge_winner_has_no_target:' . $key . ':' . $o->winnersourceid);
            }
            $this->record($feature, $step, $sid, '', 'merged', $winner[0], $winner[1], $o, $retry[$sid] ?? null,
                $maprows, $mapupdates, $counts);
        }
        foreach ($subs as $o) {
            if ($this->dryrun) {
                $writer->check($o->table, $o->row, false, true);
                $targetid = $this->virtual_id();
            } else {
                $targetid = $writer->insert($o->table, $o->row);
            }
            $this->record($feature, $step, $o->sourceid, $o->subkey, 'imported', $o->table, $targetid, $o, null,
                $maprows, $mapupdates, $counts);
        }
        return 0;
    }

    /**
     * Turn a primary outcome into its target write and map facts.
     *
     * @param step $step
     * @param outcome $o
     * @param \stdClass|null $sourcerow
     * @param writer $writer
     * @return array{0: string, 1: string, 2: int|null} [outcome word, target table, target id]
     */
    private function settle(step $step, outcome $o, ?\stdClass $sourcerow, writer $writer): array {
        global $DB;
        switch ($o->kind) {
            case outcome::FOLD:
                // A fold writes nothing, so nothing else would notice an undeclared table or a target that is
                // not there. A virtual (negative) id only exists in a dry run.
                $writer->check_table($o->table);
                if ((int) $o->targetid > 0 && !$DB->record_exists($o->table, ['id' => $o->targetid])) {
                    throw new blocked('fold_target_missing:' . $o->table . ':' . $o->targetid);
                }
                return ['folded', $o->table, $o->targetid];
            case outcome::ARCHIVE:
                return ['archived', '', null];
            case outcome::SKIP:
                return ['skipped', '', null];
            case outcome::ADOPT:
                $existing = $DB->get_record($o->table, ['id' => $o->targetid]);
                if (!$existing || $sourcerow === null || !self::matches_signature($sourcerow, $existing, self::signature($step))) {
                    throw new blocked('adopt_refused_not_an_identical_copy:' . $o->table . ':' . $o->targetid);
                }
                $this->dryrun ? $writer->check($o->table, $o->row) : $writer->adopt($o->table, $o->targetid, $o->row);
                return ['adopted', $o->table, $o->targetid];
        }

        // INSERT.
        $preserve = $step->idpolicy() === idpolicy::PRESERVE && $o->table === $step->targettable();
        if ($preserve) {
            $id = $o->sourceid;
            $existing = $DB->get_record($o->table, ['id' => $id]);
            if ($existing) {
                if ($sourcerow === null || !self::matches_signature($sourcerow, $existing, self::signature($step))) {
                    throw new blocked('preserve_collision:' . $o->table . ':' . $id);
                }
                $this->dryrun ? $writer->check($o->table, $o->row) : $writer->adopt($o->table, $id, $o->row);
                return ['adopted', $o->table, $id];
            }
            if ($this->dryrun) {
                $writer->check($o->table, $o->row);
            } else {
                $writer->import_preserved($o->table, $o->row, $id);
            }
            return ['imported', $o->table, $id];
        }
        if ($this->dryrun) {
            $writer->check($o->table, $o->row, false, true);
            return ['imported', $o->table, $this->virtual_id()];
        }
        return ['imported', $o->table, $writer->insert($o->table, $o->row)];
    }

    /**
     * Record one outcome: the map row (or its update), the in-memory entry, the
     * step counters and the report.
     *
     * @param string $feature
     * @param step $step
     * @param int $sid
     * @param string $subkey
     * @param string $word
     * @param string $table
     * @param int|null $targetid
     * @param outcome $o
     * @param array|null $retry Existing map entry being retried.
     * @param \stdClass[] $maprows
     * @param array $mapupdates
     * @param array<string, int> $counts
     * @return void
     */
    private function record(string $feature, step $step, int $sid, string $subkey, string $word, string $table,
                            ?int $targetid, outcome $o, ?array $retry, array &$maprows, array &$mapupdates,
                            array &$counts): void {
        $name = $step->sourcetable();
        $reason = $o->reason !== '' ? $o->reason : null;
        $detail = \core_text::substr($o->detail, 0, 255);
        $detail = $detail !== '' ? $detail : null;

        if (!$this->dryrun) {
            if ($retry !== null && $subkey === '') {
                $mapupdates[] = [(int) $retry['id'], (object) [
                    'targettable' => $table, 'targetid' => $targetid, 'outcome' => $word, 'reason' => $reason,
                    'detail' => $detail, 'runid' => $this->runid,
                ]];
            } else {
                $maprows[] = (object) [
                    'feature' => $feature, 'sourcetable' => $name, 'sourceid' => $sid, 'subkey' => $subkey,
                    'targettable' => $table, 'targetid' => $targetid, 'outcome' => $word, 'reason' => $reason,
                    'detail' => $detail, 'runid' => $this->runid, 'timecreated' => time(),
                ];
            }
        }
        $this->map->remember($name, $sid, $subkey, [
            'targettable' => $table, 'targetid' => $targetid, 'outcome' => $word, 'reason' => $reason,
            'id' => $retry['id'] ?? 0,
        ]);

        foreach ($o->warnings as $warning) {
            $this->report->count_warning($feature, $step->key(), $warning);
        }
        if ($subkey !== '') {
            return;
        }
        if ($o->tenantmethod !== null) {
            $this->report->count_tenant_method($feature, $step->key(), $o->tenantmethod);
        }
        $counts['processed']++;
        $counts[$word]++;
        if ($this->dryrun) {
            $this->acctnew[$name] = ($this->acctnew[$name] ?? 0) + 1;
        }
        if ($word !== 'imported' && $word !== 'adopted') {
            $this->report->non_imported($feature, $name, $sid, $subkey, $word, (string) $reason, (string) $detail);
            if ($reason !== null) {
                $this->report->count_reason($feature, $step->key(), $reason);
                $this->reasontally[$feature][$reason] = ($this->reasontally[$feature][$reason] ?? 0) + 1;
            }
        }
    }

    // Recompute steps.

    /**
     * Second pass over the rows this run imported or adopted.
     *
     * @param importer $importer
     * @param recompute_step $step
     * @param context $ctx
     * @param writer $writer
     * @return void
     */
    private function run_recompute_step(importer $importer, recompute_step $step, context $ctx, writer $writer): void {
        global $DB;
        $feature = $importer->feature();
        $key = $step->key();
        if ($this->dryrun) {
            $this->report->set_step($feature, $key, ['status' => 'not_simulated_in_a_dry_run']);
            return;
        }
        $state = $this->open_step($feature, $key, '', ['count' => 0, 'maxid' => 0, 'crc' => null, 'columns' => []]);
        if ($state['status'] === 'done') {
            return;
        }
        $table = $step->targettable();
        $batch = max(1, (int) $this->options['batch']);
        $after = (int) $state['watermark'];
        $batchno = 0;
        // A recompute step is idempotent by contract, so it runs over EVERY row of the feature that the import
        // created or adopted, not only this run's. Scoping by run would make a fresh apply after a failed run
        // find nothing, finish "done" and let the feature be marked complete with its second pass never run.
        // Only --retry-skipped is scoped to its own run: it touches just the rows it moved to a new outcome.
        $scope = $this->options['retry_reasons'] ? ' AND runid = :r' : '';
        do {
            // Moodle refuses a named parameter the SQL does not use, so :r is passed only when it is in the SQL.
            $params = ['f' => $feature, 't' => $table, 'after' => $after];
            if ($scope !== '') {
                $params['r'] = $this->runid;
            }
            $rows = $DB->get_records_select(legacymap::TABLE,
                'feature = :f AND targettable = :t AND id > :after AND outcome IN (\'imported\', \'adopted\')' . $scope,
                $params, 'id ASC', 'id, targetid', 0, $batch);
            if (!$rows) {
                break;
            }
            $batchno++;
            $tx = $DB->start_delegated_transaction();
            try {
                $targetids = [];
                foreach ($rows as $row) {
                    $targetids[] = (int) $row->targetid;
                    $after = (int) $row->id;
                }
                $outcomes = $step->recompute($targetids, $ctx);
                $updated = 0;
                foreach ($outcomes as $o) {
                    if (!($o instanceof outcome) || $o->kind !== outcome::UPDATE) {
                        throw new bizlms_exception('recompute_step_may_only_return_update_outcomes:' . $key);
                    }
                    if ($o->table === $table || in_array($o->table, $importer->target_tables(), true)) {
                        $writer->update_own($o->table, (int) $o->targetid, $o->row);
                    } else {
                        $writer->update_core($o->table, (int) $o->targetid, $o->row);
                    }
                    $updated++;
                }
                $state['counters']['processed'] += count($targetids);
                $state['counters']['updated'] += $updated;
                $this->writer->update_step($state['id'], (object) ($state['counters'] + [
                    'watermark' => $after, 'timemodified' => time(),
                ]));
                $this->writer->update_run($this->runid, (object) ['heartbeat' => time()]);
                if (is_callable($this->options['failpoint'])) {
                    ($this->options['failpoint'])($key, $batchno);
                }
                $tx->allow_commit();
            } catch (\Throwable $e) {
                try {
                    $tx->rollback($e);
                } catch (\Throwable $rethrown) {
                    // The rollback rethrows the original exception; it is rethrown below.
                    unset($rethrown);
                }
                throw $e;
            }
        } while (count($rows) === $batch);

        $this->writer->update_step($state['id'], (object) [
            'status' => 'done', 'timemodified' => time(), 'timefinished' => time(),
        ]);
        $this->report->set_step($feature, $key, ['status' => 'done', 'counters' => $state['counters'],
            'batches' => $batchno]);
    }

    // Helpers.

    /**
     * A negative id for a row a dry run did not write.
     *
     * @return int
     */
    private function virtual_id(): int {
        return --$this->virtualid;
    }

    /**
     * Normalise a step's adopt signature to source column => target column.
     *
     * @param step $step
     * @return array<string, string>
     */
    private static function signature(step $step): array {
        $signature = [];
        foreach ($step->adopt_signature() as $source => $target) {
            $signature[is_int($source) ? $target : $source] = $target;
        }
        foreach ($signature as $source => $target) {
            fingerprint::assert_identifier($source);
            fingerprint::assert_identifier($target);
        }
        return $signature;
    }

    /**
     * Does a target row equal the source on every signature column?
     *
     * @param \stdClass $source
     * @param \stdClass $target
     * @param array<string, string> $signature
     * @return bool
     */
    private static function matches_signature(\stdClass $source, \stdClass $target, array $signature): bool {
        if (!$signature) {
            return false;
        }
        foreach ($signature as $sourcecolumn => $targetcolumn) {
            if (!property_exists($source, $sourcecolumn) || !property_exists($target, $targetcolumn)) {
                return false;
            }
            if ((string) $source->{$sourcecolumn} !== (string) $target->{$targetcolumn}) {
                return false;
            }
        }
        return true;
    }

    /**
     * The part of an exception that may be stored: the message of a framework
     * exception (ids and codes), otherwise the class name only. A database
     * exception message can carry SQL and row values.
     *
     * @param \Throwable $e
     * @return string
     */
    public static function safe_message(\Throwable $e): string {
        if ($e instanceof bizlms_exception) {
            return \core_text::substr($e->getMessage(), 0, 500);
        }
        return get_class($e);
    }
}
