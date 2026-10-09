# Stage B rehearsal kit

Scripts that run the Stage B rehearsal on the Linux target box: a fresh live backup, restored, upgraded in two hops
(4.1.x to 4.5.x to the 5.x Sentientia package), repaired, switched to the Sentientia theme, then the ADR-032 BizLMS import
with 100% parity against a baseline taken before any upgrade.

It is the scripted form of `moodle-enhancement/docs/cutover/MIGRATION-REHEARSAL-RUNBOOK.md`. The first local rehearsal
(2026-09-30 to 2026-10-07, on a Windows workstation, MariaDB 10.11) was a handful of hand-run scripts; the kit ports what
they learned and makes it repeatable on the real target (PHP 8.3, MySQL 8.4 or MariaDB 10.11). **It is DRY by default**:
without `--execute` every script prints what it would run and changes nothing.

Nothing in the kit touches production. That is enforced, not promised: see "What it refuses".

## Quick start

```bash
cp tools/rehearsal/rehearsal.env.example tools/rehearsal/rehearsal.env     # edit: paths, database, wwwroot, PRODUCTION_DB_ENDPOINT (required: live's database host)
chmod 600 tools/rehearsal/rehearsal.env
( umask 077; read -rsp 'Database password: ' p; printf '%s\n' "$p" > /etc/rehearsal/db.pass; unset p )   # not in the shell history

bash tools/rehearsal/run_all.sh --list                    # the steps and what each cites in the runbook
bash tools/rehearsal/run_all.sh                           # DRY: print the whole plan, change nothing
sudo -u www-data bash tools/rehearsal/run_all.sh --execute
sudo -u www-data bash tools/rehearsal/run_all.sh --execute --from 05     # after fixing a failed step
```

