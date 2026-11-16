<?php
// A4 residue sweep (ADR-035): replay the stock erasure, then the supplementary erasure, for the A4 subjects whose
// DELETE request is already complete (status 10), on the LOCAL XAMPP copy. It writes NO request rows.
//
// DRY RUN BY DEFAULT: the DB session is READ ONLY and nothing is deleted. --execute deletes, behind the same gates as
// a4_rehearsal.php --execute (README "a4_sweep.php"). Running --execute is Nitin's call.
//
// Per status-10 A4 subject (request type DELETE, creationmethod AUTO, status 10; subject user row present with
// deleted = 1; nothing else is ever selected):
//   plan        erasure_completeness::plan($userid, $truncation, true), taken BEFORE anything is deleted.
//               $truncation = erasure_completeness::stock_approval_truncates() (false the day core is fixed).
//   stock       the three calls process_data_request_task::execute() makes (:93, :143, :151):
//                 \core_privacy\manager::get_contexts_for_userid($userid)
//                 \tool_dataprivacy\api::get_approved_contextlist_collection_for_collection($collection, $user, DELETE)
//                 \core_privacy\manager::delete_data_for_user($approved)
//               with the recording observer (a4_lib.php), never \tool_dataprivacy\manager_observer (DPO messages).
//               With both ADR-035 flags OFF (required for --execute) local_sentientia_privacy behaves as 1.0.3 here.
//   supplement  the list local_sentientia_privacy's provider builds with a flag ON (ONE add_from_sql over SYSCONTEXTID
//               plus plan->supplementary_contextids()), through the SAME approval call (DELETE: the data registry's
//               per-context filter when defaults are set), then
//                 erasure_completeness::apply_delete($user, <approved ids>, 'cli', $truncation, true, $requestid)
//               with the pre-delete plan (hit_C = missed_C ∩ approved; completion gap ∩ approved; plan must be 'cached').
//               On 'cli' apply_delete() also runs the manager's shared completion cleanup on the approved ids.
//   The plan's full lists AND iterated split must equal the stock discovery's (a4s_plan_vs_discovery): reported in a
//   dry run, a tripwire before the stock delete under --execute.
//   No request row, status or message is written; the message tail is not run (the requests are complete).
// Dry run: everything above that only READS (discovery, plan, both approvals, the hit sets apply_delete would use),
// then a projection: every leftover row of the bucketed must-be-empty specs (a4_lib.php) classified as deleted by the
// stock pass, by the supplement, or by neither (vanished = its context is gone).
// Execute: per subject one delegated transaction; the subject's rows per blast spec are counted before, after the
// stock delete and after the supplement (deletes per source); any provider failure, supplementary failure or tripwire
// rolls the subject back and stops (tripwire) or continues (failure; --stop-on-error stops).
//
// Every run needs the A4 network/mail guard flags on the php command line (a4_lib.php A4_PHP_FLAGS). cwd
// C:/xampp/htdocs/moodle5/public.
//
// Options:
//   --execute            Delete. Without it the run is a dry run (READ ONLY session).
//   --limit=N            At most N subjects (after filtering).
//   --ids=1,2,3          Only these request ids (must be status-10 A4 requests with a deleted subject row).
//   --all                Execute only: required when neither --limit nor --ids is given.
//   --report=PATH        Absolute path of the JSON-lines report (default reports/a4-sweep-<mode>-<timestamp>.jsonl).
//   --state-dir=PATH     Absolute folder for sweep-manifest.json, sweep-journal.jsonl and sweep.lock (default state/).
//   --write-manifest     Dry run only: (re)write <state-dir>/sweep-manifest.json from the code as it is now. Without it
//                        a dry run writes the manifest only when none exists, and otherwise reports differences.
//   --backup=PATH        Execute only, required: the complete mysqldump taken just before (a4_check_backup()).
//   --web=maintenance    Execute only, required: admin/cli/maintenance.php --enable was run (Nitin's call).
//   --allow-insert=t1,.. Execute only: tables whose max id may grow (default none).
//   --stop-on-error      Stop after the first subject with a failure.
//   -h, --help
// Exit codes: 0 ok, 1 usage, 2 guard/abort, 3 tripwire, 4 stopped on error.

define('CLI_SCRIPT', true);

