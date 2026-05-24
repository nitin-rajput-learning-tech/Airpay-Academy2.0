# 01 — Plugin Profiles
**Phase B output** | Generated: 2026-05-12 | Session: context-2
**Source:** version.php (31 files), db/install.xml (22 plugins), db/services.php (19 plugins), db/access.php (20 plugins)

---

## Summary table

| # | Component | Ver | Tables | WS functions | Status |
|---|-----------|-----|--------|-------------|--------|
| 1 | local_airpay_core | 2026051200 | 0 | 0 | STABLE |
| 2 | local_airpay_org | 2026040300 | 1 | 2 | STABLE |
| 3 | local_airpay_users | 2026040300 | 0 | 3+ | STABLE |
| 4 | local_airpay_courses | 2026040300 | 1 | 3 | STABLE |
| 5 | local_airpay_roles | 2026040300 | 1 | 3+ | STABLE |
| 6 | local_airpay_skills | 2026040300 | 6 | 3 | STABLE |
| 7 | local_airpay_request | 2026051201 | 1 | 3 | STABLE |
| 8 | local_airpay_reports | 2026040300 | 1 | 3 | STABLE |
| 9 | local_airpay_analytics | 2026040300 | 0 | 0 | STABLE |
| 10 | local_airpay_rest | 2026040300 | 0 | 0 | STABLE |
| 11 | local_airpay_programs | 2026040300 | 4 | 3 | STABLE |
| 12 | local_airpay_learningpath | 2026040300 | 3 | 3 | STABLE |
| 13 | local_airpay_classroom | 2026040300 | 4 | 3 | STABLE |
| 14 | local_airpay_exams | 2026040300 | 1 | 3 | STABLE |
| 15 | local_airpay_evaluation | 2026040300 | 3 | 3 | STABLE |
| 16 | local_airpay_manager | 2026040300 | 2 | 3 | STABLE |
| 17 | local_airpay_cart | 2026051201 | 5 | 3+ | STABLE |
| 18 | local_airpay_challenge | 2026040300 | 3 | 3+ | STABLE |
| 19 | local_airpay_gamification | 2026040300 | 4 | 0 | STABLE |
| 20 | local_airpay_ratings | 2026040300 | 1 | 0 | STABLE |
| 21 | local_airpay_compliance_report | 2026040300 | 4 | 0 | STABLE |
| 22 | local_airpay_recompletion | 2026051201 | 2 | 0 | STABLE |
| 23 | local_airpay_notifications | 2026040300 | 3 | 3 | STABLE |
| 24 | local_airpay_emails | 2026040300 | 4 | 3 | STABLE |
| 25 | local_airpay_assistant | 2026040300 | 2 | 2 | STABLE |
| 26 | local_airpay_integrations | 2026040300 | 1 | 0 | STABLE |
| 27 | local_airpay_proctoring | 2026051201 | 5 | 4 | STABLE |
| 28 | local_airpay_privacy | 2026040300 | 2 | 0 | STABLE |
| 29 | local_airpay_cohort_sync | 2026040300 | 0 | 0 | STABLE |
| 30 | block_airpay_dashboard | 2026040300 | 0 | 0 | STABLE |
| 31 | theme_airpayux | 2026040500 | 0 | 0 | BETA |
| +  | quizaccess_airpay_proctor | 2026040300 | 0 | 0 | STABLE |

_Note: version 2026051201 = Phase 8.1 security remediation batch (cart, proctoring, recompletion, request)._

---

## 1. local_airpay_core

```
component:    local_airpay_core
version:      2026051200
maturity:     MATURITY_STABLE
requires:     2022041900 (Moodle 4.0)
purpose:      Shared tenant-isolation helper library consumed by all other airpay_* plugins.
replaces:     new — introduced Phase 8.1
db_tables:    (none)
capabilities: (none)
ws_functions: (none)
key_classes:
  - local_airpay_core\helper::root_for_user($userid)      — resolves tenant root costcenter for a user
  - local_airpay_core\helper::viewer_can_access($userid)   — checks tenant read access for caller
  - local_airpay_core\helper::require_access($userid)      — throws exception if no access
  - local_airpay_core\helper::sql_filter($alias)           — returns SQL fragment to scope queries to caller tenant
amd_modules:  (none)
depends_on:   (none — must be installed first)
status:       STABLE
open_items:   Introduced 2026-05-12 as Phase 8.1 security remediation; not yet unit-tested separately
```

