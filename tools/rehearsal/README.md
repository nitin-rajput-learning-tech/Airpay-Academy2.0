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
first failure, and always finishes with the summary.

## What it refuses (step 00, and every step re-checks the policy when it loads the env)

* a database that is not on the explicit allow-list `REHEARSAL_DB_ALLOWLIST`; a name with `prod` or `uat` anywhere in it
  (production's database is `airpayprod`, UAT's `sentientia_uat`: no underscore boundary is needed), `live` as a word, or a
  system schema (`moodle`, `mysql`, ...) is refused even if it is listed; and a database **server** that holds the schema
  `airpayprod` (or one named in `FORBIDDEN_SERVER_SCHEMAS`) is refused;
* any production hostname (`PRODUCTION_HOSTNAMES`: the live and UAT sites; substring match) in the database host, the
  wwwroot, the paths, the env file's host settings, or any string of a `config.php` already on the box. The live **database
  endpoint** (`PRODUCTION_DB_ENDPOINT`, dbhost of live's config.php) is required with `--execute` and joins that list, so a
  `DB_HOST` copied from live's config cannot get through;
* a database or moodledata that does not carry the **kit marker**: step 01 stamps the database (a `{config}` row) and the
  moodledata (a file) with a random restore id, and every writing step (02 to 11) refuses a database or directory without
  it, so an allow-listed name on the wrong server, or UAT's database, is never written to. A database restored by hand is
  stamped only on `RESTORE_DONE_BY_HAND=<its name>` with `RESTORE_DB_DUMP` unset (both set is refused outright: the first stays
  in `rehearsal.env` until cleared) and never when a kit restore started and did not complete (drop the database and create it
  empty first; the kit sees it empty, archives the failed restore's record, and the hand restore follows). That record names the
  database it was writing to (`state/kv/restore.started_db`: host, port, name) and is cleared **only** when that same database is
  seen absent or empty, on **two reads that agree**: `probe_db` never reads an empty answer to its SCHEMATA or TABLES count as
  "absent" or "empty" (it asks again, and an answer that stays empty is "cannot be reached"), and a second read after a pause must
  say the same. A run for any other database name, host or port refuses and leaves the record alone, and so does a restore into
  another database (it would archive the record with the rest of the state). A populated moodledata without the marker is refused too, unless
  `RESTORE_MOODLEDATA_BY_HAND=<its path>` (a statement of its own: the database statement does not cover the directory) and
  `sessions/` and `localcache/` show no write in the last 30 minutes (a running site, UAT's for one, is never adopted).
  **One rehearsal, one moodledata:** a new restore into an empty database refuses a moodledata that an earlier rehearsal ran in
  (its role-9 state file, caches, sessions and cron files would carry over) unless it is the finished unpack of the same
  `RESTORE_MOODLEDATA_ARCHIVE` that no step after 01 has used (that fact is written to `restore-ids.log` when the new restore
  moves the earlier state to `archive/`, so a new restore that dies and is retried still refuses the used dataroot); empty it or
  point `MOODLEDATA` at a new directory. A named
  `RESTORE_MOODLEDATA_ARCHIVE` is never ignored: with a filedir already present it must be that archive's unpack (the marker
  file records the archive's path, size and mtime, and that the unpack finished), or step 01 stops;
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
| 01 | `01_restore_check.sh` | Restores the dump into an empty database and unpacks the moodledata if asked (`RESTORE_*`). The dump is **refused** when it holds `USE` / `CREATE DATABASE` / `DROP DATABASE` (it would reach another schema, whatever the allow-list says; the client also runs with `--one-database`), a `SET @@GLOBAL.GTID_PURGED` (a server-wide setting: dump with `--set-gtid-purged=OFF`), or has no `-- Dump completed` trailer (an aborted `mysqldump` restores as a silent partial copy), or names a MySQL 8 collation (`utf8mb4_0900_*`) when the server is MariaDB; the restore stops at the first error (pipefail), and a restore that did not complete is refused on re-run, whatever `RESTORE_DONE_BY_HAND` says (drop the partial database and create it empty; the kit then sees THAT database (the record names host, port and name) absent or empty on two reads that agree and moves the record of the failed restore to `archive/`; a hand restore then follows: run step 01 once on the empty database, restore by hand, run step 01 with `RESTORE_DB_DUMP` unset and `RESTORE_DONE_BY_HAND=<its name>`; `RESTORE_DONE_BY_HAND` together with `RESTORE_DB_DUMP` is refused outright). **Stamps the database and the moodledata with the restore id** (see "What it refuses"); the marker file also records which archive was unpacked and that it finished. A new restore moves the earlier rehearsal's state, reports, baseline and cache configuration to `archive/`. Blanks the stored OAuth2 system-account tokens when the audit finds any. Checks release (`SOURCE_RELEASE_REGEX`, live is 4.1.x), active users, the BizLMS `open_path` substrate. **File store gate:** every distinct `files.contenthash` with content must exist at `filedir/ab/cd/<hash>`; missing ones go to `reports/filedir-missing.txt` and stop the step (a DB-only restore 404s every SCORM package). Then neutralises the restore: wipes `smtphosts/smtpuser/smtppass` and the push-service key (airnotifier), sets `cron_enabled = 0`, moves the restored `muc/config.php` aside, writes the restored mail backlog audit (input I-11) and an audit of the other outbound settings (counts only). With `LIVE_BASELINE_FILE` it compares the restored copy with live's own baseline (isolates restore loss). | runbook 1; plan 4c, 4f-a |
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

## Testing the kit

`bash tools/rehearsal/selftest.sh` (needs bash, PHP, coreutils; no database, no Moodle): the policy refusals (the database name
guard, the live endpoint), the config checks, the generated `config.php` (a hostile password round-trips, the guard exits, a
foreign config is not overwritten, the cache configuration points at the kit's directory), the dump scan (a `USE`, `CREATE
DATABASE` or `DROP DATABASE` statement, a missing trailer, plain and gzip), the file store comparison, `judge()`,
`unpack_tree`, the helpers that decide the re-runs of steps 04, 06 and 09, the whole-path cron scan, the archive identity and the
marker's unpack record, the GTID and MariaDB-collation dump checks, the snapshot acknowledgement, the tree manifest hash, the state rotation, step 01's
decision on a populated database (run in `--execute` mode against a stand-in `mysql` client, still no database: `RESTORE_DONE_BY_HAND` with
`RESTORE_DB_DUMP` refused, a restore that did not complete refused whatever `RESTORE_DONE_BY_HAND` says and cleared only by the kit seeing the
database empty, a plain hand restore adopted), the orchestrator in DRY mode, and that no Windows path is hard-coded. Every kit script passes `bash -n` (the selftest runs it);
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

**Not run: any step against real Moodle 4.5 or 5.x code, `shellcheck` (not installed on that machine), PHPUnit.** The stand-in
proves the kit's logic and parsing, not that the real tools print exactly what it expects: the first execution on the
target box does. Parsing that depends on real output (the `--status` fact lines, the repair scripts' result lines, the
`upgrade.php` success line, `meta.decisions_hash` in the report, the role-9 dry-run line) was written from the tools' source.
