# Compliance Officer User Guide

**Persona:** Compliance Officer / Data Protection Officer (DPO)
**Platform:** Sentientia LMS / Airpay Academy — Theme `airpayux` v1.0.37-beta
**Audience:** The officer who owns statutory-training coverage and produces audit packs for RBI / POSH committee / DPO returns
**Status:** v1.0 (2026-05-25)
**Test account referenced:** `joseph.mandapati@airpay.co.in` — user id 627, BizLMS administrator role assigned at category context (contextlevel 40). Local password (XAMPP only): `AcademyAudit2026!`
**Local URL:** `http://localhost:8080/moodle/`

> **How this account was identified:** the task asked to "find an account in DB via mdl_role_assignments". The compliance role on Airpay Academy is a **BizLMS administrator role assignment at category context** (roleid 9, contextlevel 40), NOT a dedicated `complianceofficer` capability set. The documented holder is Joseph Mandapati (confirmed in `docs/visual-audit-2026-05-22/AUDIT-REPORT.md` and `docs/GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md` §5). To re-confirm against the live DB:
>
> ```sql
> SELECT u.id, u.email, ra.roleid, ctx.contextlevel, ctx.instanceid
> FROM   mdl_role_assignments ra
> JOIN   mdl_context ctx ON ctx.id = ra.contextid
> JOIN   mdl_user u      ON u.id   = ra.userid
> WHERE  ra.roleid = 9            -- BizLMS administrator
>   AND  ctx.contextlevel = 40    -- CONTEXT_COURSECAT
>   AND  u.email = 'joseph.mandapati@airpay.co.in';
> ```

> **Sibling guides:** [`learner-guide.md`](learner-guide.md) · [`tenant-admin-guide.md`](tenant-admin-guide.md) · [`course-author-guide.md`](course-author-guide.md) · [`site-admin.md`](site-admin.md)

---

## Table of contents

