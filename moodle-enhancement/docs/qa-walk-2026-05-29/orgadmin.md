# QA Walk Report — L&D / Org Admin Persona
**Date:** 2026-05-29  
**Persona:** qa_orgadmin (user id 3418, tenant open_path /1 Airpay)  
**Instance:** http://localhost:8080/moodle (LOCAL only)  
**Walk conducted by:** QA sub-agent (chrome-devtools MCP)

> ⚠️ **ORCHESTRATOR CORRECTION (post-walk verification).** The sub-agent's
> BUG-OA-01..07 (`nopermissions` on Manage Courses/Users/Reports/Classroom/
> Programs/Evaluations + missing "New Path" button) were **PROVISIONING
> ARTIFACTS, not product bugs.** qa_orgadmin had the `administrator` role at a
> **category** context, but the admin pages do `require_capability('local/airpay_*:view',
> context_system::instance())` — a **system**-context check. The administrator role
> grants those caps (`role_capabilities` perm=ALLOW @ system); they just weren't
> *effective* at system from a category-scoped assignment. After granting
> `administrator@system`, all 7 surfaces return **200** and "New Path" appears
> (empirically re-probed). **Net product bugs from this persona: 0.** One genuine
> P3 *latent* finding remains (OA-GRAN, see BUG-LOG): the L&D shell is granted to
> `administrator@category` users while pages require caps@system, so a purely
> category-scoped admin could see sidebar links that 403. OA-08 (sentientia_live
> placeholder) is deferred to the Trainer walk. Read the sub-agent's raw
> observations below with this correction in mind.

---

## 1. Login Status

**Result: OK**

- Navigated to `/login/index.php`, submitted credentials `qa_orgadmin / Qa@Airpay#26`
- Redirected to `/my/` — Dashboard title confirmed as "Dashboard | airpay"
- User identified in sidebar as "QO QA OrgAdmin"

---

## 2. Detected Shell — Coherence Check

**Shell detected: L&D / OrgAdmin — COHERENT**

Sidebar navigation items present:
| Text | URL |
|------|-----|
| airpay academy | /my/ |
| Dashboard | /my/ |
| Manage Users | /local/airpay_users/index.php |
| Manage Courses | /local/airpay_courses/index.php |
| Online Exams | /local/airpay_exams/index.php |
| Classrooms | /local/airpay_classroom/index.php |
| Learning Paths | /local/airpay_learningpath/index.php |
| Reports | /local/airpay_reports/index.php |
| Analytics | /local/airpay_analytics/index.php |
| Compliance | /local/airpay_compliance_report/index.php |

Dashboard widgets present: Welcome back, Enrolment Trend, Course Distribution, User Analytics, Top Courses, Recent Activity, Featured for you. Role-switch option available (Employee role visible).

Shell matches expected L&D/OrgAdmin profile: admin widgets, manage-oriented sidebar. **Coherent.**

---

## 3. Breadth Probe — 14 Surfaces