---

## 2. local_airpay_org

```
component:    local_airpay_org
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Tenant-isolated org tree (department hierarchy) — CRUD, visibility toggle, user assignment.
replaces:     local_costcenter (BizLMS)
db_tables:
  - airpay_org           — org node records (id, tenantid, parentid, name, code, visible, timecreated)
capabilities:
  - local/airpay_org:view         — read org tree
  - local/airpay_org:manage       — create/edit/delete nodes
  - local/airpay_org:create       — create new org node
  - local/airpay_org:edit         — edit org node name/code
  - local/airpay_org:delete       — delete node (blocked if has users or children)
  - local/airpay_org:tenantadmin  — cross-tenant admin actions
ws_functions:
  - local_airpay_org_delete_org         (write) — refuses if tenant root, has descendants, or has users
  - local_airpay_org_toggle_visibility  (write) — toggle active/hidden
key_classes:
  - local_airpay_org\manager        — business logic for CRUD, tree traversal
  - local_airpay_org\external\*     — WS endpoint classes
amd_modules:  amd/src/org_tree.js (tree render + inline edit)
depends_on:   local_airpay_core
status:       STABLE
open_items:   (none from state cards)
```

---

## 3. local_airpay_users

```
component:    local_airpay_users
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      User management UI — list, search, suspend, delete, bulk import/export, scoped to caller tenant.
replaces:     local_users (BizLMS)
db_tables:    (none — reads/writes mdl_user directly)
capabilities:
  - local/airpay_users:view       — list users in own tenant
  - local/airpay_users:edit       — edit user profile fields
  - local/airpay_users:delete     — soft-delete user
  - local/airpay_users:suspend    — activate / suspend
  - local/airpay_users:import     — CSV bulk import
  - local/airpay_users:export     — CSV bulk export
  - local/airpay_users:masquerade — login-as (Airpay admin only)
ws_functions:
  - local_airpay_users_list_users    (read)  — server-side search, sort, paginate
  - local_airpay_users_suspend_user  (write) — suspend or activate
  - local_airpay_users_delete_user   (write) — soft delete
key_classes:
  - local_airpay_users\manager       — tenant-scoped user queries, import/export logic
  - local_airpay_users\external\*    — WS endpoint classes
amd_modules:  amd/src/users_datatable.js
depends_on:   local_airpay_core, local_airpay_org
status:       STABLE
open_items:   (none from state cards)
```

---

## 4. local_airpay_courses

```
component:    local_airpay_courses
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Course catalogue management — list, search, visibility toggle, delete, featured-course curation.
replaces:     local_courses (BizLMS)
db_tables:
  - airpay_featured_courses  — admin-curated list for learner dashboard spotlight widget
capabilities:
  - local/airpay_courses:view       — browse catalogue
  - local/airpay_courses:visibility — show/hide courses
  - local/airpay_courses:delete     — delete course
  - local/airpay_courses:feature    — manage featured courses
  - local/airpay_courses:enrol      — manual enrolment actions
  - local/airpay_courses:unenrol    — manual unenrolment
  - local/airpay_courses:export     — export course list
ws_functions:
  - local_airpay_courses_list_courses       (read)  — shared datatable with tenant filter
  - local_airpay_courses_toggle_visibility  (write) — show or hide
  - local_airpay_courses_delete_course      (write) — delete course
key_classes:
  - local_airpay_courses\manager      — catalogue query, visibility, featured list
  - local_airpay_courses\external\*   — WS endpoint classes
amd_modules:  amd/src/courses_datatable.js
depends_on:   local_airpay_core
status:       STABLE
open_items:   (none from state cards)
```

---

## 5. local_airpay_roles

```
component:    local_airpay_roles
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Role and capability management UI with immutable append-only audit log of all permission changes.
replaces:     new
db_tables:
  - airpay_roles_auditlog  — append-only audit trail (userid, roleid, capability, old_permission, new_permission, timemodified)
capabilities:
  - local/airpay_roles:view         — read role list and capabilities
  - local/airpay_roles:manage       — create/edit roles
  - local/airpay_roles:edit         — edit capability overrides
  - local/airpay_roles:assign       — assign roles to users
  - local/airpay_roles:export       — export audit log
ws_functions:
  - local_airpay_roles_list_roles        (read)  — roles with cap count, assignment count, archetype
  - local_airpay_roles_get_role_caps     (read)  — capabilities for a role with inherited defaults
  - local_airpay_roles_update_capability (write) — set a capability override (writes audit log)
key_classes:
  - local_airpay_roles\manager       — wraps core role API, writes to auditlog after each change
  - local_airpay_roles\external\*    — WS endpoint classes
amd_modules:  amd/src/roles_datatable.js, amd/src/caps_editor.js
depends_on:   local_airpay_core
status:       STABLE
open_items:   44 PHPUnit tests / 543 assertions (shipped 2026-05-07, commit 248302b3b)
```