require('C:/xampp/htdocs/moodle5/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . "/{$CFG->admin}/tool/dataprivacy/lib.php");
require_once(__DIR__ . '/a4_lib.php');

use core_privacy\local\request\contextlist;
use core_privacy\local\request\contextlist_collection;
use local_sentientia_privacy\local\erasure_completeness;
use local_sentientia_privacy\local\erasure_plan;
use tool_dataprivacy\api;
use tool_dataprivacy\data_request;

/** The host component of the supplementary list. */
const A4S_SUPP = 'local_sentientia_privacy';

$phpfacts = a4_require_hardened_php();

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'execute' => false,
    'limit' => 0,
    'ids' => '',
    'all' => false,
    'report' => '',
    'state-dir' => '',
    'write-manifest' => false,
    'backup' => '',
    'web' => '',
    'allow-insert' => '',
    'stop-on-error' => false,
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
foreach (['ids', 'limit', 'report', 'state-dir', 'backup', 'web', 'allow-insert'] as $k) {
    if ($options[$k] === true) {
        cli_error("--$k needs a value (--$k=...)", 1);
    }
}
$execute = !empty($options['execute']);
$web = (string)$options['web'];
$stoponerror = !empty($options['stop-on-error']);
$writemanifest = !empty($options['write-manifest']);
if (!preg_match('/^\d+$/', (string)$options['limit'])) {
    cli_error('--limit must be a whole number', 1);
}
$limit = (int)$options['limit'];
$ids = [];
if ((string)$options['ids'] !== '') {
    foreach (explode(',', (string)$options['ids']) as $v) {
        $v = trim($v);
        if (!preg_match('/^[1-9][0-9]*$/', $v)) {
            cli_error("--ids: '$v' is not a request id", 1);
        }
        $ids[(int)$v] = true;
    }
}
$allowinsert = [];
if ((string)$options['allow-insert'] !== '') {
    foreach (explode(',', (string)$options['allow-insert']) as $t) {
        $t = trim($t);
        if (!preg_match('/^[a-z0-9_]+$/', $t)) {
            cli_error("--allow-insert: '$t' is not a table name (without prefix)", 1);
        }
        $allowinsert[$t] = true;
    }
}
foreach (['report', 'state-dir'] as $k) {
    if ((string)$options[$k] !== '' && !a4_is_absolute((string)$options[$k])) {
        cli_error("--$k must be an absolute path", 1);
    }
}
if (!$execute && ($options['backup'] !== '' || $options['all'] || $web !== '' || $allowinsert)) {
    cli_error('--backup, --web, --allow-insert and --all only apply with --execute', 1);
}
if ($execute && $writemanifest) {
    cli_error('--write-manifest is a dry-run option', 1);
}
if ($execute) {
    if (!$limit && !$ids && empty($options['all'])) {
        cli_error('--execute needs --limit=N, --ids=... or an explicit --all', 1);
    }
    if ((string)$options['backup'] === '') {
        cli_error('--execute needs --backup=PATH pointing at the mysqldump file taken just before the run', 1);
    }
    if ($web !== 'maintenance') {
        cli_error('--execute needs --web=maintenance (run admin/cli/maintenance.php --enable first: Nitin\'s call)', 1);
    }
}

$mode = $execute ? 'execute' : 'dryrun';
$stamp = date('Ymd-His');
$reportpath = ((string)$options['report'] !== '') ? (string)$options['report']
    : __DIR__ . "/reports/a4-sweep-$mode-$stamp.jsonl";
$statedir = ((string)$options['state-dir'] !== '') ? rtrim((string)$options['state-dir'], '/\\') : __DIR__ . '/state';
$manifestpath = $statedir . '/sweep-manifest.json';
$journalpath = $statedir . '/sweep-journal.jsonl';

// ---------------------------------------------------------------------------------------------------------------
// Guards (as a4_rehearsal.php).
// ---------------------------------------------------------------------------------------------------------------
$envfacts = a4_guard_environment();
$rofacts = $execute ? null : a4_set_readonly_session(true);
\core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);
\core\cron::setup_user();
$killfacts = a4_apply_kill_switches();
$eventfacts = a4_event_recorder::install();
$CFG->mtrace_wrapper = 'a4_mtrace_capture';
$registry = a4_registry_facts();

if (!class_exists(erasure_completeness::class) || !class_exists(\local_sentientia_privacy\privacy\provider::class)) {
    a4_abort('local_sentientia_privacy 1.1.0 (erasure_completeness) is not loadable on the served site: deploy it and '
        . 'purge caches first');
}
$flags = [
    'truncation' => \local_sentientia_privacy\privacy\provider::flag_enabled(
        \local_sentientia_privacy\privacy\provider::FLAG_TRUNCATION),
    'completion' => \local_sentientia_privacy\privacy\provider::flag_enabled(
        \local_sentientia_privacy\privacy\provider::FLAG_COMPLETION),
];
if ($execute && ($flags['truncation'] || $flags['completion'])) {
    a4_abort('an ADR-035 flag is ON at global scope: the stock calls would already run the supplement inside '
        . 'local_sentientia_privacy\'s provider. The sweep replays the flags-OFF case only.');
}
$GLOBALS['A4_MTRACE_BUF'] = '';
ob_start();
$truncation = erasure_completeness::stock_approval_truncates();
ob_end_clean();
$GLOBALS['A4_MTRACE_BUF'] = '';

$webfacts = null;
$backupfacts = null;
if ($execute) {
    $mfile = $CFG->dataroot . '/climaintenance.html';
    if (!is_file($mfile)) {
        a4_abort('--web=maintenance, but climaintenance.html is not in dataroot: run admin/cli/maintenance.php '
            . '--enable first (Nitin\'s call)');
    }
    $webfacts = ['web' => $web, 'climaintenance_file' => true];
    $GLOBALS['A4_REQUIRE_MAINT'] = $mfile;   // Re-checked after every subject by a4_tripwire_compare().
    a4_out('Checking the backup file (one read pass, sha256) ...');
    $backupfacts = a4_check_backup((string)$options['backup']);
}

