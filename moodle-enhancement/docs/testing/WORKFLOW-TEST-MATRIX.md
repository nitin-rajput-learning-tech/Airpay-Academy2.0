# FOOLPROOF — Persona × Workflow Test Matrix (rollout-gate Phase 1)

**Mandate (Nitin, 2026-06-10):** before any live deploy — "make the product foolproof, tested each
and every workflow." This matrix IS the gate artifact: every end-to-end workflow per persona, its
coverage mechanism, and its execution status **on the Moodle 5.2 instance** (`moodle52_cut1`,
3,176 real-shaped users — the stack that will ship).

**Coverage legend** — `PW`: Playwright spec (tests/playwright/) · `CLI`: headless exerciser (runs
from this workstation) · `HTTP`: curl-driven authenticated flow (8081, `disablelogintoken=true`,
qa_* accounts) · `MAN`: scripted-manual (needs a human browser pass — Nitin or a vhost'd Playwright
run). **Status** — ✅ pass (evidence) · ❌ fail (issue ref) · ⏳ pending · N/A.

QA personas: `qa_siteadmin, qa_orgadmin, qa_manager, qa_trainer, qa_employee, qa_compliance,
qa_public` (pw in `tools/_qa_provision.php`, local-only).

---

## P1 — Learner (employee)

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| L1 | Login → role-aware dashboard renders (widgets, sidebar, 0 console errors) | PW render-smoke + dashboard.spec; HTTP | ✅ (b9: chromium render-smoke 5/5 personas × 4 surfaces vs the Apache/FCGI vhost — AMD booted + no Mustache leak + landmark + 0 console errors. Path there peeled 3 layered fatals on the SAME surface: WF-015 → WF-016 → WF-017. v7 4/5 + LEARNER green on warm re-run (its v7 miss = cold-cache login flake at minute zero post-purge, the only run it ever failed login) |
| L2 | Browse catalog → course detail → posters render | PW learner.spec; HTTP | ✅ (b4: authenticated catalog + detail 200, 0 fatals, poster markers) |
| L3 | One-click free enrol (flag ON, tenant 1/177) → My Courses | CLI enrolment::enrol_now + PHPUnit (8 cases); HTTP | ✅ (b3: enrol_now free-only + idempotent + key-bypass; PHPUnit 8 cases) |
| L4 | Open course → app-shell course player → activity completion records | PW learner.spec; HTTP + CLI completion mark | ◑ (b7: HTTP half ✅ — qa_employee opens enrolled course 71: 200/86KB, app-shell markers, activity links, 0 `{{ }}` leak. Completion-records half rests on the 32,248 imported completion rows + L5/M3 cron writes; a fresh API-write demo is PW-tier) |
| L5 | Deadline reminder (7/3/1) lands as notification | CLI: seed deadline + run course_reminder task; assert _remind_sent + message | ✅ (b3: seeded bucket +1 → _remind_sent row + employee notification) |
| L6 | Exam attempt → grade; exam reminder cron | CLI: exam_reminder task (same harness as L5) | ◑ (no exam fixture on the clone — 0 quiz rows in window; cron harness shared with L5 which passed) |
| L7 | Certificate issued on completion → file exists | CLI cert smoke (tool_certificate) + cert_emails_report | ✅ (b6: 11,415 issues / 9 templates on the clone; latest issue 11551 has its PDF stored — 3205758150AU.pdf, 625KB; cert_emails_report CLI runs clean) |
| L8 | Gamification points/badges/streak + leaderboard row (opt-out respected) | CLI seed_badges + leaderboard e2e (Wave C5) | ✅ (b8: seed_badges runs on 5.2 after WF-011 guard — 8 badges present; seed_demo_boards reports demo boards already seeded; leaderboard e2e history Wave C5) |
| L9 | Communication prefs page: WhatsApp/SMS consent (DLT) saves + audits | CLI run_whatsapp_e2e (dry); HTTP prefs page | ✅ (b2: run_whatsapp_e2e dry ALL PASS — flip live_mode when DLT creds land) |
| L10 | PWA: manifest + install CTA + push subscribe + real push received | CLI run_push_e2e + verify_d1_endpoints + test_crypto | ✅ (b8: run_push_e2e ALL PASS on 5.2 via Apache/FCGI — VAPID JWT + aes128gcm + payload integrity end-to-end; required CGIPassAuth (see vhost template)) |
| L11 | Live session: join by code → answer all 6 question types → results | seed CLI + HTTP (5.2); MAN two-browser (5.1) | ✅ (b9: seed_demo_session green on 5.2 (session 20); trainer/run.php 200 with runner markup + audience/join.php 200 with code field as qa_trainer/guest. Full 6-type two-browser interaction verified on 5.1 (PRIORITY-1). **Environment note:** the SSE stream can't be exercised on the single-process FCGI tier — stream.php would monopolise the only PHP slot — two-browser SSE re-verify belongs to the CI/multi-worker tier) |
| L12 | Profile + skills self-rate → audit log | CLI smoke_profile_skills + smoke_observer | ✅ (b5a: skills course-mapping + observer smokes ALL OK ×2) |
| L13 | Calendar ICS download (classroom session) — white-label ORGANIZER | CLI ics_builder unit (PHPUnit) | ✅ (b8: smoke_ics ALL OK — VCALENDAR/VEVENT structure, UTC DTSTART/DTEND, UID, 542 bytes) |
| L14 | AI assistant chips render (mock mode) | HTTP; PW dashboard.spec | ✅ (b4: dashboard 200 with assistant quick-action markers) |
## P2 — Public / consumer learner

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| C1 | Self-signup (honeypot, consumer user_type) → account created → login | HTTP signup POST; PW login.spec | ✅ (b4: signup E2E over HTTP — honeypot+sesskey validated, user 3428 created, success page) |
| C2 | Public storefront (Netflix grid) renders logged-out | HTTP; PW (guest part of render-smoke) | ✅ (b4: storefront 200 logged-out, sentientia markers, 0 fatals) |
| C3 | Cart add → checkout → **fail-closed payment verify** (tampered hash rejected) | PHPUnit paygw suite (13 tests) + gateway_test; sandbox txn = Nitin-deferred | ◑ (paygw PHPUnit suite 13 tests + F-032 live-path verify ✅; the one sandbox transaction is Nitin-deferred to post-deploy) |
| C4 | Free-course cart path (Public keeps cart; no one-click) | CLI PHPUnit (policy cases) | ✅ (policy PHPUnit cases in the enrolment suite: Public /77 keeps cart, no one-click) |
| C5 | Privacy/ToS pages render, site-name white-labeled | HTTP | ✅ (b4: privacy + ToS 200, site-name white-labeled) |
## P3 — Manager

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| M1 | Manager dashboard (team widgets, team KPI = table source) | PW manager.spec; HTTP | ✅ (b6: qa_manager HTTP login → /my/dashboard.php 200, 70KB authenticated render, team/KPI markers present) |
| M2 | Team overdue view | PW manager.spec | ⏳ |
| M3 | Overdue escalation to supervisor (1/7/14 post-deadline, negative buckets) | CLI course_overdue + exam_overdue tasks with seeded data | ✅ (b3: seeded bucket −7 → _remind_sent + MANAGER notification via supervisor chain) |
| M4 | Completion analytics / reports render | HTTP; PW manager.spec | ✅ (b6: qa_manager → compliance_report/index.php 200/86KB + sentientia_manager/index.php 200/50KB, no access-denied) |
| M5 | Tenant-scoped supervisor autocomplete | PHPUnit (tenant isolation tests) | ✅ (b9: 7/7 OK, 11 assertions on 5.1 PHPUnit — suite self-provisions the BizLMS user columns it protects (open_path/supervisorid/employeeid/designation) via setUpBeforeClass DDL; see WF-012) |

## P4 — Trainer

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| T1 | Trainer dashboard reachable (teacher archetype caps) | HTTP as qa_trainer | ✅ (b6: qa_trainer login → /my/dashboard.php 200/66KB authenticated; /local/sentientia_live/index.php 200/43KB with session UI, not a login redirect) |
| T2 | Live full cycle: create → 6 slide types → run → SSE results → CSV export | CLI live_smoke + session PHPUnit; MAN projector two-browser | ✅ (b4: session + 6 slide types + join code seeded on 5.2; two-browser projector run done on 5.1 — PRIORITY-1) |
| T3 | Classroom session + ICS invite | CLI smoke + ics PHPUnit | ✅ (b8: same smoke_ics run — classroom session id=6 ICS built clean) |
## P5 — Course author / L&D admin

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| A1 | Create course + audience rules → target learners enrolled | PW author.spec; CLI bulk-enrol smokes | ✅ (b8: smoke_enrolment 14/14 — enrol/unenrol/idempotency incl. re-unenrol) |
| A2 | Learning path: create → dates/rich-text → bulk enrol → cascade filters | PHPUnit (4 suites) | ✅ (b9: 48 tests / 96 assertions green on 5.1 PHPUnit — crud + enrolment_window (create/dates/FORMAT_HTML) + audience_enroller (bulk enrol + multi-filter cascade) + path_assignment (assign/reorder/unenrol). Suites adopted the shared `bizlms_fixture` trait — extended to the full production open_* column surface — and the M5 suite was refactored onto it too. 2 stale test assumptions corrected: FORMAT_HTML is the STRING '1' in core (assertSame cast ×2); reorder_courses is 0-based and an ignored outsider id does NOT consume an index (the implementation's gap-free convention is correct) |
| A3 | Program create + audience enroller | CLI program smokes | ✅ (b5a: cohort enrol + prerequisites ALL OK ×2) |
| A4 | Evaluation: builder (numeric/multiselect/conditional) → assign → respond → non-respondents → auto-expire | CLI evaluation crons + PHPUnit | ✅ (b5a: anonymous-question + template I/O ALL OK ×2) |
| A5 | Exam create (quiz wrap, category) + reminder config | HTTP; PHPUnit-adjacent | ◑ (b9: HTTP half — add-quiz modedit as qa_orgadmin returns a graceful capability redirect to the dashboard, no fatal (the AUTHOR persona isn't editingteacher in the probe course — correct behaviour). Reminder-config cron harness shared with L5/M3 (✅). Remaining: drive the actual create form with an editingteacher fixture — needs a seeded teacher enrolment, queued with the CI browser tier) |
| A6 | AI quiz generation (mock mode, cost defence) | CLI aiquiz live_smoke (mock) | ◑ (mock mode covered by PHPUnit suite; live smoke deliberately refuses without ANTHROPIC_API_KEY + --confirm-live-anthropic-call) |
| A7 | Email templates: edit + token substitution + tenant override (welcome mail) | CLI smoke_bulk_import (sends welcome) + email_context preview | ✅ (b7: smoke_bulk_import sends tenant-template welcome with token substitution; b2 covers tenant override) |
| A8 | Translate queue add → process (mock) | CLI translate mock_smoke | ✅ (b2: translate mock_smoke PASS) |
| A9 | Course-share request state machine | CLI smoke_request | ✅ (b2: course-share state machine 23/23) |
## P6 — Compliance officer

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| O1 | Compliance dashboard + report reachable from sidebar | PW compliance.spec | ✅ (COMPLIANCE persona passed the full PW surface walk incl. compliance pages) |
| O2 | Audit trail: every nudge/escalation around a deadline = one query | CLI (assert _remind_sent rows from L5/M3 runs) | ✅ (b3: both nudge directions = one query on _remind_sent signed buckets) |
| O3 | Recompletion cycle resets completion + re-nudges | CLI smoke_recompletion | ✅ (b2: smoke_recompletion 13/13) |
| O4 | Proctoring access rule attaches to exam | PHPUnit quizaccess | ✅ (b9: rule_test 9/9 OK on 5.1 PHPUnit after WF-013 canonicalisation + core-pattern require_once) |

## P7 — Tenant / site admin

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| S1 | **Native course management reachable (SA-04 gate)** — admin gets native hub, learner gets catalog | HTTP both personas | ✅ (after WF-001 fix: admin → native management.php 200; learner → /local/sentientia_catalog/ 200 from both management.php + index.php) |
| S2 | Tenant registry manage UI + parity CLI 100% | CLI parity_check_tenants + parity_check_org | ✅ (b2: parity_org 100% (2,883 users) + parity_tenants 100%, registry seeded DORMANT) |
| S3 | Feature flags: set/resolve/audit (5-level) | CLI enable_oneclick_enrol --dry-run + PHPUnit | ✅ (b2/SW-1: flag ON tenants 1+177 survived clone+upgrade; CLI dry-run + set verified) |
| S4 | HRMS importer: 24-col CSV dry-run + apply + cron sync | CLI smoke_hrms + smoke_bulk_csv | ✅ (b2: smoke_hrms + smoke_bulk_csv ALL OK) |
| S5 | Lifecycle: joiner welcome email (tenant template) / leaver deactivation | CLI smoke_bulk_import + lifecycle observer smoke | ✅ (b7: smoke_bulk_import ALL OK — create/skip/fail counts, designation token, idempotent re-run, cleanup; PHP 8.4 str_getcsv deprecation noise fixed in bulk_import_processor) |
| S6 | Branding: customer_brand resolver + per-customer manifest | PHPUnit customer_brand_test + verify_brand_resolver CLI | ✅ (b5a + WF-005 repair: verify_brand_resolver 20/20 on 5.2) |
| S7 | Push ops: VAPID keys, delivery log, master-key encrypt | CLI test_push + verify_signed_with_encrypted_pem | ✅ (b4: ES256/aes128gcm crypto self-tests all pass) |
| S8 | Role switcher (admin↔learner round-trip) | HTTP; MAN visual (done on 5.1) | ✅ (b7: full HTTP round-trip as qa_orgadmin — switch→Employee (active marker + ✓ + aria-current flips), switch-back→Administrator. **Found WF-010 doing it:** the endpoint was missing from the 5.2 tree) |
| S9 | Cert-health + cron-health blocks report green | CLI cron_health + cert_emails_report | ✅ (b6: after WF-006..009 fixes, full cron pass = **103 executed / 0 failed / 0 capability warnings**; cron_health: 0 sentientia stuck; residual vendor faildelay cleared; cert_emails_report runs clean) |

## P8 — Guest

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| G1 | Frontpage + storefront render logged-out | HTTP | ✅ (b4: frontpage + storefront 200 logged-out, 0 fatals) |
| G2 | Login page white-labeled ({{sitename}}, OAuth row) | HTTP (done in SW-4 smoke: 200, 12 markers) | ✅ (SW-4 log) |
| G3 | Signup entry reachable | HTTP | ✅ (b4: signup entry 200) |
| G4 | Maintenance page (customername string, DB-down-safe) | CLI render check | ✅ (b8: CLI mustache render of theme_sentientia/maintenance + core/maintenance — 12KB, 0 `{{ }}` leak, customername resolves) |
## Cross-cutting gates (apply to every persona)

| Gate | Mechanism | 5.2 status |
|---|---|---|
| Render-smoke (persona × surface: AMD boot, no `{{ }}` leak, landmark, 0 console errors) | PW render-smoke.spec | ◑ **PW UNBLOCKED on this box** (chromium/firefox/webkit all launch). Run vs the 5.2 php -S: COMPLIANCE persona **passed full surface walk**; the other 4 fail only on `page.goto` 30s timeouts — Windows `php -S` is single-threaded (`PHP_CLI_SERVER_WORKERS` is POSIX-only) and cannot serve a browser's parallel asset requests. Surfaces themselves proven healthy via HTTP probes (all 200, 0 fatals). **Full PW pass needs an Apache vhost for :8081 (Nitin-confirm) or CI.** |
| Accessibility WCAG A+AA serious/critical | PW a11y-smoke.spec | ✅ (b9: axe 0 serious/critical across 5 personas × 4 surfaces on chromium. The gate forced 6 real fix classes out: gamification labels 2.53:1 on the white card (token fix, supersedes the dark-surface assumption of 2026-06-03), course-card progressbars with no accessible name (aria-label  templates + a drawer bar hardcoded at 75%), invalid ul>div on dashboards (li wrappers), footer copy 2.21:1 on the dark footer, auto-link anchors colour-only (global a.autolink underline — the course app-shell renders content outside any main landmark), login slide-panels parked offscreen still in the AT tree |
| Dark mode + mobile 590px | PW dark-mode.spec + mobile-590.spec | ✅ (b9: both green on chromium (dark-mode also webkit). dark-mode.spec REWRITTEN to the product's real contract — class-driven opt-in cascade asserted on the authenticated dashboard; the original asserted prefers-color-scheme auto-darkening, a feature deliberately not built (618 body.dark-mode rules, 0 media queries), and the login page's id-specificity background intentionally wins there. mobile-590 surfaced a REAL overflow: 3 login slide-panels parked offscreen via position:absolute stretched scrollWidth to 885px — now position:fixed (no scroll contribution) + visibility:hidden when closed; its username locator also corrected (Moodle's guest form carries a hidden username input) |
| Hindi + 4-locale rendering | lang-parity tooling (static ✅) + MAN spot pass | ⏳ |
| Visual diff | PW visual.spec (gated `PLAYWRIGHT_VISUAL`, baselines pending) | ⏳ |

---

## Execution log

| When | Batch | Result |
|---|---|---|
| 2026-06-10 | SW-4 smoke (G2 + storefront) | ✅ login 200/12 markers, storefront 200, 0 fatals |
| 2026-06-10 | Batch 1: QA personas provisioned on 5.2 (7 accounts, role-detection verified: orgadmin→L&D, employee→Learner, public→Learner /77); persistent php-S 8081 up; L1 login spot-checks (admin /my/ 200/35 markers, employee /my/ 200/23); S1 SA-04 both halves | ✅ S1 pass (after WF-001 fix) |
| 2026-06-10 | Batch 2 (CLI exercisers on 5.2): **S4** smoke_hrms ✅ + smoke_bulk_csv ✅ ALL OK · **O3** recompletion ✅ 13/13 · **A9** course-share ✅ 23/23 · **L9** whatsapp e2e (qa_employee) ✅ ALL PASS ("pipeline ready; flip live_mode when DLT creds land") · **S2** parity_org ✅ 100% (2,883 users) + parity_tenants ✅ 100% (registry was empty on the clone — idempotent seed_tenants ran, 3 created, DORMANT per Gate B) · **S3** oneclick flag ✅ tenants 1+177 ON (state survived clone+5.2 upgrade) · **A8** translate mock ✅ PASS · **A6** aiquiz = mock covered by its PHPUnit suite; the live smoke deliberately refuses without ANTHROPIC_API_KEY + --confirm-live-anthropic-call (budget ask C) · **S9** cron_health: 0 Sentientia tasks stuck ✅; 27 stuck CORE tasks = cold clone (cron never run there) — clears with one cron pass, noted | ✅ 9 pass · 2 qualified (A6 key-gated, S9 cold-clone) |

**Batch 4 verdicts (5.2):** **C1 ✅** consumer signup E2E via HTTP POST (form render → honeypot +
sesskey validation → user created id=3428 auth=email confirmed=0-pending-mail → success page) ·
**S7 ✅** push crypto self-tests all pass (ES256/aes128gcm internals) · **T2 ✅** Live demo session
seeded (session + slides + join code issued) · **G1/G3/C5/C2 ✅** guest sweep (frontpage, signup
entry, privacy, storefront — all 200, 0 fatals, sentientia markers) · **L2/L14 ✅** authenticated
catalog + dashboard (200, 0 fatals; assistant quick-action markers present).

**Batch 5a verdicts (5.2):** **A4 ✅×2** evaluation (anonymous-question + template I/O ALL OK) ·
**A3 ✅×2** programs (cohort enrol + prerequisites ALL OK) · **L12 ✅×2** skills (course mapping +
observer ALL OK) · **S6 ◑** brand resolver 18/20 — the 2 fails are `icon_192_url`/`icon_512_url`
path checks on the 5.2 instance (PWA icon asset paths; logged as WF-005, open).


**Batch 6 verdicts (2026-06-11 overnight, 5.2):** **CRON GAUNTLET** — first-ever FULL cron passes on the
production-scale clone. Run 1 (19 tasks) crashed 3 ways → WF-006/007/008 found + fixed → run 2: 84
executed / 1 failed (→ WF-009 found + fixed) → run 3: **103 executed / 0 failed / 0 stale-capability
warnings**. `timepsent` one-time-migration flag now set; 2 vendor tasks' residual faildelay cleared after
direct re-validation. **S9 ✅** cron_health: 0 sentientia stuck · **L7 ✅** 11,415 cert issues, latest PDF
stored (625KB), cert report CLI clean · **T1/M1/M4 ✅** HTTP probes as qa_trainer/qa_manager (authenticated
dashboards 66-70KB w/ team+KPI markers; Live trainer dashboard 200/43KB; compliance report 200/86KB +
manager page 200/50KB, no access-denied). Local env hardened: MariaDB `max_allowed_packet` 1M→64M
(live + my.ini). Note: learnerscript timespent aggregation is non-idempotent by vendor design (+= on
re-run after partial failure) — affects only LS report stats on this clone, not parity metrics.

**Batch 7 verdicts (2026-06-11 early morning, 5.2):** **S5 ✅** bulk-import lifecycle smoke ALL OK
(+ PHP 8.4 str_getcsv deprecation fixed) · **L4 ◑** HTTP half — enrolled-course app-shell 200/86KB,
0 leak · **S8 ✅** role-switch HTTP round-trip — and it flushed out **WF-010 (cutover-blocking)**:
BizLMS core-adjacent files missing from the 5.2 tree, masked by php -S path-fallback (see
known-issues). WF-007 backlog reprocess clean (0 warnings/0 errors). **Testing-method lesson
recorded:** php -S serves the nearest index.php for missing files — HTTP 200 from php -S does NOT
prove the file exists; the Apache-vhost/CI tier (or a file-existence check) does.

**Sandbox kit (#402) shipped + locally rehearsed:** `migration_parity_check.php` (--baseline/--compare)
proven at single-row sensitivity (5.1-source baseline vs migrated 5.2 clone: all metrics MATCH except 4,
each attributable to this campaign's own test writes); `MIGRATION-REHEARSAL-RUNBOOK.md` = turnkey Phase-2
procedure with mandatory post-restore repairs.

## Known issues found by this campaign

| # | Workflow | Issue | Status |
|---|---|---|---|
| WF-001 | S1 (+ the whole theme redirect policy) | `core_renderer::custom_secured_redirection()` — the LXP-catalog/profile/trainer redirect policy incl. the SA-04 gate — **lost its caller in the trait-decomposition refactor and was dead code on every page** (verified: no caller in either instance; learner browsed native /course/index.php). | ✅ FIXED — invoked from the 5 standard layouts (columns1/2, course, dashboard, drawers; URL-conditional so no-op elsewhere); deployed to both instances; learner→catalog + admin→native verified live |
| WF-002 | (tooling) | `tools/_qa_provision.php` referenced pre-rename `theme_airpayux\role_detector` — broke provisioning | ✅ FIXED in the local tool (gitignored) |
| WF-005 | S6/L10 | customer_brand DB row stored PRE-RENAME icon/start URLs (`/local/airpay_core/...`) — the rename codemod fixed code, not data; PWA manifest icons would 404 | ✅ FIXED — repair CLI extended with a brand-path section (rewrites to `/local/sentientia_platform/`, purges brand cache); applied BOTH DBs; verify_brand_resolver now **20/20** on 5.2 |
| WF-003 | (CLI hygiene) | `blocks/learnerscript/classes/observer.php:153,163` assumed web context — "Undefined array key REQUEST_URI" + deprecated `parse_url(null)` on every CLI firing enrolment events (PHP 8.4) | ✅ FIXED — defensive `?? ''` on both sites (git-tracked vendor block); deployed both instances; warnings now 0 |
| WF-004 | L5/L6/M3 + every renamed plugin's cron | **{task_scheduled} still held pre-rename `\local_airpay_*` classnames** (17 orphans; only 6 sentientia rows vs 19 plugins shipping db/tasks.php) — those crons were **silently dead on BOTH instances since ADR-025** (reminders, escalations, digests, leaderboard recompute, recompletion rules, proctoring purge, cohort sync…). Root cause: the rename handover re-pointed capabilities + WS but not tasks, and Moodle only reconciles tasks on version change. | ✅ FIXED — NEW `local/sentientia_platform/cli/repair_task_registrations.php` (dry-run default, `--apply`; reconciles 19 components + purges class-gone orphans + reports other component residue). Applied on 5.2 AND 5.1: both now sentientia=23 / stale=0. **Added to the deploy packet — must run on sandbox + live post-deploy.** |

| WF-006 | S9 (vendor cron) | `block_learnerscript` `coursetimepsent` — one-time legacy migration queries `{block_ls_timestats}` (exists only on pre-2019 LS installs); crashed EVERY cron run forever, done-flag unreachable. Failing on live production today (faildelay loop). | ✅ FIXED — table_exists guard → set done-flag + return; validated direct + via full cron; both trees |
| WF-007 | S9 (vendor cron) | `userquiztimespent` — (a) per-row lookup queries `u.open_costcenterid` (doesn't exist on production schema; open_path is the convention) → task crashed; (b) **WF-007b**: quiz path set `contextinstanceid` (not a column — silently stripped) while `{block_ls_modtimestats}.activityid` is NOT NULL no-default → insert crashed once (a) was fixed; lookup compared NULL. SCORM path was correct — vendor inconsistency. | ✅ FIXED — field_exists guard (companyid/deptid fall back 0) + activityid property aligned with the scorm path; both trees. Full quiz-attempt backlog reprocessed clean post-fix (0 warnings / 0 errors) |
| WF-008 | S9 + every message-sending flow | `refresh_snapshot` cold-run blow-up (539MB, MySQL gone away): (a) **15 `{message_providers}` rows carried stale pre-rename capability strings** (`local/airpay_*`) — every `message_send()` fired a debugging backtrace per stale row; (b) `deadline_date` PHP 8 undefined-property warning per escalation; (c) local `max_allowed_packet` was 1M. **A live-backup migration inherits (a) identically.** | ✅ FIXED — repair CLI §2c (purge orphan-component provider rows) + §2d (rewrite stale capability → renamed target when it exists) applied BOTH DBs; engine property guarded; packet 64M + runbook env gate. Follow-up (recorded): queue/chunk the inline per-overdue message_send for scale |
| WF-009 | S9 (vendor cron) | `userscormtimespent` queries `{scorm_scoes_track}` — table REMOVED in Moodle 4.3 (split into scorm_attempt/scorm_element_value). Crashed every cron on 4.3+ schemas incl. live. LS SCORM-time reports can't update on modern schemas until the vendor query is ported. | ✅ FIXED (guard) — table_exists → mtrace skip + return true; vendor port = recorded follow-up |

| WF-010 | S8 + every BizLMS link to /my/dashboard.php | **CUTOVER-BLOCKING.** The SW-4 5.2 overlay covered plugins/theme but missed **BizLMS files inside CORE dirs**: `/my/dashboard.php` (redirect shim — the canonical BizLMS dashboard URL), `/my/switchrole.php` (role-switch endpoint, which was ALSO never canonicalised into git — webroot-only, same class as P0-1), `/my/templates/dropdown.mustache`, root `.htaccess` (branded error pages). **php -S masked it**: its path-fallback serves `/my/index.php` for ANY missing file under /my/ (garbage URLs proved it — all 200/68KB), so every probe "passed"; production Apache would hard-404. Tree-sweep found no further product files missing (residue: `blocks$name` junk dir on 5.1, `.rnd`, core-evolution diffs). | ✅ FIXED — switchrole.php canonicalised into git `my/`; all 4 files deployed to the 5.2 tree (+ shim harmonised on 5.1); `overlay-airpay-customs.ps1` gained a core-adjacent section so redeploys carry them; S8 round-trip then passed live |

| WF-011 | L8 (seed tooling) | `seed_badges.php` required `badges/lib/awardlib.php` — removed in Moodle 5.2 (badges refactor); seed crashed on 5.2. No product code references it (grep-verified). | ✅ FIXED — file_exists guard (calls no awardlib function); dry-run green on 5.2 |
| WF-012 | M5 (test fixture) | `supervisor_scope_test::test_non_siteadmin_only_sees_own_tenant` errored — the Bug-9b WS hardening (correctly) requires `moodle/user:viewdetails`, but the test seeded a bare user. 6/7 tests passed. | ✅ FIXED — three fixture gaps closed: (a) the real WS capability is local/sentientia_users:view (first patch granted moodle/user:viewdetails — same display string, wrong capability); (b) grant must precede setUser() (access caches per-login); (c) vanilla PHPUnit schema lacks the BizLMS open_* columns the suite exists to protect — setUpBeforeClass now self-provisions them via DDL. 7/7 OK (11 assertions) on 5.1 PHPUnit |
| WF-013 | O4 + deploy integrity | `mod/quiz/accessrule/sentientia_proctoring` was **webroot-only — never canonicalised into git** (same class as P0-1/WF-010; only the `local/` sibling was in repo). Also its `rule_test.php` lacked the `require_once(rule.php)` that core loads directly (accessrule classes are NOT classmap-autoloaded) → 9/9 class-not-found. | ✅ FIXED — plugin canonicalised into git `mod/quiz/accessrule/`; test gains the core-pattern require_once; 9/9 OK on 5.1 PHPUnit (b9) |
| WF-014 | Fresh-install integrity (PHPUnit init, sandbox installs) | `blocks/airpay_compliance` was pre-rename residue still in git + both webroots — same block TITLE as `block_sentientia_compliance`, and Moodle aborts a FRESH INSTALL on duplicate block titles (PHPUnit init died; any clean sandbox install would too). Invisible on upgraded sites — only fresh installs walk every block dir. | ✅ FIXED — removed from the git index; webroot copies moved aside to `_stale-blocks-20260611\` (move-aside pattern, no deletes); PHPUnit init then completed |
| WF-015 | L1/render gate — every page's assets under FastCGI | **Moodle 5.2 CORE bug under php-cgi (upstream-report candidate).** Two fatal classes: (a) pure ABORT_AFTER_CONFIG scripts (javascript.php/styles.php/yui_combo.php) die at shutdown — `core\shutdown_manager` calls `ini_get_bool()` which only `lib/setuplib.php` declares and minimal bootstrap never loads; (b) cancel-abort scripts (`r.php` ESM loader, `theme/font.php`) re-enter full setup after the polyfill armed — setuplib's UNGUARDED declaration collides — redeclare fatal — fonts + React autoinit 500 on every page. Only the real-browser tier caught it (HTTP probes never pulled cold assets; php -S masked 404 classes but this was FCGI-only). | ✅ FIXED (two parts, BOTH required) — conditional `ABORT_AFTER_CONFIG` polyfill in instance config.php + SENTIENTIA-CORE-MOD function_exists guard in `lib/setuplib.php`; all 4 URL classes 200; recipe in vhost template; full record `docs/core-mods/2026-06-11-setuplib-ini-get-bool-guard.md` |
| WF-016 | L1 (learner course-view) + every kept BizLMS vendor block | **Post-rename residue, cutover-relevant.** Vendor blocks (learnerscript, userdashboard, reportdashboard, achievements, my_event_calendar, masterinfo, quick_navigation) still reference the retired `local_costcenter` plugin at 50+ sites: `\local_costcenter\lib\accesslib::get_module_context/get_costcenter_info/get_costcenter_path_field_concatsql` + `\local_costcenter\lib::get_userdate`. The plugin exists on NO tree (and won't on sandbox/live — our deploy replaces the BizLMS plugin set), so each call is a class-not-found fatal when its path executes. First render-blocking hit: learner opens /course/view.php — theme quickaccess_links — `block_learnerscript_leftmenunode()` — 500. Only the real-browser tier walked into it. | ✅ FIXED — anti-corruption alias shim in local_sentientia_org 1.5.0: `after_config` hook class_aliases `local_costcenter\lib\accesslib` —> `local_sentientia_org\accesslib` (the namespace-change-only fork — all needed methods present) and `local_costcenter\lib` —> new `compat\bizlms_lib` (byte-faithful get_userdate port from the BizLMS source backup). Zero vendor-file edits. Verified both instances (CLI alias check + learner course-view 200). **Follow-up recorded:** plain fn `local_costcenter_get_costcenter_path()` (2 learnerscript AJAX filter sites) needs retired `\->useraccess` machinery — not aliased; those filters degrade with a clear error, port if LS org-filters are needed. `{local_costcenter}` TABLE SQL in LS reports still resolves on migrated DBs (table ships in the BizLMS data), N/A on fresh installs |
| WF-017 | L1 course-view + any drawer interaction | Theme fork's `drawers.js`/`drawer.js` (epsilon-era) still call `M.util.set_user_preference()` — REMOVED from core — JS TypeError on learner/admin course-view the moment WF-016 stopped masking it (drawer auto-state writes its preference on load there). Browser-tier-only signal: HTTP probes can't see JS fatals. | ✅ FIXED — src migrated to `core_user/repository` `setUserPreference` (upstream Boost's own migration); min bundles patched via the documented re-sed practice (grunt's eslint gate fails on tree-wide CRLF — recorded follow-up: proper `grunt amd` rebuild in CI); node --check clean; both trees; ADMIN + LEARNER course-view then passed |

**Batch 3 verdicts (5.2, seeded):** **L3 ✅** enrol_now (free-only check, enrols, idempotent — args are
`(courseid, userid)`); **L5 ✅** reminder cron fired bucket +1 → remind_sent row + employee notification;
**M3 ✅** overdue cron fired bucket −7 → remind_sent row + MANAGER notification (supervisor chain works);
**O2 ✅** demonstrated — both nudges around the deadline = one query on remind_sent (signed buckets);
**L6** N/A-no-fixture on the clone (0 exam rows; same cron harness as L5, code paths shared).