---

## 6. local_airpay_skills

```
component:    local_airpay_skills
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Skills taxonomy — categories, skills, level definitions, role-skill mappings, course-skill tags, and user-skill tracking.
replaces:     new
db_tables:
  - airpay_skill_cats    — skill categories (Compliance, Technical, Leadership, etc.)
  - airpay_skills        — individual skills within categories
  - airpay_role_skills   — required skills per Moodle role/designation
  - airpay_course_skills — which skills a course delivers
  - airpay_skill_levels  — per-skill level definitions (L1–L5 with descriptors)
  - airpay_user_skills   — current user skill levels earned through courses/assessments
capabilities:
  - local/airpay_skills:manage  — full CRUD on taxonomy
  - local/airpay_skills:view    — read taxonomy and user skill levels
ws_functions:
  - local_airpay_skills_list_skills      (read)  — paginated list for datatable
  - local_airpay_skills_delete_skill     (write) — deletes skill + all role/course/user mappings
  - local_airpay_skills_delete_category  (write) — deletes category only if no skills reference it
key_classes:
  - local_airpay_skills\manager      — taxonomy CRUD, gap-analysis queries
  - local_airpay_skills\external\*   — WS endpoint classes
amd_modules:  amd/src/skills_matrix.js
depends_on:   local_airpay_core
status:       STABLE
open_items:   (none from state cards)
```

---

## 7. local_airpay_request

```
component:    local_airpay_request
version:      2026051201
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Learner self-service course enrolment request workflow — submit, list own requests, manager approval queue.
replaces:     new
db_tables:
  - airpay_request  — request records (userid, courseid, status, reason, approverid, timesubmitted, timeactioned)
capabilities:
  - local/airpay_request:request  — submit a request (learner)
  - local/airpay_request:view     — view request list
  - local/airpay_request:approve  — approve/reject requests (manager)
  - local/airpay_request:admin    — full admin override
ws_functions:
  - local_airpay_request_submit        (write) — submit new enrolment request
  - local_airpay_request_list_mine     (read)  — caller's own requests
  - local_airpay_request_list_pending  (read)  — requests pending caller's approval
key_classes:
  - local_airpay_request\manager      — state machine (pending → approved/rejected → enrolled)
  - local_airpay_request\external\*   — WS endpoint classes
amd_modules:  amd/src/request_form.js
depends_on:   local_airpay_core, local_airpay_manager
status:       STABLE
open_items:   Phase 8.1 security remediation — version bumped to 2026051201; tenant-scope fix applied
```

---

## 8. local_airpay_reports

```
component:    local_airpay_reports
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Report builder with saved report definitions, scheduled CSV exports, and active/archived status.
replaces:     new
db_tables:
  - airpay_reports  — saved report definitions (name, query_config, schedule, status, ownerid, tenantid)
capabilities:
  - local/airpay_reports:view    — run and view reports
  - local/airpay_reports:manage  — create/edit/delete report definitions
  - local/airpay_reports:export  — download CSV
ws_functions:
  - local_airpay_reports_list_reports   (read)  — paginated list for datatable
  - local_airpay_reports_delete_report  (write) — delete a report definition
  - local_airpay_reports_toggle_status  (write) — active ↔ archived
key_classes:
  - local_airpay_reports\manager      — query builder, scheduler, CSV generator
  - local_airpay_reports\external\*   — WS endpoint classes
amd_modules:  amd/src/reports_builder.js
depends_on:   local_airpay_core
status:       STABLE
open_items:   (none from state cards)
```

---

## 9. local_airpay_analytics