// Provider manifest (separate file from the rehearsal's): the execute run must run the code the dry run ran.
$manifestnow = a4_manifest_build();
$stored = is_readable($manifestpath) ? json_decode((string)file_get_contents($manifestpath), true) : null;
$diff = is_array($stored) ? a4_manifest_diff($stored, $manifestnow) : null;
if ($execute) {
    if (!is_array($stored)) {
        a4_abort("$manifestpath is missing: run a dry run first");
    }
    if ($diff) {
        foreach (array_slice($diff, 0, 50) as $d) {
            fwrite(STDERR, "  manifest: $d\n");
        }
        a4_abort(count($diff) . ' difference(s) from the sweep manifest written by the dry run of '
            . ($stored['generated'] ?? '?') . '. Review them, then re-bless with a dry run --write-manifest.');
    }
    $manifestfacts = ['status' => 'match', 'generated' => $stored['generated'], 'files' => count($stored['files'])];
    $manifestcomponents = $stored['components'];
} else if (!is_array($stored) || $writemanifest) {
    a4_manifest_write($manifestpath, $manifestnow);
    $manifestfacts = ['status' => 'written', 'path' => $manifestpath, 'generated' => $manifestnow['generated'],
        'files' => count($manifestnow['files']), 'components' => count($manifestnow['components'])];
    $manifestcomponents = $manifestnow['components'];
} else {
    $manifestfacts = ['status' => $diff ? 'differs' : 'match', 'generated' => $stored['generated'],
        'files' => count($stored['files']), 'differences' => array_slice($diff, 0, 100)];
    $manifestcomponents = $stored['components'];
    if ($diff) {
        a4_out('WARNING: ' . count($diff) . ' difference(s) from ' . $manifestpath . '; an --execute run would be '
            . 'refused (first: ' . $diff[0] . ').');
    }
}

$base = a4_snapshot(null, true);
$logbaseline = $base['logstore_maxid'];
$distbefore = a4_request_status_distribution();
if ($base['task_running'] > 0) {
    if ($execute) {
        a4_abort('a task is marked as running (task_adhoc/task_scheduled timestarted set): cron may be active');
    }
    a4_out('WARNING: a task is marked as running; cron may be active (dry run continues, read-only).');
}
if ($execute && $base['lastcronstart'] > time() - 900) {
    a4_abort('cron started less than 15 minutes ago');
}
if (!api::can_manage_data_requests($USER->id)) {
    a4_abort('cron user cannot manage data requests (api::can_manage_data_requests)');
}
$dpocount = count(api::get_site_dpos());

$lockfh = null;
if ($execute) {
    if (!is_dir($statedir) && !@mkdir($statedir, 0777, true) && !is_dir($statedir)) {
        a4_abort("cannot create $statedir");
    }
    $lockfh = fopen($statedir . '/sweep.lock', 'c');
    if (!$lockfh || !flock($lockfh, LOCK_EX | LOCK_NB)) {
        a4_abort('another a4_sweep.php --execute is running (sweep.lock)');
    }
}
$journal = $execute ? new a4_jsonl($journalpath) : null;

// ---------------------------------------------------------------------------------------------------------------
// Target set: status-10 A4 requests whose subject row exists with deleted = 1.
// ---------------------------------------------------------------------------------------------------------------
$rows = $DB->get_records_sql(
    "SELECT r.id, r.userid, r.status, u.id AS uid, u.deleted AS udeleted
       FROM {tool_dataprivacy_request} r
  LEFT JOIN {user} u ON u.id = r.userid
      WHERE r.type = :type AND r.creationmethod = :cm AND r.status = :st
   ORDER BY r.id",
    ['type' => api::DATAREQUEST_TYPE_DELETE, 'cm' => data_request::DATAREQUEST_CREATION_AUTO,
     'st' => api::DATAREQUEST_STATUS_DELETED]);
$candidates = [];
$refused = [];
foreach ($rows as $r) {
    if ($ids && empty($ids[(int)$r->id])) {
        continue;
    }
    if (empty($r->uid)) {
        $refused[] = ['requestid' => (int)$r->id, 'subjectid' => (int)$r->userid, 'outcome' => 'refused_no_user_row'];
        continue;
    }
    if (empty($r->udeleted) || is_primary_admin((int)$r->uid)) {
        $refused[] = ['requestid' => (int)$r->id, 'subjectid' => (int)$r->userid, 'outcome' => 'refused_live_subject'];
        continue;
    }
    $candidates[(int)$r->id] = ['requestid' => (int)$r->id, 'subjectid' => (int)$r->userid];
}
if ($ids) {
    foreach (array_diff(array_keys($ids), array_keys($candidates), array_column($refused, 'requestid')) as $nid) {
        $refused[] = ['requestid' => (int)$nid, 'outcome' => 'refused_not_in_target_set'];
    }
}
if ($limit > 0) {
    $candidates = array_slice($candidates, 0, $limit, true);
}

