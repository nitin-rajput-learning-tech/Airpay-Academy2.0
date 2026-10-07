<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * SANDBOX-KIT (rollout-gate Phase 2) and Stage B -- data-intact parity check for the ninja-sandbox migration rehearsal
 * and the eventual live replacement.
 *
 * Captures the numbers that define "existing Academy users' data intact": per-tenant active users, courses, enrolments,
 * role assignments, completions (total and completed), quiz attempts, SCORM attempts and tracks, badges, grades (count
 * and sum), certificate issues, a value checksum per critical table, and a full-content fingerprint of every BizLMS
 * legacy table. Every number comes from cli/source_baseline.php, the single file that also takes the baseline on the
 * SOURCE (a restored 4.1.2 copy with no Sentientia plugin): both sides run the same code, and every metric is version
 * aware (SCORM attempts, for one, are read from scorm_scoes_track before Moodle 4.3 and from scorm_attempt after).
 *
 * Usage:
 *   On the SOURCE, before any migration step, no Sentientia plugin needed (copy cli/source_baseline.php there):
 *     php source_baseline.php --config=/path/config.php --baseline=/safe/place/baseline.json
 *   On a Sentientia target, the same file can also be written by:
 *     php migration_parity_check.php --baseline=/safe/place/baseline.json
 *
 *   After the upgrade hops and the repairs, before the import (nothing but the upgrade may have changed):
 *     php migration_parity_check.php --compare=/safe/place/baseline.json
 *
 *   After import_bizlms.php --apply (the import added enrolments and role assignments, filled some course columns and
 *   moved tag instances, all on purpose):
 *     php migration_parity_check.php --compare=/safe/place/baseline.json --after-import --decisions=FILE
 *         [--expect-decisions-hash=SHA256] [--run=ID] [--report=FILE]
 *
 *   --after-import   Compare as above, except that what the import itself wrote must be EXPLAINED by its own records
 *                    (local_sentientia_legacymap and the importers' ledgers), not matched to the baseline: enrolments,
 *                    enrol instances and role assignments may have grown by exactly the rows the map says were imported;
 *                    a course may differ from the baseline in exactly the open_* columns its ledger row names; a tag
 *                    instance may have moved exactly as its ledger says. Nothing else about those tables, and nothing
 *                    at all about any other table or about the BizLMS legacy tables, may differ. It also runs the
 *                    bizlms_import invariant (accounting, missing targets, tenant values, mutated sources, every
 *                    importer's verify) and lists the needs-owner reasons the decisions do not accept.
 *   --decisions      The decisions file the import ran with (required: verify() reads it).
 *   --expect-decisions-hash   Refuse unless the file hashes to this (cutover must use the rehearsed decisions).
 *   --run            Explain the deltas with this apply run's records only (default: every apply run).
 *   --report         The JSON report import_bizlms.php --report wrote: checked against the database, this install,
 *                    the decisions and --run.
 *
 * Exit 0 = counts, aggregates, value checksums and the legacy tables match the baseline (and, after the import, every
 *          change the import made is in its own records).
 * Exit 1 = drift, listed per metric and per table, OR a hard invariant failed on this deployment whatever the baseline
 *          says (message_provider_defaults, tenant_cross_foot, bizlms_import).
 * Exit 2 = nothing drifted but something could not be checked, so the data is NOT proven intact (old baseline, a
 *          non-MySQL engine, an invariant that could not run, a needs-owner reason the decisions do not accept, an
 *          unclaimed legacy table that holds rows).
 * Exit 3 = refused: the comparison cannot be made (--after-import without an import, a decisions file that does not
 *          hash to the expected value, a --run that is not a complete apply run of this install).
 * 0, 1, 2 and 3 mean the same as in import_bizlms.php.
 * No flags = print current numbers.
 *
 * @package local_sentientia_platform
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\legacy_tables;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\parity_gate;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\parity\baseline as parity_baseline;
use local_sentientia_platform\parity\core as parity_core;
use local_sentientia_platform\parity\metrics as parity_metrics;
use local_sentientia_platform\parity\moodle_db;

parity_gate::load_library();

[$options, $unrecognised] = cli_get_params([
    'baseline' => '', 'compare' => '', 'after-import' => false, 'decisions' => '', 'expect-decisions-hash' => '',
    'run' => '', 'report' => '', 'help' => false,
], ['h' => 'help']);
if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)));
}
if ($options['help']) {
    cli_writeln('Data-intact parity check. --baseline=FILE to save, --compare=FILE to verify.');
    cli_writeln('After the import: --compare=FILE --after-import --decisions=FILE [--expect-decisions-hash=SHA256] '
        . '[--run=ID] [--report=FILE].');
    cli_writeln('Exit 0 parity, 1 drift, 2 not proven, 3 refused. See the header of this file.');
    exit(0);
}
if ($options['baseline'] !== '' && $options['compare'] !== '') {
    cli_error('Use --baseline or --compare, not both.', 3);
}
$afterimport = (bool) $options['after-import'];
if ($afterimport && $options['compare'] === '') {
    cli_error('--after-import needs --compare=FILE (the baseline taken on the source).', 3);
}
foreach (['decisions', 'expect-decisions-hash', 'run', 'report'] as $name) {
    if (!$afterimport && $options[$name] !== '') {
        cli_error("--{$name} belongs to --after-import.", 3);
    }
}
if ($afterimport && $options['decisions'] === '') {
    cli_error('--after-import needs --decisions=FILE: every importer\'s verify() reads the decisions it ran with.', 3);
}

