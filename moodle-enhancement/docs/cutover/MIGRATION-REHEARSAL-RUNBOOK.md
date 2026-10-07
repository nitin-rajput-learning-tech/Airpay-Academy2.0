# Ninja-Sandbox Migration Rehearsal Runbook (rollout-gate Phase 2)

> **2026-09-29:** live is Moodle 4.1.2 (not 5.1), so the upgrade is two hops (4.1.2 → 4.5 → 5.2); the Sentientia
> migration plan §0 has the details. Steps 3 and 4e below were updated for it.
>
> **2026-10-07:** the parity gate is rebuilt for Stage B (steps 0, 3 and 5, and the new step 5a). The baseline is
> taken with ONE standalone file that needs no Sentientia plugin and works on 4.1.2 (it could not before: the old tool
> read `scorm_attempt`, which exists only from Moodle 4.3). The legacy BizLMS tables are fingerprinted in it, and a
> post-import mode proves that everything the import changed is in its own records.

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
2. Fresh LIVE backup: full DB dump + `moodledata` archive (+ the live `config.php` for reference).
3. The `production` branch checkout (or release archive) — carries the entire product layer.

## The parity tool, in one place

| Where it runs | Command | What it needs |
|---|---|---|
| The SOURCE (a restored 4.1.2 copy, or live in the §4b freeze), and the 4.5 checkpoint after hop 1 | `php source_baseline.php --config=/path/config.php --baseline=FILE` / `--compare=FILE` | the single file `local/sentientia_platform/cli/source_baseline.php`, PHP 7.4 to 8.4, mysqli, the box's own `config.php`. No Moodle load, no Sentientia plugin. |
| A Sentientia target (after hop 2 and the repairs) | `php local/sentientia_platform/cli/migration_parity_check.php --compare=FILE` | the deployed plugin |
| A Sentientia target, after the import | `... --compare=FILE --after-import --decisions=FILE [--run=ID] [--report=FILE]` | the deployed plugin and the import's own records |

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
   e. **ADR-031 role configuration (added 2026-09-29):** the role-9 core-cap script and the
      platform-role script, dry run first, then the read-only WS smoke — migration plan §4f-f.
      These scripts are UAT-locked today; they need a target guard first.
   f. **Site theme (added 2026-10-01):** production's `$CFG->theme` is `epsilon`, which is not in the
      package, so pages fall back to stock boost (seen in the 2026-10-01 rehearsal upgrade log).
      `php admin/cli/cfg.php --name=theme --set=sentientia`; April has no user/course/category/cohort
      overrides (migration plan §8 step 7).
5. **Purge caches**, then **data-intact gate (before the import):**
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/vault/live-baseline.json`
   → **must print `RESULT: 100% PARITY - counts AND value checksums match, and the BizLMS legacy tables are untouched.`**
   (exit 0). Any DRIFT line = stop + investigate; exit 2 = not proven = stop. Nothing but the upgrade has touched the
   data at this point, so every table must match, the five core tables the import will write included.
5a. **The BizLMS import and its proof (ADR-032 "Build and run order", cutover slice):** snapshot, maintenance on, cron off,
   `noemailever` on, arm the guard, then `import_bizlms.php --preflight --all ...` and `--all --apply ... --report=FILE`.
   Then
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/vault/live-baseline.json --after-import --decisions=<the rehearsed decisions> [--expect-decisions-hash=<hash>] [--run=<id>] [--report=<the --report file>]`
   → exit 0, or exit 2 with Nitin's written acceptance. It holds everything to the SOURCE baseline except what the import
   itself wrote, and for that it asks the import's own records: `user_enrolments`, `enrol` and `role_assignments` may have
   grown by exactly the rows `local_sentientia_legacymap` says were imported (the April dry run predicts +7,733
   enrolments and +19 enrol instances); a course may differ from the baseline in exactly the `open_*` columns its
   `local_sentientia_courses_detailfill` row names; a tag instance may have moved exactly as `..._tagmove` says. A changed
   old row (an `enrol.status`, an enrolment date), an extra or missing row, a column the ledger does not name, and any
   change at all to a BizLMS legacy table is exit 1. It also runs the `bizlms_import` invariant and lists the needs-owner
   reasons the decisions do not accept (exit 2). Exit 3 means it refused (no complete apply run of this install, a run id
   that is not one, decisions that do not hash to `--expect-decisions-hash`).
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
