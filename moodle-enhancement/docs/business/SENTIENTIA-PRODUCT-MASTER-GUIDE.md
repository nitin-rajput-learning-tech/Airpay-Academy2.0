# SENTIENTIA LMS — Master Product Guide

**Audience:** End consumers (the people who use Sentientia every day) and Marketing (the people who sell it)
**Author:** Nitin Rajput, Head of L&D, Airpay Payment Services · **Date:** 2026-06-11 · **Version:** 1.0
**Status:** Single source of truth for product capability. Every claim in this document traces to the engineering record (PROJECT-STATE.md, ADRs, state cards, plugin code, test matrices). Open business decisions are marked **[NITIN DECIDES]**.

---

## How to use this guide

| You are… | Start at… |
|---|---|
| A prospect or marketing teammate who needs the pitch | §1, §2, §10 (Marketing kit) |
| A learner, manager, or admin wondering "what can it do for me?" | §3 (Who uses it), then §4 (Feature catalog) |
| Evaluating a specific capability (e.g. "does it do WhatsApp reminders?") | §4 — features are grouped by domain, every plugin has the same scannable sub-headings |
| A demo presenter | §6 (Sentientia Live showcase), §7 (white-label story), §10 (objection handling) |
| Checking what is proven vs. planned | §2 (honest numbers) and §9 (Trust & quality) |

**Reading conventions used throughout:**

- **Feature flag** — every new Sentientia feature ships behind a switch that defaults OFF and can be turned on per customer and per tenant. "Flag-gated" means a customer's deployment shows exactly the surface they bought.
- **Tenant** — an isolated business unit on one Sentientia instance (customer-zero runs three: Airpay internal, Public/consumer, ZEEA Tanzania).
- **Customer-zero** — Airpay Academy (airpay.academy), the first production deployment, where every feature is hardened at real enterprise scale before being offered to anyone else.
- **[NITIN DECIDES]** — an open business decision (pricing, naming, hosting); deliberately not invented in this document.

---

## Table of contents

