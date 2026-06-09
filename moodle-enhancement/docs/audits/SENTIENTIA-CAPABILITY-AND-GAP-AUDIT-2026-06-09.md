# Sentientia LMS — Capability Matrix & Code-Level Gap Audit

- **Date:** 2026-06-09
- **Auditor:** Claude (senior LMS product + platform auditor), autonomous background pass
- **Branch:** `feat/theme-canonicalize-2026-06-09`
- **Scope:** Read-only. Authoritative product surface = the **live local webroot**
  `C:\xampp\htdocs\moodle5\public\` (Moodle 5.1.3, split webroot). The git checkout
  at `D:\Claude Local\airpay-ld-os\` is the *production git tree* and is treated as a
  divergent source (see Gap G-P0-1).
- **Relates to:** ADR-017 (polymorphic user types), ADR-019/020/021 (sentientia_core /
  org / tenant_registry), ADR-022 (component rename, **gated**), ADR-026 (theme
  cutover & canonicalization), ADR-027 (quality-gate system — *gates first, then UI*).

---

## Executive summary

Sentientia LMS today is a **two-axis, multi-tenant LMS/LXP** built on Moodle 5.1.3:
a **role axis** (`theme_sentientia\role_detector` → siteadmin / L&D-admin / manager /
learner) composed with a **polymorphic user-type axis** (`local_sentientia_platform\
user_type_factory` → employee / consumer / partner_employee / operator). The 8 audited
personas are combinations of these two axes plus tenant scope (Airpay /1, Public /77,
ZEEA /177). The product backend is **40 `local_sentientia_*` plugins + 6
`block_sentientia_*` + `enrol_sentientiasub` + `quizaccess_sentientia_proctoring` +
`payment/gateway/airpay`**, fronted by `theme_sentientia` (708 files, app-shell:
shared sidebar + role-aware dashboard). Feature-flagging (`feature_flags`, 5-level
customer/tenant resolution, default-OFF) is mature and pervasive.

**The platform is functionally broad and well-architected, but it is NOT yet at the
"Sentientia standard" on three fronts:**

1. **Preservation / source-of-truth (most severe).** The entire 40-plugin product
   backend exists **only in the untracked live webroot** — `git log --all --
   local/sentientia_core/version.php` returns nothing; **zero `local/sentientia_*`
   files are tracked on any git branch**, including this one. ADR-026 Move 1 committed
   the *theme* to git (704 files) but the *plugins* were never committed anywhere. A
   clean reclone or a webroot wipe loses the product. The git `local/` tree is the
   stale pre-rename BizLMS snapshot (`local/courses`, `local/biz_cart`, …) that does
   not even exist in the webroot.

2. **Branding / naming debt.** ~30 of the 40 plugins still carry user-facing
   `pluginname = 'Airpay …'` titles (shown in admin + nav). The foundational plugin
   `local_sentientia_platform` literally self-titles "Airpay Core (shared
   infrastructure)". `structured_logger` stamps every log row with a
   `local_airpay_*` component string. `learningpath`/`notifications` still query the
   un-renamed table `local_airpay_lp_users`. The login + maintenance + email
   templates hardcode "airpay academy" as user-visible text. The airpayux→sentientia
   AMD rename left 6 `.min.js.map` source-maps un-rewritten.

3. **Surface-upgrade gaps (exactly as ADR-027 predicted).** The app-shell
   (`airpay_shell_start`) is wired into only **2 of 10 theme layouts**
   (`columns2.php`, `dashboard.php`). In-course activity pages (quiz/SCORM/assign/
   forum, all rendered via `course.php`) use a *separate bespoke course-player UI*,
   not the app-shell sidebar; admin interior pages use `drawers.php` (no app-shell).
   The dashboard ships an **inline duplicate** of the sidebar markup. Several
   feature-flagged surfaces are explicit placeholders (`sentientia_live` real-time
   projector, `sentientia_assistant` AI bridge, two `sentientia_emails` rules).

The ADR-027 sequencing decision ("**gates first, then UI upgrades**") is honored in the
action plan below: P0 closes correctness + preservation, P1 builds/extends the gates,
P2 does surface upgrades behind those gates, P3 is branding/naming + nice-to-have.

### Gap count by category

| Category | Gaps | Severity skew |
|---|---|---|
| (a) Surfaces still on legacy / non-app-shell UI | 6 | P1–P2 |
| (b) Stale branding / naming debt | 9 | P2–P3 (one P0: structured_logger correctness-adjacent) |
| (c) Correctness debt the ADR-027 gates target | 5 | P0–P1 |
| (d) Dependency-map / coupling risks | 4 | sequencing constraints |
| (e) Known "not done" markers (placeholders / flagged-OFF / pending) | 8 | P2–P3 |
| **Structural (source-of-truth / preservation)** | 3 | **P0** |
| **Total catalogued** | **35** | |

---

## 1. Capability Matrix (D1)

### 1.1 How a persona is computed (read this first)

Two orthogonal axes compose into every persona. Source of truth:

- **Role axis** — `theme_sentientia\role_detector::detect()`
  (`theme/sentientia/classes/role_detector.php`). Returns
  `issiteadmin / isldadmin / ismanager / islearner / switched_to_employee`. Rules:
  - `issiteadmin` = `is_siteadmin()`.
  - `isldadmin` = not siteadmin, not switched-to-employee, AND
    (`has_capability('local/sentientia_courses:manage', system)` OR holds the
    `administrator` role at a **category context** (contextlevel 40)).
  - `ismanager` = not admin, AND (`has_capability('moodle/site:viewreports', system)`
    OR has direct reports via `user.open_supervisorid` — guarded by `field_exists`).
  - `islearner` = default (not admin, not manager).
  - BizLMS role-switch (`$SESSION->airpay_switchrole`, `$USER->useraccess`) can demote
    an admin to the learner experience.
- **User-type axis** — `local_sentientia_platform\user_type_factory::for_user($uid)`
  reads `local_sentientia_user_type.user_type` → `employee | consumer |
  partner_employee | operator` (defaults to `employee` when unrowed). Each provider
  (`classes/user_type/*_provider.php`) drives `dashboard_widgets()`, `sidebar_items()`,
  `profile_context()`, `onboarding_steps()`, `required_consents()`,
  `feature_supported()`.
- **Tenant scope** — `$USER->open_path` (e.g. `/1/...`, `/77`, `/177`); root segment =
  tenant id (Airpay 1, Public 77, ZEEA 177). Drives cart visibility, cross-tenant
  course-share links, branding.

Sidebar items per role tier are emitted by
`theme_sentientia\sidebar_navigation::get_nav_items()`
(`theme/sentientia/classes/sidebar_navigation.php`) — the tables below are derived
directly from that method plus each target page's `db/access.php`.

### 1.2 Per-persona capabilities

> Legend for "Source": `SN` = sidebar_navigation.php nav item; `acc` = the target
> plugin's `db/access.php` capability; `flag` = `feature_flags` gate.

#### Persona 1 — Learner (employee, manual-auth) · role=learner · type=employee
| Capability | Surface / entry point | Source |
|---|---|---|
| Personal dashboard (continue-learning, mandatory-compliance, gamification widgets) | `/my/` → `dashboard.php` + `dashboard.mustache` (role-aware learner view) | SN `Dashboard`; `user_type_provider::dashboard_widgets` |
| My Courses (enrolled) | `/local/sentientia_catalog/mycourses.php` | SN `My Courses` |
| Browse course catalog (LXP grid) | `/local/sentientia_catalog/public.php` | SN `Catalog` |
| Skills self-assessment / gap radar / recommended courses | `/local/sentientia_skills/index.php` | SN `My Skills` (cap `local/sentientia_skills:view`) |
| View / download certificates | `/local/sentientia_pages/certificates.php` + `admin/tool/certificate` | SN `Certificates` |
| Edit profile | `/local/sentientia_users/profile.php` | SN `Profile` |
| Take SCORM / quiz / assignment / lesson / forum activities | `mod/*` via `course.php` layout (bespoke course player) | core mods + `theme/sentientia/layout/course.php` |
| Compliance report (if granted `moodle/site:viewreports`) | `/local/sentientia_compliance_report/index.php` | SN `iscomplianceuser` branch |
| Live-session **audience** join/respond (polls, word-cloud, Q&A) | `/local/sentientia_live/` audience UI | acc `local/sentientia_live:join`,`:respond`; `flag live.enabled` |
| Course ratings / reviews | `local_sentientia_ratings` (WS-driven) | plugin WS |
| Notifications / WhatsApp & SMS channel prefs (DPDP consent) | `local_sentientia_whatsapp` prefs; `local_sentientia_notifications` | plugin |
| PWA install + push notifications | `local_sentientia_pwa` | `flag` (PWA) |
| AI learning assistant (if wired) | `local_sentientia_assistant` | **placeholder — see G-E-2** |

#### Persona 2 — Manager (has direct reports) · role=manager · type=employee
Inherits **all Learner capabilities**, plus:
| Capability | Surface | Source |
|---|---|---|
| Team dashboard / team compliance & KPIs | `/local/sentientia_manager/index.php` | SN `My Team` |
| Team compliance report | `/local/sentientia_compliance_report/index.php` | SN `Compliance` |
| Run a Live session (trainer) if `live:create` | `/local/sentientia_live/trainer/index.php` | SN `Live Sessions`; `flag live.enabled` + cap |
| Cross-tenant "Browse Airpay Library" + "My Requests" (non-Airpay tenants only) | `/local/sentientia_courses/browse_airpay.php`, `my_requests.php` | SN; `flag commerce.crossTenantRequest.enabled` |
| Cart (cart-enabled tenants) | `/local/sentientia_catalog/cart.php` | SN `My Cart` |

#### Persona 3 — L&D Administrator (category-context administrator) · role=ldadmin
| Capability | Surface | Source |
|---|---|---|
| Manage Users | `/local/sentientia_users/index.php` | SN (cap `local/sentientia_users:view`) |
| Manage Courses (create/update/delete/visibility/enrol) | `/local/sentientia_courses/index.php` | SN (cap `local/sentientia_courses:view`/`:manage`) |
| Online Exams | `/local/sentientia_exams/index.php` | SN (cap `local/sentientia_exams:view`) |
| Classrooms (ILT sessions) | `/local/sentientia_classroom/index.php` | SN (cap `local/sentientia_classroom:view`) |
| Learning Paths | `/local/sentientia_learningpath/index.php` | SN (cap) |
| Reports | `/local/sentientia_reports/index.php` | SN (cap `local/sentientia_reports:view`) |
| Analytics | `/local/sentientia_analytics/index.php` | SN (ungated) |
| Compliance | `/local/sentientia_compliance_report/index.php` | SN (ungated) |
| Cross-tenant course requests (non-Airpay) | `/local/sentientia_courses/browse_airpay.php` | SN; `flag commerce.crossTenantRequest.enabled` |
| (Each admin link is capability-gated — QA fix OA-GRAN so category-admins don't see 403 links) | — | SN per-item `has_capability` |

#### Persona 4 — Course Author / SME (trainer role) · usually role=learner/manager + trainer caps
| Capability | Surface | Source |
|---|---|---|
| Create + run Live engagement sessions (polls/quiz/word-cloud/Q&A) | `/local/sentientia_live/trainer/` | acc `local/sentientia_live:create`,`:run`; teacher archetype (T-01 fix) |
| Authoring of course activities (SCORM, quiz, assign, etc.) | `mod/*` editing UI via `course.php` | core trainer caps |
| Trainer dashboard block | `block_sentientia_trainer` | block |
| AI quiz generation (draft questions) | `local_sentientia_aiquiz` | `flag` (ai quiz), mock-mode |
| Classroom session management (venue, attendance) | `local_sentientia_classroom` | acc |
| Ratings visibility on own courses | `local_sentientia_ratings` | plugin |

#### Persona 5 — Compliance Officer (administrator, overlaps L&D) · role=ldadmin or learner+viewreports
| Capability | Surface | Source |
|---|---|---|
| Compliance reporting engine (mandatory-training status, expiry, gaps) | `/local/sentientia_compliance_report/index.php` | page accepts `moodle/site:viewreports`; SN `iscomplianceuser` even in learner shell |
| Reports + Analytics (if L&D-admin tier) | `/local/sentientia_reports/`, `/local/sentientia_analytics/` | SN |
| Recompletion / certification expiry rules | `local_sentientia_recompletion` | plugin |
| Evaluations (Kirkpatrick-style) | `local_sentientia_evaluation` | plugin |
| Privacy / DPDP data-subject tooling | `local_sentientia_privacy` | plugin |

#### Persona 6 — Tenant Administrator (administrator scoped to a tenant, e.g. Public /77)
Same as L&D Admin but **tenant-scoped** (all queries scoped by `open_path` root via
`local_sentientia_org`), plus:
| Capability | Surface | Source |
|---|---|---|
| Organisation hierarchy admin (cost-centres / org tree) | `/local/sentientia_org/admin.php` | SN (siteadmin) / `local_sentientia_org` |
| Tenant branding (logo, colours, support URLs, welcome) | `/local/sentientia_org/tenant_settings.php` | `branding_manager` |
| Cross-tenant course-share **requests** (file + track) | `/local/sentientia_courses/browse_airpay.php`, `my_requests.php` | `flag commerce.crossTenantRequest.enabled` |
| Self-registration policy for tenant (default `/77`) | `local_sentientia_users` signup settings | plugin settings |

#### Persona 7 — Site Administrator (siteadmin)
Full platform operator. Sidebar (siteadmin branch) exposes: Dashboard, Manage Users,
Manage Courses, **Course-share Requests** (`flag`), Online Exams, Classrooms, Learning
Paths, Programs, Reports, Analytics, Compliance, Organisation, Skills (admin),
Notifications, Evaluations, Certificates (`admin/tool/certificate`), Emails, Privacy,
**Site Admin** (`/admin/search.php`). Plus everything any lower tier can do (capability
bypass). Plus the **Switchboard** (feature-flag admin —
`local_sentientia_platform`), feature-flag audit log, customer-level flag layer,
and all `settings.php` of every plugin.

#### Persona 8 — External Public Learner (Public-tenant member, no role assignments) · role=learner · type=consumer
| Capability | Surface | Source |
|---|---|---|
| Public storefront / course catalog (B2C) | `/local/sentientia_catalog/public.php` | SN `Catalog` |
| Self-signup (free account, tenant `/77`) | `local_sentientia_users` signup flow | plugin |
| Cart + paid checkout (Airpay gateway: Cards / UPI / Net Banking) | `/local/sentientia_catalog/cart.php` → `payment/gateway/airpay` | SN `My Cart` (cart-enabled tenant); `enrol_sentientiasub` |
| Paid-course history, interest-based recommendations | consumer dashboard widgets | `consumer_provider::dashboard_widgets` |
| Leaderboard (opt-in, GDPR) | `local_sentientia_leaderboard` + `block_sentientia_leaderboard` | `flag` + consent |
| Certificates for completed paid courses | `local_sentientia_pages/certificates.php` | SN |
| **No** team/admin/compliance/manage surfaces | — | role=learner + type=consumer filters |

#### Persona 9 — API Consumer (noted, not deep-audited)
No UI. Consumes the Moodle REST WS surface (162 `local_sentientia_*` external
functions per the 2026-06-08 scratch-install proof; 22 MOBILE-READY + 14 learner-write
+ 36 sensitive-admin per `MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`). Token-auth,
capability-checked server-side.

### 1.3 Product plugin inventory (capability provider map)
40 `local_sentientia_*` plugins. Newer plugins are correctly titled "Sentientia LMS —
X"; older inherited plugins still say "Airpay X" (branding gap G-B-1).

| Plugin | Display name (EN) | Entry points | Role/feature it serves |
|---|---|---|---|
| sentientia_platform | **Airpay Core (shared infrastructure)** | settings | feature_flags, user_type, customer, structured_logger, backup_filename (FOUNDATION) |
| sentientia_core | Sentientia Core | index, settings | tenant_identity / tenant_registry seam (ADR-019/021) |
| sentientia_org | **Airpay Organization Engine** | index, admin, settings | org hierarchy + branding_manager (30 dependents) |
| sentientia_courses | **Airpay Course Engine** | index, settings | course CRUD, visibility, cross-tenant share |
| sentientia_catalog | **Airpay Course Catalog** | index, public | LXP grid, mycourses, session cart, one-click enrol |
| sentientia_cart | **Airpay Cart** | index, settings | DB cart (WS-fed) |
| sentientia_users | **Airpay User Engine** | index, settings | user mgmt, signup, profile, open_* fields |
| sentientia_manager | **Airpay Manager Dashboard** | index | team KPIs / compliance |
| sentientia_compliance_report | **Airpay Compliance Report** | index, settings | compliance reporting engine |
| sentientia_reports | **Airpay Reports** | index | reporting |
| sentientia_analytics | **Airpay Advanced Analytics** | index | analytics dashboards |
| sentientia_exams | **Airpay Online Exams** | index, view, settings | exam engine (quiz wrapper) |
| sentientia_classroom | **Airpay Classroom Training** | index, view | ILT/venue/attendance |
| sentientia_learningpath | **Airpay Learning Paths** | index, view | learning paths (uses `local_airpay_lp_users` table — G-B-3) |
| sentientia_programs | **Airpay Certification Programs** | index, view | multi-course programs |
| sentientia_skills | **Airpay Skills Matrix** | index, view, admin | skills/competency, gap radar |
| sentientia_evaluation | **Airpay Evaluations** | index | course evaluations (events are placeholders — G-E-4) |
| sentientia_gamification | **Airpay Gamification** | (library/WS) | points/badges engine |
| sentientia_challenge | **Airpay Gamification Challenges** | index, view | challenges + leaderboard |
| sentientia_leaderboard | Sentientia LMS — Real-time Leaderboards | index, view | SSE live ranking |
| sentientia_live | Sentientia LMS — Live engagement | index, settings | Mentimeter-clone (projector = placeholder — G-E-1) |
| sentientia_notifications | **Airpay Smart Notifications** | index | rule engine (uses `local_airpay_lp_users` — G-B-3) |
| sentientia_emails | **Airpay Email Templates** | settings | branded email + rule tasks (2 rules unimpl — G-E-3) |
| sentientia_whatsapp | **Airpay WhatsApp & SMS** | settings | DLT-templated WhatsApp/SMS (DPDP consent) |
| sentientia_recompletion | **Airpay Recompletion** | index, settings | cert expiry / re-completion |
| sentientia_recommendations | Sentientia LMS — AI Course Recommendations | settings | AI recs |
| sentientia_aiquiz | Sentientia LMS — AI Quiz Generation | settings | AI quiz gen (mock-mode, cost-gated) |
| sentientia_assistant | **Airpay AI Learning Assistant** | settings | AI chat (core_ai_bridge = placeholder — G-E-2) |
| sentientia_translate | Sentientia LMS — AI Content Translation | settings | AI translation |
| sentientia_calendar | Sentientia LMS — Calendar Sync | index, settings | ICS feed |
| sentientia_m365 | Sentientia LMS — Microsoft 365 | settings | M365/Graph integration |
| sentientia_pwa | Sentientia LMS — PWA | settings | PWA + push (VAPID) |
| sentientia_integrations | **Airpay Integrations Hub** | settings | external integrations |
| sentientia_pages | **Airpay Pages** | index, settings | static pages, certificates page |
| sentientia_roles | **Airpay Role Management** | index, view | role admin |
| sentientia_request | **Airpay Course Requests** | index, settings | course request workflow |
| sentientia_lifecycle | **Airpay Employee Lifecycle** | (library/WS) | joiner/mover/leaver automation |
| sentientia_proctoring | **Airpay Proctoring** | index, admin, settings | proctoring (pairs `quizaccess_sentientia_proctoring`) |
| sentientia_privacy | **Airpay Privacy (DPDP)** | index | DPDP data-subject tooling |
| sentientia_ratings | **Airpay Ratings** | (library/WS) | course ratings (renamed from local_airpay_ratings, ADR-022 batch-1) |

Other product components: **blocks** — `block_sentientia_leaderboard`,
`_recommendations`, `_compliance`, `_trainer`, `_cert_health`, `_cron_health`;
**enrol** — `enrol_sentientiasub` (subscription/paid enrolment);
**quizaccess** — `quizaccess_sentientia_proctoring`;
**payment** — `payment/gateway/airpay` (real gateway — kept branded by ADR-025 decision).

---

## 2. Code-Level Gap Audit (D2)

> Severity: **P0** blocking/correctness/preservation · **P1** surface upgrade ·
> **P2** branding/naming · **P3** nice-to-have. File:line evidence is from the live
> webroot unless noted.

### Structural (source-of-truth / preservation) — P0

- **G-P0-1 — The 40-plugin product backend is tracked in NO git branch.**
  `git log --all -- local/sentientia_core/version.php` → **empty**;
  `git ls-files local/ | grep -c sentientia` → **0** on `feat/theme-canonicalize-2026-06-09`.
  The webroot has 40 `local/sentientia_*` plugins; git's `local/` has the *legacy*
  BizLMS tree (`local/courses`, `local/users`, `local/biz_cart`, `local/airpay_lifecycle`,
  `local/program`, …) which **do not exist in the webroot at all**. ADR-026 Move 1
  committed only the **theme** (`git ls-files theme/sentientia | wc -l` = 704 vs webroot
  708). **The product's plugin layer, 6 sentientia blocks, `enrol_sentientiasub`,
  `quizaccess_sentientia_proctoring`, and `payment/gateway/airpay` are untracked.** A
  clean reclone or webroot wipe loses the entire product. This is the literal
  bus-factor scenario ADR-026 §"Why it matters" was written to prevent — it closed
  the theme half, the plugin half is still open. **Highest-priority gap.**
- **G-P0-2 — Git production tree ≠ served product (whole-tree divergence).** The git
  checkout is a stale Moodle webroot whose `local/` + `theme/airpayux` reflect the
  pre-ADR-025 BizLMS state. Deploy-from-git would ship a different (older, branded)
  application than what users see. ADR-026 documents this for the theme; it applies to
  the entire `local/` layer too. The deploy pipeline (`tools/overlay-airpay-customs.ps1`)
  still sources from the hand-maintained webroot, not git.
- **G-P0-3 — `theme_airpayux` (legacy) still tracked in git** (`git ls-files
  theme/airpayux` non-empty) while the webroot serves only `theme_sentientia` and has
  no `theme/airpayux`. Retire-at-cutover is the ADR-026 decision but until then git
  advertises a theme that isn't served.

### (a) Surfaces still on legacy / non-app-shell UI

- **G-A-1 (P1) — App-shell wired into only 2 of 10 layouts.** Only
  `theme/sentientia/layout/columns2.php` and `dashboard.php` call
  `$OUTPUT->airpay_shell_start()` (the shared sidebar shell built from
  `sidebar_navigation::get_context()` →
  `core_renderer.php:259` `airpay_shell_start()`). The other 8 layouts —
  `course.php`, `columns1.php`, `frontpage.php`, `drawers.php`, `embedded.php`,
  `secure.php`, `login.php`, `maintenance.php` — do **not** use the app-shell.
  Layout→page mapping (`config.php:39`): `standard`/`base`/`coursecategory`/`mycourses`/
  `mypublic`/`report` → `columns2.php` (✅ shell); `admin` → `drawers.php` (✗);
  `course`/`incourse` → `course.php` (✗); `popup`/`frametop`/`print` → `columns1.php` (✗).
- **G-A-2 (P1) — In-course activity pages use a bespoke course-player, not the
  app-shell.** `course.php:135–250` builds a custom sticky progress bar + module-tree
  sidebar (`ap-course-sidebar`) and renders `theme_sentientia/course` (which extends
  `{{< theme_sentientia/drawer }}` and includes `{{> theme_sentientia/footer }}`).
  So **every quiz attempt, SCORM player, assignment, forum, lesson** renders in the
  Boost-derived drawer + course-player UI, NOT the Sentientia app-shell sidebar. This
  is the exact gap ADR-027 §"Surface-upgrade workstream" flagged. (Note: AMD *does*
  boot here — `footer.mustache:44` emits `standard_end_of_body_html` — so this is a
  *consistency/styling* gap, not a dead-JS gap.)
- **G-A-3 (P1) — Admin interior pages on `drawers.php`** (no app-shell). Site-admin
  navigation (`/admin/*`) gets Moodle's stock drawer chrome with CSS shims
  (`core_renderer.php:345` `$tabcss`), not the Sentientia sidebar. Acceptable for deep
  config pages but inconsistent with the product shell.
- **G-A-4 (P2) — `frontpage.php` (public landing) is a separate layout** outside the
  app-shell. Expected (guests don't get the sidebar) but worth a styled-consistency
  pass for the B2C storefront entry.
- **G-A-5 (P2/C) — Dashboard ships an INLINE duplicate of the sidebar.**
  `dashboard.mustache:63` renders its own `<aside class="ap-sidebar">…</aside>` markup
  (brand, nav loop, roleswitch) rather than including `theme_sentientia/sidebar`. It
  consumes the same `navitems` context, but the markup is a second copy of the shell
  sidebar → drift risk (a sidebar change must be made in two places). This is the
  "dashboard.mustache has an INLINE sidebar variant" the brief noted.
- **G-A-6 (P2) — Enrolment / checkout / payment surfaces** (`sentientia_cart`,
  `sentientia_catalog/cart.php`, `payment/gateway/airpay`, `enrol_sentientiasub`)
  render via `standard`/`columns2` so they *do* get the shell, but the checkout funnel
  and gateway redirect screens were last restyled in the C4 storefront pass and should
  be re-verified against the app-shell + dark mode (per the 2026-05-29 signup-flow
  fixes, several were previously unstyled).

### (b) Stale branding / naming debt

- **G-B-1 (P2) — ~30 plugins still titled "Airpay X" in user-facing `pluginname`.**
  Evidence (`local/sentientia_*/lang/en/*.php`): `Airpay Advanced Analytics`,
  `Airpay Cart`, `Airpay Course Catalog`, `Airpay Course Engine`, `Airpay User Engine`,
  `Airpay Manager Dashboard`, `Airpay Compliance Report`, `Airpay Reports`,
  `Airpay Online Exams`, `Airpay Classroom Training`, `Airpay Learning Paths`,
  `Airpay Certification Programs`, `Airpay Skills Matrix`, `Airpay Evaluations`,
  `Airpay Gamification`, `Airpay Gamification Challenges`, `Airpay Smart Notifications`,
  `Airpay Email Templates`, `Airpay WhatsApp & SMS`, `Airpay Recompletion`,
  `Airpay AI Learning Assistant`, `Airpay Integrations Hub`, `Airpay Pages`,
  `Airpay Role Management`, `Airpay Course Requests`, `Airpay Employee Lifecycle`,
  `Airpay Proctoring`, `Airpay Privacy (DPDP)`, `Airpay Organization Engine`,
  `Airpay Ratings`. Most egregious: `local_sentientia_platform` = **"Airpay Core
  (shared infrastructure)"** — the foundational plugin. For a white-label product these
  titles show in Site admin → Plugins, nav, and some headings. (Newer plugins —
  aiquiz/calendar/leaderboard/live/m365/pwa/translate/recommendations/core — are
  correctly "Sentientia LMS — X".)
- **G-B-2 (P2) — Login + maintenance + email templates hardcode "airpay academy" as
  user-visible text** (not `{{#str}}`): `core/loginform.mustache:40`
  `<h1 class="airpay-login__hero-title">airpay academy</h1>`; `:37,:103` logo
  `alt="airpay academy"`; `core/maintenance.mustache:84,88` "airpay academy" +
  "airpay academy — enterprise learning platform"; `core/email_html.mustache:67,191`
  "airpay academy" + "Airpay Payment Services Pvt. Ltd." in every branded email.
- **G-B-3 (P1, correctness-adjacent) — Plugins renamed but their table did not.**
  `local_sentientia_learningpath` and `local_sentientia_notifications` still
  read/write the table `{local_airpay_lp_users}`:
  `sentientia_learningpath/classes/privacy/provider.php:20,35,46,57,65,74,84` (privacy
  export/delete) and `sentientia_notifications/classes/rule_engine.php:492,501`. The
  ADR-022 table rename was only done for `ratings`. The privacy provider guards with
  `table_exists`, so it silently exports nothing if the table were ever renamed — a
  latent GDPR/DPDP correctness bug.
- **G-B-4 (P2, correctness-adjacent) — `structured_logger` stamps the wrong component.**
  `sentientia_platform/classes/structured_logger.php:94`
  `'component' => 'local_airpay_' . $plugin` — every structured log row labels itself
  `local_airpay_X` even though the components are now `local_sentientia_X`. Breaks
  APM/log filtering by component.
- **G-B-5 (P3) — 6 theme AMD source-maps still reference `theme_airpayux`.**
  `theme/sentientia/amd/build/{page_title,org_cascade,deprecated,datatable,
  user_status_badge,announcement}.min.js.map`. The F-LOAD-02 hot-fix rewrote `.min.js`
  but not the `.map` siblings. Cosmetic (maps aren't executed) but a debug-tooling
  inconsistency; the durable `overlay-airpay-customs.ps1 Repair-AmdModuleNames` step
  scopes `.js`, not `.map`.
- **G-B-6 (P3) — `sentientia_courses` event strings prefixed "Airpay:"**
  (`lang/en/local_sentientia_courses.php:96,97,104–107`: "Airpay: course shared to
  tenant", etc.) and "An Airpay administrator will review it" (`:107`) — user-facing
  in the log/events report and the request-filed notice.
- **G-B-7 (P3) — `sentientia_whatsapp` user-facing consent copy says "Airpay Academy"**
  (`lang/en/local_sentientia_whatsapp.php:12,13,34,47`) — these are tenant-brandable
  strings that hardcode the customer name; should resolve via `branding_manager` /
  per-customer config for white-label.
- **G-B-8 (P3) — `sentientia_users` signup copy hardcodes "Airpay Academy"**
  (`:147,149`: "Create your Airpay Academy account", "Sign up … Airpay Academy's public
  courses"). Same white-label concern on the B2C signup funnel.
- **G-B-9 (P3) — Theme template comment headers + CSS comments say "Airpay"**
  (`templates/components/*.mustache:2`, `head.mustache:71,109`, `sidebar.mustache:2`,
  `navbar.mustache:115`). Non-user-facing (comments) — lowest priority, but part of a
  full de-brand sweep.

### (c) Correctness debt the ADR-027 gates target

- **G-C-1 (P0/P1) — Gate-1 render-smoke not built.** ADR-027 Gate 1 (0 console
  errors/warnings; `typeof window.require==='function'`; no literal `{{`/`}}` in
  `document.body.innerText`; landmarks present), run per surface × persona, is the
  check that would have caught the dashboard-charts (F-LOAD-01), stale-AMD (F-LOAD-02),
  AND comment-leak (#3) bugs. Status: staged, "Next build" — extends
  `tests/playwright`. Until it exists, the recurring "looks-fine-but-broken" class can
  re-enter on any of the 8 non-shell layouts / parameterized pages.
- **G-C-2 (P1) — Gate-0 sibling scanners not built.** Only the Mustache *comment-leak*
  scanner shipped (`tools/scan_mustache_comment_leaks.php`, hook CHECK 13, CI step).
  The planned siblings — **unescaped `{{{ }}}` on user data**, **missing
  `standard_end_of_body_html`**, **stale `theme_airpayux/*` AMD names**, **hardcoded
  English in `.mustache`** — are not yet scanners. Each maps directly to a gap above
  (G-B-5 stale AMD, G-B-2 hardcoded English, G-A-* end-of-body coverage).
- **G-C-3 (P2) — Triple-brace audit incomplete.** `dashboard.mustache` alone has 23
  `{{{…}}}` (mostly `{{{wwwroot}}}`/`{{{url}}}` → safe, system-derived URLs). No
  user-controlled field was confirmed triple-braced in the spot-checks, but without the
  G-C-2 XSS scanner there is no standing guarantee across 104 theme templates + plugin
  templates.
- **G-C-4 (P1) — `course.php` swallows exceptions silently.** `course.php:245`
  `catch (Exception $e) { /* Non-fatal */ }` — course-player enhancements fail silent.
  Acceptable for resilience but hides data errors from the render-smoke gate; should
  `debugging()` in DEBUG_DEVELOPER.
- **G-C-5 (P2) — Gate-2 (visual snapshot + axe a11y) and Gate-3 (coverage matrix =
  definition-of-done, with the "Sentientia-styled?" column) not published.** The
  surface-upgrade tracker that would make G-A-* measurable does not exist yet.

### (d) Dependency map / coupling (sequencing constraints)

Derived from namespace-reference scans across `local/sentientia_*`, `theme/sentientia`,
`blocks/sentientia_*`, `enrol/sentientiasub`:

- **G-D-1 — `local_sentientia_org` is the load-bearing seam (≈30 dependents).**
  Referenced by analytics, assistant, cart, catalog, challenge, classroom,
  compliance_report, core, courses, emails, evaluation, exams, gamification,
  integrations, learningpath, lifecycle, manager, notifications, pages, platform,
  proctoring, programs, ratings, recompletion, reports, request, roles, skills, users,
  + theme. **Any rename or schema change to `sentientia_org` ripples platform-wide** —
  the single biggest sequencing risk for ADR-022.
- **G-D-2 — `local_sentientia_platform\feature_flags` is the second seam (16
  dependents + theme):** aiquiz, assistant, calendar, catalog, courses, gamification,
  leaderboard, live, m365, pages, platform, pwa, recommendations, translate, whatsapp,
  enrol_sentientiasub, theme. Plus `customer::current()` and `user_type_factory`
  live here. Note `sentientia_platform` (the flag/user-type home) is *distinct* from
  `sentientia_core` (tenant_identity) — two foundational plugins.
- **G-D-3 — `local_sentientia_core` (tenant_identity / tenant_registry seam,
  ADR-019/021) — 5 dependents + theme:** assistant, manager, notifications, platform,
  theme. Narrower, but it is the multi-customer foundation (ADR-021 gated).
- **G-D-4 — `user_type_factory` (ADR-017 polymorphic) — 4 dependents + theme:**
  leaderboard, pages, users, platform, theme. The two-axis composition is consumed in
  `sidebar_navigation.php:74` (defensive `class_exists` guard) and the dashboard.
  Sequencing: foundations must be tracked-in-git + stable **before** any rename wave —
  i.e., G-P0-1 precedes ADR-022.

### (e) Known "not done" markers (placeholders / flagged-OFF / pending)

- **G-E-1 (P2) — Sentientia Live real-time projector is a placeholder.**
  `sentientia_live/trainer/run.php:6,10` "minimal placeholder"; `edit.php:6,12`
  "minimal placeholder … coming soon"; lang `live_runner_pending_title` = "Live runner
  — real-time projector coming soon", `slide_editor_pending_title` = "Slide editor —
  coming soon" (`lang/en/local_sentientia_live.php:144,161,162`). The audience UI +
  charts are built (verified painted in PROJECT-STATE), but the trainer projector
  (live audience count, advance/back, full-screen) is not. Flag-gated `live.enabled`.
- **G-E-2 (P2) — AI Learning Assistant has no production wiring.**
  `sentientia_assistant/version.php:7` "No production wiring; the `core_ai_bridge`
  class is a placeholder". Requires ANTHROPIC_API_KEY + the AI bridge.
- **G-E-3 (P3) — Two notification rules unimplemented.**
  `sentientia_emails/classes/task/process_rules.php:376,385` — `streak_broken`
  ("requires gamification data") and `manager_nudge` ("requires compliance snapshot")
  both `mtrace` "Not yet implemented".
- **G-E-4 (P3) — Evaluation events are placeholders.**
  `sentientia_evaluation/db/events.php:19` — "placeholders … become active once W1-9
  (event emission) ships matching".
- **G-E-5 (P3) — State-cards are stale / un-renamed.** `moodle-enhancement/state-cards/`
  holds ~30 cards still named `airpay_*-state.md` (e.g. `airpay_courses-state.md`,
  `airpay_org-state.md`); only ~10 are `sentientia_*`. 30 cards contain
  pending/gated/WIP/TODO markers. Doc-hygiene + the ADR-README backfill task
  (ADRs 002–017 not in the index) are the documentation debt.
- **G-E-6 (P3) — Feature-flag-OFF features that are incomplete:** the customer-level
  flag layer (`sentientia.customer_level_flags.enabled`, default OFF) is built but the
  multi-customer tenant_registry (ADR-021) it serves is "Proposed — gated".
  `commerce.crossTenantRequest.enabled` gates a full cross-tenant request workflow
  that is built but off by default.
- **G-E-7 (P2) — ADR-022 component rename is GATED/partial.** Only `ratings` was
  renamed (and that merge was reverted on production per PROJECT-STATE). The directory
  + component + table rename for the other 39 plugins is unstarted; `_removed_airpay_ratings`
  clutter sits at the webroot parent. The rename must wait on G-P0-1 (commit to git
  first) and is high-risk given G-D-1/G-D-2.
- **G-E-8 (P3) — Deploy pipeline directory de-brand gap.** `overlay-airpay-customs.ps1`
  defaults `$Source` to a webroot that already has `theme/sentientia/`; a true
  from-git deploy must first lay `theme/airpayux` down AS `theme/sentientia`, which no
  script does (PROJECT-STATE residual #1). The AMD-rename step is durable; the
  directory rename is not scripted.

---

## 3. Dependency Map (for safe sequencing)

```
                         ┌──────────────────────────────┐
                         │ local_sentientia_platform     │  ← feature_flags, customer,
                         │  (feature_flags, user_type,   │     user_type_factory,
                         │   customer, structured_logger)│     structured_logger
                         └───────────────┬───────────────┘
   16 plugins + theme ───────────────────┘ (feature_flags)
    aiquiz, assistant, calendar, catalog, courses, gamification, leaderboard,
    live, m365, pages, pwa, recommendations, translate, whatsapp, enrol_sentientiasub

                         ┌──────────────────────────────┐
                         │ local_sentientia_org          │  ← org hierarchy +
                         │  (branding_manager, org tree) │     branding_manager
                         └───────────────┬───────────────┘
   ~30 plugins + theme ──────────────────┘  (THE widest coupling — change with care)

                         ┌──────────────────────────────┐
                         │ local_sentientia_core         │  ← tenant_identity (ADR-019)
                         │  (tenant_identity, registry)  │     tenant_registry (ADR-021, gated)
                         └───────────────┬───────────────┘
   5 plugins + theme ─────────────────────┘
    assistant, manager, notifications, platform

   theme_sentientia ── role_detector (role axis) ─┐
                    └─ sidebar_navigation ─────────┤── composes BOTH axes;
   user_type_factory (type axis) ─ 4 plugins ──────┘   consumed at sidebar + dashboard
