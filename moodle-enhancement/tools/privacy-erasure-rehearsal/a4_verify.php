<?php
// A4 rehearsal verification. READ-ONLY: the DB session is switched to READ ONLY before anything else, with the same
// zero-row self-test as the rehearsal's dry run. Prints and writes ids and counts only.
//
// What it checks, after one or more --execute runs of a4_rehearsal.php ("processed" = outcome deleted or
// deleted_with_provider_failures in the reports):
//   1. Discovery. Re-asks every privacy provider get_contexts_for_userid() for the processed subjects and for two
//      control ids: one user id that never existed and one deleted user outside A4 that has a user context. A context
//      a component also reports for both controls is "unconditional" (reported for any user id). A context kept by
//      design (A4_RESIDUE_BY_DESIGN, code-verified) is listed separately. Anything else still reported is
//      "still_reported"; when the component's discovery is exact (local_sentientia_* except request,
//      tool_certificate: a4_discovery_exact) it is a VIOLATION: the delete did not remove the data.
//   2. Must-be-empty checks (a4_must_be_empty_specs). Specs that are not context-scoped (Sentientia tables, core_user,
//      core_message, tool_messageinbound): the rows the provider's delete removes must be gone for the subjects whose
//      request approved that provider; any row left is a VIOLATION (as before). Context-scoped specs (tool_certificate,
//      mod_quiz, mod_scorm, core_grades, core_course, tool_log, report_stats; 2026-11-13): every leftover row of every
//      processed subject is put in a bucket by the context the provider's delete keys on, against that subject's lists
//      in the report line: failure (context in the approved list, iterated or full: the delete was asked to run there;
//      a VIOLATION), undiscovered (context gone = ctx_missing, or not in the full discovery), truncated (in the full
//      discovery, not iterated: contextlist_base iterator bug), registry (iterated, not approved). Report lines written
//      before the change (the canary) lack the full lists: failure uses their approved_ids and every other row is in
//      not_approved_unbucketed_pre_guard (not a violation). README "Residue buckets". Also VIOLATIONS (2026-11-13
//      review): any row in registry while every execute start line has defaults_set false (api.php then approves
//      every iterated context, so the recorded lists are inconsistent: registry_bucket_impossible), and any execute
//      request line whose approved list has an iterator hole (stock api.php builds approved lists without holes:
//      approved_list_hole). tool_certificate issue owners are the union of cert_issue_owners over every start line.
//   3. Other people's rows: compares the blast-radius baseline in the run's start line (rows of everyone who is not
//      an A4 subject, per table the A4 path writes) with now. Fewer non-subject rows, or more rows anonymised than
//      A4 subjects held, is a VIOLATION.
//   4. Residual-data counts for all A4 subjects against the run's start and the census baseline (information).
//   5. Non-empty user-row fields of the processed subjects (core_user keeps names by design; information).
//   6. Tripwires: the run's start snapshot (all-table max ids, state checksums, send tables) against now.
//   7. Request statuses and max id (any id above the start max id = a new request was created).
//
// Usage (cwd C:/xampp/htdocs/moodle5/public), with the A4 php guard flags (see README):
//   php <A4 flags> "D:/Claude Local/kit-scratch/1113/a4/rehearsal/a4_verify.php" --report=PATH[,PATH...] [options]
// Options:
//   --report=PATHS     Comma-separated a4_rehearsal.php execute report(s). The earliest "start" line is the baseline
//                      (snapshot, blast baseline, cert_issue_ids); cert_issue_owners and registry.defaults_set are
//                      read from every execute start line.
//   --no-discovery     Skip step 1 (about 1 s per subject).
//   --limit=N          Discovery for the first N processed subjects only.
//   --census=PATH      Census raw JSON (default ../maps/data-census-raw.json).
//   --out=PATH         Absolute path of the JSON result (default reports/a4-verify-<timestamp>.json).
//   -h, --help
// Exit codes: 0 all checks pass, 1 usage, 2 guard/abort, 3 tripwire or new request, 4 verification violation.

define('CLI_SCRIPT', true);