$report = new a4_jsonl($reportpath);
$startrec = [
    'event' => 'start', 'mode' => $mode, 'options' => $options, 'php' => $phpfacts, 'env' => $envfacts,
    'readonly' => $rofacts, 'killswitches' => $killfacts, 'events' => $eventfacts, 'registry' => $registry,
    'flags_global' => $flags, 'stock_approval_truncates' => $truncation, 'manifest' => $manifestfacts,
    'web' => $webfacts, 'backup' => $backupfacts, 'cron_user' => (int)$USER->id, 'dpo_count' => $dpocount,
    'allow_insert' => array_keys($allowinsert), 'snapshot' => $base, 'requests' => $distbefore,
    'candidates' => count($candidates), 'refused' => count($refused),
];
$report->write($startrec);
if ($journal) {
    $journal->write(['event' => 'run_start', 'report' => $reportpath, 'backup' => $backupfacts]);
}
foreach ($refused as $rf) {
    $report->write(['event' => 'subject', 'mode' => $mode] + $rf);
}
a4_out(sprintf('A4 SWEEP %s: %d candidate subject(s), %d refused. Flags (global): truncation %s, completion %s; '
    . 'stock_approval_truncates %s; registry defaults_set %s; manifest %s. Report: %s', strtoupper($mode),
    count($candidates), count($refused), var_export($flags['truncation'], true), var_export($flags['completion'], true),
    var_export($truncation, true), var_export($registry['defaults_set'], true), $manifestfacts['status'], $reportpath));

/**
 * The supplementary list exactly as local_sentientia_privacy's provider builds it with a flag ON: ONE add_from_sql over
 * SYSCONTEXTID plus the plan's supplementary ids (classes/privacy/provider.php get_contexts_for_userid()).
 */
function a4s_supplementary_list(erasure_plan $plan): ?contextlist {
    global $DB;
    $supp = $plan->supplementary_contextids();
    if (!$supp) {
        return null;
    }
    $ids = erasure_completeness::dense(array_merge([SYSCONTEXTID], $supp));
    [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'sctx');
    $cl = new contextlist();
    $cl->add_from_sql("SELECT id FROM {context} WHERE id $insql", $params);
    $cl->set_component(A4S_SUPP);
    return $cl;
}

/** Hit sets apply_delete() uses with a cached plan: missed_C ∩ approved per component, completion gap ∩ approved. */
function a4s_hits(erasure_plan $plan, array $approved, bool $truncation): array {
    $hits = [];
    if ($truncation) {
        foreach ($plan->components as $c => $entry) {
            $h = erasure_completeness::dense(array_intersect($entry['missed'], $approved));
            if ($h) {
                $hits[$c] = $h;
            }
        }
    }
    return [$hits, erasure_completeness::dense(array_intersect($plan->completiongap, $approved))];
}

/**
 * Plan lists against the stock discovery's lists (they come from the same discovery code, run twice). Both the full
 * lists AND the iterated split are compared: the stock pass deletes the stock-iterated ids and the supplement the
 * plan's missed ids, so if the two runs split one list differently (logstore discovery has no ORDER BY) a context
 * could fall in neither set. Any mismatch is a tripwire before the stock delete under --execute.
 */
function a4s_plan_vs_discovery(erasure_plan $plan, array $cldisc): array {
    $mismatch = [];
    if (!$plan->truncation) {
        // Core fixed (no truncation part): the plan holds no component lists to compare.
        return $mismatch;
    }
    foreach ($cldisc as $c => $d) {
        if ($c === A4S_SUPP) {
            continue;
        }
        $p = $plan->components[$c]['full'] ?? [];
        $s = $d['full'];
        sort($p);
        sort($s);
        if ($p !== $s) {
            $mismatch[$c] = ['plan' => count($p), 'stock' => count($s)];
            continue;
        }
        $pi = $plan->components[$c]['iterated'] ?? [];
        $si = $d['iterated'];
        sort($pi);
        sort($si);
        if ($pi !== $si) {
            $mismatch[$c] = ['plan_iterated' => count($pi), 'stock_iterated' => count($si)];
        }
    }
    foreach ($plan->components as $c => $entry) {
        if (!isset($cldisc[$c]) && $entry['full']) {
            $mismatch[$c] = ['plan' => count($entry['full']), 'stock' => 0];
        }
    }
    return $mismatch;
}

/** Subject row counts per blast spec ('subj' of a4_blast_counts()). */
function a4s_subj(array $blast): array {
    $o = [];
    foreach ($blast as $k => $v) {
        if (isset($v['subj'])) {
            $o[$k] = $v['subj'];
        }
    }
    return $o;
}

// ---------------------------------------------------------------------------------------------------------------
// Main loop.
// ---------------------------------------------------------------------------------------------------------------
$obs = new a4_recording_observer();
$prev = $base;
$stopcode = 0;
$t0run = microtime(true);
$sum = ['subjects' => 0, 'by_outcome' => [], 'plan_secs' => [], 'subject_secs' => [], 'plan_vs_discovery_mismatch' => 0,
    'stock_contexts' => 0, 'supp_listed' => 0, 'supp_approved' => 0, 'supp_hits_by_component' => [],
    'supp_hit_subjects_by_component' => [], 'completion_hits' => 0, 'completion_hit_subjects' => 0,
    'failures_by_component' => [], 'deleted_rows_stock' => [], 'deleted_rows_supplement' => [], 'events_total' => 0,
    'supplement_runs' => 0, 'other_output_lines' => 0];
