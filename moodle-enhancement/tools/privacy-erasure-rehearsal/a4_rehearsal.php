<?php
// A4 rehearsal: process the automatic tool_dataprivacy deletion requests on the LOCAL XAMPP copy with zero sends.
//
// DRY RUN BY DEFAULT. Nothing is written unless --execute is given. See README.md in this folder.
//
// What it does per request (execute mode, subject marked deleted):
//   approval    = admin/tool/dataprivacy/classes/api.php approve_data_request() :646-664, WITHOUT :683
//                 (queue_data_request_task): status 2 -> 3, dpo = cron admin.
//   processing  = admin/tool/dataprivacy/classes/task/process_data_request_task.php execute() lines 66-158, inline,
//                 same order, same status writes (3 -> 4 -> 10), same \core_privacy\manager calls, but with a
//                 recording observer instead of \tool_dataprivacy\manager_observer (which message_send()s the DPOs).
//   message tail = lines 160-298 run as written (message built, template rendered, every condition evaluated with the
//                 real api calls) with the four send calls and delete_user() replaced by recorders (a4_message_tail).
//   not run     = the task queue and cron.
// --txn=request (default): approval, processing, deletion and status 10 of one request commit or roll back together.
// Subjects with no user row: the real task cannot process them (TypeError at :143 -> api.php:1203). This script
// reproduces that read-only, reports it, and writes nothing for them.
// Every request line also records, per component of the discovery and approved collections, count($contextlist), the
// full list (get_contextids()), the iterated list, and iterator_truncated (full minus iterated: stock core bug,
// contextlist_base.php:66 + :170; recorded, never a trip). The approval is NOT changed: it iterates, as stock does.
// README "Residue buckets".
//
// Every run needs the network/mail guard flags on the php command line (a4_lib.php A4_PHP_FLAGS):
//   php -d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail,
//       mb_send_mail,imap_mail,proc_open,popen,exec,shell_exec,system,passthru -d allow_url_fopen=0 -d SMTP=127.0.0.1
//       -d smtp_port=9 "D:/Claude Local/kit-scratch/1113/a4/rehearsal/a4_rehearsal.php" [options]
// Run from cwd C:/xampp/htdocs/moodle5/public.
//
// Options:
//   --execute            Make changes. Without it the run is a dry run: the DB session is READ ONLY.
//   --counts-only        Dry-run sub-mode: print the tripwire counts, all-table counts and max ids, state checksums
//                        and request statuses, then exit.
//   --limit=N            Process at most N requests (after filtering).
//   --ids=1,2,3          Only these request ids (must be in the target set, else refused and reported).
//   --only=all|deleted|missing   Subject state filter (default all).
//   --stop-on-error      Stop after the first request with a provider failure or an unhandled exception.
//   --report=PATH        Absolute path of the JSON-lines report (default: reports/a4-<mode>-<timestamp>.jsonl here).
//   --trace=PATH         Absolute path: save captured provider output other than the privacy manager's progress lines.
//   --residuals          Record residual-data counts and the blast-radius baseline for all A4 subjects at the start
//                        (and the residual counts at the end in execute).
//   --write-manifest     Dry run only: (re)write state/provider-manifest.json from the code as it is now. Without it a
//                        dry run writes the manifest only when none exists, and otherwise reports differences.
//   --txn=request|none   Execute only. request (default): each request runs in one delegated transaction (approval to
//                        status 10) with pre-commit tripwires; any provider failure or tripwire rolls the whole
//                        request back to status 2. none: exactly the cron behaviour (each write commits; a provider
//                        failure still ends at status 10; an open transaction after the request is rolled back).
//   --resume             Execute only. Also take requests at status 3/4 that this script's journal shows it moved
//                        (only --txn=none can leave a request there).
//   --backup=PATH        Execute only, required: absolute path of the complete mysqldump taken just before
//                        (checked: header, footer, key tables, no phpu_, > 1 GB, < 48 h; sha256 recorded).
//   --web=maintenance    Execute only, required: admin/cli/maintenance.php --enable was run (checked at start and
//                        after every request: climaintenance.html in dataroot). Every web request then dies in
//                        lib/setup.php before any DB work. 'stopped' and 'open' were removed 2026-11-13 (final
//                        check): the guards live only in this process, so a web request would run the real observers
//                        and message_send(), and the XAMPP watchdog restarts a stopped Apache.
//   --allow-insert=t1,t2 Execute only: tables whose max id may grow (default none: this path inserts nothing).
//   --all                Execute only: required when neither --limit nor --ids is given.
//   -h, --help
//
// Exit codes: 0 ok, 1 usage, 2 guard/abort, 3 tripwire, 4 stopped on error.

define('CLI_SCRIPT', true);

require('C:/xampp/htdocs/moodle5/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . "/{$CFG->admin}/tool/dataprivacy/lib.php");   // As process_data_request_task.php:59.
require_once(__DIR__ . '/a4_lib.php');

use tool_dataprivacy\api;
use tool_dataprivacy\data_request;

$phpfacts = a4_require_hardened_php();

[$options, $unrecognised] = cli_get_params([
    'help' => false,
    'execute' => false,
    'counts-only' => false,
    'limit' => 0,
    'ids' => '',
    'only' => 'all',
    'stop-on-error' => false,
    'report' => '',
    'txn' => 'request',
    'resume' => false,
    'backup' => '',
    'all' => false,
    'residuals' => false,
    'trace' => '',
    'web' => '',
    'allow-insert' => '',
    'write-manifest' => false,
], ['h' => 'help']);

if ($unrecognised) {
    cli_error('Unknown option(s): ' . implode(' ', $unrecognised), 1);
}
if ($options['help']) {
    $lines = file(__FILE__);
    foreach ($lines as $i => $l) {
        if ($i > 0 && strpos($l, '//') === 0) {
            echo substr($l, 3);
        } else if ($i > 0) {
            break;
        }
    }
    exit(0);
}
// A value option given bare (--ids) arrives as true (lib/clilib.php:105-107) and would become "1": refuse it.
foreach (['ids', 'limit', 'report', 'trace', 'backup', 'only', 'txn', 'web', 'allow-insert'] as $k) {
    if ($options[$k] === true) {
        cli_error("--$k needs a value (--$k=...)", 1);
    }
}

$execute = !empty($options['execute']);
$countsonly = !empty($options['counts-only']);
$only = (string)$options['only'];
$txnmode = (string)$options['txn'];
$stoponerror = !empty($options['stop-on-error']);
$resume = !empty($options['resume']);
$withresiduals = !empty($options['residuals']);
$writemanifest = !empty($options['write-manifest']);
$web = (string)$options['web'];
if (!preg_match('/^\d+$/', (string)$options['limit'])) {
    cli_error('--limit must be a whole number', 1);
}
$limit = (int)$options['limit'];
$ids = [];
if ($options['ids'] !== '' && $options['ids'] !== false) {
    foreach (explode(',', (string)$options['ids']) as $v) {
        $v = trim($v);
        if (!preg_match('/^[1-9][0-9]*$/', $v)) {
            cli_error("--ids: '$v' is not a request id", 1);
        }
        $ids[(int)$v] = true;
    }
}
$allowinsert = [];
if ($options['allow-insert'] !== '' && $options['allow-insert'] !== false) {
    foreach (explode(',', (string)$options['allow-insert']) as $t) {
        $t = trim($t);
        if (!preg_match('/^[a-z0-9_]+$/', $t)) {
            cli_error("--allow-insert: '$t' is not a table name (without prefix)", 1);
        }
        $allowinsert[$t] = true;
    }
}
foreach (['report', 'trace'] as $k) {
    if ($options[$k] !== '' && $options[$k] !== false && !a4_is_absolute((string)$options[$k])) {
        cli_error("--$k must be an absolute path", 1);
    }
}
if ($execute && $countsonly) {
    cli_error('--counts-only is a dry-run mode; it cannot be combined with --execute', 1);
}
if (!in_array($only, ['all', 'deleted', 'missing'], true)) {
    cli_error('--only must be all, deleted or missing', 1);
}
if (!in_array($txnmode, ['request', 'none'], true)) {
    cli_error('--txn must be request or none', 1);
}
if (!$execute && ($resume || $options['backup'] !== '' || $options['all'] || $web !== '' || $allowinsert)) {
    cli_error('--resume, --backup, --web, --allow-insert and --all only apply with --execute', 1);
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
        cli_error('--execute needs --web=maintenance (run admin/cli/maintenance.php --enable first)', 1);
    }
}

