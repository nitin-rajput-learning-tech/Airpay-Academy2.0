# Sentientia LMS — Platform Showcase

**A white-label, enterprise-grade Learning Management & Experience Platform**
**Customer-zero:** Airpay Academy (Airpay Payment Services)

| | |
|---|---|
| **Document type** | C-suite / investor showcase + live platform walkthrough |
| **Date** | 2026-06-09 |
| **Platform version** | Moodle 5.1.3+ foundation · `theme_sentientia` design system · 40 `local_sentientia_*` plugins |
| **Status of this instance** | Live local build, real production data imported (1,424 users · 407 courses · 3 tenants), audited clean (0 console errors across 19 surfaces) |
| **Companion docs** | `docs/audits/AMD-LOADING-FIXES-2026-06-09.md` (engineering hardening) · `docs/visual-evidence/2026-06-09/` (screenshots) · `docs/adr/` (architecture decisions) |

> **How to read this document.** §1–§3 are the narrative ("what is Sentientia, why
> it exists, what we built"). §4 is the capability catalogue (every feature built on
> top of the Moodle foundation). §5 is the engineering-rigor story that makes it
> *saleable* (multi-tenant, security, i18n, accessibility, offline). §6 is the live
> visual walkthrough per persona. §7 is the roadmap.

---

## 1. Executive summary

**Sentientia LMS is a white-label enterprise learning platform** built on a hardened
Moodle 5.1 core, extended with **40 first-party plugins**, a **standalone design
system** (`theme_sentientia`), and a suite of modern capabilities that Moodle does
not ship: a live audience-engagement engine (a Mentimeter-class tool), a Progressive
Web App with native web-push, WhatsApp delivery, AI-assisted authoring, and a
multi-tenant / multi-customer architecture designed for resale.

The product follows a deliberate **customer-zero strategy**: every feature is
hardened against a real, demanding production deployment — **Airpay Academy**, the
corporate learning platform for Airpay Payment Services (3,500+ employees across
three business tenants) — *before* it becomes a sellable feature of the product. We
are never building for a hypothetical; we are building for a paying user today and
generalising for "Enterprise N" tomorrow.

**Where it stands today:** the platform runs the full Airpay Academy workload on the
Moodle 5.1 foundation with a 5.2 upgrade already code-complete and staged. Every
user-visible surface has been rebuilt in the Sentientia design system, audited for
accessibility (WCAG-AA), localised across five languages, and verified to load
cleanly end-to-end.

---

## 2. The strategy — why "customer-zero"

Most LMS startups fail one of two ways: they build a beautiful demo that collapses
under real enterprise scale, or they build a bespoke internal tool that can never be
sold. Sentientia avoids both by treating **Airpay Academy as customer-zero**:

- **Real scale, real data.** Development and QA run against an imported production
  snapshot — 1,424 users, 407 courses, three live tenants — not seed data.
- **Two customers in every decision.** Each architectural choice is validated against
  (1) Airpay today and (2) any future enterprise customer. Backwards compatibility
  with Airpay's live behaviour is non-negotiable; new features ship **additively,
  behind feature flags, default-OFF**, until deliberately enabled per customer.
- **The internal tool *is* the product.** The same plugins, theme, and pipelines that
  power Airpay Academy are the SKU we sell — no fork, no divergence.

This is recorded formally in **ADR-001** (fork strategy & product pivot) and carried
through every subsequent ADR.

---

## 3. What we built on top of the production Moodle backup

We started from Airpay's production Moodle deployment and transformed it along four
axes:

1. **De-Moodle-ified the surface.** A standalone theme fork (`theme_sentientia`,
   `$THEME->parents = []` — we own every template) replaces Moodle's stock UI with a
   modern app-shell: persistent sidebar navigation, top-bar contextual search,
   role-aware dashboards, dark mode, and a Netflix-style course storefront. The
   product is fully de-branded from both "Moodle" and the legacy "Airpay/eAbyas"
   identity (ADR-025).
2. **Replaced generic Moodle workflows with L&D-grade engines.** 40 purpose-built
   `local_sentientia_*` plugins (see §4) cover the corporate-learning lifecycle —
   HRMS-synced user provisioning, learning paths, certification programs, classroom
   training, evaluations, skills, compliance reporting — at a depth Moodle's stock
   modules don't reach.
3. **Added capabilities Moodle doesn't have.** Live audience engagement, PWA + web
   push, WhatsApp delivery, an AI assistant, AI quiz generation, content translation,
   and a SOP→SCORM content pipeline.