```
component:    local_airpay_analytics
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Learning analytics dashboard — aggregated KPIs (enrolments, completion rates, time-on-platform) visualised per tenant.
replaces:     new
db_tables:    (none — queries mdl_course_completions and Moodle log tables)
capabilities: (uses moodle/site:viewreports or similar — not defined in custom access.php)
ws_functions: (none — server-rendered dashboard)
key_classes:
  - local_airpay_analytics\dashboard  — aggregation queries, chart data builder
amd_modules:  amd/src/analytics_charts.js (Chart.js wrapper)
depends_on:   local_airpay_core
status:       STABLE
open_items:   (no state card entry — assume minimal changes in Phase 8)
```

---

## 10. local_airpay_rest

```
component:    local_airpay_rest
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      REST API gateway — exposes selected Moodle WS functions to external systems (KeKa HRMS, M365) under a unified /local/airpay_rest/ endpoint.
replaces:     new
db_tables:    (none)
capabilities: (token-based — no UI capability checks)
ws_functions: (acts as proxy; no new function definitions)
key_classes:
  - local_airpay_rest\dispatcher  — routes inbound REST calls to Moodle WS layer
amd_modules:  (none)
depends_on:   local_airpay_core
status:       STABLE
open_items:   Token rotation / rate-limiting not documented in state cards
```

---

## 11. local_airpay_programs

```
component:    local_airpay_programs
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Certification programme management — multi-level programmes containing ordered courses, with user enrolment and completion tracking.
replaces:     new
db_tables:
  - airpay_programs          — programme definitions (name, tenantid, status: draft/active/archived)
  - airpay_programs_levels   — level definitions within a programme (ordering, pass criteria)
  - airpay_programs_courses  — course membership within levels (courseid, levelid, sequence)
  - airpay_programs_users    — user enrolments + completion state per programme
capabilities:
  - local/airpay_programs:view    — browse programmes
  - local/airpay_programs:create  — create new programme
  - local/airpay_programs:update  — edit programme and level/course membership
  - local/airpay_programs:delete  — delete programme
  - local/airpay_programs:enrol   — enrol users
  - local/airpay_programs:export  — export completion data
ws_functions:
  - local_airpay_programs_list_programs   (read)  — paginated datatable
  - local_airpay_programs_change_status   (write) — draft → active → archived
  - local_airpay_programs_delete_program  (write) — delete programme
key_classes:
  - local_airpay_programs\manager      — programme lifecycle, completion checks
  - local_airpay_programs\external\*   — WS endpoint classes
amd_modules:  amd/src/programs_builder.js
depends_on:   local_airpay_core, local_airpay_courses
status:       STABLE
open_items:   (none from state cards)
```

---

## 12. local_airpay_learningpath

```
component:    local_airpay_learningpath
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Curated learning paths — ordered sequences of courses assigned to users, with progress tracking.
replaces:     new
db_tables:
  - airpay_learningpath         — path definitions (name, description, tenantid, status)
  - airpay_learningpath_courses — course sequence within a path (courseid, pathid, sequence)
  - airpay_learningpath_users   — user enrolments + progress per path
capabilities:
  - local/airpay_learningpath:view    — browse paths
  - local/airpay_learningpath:create  — create path
  - local/airpay_learningpath:update  — edit path and course sequence
  - local/airpay_learningpath:delete  — delete path
  - local/airpay_learningpath:enrol   — enrol users to a path
  - local/airpay_learningpath:unenrol — remove users from a path
ws_functions:
  - local_airpay_learningpath_list_paths     (read)  — paginated datatable
  - local_airpay_learningpath_toggle_status  (write) — active ↔ archived
  - local_airpay_learningpath_delete_path    (write) — delete path
key_classes:
  - local_airpay_learningpath\manager      — path CRUD, progress aggregation
  - local_airpay_learningpath\external\*   — WS endpoint classes
amd_modules:  amd/src/learningpath_builder.js
depends_on:   local_airpay_core, local_airpay_courses
status:       STABLE
open_items:   (none from state cards)
```

---

## 13. local_airpay_classroom

