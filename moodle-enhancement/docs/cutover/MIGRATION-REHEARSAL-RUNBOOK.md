# Ninja-Sandbox Migration Rehearsal Runbook (rollout-gate Phase 2)

> **2026-09-29:** live is Moodle 4.1.2 (not 5.1), so the upgrade is two hops (4.1.2 → 4.5 → 5.2); the Sentientia
> migration plan §0 has the details. Steps 3 and 4e below were updated for it.
>
> **2026-10-07:** the parity gate is rebuilt for Stage B (steps 0, 3 and 5, and the new step 5a). The baseline is
> taken with ONE standalone file that needs no Sentientia plugin and works on 4.1.2 (it could not before: the old tool
> read `scorm_attempt`, which exists only from Moodle 4.3). The legacy BizLMS tables are fingerprinted in it, and a
> post-import mode proves that everything the import changed is in its own records.

> **2026-10-07 (the rehearsal kit):** the procedure below is now scripted for the Linux target box in
> `tools/rehearsal/` (13 steps, one env file, DRY by default). See "The rehearsal kit" below for which script runs which
> step. Two changes to the order came with it: the ADR-032 capability repair is step 4e and runs BEFORE the ADR-031 role
> scripts (it must run before anything reads a role), and step 5a now spells out the guard commands and where the
> decisions hash comes from.
>
> **2026-10-07 (owner decisions):** the BizLMS feature-data import (ADR-032) is part of the rehearsal. Step 4h and the section
> "BizLMS import: Stage B checks" below carry the checks and owner confirmations the 2026-10-07 owner decisions
> require; step 5 is amended (decision F-34). Per-decision detail: `OWNER-DECISIONS-2026-10-07.md`.
>
> **2026-10-08 (Stage B tools, merged with the owner-decision branch):** the post-import gate is ONE command,
> `migration_parity_check.php --compare=<baseline> --after-import --decisions=<file> --expect-decisions-hash=<hash>`
> (step 5a): it explains the enrolments import's deltas, including the BizLMS enrol instances it switches off (CRS-01), from the
> import's own records, and refuses without the decisions. The former `--compare ... --decisions` form without `--after-import`
> is gone (refused, exit 3). Steps 5b to 5e are the enrolments report, the admin-unenrol rule, the resume rule and the frozen
> legacy source of the owner-decision branch (numbered 5a to 5d there).

**Owner:** Nitin Rajput · **Status:** kit READY, locally rehearsed 2026-06-10 · **Executes:** on the
ninja sandbox when Nitin provides server access + a fresh live backup. **Nothing here touches live.**

**Goal:** prove that a LIVE airpay.academy backup migrates onto the Sentientia stack with
**existing Academy users' data intact** — the explicit precondition Nitin set for replacement.

---

## Inputs (Nitin/IT provide)

1. Ninja sandbox server (SSH/RDP), PHP **≥ 8.3** (CLI + web SAPI) with extensions
   mysqli/intl/mbstring/curl/zip/gd/soap/openssl/sodium/exif/fileinfo, `max_input_vars ≥ 5000`,
   MySQL 8 / MariaDB ≥ 10.6 with **`max_allowed_packet ≥ 64M`** (2026-06-11 cron gauntlet: 1M
   drops the connection mid-cron — "MySQL server has gone away"), web server.
