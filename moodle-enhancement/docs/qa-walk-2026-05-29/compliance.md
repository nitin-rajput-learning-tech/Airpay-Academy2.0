# QA Walk — Compliance Officer Persona
**Date:** 2026-05-29  
**Persona:** qa_compliance / `Qa@Airpay#26` (user id 3423, tenant /1 Airpay)  
**Shell detected:** L&D/OrgAdmin (BizLMS role_id=9 at category context + system `administrator`)  
**Instance:** http://localhost:8080/moodle/ (local XAMPP — Moodle 5.1.3+)

> ✅ **ORCHESTRATOR DISPOSITION (post-walk verification):**
> - **BUG-C-001 (5/8 dead sidebar links) → FIXED.** Confirmed the real P1 (OA-GRAN):
>   `sidebar_navigation.php` rendered admin links by shell detection without checking the
>   system caps the pages enforce. Gated each link by its page's cap. Re-verified:
>   qa_compliance sidebar now 6 links, 0 dead.
> - **BUG-C-002 (Export blocked) → FIXED.** `export.php` gated on the unregistered
>   `local/courses:manage`; now mirrors index.php's access logic. Re-verified: export → 200 + xlsx.
> - **BUG-C-003 (ZEEA leak into BU=1) → FALSE POSITIVE.** The "ATZ"/`airpay.tz` users are
>   Airpay **Tanzania** (`/1/116/...`, root=1), legitimately inside the Airpay tenant tree —
>   not ZEEA `/177`. The matrix filter is provably correct. Verified via DB (all root=1).
> - **BUG-C-004 (KPI bar not re-aggregating) → P3, logged** (may be by-design). 
> Read the sub-agent's raw notes below with these dispositions in mind.

---

## 1. Login

**PASS.** Logout → `login/index.php` → credential injection → redirected to `/my/` with title `Dashboard | airpay`. Display name resolved as "QA Compliance".

---

## 2. Sidebar Dead-Link Audit (OA-GRAN)

The sidebar renders 8 admin links for this shell. Fetch-probe results:

| Sidebar Link | URL | HTTP | Accessible? |
|---|---|---|---|
| Manage Users | `/local/airpay_users/index.php` | 404 | NO — "do not have permission (View user profiles)" |
| Manage Courses | `/local/airpay_courses/index.php` | 404 | NO — "do not have permission (View course management)" |
| Online Exams | `/local/airpay_exams/index.php` | 404 | NO — "do not have permission (View online exams)" |
| Classrooms | `/local/airpay_classroom/index.php` | 404 | NO — "do not have permission (View classroom sessions)" |
| Learning Paths | `/local/airpay_learningpath/index.php` | 200 | YES |
| Reports | `/local/airpay_reports/index.php` | 404 | NO — "do not have permission (View saved reports)" |
| Analytics | `/local/airpay_analytics/index.php` | 200 | YES |
| Compliance | `/local/airpay_compliance_report/index.php` | 200 | YES |

**OA-GRAN result: 5 of 8 sidebar links are dead for this user.**  
Visible but inaccessible: Manage Users, Manage Courses, Online Exams, Classrooms, Reports.  
Each shows a proper Moodle `nopermission` error (not a blank or PHP crash), but the links should not appear in the sidebar at all for this role.

**Root cause:** The sidebar template renders all links based on the OrgAdmin shell detection, not on per-capability checks. The compliance officer is assigned as a BizLMS org-admin (role_id=9 at category level) which triggers the full OrgAdmin sidebar, but the underlying plugins gate on specific capabilities (`local/airpay_users:view`, `local/airpay_courses:manage`, `local/airpay_exams:view`, etc.) that are not granted to this role.

---

## 3. Compliance Report — Deep Dive

### 3a. Page renders