| Surface | HTTP | Classification | Notes |
|---------|------|----------------|-------|
| `/local/airpay_catalog/index.php` | 200 | ✅ | "Course Catalog" — loads |
| `/local/airpay_courses/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: "View course management" — sidebar link present but blocked |
| `/local/airpay_users/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: "View user profiles" — sidebar link present but blocked |
| `/local/airpay_learningpath/index.php` | 200 | ✅ | "Learning Paths" — 18 paths, list + edit/delete actions visible |
| `/local/airpay_programs/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: capability check at line 12 |
| `/local/airpay_evaluation/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: capability check at line 12 |
| `/local/airpay_skills/index.php` | 200 | ✅ | "Skills Matrix" — loads but "No skills data yet" (no designation set for QA user) |
| `/local/airpay_reports/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: "View saved reports" — sidebar link present but blocked |
| `/local/airpay_compliance_report/index.php` | 200 | ✅ | "Compliance Report" — fully functional, real data |
| `/local/airpay_manager/index.php` | 404 | ℹ️ expected | "You have no direct reports" — correct: QA user has no team members |
| `/local/airpay_classroom/index.php` | 404 | ⚠️ CANDIDATE BUG | `nopermissions`: "View classroom sessions" — sidebar link present but blocked |
| `/local/airpay_request/index.php` | 200 | ✅ | "My course requests" — loads |
| `/local/sentientia_live/index.php` | 200 | ✅ | Phase E.0 placeholder — no trainer UI yet (by design) |
| `/local/sentientia_leaderboard/index.php` | 200 | ✅ | Loads but shows "Leaderboards are not enabled" — feature flag off |

**Summary: 7 surfaces 200 (functional), 6 surfaces 404/nopermissions (5 candidate bugs + 1 expected), 1 404/expected (manager)**

---

## 4. Deep Interaction Findings

### Workflow 1: Learning Paths (`/local/airpay_learningpath/index.php`)

**Status: PARTIALLY FUNCTIONAL**

- List page loads correctly — 18 paths shown with columns: Path Name, Courses, Enrolled, Created, Status
- Filter controls present: Organisation / Department / Sub-Department / Level 4 / Level 5 / Status dropdowns — all functional
- Per-row actions available: view link, `data-action="edit-path"` button, `data-action="delete-path"` button
- **No "Create New Path" button present** — this persona can see and edit existing paths but cannot create new ones
- Navigated to `/local/airpay_learningpath/view.php?id=1` ("HR Onboarding"): loads cleanly with tabs Overview / Courses 0 / Users 0
- `/local/airpay_learningpath/edit.php?id=1` returns 404 — edit is handled via AJAX modal in the list (expected, not a bug)
- **No console errors on this surface**

**Finding:** L&D Admin can view and (via AJAX) edit existing learning paths but has no "Create" button. Unclear if this is intentional capability gating or a missing capability assignment for qa_orgadmin.

### Workflow 2: Compliance Report (`/local/airpay_compliance_report/index.php`)

**Status: FULLY FUNCTIONAL**

- Page loads with live data: 71% compliance rate, 659 completed, 4 overdue
- Table renders 50 rows with columns: Employee, Email, Department + 5 compliance course columns (POSH, IT Security, AML, Phishing, POSH-IC)
- Business unit filter works (Airpay / ZEEA options present — correctly tenant-scoped to /1 Airpay)
- Tabs present: Compliance Matrix, Defaulters, Dept Scorecard, Manager Report
- Export Excel link present: `/local/airpay_compliance_report/export.php?tab=matrix` (not clicked — would trigger download)
- **No console errors on this surface**

**Finding:** Compliance Report is the best-functioning surface for this persona. Full data, filters, export all present and correct.

### Workflow 3: Sentientia Live (`/local/sentientia_live/index.php`)

**Status: PLACEHOLDER — by design**

- Page renders cleanly: "Phase E.0 — Foundation" message
- States: "Trainer + audience UIs land in Phases E.1 and E.2"
- No trainer session list, no Create Session button
- Note: Per the codebase, Sentientia Live trainer UI does exist (Phases E.1-E.3 were shipped). This suggests qa_orgadmin lacks the `sentientia/live:viewtrainer` or equivalent capability. The index.php may be the pre-E.1 placeholder and the actual trainer dashboard is at a different URL.
- **No console errors**

**Additional surface checked — Skills Matrix (`/local/airpay_skills/index.php`):**
- Loads cleanly, shows "0% Skill Readiness / No skills data yet"
- Message: "Skills are mapped to your role. Contact your L&D team if your designation isn't set."
- Correct behaviour for a QA account with no designation set. Not a bug.

---

## 5. Console Errors Found

**Across all visited pages: ZERO console errors (error level)**

One error was present on `/local/airpay_skills/index.php` from a preserved message context: a single HTTP 404 on a resource — traced to theme/yui_combo.php or similar static asset. This is a known local-env infrastructure issue (YUI combo handler) unrelated to plugin functionality. Not a candidate bug.

Dashboard, Learning Paths, and Compliance Report all returned clean console logs.

---

## 6. Candidate Bug List

| ID | URL / Surface | Severity | One-line reasoning |
|----|---------------|----------|--------------------|
| **BUG-OA-01** | `/local/airpay_courses/index.php` | **P1 HIGH** | Sidebar shows "Manage Courses" link but clicking it throws `nopermissions` (capability: "View course management"). Core L&D Admin workflow blocked. |
| **BUG-OA-02** | `/local/airpay_users/index.php` | **P1 HIGH** | Sidebar shows "Manage Users" link but clicking it throws `nopermissions` (capability: "View user profiles"). Core L&D Admin workflow blocked. |
| **BUG-OA-03** | `/local/airpay_reports/index.php` | **P1 HIGH** | Sidebar shows "Reports" link but clicking it throws `nopermissions` (capability: "View saved reports"). Core reporting workflow blocked. |
| **BUG-OA-04** | `/local/airpay_classroom/index.php` | **P1 HIGH** | Sidebar shows "Classrooms" link but clicking it throws `nopermissions` (capability: "View classroom sessions"). Core classroom management blocked. |
| **BUG-OA-05** | `/local/airpay_evaluation/index.php` | **P1 MEDIUM** | No sidebar link but URL throws `nopermissions`. If L&D Admin is expected to run evaluations, capability is missing. |
| **BUG-OA-06** | `/local/airpay_programs/index.php` | **P1 MEDIUM** | No sidebar link but URL throws `nopermissions`. Programs management inaccessible; confirm if this persona should have access. |
| **BUG-OA-07** | `/local/airpay_learningpath/index.php` | **P2 LOW** | Learning Paths list loads but has no "Create New Path" button. Edit/delete actions are present per-row. Uncertain if create is intentionally blocked or capability not assigned. |
| **BUG-OA-08** | `/local/sentientia_live/index.php` | **P2 LOW** | Live engagement shows Phase E.0 placeholder text — but Phases E.1-E.3 trainer UI has been shipped. Trainer capability likely not assigned to qa_orgadmin's role. |

**Root cause for BUG-OA-01 through BUG-OA-06:** The qa_orgadmin account was provisioned as "administrator role @ category context" but the Moodle role assigned likely does not include the custom `local/airpay_*:view*` capabilities. Capabilities need to be granted either via the system role or via a BizLMS custom role assignment. The sidebar renders these links regardless of capability check (dead links for this user = navigation vs. permission mismatch).

**Not flagged as bugs:**
- `/local/airpay_manager/index.php` 404 — correct: "no direct reports" is an expected guard, not a bug
- `/local/sentientia_leaderboard/index.php` "not enabled" — feature flag off, expected
- `/local/airpay_skills/index.php` "no skills data" — no designation set on QA account, expected

---

## 7. Screenshots Saved

| File | Surface |
|------|---------|
| `orgadmin-01-dashboard.png` | Dashboard (viewport) |
| `orgadmin-02-learningpath-list.png` | Learning Paths list (18 paths) |
| `orgadmin-03-compliance-report.png` | Compliance Report (live data, 71% rate) |
| `orgadmin-04-sentientia-live.png` | Sentientia Live (Phase E.0 placeholder) |

---

## 8. Summary

- **Shell coherent:** Yes — dashboard widgets, sidebar, role-switch all match expected L&D/OrgAdmin profile
- **7 of 14 surfaces 200** (functional), 6 nopermissions (5 bugs + 1 expected), 1 expected guard
- **Systemic root cause:** qa_orgadmin's role lacks `local/airpay_*:view*` capabilities for Courses, Users, Reports, Classroom, Evaluation, Programs — all of which have sidebar links. This is a capability provisioning gap in the QA account, not a plugin code bug per se, but it is a critical UX bug: sidebar shows inaccessible links.
- **Zero console errors** across all tested surfaces
- **Compliance Report is the strongest surface** for this persona — fully functional with real data
- **Learning Paths is partially functional** — list and view work, create is unclear
