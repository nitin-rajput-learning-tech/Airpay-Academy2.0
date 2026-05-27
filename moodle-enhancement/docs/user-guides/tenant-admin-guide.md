# Tenant Administrator User Guide

**Persona:** Tenant Administrator
**Platform:** Sentientia LMS / Airpay Academy — Theme `airpayux` v1.0.37-beta
**Audience:** Day-to-day tenant administrators running ONE BizLMS costcenter on behalf of a customer
**Status:** v1.0 (2026-05-25) — supersedes the v1-draft skeleton at `tenant-admin.md`
**Test accounts referenced:**
- **Public tenant (id=77):** `academyexadmin@airpay.co.in` — primary capture account for screenshots
- **Airpay tenant (id=1):** `nitin.rajput@airpay.co.in` — cross-tenant comparisons + L&D-admin overlay
- Local password (XAMPP only): `AcademyAudit2026!`
- Local base URL: `http://localhost:8080/moodle/`
- Production base URL: `https://www.airpay.academy/` (read-only for guide capture)

> **Sibling guides:** [`learner-guide.md`](learner-guide.md) · [`course-author-guide.md`](course-author-guide.md) · [`compliance-officer-guide.md`](compliance-officer-guide.md) · [`site-admin.md`](site-admin.md)

---

## Table of contents