```
component:    local_airpay_classroom
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Instructor-led training (ILT) — classroom events with sessions, participant registration, and attendance marking.
replaces:     new
db_tables:
  - airpay_classroom             — classroom event definitions
  - airpay_classroom_sessions    — individual sessions within a classroom event
  - airpay_classroom_users       — participant registration per classroom
  - airpay_classroom_attendance  — per-session attendance records
capabilities:
  - local/airpay_classroom:view    — browse classrooms
  - local/airpay_classroom:update  — edit classroom and sessions
  - local/airpay_classroom:delete  — delete classroom + cascade sessions
  - local/airpay_classroom:enrol   — register participants
  - local/airpay_classroom:attend  — mark attendance
  - local/airpay_classroom:export  — export attendance report
ws_functions:
  - local_airpay_classroom_list_classrooms  (read)  — paginated datatable
  - local_airpay_classroom_change_status    (write) — active/cancelled/completed
  - local_airpay_classroom_delete_classroom (write) — delete classroom + all sessions
key_classes:
  - local_airpay_classroom\manager      — event/session lifecycle, attendance tracking
  - local_airpay_classroom\external\*   — WS endpoint classes
amd_modules:  amd/src/classroom_calendar.js
depends_on:   local_airpay_core
status:       STABLE
open_items:   (none from state cards)
```

---

## 14. local_airpay_exams

```
component:    local_airpay_exams
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Exam management wrapper around Moodle quiz activities — create named exam definitions, activate/deactivate, link to proctoring.
replaces:     new
db_tables:
  - airpay_exams  — exam wrapper records (quizid, tenantid, status, proctoring_enabled, timecreated)
capabilities:
  - local/airpay_exams:view    — list exams
  - local/airpay_exams:manage  — create/edit/delete exam wrappers
  - local/airpay_exams:export  — export exam results
ws_functions:
  - local_airpay_exams_list_exams     (read)  — paginated datatable
  - local_airpay_exams_toggle_status  (write) — activate/deactivate
  - local_airpay_exams_delete_exam    (write) — delete wrapper (does not affect underlying quiz)
key_classes:
  - local_airpay_exams\manager      — exam lifecycle, links to local_airpay_proctoring
  - local_airpay_exams\external\*   — WS endpoint classes
amd_modules:  amd/src/exams_datatable.js
depends_on:   local_airpay_core, local_airpay_proctoring
status:       STABLE
open_items:   (none from state cards)
```

---

## 15. local_airpay_evaluation

```
component:    local_airpay_evaluation
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Post-training evaluation forms (Kirkpatrick L1/L2) — create form templates with questions, link to courses, collect learner responses.
replaces:     new
db_tables:
  - airpay_evaluation            — evaluation form definitions (name, courseid, tenantid, status)
  - airpay_evaluation_questions  — questions within each form (text, type, options)
  - airpay_evaluation_responses  — per-user responses (userid, questionid, answer, timecreated)
capabilities:
  - local/airpay_evaluation:manage  — create/edit/delete forms
  - local/airpay_evaluation:respond — submit a response (learner)
ws_functions:
  - local_airpay_evaluation_list_evaluations  (read)  — paginated datatable
  - local_airpay_evaluation_change_status     (write) — draft/active/archived
  - local_airpay_evaluation_delete_evaluation (write) — delete form + cascade questions + responses
key_classes:
  - local_airpay_evaluation\manager      — form lifecycle, response aggregation, reporting
  - local_airpay_evaluation\external\*   — WS endpoint classes
amd_modules:  amd/src/evaluation_form.js
depends_on:   local_airpay_core, local_airpay_courses
status:       STABLE
open_items:   (none from state cards)
```

---

## 16. local_airpay_manager

```
component:    local_airpay_manager
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Manager portal — approve/reject enrolment requests from direct reports; allocate courses directly to team members.
replaces:     new
db_tables:
  - airpay_mgr_requests    — enrolment requests from learners awaiting manager approval
  - airpay_mgr_allocations — manager-driven course allocations to direct reports
capabilities:
  - local/airpay_manager:view     — view pending requests and team allocations
  - local/airpay_manager:approve  — approve or reject enrolment requests
  - local/airpay_manager:allocate — push course allocations to direct reports
ws_functions:
  - local_airpay_manager_list_requests    (read)  — requests pending the caller manager
  - local_airpay_manager_decide_request   (write) — approve or reject
  - local_airpay_manager_list_allocations (read)  — allocations made by caller manager
key_classes:
  - local_airpay_manager\manager      — approval workflow, org-hierarchy traversal
  - local_airpay_manager\external\*   — WS endpoint classes
amd_modules:  amd/src/manager_queue.js
depends_on:   local_airpay_core, local_airpay_org, local_airpay_users
status:       STABLE
open_items:   Works in tandem with local_airpay_request (request submitter side)
```

---

## 17. local_airpay_cart