require('C:/xampp/htdocs/moodle5/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . "/{$CFG->admin}/tool/dataprivacy/lib.php");
require_once(__DIR__ . '/a4_lib.php');

$phpfacts = a4_require_hardened_php();

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'report' => '',
    'no-discovery' => false,
    'limit' => 0,
    'census' => '',
    'out' => '',
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
foreach (['report', 'limit', 'census', 'out'] as $k) {
    if ($options[$k] === true) {
        cli_error("--$k needs a value (--$k=...)", 1);
    }
}
if (!preg_match('/^\d+$/', (string)$options['limit'])) {
    cli_error('--limit must be a whole number', 1);
}
$limit = (int)$options['limit'];
if ($options['out'] !== '' && !a4_is_absolute((string)$options['out'])) {
    cli_error('--out must be an absolute path', 1);
}
$censuspath = ($options['census'] !== '') ? (string)$options['census'] : dirname(__DIR__) . '/maps/data-census-raw.json';
$outpath = ($options['out'] !== '') ? (string)$options['out'] : __DIR__ . '/reports/a4-verify-' . date('Ymd-His') . '.json';

// Guards: local copy only, READ ONLY session, the same in-process kill switches and event recorder.
$envfacts = a4_guard_environment();
$rofacts = a4_set_readonly_session(true);
\core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);
\core\cron::setup_user();
$killfacts = a4_apply_kill_switches();
$eventfacts = a4_event_recorder::install();
$CFG->mtrace_wrapper = 'a4_mtrace_capture';

// ---- Read the report(s).
$start = null;
$starts = [];         // every execute start line (sorted by ts below)
$approvedholes = [];  // requestid => approved-list iterator hole (must never happen on the stock path)
$processed = [];      // subjectid => requestid
$approvedids = [];    // subjectid => [component => [contextid]]
$lists = [];          // subjectid => a4_subject_lists_from_report() (full/iterated lists, or pre_guard)
$outcomes = [];
$failures = 0;
$reportfiles = [];
if ($options['report'] !== '' && $options['report'] !== false) {
    foreach (explode(',', (string)$options['report']) as $p) {
        $p = trim($p);
        if ($p === '') {
            continue;
        }
        if (!is_readable($p)) {
            cli_error("report not readable: $p", 1);
        }
        $reportfiles[] = $p;
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $r = json_decode($line, true);
            if (!is_array($r) || empty($r['event'])) {
                continue;
            }
            if ($r['event'] === 'start' && ($r['mode'] ?? '') === 'execute') {
                $starts[] = $r;
                if ($start === null || strcmp($r['ts'], $start['ts']) < 0) {
                    $start = $r;
                }
            }
            if ($r['event'] === 'request' && ($r['mode'] ?? '') === 'execute') {
                $o = (string)($r['outcome'] ?? '');
                $outcomes[$o] = ($outcomes[$o] ?? 0) + 1;
                $failures += count($r['provider_failures'] ?? []);
                // An iterator hole in an APPROVED list (any outcome): stock api.php:1210-1230 builds approved lists from
                // iterated, unique ids, so this cannot happen and means the recorded evidence is inconsistent.
                $holes = array_map(fn($ids) => count((array)$ids), (array)($r['iterator_truncated']['approved'] ?? []));
                $lostn = max(array_sum($holes), (int)($r['truncation']['approved_contexts_lost'] ?? 0));
                if ($lostn > 0) {
                    $approvedholes[(int)($r['requestid'] ?? 0)] = ['subjectid' => (int)($r['subjectid'] ?? 0),
                        'outcome' => $o, 'contexts' => $lostn, 'by_component' => $holes];
                }
                if (in_array($o, ['deleted', 'deleted_with_provider_failures'], true) && !empty($r['subjectid'])) {
                    $processed[(int)$r['subjectid']] = (int)$r['requestid'];
                    $approvedids[(int)$r['subjectid']] = $r['approved_ids'] ?? [];
                    $lists[(int)$r['subjectid']] = a4_subject_lists_from_report($r);
                }
            }
        }
    }
}
usort($starts, fn($a, $b) => strcmp((string)($a['ts'] ?? ''), (string)($b['ts'] ?? '')));
ksort($approvedholes);
// Data registry state over every execute start line: a row in the registry bucket is impossible when ALL of them
// say defaults_set false (api.php:1208-1225 then approves every iterated context). Unknown or true: possible.
$defaultsset = array_map(fn($s) => $s['registry']['defaults_set'] ?? null, $starts);
$registryimpossible = $starts && !array_filter($defaultsset, fn($v) => $v !== false);
$census = a4_load_census_baseline($censuspath);
$allsubjects = a4_all_a4_subject_ids();
$violations = [];

