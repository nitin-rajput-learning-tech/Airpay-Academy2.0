# A4 rehearsal: process the 515 automatic deletion requests locally, with zero sends

Decision A4 (Nitin, 2026-11-13): process the automatic `tool_dataprivacy` deletion requests (type 2 delete,
creationmethod 1 automatic, status 2 awaiting approval) on the **local XAMPP copy only**, as a rehearsal of every
plugin's delete code. Hard constraint, verbatim: "no emails or notifications should be triggered".

**Status 2026-11-13 11:45 IST: DONE on the local copy.** 286 requests executed (canary of 9, request 504, then
277), all `deleted`; the 229 requests whose subject has no user row are untouched at status 2 (the stock task would
throw a TypeError on them). Zero sends: 0 events, 0 send attempts, no new notification, message, popup or e-mail row,
task queue unchanged. `a4_verify.php` over the three execute reports: 0 violations, residue bucket `failure` = 0.
Results for Nitin and the DPO: `docs/cutover/A4-ERASURE-REHEARSAL-RESULTS-2026-11-13.md`. Repo copy of the scripts
(working copy and run output: `D:/Claude Local/kit-scratch/1113/a4/`); they run against the local XAMPP site only.
The sections below were written while building and are kept as the record.

## Files

| File | What it is |
|---|---|
| `a4_rehearsal.php` | The rehearsal. Dry run by default; changes data only with `--execute`. |
| `a4_verify.php` | Read-only check after an execute run: discovery against control ids, must-be-empty checks (flat, or bucketed by context for the context-scoped ones: only `failure` is a violation, see "Residue buckets"), other people's rows, residual counts, tripwires. Exit 4 on any violation. Only syntax-checked; its SQL ran in the `--residuals` dry run (`reports/test5-dry-residuals.jsonl`). |
| `a4_lib.php` | Shared helpers: php guard check, environment guard, read-only session, kill switches, event recorder, snapshots and tripwires, blast-radius counts, message-tail mirror, provider manifest, backup gate, verify specs. |
| `reports/` | JSON-lines reports (`event` = start, request, summary, counts). Ids and counts only. |
| `state/` | `provider-manifest.json` (written by the dry run), and from `--execute`: `journal.jsonl` and `a4.lock`. |

Both scripts refuse to run unless the php guard flags are on (below), `wwwroot` is localhost, `dbhost` is local,
`dbname` is `moodle`, the prefix is `mdl_` (never `phpu_`) and `$CFG->noemailever` is true.

## What one request goes through

Execute mode, subject row present with `deleted = 1` (286 requests). Line numbers are the 5.1 XAMPP files
(`admin/tool/dataprivacy/classes/...`).

| Step | Real code | Rehearsal |
|---|---|---|
| Approve | `api::approve_data_request()` :646 DPO check, :652 status must be 2, :658 delete capability, :664 `update_request_status(3, dpo)` | Checks :646/:658 once for the cron user at start, then calls `api::update_request_status($id, 3, $USER->id)` directly (not `approve_data_request()`) |
| Queue | :683 `queue_data_request_task()` | **Skipped** |
| Run as | cron sets the cron admin (`\core\cron::setup_user()`) | Same call; memory `MEMORY_EXTRA` as cron (`cron.php:87`) |
| Task body | `process_data_request_task::execute()` :66-158 | Inline, same order: reload :66-67, `is_active` :70-74, `new manager` :84, `get_user` :88, status 4 :92, discovery :93, primary-admin check :140, approved collection :143, new manager :148, `delete_data_for_user` :151, status 10 :152/:158 |
| Failure handler | `\tool_dataprivacy\manager_observer` :85/:149 (`message_send` to every DPO) | `a4_recording_observer`: records component, method, exception class and message; sends nothing |
| Message tail | :160-293 builds the result message, renders the template, decides the four sends | **Runs as written** (`a4_message_tail()`): message built, template rendered, every condition evaluated with the real api calls. Only the four send calls (:241, :244, :287, :289) are replaced by recorders; any "would send" trips the run |
| `delete_user()` | :295-298 when `$deleteuser = !$foruser->deleted` | Replaced by a recorder (false for every subject; a true value trips the run) |
| Cron wrapper | `cron.php`: "Task left transaction open" check, rollback on failure | Same with `--txn=none`; with `--txn=request` the whole request is one transaction |

Dry run: everything the real code **reads** (discovery over all components, the approved-collection filter, the
approval preconditions, the message tail with the dpo set as the approval would set it, and the DPO list exporter
`data_request_exporter`), nothing it writes. The DB session is `READ ONLY` for the whole process; a zero-row
self-test (`UPDATE {tool_dataprivacy_request} SET status = status WHERE id < 0`) must be refused before the run
starts, and `tx_read_only` is checked again at the end.

### Steps skipped from the real code, and why

1. **`api.php:683` `queue_data_request_task()` and the cron run.** Scheduling only. Running a task runner would also
   run the 41 queued `send_login_notifications` and the overdue scheduled tasks. The task body runs inline under the
   same cron user, so providers receive identical calls.
2. **`\tool_dataprivacy\manager_observer`.** Its only job is `message_send()` to every DPO (4 site admins, because
   `dporoles` is unset). The try/catch, `debugging()` and continue-with-next-component logic live in
   `\core_privacy\manager` and are unchanged. Failures become more visible (recorded per request), not less.
3. **The four send calls and `delete_user()`** inside the tail (recorders instead). Everything around them runs.
4. **The 229 subjects with no user row get no writes at all** (see below).

The rehearsal summarises each collection before handing it to the providers and then rewinds every contextlist, so
providers receive them positioned as the real task passes them (`contextlist_base::current()` depends on it). Since
2026-11-13 the summaries iterate a **clone** of each contextlist: `current()` unsets the id of a context that no longer
exists (`contextlist_base.php:130-133`), which on the original would leave a key hole the approval would then stop at.
Discovery only returns existing contexts (`contextlist::add_from_sql()` joins `{context}`), so this changes nothing in
practice; it removes the rehearsal's own effect on the lists. What gets approved and deleted is unchanged: the approval
still iterates, exactly as stock does (see "Residue buckets").