$proj = [];   // uid => ['stock' => comp => ids, 'supp' => comp => ids, 'gap' => ids] for the dry-run projection
$plists = []; // uid => lists for a4_must_be_empty_buckets (this run's discovery + approvals)
$n = 0;
foreach ($candidates as $c) {
    $n++;
    $requestid = $c['requestid'];
    $userid = $c['subjectid'];
    $t0 = microtime(true);
    $obs->requestid = $requestid;
    $obs->failures = [];
    a4_event_recorder::$events = [];
    $rec = ['event' => 'subject', 'mode' => $mode, 'requestid' => $requestid, 'subjectid' => $userid, 'outcome' => null];
    $tripped = null;
    $softtrips = [];
    $txn = null;
    $GLOBALS['A4_MTRACE_BUF'] = '';
    ob_start();
    try {
        $dr = new data_request($requestid);
        if ((int)$dr->get('type') !== api::DATAREQUEST_TYPE_DELETE
                || (int)$dr->get('creationmethod') !== data_request::DATAREQUEST_CREATION_AUTO
                || (int)$dr->get('status') !== api::DATAREQUEST_STATUS_DELETED || (int)$dr->get('userid') !== $userid) {
            $rec['outcome'] = 'refused_changed_since_selection';
            throw new a4_skip();
        }
        $foruser = core_user::get_user($userid);
        if (!$foruser || empty($foruser->deleted)) {
            $rec['outcome'] = 'refused_live_or_missing_subject';
            throw new a4_skip();
        }
        if ($execute) {
            $journal->write(['event' => 'begin', 'requestid' => $requestid, 'subjectid' => $userid]);
            $txn = $DB->start_delegated_transaction();
        }

        // ---- Stock discovery (task :93), then the plan, both before anything is deleted.
        $obs->phase = 'discovery';
        $manager = new \core_privacy\manager();
        $manager->set_observer($obs);
        $collection = $manager->get_contexts_for_userid($userid);
        $cldisc = a4_contextlist_detail($collection);
        $rec['discovery'] = a4_collection_summary($collection);
        $tp = microtime(true);
        $plan = erasure_completeness::plan($userid, $truncation, true);
        $rec['plan_secs'] = round(microtime(true) - $tp, 3);
        $rec['plan'] = ['missed' => $plan->missed_by_component(), 'completion_gap' => $plan->completiongap,
            'failures' => $plan->failures];
        $rec['plan_vs_discovery'] = a4s_plan_vs_discovery($plan, $cldisc);
        if ($plan->failures) {
            throw new a4_provider_failure(array_map(fn($f) => ['phase' => 'plan'] + $f, $plan->failures));
        }
        if ($execute && $rec['plan_vs_discovery']) {
            // Dry run: reported (plan_vs_discovery_mismatch in the summary). Execute: never delete on a split the
            // stock pass and the supplement disagree about.
            throw new a4_tripwire_exception(['plan and stock discovery disagree (full list or iterated split) for: '
                . implode(', ', array_keys($rec['plan_vs_discovery']))]);
        }

        // ---- Stock approval (task :143) and the supplementary list through the same approval.
        $approved = api::get_approved_contextlist_collection_for_collection($collection, $foruser,
            api::DATAREQUEST_TYPE_DELETE);
        $rec['approved'] = a4_collection_summary($approved, true);
        $rec['approved_ids'] = $rec['approved']['ids'];
        unset($rec['approved']['ids']);
        $rec = array_merge($rec, a4_truncation_fields($cldisc, a4_contextlist_detail($approved)));
        $supplist = a4s_supplementary_list($plan);
        $suppapproved = [];
        if ($supplist !== null) {
            $suppcoll = new contextlist_collection($userid);
            $suppcoll->add_contextlist($supplist);
            $suppappr = api::get_approved_contextlist_collection_for_collection($suppcoll, $foruser,
                api::DATAREQUEST_TYPE_DELETE);
            foreach ($suppappr->get_contextlists() as $l) {
                $suppapproved = array_merge($suppapproved, $l->get_contextids());
            }
            $suppapproved = erasure_completeness::dense($suppapproved);
            $rec['supp_listed'] = count($supplist);
        }
        $rec['supp_approved_ids'] = $suppapproved;
        [$hits, $hitcc] = a4s_hits($plan, $suppapproved, $truncation);
        $rec['supp_hits'] = array_map('count', $hits);
        $rec['completion_hits'] = count($hitcc);
        $unknown = array_values(array_diff(array_keys($rec['approved_ids']), array_keys($manifestcomponents)));
        if ($unknown) {
            throw new a4_tripwire_exception(['approved component(s) not in the sweep manifest: ' . implode(', ', $unknown)]);
        }
        $apprall = $rec['approved_ids'];
        if ($suppapproved) {
            $apprall[A4S_SUPP] = $suppapproved;
        }
        $plists[$userid] = ['pre_guard' => false, 'supp_active' => true,
            'disc_full' => array_map(fn($x) => $x['full'], $cldisc),
            'disc_iter' => array_map(fn($x) => $x['iterated'], $cldisc), 'appr_full' => $apprall, 'appr_iter' => $apprall];
        // The shared cleanup the manager would run on local_sentientia_privacy's own approved list (below) counts
        // for the module-context cmc/cmv specs: a4_cover_class(..., $anylist) looks in every 'supp' list.
        $proj[$userid] = ['stock' => $rec['approved_ids'],
            'supp' => $hits + ($suppapproved ? [A4S_SUPP => $suppapproved] : []), 'gap' => $hitcc];

        if (!$execute) {
            $rec['outcome'] = ($hits || $hitcc) ? 'would_delete_stock_and_supplement' : 'would_delete_stock_only';
        } else {
            // ---- Stock delete (task :148-151).
            $bscope = a4_blast_scope($userid, $apprall, array_merge(array_keys($hits), $hitcc ? ['core_course'] : []));
            $blastbefore = a4_blast_counts([$userid], $bscope);
            $manager = new \core_privacy\manager();
            $manager->set_observer($obs);
            $obs->phase = 'delete';
            $manager->delete_data_for_user($approved);
            if ($obs->failures) {
                throw new a4_provider_failure($obs->failures);
            }
            $blastmid = a4_blast_counts([$userid], $bscope);

            // ---- Supplement, with the plan taken before the stock delete (re-stamped: the stock delete may outlast
            // the plan TTL, and the provider's delete_data_for_user() above forgot any remembered plan).
            if ($suppapproved) {
                $fresh = new erasure_plan($plan->userid, $plan->truncation, $plan->completion,
                    \core\di::get(\core\clock::class)->time());
                $fresh->components = $plan->components;
                $fresh->completiongap = $plan->completiongap;
                erasure_completeness::remember($fresh);
                $obs->phase = 'supplement';
                $result = erasure_completeness::apply_delete($foruser, $suppapproved, erasure_completeness::PATH_CLI,
                    $truncation, true, $requestid);
                $sum['supplement_runs']++;
                $rec['supplement'] = $result;
                if ($result['plan'] !== 'cached') {
                    throw new a4_tripwire_exception(['supplement recomputed its plan after the stock delete (expected '
                        . 'the pre-delete plan)']);
                }
                // The shared completion cleanup the manager would run on local_sentientia_privacy's approved list
                // (manager.php:418-419: cmc/cmv of each MODULE context in it, also those missed only for a
                // non-module component such as tool_log) is run by apply_delete() itself on PATH_CLI since
                // 2026-11-17; a failure there comes back as phase delete_shared for local_sentientia_privacy.
                if ($result['failures']) {
                    throw new a4_provider_failure(array_map(fn($f) => ['phase' => 'supplement'] + $f,
                        $result['failures']));
                }
            } else {
                erasure_completeness::forget($userid);
                $rec['supplement'] = 'nothing to do (no supplementary context approved)';
            }
            $blastafter = a4_blast_counts([$userid], $bscope);
            $sb = a4s_subj($blastbefore);
            $sm = a4s_subj($blastmid);
            $sa = a4s_subj($blastafter);
            $rec['deleted_rows'] = ['stock' => [], 'supplement' => []];
            foreach ($sb as $k => $v) {
                if (($d = $v - ($sm[$k] ?? $v)) !== 0) {
                    $rec['deleted_rows']['stock'][$k] = $d;
                }
                if (($d = ($sm[$k] ?? $v) - ($sa[$k] ?? $v)) !== 0) {
                    $rec['deleted_rows']['supplement'][$k] = $d;
                }
            }
            $pre = a4_blast_compare($blastbefore, $blastafter);
            // Events: at most one, and only erasure_supplemented (fired by apply_delete()).
            $evtrip = a4_unexpected_events(a4_event_recorder::$events, (bool)$suppapproved);
            if ($evtrip) {
                $pre[] = 'events fired: ' . implode(', ', array_unique($evtrip));
            }
            if (!a4_event_recorder::intact()) {
                $pre[] = 'event observer table was reloaded (real observers could run)';
            }
            $lines = a4_send_attempt_lines((string)ob_get_contents() . $GLOBALS['A4_MTRACE_BUF']);
            if ($lines) {
                $pre[] = 'send attempt output: ' . $lines[0];
            }
            $inside = a4_snapshot($logbaseline, true);
            $pre = array_merge($pre, a4_tripwire_compare($base, $prev, $inside, $allowinsert));
            if ($pre) {
                $rec['new_rows'] = a4_new_row_ids($base['maxids'], $inside['maxids']);
                throw new a4_tripwire_exception($pre);
            }
            $txn->allow_commit();
            $txn = null;
            $rec['outcome'] = 'swept';
            $journal->write(['event' => 'final', 'requestid' => $requestid, 'outcome' => 'swept']);
        }
    } catch (a4_skip $e) {
        $rec['outcome'] = $rec['outcome'] ?? 'skipped';
    } catch (a4_provider_failure $e) {
        $rec['outcome'] = $execute ? 'rolled_back_failure' : 'plan_failure';
        $rec['failures'] = $e->failures;
    } catch (a4_tripwire_exception $e) {
        $rec['outcome'] = $execute ? 'tripwire_rolled_back' : 'tripwire';
        $rec['tripwire'] = $e->trips;
        $tripped = $e->trips;
    } catch (\Throwable $e) {
        $rec['outcome'] = 'failed_unhandled';
        $rec['error'] = ['class' => get_class($e), 'message' => a4_trunc($e->getMessage(), 300),
            'at' => a4_relpath($e->getFile()) . ':' . $e->getLine()];
    } finally {
        $captured = (string)ob_get_clean() . $GLOBALS['A4_MTRACE_BUF'];
        $GLOBALS['A4_MTRACE_BUF'] = '';
    }
    if ($DB->is_transaction_started()) {
        $DB->force_transaction_rollback();
        $rec['rolled_back'] = true;
        if ($journal) {
            $journal->write(['event' => 'rolled_back', 'requestid' => $requestid, 'outcome' => $rec['outcome']]);
        }
    }
    [, $other] = a4_split_output($captured);
    $rec['other_output_lines'] = count($other);
    $sum['other_output_lines'] += count($other);
    $sendlines = a4_send_attempt_lines($captured);
    if ($sendlines) {
        $softtrips[] = 'send attempt output (' . count($sendlines) . ' line(s)): ' . $sendlines[0];
    }
    $failures = $obs->take();
    if ($failures) {
        $rec['provider_failures'] = $failures;
    }
    $events = a4_event_recorder::take();
    if ($events) {
        $rec['events'] = array_count_values(array_column($events, 'eventname'));
        $sum['events_total'] += count($events);
        // Dry run: no event may fire at all. Execute: the committed subject was checked inside the transaction; a
        // rolled-back subject's events never reached a real observer (the recorder is the only one).
        if (!$execute) {
            $softtrips[] = 'events fired: ' . implode(', ', array_keys($rec['events']));
        }
    }
    if (!a4_event_recorder::intact()) {
        $softtrips[] = 'event observer table was reloaded (real observers could run)';
    }
    $rec['seconds'] = round(microtime(true) - $t0, 2);
    if (!$tripped) {
        $now = a4_snapshot($logbaseline, $execute || ($n % 25 === 0));
        $trips = array_merge($softtrips, a4_tripwire_compare($base, $prev, $now, $allowinsert));
        if ($trips) {
            $tripped = $trips;
            $rec['tripwire'] = $trips;
        }
        $prev = $now;
    }
    $report->write($rec);

    $sum['subjects']++;
    $sum['by_outcome'][$rec['outcome']] = ($sum['by_outcome'][$rec['outcome']] ?? 0) + 1;
    $sum['subject_secs'][] = $rec['seconds'];
    if (isset($rec['plan_secs'])) {
        $sum['plan_secs'][] = $rec['plan_secs'];
    }
    if (!empty($rec['plan_vs_discovery'])) {
        $sum['plan_vs_discovery_mismatch']++;
    }
    $sum['stock_contexts'] += $rec['approved']['contexts_total'] ?? 0;
    $sum['supp_listed'] += $rec['supp_listed'] ?? 0;
    $sum['supp_approved'] += count($rec['supp_approved_ids'] ?? []);
    foreach ($rec['supp_hits'] ?? [] as $comp => $cnt) {
        $sum['supp_hits_by_component'][$comp] = ($sum['supp_hits_by_component'][$comp] ?? 0) + $cnt;
        $sum['supp_hit_subjects_by_component'][$comp] = ($sum['supp_hit_subjects_by_component'][$comp] ?? 0) + 1;
    }
    $sum['completion_hits'] += $rec['completion_hits'] ?? 0;
    if (!empty($rec['completion_hits'])) {
        $sum['completion_hit_subjects']++;
    }
    foreach (array_merge($failures, $rec['failures'] ?? []) as $f) {
        $k = ($f['phase'] ?? '?') . ':' . ($f['component'] ?? '?');
        $sum['failures_by_component'][$k] = ($sum['failures_by_component'][$k] ?? 0) + 1;
    }
    foreach (['stock', 'supplement'] as $src) {
        foreach ($rec['deleted_rows'][$src] ?? [] as $k => $d) {
            $sum['deleted_rows_' . $src][$k] = ($sum['deleted_rows_' . $src][$k] ?? 0) + $d;
        }
    }
    a4_out(sprintf('req %5d  subj %6d  %-34s  stock ctx %4s  supp appr %4s  hits %s  cc %3s  %6.1fs%s', $requestid,
        $userid, $rec['outcome'], $rec['approved']['contexts_total'] ?? '-', count($rec['supp_approved_ids'] ?? []),
        json_encode($rec['supp_hits'] ?? []), $rec['completion_hits'] ?? '-', $rec['seconds'], $tripped ? '  TRIPWIRE' : ''));
    if ($tripped) {
        a4_out('TRIPWIRE: ' . implode('; ', $tripped) . ' -- stopping.');
        $stopcode = 3;
        break;
    }
    if ($stoponerror && in_array($rec['outcome'], ['rolled_back_failure', 'plan_failure', 'failed_unhandled'], true)) {
        a4_out('Stopping on error (--stop-on-error).');
        $stopcode = 4;
        break;
    }
}

