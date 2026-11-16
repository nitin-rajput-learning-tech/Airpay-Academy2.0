<?php
// A4 supplement plan (ADR-035 proof, 2026-11-17). READ-ONLY: the DB session is switched to READ ONLY (with the same
// zero-row self-test as the rehearsal's dry run) and every plan runs inside a delegated transaction that is always
// rolled back. Prints and writes ids and counts only.
//
// What it does:
//   1. For every A4 subject (tool_dataprivacy_request type DELETE, creationmethod AUTO) it computes
//      \local_sentientia_privacy\local\erasure_completeness::plan($userid, true, true): per component the full,
//      iterated and missed context lists of the manager-equivalent discovery, the completion gap and the failures.
//      plan() is flag-independent: no feature flag is read or changed. Two groups: the status-10 subjects with a user
//      row (the 286 the A4 rehearsal processed) and the subjects with no user row (the 229 the stock task cannot run).
//   2. Reconciles the plans of the processed subjects against the residue as it is NOW: the bucketed must-be-empty
//      specs of a4_lib.php (same SQL and buckets as a4_verify.php, over the lists in the execute reports) are run
//      again, and every leftover row is classified against TODAY's plan of its subject:
//        stock       its context is in the component's iterated list today (a stock re-run would delete it);
//        supplement  its context is in the component's missed list today, or (core_course specs) in the completion gap;
//        uncovered   neither;
//        vanished    the context no longer exists (no discovery can find it): out of scope, reported separately.
//      In scope (the ADR-035 claim): bucket truncated (every spec) and bucket undiscovered for course_completions. The
//      other buckets are classified too and reported apart. The module-context course_modules_completion /
//      course_modules_viewed specs (added 2026-11-17) are bucketed per row against mod_<modname>.
//   3. Proves it wrote nothing: tripwire tables, MAX(id) of every mdl_ table and the state checksums before and after,
//      plus COUNT(*) and MAX(id) of logstore_standard_log, task_adhoc, notifications, messages and event.
//
// Usage (cwd C:/xampp/htdocs/moodle5/public), with the A4 php guard flags (README "How to run"):
//   php <A4 flags> ".../a4_supplement_plan.php" --report=PATH[,PATH...] --out=ABS_PATH [options]
// Options:
//   --report=PATHS   Comma-separated a4_rehearsal.php execute reports (the lists the residue is bucketed against).
//   --out=PATH       Absolute path of the JSON result (default reports/a4-supplement-plan-<timestamp>.json here).
//   --limit=N        Plan the first N subjects of each group only (the reconciliation then covers those).
//   --skip-missing   Do not plan the subjects with no user row.
//   -h, --help
// Exit codes: 0 ok (nothing written, nothing uncovered in scope), 1 usage, 2 guard/abort, 3 something was written or
// an event fired, 5 rows in scope are uncovered (or a plan failed).

define('CLI_SCRIPT', true);

require('C:/xampp/htdocs/moodle5/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . "/{$CFG->admin}/tool/dataprivacy/lib.php");
require_once(__DIR__ . '/a4_lib.php');

use local_sentientia_privacy\local\erasure_completeness;

$phpfacts = a4_require_hardened_php();

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'report' => '',
    'out' => '',
    'limit' => 0,
    'skip-missing' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(' ', $unrecognised), 1);
}
if ($options['help']) {
    foreach (file(__FILE__) as $i => $l) {
        if ($i > 0 && strpos($l, '//') === 0) {
            echo substr($l, 3);
        } else if ($i > 0) {
            break;
        }
    }
    exit(0);
}
foreach (['report', 'out', 'limit'] as $k) {
    if ($options[$k] === true) {
        cli_error("--$k needs a value (--$k=...)", 1);
    }
}
if ((string)$options['report'] === '') {
    cli_error('--report=PATH[,PATH...] is required (the a4_rehearsal.php execute reports)', 1);
}
if (!preg_match('/^\d+$/', (string)$options['limit'])) {
    cli_error('--limit must be a whole number', 1);
}
$limit = (int)$options['limit'];
if ($options['out'] !== '' && !a4_is_absolute((string)$options['out'])) {
    cli_error('--out must be an absolute path', 1);
}
$outpath = ($options['out'] !== '') ? (string)$options['out']
    : __DIR__ . '/reports/a4-supplement-plan-' . date('Ymd-His') . '.json';

// ---- Guards: local copy only, READ ONLY session, in-process kill switches, event recorder.
$envfacts = a4_guard_environment();
$rofacts = a4_set_readonly_session(true);
\core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);
\core\cron::setup_user();
$killfacts = a4_apply_kill_switches();
$eventfacts = a4_event_recorder::install();
$CFG->mtrace_wrapper = 'a4_mtrace_capture';