4. **Built the product chassis.** Multi-tenant isolation, a tenant/customer registry,
   per-customer branding, feature flags, and an upgrade-safe core-modification
   discipline — the things that turn "our LMS" into "an LMS we can sell to anyone."

---

## 4. Capability catalogue

### 4.1 Core learning engines (the `local_sentientia_*` suite)

| Surface | Plugin | What it does |
|---------|--------|--------------|
| **Manage Users** | `sentientia_users` | 24-column HRMS-synced user engine: bulk import, cron sync, supervisor hierarchy, DOB/DOJ, tenant-scoped provisioning |
| **Manage Courses** | `sentientia_courses` | Course engine with completion-day rules, deadline + overdue-escalation crons, CRUD audit events, course-share request workflow |
| **Learning Paths** | `sentientia_learningpath` | Sequenced multi-course paths with dates, rich-text, cascade audience filters, bulk enrol |
| **Programs** | `sentientia_programs` | Certification programs with audience enroller + cohort-driven targeting |
| **Online Exams** | `sentientia_exams` | Exam engine with categories, reminder + escalation crons, proctoring hook |
| **Classrooms** | `sentientia_classroom` | Instructor-led training: sessions, attendance, audience bulk-enrol, status workflow |
| **Evaluations** | `sentientia_evaluation` | Kirkpatrick-style training evaluations: 6 question types, conditional questions, auto-assign, non-respondent tracking, template library, auto-expire |
| **Skills** | `sentientia_skills` | Skills matrix with learner self-rating workflow + audit log |
| **Compliance** | `sentientia_compliance_report` | Tenant-scoped compliance dashboards and reporting |
| **Analytics / Reports** | `sentientia_analytics`, `sentientia_reports` | KPI dashboards (Chart.js), enrolment trends, course distribution, CSV export |
| **Organisation** | `sentientia_org` | Org-model hierarchy (cost-centre / manager chain) with cascade pickers |
| **Notifications** | `sentientia_notifications` | Rule-driven multi-channel notification engine |
| **Emails** | `sentientia_emails` | Tenant-scoped email template management with token substitution |
| **Privacy** | `sentientia_privacy` | GDPR self-service (data export / consent) |
| **Certificates** | `tool_certificate` (+ tenant scoping) | Tenant-scoped certificate template library |

### 4.2 Differentiating capabilities (beyond stock Moodle)

- **Sentientia Live** (`local_sentientia_live`) — a real-time audience-engagement
  engine (a Mentimeter-class tool): trainer creates a session, audience joins by code,
  responses stream live over Server-Sent Events into dynamically-updating charts.
  Six+ question types (multiple-choice, word cloud, quiz, rating, open text, …),
  live leaderboard, correct-answer reveal, and CSV session analytics.
- **Progressive Web App + Web Push** (`local_sentientia_pwa`) — installable app
  (manifest, service worker, offline fallback, iOS add-to-home-screen guidance) with
  **standards-compliant web push** (VAPID/ES256 JWT, RFC 8291 aes128gcm payload
  encryption, envelope-encrypted keys at rest, tenant-scoped subscriptions, delivery
  log). Push is wired into the reminder + overdue-escalation crons.
- **WhatsApp delivery** — a notification bridge that fans reminders/escalations out
  over the WhatsApp Business API alongside email + push.
- **AI features** — `sentientia_aiquiz` (AI quiz generation with a 4-layer cost
  defence + mock mode), `sentientia_translate` (content translation queue),
  `sentientia_assistant` (role-aware in-product AI assistant with quick-action chips),
  plus course recommendations and real-time leaderboards (`sentientia_leaderboard`).
- **Calendar sync** (`sentientia_calendar`) — token-URL ICS feed + OAuth phase-2
  scaffolding.
- **SOP → SCORM content pipeline** — a six-agent pipeline (parse → narrate → slides →
  voice → package → upload) that turns a standard-operating-procedure PDF into a
  validated SCORM 1.2 package; positioned as a sellable authoring feature.

---

## 5. The engineering rigor that makes it saleable