1. [Your scope — what a Tenant Admin can and cannot do](#1-your-scope)
2. [First login + dashboard tour](#2-first-login)
3. [Navbar walkthrough](#3-navbar-walkthrough)
4. [Sidebar walkthrough](#4-sidebar-walkthrough)
5. [Dashboard widgets in depth](#5-dashboard-widgets)
6. [User management within your tenant](#6-user-management)
7. [Course / programme / learning-path creation](#7-course-programme-path)
8. [Reporting + analytics](#8-reporting)
9. [Compliance status overview](#9-compliance-overview)
10. [Audience targeting + cohort filtering](#10-audience)
11. [Welcome-email + WhatsApp templates per tenant](#11-templates)
12. [Tenant-specific branding (read-only view)](#12-branding)
13. [Calendar sync (ICS) for tenant-wide events](#13-calendar)
14. [Push notifications + WhatsApp opt-in admin view](#14-push)
15. [AI quiz drafts — review queue](#15-aiquiz)
16. [Real-time leaderboards admin view](#16-leaderboard)
17. [Mobile (590px) walkthrough](#17-mobile)
18. [Hindi UI toggle + locale parity](#18-hindi)
19. [What's new in v1.0.37-beta — affecting Tenant Admins](#19-whats-new)
20. [Troubleshooting common issues](#20-troubleshooting)
21. [Escalation cues — when to call Site Admin](#21-escalation)
22. [Screenshot capture sequence (the local-XAMPP recipe)](#22-screenshot-sequence)
23. [References](#23-references)

---

## 1. Your scope <a id="1-your-scope"></a>

You are a **Tenant Admin**, one tier below Site Admin. You operate inside ONE
BizLMS costcenter inside the customer "Airpay Payment Services". On Airpay
Academy customer-zero today there are three tenants:

| Tenant id | Label              | Who | Typical admin account |
|-----------|--------------------|-----|-----------------------|
| 1         | **Airpay**         | Internal employees (HRMS-synced) | `nitin.rajput@airpay.co.in` (L&D head — also a Tenant Admin for id=1) |
| 77        | **Public**         | External self-registered learners | `academyexadmin@airpay.co.in` (External Admin) |
| 177       | **ZEEA**           | Partner / vendor learners | (assigned externally) |

Tenant scope is enforced at the database-query layer in every Sentientia plugin —
every query filters by `costcenterid` derived from `$USER->open_path`. If you
ever see data that does not belong to your tenant, that is a **P0 cross-tenant
leakage bug**: stop, screenshot, escalate to Site Admin immediately.

### Capability matrix vs Site Admin

| Capability                                              | Tenant Admin | Site Admin |
|---------------------------------------------------------|--------------|------------|
| Manage users in YOUR tenant                             | ✅           | ✅          |
| Manage users in OTHER tenants                           | ❌           | ✅          |
| Create courses / programmes / paths in YOUR tenant     | ✅           | ✅          |
| Edit Site-Admin-only courses                            | ❌           | ✅          |
| Configure SMTP, security, site schema                   | ❌           | ✅          |
| Install / uninstall plugins                             | ❌           | ✅          |
| Customer-level feature flags                            | ❌           | ✅          |
| Tenant-level feature flags                              | 🟡 (read)    | ✅ (write)  |
| Tenant-scoped reports                                   | ✅           | ✅ (all tenants) |
| Tenant branding (logo, colours)                         | ❌ (read-only) | ✅       |
| Trigger HRMS sync, password-reset CLI                   | ❌           | ✅          |
| WhatsApp / push opt-in **overrides**                    | ❌ (illegal under DPDP) | ❌ (also illegal) |

> 🟡 Read-only means you can see flag state in the Switchboard UI but the Apply
> button is disabled.

📸 **Screenshot 01:** `screenshots/tenant-admin/01-capability-matrix.png` — open
the Switchboard at `/local/airpay_core/switchboard.php` and capture the read-only
state vs Site Admin's write state.

---

## 2. First login + dashboard tour <a id="2-first-login"></a>

### Step 1 — Sign in

1. Browse to `http://localhost:8080/moodle/login/index.php`.
2. Enter your test username + password:
   - `academyexadmin@airpay.co.in` / `AcademyAudit2026!` (Public tenant)
   - or `nitin.rajput@airpay.co.in` / `AcademyAudit2026!` (Airpay tenant + L&D-Admin overlay)
3. Click **Sign in**.

📸 **Screenshot 02:** `screenshots/tenant-admin/02-login.png` — capture the
Sentientia login surface (left-column hero, right-column form). Login form
template lives at `theme/airpayux/templates/core/loginform.mustache`, layout at
`theme/airpayux/layout/login.php`, styling at `_surface-login.scss` (refactored
in chip-K to 11 `!important` from 66).

```
+-----------------------------+   +-------------------------------+
|                             |   |   Sign in                    |
|   Sentientia LMS hero       |   |   _________________________   |
|   "Learn. Comply. Lead."    |   |   |  username           |    |
|   gradient bg, brand logo   |   |   _________________________   |
|                             |   |   |  password           |    |
|                             |   |   _________________________   |
|                             |   |   [ Sign in ]                 |
|                             |   |   Forgot password? · Sign up |
+-----------------------------+   +-------------------------------+
```

### Step 2 — Land on the tenant dashboard

After login you arrive at `/my/dashboard.php` (NOT `/my/index.php` — the airpayux
`my_dashboard_redirect.php` core-mod sends you to the Sentientia dashboard). The
dashboard branches on your role:

- If you carry `local/courses:manage` capability (or BizLMS admin role at
  category context) → you see the **L&D Admin / Tenant Admin** shape.
- If you only carry a learner role → you see the **Learner** shape (see
  `learner-guide.md`).

For your account on the Public tenant, the dashboard caption reads:

> "Public — Platform overview and system health"

For Airpay tenant the caption reads:

> "Airpay — Platform overview and system health"

📸 **Screenshot 03:** `screenshots/tenant-admin/03-dashboard-public.png` —
academyexadmin's full dashboard on Public tenant. Expect 5 KPI tiles
(`compliance_rate`, `completed`, `overdue`, `not_enrolled`, `exempted`),
two charts (Enrolment Trend, Course Distribution), Top Courses table,
Activity Timeline, Featured tile.

📸 **Screenshot 04:** `screenshots/tenant-admin/04-dashboard-airpay.png` —
same shape from nitin.rajput's view; numbers will be larger (3,500+ employees vs
~700 Public learners).

### What changed visually in this release

The dashboard shipped chip-C (P0 #5 dashboard inline-style cleanup), chip-G
(F-13 i18n strings — 12 new keys, all 5 locales), and chip-N (P1 #14
chart-loader migration to `{{#js}}` block — kills the inline `<script
src="cdn.jsdelivr.net">` CSP risk).

**Practical effect for you:** the dashboard now renders in your selected
language (English / Hindi / Kannada / Marathi / Swahili — see §18), the charts
load via vendored Chart.js instead of an external CDN, and the compliance KPI
tile respects dark mode (the `#16a34a` / `#dc2626` raw hex literals are gone —
they now resolve through `$ap-success` / `$ap-error` tokens).

---

## 3. Navbar walkthrough <a id="3-navbar-walkthrough"></a>

**Template:** `theme/airpayux/templates/navbar.mustache` (179 lines, audit-clean
post chip-B)
**Styling:** `theme/airpayux/scss/moodle/partials/_surface-navbar.scss`

```
+-----------------------------------------------------------------+
| [Logo: Sentientia/Airpay Academy]                               |
|                                                                 |
| [Dashboard] [My Courses] [Catalog] [Profile]   [search]  [🛒]  |
|                                              [🌗 dark] [photo] |
+-----------------------------------------------------------------+
```

📸 **Screenshot 05:** `screenshots/tenant-admin/05-navbar.png` — desktop
navbar at >1200px.

### Element-by-element

| Slot                  | What it does                                                                             | Visible to Tenant Admin? |
|-----------------------|------------------------------------------------------------------------------------------|--------------------------|
| Logo                  | Returns to `/my/dashboard.php`. Logo path resolved per tenant via `core_renderer`.       | ✅                       |
| Dashboard pill        | Same as logo click. Highlighted (`--active`) when on dashboard. **i18n** since chip-B.   | ✅                       |
| My Courses            | Routes to `/local/airpay_catalog/mycourses.php`. **i18n** since chip-B.                  | ✅                       |
| Catalog               | Routes to `/local/airpay_catalog/index.php`. **i18n** since chip-B.                      | ✅                       |
| Profile               | Routes to `/user/profile.php?id=<self>`. **i18n** since chip-B.                          | ✅                       |
| Search box            | Site-wide search across courses, people, content. Placeholder **i18n** since chip-B.     | ✅                       |
| Cart icon + badge     | Shopping cart (Public tenant only on customer-zero — paid courses).                      | ✅ Public · ❌ Airpay     |
| Dark-mode toggle      | Flips `data-theme` between `light` and `dark`. Persists in `localStorage`.               | ✅                       |
| User photo + dropdown | Profile, Preferences, Language, Logout. Shows your full name + role.                     | ✅                       |

📸 **Screenshot 06:** `screenshots/tenant-admin/06-navbar-dropdown.png` — click
your photo top-right; capture the user-menu dropdown (Profile / Preferences /
Language / Logout).

### Cart slot — Public tenant only

When `costcenterid === 77`, the cart icon and badge render. The badge is hidden
(`hidden` HTML attribute since chip-Q replaced `style="display:none;"`) until a
non-zero cart count is detected. Cart count is driven by `local_airpay_cart`
via `theme_airpayux/cart_badge` AMD module (chip-S follow-up wired across
dashboard.php + course.php layouts).

When you log in as academyexadmin (Public) the cart is visible; when you log in
as nitin.rajput (Airpay employees do not buy courses) the cart slot is hidden
at the renderer level by capability check.

---

## 4. Sidebar walkthrough <a id="4-sidebar-walkthrough"></a>

**Template:** `theme/airpayux/templates/sidebar.mustache` (plus
`drawers.mustache` for the BS5 left nav drawer).
**Logic:** `theme/airpayux/classes/output/role_detector.php` (introduced in the
Goal A.x wave — single source of truth for which sidebar items show per role).

As a Tenant Admin you should see the **L&D-Admin-equivalent** sidebar inside
your tenant scope. The role detector returns `tier=admin_tenant`; the sidebar
template iterates a 9-item list:

| # | Sidebar item       | URL                                            | Notes                                       |
|---|--------------------|------------------------------------------------|---------------------------------------------|
| 1 | Dashboard          | `/my/dashboard.php`                           | Always first                                |
| 2 | Manage Users       | `/local/airpay_users/manage.php`              | Scoped to your tenant                       |
| 3 | Courses            | `/local/airpay_courses/manage.php`            | Tenant-scoped category list                 |
| 4 | Catalog            | `/local/airpay_catalog/index.php`             | Public catalogue + admin overlays           |
| 5 | Programmes         | `/local/airpay_programs/manage.php`           | Multi-course cohorted intakes               |
| 6 | Learning paths     | `/local/airpay_learningpath/manage.php`       | Sequenced course bundles                    |
| 7 | Reports            | `/local/airpay_reports/index.php`             | Tenant-scoped reports                       |
| 8 | Compliance         | `/local/airpay_compliance_report/index.php`   | Tenant-scoped (Airpay only — Public N/A)    |
| 9 | Analytics          | `/local/airpay_analytics/admin.php`           | KPI dashboards + funnels                    |

📸 **Screenshot 07:** `screenshots/tenant-admin/07-sidebar-airpay.png` — full
sidebar from `nitin.rajput@airpay.co.in` on Airpay tenant.

📸 **Screenshot 08:** `screenshots/tenant-admin/08-sidebar-public.png` — same
sidebar from `academyexadmin@airpay.co.in` on Public tenant. **Difference**:
no Compliance item (Public tenant has no statutory training stack); an extra
**My Cart** item replaces Compliance in slot 8 (Public e-commerce).

### What changed in this release

The Goal-A audit caught a sidebar/dashboard role-detection drift (Bug #11 —
Compliance Officer saw only 5 Learner items because the sidebar used
capability-only checks while dashboard.php honoured BizLMS role assignment).
ADR-009 introduced the shared `role_detector` class. The sidebar now mirrors
dashboard.php's tier resolution exactly; the `role_detector_test` PHPUnit class
covers all 7 detection methods × 5 tiers.

---

## 5. Dashboard widgets in depth <a id="5-dashboard-widgets"></a>

The L&D-Admin dashboard renders 6 widget bands. Each is described below in the
order they appear from top to bottom.

### 5.1 Welcome header

```
+----------------------------------------------------------------+
|  Welcome back, <firstname>                                     |
|  <tenant_name> — Platform overview and system health          |
+----------------------------------------------------------------+
```

- Source: `dashboard.mustache:174-185` after chip-C cleanup. Inline styles removed;
  classes now `.airpay-dash__welcome-title` and `.airpay-dash__welcome-subtitle`,
  styled in `_surface-dashboard.scss` against `$ap-text-primary` /
  `$ap-text-secondary`.
- Strings: 6 new theme_airpayux keys (`welcome_back_admin`,
  `welcome_manager`, `welcome_learner`, `subtitle_admin`, `subtitle_manager`,
  `subtitle_learner`) — Hindi + Kannada + Marathi + Swahili parity all at
  178/178 keys after chip-#255.

📸 **Screenshot 09:** `screenshots/tenant-admin/09-welcome-header.png` —
desktop welcome header.

### 5.2 KPI tile strip (5 tiles)

Source of truth: `local/airpay_compliance_report/index.php:151-184` builds
`$kpi_tiles` array; rendered via `stat_card.mustache` partial.

| Tile             | Source field        | Colour gate                                |
|------------------|---------------------|--------------------------------------------|
| Compliance Rate  | `compliance_rate`   | `success` ≥80%, `warning` <80%             |
| Completed        | `completed`         | `success`                                  |
| Overdue          | `overdue`           | `danger` if >0, `primary` (muted) if 0     |
| Not Enrolled     | `not_enrolled`      | `warning`                                  |
| Exempted         | `exempted`          | `info`                                     |

📸 **Screenshot 10:** `screenshots/tenant-admin/10-kpi-tiles.png` — capture the
KPI strip. Compare Public (small numbers, often 0 overdue) with Airpay (3,500+
denominator).

### 5.3 Charts band (Enrolment Trend + Course Distribution)

Two `<canvas>` elements rendered via Chart.js (vendored to
`theme/airpayux/javascript/chart.umd.min.js` since chip-#257; loaded via
`{{#js}}` block since chip-N — no external CDN, CSP-safe).

- **Enrolment Trend** — line chart, last 12 months × enrolment count.
- **Course Distribution** — doughnut chart, count of courses per category.

Both canvases carry `aria-label` since chip-N (F-15 fix) and a
visually-hidden `<table>` fallback so screen readers can read the data.

📸 **Screenshot 11:** `screenshots/tenant-admin/11-charts.png` — both charts
side-by-side, light mode.

📸 **Screenshot 12:** `screenshots/tenant-admin/12-charts-dark.png` — both
charts in dark mode (click the moon icon top-right). Verify legend text is
readable on the dark surface.

### 5.4 Top Courses table

Top 5 courses by enrolment, scoped to your tenant. Columns: Course / Category /
Enrolled / Completed / Completion %.

Inline progress bar in the Completion % column. Dynamic `style="width:N%"` is
the one legitimate inline-style use kept after chip-C (per F-12 verdict).

📸 **Screenshot 13:** `screenshots/tenant-admin/13-top-courses.png`

### 5.5 Activity Timeline

Last 10 platform events visible to your tenant — enrolments, completions,
certificate issuance, cohort additions. Auto-refreshes every 60s via vanilla
JS poll (NOT SSE — SSE is reserved for Sentientia Live; ADR-004).

📸 **Screenshot 14:** `screenshots/tenant-admin/14-activity-timeline.png`

### 5.6 Featured tile

A single highlighted course / programme — set by Site Admin in
`/admin/settings.php?section=local_airpay_catalog`. Tenant Admin can request a
change but cannot edit it directly.

📸 **Screenshot 15:** `screenshots/tenant-admin/15-featured-tile.png`

---

## 6. User management within your tenant <a id="6-user-management"></a>

### 6.1 Browse users

`/local/airpay_users/manage.php`

Lists every user whose `open_path` begins with your tenant's path (Airpay
employees: `/1/...`; Public learners: `/77/...`). Default sort: `lastname ASC`.

Filters in the left rail:
- Search (matches firstname + lastname + email + idnumber)
- Suspended (yes / no / all)
- Designation
- Department (Airpay only)
- Date of joining range (Airpay only)

📸 **Screenshot 16:** `screenshots/tenant-admin/16-user-list.png`

### 6.2 Add a single user

`/local/airpay_users/add.php` — 24-column form. Required: `username`, `email`,
`firstname`, `lastname`. Optional but recommended: `idnumber` (employee ID),
`manager_id`, `designation`, `doj`, `dob`.

The form auto-stamps `costcenterid` with your tenant — you cannot override it.
This is enforced at the controller layer (PHP) AND at the validate layer
(form rule), AND at the DB level (the unique key `idx_costcenter` filters
on input).

📸 **Screenshot 17:** `screenshots/tenant-admin/17-add-user-form.png`

### 6.3 Bulk import (CSV)

`/local/airpay_users/import.php`

| Step | What happens                                                                          |
|------|---------------------------------------------------------------------------------------|
| 1    | Upload CSV with header row.                                                           |
| 2    | The form parses headers and previews 5 sample rows mapped to fields.                  |
| 3    | You confirm column mappings or change them via dropdowns.                             |
| 4    | "Run dry" button → shows what WOULD happen (create vs update vs skip vs error).      |
| 5    | "Run live" button → actually writes. You see a progress bar; final report downloads. |

The same parser runs the nightly HRMS sync (Airpay only — Public tenant is
self-registered). HRMS sync logs are at `/local/airpay_users/sync_log.php`
(Tenant Admin can read; Site Admin can re-trigger).

📸 **Screenshot 18:** `screenshots/tenant-admin/18-bulk-import-preview.png`

### 6.4 Suspend / un-suspend

Row action menu on `/local/airpay_users/manage.php`. Suspending blocks login +
freezes enrolments. The user retains all completion + certificate records.

### 6.5 Password reset (you trigger; you cannot see passwords)

Row action → "Send password reset email". The user receives a tokenised link
from Moodle's standard password-reset flow.

If they cannot access their email (e.g. left the company), you must escalate
to Site Admin — Site Admin runs `php admin/cli/reset_password.php` from
the XAMPP shell.

### 6.6 What the audit found about user management

Goal-A walk verified `/local/airpay_users/manage.php` renders in Sentientia
brand chrome at all sizes; Bug #4 (My Courses sort by start date) is in the
learner surface, not here.

---

## 7. Course / programme / learning-path creation <a id="7-course-programme-path"></a>

### 7.1 Create a course

`/course/edit.php?category=<your-tenant-category>`

This is Moodle's standard course-edit form. The Goal-A audit flagged
`/course/edit.php` as one of the six remaining "Moodle leak" surfaces (point 4
in audit report §"Where the Moodle leak truly happens"). Subsequent chips have
not yet redesigned this surface — expected in Phase 2 React/Next overlay.

Practically: the form WORKS, it just looks more "Moodle" than the rest. When
you save, the course lands in your tenant's category tree and is visible only
to your tenant.

📸 **Screenshot 19:** `screenshots/tenant-admin/19-create-course.png`

### 7.2 Create a programme (`local_airpay_programs`)

`/local/airpay_programs/edit.php`

A programme is a sequenced bundle of courses with a fixed delivery schedule
(cohorted intake). Use this for onboarding waves, leadership development,
certification tracks.

### 7.3 Create a learning path (`local_airpay_learningpath`)

`/local/airpay_learningpath/edit.php`

A path is like a programme without the fixed schedule — prerequisites enforced
("complete course A before unlocking B"), but learners progress at their own
pace.

📸 **Screenshot 20:** `screenshots/tenant-admin/20-create-path.png`

### 7.4 Audience attach

Each course/programme/path has an Audience editor at
`/local/airpay_courses/audience.php?courseid=<id>`. Cohort sync auto-enrols all
current + future members. See §10 for the dedicated audience walkthrough.

---

## 8. Reporting + analytics <a id="8-reporting"></a>

| Report                | URL                                                | Output formats   |
|-----------------------|----------------------------------------------------|------------------|
| Compliance status     | `/local/airpay_compliance_report/index.php`        | HTML / CSV       |
| Course catalogue      | `/local/airpay_catalog/mycourses.php?admin=1`      | HTML / CSV       |
| Skill matrix          | `/local/airpay_skills/matrix.php`                  | HTML / CSV       |
| Engagement KPIs       | `/local/airpay_analytics/admin.php`                | HTML             |
| Audit log             | `/local/airpay_audit_log/index.php` (if installed) | HTML / CSV       |
| Reports hub           | `/local/airpay_reports/index.php`                  | Multiple — query builder |

CSV format across Sentientia is consistent: UTF-8 with BOM, semicolon
separator, dates ISO-8601, Excel-friendly for Indian Hindi/regional rows.

📸 **Screenshot 21:** `screenshots/tenant-admin/21-reports-hub.png`

📸 **Screenshot 22:** `screenshots/tenant-admin/22-analytics-funnel.png` —
funnel chart from `airpay_analytics`: catalogue view → enrol → start → complete.

---

## 9. Compliance status overview <a id="9-compliance-overview"></a>

**Airpay tenant only** — Public tenant has no statutory training stack so
`/local/airpay_compliance_report/` is hidden from sidebar for academyexadmin.

The compliance dashboard ships **5 tabs** (driven by `?tab=` param):

| Tab        | What it shows                                                                      |
|------------|------------------------------------------------------------------------------------|
| `matrix`   | Per-user × per-course completion grid. 50 rows / page. Default tab.                |
| `defaulters` | Users with at least one expired or in-progress mandatory course.                 |
| `scorecard` | Department-level rollup.                                                          |
| `manager`  | Manager → direct-reports rollup (mirrors what each manager sees on their dashboard). |
| `config`   | Add / remove courses from compliance tracking; exclude / include users.            |

### Six-state engine

Each (user, course) pair resolves to one of:

| State                    | Colour    | Meaning                                           |
|--------------------------|-----------|---------------------------------------------------|
| `not_enrolled`           | grey      | User in scope but not enrolled                    |
| `enrolled_not_started`   | grey-blue | Enrolled, no activity yet                         |
| `in_progress`            | yellow    | Started, not yet complete                         |
| `completed_current`      | green     | Done, within validity period                      |
| `completed_expiring`     | orange    | Done, validity expires within 30 days             |
| `completed_expired`     | red       | Done, but validity has lapsed (recompletion due)  |

The compliance engine refreshes hourly via
`\local_airpay_compliance_report\task\refresh_aggregates`. The "last refreshed"
banner on top of the page tells you whether you are looking at stale data
(>2h since last refresh shows the orange "stale" pill).

📸 **Screenshot 23:** `screenshots/tenant-admin/23-compliance-matrix.png`

📸 **Screenshot 24:** `screenshots/tenant-admin/24-compliance-defaulters.png`

📸 **Screenshot 25:** `screenshots/tenant-admin/25-compliance-config.png`

### Drill-down

Click any cell in the matrix → modal opens with the user's full course history
(every attempt, every score, every recompletion event). The drill-down was
added in chip-#259 state-card refresh.

### CSV export

Top-right "Export" button → CSV of the active tab. Statutory-return formatted —
suitable for RBI / POSH / DPO audit packs.

---

## 10. Audience targeting + cohort filtering <a id="10-audience"></a>

`/local/airpay_courses/audience.php?courseid=<id>`

Audience targeting decides who can self-enrol / who is auto-enrolled into a
course. Three patterns, can be combined:

### 10.1 Cohort sync

`Course → Users → Enrolment methods → Add method → Cohort sync`. Pick a cohort
+ role (usually "Student"). Every current + future cohort member is auto-enrolled.

### 10.2 Designation / department filter

In the airpayux Audience editor: pick one or more designations OR departments.
The bulk-enrol button enrols everyone matching today; new joiners are picked
up by the nightly sync.

### 10.3 Self-enrol with capability gate

Set enrolment method to "Self enrolment", then attach an audience filter. Users
who meet the filter see the "Enrol me" button in the catalogue; users who do
not, see "Request access" which routes to `local_airpay_request` (manager
approval flow — see `learner-guide.md` §6 and `manager.md`).

📸 **Screenshot 26:** `screenshots/tenant-admin/26-audience-editor.png`

---

## 11. Welcome-email + WhatsApp templates per tenant <a id="11-templates"></a>

### 11.1 Welcome email

`/local/airpay_users/welcome_template.php`

Each tenant gets its own template stored as Markdown in
`local_airpay_users/templates/welcome-{tenantid}.md`. Token substitutions:

```
{firstname}        — User's first name
{lastname}         — Last name
{username}         — Login username
{email}            — Email
{tenant_name}      — Airpay / Public / ZEEA
{login_url}        — Direct login URL
{designation}      — User's designation (HRMS-synced for Airpay)
{manager_name}     — Reporting manager name
```

Hindi parity is **required** for Airpay tenant (Hindi is the default language
of many internal employees). The bilingual template is auto-selected based on
`$USER->lang`.

📸 **Screenshot 27:** `screenshots/tenant-admin/27-welcome-template.png`

### 11.2 WhatsApp templates (opt-in-only)

`/local/airpay_whatsapp/admin/templates.php`

You see a read-only list of all 5 seeded DLT-pending templates:
`deadline_7d`, `deadline_3d`, `deadline_1d`, `team_overdue`, `course_completed`.

You CANNOT add or edit templates — that requires Meta Business API DLT
approval which only Site Admin handles. You CAN see delivery analytics per
template per tenant.

---

## 12. Tenant-specific branding (read-only view) <a id="12-branding"></a>

`/local/airpay_core/customer_brand.php?customerid=1`

You see the active branding for your customer (logo, primary / accent
colours, font family, favicon). The page is read-only for you — only Site Admin
can edit, and on Phase 0/1 there is only one customer (Airpay), so this is
not a frequent change.

📸 **Screenshot 28:** `screenshots/tenant-admin/28-branding-readonly.png`

When the Phase 2 white-label work lands, this page will become a per-customer
override panel; today it shows the resolved bundle.

---

## 13. Calendar sync (ICS) for tenant-wide events <a id="13-calendar"></a>

`/local/sentientia_calendar/index.php`

This is your personal ICS subscription page. Each user (including you as a
Tenant Admin) gets a tokenised feed URL covering THEIR course deadlines,
classroom (ILT) sessions, and exam close dates. Plain RFC 5545 conformant
ICS so Outlook / Google / Apple Calendar consume it natively.

**Feature flag:** `sentientia.calendar_sync.enabled` — default OFF. Site Admin
flips it in Switchboard before learners can use it.

When the flag is ON you see:
- Your subscription URL (copy-to-clipboard button)
- 3 per-event-type sub-flags (courses / classroom / exams — default ON each)
- A "Regenerate token" button (sesskey-protected; the old URL stops working)

📸 **Screenshot 29:** `screenshots/tenant-admin/29-calendar-sync.png`

You cannot push a tenant-wide event into every learner's calendar — that
would violate consent. You CAN communicate the existence of the feature via
your welcome-email template.

---

## 14. Push notifications + WhatsApp opt-in admin view <a id="14-push"></a>

### 14.1 PWA admin push log

`/local/sentientia_pwa/admin/push_log.php`

Filter by user / result / since. Tells you which learners in your tenant
received which push (and which failed). 90-day retention via the daily
`purge_old_subscriptions` scheduled task.

You CANNOT force a user to subscribe — that is an iOS / Chrome / Edge
browser-permission interaction. You CAN see which users have subscribed via
the `count_users_subscribed` aggregate banner.

📸 **Screenshot 30:** `screenshots/tenant-admin/30-push-log.png`

### 14.2 WhatsApp opt-in status

`/local/airpay_whatsapp/admin/opt_in_status.php`

Same shape — per-tenant aggregate of opt-in counts + per-user opt-in dates.
You CANNOT force-enable — DPDP Act 2023 prohibits silent opt-in.

📸 **Screenshot 31:** `screenshots/tenant-admin/31-whatsapp-optin.png`

---

## 15. AI quiz drafts — review queue <a id="15-aiquiz"></a>

`/local/sentientia_aiquiz/review.php`

The AI Quiz Generation (Tier 1 #4, Phase G.0) plugin produced quiz draft
candidates from source content. Tenant Admins can see drafts produced by
their tenant's Course Authors — you can approve / edit / reject per question,
then push approved questions to a `mod_quiz` activity.

The plugin runs in MOCK mode by default (`sentientia.aiquiz.live_api`
flag is OFF). When Site Admin flips the live-API flag with `[CONFIRM]`, real
Anthropic API calls run.

📸 **Screenshot 32:** `screenshots/tenant-admin/32-aiquiz-review.png`

---

## 16. Real-time leaderboards admin view <a id="16-leaderboard"></a>

`/local/sentientia_leaderboard/admin.php`

Board types: quiz / completion / skill. Each board can be tenant-scoped or
cohort-scoped. You see the board, the participant count, the opt-out count
(GDPR-compliant — see ADR-014), and a refresh-now button.

Leaderboard ranking is live via SSE (Server-Sent Events) — same realtime infra
as Sentientia Live (ADR-004). 24h throttle on rank-change notifications
(chip-P3-O / `intelligent-ride-82LNQ`).

📸 **Screenshot 33:** `screenshots/tenant-admin/33-leaderboard-admin.png`

---

## 17. Mobile (590px) walkthrough <a id="17-mobile"></a>

**Primary mobile breakpoint:** 590px (matches the airpayux `_surface-*.scss`
discipline). Secondary: 480px (SE / 12-mini), 380px (very small Galaxy S).

To replicate: open Chrome DevTools → Toggle Device Toolbar (Ctrl+Shift+M) →
Responsive → set width to 590.

### 17.1 Navbar — mobile shape

Top nav collapses to a hamburger trigger + logo + dark-toggle + user-photo.
The mobile bottom-nav band (`mobile_bottom_nav.mustache`) appears with the 4
primary pills (Home / Catalog / My Courses / Profile) with `aria-current="page"`.

📸 **Screenshot 34:** `screenshots/tenant-admin/34-mobile-navbar.png`

### 17.2 Sidebar — mobile drawer

Hamburger trigger opens the BS5 drawer. The drawer template
(`drawer.mustache`) was backported in chip-#264 for Moodle 5.2 compat with
5.1 guards.

📸 **Screenshot 35:** `screenshots/tenant-admin/35-mobile-drawer.png`

### 17.3 Dashboard — mobile stack

KPI tiles stack vertically (1 column). Charts compress to ~85vw width. Top
Courses table gets horizontal scroll inside its card. Activity Timeline keeps
its full height but each row wraps copy.

📸 **Screenshot 36:** `screenshots/tenant-admin/36-mobile-dashboard.png`

### 17.4 Compliance matrix — mobile

The matrix becomes a vertically-scrolling "card-per-user" layout below 590px.
Each card shows the user's name, designation, and a chip-strip of status pills
(one chip per compliance course).

📸 **Screenshot 37:** `screenshots/tenant-admin/37-mobile-compliance.png`

### 17.5 What changed for mobile in this release

- Chip-D / P2 #19 follow-up — all `_surface-*.scss` inline transition timings
  migrated to `var(--ap-transition-*)` tokens that collapse to 0ms under
  `prefers-reduced-motion: reduce` (WCAG 2.3.3).
- Chip-#264 — `drawer.mustache` Moodle 5.2 backport + 5.1 guards (no visual
  change today; future-proofs mobile drawer).

---

## 18. Hindi UI toggle + locale parity <a id="18-hindi"></a>

Top-right user menu → Preferences → Language → "हिन्दी (hi)" → Save.

After save, every page reloads in Hindi. The post-chip-#255 locale parity
table:

| Locale | String count | Parity |
|--------|--------------|--------|
| `en`   | 178          | 100%   |
| `hi`   | 178          | 100% ✅ |
| `kn`   | 178          | 100% ✅ |
| `mr`   | 178          | 100% ✅ |
| `sw`   | 178          | 100% ✅ |

Audit baseline (2026-05-24) caught hi at 85%, kn at 76%, mr+sw at 96%. Chip-B
added the 13 new keys (navbar / footer / dashboard); chip-#255 closed the
parity sweep across all 5 locales.

📸 **Screenshot 38:** `screenshots/tenant-admin/38-hindi-dashboard.png`

📸 **Screenshot 39:** `screenshots/tenant-admin/39-hindi-sidebar.png`

### Hindi parity for tenant content

The theme strings are at 100%. Course content (titles, summaries, activity
labels) is the Course Author's responsibility — see `course-author-guide.md`
§8 for the Hindi readiness checklist + `/local/airpay_courses/hindi_audit.php`.

---

## 19. What's new in v1.0.37-beta — affecting Tenant Admins <a id="19-whats-new"></a>

The 2026-05-24 Day-0 chip wave landed 21 merges. Below are the changes that
touch the Tenant Admin surface. Each chip is referenced by its alphabetical
identifier or merge id; full audit doc at
`docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md`.

| # | Chip | Surface affected | What you'll notice |
|---|------|------------------|--------------------|
| 1 | A — `git rm` orphan `Claude` SCSS | Theme build | None visible — faster theme compile (98 KB stripped). |
| 2 | B — Move `MONOLITH_BACKUP.scss` to `_archive/` | Theme build | None visible — 284 KB removed from compile path. |
| 3 | B — Navbar i18n (8 strings) | Navbar | Nav items render in your selected locale. |
| 4 | B — Footer i18n (4 strings) | Footer | Footer links render in your selected locale. |
| 5 | C — Dashboard inline-style cleanup (34 inline styles + 5 hex literals) | Dashboard | KPI tile colours respect dark mode; tokens cascade properly. |
| 6 | C — Footer attribution band styled via SCSS | Footer | Footer attribution band themes correctly in dark mode (no more white-on-white). |
| 7 | #255 — Locale parity restored to 178/178 across en/hi/kn/mr/sw | All UI | Hindi / regional users see 100% translated theme strings. |
| 8 | E — `aria-live` regions in `sentientia_live` | Live-engagement | Live polls announce updates to screen readers (NVDA verified in chip-P2-H). |
| 9 | F — `navbar` cart-badge IIFE extracted to AMD module | Navbar | CSP-strict customers can now use the cart badge. |
| 10 | J — `_surface-profile.scss` split into 4 partials | Profile / Badges / Grades / Calendar / User edit / Preferences | None visible — same UI; faster compile + cleaner cascade. |
| 11 | K — `_surface-login.scss` `!important` cleanup (66 → 11) | Login | None visible — easier future restyling. |
| 12 | P1 #12 + H — `:focus-visible` siblings added across `_bizlms-admin.scss` + surface partials | Every interactive element | Mouse-click stops flashing the focus ring; keyboard nav still rings correctly. |
| 13 | I — `dark_mode.scss` token cascade (253 → 36 `!important`) | Every dark-mode surface | Dark mode now respects token cascade — fewer override surprises when site admin changes brand colours. |
| 14 | L — Footer mobile breakpoint added | Footer mobile | Footer no longer overflows on Galaxy S series (<400px). |
| 15 | M — Sentientia Live Bootstrap-utility → BEM tokens | Live-engagement | Live plugin buttons / badges now match Sentientia brand shape across light + dark. |
| 16 | G — Dashboard 11 hardcoded strings extracted to lang | Dashboard | KPI labels + chart titles translate. |
| 17 | N — Chart.js vendored + `{{#js}}`-block init | Dashboard | Charts work offline / CSP-restricted networks. CDN dependency removed. |
| 18 | #18 — `_moodle-overrides.scss` `!important` trim | Site-wide chrome | Cascade reads correctly; fewer override surprises. |
| 19 | #19 + D — `prefers-reduced-motion` stylelint rule + inline-timing → token migration | Every animation | Vestibular-accessibility safe (WCAG 2.3.3). |
| 20 | Q — coursebannerimage CSS-url injection safety doc | Course banner | Doc-only — no runtime change. |
| 21 | O / #21 — footer removed-badge comment block trimmed | Footer | None visible — template hygiene. |

### Bonus (rolled in same wave)

- **P0-A** — Conflict-marker pre-commit hook + CI gate (catches stray `<<<<<<<`
  in PHP / lang files; protects you from broken merges hitting production).
- **P2-J** — Cutover-day smoke-test harness (`scripts/cutover-smoke-test.py`).
- **P3-N** — Calendar Sync OAuth scaffolding (Outlook + Google bi-directional
  flag, default OFF).
- **P3-O** — Leaderboard L.1 rank-change notifications (24h throttle).

📸 **Screenshot 40:** `screenshots/tenant-admin/40-whats-new-diff.png` —
side-by-side desktop dashboard before/after v1.0.37-beta (use the pre-chip-C
asset at `docs/visual-evidence/2026-05-21/regression-dashboard.png` if you do
not have an earlier baseline locally).

---

## 20. Troubleshooting common issues <a id="20-troubleshooting"></a>

### Symptom — "I cannot see a user that I should be able to manage"

| Possible cause                                                   | Resolution                                                                                                            |
|-------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------|
| User belongs to a different tenant (`open_path` does not match yours) | Confirm in `/admin/user.php?id=<uid>` (Site Admin); cannot cross-tenant manage.                                       |
| User is `suspended` or `deleted`                                  | Toggle the "Suspended / Deleted" filter on `/local/airpay_users/manage.php`.                                          |
| HRMS sync did not include the user                                | Site Admin re-runs `\local_airpay_users\task\hrms_sync_task` and confirms the user is in the source CSV.              |

### Symptom — "Dashboard shows stale numbers"

The compliance engine refreshes hourly. If you see "Last refreshed: 4 hours
ago" pill banner, request Site Admin run the refresh task manually:

```bash
php admin/cli/scheduled_task.php \
    --execute='\local_airpay_compliance_report\task\refresh_aggregates'
```

### Symptom — "User says they did not receive welcome email"

1. Check `/local/airpay_users/sync_log.php` for delivery attempt.
2. Check email server logs (Site Admin only — SMTP at `/admin/settings.php?section=outgoingmailconfig`).
3. Confirm user's email is correct in their profile.
4. Resend manually from the row action menu in `/local/airpay_users/manage.php`.

### Symptom — "Compliance status looks wrong (impossible numbers)"

This is a P1 escalation — never edit the DB to "fix" it. Site Admin runs:

```bash
php admin/cli/scheduled_task.php \
    --execute='\local_airpay_compliance_report\task\refresh_aggregates'
```

If still wrong after a refresh, file a bug — likely a `costcenterid`-filter
regression in the engine.

### Symptom — "I see data from a different tenant"

**STOP. SCREENSHOT. ESCALATE TO SITE ADMIN.**

This is a P0 cross-tenant leakage — the highest-severity class of bug. Do not
proceed until Site Admin (or Nitin) clears the suspicion.

### Symptom — "Push notifications not arriving for a user"

| Step                                                                                              |
|---------------------------------------------------------------------------------------------------|
| Confirm user opted in via `/user/preferences/sentientia_pwa.php` (they must click "Subscribe").  |
| Check `/local/sentientia_pwa/admin/push_log.php` for delivery attempts to that user.              |
| If `result=410` (subscription gone) — the user uninstalled / cleared browser; ask them to re-subscribe. |
| If `result=429` (rate-limited) — back off; the cron sender has its own throttle.                  |
| If `result=5xx` consistently — escalate to Site Admin; VAPID keys may need regeneration.          |

### Symptom — "WhatsApp delivery fails"

DLT templates need approval before live mode. Until Site Admin flips
`engagement.whatsapp.reminders` ON with vendor credentials present in `.env`,
WhatsApp runs in mock mode (logs the attempt, never actually POSTs).

### Symptom — "Dark mode looks broken on one page"

Open Chrome DevTools → Elements → search for `display: none` or `color: #` on
that surface. If you find an inline style, that is a F-12 / chip-C regression
that should be reported (file under `docs/visual-evidence/<today>/` with the
URL + a screenshot).

### Symptom — "Mobile layout overflows horizontally"

Run the cutover smoke test (Site Admin):

```bash
python scripts/cutover-smoke-test.py --base-url http://localhost:8080/moodle/ \
    --persona tenant-admin --width 590
```

It walks the 8 surfaces and emits JUnit XML.

---

## 21. Escalation cues — when to call Site Admin <a id="21-escalation"></a>

| Trigger                                                              | Action                                                                       |
|----------------------------------------------------------------------|------------------------------------------------------------------------------|
| You see data from a tenant that is not yours                         | P0 — stop, screenshot, escalate immediately.                                 |
| A user is locked out and cannot reset via email                      | Site Admin runs CLI password reset.                                          |
| A plugin appears disabled or throws errors sitewide                  | Site Admin checks `/admin/plugins.php` + error logs.                         |
| Compliance numbers look impossible after a fresh refresh             | Site Admin runs `refresh_aggregates` again, then escalates to Nitin if same. |
| A learner requests a refund (paygw_airpay)                           | Site Admin has refund capability.                                            |
| New cohort needed                                                    | Site Admin creates cohort definition; you attach to courses afterwards.      |
| Branding change (logo, colour, font)                                 | Site Admin owns `customer_brand`; file ticket.                               |
| You need a new WhatsApp template                                     | Site Admin submits to Meta DLT; ~3 day turnaround.                           |
| You need a new feature flag at customer scope                        | Site Admin only — customer flags require `sentientia.customer_level_flags.enabled` gate ON. |
| Bug report                                                           | Always: include URL, exact replication steps, screenshot, time, user account. |

---

## 22. Screenshot capture sequence (the local-XAMPP recipe) <a id="22-screenshot-sequence"></a>

The 40 screenshots referenced in this guide all sit under
`docs/user-guides/screenshots/tenant-admin/` once captured. Use the
following recipe on your local Windows + XAMPP setup (your remote container
cannot capture them because the Moodle web server is not reachable from this
session):

```powershell
# 1. Ensure XAMPP Apache + MySQL are running
Set-Location C:\xampp
.\xampp_start.exe   # or use the Control Panel

# 2. Purge caches
Set-Location C:\xampp\htdocs\moodle5\public
php ..\admin\cli\purge_caches.php

# 3. Open Chrome with the canonical capture viewport
"C:\Program Files\Google\Chrome\Application\chrome.exe" `
    --user-data-dir="C:\tmp\chrome-airpay-capture" `
    --window-size=1440,900 `
    http://localhost:8080/moodle/login/index.php

# 4. Sign in as academyexadmin@airpay.co.in / AcademyAudit2026!
# 5. Walk the sequence in §3 → §17 of this guide.
# 6. Save each screenshot to:
#    D:\Claude Local\airpay-ld-os\moodle-enhancement\docs\user-guides\screenshots\tenant-admin\NN-<slug>.png
#    where NN is the screenshot number from this guide.

# 7. For the mobile shots (§17), open Chrome DevTools → Toggle Device Toolbar (Ctrl+Shift+M)
#    Set responsive width to 590 → walk same sequence → save as 34-mobile-*.png etc.

# 8. For the Airpay tenant shots, sign out → sign in as nitin.rajput@airpay.co.in
#    → repeat the sub-sequence that requires the Airpay scope.

# 9. For dark-mode shots (§5.3 + §17), click the moon icon top-right
#    BEFORE capture → save with -dark suffix.

# 10. Commit + push:
git add docs/user-guides/screenshots/tenant-admin/
git commit -m "docs(user-guides): tenant-admin screenshots capture"
git push -u origin claude/friendly-gates-10iUM
```

Each screenshot should be PNG, ~80% lossless quality, ideally <500 KB. Open
each PNG in IrfanView / Preview after capture to confirm it actually shows
what the caption claims.

### Screenshot checklist (use as `screenshots/tenant-admin/README.md`)

The screenshot README at `docs/user-guides/screenshots/tenant-admin/README.md`
ships with this guide and lists all 40 captures with their expected URL,
viewport, and persona.

---

## 23. References <a id="23-references"></a>

- [`learner-guide.md`](learner-guide.md) — what your tenant's learners see
- [`course-author-guide.md`](course-author-guide.md) — author dashboard companion
- [`compliance-officer-guide.md`](compliance-officer-guide.md) — compliance walkthrough
- [`site-admin.md`](site-admin.md) — full-scope admin reference (existing v1)
- [`tenant-admin.md`](tenant-admin.md) — v1-draft scaffold (this guide supersedes for content depth)
- [`README.md`](README.md) — guide index + chooser flowchart
- `moodle-enhancement/docs/audits/PLATFORM-VISUAL-AUDIT-2026-05-24.md` — visual audit
- `moodle-enhancement/docs/customer-config/airpay.md` — customer-zero reference config
- `moodle-enhancement/docs/customer-config/TEMPLATE.md` — onboarding skeleton
- `moodle-enhancement/docs/adr/ADR-002-customer-level-feature-flags.md` — flag layering rules
- `moodle-enhancement/docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md` — role_detector pattern
- `moodle-enhancement/docs/qa/NVDA-VERIFICATION-PROCEDURE.md` — a11y verification
- `CLAUDE.md` (root) — operating rules + escalation flags

---

| Version | Date       | Author                      | Notes                                                |
|---------|------------|-----------------------------|------------------------------------------------------|
| v1.0    | 2026-05-25 | Wave D3 P3 testing-and-docs chip | Full ≥20-page guide; supersedes v1-draft scaffold |