1. [Who is the Compliance Officer here?](#1-who)
2. [First login + landing experience](#2-first-login)
3. [The role-detection story (Bug #11 — why this matters)](#3-role-detection)
4. [Sidebar walkthrough](#4-sidebar)
5. [The Compliance dashboard — overview](#5-dashboard-overview)
6. [The six-state engine](#6-six-state)
7. [Tab 1 — Matrix](#7-tab-matrix)
8. [Tab 2 — Defaulters](#8-tab-defaulters)
9. [Tab 3 — Department Scorecard](#9-tab-scorecard)
10. [Tab 4 — Manager report](#10-tab-manager)
11. [Tab 5 — Config (manage courses + exclusions)](#11-tab-config)
12. [Filters — Business Unit / Department / Sub-department](#12-filters)
13. [Search by user / course](#13-search)
14. [Drill-down from KPI / chart → user detail](#14-drill-down)
15. [Export — CSV for statutory returns](#15-export)
16. [Data freshness + the hourly refresh](#16-freshness)
17. [Audit log + recompletion history](#17-audit-log)
18. [Daily compliance alert (message provider)](#18-alert)
19. [Statutory mapping — POSH / AML-KYC / DPDP / IT Act / RBI](#19-statutory)
20. [What's new in v1.0.37-beta — affecting Compliance Officers](#20-whats-new)
21. [Troubleshooting common issues](#21-troubleshooting)
22. [Escalation cues](#22-escalation)
23. [Screenshot capture sequence](#23-screenshot-sequence)
24. [References](#24-references)

---

## 1. Who is the Compliance Officer here? <a id="1-who"></a>

The Compliance Officer is the audit-facing persona who owns **statutory training
coverage**. The primary tool is `local_airpay_compliance_report` — described in
its README as:

> "The audit-facing view that Reserve Bank of India / POSH committee / DPO
> consume for statutory reporting."

You do not author courses (that is the Course Author) and you do not manage
users wholesale (that is the Tenant Admin). Your job is to **prove coverage**:
that every employee in scope has completed every mandatory training within its
validity window, and to produce the evidence pack when a regulator asks.

Capabilities you carry:
- BizLMS administrator role at category context (roleid 9, contextlevel 40)
- `moodle/site:viewreports` — read the compliance + analytics dashboards
- `local/courses:manage` (in some configurations) — manage the compliance
  course list

Capabilities you do **not** carry:
- `moodle/site:config`, plugin install, SMTP — those are Site Admin
- Wholesale user CRUD — that is Tenant Admin

📸 **Screenshot 01:** `screenshots/compliance-officer/01-persona-context.png` —
joseph.mandapati's dashboard immediately after login, showing the L&D-Admin
shape (NOT the bare Learner shape — see §3).

---

## 2. First login + landing experience <a id="2-first-login"></a>

1. Browse to `http://localhost:8080/moodle/login/index.php`.
2. Enter `joseph.mandapati@airpay.co.in` / `AcademyAudit2026!`.
3. Click **Sign in**.

📸 **Screenshot 02:** `screenshots/compliance-officer/02-login.png`

You land on `/my/dashboard.php`. Because your BizLMS admin role resolves to the
`admin_tenant` tier in the shared `role_detector`, you see the admin-shape
dashboard (KPI tiles, charts, top courses) PLUS the compliance sidebar items.

📸 **Screenshot 03:** `screenshots/compliance-officer/03-dashboard.png`

### Going straight to compliance

Bookmark `/local/airpay_compliance_report/index.php` — that is where you spend
95% of your time.

📸 **Screenshot 04:** `screenshots/compliance-officer/04-compliance-home.png` —
the compliance dashboard with the default `matrix` tab active.

---

## 3. The role-detection story (Bug #11 — why this matters) <a id="3-role-detection"></a>

This is worth understanding because it directly affected your persona during
the audit.

**The bug (caught 2026-05-22):** Joseph's page-layer auth correctly accepted his
BizLMS admin role at category context, so `/local/airpay_compliance_report/`
loaded fine. BUT his **sidebar showed only 5 Learner items** — no Compliance,
no Manage Users — because the sidebar's role-detection only checked the
`local/courses:manage` capability and ignored the BizLMS admin role assignment
that `dashboard.php`'s layout DID honour. The incoherence was the bug.

**The fix (commit `40fb6fb3b`, formalised in ADR-009):** A shared
`role_detector` class became the single source of truth. The sidebar now
mirrors `dashboard.php`'s tier resolution exactly: capability OR BizLMS
administrator role at contextlevel 40. Joseph now sees 9 sidebar items
including Compliance, Reports, Analytics, Manage Users.

A second sub-bug surfaced en route: a double-`LIMIT 1` in `record_exists_sql()`
silently returned false (the Moodle web layer swallowed the
`dml_read_exception`; only a CLI test exposed it). Also fixed.

**Why you should care:** if you ever see a *reduced* sidebar (only Learner
items) after a deploy, that is a role-detection regression — escalate
immediately, citing Bug #11 + ADR-009.

📸 **Screenshot 05:** `screenshots/compliance-officer/05-sidebar-full.png` — your
full 9-item sidebar (proof the fix holds).

---

## 4. Sidebar walkthrough <a id="4-sidebar"></a>

| # | Sidebar item   | URL                                          | Your use                                       |
|---|----------------|----------------------------------------------|------------------------------------------------|
| 1 | Dashboard      | `/my/dashboard.php`                          | Quick KPI glance.                              |
| 2 | Manage Users   | `/local/airpay_users/manage.php`             | Look up a user's compliance history.           |
| 3 | Courses        | `/local/airpay_courses/manage.php`           | See which courses are mandatory.               |
| 4 | Catalog        | `/local/airpay_catalog/index.php`            | Confirm a statutory course is published.       |
| 5 | Compliance     | `/local/airpay_compliance_report/index.php`  | **Your home base.**                            |
| 6 | Reports        | `/local/airpay_reports/index.php`            | Cross-cut reports (completion by date, etc.).  |
| 7 | Analytics      | `/local/airpay_analytics/admin.php`          | Engagement funnels.                            |
| 8 | Programmes     | `/local/airpay_programs/manage.php`          | Compliance programmes (e.g. annual refresher). |
| 9 | Learning paths | `/local/airpay_learningpath/manage.php`      | Sequenced compliance tracks.                   |

📸 **Screenshot 06:** `screenshots/compliance-officer/06-sidebar-annotated.png`

---

## 5. The Compliance dashboard — overview <a id="5-dashboard-overview"></a>

`/local/airpay_compliance_report/index.php`

```
+--------------------------------------------------------------------+
| Compliance Report                  Last refreshed: 25 May, 09:14 AM |
| [Matrix] [Defaulters] [Scorecard] [Manager] [Config]   [Export ⬇]   |
+--------------------------------------------------------------------+
| KPI strip:                                                          |
|  [Compliance Rate 87%] [Completed 2,140] [Overdue 38]              |
|  [Not Enrolled 12] [Exempted 6]                                    |
+--------------------------------------------------------------------+
| Filters: [Business Unit ▾] [Department ▾] [Sub-dept ▾]            |
+--------------------------------------------------------------------+
| <active tab content>                                               |
+--------------------------------------------------------------------+
```

The page is built by `index.php` (read earlier in this chip). Key behaviours:
- **Tenant scoping** — if you are not a site admin, the page scopes to your
  org path via `\local_airpay_org\tenant_manager::get_tenant_path()`.
- **Caching** — KPIs are cached per filter-path (`kpis_<md5(path)>`); the
  hourly refresh task rebuilds the aggregate.
- **Action handling** — addcourse / removecourse / exclude / include actions
  are sesskey-protected POST actions on the Config tab.

📸 **Screenshot 07:** `screenshots/compliance-officer/07-dashboard-full.png` —
full-page capture (scroll-stitch if needed) showing KPI strip + filters + matrix.

---

## 6. The six-state engine <a id="6-six-state"></a>

Every (user, course) pair resolves to exactly one state. This is the
conceptual core of the whole dashboard.

| State                    | Colour    | Meaning                                          | Counts toward compliance? |
|--------------------------|-----------|--------------------------------------------------|----------------------------|
| `not_enrolled`           | grey      | In scope but not enrolled in the mandatory course | ❌ (gap)                   |
| `enrolled_not_started`   | grey-blue | Enrolled, zero activity                          | ❌ (gap)                   |
| `in_progress`            | yellow    | Started, not yet complete                        | ❌ (in flight)             |
| `completed_current`      | green     | Done, within validity period                     | ✅                         |
| `completed_expiring`     | orange    | Done, validity expires within 30 days            | ✅ (but renewal due soon)  |
| `completed_expired`      | red       | Validity lapsed; recompletion required           | ❌ (lapsed)                |

The "Compliance Rate" KPI = `completed_current + completed_expiring` ÷
`(in-scope population)`. The `completed_expiring` bucket is the one you watch
most closely — it is your early-warning radar for renewal waves.

📸 **Screenshot 08:** `screenshots/compliance-officer/08-six-state-legend.png` —
capture the colour legend (usually rendered above the matrix).

---

## 7. Tab 1 — Matrix <a id="7-tab-matrix"></a>

`?tab=matrix` (default). Per-user × per-course completion grid. 50 rows / page.

Rows = users in scope. Columns = mandatory courses. Each cell is a colour-coded
status pill per the six-state engine. Hover any cell → tooltip with completion
date + validity expiry.

```
                 POSH 2026  AML-KYC  DPDP  IT-Act  RBI-Circ
Asif Ansari      [green]    [green]  [org] [green] [red]
Priya Sharma     [green]    [yellow] [grey][green] [green]
Binay Upadhyay   [green]    [green]  [green][green][green]
...
```

📸 **Screenshot 09:** `screenshots/compliance-officer/09-matrix.png`

### Pagination

50 rows / page, `?page=N`. The page count appears bottom-right. For a 3,500-user
tenant this is ~70 pages — use the filters (§12) and Defaulters tab (§8) to
narrow rather than paging through everything.

---

## 8. Tab 2 — Defaulters <a id="8-tab-defaulters"></a>

`?tab=defaulters`. Shows only users with at least one `completed_expired` or
`in_progress`-past-deadline mandatory course. This is your action list.

Columns: User / Designation / Department / Course(s) overdue / Days overdue /
Last nudged.

📸 **Screenshot 10:** `screenshots/compliance-officer/10-defaulters.png`

Sort by "Days overdue" desc to triage worst-first. Each row links to the
user's drill-down (§14).

---

## 9. Tab 3 — Department Scorecard <a id="9-tab-scorecard"></a>

`?tab=scorecard`. Department-level rollup. Each department gets a compliance %
and a colour band (green ≥ 90%, amber 70-89%, red < 70%).

Use this for leadership reporting — "Operations is at 94%, Risk is at 71% and
needs intervention".

📸 **Screenshot 11:** `screenshots/compliance-officer/11-scorecard.png`

---

## 10. Tab 4 — Manager report <a id="10-tab-manager"></a>

`?tab=manager`. Manager → direct-reports rollup. This mirrors what each line
manager sees on their own dashboard (see `manager.md`), but from your
all-managers vantage.

Use this to identify managers whose teams lag — the overdue-escalation cron
already emails them, but you may need to escalate to skip-level when a manager
ignores the nudges.

📸 **Screenshot 12:** `screenshots/compliance-officer/12-manager-report.png`

---

## 11. Tab 5 — Config (manage courses + exclusions) <a id="11-tab-config"></a>

`?tab=config`. Two panels:

### 11.1 Compliance course list

Add or remove courses from compliance tracking. Adding a course:
- Pick the course from the dropdown (all visible courses, id > 1)
- Optionally scope to an org entity (BU / department)
- Set the validity window in days (default 30 — meaning recompletion required
  every 30 days; for annual training set 365)

Removing a course stops tracking it (historical data is retained, just no
longer surfaced in the matrix).

Both actions are sesskey-protected (`action=addcourse` / `action=removecourse`).

📸 **Screenshot 13:** `screenshots/compliance-officer/13-config-courses.png`

### 11.2 Excluded users

Some users are legitimately out of scope (contractors, board members, users on
long leave). Exclude them with a reason (`action=exclude`, default reason
"Operations exclusion"). They drop out of the denominator so the compliance %
is not artificially depressed. Re-include (`action=include`) when they return
to scope.

> **Audit note:** every exclusion writes a reason + timestamp + actor to the
> audit log. Never exclude to "improve the number" — exclude only for genuine
> out-of-scope cases. The audit trail will be examined by regulators.

📸 **Screenshot 14:** `screenshots/compliance-officer/14-config-exclusions.png`

---

## 12. Filters — Business Unit / Department / Sub-department <a id="12-filters"></a>

Three cascading dropdowns above every tab:

```
[Business Unit ▾]  →  [Department ▾]  →  [Sub-department ▾]
```

- Picking a Business Unit populates the Department dropdown with its children.
- Picking a Department populates the Sub-department dropdown.
- The selected path (`/bu/dept/subdept`) becomes the filter applied to the
  active tab's query.

The dropdowns are built from `compliance_engine::get_org_hierarchy_level()` and
`get_org_hierarchy_children()`. The org tree comes from `local_airpay_org`.

📸 **Screenshot 15:** `screenshots/compliance-officer/15-filters-cascade.png` —
capture the three dropdowns with a BU selected and Department options visible.

---

## 13. Search by user / course <a id="13-search"></a>

Within the matrix tab, the search box filters rows by user name / email /
employee id. To find a specific course's coverage, use the column header — or
narrow to that course on the Config tab first.

For a single user's full compliance picture across ALL courses (not just
mandatory), use the drill-down (§14) or `/local/airpay_users/manage.php` →
click the user → "Compliance" sub-tab.

📸 **Screenshot 16:** `screenshots/compliance-officer/16-search.png`

---

## 14. Drill-down from KPI / chart → user detail <a id="14-drill-down"></a>

Click any matrix cell, or any row in Defaulters, to open the **user
drill-down** modal. It shows:
- Full name, designation, department, manager
- Every mandatory course + current state + completion date + validity expiry
- Recompletion history (every time the user moved from `completed_expired` back
  to `completed_current`) — sourced from `local_airpay_recompletion_history`
- A "Send reminder now" button (fires an out-of-band reminder to that user)

📸 **Screenshot 17:** `screenshots/compliance-officer/17-drill-down.png`

This drill-down is your evidence-gathering tool: when a regulator asks "prove
that employee X completed POSH training within the window", you open the
drill-down, screenshot it, and it shows the date + the recompletion chain.

---

## 15. Export — CSV for statutory returns <a id="15-export"></a>

Top-right **Export** button → CSV of the active tab + active filter.

`export.php` honours the same `?tab=`, `?bu=`, `?dept=`, `?subdept=` params, so
the CSV matches exactly what you see on screen. Format:
- UTF-8 with BOM (opens cleanly in Excel even with Hindi rows)
- Semicolon separator
- ISO-8601 dates
- One row per (user, course) for the matrix; one row per user for defaulters

📸 **Screenshot 18:** `screenshots/compliance-officer/18-export.png`

> **Open backlog (from the plugin README §"Open backlog"):** a signed PDF +
> CSV audit-export bundle for RBI returns is planned but not yet shipped.
> Today the officer pulls CSV manually + assembles the pack. Track in
> master-doc Section 10.5.

---

## 16. Data freshness + the hourly refresh <a id="16-freshness"></a>

The compliance aggregate is rebuilt hourly by
`\local_airpay_compliance_report\task\refresh_aggregates`. The "Last refreshed"
banner top-right tells you the snapshot time.

If `time() - last_snapshot > 7200` (>2 hours), a **stale** pill appears. That
means cron is behind — escalate to Site Admin to run:

```bash
php admin/cli/scheduled_task.php \
    --execute='\local_airpay_compliance_report\task\refresh_aggregates'
```

📸 **Screenshot 19:** `screenshots/compliance-officer/19-freshness-banner.png` —
capture the "Last refreshed" banner (and, if you can reproduce it, the stale
pill state).

---

## 17. Audit log + recompletion history <a id="17-audit-log"></a>

Compliance changes are audit-logged. Two sources:

1. **Sentientia audit log** — `/local/airpay_audit_log/index.php` (if
   installed) or the standard Moodle log report at
   `/admin/report/log/index.php`. Filter by component
   `local_airpay_compliance_report` to see add-course / remove-course /
   exclude / include events with actor + timestamp.

2. **Recompletion history** — `local_airpay_recompletion_history` table feeds
   the drill-down. Each row records when a user re-completed an expired course.
   The compliance dashboard inherits the recompletion engine's tenant-awareness
   (Phase 8.1 B6).

📸 **Screenshot 20:** `screenshots/compliance-officer/20-audit-log.png`

---

## 18. Daily compliance alert (message provider) <a id="18-alert"></a>

The plugin ships a message provider `compliance_dashboard_alert` — a daily
summary delivered to the Compliance Officer listing users moving into the
`completed_expiring` state.

Configure your delivery channel at
`/user/preferences/notification_preferences.php`:
- Email (default)
- In-app notification (always on)
- Push (PWA — if subscribed)
- WhatsApp (if opted-in)

This is your early-warning system: every morning you get a list of who is
about to lapse, so you can nudge before the red state hits.

📸 **Screenshot 21:** `screenshots/compliance-officer/21-alert-preference.png`

---

## 19. Statutory mapping — POSH / AML-KYC / DPDP / IT Act / RBI <a id="19-statutory"></a>

The compliance dashboard aggregates statutory training coverage across the
Indian regulatory stack relevant to a payments company:

| Regulation     | Typical course                          | Validity window | Audience            |
|----------------|------------------------------------------|-----------------|---------------------|
| POSH (2013)    | Prevention of Sexual Harassment 2026     | 365 days        | All employees       |
| AML / KYC      | Anti-Money-Laundering + Know-Your-Customer | 365 days      | Customer-facing + ops |
| DPDP Act 2023  | Data Protection + Privacy                | 365 days        | All employees       |
| IT Act         | Information Security 2026                 | 365 days        | All employees       |
| RBI circulars  | Periodic regulatory updates              | Per-circular    | Risk + compliance   |

You map each statutory requirement to a course on the Config tab (§11) and set
its validity window. The matrix then proves coverage per regulation per
employee.

> These mappings are configured per-customer. The course names above are
> illustrative of customer-zero (Airpay); your live config may differ. Always
> confirm the actual mapping on the Config tab before quoting coverage numbers
> to a regulator.

📸 **Screenshot 22:** `screenshots/compliance-officer/22-statutory-mapping.png`

---

## 20. What's new in v1.0.37-beta — affecting Compliance Officers <a id="20-whats-new"></a>

The Day-0 chip wave (21 merges, 2026-05-24). The single most important one for
your persona is **Bug #11 / ADR-009** — but that landed in the Goal-A wave
just before the chip wave. Within the 21-chip wave itself, these affect you:

| # | Chip | Surface affected | What you'll notice |
|---|------|------------------|--------------------|
| 1 | A — Orphan `Claude` SCSS removed | Theme build | Faster compliance-dashboard paint. |
| 2 | B — `MONOLITH_BACKUP.scss` archived | Theme build | Smaller compile target. |
| 3 | B — Navbar i18n | Navbar | Nav pills render in your locale. |
| 4 | B — Footer i18n | Footer | Footer links localised. |
| 5 | C — Dashboard inline-style cleanup | Dashboard | Your KPI tiles respect dark mode (compliance-rate green / overdue red now token-driven, not raw hex). |
| 6 | C / F-06 — Footer attribution via SCSS | Footer | Dark-mode-correct footer. |
| 7 | #255 — All 5 locales at 178 strings | Every UI string | Hindi / regional users get full translation. |
| 8 | E — Sentientia Live `aria-live` regions | Live engagement | a11y (peripheral to your persona). |
| 9 | F — Navbar cart-badge → AMD | Navbar | CSP-strict safe. |
| 10 | **P0-B — `_bizlms-admin.scss` `:focus-visible` siblings** | **Admin-interior surfaces incl. compliance** | Keyboard focus rings correct; mouse-click no longer flashes. The compliance dashboard's admin chrome is a `_bizlms-admin.scss` surface — this chip directly touched your screens. |
| 11 | J — `_surface-profile.scss` split | Profile / Badges / Grades / Calendar | Cleaner cascade (peripheral). |
| 12 | K — `_surface-login.scss` cleanup | Login | Easier restyle. |
| 13 | I — `dark_mode.scss` token cascade | Every dark surface | Compliance dashboard dark mode is now token-driven; fewer override surprises when brand colours change. |
| 14 | L — Footer mobile breakpoint | Footer mobile | No overflow on small phones. |
| 15 | M — Live BEM tokens | Live engagement | Peripheral. |
| 16 | G — Dashboard i18n strings | Dashboard | KPI labels translate. |
| 17 | N — Chart.js vendored + `{{#js}}` | Dashboard charts | Compliance charts work on CSP-strict / offline networks. |
| 18 | #18 — `_moodle-overrides.scss` `!important` trim | Site chrome | Cascade reads correctly. |
| 19 | #19 + D — `prefers-reduced-motion` + token timing | All animations | WCAG 2.3.3 safe. |
| 20 | Q — coursebannerimage CSS-url safety doc | Course banner | Doc-only. |
| 21 | O / #21 — Footer comment trim | Footer | Hygiene. |

### Bonus (highlighted for Compliance)

- **P2-K — PHPUnit CI gate for Moodle 5.2** — the compliance engine's
  tenant-isolation tests now run on every push; a regression in
  `costcenterid` scoping (which could leak another tenant's compliance data
  into your view) gets caught in CI.
- **P0-A — Conflict-marker hook** — protects the compliance plugin's lang +
  PHP files from broken-merge corruption hitting production.

📸 **Screenshot 23:** `screenshots/compliance-officer/23-whats-new-diff.png` —
before/after of the compliance dashboard's focus-ring + dark-mode behaviour.

---

## 20b. Worked example — producing a POSH audit pack for a regulator <a id="20b-worked-example"></a>

A concrete end-to-end run of the most common high-stakes task: a regulator or
the POSH internal committee asks you to "prove that 100% of employees completed
POSH training within the last 12 months". Here is the exact click-path.

### Step 1 — Confirm the mapping

`/local/airpay_compliance_report/index.php?tab=config`

Verify "Prevention of Sexual Harassment 2026" is in the tracked-course list
with a 365-day validity window. If it is not mapped, add it (§11.1) — but note
that adding it now only starts tracking now; historical completion data is read
from the course's own completion records, so coverage prior to mapping is still
visible.

📸 (reuse Screenshot 13 `13-config-courses.png`)

### Step 2 — Set the scope

Use the filters (§12) to scope to "All employees" (clear all BU / Dept filters
for the full org) OR to the specific business unit the regulator named.

### Step 3 — Read the headline number

The "Compliance Rate" KPI tile now shows POSH coverage for the scoped
population. Note it. If it is below 100%, you have defaulters to enumerate.

### Step 4 — Enumerate the gaps

`?tab=defaulters`, sort by "Days overdue" desc. This is your gap list — every
employee not currently `completed_current` or `completed_expiring` on POSH.

Export it (§15) → `defaulters-posh-<date>.csv`.

### Step 5 — Evidence the completions

For the regulator's spot-checks, open the drill-down (§14) for a sample of
`completed_current` employees and screenshot each one's completion date +
validity window + recompletion chain. This is your per-employee evidence.

### Step 6 — Assemble the pack

Until the signed PDF+CSV bundle ships (backlog §15), assemble manually:
1. The scoped matrix CSV (`?tab=matrix` → Export) — full coverage grid
2. The defaulters CSV from Step 4 — the gap list + remediation status
3. The department scorecard CSV (`?tab=scorecard`) — leadership rollup
4. A cover note stating: snapshot timestamp (from the freshness banner),
   scope, mapping (course + validity window), and your name + date
5. The sample drill-down screenshots from Step 5

### Step 7 — Note the data-freshness caveat

Always state the snapshot time. If the freshness banner showed "stale", run the
refresh (§16) and re-export before submitting — never submit a stale pack.

📸 **Screenshot 24:** `screenshots/compliance-officer/24-audit-pack-assembly.png` —
the matrix + defaulters + scorecard exports open side-by-side as the raw
material for a pack.

> **Discipline:** never adjust an exclusion to improve a coverage number for a
> regulator pack. Exclusions are for genuine out-of-scope cases only, and every
> exclusion is audit-logged with actor + reason + timestamp. A regulator who
> finds an exclusion timed to a submission date will (rightly) escalate.

---

## 21. Troubleshooting common issues <a id="21-troubleshooting"></a>

### "My sidebar only shows Learner items"

This is the Bug #11 regression signature. The sidebar's role-detection has
drifted from `dashboard.php`'s. Escalate immediately citing Bug #11 + ADR-009
(`role_detector` is the single source of truth). Do NOT work around it — a
regression here means other admin-tier users may be silently under-privileged.

### "Compliance numbers look impossible"

| Cause                                              | Resolution                                                              |
|----------------------------------------------------|-------------------------------------------------------------------------|
| Stale snapshot (cron behind)                       | Site Admin runs `refresh_aggregates`; check the freshness banner.       |
| A course was added/removed from tracking recently  | The matrix reflects current config; historical rows persist. Confirm Config tab. |
| An exclusion changed the denominator               | Check the Config → Excluded users panel + audit log.                    |
| Cross-tenant leakage (you see another org's users) | **P0 — stop, screenshot, escalate.**                                    |

### "Export CSV is empty"

The export honours your active filter. If you have a BU/Dept filter applied
that matches zero users, the CSV is empty. Clear filters and re-export.

### "A user is in the matrix but shouldn't be (left the company)"

If HRMS sync suspended them, they should drop out. If they linger:
1. Confirm their `suspended` flag in `/local/airpay_users/manage.php`.
2. If still active, ask Tenant Admin to suspend.
3. If genuinely out-of-scope (e.g. contractor), exclude them on the Config tab
   with a clear reason.

### "Recompletion history is missing for a user"

The drill-down reads `local_airpay_recompletion_history`. If a user re-completed
before the recompletion engine was made tenant-aware (Phase 8.1 B6), older
history may be sparse. Note this caveat in any audit pack.

### "Dark mode broke a status pill colour"

Pre-chip-C, the compliance KPI tiles used raw hex `#16a34a` / `#dc2626` that
did not theme. Post-chip-C they use `$ap-success` / `$ap-error` tokens. If you
still see a non-theming pill, that is a regression — screenshot + file under
`docs/visual-evidence/<today>/`.

### "Compliance daily alert not arriving"

Check `/user/preferences/notification_preferences.php` → `compliance_dashboard_alert`
channel is enabled. Confirm cron is running (Site Admin: `php admin/cli/cron.php`).

---

## 22. Escalation cues <a id="22-escalation"></a>

| Trigger                                                         | Action                                                            |
|-----------------------------------------------------------------|-------------------------------------------------------------------|
| Sidebar reduced to Learner items                                | P1 — Bug #11 regression; escalate to Site Admin + cite ADR-009.   |
| Cross-tenant data visible                                       | P0 — stop, screenshot, escalate to Nitin.                         |
| Compliance numbers impossible after fresh refresh               | Escalate to Site Admin → likely engine scoping regression.        |
| Statutory course missing from catalogue                         | Course Author / Tenant Admin to publish it.                       |
| Need a signed audit-export bundle (PDF + CSV + manifest)        | Not yet shipped — note as a manual assembly + flag the backlog.   |
| Regulator deadline approaching + coverage gap                   | Escalate to L&D head (Nitin) + the relevant department managers.  |

---

## 23. Screenshot capture sequence <a id="23-screenshot-sequence"></a>

```powershell
# 1. XAMPP up + caches purged + run the compliance refresh first
Set-Location C:\xampp\htdocs\moodle5\public
php ..\admin\cli\purge_caches.php
php ..\admin\cli\scheduled_task.php `
    --execute='\local_airpay_compliance_report\task\refresh_aggregates'

# 2. Open Chrome at canonical capture viewport
"C:\Program Files\Google\Chrome\Application\chrome.exe" `
    --user-data-dir="C:\tmp\chrome-airpay-capture-compliance" `
    --window-size=1440,900 `
    http://localhost:8080/moodle/login/index.php

# 3. Sign in as joseph.mandapati@airpay.co.in / AcademyAudit2026!

# 4. Walk: dashboard → compliance home → each of the 5 tabs → filters →
#    drill-down → export. Save each PNG to:
#    moodle-enhancement/docs/user-guides/screenshots/compliance-officer/NN-<slug>.png

# 5. For the dark-mode focus-ring shot (Screenshot 23), click the moon icon,
#    then Tab through the tab strip to show :focus-visible rings.

# 6. For the stale-banner shot (Screenshot 19), if you cannot reproduce
#    staleness, annotate the fresh banner and note the stale state in the caption.

# 7. Commit + push:
git add docs/user-guides/screenshots/compliance-officer/
git commit -m "docs(user-guides): compliance-officer screenshots capture"
git push -u origin claude/friendly-gates-10iUM
```

Total captures: ~23 desktop + 1 dark-mode.

---

## 24. References <a id="24-references"></a>

- [`tenant-admin-guide.md`](tenant-admin-guide.md) — broader admin context
- [`manager.md`](manager.md) — manager-side compliance rollup
- [`learner-guide.md`](learner-guide.md) — learner-side compliance experience
- [`site-admin.md`](site-admin.md) — full-scope admin
- [`README.md`](README.md) — guide index
- `local/airpay_compliance_report/README.md` — plugin reference (six-state engine, scheduled tasks, message providers)
- `docs/visual-audit-2026-05-22/AUDIT-REPORT.md` — Bug #11 finding + fix
- `docs/adr/ADR-009-detection-consistency-and-ws-contract-invariants.md` — `role_detector` pattern
- `docs/GOAL-A-Y-FUNCTIONAL-AUDIT-MATRIX.md` §5 — Compliance Officer feature matrix
- `CLAUDE.md` (root) — escalation flags + multi-tenant scoping rules
- `.claude/rules/database.md` — multi-tenant scoping discipline (why cross-tenant leakage is P0)

---

| Version | Date       | Author                       | Notes                                            |
|---------|------------|------------------------------|--------------------------------------------------|
| v1.0    | 2026-05-25 | Wave D3 P3 testing-and-docs chip | Full ≥20-page guide; new persona (no prior scaffold) |