```
component:    local_airpay_cart
version:      2026051201
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Course purchase cart — tenant-isolated inventory, ledger, credit balance, invoice generation, and purchase history.
replaces:     new
db_tables:
  - airpay_cart_id       — active cart items per user (userid, courseid, qty, price)
  - airpay_cart_history  — completed purchase records
  - airpay_cart_ledger   — double-entry payment ledger (debit/credit per transaction)
  - airpay_cart_credits  — credit balance per user/tenant
  - airpay_cart_invoices — generated invoice records
capabilities:
  - local/airpay_cart:view      — view own cart and purchase history
  - local/airpay_cart:purchase  — add to cart and check out
  - local/airpay_cart:manage    — admin — view all carts, adjust prices
  - local/airpay_cart:refund    — process refund
ws_functions:
  - local_airpay_cart_add_item    (write) — add course to current user cart
  - local_airpay_cart_remove_item (write) — remove course from cart
  - local_airpay_cart_get_cart    (read)  — current contents + totals
key_classes:
  - local_airpay_cart\manager      — cart state, checkout, ledger writes, invoice PDF
  - local_airpay_cart\external\*   — WS endpoint classes
amd_modules:  amd/src/cart_widget.js
depends_on:   local_airpay_core, local_airpay_courses
status:       STABLE
open_items:   Phase 8.1 security remediation — tenant-scope SQL fix applied (version 2026051201)
```

---

## 18. local_airpay_challenge

```
component:    local_airpay_challenge
version:      2026040300
maturity:     MATURITY_STABLE
requires:     2022041900
purpose:      Course-completion challenges with leaderboard — admins define challenges, learners enrol and compete; leaderboard refreshed every 15 minutes by scheduled task.
replaces:     new
db_tables:
  - airpay_challenge_challenges  — challenge definitions (courses required, start/end dates, rewards)
  - airpay_challenge_attempts    — per-user enrolment + progress (one row per userid×challengeid)
  - airpay_challenge_leaderboard — pre-computed leaderboard snapshot (refreshed by scheduled task)
capabilities:
  - local/airpay_challenge:view    — browse active challenges
  - local/airpay_challenge:manage  — create/edit/archive challenges
  - local/airpay_challenge:create  — create a challenge (separate from manage for delegation)
  - local/airpay_challenge:enrol   — enrol in a challenge
ws_functions:
  - local_airpay_challenge_list_challenges  (read)  — paginated list (active by default)
  - local_airpay_challenge_get_challenge    (read)  — single challenge + caller progress
  - local_airpay_challenge_create_challenge (write) — create a new challenge
key_classes:
  - local_airpay_challenge\manager        — challenge lifecycle, progress evaluation
  - local_airpay_challenge\task\refresh_leaderboard — scheduled task (every 15 min)
  - local_airpay_challenge\external\*     — WS endpoint classes
amd_modules:  amd/src/challenge_card.js, amd/src/leaderboard.js
depends_on:   local_airpay_core, local_airpay_gamification
status:       STABLE
open_items:   (none from state cards)
```

---

## 19. local_airpay_gamification

| Field | Value |
|---|---|
| component | local_airpay_gamification |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Points, badges, and streaks engine; feeds challenge leaderboard and dashboard widgets |
| replaces | — |
| db_tables | airpay_points_log, airpay_badges, airpay_user_badges, airpay_streaks |
| capabilities | (none published — internal API only) |
| ws_functions | (none) |
| key_classes | (event observer hooks into course completion events) |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | (none from state cards) |

---

## 20. local_airpay_ratings

| Field | Value |
|---|---|
| component | local_airpay_ratings |
| version | 2026040300 |
| maturity | STABLE |
| purpose | 5-star course rating system; results surfaced in dashboard and course catalogue |
| replaces | — |
| db_tables | airpay_ratings |
| capabilities | (none published) |
| ws_functions | (none) |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | (none from state cards) |

---

## 21. local_airpay_compliance_report

| Field | Value |
|---|---|
| component | local_airpay_compliance_report |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Compliance tracking reports — deadline monitoring, overdue alerts, completion exports |
| replaces | — |
| db_tables | compliance_rules, compliance_enrolments, compliance_completions, compliance_alerts |
| capabilities | view, manage, export |
| ws_functions | (none found in services.php) |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core, local_airpay_org |
| status | STABLE |
| open_items | Table names use compliance_ prefix (not airpay_); verify prefix in install.xml before schema migrations |

---

## 22. local_airpay_recompletion