$mode = $execute ? 'execute' : ($countsonly ? 'counts' : 'dryrun');
$stamp = date('Ymd-His');
$reportpath = ($options['report'] !== '' && $options['report'] !== false)
    ? (string)$options['report'] : __DIR__ . "/reports/a4-$mode-$stamp.jsonl";
$journalpath = __DIR__ . '/state/journal.jsonl';
$manifestpath = __DIR__ . '/state/provider-manifest.json';

// ---------------------------------------------------------------------------------------------------------------
// Guards.
// ---------------------------------------------------------------------------------------------------------------
$envfacts = a4_guard_environment();
$rofacts = null;
if (!$execute) {
    // Dry run: the whole session is READ ONLY from here on. Any write attempt by any code fails with an exception.
    $rofacts = a4_set_readonly_session(true);
}

\core_php_time_limit::raise();
raise_memory_limit(MEMORY_EXTRA);   // As cron does (lib/classes/cron.php:87).

// Run as the cron user, as cron does for a task without a userid (lib/classes/cron.php, setup_user()).
\core\cron::setup_user();

$killfacts = a4_apply_kill_switches();
$eventfacts = a4_event_recorder::install();
// \core_privacy\manager prints its progress through text_progress_trace -> mtrace() -> fwrite(STDOUT); collect it
// per request instead (lib/moodlelib.php mtrace() honours $CFG->mtrace_wrapper). Logging only.
$CFG->mtrace_wrapper = 'a4_mtrace_capture';
$registry = a4_registry_facts();

$webfacts = null;
$backupfacts = null;
if ($execute) {
    $mfile = $CFG->dataroot . '/climaintenance.html';
    if ($web === 'maintenance' && !is_file($mfile)) {
        a4_abort('--web=maintenance, but climaintenance.html is not in dataroot: run admin/cli/maintenance.php '
            . '--enable first (Nitin\'s call)');
    }
    $webfacts = ['web' => $web, 'climaintenance_file' => is_file($mfile)];
    $GLOBALS['A4_REQUIRE_MAINT'] = $mfile;   // Re-checked after every request by a4_tripwire_compare().
    a4_out('Checking the backup file (one read pass, sha256) ...');
    $backupfacts = a4_check_backup((string)$options['backup']);
}

// Provider manifest: the execute run must run the code the dry run ran.
$manifestfacts = null;
$manifestcomponents = [];
if (!$countsonly) {
    $manifestnow = a4_manifest_build();
    $stored = is_readable($manifestpath) ? json_decode((string)file_get_contents($manifestpath), true) : null;
    $diff = is_array($stored) ? a4_manifest_diff($stored, $manifestnow) : null;
    if ($execute) {
        if (!is_array($stored)) {
            a4_abort('state/provider-manifest.json is missing: run a dry run first');
        }
        if ($diff) {
            foreach (array_slice($diff, 0, 50) as $d) {
                fwrite(STDERR, "  manifest: $d\n");
            }
            a4_abort(count($diff) . ' difference(s) from the provider manifest written by the dry run of '
                . ($stored['generated'] ?? '?') . '. Review them, then re-bless with a dry run --write-manifest.');
        }
        $manifestfacts = ['status' => 'match', 'generated' => $stored['generated'], 'files' => count($stored['files'])];
        $manifestcomponents = $stored['components'];
    } else if (!is_array($stored) || $writemanifest) {
        a4_manifest_write($manifestpath, $manifestnow);
        $manifestfacts = ['status' => 'written', 'generated' => $manifestnow['generated'],
            'files' => count($manifestnow['files']), 'components' => count($manifestnow['components'])];
        $manifestcomponents = $manifestnow['components'];
    } else {
        $manifestfacts = ['status' => $diff ? 'differs' : 'match', 'generated' => $stored['generated'],
            'files' => count($stored['files']), 'differences' => array_slice($diff, 0, 100)];
        $manifestcomponents = $stored['components'];
        if ($diff) {
            a4_out('WARNING: ' . count($diff) . ' difference(s) from state/provider-manifest.json; an --execute run '
                . 'would be refused (first: ' . $diff[0] . ').');
        }
    }
}