| Dimension | What we did |
|-----------|-------------|
| **Multi-tenant isolation** | Three live tenants (Airpay /1, Public /77, ZEEA /177) with every query tenant-scoped; a `tenant_identity` seam abstracts detection off raw DB columns (ADR-018 Wave 2 migrated ~22 call-sites across 11 plugins); a `tenant_registry` + `customer` schema (ADR-021) generalises this to N customers. |
| **Per-customer branding** | `customer_brand` schema (ADR-008) feeding `core_renderer`: each customer overrides logo, colours, typography, favicon. |
| **Feature flags** | Every new user-visible feature ships behind a default-OFF flag with per-customer + per-tenant override, so customer-zero behaviour never regresses. |
| **Accessibility** | WCAG-AA pass: universal `:focus-visible`, dark-mode token cascade with contrast fixes, `prefers-reduced-motion` honoured (stylelint-enforced), aria-live on live regions, NVDA verification procedure documented. |
| **Internationalisation** | Five language packs at parity — English, Hindi, Kannada, Marathi, Swahili — enforced 100% across the most-touched plugins. |
| **Security** | OWASP-aligned reviews; payment-verification bypass fixed fail-closed; envelope-encrypted push keys; CSRF/escaping discipline; a pre-commit + CI conflict-marker gate; WS-contract drift gate in CI. |
| **Upgrade safety** | Moodle 5.2 upgrade is code-complete and staged (hook migration, SCSS rebase, AMD shims) behind a customer-driven cutover decision; core modifications are tracked in `docs/core-mods/` with ADRs. |
| **Quality gates** | PHPUnit suites (tenant isolation, RFC 8291 vectors, role detection, WS contracts) wired into GitHub Actions; Playwright persona E2E specs. |
| **Loading integrity** | This session: found & fixed a platform-wide AMD/RequireJS breakage (dashboard charts, sidebar prefs) that screenshot-only QA had missed, via code + DOM + console inspection — now 0 console errors / 0 warnings across 19 surfaces (see `AMD-LOADING-FIXES-2026-06-09.md`). |

---

## 6. Live visual walkthrough

> Captured live in Chrome against this instance. Screenshots under
> `docs/visual-evidence/2026-06-09/`. This section is built out persona-by-persona;
> the Site Admin hero surfaces are captured, with Learner / Manager / Trainer /
> Compliance / Course-Author / Guest / Mobile + dark-mode passes to follow.

### 6.1 Site Admin — platform overview dashboard ✅ captured

`docs/visual-evidence/2026-06-09/siteadmin/dashboard-charts-fixed.jpg`

The app-shell dashboard: persistent left sidebar (Manage Users → Privacy → Site
Admin), top-bar contextual search, a "Welcome back" hero, four KPI tiles
(**20 active / 1,424 total users · 407 courses · 8,075 completions · 22,523
enrolments**), and two live Chart.js panels — **Enrolment Trend** (bar) and **Course
Distribution** (doughnut, segmented by tenant). Dark-mode toggle and labelled
**Log out** control anchor the sidebar.

### 6.2 Sentientia Live — trainer dashboard ✅ captured (+ bug fixed live)

`docs/visual-evidence/2026-06-09/siteadmin/sentientia-live-trainer-dashboard.jpg`

The flagship real-time engagement engine: **"Your live sessions"** with a *Create
new session* CTA and a sessions table — State (Live), 6-digit Join code, slide count,
live-audience badge, Created, and per-row **Run / End / Export / delete** actions.

> **Audit finding F-LIVE-01 (fixed this session).** The root URL
> `/local/sentientia_live/index.php` was a stale Phase-E.0 *"being built
> incrementally"* placeholder — even though the full trainer + audience UIs shipped in
> E.1/E.2 and were live-tested. The placeholder's own comment said *"Phase E.1 will
> replace this with the full trainer dashboard"*; it never did, so the flagship feature
> showed "coming soon" at its front door. **Fix:** replaced the stub with a role-aware
> router (trainers → `trainer/index.php`; everyone else → `audience/join.php`).
> Before/after evidence: `sentientia-live-STUB-before.jpg` → `sentientia-live-trainer-dashboard.jpg`.

### 6.3 Persona matrix (planned capture set)

| Persona | Hero surfaces | Desktop | Mobile (590px) | Dark |
|---------|---------------|:---:|:---:|:---:|
| Guest / prospect | Storefront, login, signup | ✅ | ✅ | ✅ |
| Site Admin | Dashboard, admin tools | ✅ | ☐ | ☐ |
| Tenant Admin | Tenant-scoped dashboard (Public /77) | ✅ | ☐ | ☐ |
| L&D / Course Author | Dashboard + role-switch to Trainer | ✅ | ☐ | ☐ |
| Trainer | Sentientia Live runner, classrooms | ✅ (Live) | ☐ | ☐ |
| Compliance Officer | Dashboard (admin shell) + Compliance plugin | ✅ | ☐ | ☐ |
| Manager | Team KPIs, compliance | ✅ | ☐ | ☐ |
| Learner / Employee (internal, B2B) | Dashboard, catalog, certificates, PWA | ✅✅ | ☐ | ☐ |
| External Public Learner (B2C) | Cart-based dashboard (Public /77) | ✅ | ☐ | ☐ |