```

**Safe-sequencing rules implied:**
1. Nothing can be safely renamed (ADR-022) until G-P0-1 is closed (commit product to git).
2. `sentientia_platform` and `sentientia_org` are foundations — stabilize + track them
   first; rename them *last* and with the most testing (16 / 30 dependents).
3. `feature_flags` + `branding_manager` are the de-brand levers: per-customer branding
   (G-B-7/B-8) should route through `branding_manager` rather than hardcoded strings,
   so fixing branding and finishing multi-customer (ADR-021) are the same workstream.

---

## 4. Action Plan (D3) — gates first, then UI (per ADR-027 + Nitin 2026-06-09)

> Effort: **S** ≤0.5d · **M** ~1–3d · **L** >3d. Each item lists scope, files,
> dependencies, and the ADR-027 gate that protects it.

### Wave P0 — Blocking correctness + preservation (do first, in this order)

| # | Action | Scope / files | Effort | Prereq | Gate that protects it |
|---|---|---|---|---|---|
| P0-1 | **Commit the 40-plugin product backend into git** (close G-P0-1). Snapshot webroot `local/sentientia_*` (40), `blocks/sentientia_*` (6), `enrol/sentientiasub`, `mod/quiz/accessrule/sentientia_proctoring`, `payment/gateway/airpay` into git on a review branch — mirror the ADR-026 Move-1 pattern used for the theme. `php -l` every file; run conflict-marker + lint gates. | `local/sentientia_*/**`, `blocks/sentientia_*/**`, `enrol/sentientiasub/**`, `mod/quiz/accessrule/sentientia_proctoring/**`, `payment/gateway/airpay/**` | L | none — **this is the #1 task** | Gate 0 (conflict-marker/lint on commit) |
| P0-2 | **Wire the deploy pipeline to git-as-source for plugins** (close G-P0-2/G-E-8). Extend `overlay-airpay-customs.ps1`: source `local/`+blocks+enrol+quizaccess+payment from a git checkout; add the directory de-brand step (`theme/airpayux`→`theme/sentientia`) so a from-git deploy reproduces the webroot byte-for-byte. | `moodle-enhancement/tools/overlay-airpay-customs.ps1` | M | P0-1 | byte-for-byte from-git verify (ADR-026 action 4) |
| P0-3 | **Fix `local_airpay_lp_users` table reference** (close G-B-3). Decide: rename the table to `local_sentientia_lp_users` (with `db/upgrade.php` `rename_table`, both plugins) OR keep the legacy name and document it. Either way, remove the silent-empty privacy risk. | `sentientia_learningpath/classes/privacy/provider.php`, `sentientia_learningpath/db/upgrade.php`, `sentientia_notifications/classes/rule_engine.php` | M | P0-1 | Gate 0 + PHPUnit (privacy provider test) |
| P0-4 | **Fix `structured_logger` component label** (close G-B-4). `'local_airpay_' . $plugin` → `'local_sentientia_' . $plugin` (or accept a fully-qualified component arg). | `sentientia_platform/classes/structured_logger.php:94` | S | P0-1 | PHPUnit (logger emits correct component) |

### Wave P1 — Build/extend the gates (ADR-027), then the highest-value surface upgrades

| # | Action | Scope / files | Effort | Prereq | Gate |
|---|---|---|---|---|---|
| P1-1 | **Build Gate 1 render-smoke** (close G-C-1). Playwright spec: login as each of the 8 personas, visit a curated ~20–30-surface list, assert (a) 0 console errors/warnings, (b) `typeof window.require==='function'`, (c) no literal `{{`/`}}` in `document.body.innerText`, (d) landmark present. Wire into CI (`playwright-linux`), `continue-on-error` for a calibration window then block. | `tests/playwright/**` (⚠ another agent owns this dir — coordinate; do NOT edit concurrently), `.github/workflows/ci.yml` | L | persona fixtures | **is** the gate |
| P1-2 | **Build the Gate-0 sibling static scanners** (close G-C-2). Add scanners mirroring `scan_mustache_comment_leaks.php` for: unescaped `{{{ }}}` on user data, missing `standard_end_of_body_html` in layout templates, stale `theme_airpayux/*` AMD names (incl. `.map`), hardcoded English in `.mustache`. Wire each into pre-commit + CI. | `moodle-enhancement/tools/scan_*.php`, `.claude/hooks/pre-commit.sh`, `.github/workflows/ci.yml` | M | none | **is** the gate |
| P1-3 | **Extend the app-shell to `course.php`/in-course** (close G-A-2) — the biggest UX-consistency win. Either wrap the bespoke course-player in `airpay_shell_start/end`, or formally bless the course-player as the intended in-course chrome and document it. Re-verify quiz attempt / SCORM player / assignment / forum under the chosen shell + dark mode. | `theme/sentientia/layout/course.php`, `templates/course.mustache`, `templates/drawer.mustache` | L | P1-1 (so it can't regress) | Gate 1 + Gate 2 |
| P1-4 | **Extend app-shell to admin (`drawers.php`) + remaining standard layouts** (close G-A-1/G-A-3) where it makes sense, or document the deliberate exceptions in the Gate-3 matrix. | `theme/sentientia/layout/{drawers,columns1,frontpage}.php` | M | P1-1 | Gate 1 |
| P1-5 | **De-duplicate the dashboard sidebar** (close G-A-5). Replace the inline `<aside class="ap-sidebar">` block in `dashboard.mustache` with `{{> theme_sentientia/sidebar }}` so there is one sidebar source. | `theme/sentientia/templates/dashboard.mustache:63+`, `sidebar.mustache` | M | P1-1 | Gate 1 (no regression) + Gate 2 (visual diff) |
| P1-6 | **`course.php` exception visibility** (close G-C-4). Add `debugging(..., DEBUG_DEVELOPER)` in the `catch`. | `theme/sentientia/layout/course.php:245` | S | none | Gate 1 |
| P1-7 | **Re-verify enrolment / checkout / gateway surfaces** under app-shell + dark (close G-A-6). | `sentientia_catalog/cart.php`, `sentientia_cart/*`, `payment/gateway/airpay/*`, `enrol_sentientiasub/*` | M | P1-1 | Gate 1 + Gate 2 |

### Wave P2 — Branding / naming de-brand (behind the gates) + Gate 2/3

| # | Action | Scope / files | Effort | Prereq | Gate |
|---|---|---|---|---|---|
| P2-1 | **Re-title the ~30 "Airpay X" plugins → "Sentientia …"** (close G-B-1), incl. `sentientia_platform` "Airpay Core" → "Sentientia Platform". `pluginname` (+ hi/sw parity). Pure string edits, no rename — low risk. | `local/sentientia_*/lang/{en,hi,sw}/*.php` (`pluginname` + headings) | M | P0-1 | G-C-2 hardcoded-string scanner + Gate 1 |
| P2-2 | **De-brand user-facing template strings** (close G-B-2/B-6). Replace hardcoded "airpay academy"/"Airpay" in `loginform`, `maintenance`, `email_html`, `sentientia_courses` event strings with `{{#str}}` / `branding_manager`-resolved values. | `theme/sentientia/templates/core/{loginform,maintenance,email_html}.mustache`, `sentientia_courses/lang/en/*` | M | P0-1 | G-C-2 hardcoded-string scanner |
| P2-3 | **Route customer name through `branding_manager`** for white-label (close G-B-7/B-8) — `sentientia_whatsapp` + `sentientia_users` signup copy should resolve the customer/tenant name, not hardcode "Airpay Academy". | `sentientia_whatsapp/lang/*`, `sentientia_users/lang/*`, `sentientia_org/classes/branding_manager.php` | M | P0-1; ties to ADR-021 | Gate 1 |
| P2-4 | **Rewrite the 6 stale `.min.js.map` source-maps** (close G-B-5) or regenerate via `grunt amd`; extend the overlay `Repair-AmdModuleNames` scope to `.map`. | `theme/sentientia/amd/build/*.min.js.map`, `overlay-airpay-customs.ps1` | S | none | G-C-2 stale-AMD scanner |
| P2-5 | **Publish Gate-3 coverage matrix with the "Sentientia-styled?" column** (close G-C-5) — every route/template × persona × [static✓ render✓ visual✓ a11y✓ styled✓]. Becomes the merge definition-of-done + the surface-upgrade tracker for P1-3/P1-4. | new `moodle-enhancement/docs/COVERAGE-MATRIX.md` | M | P1-1, P1-2 | **is** Gate 3 |
| P2-6 | **Add Gate 2 (visual snapshot + axe a11y)** in the Playwright job (close G-C-5). | `tests/playwright/**` (coordinate ownership), CI | L | P1-1 | **is** Gate 2 |

### Wave P3 — Nice-to-have / finish flagged-OFF features / docs

| # | Action | Scope / files | Effort | Prereq | Gate |
|---|---|---|---|---|---|
| P3-1 | **Finish Sentientia Live trainer projector** (close G-E-1) — real-time audience count, advance/back, full-screen; replace `run.php`/`edit.php` placeholders. Behind `live.enabled`. | `sentientia_live/trainer/{run,edit}.php`, templates, lang | L | P0-1 | Gate 1 + Gate 2 |
| P3-2 | **Wire AI Learning Assistant `core_ai_bridge`** (close G-E-2) — needs ANTHROPIC_API_KEY; cost-gated like aiquiz. | `sentientia_assistant/classes/core_ai_bridge.php`, settings | L | API key | Gate 1 |
| P3-3 | **Implement `streak_broken` + `manager_nudge` notification rules** (close G-E-3). | `sentientia_emails/classes/task/process_rules.php` | M | gamification + compliance data | PHPUnit |
| P3-4 | **Activate evaluation event emission (W1-9)** (close G-E-4). | `sentientia_evaluation/db/events.php`, observers | M | — | PHPUnit |
| P3-5 | **Doc hygiene** (close G-E-5): rename state-cards `airpay_*`→`sentientia_*`; backfill ADRs 002–017 into `docs/adr/README.md` index. | `moodle-enhancement/state-cards/*`, `docs/adr/README.md` | S | — | n/a |
| P3-6 | **Execute ADR-022 component rename** (close G-E-7) — directory + component + table + WS for the 39 remaining plugins, using `tools/rename/handover.php`. **High-risk: do `sentientia_org` (30 deps) and `sentientia_platform` (16 deps) last, one at a time, each rehearsed on a restored-prod-data branch.** | all `local/sentientia_*`, `tools/rename/*` | L | **P0-1 mandatory**; Gate 1 green | Gate 1 (per-rename smoke) + WS-contract gate (ADR-009) |
| P3-7 | **Retire `theme_airpayux` from git** (close G-P0-3) at the 5.2 cutover (ADR-026 Move 2). | git `theme/airpayux` | S | ADR-011 5.2 cutover | n/a |

### Critical path
`P0-1 (commit product to git)` → `P0-2/3/4` → `P1-1 (Gate 1)` + `P1-2 (Gate 0 siblings)`
→ `P1-3/4/5 (surface upgrades)` → `P2-* (de-brand + Gate 2/3)` → `P3-6 (rename)` last.

**Do NOT start any UI upgrade (P1-3+) or the rename (P3-6) before P0-1 + P1-1** — per
ADR-027's iron rule, a surface change must be protected by a gate, and per G-P0-1 a
change to an untracked plugin can be lost on the next reclone.

---

## 5. Notes, caveats, and method

- **Authoritative tree.** All capability + gap evidence is from the **live webroot**
  (`C:\xampp\htdocs\moodle5\public\`), which is the served Sentientia product. The git
  checkout's `local/` is the pre-rename BizLMS snapshot and is treated as divergent
  (the divergence itself is gaps G-P0-1/2/3). The theme is committed to git on this
  branch (704 files) and matches the webroot within 4 files.
- **`payment/gateway/airpay` is intentionally branded** (ADR-025 decision — it is the
  real Airpay payment gateway product, not a de-brand target). Likewise the
  `paymentmethod_airpay` / `settings_gateway_airpay` strings in `sentientia_cart`.
- **No source files were modified.** The only file written is this report.
- **`tests/playwright/**` was not touched** (another agent owns it). Action items that
  extend it (P1-1, P2-6) flag the coordination need.
- **Scans were scoped** (the full Moodle webroot times out under a single ripgrep);
  patterns were run against `theme/sentientia`, `local/sentientia_*`, `blocks/sentientia_*`,
  `enrol/sentientiasub`, `payment/gateway/airpay`, and `mod/quiz/accessrule/sentientia_proctoring`.
  Exhaustive about *classes* of issues, sampled within each class.
- **What was verified NOT a bug** (to avoid false alarms): in-course pages DO boot AMD
  (`footer.mustache:44` emits `standard_end_of_body_html`, inherited by `course.mustache`
  via `{{< theme_sentientia/drawer }}`); the F-LOAD-01 dashboard end-of-body fix is
  present in the webroot; `role_detector`/`feature_flags`/`user_type` are fully
  de-branded and well-guarded against vanilla/column-less Moodle.
```