`/local/airpay_compliance_report/index.php` — HTTP 200. Page renders a full compliance matrix with:
- Summary KPIs: **71% compliance rate**, 659 Completed, 4 Overdue, 0 Not Enrolled, 0 Exempted
- Compliance matrix table: 5 mandatory courses across columns (POSH Training 2025, IT and Information Security Awareness Training, Anti Money Laundering, Phishing Awareness Training, POSH Training for Internal Committee Members)
- Per-employee rows with status labels: Completed / Not Started / In Progress / Overdue (with day counts e.g. "516d")
- No PHP error, no exception, no blank page.

### 3b. Tabs

All 4 report tabs tested via fetch probe — all HTTP 200 with table content, no PHP errors:

| Tab | Status | Content |
|---|---|---|
| Compliance Matrix (default) | 200 | Full employee matrix |
| Defaulters | 200 | Defaulter list with table |
| Dept Scorecard | 200 | Department-level breakdown |
| Manager Report | 200 | Manager-scoped view |

### 3c. Business Unit filter

BU dropdown offers: "All Business Units", "AIRPAY PAYMENT SERVICES PRIVATE LIMITED (1)", "ZEEA (1)".  
Filter by BU=1 (Airpay) → HTTP 200, page reloads with filtered context.  
Filter by BU=177 (ZEEA) → HTTP 200, shows 0% compliance rate and no Airpay employee records.

### 3d. Export Excel

**FAIL — HTTP 404 `nopermission`.**  
`/local/airpay_compliance_report/export.php?tab=matrix` — Export Excel link is visible on the page but clicking it returns `error/nopermission`.

**Root cause confirmed in source** (`export.php` line 14):  
```php
if (!is_siteadmin() && !has_capability('local/courses:manage', $systemcontext)) {
    throw new moodle_exception('nopermission');
}
```
`index.php` uses a BizLMS role_id=9 fallback that allows the Compliance Officer to view the report, but `export.php` does not include this fallback — it only checks `local/courses:manage`, which this user does not have. The Export button is rendered unconditionally for all viewers, not capability-gated in the template.

---

## 4. Tenant Scoping Observation

**PARTIAL ISSUE FOUND.**

- BU filter set to "AIRPAY PAYMENT SERVICES PRIVATE LIMITED (1)" (BU=1): Shows stats 71%/659/4 (same as unfiltered). **Two ZEEA-tenant employees leaked through:**
  - `ATZ002` / `yasmin@airpay.tz` (Co-Founder)
  - `ATZ017` / `mwatatu.husssein@airpay.tz` (Agritetch Research Intern)

- BU filter set to "ZEEA (1)" (BU=177): Shows 0% / no Airpay employee records. **No cross-tenant leak in this direction.**

**Analysis:** The BU=1 filter leaks 2 ZEEA employees (`ATZ` prefix, `@airpay.tz` emails). These appear to be ZEEA employees whose BizLMS org path is either under tenant /1 or has a null/default cost center assignment. This is a data configuration issue (these users may be misclassified at seed/import time) but the report should be filtering on org path, not just `bu` param. The compliance_engine's `get_compliance_matrix()` method likely does not exclude ZEEA users when BU=1 is selected — it may only include users who have `open_path LIKE '/1/%'` but misses users under the shared root or wrong tenant.

Note: The summary KPI stats (71%/659/4) do not change between "All" and "BU=1" — which confirms the BU filter is not affecting the aggregate calculation, only the display rows. This is a secondary issue: KPI bar should re-aggregate when a BU filter is applied.

---

## 5. Sibling Report Pages

| Page | Status | Accessible? | Notes |
|---|---|---|---|
| `/local/airpay_reports/index.php` | 404 | NO | nopermission |
| `/local/airpay_analytics/index.php` | 200 | YES | Analytics Dashboard renders fine |
| `/local/airpay_recompletion/index.php` | 404 | NO | nopermission |
| `/local/airpay_compliance_report/index.php` | 200 | YES | Core surface — works |

---

## 6. Console Errors

Console errors (error level only, preserved across navigations): **1 error type, repeated 15 times.**  
`Failed to load resource: the server responded with a status of 404 (Not Found)`

