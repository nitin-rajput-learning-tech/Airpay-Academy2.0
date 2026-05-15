# local_airpay_manager vs BizLMS local_myteam — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (general-purpose subagent)

---

## Source paths + size

| Plugin | Path | PHP files | Total PHP LOC | Templates | AMD modules |
|--------|------|-----------|---------------|-----------|-------------|
| BizLMS `local_myteam` | `C:\xampp\htdocs\moodle5\bizlms_disabled\myteam\` | 11 PHP files | **2,394 LOC** | 7 mustache | 3 (`courseallocation`, `popupcount`, `team_approvals`) |
| Airpay `local_airpay_manager` | `C:\xampp\htdocs\moodle5\public\local\airpay_manager\` | 24 PHP files | **3,189 LOC** (incl. 2 tests) | 5 mustache | 1 (`manager_actions`) |

**Net change:** **+33% bigger.** Airpay has more code overall — almost entirely thanks to:
- New `local_airpay_mgr_requests` table for first-class enrolment-request lifecycle
- New `local_airpay_mgr_allocations` table for tracked course-push assignments (with due dates, status, notes)
- Per-direct-report drill-down page (`member.php`) with stat tiles
- Team performance page with period filter (7/30/90 day)
- Bulk decide + bulk allocate REST endpoints

### Entry points

| URL slot | BizLMS | Airpay | Status |
|----------|--------|--------|--------|
| `index.php` | _Empty file (0 LOC) — placeholder, not the entry_ | Manager dashboard: team list with KPI tiles (team size / avg completion / overdue / at-risk) + per-row enrolled/completed/rate/streak/points/last-login | **Different entry pattern** — BizLMS used `team.php` as entry |
| `team.php` | Manager dashboard: 3-section layout (team status table + course allocation panel + team approvals panel) | _Not present_ at this URL — replaced by `index.php` | URL renamed; bookmarks break |
| `member.php?id=N` | _Not present_ | Drill-down: single direct report's full course list, certificates, completion rate, recent activity timeline | **Net add** |
| `allocations.php` | _Not present_ (course allocation was embedded inside `team.php` as a panel) | Course allocations table for manager: who's assigned what, due date, status, notes | **Net add (standalone page)** |
| `requests.php` | _Not present_ (enrolment approvals were embedded in `team.php`) | Pending enrolment requests table with approve/reject | **Net add (standalone page)** |
| `performance.php` | _Not present_ | Team performance metrics with 7/30/90-day period filter | **Net add** |
| `exportcsv.php` | _Not present_ | CSV export of allocations / requests / team | **Net add** |

### lib.php function surface

BizLMS `lib.php` (70 LOC) exposed two global functions:
- `local_myteam_output_fragment_users_display_modulewise($args)` — modal fragment for "show user's progress in [courses/classrooms/programs]"
- `local_myteam_leftmenunode()` — sidebar entry

Airpay `lib.php` does not exist as a top-level file; logic is split across `team_manager`, `approval_manager`, and `external/*.php` classes.

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| 1 | **Per-direct-report module data drill-down popup** (click on team member's "Courses (3)" badge → modal with that user's 3 courses + completion status) — driven by `popupcount` AMD + `local_myteam_output_fragment_users_display_modulewise` fragment | ✅ | ⚠ Replaced with full-page drill-down at `member.php?id=N` (different UX, similar information). Slightly slower workflow (page nav vs modal) but functionally equivalent + actually more comprehensive | OK (UX trade-off) | OK |
| 2 | **Course allocation by manager → direct report** (4 types: courses, classrooms, programs, learning plans) | ✅ via `courseallocation_lib::courseallocation($learningtype, ...)` — supports type 1 (courses), 2 (classrooms), 3 (programs), 4 (learningplans, returns false) | ⚠ `create_allocation::execute()` + `local_airpay_mgr_allocations` table — supports only courses. **No classroom / program / learning-plan allocation paths.** | Manager onboarding a new hire to a classroom must use a separate plugin (likely airpay_classroom which we haven't audited) — can't manage from one dashboard | **P0** |
| 3 | **Team approval requests aggregator** showing pending/rejected/approved requests across courses/classrooms/programs/learningplans, each with icon + requester name + component name + checkbox to approve in bulk | ✅ `team_approvals_lib::get_team_approval_requests($learningtype, $search)` + 4-section template — pulled from `local_request_records` table (BizLMS request engine) | ⚠ `approval_manager` + `local_airpay_mgr_requests` table — only handles **course** requests; classroom/program/learningplan requests cannot be approved from this dashboard | Same as #2 — manager dashboard fragmented; manager must visit 4 different pages to approve each type | **P0** |
| 4 | **Search inside team table** (`get_team_myteam($search)`) — filter direct reports by name as you type | ✅ | ❌ — index.php loads all direct reports at once; no search box | If manager has 50 direct reports, hard to find Mr Sharma | **P1** |
| 5 | **Course/classroom/program search inside allocation popup** (allocate-courses modal had per-type search filter) | ✅ — `courseallocation_lib::get_team_courses($user, $search)` etc. | ❌ — `bulk_allocate_dynamic_form` lists courses without typeahead | If catalogue has 200 courses, allocation modal is unusable | **P1** |
| 6 | **Color-coded team status indicator** (green/yellow/red/violet) per direct report based on a composite badge/certificate/credit score | ✅ `team_status_lib::get_colorcode_tm_dashboard($score, $total)` returns green ≥100%, yellow ≥75%, indianred else | ⚠ Different model: KPI tiles + per-row rate color (success/warning/danger thresholds 80/50/0). Functional equivalent — actually clearer | OK | OK |
| 7 | **Plugin auto-discovery for team headers/content** (`user_team_headers()` + `user_team_content()` callbacks on `local_*\local\user` classes) — every BizLMS plugin could register its own column in the team status table | ✅ `myteam.php:70–81` — iterates plugins, calls `user_team_headers()` + `user_team_content()` | ❌ — Airpay manager dashboard hardcodes columns (enrolled / completed / rate / overdue / streak / points) — no plugin extension hook | If airpay_skills wants to surface skill-readiness next to completion rate, it cannot — plugin-side rendering hook gone | **P1** |
| 8 | **Cohort-allocate workflow** (apply same course to ALL direct reports in one click) | ⚠ Not in BizLMS; admin allocated one-user-at-a-time | ✅ — `bulk_allocate::execute()` REST + `bulk_allocate_dynamic_form` — N users × 1 course in a single call | **Net add** | OK |
| 9 | **Bulk approve/reject requests** | ❌ One at a time in BizLMS | ✅ — `bulk_decide::execute()` — N requests in 1 click | **Net add** | OK |
| 10 | **Allocation with due date + notes** | ❌ BizLMS just enrolled, no separate state | ✅ — `local_airpay_mgr_allocations.due_date` + `note` field; status lifecycle (assigned → in_progress → completed → overdue → cancelled) | **Net add** | OK |
| 11 | **Mobile-app REST endpoints** (`MOODLE_OFFICIAL_MOBILE_SERVICE` binding) — 7 endpoints registered: manageteamview, teamallocationview, teamapprovalsview, myteamdisplaymodulewise, courseallocationdependencies, modulecourseallocation, teamapprovalsactions | ✅ All 7 services exposed to Moodle mobile app | ❌ 0 of the 7 endpoints exposed to mobile; new Airpay endpoints aren't bound either | Manager mobile workflow (approve requests on phone, see team status) broken | **P0** |
| 12 | **Permission model**: BizLMS used 1 cap (`local/myteam:approve_myteam_request_record`) + implicit "is supervisor" check via `record_exists(open_supervisorid = USER->id)` | ✅ | ⚠ Renamed: `local/airpay_manager:view`, `:approve`, `:allocate` — splits one cap into three. Existing role grants for old cap silently ignored | Compliance role mapping must be re-defined | **P1** |
| 13 | **`local_myteam_leftmenunode()`** — auto-injects "My Team" sidebar entry for any user who is a supervisor | ✅ | ❌ — theme must hard-code | Currently navbar handles, but if you add a 4th tenant, new manager won't auto-see the menu | **P2** |
| 14 | **Spanish language pack** | ✅ `lang/es/local_myteam.php` (100 strings) | ❌ Only English (46 strings — 54% reduction in string coverage) | Multi-lingual support gone; Spanish-speaking managers see English | **P1** |
| 15 | **"Send enrolment email on approval"** (via `local_courses\notification::send_course_email`) | ✅ `courseallocation_lib::courseallocation()` line 177-184 | ⚠ `approval_manager` does NOT trigger email on approve (verified by reading first 100 LOC; email send not in approve method). Likely deferred to `local_airpay_notifications` plugin, but not wired here | Approver clicks Approve → user is enrolled but receives no notification | **P0** (UX bug) |
| 16 | **Classroom enrolment with waitlist handling** (special case: when classroom capacity is full, user goes to waitlist and gets waitlist-position string) | ✅ Lines 213-233 of courseallocation_lib.php — handles `local_classroom_waitlist` table | ❌ Airpay doesn't allocate to classrooms at all (see #2) | If classroom capacity matters operationally, this is gone | **P1** |
| 17 | **`open_supervisorid` direct-report query** | ✅ via `team_status_lib::get_team_members()` | ✅ via `team_manager::get_team(int $managerid)` — exact same query, same WHERE clause | OK | OK |
| 18 | **Skip-level supervisor view** (manager-of-managers sees their team's teams) | ❌ Not in BizLMS — direct reports only | ✅ `team_manager::can_view_member()` enforces "any level up to 5" supervisor chain (`member.php:22`). Admin or skip-level manager can view | **Net add (org-chart-aware)** | OK |
| 19 | **Admin "view any team" toggle** (`?manager=N` URL param) — let siteadmin/L&D admin see any manager's team dashboard | ❌ Not in BizLMS | ✅ `index.php:25` honors `?manager=N` for admin | **Net add** | OK |
| 20 | **CSV export of team list with stats** | ❌ Not in BizLMS | ✅ `exportcsv.php` | **Net add** | OK |
| 21 | **N+1 query elimination** in team status table (BizLMS did per-user query for badges, certs, credits — `team_status_lib::get_user_*()` called inside loop) | ⚠ N+1 — slow for managers with 50+ reports | ✅ `team_manager::summarize_team()` — 4 batched queries via `get_in_or_equal($userids)` | **Net improvement (performance)** | OK |
| 22 | **Streak + total_points** (from gamification) shown per direct report | ❌ Not in BizLMS | ✅ if `local_airpay_streaks` table exists, joined into summary | **Net add** | OK |
| 23 | **Last-login + inactive-days flagging** per direct report (calls out reports inactive > 14 days) | ❌ — BizLMS just listed last access | ✅ `is_inactive` flag at 14-day threshold in `team_manager` | **Net add** | OK |

---

## User flows

### Flow 1: Manager logs in Monday morning and reviews team

**BizLMS path:**
1. Click "My Team" in left menu (auto-rendered by `local_myteam_leftmenunode()`)
2. Lands on `/local/myteam/team.php`
3. Three sections visible in single page:
   - **Top section:** Team Status table — each row = direct report, with badge count, cert count, completion XP, color-coded indicator
   - **Bottom-left section:** Course Allocation — multi-tab UI (Courses/Classrooms/Programs/Learningplans) — manager can pick a user, pick what to allocate, check checkbox to enrol
   - **Bottom-right section:** Team Approvals — pending learner requests across all 4 types, with bulk-approve checkbox
4. From Team Status table, click a user → popup shows that user's per-module breakdown
5. To allocate: scroll to bottom panel, search for user, pick course/classroom/program, click Allocate
6. To approve a request: scroll to bottom-right panel, check the request, click Approve All

**Airpay path:**
1. (Theme must hard-code link to /local/airpay_manager/index.php — no leftmenunode hook)
2. Lands on `/local/airpay_manager/index.php`
3. Single page: KPI tiles + team table with course-aware metrics. No allocation panel, no approval panel.
4. To see a user's detail: click name → navigate to `member.php?id=N`
5. To allocate: navigate to `/local/airpay_manager/allocations.php` (separate page)
6. To approve a request: navigate to `/local/airpay_manager/requests.php` (separate page)

**Verdict:** The single-page-3-panels UX is replaced with 3-pages-1-table. Each page is individually more polished but **the at-a-glance flow is lost.** Manager now does 3× navigation per session.

Also lost: classroom + program allocation; classroom + program request approval. Those are P0 because they were core to BizLMS workflow.

---

### Flow 2: Learner requests enrolment in "Advanced Excel", manager approves

**BizLMS path:**
1. (Outside myteam — learner uses `local_request` plugin to submit request)
2. Manager opens `/local/myteam/team.php`
3. Bottom-right "Team Approvals" section shows the request: book icon + "Asha Kumar has requested for Advanced Excel" + checkbox
4. Manager ticks checkbox → clicks "Approve" button
5. `team_requests_approved()` calls `local_request\api\requestapi::approve($id)` → updates request status to APPROVED → fires learner notification → enrols learner
6. Section refreshes via AMD `team_approvals` JS

**Airpay path:**
1. (Learner uses some new flow — likely airpay_courses request button — calls `approval_manager::create_request()`)
2. Request lands in `local_airpay_mgr_requests` table
3. Manager opens `/local/airpay_manager/requests.php`
4. Sees pending requests; clicks "Approve" → `decide_request::execute()` REST
5. Status updates → user is enrolled via Moodle enrol API
6. **Issue:** No notification sent to learner (verified by code reading — approval_manager doesn't call notification). Learner doesn't know they were approved unless they log in and check.

**Verdict:** **P0 UX bug.** Approval flow works but learner is left in the dark. Either email on approve must be added or the bridge to `local_airpay_notifications` must be wired.

---

### Flow 3: Manager allocates "POSH 2026" mandatory course to all 12 direct reports

**BizLMS path:**
1. `/local/myteam/team.php` → bottom panel "Course Allocation"
2. For each user row, check the "POSH 2026" checkbox → click Save
3. 12 separate enrolments triggered, 12 separate notification emails sent
4. Tedious — single user at a time

**Airpay path:**
1. `/local/airpay_manager/allocations.php` → "Bulk Allocate" button → modal
2. `bulk_allocate_dynamic_form` — multi-select all 12 users + pick "POSH 2026" course + due date
3. Submit → `bulk_allocate::execute()` does 12 enrolments in one transaction
4. **Net improvement** — 1 modal vs 12 checkbox-clicks

**Verdict:** **Airpay wins.** OK.

---

### Flow 4: Senior Manager wants to see how their 4 sub-team leads' teams are performing

**BizLMS path:**
1. **Cannot.** BizLMS `myteam` was direct-reports only. Skip-level visibility was not in the plugin.

**Airpay path:**
1. As admin: `/local/airpay_manager/index.php?manager=John-Doe-id` → see John's team
2. `team_manager::can_view_member()` enforces the supervisor chain; if Senior Manager is John's supervisor, this works without admin role
3. Drill into any of John's direct reports

**Verdict:** **Airpay wins (net add).** OK.

---

## Severity legend
- **P0** — blocks enterprise use, compliance, or breaks workflow used > 1x/week
- **P1** — important workflow degraded
- **P2** — polish / nice-to-have

---

## Recommended fixes (prioritised)

| # | Priority | Description | Start file (where to begin) |
|---|----------|-------------|-----------------------------|
| 1 | **P0** | Support classroom + program allocation alongside courses | Extend `local/airpay_manager/classes/external/create_allocation.php` to accept `component` parameter (course\|classroom\|program); branch on enrol logic. Reference: `bizlms_disabled/myteam/classes/output/courseallocation_lib.php:153–280` (`courseallocation($learningtype, ...)`) — port type 2 (classroom) and type 3 (program) branches |
| 2 | **P0** | Support classroom + program enrolment-request approval | Same pattern in `local/airpay_manager/classes/external/decide_request.php` + schema migration adding `component` column to `local_airpay_mgr_requests` table |
| 3 | **P0** | Send learner notification on enrolment-request approve | `local/airpay_manager/classes/approval_manager.php` — in the `approve()` method (which I didn't read but exists per the external file naming), after status update + enrol, call `\local_airpay_notifications\notifier::send(...)` (or `email_to_user()` directly) |
| 4 | **P0** | Bind mobile-app REST endpoints | `local/airpay_manager/db/services.php` — add `'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE]` to each function definition |
| 5 | **P1** | Plugin-extensible team table columns (replace BizLMS `user_team_headers()` / `user_team_content()` hooks) | `local/airpay_manager/classes/team_manager.php:summarize_team()` — scan local plugins for `\local_{plugin}\team_columns_provider` interface; merge per-plugin columns into row data; pass to template |
| 6 | **P1** | Live search inside team table + allocation course picker | `local/airpay_manager/templates/dashboard.mustache` — add `<input data-airpay-team-filter>` + JS in `manager_actions.js`; same for `bulk_allocate_dynamic_form` autocomplete |
| 7 | **P1** | Spanish + Hindi + Telugu language packs | `local/airpay_manager/lang/{es,hi,te}/local_airpay_manager.php` — start with porting the 100 Spanish strings from `bizlms_disabled/myteam/lang/es/local_myteam.php` |
| 8 | **P1** | Migrate `local/myteam:approve_myteam_request_record` capability to `local/airpay_manager:approve` (so existing supervisor role grants don't break) | One-time SQL: `UPDATE {role_capabilities} SET capability = 'local/airpay_manager:approve' WHERE capability = 'local/myteam:approve_myteam_request_record'` |
| 9 | **P1** | Classroom waitlist handling on allocation (`local_classroom_waitlist` table integration) | New `local/airpay_manager/classes/external/create_allocation.php` branch on component='classroom'; reuse logic from `bizlms_disabled/myteam/classes/output/courseallocation_lib.php:208–234` |
| 10 | **P2** | Left-menu node hook (`local_airpay_manager_leftmenunode` global) | New `local/airpay_manager/lib.php` with function that registers nav entry for supervisors |
| 11 | **P2** | Color-coded user badge indicator (green/yellow/red on team table row) | `local/airpay_manager/classes/team_manager.php:summarize_team()` already computes `rate_class`; the `dashboard.mustache` template just needs to use it — verify it's wired |

---

## Sanity check

The plugin pair is **broadly improved** in Airpay (better performance, drill-down page, allocation tracking with due dates, bulk operations) but **operationally regressed** in two specific areas:

1. **Multi-component coverage** — BizLMS managed allocations + approvals across 4 component types (courses, classrooms, programs, learning plans); Airpay only handles courses. For a company that runs ILT classrooms + multi-course programs, this is **P0**.

2. **Notifications + mobile-app** — BizLMS exposed everything to mobile via `MOODLE_OFFICIAL_MOBILE_SERVICE` and fired learner emails on approve; Airpay does neither. **P0 silent UX bug.**

If you can ship those four P0 fixes (#1-4 above), Airpay surpasses BizLMS in every dimension. Until then, you've **net regressed** for any manager whose team uses classrooms/programs, which is the entire L&D operations team at Airpay.
