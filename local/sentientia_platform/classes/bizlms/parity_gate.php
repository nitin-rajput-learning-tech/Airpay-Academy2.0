<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\parity\database as parity_database;
use local_sentientia_platform\parity\legacy as parity_legacy;

/**
 * What cli/migration_parity_check.php needs from the import framework (ADR-032 "Parity hooks", Stage B gate 4).
 *
 * The parity tool compares a database with the baseline taken on the SOURCE. After the import three things differ from
 * that baseline on purpose: the import inserted enrolments, enrol instances and role assignments, it filled a few empty
 * course columns, and it moved tag instances to the core course area. This class reads the import's OWN records
 * (local_sentientia_legacymap and the importers' ledgers) and says exactly which rows and columns those are, so that
 * parity_core::evaluate() can hold every other row and column to the baseline.
 *
 * Nothing here writes.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parity_gate {

    /**
     * Core tables the import only INSERTS into. The rows it inserted are the map rows with outcome imported and one of
     * these as target table (a fan-out sub-row has a subkey but the same outcome and target table).
     *
     * @var string[]
     */
    public const INSERT_TABLES = ['user_enrolments', 'enrol', 'role_assignments'];

    /**
     * Parity count metric => the core table whose rows the import inserted.
     *
     * @var array<string, string>
     */
    public const COUNT_KEYS = [
        'enrolments' => 'user_enrolments',
        'enrol_instances' => 'enrol',
        'role_assignments' => 'role_assignments',
    ];

    /**
     * Core tables the import UPDATES in place, and the importer's own ledger that names each row (and column) it
     * wrote. 'columns' is the ledger column holding a comma list of the columns written, or null when the importer
     * always writes the fixed list in 'written'.
     *
     * @var array<string, array{table: string, key: string, columns: ?string, written: string[]}>
     */
    public const LEDGERS = [
        'course' => ['table' => 'local_sentientia_courses_detailfill', 'key' => 'courseid', 'columns' => 'filledcols',
            'written' => []],
        'tag_instance' => ['table' => 'local_sentientia_courses_tagmove', 'key' => 'taginstanceid', 'columns' => null,
            'written' => ['component', 'itemtype']],
    ];

    /** The map's run table. */
    private const RUN_TABLE = 'local_sentientia_legacyrun';

    /** The map's step table. */
    private const STEP_TABLE = 'local_sentientia_legacystep';

    /** Counters a report and a step row both carry. */
    private const COUNTERS = ['processed', 'imported', 'adopted', 'merged', 'folded', 'archived', 'skipped', 'updated'];

    /**
     * Load the metrics library (cli/source_baseline.php) without running its command line. The standalone baseline
     * tool and this plugin use that one file, so a number is computed by the same code on both sides.
     *
     * @return void
     */
    public static function load_library(): void {
        if (!defined('SENTIENTIA_PARITY_LIBRARY_ONLY')) {
            define('SENTIENTIA_PARITY_LIBRARY_ONLY', true);
        }
        require_once(__DIR__ . '/../../cli/source_baseline.php');
    }

    /**
     * A core table the registry lets importers write that this class cannot explain. Adding a table to
     * registry::CORE_WRITES_ALLOWED without adding it here (and to parity\core::WRITES) leaves the parity check blind to
     * it, so it is a hard problem and a test.
     *
     * @return string[]
     */
    public static function unexplained_core_writes(): array {
        $explained = array_merge(self::INSERT_TABLES, array_keys(self::LEDGERS));
        return array_values(array_diff(array_keys(registry::CORE_WRITES_ALLOWED), $explained));
    }

    /**
     * Why the post-import comparison cannot be made at all. Each line is a refusal (exit 3): there is nothing to explain
     * the deltas with.
     *
     * @param int|null $runid The run to explain the deltas with, or null for every apply run.
     * @return string[]
     */
    public static function refusals(?int $runid): array {
        global $DB;
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(legacymap::TABLE) || !$dbman->table_exists(self::RUN_TABLE)) {
            return ['the_framework_tables_are_missing_so_no_import_ran_here'];
        }
        $out = [];
        if ($runid !== null) {
            $run = $DB->get_record(self::RUN_TABLE, ['id' => $runid]);
            if (!$run) {
                return ['run_not_found:' . $runid];
            }
            if ($run->runmode !== 'apply') {
                $out[] = 'run_is_not_an_apply_run:' . $runid;
            }
            if ($run->status !== 'complete') {
                $out[] = 'run_did_not_complete:' . $runid . ':' . $run->status;
            }
            if ((string) $run->fingerprint !== fingerprint::install()) {
                $out[] = 'run_is_from_another_install:' . $runid;
            }
        } else if (!$DB->record_exists_select(self::RUN_TABLE, "runmode = 'apply' AND status = 'complete'")) {
            $out[] = 'no_complete_apply_run_in_the_database';
        }
        return $out;
    }

    /**
     * What the import's own records say it wrote to each core table.
     *
     * Insert tables: the target ids of the map rows with outcome imported. Update tables: the rows (and columns) the
     * importer's ledger names. With a run id only that run's map rows count, and a ledger row counts when the map says
     * that run imported it.
     *
     * @param int|null $runid
     * @return array<string, array{inserted?: int[], changed?: array<int, string[]>}>
     */
    public static function expected(?int $runid): array {
        global $DB;
        $dbman = $DB->get_manager();
        $out = [];
        $runsql = $runid === null ? '' : ' AND runid = :blmrun';
        $runparams = $runid === null ? [] : ['blmrun' => $runid];

        foreach (self::INSERT_TABLES as $table) {
            $ids = $DB->get_fieldset_sql(
                'SELECT DISTINCT targetid FROM {' . legacymap::TABLE . "}
                  WHERE targettable = :blmtable AND outcome = 'imported' AND targetid IS NOT NULL" . $runsql,
                ['blmtable' => $table] + $runparams);
            $out[$table] = ['inserted' => array_map('intval', $ids)];
        }

        foreach (self::LEDGERS as $table => $ledger) {
            $out[$table] = ['changed' => []];
            if (!$dbman->table_exists($ledger['table'])) {
                continue;
            }
            $where = '';
            $params = [];
            if ($runid !== null) {
                $where = ' WHERE l.id IN (SELECT m.targetid FROM {' . legacymap::TABLE . '} m
                                           WHERE m.targettable = :blmledger AND m.outcome = \'imported\'
                                             AND m.runid = :blmrun)';
                $params = ['blmledger' => $ledger['table'], 'blmrun' => $runid];
            }
            $select = 'l.id AS id, l.' . $ledger['key'] . ' AS target' . ($ledger['columns'] !== null ? ', l.' . $ledger['columns'] . ' AS cols' : '');
            $rows = $DB->get_recordset_sql('SELECT ' . $select . ' FROM {' . $ledger['table'] . '} l' . $where, $params);
            foreach ($rows as $row) {
                if ($ledger['columns'] === null) {
                    $columns = $ledger['written'];
                } else {
                    $columns = array_values(array_filter(explode(',', (string) $row->cols), 'strlen'));
                    sort($columns);
                    if (!$columns) {
                        // A trail row that wrote nothing names no change.
                        continue;
                    }
                }
                $out[$table]['changed'][(int) $row->target] = $columns;
            }
            $rows->close();
        }
        return $out;
    }

    /**
     * How much each parity count grew because of the import.
     *
     * @param array<string, array> $expected As returned by expected().
     * @return array<string, int> Count metric => rows the import inserted.
     */
    public static function explained_counts(array $expected): array {
        $out = [];
        foreach (self::COUNT_KEYS as $metric => $table) {
            $out[$metric] = count($expected[$table]['inserted'] ?? []);
        }
        return $out;
    }

    /**
     * The legacy tables of a baseline, compared with this database by name, and any table that is legacy now but was
     * not in the baseline.
     *
     * The current fingerprints come from the same code that took the baseline (parity\legacy), with no row cap.
     *
     * @param parity_database $db
     * @param array<string, array> $baselinelegacy The baseline's 'legacy' section.
     * @param array<string, array> $baselineother The baseline's 'legacy_other' section.
     * @return array{comparison: array, other: string[]} The comparison parity::comparison_problems() sorts, and the
     *         unproven lines of the tables no inventory names.
     */
    public static function legacy_comparison(parity_database $db, array $baselinelegacy, array $baselineother): array {
        self::load_library();
        $current = parity_legacy::fingerprints($db, array_keys($baselinelegacy));
        // A table that is legacy here and in neither section of the baseline: the comparison lists it as new (unproven).
        $known = array_merge(array_keys($baselinelegacy), array_keys($baselineother));
        foreach (array_diff(legacy_tables::detect(), $known) as $table) {
            $current[$table] = ['count' => 0, 'maxid' => 0, 'crc' => null, 'columns' => []];
        }
        $comparison = parity::compare_fingerprints($baselinelegacy, $current);
        $othernow = parity_legacy::fingerprints($db, array_keys($baselineother));
        return ['comparison' => $comparison, 'other' => parity_legacy::other_unproven($baselineother, $othernow)];
    }

    /**
     * Needs-owner reasons the decisions file has not accepted, and unclaimed legacy tables that hold rows: the unproven
     * items of ADR-032 "Parity hooks" 3 (exit 2), the same ones import_bizlms.php reports at the end of an apply run.
     *
     * @param decisions $decisions
     * @param importer[] $importers registry::load()
     * @return string[]
     */
    public static function unproven(decisions $decisions, array $importers): array {
        global $DB;
        $out = [];
        $reasons = [];
        foreach ($importers as $feature => $importer) {
            foreach ($importer->reasons() as $reason) {
                $reasons[$feature][$reason->code] = $reason;
            }
        }
        $rows = $DB->get_records_sql(
            'SELECT MIN(id) AS k, feature, reason, COUNT(1) AS n FROM {' . legacymap::TABLE . '}
              WHERE reason IS NOT NULL GROUP BY feature, reason');
        foreach ($rows as $row) {
            $reason = $reasons[$row->feature][$row->reason] ?? null;
            if ($reason !== null && $reason->needsowner && !$decisions->accepts($row->feature, $row->reason)) {
                $out[] = "{$row->feature}:{$row->reason}={$row->n}";
            }
        }
        sort($out);
        foreach (unclaimed::with_rows($importers) as $table) {
            $out[] = 'unclaimed_table:' . $table;
        }
        return $out;
    }

    /**
     * Cross-check an import report (import_bizlms.php --report) with the database and the other options.
     *
     * The report leaves the database, so this proves it is the report of THIS install's run, made with these decisions,
     * and that its step counters are the ones the database holds.
     *
     * @param array $report The decoded JSON.
     * @param int|null $runid The --run option, or null.
     * @param decisions|null $decisions
     * @return string[] Hard problems.
     */
    public static function report_problems(array $report, ?int $runid, ?decisions $decisions): array {
        global $DB;
        $meta = (array) ($report['meta'] ?? []);
        $problems = [];
        $reportrun = (int) ($meta['runid'] ?? 0);
        if ($reportrun <= 0) {
            return ['report_has_no_run_id'];
        }
        if (!empty($meta['dryrun'])) {
            $problems[] = 'report_is_of_a_dry_run';
        }
        if (($meta['mode'] ?? '') !== 'apply') {
            $problems[] = 'report_mode_is_not_apply:' . (string) ($meta['mode'] ?? '');
        }
        if (isset($meta['fingerprint']) && (string) $meta['fingerprint'] !== fingerprint::install()) {
            $problems[] = 'report_is_from_another_install';
        }
        if ($runid !== null && $runid !== $reportrun) {
            $problems[] = "report_is_of_run_{$reportrun}_not_run_{$runid}";
        }
        if ($decisions !== null && (string) ($meta['decisions_hash'] ?? '') !== $decisions->hash()) {
            $problems[] = 'report_was_made_with_other_decisions';
        }
        if (!$DB->record_exists(self::RUN_TABLE, ['id' => $reportrun])) {
            $problems[] = 'report_run_is_not_in_the_database:' . $reportrun;
            return $problems;
        }

        foreach ((array) ($report['features'] ?? []) as $feature => $section) {
            foreach ((array) ($section['steps'] ?? []) as $key => $step) {
                if (!isset($step['counters'])) {
                    continue;
                }
                $row = $DB->get_record(self::STEP_TABLE, ['runid' => $reportrun, 'stepkey' => (string) $key]);
                if (!$row) {
                    $problems[] = "report_step_not_in_the_database:{$feature}:{$key}";
                    continue;
                }
                foreach (self::COUNTERS as $counter) {
                    $inreport = (int) ($step['counters'][$counter] ?? 0);
                    if ($inreport !== (int) $row->{$counter}) {
                        $problems[] = "report_step_counter_differs:{$feature}:{$key}:{$counter} report={$inreport} database={$row->{$counter}}";
                    }
                }
            }
        }
        return $problems;
    }
}