$result = [
    'generated' => gmdate('Y-m-d\TH:i:s\Z'), 'php' => $phpfacts, 'env' => $envfacts, 'readonly' => $rofacts,
    'events' => $eventfacts, 'reports' => $reportfiles, 'report_outcomes' => $outcomes,
    'report_provider_failures' => $failures, 'processed_subjects' => count($processed), 'a4_subjects' => count($allsubjects),
];

// ---- 1. Discovery with control ids.
/** Discovery for one user id: [component => [key => contextid]], key = system | own_user | ctx:<id>. */
function a4v_discover(int $uid, a4_recording_observer $obs): array {
    $GLOBALS['A4_MTRACE_BUF'] = '';
    ob_start();
    try {
        $manager = new \core_privacy\manager();
        $manager->set_observer($obs);
        $collection = $manager->get_contexts_for_userid($uid);
        $out = [];
        foreach ($collection as $cl) {
            $c = $cl->get_component();
            foreach ($cl as $ctx) {
                if ((int)$ctx->contextlevel === CONTEXT_SYSTEM) {
                    $key = 'system';
                } else if ((int)$ctx->contextlevel === CONTEXT_USER && (int)$ctx->instanceid === $uid) {
                    $key = 'own_user';
                } else {
                    $key = 'ctx:' . $ctx->id;
                }
                $out[$c][$key] = (int)$ctx->id;
            }
        }
        return $out;
    } finally {
        ob_end_clean();
        $GLOBALS['A4_MTRACE_BUF'] = '';
    }
}