global $DB;
$parity_db = new moodle_db($DB);

/**
 * Invariants that must hold on the deployment being checked, whatever the baseline says. They are not compared with the
 * baseline (the source is BizLMS, where they do not apply); any problem is a hard failure.
 *
 * message_provider_defaults: \local_sentientia_platform\message_pref_repair::check()
 * - providers missing a <processor>_provider_<component>_<name>_locked default
 * (message_send() throws for them), legacy site-wide disable switches not
 * carried over, and users' choices stranded under pre-rename names. Found
 * 2026-09-29: 28 of 30 Sentientia providers on a relabelled copy; a count-only
 * parity check could not see it. Repair: repair_task_registrations.php --apply.
 *
 * tenant_cross_foot: the tenant buckets add up to the active users (a user who dropped out of every bucket, or
 * counted twice, is what a truncated open_path looks like).
 *
 * bizlms_import (only with --after-import): parity::invariant_problems().
 *
 * The check never stops the run. On a BizLMS source box that has the plugin
 * directory but not the tables, or on any DB error, check() can throw; that must
 * not stop --baseline from writing its file. The error is caught and the
 * invariant is reported as SKIPPED with its message (--compare then exits 2,
 * "not proven", never a pass).
 *
 * @param array<string, int> $counts The current counts (for the cross-foot).
 * @param decisions|null $decisions Set with --after-import: the run's decisions.
 * @return array<string,string[]|string|null> a list of problems (empty = OK);
 *         null = check not available here; a string = the check could not run,
 *         and the string says why
 */
function sentientia_parity_invariants(array $counts, ?decisions $decisions = null): array {
    $out = [];
    if (!class_exists('\local_sentientia_platform\message_pref_repair')) {
        $out['message_provider_defaults'] = null;
    } else {
        try {
            $out['message_provider_defaults'] = \local_sentientia_platform\message_pref_repair::check();
        } catch (\Throwable $e) {
            $out['message_provider_defaults'] = 'check could not run: ' . $e->getMessage();
        }
    }
    $foot = parity_metrics::cross_foot($counts);
    $out['tenant_cross_foot'] = $foot === null ? [] : [$foot];
    if ($decisions !== null) {
        try {
            $out['bizlms_import'] = parity::invariant_problems($decisions);
        } catch (\Throwable $e) {
            $out['bizlms_import'] = 'check could not run: ' . $e->getMessage();
        }
    }
    return $out;
}

/** Print the invariants; returns [failed, skipped]. */
function sentientia_parity_print_invariants(array $invariants): array {
    $failed = 0;
    $skipped = 0;
    cli_writeln('');
    cli_writeln('Invariants (must hold whatever the baseline says):');
    foreach ($invariants as $k => $problems) {
        if ($problems === null) {
            cli_writeln(sprintf('  SKIPPED %-26s (check not available on this deployment)', $k));
            $skipped++;
        } else if (is_string($problems)) {
            cli_writeln(sprintf('  SKIPPED %-26s (%s)', $k, $problems));
            $skipped++;
        } else if ($problems) {
            cli_writeln(sprintf('  FAIL    %-26s %d problem(s) - must be 0', $k, count($problems)));
            foreach ($problems as $line) {
                cli_writeln('          ' . $line);
            }
            $failed++;
        } else {
            cli_writeln(sprintf('  OK      %-26s 0', $k));
        }
    }
    return [$failed, $skipped];
}

$print = static function (string $line): void {
    cli_writeln($line);
};
$meta = ['wwwroot' => $CFG->wwwroot, 'release' => $CFG->release, 'version' => (string) $CFG->version,
    'tool' => 'migration_parity_check.php'];
$progress = static function (string $phase, float $seconds): void {
    cli_writeln(sprintf('  [%-12s %7.2fs]', $phase, $seconds));
};