if (!class_exists(erasure_completeness::class)) {
    a4_abort('\\local_sentientia_privacy\\local\\erasure_completeness is not loadable on the served site (deploy '
        . 'local_sentientia_privacy 1.1.0 and purge caches first)');
}

// ---- Write-proof baseline (before anything else touches the data).
$probetables = ['logstore_standard_log', 'task_adhoc', 'notifications', 'messages', 'event'];
$snap0 = a4_snapshot(null, true);
$cnt0 = a4_counts($probetables);
$max0 = a4_maxids($probetables);

// ---- Reports and subjects.
$rep = a4_read_execute_reports(explode(',', (string)$options['report']));
$a4rows = $DB->get_records_sql(
    "SELECT r.id, r.userid, r.status, u.id AS uid, u.deleted AS udeleted
       FROM {tool_dataprivacy_request} r
  LEFT JOIN {user} u ON u.id = r.userid
      WHERE r.type = :type AND r.creationmethod = :cm
   ORDER BY r.id",
    ['type' => \tool_dataprivacy\api::DATAREQUEST_TYPE_DELETE,
     'cm' => \tool_dataprivacy\data_request::DATAREQUEST_CREATION_AUTO]);
$groups = ['processed' => [], 'missing' => []];
$other = [];
foreach ($a4rows as $r) {
    $uid = (int)$r->userid;
    if (empty($r->uid)) {
        $groups['missing'][$uid] = (int)$r->id;
    } else if ((int)$r->status === \tool_dataprivacy\api::DATAREQUEST_STATUS_DELETED) {
        $groups['processed'][$uid] = (int)$r->id;
    } else {
        $k = 'status' . (int)$r->status . (!empty($r->udeleted) ? '_deleted' : '_live');
        $other[$k] = ($other[$k] ?? 0) + 1;
    }
}
ksort($groups['processed']);
ksort($groups['missing']);
$reportonly = array_values(array_diff(array_keys($rep['processed']), array_keys($groups['processed'])));
$status10only = array_values(array_diff(array_keys($groups['processed']), array_keys($rep['processed'])));
if ($options['skip-missing']) {
    $groups['missing'] = [];
}
if ($limit) {
    foreach ($groups as $g => $list) {
        $groups[$g] = array_slice($list, 0, $limit, true);
    }
}

$result = [
    'generated' => gmdate('Y-m-d\TH:i:s\Z'), 'php' => $phpfacts, 'env' => $envfacts, 'readonly' => $rofacts,
    'events' => $eventfacts, 'reports' => $rep['files'], 'report_outcomes' => $rep['outcomes'],
    'report_processed_subjects' => count($rep['processed']),
    'a4_requests' => count($a4rows), 'a4_status10_with_user_row' => count($groups['processed']),
    'a4_no_user_row' => count($groups['missing']), 'a4_other' => $other,
    'report_subjects_not_status10_now' => $reportonly, 'status10_subjects_not_in_reports' => $status10only,
    'limit' => $limit, 'registry' => a4_registry_facts(),
];

// ---- 1. Plans, inside a transaction that is always rolled back (and a READ ONLY session).
$tx = $DB->start_delegated_transaction();
$GLOBALS['A4_MTRACE_BUF'] = '';
ob_start();
$t0s = microtime(true);
$stocktruncates = erasure_completeness::stock_approval_truncates();
$result['stock_approval_truncates'] = $stocktruncates;
$result['stock_approval_truncates_seconds'] = round(microtime(true) - $t0s, 3);
$selfcheckout = (string)ob_get_clean() . $GLOBALS['A4_MTRACE_BUF'];
$GLOBALS['A4_MTRACE_BUF'] = '';