$base = a4_snapshot(null, true);
$logbaseline = $base['logstore_maxid'];
$base['log_send_events_since_start'] = 0;
$distbefore = a4_request_status_distribution();
$countsstart = ($execute || $countsonly) ? a4_counts(a4_all_id_tables()) : null;
$restoremarkers = [
    'upgrade_log_maxid' => $base['maxids']['upgrade_log'] ?? null,
    'logstore_maxid' => $base['logstore_maxid'],
    'plugin_versions_crc' => (string)$DB->get_field_sql("SELECT COALESCE(SUM(CRC32(CONCAT_WS('|', plugin, value))), 0)
                                                           FROM {config_plugins} WHERE name = 'version'"),
    'config_plugins_checksum' => $base['checksums']['config_plugins'],
];

if ($base['task_running'] > 0) {
    if ($execute) {
        a4_abort('a task is marked as running (task_adhoc/task_scheduled timestarted set): cron may be active');
    }
    a4_out('WARNING: a task is marked as running; cron may be active (dry run continues, read-only).');
}
if ($execute && $base['lastcronstart'] > time() - 900) {
    a4_abort('cron started less than 15 minutes ago');
}

$report = new a4_jsonl($reportpath);

if ($countsonly) {
    $rec = [
        'event' => 'counts', 'mode' => $mode, 'tx_read_only' => a4_readonly_state(), 'php' => $phpfacts,
        'events' => $eventfacts, 'tripwire_tables' => a4_tripwire_tables(), 'snapshot' => $base,
        'all_table_counts' => $countsstart, 'requests' => $distbefore, 'registry' => $registry,
        'recorded_events' => a4_event_recorder::take(), 'observers_intact' => a4_event_recorder::intact(),
    ];
    $report->write($rec);
    $short = $rec;
    unset($short['snapshot']['maxids'], $short['all_table_counts']);
    $short['all_tables'] = ['tables' => count($countsstart), 'rows' => array_sum($countsstart),
        'maxid_sum' => array_sum($base['maxids'])];
    a4_out(json_encode($short, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    exit(0);
}

// Approval preconditions (api.php approve_data_request :646 and :658), for the cron user.
if (!api::can_manage_data_requests($USER->id)) {
    a4_abort('cron user cannot manage data requests (api::can_manage_data_requests)');
}
if (!api::can_create_data_deletion_request_for_other()) {
    a4_abort('cron user lacks tool/dataprivacy:requestdeleteforotheruser');
}
$dpocount = count(api::get_site_dpos());
$admincount = count(get_admins());

$lockfh = null;
if ($execute) {
    if (!is_dir(__DIR__ . '/state')) {
        mkdir(__DIR__ . '/state', 0777, true);
    }
    $lockfh = fopen(__DIR__ . '/state/a4.lock', 'c');
    if (!$lockfh || !flock($lockfh, LOCK_EX | LOCK_NB)) {
        a4_abort('another a4_rehearsal.php --execute is running (state/a4.lock)');
    }
}
$journal = $execute ? new a4_jsonl($journalpath) : null;

// ---------------------------------------------------------------------------------------------------------------
// Target set: type DELETE, creationmethod AUTO, status AWAITING_APPROVAL (plus, with --resume, status 3/4 rows this
// script moved itself). Nothing else is ever touched.
// ---------------------------------------------------------------------------------------------------------------
$statuses = [api::DATAREQUEST_STATUS_AWAITING_APPROVAL];
$journalids = [];
if ($resume) {
    $journalids = a4_journal_ids($journalpath);
    $statuses[] = api::DATAREQUEST_STATUS_APPROVED;
    $statuses[] = api::DATAREQUEST_STATUS_PROCESSING;
}
[$sin, $sparams] = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED, 'st');
$rows = $DB->get_records_sql(
    "SELECT r.id, r.userid, r.status, u.id AS uid, u.deleted AS udeleted
       FROM {tool_dataprivacy_request} r
  LEFT JOIN {user} u ON u.id = r.userid
      WHERE r.type = :type AND r.creationmethod = :cm AND r.status $sin
   ORDER BY r.id",
    ['type' => api::DATAREQUEST_TYPE_DELETE, 'cm' => data_request::DATAREQUEST_CREATION_AUTO] + $sparams);

$candidates = [];
$refused = [];
$filteredout = 0;
foreach ($rows as $r) {
    $st = (int)$r->status;
    if ($st !== api::DATAREQUEST_STATUS_AWAITING_APPROVAL && empty($journalids[(int)$r->id])) {
        continue;   // Status 3/4 not moved by this script: not ours, never touched.
    }
    if ($ids && empty($ids[(int)$r->id])) {
        continue;
    }
    $state = empty($r->uid) ? 'missing' : (!empty($r->udeleted) ? 'deleted' : 'live');
    if ($state === 'live' || (!empty($r->uid) && is_primary_admin((int)$r->uid))) {
        $refused[] = ['requestid' => (int)$r->id, 'subjectid' => (int)$r->userid, 'subject_state' => $state,
            'status' => $st, 'outcome' => 'refused_live_subject',
            'note' => 'subject user row exists with deleted = 0: the real task would delete_user() it (:153/:297). Not touched.'];
        continue;
    }
    if ($only !== 'all' && $only !== $state) {
        $filteredout++;
        continue;
    }
    $candidates[(int)$r->id] = ['requestid' => (int)$r->id, 'subjectid' => (int)$r->userid, 'state' => $state,
        'status' => $st];
}
if ($ids) {
    $notarget = array_diff(array_keys($ids), array_keys($candidates), array_column($refused, 'requestid'));
    if ($notarget) {
        [$nin, $np] = $DB->get_in_or_equal(array_values($notarget), SQL_PARAMS_NAMED, 'nt');
        $found = $DB->get_records_select('tool_dataprivacy_request', "id $nin", $np, 'id',
            'id, type, status, creationmethod');
        foreach ($notarget as $nid) {
            $f = $found[$nid] ?? null;
            if ($f === null || $only === 'all' || !in_array((int)$f->status, $statuses, true)) {
                $refused[] = ['requestid' => (int)$nid, 'outcome' => 'refused_not_in_target_set',
                    'found' => (bool)$f, 'type' => $f ? (int)$f->type : null, 'status' => $f ? (int)$f->status : null,
                    'creationmethod' => $f ? (int)$f->creationmethod : null];
            }
        }
    }
}
if ($limit > 0) {
    $candidates = array_slice($candidates, 0, $limit, true);
}

$a4subjects = a4_all_a4_subject_ids();
$residualstart = $withresiduals ? a4_residual_counts($a4subjects) : null;
// Run baseline for a4_verify's "other people's rows unchanged" check (whole tables: slow, so execute or --residuals).
$t0b = microtime(true);
$blastbaseline = ($execute || $withresiduals) ? a4_blast_baseline($a4subjects) : null;
$blastbaselinesecs = round(microtime(true) - $t0b, 1);
$certissueids = a4_cert_issue_ids($a4subjects);
$certissueowners = a4_cert_issue_owners($a4subjects);   // issue id => owner, for a4_verify's files bucketing.
// The same checks a4_verify runs after an execute, over all A4 subjects before anything is deleted (baseline; also
// proves the SQL). Slow on the large tables, so execute or --residuals only.
$mbebaseline = ($execute || $withresiduals)
    ? a4_must_be_empty_counts(function(string $c) use ($a4subjects): array {
        return $a4subjects;
    }, $certissueids) : null;

$startrec = [
    'event' => 'start', 'mode' => $mode, 'txn' => $execute ? $txnmode : null, 'options' => $options,
    'php' => $phpfacts, 'env' => $envfacts, 'readonly' => $rofacts, 'killswitches' => $killfacts,
    'events' => $eventfacts, 'registry' => $registry, 'manifest' => $manifestfacts, 'web' => $webfacts,
    'backup' => $backupfacts, 'cron_user' => (int)$USER->id, 'dpo_count' => $dpocount, 'admin_count' => $admincount,
    'memory_limit' => ini_get('memory_limit'), 'allow_insert' => array_keys($allowinsert),
    'tripwire_tables' => a4_tripwire_tables(), 'all_id_tables' => count(a4_all_id_tables()), 'snapshot' => $base,
    'all_table_counts' => $countsstart, 'restore_markers' => $restoremarkers, 'requests' => $distbefore,
    'candidates' => count($candidates), 'refused' => count($refused), 'filtered_out' => $filteredout,
    'a4_subjects' => count($a4subjects), 'cert_issue_ids' => $certissueids, 'cert_issue_owners' => $certissueowners,
    'residuals' => $residualstart,
    'blast_baseline' => $blastbaseline, 'blast_baseline_seconds' => $blastbaseline ? $blastbaselinesecs : null,
    'must_be_empty_baseline' => $mbebaseline,
];
$report->write($startrec);
if ($journal) {
    $journal->write(['event' => 'run_start', 'report' => $reportpath, 'txn' => $txnmode, 'web' => $web,
        'backup' => $backupfacts, 'restore_markers' => $restoremarkers]);
}
foreach ($refused as $rf) {
    $report->write(['event' => 'request', 'mode' => $mode] + $rf);
    a4_out(sprintf('req %5d  REFUSED  %s', $rf['requestid'], $rf['outcome']));
}

a4_out(sprintf('A4 %s: %d candidate request(s), %d refused, %d filtered out. Report: %s',
    strtoupper($mode), count($candidates), count($refused), $filteredout, $reportpath));
a4_out('Kill switches: ' . $killfacts['message_providers_disabled'] . ' message providers disabled in-process; '
    . $killfacts['http'] . '. Events: ' . $eventfacts['observers_replaced'] . ' observers replaced by the recorder. '
    . 'Read-only session: ' . ($rofacts ? 'ON (' . $rofacts['selftest'] . ')' : 'off (execute)')
    . '. Manifest: ' . ($manifestfacts['status'] ?? '-') . '. Registry defaults_set: '
    . var_export($registry['defaults_set'], true));

// ---------------------------------------------------------------------------------------------------------------
// Main loop.
// ---------------------------------------------------------------------------------------------------------------
$obs = new a4_recording_observer();
$tracefh = ($options['trace'] !== '' && $options['trace'] !== false) ? new a4_jsonl((string)$options['trace']) : null;
$prev = $base;
$sum = [
    'by_outcome' => [], 'by_state' => [], 'requests' => 0, 'seconds' => 0.0,
    'approved_contexts_total' => 0, 'approved_unique_contexts_total' => 0,
    'discovery_contexts_total_missing' => 0, 'discovery_contexts_total_deleted' => 0, 'excluded_by_registry' => 0,
    'excluded_by_registry_iterated' => 0,
    // contextlist_base iterator truncation (a4_contextlist_detail): recorded, never a trip.
    'truncation' => ['requests' => 0, 'requests_by_state' => [], 'components' => [], 'contexts_lost' => 0,
        'contexts_lost_by_component' => [], 'approved_requests' => 0, 'approved_contexts_lost' => 0, 'missing_ctx' => 0],
    'component_requests' => [], 'failures_by_component' => [],
    'failures_total' => 0, 'dpo_messages_avoided' => 0, 'other_output_lines' => 0, 'typeerror_reproduced' => 0,
    'levels' => [], 'exporter' => [], 'tail_ok' => 0, 'tail_would_send' => [], 'tail_conditions' => [],
    'events_total' => 0, 'send_attempt_lines' => 0, 'mem_peak_mb_max' => 0.0, 'components_not_in_manifest' => [],
    'full_snapshots' => 0, 'blast_trips' => 0,
];
$stopcode = 0;
$t0run = microtime(true);
$blastsample = null;
$n = 0;
$fullevery = $execute ? 1 : 25;   // Dry run: the DB session is read-only, so the all-table check is periodic.

foreach ($candidates as $c) {
    $n++;
    $requestid = $c['requestid'];
    $t0 = microtime(true);
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }
    $obs->requestid = $requestid;
    $obs->failures = [];
    a4_event_recorder::$events = [];
    $rec = [
        'event' => 'request', 'mode' => $mode, 'requestid' => $requestid, 'subjectid' => $c['subjectid'],
        'subject_state' => $c['state'], 'status_before' => $c['status'], 'txn' => $execute ? $txnmode : null,
        'writes' => [], 'planned_writes' => [], 'outcome' => null,
    ];
    $tripped = null;
    $softtrips = [];   // Found after the work (dry run, or --txn=none where the writes are already committed).
    $GLOBALS['A4_MTRACE_BUF'] = '';
    ob_start();
    try {
        // Re-read the request: it must still be what the target query saw.
        $dr = new data_request($requestid);
        $status = (int)$dr->get('status');
        if ((int)$dr->get('type') !== api::DATAREQUEST_TYPE_DELETE
                || (int)$dr->get('creationmethod') !== data_request::DATAREQUEST_CREATION_AUTO
                || $status !== $c['status'] || (int)$dr->get('userid') !== $c['subjectid']) {
            $rec['outcome'] = 'refused_changed_since_selection';
            throw new a4_skip();
        }
        $userid = (int)$dr->get('userid');
        $foruser = core_user::get_user($userid);   // Task :88 (IGNORE_MISSING: false when there is no row).
        if ($foruser && empty($foruser->deleted)) {
            $rec['outcome'] = 'refused_live_subject';
            throw new a4_skip();
        }
        if (!$execute) {
            // DPO list page / get_data_request web service: data_request_exporter loads the subject and the
            // requester with MUST_EXIST (external/data_request_exporter.php:137, :143). Read-only.
            try {
                $exp = new \tool_dataprivacy\external\data_request_exporter($dr, ['context' => \context_system::instance()]);
                $exp->export($PAGE->get_renderer('tool_dataprivacy'));
                $rec['exporter_ok'] = true;
            } catch (\Throwable $ee) {
                $rec['exporter_ok'] = false;
                $rec['exporter_error'] = ['class' => get_class($ee),
                    'errorcode' => ($ee instanceof \moodle_exception) ? $ee->errorcode : null,
                    'at' => a4_relpath($ee->getFile()) . ':' . $ee->getLine()];
            }
        }

        if (!$foruser) {
            // ---- Subject with no user row. The real code: approval 2 -> 3, task :92 status 4, :93 discovery,
            // :140 PHP warning ($foruser->id on false), :143 TypeError, request stuck at 4, task retried 12 times,
            // then failedtaskmaxdelay to every site admin. Nothing is deleted. Here: discovery (read-only), the
            // TypeError reproduced read-only, NO writes.
            $obs->phase = 'discovery';
            $manager = new \core_privacy\manager();
            $manager->set_observer($obs);
            $collection = $manager->get_contexts_for_userid($userid);   // Task :93.
            $cldisc = a4_contextlist_detail($collection);   // Full + iterated lists, before anything iterates them.
            $rec['discovery'] = a4_collection_summary($collection);
            $rec += a4_truncation_fields($cldisc, null);
            try {
                api::get_approved_contextlist_collection_for_collection($collection, $foruser,
                    api::DATAREQUEST_TYPE_DELETE);                         // Task :143 with $foruser === false.
                $rec['typeerror_reproduced'] = false;
            } catch (\TypeError $te) {
                $rec['typeerror_reproduced'] = true;
                $rec['typeerror'] = a4_trunc($te->getMessage(), 200);
            }
            $rec['outcome'] = 'not_processable_missing_user_row';
            $rec['real_code'] = 'approve 2->3, task sets 4, discovery, TypeError at api.php:1203 (from task :143); '
                . 'nothing deleted; request stuck at 4; 12 retries over ~34 h, then failedtaskmaxdelay to '
                . 'every site admin (' . $admincount . ' notifications for this request).';
        } else if (!$execute) {
            // ---- Dry run, subject marked deleted: everything the real code reads, nothing it writes.
            $rec['planned_writes'] = [
                ($status === api::DATAREQUEST_STATUS_AWAITING_APPROVAL ? '2->3 (dpo=' . $USER->id . ')' : null),
                '->4', 'provider deletions', '->10',
            ];
            $rec['planned_writes'] = array_values(array_filter($rec['planned_writes']));
            $obs->phase = 'discovery';
            $manager = new \core_privacy\manager();
            $manager->set_observer($obs);
            $collection = $manager->get_contexts_for_userid($userid);   // Task :93.
            $cldisc = a4_contextlist_detail($collection);   // Full + iterated lists, before anything iterates them.
            $rec['discovery'] = a4_collection_summary($collection);
            if (is_primary_admin($foruser->id)) {                       // Task :140 (cannot happen: deleted).
                $rec += a4_truncation_fields($cldisc, null);
                $rec['outcome'] = 'would_reject_primary_admin';
            } else {
                $approved = api::get_approved_contextlist_collection_for_collection($collection, $foruser,
                    api::DATAREQUEST_TYPE_DELETE);                         // Task :143.
                $rec['approved'] = a4_collection_summary($approved, true);
                $rec['approved_ids'] = $rec['approved']['ids'];
                unset($rec['approved']['ids']);
                // Full/iterated lists of both collections, truncation, excluded_by_registry (full lists) and
                // excluded_by_registry_iterated (the earlier figure). Recorded only: truncation is stock behaviour.
                $rec = array_merge($rec, a4_truncation_fields($cldisc, a4_contextlist_detail($approved)));
                $unknown = array_values(array_diff(array_keys($rec['approved_ids']), array_keys($manifestcomponents)));
                if ($unknown) {
                    $rec['components_not_in_manifest'] = $unknown;
                }
                if ($blastsample === null) {
                    // Time one per-request blast-radius count (execute takes it twice per request).
                    $t0s = microtime(true);
                    $blastsample = ['requestid' => $requestid,
                        'counts' => a4_blast_counts([$userid], a4_blast_scope($userid, $rec['approved_ids']))];
                    $blastsample['seconds'] = round(microtime(true) - $t0s, 2);
                    $rec['blast_sample_seconds'] = $blastsample['seconds'];
                }
                // Message tail as the real task would run it after status 10, dpo set as the approval sets it.
                $request = $dr->to_record();
                $request->dpo = $USER->id;
                $rec['tail'] = a4_message_tail($request, $foruser, !$foruser->deleted);
                if ($rec['tail']['would']) {
                    $softtrips[] = 'message tail would send: ' . implode(', ', $rec['tail']['would']);
                }
                $rec['outcome'] = 'would_delete';
            }
        } else {
            // ---- Execute, subject marked deleted.
            $journal->write(['event' => 'begin', 'requestid' => $requestid, 'status' => $status, 'txn' => $txnmode]);
            $txn = ($txnmode === 'request') ? $DB->start_delegated_transaction() : null;
            try {
                if ($status === api::DATAREQUEST_STATUS_AWAITING_APPROVAL) {
                    // api.php approve_data_request() :664. The :683 queue_data_request_task() is NOT called.
                    api::update_request_status($requestid, api::DATAREQUEST_STATUS_APPROVED, $USER->id);
                    $rec['writes'][] = '2->3 (dpo=' . $USER->id . ')';
                    if (!$txn) {
                        $journal->write(['event' => 'approved', 'requestid' => $requestid]);
                    }
                }
                // process_data_request_task::execute(), inline.
                $requestpersistent = new data_request($requestid);           // :66
                $request = $requestpersistent->to_record();                  // :67
                if (!api::is_active($requestpersistent->get('status'))) {     // :70-74
                    $rec['outcome'] = 'not_active';
                    throw new a4_skip();
                }
                $manager = new \core_privacy\manager();                      // :84
                $manager->set_observer($obs);                                // :85 (recording observer, no messages)
                $foruser = core_user::get_user($request->userid);            // :88
                api::update_request_status($requestid, api::DATAREQUEST_STATUS_PROCESSING);   // :92
                $rec['writes'][] = '->4';
                $obs->phase = 'discovery';
                $contextlistcollection = $manager->get_contexts_for_userid($requestpersistent->get('userid'));   // :93
                $cldisc = a4_contextlist_detail($contextlistcollection);   // Before anything iterates the lists.
                $rec['discovery'] = a4_collection_summary($contextlistcollection);

                if (is_primary_admin($foruser->id)) {                        // :140 (unreachable: subject is deleted)
                    api::update_request_status($requestid, api::DATAREQUEST_STATUS_REJECTED);   // :141 + :158
                    $rec['writes'][] = '->7';
                    $rec += a4_truncation_fields($cldisc, null);
                    $rec['outcome'] = 'rejected_primary_admin';
                    if ($txn) {
                        $txn->allow_commit();
                        $txn = null;
                    }
                } else {
                    $approvedclcollection = api::get_approved_contextlist_collection_for_collection(
                        $contextlistcollection, $foruser, $request->type);   // :143
                    $rec['approved'] = a4_collection_summary($approvedclcollection, true);   // Rewinds afterwards.
                    $rec['approved_ids'] = $rec['approved']['ids'];
                    unset($rec['approved']['ids']);
                    // Recorded only (stock behaviour, never a trip): the approval at :143 iterated the discovery
                    // lists, so a truncated component is approved (and deleted) in its iterated contexts only.
                    $rec = array_merge($rec, a4_truncation_fields($cldisc,
                        a4_contextlist_detail($approvedclcollection)));
                    $unknown = array_values(array_diff(array_keys($rec['approved_ids']), array_keys($manifestcomponents)));
                    if ($unknown) {
                        throw new a4_tripwire_exception(['approved component(s) not in the provider manifest: '
                            . implode(', ', $unknown)]);
                    }
                    $bscope = a4_blast_scope($userid, $rec['approved_ids']);   // Quizzes read before the deletion.
                    $blastbefore = a4_blast_counts([$userid], $bscope);
                    $manager = new \core_privacy\manager();              // :148
                    $manager->set_observer($obs);                        // :149 (recording observer)
                    $obs->phase = 'delete';
                    $manager->delete_data_for_user($approvedclcollection);   // :151
                    if ($obs->failures && $txn) {
                        // A failure would still be committed as status 10 by the real code and could never be
                        // rehearsed again: roll the whole request back to status 2 instead.
                        throw new a4_provider_failure($obs->failures);
                    }
                    // :152 status DELETED, written at :158. :153 $deleteuser = !$foruser->deleted is false (the
                    // subject is deleted), so :295-298 delete_user() is a no-op in the real code as well.
                    api::update_request_status($requestid, api::DATAREQUEST_STATUS_DELETED);
                    $rec['writes'][] = '->10';
                    // :160-298 with recorders for the sends and delete_user().
                    $rec['tail'] = a4_message_tail($request, $foruser, !$foruser->deleted);
                    $blastafter = a4_blast_counts([$userid], $bscope);
                    $pre = a4_blast_compare($blastbefore, $blastafter);
                    $rec['blast'] = [];
                    foreach ($blastafter as $k => $v) {
                        $b = $blastbefore[$k] ?? [];
                        if (isset($v['total'], $b['total']) && ($v['total'] !== $b['total'] || $v['subj'] !== $b['subj']
                                || ($v['anon'] ?? null) !== ($b['anon'] ?? null))) {
                            $rec['blast'][$k] = ['total' => [$b['total'], $v['total']], 'subj' => [$b['subj'], $v['subj']]]
                                + (isset($v['anon']) ? ['anon' => [$b['anon'], $v['anon']]] : [])
                                + (isset($v['anon_owned']) ? ['anon_owned' => [$b['anon_owned'] ?? null, $v['anon_owned']],
                                    'subj_owned' => [$b['subj_owned'] ?? null, $v['subj_owned']]] : []);
                        }
                    }
                    if ($rec['tail']['would']) {
                        $pre[] = 'message tail would send: ' . implode(', ', $rec['tail']['would']);
                    }
                    if ($txn) {
                        // Pre-commit tripwires, inside the transaction: every row this request wrote is still
                        // uncommitted, and every event already reached the (internal) recorder.
                        $inside = a4_snapshot($logbaseline, true);
                        $pre = array_merge($pre, a4_tripwire_compare($base, $prev, $inside, $allowinsert));
                        if (a4_event_recorder::$events) {
                            $pre[] = 'events fired: ' . implode(', ', array_unique(array_column(a4_event_recorder::$events,
                                'eventname')));
                        }
                        if (!a4_event_recorder::intact()) {
                            $pre[] = 'event observer table was reloaded (real observers could run)';
                        }
                        $lines = a4_send_attempt_lines((string)ob_get_contents() . $GLOBALS['A4_MTRACE_BUF']);
                        if ($lines) {
                            $pre[] = 'send attempt output: ' . $lines[0];
                        }
                        if ($pre) {
                            $rec['new_rows'] = a4_new_row_ids($base['maxids'], $inside['maxids']);
                            throw new a4_tripwire_exception($pre);
                        }
                        $txn->allow_commit();
                        $txn = null;
                    } else {
                        $softtrips = array_merge($softtrips, $pre);
                        if ($DB->is_transaction_started()) {
                            // lib/classes/cron.php: "Task left transaction open" -> force_transaction_rollback().
                            throw new \coding_exception('Task left transaction open');
                        }
                    }
                    $rec['outcome'] = $obs->failures ? 'deleted_with_provider_failures' : 'deleted';
                }
            } catch (\Throwable $e) {
                if ($DB->is_transaction_started()) {
                    $DB->force_transaction_rollback();               // As cron.php does on a task failure.
                    $rec['rolled_back'] = true;
                    $journal->write(['event' => 'rolled_back', 'requestid' => $requestid, 'reason' => get_class($e)]);
                }
                throw $e;
            }
            $journal->write(['event' => 'final', 'requestid' => $requestid, 'outcome' => $rec['outcome']]);
        }
    } catch (a4_skip $e) {
        // Outcome already set; the request is left as it is (rolled back in --txn=request).
        $rec['outcome'] = $rec['outcome'] ?? 'skipped';
    } catch (a4_provider_failure $e) {
        $rec['outcome'] = 'rolled_back_provider_failure';
    } catch (a4_tripwire_exception $e) {
        // --txn=none has nothing to roll back: the writes before the trip are committed.
        $rec['outcome'] = !empty($rec['rolled_back']) ? 'tripwire_rolled_back' : 'tripwire_stopped';
        $rec['tripwire'] = $e->trips;
        $tripped = $e->trips;
    } catch (\moodle_exception $e) {
        $rec['outcome'] = 'failed_unhandled';
        $rec['error'] = ['class' => get_class($e), 'errorcode' => $e->errorcode,
            'message' => a4_trunc($e->getMessage(), 300), 'at' => a4_relpath($e->getFile()) . ':' . $e->getLine()];
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
    }
    if ($execute && $txnmode === 'none'
            && (in_array($rec['outcome'], ['failed_unhandled', 'tripwire_stopped'], true) || $softtrips)) {
        $rec['real_code'] = 'task fails: cron rolls back only an open transaction (provider work already committed '
            . 'stays), the request stays at 4, the task is retried up to 12 times, then failedtaskmaxdelay goes to '
            . 'every site admin. This rehearsal did the same as cron (txn=none).';
        $rec['recovery'] = 'rerun with: --execute --resume --ids=' . $requestid . ' --txn=none --backup=<dump> --web=<...>';
    } else if ($execute && !empty($rec['rolled_back'])) {
        $rec['note'] = 'txn=request: the whole request was rolled back; it is at status 2 again and can be re-run with '
            . '--ids=' . $requestid . ' once the cause is fixed. A failure after an inner rollback can be a cascade '
            . 'of --txn=request; --txn=none shows the real-code result.';
    }

    [$tracelines, $other] = a4_split_output($captured);
    $rec['trace_lines'] = $tracelines;
    $rec['other_output_lines'] = count($other);
    if ($tracefh && $other) {
        $tracefh->write(['requestid' => $requestid, 'lines' => array_slice($other, 0, 200)]);
    }
    $sendlines = a4_send_attempt_lines($captured);
    if ($sendlines) {
        $rec['send_attempt_lines'] = $sendlines;
        $softtrips[] = 'send attempt output (' . count($sendlines) . ' line(s)): ' . $sendlines[0];
    }
    $failures = $obs->take();
    $rec['provider_failures'] = $failures;
    if ($failures) {
        $rec['dpo_messages_avoided'] = count($failures) * $dpocount;
    }
    $events = a4_event_recorder::take();
    if ($events) {
        $rec['events'] = array_count_values(array_column($events, 'eventname'));
        $softtrips[] = 'events fired: ' . implode(', ', array_keys($rec['events']));
    }
    if (!a4_event_recorder::intact()) {
        $softtrips[] = 'event observer table was reloaded (real observers could run)';
    }
    $rec['status_after'] = (int)$DB->get_field('tool_dataprivacy_request', 'status', ['id' => $requestid]);
    $rec['seconds'] = round(microtime(true) - $t0, 2);
    $rec['mem_peak_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);

    // Post-request tripwire (light every request; all tables and checksums every request in execute).
    if (!$tripped) {
        $full = ($n % $fullevery === 0);
        $now = a4_snapshot($logbaseline, $full);
        $trips = array_merge($softtrips, a4_tripwire_compare($base, $prev, $now, $allowinsert));
        if ($full) {
            $sum['full_snapshots']++;
        }
        if ($trips) {
            if (!$full) {
                $now = a4_snapshot($logbaseline, true);
                $trips = array_values(array_unique(array_merge($trips, a4_tripwire_compare($base, $prev, $now, $allowinsert))));
            }
            $tripped = $trips;
            $rec['tripwire'] = $trips;
            $rec['new_rows'] = a4_new_row_ids($base['maxids'], $now['maxids']);
        }
        $prev = $now;
    }

    $report->write($rec);

    // Summary accounting.
    $sum['requests']++;
    $sum['seconds'] += $rec['seconds'];
    $sum['by_outcome'][$rec['outcome']] = ($sum['by_outcome'][$rec['outcome']] ?? 0) + 1;
    $sum['by_state'][$c['state']] = ($sum['by_state'][$c['state']] ?? 0) + 1;
    $sum['other_output_lines'] += count($other);
    $sum['mem_peak_mb_max'] = max($sum['mem_peak_mb_max'], $rec['mem_peak_mb']);
    if (!empty($rec['approved'])) {
        $sum['approved_contexts_total'] += $rec['approved']['contexts_total'];
        $sum['approved_unique_contexts_total'] += $rec['approved']['unique_contexts'];
        $sum['excluded_by_registry'] += $rec['excluded_by_registry'] ?? 0;
        $sum['excluded_by_registry_iterated'] += $rec['excluded_by_registry_iterated'] ?? 0;
        foreach ($rec['approved']['by_component'] as $comp => $cnt) {
            $sum['component_requests'][$comp] = ($sum['component_requests'][$comp] ?? 0) + 1;
        }
        foreach ($rec['approved']['unique_by_level'] as $lvl => $cnt) {
            $sum['levels'][$lvl] = ($sum['levels'][$lvl] ?? 0) + $cnt;
        }
    }
    if (!empty($rec['discovery']['contexts_total'])) {
        $sum['discovery_contexts_total_' . ($c['state'] === 'missing' ? 'missing' : 'deleted')]
            += $rec['discovery']['contexts_total'];
    }
    if (!empty($rec['truncation'])) {
        $tr = $rec['truncation'];
        if ($tr['discovery_contexts_lost'] > 0) {
            $sum['truncation']['requests']++;
            $sum['truncation']['requests_by_state'][$c['state']] = ($sum['truncation']['requests_by_state'][$c['state']] ?? 0) + 1;
            $sum['truncation']['contexts_lost'] += $tr['discovery_contexts_lost'];
            foreach ($rec['iterator_truncated']['discovery'] ?? [] as $comp => $lostids) {
                $sum['truncation']['components'][$comp] = ($sum['truncation']['components'][$comp] ?? 0) + 1;
                $sum['truncation']['contexts_lost_by_component'][$comp] =
                    ($sum['truncation']['contexts_lost_by_component'][$comp] ?? 0) + count($lostids);
            }
        }
        if ($tr['approved_contexts_lost'] > 0) {
            $sum['truncation']['approved_requests']++;
            $sum['truncation']['approved_contexts_lost'] += $tr['approved_contexts_lost'];
        }
        $sum['truncation']['missing_ctx'] += $tr['missing_ctx'];
    }
    if (!empty($rec['typeerror_reproduced'])) {
        $sum['typeerror_reproduced']++;
    }
    if (isset($rec['exporter_ok'])) {
        $k = $c['state'] . ':' . ($rec['exporter_ok'] ? 'ok' : 'fails ' . ($rec['exporter_error']['class'] ?? '?'));
        $sum['exporter'][$k] = ($sum['exporter'][$k] ?? 0) + 1;
    }
    if (isset($rec['tail'])) {
        $sum['tail_ok']++;
        foreach ($rec['tail']['would'] as $w) {
            $sum['tail_would_send'][$w] = ($sum['tail_would_send'][$w] ?? 0) + 1;
        }
        $ck = json_encode($rec['tail']['conditions']);
        $sum['tail_conditions'][$ck] = ($sum['tail_conditions'][$ck] ?? 0) + 1;
    }
    foreach ($rec['components_not_in_manifest'] ?? [] as $u) {
        $sum['components_not_in_manifest'][$u] = true;
    }
    $sum['events_total'] += array_sum($rec['events'] ?? []);
    $sum['send_attempt_lines'] += count($rec['send_attempt_lines'] ?? []);
    foreach ($failures as $f) {
        $k = $f['phase'] . ':' . $f['component'] . '::' . $f['method'];
        $sum['failures_by_component'][$k] = ($sum['failures_by_component'][$k] ?? 0) + 1;
        $sum['failures_total']++;
    }
    $sum['dpo_messages_avoided'] += $rec['dpo_messages_avoided'] ?? 0;

    a4_out(sprintf('req %5d  subj %6d  %-7s  %-34s  ctx %3s  comp %3s  trunc %3s  fail %d  %5.1fs  status %d->%d%s',
        $requestid, $c['subjectid'], $c['state'], $rec['outcome'],
        $rec['approved']['unique_contexts'] ?? ($rec['discovery']['unique_contexts'] ?? '-'),
        $rec['approved']['components'] ?? ($rec['discovery']['components'] ?? '-'),
        $rec['truncation']['discovery_contexts_lost'] ?? '-',
        count($failures), $rec['seconds'], $c['status'], $rec['status_after'],
        $tripped ? '  TRIPWIRE' : ''));

    if ($tripped) {
        a4_out('TRIPWIRE: ' . implode('; ', $tripped) . ' -- stopping.');
        if (!empty($rec['new_rows'])) {
            foreach ($rec['new_rows'] as $t => $rids) {
                a4_out("  new rows in $t (ids): " . implode(',', array_slice($rids, 0, 50)) . (count($rids) > 50 ? ' ...' : ''));
            }
        }
        if ($execute && empty($rec['rolled_back'])) {
            a4_out('  Data of this request is COMMITTED. Do not run cron, adhoc_task.php or scheduled_task.php. '
                . 'Nitin decides: restore from the --backup dump, or review the rows above (README "After a tripwire").');
        }
        $stopcode = 3;
        break;
    }
    if ($stoponerror && ($failures || in_array($rec['outcome'], ['failed_unhandled', 'rolled_back_provider_failure'], true))) {
        a4_out('Stopping on error (--stop-on-error).');
        $stopcode = 4;
        break;
    }
}

// ---------------------------------------------------------------------------------------------------------------
// End: tripwires, statuses, read-only state, summary.
// ---------------------------------------------------------------------------------------------------------------
$end = a4_snapshot($logbaseline, true);
$endtrips = a4_tripwire_compare($base, $base, $end, $allowinsert);
$strayevents = a4_event_recorder::take();
if ($strayevents) {
    $endtrips[] = 'events fired outside a request: ' . count($strayevents);
}
if (!a4_event_recorder::intact()) {
    $endtrips[] = 'event observer table was reloaded';
}
$endnewrows = $endtrips ? a4_new_row_ids($base['maxids'], $end['maxids']) : [];
$countsend = $execute ? a4_counts(a4_all_id_tables()) : null;
$countchanges = [];
if ($countsstart && $countsend) {
    foreach ($countsend as $t => $v) {
        if (($countsstart[$t] ?? null) !== $v) {
            $countchanges[$t] = [$countsstart[$t] ?? null, $v];
        }
    }
}
$distafter = a4_request_status_distribution();
$residualend = ($withresiduals && $execute) ? a4_residual_counts($a4subjects) : null;
$roend = $execute ? null : a4_readonly_state();
arsort($sum['component_requests']);
arsort($sum['truncation']['components']);
arsort($sum['truncation']['contexts_lost_by_component']);
ksort($sum['levels']);
$sum['components_not_in_manifest'] = array_keys($sum['components_not_in_manifest']);
$summary = [
    'event' => 'summary', 'mode' => $mode, 'elapsed_seconds' => round(microtime(true) - $t0run, 1),
    'stopped' => $stopcode, 'refused' => count($refused), 'filtered_out' => $filteredout,
    'tx_read_only_end' => $roend,
    'snapshot_start' => $base, 'snapshot_end' => $end, 'snapshot_delta' => a4_snapshot_delta($base, $end),
    'tripwire_trips_end_vs_start' => $endtrips, 'new_rows_end' => $endnewrows,
    'all_table_count_changes' => $execute ? $countchanges : null,
    'requests_start' => $distbefore, 'requests_end' => $distafter, 'residuals_end' => $residualend,
    'blast_sample' => $blastsample,
] + $sum;
$report->write($summary);

a4_out('');
a4_out('==== SUMMARY (' . $mode . ') ====');
a4_out('requests processed: ' . $sum['requests'] . ' in ' . round($sum['seconds'], 1) . ' s; refused ' . count($refused)
    . '; filtered out ' . $filteredout . '; wall ' . round(microtime(true) - $t0run, 1) . ' s');
a4_out('by subject state: ' . json_encode($sum['by_state']));
a4_out('by outcome: ' . json_encode($sum['by_outcome']));
a4_out('approved collection, summed over requests: ' . $sum['approved_contexts_total'] . ' component-context pairs; '
    . $sum['approved_unique_contexts_total'] . ' distinct contexts (counted per request); by context level '
    . json_encode($sum['levels']) . ' (10 system, 30 user, 40 category, 50 course, 70 module, 80 block)');
a4_out('discovery pairs (iterated): deleted subjects ' . $sum['discovery_contexts_total_deleted'] . ', missing subjects '
    . $sum['discovery_contexts_total_missing'] . '; excluded_by_registry (full lists: full discovery minus full approved) '
    . $sum['excluded_by_registry'] . ' = excluded by the data registry on the iterated lists '
    . $sum['excluded_by_registry_iterated'] . ' + not iterated (approval truncated) '
    . ($sum['excluded_by_registry'] - $sum['excluded_by_registry_iterated'])
    . ' (registry defaults_set=' . var_export($registry['defaults_set'], true) . ')');
a4_out('contextlist iterator truncation (core bug, contextlist_base.php:66 + :170; recorded, not a trip): '
    . $sum['truncation']['requests'] . ' request(s) ' . json_encode($sum['truncation']['requests_by_state'])
    . ', discovery contexts not iterated ' . $sum['truncation']['contexts_lost'] . ', by component (requests) '
    . json_encode($sum['truncation']['components']) . ', contexts '
    . json_encode($sum['truncation']['contexts_lost_by_component']) . '; approved lists truncated: '
    . $sum['truncation']['approved_requests'] . ' (contexts ' . $sum['truncation']['approved_contexts_lost']
    . '; expected 0, stock api.php builds approved lists without holes: a4_verify reports any as approved_list_hole)'
    . '; missing contexts dropped by the iterator: ' . $sum['truncation']['missing_ctx']);
a4_out('missing-subject TypeError reproduced: ' . $sum['typeerror_reproduced']);
a4_out('provider failures: ' . $sum['failures_total'] . ' ' . json_encode($sum['failures_by_component'])
    . '; DPO messages the real observer would have sent: ' . $sum['dpo_messages_avoided']
    . ($execute ? '' : ' (discovery only: the dry run never calls delete_data_for_user)'));
a4_out('message tail run: ' . $sum['tail_ok'] . '; would send: ' . ($sum['tail_would_send'] ? json_encode($sum['tail_would_send']) : 'nothing')
    . '; conditions: ' . json_encode($sum['tail_conditions']));
if ($sum['exporter']) {
    a4_out('DPO list exporter (data_request_exporter): ' . json_encode($sum['exporter']));
}
a4_out('events recorded: ' . $sum['events_total'] . '; send-attempt output lines: ' . $sum['send_attempt_lines']
    . '; observer table intact: ' . (a4_event_recorder::intact() ? 'yes' : 'NO'));
a4_out('components with deletions (requests): ' . json_encode(array_slice($sum['component_requests'], 0, 60, true)));
if ($sum['components_not_in_manifest']) {
    a4_out('approved components NOT in the manifest: ' . implode(', ', $sum['components_not_in_manifest']));
}
a4_out('memory: limit ' . ini_get('memory_limit') . ', peak per request max ' . $sum['mem_peak_mb_max'] . ' MB');
if ($blastsample) {
    a4_out('blast-radius count, one subject: ' . $blastsample['seconds'] . ' s (execute takes it twice per request)');
}
a4_out('other output lines captured: ' . $sum['other_output_lines']);
a4_out('all-table snapshots: ' . $sum['full_snapshots'] . ' during the run + start + end (' . count(a4_all_id_tables())
    . ' tables)');
a4_out('tripwire end vs start: ' . ($endtrips ? implode('; ', $endtrips) : 'no increase'));
a4_out('snapshot delta: ' . json_encode(a4_snapshot_delta($base, $end)));
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
exit($stopcode ?: ($endtrips ? 3 : 0));
