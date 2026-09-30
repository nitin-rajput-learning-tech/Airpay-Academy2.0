<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Import BizLMS feature data into Sentientia history (ADR-032).
 *
 * The 22 purchased BizLMS plugins' tables stay in the database as the archive and
 * are never written. Each feature's importer, declared in its plugin's
 * db/bizlms_import.php, turns that history into normal Sentientia rows through
 * one shared framework: an idempotence map, batch or feature transactions, a
 * writer that refuses silent column loss, a side-effect tripwire and a report.
 *
 *   php local/sentientia_platform/cli/import_bizlms.php --status
 *   php local/sentientia_platform/cli/import_bizlms.php --list
 *   php local/sentientia_platform/cli/import_bizlms.php --preflight --all --decisions=FILE
 *   php local/sentientia_platform/cli/import_bizlms.php --all                  (dry run, writes nothing)
 *   php local/sentientia_platform/cli/import_bizlms.php --all --apply --confirm=<fingerprint> --decisions=FILE
 *
 * Options
 *   --status                        fingerprint, guard state, per-feature state, heartbeats
 *   --list                          features, owners, dependencies, source presence, unclaimed tables
 *   --preflight                     read-only; enum histograms and blockers
 *   --feature=a[,b] | --all         dependencies are added and sorted for --apply and for --all
 *   (default)                       dry run: writes nothing, not even bookkeeping
 *   --apply                         needs every guard of ADR-032 "Gating"
 *   --resume                        continue the NEWEST apply run from its watermarks, if it did not complete
 *   --retry-skipped=reason[,..]     re-process rows skipped with a retryable reason (single-row steps only:
 *                                   a grouped or derived step that has such rows is refused, not skipped)
 *   --verify                        run verify and the framework checks only
 *   --decisions=FILE                owner choices; its sha256 is stored on the run
 *   --expect-decisions-hash=SHA256  cutover must use the rehearsed decisions
 *   --report=FILE                   JSON, plus FILE.csv of the rows that were not imported
 *   --batch=500 --atomic-threshold=50000 --max-group-scan=500000 --crc-max-rows=2000000
 *                                   --crc-max-rows: a source fingerprint skips its CRC above this many rows
 *                                   (the CRC read took 2.5 minutes on a 2.6M-row table); 0 never skips
 *   --confirm=<fingerprint>         required with --apply and --purge-feature (printed by --status)
 *   --allow-online                  skip the maintenance requirement (rehearsal only)
 *   --acknowledge-tripwire=<run>    rehearsal only: let a feature run again after its side-effect tripwire tripped
 *                                   in run <run> (--status shows tripped=<run>). Look at the report and the tables
 *                                   first. Refused when bizlms_production = 1: restore the snapshot instead.
 *                                   --purge-feature also clears a trip.
 *   --purge-feature=<key> --i-understand-this-deletes
 *                                   rehearsal only; deletes only rows whose map outcome is imported
 *
 * Exit codes: 0 done (or nothing applicable); 1 blocker, failure, collision or source drift;
 * 2 done but unproven (needs-owner reasons not accepted, or unclaimed legacy tables holding rows);
 * 3 a guard refused. 0, 1 and 2 match migration_parity_check.php.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_sentientia_platform\bizlms\bizlms_exception;
use local_sentientia_platform\bizlms\decisions;
use local_sentientia_platform\bizlms\fingerprint;
use local_sentientia_platform\bizlms\guard;
use local_sentientia_platform\bizlms\guard_permit;
use local_sentientia_platform\bizlms\guard_refused;
use local_sentientia_platform\bizlms\parity;
use local_sentientia_platform\bizlms\registry;
use local_sentientia_platform\bizlms\registry_error;
use local_sentientia_platform\bizlms\report;
use local_sentientia_platform\bizlms\runner;
use local_sentientia_platform\bizlms\unclaimed;