$plans = [];        // uid => ['stock' => comp => iterated, 'supp' => comp => missed, 'gap' => ids] (processed group)
$persubject = [];
$groupstats = [];
$outputlines = ['count' => 0, 'first' => []];
$sendlines = [];
foreach ($groups as $g => $list) {
    $gs = ['subjects' => count($list), 'subjects_with_missed' => 0, 'subjects_with_gap' => 0,
        'subjects_with_supplementary' => 0, 'supplementary_contexts' => 0, 'gap_contexts' => 0,
        'missed_by_component' => [], 'missed_subjects_by_component' => [], 'components_with_data' => 0,
        'failures' => [], 'secs' => []];
    foreach ($list as $uid => $rid) {
        $GLOBALS['A4_MTRACE_BUF'] = '';
        ob_start();
        $t0 = microtime(true);
        $plan = erasure_completeness::plan((int)$uid, true, true);
        $secs = microtime(true) - $t0;
        $captured = (string)ob_get_clean() . $GLOBALS['A4_MTRACE_BUF'];
        $GLOBALS['A4_MTRACE_BUF'] = '';
        [, $otherlines] = a4_split_output($captured);
        $outputlines['count'] += count($otherlines);
        foreach ($otherlines as $ol) {
            $ol = a4_trunc($ol, 160);
            if (count($outputlines['first']) < 8 && !in_array($ol, $outputlines['first'], true)) {
                $outputlines['first'][] = $ol;
            }
        }
        foreach (a4_send_attempt_lines($captured) as $sl) {
            $sendlines[] = $sl;
        }
        $gs['secs'][] = $secs;
        $missed = $plan->missed_by_component();
        $supp = $plan->supplementary_contextids();
        $gs['components_with_data'] += count($plan->components);
        if ($missed) {
            $gs['subjects_with_missed']++;
        }
        if ($plan->completiongap) {
            $gs['subjects_with_gap']++;
        }
        if ($supp) {
            $gs['subjects_with_supplementary']++;
        }
        $gs['supplementary_contexts'] += count($supp);
        $gs['gap_contexts'] += count($plan->completiongap);
        foreach ($missed as $c => $ids) {
            $gs['missed_by_component'][$c] = ($gs['missed_by_component'][$c] ?? 0) + count($ids);
            $gs['missed_subjects_by_component'][$c] = ($gs['missed_subjects_by_component'][$c] ?? 0) + 1;
        }
        foreach ($plan->failures as $f) {
            $fk = $f['phase'] . ':' . $f['component'] . ':' . $f['exception'];
            $gs['failures'][$fk] = ($gs['failures'][$fk] ?? 0) + 1;
        }
        $persubject[] = ['group' => $g, 'userid' => (int)$uid, 'requestid' => (int)$rid, 'secs' => round($secs, 3),
            'components' => count($plan->components), 'missed' => array_map('count', $missed),
            'gap' => count($plan->completiongap), 'supplementary' => count($supp), 'failures' => count($plan->failures)];
        if ($g === 'processed') {
            $stock = [];
            $suppby = [];
            foreach ($plan->components as $c => $entry) {
                $stock[$c] = $entry['iterated'];
                if ($entry['missed']) {
                    $suppby[$c] = $entry['missed'];
                }
            }
            $plans[(int)$uid] = ['stock' => $stock, 'supp' => $suppby, 'gap' => $plan->completiongap,
                'failed' => array_values(array_unique(array_column($plan->failures, 'component')))];
        }
    }
    arsort($gs['missed_by_component']);
    ksort($gs['failures']);
    $gs['timing'] = a4_timing_stats($gs['secs']);
    unset($gs['secs']);
    $groupstats[$g] = $gs;
}
$planevents = a4_event_recorder::take();
$result['tx_read_only_in_plan'] = a4_readonly_state();
$DB->force_transaction_rollback();
unset($tx);
$result['plan_groups'] = $groupstats;
$result['plan_output_lines'] = $outputlines;
$result['plan_send_attempt_lines'] = count($sendlines);
$result['plan_events'] = count($planevents);
$alltimes = array_column($persubject, 'secs');
$result['plan_timing_all'] = a4_timing_stats($alltimes);