### The 229 subjects with no user row

The real code cannot process them. The task gets `false` from `core_user::get_user()` (:88), sets status 4 (:92), runs
discovery (:93), warns at :140, then throws `TypeError` at :143 (`api.php:1203` requires `\stdClass $foruser`). Nothing
is deleted, the request stays at "Processing", the task is retried 12 times over about 34 h, and the 12th failure sends
`failedtaskmaxdelay` to every site admin: 229 x 4 = 916 notifications.

The rehearsal runs the discovery (read-only), reproduces the `TypeError` by calling the same API with `false` (it
fails at parameter binding, before any work), records outcome `not_processable_missing_user_row`, and writes
nothing: no approval, no status 4. The DPO list exporter fails for every one of them (`dml_missing_record_exception`),
so the DPO request list page cannot show them: see the live runbook.

## Zero sends: belt and braces

### Process-level (php command line, checked at start; abort otherwise)

Every A4 command runs with:

```
-d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail,mb_send_mail,imap_mail,proc_open,popen,exec,shell_exec,system,passthru -d allow_url_fopen=0 -d SMTP=127.0.0.1 -d smtp_port=9
```

`a4_require_hardened_php()` aborts unless every one of those functions is gone, `allow_url_fopen` is off and the PHP
mail SMTP target is the dead local port (that last one covers mail paths `disable_functions` cannot reach, such as
`mb_send_mail` internals and `error_log()` type 1). Raw `curl_init()` callers (AI gateway, Keka, Teams, AWS, PWA)
therefore cannot send. Moodle's own `\curl` refuses the URL earlier (below), so it is unaffected. Proven in the dry
runs: the run starts only with the flags.

### In-process (this PHP process only, no config writes)

| Channel | Guard |
|---|---|
| E-mail | Abort unless `$CFG->noemailever` is true (config.php:44); set again in-process; PHP mail off (flags) |
| Moodle notifications and messages | `$CFG->forced_plugin_settings['message'][<component>_<name>_disable] = 1` for all 83 providers, so `message_send()` returns before writing a row. Self-checked through `get_message_output_default_preferences()` |
| DPO failure messages | Recording observer instead of `manager_observer` |
| Failed-task admin messages | No task queue, no runner; `moodle/failedtaskmaxdelay` also disabled |
| Events and every event observer | `a4_event_recorder`: the dispatcher's observer table is replaced by one internal recorder, so no real observer (tool_log, tool_monitor, webhook queue, e-mail rules, WhatsApp, challenge, LearnerScript) can run, also not at commit. Self-tested with one `course_viewed` event at start. Any event during a request trips the run; the table is re-checked after every request (a cache reset would reload it) |
| Mobile push (airnotifier) | `$CFG->airnotifierurl` and `$CFG->airnotifieraccesskey` (core settings) blanked; aborts unless `message_airnotifier_manager::is_system_configured()` is false |
| PWA web push, WhatsApp/SMS | VAPID keys and the Karix/MSG91 keys forced empty |
| HTTP via `\curl` and `\core\http_client` | `curlsecurityallowedport = 1` blocks every http/https URL; self-checked. Host blocklist cleared in-process on purpose (no DNS lookups). Second layer: dead proxy `127.0.0.1:9` |
| Log stores | `logstore_standard` buffersize 1 (no buffered rows escape a check); `logstore_database` dbdriver/dbhost empty (its privacy provider would otherwise open the external connection) |
| Send attempts made visible | `$CFG->debug = DEBUG_DEVELOPER`, `debugdisplay = 1`: `email_to_user()` prints "Not sending email due to ..." into the captured request output, and that line (or "Attempt to send msg", or an observer exception) trips the run |
| Event monitor | `tool_monitor/enablemonitor = 0` |

### Tripwires

- **Light snapshot after every request:** rows and max id of the send and queue tables (notifications, popup,
  messages, email digest, task_adhoc, the dataprivacy request and contextlist tables, Sentientia send/push/webhook/
  notification/e-mail logs, evaluation assignments and triggers, badges, certificate issues, points, calendar events,
  report schedules, LearnerScript schedule, integration and AI logs, reminder logs, webhook subscriptions, plus any
  table named like an outbox or queue), messaging and dataprivacy ad hoc tasks, tasks marked running,
  `tool_task/lastcronstart`, live-user count, and new `notification_sent`/`message_sent`/`user_deleted`/`email_failed`
  rows in the standard log.
- **Full snapshot** (every request in execute and inside the transaction before commit; every 25 requests and at the
  end in the dry run): MAX(id) of **all 717 `mdl_` tables with an id column**, with an empty allowlist (this path
  inserts nothing; `--allow-insert=t1,t2` exists for a reviewed exception), and checksums of `task_scheduled`
  (classname, nextruntime, disabled, lastruntime, faildelay, timestarted), `task_adhoc` (nextruntime, faildelay,
  timestarted, userid), `config` and `config_plugins`. A re-armed task or a re-enabled setting changes a checksum.
- **Events, observer table, send-attempt output, message-tail recorders**, as above.
- **Blast radius (execute, per request):** before and after the deletion, for 56 table specs the A4 path writes
  (40 delete, 7 anonymise, 9 keep):
  `delete` specs trip unless the drop in total rows equals the drop in the subject's rows; `keep` specs
  (user, context, role assignments, enrolments, requests, Sentientia requests, course, course modules, grade items)
  trip on any change; `anon` specs (org_member.managerid, learningpath enrolledby, the users legacy tables) trip if
  more rows were anonymised than the subject held. The large tables are scoped: the log store to the contexts
  approved for `tool_log` (its only delete is `userid = ? AND contextid IN (...)`), the question tables to the usages
  of the quizzes the subject attempted. One subject's count takes about 1.1-1.7 s here.
- **Provider manifest:** sha1 of every component's `classes/privacy/provider.php` (541 components), the dataprivacy
  task path, the privacy manager, the send and event code and the delete-path helpers (594 files), plus the
  registry state. The dry run writes `state/provider-manifest.json` when none exists (or with `--write-manifest`);
  `--execute` refuses to start on any difference and lists it. An approved component missing from the manifest
  trips the request.

