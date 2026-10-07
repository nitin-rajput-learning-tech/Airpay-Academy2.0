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
cp tools/rehearsal/rehearsal.env.example tools/rehearsal/rehearsal.env     # edit: paths, database, wwwroot
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

* a database that is not on the explicit allow-list `REHEARSAL_DB_ALLOWLIST`; a name that looks like production
  (`prod`, `production`, `live`) or a system schema (`moodle`, `mysql`, ...) is refused even if it is listed;
* any production hostname (`PRODUCTION_HOSTNAMES`, substring match) in the database host, the wwwroot, the paths, the
  env file's host settings, or any string of a `config.php` already on the box;
* a `config.php` that points at another database or host, names another wwwroot or dataroot, or lacks
  `$CFG->noemailever = true` (the restored dump holds real e-mail addresses: the May 2026 incident, 151 e-mails);
* a scheduler that runs Moodle's cron (crontab of the running user, `/etc/crontab`, `/etc/cron.d`, systemd timers) and,
  once step 01 has set it, a database whose `cron_enabled` is not 0;
* Windows paths, one directory for both code trees, a code tree inside the other, a password file readable by others,
  an env file writable by others, running as anyone but `WEB_USER`.

The `config.php` the kit writes (`lib/make_config.sh`, the port of `make_config.py`) carries a **REHEARSAL GUARD** that
exits on every load, CLI or web, when the database is not on the allow-list or the database host or wwwroot names a
production host. The kit never overwrites a `config.php` it did not write, never drops or truncates anything, restores
only into an EMPTY database, and never flips a feature flag.

## The steps