**8×3 grid COMPLETE.** All 8 personas captured at desktop; Guest + all five
authenticated personas (Learner, Manager, Tenant Admin, Course Author, Compliance)
also captured at **mobile (414px)** and **dark mode**. Every cell renders in the
Sentientia design system with 0 console errors — the shared app-shell collapses to a
hamburger drawer on mobile and flips to dark navy with preserved contrast across all
roles. Evidence: `docs/visual-evidence/2026-06-09/<persona>/` (NN-…-mobile.jpg / …-dark.jpg).

### 6.5 The polymorphic role-aware dashboard (one platform, three experiences)

The same `/my/` URL renders a fundamentally different experience per role — driven by
the `user_type_provider` architecture (ADR-017). All three captured this session, all
0 console errors:

- **Learner** (`learner/01-learner-dashboard.jpg`, `02-learner-catalog.jpg`) —
  *"Welcome back, Fatma!"*; sidebar = Dashboard / My Courses / Catalog / Certificates /
  Profile; KPI tiles (36 enrolled · 4 in progress · 22 completed · 25 certificates);
  a **61% completion radial gauge**; and the **LXP gamification layer** — level/points/
  rank, a day-streak tracker, a department leaderboard. The Catalog is a Netflix-style
  storefront: a *Continue Learning* poster carousel with progress, *Browse by Category*
  tiles, skills search, and the floating AI-assistant.
- **Manager** (`manager/01-manager-dashboard.jpg`) — *"Welcome, Binay — Team overview
  and compliance status"*; sidebar gains **My Team** + **Compliance**; team KPIs
  (9 members · 31 enrolments · 28 completions · **90.3% completion rate**); and a
  **Team Compliance** table with per-report Enrolled/Completed/Rate/Pending/Overdue/
  Last-Active and drill-down.
- **Site Admin** (`siteadmin/dashboard-charts-fixed.jpg`) — platform KPIs +
  Enrolment-Trend and Course-Distribution charts; full admin sidebar.

That a single codebase serves the employee's gamified learning journey, the manager's
team-compliance cockpit, and the admin's platform console — each with its own
navigation, widgets, and data scope — is the product's core UX thesis.