1. [What is Sentientia LMS](#1-what-is-sentientia-lms)
2. [Platform at a glance](#2-platform-at-a-glance)
3. [Who uses it — personas and journeys](#3-who-uses-it--personas-and-journeys)
4. [Feature catalog by domain](#4-feature-catalog-by-domain)
   - 4.1 Learning Core · 4.2 Engagement & Motivation · 4.3 Commerce & Discovery · 4.4 Intelligence & AI · 4.5 Communications & Reach · 4.6 People, Org & Governance · 4.7 Integrations · 4.8 Dashboard blocks
5. [The experience layer — design, accessibility, mobile](#5-the-experience-layer)
6. [Sentientia Live — built-in live engagement](#6-sentientia-live--the-built-in-live-engagement-engine)
7. [Multi-tenant and white-label architecture, in business terms](#7-multi-tenant--white-label-architecture-in-business-terms)
8. [The content pipeline — SOP to SCORM](#8-the-content-pipeline--sop-to-scorm)
9. [Trust & quality — how we know it works](#9-trust--quality)
10. [Marketing kit — pitch, differentiators, FAQ, objection handling](#10-marketing-kit)
11. [Appendix — full plugin index and glossary](#11-appendix)

---

# 1. What is Sentientia LMS

## The elevator pitch

**Sentientia LMS is a white-label, enterprise-grade learning platform that was not built in a lab — it was built inside a live financial-services company and hardened against 3,500+ real learners before being offered to anyone else.**

It is an LMS (learning management system), an LXP (learning experience platform), and a compliance engine in one product: structured courses, learning paths and certification programs; gamified engagement with real-time leaderboards; a Mentimeter-class live classroom tool built in; AI quiz generation and recommendations; a payment-enabled public course storefront; and reminders that reach learners on email, web push, WhatsApp and SMS — all multi-tenant, all brandable per customer, all switchable per feature.

## Positioning

Sentientia sits between two worlds that enterprises usually have to choose between:

- **Generic open-source LMS** (vanilla Moodle and its skins) — cheap to start, but single-company assumptions everywhere, dated UX, and every enterprise feature (HRMS sync, escalations, white-label, multi-channel nudges) is a bespoke project.
- **Big-ticket corporate LXP SaaS** — polished, but rented: per-seat fees forever, your data on someone else's roadmap, and "customization" means waiting for their backlog.

Sentientia is the third option: **an open-core enterprise platform you can own or have hosted, with the enterprise layer already built** — 40+ product plugins, a four-gate automated quality system, per-customer branding, and a proven multi-tenant architecture. Because the base is Moodle (the world's most widely deployed open-source LMS), the content standards (SCORM), the plugin ecosystem, and the talent pool come for free; because the Sentientia layer exists, none of the enterprise pain has to be rebuilt.

## The customer-zero proof story

This is the part competitors cannot copy with a brochure.

Eighteen months ago, Airpay Payment Services ran its L&D on a heavily customised, vendor-locked Moodle deployment. Instead of paying to patch it indefinitely, the team forked it, absorbed and de-branded the vendor layer, and rebuilt it as a product — with **Airpay Academy as the product's first customer ("customer-zero")**.

What that means in practice:

- **Every feature is dogfooded at real scale first.** Compliance training (POSH, AML, IT security) with deadlines, automated learner reminders, and automatic manager escalations run daily for 3,500+ learners across 3 business units — Airpay internal, a Public/consumer tenant, and ZEEA Tanzania (Swahili).
- **The hard problems are already solved** because customer-zero hit them: multi-tenant data isolation, HRMS-driven joiner/leaver lifecycle, India-realistic delivery (DLT-registered SMS/WhatsApp templates with consent capture for learners without reliable data connectivity), five languages with enforced translation parity, and a payment gateway integration (Airpay's own — the company dogfoods its core product inside its LMS).
- **The quality system was born from real incidents.** Sentientia ships with a four-gate automated quality pipeline (static scans → render checks per persona → accessibility checks → an honest coverage matrix) because "we ran visual audits and still shipped UI bugs" was a real lesson, recorded in the engineering log.
- **A second customer is a configuration exercise, not a project.** A completed June-2026 white-label audit verified that every rendered customer-name string resolves from configuration. Change the site name, logo, and colours — the entire product re-skins itself across login, emails, calendar invites, WhatsApp consent text, and push notifications, in five languages, with **zero code changes**.

## What Sentientia is not

Honesty is a feature of this guide:

- It is not a closed-source licence. The base is GPL (Moodle), so the business model is what enterprises actually pay for: **the hosted/managed platform, implementation, integrations, the content pipeline, SLAs and support, and the Sentientia brand** — the same model the commercial Moodle ecosystem runs profitably.
- It is not vapourware. §2 and §9 separate what is live in production today, what is built and verified on staging awaiting a deploy window, and what is designed but deliberately switched off (AI live mode pending an API key, M365 integration pending an Azure registration, recurring subscriptions designed in an ADR).
- Pricing, packaging tiers, and the demo environment host are open business decisions: **[NITIN DECIDES]** (a decision-ready pricing/packaging draft and a demo-tenant plan exist in the business docs).

# 2. Platform at a glance

Every number below comes from the engineering record (production snapshots, the rollout-gate test matrix, and the June-2026 capability audit). Nothing is projected.

## The deployment that proves it

| Metric | Value (from the record) |
|---|---|
| Learners on customer-zero | **3,500+ across 3 tenants** (2,871 accounts / 411 courses in the May-2026 production snapshot) |
| Staging rehearsal scale | Moodle 5.2 cutover executed on a production-scale clone: **3,176 users / 412 courses / 22,523 enrolments / 32,248 completion records — data intact** |
| Tenants in production | 3 — Airpay internal (HRMS-synced employees), Public (self-signup consumers + storefront), ZEEA Tanzania (Swahili) |
| Certificates issued (clone evidence) | **11,415 certificate issues across 9 templates**, PDFs verified on disk |
| Languages | **5 — English, Hindi, Marathi, Kannada, Swahili** — key-parity enforced by tooling (Hindi at 100%) |
| User-type classification | 2,880 classified accounts: 2,196 employees / 682 consumers / 2 operators (polymorphic user-type architecture, ADR-017) |

## The product

| Metric | Value (from the record) |
|---|---|
| Product backend | **40 `sentientia_*` plugins** + the `airpay_ratings` customer-zero shim = 41 local plugins, plus **6 Sentientia dashboard blocks**, a subscription enrolment plugin, a quiz proctoring access rule, and the Airpay payment gateway |
| Theme / design system | 700+ file standalone theme (no upstream theme dependency), light + dark mode, WCAG-conscious, 590px-first mobile breakpoints |
| Personas served | **8** (learner, public/consumer learner, manager, trainer, course author, compliance officer, tenant/L&D admin, site admin) — each with a written user guide |
| Mobile-ready APIs | 22 read + 14 learner-write web-service endpoints audited mobile-app-ready; 36 admin endpoints deliberately desktop-only |
| Architecture record | **27 Architecture Decision Records**, per-plugin state cards, visual evidence required for every UI change |
| Platform base | Moodle 5.1.3 (LTS-class open core) in production · PHP 8.3 · MariaDB/MySQL · **Moodle 5.2 upgrade rehearsed, code-complete, and used as the verification stack** — cutover at the customer's discretion |

## The verification (how hard it has been tested)

| Check | Result (cited from the test record) |
|---|---|
| Rollout-gate workflow matrix | **53 end-to-end persona workflows tracked: 47 verified green, 5 partially verified** (their remaining halves deliberately deferred to the CI browser tier or post-deploy gates), 1 pending — on the production-scale Moodle 5.2 stack |
| Browser render-smoke | **5/5 personas × 4 surfaces green on Chromium and on branded Google Chrome** (AMD modules booted, no template leaks, landmarks present, 0 console errors); WebKit environmentally constrained locally, re-verified on the CI Linux tier |
| Accessibility | **0 serious/critical axe-core findings across 5 personas × 4 surfaces** (WCAG A+AA checks; serious/critical fail the build by design) |
| Scheduled-task health | Full cron pass on the production-scale clone: **103 tasks executed / 0 failed / 0 warnings** |
| Unit/integration suites | Learning paths 48 tests/96 assertions · AI quiz ~47 tests · payment gateway 13-test fail-closed suite · recompletion 13/13 · course-share state machine 23/23 · proctoring rule 9/9 · tenant isolation 7/7 · push crypto end-to-end (VAPID JWT + aes128gcm) ALL PASS |
| Quality gates | 4 automated gates (static scanners → persona render-smoke → accessibility → coverage matrix) wired into pre-commit and CI |

## Honest status: shipped vs staged vs designed

| | Capability | Status |
|---|---|---|
| ✅ **Live on airpay.academy today** | Core LMS, compliance training, exams, 3 tenants, HRMS sync, certificates, dashboards | Serving 3,500+ users now (the pre-product-layer deployment) |
| 🟦 **Built & verified, awaiting production rollout** | The Sentientia product layer: theme + design system, white-label, quality gates, PWA/push, WhatsApp bridge, Sentientia Live, leaderboards, AI features (mock mode), storefront, payment-gateway security fix | On the `production` git branch, verified on staging with imported production data; deploy window with IT is the remaining step |
| 🟨 **Designed / scaffolded, switched off** | Recurring subscriptions (ADR-023), M365/Graph integration, AI live mode (needs API key), voice pipeline live mode (needs ElevenLabs budget), native mobile store wrappers (ADR-005) | Activation is a business decision, not an engineering rebuild |

# 3. Who uses it — personas and journeys

Sentientia serves **8 personas**, each with a written user guide in `docs/user-guides/`. The platform is role-aware: the same login URL lands every persona on a dashboard shaped for their job, and a built-in role switcher lets multi-role users (e.g. a manager who is also a learner) flip views without logging out.

## 3.1 Learner / Employee — the largest population

**Who:** every employee with assigned training; on customer-zero, thousands of Airpay staff synced in from HRMS.

**A day in the life:**

1. **Open the app** — from a browser, or from the installed PWA icon on their phone home screen (install prompt offered on first login; Android one-tap, iOS guided "Add to Home Screen").
2. **Dashboard shows what matters** — My Courses grouped as In progress / Pending / Overdue (red banner) / Completed; deadline calendar; AI assistant quick-action chips; gamification points and streak.
3. **A nudge arrives where they actually are** — 7, 3, and 1 days before a compliance deadline they get a reminder on the channels they opted into: email, in-app, web push to their phone, WhatsApp or SMS.
4. **Take the course** — SCORM player with auto-saved resume position; quizzes with timers; proctored exams show a consent + identity step first; feedback forms (anonymous where configured).
5. **Get rewarded** — completion issues a badge and a PDF certificate with a unique verification ID anyone can check online; points land on the leaderboard (opt-out respected); streaks continue.
6. **Self-serve everything else** — skill self-rating (1–5 against their role's skill catalogue), course access requests routed to their manager, language toggle (English/Hindi/Marathi/Kannada/Swahili), notification channel preferences, ICS calendar feed into Outlook/Google.

**What they never have to do:** chase anyone for access, wonder what is due, or learn "an LMS" — the verified mobile breakpoint (590px), dark mode, and a 0-serious/critical accessibility result mean the path of least resistance is just doing the training.

## 3.2 Public / Consumer learner — the storefront audience

**Who:** anyone on the internet, on the Public tenant. Not an employee; self-signed-up.

**Journey:** browse the Netflix-style public storefront logged-out → self-register (honeypot-protected signup, privacy policy + T&C consent, email confirmation) → filter the catalog by category/language/price → add a paid course to cart → check out through the **Airpay payment gateway** (UPI, cards, net banking, wallets) with a fail-closed payment verification → immediate access → learn → download a verifiable certificate → optionally request account deletion under the privacy workflow (30-day cooling-off, certificates stay verifiable after anonymisation).

Consumers are a distinct **user type** in the architecture (ADR-017), not employees with blanks: no manager chain, no internal compliance, but full PWA, Hindi UI, and certificates.

## 3.3 Manager / Supervisor

**Who:** line managers with direct reports (the supervisor chain comes from HRMS sync).

**A day in the life:**

1. **My Team dashboard** — every direct report with a compliance status pill (green/yellow/red/grey) and last-activity date.
2. **Escalations find them automatically** — if a report goes past a deadline, the system emails the manager at 1, 7, and 14 days overdue (verified end-to-end in the test matrix: seeded overdue → manager notification via the supervisor chain). No spreadsheet policing.
3. **Approval queue** — course-access requests from their team arrive with the requester's reason; Approve / Reject (reason required) / Delegate, with SLA nudges if unactioned.
4. **Skill validation** — review team self-ratings; endorse, override with comment, or request evidence; every change is audit-logged.
5. **Report up** — team compliance and KPI tables with CSV export (Excel-friendly UTF-8 with BOM) for business reviews.

## 3.4 Trainer

**Who:** runs instructor-led and live sessions; on customer-zero, the BizLMS trainer role (teacher archetype).

**Journey:** trainer dashboard → schedule a classroom session (learners get an ICS calendar invite, white-labelled) → run a **Sentientia Live** session: create slides of 6 interactive question types, share a join code, audience answers on their phones, results animate on the projector in real time → export session analytics to CSV → review attendance and feedback.

## 3.5 Course Author / L&D Admin

**Who:** subject-matter experts and L&D staff who build the learning estate.

**A day in the life:**

1. **Create a course** with Sentientia's additions to Moodle's form: target-audience rules (designation/department/cohort), completion days that drive the reminder engine, Hindi-readiness audit.
2. **Assemble activities** — SCORM (including pipeline-generated packages), quizzes, exams with open/close windows, structured evaluations with conditional questions, resources.
3. **Wire the audience** — cohort sync or one-click bulk-enrol of everyone matching the audience rules (verified: 14/14 enrolment smoke tests).
4. **Chain it** — learning paths with prerequisites and enrolment windows; programs with cohort intake and prerequisites.
5. **Let AI help** — generate a draft quiz from course content (mock mode today; flips live with an API key), queue content for AI translation, edit token-based email templates per tenant.
6. **Publish and verify** — visibility flip, catalog presence check, smoke-test as a learner via the role switcher.

## 3.6 Compliance Officer

**Who:** owns statutory-training coverage (POSH, AML, IT security) and answers to auditors.

**Journey:** compliance dashboard (all tenants or their scope) → drill into red cells → the audit answer in one query: *every reminder and escalation around any deadline is a logged row* — "show me every nudge around Alice's POSH deadline" is a single filtered view, not an email archaeology project → recompletion cycles re-open annually-renewed certifications automatically (13/13 cycle tests green) → exports for the auditor.

## 3.7 Tenant Admin

**Who:** runs ONE business unit (tenant) inside a customer — users, courses, reports, templates — without seeing any other tenant's data.

**Journey:** add or bulk-import users (24-column CSV, same shape as HRMS sync, auto-scoped to their tenant) → manage suspensions and password resets → create courses/paths/programs inside their tenant's category tree → tenant-scoped dashboards (compliance, skills matrix, engagement KPIs, audit log) → edit the tenant's welcome-email template with token substitution → view (not edit) the branding the site admin configured. Tenant scope is enforced at the database query layer; seeing another tenant's data is treated as a P0 security bug.

## 3.8 Site Admin

**Who:** the superuser across all tenants and customers — typically the customer's IT or the Sentientia managed-service operator.

**Journey:** Day-1 setup checklist (SSL, branding, SMTP, HRMS sync, retention policies) → the **Switchboard**: the feature-flag console with 5-level precedence (customer+tenant → customer → tenant → global → registered default, default OFF) → plugin health, cron health and certificate-email health blocks on the admin dashboard → VAPID push key management (private key envelope-encrypted at rest) → WhatsApp template governance (Meta/DLT approval workflow) → per-customer branding (logo light/dark, colours, typography, favicon — applied without cache purges) → emergency procedures (CLI password reset, maintenance mode, documented rollback).

# 4. Feature catalog by domain

This is the complete capability inventory: **41 local plugins, 6 dashboard blocks, a subscription enrolment plugin, a proctoring quiz-access rule, and a payment gateway**, grouped into seven business domains. Every plugin entry follows the same shape so the catalog stays scannable:

> **What it is** (plain English) → **Key features** → **How it's used** (the workflow) → **Who uses it** → **Admin & flags** → **Works with** (sibling integrations)

Naming note: the product components are `local_sentientia_*` (the airpay→sentientia component rename, ADR-025, is complete across 36 components). State cards written before the rename reference the old `local_airpay_*` names for the same code. Release versions cited are from each plugin's `version.php` as of 2026-06-11. Maturity labels are the engineering stamps: **Stable** (production-hardened), **Beta** (feature-complete, hardening), **Alpha** (built, flag-gated, deliberately switched off until activated).

---

## 4.1 Learning Core

*The backbone: structured learning, assessment, certification renewal, and the automation around deadlines.*

### Courses — `local_sentientia_courses` · v1.11.2 · Stable

**What it is.** The course management engine on top of Moodle's course core: progress tracking, deadline automation, featured-course curation, and — unusually for an LMS — **cross-tenant course sharing with a request/approve marketplace workflow** between business units.

**Key features**
- Deadline engine: learner reminders at **7/3/1 days before** a course deadline and **manager escalations at 1/7/14 days after** it, with a deduplication log so nobody is double-nudged — every nudge is one auditable row.
- Cross-tenant sharing: an owning tenant (e.g. head office) shares selected courses into another tenant's catalog. Completion data stays segregated automatically — a borrowing tenant's learner completions only ever surface in that tenant's reports.
- Pull-request workflow: a receiving tenant's manager browses the provider tenant's full library and files a request; a super admin approves or rejects from an inbox, with a decision reason and audit events on every step (state machine verified 23/23 in the test matrix).
- "Featured for you" dashboard widget with Netflix-style poster thumbnails.
- Bulk operations: CSV enrol, bulk unenrol, enrolled-users export.
- 10 granular capabilities (view/create/update/delete/enrol/visibility/share/request/approve/manage) so tenants can split duties precisely.

**How it's used.** An author sets "completion days" on a course → the reminder engine takes over with zero further configuration (it fires automatically when a learner has a deadline and a manager populated from HRMS). A Public-tenant manager wants a head-office course → opens "Browse provider catalog" → Request access → the super admin approves → the course appears in the Public catalog with a "Provided by" badge.

**Who uses it.** Course authors and tenant admins (creation, sharing), managers (requests), learners (the receiving end of reminders), compliance officers (the audit trail).

**Admin & flags.** Ships behind capability gates and tenant scoping (pre-dates the flag mandate). 4 database tables; 43 PHPUnit test methods across 5 suites.

**Works with.** `sentientia_catalog` (shared courses appear with provenance badges), `sentientia_emails` (reminder templates), `sentientia_whatsapp`/`sentientia_pwa` (reminder channels), `sentientia_manager` (escalation chain), `sentientia_compliance_report` (the audit view).

---

### Learning Paths — `local_sentientia_learningpath` · v1.7.1 · Stable

**What it is.** Curated sequences of courses — "complete these six, in this order" — with per-path enrolment, progress tracking, and completion when all mandatory courses are done.

**Key features**
- Path builder: add/remove/reorder courses with mandatory/optional flags; drag-free bulk reorder.
- Enrolment windows and rich-text descriptions with dates.
- Audience-rule bulk enrolment (designation/department/cohort filters that cascade).
- 10 web services covering the full admin surface (list, assign, reorder, enrol, etc.) — the same operations are scriptable.
- A production diagnostic CLI that walks 7 checks (tables, files, web services, capabilities, role grants) and can self-repair capability grants — born from a real customer-zero support case.
- A learner enrolled mid-path keeps their enrolment when a course is added; completion recalculates live.

**How it's used.** L&D admin creates "New Joiner Induction" → adds 6 courses, marks 4 mandatory → sets the enrolment window → bulk-enrols everyone matching "Designation: Sales Executive, Department: Mumbai" → the path tracks each learner to completion.

**Who uses it.** Course authors/L&D admins (build), tenant admins (enrol), learners (follow), managers (watch progress).

**Admin & flags.** Capability-gated (6 capabilities). 3 tables. **62 PHPUnit methods across 6 suites — 48 tests/96 assertions of it re-verified green in the June-2026 rollout gate.**

**Works with.** `sentientia_programs` (its tiered sibling), `sentientia_courses` (the units of a path), `sentientia_catalog`.

---

### Programs — `local_sentientia_programs` · v1.8.1 · Stable

**What it is.** Multi-level certification programs — sequential tiers of courses ("Foundation → Practitioner → Expert") where completing a level unlocks the next. The tiered big brother of learning paths.

**Key features**
- Levels with ordered course sets; required vs optional courses per level.
- Level-unlock state machine (the deepest-tested part: 17 dedicated test methods).
- Audience-rule bulk enrolment; cohort enrol + prerequisites verified in the rollout gate.
- Completion observer: finishing a course automatically re-evaluates program progress.
- Per-program certification-authority metadata.

**How it's used.** Define "Payments Specialist Program" with 3 levels → assign courses per level → enrol the intake cohort → learners progress level by level; the system unlocks the next tier on completion of the previous one.

**Who uses it.** L&D admins (design), learners (the long-arc journey), managers (development conversations).

**Admin & flags.** 6 capabilities, 4 tables, 41 PHPUnit methods. GDPR privacy provider included.

**Works with.** `sentientia_learningpath`, `sentientia_skills` (programs teach skills), certificates (`tool_certificate` integration is a tracked open item).

---

### Classroom / ILT — `local_sentientia_classroom` · v1.10.1 · Stable

**What it is.** Instructor-led training: physical or virtual classroom sessions with scheduling, rosters, waitlists, attendance, and calendar invites.

**Key features**
- Session management with conflict detection and trainer assignment (18 test methods on scheduling alone).
- Waitlist with automatic promotion when seats free up.
- Per-session attendance recording (status + timestamp + who recorded it), gated by a dedicated attendance capability so trainers can mark attendance without full manage rights.
- RFC-5545 ICS feed per classroom — sessions land in learners' Outlook/Google calendars (ICS structure unit-verified: VCALENDAR/VEVENT, UTC times, stable UIDs, white-label ORGANIZER).
- Audience-rule bulk enrolment, enrolment windows.

**How it's used.** Admin creates the classroom and sessions → assigns a trainer → learners enrol (or are bulk-enrolled) and receive a calendar invite → trainer marks attendance per session → attendance feeds ILT reporting and the trainer dashboard block.

**Who uses it.** Trainers (run + attendance), L&D admins (schedule), learners (attend), managers (attendance visibility).

**Admin & flags.** 6 capabilities, 4 tables, 49 PHPUnit methods. Whether classroom sessions appear in the calendar feed is governed by `local_sentientia_calendar`'s `events.classroom` flag.

**Works with.** `sentientia_calendar` (event source), `block_sentientia_trainer` (the trainer's dashboard), `sentientia_live` (in-session engagement), `sentientia_evaluation` (post-session feedback).

---

### Exams — `local_sentientia_exams` · v1.6.1 · Stable

**What it is.** Online exam administration wrapped around Moodle's battle-tested quiz engine: exam periods, eligibility, reminder cadence, and attempt management — exam-level metadata kept cleanly separate from quiz-level configuration.

**Key features**
- Exam definitions linked to quiz activities, with open/close periods and eligibility filters.
- The same reminder machinery as courses: per-(user × exam × cadence-day) reminder dedup log, cron-driven (the exam-reminder cron shares its verified harness with the course-reminder cron).
- Deep-link enrolment for inviting learners straight into an exam.
- Status lifecycle management and an exam admin surface.

**How it's used.** Compliance officer schedules the annual AML exam → sets the window and eligible audience → learners are reminded automatically as the window approaches → attempts run on the quiz engine (optionally proctored) → results feed compliance reports.

**Who uses it.** Compliance officers and L&D admins (schedule), learners (attempt), managers (overdue visibility).

**Admin & flags.** 3 capabilities, 2 tables. Exam dates in the calendar feed are governed by the calendar plugin's `events.exams` flag.

**Works with.** `mod_quiz` (delivery), `quizaccess_sentientia_proctoring` (proctored attempts), `sentientia_recompletion` (annually renewed certs), `sentientia_calendar`.

---

### Evaluations — `local_sentientia_evaluation` · v1.15.2 · Stable

**What it is.** Structured evaluation forms — Kirkpatrick level-1 reaction surveys, post-classroom feedback, manager-effectiveness questionnaires — with a reusable template library and roll-up analytics.

**Key features**
- Form builder with question types including numeric and multi-select, and **conditional questions** (show question B only for certain answers to A).
- Trigger engine: fire a form automatically on course completion (event observer) or on schedule; auto-expiry of stale assignments and non-respondent tracking (cron-verified in the rollout gate).
- Reusable template library with JSON import/export round-trip.
- Analysis page per form: aggregate scoring, NPS calculation, response distributions (15 test methods on analytics alone).
- Audience-rule assignment and per-response CSV export.

**How it's used.** Author builds the "Post-Training Feedback" template once → attaches a trigger "on completion of any classroom course" → learners get the form automatically → L&D reads the per-form analysis and exports for the QBR.

**Who uses it.** L&D admins (build + analyse), learners (respond), trainers (their session scores).

**Admin & flags.** 2 deliberately compact capabilities (manage / respond), 6 tables, 37 PHPUnit methods.

**Works with.** `sentientia_classroom` (post-ILT feedback), `sentientia_analytics` (dashboards), `sentientia_emails` (reminder pipeline — tracked open item).

---

### Skills — `local_sentientia_skills` · v1.6.2 · Stable

**What it is.** A skills framework: per-customer skill catalogue, role-to-skill mapping (designation matrix), course-to-skill mapping, and per-user skill levels with a full append-only history.

**Key features**
- Skill categories, catalogue, and per-skill level definitions (e.g. Beginner/Intermediate/Expert).
- Designation matrix: which skills each role is expected to hold, at which level.
- Course mapping: which skills a course teaches — completing the course can level a learner up via an event observer.
- Learner **self-rating** (capability-gated) plus the manager endorse/override/request-evidence workflow described in the manager guide; every change lands in the append-only history table.
- The history table is the data source for the leaderboard's "skill" board type.

**How it's used.** Admin defines the catalogue and the designation matrix → learners self-rate against their role's skills → managers validate → dashboards show the team skill matrix → leaderboards and (future) recommendations consume the history.

**Who uses it.** Learners (self-rate), managers (validate), L&D admins (taxonomy), HR (the matrix view).

**Admin & flags.** 3 capabilities, 7 tables, 23 PHPUnit methods including dedicated privacy-provider tests (per-user export + delete).

**Works with.** `sentientia_leaderboard` (skill boards), `sentientia_analytics` (skill radar), `sentientia_recommendations` (skill-gap signal — tracked open item).

---

### Recompletion — `local_sentientia_recompletion` · v1.1.1 · Stable

**What it is.** The certification-renewal engine. Mandatory training that expires — cyber security every 12 months, KYC/AML every 6 — gets re-opened automatically, with an audit trail of every reset.

**Key features**
- Rule definitions: course + period + audience filter + on-reset behaviour.
- A cron walker compares last-completion timestamps against each rule's period and resets per-user completion data on the due date.
- Append-only reset history (user, course, reason, before/after state).
- A dedicated `:reset` capability so compliance can run an ad-hoc reset without rule-edit rights.
- Verified **13/13 in the rollout-gate smoke**: cycle resets completion and the reminder engine re-engages.

**How it's used.** Compliance sets "AML Essentials: every 180 days, all customer-facing staff" → six months after each learner's completion, their status flips back to "required" → the standard 7/3/1 reminders and manager escalations re-arm automatically.

**Who uses it.** Compliance officers (rules + ad-hoc resets), learners (the renewal experience), auditors (the history table).

**Admin & flags.** 3 capabilities, 2 tables.

**Works with.** `sentientia_courses` (the reminder engine it re-arms), `sentientia_compliance_report` (coverage view), `sentientia_lifecycle` (compliance scanning).

---

### Lifecycle — `local_sentientia_lifecycle` · v1.0.0-beta · Beta

**What it is.** Event-driven course-lifecycle automation: it watches what happens (completions, enrolment changes) and runs a daily compliance scan that flags overdue mandatory training.

**Key features**
- Event observers on course completion, enrolment created, enrolment deleted.
- A daily `compliance_check` scheduled task (02:00 IST on customer-zero) that flags overdue mandatory training.
- Two message providers — `compliance_overdue` and `compliance_due_soon` — that plug into Moodle's notification preference system, so learners control the channel.
- No plugin-owned tables: it deliberately rides Moodle's standard event + task infrastructure.

**How it's used.** Invisible by design. It runs nightly; learners and managers experience it as the "due soon" and "overdue" notifications appearing in their preferred channels.

**Who uses it.** Everyone, passively; compliance officers actively (the scan feeds their dashboards).

**Admin & flags.** No direct capabilities (reads compliance-report permissions); joiner/leaver automation around it verified in the rollout gate (welcome-email on join, deactivation on leave).

**Works with.** `sentientia_compliance_report`, `sentientia_notifications`, `sentientia_emails`, the HRMS sync in `sentientia_users`.

## 4.2 Engagement & Motivation

*Learning that people come back to: points, streaks, badges, live rankings, challenges, ratings, and a built-in live-polling engine.*

### Gamification — `local_sentientia_gamification` · v1.0.1-beta · Beta

**What it is.** The points-badges-streaks engine. Actions earn points (course completion, quiz attempts, daily login), badges unlock at thresholds, and daily-login streaks build — all surfaced on the learner dashboard's achievements tile.

**Key features**
- Append-only points-event log (every point traceable to the action that earned it).
- Per-tenant badge catalogue and per-user earned badges.
- Daily-login streak counter.
- Course-completion observer awards points automatically.
- Optional completion **confetti** moment (its own flag — celebration is a choice).

**How it's used.** Zero learner effort: do the learning, watch the points/streak tile move. Admins curate the badge catalogue per tenant.

**Who uses it.** Learners (earn), tenant admins (badge catalogue), L&D (engagement lever).

**Admin & flags.** `engagement.gamification.enabled` master switch (when OFF: widget hides, observers stop awarding, leaderboard nav link disappears) and `engagement.gamification.confetti`. 4 tables. Badge seeding verified on the 5.2 stack in the rollout gate (8 badges present).

**Works with.** `sentientia_leaderboard` (rankings), `sentientia_challenge` (points rewards), the dashboard.

---

### Real-time Leaderboards — `local_sentientia_leaderboard` · v0.2.0-alpha · Alpha (flag-gated) · ADR-014

**What it is.** Live-updating leaderboards that rank learners by quiz performance, completion speed, or skill growth — pushed to screens in real time over Server-Sent Events (SSE), with privacy opt-out built in as a first-class right.

**Key features**
- **Three board types**, each independently flag-gated: `quiz` (best scores, faster attempt wins ties), `completion` (enrol-to-complete speed), `skill` (level growth from the skills history).
- **Real-time via SSE** — the same event-journal + stream pattern proven by Sentientia Live (ADR-014 deliberately reuses the pattern, not the tables, so leaderboards run even where Live is disabled). A kill-switch flag degrades gracefully to 30-second polling.
- **Privacy by design:** a one-click "Hide me from public leaderboards" toggle in user preferences; the opt-out filters every learner-facing read; only the HR-analytics capability `:viewall` can bypass it, and opt-in fully deletes the opt-out row (no stale flags). GDPR provider exports rankings and opt-out timestamps.
- Tenant scope is mandatory on every aggregator; customer-wide boards require a separate promote capability.
- Surfaces: a dashboard block, a full top-25 page, an admin index, and a preferences page; 3 REST functions; scheduled recompute (every 2 minutes for due boards) + daily event purge.

**How it's used.** Admin creates a board (e.g. "Q2 Compliance Sprint — completion") and flips the type flag → the block appears on dashboards → completions recompute rankings → connected screens update without refresh. A learner who prefers privacy opts out once and disappears from public views everywhere.

**Who uses it.** Learners (compete or opt out), trainers/L&D (motivation campaigns), HR (`:viewall` analytics).

**Admin & flags.** 6 flags (master OFF by default — installs render nothing until enabled), 4 capabilities, 4 tables, 31 PHPUnit methods (ranking correctness incl. 1-2-2-4 competition ties, tenant scope, opt-out honouring, idempotent recompute). Demo-board seed CLI generates boards from real completion data.

**Works with.** `block_sentientia_leaderboard` (the widget), `sentientia_gamification`, `sentientia_skills` (skill boards), `sentientia_live` (shared SSE pattern).

---

### Challenges — `local_sentientia_challenge` · v1.1.4-alpha · Alpha

**What it is.** Time-boxed learning competitions: "complete 3 courses this month, earn 500 points." Learners join voluntarily, progress is tracked automatically, and a per-tenant leaderboard shows who's ahead.

**Key features**
- **Three challenge types shipped:** course-completion count, daily-login streaks, and quiz-score thresholds.
- Fairness engineering you can explain to a works council: the target is **snapshotted at join time** (an admin raising the bar mid-challenge only affects new joiners), completion is terminal (no point-farming by grinding easy courses), and progress evaluation runs both event-driven (instant) and cron-driven (catch-up) through one idempotent engine.
- Auto-expiry of attempts past the end date; pre-computed leaderboard snapshots recomputed every 15 minutes, with `challengeid=0` as the all-challenges aggregate.
- 8 web services covering the full lifecycle (list/get/create/update/delete/join/leave/leaderboard), all tenant-scoped.
- 4 capabilities — including a deliberate `:viewall` split so a normal learner's leaderboard auto-scopes to their own tenant while HR can opt into cross-tenant views.

**How it's used.** L&D drafts a challenge → activates it → learners hit Join on the challenge page → completions tick progress automatically → points land on completion → leaderboard page shows standings.

**Who uses it.** Learners (join + compete), L&D/managers (create — `:manage`), HR (cross-tenant views).

**Admin & flags.** 3 tables, ~95 language strings, ~50 PHPUnit methods including a Phase-2 suite for streak/quiz/expiry.

**Works with.** `sentientia_gamification` (points), `sentientia_leaderboard`, `mod_quiz` (score observers).

---

### Course Ratings — `local_airpay_ratings` · v1.1.1 · Stable (with `local_sentientia_ratings` staged)

**What it is.** Five-star course ratings with comments, submitted by enrolled learners, aggregated onto catalog cards and course detail pages — the social proof layer of the catalog.

**Key features**
- 1–5 stars + free-text comment; one rating per user per course (re-submitting updates, never duplicates).
- Aggregates surface in the catalog and on course detail.
- Course-scoped by design (Moodle core ratings are activity-scoped — this fills the gap that matters for a catalog).
- Capability-gated to enrolled learners; 14 PHPUnit methods on the rate/update/aggregate flow with multi-user fixtures.

**A naming note (honesty over polish):** this is the one plugin still carrying the customer-zero `airpay_` component name. Its rename to `local_sentientia_ratings` was fully rehearsed (12/12 verification on a production-data clone, including capability hand-over and web-service re-registration) and the staging directory exists in the repo; executing the rename on production is an owner-gated call. **[NITIN DECIDES]** when batch-1 of the component rename rolls out.

**Who uses it.** Learners (rate), prospective learners (read), L&D (quality signal).

**Works with.** `sentientia_catalog` (star display), `sentientia_recommendations` (quality signal).

---

### Sentientia Live — `local_sentientia_live` · v0.2.2-alpha · Alpha (flag-gated) · ADR-004

The built-in Mentimeter-class live engagement engine: 6 interactive question types, join-by-code audience flow, real-time SSE results, projector mode, and CSV analytics. It is significant enough to get its own showcase — see **§6**.

## 4.3 Commerce & Discovery

*From "what should I learn?" to "paid and enrolled" — catalog, cart, storefront, payment gateway, and the subscriptions engine waiting in the wings.*

### Catalog — `local_sentientia_catalog` · v1.0.2-beta+ · Beta

**What it is.** The browse-and-discover surface for both logged-in learners and the public internet: an LXP-style catalog with poster cards, carousels, search, and a "For You" feed — the front door of the platform.

**Key features**
- **Member catalog**: searchable, filterable, paginated course grid with real-image poster thumbnails (gradient fallback when a course has no image), category tree filtering, "For You" recommendations feed (falls back to "Trending this week" when AI recommendations are off), trending and new-arrival rails.
- **Public storefront** (`public.php`): the logged-out Netflix-style grid with a "Popular picks" scroll-snap carousel — flag-branched so a customer can keep a plain legacy grid byte-for-byte if they prefer (the LXP look is now the default, reversible per tenant via the Switchboard).
- **One-click free enrolment for internal staff** (flag-gated, per-tenant): a logged-in internal-tenant employee clicking a free course is enrolled instantly — no cart detour, enrolment-key bypassed, idempotent. Public-tenant users and guests keep the cart; paid courses always cart. Born from a real QA finding where employees couldn't self-enrol in free courses; fixed with an 8-case PHPUnit policy/mechanism suite and real-browser verification.
- Cross-tenant share awareness: borrowed courses appear with a "Provided by" provenance badge; each tenant gets its own catalog cache.
- Commerce hooks throughout: price display, add-to-cart, cart pill.

**How it's used.** Learner: open Catalog → filter by category/skill/language → click a poster → course detail → Enrol (instant if free + internal) or Add to cart. Guest: land on the storefront, browse, sign up when ready.

**Who uses it.** Learners, public visitors, tenant admins (what appears is driven by course visibility + audience + shares).

**Admin & flags.** `sentientia.catalog.public_lxp.enabled` (storefront look), `sentientia.catalog.free_oneclick_enrol.enabled` (default OFF; enable per internal tenant). No tables of its own — it is a read layer, which keeps it fast and safe.

**Works with.** `sentientia_courses` (tenant-share filter), `sentientia_cart` (commerce), `airpay_ratings` (stars), `sentientia_recommendations` (For You feed).

---

### Cart & Orders — `local_sentientia_cart` · v1.0.2 · Stable

**What it is.** The commerce backbone: shopping cart, checkout, order history, **GST-compliant invoicing**, refunds, and a per-user credit ledger. The cart owns the order lifecycle; the payment gateway processes the charge.

**Key features**
- Cart and checkout flow with order-number sequencing (atomic counter — no duplicate order IDs under load).
- Append-only payment ledger (charges, refunds, credits) — the money trail is never overwritten.
- GST-compliant invoice generation (India regulatory) with line items; invoice history downloadable by the learner.
- Per-user credit balances for refunds and promotional credits.
- Gateway callback hardening: callback audit logging and an IP-allowlist gate on the payment callback endpoint.
- Admin order list, daily-sums report with CSV export.
- 5 capabilities including separate `refund` and `manageprices` so finance duties can be split.

**How it's used.** Public learner adds a paid course → checkout → pays on the gateway page → callback verifies → enrolment fires automatically → invoice lands in their purchase history. Failed payments return them to the cart with contents preserved.

**Who uses it.** Public/consumer learners (buy), site admins/finance (orders, refunds, pricing).

**Admin & flags.** 5 tables. The free-course cart path for the Public tenant is explicitly policy-tested (Public keeps the cart; no one-click).

**Works with.** `paygw_airpay` (the charge), `sentientia_catalog` (add-to-cart), `enrol_sentientiasub` (future recurring billing).

---

### Payment Gateway — `paygw_airpay` · v1.0.1 (2024100700.10) · Stable

**What it is.** A standards-compliant Moodle `core_payment` gateway for Airpay's payment service — UPI, cards, net banking, wallets. Customer-zero literally dogfoods its own core product here: Airpay's LMS charges through Airpay's gateway.

**Key features**
- Implements the standard gateway interface, so anything in Moodle that uses `core_payment` can charge through it.
- **Fail-closed payment verification** — a payment-verification bypass found in the inherited gateway code was fixed with a verifier that rejects anything it cannot positively verify (tampered-hash rejection is an explicit test case), proven by a **13-test security suite** plus a live-path verification; MD5 was deprecated in the same hardening wave. The one remaining gate before production deploy is a single sandbox transaction test — deliberately owner-gated. **[NITIN DECIDES]** the sandbox-test window.
- Checksum implementation is the highest-tested area (10 dedicated hash/verify methods of 28 total PHPUnit methods).
- Per-payment audit tables: gateway-side details, error log for support diagnostics, and an enrolment log tying every successful payment to the enrolment it triggered.
- GDPR privacy provider.

**How it's used.** Invisible when it works: the learner picks a payment method, pays on the hosted page, and lands back enrolled. For support, every failure is in the error log; for audit, every enrolment-by-payment is logged.

**Who uses it.** Public learners (pay), site admins (configuration per payment account), finance (audit).

**Works with.** `sentientia_cart`, `enrol_sentientiasub` (future mandates).

---

### Pages — `local_sentientia_pages` · v1.1 · Stable

**What it is.** The standalone-page toolkit: the branded site landing page, new-employee onboarding journey, QR-code attendance scan-in, certificate gallery, and the legal/privacy static pages — one home for surfaces that don't justify a full plugin each.

**Key features**
- `homepage` (branded landing), `onboarding` (new-employee journey, tenant-scoped), `qr_attendance` + `qr_scan` (QR attendance flow), `certificates` (gallery).
- Static legal pages: privacy, terms, help, contact, and the DPDP-Act data-fiduciary statement — all white-labelled (verified rendering with the configured site name in the rollout gate).
- **Tenant-scoped certificate-template browser** (flag-gated `sentientia.certificate.tenant_scope.enabled`): non-siteadmin tenant admins see only global + their own tenant's certificate templates, driven by an admin-configurable JSON map — without touching the vendored certificate tool.
- 5-locale language packs (en/hi/kn/mr/sw); 11 operational CLI seed/setup scripts for environment provisioning.

**How it's used.** Mostly ambient: learners hit the landing page and legal pages; new joiners walk the onboarding journey; trainers run QR attendance at ILT sessions; admins browse certificate templates within their scope.

**Who uses it.** Everyone (landing/legal), new joiners (onboarding), trainers (QR), tenant admins (cert templates).

**Works with.** `sentientia_classroom` (attendance integration is a tracked open item), `tool_certificate` (read-only browser), the theme.

---

### Recurring Subscriptions — `enrol_sentientiasub` · v0.2.0-alpha · Alpha (designed, gated) · ADR-023

**What it is.** The subscription enrolment engine: course access that stays active while a recurring payment mandate is active, suspends on a failed charge, and revokes on cancellation — Netflix economics for a course library.

**Key features (built today)**
- Per-instance scope: a subscription can cover **one course, a whole category, or all-access** — all three models in one plugin (the ADR-023 decision).
- Full lifecycle state machine: create → activate → suspend ↔ record-cycle → cancel, with cohort-sync grant/revoke for category and all-access scopes (suspend removes cohort membership; reactivation re-adds it). 17/17 state-machine and 7/7 cohort-lifecycle smokes verified; 10 CI test cases.
- 4 capabilities, settings, EN+HI lang at parity, GDPR provider.

**What is deliberately NOT built yet:** the actual recurring charge (Airpay mandate checkout and the per-cycle verification callback) — gated on the payment sandbox and the verified fail-closed pattern, by design after the 2026-06-02 payment-verification lesson. Billing periods, pricing, charge-failure policy and target tenants are open product decisions. **[NITIN DECIDES]** per ADR-023.

**Admin & flags.** `sentientia.subscriptions.enabled` (default OFF — the platform is unchanged until flipped; the plugin even refuses to offer new instances while OFF).

**Works with.** `paygw_airpay` (mandates), `sentientia_cart` (billing surface), cohorts (access mechanics).

## 4.4 Intelligence & AI

*AI that is governed, costed, and reviewable — every AI feature ships with a 4-layer cost defence, a deterministic mock mode (fully demoable at zero spend), and a human-review gate. Live mode activates with an API key; nothing auto-publishes.*

**The shared AI governance pattern** (consistent across aiquiz, recommendations, translate, assistant):
1. Master feature flag (default OFF) → 2. separate live-API flag (default OFF — mock client until flipped) → 3. per-call [CONFIRM] checkbox on every generation → 4. daily token caps (per-user or per-customer depending on the feature). Plus: versioned prompts in code, strict parsers that drop malformed output, per-tenant data isolation, and GDPR providers declaring the external AI link.

### AI Quiz Generation — `local_sentientia_aiquiz` · v0.2.0-alpha · Alpha (flag-gated) · ADR-012

**What it is.** Paste course content — a SCORM transcript, narration text, an SOP excerpt — and get a draft multiple-choice quiz generated by Anthropic Claude. Every draft passes a mandatory human-review gate before any question can reach a real quiz.

**Key features**
- Generate → review → approve/edit/reject **per question**; drafts carry a full lifecycle (pending → generated → approved/rejected → pushed/failed) with reviewer audit and token usage recorded per draft.
- **Hindi generation** (Phase G.1): a Devanagari-aware prompt with few-shot examples, a character-safe (not byte-safe) parser, and a language picker — quizzes generate natively in Hindi.
- **Per-customer prompt overrides**: a customer can tune the generation template without code.
- Strict JSON contract parsing — malformed items are dropped, never shown.
- Cross-tenant draft-ID guessing is rejected at the data layer.
- Admin ceilings: max questions per request, max source words (4,000), per-user daily token cap.

**How it's used.** Author opens Generate → pastes source + picks language → ticks the cost-confirm checkbox → reviews the draft question by question → approves the keepers. (The push-to-quiz button exists but is deliberately stubbed until Phase G.4; auto-push has its own OFF flag.)

**Who uses it.** Course authors (generate + review), managers (`:manage_all` oversight across owners).

**Admin & flags.** 3 flags (`enabled` / `live_api` / `auto_push`, all default OFF), 3 capabilities, 2 tables, **82 PHPUnit methods** (parser, prompts, lifecycle, client). Live-mode budget is an open ask. **[NITIN DECIDES]** the Anthropic monthly cap before `live_api` flips ON.

**Works with.** `mod_quiz` (eventual push target), `sentientia_core` (per-customer config registry), the content pipeline (source text).

---

### AI Assistant — `local_sentientia_assistant` · v1.1.2-alpha · Alpha

**What it is.** A floating chat drawer available on every page that answers "where do I find X?", course, deadline, and policy questions — with role-aware quick-action chips so the first tap is usually enough.

**Key features**
- **Role-aware quick actions**: a manager sees "Team status"; a Public-tenant learner sees "My certificates" instead of deadlines/team — chips are built from the role detector + tenant identity, localised in all 5 languages (verified live: a Public non-manager session gets exactly the right chip set).
- Moodle-context bridge: the assistant knows what page/course context it was opened from.
- Every prompt + response is audit-logged with model and token counts; a response cache keeps common questions cheap.
- Mock + live client; when the master flag is OFF the button disappears everywhere and the client returns a polite "temporarily unavailable".

**How it's used.** Learner taps the floating bubble → taps a chip or types a question → answer in the drawer. Rendered chips were part of the verified persona render-smoke (assistant markers present on the dashboard).

**Who uses it.** All personas; the chips adapt.

**Admin & flags.** `ai.assistant.enabled` master flag; admin settings for model, system prompt, token caps. 2 tables (chat log + cache).

**Works with.** `sentientia_core` (AI client + tenant identity), the theme (drawer injection via Moodle 5.x hooks).

---

### AI Recommendations — `local_sentientia_recommendations` · v0.1.0-alpha · Alpha (flag-gated) · ADR-015

**What it is.** Top 3–5 personalised course recommendations on the learner dashboard, generated from completion history, current skills, role, and tenant learning patterns — each with a one-line "Why this?" rationale.

**Key features**
- **Catalogue-bounded parser**: the model cannot recommend a course that isn't in the candidate list — invented course IDs are dropped. No hallucinated courses, ever.
- Recommendation lifecycle per row: active → dismissed / enrolled / expired — dismissals stick, enrolments are tracked as conversions.
- Cost defence at the right unit for a fan-out feature: **per-customer** daily token cap.
- Companion dashboard block (`block_sentientia_recommendations`) that renders nothing when the flag is off, no batch exists, or the viewer lacks the capability.
- 79 language keys at 100% EN/HI parity; demo seed CLI for instant showcase data.

**How it's used.** Manager triggers a batch (cost-gated) or — once the cron flag ships its schedule — batches refresh in the background; learners see the "For You" cards on their dashboard and in the catalog feed; dismiss or enrol.

**Who uses it.** Learners (receive), managers (`:generate`, `:manage_all`).

**Admin & flags.** 3 flags (master / live_api / auto_cron, default OFF), 3 capabilities, 1 log table, 4 test suites + block render test.

**Works with.** `sentientia_catalog` ("For You" feed), `sentientia_skills` (profile signal), `block_sentientia_recommendations`.

---

### Analytics — `local_sentientia_analytics` · v1.0.1-beta · Beta

**What it is.** The curated L&D analytics dashboard: engagement, completion rate, time-to-completion, top courses, top tenants — KPI tiles with drill-down, no query-writing required.

**Key features**
- KPI tiles + per-KPI drill-down pages + CSV export.
- Read-only by design: aggregates over core Moodle and Sentientia tables with no schema of its own — nothing to migrate, nothing to corrupt.
- Site-wide view for admins; tenant-scoped views for tenant managers.
- Charts vendored locally (Chart.js) — CSP-safe, no CDN dependency.

**How it's used.** L&D admin opens the dashboard → reads the funnel → clicks a tile to drill into the population behind it → exports for the deck.

**Who uses it.** L&D admins, tenant admins, leadership (via exports).

**Admin & flags.** Gated by the core `viewreports` capability.

**Works with.** `sentientia_skills` (skill radar), `sentientia_reports` (ad-hoc sibling), `sentientia_compliance_report`.

---

### AI Content Translation — `local_sentientia_translate` · v0.2.0-alpha · Alpha (flag-gated) · ADR-016

**What it is.** Paste English course content, get a native-script translation into Hindi, Marathi, Kannada, or Swahili — with **guaranteed brand-name preservation** and a side-by-side diff the admin must review before anything saves.

**Key features**
- 4 target languages across 3 scripts (Devanagari ×2, Kannada, Latin).
- **Brand preservation engineered, not hoped for**: a default-protected term list (Airpay, Sentientia, UPI, RBI, KYC, PAN, Aadhaar, FIU-IND, NEFT, RTGS, IMPS, SCORM) kept verbatim with zero config, plus per-customer script-override maps (e.g. render "Airpay" in Kannada script for `kn`) applied as a deterministic whole-token, longest-first post-process — unit-testable without the API, and one customer's brand map can never leak into another's.
- Human-review diff before save; nothing auto-saves. Full lifecycle per request (pending → translated → saved/discarded/failed).
- Unified admin queue dashboard: stats cards (Total/Pending/Saved/Failed), status + language filter chips, recent-translations table — scoped to what the viewer is allowed to see.
- Mock-mode smoke verified in the rollout gate (queue add → process).

**How it's used.** Admin opens Translate → pastes source, picks target language → [CONFIRM] → reviews the diff → saves or discards. Brand admins maintain the override map on its own page.

**Who uses it.** L&D admins/translators (`:translate`), brand managers (`:manage_brands`).

**Admin & flags.** 2 flags (master / live_api, default OFF), 3 capabilities, 2 tables, 5 PHPUnit suites, 99 lang keys at parity.

**Works with.** The 5-locale platform parity drive; T.1 will write back into course content; T.2 connects to the voice pipeline.

---

### Reports — `local_sentientia_reports` · v1.1.1 · Stable

**What it is.** The saved-report builder: define a report once (columns, filters, parameters), run it on demand, export it — the self-serve ad-hoc reporting hub for L&D and compliance, replacing the inherited vendor reporting stack with a Moodle-native pattern.

**Key features**
- Saved report definitions with parameters and recipient lists.
- Run-on-demand with live results; CSV/XLSX export.
- A deliberate capability split: `:export` is read-only-with-data so compliance auditors can pull data without being able to edit definitions.
- The legacy LearnerScript vendor reporting blocks remain in the tree (patched and crash-guarded during the cron gauntlet) until report parity is verified — a documented transition, not a silent gap.

**How it's used.** L&D builds "Completions by department, last quarter" once → reruns it monthly → auditors export without edit rights.

**Who uses it.** L&D admins (build), compliance (export), managers (consume).

**Admin & flags.** 3 capabilities, 1 table.

**Works with.** `sentientia_analytics` (curated sibling), `sentientia_compliance_report`, `sentientia_emails` (scheduled delivery is a tracked open item).

## 4.5 Communications & Reach

*Email always; web push to phones and desktops; WhatsApp and SMS for India-realistic delivery. One rule engine decides when and what; the learner decides where.*

### Notification Rules Engine — `local_sentientia_notifications` · v1.4.2 · Stable

**What it is.** The dispatcher above the channels: admins define WHEN and WHAT to send once, and the rule engine routes through whichever channels are enabled for each user — email, WhatsApp/SMS, push — without per-channel forking.

**Key features**
- Rule definitions: trigger + audience + channel allow-list + template.
- Per-user channel-routing preferences with tenant defaults as fallback.
- Send-attempt audit log per channel — and a deliberate `:viewlogs` capability split so compliance can see delivery without edit rights.
- Manager aggregate rules: `monthly_summary` (team snapshot digest) and `manager_nudge` (fires when a manager has 3+ overdue reports) — migrated onto the org-model seam and **proven behaviour-identical against production data: 117 managers with exact (team-size, completions) matches**.
- Scheduled rule walker + replay/dedup CLI tooling.

**How it's used.** Admin opens the rule registry → defines "compliance overdue → managers, weekly, email+WhatsApp" → the engine takes it from there. Delivery questions go to the log table, not to guesswork.

**Who uses it.** Site/tenant admins (rules), compliance (logs), everyone (recipients).

**Admin & flags.** 3 tables, 3 capabilities, 20 PHPUnit methods (channel-routing matrix is the deepest suite). Consumes the channel master flags (`engagement.whatsapp.*`, `engagement.sms.enabled`).

**Works with.** `sentientia_emails`, `sentientia_whatsapp`, `sentientia_pwa` (the three channels), `sentientia_org` (manager grouping).

---

### Email Engine — `local_sentientia_emails` · v1.1.2+ · Stable

**What it is.** The branded transactional-email engine: admin-editable Mustache templates with token substitution, per-tenant overrides, 11 rule types, ramping reminder cadences, certificate-PDF attachment, and a delivery log built for audit retention.

**Key features**
- **11 rule types** spanning cron-driven (course not started, deadline approaching, compliance enrolled/reminder/overdue, weekly manager escalation, ramping course-incomplete) and event-driven (new course published, course completed).
- **Completion email with the certificate PDF attached**: on course completion, in-flight reminders are stamped `suppressed_completion` (the audit trail stays honest) and a congratulations email goes out with the freshly issued certificate attached — temp files cleaned up after send.
- **Ramping reminders**: per-rule JSON cadence (e.g. days 1, 3, 7, 14, 21 after enrolment), a per-user reminder cap, auto-stop on completion, and calendar-day idempotency (ops can re-run cron during an incident without double-sending).
- Per-tenant template overrides (subject/body), live preview, and a token system ({firstname}, {tenant_name}, {login_url}, {designation}…) — tenant-templated welcome mail with token substitution verified end-to-end in the rollout gate.
- 6 capabilities split so auditors can hold `view_logs` without any edit rights; a certificate-emails audit CLI reports by tenant/status.
- Defensive engineering: the completion observer can never throw (a failure must never block a learner's completion record).

**How it's used.** Admin edits a template with live preview → enables the rule → the hourly walker and event observers do the rest. "Did Alice get her welcome mail?" is a log-table lookup.

**Who uses it.** Tenant admins (templates), L&D (rules), compliance (logs), learners (recipients).

**Admin & flags.** 4 tables, 26 PHPUnit methods. Rule-level enable/disable lives on each rule row.

**Works with.** `tool_certificate` (PDF attach), `sentientia_notifications` (channel routing), `sentientia_users` (welcome on import), the tenant registry (allow-list).

---

### WhatsApp / SMS Bridge — `local_sentientia_whatsapp` · v0.4.0-alpha · Alpha (mock-complete, live confirm-gated)

**What it is.** WhatsApp Business API plus SMS fallback as first-class notification channels — built for Indian regulatory reality: DLT-registered templates, TCCPR/DPDP-compliant consent, and full delivery audit. SMS reaches field staff with no data connectivity.

**Key features**
- **Consent done right**: per-user, per-rule-type channel opt-in with an append-only audit of every preference change. Default OFF; admins cannot force-enable a user (a hard product rule). The consent flow saves + audits verified in the rollout gate (E2E dry-run ALL PASS).
- **DLT template registry** (India's regulatory template regime for SMS): approved templates seeded on install, matched at send time — no template, no send.
- **Content-event triggers** (flag-gated, default OFF): new course published (announce-once semantics — re-edits don't re-spam), course due within 48h, certificate ready, and learning-path milestone crossings (25/50/75/100%) — each with a 6-hour per-(user, template, context) anti-spam throttle.
- Channel router picks WhatsApp vs SMS vs nothing based on prefs + flags + template availability; every send attempt logs a precise outcome (`sent / mocked / opted_out / no_template / no_mobile / throttled / flag_off / failed`).
- Delivery analytics: sent/delivered/read/failed per template per tenant.
- The **entire pipeline runs in mock mode** end-to-end (preference lookup → DLT match → render → send → log) — demoable with zero external calls; the live flip awaits DLT credentials and is [CONFIRM]-gated.

**How it's used.** Learner opts in on their preferences page → from then on, deadline nudges and certificate-ready pings arrive on WhatsApp; field staff without data get the SMS fallback. Admin watches delivery analytics per template.

**Who uses it.** Learners (opt-in + receive), tenant admins (opt-in status view), site admins (templates + API config), compliance (consent audit).

**Admin & flags.** 4 tables; flags `engagement.whatsapp.enabled`, `engagement.sms.enabled`, reminder/overdue sub-flags, and the plugin-owned content-notifications flag — every channel independently switchable. 50 PHPUnit methods across 5 suites, all mock-mode.

**Works with.** `sentientia_notifications` (same triggers as email), `sentientia_courses` (the <48h reminder surface), `tool_certificate` (cert-ready event).

---

### PWA & Web Push — `local_sentientia_pwa` · v0.5.3-alpha · Alpha (flag-gated) · ADR-003/ADR-005

**What it is.** The plugin that turns any Sentientia deployment into an installable app: home-screen icon, offline fallback, and standards-grade web push notifications — **self-hosted, no third-party push vendor, no per-message fees**.

**Key features**
- Install experience: web manifest endpoint, service worker (cache-first static assets, network-first HTML), "Add to Home Screen" prompt with a learner-controllable dismiss, offline.html fallback. Per-customer manifest/branding resolution.
- **Web push with our own crypto** (ADR-003): ES256 VAPID JWTs and RFC-8291 `aes128gcm` payload encryption implemented in-house and **verified end-to-end on the production-scale 5.2 stack — VAPID JWT + payload integrity ALL PASS** in the rollout gate. Subscription keys are stored encrypted at rest; the VAPID private key is envelope-encrypted with a master key.
- A notification bridge hooks Moodle's message pipeline, so course completions, exam reminders, and classroom sessions can arrive as native-feeling push notifications.
- Per-user opt-in preferences page and per-(user, browser) subscription management; append-only tenant-scoped push log; admin surfaces for VAPID lifecycle, delivery log, and stale-subscription pruning.
- Native-wrapper compatibility groundwork for app-store presence (ADR-005: PWA first, Cordova/Capacitor wrappers as a later business decision).

**How it's used.** Learner accepts the install prompt → the academy lives on their home screen → they opt into push → deadline reminders land on their lock screen. Admin generates the VAPID keypair once via CLI and watches the delivery log.

**Who uses it.** Learners (install + receive), site admins (`:manage` operations).

**Admin & flags.** 5 flags (master, install prompt, push master, reminders sub-channel, overdue sub-channel — mirror-symmetric with the WhatsApp sub-flags so channels roll out independently). 2 tables, 2 capabilities, **34 PHPUnit methods including a 16-method crypto-audit suite and 13 tenant-isolation tests**.

**Works with.** `sentientia_notifications` (channel), the theme (manifest + meta), `sentientia_core` (per-customer branding for icons).

## 4.6 People, Org & Governance

*The enterprise spine: who people are, who they report to, what they may do, how requests flow, and how all of it stays compliant and auditable.*

### Users & HRMS Sync — `local_sentientia_users` · v2.7.1+ · Stable

**What it is.** User management built for enterprise reality: a 24-column HRMS importer with scheduled sync, public self-signup with bot protection, rich employee profiles, bulk operations, and welcome emails — the front door for every account on the platform.

**Key features**
- **HRMS importer**: 24-column CSV (username/email/name required; employee ID, manager, designation, dates and 17 org fields optional), dry-run preview, scheduled cron sync, and full per-run audit (a runs table plus a per-row error table with line numbers and error codes). Joiners get tenant-templated welcome emails with credentials; leavers are deactivated; supervisor changes follow the org tree. Import + lifecycle verified end-to-end in the rollout gate (create/skip/fail counts, idempotent re-run).
- **Public self-signup**: honeypot bot protection, reCAPTCHA gate (active when keys are configured), privacy-policy + T&C consent, email confirmation — signup E2E verified over HTTP (honeypot + CSRF validated, account created, success page rendered).
- Profile page with gamification/skills enrichment and an account-actions bar (edit profile / change password / preferences).
- Admin listing with filter chips and bulk actions; CSV export behind a dedicated read-only `:export` capability; suspension freezes login + enrolments.
- **Tenant-scoped supervisor lookups proven by test**: the 7/7 tenant-isolation suite exists precisely to guarantee a non-siteadmin only ever sees their own tenant's users.

**How it's used.** IT points the nightly HRMS CSV at the watch folder once; from then on the workforce mirror maintains itself. Tenant admins add or bulk-import users scoped automatically to their tenant.

**Who uses it.** Site admins (sync config), tenant admins (day-to-day), public visitors (signup), every user (profile).

**Admin & flags.** 7 capabilities, 2 audit tables, **70 PHPUnit methods across 8 suites** — the most-tested people plugin.

**Works with.** `sentientia_org` (hierarchy), `sentientia_emails` (welcome mail), `sentientia_lifecycle` (joiner/leaver events), `sentientia_platform` (user types).

---

### Org Hierarchy — `local_sentientia_org` · v1.4.1+ (1.5.0 with compat shim) · Stable

**What it is.** The organisation tree: tenants, divisions, departments — each with its own name, logo, and colour scheme — plus the access library the rest of the platform uses to answer "what can this user see?"

**Key features**
- Hierarchical org table (path-based, e.g. `/1/2/3`: tenant → division → department) with per-org branding (logo, brand/button/hover colours, theme scheme).
- A 6-method access library that is API-compatible with the vendor layer it replaced — existing role assignments kept working through the transition.
- Tenant manager, org CRUD (children/descendants/tenants), per-tenant settings UI, and a data-migration CLI from the legacy vendor table.
- **Anti-corruption compatibility shim (v1.5.0)**: when the retired vendor plugin's classes are referenced by kept legacy report blocks, the org plugin aliases them onto its own implementations at boot — zero vendor-file edits, verified by a 20/20 resolver check and a learner course-view that went from a 500 to a 200.
- 6 capabilities for split org administration (own organisation vs own departments vs multi-organisation).

**How it's used.** Mostly invisible: it is the substrate every tenant-scoped query and per-tenant logo resolution rides on. Admins touch it through the org admin and tenant-settings pages.

**Who uses it.** Site admins (structure), tenant admins (their subtree), every plugin (the access library).

**Works with.** Everything — it is foundational. `sentientia_core` (the future org model it feeds), the theme (per-tenant branding).

---

### Roles & Permissions — `local_sentientia_roles` · v1.1.3-beta · Beta

**What it is.** A role-management layer on top of Moodle's role admin that adds the three things compliance teams actually ask for: a filterable role overview, an **append-only audit log of every capability change**, and Excel-friendly CSV exports.

**Key features**
- Tenant-aware role listing with archetype/substring/capability-count filters.
- Every capability mutation made through the UI writes an audit row — who changed which capability, when, on which role — with the role shortname denormalised so the log survives role deletion, and the changer's tenant snapshot for attribution.
- CSV export of capabilities-by-role and of the audit log (UTF-8 BOM).
- Deliberately supplements rather than replaces core role admin — changes go through Moodle's own permission APIs, so role behaviour stays exactly stock.
- `:view`/`:audit` split so an auditor can hold read-only audit access; 4 web services with input-size validation.

**How it's used.** Admin answers "who changed which capability when, with what justification" from one filterable page instead of trawling raw logs; quarterly access reviews are a CSV download.

**Who uses it.** Site admins (manage), compliance auditors (audit + export).

**Admin & flags.** 1 audit table, 5 capabilities, **71 PHPUnit methods**.

**Works with.** Moodle core roles, `sentientia_compliance_report` (governance posture).

---

### Manager Workspace — `local_sentientia_manager` · v1.3.3 · Stable

**What it is.** The line manager's command centre: team view, member drill-down, approval workflow with a real state machine, and budget/quota allocations.

**Key features**
- Team dashboard resolved through the org seam (reporting line from HRMS today, the Sentientia org model after cutover — no caller change), **proven behaviour-identical on production data**.
- Approval state machine (pending → approved → revoked, with escalation and re-routing on supervisor change) — 20 dedicated test methods.
- Allocations: budget / time / training-quota grants per manager.
- Team performance summary + CSV export; per-member detail page.
- Considered empty-state UX: a manager-shell user with zero direct reports gets a friendly localized empty state, not an error (a real QA finding, fixed and visually verified).

**How it's used.** Manager logs in → team tiles and KPI table (verified: authenticated render with team/KPI markers) → drills into an overdue member → actions the approval queue → exports for the skip-level review.

**Who uses it.** Managers and supervisors; HRBPs with report-view rights.

**Admin & flags.** 2 tables, 3 capabilities, 28 PHPUnit methods.

**Works with.** `sentientia_request` (approvals source), `sentientia_org`/`sentientia_core` (reporting line), `sentientia_compliance_report` (team compliance), `sentientia_notifications` (digests).

---

### Course Requests — `local_sentientia_request` · v1.2.2 · Stable

**What it is.** The learner-to-manager request workflow: see an interesting course outside your default audience, ask for it, manager approves, enrolment happens. (Distinct from the tenant-to-tenant course-share requests in `sentientia_courses` — this one is person-to-manager.)

**Key features**
- Request records with full decision audit (status, approver, decision time, rationale).
- Approver inbox; admin all-requests view; an `overrideroute` capability for re-routing to an alternate approver.
- Notification dispatcher + scheduled escalation task; audit events.
- 4 capabilities mapping cleanly to the request/approve/oversee/re-route duties.

**How it's used.** Learner clicks "Request access" on a catalog course → adds a reason → manager's inbox (with SLA nudges) → Approve enrols them; Reject requires a reason the learner sees.

**Who uses it.** Learners (request), managers (approve), admins (oversight).

**Works with.** `sentientia_manager` (the inbox lives in the manager workspace), `sentientia_catalog` (the request button), `sentientia_notifications`.

---

### Platform Foundation — `local_sentientia_platform` · v1.7.0 · Stable

**What it is.** The shared infrastructure every Sentientia plugin stands on: the **feature-flag registry**, the **customer-brand registry**, the tenant-equality guard, the polymorphic user-type system, structured logging, and the operational CLI toolbox.

**Key features**
- **Feature flags**: the flag + flag-audit tables behind the Switchboard — every flag set/resolve/audit operation, with the 5-level customer/tenant precedence (verified by CLI dry-run + PHPUnit in the rollout gate, including flags surviving a clone + major upgrade).
- **Customer branding**: the `customer_brand` table (ADR-008) the renderer and PWA manifest read — logo light/dark, colours, icons, display name per customer (brand resolver verified 20/20).
- **Tenant guard**: a single static helper (`require_access`, `viewer_can_access`, `sql_filter`) that every web service and query uses for tenant scoping — centralised because 10 of 11 blocking security findings in the platform's hardening audit traced back to ad-hoc tenant checks.
- **Polymorphic user types (ADR-017)**: user-type classification plus per-type profile tables (employee / consumer / operator / partner-employee) and a provider factory — so a consumer is a first-class citizen, not an employee with empty fields. 2,880 production-shaped accounts classified (2,196 employee / 682 consumer / 2 operator); fresh installs create all 5 tables (verified by a clean PHPUnit init).
- **Operations toolbox**: structured logger, cron-health publisher, PII masking for dev environments, orphan-file finder, brand-resolver verifier, user classification CLI, and the task-registration repair CLI that became a mandatory deploy step after it caught 17 silently-dead cron registrations.

**How it's used.** Indirectly by everyone; directly by site admins through the Switchboard and the ops CLIs.

**Who uses it.** Every plugin; site admins; the deploy runbook.

**Works with.** Literally everything — never disable it.

---

### Multi-Customer Core — `local_sentientia_core` · v0.7.0-alpha · Alpha (dormant by design) · ADR-019/020/021

**What it is.** The product's future-proofing layer: three clean "seams" that decouple Sentientia from its customer-zero heritage so the same codebase serves any Enterprise N — each seam behind a default-ON legacy flag, meaning **production behaviour is byte-identical until an operator deliberately (and reversibly) flips the source**.

**Key features**
- **Tenant identity seam** (ADR-019): the single sanctioned API for "which tenant is this user in?" — ~22 call sites across 11 plugins already migrated onto it.
- **Org hierarchy seam** (ADR-020): manager/reports resolution with a dual-write reconciler that mirrors the legacy org graph into the new model on a 4-hour cycle (only when enabled), backfill + parity CLIs, and a hard cutover gate: **100% parity required — rehearsed on 2,883 production-shaped users: 160 org units, 2,883 members, 0 mismatches**.
- **Tenant registry seam** (ADR-021): tenants and customers as data (`customer` + `tenant` tables), not hardcoded IDs — the literal mechanism that makes "a new customer is a registry row" true. Seeded dormant, parity 100%.
- **First-party substrate ownership** (ADR-024): the plugin can create the 37 user + 18 course compatibility columns on a vanilla Moodle — Sentientia installs from scratch with no vendor dependency.
- Standalone by design: every delegation to the heritage layer is existence-guarded with an inline fallback, so the plugin ships for a customer with no Airpay code present.

**How it's used.** Today: dormant seams keeping customer-zero stable. Tomorrow: the activation runbook is written (deploy → seed → parity gate → flip per-tenant, smallest tenant first, instantly reversible).

**Who uses it.** Engineering/operations; indirectly, every future customer.

**Admin & flags.** 3 legacy flags (default ON) + dual-write flag (default OFF); 4 tables; 41 PHPUnit cases across the seams; `:managetenants` admin UI.

**Works with.** `sentientia_platform` (flags), `sentientia_org` (legacy source), every migrated caller.

---

### Privacy Hub — `local_sentientia_privacy` · v1.0.1 · Stable

**What it is.** The GDPR/DPDP request hub: data-export and right-to-erasure requests in one dashboard, orchestrated across every plugin's privacy provider, plus an append-only consent ledger.

**Key features**
- Per-user data-export / data-deletion request tracking, working with Moodle's built-in data-privacy tooling.
- Append-only consent ledger for marketing/analytics opt-ins — consent is provable, with timestamps.
- Rides the platform-wide convention: Sentientia plugins ship `privacy/provider.php` classes declaring their user data for export and deletion (several with dedicated provider test suites).

**How it's used.** A Public learner requests account deletion → 30-day cooling-off → personal data erased, certificates anonymised but still verifiable. An auditor asks "when did this user consent to marketing?" → one ledger row.

**Who uses it.** All users (their rights), admins (`:manage`), DPO/compliance.

**Works with.** Every plugin's privacy provider; `sentientia_whatsapp` (channel consent); the DPDP statement page in `sentientia_pages`.

---

### Compliance Reporting — `local_sentientia_compliance_report` · v1.0.0 · Stable

**What it is.** The statutory-training audit engine: which courses are mandatory per tenant, who has completed them, who is overdue, who is exempt and why — with scheduled snapshots and escalation wiring.

**Key features**
- Per-tenant registry of compliance-mandatory courses with deadline rules.
- Periodic per-(user × course) compliance snapshots via scheduled task; overdue flagging feeds the escalation pipeline.
- **Manager exemption workflow** with rationale and expiry — auditable mercy.
- Email-send audit joined with the email engine's log (certificate PDFs included).
- **PII-protected export**: the full-matrix export (names, emails, employee IDs) is gated behind a dedicated `:export` capability (RISK_PERSONAL) — line managers can view but not bulk-export; the server gate and the button visibility use the same check so they cannot disagree (6/6 permission tests green).
- The single-query audit promise verified: every nudge and escalation around any deadline is one query on the signed-bucket reminder log.

**How it's used.** Compliance officer opens the dashboard (reachable from their sidebar — verified in the persona walk) → green/yellow/red/grey per person per requirement → drill into reds → grant or review exemptions → export (if entitled) for the regulator.

**Who uses it.** Compliance officers, tenant admins, managers (team view), auditors.

**Works with.** `sentientia_courses`/`sentientia_exams` (deadlines), `sentientia_recompletion` (renewal cycles), `sentientia_emails` (escalations), `block_sentientia_compliance` + `block_sentientia_cert_health` (dashboard widgets).

---

### Proctoring — `local_sentientia_proctoring` · v1.0.3 · Stable + `quizaccess_sentientia_proctoring` · v1.1.1 · Stable

**What it is.** Exam integrity in two halves: the proctoring **engine** (identity verification, in-attempt event capture, recording storage, post-attempt analysis, human reviewer queue) and the **quiz access rule** that attaches it to any Moodle quiz.

**Key features**
- Pre-attempt consent + identity verification flow (selfie + government-ID hash — hashes, not raw documents).
- Append-only in-attempt event capture: focus loss, face-count changes, audio anomalies.
- Recording chunks stored in S3 with pointer tables; post-attempt AI/heuristic analysis.
- Human reviewer queue with cleared/flagged/rejected decisions — a person, not an algorithm, makes the final call.
- A `:bypass` capability for designated test administrators; scheduled finalize + purge tasks; GDPR provider.
- The access rule attaches per-quiz — proctoring is a per-exam choice, not a platform mandate. Rule integration verified **9/9 in PHPUnit** in the rollout gate; available to both employee and Public-tenant exams.

**How it's used.** Author enables the rule on a high-stakes exam → learner sees the consent + identity step before the attempt opens → events and recordings accumulate during the attempt → analyzer pre-screens → reviewer clears or flags.

**Who uses it.** Learners (the attempt), exam admins (configuration), reviewers (`:review`), compliance.

**Works with.** `mod_quiz` (delivery), `sentientia_exams` (scheduling layer).

## 4.7 Integrations

*Meet the enterprise where it already lives: calendars, Microsoft 365, HRMS, and Teams.*

### Calendar Sync — `local_sentientia_calendar` · v1.2.0-beta · Beta (flag-gated) · ADR-013

**What it is.** Learners' course deadlines, classroom sessions, and exam dates appear inside the calendar they already use — Outlook, Google Calendar, Apple Calendar — without keeping the LMS open in a tab.

**Key features**
- **Phase 1 (shipped): outbound ICS feed** — a personal subscription URL authenticated by a 64-character random token (no OAuth needed for read-only sync). One click to copy into any calendar app; Regenerate revokes the old token instantly. Tokens carry abuse-forensics fields (last use, IP, count) and revoked tokens purge after 90 days.
- Three event categories, each its own sub-flag: course completion deadlines, classroom/ILT sessions (past + future), exam close dates (90-day forward window). Sources degrade gracefully — the feed simply omits a category whose plugin isn't installed.
- **Phase 2.1 (shipped, flag OFF): live OAuth bi-directional groundwork** — PKCE token exchange, refresh, and revoke wired for providers, tokens encrypted at rest, per-provider connect/disconnect status UI.
- RFC-5545 conformance unit-tested (30 PHPUnit methods across token lifecycle + ICS building); the ICS ORGANIZER is white-labelled (rollout-gate verified).
- Visual evidence includes Outlook-on-the-web actually displaying the subscribed feed.

**How it's used.** Learner opens Calendar Sync → copies their personal URL → pastes into Outlook/Google → deadlines appear and stay current automatically.

**Who uses it.** Learners and trainers; admins only touch the flags.

**Admin & flags.** 5 flags (master OFF; per-category ON within it; OAuth OFF), 2 capabilities, 2 tables.

**Works with.** `sentientia_classroom`, `sentientia_courses`, `sentientia_exams` (event sources), `sentientia_m365` (future Graph sync sibling).

---

### Microsoft 365 — `local_sentientia_m365` · v0.2.0-alpha · Alpha (scaffold, deliberately inert)

**What it is.** The bridge to a customer's Microsoft 365 tenant — designed so SharePoint SOP libraries feed the content pipeline and Outlook enablement sessions auto-create LMS classrooms. Today it is the **security-first scaffold**: OAuth, storage, privacy, and admin surfaces are built; every Graph call deliberately refuses to fire until Phase C.2 is unlocked.

**Key features (built)**
- OAuth PKCE flow (no client secret in the database — by policy), tokens encrypted at rest with Moodle's Sodium-based core encryption, decryption failures never leak their cause.
- Tokens scoped per (user, customer) — one person can link two distinct M365 tenants under a multi-customer deployment without collision.
- Admin landing dashboard: configuration status cards, readiness banner, the C.1→C.6 roadmap with done/planned badges.
- Full GDPR provider (token rows export + erase; the Graph data-flow declared).
- An explicit capability (`:use`, default false everywhere) and a default-OFF flag per customer.

**The roadmap is honest:** C.2 real Graph calls (per-call confirm-gated) → C.3 SharePoint ingestion into the SOP parser → C.4 Outlook-to-classroom sync → C.5 Teams attendance → completion records. Activation requires the customer's Azure app registration. **[NITIN DECIDES]** the Azure registration timing.

**Who uses it (when live).** Learners (connect account), admins (config), the content pipeline (SharePoint feed).

**Works with.** `sentientia_calendar` (shared OAuth patterns), the SENTIENTIA pipeline, `sentientia_classroom`.

---

### Integrations Hub — `local_sentientia_integrations` · v1.1.1-beta · Beta

**What it is.** The single home for outbound integrations too small for their own plugin — HRMS API client (Keka), Microsoft Teams webhook notifier, a generic inbound webhook receiver — with one rule: **every external call writes an audit row**.

**Key features**
- Keka HRMS API client (the API-side complement to the CSV sync).
- Teams webhook notifier for channel announcements.
- Generic inbound webhook receiver for external systems pushing events in.
- Append-only integration log: service, endpoint, status, response excerpt, latency — diagnostics without log-diving.
- All clients tested against mocked HTTP (11 PHPUnit methods); no surprise outbound traffic.

**How it's used.** Configure once; afterwards, "did the Teams notification go out?" is an audit-log lookup.

**Who uses it.** Site admins, integration engineers.

**Works with.** `sentientia_users` (HRMS), `sentientia_notifications` (channel fan-out).

---

## 4.8 Dashboard blocks

Six Sentientia blocks complete the dashboard experience (plus the inherited vendor reporting blocks, kept patched during the reporting transition):

| Block | Version | What it shows | For whom |
|---|---|---|---|
| `block_sentientia_cert_health` | 1.0.0 · Stable | Certificate-email delivery health: 3 KPI cards over a 7-day window (emailed / failed / suppressed) with severity rules — any failed send goes Critical because compliance relies on certificates reaching people. **WCAG 2.1 AA engineered**: landmark region, grouped aria-labels, severity conveyed three ways (colour + badge + label); axe-core verified 0 violations; 6/6 PHPUnit. Renders only for site admins. | Site admins |
| `block_sentientia_cron_health` | 1.0.0 · Stable | Scheduled-task health (stuck/failing tasks) — the same accessibility pattern as cert-health; part of the verified "S9: cron green" rollout-gate row (103 executed / 0 failed). | Site admins |
| `block_sentientia_trainer` | 1.0.0 · Stable | The trainer's 10 most recent assigned classroom sessions, with a standalone trainer dashboard page; renders on dashboards, hidden inside courses. | Trainers |
| `block_sentientia_leaderboard` | 0.1.0-alpha | Live top-5 leaderboard widget (full SSE behaviour described in §4.2); placeable on dashboard, site index, or course pages. | Learners |
| `block_sentientia_recommendations` | 0.1.0-alpha | The learner's current AI recommendation batch with "Why this?" rationales; renders nothing when the flag is off or no batch exists. | Learners |
| `block_sentientia_compliance` | 1.0.0-beta | Compliance status widget pairing with the compliance-report engine. | Compliance, managers |

# 5. The experience layer

*Enterprise software people don't hate using. The Sentientia theme is a standalone design system — not a skin on someone else's theme — and the experience claims below are test results, not adjectives.*

## 5.1 The Sentientia theme & design system

**`theme_sentientia` (canonical fork of the in-house `airpayux` theme) · v1.0.46-beta+ · 700+ files · zero upstream theme dependency (`$THEME->parents = []`)**

- **Owned, not inherited.** Every layout (10), template (~50+), and SCSS partial (55+) belongs to the product. There is no upstream theme that can break it on update, and the inherited vendor branding has been systematically removed (with GPL attribution correctly retained on Moodle-core-derived code — a documented, lawyer-friendly de-brand).
- **Design tokens, not magic numbers.** Primary `#0066A7`, accent `#0f7a73`, background `#F2F4FB`, Montserrat 400–800, an 8px spacing grid, 8–20px radii — all defined once in a token sheet, consumed everywhere. The 22 C-suite-approved UX prototypes are the design reference.
- **App-shell experience.** A fixed role-aware sidebar + sticky topbar "cockpit" for dashboards and admin surfaces, with content-scroll surfaces (like the catalog) deliberately exempted; a built-in **role switcher** in the sidebar lets multi-role users flip between Administrator and Employee views (full round-trip verified over HTTP, with active-state markers and `aria-current` flipping correctly).
- **Role detection built in:** the theme knows whether the viewer is a learner, manager, or trainer and shapes navigation accordingly (8 dedicated tests).
- **Operational hygiene as a feature:** a WS-contract drift scanner lives inside the theme's test suite (born from a real bug), the maintenance page renders white-labelled even with the database down, and Chart.js is vendored locally (CSP-safe, offline-capable).

## 5.2 Per-customer white-label branding

Each customer overrides — from configuration, with **zero code changes**:
- Logo (light + dark variants), favicon, PWA icons (192/512)
- Primary + accent + background colours
- Typography
- Display name everywhere it is rendered — login, emails, calendar ORGANIZER, WhatsApp consent text, push payloads, in all 5 languages (the June-2026 white-label audit verified every rendered customer-name string resolves from configuration; the brand resolver passes 20/20 checks)

Per-tenant branding nests inside per-customer branding: on customer-zero, the Airpay, Public, and ZEEA tenants each present their own logo and accent within the customer's brand system.

## 5.3 Dark mode

Light + dark are both first-class: the dark cascade is token-driven (no per-component forks), and a dedicated WCAG-AA contrast wave fixed real findings (including a 1.06:1 contrast bug) across catalogue chips and framework text utilities. Components like the certificate celebration card are explicitly pinned so the dark flip never inverts them into illegibility.

## 5.4 Accessibility (measured, not promised)

- **0 serious/critical axe-core findings across 5 personas × 4 surfaces** (WCAG A + AA rulesets; serious/critical fail the build by design — Gate 2 of the quality system).
- Severity is never colour-only: status widgets convey state three ways (colour + text badge + aria label) per WCAG 1.4.1; small-text palettes exceed the 4.5:1 AA contrast ratio.
- Keyboard + screen-reader parity work: `:focus-visible` coverage across interactive components, `aria-live` announcements, landmark regions on dashboard widgets.
- **Vestibular safety (WCAG 2.3.3):** all motion rides duration tokens that collapse to 0ms under `prefers-reduced-motion` — and a stylelint rule bans hardcoded transition timings so it stays that way.

## 5.5 Mobile

Responsive across 7 verified breakpoints (1400/1200/992/768/**590 primary mobile**/480/380): the nav collapses to a hamburger, dashboard tiles stack, tables scroll within their cards, the SCORM player goes full-screen, forms expand to full width. Mobile rendering is part of the visual-evidence discipline — every UI change ships desktop + 590px screenshots.

## 5.6 Installable app (PWA) + push

Covered in depth at §4.5: install prompts (Android one-tap, iOS guided), offline fallback, and self-hosted web push with no per-message vendor fees — verified end-to-end on the production-scale stack. The PWA manifest resolves per-customer branding, so each customer's installed app carries their own icon and name.

## 5.7 Five languages, enforced

English, Hindi, Marathi, Kannada, Swahili — with **tooling-enforced key parity** (Hindi at 100%, chrome strings verified 178/178 across all 5 locales). Language switching is per-user and immediate; notification templates, AI prompts (Hindi quiz generation), and DLT message templates follow the learner's language. Multi-language is not a translation file bolted on — it is a platform invariant the CI guards.

## 5.8 Verified browser matrix

The render-smoke gate (every persona × surface: AMD boot, zero template leaks, landmarks present, zero console errors) passes **5/5 personas on Chromium and 5/5 on branded Google Chrome**; WebKit passes all render/leak/landmark checks with one environment-specific transport flake on the local single-process test rig (re-verified on the CI Linux tier); Firefox runs on the CI tier. The verification stack is the Moodle 5.2 production-scale clone — the stack that ships.

# 6. Sentientia Live — the built-in live engagement engine

**`local_sentientia_live` · v0.2.2-alpha · flag-gated · ADR-004**

Most enterprises run training sessions with the LMS in one tab and a paid Mentimeter/Slido subscription in another. Sentientia Live removes the second tab — and the second subscription. It is a **Mentimeter-class live audience engagement engine built into the LMS**: same login, same roles, same tenant isolation, same audit posture, results stored where the rest of the learning data lives.

## What a session looks like

1. **Trainer creates a session** from the trainer dashboard — adds slides, gets a join code.
2. **Audience joins by code** from any device — phones included; no app, no account gymnastics (anonymous joining exists behind a compliance-friendly default-OFF flag, with bearer join tokens).
3. **Trainer runs the show**: advances slides; the **projector view** updates live — audience count, response tallies, charts animating as answers arrive.
4. **Audience answers in real time**; re-submitting updates your answer (one response per person per slide, enforced at the data layer).
5. **Session ends** → results persist; **CSV export** for the analytics trail; the real-time event journal cleans itself up 24 hours later.

## The six question types (all fully implemented)

| Type | What it does | Notable engineering |
|---|---|---|
| **Multiple choice** | Classic poll with a live bar chart | Chart updates pushed per answer |
| **Word cloud** | Audience words form a live tag cloud | Great for workshop energy |
| **Open-ended** | Free-text wall (500-char limit) | **Paginated + moderation support** — trainer controls what the room sees |
| **Rating scale** | Stars or 0–10 NPS | Live **mean/median** computed |
| **Quiz** | Right/wrong with scoring | **Top-10 leaderboard** for competitive sessions |
| **Ranking** | Order the options | **Borda-count** aggregate + average position — mathematically fair ranking |

Each type sits behind its own feature flag, so a customer can roll out "polls and quizzes" without the workshop types (or vice versa).

## The real-time engineering (ADR-004)

- **Server-Sent Events (SSE)** push slide changes and response tallies to every connected screen; responses come back over plain POSTs. No WebSocket daemon to operate, no third-party realtime service.
- A **kill-switch flag degrades to 3-second polling** — the feature survives hostile networks and conservative proxies.
- Capacity engineering is explicit: a 500-concurrent-attendee cap per session protects the web-server worker pool (the documented upgrade path beyond that is a WebSocket daemon); PHP session locks are released early on the stream so a long-lived connection never blocks the user's other tabs; burst math is documented (a 500-person room answering within 5 seconds ≈ 100 writes/sec peak — well within the database's comfort).

## Built like enterprise software, not a toy

- **5 capabilities** (create / run / join / respond / manage-all); trainers with the teacher archetype can create and run sessions out of the box (a real QA finding, fixed with a proper capability back-fill, not a hack).
- **Per-tenant kill switch admin UI**: a site admin sees every `live.*` flag per (customer, tenant) pair and flips rows inline — there is always a rollback path.
- **Accessibility pass completed**: ARIA live regions across trainer and audience surfaces — screen-reader users hear response counts update in real time; charts carry image roles with labels. 264/264 language keys at EN/HI parity.
- **Privacy provider** included; sessions, slides, participants, responses, events all in tenant-scoped tables.
- **Verified**: full create → 6 slide types → run → SSE results cycle seeded and exercised in the rollout gate; the interactive two-browser projector run verified on the 5.1 stack; the SSE stream re-verification on the CI multi-worker tier is explicitly tracked (the local single-process test rig cannot exercise a long-lived stream by construction — an honest environment note, not a gap in the feature).

## Why it matters commercially

- Replaces a per-seat SaaS subscription with a platform capability.
- Results, attendance, and engagement data live in the same reporting/compliance stack as everything else.
- For the demo: it is the single most visibly differentiating five minutes in the product. **[NITIN DECIDES]** which question-type set headlines the first customer-zero live session (compliance-style quiz+poll vs workshop-style word-cloud+open-ended).

# 7. Multi-tenant & white-label architecture, in business terms

This is the moat. Skins are easy; **operating multiple isolated businesses on one platform, provably, is not.**

## 7.1 The three-level model

```
CUSTOMER  (the enterprise that bought Sentientia — e.g. Airpay Payment Services)
   └── TENANT (an isolated business unit — e.g. Airpay internal / Public / ZEEA Tanzania)
          └── ORG TREE (divisions → departments → people, with a supervisor chain)
```

- **Customers and tenants are data, not code** (ADR-021): registry tables, not hardcoded ID lists. Onboarding a new business unit — or a whole new customer — is rows + configuration.
- **Users are typed** (ADR-017): employee, consumer, operator, partner-employee — each with its own profile shape. A paying public learner is not "an employee with empty HR fields"; 2,880 production accounts are already classified.

## 7.2 Data isolation you can demonstrate

- Every tenant-scoped table carries the tenant key; every query goes through the centralised tenant guard (`require_access` / `sql_filter`) — built after a hardening audit traced 10 of 11 blocking findings to ad-hoc tenant checks.
- **Tested, not asserted**: dedicated tenant-isolation suites (7/7 supervisor scoping; 13 push-subscription isolation tests), org parity verified at **100% across 2,883 users**, and cross-tenant ID-guessing rejected at the data layer in the AI plugins.
- Cross-tenant sharing, where wanted, is **explicit and audited** (the course-share workflow) — and even then, completion data never crosses the boundary.
- The planned standing demo tenant doubles as a permanent isolation test: prospect data and customer-zero data on one instance, provably separate (see `DEMO-TENANT-PLAN.md`).

## 7.3 Feature flags: sell exactly the surface they bought

Every feature resolves through a **5-level precedence** (ADR-002):

```
customer+tenant override → customer-wide → tenant → global → registered default (OFF)
```

- Defaults are OFF — installing a plugin changes nothing until someone deliberately enables it.
- The **Switchboard** is the single console for all flags, with a full audit table of every flip.
- Proven resilient: flag state survived a database clone plus a major-version Moodle upgrade in the rollout gate.
- Commercially: packaging tiers map to flag sets. Turning a feature on for one customer (or one of their business units) is configuration with an audit trail.

## 7.4 White-label, audited

The June-2026 white-label ledger (`WHITELABEL-DEBRAND-LEDGER.md`) verified that **every rendered customer-name string resolves from configuration** — login page, transactional emails, calendar invites, WhatsApp consent text, push notifications, the maintenance page (even with the database down) — in all five languages. Per-customer branding (ADR-008) covers logo light/dark, colours, typography, favicon, and PWA icons, resolved by the renderer at request time (no cache purge needed) and verified 20/20 by the brand resolver check.

Product chrome says **Sentientia**; customer chrome says whatever the customer is called. Both are deliberate.

## 7.5 What onboarding Customer N actually takes

From the demo-tenant bill of materials and the install rehearsal — all data and configuration, zero code deploy:

1. A customer registry row + tenant registry row(s)
2. A brand row + two icon PNGs
3. The feature-flag set they bought
4. An org tree + their user feed (CSV/HRMS)
5. Course content (theirs, or pipeline-generated)

A from-scratch install guide (`INSTALL-SENTIENTIA.md`) exists and was validated by wiping and reinstalling the team's own environment; the platform can even self-provision its compatibility substrate on vanilla Moodle (ADR-024). The sandbox migration-rehearsal kit (parity CLI + runbook) has been locally rehearsed with single-row drift sensitivity proven.

## 7.6 The licensing honesty (what we sell)

Moodle is GPLv3, so Sentientia's code is GPL too. The commercial offer is what enterprises actually pay for: **hosted/managed platform, implementation, integrations (HRMS/WhatsApp/payments/M365), the content pipeline, SLAs and support, and the Sentientia brand** — the same model the commercial Moodle ecosystem runs profitably. IP protection lives in trademark, content, configuration know-how, and operations (ADR-001; trademark checklist drafted). Pricing tiers and packaging are decision-ready drafts: **[NITIN DECIDES]** (see `PRICING-PACKAGING-DRAFT.md`, `SUPPORT-SLA-MODEL.md`, `TRADEMARK-CHECKLIST.md` — every figure in them is a placeholder awaiting the owner's call).

# 8. The content pipeline — SOP to SCORM

Every enterprise has the same content bottleneck: shelves of SOPs, policies, and process documents that should be training but aren't, because converting one document into an e-learning module traditionally takes a vendor and weeks. The **SENTIENTIA content pipeline** turns an SOP PDF into a packaged SCORM course through six specialised agents — built and tested end-to-end in mock/local mode, and a **sellable capability of the product**, not an internal tool.

## The six agents

| # | Agent | Input → Output | Discipline built in |
|---|---|---|---|
| 1 | **SOP Parser** | SOP PDF → structured JSON | Max 2,000 words per module — forced atomisation of bloated documents |
| 2 | **Narration** | parsed JSON → narration script | ≤25-word sentences, paced at 130 wpm — written to be heard, not read |
| 3 | **Slides** | narration → slide deck JSON | Max 5 bullets per slide, max 8 words per bullet — no wall-of-text slides, by rule |
| 4 | **Voice** | narration → MP3 voice-over | ElevenLabs AI voice; **cost-gated**: every run is confirm-gated (~$0.30 per 1,000 characters) and PII is stripped before anything leaves the building |
| 5 | **SCORM Packager** | slides + audio → SCORM 1.2 ZIP | Hard validation gates before packaging (see below) |
| 6 | **Uploader** | SCORM ZIP → the LMS | Confirm-gated — nothing lands on a live server silently |

Agents never chain automatically: each runs as its own step, writing its output to disk for the next — every intermediate artifact is inspectable, re-runnable, and auditable.

## The SCORM validation gates (why packages don't bounce)

A package is only built when **all** gates pass: `imsmanifest.xml` at the ZIP root (the #1 cause of LMS rejections), organization structure with resolving file references, a mastery score present (70 is the customer-zero default; **configurable per customer**), and every manifest-listed file physically in the ZIP. Two failed validations is an escalation, not a retry loop.

## Status & activation

The pipeline is demoable today in mock/local mode (a sample pipeline SCORM is part of the demo-tenant content plan). Going live with AI voice-over requires an ElevenLabs budget — deliberately a business decision because it is the only per-unit-cost step. **[NITIN DECIDES]** the voice budget. The M365 integration's Phase C.3 is designed to feed this pipeline directly from a customer's SharePoint SOP library; the translation plugin's Phase T.2 extends it to multilingual voice re-packs.

# 9. Trust & quality — how we know it works

Quality claims in sales decks are cheap. Sentientia's are citations from a test record.

## 9.1 The four-gate quality system (ADR-027)

Built because of a real, recorded lesson — "we ran visual audits and still shipped UI bugs":

| Gate | What it does | Catches |
|---|---|---|
| **Gate 0 — static scanners** | Run at commit time and in CI | The three recurring bug classes: template comment leaks, stale theme references, conflict markers |
| **Gate 1 — render-smoke** | Renders every persona × surface in a real browser | PHP fatals, raw `{{ }}` template leaks, missing landmarks, console errors — failed runs block |
| **Gate 2 — accessibility** | Automated axe-core (WCAG A+AA) per persona × surface | Serious/critical findings **fail the build**; current result: 0 serious/critical across 5 personas × 4 surfaces |
| **Gate 3 — coverage matrix** | An honest ledger of what is and isn't tested | Self-deception |

Plus: a multi-check pre-commit hook (PHP syntax, credential leaks, raw SQL/superglobals, core-file edits, SCORM structure, version formats, conflict markers) and CI gates on every push (PHPUnit, contract drift, conflict markers, Playwright) — several of them born from specific, documented incidents.

## 9.2 The FOOLPROOF campaign (the rollout gate)

The owner's standing rule: **no live deploy until every persona workflow is proven** — foolproof testing → sandbox migration rehearsal → replacement with data intact. The campaign artifact is the persona × workflow matrix (`docs/testing/WORKFLOW-TEST-MATRIX.md`):

- **53 end-to-end workflows across 8 personas** on the production-scale Moodle 5.2 clone (3,176 users, 412 courses, 22,523 enrolments, 32,248 completions): **47 green, 5 partial** (remaining halves deliberately deferred to the CI browser tier or post-deploy gates), 1 pending.
- Highlights: signup E2E over HTTP; one-click enrol policy + mechanism; reminder and manager-escalation crons fired against seeded deadlines (both nudge directions provably one audit query); HRMS import; WhatsApp consent pipeline; recompletion 13/13; course-share state machine 23/23; push crypto end-to-end; Live session with all 6 question types; certificate engine evidence (11,415 issues, latest PDF physically on disk); full cron pass **103 tasks / 0 failures / 0 warnings**; org parity 100% across 2,883 users.
- **The campaign earned its keep**: it found and fixed ~17 real defects (WF-001…WF-017) that would have shipped silently — a dead URL-redirect policy, 17 silently-dead cron registrations, stale brand-row data, a Moodle-core 5.2 bug under FastCGI (documented as an upstream-report candidate), vendor-block fatals from retired dependencies, and a JS API removed upstream. Each fix carries its evidence trail; several produced new mandatory deploy-runbook steps.

## 9.3 Security posture

- **Fail-closed payments**: a payment-verification bypass found in the inherited gateway was fixed with a verifier that rejects anything unverifiable (tampered-hash rejection is a test case), proven by a 13-test suite plus a live-path check; MD5 retired. The final sandbox transaction before deploy is deliberately owner-gated.
- **Secrets discipline**: credentials live in environment files, never in git; the pre-commit hook blocks credential patterns, raw SQL string-building, and superglobal use; tokens are never logged.
- **Crypto with tests**: the push stack's ES256/RFC-8291 implementation ships with a 16-method crypto-audit suite plus end-to-end verification; OAuth tokens (M365, calendar) are encrypted at rest via Moodle core encryption; the VAPID private key is envelope-encrypted.
- **Tenant isolation as a test class**: dedicated isolation suites exist precisely because isolation is the product promise (7/7 supervisor scoping, 13 push-subscription tests, cross-tenant ID-guessing rejection).
- **PII guards**: bulk-PII exports gated behind dedicated RISK_PERSONAL capabilities (compliance report); PII never sent to external AI/voice services (pipeline rule); a PII-masking CLI for dev environments.

## 9.4 Privacy & regulatory

- **GDPR / DPDP (India) providers across the plugin suite** — subject-access export and right-to-erasure supported plugin by plugin, several with dedicated provider test suites; a privacy request hub with an append-only consent ledger; a DPDP-Act data-fiduciary statement page.
- **Telecom-regulation-ready messaging**: DLT template registry for SMS, Meta-approved WhatsApp templates, per-user consent with timestamped audit, and a hard rule that admins cannot force-enable a user's channel.
- **Compliance answers in one query**: every reminder and escalation around any deadline is a logged row — the audit conversation becomes a filter, not a forensic project.

## 9.5 Engineering discipline (the part a buyer's IT team will ask about)

- **27 Architecture Decision Records** — every cross-cutting decision is written down with its alternatives.
- **Per-plugin state cards** — every plugin's schema, capabilities, flags, tests, and open items in one refreshed document.
- **Visual evidence required for every UI change** — screenshots (desktop + 590px mobile) committed with the work.
- **Core modifications tracked**: the rare Moodle-core touches are tagged in code, documented in `docs/core-mods/` with before/after and upgrade-safety notes.
- **Honest status language**: shipped vs staged vs designed is a published table (§2), and the coverage matrix records what is *not* tested with the same care as what is.

# 10. Marketing kit

## 10.1 The one-paragraph pitch

**Sentientia LMS is the enterprise learning platform that was hardened inside a real financial-services company before it was ever offered for sale.** It runs Airpay Academy — 3,500+ learners, three isolated business units, five languages, daily compliance deadlines with automatic manager escalations — and every feature you'd normally bolt on as a project comes built in: HRMS-synced user lifecycle, WhatsApp/SMS/push/email nudges with India-grade consent compliance, a payment-enabled public course storefront, AI quiz generation and recommendations with governed costs, a built-in Mentimeter-class live engagement engine, and an SOP-to-SCORM content pipeline. It is white-label to the last string — change a logo, colours, and a name, and the whole platform re-skins itself in five languages with zero code changes — and every claim in this paragraph traces to a published test record: 53 persona workflows verified on a production-scale clone, zero serious/critical accessibility findings, and a 103-task cron run with zero failures.

## 10.2 Ten differentiators (each one traceable)

1. **Customer-zero proof, not a demo script** — live at airpay.academy with 3,500+ learners across 3 tenants; the product layer verified on a production-data clone before any sale.
2. **White-label that survived an audit** — every rendered customer-name string proven to resolve from configuration (June-2026 ledger); a new customer's branding is one registry row + two icons.
3. **Compliance that runs itself** — 7/3/1 pre-deadline reminders, 1/7/14 manager escalations, recompletion cycles, exemption workflows — and every nudge is one auditable query.
4. **Reach learners where they are** — email + self-hosted web push (no per-message vendor fees) + WhatsApp/SMS with DLT templates and DPDP-grade consent; channels independently flag-switchable.
5. **A Mentimeter-class live engine inside the LMS** — 6 question types, SSE real-time projector view, CSV analytics — no second subscription, no second login.
6. **AI with adult supervision** — quiz generation, recommendations, translation, assistant: every one behind a 4-layer cost defence, mock-demoable at zero spend, human-reviewed before anything publishes.
7. **Multi-tenant isolation you can test in the demo** — tenants as data, a centralised tenant guard, dedicated isolation test suites, and explicit (audited) cross-tenant sharing where wanted.
8. **An accessibility record, not a statement** — 0 serious/critical axe-core findings across 5 personas × 4 surfaces; reduced-motion, focus-visible, and screen-reader live regions engineered and linted.
9. **The SOP-to-SCORM pipeline** — six agents from PDF to packaged course with hard validation gates and confirm-gated spend; content production becomes an internal capability.
10. **An engineering record a buyer's IT team can read** — 27 ADRs, per-plugin state cards, a four-gate quality system, a 53-workflow rollout gate, and honest shipped/staged/designed status tables.

## 10.3 FAQ — what prospects actually ask

**Q1. Is this just Moodle with a theme?**
No — and it is honestly built **on** Moodle (5.1 LTS-class core, 5.2-ready). On top sit 41 product plugins, 6 dashboard blocks, a payment gateway, a proctoring rule, a subscription enrolment engine, and a 700+ file standalone design system with no upstream theme dependency. The base gives you SCORM, the plugin ecosystem, and a global talent pool; the Sentientia layer is what you'd otherwise spend years building. See §10.4 for the full "why not just Moodle" treatment.

**Q2. Who uses it in production today?**
Airpay Payment Services (airpay.academy): 3,500+ learners, 3 tenants (internal employees, a public consumer storefront, ZEEA Tanzania), compliance training, exams, certifications, HRMS sync — daily. The May-2026 snapshot: 2,871 accounts, 411 courses; 11,415 certificates issued on the verification clone.

**Q3. Can it look like OUR brand, not yours?**
Yes — that is the product's core design constraint. Logo (light/dark), colours, typography, favicon, PWA icons, and the displayed name everywhere (login, emails, calendar invites, WhatsApp texts, push payloads, in 5 languages) resolve from per-customer configuration. A completed audit verified there is no hardcoded customer name left in the rendered product.

**Q4. How is our data separated from other tenants/customers?**
Three levels (customer → tenant → org tree), tenant keys on every scoped table, a single centralised tenant guard used by every query and web service, and dedicated isolation test suites. Cross-tenant course sharing exists but is explicit, capability-gated, and audited — and completion data never crosses the boundary even then.

**Q5. What languages does it support?**
English, Hindi, Marathi, Kannada, Swahili — with tooling-enforced key parity (Hindi at 100%). AI quiz generation works natively in Hindi; the translation engine covers all four non-English targets with guaranteed brand-name preservation.

**Q6. Does it work on phones?**
Yes, three ways: responsive web (verified at a 590px primary mobile breakpoint and six others), an installable PWA with offline fallback and push notifications (verified end-to-end), and a designed path to app-store wrappers when store presence matters (ADR-005). 22 read + 14 write web-service endpoints are audited mobile-app-ready.

**Q7. How do reminders actually reach people who ignore email?**
The learner picks their channels: in-app, email, web push to their lock screen, WhatsApp, or SMS (which reaches field staff with no data connectivity). India deployments get DLT-registered templates and DPDP-compliant consent with a timestamped audit; admins cannot force-enable anyone's channel.

**Q8. What does the AI cost us? Can it run up a bill?**
Not without deliberate human action — four times over: master flag (default OFF), live-API flag (default OFF; everything demoable in mock mode at zero spend), a per-call confirmation checkbox, and daily token caps (per-user or per-customer). Every AI call's token usage is logged.

**Q9. Can we sell courses to the public?**
Yes: a Netflix-style logged-out storefront, self-signup with bot protection, cart + checkout through a fail-closed payment gateway (UPI, cards, net banking, wallets), GST-compliant invoicing, refunds and credit ledger, and verifiable certificates. A recurring-subscriptions engine (course / category / all-access scopes) is built and gated for activation.

**Q10. Is it accessible? Our procurement requires WCAG.**
The automated gate enforces WCAG A+AA: current result is 0 serious/critical axe-core findings across 5 personas × 4 surfaces, and serious/critical findings fail the build. Reduced-motion compliance is lint-enforced; status widgets never rely on colour alone; live surfaces carry screen-reader announcements.

**Q11. What about exams and cheating?**
Quiz-engine exams with open/close windows plus an optional proctoring layer per exam: consent + identity verification, in-attempt event capture, recordings, AI pre-screening, and a human reviewer queue making the final call. The access rule is verified 9/9 in the test suite.

**Q12. How long does onboarding take?**
The onboarding bill of materials is data + configuration only: registry rows, a brand row + icons, a flag set, an org tree, a user feed, and content. A from-scratch install guide exists and was validated by reinstalling the team's own environment. Specific timelines and packaging: **[NITIN DECIDES]** (drafts exist).

**Q13. What does it cost?**
Pricing and packaging are in a decision-ready draft with deliberate placeholders — quoted per engagement until published. **[NITIN DECIDES.]**

**Q14. Who supports it, and what's the SLA?**
A support/SLA model (P1–P4 severities, response anchors, escalation paths) is drafted and awaiting the owner's sign-off. **[NITIN DECIDES.]**

**Q15. Is the code open source? What stops us from self-hosting?**
The code is GPL (Moodle's licence) — and nothing stops you. What you buy is the hosted/managed platform, implementation and integrations, the content pipeline, support/SLAs, and the hardening that comes from customer-zero. That is the same commercially proven model as the broader enterprise-Moodle ecosystem.

## 10.4 Objection handling: "Why not just Moodle?"

| The objection | The answer |
|---|---|
| "Moodle is free; we'll just install it." | Vanilla Moodle ships none of this: multi-tenant isolation, HRMS lifecycle sync, deadline escalation chains, WhatsApp/SMS with DLT compliance, self-hosted push, a public storefront with an Indian payment gateway, live polling, AI features with cost governance, or white-label branding. Each is a 6–18 month internal project — Sentientia is those projects, already built and already hardened against 3,500 real users. |
| "We'll buy a big-name LXP instead." | Then you rent forever: per-seat fees, your roadmap in their backlog, your data in their cloud. Sentientia is open-core — you can own the deployment, and the things you pay for (operations, support, integrations, content pipeline) are the things that genuinely cost money. |
| "Custom Moodle deployments rot. We've been burned." | So was customer-zero — that is literally the origin story (a vendor-locked, undocumented deployment, recorded in ADR-001). The answer is the discipline: 27 ADRs, state cards per plugin, a four-gate CI quality system, additive feature-flagged changes, tracked core mods, and a published upgrade rehearsal (Moodle 5.2 cutover executed on a clone with all data intact). |
| "AI features are a compliance risk for us." | Every AI feature defaults OFF, runs mock-mode for evaluation at zero spend, requires per-call human confirmation, caps daily tokens, logs every prompt/response, and gates generated content behind human review. PII redaction rules are part of the platform's operating rules. Turn it on per customer, per tenant — or never. |
| "Our learners are field staff with cheap phones and bad data." | That constraint shaped the product: SMS fallback via DLT templates, a PWA that installs and works offline, a 590px-first responsive design, and Hindi/Marathi/Kannada/Swahili at enforced parity. |
| "How do we know any of this is true?" | Read the record: the workflow test matrix, the white-label ledger, the ADR index, the audits folder. Every claim in this guide cites it. |

# 11. Appendix

## 11.1 Full plugin index

Versions from each plugin's `version.php` in the repository, 2026-06-11. Maturity: S = Stable, B = Beta, A = Alpha (flag-gated).

### Local plugins (41)

| Component | Domain | One-line purpose | Release |
|---|---|---|---|
| `local_airpay_ratings` | Engagement | 1–5 star course ratings with comments, aggregated into the catalog | 1.1.1 · S |
| `local_sentientia_aiquiz` | Intelligence & AI | AI quiz generation from pasted content, human-reviewed per question (EN + HI) | 0.2.0-alpha · A |
| `local_sentientia_analytics` | Intelligence & AI | Curated L&D KPI dashboards with drill-down + CSV | 1.0.1-beta · B |
| `local_sentientia_assistant` | Intelligence & AI | Floating AI chat drawer with role-aware quick actions | 1.1.2-alpha · A |
| `local_sentientia_calendar` | Integrations | Token-URL ICS feed of deadlines/sessions/exams + OAuth groundwork | 1.2.0-beta · B |
| `local_sentientia_cart` | Commerce & Discovery | Cart, checkout, GST invoicing, refunds, credit ledger | 1.0.2 · S |
| `local_sentientia_catalog` | Commerce & Discovery | LXP catalog + public Netflix-style storefront + one-click free enrol | 1.0.2-beta · B |
| `local_sentientia_challenge` | Engagement | Time-boxed learning challenges (completion/streak/quiz-score) with leaderboards | 1.1.4-alpha · A |
| `local_sentientia_classroom` | Learning Core | ILT: sessions, rosters, waitlists, attendance, ICS invites | 1.10.1 · S |
| `local_sentientia_compliance_report` | People & Org | Compliance snapshots, overdue flagging, exemptions, PII-gated export | 1.0.0 · S |
| `local_sentientia_core` | People & Org | Multi-customer seams: tenant identity, org model, tenant registry (dormant by design) | 0.7.0-alpha · A |
| `local_sentientia_courses` | Learning Core | Course management, 7/3/1 + 1/7/14 reminder engine, cross-tenant sharing marketplace | 1.11.2 · S |
| `local_sentientia_emails` | Communications | Branded token-template email engine, 11 rule types, cert-PDF attach | 1.1.2 · S |
| `local_sentientia_evaluation` | Learning Core | Evaluation forms with conditional questions, triggers, NPS analytics | 1.15.2 · S |
| `local_sentientia_exams` | Learning Core | Exam administration over the quiz engine: periods, eligibility, reminders | 1.6.1 · S |
| `local_sentientia_gamification` | Engagement | Points, badges, streaks (+ optional confetti) | 1.0.1-beta · B |
| `local_sentientia_integrations` | Integrations | Keka HRMS client, Teams webhooks, inbound webhook receiver, audit funnel | 1.1.1-beta · B |
| `local_sentientia_leaderboard` | Engagement | SSE real-time leaderboards (quiz/completion/skill) with privacy opt-out | 0.2.0-alpha · A |
| `local_sentientia_learningpath` | Learning Core | Curated course sequences with audience-rule bulk enrolment | 1.7.1 · S |
| `local_sentientia_lifecycle` | Learning Core | Event observers + daily compliance-check cron + due/overdue message providers | 1.0.0-beta · B |
| `local_sentientia_live` | Engagement | Built-in Mentimeter-class live engagement engine (6 question types, SSE) | 0.2.2-alpha · A |
| `local_sentientia_m365` | Integrations | Microsoft 365 bridge: OAuth/PKCE scaffold, Graph stubs confirm-gated | 0.2.0-alpha · A |
| `local_sentientia_manager` | People & Org | Manager workspace: team view, approvals state machine, allocations | 1.3.3 · S |
| `local_sentientia_notifications` | Communications | Rule-driven notification dispatcher across email/WhatsApp/push | 1.4.2 · S |
| `local_sentientia_org` | People & Org | Org hierarchy (tenant → division → department) + access library + per-org branding | 1.4.1 · S |
| `local_sentientia_pages` | Commerce & Discovery | Landing page, onboarding journey, QR attendance, cert gallery, legal pages | 1.1 · S |
| `local_sentientia_platform` | People & Org | Foundation: feature flags, customer branding, tenant guard, user types, ops CLIs | 1.7.0 · S |
| `local_sentientia_privacy` | People & Org | GDPR/DPDP request hub + append-only consent ledger | 1.0.1 · S |
| `local_sentientia_proctoring` | People & Org | Proctoring engine: identity, event capture, recordings, reviewer queue | 1.0.3 · S |
| `local_sentientia_programs` | Learning Core | Multi-level certification programs with level-unlock | 1.8.1 · S |
| `local_sentientia_pwa` | Communications | Installable PWA + self-hosted standards-grade web push | 0.5.3-alpha · A |
| `local_sentientia_ratings` | Engagement | Staging directory for the `airpay_ratings` rename (rehearsed; owner-gated) | — (partial) |
| `local_sentientia_recommendations` | Intelligence & AI | AI course recommendations with "Why this?" rationales (catalogue-bounded) | 0.1.0-alpha · A |
| `local_sentientia_recompletion` | Learning Core | Periodic recompletion engine for expiring certifications | 1.1.1 · S |
| `local_sentientia_reports` | Intelligence & AI | Saved-report builder with export-only capability split | 1.1.1 · S |
| `local_sentientia_request` | People & Org | Learner → manager course-access request workflow | 1.2.2 · S |
| `local_sentientia_roles` | People & Org | Role management UI + append-only capability audit log + CSV | 1.1.3-beta · B |
| `local_sentientia_skills` | Learning Core | Skills catalogue, designation matrix, self-rate + history | 1.6.2 · S |
| `local_sentientia_translate` | Intelligence & AI | AI translation (hi/mr/kn/sw) with guaranteed brand preservation + diff review | 0.2.0-alpha · A |
| `local_sentientia_users` | People & Org | User management, 24-column HRMS sync, public signup, welcome mailer | 2.7.1 · S |
| `local_sentientia_whatsapp` | Communications | WhatsApp Business + SMS bridge with DLT templates and consent audit | 0.4.0-alpha · A |

### Blocks, enrolment, payment, quiz access, theme

| Component | Type | One-line purpose | Release |
|---|---|---|---|
| `block_sentientia_cert_health` | Block | Certificate-email delivery health KPIs (WCAG AA, axe-verified) | 1.0.0 · S |
| `block_sentientia_compliance` | Block | Compliance status dashboard widget | 1.0.0-beta · B |
| `block_sentientia_cron_health` | Block | Scheduled-task health widget | 1.0.0 · S |
| `block_sentientia_leaderboard` | Block | Live top-5 leaderboard widget (SSE) | 0.1.0-alpha · A |
| `block_sentientia_recommendations` | Block | AI recommendations dashboard widget | 0.1.0-alpha · A |
| `block_sentientia_trainer` | Block | Trainer's recent classroom sessions | 1.0.0 · S |
| `enrol_sentientiasub` | Enrolment | Recurring-subscription enrolment (course/category/all-access) | 0.2.0-alpha · A |
| `paygw_airpay` | Payment gateway | Airpay payment gateway with fail-closed verification | 1.0.1 (2024100700.10) · S |
| `quizaccess_sentientia_proctoring` | Quiz access rule | Attaches the proctoring engine to any quiz | 1.1.1 · S |
| `theme_sentientia` (canonical `airpayux` fork) | Theme | The 700+ file standalone design system, light + dark, white-label | 1.0.46-beta+ · B |

Also in the tree: the inherited vendor reporting blocks (LearnerScript family), kept patched and crash-guarded during the documented transition to `sentientia_reports`/`sentientia_analytics`.

## 11.2 Glossary

| Term | Meaning |
|---|---|
| **ADR** | Architecture Decision Record — a written, numbered record of a significant technical decision and its alternatives (27 exist). |
| **axe-core** | The industry-standard automated accessibility testing engine used by quality Gate 2. |
| **BizLMS** | The inherited vendor customisation layer Sentientia absorbed and de-branded (ADR-024); its concepts (costcenter, open_path) survive as compatibility seams. |
| **[CONFIRM] gate** | A platform rule: actions that spend money or touch live systems require an explicit human confirmation step. |
| **Costcenter / `open_path`** | The legacy tenant mechanism: each user carries a path like `/1/2/3` whose first segment is their tenant root (1 = Airpay, 77 = Public, 177 = ZEEA). Being replaced by the tenant registry, behind flags. |
| **Customer** | An enterprise running Sentientia (customer-zero = Airpay). Owns one or more tenants, a brand row, and a flag set. |
| **Customer-zero** | The first production customer, used to harden every feature at real scale before external sale. |
| **DLT** | India's Distributed Ledger Technology SMS-template registry — commercial SMS must use pre-registered templates. |
| **DPDP** | India's Digital Personal Data Protection Act 2023 — the privacy regime the consent and data-rights flows are built for (alongside GDPR). |
| **Feature flag** | A switch (default OFF) controlling a feature per customer/tenant through a 5-level precedence; managed in the Switchboard with an audit trail. |
| **HRMS** | Human Resource Management System (e.g. Keka) — the source of truth for employees, synced nightly. |
| **ILT** | Instructor-Led Training — classroom sessions, physical or virtual. |
| **LXP** | Learning Experience Platform — the consumer-grade discovery layer (posters, rails, recommendations) on top of LMS plumbing. |
| **Mock mode** | AI/messaging features running against deterministic local clients — full pipeline demoable with zero external calls and zero spend. |
| **Persona** | One of the 8 user archetypes the platform is designed and tested around. |
| **PWA** | Progressive Web App — the installable, offline-capable, push-enabled app experience. |
| **Recompletion** | The automatic re-opening of expiring mandatory training on a per-rule cycle. |
| **Render-smoke** | Quality Gate 1: every persona × surface rendered in a real browser; fatals, template leaks, missing landmarks, or console errors fail it. |
| **SCORM** | The e-learning content packaging standard (1.2) the pipeline produces and the player consumes. |
| **Seam** | A sanctioned API layer that decouples product code from a legacy mechanism, switchable by flag (tenant identity, org, tenant registry). |
| **SSE** | Server-Sent Events — the one-directional real-time push mechanism behind Live and the leaderboards (no WebSocket daemon to operate). |
| **State card** | The per-plugin engineering dossier: schema, capabilities, flags, tests, decisions, open items. |
| **Switchboard** | The admin console for all feature flags. |
| **Tenant** | An isolated business unit inside a customer; the unit of data isolation. |
| **VAPID** | The web-push server-identification standard; Sentientia self-hosts its keypair (encrypted at rest). |
| **White-label** | Every customer-visible brand element resolving from configuration — proven by audit, not promised. |

## 11.3 Where to look for more

| Question | Document (repo-relative) |
|---|---|
| Why a product, not a patch? | `docs/adr/ADR-001-fork-strategy-and-product-pivot.md` |
| The C-suite summary | `docs/SENTIENTIA-PRODUCT-GUIDE.md` |
| Full capability + gap audit | `docs/audits/SENTIENTIA-CAPABILITY-AND-GAP-AUDIT-2026-06-09.md` |
| White-label proof | `docs/WHITELABEL-DEBRAND-LEDGER.md` |
| The rollout-gate test matrix | `docs/testing/WORKFLOW-TEST-MATRIX.md` |
| Quality-gate design | `docs/adr/ADR-027-quality-gate-system.md` |
| Per-persona user guides (8) | `docs/user-guides/` |
| Per-plugin engineering dossiers | `state-cards/` |
| Demo environment plan | `docs/business/DEMO-TENANT-PLAN.md` |
| Pricing / SLA / trademark drafts | `docs/business/PRICING-PACKAGING-DRAFT.md`, `SUPPORT-SLA-MODEL.md`, `TRADEMARK-CHECKLIST.md` |
| From-scratch install | `docs/INSTALL-SENTIENTIA.md` |
| Live engineering state | `PROJECT-STATE.md` |

---

*Prepared 2026-06-11 from the engineering record: PROJECT-STATE.md, 27 ADRs, 50+ state cards, plugin source (version.php / lang packs / feature-flag registries), the FOOLPROOF workflow test matrix, the white-label ledger, and the per-persona user guides. Where a statement is a business decision rather than an engineering fact, it is marked [NITIN DECIDES]. Maintain this guide by updating the affected §4 plugin entry and the §11.1 index row whenever a plugin ships a release, and re-checking §2's numbers against PROJECT-STATE.md at each milestone.*

| Version | Date | Author | Notes |
|---|---|---|---|
| 1.0 | 2026-06-11 | Doc-writer session (sources: engineering record) | Initial master guide — consumers + marketing |