| Step | Script | What it does | Runbook / plan |
|---|---|---|---|
| 00 | `00_preflight.sh` | The refusals above. Changes nothing, even with `--execute`. | runbook Inputs, step 1; plan 1.3, 4d.3, 8-2 |
| 01 | `01_restore_check.sh` | Restores the dump into an empty database and unpacks the moodledata if asked (`RESTORE_*`). Checks release (`SOURCE_RELEASE_REGEX`, live is 4.1.x), active users, the BizLMS `open_path` substrate. **File store gate:** every distinct `files.contenthash` with content must exist at `filedir/ab/cd/<hash>`; missing ones go to `reports/filedir-missing.txt` and stop the step (a DB-only restore 404s every SCORM package). Then neutralises the restore: wipes `smtphosts/smtpuser/smtppass`, sets `cron_enabled = 0`, writes the restored mail backlog audit (input I-11). With `LIVE_BASELINE_FILE` it compares the restored copy with live's own baseline (isolates restore loss). | runbook 1; plan 4c, 4f-a |
| 02 | `02_source_baseline.sh` | `source_baseline.php` on the restored 4.1.x copy, before any upgrade. Refuses on an upgraded copy. An existing baseline is re-verified, never retaken. `LIVE_BASELINE_FILE` makes live's own baseline the baseline of record. Records the SHA-256. | runbook 0; plan 4a, 5.1; ADR-032 Parity hooks |
| 03 | `03_hop1_to_45.sh` | Hop 1 on a clean Moodle 4.5 core, **BizLMS code off disk** (checked against `lib/bizlms_plugins.txt`), config with the DB guard, PHP 8.1-8.3 and extensions, restore point, `upgrade.php --non-interactive` timed, release and pending checks, the plugins "missing from disk" listed, then **parity after hop 1** (`source_baseline.php --compare`, exit 0). | runbook 3; plan 0, I-4 |
| 04 | `04_hop2_to_5x.sh` | Hop 2 on the Sentientia package in its own directory: SHA-256 gate, the package's CLI files present, no leaked `config.php`, BizLMS code and `local/airpay_ratings` off disk, PHP 8.3, `max_allowed_packet >= 64M`, restore point, upgrade timed, Sentientia plugins installed, `enrol_sentientiasub` not enabled, then **parity after hop 2** (`migration_parity_check.php --compare`). | runbook 2-3; plan 0, 4d, 4e |
| 05 | `05_repairs.sh` | CLI maintenance on; `repair_task_registrations.php` dry run then `--apply` (the message preference check must report 0 problems); the tenant seed and tenant/org parity checks (`TENANT_CHECKS`); the ADR-032 **capability repair**: inventory, check against `docs/cutover/bizlms-capability-allowlist.json` (exit 0 required), `--apply --confirm=<fingerprint>`, check again. | runbook 4a-4c, 4e; ADR-032 Capabilities |
| 06 | `06_adr031_roles.sh` | The four ADR-031 scripts from `tools/uat/` in **target mode** (`--target=<wwwroot> --config=<config.php>`): predeploy probe, role-9 core caps (dry run, apply), cross-tenant role (dry run, apply), read-only web-service smoke (`ERROR=0` required). | runbook 4f; plan 4f-f |
| 07 | `07_theme_switch.sh` | `cfg.php --name=theme --set=sentientia` (production's epsilon is not in the package), theme overrides checked, purge, landing posture printed. | runbook 4g; plan 8-7 |
| 08 | `08_import_guard.sh` | Arms the ADR-032 guard: `cron.php --disable`, CLI maintenance on, `noemailever` confirmed, the standard log store, no restored task row marked running, `bizlms_import_armed_until`; then `import_bizlms.php --status` must show every fact the guard needs. | runbook 5a; ADR-032 Gating |
| 09 | `09_import.sh` | The data-intact gate (`migration_parity_check.php --compare`, exit 0), `--preflight --all`, a dry run with `--report` (**records the decisions hash** from `meta.decisions_hash`), `--all --apply --confirm=<fp> --decisions=... --expect-decisions-hash=<hash> --report=...`, `--verify --all`. All 19 importers. | runbook 5, 5a; ADR-032 CLI |
| 10 | `10_parity_compare.sh` | `migration_parity_check.php --compare ... --after-import --decisions=... --expect-decisions-hash=... --run=<id> --report=<apply report>`: everything equals the source baseline except what the import's own records explain. | runbook 5a; ADR-032 Parity hooks 2, 4 |
| 11 | `11_cron_cycle.sh` | One cron cycle (`cron.php --force --keep-alive=0`) under `noemailever`, timed, with the restored backlog before and after, the e-mails `noemailever` swallowed counted, and the **`transfer_question_categories` task timed** from `{task_log}`; `checks.php`; the parity once more (informational). | ADR-032 Cutover slice 7; plan 8-2, 9 |
| 12 | `12_summary.sh` | `reports/summary.md`: steps and seconds, the I-4 hard-down estimate, the parity checkpoints, the evidence hashes, accepted unproven items, the seven rollout-gate items and who proves each. Runs even after a failure. | runbook 7; plan 9, 10 |

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
```

## Re-running, resuming, rolling back

Every step is idempotent, and a whole `run_all.sh --execute` can be repeated on a finished rehearsal. A step that already
finished says so and checks again instead of redoing: the restore never writes over a populated database, the mail backlog
audit keeps the first (pre-wipe) numbers, the baseline is verified, never retaken (after the hops it only confirms that the
recorded file is intact), a hop that is done is skipped, the repairs and the role scripts are idempotent, and once the
import is applied (`state/kv/import.applied`) step 09 runs only the verify, because the gate before the import describes
a database that no longer exists. After fixing the cause of a failure, `run_all.sh --execute --from NN`. The rollback of a failed hop or import is the restore point taken before it
(`SNAPSHOT_HOOK`, or by hand: the kit prints a reminder). In a rehearsal the import can also be purged and repeated
(`import_bizlms.php --purge-feature`) while `bizlms_production` is not 1 (`BIZLMS_PRODUCTION_FLAG=1` in step 08 makes the
rehearsal the exact cutover form, where the snapshot is the only way back).

## Cutover day

The same scripts are the cutover procedure after the rehearsal is signed off: the same env file shape, with the target's
paths. Cutover differs in three places: `LIVE_BASELINE_FILE` is the baseline taken on live at the freeze (migration plan
4a/4b); `BIZLMS_PRODUCTION_FLAG=1`; and `IMPORT_EXPECT_DECISIONS_HASH` is the hash step 09 recorded in the rehearsal, so a
changed decisions file is refused. The freeze, backup, DNS and repoint steps of the migration plan are IT's and are not
here.

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

## Testing the kit

`bash tools/rehearsal/selftest.sh` (needs bash, PHP, coreutils; no database, no Moodle): the policy refusals, the config
checks, the generated `config.php` (a hostile password round-trips, the guard exits, a foreign config is not overwritten),
the file store comparison, `judge()`, `unpack_tree`, the orchestrator in DRY mode, and that no Windows path is hard-coded.
`bash -n` passes on every script; `shellcheck` was not available when the kit was written, run it where it is.

Verified for this commit, on a Windows workstation (Git Bash, PHP 8.2, MariaDB 10.11, scratch schemas named `stageb_*`):

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
* `selftest.sh`.

**Not run: any step against real Moodle 4.5 or 5.x code, `shellcheck` (not installed on that machine), PHPUnit.** The stand-in
proves the kit's logic and parsing, not that the real tools print exactly what it expects: the first execution on the
target box does. Parsing that depends on real output (the `--status` fact lines, the repair scripts' result lines, the
`upgrade.php` success line, `meta.decisions_hash` in the report) was written from the tools' source.