$disc = ['skipped' => true];
if (empty($options['no-discovery'])) {
    $obs = new a4_recording_observer();
    $obs->phase = 'verify-discovery';
    $c0 = (int)$DB->get_field_sql('SELECT MAX(id) FROM {user}') + 1000000;
    [$nin, $np] = $DB->get_in_or_equal($allsubjects ?: [0], SQL_PARAMS_NAMED, 'a', false);
    $c1 = (int)$DB->get_field_sql("SELECT MIN(u.id) FROM {user} u
                                     JOIN {context} c ON c.instanceid = u.id AND c.contextlevel = 30
                                    WHERE u.deleted = 1 AND u.id $nin", $np);
    if (!$c1) {
        $c1 = (int)$DB->get_field_sql("SELECT MIN(u.id) FROM {user} u WHERE u.deleted = 1 AND u.id $nin", $np);
    }
    $controls = [$c0 => a4v_discover($c0, $obs)];
    if ($c1) {
        $controls[$c1] = a4v_discover($c1, $obs);
    }
    // Unconditional (component, key): reported for every control id.
    $unconditional = [];
    $first = true;
    foreach ($controls as $cid => $d) {
        $keys = [];
        foreach ($d as $comp => $ks) {
            foreach (array_keys($ks) as $k) {
                $keys["$comp|$k"] = true;
            }
        }
        $unconditional = $first ? $keys : array_intersect_key($unconditional, $keys);
        $first = false;
    }
    $c1ownuser = [];
    foreach ($controls[$c1] ?? [] as $comp => $ks) {
        if (isset($ks['own_user'])) {
            $c1ownuser[] = $comp;
        }
    }

    $scope = array_keys($processed);
    sort($scope);
    if ($limit) {
        $scope = array_slice($scope, 0, $limit);
    }
    $comp = [];
    $stillreported = [];
    $t0 = microtime(true);
    foreach ($scope as $uid) {
        $obs->requestid = $processed[$uid] ?? 0;
        foreach (a4v_discover((int)$uid, $obs) as $c => $ks) {
            foreach ($ks as $k => $ctxid) {
                $comp[$c] = $comp[$c] ?? ['subjects' => 0, 'unconditional' => 0, 'by_design' => 0, 'still_reported' => 0,
                    'approved_still_reported' => 0];
                if (isset($unconditional["$c|$k"])) {
                    $class = 'unconditional';
                } else if (isset(A4_RESIDUE_BY_DESIGN[$c][$k]) || isset(A4_RESIDUE_BY_DESIGN[$c]['*'])) {
                    $class = 'by_design';
                } else {
                    $class = 'still_reported';
                    $wasapproved = in_array($ctxid, $approvedids[$uid][$c] ?? [], true);
                    if ($wasapproved) {
                        $comp[$c]['approved_still_reported']++;
                    }
                    $row = ['subjectid' => (int)$uid, 'requestid' => $processed[$uid] ?? 0, 'component' => $c,
                        'contextid' => $ctxid, 'key' => $k, 'approved' => $wasapproved];
                    if (count($stillreported) < 2000) {
                        $stillreported[] = $row;
                    }
                    if (a4_discovery_exact($c)) {
                        $violations[] = ['check' => 'discovery_exact', 'note' => 'provider still reports data after delete']
                            + $row;
                    }
                }
                $comp[$c][$class]++;
            }
            $comp[$c]['subjects']++;
        }
    }
    ksort($comp);
    $disc = [
        'subjects' => count($scope), 'seconds' => round(microtime(true) - $t0, 1),
        'controls' => ['never_existed' => $c0, 'deleted_outside_a4' => $c1],
        'control_unconditional' => array_keys($unconditional), 'control_own_user_components' => $c1ownuser,
        'by_design' => A4_RESIDUE_BY_DESIGN, 'components' => $comp, 'still_reported' => $stillreported,
        'failures' => $obs->take(),
    ];
}
$result['discovery'] = $disc;

// ---- 2. Must-be-empty checks: flat for the specs that are not context-scoped, bucketed for the others.
$issueids = array_map('intval', $start['cert_issue_ids'] ?? []);   // Earliest start line: the widest set.
// Issue owners: union over EVERY execute start line, earliest first (2026-11-13 review). The canary's start line has no
// cert_issue_owners; a later run's start line records the owners of the issues that still existed then, so a file
// that run leaves behind is given to its subject instead of failure_subject_unresolved.
$issueowners = [];
$ownerlines = 0;
foreach ($starts as $s) {
    if (!empty($s['cert_issue_owners'])) {
        $ownerlines++;
    }
    foreach ($s['cert_issue_owners'] ?? [] as $iid => $owner) {
        if (!isset($issueowners[(int)$iid])) {
            $issueowners[(int)$iid] = (int)$owner;
        }
    }
}
$mbeflat = a4_must_be_empty_counts(function(string $component) use ($processed, $approvedids): array {
    $s = [];
    foreach ($processed as $uid => $rid) {
        if (!$approvedids[$uid] || isset($approvedids[$uid][$component])) {
            $s[] = (int)$uid;
        }
    }
    return $s;
}, $issueids, true);
$mbebucket = a4_must_be_empty_buckets($lists, $issueids, $issueowners);
$mbe = [];
foreach (a4_must_be_empty_specs() as [$t, $w, $c]) {   // Spec order.
    $k = a4_mbe_key($t, $w, $c);
    $mbe[$k] = $mbebucket[$k] ?? $mbeflat[$k];
}
$buckettotal = array_fill_keys(A4_RESIDUE_BUCKETS, 0) + ['ctx_missing' => 0, 'ctx_missing_in_failure' => 0,
    'failure_subject_unresolved' => 0, 'subject_not_processed' => 0, 'rows' => 0];
foreach ($mbe as $k => $v) {
    if (!empty($v['bucketed']) && isset($v['buckets'])) {
        foreach ($v['buckets'] as $bk => $n) {
            $buckettotal[$bk] += $n;
        }
        $buckettotal['ctx_missing_in_failure'] += $v['ctx_missing_by_bucket']['failure'] ?? 0;
        foreach (['ctx_missing', 'failure_subject_unresolved', 'subject_not_processed', 'rows'] as $f) {
            $buckettotal[$f] += $v[$f];
        }
        if ($v['buckets']['failure'] > 0) {
            $violations[] = ['check' => 'must_be_empty', 'spec' => $k, 'bucket' => 'failure',
                'rows_left' => $v['buckets']['failure'], 'subject_unresolved' => $v['failure_subject_unresolved'],
                'first' => array_slice($v['failure_rows'], 0, 20)];
        }
        if ($registryimpossible && $v['buckets']['registry'] > 0) {
            $regsubj = array_keys(array_filter($v['by_subject'], fn($b) => !empty($b['registry'])));
            $violations[] = ['check' => 'registry_bucket_impossible', 'spec' => $k, 'rows' => $v['buckets']['registry'],
                'subjects' => array_slice($regsubj, 0, 20), 'note' => 'defaults_set is false in every start line, so '
                . 'api.php approves every iterated context: a row in registry means the recorded lists do not match '
                . 'what was approved'];
        }
    } else if (!empty($v['rows_left'])) {
        $violations[] = ['check' => 'must_be_empty', 'spec' => $k, 'rows_left' => $v['rows_left']];
    }
    if (isset($v['error'])) {
        $violations[] = ['check' => 'must_be_empty_error', 'spec' => $k, 'error' => $v['error']];
    }
}
if ($approvedholes) {
    $violations[] = ['check' => 'approved_list_hole', 'requests' => count($approvedholes),
        'contexts' => array_sum(array_column($approvedholes, 'contexts')),
        'first' => array_slice($approvedholes, 0, 20, true), 'note' => 'an approved list with an iterator hole: stock '
        . 'api.php:1210-1230 builds approved lists from iterated unique ids, so the recorded evidence is inconsistent'];
}
$preguard = array_keys(array_filter($lists, fn($l) => !empty($l['pre_guard'])));
sort($preguard);
$result['must_be_empty'] = $mbe;
$result['must_be_empty_buckets_total'] = $buckettotal;
$result['must_be_empty_pre_guard_subjects'] = $preguard;
$result['registry_defaults_set_by_start_line'] = $defaultsset;
$result['registry_bucket_is_violation'] = $registryimpossible;
$result['approved_list_holes'] = $approvedholes;
$result['cert_issue_owners'] = ['start_lines' => count($starts), 'start_lines_with_owners' => $ownerlines,
    'issues_with_owner' => count($issueowners), 'cert_issue_ids_from_earliest' => count($issueids)];

// ---- 3. Other people's rows (run-level blast radius).
$blast = ['skipped' => 'no blast_baseline in the start line'];
if (!empty($start['blast_baseline'])) {
    $nowb = a4_blast_baseline($allsubjects);
    $blast = [];
    foreach ($start['blast_baseline'] as $k => $b) {
        $nw = $nowb[$k] ?? null;
        if (!isset($b['total'], $nw['total'])) {
            continue;
        }
        $row = ['mode' => $b['mode'], 'total' => [$b['total'], $nw['total']], 'a4' => [$b['a4'], $nw['a4']],
            'nonsubject' => [$b['nonsubject'], $nw['nonsubject']]];
        if (isset($b['anon'])) {
            $row['anon'] = [$b['anon'], $nw['anon']];
        }
        $bad = null;
        if ($b['mode'] === 'anon') {
            // The A4 subjects' own rows in this table go through the table's delete spec (org_member, learningpath
            // users): the total may fall by exactly those. The key is derived from the table name, so start lines
            // written before 'owned_by' existed (the canary, 2026-11-13) are read the same way.
            $ownkey = $b['owned_by'] ?? (strtok($k, ':') . ':delete');
            $ownedgone = isset($start['blast_baseline'][$ownkey]['a4'], $nowb[$ownkey]['a4'])
                ? $start['blast_baseline'][$ownkey]['a4'] - $nowb[$ownkey]['a4'] : 0;
            // Newly anonymised rows among rows the A4 subjects do not own. Old start lines (no anon_owned) get an upper
            // bound: assume every A4-owned row at the start had the anonymised value (review 2026-11-13).
            $growth = isset($b['anon_owned'], $nw['anon_owned'])
                ? ($nw['anon'] - $nw['anon_owned']) - ($b['anon'] - $b['anon_owned'])
                : ($nw['anon'] - ($nw['anon_owned'] ?? 0))
                    - max(0, $b['anon'] - ($start['blast_baseline'][$ownkey]['a4'] ?? 0));
            // Rows pointing at a subject that the subjects also own are deleted, never anonymised: no allowance.
            $allowance = $b['a4'] - ($b['a4_owned'] ?? 0);
            $row['owned_removed'] = $ownedgone;
            $row['anon_growth'] = $growth;
            $row['allowance'] = $allowance;
            if ($b['total'] - $nw['total'] > $ownedgone) {
                $bad = 'rows removed from a table the provider only anonymises (beyond the subjects\' own '
                    . $ownedgone . ')';
            } else if ($growth > $allowance) {
                $bad = $growth . ' rows newly anonymised, A4 subjects held ' . $b['a4'];
            }
        } else if ($b['mode'] === 'delete') {
            // Rows that were not A4 subjects' rows at the start: fewer now means someone else's rows were removed.
            // An anonymised A4 row also becomes "non-subject"; that only makes the count grow.
            if ($nw['nonsubject'] < $b['nonsubject']) {
                $bad = 'non-subject rows ' . $b['nonsubject'] . ' -> ' . $nw['nonsubject'];
            }
        } else if ($b['mode'] === 'keep' && $nw['total'] < $b['total']) {
            $bad = 'rows ' . $b['total'] . ' -> ' . $nw['total'];
        }
        if ($bad !== null) {
            $row['violation'] = $bad;
            $violations[] = ['check' => 'other_peoples_rows', 'spec' => $k, 'note' => $bad];
        } else if ($nw['nonsubject'] > $b['nonsubject'] && $b['mode'] !== 'anon') {
            $row['note'] = 'non-subject rows grew (other activity on the site?)';
        }
        $blast[$k] = $row;
    }
}
$result['other_peoples_rows'] = $blast;

// ---- 4. Residual data, all A4 subjects.
$resnow = a4_residual_counts($allsubjects);
$rescompare = [];
foreach ($resnow as $key => $v) {
    $rescompare[$key] = [
        'census' => $census['residuals'][$key]['rows'] ?? null,
        'run_start' => $start['residuals'][$key]['rows'] ?? null,
        'now' => $v['rows'] ?? null,
        'subjects_now' => $v['subjects'] ?? null,
    ];
}
$result['residuals_all_a4_subjects'] = $rescompare;
$result['residuals_processed_subjects'] = $processed ? a4_residual_counts(array_keys($processed)) : [];

// ---- 5. User-row residue.
$result['user_row_residue_processed'] = $processed ? a4_user_row_residue(array_keys($processed)) : [];
$result['user_row_residue_all_deleted_a4'] = a4_user_row_residue($allsubjects);
$result['user_row_residue_census'] = $census['userrow'] ?? null;

// ---- 6. Tripwires.
if ($start && !empty($start['snapshot'])) {
    $base = $start['snapshot'];
    $now = a4_snapshot((int)$base['logstore_maxid'], isset($base['maxids']));
    $result['tripwire_baseline'] = 'report start';
    $result['tripwire_trips'] = a4_tripwire_compare($base, $base, $now);
    $result['tripwire_delta'] = a4_snapshot_delta($base, $now);
    $result['new_rows'] = isset($base['maxids']) && $result['tripwire_trips']
        ? a4_new_row_ids($base['maxids'], $now['maxids']) : [];
} else {
    $now = a4_snapshot();
    $c = $census['counts'] ?? [];
    $trips = [];
    $checks = [
        'notifications' => 'notifications_max_id', 'messages' => 'messages_max_id',
        'task_adhoc' => 'task_adhoc_max_id', 'tool_dataprivacy_request' => 'tool_dataprivacy_request_max_id',
    ];
    foreach ($checks as $t => $ck) {
        if (isset($c[$ck], $now['tables'][$t]) && $now['tables'][$t]['maxid'] > (int)$c[$ck]) {
            $trips[] = "$t.maxid {$c[$ck]} -> {$now['tables'][$t]['maxid']}";
        }
    }
    $result['tripwire_baseline'] = 'census (no execute report given): max ids only';
    $result['tripwire_trips'] = $trips;
}
$ev = a4_event_recorder::take();
if ($ev) {
    $result['tripwire_trips'][] = 'events fired during verification: ' . count($ev);
}

// ---- 7. Requests.
$result['requests_start'] = $start['requests'] ?? null;
$result['requests_now'] = a4_request_status_distribution();
$startmax = (int)($start['requests']['maxid'] ?? ($census['counts']['tool_dataprivacy_request_max_id'] ?? 515));
$result['new_request_ids'] = array_map('intval', $DB->get_fieldset_select('tool_dataprivacy_request', 'id',
    'id > :m', ['m' => $startmax]));
$result['violations'] = $violations;
$result['tx_read_only_end'] = a4_readonly_state();

// ---- Output.
$dir = dirname($outpath);
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents($outpath, json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR));