if ($options['baseline'] !== '') {
    $doc = parity_baseline::build($parity_db, $meta, $progress);
    $json = json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($options['baseline'], $json . "\n") === false) {
        cli_error('Cannot write ' . $options['baseline']);
    }
    cli_writeln('Baseline saved: ' . $options['baseline']);
    \local_sentientia_platform\parity\print_summary($doc, $print);
    // Informational on the source side; enforced by --compare on the target.
    sentientia_parity_print_invariants(sentientia_parity_invariants($doc['counts']));
    exit(0);
}

if ($options['compare'] !== '') {
    $base = json_decode((string) @file_get_contents($options['compare']), true);
    if (!$base || empty($base['counts'])) {
        cli_error('Cannot read baseline file: ' . $options['compare']);
    }
    cli_writeln('Baseline: ' . ($base['wwwroot'] ?? '?') . ' @ '
        . userdate($base['captured_at'] ?? 0) . ' (' . ($base['release'] ?? '?') . ', format ' . ($base['format'] ?? 1) . ')');
    cli_writeln('Current:  ' . $CFG->wwwroot . ' (' . $CFG->release . ')');

    // Everything --after-import needs, read before any number is printed: a refusal prints nothing half-done.
    $decisions = null;
    $runid = null;
    $expected = [];
    $explained = [];
    $importers = [];
    $reportproblems = [];
    if ($afterimport) {
        try {
            $decisions = decisions::load((string) $options['decisions']);
            $expect = trim((string) $options['expect-decisions-hash']);
            if ($expect !== '' && !hash_equals($decisions->hash(), $expect)) {
                throw new \local_sentientia_platform\bizlms\guard_refused('decisions_hash_differs_from_the_expected_one');
            }
            $runid = $options['run'] !== '' ? (int) $options['run'] : null;
            if ($runid !== null && $runid <= 0) {
                throw new \local_sentientia_platform\bizlms\guard_refused('run_is_not_a_run_id');
            }
            $refusals = parity_gate::refusals($runid);
            if ($refusals) {
                throw new \local_sentientia_platform\bizlms\guard_refused(implode(', ', $refusals));
            }
            $report = null;
            if ($options['report'] !== '') {
                $report = json_decode((string) @file_get_contents((string) $options['report']), true);
                if (!is_array($report)) {
                    throw new \local_sentientia_platform\bizlms\guard_refused('report_unreadable');
                }
                $reportproblems = parity_gate::report_problems($report, $runid, $decisions);
            }
        } catch (bizlms_exception $e) {
            // Whatever stopped it (a decisions file, a hash, a run), nothing was compared: the same exit as a guard.
            cli_writeln('REFUSED: ' . $e->getMessage());
            exit(3);
        }
        $expected = parity_gate::expected($runid);
        $explained = ['counts' => parity_gate::explained_counts($expected),
            'skip_checksums' => parity_gate::INSERT_TABLES];
        try {
            $importers = registry::load();
        } catch (\local_sentientia_platform\bizlms\registry_error $e) {
            // parity::invariant_problems() reports it as a hard problem; there is nothing to list reasons from.
            $importers = [];
        }
    }

    $now = parity_baseline::build($parity_db, $meta, $progress, ['metrics']);
    $result = parity_baseline::compare_metrics($base, $now, $print, $explained);
    $drift = $result['drift'];
    $skipped = $result['skipped'];
    $unproven = [];

    $invariants = sentientia_parity_invariants($now['counts'], $decisions);
    [$hardfail, $invskipped] = sentientia_parity_print_invariants($invariants);
    $skipped += $invskipped;

    // The BizLMS legacy tables, the archive: the upgrades and the import never write them, so they must be identical.
    cli_writeln('');
    cli_writeln('BizLMS legacy tables (the archive: the upgrades and the import never write them):');
    $legacyhard = [];
    if (isset($base['legacy'])) {
        $cmp = parity_gate::legacy_comparison($parity_db, (array) $base['legacy'], (array) ($base['legacy_other'] ?? []));
        $problems = parity::comparison_problems($cmp['comparison']);
        $legacyhard = $problems['hard'];
        $legacyunproven = array_merge($problems['unproven'], $cmp['other']);
        if (!$legacyhard && !$legacyunproven) {
            cli_writeln(sprintf('  MATCH   %d table(s): count, max id, columns and a CRC over every column of every row',
                count((array) $base['legacy'])));
        }
        foreach ($legacyhard as $line) {
            cli_writeln('  DRIFT   ' . $line);
        }
        foreach ($legacyunproven as $line) {
            cli_writeln('  UNPROVEN ' . $line);
        }
        $unproven = array_merge($unproven, $legacyunproven);
    } else if (legacy_tables::detect()) {
        cli_writeln('  SKIPPED - the baseline holds no legacy-table fingerprints (take it again with this version of '
            . 'the tool), and this database has legacy tables.');
        $skipped++;
    } else {
        cli_writeln('  none: this database has no legacy tables and the baseline names none.');
    }

    // After the import: every change to a core table must be in the import's own records.
    $corehard = [];
    if ($afterimport) {
        cli_writeln('');
        cli_writeln('Import (' . ($runid === null ? 'every apply run' : "run {$runid}") . '): changes to the core tables it may write:');
        if (empty($base['core'])) {
            cli_writeln('  SKIPPED - the baseline holds no core section (take it again with this version of the tool).');
            $unproven[] = 'baseline_has_no_core_section';
        } else {
            $verdict = parity_core::evaluate((array) $base['core'], parity_core::evidence($parity_db, (array) $base['core']),
                $expected);
            foreach ($verdict['lines'] as $line) {
                cli_writeln($line);
            }
            $corehard = $verdict['hard'];
            foreach ($corehard as $line) {
                cli_writeln('  FAIL    ' . $line);
            }
            foreach ($verdict['unproven'] as $line) {
                cli_writeln('  UNPROVEN ' . $line);
                $unproven[] = $line;
            }
            if (!$verdict['hard'] && !$verdict['unproven']) {
                cli_writeln('  OK      every row and column that differs from the baseline is named by the import\'s own records');
            }
        }
        foreach (parity_gate::unexplained_core_writes() as $table) {
            $corehard[] = 'core_write_this_check_cannot_explain:' . $table;
            cli_writeln('  FAIL    core_write_this_check_cannot_explain:' . $table);
        }
        if ($options['report'] !== '') {
            if ($reportproblems) {
                foreach ($reportproblems as $line) {
                    cli_writeln('  FAIL    ' . $line);
                }
            } else {
                cli_writeln('  OK      the report is of this install, of the run, made with these decisions, and its step counters '
                    . 'are the database\'s');
            }
        }
        if ($importers) {
            $reasons = parity_gate::unproven($decisions, $importers);
            foreach ($reasons as $line) {
                cli_writeln('  UNPROVEN ' . $line);
            }
            $unproven = array_merge($unproven, $reasons);
        }
    }

    cli_writeln('');
    $hard = $drift + $hardfail + count($legacyhard) + count($corehard) + count($reportproblems);
    if ($hard > 0) {
        if ($drift > 0) {
            cli_writeln("RESULT: $drift metric(s) DRIFTED - investigate before proceeding.");
        }
        if ($hardfail > 0) {
            $failed = [];
            foreach ($invariants as $name => $problems) {
                if (is_array($problems) && $problems) {
                    $failed[] = $name;
                }
            }
            cli_writeln("RESULT: $hardfail invariant(s) FAILED (" . implode(', ', $failed) . ').');
            if (!empty($invariants['message_provider_defaults']) && is_array($invariants['message_provider_defaults'])) {
                cli_writeln('        message_provider_defaults: run local/sentientia_platform/cli/repair_task_registrations.php '
                    . '--apply, then re-check.');
            }
        }
        if ($legacyhard) {
            cli_writeln('RESULT: ' . count($legacyhard) . ' BizLMS legacy table(s) CHANGED or missing - the archive is not intact.');
        }
        if ($corehard) {
            cli_writeln('RESULT: ' . count($corehard) . ' change(s) to a core table are NOT explained by the import.');
        }
        if ($reportproblems) {
            cli_writeln('RESULT: ' . count($reportproblems) . ' problem(s) with the import report.');
        }
        exit(1);
    }
    $open = $skipped + count($unproven);
    if ($open > 0) {
        // Deliberately NOT "100% parity". Saying so here would be the same
        // defect as the rest of this file's history: a success message the
        // evidence does not support.
        cli_writeln("RESULT: counts match, but $open table(s)/invariant(s)/item(s) could not be "
            . 'checked. Data is NOT proven intact - re-run with a '
            . 'checksum-capable baseline on MySQL or MariaDB, on the Sentientia target, and settle the UNPROVEN lines.');
        exit(2);
    }
    if ($afterimport) {
        cli_writeln('RESULT: 100% PARITY - every number matches the source baseline, the BizLMS legacy tables are untouched, '
            . 'and every change to a core table is in the import\'s own records.');
    } else {
        cli_writeln('RESULT: 100% PARITY - counts AND value checksums match'
            . (isset($base['legacy']) ? ', and the BizLMS legacy tables are untouched.' : '.'));
    }
    exit(0);
}

// Print mode: the current numbers, no file.
$doc = parity_baseline::build($parity_db, $meta, $progress, ['metrics', 'legacy']);
\local_sentientia_platform\parity\print_summary($doc, $print);
// Print mode stays exit 0; --compare is the gate that fails on an invariant.
sentientia_parity_print_invariants(sentientia_parity_invariants($doc['counts']));
exit(0);