Any trip stops the run (exit 3), with or without `--stop-on-error`. In `--txn=request` the pre-commit checks run
inside the transaction, so the request (approval included) is rolled back to status 2. On a trip the report lists
the ids of the new rows in every table that grew (`new_rows`).

### Blind spots (what the guards do not see)

- A `message_send()` for a known provider returns silently at the disable check (`messagelib.php:71-74`): an attempt
  leaves no trace. The provider code reading (maps) and the recorder on the four known send sites are the evidence.
- `\core\hook` dispatches are not recorded (no hook on this path per the maps).
- An UPDATE that re-arms a send through a table other than the checksummed ones (a status column reset to pending).
  The blast-radius `keep`/`anon` specs and the all-table max ids narrow it; they do not close it.
- Everything above is **in-process only**: it does not reach a web request or another CLI process (see Concurrency).

## Concurrency

The kill switches and the event recorder protect this PHP process only. A web request during the run would run real
observers and real `message_send()` (notification and popup rows; e-mail is still blocked by `noemailever` in
config.php). Someone was logged in on the local site on 2026-11-13 06:00-06:04 IST. Therefore:

- `--execute` requires `--web=maintenance`: `php ..\admin\cli\maintenance.php --enable` was run first (writes
  `climaintenance.html` to dataroot, and `maintenance_enabled` to config before the baseline). The script checks the
  file at start and after every request (it trips if the file is gone). Every web request then dies in
  `lib/setup.php` before any DB work. `stopped` and `open` were removed on 2026-11-13 after the final check: the XAMPP
  watchdog restarts a stopped Apache, and an open site runs the real observers. Undo afterwards with
  `maintenance.php --disable`.
- No task runner of any kind (`cron.php`, `adhoc_task.php`, `scheduled_task.php`) during or after the run until the
  queue is reviewed. `scheduled_task.php` has no CLI-maintenance check.
- Manifest helper paths must exist (fixed 2026-11-13: `classes/user_type_tables.php` was really
  `classes/schema/user_type_tables.php` and hashed nothing). Re-blessed at 03:39:54Z; the earlier manifest is kept as
  `state/provider-manifest.0234Z.json`.
- Tasks running and `lastcronstart` are checked at start and after every request (a change trips the run). The
  all-table max-id check also catches rows another process inserts (a log row, a session).
- A login queues `send_login_notifications` only for a new session cookie from a new IP (`moodlelib.php:4152-4176`);
  any such row trips the run.

## Transactions (`--txn`)

`--txn=request` (default): one delegated transaction per request, from the approval (2 -> 3) through processing (4),
deletion and the final status 10, plus the pre-commit tripwires. Any provider failure (discovery or delete), any
tripwire, any exception or a PHP crash rolls the **whole request** back: it is at status 2 again, reachable by the
script, the UI approve/deny buttons and `--ids=N`. The real code would have committed status 10 despite a provider
failure; the rehearsal refuses that on purpose (outcome `rolled_back_provider_failure`) so the request can be
re-run once the provider is fixed.

Where it is not equivalent or not safe:
- **File content.** `tool_certificate` deletes its files through the file API. The `files` rows are transactional,
  the content move to trashdir is not. Locally `filedir` holds content for 0 of the 80 certificate files, so nothing
  happens on disk here; on live the PDFs move to trashdir and are purged later.
- **Nested rollback.** If a provider rolls back its own delegated transaction, Moodle poisons the outer one; the
  request is rolled back. The real code would have committed the other providers' work. The report marks this
  (`rolled_back`, `note`); `--txn=none` shows the real-code result.
- Caches purged by a provider are not restored by a rollback.

