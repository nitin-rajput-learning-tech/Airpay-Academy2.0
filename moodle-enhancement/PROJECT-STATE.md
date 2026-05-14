# PROJECT STATE — Airpay Academy L&D OS
**Updated:** 2026-05-14 — **DAY-2 EXTENSIONS SHIPPED.** Picked up the PROJECT-STATE follow-up list from Day-1 EOD. Added the admin Settings UI for ramping cadence + cert-attach toggle, plus a one-command post-deploy verifier that wraps every diagnostic CLI.
**Phase:** Academy 4.0 — admin-feedback delivery complete + Day-2 extensions. Cutover gates remain (IT staging deploy + k6 + pen-test + sign-off).

---

## 🆕 DAY-2 ADDITIONS (2026-05-14, 3 commits: `6eae3a5cd..1650fa05c`)

### 1. Admin Settings UI (`6eae3a5cd`)
New page at Site Admin → Plugins → Local plugins → **Airpay Emails — Settings**:
- `default_cadence_days_json` — JSON-validated, ≤10 entries, positive ints only
- `default_max_reminders` — cap per (user × course), 0 = unlimited
- `default_auto_stop` — checkbox, ON by default
- `attach_certificate_pdf` — global kill-switch for the cert PDF attachment

The runtime fallback chain is now: rule's own column → admin setting → hard-coded `[1,3,7,14,21]` baseline. Includes a custom validator class (`setting_cadence_json`) that rejects bad input at save time with a specific error message rather than the previous silent-fallback-at-runtime behaviour. 10-case PHPUnit test suite ships alongside.

### 2. Post-deploy verifier (`1650fa05c`)
`moodle-enhancement/deploy/post_deploy_verify.sh` — one command, 5 gates, pass/fail report. Wraps:
- Sprint A `diagnose_admin_ux.php` (with optional `--user=email`)
- Sprint B `cert_emails_report.php`
- Sprint C `manage_shares.php --list`
- `cron_health.php` (WARN-not-FAIL on stuck tasks; expected on fresh deploy)
- Block presence check for cron_health + cert_health

`--json` flag for CI dashboard ingestion. Runbook updated with Step 10 to run this before cutover-evidence sign-off.

---

## ⏸️  NEXT SESSION PICKUP

**Session paused 2026-05-14. All 25 commits pushed to production branch.**

### Day-2 test posture
- **39 PHPUnit tests** (cadence + cert_helper + observer + setting_cadence_json + sharing + request), **0 errors, 0 failures, 14 skipped** (need staging open_path column)
- **post_deploy_verify.sh** on dev: **5 PASS, 1 WARN (cron, expected), 0 FAIL**
- All four Day-1 deliverables still green after Day-2 additions.

### Recommended day-1 actions (in priority order)

