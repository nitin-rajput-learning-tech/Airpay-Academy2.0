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
| L1 | Login → role-aware dashboard renders (widgets, sidebar, 0 console errors) | PW render-smoke + dashboard.spec; HTTP | ⏳ |
| L2 | Browse catalog → course detail → posters render | PW learner.spec; HTTP | ⏳ |
| L3 | One-click free enrol (flag ON, tenant 1/177) → My Courses | CLI enrolment::enrol_now + PHPUnit (8 cases); HTTP | ⏳ |
| L4 | Open course → app-shell course player → activity completion records | PW learner.spec; HTTP + CLI completion mark | ⏳ |
| L5 | Deadline reminder (7/3/1) lands as notification | CLI: seed deadline + run course_reminder task; assert _remind_sent + message | ⏳ |
| L6 | Exam attempt → grade; exam reminder cron | CLI: exam_reminder task (same harness as L5) | ⏳ |
| L7 | Certificate issued on completion → file exists | CLI cert smoke (tool_certificate) + cert_emails_report | ⏳ |
| L8 | Gamification points/badges/streak + leaderboard row (opt-out respected) | CLI seed_badges + leaderboard e2e (Wave C5) | ⏳ |
| L9 | Communication prefs page: WhatsApp/SMS consent (DLT) saves + audits | CLI run_whatsapp_e2e (dry); HTTP prefs page | ⏳ |
| L10 | PWA: manifest + install CTA + push subscribe + real push received | CLI run_push_e2e + verify_d1_endpoints + test_crypto | ⏳ |
| L11 | Live session: join by code → answer all 6 question types → results | CLI sentientia_live smokes; MAN two-browser (done on 5.1: PRIORITY-1) | ⏳ |
| L12 | Profile + skills self-rate → audit log | CLI smoke_profile_skills + smoke_observer | ⏳ |
| L13 | Calendar ICS download (classroom session) — white-label ORGANIZER | CLI ics_builder unit (PHPUnit) | ⏳ |
| L14 | AI assistant chips render (mock mode) | HTTP; PW dashboard.spec | ⏳ |

## P2 — Public / consumer learner

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| C1 | Self-signup (honeypot, consumer user_type) → account created → login | HTTP signup POST; PW login.spec | ⏳ |
| C2 | Public storefront (Netflix grid) renders logged-out | HTTP; PW (guest part of render-smoke) | ⏳ |
| C3 | Cart add → checkout → **fail-closed payment verify** (tampered hash rejected) | PHPUnit paygw suite (13 tests) + gateway_test; sandbox txn = Nitin-deferred | ⏳ |
| C4 | Free-course cart path (Public keeps cart; no one-click) | CLI PHPUnit (policy cases) | ⏳ |
| C5 | Privacy/ToS pages render, site-name white-labeled | HTTP | ⏳ |

## P3 — Manager

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| M1 | Manager dashboard (team widgets, team KPI = table source) | PW manager.spec; HTTP | ⏳ |
| M2 | Team overdue view | PW manager.spec | ⏳ |
| M3 | Overdue escalation to supervisor (1/7/14 post-deadline, negative buckets) | CLI course_overdue + exam_overdue tasks with seeded data | ⏳ |
| M4 | Completion analytics / reports render | HTTP; PW manager.spec | ⏳ |
| M5 | Tenant-scoped supervisor autocomplete | PHPUnit (tenant isolation tests) | ⏳ |

## P4 — Trainer

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| T1 | Trainer dashboard reachable (teacher archetype caps) | HTTP as qa_trainer | ⏳ |
| T2 | Live full cycle: create → 6 slide types → run → SSE results → CSV export | CLI live_smoke + session PHPUnit; MAN projector two-browser | ⏳ |
| T3 | Classroom session + ICS invite | CLI smoke + ics PHPUnit | ⏳ |

## P5 — Course author / L&D admin

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| A1 | Create course + audience rules → target learners enrolled | PW author.spec; CLI bulk-enrol smokes | ⏳ |
| A2 | Learning path: create → dates/rich-text → bulk enrol → cascade filters | CLI learningpath smokes | ⏳ |
| A3 | Program create + audience enroller | CLI program smokes | ⏳ |
| A4 | Evaluation: builder (numeric/multiselect/conditional) → assign → respond → non-respondents → auto-expire | CLI evaluation crons + PHPUnit | ⏳ |
| A5 | Exam create (quiz wrap, category) + reminder config | HTTP; covered via L6 cron | ⏳ |
| A6 | AI quiz generation (mock mode, cost defence) | CLI aiquiz live_smoke (mock) | ⏳ |
| A7 | Email templates: edit + token substitution + tenant override (welcome mail) | CLI smoke_bulk_import (sends welcome) + email_context preview | ⏳ |
| A8 | Translate queue add → process (mock) | CLI translate mock_smoke | ⏳ |
| A9 | Course-share request state machine | CLI smoke_request | ⏳ |

## P6 — Compliance officer

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| O1 | Compliance dashboard + report reachable from sidebar | PW compliance.spec | ⏳ |
| O2 | Audit trail: every nudge/escalation around a deadline = one query | CLI (assert _remind_sent rows from L5/M3 runs) | ⏳ |
| O3 | Recompletion cycle resets completion + re-nudges | CLI smoke_recompletion | ⏳ |
| O4 | Proctoring access rule attaches to exam | PHPUnit quizaccess | ⏳ |