a4_out('==== A4 VERIFY (read-only) ====');
a4_out('reports: ' . ($reportfiles ? implode(', ', $reportfiles) : 'none') . '; execute outcomes ' . json_encode($outcomes)
    . '; provider failures in reports ' . $failures);
a4_out('processed subjects: ' . count($processed) . ' of ' . count($allsubjects) . ' A4 subjects');
if (empty($disc['skipped'])) {
    a4_out('discovery: ' . $disc['subjects'] . ' subject(s) in ' . $disc['seconds'] . ' s; controls ' . json_encode($disc['controls'])
        . '; provider failures: ' . count($disc['failures']));
    foreach ($disc['components'] as $c => $v) {
        $flag = a4_discovery_exact($c) ? ' exact' : '';
        a4_out(sprintf('  %-40s subj %4d  unconditional %4d  by-design %4d  STILL %4d (approved %4d)%s', $c, $v['subjects'],
            $v['unconditional'], $v['by_design'], $v['still_reported'], $v['approved_still_reported'], $flag));
    }
}
a4_out('must-be-empty checks (flat: rows_left is a violation; bucketed: only failure is a violation):');
foreach ($mbe as $k => $v) {
    if (!empty($v['bucketed']) && isset($v['buckets'])) {
        $short = ['subjects' => $v['subjects'], 'rows' => $v['rows'], 'buckets' => array_filter($v['buckets']),
            'ctx_missing' => $v['ctx_missing'], 'secs' => $v['secs']]
            + (!empty($v['ctx_missing_by_bucket']['failure'])
                ? ['ctx_missing_in_failure' => $v['ctx_missing_by_bucket']['failure']] : [])
            + ($v['failure_subject_unresolved'] ? ['failure_subject_unresolved' => $v['failure_subject_unresolved']] : [])
            + ($v['subject_not_processed'] ? ['subject_not_processed' => $v['subject_not_processed']] : []);
        a4_out('  ' . str_pad(a4_trunc($k, 70), 72) . ' BUCKETED ' . json_encode($short));
    } else {
        a4_out('  ' . str_pad(a4_trunc($k, 70), 72) . ' ' . json_encode($v));
    }
}
a4_out('residue buckets, all context-scoped specs: ' . json_encode($buckettotal)
    . ' (ctx_missing = bucketed rows whose context is gone: in undiscovered or not_approved_unbucketed_pre_guard, or in'
    . ' failure (ctx_missing_in_failure) when the approved list held that context)');