| Field | Value |
|---|---|
| component | local_airpay_recompletion |
| version | 2026051201 (Phase 8.1 security remediation) |
| maturity | STABLE |
| purpose | Forced course recompletion on policy trigger; resets completion state per configured rules |
| replaces | — |
| db_tables | airpay_recompletion_rules, airpay_recompletion_history |
| capabilities | manage |
| ws_functions | (none) |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE — version bumped Phase 8.1 |
| open_items | (none from state cards) |

---

## 23. local_airpay_notifications

| Field | Value |
|---|---|
| component | local_airpay_notifications |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Rule-based in-platform notification engine; triggers on enrolment, completion, deadline, manager approval events |
| replaces | — |
| db_tables | airpay_notif_rules, airpay_notif_log, airpay_notif_prefs |
| capabilities | manage, view |
| ws_functions | local_airpay_notifications_list_rules, local_airpay_notifications_toggle_rule, local_airpay_notifications_delete_rule |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE — release 1.4.0 |
| open_items | (none from state cards) |

---

## 24. local_airpay_emails

| Field | Value |
|---|---|
| component | local_airpay_emails |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Overrides Moodle core email templates with branded HTML; per-event rule engine; logs all outbound email |
| replaces | Moodle core email templates |
| db_tables | airpay_email_overrides, airpay_email_rules, airpay_email_log, airpay_email_prefs |
| capabilities | manage, view |
| ws_functions | local_airpay_emails_get_template, local_airpay_emails_save_template, local_airpay_emails_revert_template |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | (none from state cards) |

---

## 25. local_airpay_assistant

| Field | Value |
|---|---|
| component | local_airpay_assistant |
| version | 2026040300 |
| maturity | STABLE |
| purpose | AI-powered learning assistant chatbot; answers learner queries against LMS content corpus |
| replaces | — |
| db_tables | airpay_chat_log, airpay_chat_cache |
| capabilities | use, manage |
| ws_functions | local_airpay_assistant_ask, local_airpay_assistant_get_history |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | External AI provider credentials in .env (AZURE_CLIENT_ID etc.) — never hardcode |

---

## 26. local_airpay_integrations

| Field | Value |
|---|---|
| component | local_airpay_integrations |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Outbound integration hub; logs all calls to external systems (HRMS, Azure AD, ElevenLabs, Gamma) |
| replaces | — |
| db_tables | airpay_integration_log |
| capabilities | manage, view |
| ws_functions | (none — internal dispatcher only) |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | (none from state cards) |

---

## 27. local_airpay_proctoring

| Field | Value |
|---|---|
| component | local_airpay_proctoring |
| version | 2026051201 (Phase 8.1 security remediation) |
| maturity | STABLE |
| purpose | AI-based exam proctoring — identity verification, session recording, anomaly event reporting |
| replaces | — |
| db_tables | airpay_proctor_sessions, airpay_proctor_identity, airpay_proctor_events, airpay_proctor_recordings, airpay_proctor_reviews |
| capabilities | proctor, review, manage |
| ws_functions | local_airpay_proctoring_start_session, local_airpay_proctoring_give_consent, local_airpay_proctoring_submit_identity, local_airpay_proctoring_report_event |
| key_classes | — |
| amd_modules | (webcam / media capture AMD module inferred — not confirmed by search) |
| depends_on | local_airpay_core |
| status | STABLE — version bumped Phase 8.1 |
| open_items | Used by local_airpay_exams and quizaccess_airpay_proctor; changes here require coordinated testing of both consumers |

---

## 28. local_airpay_privacy

| Field | Value |
|---|---|
| component | local_airpay_privacy |
| version | 2026040300 |
| maturity | STABLE |
| purpose | DPDP / GDPR compliance layer — consent recording, data subject requests, retention enforcement |
| replaces | — |
| db_tables | privacy_consents, privacy_requests (prefix: privacy_, not airpay_) |
| capabilities | manage, view |
| ws_functions | (none found in services.php) |
| key_classes | — |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_core |
| status | STABLE |
| open_items | Table names use privacy_ prefix — verify in install.xml before schema migrations |

---

## 29. local_airpay_cohort_sync