Run it as the web user (`www-data`), as Moodle's own CLI tools are meant to be run: every file it writes is then owned by
the user that needs it, and `WEB_USER` in the env file makes the preflight check it. Each step is a separate script and
can be run alone (`bash tools/rehearsal/05_repairs.sh --execute`); `run_all.sh` only runs them in order, stops at the
first failure, and always finishes with the summary. One `--execute` run per `REHEARSAL_WORK` at a time: `run_all.sh` takes
`REHEARSAL_WORK/.run.lock`, and a step run alone takes it too (exit 3 when it is held; the lock is per work directory: two work directories on the same database are NOT locked against each other, so give every rehearsal database one `REHEARSAL_WORK` and never run steps of two work directories on it at once). The lock directory holds the pid of the run
(`pid`) and, while a step that `run_all.sh` started is running, the pid of that step (`step.pid`); the refusal names both and says
whether each is alive. **Remove the directory only when none of them is alive**: a step outlives a `run_all.sh` that was killed with
`KILL`, and a second run in the same work directory writes the same database and state.
A `TERM`, `INT` or `HUP` sent to `run_all.sh` alone does not interrupt the step that is running and does not release the lock under
it: `run_all.sh` waits for that step, starts no further step (the summary included; a signal that arrives in the instant between two
steps starts nothing either) and exits 143 (`INT`: 130, `HUP`: 129). `USR1`, `USR2`, `ALRM`, `VTALRM`, `XCPU` and `XFSZ` are
handled the same way (exit 128+n): bash runs the EXIT trap with status 0 for each of them, so an untrapped one used to end `run_all.sh`
with its lock released under a running step, and to record a step as `ok`. `SIGPIPE` (the death of the `tee` a step writes its log
through) is only noted and never ends a step as ok: a step that saw it and no other signal ends as `fail` with `rc=141` and `signal=PIPE`. A signal sent to a **step** (to its pid, not its group) is
acted on only when the command it is running (`php upgrade.php`, the restore, the import) has returned: the step then writes
`state/NN.status` as `status=fail`, `rc=128+n`, `signal=NAME`, releases its lock and exits 128+n, so a step is **never recorded ok on a
signal** and the lock is never released under a command that still runs. A step also writes `status=running` when it starts, so one
that dies without a trap (`KILL`, a power cut) leaves a status nothing reads as ok (`step_done_ok`, the preflight and the summary all
refuse it; the summary prints it as DID NOT FINISH). **To stop a step and what it runs, signal the whole process group** (`kill -TERM
-- -PGID`; Ctrl-C and an ssh hangup do the same): the command ends at once, and the signal also kills the `tee` that `run_all.sh` and the
step write their output through, so their handlers log straight to the log files when the pipe is gone (a handler that wrote to the dead
pipe used to die of `SIGPIPE`: exit 141, `status=running`, `.run.lock` left behind, no STOPPED line; and a `SIGPIPE` that is handled
before the `TERM` would have been recorded instead of it, so `SIGPIPE` is only noted). The result is the same as for a
signal to the step alone: the step records `fail` with `rc=128+n` and `signal=NAME`, `run_all.sh` writes its STOPPED line to
`logs/run_all.log`, exits 128+n (143 for `kill -TERM`) and releases the lock. **Cost of a signal to step 01 during the database load:** it
is acted on when the load has returned, and a load that returned 0 is then not verified (the in-flight table stays), so the database must
be dropped and restored again (the load of live's dump takes tens of minutes): do not signal step 01 during the load unless that is what
is wanted. Run the rehearsal under `tmux` or `screen`: a dropped ssh session sends `HUP` (sudo relays it).

## What it refuses (step 00, and every step re-checks the policy when it loads the env)

* a database that is not on the explicit allow-list `REHEARSAL_DB_ALLOWLIST`; a name with `prod` or `uat` anywhere in it
  (production's database is `airpayprod`, UAT's `sentientia_uat`: no underscore boundary is needed), `live` as a word, or a
  system schema (`moodle`, `mysql`, ...) is refused even if it is listed; and a database **server** that holds the schema
  `airpayprod` (or one named in `FORBIDDEN_SERVER_SCHEMAS`) is refused. That scan sees only the schemas the rehearsal login holds
  a privilege on, unless it has the global `SHOW DATABASES` privilege (MySQL and MariaDB hide the rest from
  `information_schema.SCHEMATA`): on any server that is not the rehearsal's own, grant the login `SHOW DATABASES`, or the scan
  cannot see `airpayprod` there (the name guard, the allow-list and the kit marker do not depend on it);
* any production hostname (`PRODUCTION_HOSTNAMES`: the live and UAT sites; substring match) in the database host, the
  wwwroot, the paths, the env file's host settings, or any string of a `config.php` already on the box. The live **database
  endpoint** (`PRODUCTION_DB_ENDPOINT`, dbhost of live's config.php) is required with `--execute` and joins that list, so a
  `DB_HOST` copied from live's config cannot get through;
* a database or moodledata that does not carry the **kit marker**: step 01 stamps the database (a `{config}` row) and the
  moodledata (a file) with a random restore id, and every writing step (02 to 11) refuses a database or directory without
  it, so an allow-listed name on the wrong server, or UAT's database, is never written to. A database restored by hand is
  stamped only on `RESTORE_DONE_BY_HAND=<its name>` with `RESTORE_DB_DUMP` unset (both set is refused outright: the first stays
  in `rehearsal.env` until cleared). **A restore the kit started and did not finish is recorded in the database it was writing to,
  not in the work directory:** after the dump has been checked, and immediately before it loads it, step 01 creates the table
  `zz_rehearsal_restore_inflight` (restore id, dump path, start time; outside the Moodle prefix) in the target database, and drops
  it only when the restore is verified complete, just before it stamps the database. A database that holds that table is a
  partial copy and is refused **whatever `RESTORE_DONE_BY_HAND` says and whatever `REHEARSAL_WORK` or env file the
  run uses**; only `DROP DATABASE` (then create it empty) clears it. The table is read by its own error, never from one count:
  the server's error 1146 is the only "absent", a read that ran without an error and said so is "present", and anything else (no
  answer, a lost connection, another error) is asked again twice and then "cannot tell", which is refused. The `CREATE TABLE` is
  also the claim on an empty database: a second restore into
  it (a second run, another work directory) finds the table, or finds the database no longer empty right after it claimed it,
  and stops without writing anything. The earlier rehearsal's `state/` moves to `archive/` only after that claim is taken, so a
  run that misreads a finished database as empty stops with `state/` in place. A dump that names the table (it was taken from a
  partial copy) is refused with the other unsafe statements. Nothing about an unfinished restore is kept in `state/`.
  **The moodledata has the same record:** before the unpack of `RESTORE_MOODLEDATA_ARCHIVE` writes anything, step 01 creates the file
  `MOODLEDATA/.rehearsal_unpack_inflight` (restore id, archive, start time), and removes it only when the unpack has returned success
  (and the file is still the one it wrote: an archive made from an unfinished unpack carries its own and is refused). A moodledata
  that holds the file, or whose marker file records an `archive=` line and no `unpacked=` line, is a partial copy: step 01 (before it
  looks at the database), step 00 and the gate of steps 02 to 11 refuse it **whatever `RESTORE_MOODLEDATA_ARCHIVE` (even unset),
  `RESTORE_MOODLEDATA_BY_HAND` or `REHEARSAL_WORK` say**. Empty the directory (or point `MOODLEDATA` at a new, empty one) and run
  step 01 again, or unpack the archive by hand into an EMPTY directory and use `RESTORE_MOODLEDATA_BY_HAND` (a hand unpack has no
  check of its own: verify the archive's SHA-256 against the one taken where it was made, and that `tar` ran to the end of a COMPLETE
  archive, because a tar cut at a member header also exits 0; the file store gate's size check below catches a content file cut
  inside, from any route). **A moodledata archive is proven whole before the database is touched:** with `RESTORE_MOODLEDATA_SHA256`
  (the checksum from the live backup's manifest) its SHA-256 must match, and that is the whole proof (recorded in `state/kv`
  `restore.moodledata_sha256`, `restore.moodledata_proof=sha256`). **An uncompressed `.tar` REQUIRES the checksum and is refused
  without it** (round 7): nothing inside a plain tar can show that every byte of it arrived. A copy that stopped part way (a pre-allocated
  or segmented download, a file system that kept the size and lost the data) is full size and ends in zeros, GNU tar takes two zero blocks
  where a header is due for the end of the archive and exits 0, and the members after the zero-filled region are simply not unpacked, so
  the last 1024 bytes cannot prove anything. A compressed tar (`.gz`, `.bz2`, `.xz`, `.zst`) is read to its end by its decompressor, which
  fails on a cut or zero-filled stream, and its last 1024 bytes must be the two zero blocks that end every tar (`restore.moodledata_proof=tar-end`):
  GNU tar unpacks a tar cut exactly at a member header with exit status 0 and no message, and a `.tar.gz` written by a `tar` that
  died is a valid gzip file around a cut tar (`gzip -t` passes it). A `.zip` is checked by `unzip` itself while it unpacks: its central
  directory (at the end of the file) and a CRC-32 per member, which fail on a cut or zero-filled zip (`restore.moodledata_proof=format`).
  **A checksum that is set is always checked:** before an unpack, and also on a re-run where `filedir/` is already there and on a new
  restore that reuses the finished unpack (the proof is recorded again), and a checksum without `RESTORE_MOODLEDATA_ARCHIVE` is refused (there
  is no archive for it to prove). A refused archive costs nothing: no restore, no claim, no stamp, no unpack. The archive's path, size and
  mtime are read again after the unpack, and an archive that changed (still being copied, re-synced) leaves the in-flight file in
  place. The gate of steps 02 to 11
  also refuses a database that holds the in-flight table although it carries the marker (a dump or snapshot of a stamped, unfinished copy).
  A populated moodledata without the marker is refused too, unless
  `RESTORE_MOODLEDATA_BY_HAND=<its path>` (a statement of its own: the database statement does not cover the directory) and
  `sessions/` and `localcache/` show no write in the last 30 minutes (a running site, UAT's for one, is never adopted).
  **One rehearsal, one moodledata:** a new restore into an empty database refuses a moodledata that an earlier rehearsal ran in
  (its role-9 state file, caches, sessions and cron files would carry over) unless it is the finished unpack of the same
  `RESTORE_MOODLEDATA_ARCHIVE` that no step after 01 has used (that fact is written to `restore-ids.log` when the new restore
  moves the earlier state to `archive/`, so a new restore that dies and is retried still refuses the used dataroot); empty it or
  point `MOODLEDATA` at a new directory. A named
  `RESTORE_MOODLEDATA_ARCHIVE` is never ignored: with a filedir already present it must be that archive's unpack (the marker
  file records the archive's path, size and mtime, and that the unpack finished), or step 01 stops;
* a copy that step 01 **stamped but did not clear** (round 7). Step 01 stamps the database and the moodledata right after the restore, long
  before its file store gate and its neutralisation (the SMTP credentials wiped, `cron_enabled = 0`, the restored OAuth2 tokens blanked),
  so a stamped copy is not a cleared copy: a step 01 that failed at the gate, or was killed before the gate or the neutralisation, used to
  leave a stamped copy that steps 02 to 11 accepted (live's OAuth2 refresh tokens then stayed usable by the step 11 cron cycle). The last
  act of a step 01 that reached its end is a record that it finished for this restore id: the `{config}` row `rehearsal_kit_step01_ok`
  (it travels with the data, so a snapshot or a dump taken before step 01 finished does not carry it) and `state/kv/restore.verified`;
  step 01 removes both when it starts on a copy it already stamped. `require_kit_marker`, the gate every step from 02 to 11 starts with, refuses
  the copy unless `state/01.status` is ok AND both records hold the restore id (a record that cannot be read is "cannot tell", refused), and
  its message names step 01 and `run_all.sh --execute --from 01`. The preflight refuses a run that starts after step 01 (`--from 02` and
  later, or `--only` without 01) on such a copy, before any step; a run that goes through step 01 only warns, because step 01 is what
  finishes the copy;
* a `config.php` that points at another database or host, names another wwwroot or dataroot, or lacks
  `$CFG->noemailever = true` (the restored dump holds real e-mail addresses: the May 2026 incident, 151 e-mails);
* a scheduler line that runs THIS rehearsal's Moodle cron (a crontab, `/etc/crontab`, `/etc/cron.d` or systemd timer line
  naming `CODE_45_DIR` or `CODE_5X_DIR` as a whole path, with or without a slash after it, as in `cd /srv/rehearsal/moodle5 &&
  php admin/cli/cron.php`; another site's cron, `moodle5-uat` for one, is noted, not refused) and, once step 01 has set it, a
  database whose `cron_enabled` is not 0, which is the guard that holds whatever a scheduler does;
* with `BIZLMS_PRODUCTION_FLAG=1` (the cutover form, where the snapshot is the only way back), a hop or the import that has no
  restore point: neither `SNAPSHOT_HOOK` nor `SNAPSHOT_TAKEN=<label>` (before-hop-1, before-hop-2, before-import, or all). Without
  the flag a missing hook is a reminder only, and the step starts in the same second;
* a hop 2 that would delete activities (step 04, before anything is written): the Moodle 5.0 upgrade uninstalls `mod_survey` and
  `mod_chat` when their code is not on disk, and uninstalling deletes their activities and data. The step counts them first and
  stops while the package carries no code for a type that has any (see "What the first local rehearsal taught");
* Windows paths, one directory for both code trees, a code tree inside the other, a password file readable by others,
  an env file writable by others, running as anyone but `WEB_USER`.

The `config.php` the kit writes (`lib/make_config.sh`, the port of `make_config.py`) carries a **REHEARSAL GUARD** that
exits on every load, CLI or web, when the database is not on the allow-list or the database host or wwwroot names a
production host, and sets `altcacheconfigpath` to the kit's own directory (the restored `muc/config.php` names live's cache
stores; step 01 moves it aside too). The kit never overwrites a `config.php` it did not write, never drops or truncates
anything, restores only into an EMPTY database, and never flips a feature flag.

**The kit is for REHEARSAL. It is not the cutover procedure as it stands.** The cutover's `wwwroot` is the production one,
which `PRODUCTION_HOSTNAMES` refuses by design; making the same scripts run there means taking the production host off that
list, which switches the main guard off. A cutover mode needs its own, separately guarded design (and a decision about
the marker, the allow-list and who may run it); until then the freeze, the restore, the hops and the import of cutover day
are run by hand from the runbook, using what the rehearsal recorded (the decisions hash, the timings, the restore point).

## The steps

| Step | Script | What it does | Runbook / plan |
|---|---|---|---|
| 00 | `00_preflight.sh` | The refusals above. Changes nothing, even with `--execute`. | runbook Inputs, step 1; plan 1.3, 4d.3, 8-2 |
| 01 | `01_restore_check.sh` | Restores the dump into an empty database and unpacks the moodledata if asked (`RESTORE_*`). The dump is **refused** when it holds `USE` / `CREATE DATABASE` / `DROP DATABASE` (it would reach another schema, whatever the allow-list says; the client also runs with `--one-database`), a `SET @@GLOBAL.GTID_PURGED` (a server-wide setting: dump with `--set-gtid-purged=OFF`), or has no `-- Dump completed` trailer (an aborted `mysqldump` restores as a silent partial copy), or names a MySQL 8 collation (`utf8mb4_0900_*`) when the server is MariaDB; the restore stops at the first error (pipefail), and a restore that did not finish leaves the table `zz_rehearsal_restore_inflight` in the database (created just before the load, dropped when the restore is verified complete), which makes every later run refuse that database, whatever `RESTORE_DONE_BY_HAND` says and whatever work directory it uses (drop the partial database and create it empty; a kit restore or a hand restore then follows: restore by hand, then run step 01 with `RESTORE_DB_DUMP` unset and `RESTORE_DONE_BY_HAND=<its name>`; `RESTORE_DONE_BY_HAND` together with `RESTORE_DB_DUMP` is refused outright). **Stamps the database and the moodledata with the restore id** (see "What it refuses"); the marker file also records which archive was unpacked and that it finished, and an unpack that did not finish leaves `MOODLEDATA/.rehearsal_unpack_inflight` (created before it writes anything, removed only when it is verified complete), which makes every step refuse that directory whatever any setting says (empty it and run step 01 again). A dump that names the in-flight table is refused with the other unsafe statements. A new restore moves the earlier rehearsal's state, reports, baseline and cache configuration to `archive/`. Blanks the stored OAuth2 system-account tokens when the audit finds any. Checks release (`SOURCE_RELEASE_REGEX`, live is 4.1.x), active users, the BizLMS `open_path` substrate. **File store gate:** every distinct `files.contenthash` with content must exist at `filedir/ab/cd/<hash>`; missing ones go to `reports/filedir-missing.txt` and stop the step (a DB-only restore 404s every SCORM package). Then neutralises the restore: wipes `smtphosts/smtpuser/smtppass` and the push-service key (airnotifier), sets `cron_enabled = 0`, moves the restored `muc/config.php` aside, writes the restored mail backlog audit (input I-11) and an audit of the other outbound settings (counts only). With `LIVE_BASELINE_FILE` it compares the restored copy with live's own baseline (isolates restore loss). | runbook 1; plan 4c, 4f-a |
| 02 | `02_source_baseline.sh` | `source_baseline.php` on the restored 4.1.x copy, before any upgrade. Refuses on an upgraded copy. An existing baseline is re-verified, never retaken (and a baseline taken with another metrics version of the tool is refused, exit 3: delete it and take it again). `LIVE_BASELINE_FILE` makes live's own baseline the baseline of record. Records the SHA-256 of the baseline and of the tool, and refuses a baseline whose `tool.sha256` is not this checkout's `SOURCE_BASELINE_PHP` (with `LIVE_BASELINE_FILE` the baseline came from elsewhere). | runbook 0; plan 4a, 5.1; ADR-032 Parity hooks |
| 03 | `03_hop1_to_45.sh` | Hop 1 on a clean Moodle 4.5 core, **BizLMS code off disk** (checked against `lib/bizlms_plugins.txt`), config with the DB guard, PHP 8.1-8.3 and extensions, restore point, `upgrade.php --non-interactive` timed, release and pending checks, the plugins "missing from disk" listed, then **parity after hop 1** (`source_baseline.php --compare`, exit 0). | runbook 3; plan 0, I-4 |
| 04 | `04_hop2_to_5x.sh` | Hop 2 on the Sentientia package in its own directory: SHA-256 gate, the package's CLI files present, no leaked `config.php`, BizLMS code and `local/airpay_ratings` off disk, PHP 8.3, `max_allowed_packet >= 64M`, **the activity check** (the count of `mod_survey` and `mod_chat` activities the 5.0 upgrade would delete because the package has no code for them: any above zero stops the step before the snapshot), restore point, upgrade timed, Sentientia plugins installed, `enrol_sentientiasub` not enabled, the tree manifest recorded, **the package's `source_baseline.php` must be the file the baseline names as the one that took it** (`tool.sha256` inside the baseline; no override: the tool refuses the comparison itself), then **parity after hop 2** (`migration_parity_check.php --compare`; the `message_provider_defaults` invariant alone, which step 05 repairs, is not a stop here). | runbook 2-3; plan 0, 4d, 4e |
| 05 | `05_repairs.sh` | CLI maintenance on; `repair_task_registrations.php` dry run then `--apply` (the message preference check must report 0 problems); the tenant seed and tenant/org parity checks (`TENANT_CHECKS`); the ADR-032 **capability repair**: inventory, check against `docs/cutover/bizlms-capability-allowlist.json` (exit 0 required), `--apply --confirm=<fingerprint>`, check again. | runbook 4a-4c, 4e; ADR-032 Capabilities |
| 06 | `06_adr031_roles.sh` | The four ADR-031 scripts from `tools/uat/` in **target mode** (`--target=<wwwroot> --config=<config.php>`): predeploy probe, role-9 core caps (dry run, apply; the apply is skipped on a re-run when its state file exists and the dry run finds nothing left to do, because the script refuses a second apply), cross-tenant role (dry run, apply), read-only web-service smoke (`ERROR=0` required). | runbook 4f; plan 4f-f |
| 07 | `07_theme_switch.sh` | `cfg.php --name=theme --set=sentientia` (production's epsilon is not in the package), theme overrides checked, purge, landing posture printed. | runbook 4g; plan 8-7 |
| 08 | `08_import_guard.sh` | Arms the ADR-032 guard: `cron.php --disable`, CLI maintenance on, `noemailever` confirmed, the standard log store, no restored task row marked running, `bizlms_import_armed_until`; then `import_bizlms.php --status` must show every fact the guard needs. | runbook 5a; ADR-032 Gating |
| 09 | `09_import.sh` | The data-intact gate (`migration_parity_check.php --compare`, exit 0), `--preflight --all`, a dry run with `--report` (**records the decisions hash** from `meta.decisions_hash`), the restore point (`SNAPSHOT_HOOK before-import`), `--all --apply --confirm=<fp> --decisions=... --expect-decisions-hash=<hash> --report=...`, `--verify --all`. All 19 importers. **A re-run knows where it stands** from the kit's record and from the newest apply run in the database: an unfinished run is continued with `IMPORT_APPLY_MODE=resume` without running the gate again, an exit 2 awaiting Nitin's written acceptance is recorded first and judged again. | runbook 5, 5a; ADR-032 CLI |
| 10 | `10_parity_compare.sh` | `migration_parity_check.php --compare ... --after-import --decisions=... --expect-decisions-hash=... --run=<id> --report=<apply report>`: everything equals the source baseline except what the import's own records explain. | runbook 5a; ADR-032 Parity hooks 2, 4 |
| 11 | `11_cron_cycle.sh` | One cron cycle (`cron.php --force --keep-alive=0`) under `noemailever`, timed, with the restored backlog before and after, the e-mails `noemailever` swallowed counted (best effort: the count reads the cron output), the scheduled tasks that phone home switched off in the rehearsal database for the cycle, and the **`transfer_question_categories` task timed** from `{task_log}`; `checks.php`; the parity once more (informational). | ADR-032 Cutover slice 7; plan 8-2, 9 |
| 12 | `12_summary.sh` | `reports/summary.md`: steps and seconds, the I-4 hard-down estimate (restore, baseline and every timed operation of steps 03 to 10), the parity checkpoints, the evidence hashes, accepted unproven items, the seven rollout-gate items and who proves each. Runs even after a failure. | runbook 7; plan 9, 10 |

Exit codes of the steps: 0 ok, 1 a gate failed, 2 not proven, 3 usage or a refusal. The parity and import tools use the
same four codes. **Exit 2 is a stop.** ADR-032 allows it only with Nitin's written acceptance: set `ACCEPT_UNPROVEN=1` and
`ACCEPT_UNPROVEN_REF=<where the acceptance is written>` and re-run the step; the reference is printed in the summary.

## Where things go (`REHEARSAL_WORK`)

```
logs/NN-name.log        every line timestamped (UTC); logs/run_all.log; logs/timings.tsv = time, step, operation, seconds, rc
state/NN.status         ok / unproven / fail per step, seconds, warnings; state/kv/* the values the later steps read
reports/                every command's output, the import dry-run and apply reports (JSON + CSV), filedir lists, summary.md
baseline/               source-baseline.json (keep it with the change ticket)
conf/                   the database settings the baseline tool reads (mode 700 directory)
adr031/                 the four ADR-031 scripts copied from tools/uat/
muc/                    the rehearsal's own cache configuration (altcacheconfigpath), so live's restored muc/config.php is never read
                        (a new restore moves it to archive/: 5.x writes a cacheconfig.php there that hop 1's 4.5 code must not start on)
archive/<stamp>-<id>/   state, reports, baseline and timings of an EARLIER rehearsal, moved here by a new restore (never deleted)
restore-ids.log         every restore id this kit stamped in this work directory (not moved by a new restore), and a line "<id> used <time>"
                        for each restore whose moodledata a step after 01 ran against (written when a new restore moves its state to
                        archive/). A moodledata that carries an earlier id of this lineage is reused by a NEW restore only when it is the
                        finished unpack of the same archive and no step after 01 ran against it (the marker file says which archive);
                        otherwise a new rehearsal needs a new moodledata
```

## Re-running, resuming, rolling back

A step that already finished says so and checks again instead of redoing: the restore never writes over a populated database
(a populated database must carry this rehearsal's marker, and a restore the kit started and did not finish is refused), the
mail backlog audit keeps the first (pre-wipe) numbers, the baseline is verified, never retaken (after the hops it only
confirms that the recorded file is intact), a hop that is done is skipped, and the repairs are idempotent. The ADR-031 role
scripts are idempotent EXCEPT that `adr031_role9_core_caps.php --apply` refuses a second apply while its state file exists;
step 06 therefore skips the apply on a re-run, after the dry run has shown that nothing is left to prohibit or remove, and
stops if the dry run still wants changes. After fixing the cause of a failure, `run_all.sh --execute --from NN`.

**Step 09 and a failed import.** Where it stands is decided from the kit's record and from the newest apply run in the database
(`local_sentientia_legacyrun`), not from the record alone:

* no apply run in the database: from the gate (an apply the guard refused before writing leaves no run);
* an unfinished run (failed, killed, the guard expired): the data now holds imported rows, so the gate, preflight and dry run
  are history and are NOT run again. Re-arm the guard (step 08) if it expired, then run step 09 with
  `IMPORT_APPLY_MODE=resume`; the decisions hash recorded before the apply (or the run's own) is passed again;
* a finished run: recorded first (`state/kv/import.applied`, `.runid`, `.apply_exit`), then its exit is judged. An exit 2 that
  Nitin has not accepted yet stops the step; after he accepts it in writing, re-run with `ACCEPT_UNPROVEN=1` and
  `ACCEPT_UNPROVEN_REF=<where>`: only the judgement and the verify run.

The rollback of a failed hop or import is the restore point taken before it (`SNAPSHOT_HOOK` is called before hop 1, hop 2 and the
import; or by hand: the kit prints a reminder, and with `BIZLMS_PRODUCTION_FLAG=1` refuses to go on until `SNAPSHOT_TAKEN` names
the label). To repeat an import, restore the snapshot or the dump and delete `state/kv/import.*`; the kit does not purge a feature
and repeat it (`import_bizlms.php --purge-feature` does not reset the run's step watermarks, so the purged feature would not be
imported again and the verify would fail with `feature_not_complete`).

A **new restore** (an empty database and `RESTORE_DB_DUMP`, or `RESTORE_DONE_BY_HAND` for a hand restore) starts a new
rehearsal: the earlier one's state, reports, baseline, cache configuration and timings move to `archive/`, so a stale
`import.applied`, baseline or summary can never be taken for this run's result. It also needs a **new or emptied moodledata**
(see "What it refuses"): the earlier rehearsal's dataroot is not a clean unpack of the archive any more. The timings of a step
that was run twice add up (the summary says so).

## Cutover day

See the note under "What it refuses": the kit is for rehearsal. What the rehearsal hands to cutover day is: the decisions hash
(`state/kv/import.decisions_hash`, passed as `--expect-decisions-hash`), the timings that size the window, the baseline taken the
same way on live at the freeze (migration plan 4a/4b; the tool must be the same file, and `bizlms_production = 1` is set for the
import), the restore point, and the list of what a person must still do. The freeze, backup, DNS and repoint steps of the migration
plan are IT's and are not here.

## Not in the kit

* The per-user fingerprint diff, the real known-password logins, the SCORM and certificate walk (runbook step 6), the mail
  sender test: gates 2, 3, 4 and 6 of the rollout gate are people's work. The summary lists them.
* Runbook 4d, `enable_oneclick_enrol.php` (SW-1): it flips a feature flag, which is Nitin's decision.
* Who holds `local/sentientia_platform:crosstenant`: step 06 creates the platform role empty.
* Uninstalling anything: **never uninstall a plugin that is missing from disk**, it drops its tables, the archive the import
  reads. The kit never does, and says so after hop 1.

## What the first local rehearsal taught (and where the kit enforces it)

| Learned | Kit |
|---|---|
| `local/airpay_ratings` next to `local/sentientia_ratings` stops hop 2 in 14 s ("Cannot redeclare airpay_display_rating()") | `lib/bizlms_plugins.txt`, step 04 |
| The 50 BizLMS plugins show as "missing from disk" after hop 1 and that is the intended state | steps 03, 04 list them; unknown missing plugins are warned |
| The rehearsal config must refuse any other database (`make_config.py`) | `lib/make_config.sh` guard, step 00 |
| The package build leaves `public/config.php` (Moodle's 5.x loader) out of the zip | `lib/make_config.sh --tree 5x` writes it when absent |
| GNU tar writes a "zip" that is a tar; the 2026-08-03 zip leaked a dev `config.php` | `unpack_tree`, step 04 |
| A DB-only restore 404s SCORM content | the file store gate, step 01 |
| `cron.php` refuses under CLI maintenance; `cron_keepalive` makes it poll for 3 minutes | step 11 lifts maintenance and passes `--keep-alive=0` |
| `--allow-unstable` is required in non-interactive mode for a non-stable build | `UPGRADE_ALLOW_UNSTABLE=1` |
| A restored backup can carry task rows marked running, which the import guard counts | step 08 |
| Baseline from an upgraded copy is worthless | step 02 |
| (Stage B tools review 2026-10-08) A dump made with `mysqldump --databases` carries `USE airpayprod`, which sends the restore to that schema on whatever server answers; an aborted dump restores as a partial copy | the dump scan, `--one-database`, pipefail, the trailer check, step 01 |
| The restored `muc/config.php` is live's cache configuration (Redis, memcached) and Moodle loads it from dataroot | `altcacheconfigpath` in the generated config, the file moved aside, step 01 |
| An allow-listed database name is not proof that the database is the rehearsal's (`airpayprod`, `sentientia_uat` slipped past the old name patterns) | the `prod`/`uat` name guard, `PRODUCTION_DB_ENDPOINT`, the server schema scan, the kit marker |
| The role-9 script refuses a second `--apply` ("Already applied"), so a repeated step 06 could not pass | step 06 skips the apply when the state file exists and the dry run shows nothing left |
| An import that failed half way left a database the gate refuses, and the documented recovery re-ran the gate | step 09 reads the newest apply run from the database |
| The Moodle 5.0 upgrade uninstalls mod_survey and mod_chat when their code is not on disk, deleting their activities; the first metric sets counted no `course_modules`, so the April copy read "100% PARITY" | metrics version 3 (`course_modules` and the BizLMS substrate are checksummed), ADR-032 "FINDING". The loss cannot be accepted by the parity tool (it has no acceptance for drift), so step 04 counts the activities BEFORE hop 2 and stops: the one way forward is a package that carries a 5.x `mod_survey` and `mod_chat` (Nitin's decision); step 01 notes the counts early |
| (Stage B tools fix round 2) A new rehearsal reusing the earlier rehearsal's moodledata silently ignored the new archive and carried over the role-9 state file, so step 06 died and told the operator to revert the earlier rehearsal's role | the marker file records the unpacked archive; a new restore refuses a used dataroot; a named archive is never ignored |
| (Stage B tools fix round 2) The first parity compare after the import fails on a clean run when the import switches off a BizLMS enrol instance (owner decision CRS-01) and the compare holds `enrol` as insert-only | `parity\core::WRITES['enrol']` is an update table (status, timemodified), named by the enrolments importer's trail `local_sentientia_courses_enroloff` |
| (Stage B tools fix round 3) A `RESTORE_DONE_BY_HAND` left in `rehearsal.env` met the next rehearsal's `RESTORE_DB_DUMP`: the kit restore died part way, the operator re-ran step 01 without dropping the database, and the partial copy was adopted with one warning and stamped complete (round 2 had moved the by-hand check above the partial-restore refusal) | both variables set is refused outright; a restore that was started and did not complete is refused whatever `RESTORE_DONE_BY_HAND` says, and only the kit seeing the database absent or empty clears it (step 01, `selftest.sh`) |
| (Stage B tools fix round 4) The round 3 rule that clears the record of a failed restore trusted ONE probe (a client that printed nothing for the SCHEMATA count, a fault seen on this box, read as "absent": the record was archived with 120 tables still there and a later `RESTORE_DONE_BY_HAND` adopted them), and the record named no database (a run of step 01 for `DB_NAME=other`, seeing that database absent, archived it, and the original partial copy was then adopted) | the record names host, port and name (`restore.started_db`) and is cleared only when that same database is absent or empty on two reads that agree; `probe_db` retries an empty COUNT and treats one that stays empty as unreachable; a run for another database refuses (step 01, `common.sh`, `selftest.sh`) |
| (Stage B tools fix round 4) The "a step after 01 used this moodledata" fact lived in `state/*.status`, which a new restore rotates to `archive/`: a kit restore of rehearsal B that died, then retried, found no status file and reused rehearsal A's used dataroot | `rotate_work_state` writes `<id> used` to `restore-ids.log`, which no rotation moves; step 01 reads it (`restore_dataroot_used`) |
| (Stage B tools fix round 5) The in-flight record of a failed kit restore lived only in `REHEARSAL_WORK/state/kv`, so (1) a run with a NEW `REHEARSAL_WORK` had no record and `RESTORE_DONE_BY_HAND` adopted the partial copy, and (2) a second step 01 in the same work directory, while the first was still scanning its dump (the database absent or empty, the record already written), archived the first's record after two agreeing "empty" reads, and the first then wrote 120 tables and failed | the record is a table in the database (`zz_rehearsal_restore_inflight`, created before the load, dropped when the restore is verified complete): a database that holds it is refused for any work directory, only `DROP DATABASE` clears it, and the `CREATE TABLE` is the claim on an empty database; the work-directory record (`restore.started`, `restore.started_db`) and the two-agreeing-reads clearing are gone (one mechanism; step 01, `common.sh`, `selftest.sh`) |
| (Stage B tools fix round 5) Only `run_all.sh` took `.run.lock`: a step run alone never checked it | `step_init` takes `REHEARSAL_WORK/.run.lock` for `--execute` (released on exit; exit 3 when held), or verifies that `run_all.sh` holds it (`REHEARSAL_RUN_LOCK` and `REHEARSAL_RUN_LOCK_PID`, which the lock's pid file must still hold) |
| (Stage B tools fix round 5) The scan for production and UAT schemas read an empty schema list as "no forbidden schema here" (a client that printed nothing, or a failed query) | an answer without `information_schema` (it is always listed) is asked again twice and then leaves the server "unreachable", which every caller refuses (`probe_db`) |
| (Stage B tools fix round 6) The moodledata half of the same defect class: a kit unpack of `RESTORE_MOODLEDATA_ARCHIVE` that was cut short (tar "Unexpected EOF") was adopted by a re-run with the archive variable unset, or in a new `REHEARSAL_WORK`: the filedir gate checks that a file exists, not its size, so a cut after `filedir/` (lang packs, repository, models) passed and steps 02 to 11 ran on a partial copy | the in-flight FILE `MOODLEDATA/.rehearsal_unpack_inflight` (restore id, archive, start time), created before the unpack writes anything and removed only after it is verified complete; a moodledata that holds it, or whose marker has an `archive=` line and no `unpacked=` line, is refused by step 00, step 01 (before the database is looked at) and `require_kit_marker`, whatever any variable says; the unpack's failure messages say it is unfinished and what to do |
| (Stage B tools fix round 6) The in-flight table was read with one `COUNT(*)` of `information_schema.TABLES`: a count that came back wrong (the selftest's own fault model has one) read a present table as absent, and nothing backstopped that read on the `RESTORE_DONE_BY_HAND` path | `inflight_count` reads the table itself: error 1146 is the only "absent", a query that ran without an error and printed its own `answered` line is "present", anything else is asked again and then "cannot tell" (refused) |
| (Stage B tools fix round 6) `require_kit_marker`, the gate of steps 02 to 11, did not look for the in-flight table, so a dump or snapshot of a stamped, unfinished copy (its `{config}` marker row loaded, the rest not) passed it; and a dump that named the table would have dropped and re-created the kit's claim in the middle of the load | the gate also refuses a database that holds the table and a moodledata that holds the in-flight file or an unfinished unpack record; `dump_unsafe_statement` refuses a dump that names the table |
| (Stage B tools fix round 6) Step 01 moved `state/` to `archive/` before it had claimed the database: an idempotent re-run on a finished rehearsal that misread the database as absent archived the finished rehearsal's state, stopped at "cannot create database", and the next plain re-run refused its own database ("carries restore id ..., but this work directory records none") | the restore id is chosen first and `state/` moves only after the claim (`inflight_begin`) or, for a hand restore, after the operator's statement is taken (`archive_earlier_rehearsal`) |
| (Stage B tools fix round 6) A `TERM` sent to `run_all.sh` alone removed `.run.lock` (its EXIT trap) while the step it had started kept running, and a step run alone could then take the lock in the same work directory | `run_all.sh` traps `TERM` and `INT`: the signal is noted, the lock stays held until the running step has finished, no further step starts, exit 143 / 130 (the running step is not interrupted: bash defers a trapped signal until its foreground command ends) |
| (Stage B tools fix round 6b) A moodledata archive cut exactly at a tar member header was adopted: GNU tar exits 0 on it, the in-flight file went, the marker said 'unpacked', the filedir gate passed when the cut fell after `filedir/`, and steps 02 to 11 ran on a moodledata with no lang packs or repository files. The same holds for a `.tar.gz` that a dying `tar` wrote into `gzip` (a valid gzip around a cut tar) | `archive_proof` refuses the archive BEFORE the database is touched: `RESTORE_MOODLEDATA_SHA256` (the live backup's manifest) must match, or a tar of any compression must read to its end and finish with the two zero blocks (`tar_ends_complete`); the proof is recorded (`restore.moodledata_proof`, `restore.moodledata_sha256`); the archive's identity is read again after the unpack; the filedir gate compares each content file's size with `{files}.filesize` (`FILEDIR_MAX_WRONGSIZE`) |
| (Stage B tools fix round 6b) A step killed by `TERM` or `HUP` while a foreground command ran recorded SUCCESS (the EXIT trap saw the last completed status, 0) and released `.run.lock` under the still-running command; a second run then took the lock in the same work directory | `step_init` traps `TERM`, `INT` and `HUP`: the signal is acted on when the foreground command has returned, the step records `status=fail rc=128+n signal=NAME`, releases the lock and exits 128+n; a step writes `status=running` at its start so a `KILL` is never read as ok (`step_done_ok`, the summary); `on_exit` stops the step's background jobs; each step writes its pid to `.run.lock/step.pid` and the refusal names and liveness-checks both pids; `run_all.sh` also traps `HUP` and checks the signal immediately before it starts a step |
| (Stage B tools fix round 6b) `marker_get` failed open three ways (a failed value read, a failed or blank COUNT, a value that stayed blank while the COUNT said 1), each as 'no marker', and on the `RESTORE_DONE_BY_HAND` path (which stays in `rehearsal.env`) one lost connection archived a finished rehearsal's `state/` and overwrote the database's marker with a new id | `marker_get` returns rc 1 ('cannot tell') on all three, with a retried read; every caller refuses it; the hand path needs `marker_definitely_absent` (two agreeing error-free counts of 0 on an existing config table) and stamps with a plain `INSERT` (error 1062 = a marker exists), archiving `state/` only after it succeeded |
| (Stage B tools fix round 6b) A changed `RESTORE_DB_DUMP` on a stamped database was silently ignored; a dump re-written while it was scanned and loaded could load with exit 0 and be stamped; a table count that was wrong once after the load refused a complete copy; a work directory holding an older kit's `restore.started` without `restore.complete` adopted that restore's partial copy; a dump line that is a mysql client command (`\u db`, `source`, `system`) went unnoticed; every kit client call let `~/.my.cnf` override the kit's host, port and password | the dump's identity is recorded (`restore.dump`) and checked before and after the load; the post-load probe is repeated once; the older kit's record refuses the copy; the dump scan flags client commands; every client call uses `--defaults-file` (the kit's file only) |
| (Stage B tools fix round 7) Without `RESTORE_MOODLEDATA_SHA256`, an uncompressed tar was accepted on the test of its last 1024 bytes: a zero-filled region (a pre-allocated or segmented copy that stopped, a file system that kept the size and lost the data) ends in zero blocks at any cut point, GNU tar takes two zero blocks where a header is due for the end of the archive and exits 0, and a full-size copy with a zero-filled tail unpacked without its lang packs or repository files (reproduced); and a checksum that was set was silently ignored when `filedir/` was already there | an uncompressed tar REQUIRES `RESTORE_MOODLEDATA_SHA256` and is refused without it, before anything is touched; a set checksum is checked on every run (a re-run, a reused unpack) and the proof is recorded again; a checksum with no archive is refused; compressed archives keep the end-of-stream test (their decompressor fails on zeros); a zip is checked by `unzip` (`archive_proof`) |
| (Stage B tools fix round 7) Steps 02 to 11 accepted a copy that step 01 had stamped but not cleared (a failed file store gate, or a step 01 killed before the gate or the neutralisation), so live's OAuth2 refresh tokens stayed usable by the step 11 cron cycle; and the preflight's `cron_enabled` check only warned once `step_done_ok 01` was false, so `run_all.sh --from 02` got through it | step 01 records that it finished (`{config}` row `rehearsal_kit_step01_ok` and `state/kv/restore.verified`) as its very last act and removes the record when it starts; `require_kit_marker` refuses a copy without both records or with `01.status` not ok, naming step 01; the preflight refuses a run that starts after step 01 on a stamped copy it has not finished (`REHEARSAL_RUN_STEP01`, set by `run_all.sh`) |
| (Stage B tools fix round 7) A signal that ends bash but was not trapped (`USR1`, `USR2`, `ALRM`, `VTALRM`, `XCPU`, `XFSZ`, `PIPE`) ran the EXIT trap with status 0: a step recorded `ok` and released its lock under its command; and a signal to the whole process group, which the documentation recommended, also killed the `tee` that the handlers wrote through, so the handler died of `SIGPIPE` (exit 141, `status=running`, the lock left behind, no STOPPED line) | every such signal but `PIPE` is trapped like `TERM` (`STEP_SIGNALS`), and `PIPE` is noted (`on_step_pipe`: a step that saw it and no other signal ends `fail`, rc 141), so that the signal that caused the dead pipe is the one that is recorded; the handlers log through `log_survivor` (SIGPIPE ignored, the line appended to the log file when the pipe is gone); `run_all.sh` does the same and exits 128+n; the documentation says how a group signal ends and what a signal to step 01 during the database load costs |
| (Stage B tools fix round 7, should-fix) The adopt branch recorded no dump, so a later run that still named the same `RESTORE_DB_DUMP` was refused with a message that did not match what happened; the file store size gate refused a complete copy when {files} held one content hash with two different `filesize` values | the adopt branch records `restore.adopted` and `check_dump_record` says so; a hash recorded with two sizes counts as cut only when the file on disk matches none of them (`filedir_wrong_sizes`; the list shows every recorded size) |

## Testing the kit

`bash tools/rehearsal/selftest.sh` (needs bash, PHP, coreutils; no database, no Moodle): the policy refusals (the database name
guard, the live endpoint), the config checks, the generated `config.php` (a hostile password round-trips, the guard exits, a
foreign config is not overwritten, the cache configuration points at the kit's directory), the dump scan (a `USE`, `CREATE
DATABASE` or `DROP DATABASE` statement, a missing trailer, plain and gzip), the file store comparison, `judge()`,
`unpack_tree`, the helpers that decide the re-runs of steps 04, 06 and 09, the whole-path cron scan, the archive identity and the
marker's unpack record, the GTID and MariaDB-collation dump checks, the snapshot acknowledgement, the tree manifest hash, the state rotation, step 01's
decision on a populated database (run in `--execute` mode against a stand-in `mysql` client that keeps the in-flight table like a database does,
still no database: `RESTORE_DONE_BY_HAND` with `RESTORE_DB_DUMP` refused; a database that holds the in-flight table refused whatever
`RESTORE_DONE_BY_HAND` says, in a new work directory, after a misread count, and cleared only by a dropped and re-created database; the claim
losing to another restore or to a database that is not empty, and, with two real processes and a stand-in client that blocks the first one at its
claim until a flag file appears, a second run that restores the whole database meanwhile; the same target string on another server; the run lock; an
empty schema list; a plain hand restore adopted; round 6: a cut moodledata archive whose retry with the archive variable unset, in a new work
directory, or with both hand statements is refused, the in-flight read by error (a count that always answers 0, a lost connection, a blank answer
for an absent table), `require_kit_marker` refusing an in-flight table or file or an unfinished unpack record behind a correct marker, a dump that
names the table, `state/` staying in place when a claim is not taken), the orchestrator in DRY mode, `run_all.sh` keeping its lock while a step
runs after a `TERM` (a copy of the kit with stub steps), and that no Windows path is hard-coded; round 6b: a moodledata archive cut exactly at a tar member header (plain, and wrapped in a valid `.gz`) refused before anything is restored, `RESTORE_MOODLEDATA_SHA256` matched, mismatched, malformed and recorded, an archive that grew during the unpack, a content file whose size is not `{files}.filesize`; a `TERM` or `HUP` to a step alone and to a step under `run_all.sh` (status `fail` with `signal=`, lock held until the command returned, background jobs gone), a step killed with `KILL` read as not ok, `HUP` to `run_all.sh`, a signal between two steps, the lock message naming both pids; `marker_get` and `marker_definitely_absent` with every fault of the stand-in client (a failed read, a blank read, a blank or failed count, a missing config table), and the hand path meeting each of them (nothing moved, nothing stamped, a marker row that exists refused by the plain `INSERT`); a changed dump, a dump rewritten during its load, a wrong table count after the load, an older kit's unfinished-restore record, the client commands a dump must not carry; round 7: a complete and a zero-filled uncompressed tar refused without `RESTORE_MOODLEDATA_SHA256` (and the zero-filled one still refused with the checksum of the complete archive), a zero-filled `.tar.gz` refused, a checksum checked on a re-run and refused without an archive, a whole step 01 against the stand-in (it writes its record that it finished last and removes it when it starts again), `require_kit_marker` refusing a stamped copy for every way the record can be wrong, the real steps 02, 03 and 11 and the preflight refusing the copy a failed file store gate left and the copy a `TERM` left before the gate (nothing neutralised), `USR1` and `ALRM` to a step alone, `USR1` to `run_all.sh` alone, a step whose log pipe died (`fail`, rc 141, `signal=PIPE`) and a `TERM` to the whole process group (step `fail` with `signal=TERM`, `run_all.sh` exit 143 and its STOPPED line in the log, lock released), a content hash that `{files}` records with two sizes. The `HUP` cases need a shell that does not ignore `SIGHUP` (not started under `nohup`: bash cannot trap a signal that was ignored on entry); the suite probes this and prints `skip` lines, counted apart from the passes, when it cannot deliver `HUP`. Every kit script passes `bash -n` (the selftest runs it);
`shellcheck` was not available where the kit was written, run it where it is. The selftest starts many `bash` processes: on
a workstation under antivirus load it takes tens of minutes.

Verified, on a Windows workstation (Git Bash, PHP 8.2, MariaDB 10.11, scratch schemas named `stageb_*`):

* the whole kit in DRY mode;
* steps 00, 01, 02 and 12 in `--execute` mode against a scratch schema: a real restore from a gzip dump and a tar of the
  moodledata into a new schema, the file store gate failing on a missing content hash and passing when it is fixed, the
  SMTP wipe and the cron switch-off, `source_baseline.php` taking a baseline and re-verifying it;
* steps 03 to 11 in `--execute` mode against a **stand-in Moodle** (fake `upgrade.php`, `cfg.php`, `cron.php`,
  `maintenance.php`, `import_bizlms.php`, `migration_parity_check.php`, the repair and ADR-031 scripts, printing what the
  real tools print, reading and writing the scratch schema) to exercise the control flow: the full run, a stop at a
  failed gate and the resume with `--from`, a full second run on the finished rehearsal (idempotence), the guard arming
  and its facts, the decisions hash read from the report JSON, exit 2 stopping and being accepted with a reference, the
  refusals (cron switched back on, role holders below system context, web-service smoke errors, the log store off,
  parity drift after the import);
* `selftest.sh`;
* (Stage B tools review, 2026-10-08; 28 scenario assertions, all pass, and `selftest.sh`: 105) step 01 in `--execute` mode against scratch schemas: the marker, a hand restore with and
  without `RESTORE_DONE_BY_HAND`, a dump with `USE` and one without its trailer refused, a second restore rotating the
  state to `archive/`; steps 06 and 09 against the same stand-in: a repeated step 06, a failed apply continued with
  `IMPORT_APPLY_MODE=resume`, an exit 2 recorded and then accepted on a re-run; the new metrics on the 4.1.2 tables of the April dump and
  on the same data after the upgrades (`bizlms_april`, read only).

* (Stage B tools fix round 2, 2026-10-08; `selftest.sh`: 128 pass, and a DRY run of all 13 steps) step 01 in `--execute` mode against
  scratch schemas `stageb_h01` and `stageb_h_src` (22 scenario assertions, all pass): a fresh restore (the marker records the archive and
  the finished unpack, the OAuth2 tokens are blanked, the survey count is noted), a re-run, a re-run naming another archive (refused), no
  archive named, a NEW restore over the same finished unpack with nothing after step 01 run (reused), the same after a later step ran
  (refused), a new moodledata for the new rehearsal, a populated directory the kit did not stamp (refused; not vouched for by
  `RESTORE_DONE_BY_HAND`; refused with a fresh session file even with `RESTORE_MOODLEDATA_BY_HAND`; adopted when idle; an archive named
  for it refused), a GTID dump refused before anything is restored, a hand restore after a failed kit restore. The parity gate on a real
  engine without PHPUnit: the real `core::baseline/evidence/evaluate` and `parity_gate::expected()` through a `$DB` stand-in on scratch
  schema `stageb_h02` (34 assertions: `enrol` as an update table, a switched-off instance explained, an unrecorded one, a trail row on a
  manual instance, a trail row whose method differs, a changed fixed column, a stray insert, a removed row, the run filter, the registry and
  the gate naming the same operations) and the standalone `source_baseline.php` (7 assertions: `tool.sha256`, a baseline another file took
  refused, no hash refused, a metrics 3 baseline refused, a CRLF copy accepted). The harness scripts are not in the repository; the scratch
  schemas were dropped.

* (Stage B tools fix round 3, 2026-10-08; `selftest.sh`: 145 pass, 0 fail) step 01 in `--execute` mode against a stand-in `mysql` client (no
  database; 17 new assertions): `RESTORE_DONE_BY_HAND` with `RESTORE_DB_DUMP` refused in DRY and `--execute` mode before the database is probed;
  a kit restore that dies part way, then the re-run with the leftover statement and the dump (refused), with the statement alone on the 400-table
  partial copy (refused, nothing stamped, the failed record still in place) and with nothing (refused); the database dropped and recreated empty
  (stops at "restore the live backup first", the failed record moved to `archive/`) and then restored by hand and adopted; a plain hand restore
  adopted; an unmarked database without the statement, or with a statement naming another database, refused. The same 17 assertions run against
  the round 2 `01_restore_check.sh`: 7 fail (the partial copy is stamped with a restore id), 10 pass.

* (Stage B tools fix round 5, 2026-10-08; `selftest.sh`: 187 pass, 0 fail, 48 minutes on the workstation) step 01 in `--execute` mode against a
  stand-in `mysql` client that keeps the in-flight table the way a database does (the round 3 and 4 tests of the work-directory record were
  rewritten for the table that replaces it): the failed restore leaves the partial copy AND the table (restore id and dump path in its row) and
  keeps nothing in `state/`; the 400-table copy is refused with `RESTORE_DONE_BY_HAND`, with no statement, and by a retry with the dump (the
  client sees one load only); a NEW work directory refuses the same copy; an in-flight count the client prints nothing for (once, and always),
  a schema count that prints nothing or answers 0 once, and a table count that answers 0 once never adopt it or restore over it; a populated
  database read as empty releases its own claim and loads nothing; another restore that claims the database between the probe and the claim is
  left alone; the same target string on another (empty) server restores there, drops its table when verified complete, stamps, and a re-run
  is not refused and restores nothing; a restore with no `mdl_config` keeps its table; `.run.lock` held by another run stops a step run alone
  (exit 3, nothing touched, the other run's lock kept), run_all's own steps go on, a forged pid is not believed; an empty schema list is
  "cannot be reached" and a list naming `airpayprod` is still refused.

* (Stage B tools fix round 6, 2026-10-08; `selftest.sh`: 230 pass, 0 fail, 62 minutes on the workstation) 43 new assertions: a moodledata
  archive cut inside a language pack AFTER `filedir/` (tar "Unexpected EOF") leaves the in-flight file and a marker with an archive line and
  no unpacked line, and is refused on the retry with the archive variable UNSET (the defect the round 5 review reproduced), on a second run with
  the archive named, in a NEW work directory, with both hand statements, and with the in-flight file removed (marker alone); an emptied
  directory and a complete archive are unpacked and the file goes only after the unpack is verified; an archive that carries its own in-flight
  file is not a complete unpack; the in-flight table is read by error (a count that always answers 0 cannot adopt the partial copy and is never
  asked, a lost connection is retried and then "cannot tell", a blank answer for an ABSENT table does not refuse a plain hand restore);
  `require_kit_marker` refuses a correct marker on a database with the in-flight table or on a moodledata with the in-flight file or an
  unfinished unpack record (and passes the finished one); a dump naming the table is flagged (plain and gzip) and step 01 refuses it before it
  creates anything; `state/` stays in place (and nothing is archived) when an idempotent re-run misreads the finished database as absent or loses
  the claim, and the next plain re-run adopts its own database; two real step 01 processes race for one empty database with the stand-in client
  holding the first at its claim until a flag file appears (the second restores and stamps; the first, released, finds the database no longer
  empty, releases its claim and loads nothing); a `TERM` to `run_all.sh` alone, against a copy of the kit with stub steps, leaves it alive and
  holding `.run.lock` while step 01 runs, then no further step starts, the lock is released and the exit is 143. The same new assertions run
  against the round 5 kit (`git archive 6a1f36b84`): 27 fail (the assertions that test the change), 25 pass
  (the controls, and the concurrency case, which exercises the post-claim count round 5 already had).

* (Stage B tools fix round 6b, 2026-10-09; `selftest.sh`: 322 pass, 0 fail, 0 skipped, about two hours on the loaded workstation) 92 new assertions for the three
  must-fix items of the round 6 review: an archive cut at a tar member header (plain and inside a valid `.gz`), a file that is no tar, a wrong, malformed and
  right `RESTORE_MOODLEDATA_SHA256` (recorded in `state/kv`), an archive that grew during the unpack; `TERM` and `HUP` to a step run alone and to a step
  under `run_all.sh`, `HUP` to `run_all.sh`, a signal between two steps, `KILL` read as not ok, the two pids of the lock; every fault of the stand-in client
  against `marker_get` and `marker_definitely_absent`, and the hand path meeting them with the statement still in the env file. The `HUP` cases need a
  shell that does not ignore `SIGHUP` (not started under `nohup`); the suite says `skip` for them otherwise. Both clients of this machine (XAMPP 10.11 and
  MariaDB 11.4) read host and port from a `--defaults-file` the way the kit now calls them.

* (Stage B tools fix round 7, 2026-10-09; `selftest.sh`: 390 pass, 0 fail, 0 skipped, about 2.5 hours on the loaded workstation) 68 new assertions for the two
  must-fix items of the round 6b review and its cheap should-fix items: an uncompressed tar, complete or zero-filled, refused without
  `RESTORE_MOODLEDATA_SHA256` before anything is touched (the zero-filled copy of the review's repro is full size and passes the old last-1024-bytes
  test, and GNU tar unpacks it with exit status 0 and no `lang/`), still refused with the checksum of the complete archive, a zero-filled `.tar.gz`
  refused; the checksum checked on a re-run where `filedir/` is there (wrong: refused, right: the proof is recorded again) and refused without an
  archive; a whole step 01 against the stand-in client in end-to-end mode, which writes its finished record last, after the neutralisation, and removes
  it when it starts again; `require_kit_marker` refusing a stamped copy whose `01.status` is fail, running or missing, whose `restore.verified` or
  database record is missing, belongs to another restore or cannot be read; the real steps 02, 03 and 11 refusing the copy a failed file store gate
  left, and the copy a `TERM` left before the gate (nothing neutralised: no SMTP wipe, no `cron_enabled`, no OAuth2 statement reached the database),
  and the preflight refusing a run that starts after step 01 on such a copy; `USR1` and `ALRM` to a step alone, `USR1` to `run_all.sh` alone, a step
  whose log pipe died, and a `TERM` to the whole process group (the step `fail` with `signal=TERM`, `run_all.sh` exit 143 with its STOPPED line in the
  log, lock released); a content hash that `{files}` records with two sizes. The real `check_pass_file`, which steps 00 and 02 run first, cannot pass on
  a file system that does not keep mode 600 (Git Bash on NTFS): the real steps 00 and 02 are run from a copy of the kit whose only change is that this
  check always passes. The same new assertions were not run against the round 6b kit (`git archive 9423e662d`); the premises (the old test passes the
  zero-filled tar, GNU tar unpacks it without `lang/`) are asserted in the suite instead.

**Not run: any step against real Moodle 4.5 or 5.x code, `shellcheck` (not installed on that machine), PHPUnit.** The stand-in
proves the kit's logic and parsing, not that the real tools print exactly what it expects: the first execution on the
target box does. Parsing that depends on real output (the `--status` fact lines, the repair scripts' result lines, the
`upgrade.php` success line, `meta.decisions_hash` in the report, the role-9 dry-run line) was written from the tools' source.