a4_out('registry defaults_set by execute start line: ' . json_encode($defaultsset) . '; rows in registry are a violation: '
    . ($registryimpossible ? 'yes' : 'no') . '; approved-list iterator holes (violation): ' . count($approvedholes)
    . ' request(s); cert issue owners: ' . count($issueowners) . ' from ' . $ownerlines . ' of ' . count($starts)
    . ' start line(s)');
if ($preguard) {
    a4_out('NOTE: ' . count($preguard) . ' of ' . count($lists) . ' processed subject(s) come from report lines written '
        . 'before the bucketing change (no full discovery/approved lists): for them failure uses approved_ids and every '
        . 'other leftover row is in not_approved_unbucketed_pre_guard (not a violation). Subjects: '
        . implode(',', $preguard));
}
if (isset($blast['skipped'])) {
    a4_out('other people\'s rows: ' . $blast['skipped']);
} else {
    $changed = array_filter($blast, fn($r) => $r['total'][0] !== $r['total'][1] || isset($r['violation']));
    a4_out('other people\'s rows: ' . count($blast) . ' specs; changed: ' . json_encode($changed));
}
a4_out('residual rows, all A4 subjects (census -> run start -> now):');
foreach ($rescompare as $k => $v) {
    if (!$v['census'] && !$v['run_start'] && !$v['now']) {
        continue;
    }
    a4_out(sprintf('  %-48s %8s -> %8s -> %8s', $k, $v['census'] ?? '-', $v['run_start'] ?? '-', $v['now'] ?? '-'));
}
a4_out('user-row residue (processed): ' . json_encode($result['user_row_residue_processed']));
a4_out('tripwires (' . $result['tripwire_baseline'] . '): '
    . ($result['tripwire_trips'] ? implode('; ', $result['tripwire_trips']) : 'no increase'));
a4_out('requests now: ' . json_encode($result['requests_now']['by_type_cm_status']) . '; new request ids: '
    . json_encode($result['new_request_ids']));
a4_out('VIOLATIONS: ' . count($violations) . ($violations ? ' ' . json_encode(array_slice($violations, 0, 20)) : ''));
a4_out('read-only session at end: tx_read_only=' . $result['tx_read_only_end']);
a4_out('result: ' . $outpath);
exit(($result['tripwire_trips'] || $result['new_request_ids']) ? 3 : ($violations ? 4 : 0));
