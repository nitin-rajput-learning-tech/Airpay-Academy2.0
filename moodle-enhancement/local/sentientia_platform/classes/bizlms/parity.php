<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Parity hooks of the import (ADR-032, "Parity hooks"). Pure functions that
 * cli/migration_parity_check.php calls; this class does not edit that script.
 *
 * 1. legacy_fingerprints() / compare_fingerprints(): count, MAX(id), CRC over all
 *    columns (sorted by name) and the column list of every legacy table. Taken on
 *    the restored 4.1.2 copy before the core hops; on --compare every legacy table
 *    must be present and identical, which proves the hops and the import left the
 *    archive untouched. A column change counts as drift; a missing table is drift.
 *    The CRC is null on engines without CRC32 and is then reported as skipped; comparison_problems()
 *    turns skipped into an unproven item (exit 2), never a pass.
 * 2. invariant_problems(): the bizlms_import invariant. Returns an empty list when
 *    the database holds no legacy tables (a fresh install). Every problem is a hard
 *    failure (exit 1). compare_invariant() is the form the parity CLI uses: with no decisions
 *    file it says "not proven" (exit 2) instead of failing every importer's verify().
 * 3. Unproven items (exit 2) are unclaimed legacy tables holding rows and needs-owner
 *    reasons the decisions file has not accepted; see unclaimed and runner.
 * 4. The core counts and checksums stay the proof that the import had no side effects on users, completions, attempts,
 *    badges and grades. The ONE core table the import writes on purpose that those counts cover is user_enrolments (the
 *    enrolments importer, gap G6): imported_enrolments(), enrolment_count_explained() and
 *    enrolment_checksum_explained() explain exactly that delta from the legacy map, and nothing else (owner decision,
 *    2026-10-07). Any other difference stays drift.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class parity {

    /** Problems listed per check before "and more" is implied. */
    private const SAMPLE = 10;

    /**
     * Fingerprint every legacy table.
     *
     * @param int $crcmaxrows Skip a table's CRC above this many rows. The default reads every row: the
     *        archive proof is taken once on the restored copy and once after the import, not per step.
     * @return array<string, array{count: int, maxid: int, crc: ?string, columns: string[]}> table => fingerprint
     */
    public static function legacy_fingerprints(int $crcmaxrows = PHP_INT_MAX): array {
        $out = [];
        foreach (legacy_tables::detect() as $table) {
            $out[$table] = fingerprint::table($table, ['', []], $crcmaxrows);
        }
        return $out;
    }

    /**
     * Compare a baseline of legacy fingerprints with the current ones.
     *
     * @param array $baseline As returned by legacy_fingerprints() when the baseline was taken.
     * @param array $current As returned by legacy_fingerprints() now.
     * @return array{drift: string[], missing: string[], skipped: string[], new: string[]}
     */
    public static function compare_fingerprints(array $baseline, array $current): array {
        $out = ['drift' => [], 'missing' => [], 'skipped' => [], 'new' => []];
        foreach ($baseline as $table => $was) {
            if (!isset($current[$table])) {
                $out['missing'][] = $table;
                continue;
            }
            $now = $current[$table];
            if ((int) $was['count'] !== (int) $now['count'] || (int) $was['maxid'] !== (int) $now['maxid']
                    || array_values((array) $was['columns']) !== array_values((array) $now['columns'])) {
                $out['drift'][] = "{$table} rows {$was['count']}->{$now['count']} maxid {$was['maxid']}->{$now['maxid']}";
                continue;
            }
            if ($was['crc'] === null || $now['crc'] === null) {
                $out['skipped'][] = $table;
                continue;
            }
            if ((string) $was['crc'] !== (string) $now['crc']) {
                $out['drift'][] = "{$table} crc {$was['crc']}->{$now['crc']}";
            }
        }
        foreach (array_diff_key($current, $baseline) as $table => $fp) {
            $out['new'][] = $table;
        }
        return $out;
    }

    /**
     * Sort a comparison into hard failures (exit 1) and unproven items (exit 2). The caller
     * (cli/migration_parity_check.php, P0.4) prints both and takes the worse exit code.
     *
     * A table whose CRC was skipped (no CRC32 on the engine, or more rows than --crc-max-rows) matched on
     * count, max id and columns only. That is not proof the archive is untouched: an UPDATE changes neither,
     * so it is unproven and never a pass. A table that is not in the baseline is unproven too: something
     * created it after the baseline was taken. The baseline itself must be taken with no CRC cap
     * (legacy_fingerprints() default), or every comparison against it is unproven.
     *
     * @param array{drift: string[], missing: string[], skipped: string[], new: string[]} $comparison
     * @return array{hard: string[], unproven: string[]}
     */
    public static function comparison_problems(array $comparison): array {
        $hard = [];
        $unproven = [];
        foreach ($comparison['drift'] as $line) {
            $hard[] = 'legacy_table_changed:' . $line;
        }
        foreach ($comparison['missing'] as $table) {
            $hard[] = 'legacy_table_missing:' . $table;
        }
        foreach ($comparison['skipped'] as $table) {
            $unproven[] = 'legacy_table_crc_skipped:' . $table;
        }
        foreach ($comparison['new'] as $table) {
            $unproven[] = 'legacy_table_not_in_the_baseline:' . $table;
        }
        return ['hard' => $hard, 'unproven' => $unproven];
    }

    /**
     * The bizlms_import invariant: hard problems, empty when none.
     *
     * - an applicable feature without its completion marker;
     * - per source table, source rows (with the step filter) differ from primary map rows;
     * - a source row with no map row;
     * - an imported or adopted map row whose target is gone (skipped once the
     *   runbook sets bizlms_production_open, since admins may delete rows after go-live);
     * - a tenant_columns() value that is neither NULL nor a valid path with a registered root;
     * - a legacy table whose fingerprint differs from the one stored on the run;
     * - any importer::verify() failure.
     *
     * @return string[]
     */
    public static function invariant_problems(?decisions $decisions = null): array {
        global $DB;
        $dbman = $DB->get_manager();
        $legacy = legacy_tables::detect();
        if (!$legacy) {
            return [];
        }
        $legacy = array_flip($legacy);
        $decisions ??= decisions::none();
        if (!$dbman->table_exists(legacymap::TABLE)) {
            return ['framework_tables_missing'];
        }
        try {
            $importers = registry::load();
        } catch (registry_error $e) {
            return array_merge(['registry_invalid'], $e->problems);
        }

        $open = (int) get_config(writer::COMPONENT, 'bizlms_production_open') > 0;
        $reader = new legacy_reader();
        $problems = [];

        foreach ($importers as $feature => $importer) {
            $applicable = false;
            foreach (array_keys($importer->sources()) as $table) {
                if ($reader->exists($table)) {
                    $applicable = true;
                    break;
                }
            }
            if (!$applicable) {
                continue;
            }
            if (!legacymap::feature_complete($feature)) {
                $problems[] = "feature_not_complete:{$feature}";
                continue;
            }
            array_push($problems, ...self::accounting_problems($feature, $importer, $reader));
            if (!$open) {
                array_push($problems, ...self::missing_target_problems($feature, $importer));
            }
            array_push($problems, ...self::tenant_problems($feature, $importer));
            array_push($problems, ...self::mutation_problems($feature, $importer, $reader, $legacy));
            array_push($problems, ...self::verify_problems($feature, $importer, $decisions));
        }
        return $problems;
    }

    /**
     * The bizlms_import invariant as cli/migration_parity_check.php --compare reports it.
     *
     * Every importer's verify() reads owner decisions (cart.abandoned, notifications.import_bodies, ...), and a decision with
     * no default throws blocked('missing_decision:...') when the run has none. Run without the decisions, the invariant
     * therefore failed on every database that had imported cart or e-mail data: a clean import reported as an invariant FAIL
     * (review of 2026-10-07, must-fix 1). That is a false result, so:
     *
     * - no decisions and no legacy tables: nothing to prove, an empty list (a fresh install still passes);
     * - no decisions and legacy tables present: a string, never a list. The CLI prints it as SKIPPED and exits 2 ("not
     *   proven"), the same way it treats every other check that could not run. It is never a pass and never a FAIL;
     * - decisions given (the rehearsed file, pinned by --expect-decisions-hash): the full invariant, and any problem,
     *   including a decision the file does not hold, is a real FAIL.
     *
     * @param decisions|null $decisions The decisions the import ran with, or null when the caller has none.
     * @return string[]|string A list of problems (empty = OK), or the reason the invariant could not run.
     */
    public static function compare_invariant(?decisions $decisions): array|string {
        if ($decisions === null) {
            if (!legacy_tables::detect()) {
                return [];
            }
            return 'not proven: every importer verify() reads owner decisions, so the invariant needs the rehearsed file '
                . '(--decisions=FILE with --expect-decisions-hash=SHA256)';
        }
        return self::invariant_problems($decisions);
    }

    /**
     * What the enrolments import (gap G6) added to the core user_enrolments table, found from the legacy map.
     *
     * Owner decision, 2026-10-07 (courses cluster, parity doc item): the import turns each orphaned BizLMS enrolment into a
     * MANUAL enrolment, so core user_enrolments holds about 7 733 more rows after it than the source baseline (April 2026
     * copy), and migration_parity_check.php counts and checksums that table. A clean import therefore drifted by design and
     * the rehearsal's "100% PARITY" step could not pass. The delta is explained here, and only here, by the map: every row
     * the map says the feature `enrolments` imported into `user_enrolments`, and that still exists. Anything beyond that
     * stays drift. (The enrol instances the import switched off or added are not in the baseline's counts or checksums.)
     *
     * @param string[] $columns The columns the parity checksum of user_enrolments hashes, in its order.
     * @return array{rows: int, crc: ?string, switched_off: int} Rows added; the SUM(CRC32(row)) of exactly those rows built
     *         like the checksum (null on an engine without CRC32, or with no row); BizLMS instances switched off.
     */
    public static function imported_enrolments(array $columns): array {
        global $DB;
        $out = ['rows' => 0, 'crc' => null, 'switched_off' => 0];
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(legacymap::TABLE) || !$dbman->table_exists('user_enrolments')) {
            return $out;
        }
        $join = "FROM {user_enrolments} ue
                 JOIN {" . legacymap::TABLE . "} m ON m.targetid = ue.id
                WHERE m.feature = :f AND m.targettable = :t AND m.outcome = :o AND m.subkey = :k";
        $params = ['f' => 'enrolments', 't' => 'user_enrolments', 'o' => 'imported', 'k' => ''];
        $out['rows'] = (int) $DB->count_records_sql("SELECT COUNT(1) {$join}", $params);

        if ($out['rows'] > 0 && $DB->get_dbfamily() === 'mysql') {
            $existing = array_keys($DB->get_columns('user_enrolments'));
            $parts = [];
            foreach (array_values(array_intersect($columns, $existing)) as $column) {
                fingerprint::assert_identifier($column);
                $parts[] = "IFNULL(ue.`{$column}`, '~NULL~')";
            }
            if ($parts) {
                $expr = 'CONCAT_WS(0x1f, ' . implode(', ', $parts) . ')';
                $out['crc'] = (string) $DB->get_field_sql("SELECT COALESCE(SUM(CRC32({$expr})), 0) {$join}", $params);
            }
        }
        if ($dbman->table_exists('local_sentientia_courses_enroloff')) {
            $out['switched_off'] = $DB->count_records('local_sentientia_courses_enroloff');
        }
        return $out;
    }

    /**
     * Is a difference in the count of user_enrolments exactly the rows the enrolments import wrote?
     *
     * @param int $baseline The count in the source baseline.
     * @param int $current The count now.
     * @param int $added Rows the map says the import added (imported_enrolments()['rows']).
     * @return bool
     */
    public static function enrolment_count_explained(int $baseline, int $current, int $added): bool {
        return $added > 0 && $current - $baseline === $added;
    }

    /**
     * Is a difference in the user_enrolments checksum exactly the rows the enrolments import wrote?
     *
     * The checksum is SUM(CRC32(row)), so adding rows adds their CRCs: the baseline's sum plus the sum of the imported rows
     * must be the sum now, and the row count must have grown by exactly those rows. A changed legacy row or a deleted one
     * breaks the equation and stays drift. The CRCs of the imported rows are read from the rows as they are now (the map
     * keeps none), so an imported row an administrator edited since the import is still explained: what this proves is
     * that every row the baseline had is exactly as it was, which is the point of the gate.
     *
     * @param array{rows: int, crc: ?string} $baseline The table's entry in the source baseline.
     * @param array{rows: int, crc: ?string} $current The table's entry now.
     * @param array{rows: int, crc: ?string} $added As returned by imported_enrolments().
     * @return bool
     */
    public static function enrolment_checksum_explained(array $baseline, array $current, array $added): bool {
        if ((int) $added['rows'] <= 0 || $added['crc'] === null || $baseline['crc'] === null || $current['crc'] === null) {
            return false;
        }
        return (int) $current['rows'] - (int) $baseline['rows'] === (int) $added['rows']
            && (string) ((int) $baseline['crc'] + (int) $added['crc']) === (string) $current['crc'];
    }

    /**
     * Source rows against primary map rows, and source rows without a map row.
     *
     * @param string $feature
     * @param importer $importer
     * @param legacy_reader $reader
     * @return string[]
     */
    private static function accounting_problems(string $feature, importer $importer, legacy_reader $reader): array {
        global $DB;
        $problems = [];
        $steps = [];
        foreach ($importer->steps() as $step) {
            if ($step instanceof step && !$step->is_derived() && $reader->exists($step->physical_table())) {
                $steps[$step->sourcetable()][] = $step;
            }
        }
        foreach ($steps as $name => $group) {
            $source = 0;
            foreach ($group as $step) {
                $source += $reader->count($step->physical_table(), $step->source_filter());
            }
            $mapped = $DB->count_records(legacymap::TABLE, ['sourcetable' => $name, 'subkey' => '']);
            if ($source !== $mapped) {
                $problems[] = "accounting:{$feature}:{$name}: source={$source} mapped={$mapped}";
            }
            // Every source row has exactly one primary map row (ADR-032 id strategy 1). The count above applies
            // the step filters, so a filter that leaves rows out would pass it.
            $all = $reader->count($group[0]->physical_table());
            if ($all !== $mapped && $all !== $source) {
                $problems[] = "unmapped_rows:{$feature}:{$name}: table={$all} mapped={$mapped} (a source_filter leaves rows out)";
            }
            foreach ($group as $step) {
                [$and, $params] = self::filter_clause($step);
                $params['st'] = $name;
                $missing = $DB->get_records_sql(
                    'SELECT t.id FROM {' . $step->physical_table() . '} t
                      WHERE NOT EXISTS (SELECT 1 FROM {' . legacymap::TABLE . "} m
                                         WHERE m.sourcetable = :st AND m.subkey = '' AND m.sourceid = t.id) {$and}",
                    $params, 0, self::SAMPLE);
                if ($missing) {
                    $problems[] = "unmapped_source_rows:{$feature}:{$name}: ids=" . implode(',', array_keys($missing));
                }
            }
        }
        return $problems;
    }

    /**
     * Imported or adopted map rows whose target row is gone.
     *
     * @param string $feature
     * @param importer $importer
     * @return string[]
     */
    private static function missing_target_problems(string $feature, importer $importer): array {
        global $DB;
        $problems = [];
        foreach ($importer->target_tables() as $table) {
            if (!$DB->get_manager()->table_exists($table)) {
                continue;
            }
            $gone = $DB->get_records_sql(
                'SELECT m.id FROM {' . legacymap::TABLE . '} m
                  WHERE m.feature = :f AND m.targettable = :t AND m.outcome IN (\'imported\', \'adopted\')
                    AND NOT EXISTS (SELECT 1 FROM {' . $table . '} x WHERE x.id = m.targetid)',
                ['f' => $feature, 't' => $table], 0, self::SAMPLE);
            if ($gone) {
                $problems[] = "missing_target_rows:{$feature}:{$table}: map ids=" . implode(',', array_keys($gone));
            }
        }
        return $problems;
    }

    /**
     * Tenant column values that are not a valid path with a registered root.
     *
     * @param string $feature
     * @param importer $importer
     * @return string[]
     */
    private static function tenant_problems(string $feature, importer $importer): array {
        global $DB;
        $problems = [];
        foreach ($importer->tenant_columns() as $table => $column) {
            fingerprint::assert_identifier($column);
            $rows = $DB->get_records_sql(fingerprint::value_histogram_sql($table, $column, true));
            foreach ($rows as $row) {
                if (!runner::is_valid_tenant_value((string) $row->v)) {
                    $problems[] = "invalid_tenant_value:{$feature}:{$table}.{$column}="
                        . \core_text::substr((string) $row->v, 0, 60) . " rows={$row->n}";
                }
            }
        }
        return $problems;
    }

    /**
     * A legacy table whose fingerprint differs from the one stored when its step ran.
     *
     * @param string $feature
     * @param importer $importer
     * @param legacy_reader $reader
     * @param array<string, int> $legacy Legacy tables, as keys. A core source (logstore_standard_log) is live
     *        data that changes once the site opens, so only legacy tables are compared.
     * @return string[]
     */
    private static function mutation_problems(string $feature, importer $importer, legacy_reader $reader,
                                              array $legacy): array {
        global $DB;
        $problems = [];
        foreach ($importer->steps() as $step) {
            if (!($step instanceof step) || !$reader->exists($step->physical_table())
                    || !isset($legacy[$step->physical_table()])) {
                continue;
            }
            $stored = $DB->get_records_select('local_sentientia_legacystep',
                "feature = :f AND stepkey = :k AND status = 'done'", ['f' => $feature, 'k' => $step->key()],
                'runid DESC', 'id, srccount, srcmaxid, srccrc', 0, 1);
            if (!$stored) {
                continue;
            }
            $row = reset($stored);
            $now = fingerprint::table($step->physical_table(), $step->source_filter());
            $changed = (int) $row->srccount !== $now['count'] || (int) $row->srcmaxid !== $now['maxid']
                || ($row->srccrc !== null && $now['crc'] !== null && (string) $row->srccrc !== (string) $now['crc']);
            if ($changed) {
                $problems[] = "source_mutated_after_import:{$feature}:{$step->key()}";
            }
        }
        return $problems;
    }

    /**
     * The importer's own verify().
     *
     * @param string $feature
     * @param importer $importer
     * @param decisions $decisions The run's decisions; a verify() that reads one needs them.
     * @return string[]
     */
    private static function verify_problems(string $feature, importer $importer, decisions $decisions): array {
        try {
            $ctx = context::build($importer, false, 0, $decisions);
            return array_map(fn($line): string => "verify:{$feature}:" . $line, $importer->verify($ctx));
        } catch (\Throwable $e) {
            return ["verify_error:{$feature}:" . runner::safe_message($e)];
        }
    }

    /**
     * A step filter as an AND clause. Every framework query aliases the source table as t,
     * so a filter written t.col = ... works in the reader, the fingerprint and here.
     *
     * @param step $step
     * @return array{0: string, 1: array}
     */
    private static function filter_clause(step $step): array {
        [$sql, $params] = $step->source_filter();
        $sql = trim((string) $sql);
        return $sql === '' ? ['', []] : ['AND (' . $sql . ')', (array) $params];
    }
}