[$options, $unrecognised] = cli_get_params([
    'status' => false, 'list' => false, 'preflight' => false,
    'feature' => '', 'all' => false,
    'apply' => false, 'resume' => false, 'retry-skipped' => '',
    'verify' => false,
    'decisions' => '', 'expect-decisions-hash' => '', 'report' => '',
    'batch' => '500', 'atomic-threshold' => '50000', 'max-group-scan' => '500000', 'crc-max-rows' => '2000000',
    'confirm' => '', 'allow-online' => false, 'acknowledge-tripwire' => '0',
    'purge-feature' => '', 'i-understand-this-deletes' => false,
    'help' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unrecognised options: ' . implode(', ', array_keys($unrecognised)), 1);
}
if ($options['help']) {
    $usage = file_get_contents(__FILE__);
    preg_match('/\/\*\*(.*?)\*\//s', (string) $usage, $m);
    cli_writeln(trim(preg_replace('/^\s*\* ?/m', '', $m[1] ?? 'See the header of this file.')));
    exit(0);
}

/**
 * Print a run's outcome and return its exit code.
 *
 * @param array $result
 * @param report $report
 * @return int
 */
function import_bizlms_print(array $result, report $report): int {
    $data = $report->to_array();
    foreach ($data['features'] as $feature => $section) {
        cli_writeln(sprintf('%-18s %s', $feature, $section['status'] ?? '-'));
        if (!empty($section['note'])) {
            cli_writeln('  NOTE: ' . $section['note']);
        }
        foreach ($section['steps'] ?? [] as $key => $step) {
            $c = $step['counters'] ?? [];
            cli_writeln(sprintf('  %-34s %-9s src=%-8s imp=%-7s adopt=%-5s merge=%-5s fold=%-5s arch=%-5s skip=%-5s %s rows/s',
                $key, $step['status'] ?? '-', $step['source_count'] ?? '-', $c['imported'] ?? 0, $c['adopted'] ?? 0,
                $c['merged'] ?? 0, $c['folded'] ?? 0, $c['archived'] ?? 0, $c['skipped'] ?? 0,
                $step['rows_per_second'] ?? '-'));
        }
        foreach ($section['verify_failures'] ?? [] as $line) {
            cli_writeln('  VERIFY FAILED: ' . $line);
        }
    }
    foreach ($result['blockers'] as $line) {
        cli_writeln('BLOCKER: ' . $line);
    }
    foreach ($result['unproven'] as $line) {
        cli_writeln('UNPROVEN: ' . $line);
    }
    if (isset($result['error']) && $result['error'] instanceof Throwable) {
        cli_writeln('ERROR: ' . $result['error']->getMessage());
    }
    cli_writeln('RESULT: ' . $result['status'] . ' (exit ' . $result['exit'] . ')');
    return (int) $result['exit'];
}

try {
    // Facts that need no decisions file.
    if ($options['status'] || $options['list']) {
        $state = guard::state();
        if ($options['status']) {
            cli_writeln('Install fingerprint (pass as --confirm): ' . $state['fingerprint']);
            foreach (['armed_seconds_left', 'production', 'production_open', 'maintenance', 'noemailever',
                      'standard_log', 'cron_enabled', 'running_tasks'] as $fact) {
                cli_writeln(sprintf('  %-20s %s', $fact, is_bool($state[$fact]) ? var_export($state[$fact], true) : $state[$fact]));
            }
        }
        try {
            $importers = registry::load();
        } catch (registry_error $e) {
            cli_writeln('REGISTRY INVALID:');
            foreach ($e->problems as $problem) {
                cli_writeln('  ' . $problem);
            }
            exit(1);
        }
        cli_writeln(count($importers) . ' importer(s) registered');
        foreach (runner::feature_states($importers) as $feature => $s) {
            cli_writeln(sprintf('  %-18s owner=%s deps=[%s] sources=%d/%d complete=%s tripped=%s started=%s running_steps=%d heartbeat_age=%s',
                $feature, $s['owner'], implode(',', $s['depends']), $s['sources_present'], $s['sources'],
                $s['complete_runid'] ?: 'no', $s['tripped_runid'] ?: 'no', $s['started'] ? 'yes' : 'no', $s['running_steps'],
                $s['last_heartbeat_age'] === null ? '-' : $s['last_heartbeat_age'] . 's'));
        }
        if ($options['list']) {
            $rows = unclaimed::with_rows($importers);
            cli_writeln('Unclaimed legacy tables holding rows: ' . ($rows ? implode(', ', $rows) : 'none'));
        }
        exit(0);
    }

    $expect = trim((string) $options['expect-decisions-hash']);
    $decisions = $options['decisions'] !== '' ? decisions::load((string) $options['decisions']) : decisions::none();
    if ($expect !== '' && !hash_equals($decisions->hash(), $expect)) {
        throw new guard_refused('decisions_hash_differs_from_the_expected_one');
    }

    // Rehearsal purge.
    if ($options['purge-feature'] !== '') {
        $purgeoptions = [
            'confirm' => $options['confirm'], 'understood' => $options['i-understand-this-deletes'],
        ];
        $refusals = guard::refusals_for_purge($purgeoptions);
        if ($refusals) {
            foreach ($refusals as $line) {
                cli_writeln('REFUSED: ' . $line);
            }
            exit(3);
        }
        $permit = guard::permit_purge($purgeoptions);
        $lock = guard::acquire_lock();
        $purged = (new runner(['apply' => true, 'decisions' => $decisions, 'permit' => $permit]))
            ->purge((string) $options['purge-feature']);
        $lock->release();
        foreach ($purged['deleted'] as $table => $n) {
            cli_writeln(sprintf('  deleted %-40s %d', $table, $n));
        }
        cli_writeln('RESULT: purged (exit 0)');
        exit(0);
    }

    $selected = array_values(array_filter(array_map('trim', explode(',', (string) $options['feature']))));
    if (!$selected && !$options['all']) {
        cli_error('Select features with --feature=a[,b] or --all (see --help).', 1);
    }

    $apply = $options['apply'] || $options['resume'] || $options['retry-skipped'] !== '';
    $report = new report([
        'mode' => $options['verify'] ? 'verify' : ($options['preflight'] ? 'preflight' : ($apply ? 'apply' : 'dry-run')),
        'fingerprint' => fingerprint::install(),
        'features' => $selected ?: 'all',
    ]);
    if ($options['report'] !== '') {
        $report->open_csv($options['report'] . '.csv', (bool) $options['resume']);
    }
    $runneroptions = [
        'apply' => $apply,
        'all' => (bool) $options['all'],
        'resume' => (bool) $options['resume'],
        'acknowledge_tripwire' => max(0, (int) $options['acknowledge-tripwire']),
        'retry_reasons' => array_values(array_filter(array_map('trim', explode(',', (string) $options['retry-skipped'])))),
        'batch' => max(1, (int) $options['batch']),
        'atomic_threshold' => (int) $options['atomic-threshold'],
        'max_group_scan' => (int) $options['max-group-scan'],
        'crc_max_rows' => (int) $options['crc-max-rows'],
        'decisions' => $decisions,
        'report' => $report,
    ];

    if ($options['preflight']) {
        $out = (new runner($runneroptions))->preflight($selected);
        $blocked = false;
        foreach ($out['order'] as $feature) {
            $pf = $out['preflights'][$feature];
            cli_writeln($feature . ($out['applicable'][$feature] ? '' : '  (not applicable: no claimed table exists)'));
            foreach ($pf->counts() as $name => $n) {
                cli_writeln(sprintf('  %-40s %d', $name, $n));
            }
            foreach ($pf->histograms() as $column => $values) {
                cli_writeln('  histogram ' . $column . ': ' . json_encode($values));
            }
            foreach ($pf->warnings() as $line) {
                cli_writeln('  WARNING: ' . $line);
            }
            foreach ($pf->blockers() as $line) {
                cli_writeln('  BLOCKER: ' . $line);
                $blocked = $blocked || $out['applicable'][$feature];
            }
        }
        cli_writeln('RESULT: ' . ($blocked ? 'blocked (exit 1)' : 'no blockers (exit 0)'));
        exit($blocked ? 1 : 0);
    }

    if ($options['verify']) {
        $out = (new runner($runneroptions))->verify($selected);
        foreach ($out['failures'] as $feature => $lines) {
            cli_writeln($feature . ': ' . ($lines ? 'FAILED' : 'ok'));
            foreach ($lines as $line) {
                cli_writeln('  ' . $line);
            }
        }
        // The parity invariant the cutover runs (ADR-032 "Parity hooks"): accounting, missing targets, tenant
        // values, mutated sources and every importer verify, across the whole registry.
        $problems = parity::invariant_problems($decisions);
        cli_writeln('parity invariant bizlms_import: ' . ($problems ? count($problems) . ' problem(s)' : '0'));
        foreach ($problems as $line) {
            cli_writeln('  ' . $line);
        }
        $failed = $out['exit'] !== 0 || $problems;
        cli_writeln('RESULT: ' . ($failed ? 'verify failed (exit 1)' : 'verified (exit 0)'));
        exit($failed ? 1 : 0);
    }

    $lock = null;
    if ($apply) {
        $applyoptions = [
            'confirm' => $options['confirm'], 'allow_online' => $options['allow-online'],
            'decisions_hash' => $decisions->hash(), 'expect_hash' => $expect,
            'acknowledge_tripwire' => $runneroptions['acknowledge_tripwire'],
        ];
        $refusals = guard::refusals_for_apply($applyoptions);
        if ($refusals) {
            foreach ($refusals as $line) {
                cli_writeln('REFUSED: ' . $line);
            }
            exit(3);
        }
        $runneroptions['permit'] = guard::permit_apply($applyoptions);
        $lock = guard::acquire_lock();
    }

    $result = (new runner($runneroptions))->run($selected);
    if ($lock) {
        $lock->release();
    }
    if ($options['report'] !== '') {
        $report->write_json($options['report']);
    }
    exit(import_bizlms_print($result, $report));
} catch (guard_refused $e) {
    cli_writeln('REFUSED: ' . $e->getMessage());
    exit(3);
} catch (bizlms_exception $e) {
    cli_writeln('FAILED: ' . $e->getMessage());
    exit($e->exitcode());
}