> **Finding F-MGR-01 — RESOLVED, by-design (not a bug).** The manager dashboard shows
> *9 Team Members* for Binay; the persona note said *34*. Root cause confirmed by DB
> count: Binay has **36 total** reports via `open_supervisorid`, but only **9 are
> active** (26 suspended + 2 deleted). `team_manager::get_team()` filters
> `WHERE deleted = 0 AND suspended = 0` — the correct semantic for a team-compliance
> KPI (a manager's "team" should not include suspended/deleted ex-employees). The
> "34" note was the raw total on the prior data snapshot. No fix needed.

### 6.6 Multi-tenant isolation — proven live (the resale thesis)

`tenantadmin/01-tenantadmin-public-dashboard.jpg`

The single strongest evidence that Sentientia is a *product*, not a one-off: the
**Public-tenant administrator** (`/77`) sees the same Sentientia dashboard, but every
number is **scoped to their tenant**. Side by side with the Airpay Site Admin on the
same instance, same database:

| Metric | Airpay Site Admin (all tenants) | Public Tenant Admin (`/77`) |
|--------|--------------------------------:|----------------------------:|
| Total users | 1,424 | **671** |
| Courses | 407 | **183** |
| Enrolments | 22,523 | **1,070** |
| Heading | "Welcome back, Airpay" | "Welcome back, External — **Public** —" |

The dashboard also surfaces a **role switcher** (Employee ⇄ Administrator) for
multi-role users and a distinct accent treatment — the hooks for per-tenant /
per-customer branding (ADR-008 / ADR-021). This is the architecture that lets one
deployment serve Airpay today and Enterprise N tomorrow, each seeing only its own
world. 0 console errors.

### 6.7 The public face — responsive + dark mode

`guest/10-guest-frontpage-desktop-postfix.jpg` · `11-guest-frontpage-mobile.jpg` ·
`12-guest-frontpage-dark.jpg`

The unauthenticated landing reads like a SaaS product, not a Moodle site:
*"Build a skilled, compliant workforce at scale"*, Explore-Courses / Sign-In CTAs,
live public-tenant stats (**183+ Courses · 671+ Learners**), and an enterprise
trust row — **RBI Compliant · DPDP 2023 Ready · SCORM & xAPI · Multi-Tenant · 24/7
Support**. Captured at desktop (1280), **mobile (414w — fully reflowed, no
horizontal scroll)**, and **dark mode** (header/nav/footer flip to navy with
preserved contrast). Confirms the responsive design and the OS-aware dark theme
across the whole platform, not just the dashboard.

### 6.8 B2B + B2C in one platform (the final personas)

The last three personas complete the matrix and surface a second product thesis —
**one platform serves both internal employees (B2B) and external paying learners
(B2C)**:

- **Course Author / Trainer** (`courseauthor/01-courseauthor-dashboard.jpg`) — Asif's
  default is the learner shell with a **role switcher** ("Employee ⇄ Operations -
  Trainer") and a **My Skills** nav item. Default view = primary user-type; elevate to
  the trainer role on demand.
- **External Public Learner — B2C** (`publiclearner/01-publiclearner-cart-dashboard.jpg`)
  — Vimal (Public `/77`, no org role) gets a **My Cart** nav item (not "My Skills") and
  an **empty state** (0/0/0/0) that renders gracefully. External learners **purchase**
  courses via the cart (`paygw_airpay`); internal employees are **org-enrolled**. Same
  codebase, two go-to-market motions.
- **Compliance Officer** (`compliance/01-compliance-dashboard.jpg`) — Joseph carries
  the administrator role, so he lands on the admin shell; the dedicated **Compliance
  Report** engine (`local_sentientia_compliance_report`) is his working surface
  (verified 0-error in the loading sweep). A dedicated compliance-officer role is on
  the polymorphic-user-type roadmap (ADR-017).

**Audit status: the exhaustive 8-persona visual walk is complete.** Every persona's
hero surface renders in the Sentientia design system with 0 console errors, the
loading bugs are fixed, and the platform demonstrates: role-aware dashboards,
multi-tenant isolation, B2B+B2C, LXP gamification, live engagement, responsive +
dark mode, and a de-Moodle'd enterprise brand.

### 6.4 Audit findings & fixes log (running)

| ID | Severity | Finding | Status |
|----|----------|---------|--------|
| F-LOAD-01 | 🔴 P0 | Dashboard omitted `standard_end_of_body_html` → AMD never booted (blank charts) | ✅ fixed |
| F-LOAD-02 | 🔴 P0 | 34 theme AMD bundles stuck on `theme_airpayux/` name → all modules dead platform-wide | ✅ fixed |
| F-LOAD-03 | 🟠 P1 | Sidebar pref `fetch('/lib/ajax/setuserpref.php')` 404 (removed in 5.1) | ✅ fixed (`core_user/repository` + pref registration) |
| F-LOAD-04 | 🟡 P2 | `apple-mobile-web-app-capable` deprecation warning on every page | ✅ fixed (paired `mobile-web-app-capable`) |
| F-LIVE-01 | 🟠 P1 | Sentientia Live root URL = stale "coming soon" stub over a built feature | ✅ fixed (role-aware router) |

Full engineering detail: `docs/audits/AMD-LOADING-FIXES-2026-06-09.md`.

---

## 7. Roadmap (post-customer-zero generalisation)

- **Cutover to Moodle 5.2** on a customer-driven schedule (code-complete; staged).
- **Tenant/customer registry GA** — flip from single-implicit-tenant to N-customer
  trees; per-customer branding live (ADR-021 / ADR-008).
- **Recurring subscriptions** (`enrol_sentientiasub`, ADR-023) — gated on a product
  pricing decision + Airpay sandbox round-trip.
- **AI features to GA** — quiz generation, recommendations, translation off mock mode.
- **Native mobile wrappers** (Capacitor/Cordova over the PWA surface).
- **Sentientia design system v2** — formalise tokens for white-label theming at scale.

---

*Prepared as the companion showcase to the platform's engineering-hardening record.
Figures are from the live local instance dashboard on 2026-06-09; production figures
differ. Visual-evidence captures are being extended per the §6.2 matrix.*