| Field | Value |
|---|---|
| component | local_airpay_cohort_sync |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Syncs Moodle cohorts from HRMS / Azure AD; auto-enrols cohort members into programmes and learning paths |
| replaces | — |
| db_tables | (none — writes to mdl_cohort and mdl_cohort_members directly) |
| capabilities | manage |
| ws_functions | (none) |
| key_classes | (scheduled task for periodic sync inferred) |
| amd_modules | (none) |
| depends_on | local_airpay_core, local_airpay_integrations |
| status | STABLE |
| open_items | (none from state cards) |

---

## 30. block_airpay_dashboard

| Field | Value |
|---|---|
| component | block_airpay_dashboard |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Primary learner dashboard block — enrolled courses, progress widgets, gamification summary, announcements |
| replaces | Default Moodle dashboard blocks |
| db_tables | (none — reads from other airpay_ tables and mdl_* core tables) |
| capabilities | (standard block capabilities — addinstance, myaddinstance) |
| ws_functions | (none) |
| key_classes | block_airpay_dashboard (extends block_base) |
| amd_modules | (dashboard widget JS inferred — not confirmed) |
| depends_on | local_airpay_core, local_airpay_gamification, local_airpay_notifications |
| status | STABLE |
| open_items | (none from state cards) |

---

## 31. theme_airpayux

| Field | Value |
|---|---|
| component | theme_airpayux |
| version | 2026040500 |
| maturity | BETA |
| purpose | Custom Airpay Academy theme — standalone fork of epsilon with zero parent inheritance; per-tenant branding (Airpay id=1, Public id=77) |
| replaces | theme_epsilon (Moodle Workplace) |
| db_tables | (none) |
| capabilities | (standard theme capabilities) |
| ws_functions | (none) |
| key_classes | classes/output/core_renderer.php (2,129 lines — per-tenant logo, colours, header/footer) |
| amd_modules | (multiple — not catalogued here; full coverage in Phase C) |
| depends_on | (none — standalone, $THEME->parents = []) |
| status | BETA — full deep-dive deferred to Phase C |
| open_items | Full theme profile (templates, SCSS, renderer methods, AMD modules, layout files) documented in docs/_research/02-theme-profile.md (Phase C) |

---

## 32. quizaccess_airpay_proctor

| Field | Value |
|---|---|
| component | quizaccess_airpay_proctor |
| version | 2026040300 |
| maturity | STABLE |
| purpose | Quiz access rule that enforces proctoring gate — blocks quiz attempt until local_airpay_proctoring session is active and identity verified |
| replaces | — |
| db_tables | (none — reads session state from local_airpay_proctoring tables) |
| capabilities | (standard quizaccess capabilities) |
| ws_functions | (none) |
| key_classes | rule.php (extends quizaccess_base) |
| amd_modules | (none confirmed) |
| depends_on | local_airpay_proctoring |
| status | STABLE |
| open_items | Tightly coupled to local_airpay_proctoring session model; any schema change in proctor_sessions or proctor_identity requires regression test of this rule |

---

## Appendix — Open Items Consolidated

| # | Plugin | Open Item |
|---|---|---|
| 1 | local_airpay_roles | 44 PHPUnit tests / 543 assertions — shipped 2026-05-07 (commit 248302b3b); verify other plugins have comparable coverage |
| 2 | local_airpay_compliance_report | Table prefix is `compliance_` not `airpay_` — double-check install.xml before migrations |
| 3 | local_airpay_privacy | Table prefix is `privacy_` not `airpay_` — double-check install.xml before migrations |
| 4 | local_airpay_assistant | AI provider credentials must be in .env; no hardcoding |
| 5 | local_airpay_proctoring | Changes require coordinated regression test of local_airpay_exams + quizaccess_airpay_proctor |
| 6 | quizaccess_airpay_proctor | Schema changes in proctoring tables break this rule — always test together |
| 7 | theme_airpayux | Full profile deferred to Phase C (02-theme-profile.md) |
| 8 | (all) | Phase 8.1 security remediation bumped 4 plugins to v2026051201: cart, proctoring, recompletion, request |
| 9 | (open) | Q1: Confirm live Moodle version (4.5.10 vs 5.1.3+ upgrade) |
| 10 | (open) | Q2: Relationship of this document to existing 91 KB DOCX — replace or supplement? |
| 11 | (open) | Q6: Go-live date for Moodle 5.x migration — affects plugin compatibility notes in master doc |

---

*Phase B complete. All 31 plugins + 1 quiz access rule profiled.*
*Next: Phase C — Theme deep-dive → docs/_research/02-theme-profile.md*
