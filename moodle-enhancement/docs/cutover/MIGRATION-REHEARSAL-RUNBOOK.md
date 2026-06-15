# Ninja-Sandbox Migration Rehearsal Runbook (rollout-gate Phase 2)

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

## Procedure (each step has a verify; stop on any failure)

0. **Baseline on LIVE (before the backup is taken, or against the restored copy pre-upgrade):**
   `php local/sentientia_platform/cli/migration_parity_check.php --baseline=/path/live-baseline.json`
   — if the CLI can't run on live yet (plugin not deployed), restore the backup on the sandbox FIRST,
   run the baseline there pre-upgrade, then proceed. Either way the baseline must represent the
   pre-migration data.
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
2. **Deploy the Sentientia tree** (production branch) over the sandbox webroot per
   `ROLLOUT-PACKET-2026-06-10.md` step 1 (theme/sentientia, local/, payment, blocks, enrol,
   quizaccess).
3. **Upgrade:** `php admin/cli/upgrade.php --non-interactive` (locally proven: 2,057 steps, 5.1→5.2,
   zero errors — if the sandbox starts from the live 5.1-era stack the same path applies; for the
   5.2 jump confirm the PHP pre-checks first).
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
5. **Purge caches**, then **data-intact gate:**
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/path/live-baseline.json`
   → **must print `RESULT: 100% PARITY — data intact.`** Any DRIFT line = stop + investigate.
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

## Local rehearsal evidence (2026-06-10)

Executed the exact source→target shape on this workstation: baseline from the 5.1 source DB
(`moodle`, 2,888 active users) → compare on the migrated 5.2 clone (`moodle52_cut1`).
Result: **every metric matched except 4 — and all 4 attribute precisely to the FOOLPROOF campaign's
own test writes on the clone** (+1 signup-test user, +2 enrol_now test enrolments, +2 reminder-audit
rows from the cron tests). Detection works at single-row granularity; 32,248 completions,
11,415 certificate issues, 8,687 quiz attempts, 27,166 grades all MATCH.