// ---- 2. Reconciliation against the residue now (processed subjects that were planned).
$lists = array_intersect_key($rep['lists'], $plans);
$scopebuckets = ['truncated'];
$recon = [];       // spec key => [component => [class => ['rows', 'pairs', 'ctx' => set]]] (in scope)
$otherrecon = [];  // bucket => [class => rows] (out of scope, for the record)
$bycomp = [];      // component => in-scope totals
$uncoveredsample = [];
$ctxwatch = [];    // rows in context 4138 (the A4 mod_quiz cmc/cmv rows), any spec
$onrow = function(string $key, string $t, string $c, int $uid, ?int $ctx, bool $ctxgone, string $bucket, int $n)
        use (&$recon, &$otherrecon, &$bycomp, &$uncoveredsample, &$ctxwatch, $plans, $scopebuckets): void {
    $p = $plans[$uid] ?? null;
    if ($p === null) {
        return;
    }
    $class = a4_cover_class($p['stock'], $p['supp'], $p['gap'], $c, $ctx, $ctxgone, a4_is_module_shared_spec($t, $c));
    if ($class === 'uncovered' && in_array($c, $p['failed'], true)) {
        $class = 'uncovered_plan_failed';
    }
    $inscope = in_array($bucket, $scopebuckets, true) || ($bucket === 'undiscovered' && $t === 'course_completions');
    if ($ctx === 4138 && in_array($t, ['course_modules_completion', 'course_modules_viewed'], true)) {
        $ctxwatch[] = ['table' => $t, 'component' => $c, 'subject' => $uid, 'bucket' => $bucket, 'class' => $class,
            'rows' => $n];
    }
    if (!$inscope) {
        $otherrecon[$bucket][$t . ' [' . $c . ']'][$class] = ($otherrecon[$bucket][$t . ' [' . $c . ']'][$class] ?? 0) + $n;
        return;
    }
    $r = &$recon[$key][$c][$bucket];
    $r[$class]['rows'] = ($r[$class]['rows'] ?? 0) + $n;
    $r[$class]['pairs'] = ($r[$class]['pairs'] ?? 0) + 1;
    $b = &$bycomp[$c . ($t === 'course_completions' ? ' (course_completions)' : '')];
    $b['rows'] = ($b['rows'] ?? 0) + $n;
    $b['pairs'] = ($b['pairs'] ?? 0) + 1;
    if ($ctx !== null) {
        $b['ctx'][$ctx] = true;
    }
    $b[$class] = ($b[$class] ?? 0) + $n;
    $b['tables'][$t] = true;
    if (in_array($class, ['uncovered', 'uncovered_plan_failed'], true) && count($uncoveredsample) < 100) {
        $uncoveredsample[] = ['spec' => $key, 'component' => $c, 'subject' => $uid, 'ctx' => $ctx, 'bucket' => $bucket,
            'rows' => $n, 'class' => $class];
    }
};
$t0r = microtime(true);
$buckets = a4_must_be_empty_buckets($lists, $rep['issueids'], $rep['issueowners'], $onrow);
$result['reconcile_seconds'] = round(microtime(true) - $t0r, 1);
$percomp = [];
foreach ($bycomp as $c => $b) {
    $percomp[$c] = ['tables' => array_keys($b['tables']), 'contexts' => count($b['ctx'] ?? []), 'pairs' => $b['pairs'],
        'rows' => $b['rows'], 'covered_stock' => $b['stock'] ?? 0, 'covered_supplement' => $b['supplement'] ?? 0,
        'uncovered' => ($b['uncovered'] ?? 0) + ($b['uncovered_plan_failed'] ?? 0),
        'uncovered_plan_failed' => $b['uncovered_plan_failed'] ?? 0, 'vanished' => $b['vanished'] ?? 0];
}
ksort($percomp);
$bucketsummary = [];
$specerrors = [];
foreach ($buckets as $k => $v) {
    if (isset($v['error'])) {
        $specerrors[$k] = $v['error'];
    }
    if (!empty($v['buckets'])) {
        $bucketsummary[$k] = ['rows' => $v['rows'], 'buckets' => array_filter($v['buckets']),
            'ctx_missing' => $v['ctx_missing'], 'secs' => $v['secs']];
    }
}
$result['reconcile_subjects'] = count($lists);
$result['reconcile_scope'] = 'bucket truncated (every spec) + bucket undiscovered for course_completions';
$result['buckets_now'] = $bucketsummary;
$result['bucket_spec_errors'] = $specerrors;
$result['reconcile_by_component'] = $percomp;
$result['reconcile_by_spec'] = $recon;
$result['reconcile_other_buckets'] = $otherrecon;
$result['reconcile_uncovered_sample'] = $uncoveredsample;
$result['ctx4138_cmc_cmv'] = $ctxwatch;

// ---- 3. Write proof.
$snap1 = a4_snapshot((int)$snap0['logstore_maxid'], true);
$cnt1 = a4_counts($probetables);
$max1 = a4_maxids($probetables);
$trips = a4_tripwire_compare($snap0, $snap0, $snap1);
$events = a4_event_recorder::take();
if ($planevents || $events) {
    $trips[] = 'events fired: ' . (count($planevents) + count($events));
}
if (!a4_event_recorder::intact()) {
    $trips[] = 'event observer table was reloaded';
}
if ($sendlines) {
    $trips[] = 'send attempt output: ' . count($sendlines) . ' line(s)';
}
$probe = [];
foreach ($probetables as $t) {
    $probe[$t] = ['count' => [$cnt0[$t] ?? null, $cnt1[$t] ?? null], 'maxid' => [$max0[$t] ?? null, $max1[$t] ?? null],
        'unchanged' => ($cnt0[$t] ?? null) === ($cnt1[$t] ?? null) && ($max0[$t] ?? null) === ($max1[$t] ?? null)];
    if (!$probe[$t]['unchanged']) {
        $trips[] = "$t count/maxid changed";
    }
}
$result['write_proof'] = [
    'probe_tables' => $probe,
    'all_tables_maxid' => count($snap1['maxids']) . ' tables compared',
    'checksums_equal' => $snap0['checksums'] === $snap1['checksums'],
    'snapshot_delta' => a4_snapshot_delta($snap0, $snap1),
    'trips' => array_values(array_unique($trips)),
    'tx_read_only_end' => a4_readonly_state(),
    'selfcheck_output_lines' => count(a4_split_output($selfcheckout)[1]),
];
$result['per_subject'] = $persubject;