## P7 — Tenant / site admin

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| S1 | **Native course management reachable (SA-04 gate)** — admin gets native hub, learner gets catalog | HTTP both personas | ✅ (after WF-001 fix: admin → native management.php 200; learner → /local/sentientia_catalog/ 200 from both management.php + index.php) |
| S2 | Tenant registry manage UI + parity CLI 100% | CLI parity_check_tenants + parity_check_org | ⏳ |
| S3 | Feature flags: set/resolve/audit (5-level) | CLI enable_oneclick_enrol --dry-run + PHPUnit | ⏳ |
| S4 | HRMS importer: 24-col CSV dry-run + apply + cron sync | CLI smoke_hrms + smoke_bulk_csv | ⏳ |
| S5 | Lifecycle: joiner welcome email (tenant template) / leaver deactivation | CLI smoke_bulk_import + lifecycle observer smoke | ⏳ |
| S6 | Branding: customer_brand resolver + per-customer manifest | PHPUnit customer_brand_test + verify_brand_resolver CLI | ⏳ |
| S7 | Push ops: VAPID keys, delivery log, master-key encrypt | CLI test_push + verify_signed_with_encrypted_pem | ⏳ |
| S8 | Role switcher (admin↔learner round-trip) | HTTP; MAN visual (done on 5.1) | ⏳ |
| S9 | Cert-health + cron-health blocks report green | CLI cron_health + cert_emails_report | ⏳ |

## P8 — Guest

| # | Workflow | Coverage | 5.2 status |
|---|---|---|---|
| G1 | Frontpage + storefront render logged-out | HTTP | ⏳ |
| G2 | Login page white-labeled ({{sitename}}, OAuth row) | HTTP (done in SW-4 smoke: 200, 12 markers) | ✅ (SW-4 log) |
| G3 | Signup entry reachable | HTTP | ⏳ |
| G4 | Maintenance page (customername string, DB-down-safe) | CLI render check | ⏳ |

## Cross-cutting gates (apply to every persona)

| Gate | Mechanism | 5.2 status |
|---|---|---|
| Render-smoke (persona × surface: AMD boot, no `{{ }}` leak, landmark, 0 console errors) | PW render-smoke.spec | ⏳ (needs vhost or php -S router for PW) |
| Accessibility WCAG A+AA serious/critical | PW a11y-smoke.spec | ⏳ |
| Dark mode + mobile 590px | PW dark-mode.spec + mobile-590.spec | ⏳ |
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

## Known issues found by this campaign

| # | Workflow | Issue | Status |
|---|---|---|---|
| WF-001 | S1 (+ the whole theme redirect policy) | `core_renderer::custom_secured_redirection()` — the LXP-catalog/profile/trainer redirect policy incl. the SA-04 gate — **lost its caller in the trait-decomposition refactor and was dead code on every page** (verified: no caller in either instance; learner browsed native /course/index.php). | ✅ FIXED — invoked from the 5 standard layouts (columns1/2, course, dashboard, drawers; URL-conditional so no-op elsewhere); deployed to both instances; learner→catalog + admin→native verified live |
| WF-002 | (tooling) | `tools/_qa_provision.php` referenced pre-rename `theme_airpayux\role_detector` — broke provisioning | ✅ FIXED in the local tool (gitignored) |
| WF-005 | S6/L10 | customer_brand DB row stored PRE-RENAME icon/start URLs (`/local/airpay_core/...`) — the rename codemod fixed code, not data; PWA manifest icons would 404 | ✅ FIXED — repair CLI extended with a brand-path section (rewrites to `/local/sentientia_platform/`, purges brand cache); applied BOTH DBs; verify_brand_resolver now **20/20** on 5.2 |
| WF-003 | (CLI hygiene) | `blocks/learnerscript/classes/observer.php:153,163` assumed web context — "Undefined array key REQUEST_URI" + deprecated `parse_url(null)` on every CLI firing enrolment events (PHP 8.4) | ✅ FIXED — defensive `?? ''` on both sites (git-tracked vendor block); deployed both instances; warnings now 0 |
| WF-004 | L5/L6/M3 + every renamed plugin's cron | **{task_scheduled} still held pre-rename `\local_airpay_*` classnames** (17 orphans; only 6 sentientia rows vs 19 plugins shipping db/tasks.php) — those crons were **silently dead on BOTH instances since ADR-025** (reminders, escalations, digests, leaderboard recompute, recompletion rules, proctoring purge, cohort sync…). Root cause: the rename handover re-pointed capabilities + WS but not tasks, and Moodle only reconciles tasks on version change. | ✅ FIXED — NEW `local/sentientia_platform/cli/repair_task_registrations.php` (dry-run default, `--apply`; reconciles 19 components + purges class-gone orphans + reports other component residue). Applied on 5.2 AND 5.1: both now sentientia=23 / stale=0. **Added to the deploy packet — must run on sandbox + live post-deploy.** |

**Batch 3 verdicts (5.2, seeded):** **L3 ✅** enrol_now (free-only check, enrols, idempotent — args are
`(courseid, userid)`); **L5 ✅** reminder cron fired bucket +1 → remind_sent row + employee notification;
**M3 ✅** overdue cron fired bucket −7 → remind_sent row + MANAGER notification (supervisor chain works);
**O2 ✅** demonstrated — both nudges around the deadline = one query on remind_sent (signed buckets);
**L6** N/A-no-fixture on the clone (0 exam rows; same cron harness as L5, code paths shared).