Traced via `performance.getEntriesByType('resource')`: the 404s are from our own QA fetch probes (`/local/airpay_reports/index.php`, `/local/airpay_recompletion/index.php`). **No native page-load 404s.** No JS errors from the compliance report page itself. Google Fonts request has status=0 (network offline for external) — expected in local env.

---

## 7. Candidate Bugs

### BUG-C-001 — SEVERITY: P1 (UX regression, daily friction)
**5 dead sidebar links for Compliance Officer persona**  
Sidebar renders Manage Users, Manage Courses, Online Exams, Classrooms, Reports — all produce `nopermission` when clicked. The sidebar should suppress links the user cannot access. Fix: sidebar template (or the data provider feeding it) must check capabilities before rendering each link, using the same gates that the target plugins enforce.  
File: `theme/airpayux/templates/dashboard.mustache` (or the PHP data builder feeding `is_X` flags), `local/airpay_core` role_detector/sidebar builder.

### BUG-C-002 — SEVERITY: P1 (broken primary workflow)
**Export Excel blocked for Compliance Officer**  
`export.php` gates on `local/courses:manage` only. `index.php` has a BizLMS role_id=9 fallback that lets compliance users view the report — but `export.php` omits this fallback. Export is a core compliance officer workflow. Fix: add the same role_id=9 fallback to `export.php` lines 13–16, OR define and grant a dedicated `local/airpay_compliance_report:export` capability to the compliance role.  
File: `local/airpay_compliance_report/export.php` line 14.  
Additionally: the "Export Excel" link in the template should be hidden via a capability check rather than rendered unconditionally.

### BUG-C-003 — SEVERITY: P2 (data integrity / multi-tenant)
**ZEEA tenant employees leak into BU=1 (Airpay) compliance matrix**  
When BU filter is set to "AIRPAY PAYMENT SERVICES PRIVATE LIMITED (1)", 2 ZEEA employees (`ATZ002 yasmin@airpay.tz`, `ATZ017 mwatatu.husssein@airpay.tz`) appear in the Airpay matrix. This is a tenant isolation failure in the filter logic. The BU=1 filter should exclude all users whose tenant path is `/177/...`. Fix: `compliance_engine::get_compliance_matrix()` must filter on `open_path LIKE '/1/%'` (or the costcenter hierarchy for BU=1), not just on a `bu` column that may be incorrectly set for ZEEA users.  
File: `local/airpay_compliance_report/classes/compliance_engine.php`.

### BUG-C-004 — SEVERITY: P3 (minor UX inconsistency)
**KPI summary bar does not re-aggregate when BU filter is applied**  
Stats (71% / 659 Completed / 4 Overdue) are identical whether "All Business Units" or "AIRPAY (1)" is selected. The KPI bar appears to be calculated globally and not re-scoped to the filtered BU. Not blocking but misleading for a compliance officer reviewing a single business unit.  
File: `local/airpay_compliance_report/classes/compliance_engine.php` — `get_summary_kpis()` should accept the same `$orgpath` / `$bu` parameter as `get_compliance_matrix()`.

---

## 8. Screenshots

| File | Description |
|---|---|
| `compliance-01-dashboard.png` | Dashboard on login as qa_compliance |
| `compliance-02-report.png` | Compliance report — default matrix view (all BUs) |
| `compliance-03-filtered.png` | Compliance report — filtered to BU=1 (Airpay) |

---

## 9. Summary Table

| Check | Result |
|---|---|
| Login | PASS |
| Sidebar dead links (OA-GRAN) | FAIL — 5/8 links inaccessible |
| Compliance report renders | PASS |
| Matrix tab | PASS |
| Defaulters tab | PASS |
| Dept Scorecard tab | PASS |
| Manager Report tab | PASS |
| BU filter applies | PARTIAL — filter changes displayed rows but not KPI bar |
| Export Excel | FAIL — nopermission (export.php caps mismatch) |
| Tenant scoping (BU=1 clean) | PARTIAL — 2 ZEEA users leak into Airpay BU view |
| Console errors (native) | PASS — none on compliance page |
| PHP errors | NONE |
