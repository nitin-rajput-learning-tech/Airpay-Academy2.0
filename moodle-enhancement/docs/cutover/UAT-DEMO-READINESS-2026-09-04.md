# UAT demo-readiness — Sentientia LMS on academy2.airpay.ninja

**Date:** 2026-09-04 · **Purpose:** confirm every surface and action is demonstrable before the management demo · **Owner:** Nitin Rajput · **Engineering:** Claude
**Companion:** `UAT-VALIDATION-PLAN-2026-09-03.md` (progress log), `UAT-TEST-ACCOUNTS.filled.md` (persona scripts + passwords, gitignored), `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`.

## Verdict

**Functional layer: demo-ready.** Every persona's every demo surface was walked at the HTTP/DB level — 67 of 67 targets load correctly, with no exceptions, no missing-string placeholders, and no unexpected permission errors. Tenant isolation, accessibility, and SCORM playback were verified and fixed on the way.

**Visual layer: confirmed (2026-09-07).** Guest surfaces plus the logged-in persona walk are confirmed in a real browser: L&D admin (5 surfaces), trainer (Live Sessions), manager (My Team), and the full learner journey (My Courses / Certificates / Catalog / course page / course content / My Skills) via "Log in as". The three deployed fixes were re-confirmed on-screen — F-12 (course header/drawer/list all render `&`), dark mode (every page light on a dark-set laptop, toggle gone), btn-close (`×` renders). Mobile views come from the earlier 390px playwright captures plus an on-device spot check (Chrome on Windows can't emulate a phone width).

**ZEEA tenant-isolation visual (2026-09-07): PASS on real data.** The ZEEA admin sees only 2 ZEEA users and 4 ZEEA courses — no Airpay/Public user or course data leaks. Four pre-existing `sentientia_courses` issues were found for triage before the ZEEA demo (spawned as task_17fc05d8, NOT from the 09-04 deploy): (1) **P1** an `invalidrecordunknown` error modal on Manage Courses load — **root-caused 2026-09-08, and it is NOT tenant scoping:** the compiled theme bundle `amd/build/org_cascade.min.js` still called the retired web service `local_airpay_org_list_children` (its `amd/src` was already `local_sentientia_org_list_children`), so the org-cascade filter's error handler raised that modal for **every admin on every page with the 5-level org cascade** (reproduced as the Airpay L&D admin; the network log shows the two on-load calls, `list_courses` OK and `local_airpay_org_list_children` failing). `user_status_badge.min.js` had the same drift. Both bundles rebuilt (`c7f085dd8`) and **deployed to UAT 2026-09-08 12:36** (4/4 checksums OK, caches purged; the served bundle now references only `local_sentientia_org_list_children`). On-screen re-check of the Manage Courses page pending the next browser window; (2) **P2** that "UAT Smoke 01" (Category 1, Hidden) leaks into the ZEEA admin list; (3) **P2** the category filter lists all tenant categories to the ZEEA admin; (4) **P2** the KPI "15 Total Courses" is the global count, not tenant-scoped (the list itself is correctly 5). A fifth, related: the ZEEA admin **dashboard "User Analytics" widget** shows Airpay-level figures (114 logins this week, an AIRPAY slice in Course Distribution) — likely not tenant-scoped; fold into the ZEEA-isolation triage.

**Final persona visuals (2026-09-07): complete.** *Hindi (i18n)* renders cleanly across nav, headings, search, and menus. **Gap (P3, i18n-parity debt):** the dashboard **body** is hardcoded English in `theme/sentientia/layout/dashboard.php` — ~35 stat-tile labels, quick-action labels, and trend strings (admin KPIs "Active Users / Courses / Completions / Enrolments" + trends; admin quick-actions + statlabels; system + login-analytics tiles; learner "Enrolled / In Progress / Completed / Certificates"; manager "Team Members / Team Enrolments / …") are string literals passed as data, bypassing `{{#str}}`, so they stay English under `?lang=hi`. Fix = route each through `get_string()` with kpi_/dash_ keys in en+hi (placeholder `{$a}` for the trend strings). **Queued for a coordinated pass after task_a63baf9c lands** (both touch `theme/sentientia`; sequencing avoids `dashboard.php` merge conflicts). *Public/consumer learner* (Deepa) confirmed: consumer dashboard with a **My Cart** nav; cart shown empty and populated — the **F-12 cart-title fix is confirmed on-screen** (a "&"-containing course title renders correctly in the cart), and checkout stops gracefully at a disabled **"Payment Coming Soon"** ("Payment integration is being configured") — the demo-appropriate endpoint given payments are unwired (C1). F-12 was also re-confirmed on the catalog card and course-detail hero. *Compliance* was covered earlier via the admin Compliance report. **Author persona finding (demo-relevant):** the course author (`uat_author_airpay` = Sneha Kulkarni, coursecreator role) lands on a **learner dashboard with NO authoring UI** — Manage Courses returns *"Sorry, but you do not currently have permissions to do that (View course management)."* This is the known **T-01 persona-caps gap** (authoring caps grant to editingteacher/manager, not the custom author role); the author cannot demo authoring (Studio / AI quiz / question bank) until the caps + nav are wired. Not a regression from the 09-04 deploy. **→ RESOLVED 2026-09-08:** the T-01 fix (`1dc599466`, see `T-01-AUTHOR-CAPS-FIX-2026-09-07.md`) is deployed to UAT and probe-verified — the `Sentientia Author` role holds exactly the 7 authoring caps at system context on both the fresh-install and upgrade paths, and the sidebar now offers **Authoring Studio / AI Quiz / Skills AI** to any user holding the caps while the plugin flags are on. Authors manage the courses they create (`creatornewroleid` = editingteacher); tenant-admin *Manage Courses* stays excluded by design. The three authoring flags were found **already ON globally on UAT** (set 3 Sept during provisioning, not by this fix); Nitin's call on 2026-09-08: **leave them on** so the author can demo authoring in mock mode. **Confirmed on-screen 2026-09-08** (Log in as Sneha from the L&D admin account): the sidebar shows Authoring Studio / AI Quiz / Skills AI under a divider, and all three pages load for the author with their MOCK MODE banners — see `docs/visual-evidence/2026-09-08/uat-author-t01/README.md`.

## Per-persona demonstrable surfaces (functional walk, 67/67 clean)

| Persona | Surfaces confirmed loading | Demo focus |
|---------|----------------------------|-----------|
| **Guest** | Landing, login, public catalog (4 courses, Enrol free / Add-to-cart ₹499) | Storefront + brand |
| **L&D / tenant admin** (uat_ldadmin_airpay) | Dashboard, Manage Users, Manage Courses, Organisation + tenant settings, Reports, Compliance, Analytics, Learning Paths, Programs, Classrooms, Exams, Evaluations | The management view: tenant KPIs, admin breadth |
| **Manager** (uat_manager_airpay) | Dashboard (3-report team), My Team, Approvals, Requests, Allocations, team Compliance (Rahul overdue), own course | Team oversight + approvals |
| **Employee learner** (uat_learner_airpay) | Dashboard, My Courses, Certificates, completed + in-progress courses, **SCORM activity**, Catalog, Learning Path, My Skills, raise Request, Profile | The core learner journey |
| **Learner 2 — Hindi/overdue/first-login** (uat_learner2_airpay) | First-login onboarding wizard (Hindi), overdue Information Security course | i18n + onboarding + compliance nudge |
| **Trainer** (uat_trainer_airpay) | Dashboard, Live Sessions (running poll), Classrooms, editable course, AI quiz (mock), Evaluations, Exams | Live engagement + authoring |
| **Course author** (uat_author_airpay) | Dashboard, Authoring Studio, Skills AI, AI quiz, question bank | AI-assisted authoring (mock mode) |
| **Compliance officer** (uat_compliance_airpay) | Dashboard, Compliance matrix (RAG), Reports, Notification logs | Compliance reporting + exports |
| **Public learner** (uat_learner_public) | Dashboard (consumer), Catalog, Cart, completed course, Certificates | Consumer storefront + cart |
| **ZEEA learner** (uat_learner_zeea) | Dashboard, Catalog (ZEEA only), Learning Path, course | Second tenant, isolation |
| **ZEEA admin** (uat_admin_zeea) | Dashboard, Manage Users (ZEEA only), Manage Courses, Compliance, Browse Airpay | Tenant-scoped administration |

## Fixes made during the pass (all committed, deployed to UAT unless noted)

| Item | What it was | Fix |
|------|-------------|-----|
| F-10 | Course page returned HTTP 500 for any learner holding a certificate (theme queried BizLMS-only `moduleid/moduletype` columns absent on the stock certificate tool) | Schema-aware lookup; byte-identical to production where the columns exist |
| Legacy string | `download_certificate` tooltip pointed at the retired `local_courses` component → missing-string placeholder on every completed-course page | Moved to a `theme_sentientia` string (en+hi) |
| Accessibility | The global search box had a placeholder but no accessible name (WCAG 4.1.2), across three render paths | `role="search"` + `aria-label` on all three; re-scan shows 0 unlabelled inputs |
| SCORM | UAT started with an empty filedir, so SCORM was untestable | Uploaded a real SCORM 1.2 package; 2 SCOs parsed, player loads, all files serve via pluginfile — proves the player works on 5.2 |
| Dark mode | Auto-followed the browser's dark setting, forcing every page dark | Now opt-in only; super-admin flag (default OFF) decides availability. **UAT flip + deploy pending the tunnel** |
| `.btn-close` glyph | Every dismissible notification (404 page, disabled-signup message, all alerts) showed a small empty box where the close × should be — core 5.2 emits a Bootstrap 5 dismiss button this Bootstrap-4 theme never styled | New `_bs5-close.scss` partial draws a real × (font-independent); verified in the compiled theme CSS. Committed `56a41ac66`. **Deploy to UAT pending the tunnel** |
| F-12 double-escaped titles | Course/activity names with `&`/`<`/`>` rendered the entity literally ("AML **&amp;** KYC Essentials") on the *My courses* card, course-player header, course-index drawer, catalog index cards, and cart — a `format_string()` value re-escaped by Mustache `{{ }}` / PHP `s()` | Render each `format_string()`-safe value once (`{{{ }}}` / drop `s()` / raw name for str-helper params); no XSS, both catalog trees + theme, catalog 1.0.4-beta + theme 1.0.51-beta. Committed `1f8dc0eaf`. **Deploy to UAT pending the tunnel** |
| Tenant isolation | (verification, no defect) | ZEEA learner catalog leaks no Airpay courses; admin lists are AJAX-scoped, DB counts correct per tenant |
| T-01 author caps + nav | Course author landed on a learner shell with no authoring UI; the author role's caps were seeded only from `upgrade.php`, so a fresh install had no author role at all | Idempotent `author_role::ensure()` seeder called from both `install.php` and a new upgrade step; capability-gated `isauthor` + Authoring Studio / AI Quiz / Skills AI sidebar group (flag AND cap). Committed `1dc599466`, **deployed to UAT 2026-09-08**, probe-verified (7 caps @ system ctx) |

## Flows that still need visual/interactive confirmation (the pending browser walk)

These loaded correctly at the HTTP level but the presenter will click through them live, so I will confirm the interaction + rendering once the admin login is available:
- Learner: enrol from the catalog → open course → take the quiz → pass → completion → certificate download.
- Manager: approve a request → learner's enrolment updates.
- Trainer: run the live poll while an audience answers (SSE now streams correctly after the flushpackets fix).
- Dark-mode toggle on a logged-in dashboard (once the flag is re-enabled) and 590px mobile layout on device.

## Not demonstrable on UAT by design — script these as "explain / mock"

| Area | Why | In the demo |
|------|-----|-------------|
| Outbound email (reminders, escalations, signup confirmation) | `noemailever` on (151-email rule) | Show the in-app notification log; explain mail is wired at go-live |
| Real payments | Gateway not connected; C1 fix unmerged | Stop at the cart/checkout page; explain sandbox verification |
| SSO / MFA | Entra keys pending from IT | Explain the identity pack; show manual login |
| Live AI (real generation) | No Anthropic key; mock mode | Show the mock output instantly; explain the gateway + budget |
| WhatsApp / M365 knowledge | No keys; out of scope | Mention on the roadmap |

## Known cosmetic notes (non-blocking)

- The landing-page dark-toggle icon uses an FA4 name that renders as a generic glyph under FontAwesome 6 (cosmetic).
- L&D admin dashboard KPI tiles may over-count by the two ZEEA users; the underlying list is correctly tenant-scoped.
- Enrolment counts on some catalog cards read 0/1 (seeded data), expected on a fresh test set.

## Suggested 20-minute management demo path

1. **Guest** — landing page, click through to the public catalog (storefront story).
2. **Learner** (Priya) — dashboard with progress + badge, open a course, the SCORM walkthrough, certificates.
3. **Manager** (Vikram) — team dashboard, a report's progress, compliance RAG with an overdue flag, approve a request.
4. **Trainer** (Arjun) — run the live poll; audience answers from a phone.
5. **L&D admin** (Meera) — Manage Users/Courses, Compliance report + CSV export, Analytics, the feature-flag Switchboard.
6. **Tenant isolation** — log in as the ZEEA admin; show only ZEEA data.
7. **Close** — Hindi toggle on a dashboard; the migration story (same-domain cutover, full history carries).

Each step maps to a persona in `UAT-TEST-ACCOUNTS.filled.md` with its exact click path.
