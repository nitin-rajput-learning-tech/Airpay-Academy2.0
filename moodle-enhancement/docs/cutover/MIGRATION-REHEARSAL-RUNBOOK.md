# Ninja-Sandbox Migration Rehearsal Runbook (rollout-gate Phase 2)

> **2026-09-29:** live is Moodle 4.1.2 (not 5.1), so the upgrade is two hops (4.1.2 → 4.5 → 5.2);
> the Sentientia migration plan §0 has the details. Steps 3 and 4e below were updated for it.
>
> **2026-10-07:** the BizLMS feature-data import (ADR-032) is part of the rehearsal. Step 4g and the section
> "BizLMS import: Stage B checks" below carry the checks and owner confirmations the 2026-10-07 owner decisions
> require; step 5 is amended (decision F-34). Per-decision detail: `OWNER-DECISIONS-2026-10-07.md`.

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
   **Corrected 2026-09-29: live is Moodle 4.1.2, not 5.1.** 5.2 requires 4.4 or later
   (`admin/environment.xml`), so the rehearsal upgrades twice: 4.1.2 → 4.5.x on the 4.5 core, then
   4.5 → 5.2 with the Sentientia package. See `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` §0.
   Time both hops, run hop 1 on the target engine (MySQL 8.4), and capture parity after each hop.
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
   g. **BizLMS feature-data import (added 2026-10-07; ADR-032 cutover slice).** After the repairs above and before the
      data-intact gate: `php local/sentientia_platform/cli/import_bizlms.php --preflight --all --decisions=<signed file>`
      (no blockers), then the dry run, then `--all --apply --confirm=<fingerprint> --decisions=<signed file> --report=<file>`
      under the guard conditions (maintenance on, cron off, `noemailever`, the armed window). At the rehearsal record the
      decisions sha256: it is the hash cutover must match (`--expect-decisions-hash`). Run the checks in "BizLMS import: Stage B
      checks" below; the importers' data changes explain deltas in step 5.
5. **Purge caches**, then **data-intact gate:**
   `php local/sentientia_platform/cli/migration_parity_check.php --compare=/path/live-baseline.json`
   → **must print `RESULT: 100% PARITY — data intact.`** Any DRIFT line = stop + investigate.
   **Amended 2026-10-07 (decision F-34):** once the BizLMS import has been applied, the core counts and checksums are no
   longer unchanged by design: the `enrolments` importer adds about 7,733 `user_enrolments` rows (April) and changes
   `enrol.status` (CRS-01). `migration_parity_check.php` (wired to `parity::`, with `--decisions` and `--expect-decisions-hash`)
   must EXPLAIN those two deltas through the `legacymap` rows of the `enrolments` feature (target tables `user_enrolments` and
   `enrol`, outcome `imported`) and the `enrolmove` ledger, and report any unexplained delta as DRIFT. Exit 2 (needs-owner
   reasons not accepted, or an unclaimed legacy table with rows) is allowed only with Nitin's written acceptance. Until that is
   built this gate fails by design on an imported copy.
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
| notifications | run BEFORE any step that updates deleted user rows; credential check re-measured; `team_member_copy_body_withheld`; `course_from_moduleid`; many deleted recipients sharing one `timemodified` (preflight warning) | 14,197 sent, 5 not_sent, 839 withheld, 0 masked-with-body; 1,921 manager copies without body; 9,409 rows with a course | COMMS-N1..N3, N6, F-67, F-68 |
| recompletion | run upgrade 2026093001 on the rehearsal copy and time `--preflight` (the 2.59M-row log, `eventname` unindexed, scanned about six times) | 0 rows in all 16 tables | F-56 |
| cart | per-table counts; `bizlms_order_floor` equals the highest imported order number; one rehearsal-only native test order gets a number above it; `decisions_not_accepted` in the report is empty; any credit or invoice row goes to Finance | history 5 lines and 5 orders, cart_id 5, ledger 0, invoices 0, credits 0; floor 5 | cart.order_number_floor, F-05 |
| skills | re-run `--preflight` on the live backup; if a level is new or renamed, update the csv BEFORE the hash is pinned | 17 levels, csv filled | LRN-07 |
| learningplan | plans with an end date in the past and self-enrol on (the enrolment window now refuses new enrolments where BizLMS only displayed dates); `creator_root` and `shared_learner_root` counts | 0 of 17 plans have dates; `orphan_course` 4 | F-59, XC-TENANT-GUESS |
| program, classroom | rows by tenant method with `creator_root` ids (> 0: stop, ask Nitin, re-pin if he changes a value); native programs with an empty level (ids only) | 1 program on `/77`; classroom 0 rows | XC-TENANT-GUESS, F-58 |
| evaluation | time the feature; count of implied assignments on identity-protected forms (ids only); pathless forms | 1 completion, 5 values; 0 anonymous completions; form 2 pathless (`/101`, no stored root) | F-29, EV-19, EV-TENANT |
| request | `local_request_comments` rows (preflight BLOCKS if > 0); `local_learningplan_approval` rows (> 0: a decided approval is not folded into a pending row); optionally imported pending rows whose approver lacks `local/sentientia_request:approve` | all 0 | COMMS-R4, R5, R6 |
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
- users: `sync_runs.php` and `sync_run_detail.php` as a tenant manager (uploader and non-uploader) and as a cross-tenant admin.
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

## Local rehearsal evidence (2026-06-10)

Executed the exact source→target shape on this workstation: baseline from the 5.1 source DB
(`moodle`, 2,888 active users) → compare on the migrated 5.2 clone (`moodle52_cut1`).
Result: **every metric matched except 4 — and all 4 attribute precisely to the FOOLPROOF campaign's
own test writes on the clone** (+1 signup-test user, +2 enrol_now test enrolments, +2 reminder-audit
rows from the cron tests). Detection works at single-row granularity; 32,248 completions,
11,415 certificate issues, 8,687 quiz attempts, 27,166 grades all MATCH.