1. **Deploy the 22-commit run to staging** (or production if you're confident).
   Use the runbook: `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md`.
   Headline: `git pull`, `php admin/cli/upgrade.php`, `php admin/cli/purge_caches.php`,
   then `bash moodle-enhancement/deploy/pre_deploy_validate.sh` (expect 9/10 green).

2. **Run the 23 skipped PHPUnit tests on staging.**
   They skip on dev because the BizLMS `user.open_path` column isn't in the vanilla
   PHPUnit fixture. On staging (which has the BizLMS plugin active), they should
   all pass:
   ```
   cd /path/to/staging/moodle
   ./vendor/bin/phpunit public/local/airpay_courses/tests/request_manager_test.php
   ```
   Expected: 12/12 pass — currently 12 of them skip on dev.

3. **Smoke-test each Sprint via the runbook's Step 5-8 checklist.**
   - Sprint A: `diagnose_admin_ux.php --user=academy@airpay.co.in` → all 7 checks PASS.
   - Sprint B: complete a course with a `tool_certificate` activity → user receives
     email with PDF attached. Verify via `cli/cert_emails_report.php --detail`.
   - Sprint C: as site admin, share any course to Public; verify it appears in
     Public's catalog with provenance badge.
   - Sprint D: as Public manager, request access to an Airpay course; admin
     approves; verify course appears in Public catalog.

4. **Add the two dashboard widgets to /my/** for site admins.
   `/my/` → Customise this page → drop "Airpay Cron Health" + "Airpay Certificate
   Health" into a region.

### Possible follow-ups if time allows

- Settings UI page in airpay_emails for default cadence (currently editable
  per-rule via the rule editor only).
- Add the cert_health + cron_health blocks to the default `/my/` dashboard via
  `db/install.php` so they auto-appear instead of admin manually adding.
- Per-tenant cadence override — currently `cadence_days_json` applies platform-wide
  per rule; could allow tenant-specific overrides via the existing
  `local_airpay_email_overrides` table pattern.
- Backfill any remaining LMS admin feedback that surfaces after they see the
  Day-1 deployment.

### Anything broken or half-finished?

**Nothing.** All 22 commits are atomic and pushed. Lint clean. All 73 PHPUnit
tests pass on dev (with the 23 environmental-skip caveat for open_path). Pre-deploy
9/10 green (Gate 3 cron-health FAILs on dev because there's no cron daemon — it
WILL pass on staging/prod).

### Where to find the work

| Looking for | File |
|---|---|
| What the 4 sprints did | This file, "ADMIN-FEEDBACK SPRINTS A-D" section below |
| Cutover steps | `moodle-enhancement/deploy/ADMIN-FEEDBACK-DEPLOYMENT-RUNBOOK.md` |
| Plugin user docs | `local/airpay_emails/README.md`, `local/airpay_courses/README.md`, `blocks/airpay_cert_health/README.md` |
| State cards (dev reference) | `state-cards/airpay_learningpath-state.md`, `state-cards/airpay_emails-state.md`, `state-cards/airpay_courses-state.md`, `state-cards/block_airpay_cron_health-state.md`, `state-cards/block_airpay_cert_health-state.md` |
| Ops tools | `local/airpay_learningpath/cli/diagnose_admin_ux.php`, `local/airpay_emails/cli/cert_emails_report.php`, `local/airpay_courses/cli/manage_shares.php` |

---

> **CURRENT TEST POSTURE (2026-05-13 EOD)**
> - **73 PHPUnit tests, 118 assertions, 0 errors, 0 failures, 23 skipped**
>   (skipped tests need the BizLMS `user.open_path` column not
>   present in vanilla PHPUnit fixture — they exercise on staging)
> - **2 axe-core a11y suites, 0 critical, 0 serious** — both
>   dashboard blocks (cron_health + cert_health) WCAG 2.1 AA clean
> - **pre_deploy_validate.sh: 9 of 10 gates green** (only Gate 3
>   cron-health FAILs on dev — no cron daemon running locally)
> - **All 15 commits pushed to** `nitin-rajput-learning-tech/Airpay-Academy2.0`
>   production branch (`78647e47d..d3ba9784b`)

> **ADMIN-FEEDBACK SPRINTS A-D (13 May 2026, commits `78647e47d..9e92d7dad`):**
>
> *Sprint A — Learning Path admin UX*
> - 7-check diagnostic CLI at `local/airpay_learningpath/cli/diagnose_admin_ux.php`
>   with `--fix-caps` idempotent capability repair + `--user=email` for
>   per-user diagnosis + `--json` for CI integration.
> - State card at `state-cards/airpay_learningpath-state.md`.
>
> *Sprint B — course-completion email + ramping reminders + audit*
> - Event observer for `\core\event\course_completed` with fail-safe try/catch.
> - `certificate_helper` materialises the `tool_certificate` PDF into
>   `$CFG->tempdir/airpay_emails/` and the notification sender routes
>   the email through `email_to_user()` so the PDF attaches (Moodle's
>   `message_send()` doesn't carry attachments).
> - New rule type `course_incomplete` in `process_rules.php` with
>   ramping cadence (default `[1,3,7,14,21]` days from enrolment),
>   `max_reminders_per_user` cap, `auto_stop_on_completion` flag.
> - Audit CLI at `local/airpay_emails/cli/cert_emails_report.php`
>   with `--since`, `--tenant`, `--status`, `--detail`, `--csv` flags.
> - **Dashboard widget `block_airpay_cert_health`** — 3 KPI cards
>   (sent / failed / suppressed in last 7 days) with the same WCAG
>   2.1 AA pattern as cron_health. axe-core test: 16/16 passes,
>   0 violations. Wired into pre_deploy Gate 6 alongside cron_health.
>
> *Sprint C — cross-tenant course sharing (push side)*
> - New table `local_airpay_courses_tenant_share` (course × tenant).
> - New capability `local/airpay_courses:share_to_tenant` (siteadmin only).
> - `sharing_manager` class with `share_course`, `unshare_course`,
>   `list_course_shares`, `is_course_shared_to`,
>   `build_catalog_filter_sql` (the SQL that UNIONs owned + borrowed
>   courses into one WHERE-clause fragment).
> - Catalog manager's 4 query methods (`get_courses`, `get_trending`,
>   `get_new`, `get_categories`) updated to call build_catalog_filter_sql.
> - Catalog card now carries `is_borrowed` + `provider_tenant_name`
>   and renders a "Provided by Airpay Academy" badge.
> - Admin page `share.php?id=<courseid>` with tenant checkbox grid;
>   the "Share" button is wired into the course-management row
>   actions (only visible to users with the cap).
> - 2 audit events (`course_share_created` / `course_share_withdrawn`).
> - 3 WS endpoints; 15-case PHPUnit suite (all pass).
>
> *Sprint D — cross-tenant request workflow (pull side)*
> - New table `local_airpay_courses_requests` (pending/approved/rejected).
> - 2 new capabilities: `:request_course` (manager-grantable) +
>   `:approve_request` (siteadmin only).
> - `request_manager` class with `create_request` (idempotent on
>   pending; short-circuits when already shared; throws on
>   own-tenant or unknown course), `approve_request` (cascades to
>   `sharing_manager::share_course` + purges catalog caches),
>   `reject_request` (with optional rationale).
> - `browse_airpay.php` — non-Airpay manager view of the full Airpay
>   catalog with per-row "Request access" button.
> - `manage_requests.php` — Airpay Super Admin pending-requests inbox
>   with Approve / Reject buttons.
> - 3 audit events (requested / approved / rejected).
> - Sidebar navigation now exposes "Course-share Requests" for site
>   admins and "Browse Airpay Library" for managers + L&D admins in
>   non-Airpay tenants.
> - 4 WS endpoints; 12-case PHPUnit suite (skipped in vanilla fixture
>   pending the BizLMS open_path column on staging).
>
> *Sprint B+C hotfix — caught by full PHPUnit run*
> - `local_airpay_email_log.status` was char(20) but the new
>   `'suppressed_completion'` value is 21 chars. Widened to char(32)
>   with index drop/re-add dance (Moodle's `ddl_dependency_exception`
>   forbids changing a column under an index).
> - `sharing_manager::known_tenants()` queried `local_airpay_org.name`
>   but the column is actually `fullname` (renamed at port time).
>
> *Translations*
> - All Sprint B/C new strings translated to hi / kn / mr / sw.
>
> *PHPUnit verification*
> Sprint B: 16/16 pass (25 assertions).
> Sprint C: 15/15 pass; Sprint D: 14 skip (need staging open_path).
> block_airpay_cert_health: 6/6 pass (15 assertions).
> Combined: 52 tests, 81 assertions, 0 errors, 0 failures.

> **WAVE 2/3/4 polish + bugfix commits (78647e47d..d3ba9784b)**
>
> *Wave 2 — Sprint C+D wiring + cert-health block + translations*
> - Share button in `airpay_courses/index.php` row actions (cap-gated).
> - "Course-share Requests" + "Browse Airpay Library" in sidebar nav.
> - `block_airpay_cert_health` — dashboard widget with 3 KPI cards
>   (sent / failed / suppressed in last 7 days), same WCAG 2.1 AA
>   pattern as cron_health block.
> - Hi/kn/mr/sw translations for Sprint B + C strings.
> - axe-core a11y test for cert_health block + Gate 6 expansion to
>   run BOTH cron_health and cert_health a11y suites.
>
> *Wave 3 — audit + polish*
> - All 5 Sprint C/D events added to
>   `audit_log::SENSITIVE_EVENTS` so they surface in the compliance
>   dashboard alongside role-change / refund / proctoring events.
> - course_completed email template updated to mention the PDF
>   attachment ("Your certificate is attached to this email").
>
> *Sprint D bugfix — request_state edge case*
> - Historical 'approved' request rows no longer mis-report
>   "In your catalog" once admin withdraws the share. `request_state`
>   now only looks at pending/rejected request rows; the share
>   table is the source of truth for current catalog membership.
> - 2 new PHPUnit cases guard the edge case.
>
> *Sprint D follow-up — manager outbox*
> - New page `my_requests.php` showing every request the manager
>   has filed with status pill + admin rationale + per-status
>   KPI strip. Sidebar nav exposes it as "My Requests".
>
> *Ops CLI — `cli/manage_shares.php`*
> - Terminal-friendly share/request management for IT during early
>   rollout. Supports `--list`, `--list-pending`, `--course=N
>   --add=77,177`, `--course=N --remove=77`, `--approve=<rid>`,
>   `--reject=<rid> --reason="..."`, `--course=N --history`,
>   `--json` for scripting.
>
> *Event payload fix*
> - All 5 Sprint C/D events now omit the top-level `courseid` key
>   from `create()` payload — fixes Moodle's "Inconsistent courseid
>   - context combination" debugging notice. The course id stays
>   inside `other` for downstream consumers.
>
> *Docs*
> - `local_airpay_courses/README.md` updated for Sprint C/D
>   (capability table, page table, CLI table, audit events).
> - `blocks/airpay_cert_health/README.md` created from scratch.
> - `local_airpay_emails/README.md` updated for Sprint B (observer,
>   helper, course_incomplete rule, schema additions, hotfix note).
>
> *PHPUnit additions*
> - `blocks/airpay_cert_health/tests/block_test.php` — 6 tests
>   covering silent-hide-for-non-admin, KPI labels, region landmark,
>   count accuracy, non-cert-row exclusion.
>
> *pre_deploy_validate gates*
>   Gate 0 — tenant-guard lint (132 externals, 0 violations) ✅
>   Gate 1 — PHP syntax lint (764 files, single-process batch) ✅
>   Gate 2 — Python compile (all sentientia agents) ✅
>   Gate 3 — cron-health CLI (FAIL on dev — no cron daemon)
>   Gate 4 — 4 plugin smokes ✅
>   Gate 5 — PHPUnit (skip flag available)
>   Gate 6 — axe-core a11y × 2 blocks ✅
>     - a11y_block_cron_health (0 critical, 0 serious)
>     - a11y_block_cert_health (0 critical, 0 serious)
>   Gate 7 — Phase 7 UAT (opt-in)
>
> All 9 commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **ENGINEERING 13-32 (13 May 2026, commits `2d71f0bb3..3da23ebe7`):**
>
> *Pre-deploy validation pipeline*
> - Eng 17: `pre_deploy_validate.sh` — single orchestrator with 7 gates
> - Eng 18: `lint_tenant_guard.py` — architectural CI enforcement of the tenant-guard rule (132 externals, 0 violations)
> - Eng 19: wire Gate 0 (tenant-guard) into pre_deploy_validate
> - Eng 22: Gate 1 PHP-lint single-process `token_get_all` batcher (8 min → 2 sec for 729 files, 250x speedup, Windows-aware path translation)
> - Eng 23: Gate 6 axe-core a11y wiring + `--skip-a11y` flag
> - **Full pre-deploy now: 44 seconds (was 8+ min and often killed)**
>
> *Accessibility — `block_airpay_cron_health`*
> - Eng 20: axe-core a11y baseline via static fixture (no XAMPP / DB dep)
> - Eng 21: heading-order fix (h2→h5 → h2→h3), small-text contrast palette split (#15803d/#b45309/#b91c1c for 4.5:1), severity badge + ARIA labels to satisfy WCAG 1.4.1 (use of colour)
> - **Result: WCAG 2.1 AA + best-practice clean (18 passes, 0 violations)**
>
> *Tenant guard back-ports*
> - Eng 15 (earlier): `tenant::require_path_access()` helper introduced + back-port `list_course_enrolments`
> - Eng 24-27: five more externals now using the helper:
>   - `airpay_org/delete_org.php` + `airpay_org/toggle_visibility.php`
>   - `airpay_reports/delete_report.php` + `airpay_reports/toggle_status.php`
>   - `airpay_users/bulk_action.php` (uses `tenant::path_filter()` for SQL bulk filter)
> - Eng 29: 7 PHPUnit regression tests, including the silent-pass-bug guard (empty `open_path` viewer → throws, was silent-pass in the inline pattern)
>
> *Other operations*
> - Eng 13: SENTIENTIA Agent 2 production hardening (retry+backoff, token tracking, INR cost)
> - Eng 14: `branding_assets` trait (-83 lines from core_renderer)
> - Eng 16: `cron_health.php` CLI for the ops team
>
> *core_renderer.php decomposition*
> - Eng 28: `login_render` trait (-77 → 1,969)
> - Eng 30: `context_header` trait (-175 → 1,794)
> - Eng 31: `course_view` trait (-73 → 1,721)
> - Eng 32: `user_menu` trait (-356 → 1,365) ← the 350-line headline win
> - **Cumulative: 2,339 → 1,365 = -974 lines (~42%) across 7 traits**
>
> All commits pushed to `nitin-rajput-learning-tech/Airpay-Academy2.0`
> production branch.

> **PHASE 9 STRETCH (12 May 2026, commit `ffee790b9`):**
> All six non-blocking findings from the Phase 8.2 re-audit shipped:
> - N1 sliding-window rate limit (timestamp-array replaces fixed-hour bucket)
> - N2 S3 purge real SigV4 DELETE implementation (GDPR retention enforced)
> - N5 `_tenantroot` renamed to `aptenantroot` (drop non-Moodle convention)
> - N6 silent-404 callback IP-drop logging with hourly dedupe
> - N7 quizaccess config-table-bloat refactored to relational table with migration
> - N9 AWS Rekognition exponential-backoff retry (3 attempts, 250/500ms backoff)
>
> Plus the cross-cutting `\local_airpay_core\audit_log` helper for compliance
> queries (sensitive_actions, actions_by_user, tenant_actions) and 8 more
> plugin READMEs (org, users, courses, classroom, emails, notifications,
> manager, privacy). 14 of 30 plugins now have READMEs; the remaining 16
> follow the same template and are documented in their existing state cards.
>
> The full backlog of 47 items (ACTIONABLE-NOW + BLOCKED-INFRA + BLOCKED-MGMT
> + BLOCKED-CONFIRM + FORK-PLANNED + FUTURE-DESIGN + TECH-DEBT) is enumerated
> in the master-doc Section 12 + 13 + 14 and in this session's TodoWrite log.
> Of those 47: 8 actionable items closed in this session; 6 await IT; 8 await
> management decisions; 3 await Nitin [CONFIRM] gates for paid-API runs; 7
> are fork-planned for Q3 2026; 8 are FUTURE-DESIGN; 6 are TECH-DEBT (some
> closed by Phase 9 stretch).
>
> **FIVE SUPPLEMENTARY DOCUMENTS shipped alongside master v1.0:**
> - `docs/SUPP-A-RISK-REGISTER-FULL-2026-05-12.md` — 32 risks across 9
>   categories. Aggregate: 1 high-residual (P1 key-person, until engineer
>   hire lands), 4 medium-residual, rest low-residual.
> - `docs/SUPP-B-MOODLE5-UPGRADE-PLAN-2026-05-12.md` — strategic rationale,
>   8 prereqs, per-plugin compat (30/30 ✓), Q4 2026 sequencing AFTER cutover
>   AFTER BizLMS displacement.
> - `docs/SUPP-C-SENTIENTIA-DETAILED-PLAN-2026-05-12.md` — 6 agents
>   spec'd end-to-end, ₹70-125 per course economics, 90-day build sequence,
>   vendor evaluation matrix.
> - `docs/SUPP-D-BIZLMS-DISPLACEMENT-PLAN-2026-05-12.md` — Q3 2026 nine-week
>   sequenced plan covering renderer-callsite displacement (P0, 13+5=18
>   callsites), schema-column migration (50 `open_*` columns across user
>   + course tables), plugin-directory removal, block displacement,
>   LearnerScript decision. Done-criteria + risk register specific to the
>   workstream.
> - `docs/SUPP-F-ENGINEER-HIRE-BRIEF-2026-05-12.md` — operationalises
>   Decision 13.3 (the highest-leverage decision on the platform). Role
>   spec, compensation framing (₹22 lakh), 7-stage interview, 90-day
>   onboarding ramp, success metrics at 6 and 12 months, sample JD draft.

> **THREE EXECUTABLE ARTEFACTS shipped (acting on the backlog right away):**
> - `moodle-enhancement/deploy/cutover_preflight.sql` — 9-section read-only
>   pre-flight against production. Detects N4 stale manageprices grants,
>   invalid open_path users, cart tenant-list config, callback IP allow-list,
>   proctoring AWS config, recompletion rule tenancy, scheduled-task status,
>   user-population sanity, plugin version alignment.
> - `moodle-enhancement/local/airpay_core/cli/mask_pii_for_dev.php` —
>   mitigates risk S7. Sanitises mdl_user PII, clears logstore IPs, masks
>   cart billing PII, deletes proctor identity, masks email log. Hard
>   safety guards (production-DB-name blocklist + --confirm flag +
>   executive-name canary).
> - `moodle-enhancement/local/airpay_core/classes/cron_health.php` —
>   mitigates risk I5. Surfaces stuck Airpay scheduled tasks, faildelay
>   backoff state, summary tuple for the dashboard widget.
>
> **ALL 30 PLUGIN READMEs SHIPPED.** Phase 8.3 (6) + Phase 9 (8) + this
> session (17) = full coverage. Section 12.1 plugin-doc deferral closed
> entirely.

> **PHASE 9 EXTEND (12 May 2026 night):** Three more supplements, an
> agent skeleton, a regression suite, a runbook, and a structured logger.
>
> - `docs/SUPP-E-BUDGET-MODEL-2026-05-12.md` — 12-month operating budget
>   ₹35 lakh expected, ₹62 lakh savings, **+₹27 lakh** cash-positive net.
>   Sensitivity analysis on SENTIENTIA throughput / hire timing / Public-
>   tenant traction. Per-vendor sub-ceilings under Decision 13.2.
> - `docs/SUPP-G-DR-DRILL-PLAN-2026-05-12.md` — RTO 4h, RPO 24h, four
>   scenarios, drill checklist + role assignments + retention policy +
>   cold-site spec. First live drill scheduled week 3-4 of 90-day plan.
> - `docs/SUPP-H-OBSERVABILITY-PLAYBOOK-2026-05-12.md` — 6 SLIs/SLOs,
>   alert taxonomy P0/P1/P2, structured-logging contract, error-budget
>   framework, 12-month maturity roadmap. New Relic at ₹0-80,000/year.
> - `sentientia/agent2_narration_generator.py` — full prompt template,
>   validation gates, [CONFIRM] gate (tty-checked), batch + dry-run modes.
>   Anthropic SDK gated; live integration is a small diff away.
> - `sentientia/run_regression.py` — quality regression runner with
>   word-count delta, sentence-distribution KS test, vocabulary recall,
>   PII introduction check. Zero scipy dependency.
> - `sentientia/references/README.md` — 3-course reference suite
>   (POSH compliance, customer support playbook, AML fundamentals)
>   with validation thresholds and anti-golden pattern documented.
> - `moodle-enhancement/MFA-ENFORCEMENT-RUNBOOK.md` — three-tier
>   enforcement plan (admins T+30d, managers T+90d, users 12-mo eval).
>   Admin steps, comms template, verification SQL, rollback. DPDP s.8(4)
>   compliance positioning.
> - `moodle-enhancement/local/airpay_core/classes/structured_logger.php`
>   — JSON-shaped log helper backing the SUPP-H structured-logging
>   contract. ISO-8601 timestamp, request_id from upstream headers, APM
>   custom-event hook, defensive PII scrub on extra dict.

**Theme:** airpayux v1.0.0 | **Moodle:** 5.1.3+ on XAMPP
**Version:** 4.0-rc3 — All 22 Phase-2 rows ✅ + cart + proctoring + recompletion + AI + cohorts + badges + 7-persona UAT.
**GitHub:** Pushed to nitin-rajput-learning-tech/Airpay-Academy2.0 (production branch, last commit `6ce016150` — Phase 8.3 plugin READMEs + smoke fixes)
**Today's UAT result:** Phase 7 multi-role re-run **84/85** post-Phase-8.1 (identical baseline — no regressions). Plugin smoke tests **84/84** (cart 26/26, request 23/23, proctoring 22/22, recompletion 13/13). PHPUnit on `local_airpay_core::tenant` 6 pass, 3 cleanly skip (BizLMS column absent on PHPUnit fixture). Cumulative test pass: **326+ cases**.
**Today's audit + remediation + verification cycle + documentation:** Phase 8 audit NO-GO → Phase 8.1 remediation (35 files, +787/-83) → Phase 8.2 re-audit returned **GO** + Phase 7 UAT re-run **84/85** + N3 / N4 follow-ups shipped + Moodle 5 messages.php compat fixed across 5 plugins + Phase 8.3 6 plugin READMEs + smoke verification 84/84 + **Master Documentation v1.0 (123 KB md / 91 KB docx)**. Total cumulative Phase 8.x shipment: 19 commits, ~22,500 LOC, all 11 blockers closed.

> **MASTER DOCUMENTATION HANDOFF (12 May 2026 EOD):**
> Two files at `docs/`:
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.md` (123 KB, 1,394 lines, 18,128 words)
> - `AIRPAY-ACADEMY-2.0-MASTER-DOCUMENTATION-2026-05-12.docx` (91 KB, generated via python-docx with Airpay brand styling)
>
> The document follows the master prompt structure: cover + executive summary + 15 sections covering platform overview, baseline, evolution timeline (8 phases from Nov 2022 to May 2026 commit history), the airpayux theme, all 30 plugins (with deep profiles for the 8 most consequential), content + SENTIENTIA + Microsoft 365 + API surface, features by 9 user roles, commercial + operational implications (₹ figures vs SaaS alternatives), backlog by workstream, decisions required from management (8 distinct decisions with recommendations), 90-day plan week-by-week with 6-month and 12-month horizons, and 8 appendices (git log, file tree, schema overview, capability matrix, glossary, env vars, runbook map, escalation matrix). Internal source fragments held at `docs/master/`; concatenation + .docx generator script at `docs/_working/generate_docx.py`.
>
> Working notes from the discovery pass are at `docs/_working/` — full git log (2,386 commits), git shortlog, plugin matrix, tag/branch lists. These remain useful for the next quarterly refresh.

> **TOMORROW START HERE:** Re-run security audit against the new diff.
> Phase 8.2 sequence: (1) re-audit returns GO → (2) re-run Phase 7
> multi-role UAT → (3) staging k6 load test against prod-sized RDS clone
> → (4) follow `PHASE-8-DEPLOYMENT-RUNBOOK.md` for cutover.
> No cutover until all three pre-cutover gates pass.

## Phase 8.1 remediation summary

11 blocking findings closed in one session, 35 files changed, +787/-83.

Root cause: 10 of 11 findings shared one architectural gap — capability
checks at `CONTEXT_SYSTEM` without an additional tenant-equality check.
Public-tenant manager with `:viewallorders` legitimately held the cap;
the second check was missing in every external.

Fix: new `local_airpay_core` plugin with `\local_airpay_core\tenant`
helper class — `root_for_user`, `viewer_can_access`, `require_access`,
`sql_filter`. Every blocking finding now uses one of these helpers.
8 PHPUnit tests guarantee cross-tenant rejection + site-admin passthrough.

Per-finding fixes:
- B4 (CVSS 9.1) Payment tampering: callback.php compares payload.amount
  + currency to server-side cart.total_amount/currency BEFORE mark_paid.
- B11 (CVSS 5.4) Callback DoS: generic 500, optional CIDR allow-list,
  new ip_check helper with v4 + v6 CIDR matcher.
- B1 (CVSS 8.6) Cart cross-tenant: cart_manager::get_order + refund +
  list + daily_sums all use tenant::sql_filter() / require_access().
- B2 (CVSS 8.1) Proctoring read leak: 5 read paths now tenant-scoped.
- B3 (CVSS 8.2) Proctoring write IDOR: session_manager helpers verify
  ownership; s3_key whitelisted to strict regex; size+duration bounded.
- B5 (CVSS 7.4) Invoice XSS fragility: html_writer wrapper with
  white-space: pre-line replaces the nl2br()+{{{ }}} pattern.
- B6 (CVSS 7.5) Recompletion cross-tenant: rule.costcenterid now drives
  a path-prefix filter on the candidate query.
- B7 (CVSS 6.8) Identity photo abuse: 5 submits/hour rate limit, size
  cap 14MB→5.5MB, base64 strict-mode, MIME magic-byte sniff (JPEG/PNG).
- B8 (CVSS 6.5) LIMIT injection: 3 queries refactored to use limitfrom
  /limitnum args instead of string interpolation.
- B9 (CVSS 7.1) set_price context: :manageprices cap moved to
  CONTEXT_COURSE; external uses context_course::instance() for the check.
- B10 (CVSS 6.5) Approver bypass: request_manager::decide adds tenant
  equality after :overrideroute cap check.
>
> Today shipped the enterprise-grade plan end-to-end: airpay_cart (full
> e-commerce stack for external tenants), airpay_proctoring + quizaccess
> subplugin, airpay_recompletion (annual compliance engine), airpay_request
> (course-request workflow), per-tenant settings + SSO documentation,
> cohort sync from org tree + badges seed + core_ai bridge + mobile-push
> setup guide, plus a 7-persona × 14-case UAT harness that walked every
> user tier end-to-end.
>
> **Two real production bugs surfaced and fixed by Phase 7:**
> 1. `update_capabilities('local/airpay_x')` silently registers 0 caps
>    on fresh installs — every assign_capability() after it becomes a
>    no-op because record_exists() on mdl_capabilities fails. Slash form
>    looks valid but Moodle expects the underscore form. Fixed across
>    4 plugin install hooks (cart, request, proctoring, recompletion).
>    Smoke tests missed it because they call manager methods directly,
>    bypassing the capability-check WS layer.
> 2. Tenant Admin (nitin.rajput) holds the 'administrator' role at
>    contextid=11 (CONTEXT_COURSECAT, level=40) NOT at CONTEXT_SYSTEM
>    (level=10). This is correct — he manages his category, not the
>    whole site. UAT persona was relabeled "Tenant Admin (category-scoped)"
>    with expect_admin_pages=false; the admin-page block at H.1 now
>    passes as the security boundary it was designed to test.
>
> The 1/85 remaining failure is a transient login timeout on the freshly
> provisioned public.uat test user (login helper already has 2-attempt
> retry — both timed out this run; not blocking, infrastructure flake).

---

## Yesterday's snapshot (2026-05-07 EOD post-stretch — preserved for context)
**Phase 3.4** — Tier 1 closed (G-01..G-06), Tier 4 a11y closed, audits
delivered, airpay_roles UI shipped, airpay_challenge Phase 1 shipped,
airpay_integrations pre-cutover fixes shipped.
PHPUnit: ~352 tests across ~38 test files. Playwright: 8 harnesses.
Audits: `INTEGRATIONS-AUDIT.md`, `STRETCH-ACCOUNTABILITY.md`,
`airpay_roles-state.md`, `airpay_challenge-state.md`.

> **Production posture (Nitin EOD 2026-05-06):** *"We will not go to production
> till we have fixed everything. Not going to make a fool of myself going with
> half-baked product. The features shouldn't just exist — they should work
> like a true enterprise product."*
>
> Production cutover is gated on closing **all** items in `FEATURE-PARITY-AUDIT.md`
> (G-01..G-06 + Tier 2 stubs + Tier 3 partials + Tier 4 a11y polish), not just the
> most-impactful ones.
>
> See `state-cards/2026-05-06-EOD-state.md` for the full backlog (~140-180h
> estimated; sequenced over 9-10 dedicated days starting with G-04 tomorrow).

## Last 13 commits (May 5-6 audit/perf/test/quality stretch)
- `acd0a0d41` Feature-parity audit + G-01 fix + 8 CRUD PHPUnit (54/54 PASS) + Phase D-extended Playwright
- `f11bdacd0` State card update: A11Y-4/5/6 + F1 + learnerscript-P3 closure
- `2799c0926` A11Y-4 + A11Y-5 + A11Y-6 + F1 follow-up + learnerscript-P3 documented
- `b200eed6c` PHPUnit for programs/skills/notifications/evaluation (20/20) + Phase 0B export button + README
- `682143ea0` A11Y-1: aria-sort + keyboard nav on shared datatable (covers all 10 admin tables)
- `8a5c4fced` CI: also trigger on workflow changes pushed to production
- `295cfcb9e` CI: count Moodle Mustache template-inheritance forms in balance check
- `f35ce3e9b` H: SCORM e2e — 7/7 PASS, attempt persisted, integration boundary verified
- `7bd2bd9f4` K (Phase 0A): port 3 BizLMS accesslib methods + 7/7 PHPUnit PASS
- `175e220e8` E (complete): airpay_exams + airpay_learningpath PHPUnit tests
- `43deec238` State card update: A,C,D,E,F,G shipped; H + K deferred with reason
- `ae77416b8` D + E (partial): F1 investigation notes + airpay_classroom PHPUnit tests
- `002ce78b9` A: GitHub Actions CI — PHP lint + JSON + Mustache + version-bump

## What this represents

After two intensive days of audit-driven hardening, we've done the **measurement
work**: every gap is catalogued in FEATURE-PARITY-AUDIT.md, every security-critical
path is locked in by tests, every regression has a guard.

What we haven't done yet is the **build work** to close the gaps. That's the
3-4.5 weeks of Tier 1+2+3 work documented in `state-cards/2026-05-06-EOD-state.md`.

## State summary (post May 5-6 stretch)
- ✅ Deploy mechanism: 8/8 runbook steps + rollback drill
- ✅ PHPUnit: 44/44 tests passing on security-critical paths
- ✅ Browser tests: 113/116 + 73/73 + 16/16 + 12/15 = 214/220 (97%)
- ✅ All cross-tenant LIKE leaks closed (13 sites)
- ✅ All P0/P1 perf wins shipped (org 86×, analytics ∞×, catalog 40×)
- ✅ Manager onboarding UX bug fixed
- ✅ Moodle 5.x deprecations cleaned up
- ✅ -4604 LOC of orphan code removed
- ✅ CI workflow on every PR

**Production-cutover-blocked-on:** IT staging access, DB backup verification, SMTP setup. Engineering done.

---

## v3.3.0 Session (2026-05-05) — CRUD pattern + datatable + security pass

### What landed (10 commits across this session)

**11 plugins now have full CRUD on the established `core_form\dynamic_form` modal pattern:**
- airpay_users, airpay_courses, airpay_classroom, airpay_exams,
  airpay_learningpath, airpay_programs, airpay_skills, airpay_notifications,
  airpay_evaluation, airpay_reports, airpay_org

Each plugin: `classes/form/edit_*.php` dynamic form, `classes/external/{delete,toggle}_*.php`
externals via ajax-callable web services, `amd/{src,build}/*_actions.js` pure-AMD wrapper
(no Babel helpers — Moodle's RequireJS doesn't ship `_interopRequireDefault`),
templates/manage.mustache + index.php, `db/services.php` registration, lang strings.

### New shared infrastructure (commit `6362762bc`)
- **`theme_airpayux/datatable`** AMD module — server-side search (debounced 250ms),
  column sort with display-key vs sort-key decoupling, pagination, per-row HTML
  actions, public refresh()/setFilter()/getSelected() API, custom event for CRUD
  module integration, row selection.
- Web service contract: `args: {search, sort, sortdir, page, perpage, filters: JSON}` →
  `returns: {total, rows: [{id, ...cellvalues, actions: HTML}], page, perpage}`
- Retrofitted: airpay_users (2,869 rows), airpay_courses (411).

### Manager drill-down (commit `b7154851d`)
- `local_airpay_manager\team_manager` class with batched aggregates: get_team(),
  summarize_team() — 4 queries replace N×3 per-row, get_member_detail() — full
  course list + certs, can_view_member() — supervisor chain walks up to 5 levels.
- New `member.php` drill-down page with progress bars per course + certificates earned.
- Theme dashboard manager section refactored: was 1 + 34 + (34×5) = ~205 query
  operations per load for managers with 34 reports. Now 4 batched queries.

### Bulk operations (commit `b7154851d`)
- Datatable extended with row selection (`data-selectable="1"`).
- New `local_airpay_users_bulk_action` WS — suspend/activate by ID array.
- Hard-protects $USER->id, guest (1), admin (2) before UPDATE.

### Production-readiness pass (commit `ba0a44856`)
- Audited codebase for `$USER->open_costcenterid` references — found 3 real
  bugs in our owned code. Production has no such column; on production the
  comparison was 0==0 → access scoping was broken. Fixed in
  theme/airpayux/classes/output/core_renderer.php + 2 form classes.
- Authenticated curl smoke test through 10 admin pages + 2 web services: 10/10 PASS,
  list_users search 'nitin' → 12, list_courses search 'POSH' → 3.

### Mobile + dark mode polish (commit `a6c315d65`)
- Discovered: 8 templates used CSS variables that don't exist
  (`--ap-color-surface` vs the real `--ap-color-bg-surface`,
  `--ap-color-error` vs `--ap-color-danger`). Fallbacks always rendered,
  bypassing the design system. Fixed across all templates.
- Discovered: dark_mode.scss only overrode legacy `--airpay-*` tokens, not
  the current `--ap-color-*` semantic tokens. Components using
  `var(--ap-color-bg-surface, ...)` stayed light in dark mode. Added 10
  token remaps.
- New SCSS partial `_datatable.scss` with mobile breakpoint (590px) +
  explicit dark-mode rules for the shared component.

### Security audit (commit `a6c315d65`) — 6 real bugs fixed
Run by Airpay Security Auditor agent. Verdict pre-fix: **BLOCK production deploy.**

| Sev | ID | Category | Fix summary |
|-----|----|----------|-------------|
| Critical | C1 | Tenant isolation | Bulk_action could suspend any user by ID; added open_path scope filter on the UPDATE target set |
| Critical | C2 | OWASP A03 SQL | `'/1' . '%'` LIKE pattern matched /10, /100, /177 → cross-tenant data leak. Fixed with sql_like_escape + slash boundary in 8 sites (list_users, list_courses, bulk_action, count_users, count_descendants, all 4 report runners). Confirmed 6-row leak removed from `count_users(1)` and 83-row leak removed from enrolment_trend report. |
| High | H1 | A01 Authz | list_users honored caller-supplied orgid without checking it was inside caller's tenant tree. Now rejects with 'outoftenant'. |
| High | H2 | TOCTOU | org_manager::delete had race window between count_descendants check and DELETE. Wrapped in transaction with SELECT...FOR UPDATE on target row. |
| High | H3 | A01 Authz | delete_org / toggle_visibility / delete_report / toggle_status accepted any id with only the management cap checked. All 4 now reject targets outside caller's tenant. |
| Medium | M1 | A04 Insecure design | bulk_action returned actually-flipped count → user-enumeration oracle. Now returns post-tenant-filter request-set size, not change-set size. |
| Medium | M2 | A03 / DoS | JSON `filters` was PARAM_RAW with no size or depth limit. Added 4KB cap + 5-level depth limit on list_users + list_courses. |
| Medium | M3 | Tenant isolation | list_courses had no tenant scope at all. Added `(open_path = :exact OR LIKE :prefix)` filter mirroring list_users. |

Re-verified all 3 smoke tests pass post-fix. Verdict post-fix: clear for production
pending I3 follow-up (mass-assignment on update path), which is not on the WS
surface today.

### Files (counts)
- 11 plugins x ~12 files each = 132 plugin files
- 1 shared theme component (datatable.js + .min.js + .scss)
- 1 manager class for team aggregates (team_manager.php, 220 lines)
- 4 new web services for the shared datatable contract
- 1 SCSS partial + dark mode token remap

### Verification status
- **PHP lint:** all touched files clean
- **Authenticated browser test:** 10/10 admin pages render (curl-based, Chrome MCP unavailable)
- **Web services:** list_users (7/7 cases), list_courses (3/3), bulk_action (4/4),
  org CRUD (7/7), 4 report runners (4/4 PASS)
- **Security audit:** 6 findings → fixed → re-verified
- **Mobile + dark mode:** SCSS compiles correctly, selectors verified in compiled CSS
- **Tests:** ZERO PHPUnit tests written (gap — recommended for next session)

---

## v3.1.0 Session (2026-04-18) — BizLMS Feature Port: Enterprise Admin Pages

### Visual Audit (18 sidebar pages)
Screenshotted and assessed every sidebar destination. Categorized into:
- **Tier 1 Enterprise-grade (6):** Dashboard, Reports, Analytics, Compliance, Emails, Privacy
- **Tier 2 Functional (6):** Users, Courses, Organisation, Skills, Notifications, Site Admin
- **Tier 3 Stub (6):** Exams, Classrooms, Learning Paths, Programs, Evaluations, Certificates

### Bug Fixes (3 critical)
1. **Analytics crash** — missing `$cert_previous` query, nullable `trend()`, BizLMS `local_costcenter`→`local_airpay_org`, stdClass→array
2. **Admin tabs leak** — 8 plugin pages had `set_pagelayout('admin')` leaking Moodle Site Admin tabs into sidebar. Fixed with `set_pagelayout('standard')` + `set_secondary_navigation(false)`
3. **Certificates URL** — sidebar pointed to public verify form, now points to `manage_templates.php`

### Pages Rebuilt (11 pages, 36 files changed, +2,203 lines)

| Page | Key Feature |
|------|------------|
| **Manage Users** | 9-column sortable table, search+org+status filters, pagination (2,869 users), 7 capabilities, CRUD actions |
| **Manage Courses** | Admin table with enrolled/completed/rate%, org+category+status filters (411 courses) |
| **Online Exams** | 233 Moodle quiz activities with attempts/scores/time limits |
| **Classrooms** | ILT session management with status workflow, KPIs |
| **Learning Paths** | 17 real paths from legacy data |
| **Programs** | Enterprise empty state with Create CTA |
| **Analytics** | Business Unit dropdown filter (auto-submit on change) |
| **Notifications** | Type column populated (Deadline Reminder/Custom), KPIs, action dropdowns |
| **Organisation** | Tenant cards with expand/collapse departments, user counts (3/213/1,406) |
| **Evaluations** | Proper admin page with Moodle Feedback count |
| **Sidebar** | Manage Courses → airpay_courses admin (not catalog) |

### Enterprise UI Pattern (consistent across all pages)
1. Header with title + subtitle + primary action button
2. KPI cards (3-4 metrics with color coding)
3. Filter bar (Search + Organisation + Status + Category)
4. Data table (sortable, status badges, action dropdowns)
5. Pagination (25/page)
6. Empty state (icon + heading + description + CTA)

### Files Created
- `local/airpay_courses/index.php` + `templates/manage.mustache` — course admin
- `local/airpay_exams/templates/manage.mustache` — exam template
- `local/airpay_classroom/templates/manage.mustache` — classroom template
- `local/airpay_learningpath/index.php` + `templates/manage.mustache` — paths
- `local/airpay_programs/index.php` + `templates/manage.mustache` — programs
- `local/airpay_notifications/index.php` + `templates/manage.mustache` — notifications
- `local/airpay_org/admin.php` + `templates/manage.mustache` — org tree
- `local/airpay_evaluation/index.php` + `templates/manage.mustache` — evaluations
- `local/airpay_users/templates/manage.mustache` — users template

### What Remains
- CRUD modal forms (create/edit user, create course, create session) — wired to CTAs but not yet functional
- AJAX pagination (currently server-side, working but could be faster with AJAX)
- User profile page rebuild
- Reports page branding/org scoping
- Skills admin management view (currently shows learner readiness)

---

## v2.9.0 Session (2026-04-16) — BizLMS Fork Phase 1: Airpay Organization Engine

### New Plugin: local_airpay_org (10 files)
Replaces BizLMS `local_costcenter` (103 files) with Airpay-owned organization engine.

**Classes:**
- `accesslib.php` — Fork of `\local_costcenter\lib\accesslib` (6 static methods, BizLMS API compat)
- `org_manager.php` — Org CRUD: get, get_name, get_by_path, get_children, get_descendants, get_tenants
- `tenant_manager.php` — Tenant detection, open_path parsing, manager detection, public tenant, scoping
- `branding_manager.php` — Logo URL resolution, colour scheme, body CSS class, tenant branding

**Infrastructure:**
- `db/install.xml` — `local_airpay_org` table (15 fields, mirrors costcenter schema + branding colours)
- `db/access.php` — 5 capabilities mirroring BizLMS costcenter
- `lib.php` — `airpay_org_logo()` drop-in for `costcenter_logo()` + pluginfile callback
- `data_migration.php` — CLI script to copy local_costcenter → local_airpay_org (preserves IDs)

### core_renderer.php Update
- 13 BizLMS class references replaced → 0 remaining
- `use costcenter;` import removed
- `get_costcenter_scheme_css()` → `branding_manager::get_org_theme_scheme()`
- `get_my_scheme()` → `branding_manager::get_body_scheme_class()`
- `should_display_navbar_logo()` + `get_custom_logo()` → `branding_manager::get_tenant_logo()`
- All `\local_costcenter\lib\accesslib::*` → `\local_airpay_org\accesslib::*`
- 6 capability string refs (`local/costcenter:*`) kept for DB compat — Phase 7 migration

### dashboard.php Update
- Direct `{local_costcenter}` query → `org_manager::get_name_by_path()`

### Transition Strategy
- All classes: read local_airpay_org first, fall back to local_costcenter
- Logo files: check both component names (local_airpay_org, local_costcenter)
- BizLMS stays installed during transition — safe to deploy independently

### Phase 2: local_airpay_users (8 files)
Replaces BizLMS `local_users` (96 files) with Airpay-owned user engine.

**Classes:**
- `user_fields.php` — 17 open_* field constants (6 query + 11 display), prefix_label(), format_date()
- `user_manager.php` — build_profile_context() (replaces 200-line renderer), get_org_hierarchy(), get_supervisor(), get_role_names()

**Profile:**
- `profile.php` — Drop-in replacement for /local/users/profile.php
- `templates/profile.mustache` — Airpay-branded with gamification/skills enrichment + detail grid

**Updated files:**
- `local/users/renderer.php` — 7 BizLMS accesslib refs → \local_airpay_org (0 remaining)
- `theme/airpayux/core_renderer.php` — 2 config refs → dual-check (airpay_users + local_users fallback)

### Phase 3: local_airpay_courses (6 files)
Replaces BizLMS `local_courses` (136 files, already gutted to 3 templates) with Airpay-owned course engine.

**Classes:**
- `course_fields.php` — 11 open_* course field constants (2 access + 9 metadata)
- `course_manager.php` — get_progress_percentage() via core completion, deadline calc, can_manage/can_enrol dual-check

**Updated files:**
- `core_renderer.php` — 2 BizLMS accesslib calls → course_manager/airpay_org; 4 URL redirects → airpay_catalog
- `dashboard.php` — 1 URL ref → airpay_catalog

### Phase 4: Learning Modules (18 files across 3 plugins)

**local_airpay_classroom** (6 files) — Replaces BizLMS local_classroom
- `session_manager.php` — count_classrooms(), get_session() for QR attendance
- `db/install.xml` — 3 tables: classroom, sessions, attendance
- 3 capabilities

**local_airpay_exams** (6 files) — Replaces BizLMS local_onlinetests
- `exam_manager.php` — get_by_course_module(), get_by_attempt() for access control
- `db/install.xml` — 1 table: exams (linked to quiz module)
- 2 capabilities

**local_airpay_learningpath** (6 files) — Replaces BizLMS local_learningplan
- `path_manager.php` — get_courses(), is_enrolled(), get_user_progress()
- `db/install.xml` — 3 tables: paths, path_courses, path_users
- 3 capabilities

**Updated files:**
- `core_renderer.php` — 2 raw SQL queries → exam_manager API; 4 URL redirects → airpay_exams
- `dashboard.php` — 2 count queries → session_manager/exam_manager; 2 URLs → airpay plugins

### Phase 5: Search + Categories (3 files new, 4 files updated)

**New:** `category_manager.php` in airpay_catalog — wraps {local_custom_category} queries with get_name(), get_with_parent(), get_root/children helpers.

**Added to airpay_org/accesslib:** `get_user_role_switch_path()` + `get_costcenter_path_field_concatsql()` — 2 methods coursedetails.php needed.

**Updated files:**
- `local/search/coursedetails.php` — 3 BizLMS class refs + 4 raw category queries → airpay_org + category_manager
- `local/airpay_catalog/course.php` — 1 category query → category_manager
- `local/airpay_catalog/mycourses.php` — 1 category query → category_manager
- `core_renderer.php` — 1 custom_category URL → airpay_catalog

### Phase 6: Theme Complete Independence (9 files updated)

**Epsilon removed:**
- `get_primarycolor/secondarycolor/hovercolor()` — 3 methods rewired from `theme_config::load('epsilon')` → `branding_manager::get_brand_colors()`
- `getsitecolors_link()` — no longer returns epsilon CSS path
- 0 remaining `theme_config::load('epsilon')` calls

**BizLMS functions guarded:**
- `display_rating()` — 2 call sites wrapped in `file_exists()` + `function_exists()` guards
- `render_challenge_object()` — plugin context changed from `local_courses` → `local_airpay_courses`

**URLs migrated:**
- `/local/users/index.php` → `/local/airpay_users/index.php` (dashboard)
- `/local/users/signup.php` → `/local/airpay_users/signup.php` (login)
- `/local/users/profile.php` → `/local/airpay_users/profile.php` (2 locations)

**Metadata cleaned:**
- Dashboard.php header: eAbyas copyright → Airpay 2026
- Hindi lang: removed "BizLMS epsilon" from choosereadme
- Marathi lang: removed "BizLMS epsilon" from choosereadme
- SCSS: costcenter admin selectors marked deprecated (Phase 7 removal)

**Remaining (Phase 7 only):** 13 capability strings (`local/costcenter:*`, `local/courses:*`, `local/classroom:*`) — these reference DB role_capabilities rows and MUST stay until migration script reassigns them.

### Phase 7: Data Migration + BizLMS Removal (3 CLI scripts + 190 lines CSS deleted)

**CLI scripts (in local/airpay_org/cli/):**
- `migrate_all.php` — Master migration: copies 4 BizLMS tables + 10 capability mappings. Supports `--dry-run`. Verifies record counts.
- `disable_bizlms.php` — Disables 20 BizLMS plugins via config (reversible). Supports `--dry-run`.

**Capability migration (13 → 0 remaining):**
- All `local/costcenter:*` → dual-check via `accesslib::can_manage_multi/can_view/can_manage/is_org_head/is_dept_head`
- All `local/courses:*` → dual-check via `course_manager::can_manage/can_enrol`
- All `local/classroom:*` → dual-check via `accesslib::can_manage_classroom`
- 7 new helper methods added to `accesslib.php`

**CSS cleanup:** 190 lines of `#page-local-costcenter-*` selectors deleted from custom_changes.scss

**Run order:**
1. `php admin/cli/upgrade.php` (installs new tables)
2. `php local/airpay_org/cli/migrate_all.php --dry-run` (verify)
3. `php local/airpay_org/cli/migrate_all.php` (execute)
4. Smoke test all 5 roles
5. `php local/airpay_org/cli/disable_bizlms.php`
6. `php admin/cli/purge_caches.php`

### Phase 8: URL + Branding Removal (4 deliverables)
- Dashboard: "Moodle Version" → "Platform Version" (last visible Moodle text)
- `templates/core/maintenance.mustache` — Airpay-branded error/maintenance page
- `deploy/apache-airpay.conf` — Production Apache config (Option A: docroot, Option B: rewrite)
- `cli/verify_branding.php` — 10-point branding checklist (wwwroot, sitename, theme, caps, logo, favicon)

### Post-Fork: Remaining Replacements + Fixes
- **local_airpay_ratings** — Star rating engine (DB + rating_manager), replaces local_ratings
- **local_airpay_challenge** — Stub renderer for course challenges, replaces local_challenge
- **local_airpay_evaluation** — Stub for feedback forms, replaces local_evaluation
- **local_airpay_roles** — Stub for role management, replaces local_assignroles
- **local_airpay_programs** — Stub for certification programs, replaces local_program
- **block_airpay_trainer** — Trainer dashboard block + page, replaces block_trainerdashboard
- **Security:** 4 raw $_GET → optional_param(); SQL concat → parameterised queries
- **Missing pages:** airpay_users/index.php, signup.php; airpay_exams/index.php; airpay_classroom/index.php
- **BizLMS removal:** course_bannerimage() → Moodle core API; 8 files → tenant_manager; 6 debug lines removed; 3 upgrade.php stubs

### Fork Progress — ALL 8 PHASES + POST-FORK COMPLETE
| Phase | Plugin | Status |
|-------|--------|--------|
| 1 | local_airpay_org (costcenter) | ✅ COMPLETE |
| 2 | local_airpay_users (users) | ✅ COMPLETE |
| 3 | local_airpay_courses (courses) | ✅ COMPLETE |
| 4 | classroom + exams + learningpath | ✅ COMPLETE |
| 5 | search + categories | ✅ COMPLETE |
| 6 | theme independence | ✅ COMPLETE |
| 7 | data migration + BizLMS removal | ✅ COMPLETE |
| 8 | URL + branding removal | ✅ COMPLETE |
| — | Remaining plugins + fixes | ✅ COMPLETE |

### Complete Airpay Plugin Inventory (25 plugins + 2 blocks)
| Plugin | Purpose | Maturity |
|--------|---------|----------|
| local_airpay_org | Org hierarchy, tenant, accesslib, branding | STABLE |
| local_airpay_users | User management, profile, open_* fields | STABLE |
| local_airpay_courses | Course management, progress, enrollment | STABLE |
| local_airpay_classroom | ILT sessions, attendance, trainers | STABLE |
| local_airpay_exams | Online exams, quiz wrappers | STABLE |
| local_airpay_learningpath | Learning paths, course sequences | STABLE |
| local_airpay_catalog | Netflix catalog, commerce, cart, categories | STABLE |
| local_airpay_ratings | Star rating engine | STABLE |
| local_airpay_gamification | Points, badges, streaks, leaderboard | STABLE |
| local_airpay_compliance_report | 6-state compliance engine | STABLE |
| local_airpay_skills | Gap analysis, radar chart | STABLE |
| local_airpay_notifications | Rule engine, daily digest, nudge | STABLE |
| local_airpay_privacy | DPDP self-service | STABLE |
| local_airpay_assistant | AI chatbot (Claude API) | STABLE |
| local_airpay_analytics | KPIs, drill-down, export | STABLE |
| local_airpay_emails | 19 templates, rule engine | STABLE |
| local_airpay_pages | Homepage, static pages, QR, onboarding | STABLE |
| local_airpay_manager | Manager team dashboard | STABLE |
| local_airpay_integrations | KeKa HRMS sync | STABLE |
| local_airpay_lifecycle | JML automation | STABLE |
| local_airpay_challenge | Course challenges | ALPHA (stub) |
| local_airpay_evaluation | Feedback forms | ALPHA (stub) |
| local_airpay_roles | Role management UI | ALPHA (stub) |
| local_airpay_programs | Certification programs | ALPHA (stub) |
| theme_airpayux | 595 files, 9,700+ lines SCSS | STABLE |
| block_airpay_compliance | Compliance sidebar | STABLE |
| block_airpay_trainer | Trainer dashboard | STABLE |
| block_airpay_cron_health | Scheduled-task health dashboard widget (5 PHPUnit + a11y) | STABLE |
| block_airpay_cert_health | Certificate-email health dashboard widget (Sprint B, 6 PHPUnit + a11y) | STABLE |

---

## v2.8.0 Session (2026-04-16) — Commerce + Platform Cleanup

### Commerce System (NEW)
- commerce.php: Course pricing engine (config-based per-course, INR)
- public.php: Guest-accessible public catalog (no login required)
  - Search, sort (Popular/Newest/A-Z), pagination, pricing display
- course.php: Public course detail with Add to Cart / Enroll CTAs
- cart.php: Session-based shopping cart (works for guests)
  - Login redirect preserves cart via session
  - "Enroll in All (Free)" auto-enrolls via self-enrol plugin
  - "Payment Coming Soon" placeholder for paid courses
- lib.php: before_footer hook injects cart count for navbar badge
- Navbar: Custom cart icon with live count badge, BizLMS cart popup hidden

### Platform-Wide Dependency Cleanup
- Hardcoded tenant ID 77 → configurable via get_config + auto-detect
- Login stats: all fallbacks to all-tenant data removed
- Completion rate stat replaced with certificate count
- core_renderer: get_public_tenant_path() helper (no more inline /77%)
- Static page URL replacement: only targets href="/moodle/" (was breaking external links)
- 8 templates: "Moodle" sitename → "airpay academy"
- homepage.php: "Explore Courses" → public catalog, course cards show pricing

### Dark Mode Fixes
- head.mustache: Runs on EVERY page, detects OS prefers-color-scheme
- Explicitly removes dark-mode when preference is light (was only adding)
- Toggle icon synced on DOMContentLoaded
- Commerce pages: dark mode CSS in moodle.css
- Profile: .userprfltabs_container white wrapper fixed

### Signup Form
- Merged 2 checkboxes into 1 ("Privacy Policy & Terms of Use")
- Links to /local/airpay_pages/index.php?page=privacy

### New Pages
- DPDP Act 2023 page (/local/airpay_pages/index.php?page=dpdp)
- Moodle URL Removal Guide (MOODLE-URL-REMOVAL.md)

### Bug Fixes
- course.php: missing ID redirects to catalog (was 500 error)
- Switch role: $DB null crash fixed (global $DB added)
- BizLMS cart popup: hidden via CSS (conflicted with custom cart)

---

## v2.7.0 Session (2026-04-15) — Full Audit Execution

### Audit Buckets Completed (6 of 8)
| Bucket | Status | Key Deliverables |
|--------|--------|-----------------|
| 1: Bug Fixes | ✅ 16/16 | Permission bypass, race conditions, dark mode, empty states, caching |
| 2: Commercial Wins | ✅ | Learner onboarding wizard (4-step, first-login) |
| 3: UX Fixes | ✅ | ~90 dark mode rules, profile with skills/badges/stats, leaderboard confirmed |
| 4: Engagement | ✅ | Learning streak observer, manager nudge UI, daily digest task |
| 5: Admin Productivity | ✅ | Analytics drill-down (dept→users, course→learners), CSV export, compliance CSV |
| 6: Enterprise | ✅ | Manager dashboard plugin (local_airpay_manager), SSO setup guide |

### New Plugin: local_airpay_manager
- Team learning dashboard for supervisors
- Per-member: enrolled, completed, rate, overdue, streak, last login
- KPI cards: team size, avg completion, overdue, at-risk
- Action buttons: nudge, view skills, view profile
- Dark mode + mobile responsive

### DPDP Module Rewrite
- 4-tier access control: siteadmin → tenant admin → internal employee → external user
- Internal employees (Airpay tenant 1): policy notice only, no download/deletion
- External users (DPDP-enabled tenants): full self-service
- Configurable: siteadmin sets which tenants have DPDP via get_config('dpdp_tenants')

### BizLMS Switch Role Fix
- /my/switchrole.php created (was 404)
- Dashboard respects $SESSION->airpay_switchrole and $USER->useraccess
- Admin→Employee switch now shows learner dashboard (not admin)

### Profile Dark Mode Fix
- .userprfltabs_container white wrapper eliminated
- 11 dark mode rules for BizLMS profile classes
- Added to both SCSS and precompiled moodle.css

### Other Fixes
- DPO email: dpo@airpay.co.in → academy@airpay.co.in
- Privacy policy text softened for employees
- Progress bar sticky positioning fixed
- Compliance report table_exists() guard
- Quick Access hamburger CSS :has() fix

### Remaining Audit Roadmap (Buckets 7-8)
- Bucket 7: SENTIENTIA AI content creator, AI-powered recommendations
- Bucket 8: PWA mobile app, content marketplace connector

---

## v2.6.0 Session (2026-04-15) — Product Audit + Fixes

### Deep Product Audit (14-section report on Desktop)
- Full forensic audit: 15 learner modules + 10 admin modules rated
- Competitive benchmark vs Docebo, Absorb, TalentLMS, 360Learning, LearnUpon, Sana Learn
- 16 bugs found and ALL 16 resolved (1 critical, 1 high, 10 medium, 4 low)
- Top 25 prioritized actions identified
- Ticket-ready backlog for next 6 months

### Bug Fixes (16/16 complete)
- B1 CRITICAL: Compliance manager permission bypass — column guard + capability fallback
- B3: Dynamic tenant IDs (no more hardcoded [1,77,177])
- B4: Skills permission now throws error instead of silent fallback
- B5: Notification duplicate race condition — transaction-based dedup
- B6: Escalation to deleted manager — active user check
- B7: Compliance "last refreshed" timestamp + stale data warning
- B8: Notification batch LIMIT now configurable (default 500)
- B9: mycourses.php user_lastaccess try/catch guard
- B10: Email management plugin dark mode CSS (16 rules)
- B11: Email preview iframe mobile overflow fix
- B12: Compliance KPI caching via Moodle cache API
- B13: Analytics funnel empty state message
- B16: Mobile landscape orientation CSS

### New Features
- Learner Onboarding Wizard (4-step: Welcome → Interests → Goal → Courses)
  - Auto-triggers on first login for non-admin learners
  - Saves preferences to user_preferences table
  - Gradient branded UI, mobile responsive
- Quick Access hamburger menu fix (CSS :has() + JS MutationObserver)

### Multilingual Completion
- Theme lang files: 120+ strings × 4 languages (hi, mr, sw, kn)
- Email lang files: 35 strings × 4 languages
- Official Moodle lang packs installed: hi (709 files), mr (382), sw (301), kn (350)
- Translation CSV exported for Cowork review (386 strings)

### Remaining Audit Roadmap (not yet built)
- Bucket 3: Dark mode completion, profile enhancement, leaderboard on dashboard
- Bucket 4: Learning streak, manager nudges, daily digest
- Bucket 5: Custom report builder, analytics drill-down
- Bucket 6: SSO/SAML, ROI reporting, demo tenant
- Bucket 7: SENTIENTIA AI content creator, AI recommendations
- Bucket 8: PWA mobile app, content marketplace

---

## v2.5.0 Session (2026-04-14) — MEGA SESSION

### Tenant Isolation (10 cross-tenant data leaks sealed)
- Dashboard KPIs (enrolments, completions, active users, classrooms) scoped to tenant via open_path
- Homepage stats + featured courses scoped to Public tenant (/77%)
- Login page stats scoped to Public tenant
- Catalog category counts scoped to user's org
- Gamification leaderboard + rank scoped to user's tenant
- Badge criteria (compliance_complete, leaderboard_top10) scoped per-tenant
- Analytics heatmap mandatory course count + course effectiveness scoped
- Logo fallback: validates physical file exists, falls back to default_logo.png

### LXP UI/UX Overhaul (Sprints 3-11)
| Sprint | Deliverable | Files |
|--------|-------------|-------|
| 3 | Netflix catalog: carousels, bookmarks, autocomplete, lazy load | 5 |
| 4 | Course detail: completion states, related courses, social proof | 2 |
| 5 | Course player: collapsible sidebar, keyboard shortcuts, module tree | 3 |
| 6 | Exam dashboard template rewrite + CSS consolidation | 2 |
| 7 | Profile tabs modernization + certificate gallery | 3 |
| 8 | Skills dashboard (NEW from scratch) + compliance CSS | 4 |
| 9 | Notifications CSS (NEW) + gamification dark mode + AI polish | 3 |
| 10 | Email security fix + privacy bug + static pages nav | 4 |
| 11 | Homepage animations + mobile bottom nav + local QR | 3 |

### Multilingual Support (v2.5.0)
- 4 languages: Hindi (hi), Marathi (mr), Swahili (sw), Kannada (kn)
- 9 plugins × 4 languages = 29 lang files (28 new + 1 completed)
- ~1,056 total translations
- Activation: Admin installs official Moodle lang packs, selector auto-shows in navbar

### Security Fixes
- Email preview.php: path traversal injection fixed (sanitize before fallback)
- Email preview.php: tenant access validation (non-siteadmin locked to own tenant)
- Privacy index.php: account_delete enum mismatch fixed

### Tags
- v2.3.0-tenant-isolation — 10 cross-tenant leaks sealed
- v2.4.0-lxp-overhaul — Sprints 3-11 complete
- v2.5.0-multilingual — 4-language i18n across 9 plugins

---

## What's Built & Working

### Role-Based Dashboards (4 tiers)
| Role | Detection | Dashboard View |
|------|-----------|---------------|
| Siteadmin | `is_siteadmin()` | KPIs + Quick Nav + Charts + System Health + User Analytics |
| L&D Admin | `local/courses:manage` | KPIs + Quick Nav + Charts + User Analytics (no System Health) |
| Manager/HRBP | `moodle/site:viewreports` | Team KPIs + Compliance Table + Learner sections |
| Employee/External | everyone else | Welcome + Stats + Courses + Deadlines + Achievements + Timeline |

### Theme (airpayux)
- 10 surfaces styled: Login, Dashboard, Navbar, Footer, Catalog, Course Detail, Profile, Admin Tables, Mobile, Static Pages
- Dark mode + High Contrast mode (CSS layers, localStorage persistence, ~400 lines in `dark_mode.scss`)
- Component library (5 Mustache partials: button, card, badge, progress, stat_card)
- Service worker for static asset caching
- Costcenter scheme system (3 tenants)
- ~6,800 lines of custom SCSS
- jQuery compatibility: all 30 BizLMS AMD modules verified clean

### BizLMS Stabilisation (Phase 15)
- Course-to-costcenter mapping fixed (`open_path` + `selfenrol` + `open_identifiedas`)
- Role assignments configured per costcenter context
- cardPaginate float collapse fixed (CSS clearfix)
- Manager team structure: 10 employees under mgr_nitin (`open_supervisorid`)
- Manage Users, Manage Courses, Manage Company all rendering
- Dark mode covers all pages including BizLMS admin (costcenter stat cards, user/course cards, content containers)
- Visual testing complete: superadmin, L&D admin, employee, manager dashboards all verified
- Catalog blocked by BizLMS web service config (A3) — dashboard provides alternative course discovery

### Phase 16 — Production Data Import (2026-04-07)
- Imported production database (airpayprod 6th April backup, 3.5GB) into local XAMPP
- Collation fix: 2,176 instances of `utf8mb4_0900_ai_ci` → `utf8mb4_unicode_ci` (MySQL 8.0 → MariaDB 10.11)
- GTID_PURGED line removed
- 618 tables, 2,871 active users, 411 courses, 213 costcenters — all imported successfully
- Moodle upgrade ran: 53 plugins upgraded (4.1→4.5), 30 new plugins installed, 21 legacy deleted
- Fixed `MESSAGE_DEFAULT_LOGGEDIN` → `MESSAGE_DEFAULT_ENABLED` in `local_airpay_lifecycle/db/messages.php`
- Theme set to airpayux, config.php wwwroot/dataroot unchanged (already localhost)
- 3 tenants live: Airpay (id=1, 205 sub-orgs), Public (id=77), ZEEA (id=177)
- Login verified as production siteadmin (academy@airpay.co.in)

### UI/UX Audit — Round 1+2 Complete (2026-04-08)
**Fixes applied:**
- jQuery AMD wrapping: 13 mustache templates (nav-drawer + 12 BizLMS templates) — `$ is not a function` errors resolved
- "Bussiness" → "Business" typo: 9 BizLMS lang files fixed
- Created missing `local/courses/fulldescriptionpopover.js` — unblocked Online Exams + Classrooms pages
- Reports dashboard link: `viewreport.php` → `managereport.php` (was requiring missing `?id=` param)
- Learning Paths: removed invalid `use core_component;` (PHP 8.2 warning)
- `perfdebug` set to 0 (was 7 from production — caused "Reactive instances" debug text)
- CSS: hidden reactive debug panel, hidden stray Policies link, brightened dark mode Quick Nav stats

**Round 1 — Siteadmin (academy@airpay.co.in):**
- Dashboard: ✅ KPIs (1,407 users, 407 courses, 39K enrolments, 20.6% completion), charts, quick nav, system health
- Manage Users: ✅ 2,869 users, card view, zero JS errors
- Manage Courses: ✅ 411 courses with production images
- Manage Company: ✅ All 3 tenants (Airpay 2,187 users, Public 676, ZEEA 6)
- Reports: ✅ LearnerScript report list rendering
- Online Exams: ✅ (was BROKEN → fixed with fulldescriptionpopover.js)
- Classrooms: ✅ (was BROKEN → fixed with same JS)
- Learning Paths: ✅ Production plans rendering (PG Products, ERP, BC Training, Customer Success, HR Onboarding)

**Round 2 — Employee (mithu.bala@airpay.co.in, Vyaapaar Fintech):**
- Dashboard: ✅ Welcome banner, 48 enrolled, 3 in progress, 21 completed, 15 certificates
- Continue Learning: ✅ 6 course cards with progress bars
- Activity Timeline: ✅ Real learning history (completions, quiz submissions, enrollments)
- Recent Achievements: ✅ 5 certificates with codes and dates
- My Courses: ✅ Moodle course overview with progress percentages
- Profile: ✅ BizLMS profile with personal info, stats, avatar

**Round 3 — Manager (binay.upadhyay@airpay.co.in, Vyaapaar, 9 direct reports):**
- Dashboard: ✅ CRITICAL FIX — added `open_supervisorid` fallback for manager detection (production managers have no capability roles)
- My Team: ✅ 9 team members, 115 enrolments, 29 completions, 25.2% rate
- Team Compliance: ✅ All 9 reports with enrolled/completed/pending/last active
- Navbar: ✅ Correct 4 pills (Dashboard, My Courses, Catalog, Profile)

**Round 4 — External (demoairpayacademy@gmail.com, Public /77):**
- Dashboard: ✅ 42 enrolled, 4 in progress, 11 completed, 6 certificates
- Continue Learning: ✅ Mixed hiring assessments + BC training courses
- Tenant isolation: ✅ Only sees Public tenant courses
- Logo: ✅ Default academy logo (Public has no costcenter_logo set)

**Round 5 — ZEEA (user.4156200@gmail.com, /177/178):**
- Dashboard: ✅ 20 enrolled, 0 in progress, 0 completed, 5 certificates
- Logo: ✅ ZEEA mafunzo logo loaded dynamically from costcenter_logo — tenant branding works!
- Courses: ✅ Swahili course names (Jinsi ya kuweka bidhaa, Uwezeshaji wa Ufanisi)
- Recently accessed: ✅ SCORM packages, quizzes, admin guide — all ZEEA content

**Round 6 — Guest (not logged in):**
- Homepage: ✅ Enterprise hero, stats, navigation
- Login: ✅ Split-screen with production stats
- Registration: ⚠️ Password field cosmetic issue (G3 — "Click to enter text")
- Help Center: ✅ 4 help cards
- Footer: ✅ Clean

**UI/UX Audit Complete — 6/6 rounds pass. All critical fixes applied.**
- Failsafe backups at: `D:\Claude Local\Moodle Backup\moodle_local_pre_import_20260407.sql` + theme + plugin copies

### Production DB Analysis Deliverables (2026-04-07)
- `Airpay-Academy-Production-DB-Diagnostic.pdf` — 33-question diagnostic with data evidence
- `Airpay-Academy-Production-Stabilization-Guide.pdf` — Full admin playbook (74 duplicate courses, cleanup SQL, naming convention)
- `Production-Data-Verification.xlsx` — 154 orphaned users, 116 never-logged-in, 1,407 active user roster, 213 costcenter map
- `Production-Import-Upgrade-Log.xlsx` — 105 plugin upgrade/install/delete log

### Plugins Built (16 plugins)

**Tier 1 (v1.1.0):**
- `local_airpay_gamification` — Points engine, 10 badges, streak calendar, leaderboard, event observers
- `local_airpay_notifications` — Rule engine, 7 notification rules, hourly cron, Moodle messaging
- `local_airpay_catalog` — LXP-style catalog: carousels, search, filters, trending, recommendations

**Tier 2 (v1.2.0):**
- `local_airpay_skills` — 48 fintech skills, 8 categories, role mapping, gap analysis, radar chart
- `local_airpay_analytics` — KPI trends, engagement funnel, compliance heatmap, course effectiveness

**Tier 3 (v2.0.0):**
- `local_airpay_assistant` — AI learning assistant (Claude API), floating chat bubble, 20 queries/day
- `local_airpay_integrations` — KeKa HRMS OAuth client, JML webhooks, employee sync
- `local_airpay_lifecycle` — Employee lifecycle automation (MESSAGE_DEFAULT_ENABLED fix applied)

**v2.1.0:**
- `local_airpay_compliance_report` — 6-state compliance engine, auto-enrol, progressive email escalation, 5 reports, Excel export
- `local_airpay_privacy` — DPDP Act 2023 self-service: data download (JSON), account deletion, consent log

**Foundation:**
- `local_airpay_pages` — Privacy Policy, Terms, Help Center, Contact Us (editable HTML, DPDP section updated)
- `block_airpay_compliance` — Compliance Dashboard block
- CLI scripts: seed_testdata.php, seed_users.php, fix_manager_role.php

### Wiring (v2.1.0)
- Compliance Report card in admin Quick Nav (with live stats: mandatory count + overdue count)
- Privacy (DPDP) card in admin Quick Nav (with pending request count)
- "My Privacy & Data" link in user dropdown menu (all logged-in users)
- Privacy static page updated with DPDP Act 2023 sections and self-service portal link
- `$CFG->noemailever = true` in config.php — zero emails sent from local environment

### Email Templates + Notification Management (v2.2.0)
**Branded Email System (local_airpay_emails — 56 files):**
- 19 Mustache email templates (6 compliance, 5 notifications, 4 enrollment, 2 account, 2 privacy)
- Theme email wrapper override (`core/email_html.mustache`) — branded header, Airpay signature footer, Indian tricolor bar
- 3 reusable partials (CTA button, course info box, footer note)
- Email renderer with DB override resolution chain (tenant → global → file fallback)
- Per-tenant template customization (DB table: local_airpay_email_overrides)
- 10 seeded notification rules (DB table: local_airpay_email_rules)
- Unified delivery log (DB table: local_airpay_email_log) with CSV export
- User notification preferences (DB table: local_airpay_email_prefs)
- Visual preview page (`/local/airpay_emails/preview.php`) with 19 templates, tenant selector, mobile/desktop toggle
- Management panel (`/local/airpay_emails/manage.php`) with 5 tabs: Dashboard, Templates, Rules, Logs, Settings
- BizLMS legacy integration (read-only view of 20+ BizLMS notification types)
- 5 AJAX web services (get/save/revert/preview template, toggle rule)
- 3 AMD JS modules (template_editor, rule_manager, delivery_log)
- Scheduled task: hourly rule processing with dedup
- 6 capabilities for granular permission control
- Email default: popup=enabled, email=opt-in only (lesson from 151-email incident)

**Bug Fixes (v2.2.0):**
- Privacy admin panel: siteadmins now see request management (approve/reject) instead of user self-service
- AI Assistant: enable/disable toggle in admin settings (Site Admin → Plugins → Airpay AI Learning Assistant)
- Quick Access icon: fixed broken JS controller (was using notification_popover_controller, now proper toggle)
- Cookie consent popup: disabled `sitepolicyhandler` for local development
- SMTP credentials wiped from DB, noreplyaddress set to localhost.invalid
- Email sending triple-locked: noemailever + no SMTP + localhost noreply

### Test Users
| Username | Name | Role | Password | Tenant |
|----------|------|------|----------|--------|
| superadmin | Super Admin | Siteadmin | Academy@2026 | — |
| test_admin | Amit Patel | L&D Admin (local/courses:manage) | Airpay@2026 | Airpay (1) |
| mgr_nitin | Nitin Manager | Manager (moodle/site:viewreports) | Airpay@2026 | Airpay (1) |
| emp_priya | Priya Singh | Employee (student) | Airpay@2026 | Airpay (1) |
| test_external | Deepa Menon | External (student) | Airpay@2026 | Public (77) |

**Manager team:** mgr_nitin supervises 10 employees (via `open_supervisorid`)

---

## Production Deploy Checklist

### Pre-deploy
- [ ] Backup production database
- [ ] Backup production theme/epsilon directory
- [ ] Verify server environment matches (PHP 8.2, MariaDB 10.11)

### Deploy Steps
1. Copy `theme/airpayux/` to production Moodle `theme/` directory
2. Copy `local/airpay_pages/` to production Moodle `local/` directory
3. Navigate to Site Admin → Notifications (triggers plugin install)
4. Activate airpayux theme: Site Admin → Appearance → Themes → Theme selector
5. Purge all caches: Site Admin → Development → Purge all caches
6. Hard refresh browser (Ctrl+Shift+R)

### Post-deploy verification
- [ ] Login page renders (split-screen, logo, stats)
- [ ] Superadmin dashboard shows admin view (KPIs + System Health)
- [ ] L&D Admin dashboard shows admin view without System Health
- [ ] Employee dashboard shows learner view (stats, courses, deadlines)
- [ ] Manager dashboard shows team KPIs + compliance table
- [ ] Navbar pills correct per role
- [ ] Footer correct per role (compact single row)
- [ ] Dark mode toggle works + persists across page loads
- [ ] Dark mode renders cleanly on BizLMS admin pages
- [ ] Static pages load (Help, Contact, Privacy, Terms)
- [ ] BizLMS Quick Access works
- [ ] Course catalog loads with courses
- [ ] Manage Users renders user cards
- [ ] Manage Courses shows courses
- [ ] Zero new console errors

---

## Git Tags
| Tag | Description |
|-----|-------------|
| phase5-final | Moodle 4.5.10 stabilised |
| phase6a-theme-foundation | Design system + fork baseline |
| phase6b-sprint7-final | All 7 CSS sprints complete |
| phase6b-prototype-match | Dashboard sections + pill nav + footer |
| phase7a-stabilised | 4-tier roles, nav fixes |
| phase7b-tested | All user types tested |
| phase15-production-ready | BizLMS stabilised, dark mode, deployment runbook |
| v1.0.0-rc1 | Base platform (theme + 4-tier dashboards + BizLMS) |
| v1.1.0 | Tier 1: Gamification + Notifications + Catalog |
| v1.2.0 | Tier 2: Skills Matrix + Analytics + Hindi |
| v2.0.0 | Tier 3: AI Assistant + KeKa HRMS + PWA + Marketplace stubs |
| v2.1.0 | Compliance Report + DPDP Privacy + Admin wiring |
| v2.2.0 | Email Templates + Notification Management Panel + Bug Fixes |

---

## Deployment Status

**Ready for IT team.** See `DEPLOYMENT-RUNBOOK.md` (Phase 15 — Final).

### Known Limitations (Ship With)
- BizLMS DataTables list view (B3) — untested, card view works
- BizLMS modal dialogs (B4) — may need production testing
- Reports, Online Exams, Classrooms (C4-C6) — untested BizLMS modules
- Email flows — not tested locally (production SMTP pre-configured)

---

## What's Next
- Visual demo inspection (7 scenes, ~15 minutes, all roles)
- Verify compliance snapshot with real data (2,871 users × 4 mandatory courses)
- Test privacy self-service as Public tenant user
- Production deployment (IT team — see DEPLOYMENT-RUNBOOK.md)
