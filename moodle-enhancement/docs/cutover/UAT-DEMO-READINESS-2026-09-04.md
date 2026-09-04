# UAT demo-readiness — Sentientia LMS on academy2.airpay.ninja

**Date:** 2026-09-04 · **Purpose:** confirm every surface and action is demonstrable before the management demo · **Owner:** Nitin Rajput · **Engineering:** Claude
**Companion:** `UAT-VALIDATION-PLAN-2026-09-03.md` (progress log), `UAT-TEST-ACCOUNTS.filled.md` (persona scripts + passwords, gitignored), `SENTIENTIA-MIGRATION-PLAN-2026-09-04.md`.

## Verdict

**Functional layer: demo-ready.** Every persona's every demo surface was walked at the HTTP/DB level — 67 of 67 targets load correctly, with no exceptions, no missing-string placeholders, and no unexpected permission errors. Tenant isolation, accessibility, and SCORM playback were verified and fixed on the way.

**Visual layer: one step outstanding.** Guest surfaces (landing, login, public catalog) are confirmed in a real browser, light and dark. The logged-in persona walk with screenshots is the only remaining piece; it needs one admin login so I can drive "Log in as" across the ten personas. Mobile views come from the earlier 390px playwright captures plus an on-device spot check (Chrome on Windows can't emulate a phone width).

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