`--txn=none`: exactly the cron behaviour. Each write commits at once (2 -> 3, -> 4, deletions, -> 10); a provider
failure still ends at status 10 (outcome `deleted_with_provider_failures`) and can then not be rehearsed again
without a restore; an open transaction after the request is rolled back ("Task left transaction open"). A failure or
trip leaves the request at 3 or 4; recover with `--execute --resume --ids=N --txn=none --backup=... --web=...`
(the report's `recovery` field has the line).

## How to run

Run from `C:\xampp\htdocs\moodle5\public`. Never run `admin/cli/cron.php`, `scheduled_task.php --execute` or
`adhoc_task.php --execute`: the queue holds 41 `send_login_notifications` tasks.

```bash
cd C:/xampp/htdocs/moodle5/public
R="D:/Claude Local/kit-scratch/1113/a4/rehearsal"
H="-d disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_connect,mail,mb_send_mail,imap_mail,proc_open,popen,exec,shell_exec,system,passthru -d allow_url_fopen=0 -d SMTP=127.0.0.1 -d smtp_port=9"
# $H is used unquoted below on purpose (it splits into the separate -d options).
```

### 1. DB backup first, and rehearse the restore once (PowerShell)

The password stays out of the command line: put it in a client option file you create by hand, outside the repo.
Keep it until the rehearsal is accepted (the restore needs it).

```powershell
# D:\Claude Local\Moodle Backup\a4-client.cnf  (create by hand; never commit; keep until the rehearsal is accepted)
#   [client]
#   user=<local db user>
#   password=<local db password>
#   host=127.0.0.1
#   port=3306                  # XAMPP MariaDB 10.11. The 5.3 runtime is on 3308: never point this file there.

$bin = 'C:\xampp\mysql\bin'
$cnf = 'D:\Claude Local\Moodle Backup\a4-client.cnf'
& "$bin\mysql.exe" "--defaults-extra-file=$cnf" -N -B -e "SELECT @@port, @@version, DATABASE()"   # expect 3306, 10.11.x
$dir = 'D:\Claude Local\Moodle Backup\a4-pre-rehearsal-' + (Get-Date -Format 'yyyyMMdd-HHmm')
New-Item -ItemType Directory -Force $dir | Out-Null

# mdl_ tables only: the phpu_ PHPUnit tables share the database and must stay out of any dump or restore.
$tables = & "$bin\mysql.exe" "--defaults-extra-file=$cnf" -N -B -e "SELECT table_name FROM information_schema.tables WHERE table_schema = 'moodle' AND table_name LIKE 'mdl\_%' ORDER BY table_name"
$tables.Count    # expect 717

& "$bin\mysqldump.exe" "--defaults-extra-file=$cnf" --single-transaction --routines --hex-blob `
    --default-character-set=utf8mb4 "--result-file=$dir\moodle-mdl.sql" moodle @tables

Get-Item "$dir\moodle-mdl.sql" | Select-Object Length, LastWriteTime
Get-Content "$dir\moodle-mdl.sql" -Tail 1          # must read "-- Dump completed on ..."
```

`--defaults-extra-file` must be the first option. All tables are InnoDB, so `--single-transaction` gives a consistent
dump (about 2 GB of data). `--execute` re-checks the dump itself: header (`-- MariaDB dump`, `Database: moodle`),
footer (`-- Dump completed on`), the `CREATE TABLE` lines of `mdl_tool_dataprivacy_request`,
`mdl_tool_certificate_issues` and `mdl_logstore_standard_log`, no `phpu_` table, more than 1 GB, less than 48 h
old; it records the sha256 and size in the report's start line and in the journal.

**Rehearse the restore once before the first `--execute`** (Nitin's call; it creates a scratch schema):

```powershell
& "$bin\mysql.exe" "--defaults-extra-file=$cnf" -e "CREATE DATABASE moodle_a4check CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
cmd /c "`"$bin\mysql.exe`" --defaults-extra-file=`"$cnf`" moodle_a4check < `"$dir\moodle-mdl.sql`""
foreach ($t in 'mdl_tool_dataprivacy_request','mdl_tool_certificate_issues','mdl_user','mdl_files','mdl_logstore_standard_log') {
  & "$bin\mysql.exe" "--defaults-extra-file=$cnf" -N -B -e "SELECT '$t', (SELECT COUNT(*) FROM moodle.$t), (SELECT COUNT(*) FROM moodle_a4check.$t)"
}
# The two counts must match. Nitin drops moodle_a4check himself afterwards (DROP DATABASE is deny-listed for Claude).
```

### 2. Dry run (read-only)

```bash
php $H "$R/a4_rehearsal.php" --counts-only --report="$R/reports/counts-pre.jsonl"
php $H "$R/a4_rehearsal.php" --residuals            # all 515, about 11-13 minutes; writes the manifest if none exists
```

If the dry run reports manifest differences (other sessions redeploy plugins to XAMPP), review the listed files and
re-bless with `php $H "$R/a4_rehearsal.php" --write-manifest --ids=230` before any `--execute`.

### 3. Canary, then verify

The canary covers all 32 components that delete (the first five deleted subjects cover 19; requests 504, 427, 250
and 498 add the other 13: core_course, mod_quiz, tool_certificate, mod_url, report_stats, mod_resource, core_user,
tool_dataprivacy, tool_messageinbound, local_sentientia_users, core_message, learningpath, request).

```bash
php $H "$R/a4_rehearsal.php" --execute --ids=230,231,232,233,234,504,427,250,498 --residuals --stop-on-error \
    --web=maintenance --backup="D:/Claude Local/Moodle Backup/a4-pre-rehearsal-<stamp>/moodle-mdl.sql"
php $H "$R/a4_verify.php" --report="$R/reports/a4-execute-<stamp>.jsonl"
```

Pass check, all of: outcome `deleted` x9; `provider_failures` empty; tripwire "no increase"; events 0; "would send:
nothing"; statuses `t2.c1.s10` = 9; **`a4_verify` exit 0** (no violation: discovery of the exact components no longer
reports the subjects, every must-be-empty check is 0, no other person's rows removed). Expect about 5 s per request
(deletion plus two blast counts and two all-table snapshots).

Since 2026-11-13 "every must-be-empty check is 0" means: every flat check `rows_left` 0 and `failure` 0 in every
bucketed check. Rows in `truncated`, `undiscovered` or `not_approved_unbucketed_pre_guard` are what the stock path
leaves by itself and are reported, not violations (see "Residue buckets"). Rows in `registry` are a violation while
`defaults_set` is false, and so is any approved-list iterator hole (`approved_list_hole`): neither can happen on the
stock path, so either one means the recorded evidence is inconsistent.

### 4. The rest, then verify everything

```bash
php $H "$R/a4_rehearsal.php" --execute --only=deleted --all --stop-on-error --residuals \
    --web=maintenance --backup="D:/Claude Local/Moodle Backup/a4-pre-rehearsal-<stamp>/moodle-mdl.sql"
php $H "$R/a4_verify.php" --report="$R/reports/a4-execute-<stamp1>.jsonl,$R/reports/a4-execute-<stamp2>.jsonl"
php $H "$R/a4_rehearsal.php" --counts-only --report="$R/reports/counts-post.jsonl"
```

`--only=deleted` because execute writes nothing for the 229 missing subjects anyway. A request rolled back in
`--txn=request` is at status 2 again: rerun it with `--ids=N` once the cause is fixed. With `--txn=none` a stopped
run leaves at most one request at 3/4: rerun with `--resume --ids=N --txn=none`.

### After a tripwire or a failure

- `--txn=request`, trip before commit: the request is rolled back (status 2). Read `tripwire` and `new_rows` in the
  report line; nothing of it is committed.
- A trip after commit (`--txn=none`, or the end-of-run check): the report and the console list the new row ids per
  table. **Do not run cron, `adhoc_task.php` or `scheduled_task.php`** (a queued row would then run). Nitin decides:
  restore from the `--backup` dump (below) or review the listed rows.
- `failed_unhandled` / `rolled_back_provider_failure`: read `error` / `provider_failures`; fix; rerun `--ids=N`.

### Restore, only if needed (Nitin's call; it replaces every `mdl_` table in the dump)

Before restoring, compare with the run's start line (`restore_markers`): `upgrade_log` max id, plugin versions
checksum, log store max id. If they moved, another session upgraded a plugin or worked on the site after the dump,
and the restore would discard that work or leave new plugin tables beside old versions. Stop Apache (watchdog first)
and keep other sessions off the site while restoring.

```powershell
& "$bin\mysql.exe" "--defaults-extra-file=$cnf" -N -B -e "SELECT @@port"     # must be 3306
cmd /c "`"$bin\mysql.exe`" --defaults-extra-file=`"$cnf`" moodle < `"$dir\moodle-mdl.sql`""
php ..\admin\cli\purge_caches.php
Rename-Item "D:\Claude Local\kit-scratch\1113\a4\rehearsal\state\journal.jsonl" "journal-$(Get-Date -Format yyyyMMdd-HHmm).pre-restore.jsonl"
```

### Reading the report

One JSON line per request: `requestid`, `subjectid`, `subject_state` (missing/deleted), `status_before`,
`status_after`, `writes` (execute) or `planned_writes` (dry run), `discovery` and `approved` (components, contexts,
contexts by level, per component), `approved_ids` (context ids per component), `excluded_by_registry`, `tail`
(would-send list and the conditions), `exporter_ok` (dry run), `blast` (execute: counts that changed),
`provider_failures`, `events`, `send_attempt_lines`, `tripwire`, `new_rows`, `mem_peak_mb`, `seconds`, `outcome`.
The `start` line holds the guards, kill switches, event recorder facts, registry, manifest, backup facts, the full
snapshot, all-table counts (execute), restore markers, certificate issue ids, and with `--residuals` (always in
execute) the residual counts, the blast-radius baseline and the must-be-empty baseline. The `summary` line holds the
totals and the start/end comparison.

Added 2026-11-13 (dry run and execute; ids and counts only). Per request, per component:

| Field | What it is |
|---|---|
| `discovery_count`, `approved_count` | `count($contextlist)` of the discovery / approved collection |
| `discovery_full_ids`, `approved_full_ids` | `get_contextids()`: the full list, read before anything iterates it |
| `discovery_iterated_ids`, `approved_iterated_ids` | the ids a `foreach` visits (what `api.php:1214` and every iterating provider sees) |
| `iterator_truncated` | `{"discovery": {component: ids}, "approved": {...}}`: full minus iterated (core bug below). Empty `[]` when nothing was lost |
| `iterator_missing_ctx` | only when present: ids the iteration dropped because the context no longer exists |
| `truncation` | `discovery_components`, `discovery_contexts_lost`, `approved_components`, `approved_contexts_lost`, `missing_ctx` |
| `excluded_by_registry` | now from the FULL lists: sum over components of count(full discovery) minus count(full approved). It includes the contexts lost to the iterator, because the registry only ever sees the iterated list |
| `excluded_by_registry_by_component` | the same per component (non-zero only) |
| `excluded_by_registry_iterated` | the figure recorded before 2026-11-13 (iterated discovery minus approved): what the data registry itself excluded (0 while `defaults_set` is false) |

`approved_ids` is unchanged (it always was `get_contextids()` of the approved list; approved lists have no holes, so it
equals `approved_full_ids` and `approved_iterated_ids`). The start line also records `cert_issue_owners` (issue id =>
owner id) so `a4_verify` can give a file of a deleted issue to its subject. The summary line has
`excluded_by_registry_iterated` and `truncation` (requests with truncation, by subject state, components, contexts lost,
by component; approved lists truncated; missing contexts). Truncation is **recorded, never a trip or a rollback**, and
the approval is not changed. The console line per request shows `trunc N` (discovery contexts not iterated).

## Residue buckets: what the stock path leaves behind, and why (2026-11-13)

The first `a4_verify` of the canary (`reports/verify-canary.stdout.txt`) reported 4 must-be-empty violations:
certificate files 155, course completions 5, log rows 368, monthly stats 2. Investigation (workflow wf_a8cbb8ce-02a;
probes in `../investigate/`) found two stock-path causes and one checker bug. The rehearsal stays faithful to the stock
path and measures the first two; it does not work around them.

### Core bug: the contextlist iterator stops at the first duplicate context

- `privacy/classes/local/request/contextlist_base.php:65-67` `set_contextids()` is `array_unique($contextids)` without
  a reindex, so a duplicate id leaves a key hole. `:169-171` `valid()` is `isset($this->contextids[$this->iteratorposition])`,
  so every `foreach` ends at the first hole. `count()` (`:186-188`) and `get_contextids()` (`:74-76`) still hold the
  full list.
- Duplicates come from `contextlist::add_from_sql()` (`privacy/classes/local/request/contextlist.php:46-82`, one id per
  row of the provider's SQL, merged at `:80`): `logstore_standard` returns one id per log row
  (`admin/tool/log/store/standard/classes/privacy/provider.php:76-88`); `report_stats` calls `add_from_sql()` three
  times, once per stats table (`report/stats/classes/privacy/provider.php:94-117`).
- `admin/tool/dataprivacy/classes/api.php:1210-1233` builds the approved list by iterating the discovery list, so the
  approval of such a component is truncated, and its delete (`moodle_database_export_and_delete.php:113-120`,
  `report_stats` `:225-234`) never runs in the later contexts. The approved list itself has no holes
  (`approved_contextlist.php:49-53`, built from the iterated ids).
- Same code on the local 5.3 runtime (`contextlist_base.php` and `contextlist.php` byte-identical, the same loop at
  `api.php:1212-1214`) and, per the investigation, on upstream main.
- Measured: dry runs `reports/a4-dryrun-504-251-buckets.jsonl` and `reports/a4-dryrun-300-446-454-buckets.jsonl`:
  request 504 `tool_log` full `[6065, 1]`, iterated `[6065]`; 300 `tool_log` 11 contexts, 1 iterated; 446 and 454 lose
  `tool_log` and `report_stats` contexts; 251 has `tool_log` and `report_stats` data and no duplicate (nothing lost).
  Population probe (`../investigate/probe_iter_population.out`, read-only): 220 of the 278 pending deleted subjects
  are truncated in `tool_log` (3,574 subject-context pairs discovered, 311 iterated); 15,108 of their 17,421 own log rows in existing
  contexts would stay after the stock delete.

### Core gap: `core_course` never discovers most course completions

`course/classes/privacy/provider.php:70-84` finds a course only through
`completion/classes/privacy/provider.php:93-109` (`get_course_completion_join_sql()`): a `course_completion_criteria`
row of the course joined to the user's `course_modules_completion`, `course_modules_viewed` or
`course_completion_crit_compl` row. A course without completion criteria, or whose criteria the user never touched, is
never discovered, so its `course_completions` row (deleted only by `delete_completion()`,
`completion/classes/privacy/provider.php:276`) stays. Canary: 5 rows of subjects 444 and 1988
(`../investigate/probe_cc_residue.out`).

### Checker bug (fixed): certificate files

The files check used `itemid IN ({I})`, and `{I}` is the issue ids of ALL A4 subjects at the run's start, so it counted
the files of issues that unprocessed subjects still hold (155 rows, all issues that still exist). Now:
`component = 'tool_certificate' AND filearea = 'issues' AND ((itemid IN ({I}) AND itemid NOT IN (SELECT ci.id FROM
{tool_certificate_issues} ci)) OR itemid IN (SELECT ci2.id FROM {tool_certificate_issues} ci2 WHERE ci2.userid IN ({U})))`.

### How `a4_verify` buckets the residue

Context-scoped must-be-empty specs (`tool_certificate` issues and files, `mod_quiz` attempts and grades, `mod_scorm`
attempts, `core_grades` grades and history, `core_course` completions and module completions, `tool_log`,
`report_stats` daily/weekly/monthly) now count the rows of **every processed subject** and put each row in a bucket by
the context the provider's delete keys on (each `ctx` expression is checked against the delete code, file:line in
`a4_must_be_empty_specs()`): system context (certificate issues), the file's own context (certificate files), the
module context (quiz, scorm), the course context of the grade item (grades; a history row whose item is gone: the
subject's user context, the orphan path), the course context (completions, stats), the criterion's course context
(module completions), the row's raw `contextid` (log) and the file row's raw `contextid` (certificate files). For the
last two the delete keys on the context id itself, so `ctx` is the raw id and a separate flag says whether the context
row still exists (`ctx_missing`): a context removed after the run cannot move a leftover row out of `failure`.

The buckets are tried in this order (`a4_residue_bucket()`):

| Bucket | Meaning | Violation |
|---|---|---|
| `failure` | context in the subject's approved list for the component, ITERATED or FULL: the delete was asked to run there and the row is still there | **yes (exit 4)** |
| `not_approved_unbucketed_pre_guard` | report lines written before 2026-11-13 (the canary) have no full lists: `failure` uses their `approved_ids`, every other row lands here | no |
| `undiscovered` | the context no longer exists (`ctx_missing`), or the context is not in the subject's full discovery for the component (core gap above, orphan rows) | no, measured |
| `truncated` | context in the full discovery list, not iterated (core bug above) | no, measured |
| `registry` | context in the iterated discovery list, not approved (data registry) | **yes while `defaults_set` is false** in every execute start line (`registry_bucket_impossible`); otherwise no, measured |

Review fixes (2026-11-13, second round), all in `a4_lib.php` / `a4_verify.php`:

- `failure` also covers a context in the FULL approved list. `tool_log` (`moodle_database_export_and_delete.php:119-120`),
  `report_stats` (:229), `mod_scorm` (:292-323), `core_grades` (:775) and `tool_certificate` (:186) delete over
  `get_contextids()`/`get_contexts()`, the whole list; only `mod_quiz` (:383) and `core_course` (:256) iterate. Stock
  `api.php:1210-1230` builds approved lists from iterated unique ids, so full == iterated; any approved-list iterator
  hole in an execute request line is a violation (`approved_list_hole`): the recorded evidence would be inconsistent.
- `registry`: `api.php:1208-1225` approves every iterated context while `defaults_set` is false, so a row there means the
  recorded lists do not match what was approved. `a4_verify` reads `registry.defaults_set` from every execute start
  line and reports such rows as `registry_bucket_impossible` when all of them say false.
- `tool_log` and certificate files: `ctx` = the raw `contextid` plus a `ctx_missing` flag (above).
  `ctx_missing_by_bucket` and the total `ctx_missing_in_failure` show gone contexts that the approved list held.
- `cert_issue_owners` is merged from EVERY execute start line (union, earliest first); `cert_issue_ids` still comes from
  the earliest. With the canary report plus a main-run report, a file left by the main run is given to its subject
  instead of `failure_subject_unresolved` (the canary's start line has no owners).

Also per spec: `ctx_missing` (bucketed rows whose context is gone), `ctx_missing_by_bucket`, `failure_subject_unresolved`
(a file of a deleted A4 issue whose owner no start line records: counted as failure, conservative),
`subject_not_processed`, `by_subject`, `failure_rows` (first 200; `ctx_gone` marks a gone context), `ref`. Totals are in
`must_be_empty_buckets_total`; `must_be_empty_pre_guard_subjects` lists the pre-guard subjects; the result also has
`registry_defaults_set_by_start_line`, `registry_bucket_is_violation`, `approved_list_holes` and `cert_issue_owners`
(start lines, how many carry owners, issues with an owner). Specs that are not
context-scoped (Sentientia tables, `core_user`, `core_message`, `tool_messageinbound`) are flat counts as before. The
`course_modules_completion` check now covers only rows of modules that are completion criteria (what
`completion/classes/privacy/provider.php:251-260` deletes); before it counted every row in an existing course.

Canary, re-verified with the buckets (`reports/a4-verify-canary-buckets.json`, `.stdout.txt`): **exit 0, 0 violations,
`failure` 0**. All 8 processed subjects are pre-guard. Certificate files 0 (was 155); course completions 5, log rows
503 (the 368 from before plus 135 whose context no longer exists, which the old check filtered out), monthly stats 2 and
`grade_grades_history` 8 (orphans whose user context is gone) are all `not_approved_unbucketed_pre_guard`. A
classifier self-test (`../work-buckets/selftest-*`: the dry-run lines of 504, 251, 300, 446, 454 relabelled as
"deleted", nothing was deleted) put rows in `failure`, `truncated` and `undiscovered` as expected.

After the second-round review fixes (`../work-reviewfix/`): the dry run `--ids=504,251 --residuals` matches the earlier
one field for field (only timings differ); the canary re-verify is unchanged (exit 0, 0 violations, `failure` 0, 518
rows pre-guard, `ctx_missing` 143, `ctx_missing_in_failure` 0); self-test A (the builder's synthetic input) gives the
same buckets as before (failure 170, truncated 144, undiscovered 70). Synthetic inputs B to D (built by
`make_selftests.py`; nothing deleted) check the new paths: an approved `tool_log` hole for subject 450 moves its 51
truncated log rows to `failure` and raises `approved_list_hole` (1 request, 10 contexts); `report_stats` removed from
251's approved lists puts 2 rows in `registry` and raises `registry_bucket_impossible` (not raised when a start line
says `defaults_set` true: input C); a second start line gives 359 issue owners from 1 of 2 start lines; a gone context
(683, from the read-only `probe_gone_ctx.php`) added to 450's approved lists keeps its 38 log rows in `failure`
(`ctx_missing_in_failure` 38) where the earlier code put them in `undiscovered`.

### Decision for Nitin

A core fix (for example `array_values(array_unique(...))` in `set_contextids()`) would change what the stock path deletes.
ADR-033 forbids core edits on 5.3, so the fix needs Nitin's decision (an ADR exception plus a `docs/core-mods/` record, or
an upstream report and waiting for it, or accepting the residue and handling it with a separate, explicitly non-stock
cleanup). The rehearsal does not fix or work around it. The `core_course` discovery gap is the same kind of decision.

## Dry-run result (2026-11-13, after the review fixes)

`reports/a4-dryrun-full-v2.jsonl` (+ `.stdout.txt`; `.stderr.txt` and `.trace.jsonl` empty). All 515 requests,
652 s wall clock (416 s of request time), exit 0. Run with the php guard flags (checked: 15 functions gone,
`allow_url_fopen` off, SMTP dead), the event recorder (127 observers replaced, 41 of them external; self-test
passed), `MEMORY_EXTRA` (512M; peak per request 68 MB). The earlier pre-review run is `reports/a4-dryrun-full.jsonl`.

- Subjects: 286 deleted (outcome `would_delete`), 229 missing (`not_processable_missing_user_row`, TypeError
  reproduced for all 229). Refused: 0 (no live subject).
- **0 provider failures in discovery** for all 515, so discovery would send 0 DPO messages. Delete-phase failures
  (each one `notifyexceptions` to every DPO on live) are unknown until the execute canary: the dry run never calls
  `delete_data_for_user()`.
- **Message tail:** run for all 286 with the dpo set as the approval sets it: would send **nothing**. Conditions for all
  286: subject deleted (no subject e-mail, :240), requester is a site DPO under the admin cron user (no requester
  message, :268), `deleteuser` false (no `delete_user()`).
- **Events recorded: 0; send-attempt output lines: 0; observer table intact** at the end.
- Approved collection over the 286: 6,741 component-context pairs, 1,909 distinct contexts counted per request
  (system 286, user 19, category 27, course 537, module 1,040). Discovery pairs for the deleted subjects: also 6,741,
  so **0 excluded by the data registry** (`defaults_set` = false, `requireallenddatesforuserdeletion` = 1,
  `allowfiltering` 0, `dporoles` unset). Missing subjects: 3,673 discovery pairs.
- Components that would delete, by number of requests: 14 Sentientia providers plus `mod_lti` and
  `report_infectedfiles` 286 each (system context for every subject), `tool_log` 271, `core_grades` 138, `mod_quiz` 137,
  `tool_certificate` 102, `core_course` 87, `report_stats` 57, `mod_scorm` 47, `mod_url` 29, `core_user`,
  `tool_dataprivacy`, `tool_messageinbound` 19 each, `mod_resource` 13, `local_sentientia_users` 4, `core_message` 2,
  `local_sentientia_learningpath` 1, `local_sentientia_request` 1. All 32 are in the manifest. The canary ids cover 32
  of 32 (the first five alone 19).
- DPO list exporter: fails for all 229 missing subjects and for 6 deleted-subject requests (502, 505-508, 515;
  `dml_missing_record_exception`, consistent with the 6 `requestedby = 0` rows in the maps); works for the other 280.
- Manifest: `state/provider-manifest.json` written at 02:34:38Z (594 files, 541 provider components); the full run
  matched it.
- Blast-radius count for one subject (scoped, request 235 with quiz attempts): about 1.1-1.7 s. The `--residuals`
  test run (`reports/test5-dry-residuals.jsonl`, 2 requests) ran the run-level baseline over all 515 subjects
  (56 specs, 6.4 s, no SQL error) and the must-be-empty baseline (35 checks, no SQL error; before any deletion they
  show the rows the execute must remove, e.g. 367 certificate issues, 157 certificate file rows incl. directory
  entries, 349 quiz attempts, 17,923 actor log rows in existing contexts, 5 org_member rows).

**Read-only proof.** Inside the run: `tx_read_only = 1` at start and end, the zero-row self-test refused, 20 periodic
plus start and end all-table snapshots: no max id grew in any of the 717 tables, the task/config checksums did not
change, snapshot delta `[]`. Separate `--counts-only` processes before (`reports/counts-before-v2.jsonl`,
02:44:27Z) and after (`reports/counts-after-v2.jsonl`, 02:55:51Z):

| Table / check | Before | After |
|---|---|---|
| `mdl_notifications` (rows / max id) | 4,248 / 1,665,968 | 4,248 / 1,665,968 |
| `mdl_messages` | 0 / 0 | 0 / 0 |
| `mdl_message_popup_notifications` | 3,693 / 1,639,057 | 3,693 / 1,639,057 |
| `mdl_message_email_messages` | 0 / 0 | 0 / 0 |
| `mdl_task_adhoc` (rows / max id; messaging-class tasks; dataprivacy tasks) | 44 / 16,431; 41; 0 | 44 / 16,431; 41; 0 |
| `mdl_tool_dataprivacy_request` by status | `t2.c1.s2` = 515, max id 515 | `t2.c1.s2` = 515, max id 515 |
| `mdl_tool_dataprivacy_ctxlst_ctx`, `_contextlist`, `_rqst_ctxlst` | 0, 0, 0 | 0, 0, 0 |
| Webhooks: `local_sentientia_api_whdel`, `_api_whsub` | 0, 0 | 0, 0 |
| `local_sentientia_send_log`, `_push_log`, `_notif_log`, `_email_log`, `_lb_notify_log` | 0, 6, 4, 0, 0 | 0, 6, 4, 0, 0 |
| `local_compliance_email_log`, `local_emaillogs` | 129, 14,202 | 129, 14,202 |
| `events_queue`, `events_queue_handlers`, `forum_queue`, `sms_messages` | 0, 0, 0, 0 | 0, 0, 0, 0 |
| Live users; log store max id; tasks running; `lastcronstart` | 2,907; 2,596,071; 0; unchanged | 2,907; 2,596,071; 0; unchanged |
| All 717 id tables: total rows; tables whose count or max id changed | 5,570,941; - | 5,570,941; none |
| Checksums `task_scheduled`, `task_adhoc`, `config`, `config_plugins` | - | identical |

## Cutover runbook: if a DPO approved these 515 on LIVE through the UI

Sentientia on 5.3 with production data, real SMTP, no `noemailever`, `dporoles` unset (so DPOs = all site admins).
From reading the code and from the dry run's message-tail mirror, not observed on live:

1. **Approval itself sends nothing** (a session flash only). But the DPO list page builds every row with
   `data_request_exporter`, which loads the subject and the requester with `MUST_EXIST`
   (`external/data_request_exporter.php:137`, `:143`). The dry run ran the exporter for all 515: it throws for the 229
   missing subjects and for 6 more requests (502, 505-508, 515). A list page holding any of those 235 rows cannot
   render. Bulk approval through the web service does not use the exporter.
2. **The 286 deleted subjects** are processed by cron: provider deletions, status 10, no subject e-mail (subject is
   deleted, :240), no requester message (cron runs as admin, so `is_site_dpo()` is true, :268), no `delete_user()`.
   The dry run ran that tail code with these conditions and recorded no send. **Every delete-phase provider exception
   sends `notifyexceptions` to every DPO**: the dry run saw 0 discovery failures; delete-phase failures are unknown
   until the execute canary.
3. **The 229 missing subjects** fail 12 times over about 34 h and then send `failedtaskmaxdelay` to every site admin:
   229 x 4 = **916 notifications, as real e-mail**. Each retry also re-sends any provider exception to the DPOs.
   Nothing is deleted. On 5.3, `adhoc_task.php --failed` reruns exhausted tasks and repeats the notifications.
   They cannot be rejected from the UI list (the exporter fails); use the `tool_dataprivacy_bulk_deny_data_requests`
   web service or a CLI call to `api::deny_data_request()` (status 7, no sends).
4. **Whatever else is in the cron queue runs too** (locally: 41 login notifications). Set
   `$CFG->task_concurrency_limit['\tool_dataprivacy\task\process_data_request_task'] = 1` (live may otherwise run 3
   at once, `task_adhoc_concurrency_limit` = 3; the rehearsal is sequential).
5. **The data registry decides what is deleted.** These results hold only while the registry has no site defaults
   (`defaults_set()` = false, recorded in every start line and in the manifest; `--execute` refuses if it changed).
   If a DPO sets site defaults before approving, `api.php:1206-1222` keeps only expired or unprotected contexts and
   the system and category contexts are never deleted: all certificates, all Sentientia system-context data and the
   27 category contexts would stay, and course/module data would go only where expired.
6. Before cutover: decide the 229 (reject; or leave), set `dporoles`, review the ad hoc queue, decide whether the
   deleted users' `user` rows need a separate scrub, check in the Stage B copy whether the 229 user ids 3-236 (one
   contiguous block, maybe an import artifact) are missing on production too, and check the live `tool_monitor`
   rules (0 locally; the rehearsal forces the monitor off). Live BizLMS 4.1.2 was not analysed: do not approve there.
7. **5.1 vs 5.3.** The dataprivacy path is byte-identical between the local 5.1 and the local 5.3 copy
   (process_data_request_task, privacy manager, request helper, contextlist classes, approved_contextlist, the
   exporter, the requests table, failed-task callbacks). Core providers differ: on 5.3 `core_grades`
   `delete_data_for_user` also deletes `grade_outcomes_modules` rows for module contexts (and discovery reads that
   table); `core_user` (metadata only), `mod_quiz` (export only) and `api.php` (purpose/category getters) differ in
   ways that do not touch deletion. Before cutover, repeat the dry run, and ideally the canary, on the local 5.3
   runtime with the same data.

## Open questions for Nitin

1. **The 229 missing subjects.** The real code cannot process them. Options: reject them (status 7, no sends,
   through the web service or CLI), leave them, or build a separate, explicitly non-UI cleanup for their leftover
   rows (713 + 877 log rows, 9 `block_devicels`, 2 `block_ls_coursetimestats`, 4 `local_userdata`).
2. **User rows are not purged by this path.** `core_user` keeps username, first and last name and e-mail by design and
   only rewrites rows that still have a user context (19 of 286). The 37 BizLMS `open_*` columns are unknown to every
   provider. BizLMS tables (`local_emaillogs`, `local_userdata`, `local_rating`, `local_like`) and LearnerScript
   tables have no provider. A4 does not "purge all remaining personal data" unless a separate scrub is added.
3. **Full erasure instead of DPDP anonymisation.** `tool_dataprivacy` calls `delete_data_for_user()`, never the
   Sentientia `anonymise_data_for_user()`. Here that deletes 1 learning-path enrolment, 367 certificate issues and
   their 80 files (their verification codes stop working; locally only the `files` rows go, since `filedir` holds
   none of the content; on live the PDFs go to trashdir). Accept for the rehearsal?
4. **Other people's rows.** `local_sentientia_core` sets `managerid = 0` on 5 org-member rows of 5 live employees whose
   manager is one of the deleted subjects. Correct by design; confirm it is acceptable locally.
5. **`--txn` default.** `request` rolls a whole request back on any failure or trip (status 2 again); `none` is the
   exact cron behaviour (a failure is committed as status 10). Which one for the real run?
6. **Web traffic during `--execute`:** maintenance mode, Apache stopped, or accept (`--web=`)?
7. **Restore rehearsal** into a scratch schema `moodle_a4check` before the first `--execute`: go ahead?