$dir = dirname($outpath);
if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
    a4_abort("cannot create folder for $outpath");
}
file_put_contents($outpath, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR));

// ---- Summary (counts only).
$inscopeuncovered = array_sum(array_column($percomp, 'uncovered'));
$planfailures = 0;
foreach ($groupstats as $gs) {
    $planfailures += array_sum($gs['failures']);
}
a4_out('==== A4 SUPPLEMENT PLAN (read-only) ====');
a4_out('reports: ' . implode(', ', $rep['files']) . '; processed in reports ' . count($rep['processed'])
    . '; A4 requests ' . count($a4rows) . ': status 10 with user row ' . $result['a4_status10_with_user_row']
    . ', no user row ' . $result['a4_no_user_row'] . ', other ' . json_encode($other)
    . '; report subjects not status 10 now ' . count($reportonly) . ', status 10 not in reports ' . count($status10only));
a4_out('stock_approval_truncates(): ' . var_export($stocktruncates, true) . ' (' . $result['stock_approval_truncates_seconds']
    . ' s); registry defaults_set ' . var_export($result['registry']['defaults_set'], true));
foreach ($groupstats as $g => $gs) {
    a4_out(sprintf('plan %-9s subjects %3d  with missed %3d  with gap %3d  with supplementary %3d  supplementary ctx %5d  '
        . 'gap ctx %5d  failures %d', $g, $gs['subjects'], $gs['subjects_with_missed'], $gs['subjects_with_gap'],
        $gs['subjects_with_supplementary'], $gs['supplementary_contexts'], $gs['gap_contexts'], array_sum($gs['failures'])));
    a4_out('     missed contexts by component (subject-context pairs): ' . json_encode($gs['missed_by_component'])
        . '; subjects ' . json_encode($gs['missed_subjects_by_component']));
    a4_out('     timing per subject (s): ' . json_encode($gs['timing']));
    if ($gs['failures']) {
        a4_out('     failures: ' . json_encode($gs['failures']));
    }
}
a4_out('plan timing, all subjects (s): ' . json_encode($result['plan_timing_all']) . '; other output lines '
    . $outputlines['count'] . '; events ' . count($planevents) . '; send-attempt lines ' . count($sendlines));
a4_out('reconciliation over ' . count($lists) . ' processed subject(s) in ' . $result['reconcile_seconds'] . ' s; scope: '
    . $result['reconcile_scope']);
foreach ($percomp as $c => $v) {
    a4_out(sprintf('  %-46s contexts %4d  pairs %5d  rows %6d  stock %6d  supplement %6d  uncovered %4d  vanished %5d',
        $c, $v['contexts'], $v['pairs'], $v['rows'], $v['covered_stock'], $v['covered_supplement'], $v['uncovered'],
        $v['vanished']));
}
foreach ($otherrecon as $bk => $specs) {
    a4_out('  out of scope, bucket ' . $bk . ': ' . json_encode($specs));
}
a4_out('context 4138 cmc/cmv rows: ' . json_encode($ctxwatch));
if ($specerrors) {
    a4_out('bucket spec errors: ' . json_encode($specerrors));
}
a4_out('write proof: ' . json_encode($probe) . '; all-table max ids and checksums: '
    . ($result['write_proof']['checksums_equal'] ? 'checksums equal' : 'CHECKSUMS DIFFER') . '; trips: '
    . ($result['write_proof']['trips'] ? implode('; ', $result['write_proof']['trips']) : 'none')
    . '; tx_read_only at end ' . $result['write_proof']['tx_read_only_end']);
a4_out('IN-SCOPE UNCOVERED ROWS: ' . $inscopeuncovered . '; plan failures: ' . $planfailures);
a4_out('result: ' . $outpath);
exit($result['write_proof']['trips'] ? 3 : (($inscopeuncovered || $planfailures || $specerrors) ? 5 : 0));