// ---------------------------------------------------------------------------------------------------------------
// Projection (dry run): which leftover rows each pass would delete. Execute: the same over what is left now.
// ---------------------------------------------------------------------------------------------------------------
$projection = [];
$projcomp = [];
$onrow = function(string $key, string $t, string $comp, int $uid, ?int $ctx, bool $ctxgone, string $bucket, int $nrows)
        use (&$projection, &$projcomp, $proj): void {
    $p = $proj[$uid] ?? null;
    if ($p === null) {
        return;
    }
    $class = a4_cover_class($p['stock'], $p['supp'], $p['gap'], $comp, $ctx, $ctxgone,
        a4_is_module_shared_spec($t, $comp));
    $projection[$key][$class] = ($projection[$key][$class] ?? 0) + $nrows;
    $projcomp[$comp][$class] = ($projcomp[$comp][$class] ?? 0) + $nrows;
};
$t0p = microtime(true);
$projbuckets = $plists ? a4_must_be_empty_buckets($plists, [], [], $onrow) : [];
$projsecs = round(microtime(true) - $t0p, 1);
ksort($projcomp);

$end = a4_snapshot($logbaseline, true);
$endtrips = a4_tripwire_compare($base, $base, $end, $allowinsert);
$distafter = a4_request_status_distribution();
if ($distafter !== $distbefore) {
    $endtrips[] = 'tool_dataprivacy_request distribution changed (the sweep writes no request rows)';
}
$stray = a4_event_recorder::take();
if ($stray) {
    $endtrips[] = 'events fired outside a subject: ' . count($stray);
}
$roend = $execute ? null : a4_readonly_state();
// Execute: a row still in a context this run's stock or supplementary pass was asked to clear is a failed delete.
$leftover = 0;
if ($execute) {
    foreach ($projcomp as $classes) {
        $leftover += ($classes['stock'] ?? 0) + ($classes['supplement'] ?? 0);
    }
}
arsort($sum['supp_hits_by_component']);
$summary = [
    'event' => 'summary', 'mode' => $mode, 'elapsed_seconds' => round(microtime(true) - $t0run, 1), 'stopped' => $stopcode,
    'refused' => count($refused), 'tx_read_only_end' => $roend, 'snapshot_delta' => a4_snapshot_delta($base, $end),
    'tripwire_trips_end_vs_start' => $endtrips, 'requests_start' => $distbefore, 'requests_end' => $distafter,
    'plan_timing' => a4_timing_stats($sum['plan_secs']), 'subject_timing' => a4_timing_stats($sum['subject_secs']),
    'projection_by_spec' => $projection, 'projection_by_component' => $projcomp, 'projection_seconds' => $projsecs,
    'projection_spec_errors' => array_filter(array_map(fn($v) => $v['error'] ?? null, $projbuckets)),
    'leftover_in_cleared_contexts' => $execute ? $leftover : null,
] + $sum;
unset($summary['plan_secs'], $summary['subject_secs']);
$report->write($summary);