2. Fresh LIVE backup: full DB dump + `moodledata` archive (+ the live `config.php` for reference), **and the SHA-256 of the
   moodledata archive (and of the dump), computed ON THE LIVE SERVER right after each was written and before it was copied, delivered with
   the backup.** A `sha256sum` of the copy on the sandbox matches a cut copy (a pre-allocated or segmented download that stopped part way)
   just as well as a whole one and proves nothing: do not make the checksum there. The kit takes the moodledata archive's checksum as
   `RESTORE_MOODLEDATA_SHA256` (an uncompressed `.tar` is refused without it, and the preflight says so in DRY mode too; check the dump's
   with `sha256sum -c` before the restore: the kit checks the dump's trailer and statements, not a checksum). Whatever the checksum's source,
   step 01 also reads every file of `filedir/` and requires the SHA-1 of its content to be its own name (Moodle's naming), which a copy that
   lost data cannot pass.
3. The `production` branch checkout (or release archive) — carries the entire product layer.

## The parity tool, in one place

| Where it runs | Command | What it needs |
|---|---|---|
| The SOURCE (a restored 4.1.2 copy, or live in the §4b freeze), and the 4.5 checkpoint after hop 1 | `php source_baseline.php --config=/path/config.php --baseline=FILE` / `--compare=FILE` | the single file `local/sentientia_platform/cli/source_baseline.php`, PHP 7.4 to 8.4, mysqli, the box's own `config.php`. No Moodle load, no Sentientia plugin. |
| A Sentientia target (after hop 2 and the repairs) | `php local/sentientia_platform/cli/migration_parity_check.php --compare=FILE` | the deployed plugin |
| A Sentientia target, after the import | `... --compare=FILE --after-import --decisions=FILE --expect-decisions-hash=SHA256 [--run=ID] [--report=FILE]` | the deployed plugin and the import's own records (the decisions options belong to this mode only) |

Both run the same metric code (`source_baseline.php` is the library `migration_parity_check.php` requires), so a number
cannot differ because two queries drifted apart. Every metric is version aware: SCORM attempts and the SCORM checksum are
read from `scorm_scoes_track` before Moodle 4.3 and from `scorm_attempt` + `scorm_scoes_value` after it, and give the
same numbers for the same data (measured on the April 2026 production copy: 8,504 attempts, 303,086 track rows, the
same CRC on 4.1.2 and on 5.1.3).

The source tool reads `config.php` as DATA (it never runs it), opens a read-only session, takes one consistent snapshot,
and issues only SELECT and SHOW (the code refuses anything else). `--dbhost --dbname --dbuser --prefix --dbport
--dbsocket --dbpass-env=VARIABLE` replace a `config.php` that builds its settings with functions.

**Exit codes (the same in `import_bizlms.php`):** 0 parity · 1 drift or a failed invariant · 2 nothing drifted but
something could not be checked (NOT proven: treat as a stop) · 3 refused or could not run.

**What the baseline holds (JSON format 2):** counts (23 on the April copy), the grade sum as an exact decimal, a value
checksum per critical table (14), a count + max id + CRC over every column of every row of each BizLMS table that exists
(95 tables, 25,726 rows on the April copy, no row cap), the same for tables with a legacy prefix that no inventory
names (11; a change there is unproven, not failed), and the rows and columns of the five core tables the import may write
(`user_enrolments`, `enrol`, `role_assignments`, `course`, `tag_instance`). Measured on the April copy on the local
MariaDB 10.11 (16 MB buffer pool): 22 to 30 s for the baseline, 25 s for a compare; on RDS it is faster.

## The rehearsal kit (`tools/rehearsal/`, added 2026-10-07)

One orchestrator, `bash tools/rehearsal/run_all.sh`, and one script per step. **DRY by default** (it prints what it would run
and changes nothing); `--execute` runs. Run it as the web user of the target box
(`sudo -u www-data bash tools/rehearsal/run_all.sh --execute`). One env file holds every path, host and database name
(`tools/rehearsal/rehearsal.env.example`). Every script logs with timings (`logs/timings.tsv`), is idempotent, and stops on
the first failed gate; after fixing the cause, `--from NN` resumes. `tools/rehearsal/README.md` has the details.

| Kit script | Runs | Runbook step |
|---|---|---|
| `00_preflight.sh` | refuses unless the database is on the explicit rehearsal allow-list (no name with `prod` or `uat` in it), `$CFG->noemailever` is true, no scheduler runs THIS rehearsal's Moodle cron, and no production hostname (the live database endpoint, `PRODUCTION_DB_ENDPOINT`, included) appears anywhere; changes nothing | Inputs, 1 |
| `01_restore_check.sh` | restore into an EMPTY database (only if asked; a dump with USE / CREATE DATABASE, a `SET @@GLOBAL.GTID_PURGED`, or without its "-- Dump completed" trailer is refused) and stamp the database and moodledata with a restore id that every later writing step checks (the moodledata of ONE rehearsal: a new restore refuses a dataroot an earlier rehearsal ran in), release and user count, **the file store gate** (every `files.contenthash` on disk; missing = stop), SMTP wipe, OAuth2 token wipe and `cron_enabled = 0`, the restored mail backlog audit (I-11), restore loss against the live baseline | 1 |
| `02_source_baseline.sh` | the baseline on the 4.1.x copy before any upgrade; an existing baseline is re-verified, never retaken | 0 |
| `03_hop1_to_45.sh` | hop 1 on a clean 4.5 core with the BizLMS code off disk, timed, parity after | 3 |
| `04_hop2_to_5x.sh` | hop 2 on the Sentientia package in its own directory, timed, parity after; **before the hop it counts the `mod_survey` and `mod_chat` activities the 5.0 upgrade would delete and stops while the package has no code for them** | 2, 3 |
| `05_repairs.sh` | 4a to 4c and 4e: `repair_task_registrations` (dry run, apply, the message preference check), tenant seed and parity, the capability inventory, check and apply against the signed allow-list | 4a-4c, 4e |
| `06_adr031_roles.sh` | the four ADR-031 scripts in target mode | 4f |
| `07_theme_switch.sh` | `theme` epsilon to sentientia | 4g |
| `08_import_guard.sh` | arms the ADR-032 guard (cron off, maintenance on, `bizlms_import_armed_until`, the log store, no task marked running) | 5a |
| `09_import.sh` | the data-intact gate, preflight, dry run (records the decisions hash), apply with a report, verify | 5, 5a |
| `10_parity_compare.sh` | `--after-import` against the source baseline | 5a |
| `11_cron_cycle.sh` | one cron cycle under `noemailever`, timed, with the `transfer_question_categories` task timed; `checks.php` | ADR-032 Cutover slice 7 |
| `12_summary.sh` | the report for Nitin: steps, the I-4 window estimate, parity checkpoints, the evidence hashes, the seven rollout-gate items and who proves each | 7 |

Not in the kit, on purpose: runbook 4d (`enable_oneclick_enrol.php` flips a feature flag, which is Nitin's decision), the
per-user fingerprint, the known-password logins, the SCORM and certificate walk (step 6), the mail sender test.

Things the kit does that the numbered steps below do not say (added 2026-10-08, Stage B tools review):

* **Nothing is written to a database the kit did not stamp.** Step 01 stamps the restored database (a `{config}` row) and the
  moodledata (a file) with a random restore id, and every later step refuses a database or directory that does not carry it.
  A database restored by hand is stamped only on `RESTORE_DONE_BY_HAND=<its name>` with `RESTORE_DB_DUMP` unset (both set is
  refused outright: the first stays in `rehearsal.env` until cleared). A restore the kit started and did not finish is recorded IN
  THE DATABASE it was writing to: just before it loads the dump, step 01 creates the table `zz_rehearsal_restore_inflight` there
  (restore id, dump path, start time), and drops it only when the restore is verified complete, just before it stamps the
  database. A database that holds the table is a partial copy and is refused whatever `RESTORE_DONE_BY_HAND` says and whatever
  work directory (`REHEARSAL_WORK`) the run uses; only `DROP DATABASE` clears it. Drop that database and create it empty (as a
  database administrator), then either run step 01 with `RESTORE_DB_DUMP` set, or restore the live backup into it by hand and run
  step 01 with `RESTORE_DB_DUMP` unset and `RESTORE_DONE_BY_HAND=<its name>`. The `CREATE TABLE` is also the claim on an empty
  database: a second restore into it (a second run, another work directory) finds the table, or finds the database no longer empty
  right after it claimed it, and stops without writing. The table is read by its own error, never from one count: the server's
  error 1146 is the only "absent"; no answer, a lost connection or any other error is "cannot tell" (refused), never "no table". A
  new restore moves the earlier rehearsal's state, reports, baseline and cache configuration to `archive/` only AFTER that claim
  is taken, so a run that misreads a finished database as empty cannot strand the finished rehearsal's `state/`. A dump that names
  the table (it was taken from a partial copy) is refused with the other unsafe statements. The steps after 01 (the gate
  `require_kit_marker`) also refuse a database that holds the table although it carries the marker.
* **The moodledata has the same record, a file.** Before the unpack of `RESTORE_MOODLEDATA_ARCHIVE` writes anything, step 01 creates
  `MOODLEDATA/.rehearsal_unpack_inflight` (restore id, archive, start time) and removes it only when the unpack has returned
  success. A moodledata that holds it, or whose marker file records an `archive=` line and no `unpacked=` line (a cut tar, a box
  that died mid-unpack), is a partial copy: step 00, step 01 (before the database is looked at) and every later step refuse it
  whatever `RESTORE_MOODLEDATA_ARCHIVE` (even unset), `RESTORE_MOODLEDATA_BY_HAND` or `REHEARSAL_WORK` say. The filedir gate used to
  be unable to catch a cut, because it checked that a file is there and not its size (a cut inside a language pack, `repository/` or
  `models/` after `filedir/` passes the existence check); it now also compares each content file's size with `{files}.filesize`
  (`FILEDIR_MAX_WRONGSIZE`, default 0), which catches a content file cut inside from any route, a hand unpack included. Empty the
  directory (or point `MOODLEDATA` at a new, empty one) and run step 01 again, or unpack the archive by hand into an EMPTY directory
  and name its path in `RESTORE_MOODLEDATA_BY_HAND` (a hand unpack has no completeness check of its own for its archive: verify the
  archive's SHA-256 against the one computed on the live server first, and that `tar` ran to the end of a complete archive; step 01 reads
  every file of its `filedir/` all the same, see the content check below).
* **A moodledata archive is proven whole before the database is touched** (rounds 6b and 7). A tar that is cut exactly at a member
  header is unpacked by GNU tar with exit status 0 and no message, so a "cut tar" is NOT refused by tar itself, and a `.tar.gz` that a
  dying `tar` wrote into `gzip` is a valid gzip file around a cut tar. Step 01 therefore refuses the archive before it restores
  anything (nothing is claimed, stamped or unpacked). With `RESTORE_MOODLEDATA_SHA256` set (the checksum **computed on the live server**,
  see Inputs) the archive's SHA-256 must match; **a tar must still end where a tar ends, with or without the checksum** (round 8: the two
  zero blocks that end every tar and, for a plain tar, GNU tar's own end of the archive within one 10240-byte record of the end of the file),
  because a checksum taken after the live backup's `tar` died while it wrote into gzip matches the cut archive. **An uncompressed `.tar`
  needs that checksum and is refused without it:** nothing inside a plain tar can show that every byte of it arrived. A copy that stopped part way (a pre-allocated or
  segmented download, a file system that kept the size and lost the data) is full size and ends in zeros, GNU tar takes two zero blocks
  where a header is due for the end of the archive and exits 0, and the members after the zero-filled region are simply not unpacked.
  A compressed tar (gz, bz2, xz, zst) is read to its end by its decompressor, which fails on a cut or zero-filled stream, and must
  finish with the two zero blocks that end every tar; a zip is checked by `unzip` itself while it unpacks (its central directory and a
  CRC-32 per member). A checksum that is set is always checked, also on a re-run where `filedir/` is already there and on a new restore
  that reuses the finished unpack, and a checksum without `RESTORE_MOODLEDATA_ARCHIVE` is refused. The proof used, and the checksum, go
  to `state/kv` and the summary. The archive's size and mtime are read again after the unpack: an archive that changed while it was
  unpacked leaves the in-flight file in place. Take the checksum where the archive is made, on the live server.
* **The content of `filedir/` is read** (round 8, `RESTORE_FILEDIR_HASH_CHECK`, default 1). Moodle names every file of its file store by
  the SHA-1 of its content (`filedir/ab/cd/abcd...`), so the file store proves itself: step 01 reads EVERY file below `filedir/` (up to 8
  parallel `sha1sum` processes, progress logged every minute; one full read of the file store on every run of step 01, the reuse path and a
  hand unpack included) and requires the SHA-1 of what it reads to be the file's own name. A zero-filled, cut or damaged file keeps its
  name and its size in a copy that stopped part way, and passes the existence and size checks of the file store gate; it does not pass
  this, whatever the archive, its checksum or the route the moodledata took. Step 01 stops and names up to 20 files (all in
  `reports/filedir-hash-mismatch.txt`: path, SHA-1 of its content); empty `MOODLEDATA` (the kit deletes nothing), get the archive again
  from the live server with its checksum, and run step 01 again. A name that is not a content hash (Moodle's `warning.txt` in the root
  excepted) is warned about, not failed. The proof goes to `state/kv` (`restore.filedir_hash_proof`) and the summary;
  `RESTORE_FILEDIR_HASH_CHECK=0` skips the read with a warning and the summary then says NOT CHECKED (scratch rehearsals only).
* **A hop that failed after the release moved does not lock the rehearsal out** (round 8). A hop 1 whose plugin upgrade failed after core set
  the release, or whose parity check exited 1 or 2, leaves `03.status` fail and the database at 4.5. A full re-run (`run_all.sh --execute`)
  runs step 01 first, which removes its records of having finished and then used to die at the release gate (the release is not the source,
  hop 1 is not ok): step 03 was then refused for want of step 01 and step 01 could never pass again, until a new restore. Step 01 now
  accepts a release past the source (4.5 or 5.x) when this restore already passed the gate: hop 1 is done, or `state/kv/release.source`
  is set (only a step 01 that saw the source release writes it; it moves to `archive/` with a new restore). Step 01 then passes and step 03
  can be run again. A database that this work directory never saw at the source release is still refused.
* **A copy that step 01 stamped is not a copy that step 01 cleared** (round 7). Step 01 stamps the database and the moodledata right
  after the restore, long before its file store gate and its neutralisation (SMTP credentials wiped, `cron_enabled = 0`, the restored
  OAuth2 tokens blanked). The last act of a step 01 that reached its end is a record that it finished for this restore: the `{config}`
  row `rehearsal_kit_step01_ok` and `state/kv/restore.verified`; step 01 removes both when it starts on a copy it already stamped.
  Steps 02 to 11 refuse the copy unless `state/01.status` is ok and both records hold the restore id (a record that cannot be read is
  "cannot tell": refused), and the preflight refuses a run that starts after step 01 on such a copy. So a step 01 that failed at the
  file store gate, or was killed before it, leaves a copy that nothing after it will touch: fix the cause and run
  `run_all.sh --execute --from 01`.
* **One `--execute` run per work directory.** `run_all.sh` takes `REHEARSAL_WORK/.run.lock`, and a step run alone takes it too
  (exit 3 while another run holds it; the lock is per work directory, so two work directories on one database are NOT locked against each other: one `REHEARSAL_WORK` per rehearsal database). Steps started by `run_all.sh` use the lock it holds. The lock directory holds the pid of the
  run and, while a step is running, the pid of that step (`step.pid`); the refusal names both and says whether each is alive. Remove
  the directory only when NONE of them is alive: a step outlives a `run_all.sh` that was killed with `KILL`. A `TERM`, `INT` or `HUP`
  sent to `run_all.sh` alone (`kill <pid>`) does not interrupt the step that is running and does not release the lock under it:
  `run_all.sh` waits for that step, starts no further step (the summary included) and exits 143 (`INT`: 130, `HUP`: 129). A signal
  sent to a STEP is acted on when the command it is running has returned: the step records `status=fail`, `rc=128+n`, `signal=NAME`
  in `state/NN.status`, releases its lock and exits 128+n; it never records ok on a signal, and a step that dies without a trap
  (`KILL`) leaves `status=running`, which nothing reads as ok (the summary prints it as DID NOT FINISH). **A step is recorded ok only
  when it reached its last line** (`step_end`): bash runs the EXIT trap with status 0 for every signal whose default action ends it, also
  `ABRT`, `TRAP`, `SYS`, `ILL`, `FPE`, `BUS`, `SEGV` and the rest that no trap list can be complete for, so a step that ends without
  reaching its end is recorded `fail` with `ended=unreached`, and keeps its lock (its command may still be running: remove the lock
  directory only when no pid it names is alive); `run_all.sh` keeps `.run.lock` the same way. `USR1`, `USR2`, `ALRM`, `VTALRM`, `XCPU` and `XFSZ` are
  handled like `TERM` (exit 128+n; bash runs the EXIT trap with status 0 for each of them, which used to record a step as ok); `SIGPIPE`
  (the death of the `tee` a step writes its log through) is only noted, and a step that saw it and no other signal ends `fail`, rc 141.
  To stop a step and what it runs, signal the whole process group (`kill -TERM -- -PGID`; Ctrl-C and an ssh hangup do the same): the command
  ends at once, the step records `fail` with `rc=128+n` and `signal=NAME`, and `run_all.sh` logs its STOPPED line to `logs/run_all.log`,
  releases the lock and exits 128+n (143 for `kill -TERM`); the handlers write straight to the log files because the `tee` they would
  write through is gone as well. A signal sent to step 01 while it loads the database is acted on when the load has returned, and a load
  that returned 0 is then not verified: the in-flight table stays and the database must be dropped and restored again, so do not signal
  step 01 during the load unless that is what is wanted. Run the rehearsal under `tmux` or `screen`: a dropped ssh session sends `HUP`.
* **A marker that cannot be read is never "no marker".** The kit's reads of the database marker return "cannot tell" on a failed read
  (a lost connection, a restarting server, any error but the server's own 1146 for a missing config table), on a blank answer for a row
  the COUNT says exists, or on a COUNT that fails or stays blank, and step 01, the preflight and the gate of steps 02 to 11 refuse
  that. A hand restore (`RESTORE_DONE_BY_HAND`, which stays in the env file) is stamped only after two more error-free counts of 0, on an
  existing config table, agree; the stamp is a plain `INSERT` (a marker row that exists is error 1062 and nothing is overwritten) and the
  earlier rehearsal's `state/` moves to `archive/` only after that `INSERT` succeeded. The dump a database was restored from is recorded
  (`state/kv/restore.dump`: path, size, mtime); a later run that names another `RESTORE_DB_DUMP` on that database is refused instead of
  silently ignored, and a dump that changed while it was scanned and loaded leaves the in-flight table in place. The rollback to a
  snapshot or a dump of this rehearsal's own copy is a hand restore: before loading it, create the in-flight table by hand
  (`CREATE TABLE zz_rehearsal_restore_inflight (restore_id CHAR(32) NOT NULL, dump_path TEXT NOT NULL, started VARCHAR(40) NOT NULL,
  PRIMARY KEY (restore_id)) ENGINE=InnoDB`, one row with any 32-character id) and drop it only after the load returned 0, so that a
  rollback that dies half way is refused like any other partial copy (the dump or snapshot must not contain the table).
* **The database server scan sees what the rehearsal login can see.** The check that refuses a server holding `airpayprod` (or a
  schema in `FORBIDDEN_SERVER_SCHEMAS`) reads `information_schema.SCHEMATA`, which lists only the schemas the login holds a privilege
  on unless it has the global `SHOW DATABASES` privilege. On any server that is not the rehearsal's own, grant the rehearsal login
  `SHOW DATABASES`; the name guard, the allow-list and the kit marker do not depend on it.
* **The baseline has a metrics version, and names the file that took it.** A comparison refuses (exit 3) a baseline taken with
  another version of `source_baseline.php` (the checksums it lacks would otherwise go unchecked), and the baseline carries the
  SHA-256 of the exact file that took it (`tool.sha256`, carriage returns removed): the tool refuses a baseline another file took,
  step 02 stops unless this checkout's `SOURCE_BASELINE_PHP` is that file, and step 04 stops unless the package's copy is. There is
  no override. After the kit or the tool changes, take the baseline again (delete `baseline/source-baseline.json` and run step 02).
* **Hop 2 deletes activities unless the package carries `mod_survey` and `mod_chat`** (ADR-032 "FINDING"): the Moodle 5.0 upgrade
  uninstalls them when their code is absent. Step 01 notes the count early; step 04 counts again and refuses to start the hop
  while the package lacks the code of a type that has activities. The parity tool has no way to accept such a loss, so shipping the
  two modules is the one way forward (Nitin's decision); an acceptance would first need a narrow mechanism in the tool.
* **One rehearsal, one moodledata.** A new restore needs an EMPTY moodledata or a new directory: the earlier rehearsal's dataroot
  carries its role-9 state file, caches, sessions and cron files. The marker file records which archive was unpacked and that the
  unpack finished; a named `RESTORE_MOODLEDATA_ARCHIVE` is never silently ignored. That a step after 01 ran in a dataroot is also
  written to `restore-ids.log` when a new restore moves the earlier state to `archive/`, so a new restore that died and is retried
  still refuses the used dataroot. A populated directory the kit did not stamp needs
  `RESTORE_MOODLEDATA_BY_HAND=<its path>` (a statement of its own, not covered by `RESTORE_DONE_BY_HAND`) and must show no write
  in `sessions/` or `localcache/` in the last 30 minutes.
* **A restore point before each hop and the import.** `SNAPSHOT_HOOK` is called with the label (before-hop-1, before-hop-2,
  before-import). Without it the kit can only remind, and the irreversible step starts in the same second, so with
  `BIZLMS_PRODUCTION_FLAG=1` (the cutover form) it refuses to go on unless `SNAPSHOT_TAKEN` names the label.
* **The post-cron parity row is not expected to be exit 0** (step 11, informational): the 5.0 upgrade queued
  `\mod_qbank\task\transfer_question_categories`, which creates qbank activities on its first run. The gate that counts is step 10,
  before the first cron. The same holds on cutover day: run the data-intact gate before cron is enabled on the target.
* **Step 04 never hides what step 09 will judge.** When the only failed line after hop 2 is the `message_provider_defaults`
  invariant (step 05 repairs it), the compare stopped at that failure and printed no UNPROVEN items, so the step 04 row reads
  cleaner than its evidence; the data-intact gate of step 09 judges everything again, after the repairs.
* **`TENANT_CHECKS` defaults to `warn`, so 4b and 4c are not a stop.** Their "expect 100% PARITY" is a stop only with
  `TENANT_CHECKS=stop`; use that for the dress rehearsal.
* **The cron cycle (step 11) switches off, in the rehearsal database, the scheduled tasks that phone home** (moodle.net
  registration, update check, OAuth2 token refresh, webhooks, content market, HRMS sync); `noemailever` closes e-mail only.
  The restored push-notification key (airnotifier) is wiped in step 01 and the restored cache configuration is moved aside.
* **A re-run of step 09 after a failed or killed apply** does not run the gate again (the data holds imported rows): it reads the newest
  apply run from the database and continues with `IMPORT_APPLY_MODE=resume`; an exit 2 that waits for Nitin's written
  acceptance is recorded first and judged again on the re-run.

## Procedure (each step has a verify; stop on any failure)

0. **Baseline on the SOURCE (before any migration step):** copy `source_baseline.php` to the box (live in the §4b freeze,
   or the restored copy on the sandbox) and run
   `php source_baseline.php --config=/path/config.php --baseline=/vault/live-baseline.json`.
   Keep the file with the change ticket. Do not take it from a copy that has already been upgraded: the baseline
   must represent the pre-migration data. (The `migration_parity_check.php --baseline` of a Sentientia target writes the
   same format and is only for a target that is itself the reference.)
1. **Restore** the DB dump into a fresh database + unpack moodledata; point a sandbox `config.php`
   at them (`noemailever = true` MANDATORY — the dump holds real user emails; see the May email
   incident). Verify: user count query matches expectations **AND filedir parity holds** — the
   `moodledata/filedir` tree must carry the actual file *content*, not just the DB `{files}` rows.
   Assert both: `SELECT COUNT(*), SUM(filesize) FROM {files} WHERE filesize > 0` (DB view) vs the
   restored `filedir` file-count + on-disk byte total (`find moodledata/filedir -type f | wc -l` +
   `du -sb moodledata/filedir`) — they must be in the same order of magnitude (GB-scale, not a
   handful of files). **Why this gate exists (2026-06-15):** the local `moodle52_cut1` clone imported
   the prod DB rows but NOT the package binaries — its `filedir` held 21 files / ~0 MB — so SCORM
   players rendered their chrome + started the API but 404'd on the content asset
   (`pluginfile.php/.../mod_scorm/content/.../index_lms.html`). A DB-only-shaped restore looks
   broken even when the product is fine; this check catches it before the smoke tier does.
   Then run the source tool's `--compare` against the restored copy: it must exit 0 (that isolates restore loss).
2. **Deploy the Sentientia tree** (production branch) over the sandbox webroot per
   `ROLLOUT-PACKET-2026-06-10.md` step 1 (theme/sentientia, local/, payment, blocks, enrol,
   quizaccess).
3. **Upgrade:** `php admin/cli/upgrade.php --non-interactive` (locally proven: 2,057 steps, 5.1→5.2,
   zero errors — if the sandbox starts from the live 5.1-era stack the same path applies; for the
   5.2 jump confirm the PHP pre-checks first).
   **Corrected 2026-09-29: live is Moodle 4.1.2, not 5.1.** 5.2 requires 4.4 or later
   (`admin/environment.xml`), so the rehearsal upgrades twice: 4.1.2 → 4.5.x on the 4.5 core, then
   4.5 → 5.2 with the Sentientia package. See `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` §0.
   Time both hops, run hop 1 on the target engine (MySQL 8.4), and capture parity after each hop.
   **Before hop 2 (added 2026-10-08):** count the activities of `mod_survey` and `mod_chat`
   (`SELECT m.name, COUNT(*) FROM mdl_course_modules cm JOIN mdl_modules m ON m.id = cm.module WHERE m.name IN ('survey','chat') GROUP BY m.name`).
   The 5.0 upgrade deletes them, with their answers, when the package has no `public/mod/survey` or `public/mod/chat`, and nothing
   in the parity tool can accept that loss: if the count is above zero, the package must carry a 5.x-compatible `mod_survey` and
   `mod_chat` (Nitin's decision, ADR-032 "FINDING"). Kit step 04 does this count and stops before the hop.
   **Checkpoint after hop 1** (4.5 core, BizLMS code off disk, no Sentientia plugin yet): run
   `php source_baseline.php --config=<the 4.5 config.php> --compare=/vault/live-baseline.json` — exit 0, and the BizLMS
   tables must match (95 of 95 on the April copy). Any DRIFT line here is a core-upgrade effect, isolated from the rest.
4. **Post-restore repairs (MANDATORY, in order — all idempotent, dry-run first):**
   a. `php local/sentientia_platform/cli/repair_task_registrations.php --apply`
      (re-registers renamed plugins' crons, purges orphan task rows, fixes stale brand-row URL paths,
      purges orphan message_providers rows AND rewrites stale pre-rename capability strings on
      provider rows — WF-004/WF-005/WF-008a; verified counts: sentientia=23 / stale=0; brand
      resolver 20/20; 15 capability strings rewritten on both local instances — a live backup
      carries the same 15).
   b. `php local/sentientia_core/cli/seed_tenants.php` then
      `php local/sentientia_core/cli/parity_check_tenants.php` (expect **100% PARITY**; registry
      stays DORMANT).
   c. `php local/sentientia_core/cli/parity_check_org.php` (expect **100% PARITY**).
   d. `php local/sentientia_catalog/cli/enable_oneclick_enrol.php --dry-run` then `--apply`
      (SW-1; tenants 1+177).
   e. **BizLMS capability repair (ADR-032 "Capabilities"; added 2026-10-07 to this order, BEFORE the ADR-031 scripts):**
      the restore carries every role grant on capabilities of the 33 plugins that are missing from disk (520 on the
      April copy, all at system context). The allow-list `docs/cutover/bizlms-capability-allowlist.json` (signed by Nitin
      2026-09-30 against the April dump) records, one line each, what is carried (`grants`) and what is declined with a
      reason. It runs before anything reads a role, so it comes before 4f.
      ```
      php local/sentientia_platform/cli/repair_bizlms_capabilities.php                       # inventory, writes nothing
      php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist=docs/cutover/bizlms-capability-allowlist.json
                                                                                              # check: must exit 0
      php admin/cli/maintenance.php --enable                                                  # the apply needs CLI maintenance
      php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist=... --apply --confirm=<fingerprint>
      php local/sentientia_platform/cli/repair_bizlms_capabilities.php --allowlist=...        # again: exit 0, nothing to grant
      ```
      `<fingerprint>` is the first line of `php local/sentientia_platform/cli/import_bizlms.php --status`. **Exit 2 on the
      check means grants on this backup that nobody decided: stop; the allow-list is tied to the April dump, so re-run the
      inventory on the real live backup and have Nitin re-sign the file** (ADR-032 Stage B gate 2). Exit 1 = an allow-list
      line was refused; exit 3 = a guard refused (maintenance, fingerprint). It never grants
      `local/sentientia_org:manage`, `:manage_multiorganizations` or `local/sentientia_platform:crosstenant`.
   f. **ADR-031 role configuration (added 2026-09-29):** the role-9 core-cap script and the
      platform-role script, dry run first, then the read-only WS smoke — migration plan §4f-f.
      The four scripts run in target mode (`--target=<wwwroot> --config=<absolute config.php>`, 2026-09-30); copy them from
      `tools/uat/` first, they are not in the package. `--accept-nonsystem-holders` is Nitin's decision, never the operator's.
   g. **Site theme (added 2026-10-01):** production's `$CFG->theme` is `epsilon`, which is not in the
      package, so pages fall back to stock boost (seen in the 2026-10-01 rehearsal upgrade log).
      `php admin/cli/cfg.php --name=theme --set=sentientia`; April has no user/course/category/cohort
      overrides (migration plan §8 step 7).
   h. **BizLMS feature-data import (added 2026-10-07; ADR-032 cutover slice).** It runs in step 5a, after the data-intact gate of
      step 5: that gate proves that nothing but the upgrades and the repairs touched the data, which is only true before the
      import. At the rehearsal record the decisions sha256 (kit step 09 does): it is the hash cutover must match
      (`--expect-decisions-hash`). Run the checks in "BizLMS import: Stage B checks" below; the importers' data changes explain
      the deltas of the post-import gate.
5. **Purge caches**, then **data-intact gate (before the import):**
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/vault/live-baseline.json`
   → **must print `RESULT: 100% PARITY - counts AND value checksums match, and the BizLMS legacy tables are untouched.`**
   (exit 0). Any DRIFT line = stop + investigate; exit 2 = not proven = stop. Nothing but the upgrade has touched the
   data at this point, so every table must match, the five core tables the import will write included.
5a. **The BizLMS import and its proof (ADR-032 "Build and run order", cutover slice):** snapshot, maintenance on, cron off,
   `noemailever` on, arm the guard, then `import_bizlms.php --preflight --all ...` and `--all --apply ... --report=FILE`.
   **The guard commands** (kit step 08; `--apply` refuses, exit 3, naming the missing condition, unless all of them hold):
   ```
   php admin/cli/cron.php --disable                       # cron_enabled = 0; the guard fails closed on anything else
   php admin/cli/maintenance.php --enable                 # CLI maintenance (climaintenance.html); web maintenance does not count
   #   $CFG->noemailever = true in config.php (check: php admin/cli/cfg.php --name=noemailever prints 1)
   #   the standard log store on: php admin/cli/cfg.php --component=tool_log --name=enabled_stores must list logstore_standard
   #   no task marked running: a restored backup taken during a live cron can carry task_adhoc / task_scheduled rows with
   #   timestarted set; the guard counts them as running ('a_scheduled_or_adhoc_task_is_running'): reset them
   php admin/cli/cfg.php --component=local_sentientia_platform --name=bizlms_import_armed_until --set=$(( $(date +%s) + 14400 ))
   php admin/cli/cfg.php --component=local_sentientia_platform --name=bizlms_production --set=1    # cutover only: no --allow-online,
                                                           # no --purge-feature, --expect-decisions-hash becomes required
   php local/sentientia_platform/cli/import_bizlms.php --status     # prints the --confirm fingerprint and the facts the guard
                                                           # sees: maintenance true, noemailever true, standard_log true,
                                                           # cron_enabled false, running_tasks 0, armed_seconds_left > 0
   ```
   The grant window expires by itself (4 hours here); a clean `--all` run clears it. Cron cannot run while CLI maintenance
   is on (`cron.php` refuses), so after the import lift maintenance before the first cron cycle (kit step 11).

   **Where the decisions hash comes from.** The importer hashes `bizlms-import-decisions.json` itself (`decisions::hash()`: the
   SHA-256 of the file with CRLF normalised to LF, so a Windows and a Linux checkout agree; for an LF file it equals
   `sha256sum`). Every run that is given `--report=FILE`, a dry run included, writes it to the report JSON as
   `meta.decisions_hash`. The rehearsal records it from its dry run and checks that the apply report carries the same one:
   ```
   php local/sentientia_platform/cli/import_bizlms.php --all --decisions=<file> --report=/vault/import-dryrun.json
   php tools/rehearsal/lib/json_get.php /vault/import-dryrun.json meta.decisions_hash        # 64 hex characters
   ```
   (kit step 09 stores it in `state/kv/import.decisions_hash` and prints it in the summary). **That value is what cutover day
   passes** as `--expect-decisions-hash=<hash>` to `import_bizlms.php` (`--apply`, `--verify`) and to
   `migration_parity_check.php --after-import`: the rehearsed decisions are the ones that run, and a change to the file after the
   rehearsal is a re-approval event. With `bizlms_production = 1` the apply refuses without it.

   Then **the post-import gate**:
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/vault/live-baseline.json --after-import --decisions=<the rehearsed decisions> --expect-decisions-hash=<hash> [--run=<id>] [--report=<the --report file>]`
   → exit 0, or exit 2 with Nitin's written acceptance. It holds everything to the SOURCE baseline except what the import
   itself wrote, and for that it asks the import's own records: `user_enrolments`, `enrol` and `role_assignments` may have
   grown by exactly the rows `local_sentientia_legacymap` says were imported (the April dry run predicts +7,733
   enrolments and +19 enrol instances); a course may differ from the baseline in exactly the `open_*` columns its
   `local_sentientia_courses_detailfill` row names; a tag instance may have moved exactly as `..._tagmove` says; and a BizLMS
   enrol instance (classroom, learningplan or program) may differ in `status` and `timemodified`, and in nothing else, exactly
   where `local_sentientia_courses_enroloff` (the enrolments importer's trail of the instances it proved safe to switch off,
   owner decision CRS-01) names it. A changed old row (an `enrol.status` that no trail row names, an enrolment date), an extra
   or missing row, a column the ledger does not name, and any change at all to a BizLMS legacy table is exit 1. It also runs the
   `bizlms_import` invariant and lists the needs-owner reasons the decisions do not accept (exit 2). Exit 3 means it refused
   (no complete apply run of this install, a run id that is not one, decisions that do not hash to `--expect-decisions-hash`, no
   decisions file, a baseline of another metrics version or taken by another file).
   **Run it before the first cron of the target.** The 5.0 upgrade queued `\mod_qbank\task\transfer_question_categories`, whose
   first run creates qbank activities (`course_modules` and `course_sections` change, a container course may be added), so the
   same compare after a cron cycle differs from the baseline by design (kit step 11 runs it once more, informational, and says so).

   **Decision F-34 (2026-10-07), built 2026-10-08.** After the BizLMS import the core counts and checksums are no longer
   unchanged by design: the `enrolments` importer adds about 7,733 `user_enrolments` rows (April) and changes `enrol.status`
   (CRS-01). The gate above EXPLAINS those deltas from the `legacymap` rows (target tables `user_enrolments` and `enrol`, outcome
   `imported`) and the `enroloff` trail, and reports any unexplained delta as DRIFT. Exit 2 (needs-owner reasons not accepted, or
   an unclaimed legacy table with rows) is allowed only with Nitin's written acceptance.

   **Pass the decisions (added 2026-10-07, review fix round 1; merged 2026-10-08).** Every BizLMS importer's `verify()` reads
   owner decisions (`cart.abandoned`, `notifications.import_bodies`, ...) that have no default, so the `bizlms_import` invariant
   can only run with the file the import ran with. The CLI therefore takes `--decisions` and `--expect-decisions-hash` only with
   `--after-import`, and `--after-import` without a decisions file is refused, exit 3: never a pass, never a false FAIL, and
   never a quiet SKIPPED. `--expect-decisions-hash` pins the file; a different one is refused with exit 3 before any number is
   computed. The hash is the `decisions_hash` in the import run report (`--report=FILE`); the CLI also prints the sha256 of the
   file it was given, so the two can be read side by side. A decision the file does not hold is a real FAIL
   (`verify_error:<feature>:missing_decision:<key>`). A baseline taken before the import (`--baseline`) and the data-intact gate
   before the import (step 5, `--compare` alone) need neither option.
5b. **Stage B report for the enrolments import (CRS-01, added 2026-10-07).** After the `enrolments` feature has been
   applied, run `php local/sentientia_courses/cli/enrolments_access_report.php` and paste its output into the rehearsal
   report: the per-instance verdicts, the learner-course pair count and the ids of the legacy enrolments that regress. The
   import report itself carries only the per-instance skip codes; the pair count and the regression ids come only from this
   CLI. Exit 1 means a switched-off instance does not keep its learners' access: undo it (see the CLI header) before going on.
5c. **No admin unenrol before `bizlms_production_open` (LRN-10, added 2026-10-07).** From the learning-path, program and
   classroom screens an admin can now remove a pending imported enrolment row. On a rehearsal, UAT or Stage B copy that
   deletes an imported target, and the `bizlms_import` invariant then reports `missing_target_rows` as a FAIL. Do not
   unenrol imported rows on a copy that still has to pass the parity gate; once the runbook has set
   `local_sentientia_platform/bizlms_production_open` the check stops, because admins may then change rows freely.
5d. **Resuming the enrolments import (CRS-01, added 2026-10-07).** The step that recomputes which BizLMS enrol instances may be
   switched off changes `enrol.status` and `enrol.timemodified` on those instances, and both columns are inside the
   filtered CRC of the `enrol` source of `enrolments.instances` and `enrolments.legacy_instances`. In ATOMIC mode (the
   April copy, about 17 000 rows, is under the atomic threshold) one transaction covers the run and this cannot
   happen. In BATCH mode, once the recompute step has committed a batch, `--resume` of the same run stops in
   `open_step` with `source_changed_since_the_run_started`: only a FRESH run recovers (purge the feature first on a
   rehearsal copy). If a rehearsal ever has to run enrolments in batch mode, plan for a fresh run, not a resume. The
   same pattern applies to `course_tags`. A later change can leave `status` and `timemodified` out of that step's
   fingerprint; it was not done because it needs a framework change.
5e. **The legacy source is frozen from the moment `--apply` starts (added 2026-10-08).** A new run reads the fingerprint of
   every step's source at its start and stores it as a `pending` step row; each step compares it again when it opens. An edit
   to a legacy table (an INSERT, a DELETE or a changed value) at any time after the run started, including while a crashed run
   waits for `--resume`, stops the run with `source_changed_since_the_run_started:<step>` (exit 1). The step that is named
   had not started; the steps before it had already run. Do not repair the legacy data in place: restore the copy, or purge the
   feature and start a fresh run. `--status` shows `pending_steps` per feature (steps of the newest run that have not opened);
   a feature with only pending steps has not started.
6. **Workflow smoke** (subset of the FOOLPROOF matrix, all proven headless-runnable):
   provision qa users (`tools/_qa_provision.php` pattern), then login/dashboard/catalog HTTP probes,
   SA-04 both personas, signup POST, reminder cron with a seeded deadline, whatsapp e2e dry,
   `verify_brand_resolver` (20/20). Browser tier: run `tests/playwright` render-smoke + a11y with
   `PLAYWRIGHT_BASE_URL=<sandbox>` + `PLAYWRIGHT_*_USER/_PASS` (browsers proven to launch).
   **File-content gate (do NOT skip — added 2026-06-15):** open ONE SCORM activity as an enrolled
   learner and assert the player's content frame actually loads — i.e. the
   `pluginfile.php/.../mod_scorm/content/.../index_lms.html` (or the package's launch file) returns
   **HTTP 200, not 404**. The render-smoke alone passes on chrome-only surfaces (dashboard, catalog,
   course-view, quiz, forum, Page) that carry their content in the DB and need no filedir; only a
   file-backed activity exercises the restored `filedir`. If this 404s while everything else is
   green, the filedir restore (step 1) was incomplete — go back, do not proceed to Phase 3.
7. **Report to Nitin:** parity output + smoke results + any deviations. **Replacement (Phase 3)
   remains Nitin-gated.**

## BizLMS import: Stage B checks (added 2026-10-07)

Source: the 2026-10-07 owner decisions and follow-up items (`OWNER-DECISIONS-2026-10-07.md`, whose annex names each `F-nn`).
Counts and non-person ids only; never print a person's data. April = the April 2026 production dump restored in
`bizlms_april`; the live backup may differ, so re-read every number.

### A. Before the rehearsal starts

1. The signed decisions file and BOTH fixture copies carry every key an importer declares, in one commit
   (`php tools/check-bizlms-fixture-copies.php` OK); no `accepted_reasons` list yet (F-84, IDN-02).
2. One framework change in both platform trees, one PHPUnit re-init (F-83): `copies_files` marker, sequence floor
   (EV-26), preflight catching `blocked` (F-10), parity wiring (F-34, F-61). One version ledger per plugin (F-85).
3. `--group bizlms_import` and `--group tenant_isolation` pass on MySQL 8.4 and MariaDB 10.11 from the moodle5 dirroot
   (F-45); `registry::load()` returns all 19 importers (EV-25, F-33).
4. Code that decisions require is merged: COMMS-N1/N2/N3, COMMS-R1/R2/R4, CRS-01/02/03, EV-16, EV-17 (evaluationmode),
   EV-TENANT, IDN-01, IDN-07 and XC-IMPORTED-HISTORY-READERS, LRN-01..05, `cart.price_source` (revenue hole), the
   `claude/eval-followups` importer fixes (F-25). The rehearsal must run the final code and the final schema.
5. The restore carries `filedir` with the database (step 1; F-21): organisation logos and cohort descriptions need file
   content, not only `{files}` rows.

### B. Facts to read on the live backup (read-only, counts and ids; F-42, F-17, F-68)

`SHOW COLUMNS` of `local_syncerrors`, `local_emaillogs`, `local_notification_info`; counts of `local_coursedetails` and both
candidate counters; `local_dashboardcourses` lists and the roots they name; `local_courses/courses` tag instances and the
`mandatory`-tag count; BizLMS enrol instances by method (classroom and program instances may exist on live), disabled instances,
needs-owner counts and cross-tenant pairs; `local_ratings/review_enable` and the I-20 ratings counts per area; exam courses per
root and the reminder seed rows; `local_request_comments`, `local_learningplan_approval` counts; non-zero cart credit or invoice
rows. April values are quoted in section C.

### C. Per-importer checks

| Feature | Check at Stage B | April expectation | Decision |
|---|---|---|---|
| org | `logo_file_missing` warnings for logo itemids without a file row; `org:invalid_tenant_root` and `org:unmapped_enum` stay 0 | 14 organisations reference a logo, 5 file rows; roots 1/77/177 only, `visible` 1 on every row | IDN-03, IDN-04, F-21 |
| org | create the cross-tenant platform role with NO members: `adr031_crosstenant_role.php --target=<wwwroot> --config=<cfg> --dry-run`, then `--apply` (plan step 4f-f) | members only when Nitin names them | IDN-05 |
| org_roles | counts of both source tables; `user_outside_org_tenant`, `user_without_tenant`, `role_not_assignable` | 0 rows in both; the only pathless live user is a site admin; all 11 restored category role assignments same-tenant | IDN-01, F-13 |
| cohort_scope | `description_files_not_copied` stays 0 | 0 description files; 1 `local_groups` row on `/77` reported as a mismatch | IDN-04, F-21 |
| course_lookups | `coursedetails_candidate_open_level_not_written`; `course_lookups:tenant_unresolved` | 0 `local_coursedetails` rows; 0 unresolved | CRS-06, CRS-07, CRS-15 |
| course_tags | preflight `will_move`; if > 0, open the core tag index as a `/77` learner and confirm NO `/1` course names are listed (else ADR-031 course-listing rules before cutover); count of the lifecycle `mandatory` tag; `sentientia.lifecycle.autoenrol.enabled` stays OFF until L&D reviews the `will_move_with_the_lifecycle_mandatory_tag` list | 0 instances | CRS-09 |
| enrolments | report: BizLMS instances by method, disabled instances, pair count and regressions (ids only) of the CRS-01 access-window comparison, list of the cross-tenant pairs (course ids and pair counts, no person data); needs-owner counts `user_deleted`, `manual_enrolment_inactive`, `manual_enrolment_ends_sooner`; ONE converted learner's access before and after (and after a Sentientia unenrol of the manual row); `enrol_manual/expiredaction` = KEEP | 136 learningplan instances, all enabled, 16,830 active rows; 0, 0, 0; 40 cross-tenant pairs (24 from `/177`, 16 from `/77`, all into `/1` courses); `expiredaction` 1 (KEEP) | CRS-01..05, F-36 |
| exams | exam courses per root; the `exams.reminder_seed` counts; the guest storefront lists no exam or forum pseudo-course | 8 exam courses (`/1` 2, `/77` 5, `/177` 1), 3 closed exam quizzes, 154 enrolment rows on them | CRS-14, F-40 |
| ratings | skipped counts per reason and area; `local_ratings/review_enable`; the scanner rows are labelled | `unknown_area` about 195 (194 scanner rows), `orphan_item` about 85+, `invalid_rating` 1, `orphan_user` at most 1, `invalid_reaction` 0; `review_enable` 0 | CRS-10, CRS-12, F-41 |
| users | the F-17 facts; `users:invalid_login_row` | 4,874 error rows, 749 runs, 0 transcript rows; `local_uniquelogins` absent | IDN-02, F-17 |
| notifications | run BEFORE any step that updates deleted user rows; credential check re-measured; `team_member_copy_body_withheld`; `course_from_moduleid`; many deleted recipients sharing one `timemodified` (preflight warning); `unresolved_template_rows_naming_a_secret_word` for BOTH tables (a row with no template reference, `notification_infoid` 0 or NULL, is unresolved and loses its body too, so read `credentials_withheld:unresolved_template` against the count of such rows) | 14,197 sent, 5 not_sent, 839 withheld, 0 masked-with-body; 1,921 manager copies without body; 9,409 rows with a course; 0 rows with a reference of 0 or less, no `local_email_logs` table | COMMS-N1..N3, N6, F-67, F-68 |
| recompletion | run upgrade 2026093001 on the rehearsal copy and time `--preflight` (the 2.59M-row log, `eventname` unindexed, scanned about six times) | 0 rows in all 16 tables | F-56 |
| cart | per-table counts; `bizlms_order_floor` equals the highest imported order number; one rehearsal-only native test order gets a number above it; `decisions_not_accepted` in the report is empty; any credit or invoice row goes to Finance | history 5 lines and 5 orders, cart_id 5, ledger 0, invoices 0, credits 0; floor 5 | cart.order_number_floor, F-05 |
| skills | re-run `--preflight` on the live backup; if a level is new or renamed, update the csv BEFORE the hash is pinned | 17 levels, csv filled | LRN-07 |
| learningplan | plans with an end date in the past and self-enrol on (the enrolment window now refuses new enrolments where BizLMS only displayed dates); `creator_root` and `shared_learner_root` counts | 0 of 17 plans have dates; `orphan_course` 4 | F-59, XC-TENANT-GUESS |
| program, classroom | rows by tenant method with `creator_root` ids (> 0: stop, ask Nitin, re-pin if he changes a value); native programs with an empty level (ids only) | 1 program on `/77`; classroom 0 rows | XC-TENANT-GUESS, F-58 |
| evaluation | time the feature; count of implied assignments on identity-protected forms (ids only); pathless forms | 1 completion, 5 values; 0 anonymous completions; form 2 pathless (`/101`, no stored root) | F-29, EV-19, EV-TENANT |
| request | `local_request_comments` rows (preflight BLOCKS if > 0; the owner goes on by writing `request.comments = fold_reviewed`, which re-pins the hash, Q14); `local_learningplan_approval` rows (> 0: a decided approval is not folded into a pending row); optionally imported pending rows whose approver lacks `local/sentientia_request:approve`; `verify()` is import-time only, so run it right after the import, and a later failure of `pending_rows_with_an_approver_whose_requester_or_item_is_gone` means the importer routed a row it should not have | all 0 | COMMS-R4, R5, R6, COMMS-R1 |
| legacy_logs | row counts; include the `admin_log` settings call in the performance pass | 0 rows in `local_logs` and `local_courseerrors` | F-76 |

### D. Report lines every rehearsal must print (F-91)

1. CRS-01: the per-pair access-window comparison (pair count; regressions as ids).
2. Rows per feature by tenant method (path, costcenter, classroom, shared_learner_root, creator_root, pathless), with the
   `creator_root` ids (XC-TENANT-GUESS) and the pathless evaluation forms (EV-TENANT).
3. Every needs-owner code with its count, for the post-Stage-B `accepted_reasons` batch (IDN-02).
4. `files_copied:<component>/<area>=N` per area (IDN-04).
5. Any non-zero cart credit or invoice row, for Finance.

### E. Rules to carry into the runbooks (CRS-09, F-36, CRS-01, LRN-10, F-67)

1. NEVER uninstall `enrol_classroom`, `enrol_program`, `enrol_learningplan` or `local_courses` from Plugins overview: core
   uninstall deletes their instances, enrolments and tag instances (ADR-032 decision 2). The 9,097 folded enrolment rows on April
   keep their provenance only in the legacy rows.
2. Keep `enrol_manual/expiredaction` = KEEP (April value 1).
3. Purge caches after `course_tags` and after `enrolments`.
4. Check ONE converted learner's access before and after the enrolments step.
5. NEVER use 'Delete' on a disabled BizLMS instance in a course's Enrolment methods page (delete removes its `user_enrolments`
   rows). Undo for the CRS-01 disable step is `UPDATE {enrol} SET status = priorstatus` from the trail table.
6. NEVER re-run `--apply` of a completed core-writing feature after `bizlms_production_open`: an explicitly named feature
   re-runs even when complete (`runner.php:1009` skips only implicit ones), and `course_lookups` would refill columns an admin
   cleared.
7. No admin unenrol of an imported row before `bizlms_production_open` (LRN-10).
8. Run the notifications feature before any step that updates deleted users' rows (the DPDP anonymiser, an HRMS re-sync,
   cleanups): the deleted-at-send rule reads the user's `timemodified` (F-67).
9. No native GST tax invoice is issued to a real buyer, and `local_sentientia_cart/enabled_tenants` stays '77,177', until Airpay
   Finance answers the six points (migration plan section 11).
10. COMMS-N7 senders (all flags OFF until Nitin flips them after UAT evidence): the learning-path e-mail is sent by a
    five-minute poller (`send_path_enrolments`) that stands in for 'the learning-path enrol event' until `path_manager` calls
    `parity_senders::learning_path_enrolled()` directly; with its flag OFF it reads no row and only moves its marker. On UAT,
    before `send_course_enrolment` is flipped: check that no core enrol plugin's own welcome message is also sent to the same
    learner (a double send), and that the manager-copy delivery-log row reads 'A team member has completed ...' with no
    learner name. The e-mail templates are English only today (shared partials), so do not flip the senders for a Hindi-speaking
    audience before a per-recipient-language render lands.

### F. Owner confirmations the decisions require

| When | Who | What | Decision |
|---|---|---|---|
| before the rehearsal | Nitin | names (or confirms nobody) for the cross-tenant platform role | IDN-05 |
| before the rehearsal | Nitin | how the evidence session may turn `sentientia.evaluation.learner_history` on for one local test tenant (or he does it on UAT) | EV-18 |
| today's EOD UAT session | Nitin | list and confirm or disable each enabled `costcenterid = 0` recompletion rule on UAT; decide `run_rules` on UAT | LRN-06, F-55 |
| any time | Nitin | [CONFIRM] delete of `moodle-enhancement/local/airpay_ratings` (14 files, restorable from git); optional local `git gc` of the unreachable evaluation commit | CRS-13, F-22 |
| after the rehearsal | Nitin | written acceptance of each needs-owner reason with its count (ONE batch edit of `accepted_reasons`, then the hash is re-pinned): enrolments, course_lookups, ratings, org_roles, users, cart, notifications, request, learning plans, programs, evaluation | IDN-02, CRS-04/07/10, COMMS-C1 |
| after the rehearsal, only if shown | Nitin | programs or learning plans whose tenant came from their creator (`creator_root` ids): keep it, or import with no tenant | XC-TENANT-GUESS |
| after the rehearsal, only if shown | Finance (via Nitin) | any non-zero credit balance (count, INR total, tenant) or ERPNext invoice row | cart.credit_balances, cart.erpnext_invoices_legal |
| before cutover | Finance (via Nitin) | the six native-tax-invoice points | cart.native_tax_invoices |
| before cutover, after the evidence | Nitin | reader flags for Airpay (imported e-mail history and body detail, request history, `legacy_logs` report OFF, ratings widget, reactions, reviews OFF, evaluation drilldown, users sync history, notification senders) | COMMS-C2, CRS-11, CRS-12, EV-06, IDN-07, COMMS-N7 |

### G. Visual evidence owed before any reader flag is flipped (F-06, F-16, F-46, F-71; EV-18, COMMS-N5, COMMS-R3)

CLAUDE.md section 5: desktop and 590 px screenshots, a README in `docs/visual-evidence/<date>/<feature>/`, and Nitin reviews. Test
personas and test data only; no real name may appear (the local XAMPP holds a copy of production users). Every reader flag stays
OFF until he says so. Capture each page with the flag OFF and ON, as a learner and as a tenant admin:

- cart: `credits.php`, the 'Issued in ERPNext as' invoice view, `return.php` and `history.php` status rendering, the
  admin_orders Staff notes column, the checkout error path, and the catalogue price and basket after `cart.price_source`.
- users: `sync_runs.php` and `sync_run_detail.php`, desktop and mobile, with `sentientia.users.imported_sync_history` OFF and ON,
  as the uploader, as a same-tenant colleague who did not upload the run, and as a cross-tenant admin. IDN-07's hiding of the
  rejected lines is NOT behind a flag and changes native runs too, so capture a native run as the colleague (counts, no lines).
- learningplan, program, classroom, recompletion, skills: `mypaths.php` and the `view.php` cover; `myprograms.php`, the `view.php`
  levels tab, roster 'Completed on' and the logo; the classroom list with Draft and On hold, the edit form, 'No limit', the
  overview, roster completion columns, `my.php` and bulk enrol by audience; recompletion `history.php`, `history_detail.php`,
  `index.php` and the `edit.php` refusal; My Skills, the skill page levels tab, profile chips, the catalog level badge and the
  recommendation rail.
- notifications and request: the Logs tab (Sent from, Sent on, the not_sent and other badges, the BizLMS badge),
  `email_detail.php`, the Templates tab as a `/77` admin, My requests, Pending approvals, All requests and `admin_log.php`.
- evaluation: `my_evaluations.php` (named responded, anonymous with the reworded note, waiting, closed, the imported badge, the SP
  case), `response_list.php` and `response_detail.php`, and the `claude/eval-followups` pages.
- ratings and exams: the course-page stars with `sentientia.ratings.widget` OFF and ON; the guest storefront and the in-progress
  rail after CRS-14.
- the navigation entries added with each flag flip (F-47).

## Rollback on the sandbox

Disposable by design — drop the restored DB / re-restore. Nothing else is affected.

## Local rehearsal evidence

**2026-06-10.** Executed the exact source→target shape on this workstation: baseline from the 5.1 source DB
(`moodle`, 2,888 active users) → compare on the migrated 5.2 clone (`moodle52_cut1`).
Result: **every metric matched except 4 — and all 4 attribute precisely to the FOOLPROOF campaign's
own test writes on the clone** (+1 signup-test user, +2 enrol_now test enrolments, +2 reminder-audit
rows from the cron tests). Detection works at single-row granularity; 32,248 completions,
11,415 certificate issues, 8,687 quiz attempts, 27,166 grades all MATCH.

**2026-10-07 (Stage B tooling, the real source).** Source: `backups/airpayprod-mariadb-ready.sql` (the MariaDB-ready
conversion of the production mysqldump that completed 2026-04-06, release `4.1.2+ (Build: 20230401)`, version `2022112802.06`; 3.53 GB, restored into a scratch
schema in 49 min on this machine; 580 tables, 2,871 active users). The dumps in `D:\Claude Local\Moodle Backup\` are NOT
that copy: `pre-moodle5-backup*.sql` and `moodle_local_pre_import_20260407.sql` are Moodle 4.5.10, `pre-wipe-backup-20260604`
and `sw4-moodle-dump-2026-06-10.sql` are 5.1.3. Baseline taken with `source_baseline.php` on the 4.1.2 copy; compared with the
rehearsal copy that went 4.1.2 → 4.5.10 → 5.1.3 (`bizlms_april`, no import run): **exit 0, 100% PARITY** — all 23 counts, the
grade sum, 14 checksums (SCORM read from `scorm_scoes_track` on one side and `scorm_scoes_value` on the other, same CRC) and all
95 BizLMS tables. A same-length text change in one `local_costcenter` row of the scratch copy moved only that table's CRC
(count and max id unchanged) and turned the result into exit 1; reverting it restored exit 0. The only difference the
first run found was a column set: the 5.x `tool_certificate_issues` has an `archived` column the 4.1.2 BizLMS table lacks, so
it is no longer in that table's checksum.