a4_out('');
a4_out('==== A4 SWEEP SUMMARY (' . $mode . ') ====');
a4_out('subjects: ' . $sum['subjects'] . '; refused ' . count($refused) . '; by outcome ' . json_encode($sum['by_outcome'])
    . '; wall ' . $summary['elapsed_seconds'] . ' s');
a4_out('timing per subject (s): ' . json_encode($summary['subject_timing']) . '; plan only: '
    . json_encode($summary['plan_timing']));
a4_out('plan vs stock discovery (full lists and iterated split): ' . $sum['plan_vs_discovery_mismatch']
    . ' subject(s) differ');
a4_out('stock approved component-context pairs: ' . $sum['stock_contexts'] . '; supplementary listed ' . $sum['supp_listed']
    . ', approved ' . $sum['supp_approved'] . '; supplementary hits by component (pairs) '
    . json_encode($sum['supp_hits_by_component']) . ' (subjects ' . json_encode($sum['supp_hit_subjects_by_component'])
    . '); completion-gap course contexts ' . $sum['completion_hits'] . ' (' . $sum['completion_hit_subjects']
    . ' subjects)');
a4_out('failures: ' . json_encode($sum['failures_by_component']) . '; events ' . $sum['events_total']
    . '; supplement runs ' . $sum['supplement_runs'] . '; other output lines ' . $sum['other_output_lines']);
if ($execute) {
    a4_out('rows deleted by the stock pass: ' . json_encode($sum['deleted_rows_stock']));
    a4_out('rows deleted by the supplement: ' . json_encode($sum['deleted_rows_supplement']));
}
a4_out(($execute ? 'leftover rows now' : 'projection of the leftover rows') . ' by component (stock / supplement / '
    . 'uncovered / vanished), ' . $projsecs . ' s: ' . json_encode($projcomp));
if ($execute) {
    a4_out('LEFTOVER rows in contexts this run cleared (violation when > 0): ' . $leftover);
}
a4_out('tripwire end vs start: ' . ($endtrips ? implode('; ', $endtrips) : 'no increase'));
a4_out('requests before: ' . json_encode($distbefore['by_type_cm_status']) . ' after: '
    . json_encode($distafter['by_type_cm_status']));
if (!$execute) {
    a4_out('read-only session at end: tx_read_only=' . $roend);
}
a4_out('report: ' . $reportpath);
if ($lockfh) {
    flock($lockfh, LOCK_UN);
    fclose($lockfh);
}
exit($stopcode ?: ($endtrips ? 3 : ($leftover ? 4 : 0)));
