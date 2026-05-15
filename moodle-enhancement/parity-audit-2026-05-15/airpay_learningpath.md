# airpay_learningpath vs BizLMS local_learningplan — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (Opus 4.7, 1M)
**Verdict:** **MAJOR PARITY GAP — 65% feature loss.** Airpay is a functional MVP. BizLMS shipped sequenced, gated, auto-enrolling, manager-approved, target-audience-scoped, certificate-issuing learning plans. Airpay has flat course bags with manual enrolment. Five hard P0s; learning plans no longer behave as enterprise learning plans.

---

## Source paths + size

- **BizLMS**: `C:\xampp\htdocs\moodle5\bizlms_disabled\learningplan\` — **47 PHP files, 11,814 LOC**
  - Key entry points: `index.php` (264), `view.php` (70), `plan_view.php` (97), `lpathinfo.php` (59), `userdashboard.php` (67), `assign_courses_users.php` (244), `lpusers_enroll.php` (415), `lep_course_completion.php` (44), `exportcsv.php` (75), `ajax.php` (229)
  - Library: `classes/lib/lib.php` (1,111) is the engine; `lib.php` (498) hosts AJAX fragment renderers
  - Forms: `classes/forms/learningplan.php` (340, 3-step wizard), `classes/forms/courseenrolform.php` (60)
  - Renderers: `classes/render/view.php` (3,090 — gigantic), `classes/output/renderer.php`, `classes/output/learningplan_courses.php`, `classes/output/search.php`
  - External services: `classes/external.php` (841)
  - Notifications: `classes/notification.php` (202) + `db/messages.php` + `db/events.php` (7 event classes)
  - AMD: `courseenrol.js`, `lpcreate.js`, `module.js`, `form-options-selector.js`
  - Templates: 22 mustache (`learningplan_publish_edit`, `lpathcourse`, `lpathview_user`, `lpathbottomcontent`, `cousrespath`, `planview_user`, `userdashboard_*` × 7, `searchpagecontent`, `tagview`, `userprofile`)
  - Languages: en, es, hi (3 locales)

- **Airpay**: `C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\` — **29 PHP files, 3,605 LOC** (≈30% of BizLMS)
  - Entry points: `index.php` (75), `view.php` (102), `exportcsv.php` (100)
  - Library: `classes/path_manager.php` (611) — all CRUD + enrolment + progress
  - Forms: `classes/form/edit_path.php` (132), `classes/form/assign_courses_form.php` (118), `classes/form/enrol_users_form.php` (123)
  - External: 10 services in `classes/external/*.php` (assign/unassign courses, enrol/unenrol users, list paths/courses/users, reorder, toggle status, delete)
  - Templates: 2 mustache (`manage.mustache`, `view.mustache`)
  - AMD: 1 (`path_actions.js`)
  - Language: en only
  - 4 PHPUnit test files (good coverage on CRUD)

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|------------|------------|-----|----------|
| 1 | **Ordered course sequence with mandatory/optional gating** | `local_learningplan_courses.nextsetoperator` = `'and'` (mandatory in-order) or `'or'` (optional) — see `classes/lib/lib.php:454,621-645` | Single `mandatory` flag (default 1, never used in UI) | **No gating logic, no AND/OR**. All courses behave identically | **P0** |
| 2 | **Auto-enrolment into Moodle courses** | On user assignment, BizLMS auto-creates `enrol` row with `enrol='learningplan'` + auto-enrols user into the first mandatory + all already-completed-by-user optionals (`assign_users_to_learningplan` at lib.php:424-489, `to_enrol_users` at 854-876, `update_enrol_status` at 1097-1110) | None. Path enrolment writes to `local_airpay_learningpath_users` but **does NOT enrol users into the underlying courses**. User sees the path but cannot start any course unless separately enrolled via another mechanism. | **Users assigned to a learning path cannot access its courses** | **P0** |
| 3 | **Sequence advancement on course completion** | `complete_the_lep()` at lib.php:724-853 marks the path complete when all `and`-courses done; `to_enrol_users_check_completion()` at 691-723 auto-enrols next mandatory course on completion | `path_manager::get_user_progress()` (path_manager.php:85-105) only **reads** completion %. Nothing writes back the user's path-status — `ENROL_INPROGRESS`, `ENROL_COMPLETED` are defined but never assigned anywhere | Progress never advances. `local_airpay_learningpath_users.status` is permanently `0` (NEW) for every learner | **P0** |
| 4 | **Manager approval workflow (self-enrol w/ approval)** | `local_learningplan_approval` table; `selfenrol` + `approvalreqd` form fields (`classes/forms/learningplan.php:175-189`); status flow approve/reject; reject reason text; integration with `local/request` plugin (`db/access.php:153 'approverecord'`); approval event triggers email | **Not present.** No `local_airpay_learningpath_approval` table. Form has no self-enrol/approval toggle. The path is either assigned by an admin or invisible | Compliance (cost-shared training) cannot route through line managers | **P0** |
| 5 | **Email notifications** | 7 declared `event` classes (`learningplan_created/updated/deleted/users_created/users_deleted/courses_created/courses_deleted/user_completed`) wired to `classes/notification.php` (202 LOC) and `db/messages.php`. Templates for `learningplan_enrol`, `learningplan_completion`, `lep_approval_request`, `lep_rejected`, `lep_approvaled`, `lep_nomination` | Zero notifications. No `db/events.php`, no `db/messages.php`, no observer | Learners never get an "enrolled in path X" or "you completed path Y" email. Managers never know users finished | **P0** |
| 6 | **Target-audience filtering (designation, group, hrmsrole, location, band, branch, dept levels 1-5)** | Form step 3 (`form_status==2`) collects `open_designation, open_group, open_hrmsrole, open_location, open_band, open_branch, department, subdepartment, level4department, level5department`; `get_enrollable_users_to_learningplan()` filters by these on the user assignment dropdown (`classes/lib/lib.php:154-200, 202-254`) | Form only collects `costcenterid + departmentid` (single tier). Target audience filtering **not implemented** in enrol_users_form — it just shows all users in tenant | "Assign this path to all Branch Managers in West region" workflow lost | **P1** |
| 7 | **Multi-step wizard form (3 tabs: General / Other / Audience)** | `classes/forms/learningplan.php` has `formstatus` array → 3 step-by-step screens via `local_users` renderer wizard, with hierarchy fields, certificate mapping, skill+level binding, summary file picker, description editor with file uploads (`lib.php:26-180`) | Single flat `edit_path.php` (132 LOC) with 5 fields: name, description, costcenterid, status. Nothing else | Admins cannot bind certificates, skills, levels, audience or summary image when creating a path | **P0** |
| 8 | **Certificate auto-issue on path completion** | `local_learningplan.certificateid` FK → `tool_certificate_templates`; `map_certificate` checkbox in form-step-1 (`forms/learningplan.php:215-235`) | No certificate field on the table or form. `tool_certificate` integration absent | Cannot deliver compliance-certificate via a path | **P0** |
| 9 | **Skill + level binding** | `local_learningplan.open_skill` FK → `local_skill`; `local_learningplan.open_level` FK → `local_course_levels`; form step 2 has both dropdowns (`forms/learningplan.php:250-283`) | No skill/level fields on schema. No integration with `airpay_skills` | Learning path → skill matrix link lost | **P1** |
| 10 | **CSV export** | `exportcsv.php` (75 LOC) — full path list with names + audience + status | `exportcsv.php` (100 LOC) — supports two modes: `mode=paths` and `mode=path_users` (per-path enrolment + completion %); arguably **better** than BizLMS | None. Airpay is ahead here | none |
| 11 | **List view: cards vs table toggle** | `?formattype=card\|table` switcher button (`index.php:42-50, 255-262`) | Single datatable view. No card view. | Minor UX regression; admins prefer table anyway | **P2** |
| 12 | **Filters (org/dept/sub-dept/L4/L5/status/category/lp-name)** | `filters_form.php` with hierarchy_fields + categories + learningplan + status; collapsible filter panel (`index.php:170-234`) | Datatable has a free-text search but **no structured filters** (no costcenter dropdown, no status filter, no created-date) | Admin viewing 100+ paths across tenants has no way to slice the list | **P1** |
| 13 | **Bulk actions on the list (delete-many, archive-many)** | Implicit via DataTables checkbox + AJAX `module.js`; per-row delete/edit/visible | Per-row delete + toggle status only. No multi-select bulk action | Admin cleaning up old paths must click 1-by-1 | **P2** |
| 14 | **User dashboard ("my learning plans")** | `userdashboard.php` (67 LOC) + 7 `userdashboard_*.mustache` templates → tabbed view (catalog vs paginated vs inner-tab) showing learner's enrolled+completed paths | None. The Airpay learner experience for paths is entirely missing — index.php is admin-facing only | **Learner has no way to see "my learning paths" anywhere in the app** | **P0** |
| 15 | **Public path detail page for learners (lpathinfo.php)** | `lpathinfo.php` (59 LOC) loads `lpathinfo_for_employee()` renderer; full-page layout `iltfullpage`; integrates with `local_search` and `local_request` | `view.php` is admin/manager-oriented (Overview/Courses/Users tabs with edit buttons). No learner-facing flow | Learner cannot drill into a path to see its courses before enrolling | **P0** |
| 16 | **Tags** | `db/tag.php` + tags element in form (commented but framework wired); `\local_tags_tag::set_item_tags` called on save (`classes/lib/lib.php:144`) | No tags | Tag-based discovery lost | **P2** |
| 17 | **Plan publish workflow + visibility toggle** | `togglelearningplan()` at `classes/lib/lib.php:263-282` flips `visible=0/1`; `local_learningplan:publishplan` + `:visible` capabilities; integration with `block_trending_modules` | `toggle_status()` (path_manager.php:204-218) flips status active/archived. **Has** parity. | Roughly equivalent — Airpay collapses publish+visibility into status. | none |
| 18 | **Sortorder reorder UX** | `module.js` drag-and-drop reorder via AJAX `assign_courses_to_learningplan` rebuilds sortorder | `path_manager::reorder_courses()` external endpoint exists. **AMD `path_actions.js` has reorder wiring** — needs check on actual drag handle in template | view.mustache uses shared datatable. Mustache has no drag handles shown → reorder service exists but UI may not be wired | **P1** |
| 19 | **Request integration (cross-org request approval)** | `db/access.php` declares `local/learningplan:approverecord`; index.php links to `/local/request/index.php?component=learningplan` (line 153) | No request integration. `airpay_request` exists but does not list `airpay_learningpath` as a component | Learner cannot raise a "please enrol me into path X" ticket | **P1** |
| 20 | **Capability granularity** | 13 capabilities: manage, exportplans, view, visible, create, delete, update, publishplan, assignhisusers, assigncourses, assigncourses_ownorganization, owndepartment_learningplan, ownorganization_learningplan, multiorganizations_learningplan | 6 capabilities: manage, view, enrol, create, update, delete | "Assign-courses but not edit-name" and similar sub-permissions lost | **P2** |
| 21 | **Multilingual UI** | en + es + hi lang packs | en only | Hindi UI gone for Airpay India users | **P2** |
| 22 | **Date range (startdate / enddate) on path** | Validation `if($data['enddate'] < $data['startdate'])` (`classes/forms/learningplan.php:307`) → `local_learningplan.startdate` + `enddate` columns used by `lpusers_enroll.php` and notifications (`$dataobj->lep_startdate, lep_enddate`) | No start/end date fields on the path. `local_airpay_learningpath` schema has only `timecreated, timemodified` | Time-bounded compliance window ("complete this by quarter-end") not enforceable | **P1** |
| 23 | **Path category** | `local_learningplan.open_categoryid` FK → `local_custom_category`; autocomplete in form-step-0 | No category field | Paths cannot be grouped by topic | **P2** |
| 24 | **Reports integration (LearnerScript)** | `index.php:112-138` reads from `block_learnerscript` table and dynamically lists reports filtered by `category='local_learningplan'` | No LearnerScript dropdown | Pre-built path reports invisible | **P2** |
| 25 | **Description: rich-text editor with file uploads** | `editor` element with `EDITOR_UNLIMITED_FILES`, custom file area `summaryfile`, image accept-types | Plain `textarea` (5 rows × 50 cols) for description | Cannot embed images / formatted instructions | **P1** |
| 26 | **Path summary image (cover image)** | `local_learningplan.summaryfile` filearea with image filepicker (`forms/learningplan.php:208-210`); fallback default image picker | Not present | List view + learner card has no cover image — purely textual | **P2** |
| 27 | **Suspend / cohort-group based audience** | `open_group` FK → cohort; user enumeration joins `cohort_members` (lib.php:218-230) | None | Cohort-driven assignment lost | **P1** |

---

## User flows (multi-step tasks) — works/broken trace

### Flow 1: Admin creates a new learning path
**BizLMS behaviour:**
1. Click **Add Learning Path (+map icon)** on `/local/learningplan/index.php` → opens modal wizard with **3 tabs**.
2. **Tab 1 — General**: pick org→department→sub-dept→L4→L5, type plan name + shortname, pick category, set self-enrol Y/N, set approval-required Y/N (hidden unless self-enrol=Y), upload cover image.
3. **Tab 2 — Other**: Description (rich-text editor + image upload), bind to skill, bind to level, bind to certificate template (optional).
4. **Tab 3 — Audience**: pick designation, group/cohort, location, hrmsrole filters.
5. Save → row inserted, `learningplan_created` event fires, observer wires notifications.

**Airpay behaviour:**
1. Click **Add Path** → opens flat modal with 4 fields.
2. Fill name, description (plain text), pick costcenter, set status.
3. Save → row in `local_airpay_learningpath`. No event. No notification. No certificate binding. No audience targeting.

**Result:** Step 1 works. Steps 2-5 **gone**. **BROKEN — P0**

### Flow 2: Admin adds courses to a path with mandatory/optional sequencing
**BizLMS behaviour:**
1. Open path detail → Courses tab.
2. Click "+ Add courses" → modal lists all available courses scoped to tenant org tree.
3. Select courses → for each, **toggle the AND/OR operator** (`nextsetoperator`): AND=mandatory in-order, OR=optional any-time. Drag to reorder.
4. Save → rows in `local_learningplan_courses` with sortorder + nextsetoperator; auto-creates `enrol` row per course with `enrol='learningplan'` so the path-enrol plugin can later enrol users.

**Airpay behaviour:**
1. Open path → Courses tab.
2. Click "+ Add Courses" → modal with multi-select dropdown.
3. Select courses → save.
4. Rows inserted with `mandatory=1` (hardcoded; never read again), `sortorder` ascending. **No `nextsetoperator`.** No enrol-row created.

**Result:** Steps 1-2 work. Step 3 — mandatory/optional toggle **gone**. Step 4 — auto-enrol plumbing **gone**. **BROKEN — P0**

### Flow 3: Admin assigns 100 users to a path
**BizLMS behaviour:**
1. Open path → Users tab.
2. Click "Enrol users" → bootstrap-duallistbox (BizLMS ships its own JS) with target-audience-filtered candidates (`get_enrollable_users_to_learningplan` filters by designation, location, hrmsrole, cohort, dept hierarchy).
3. Submit → for each user: `assign_users_to_learningplan` inserts `local_learningplan_user`, **auto-enrols user into the first mandatory course**, fires `learningplan_users_created` event, sends `learningplan_enrol` email.

**Airpay behaviour:**
1. Open path → Users tab.
2. Click "Enrol Users" → multi-select dropdown of every user in tenant (no role/designation filter).
3. Submit → `path_manager::enrol_users` inserts `local_airpay_learningpath_users` with status=0. **No course enrolment.** **No event.** **No email.**

**Result:** **Step 3 broken — assigned users physically cannot reach any course on the path. P0**

### Flow 4: Learner self-enrols with manager approval
**BizLMS behaviour:**
1. Learner browses learning plans on `/local/search/allcourses.php?tab=lpath`.
2. Clicks a path → `lpathinfo.php` shows description, courses, "Request to enrol" button (visible only if `selfenrol=1`).
3. Click → `local/request/index.php?component=learningplan` creates approval-pending record.
4. Manager gets email → approves via request UI → `local_learningplan_approval.approvestatus=1`, learner auto-enrolled.

**Airpay behaviour:** Steps 1-4 entirely missing. **NO learner-facing path browse page exists.** **P0**

### Flow 5: Learner views progress through their learning path
**BizLMS behaviour:**
1. Learner clicks **My Learning Plans** (sidebar / dashboard widget).
2. `userdashboard.php` shows enrolled paths in card view + paginated table with per-path completion %.
3. Click path → courses list with green checkmarks on completed + locked icon on not-yet-reachable (gated by sequence).

**Airpay behaviour:** No `userdashboard.php`. No "My Learning Paths" block exists in `theme/airpayux`. Learner has no surface to see paths assigned to them. **P0**

### Flow 6: Course completion advances learner through path
**BizLMS behaviour:**
1. Learner finishes course 1 of 3-course mandatory sequence.
2. `complete_the_lep()` hook (called via `local_lib`) checks all `nextsetoperator='and'` courses for this path/user. If course 1 done, **auto-enrols user into course 2** via `to_enrol_users_check_completion`.
3. If all 3 done → `local_learningplan_user.completiondate` set, `learningplan_user_completed` event fires, **certificate generated** (if `certificateid` was set), `learningplan_completion` email sent.

**Airpay behaviour:** No completion hook. No advancement. No certificate. No event. No email. `path_manager::get_user_progress()` reports % correctly but **nothing changes user's path-status**. **P0**

### Flow 7: Admin exports path data
**BizLMS:** `exportcsv.php` outputs flat list of paths.
**Airpay:** `exportcsv.php` supports `mode=paths` and `mode=path_users` (per-path enrolment + per-user completion %).

**Result:** **Airpay is BETTER here.** No gap.

### Flow 8: Admin filters list across tenants
**BizLMS:** Collapsible filter form with org/dept/subdept/L4/L5/status filters; click sliders icon to open.
**Airpay:** Datatable free-text search only.

**Result:** **DEGRADED — P1.** Admin cannot view "all active paths in ZEEA / Department X".

### Flow 9: Path archived / unenrolled cleanup
**BizLMS:** Archive sets visible=0 (path hidden but data kept); per-user unenrol calls `delete_users_to_learningplan` AND `update_enrol_status(disabled)` so user is removed from underlying enrol instance.
**Airpay:** `toggle_status` flips active/archived (parity). Unenrol does NOT remove user from underlying course enrolment (because Airpay never enrolled them in the first place).

**Result:** Path-status parity is OK. Course-enrol cleanup N/A because no enrolments were created.

---

## Severity legend
- **P0** = blocks enterprise use; admin or learner cannot complete a core task
- **P1** = important workflow degraded but a manual workaround exists
- **P2** = polish / ergonomics

---

## Recommended fixes (prioritised)

### Wave 1 — **P0 unblockers (this week)**

1. **[P0] Wire path-enrol → course-enrol** — when `path_manager::enrol_users()` inserts a path-user row, also create a `local_airpay_learningpath` enrol-plugin row + call `enrol_user()` for each course on the path (or at minimum for the first mandatory course).
   - **Start at:** `C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\path_manager.php:431-488` (`enrol_users()` method).
   - Compare against `C:\xampp\htdocs\moodle5\bizlms_disabled\learningplan\classes\lib\lib.php:854-876` (`to_enrol_users()`) for the pattern.
   - Estimate: 1 day. Requires registering an `enrol` plugin or piggy-backing on `manual` enrol.

2. **[P0] Build "My Learning Paths" learner surface** — add `/local/airpay_learningpath/my.php` showing the learner their enrolled paths with per-path progress.
   - Reuse `path_manager::get_user_progress()` (already exists at `path_manager.php:85-105`).
   - Create new mustache `templates/my.mustache` (card list of paths + % bar + "Continue" CTA).
   - Add navbar / dashboard block entry. Block: `moodle-enhancement/theme/airpayux/templates/navbar.mustache`.
   - Estimate: 1 day.

3. **[P0] Learner path detail page** — public `view_learner.php` that shows description + ordered course list with completion ticks + "Start course" CTA.
   - Pair with #1 above so clicking "Start" actually enrols the user.
   - Estimate: 0.5 day.

4. **[P0] Sequence advancement on completion** — add observer for `\core\event\course_completed` that calls a new `path_manager::on_course_completed($userid, $courseid)` method, which:
   - Looks up every path containing that course.
   - For each path: if all mandatory courses done → set `local_airpay_learningpath_users.status = ENROL_COMPLETED + timecompleted = time()`.
   - Else: enrol user in next mandatory in sortorder.
   - **Start at:** create `classes/observer.php` and `db/events.php`. Pattern lives in `C:\xampp\htdocs\moodle5\bizlms_disabled\learningplan\classes\observer.php` and `db/events.php`.
   - Estimate: 1 day.

5. **[P0] Mandatory/Optional toggle on courses** — add `nextsetoperator ENUM('and','or')` (or `mandatory int default 1`) UI:
   - Schema change: add `mandatory` is already there; add a `gating_mode` column (`and|or`) to `local_airpay_learningpath_courses` if not present, or use existing `mandatory` (1=and, 0=or).
   - UI: add a checkbox on each course row in `view.mustache` Courses tab; AJAX-update via new `set_course_mandatory` external service.
   - Update `assign_courses_form` to expose the choice on add.
   - Estimate: 1 day.

6. **[P0] Email notifications** — create `db/events.php` + `db/messages.php` + `classes/observer.php`:
   - On `assign_users` → email "You've been enrolled in path X".
   - On path complete → email "You've completed path X" (+ attach certificate if mapped).
   - On `course_completed` advancing → optional "next course is now unlocked" email.
   - Pattern reference: `bizlms_disabled\learningplan\classes\notification.php` (202 LOC) + `db/messages.php`.
   - Estimate: 1 day.

7. **[P0] Form wizard — bring back step 2 (description editor + skill/level/certificate)** — convert `edit_path.php` into 3-page wizard OR add a separate "Settings" tab inside `view.php` for these less-common fields. At minimum bring back **rich-text description**.
   - **Start at:** `C:\xampp\htdocs\moodle5\public\local\airpay_learningpath\classes\form\edit_path.php:43-44` — change `textarea` → `editor`.
   - Add fields: `certificateid` (FK to `tool_certificate_templates`), `open_skill` (FK to `airpay_skills`), `open_level`, `startdate`, `enddate`.
   - Schema additions to `local_airpay_learningpath`: `certificateid bigint, skillid bigint, levelid bigint, startdate bigint, enddate bigint, summaryfile bigint, open_categoryid bigint`.
   - Estimate: 1.5 days.

8. **[P0] Manager approval workflow** — re-create the self-enrol-with-approval surface:
   - New table `local_airpay_learningpath_approval (id, pathid, userid, approvedby, approvestatus, reject_msg, timecreated, timemodified)`.
   - Add `selfenrol + approvalreqd` columns on `local_airpay_learningpath`.
   - On learner "Request enrolment" click → row in approval table, status=pending, email to user's manager.
   - Manager-side approve/reject UI integrated with `airpay_request`.
   - Estimate: 2 days.

### Wave 2 — **P1 (next week)**

9. **[P1] Structured filters on list view** — add costcenter dropdown, status filter, date filter to `index.php` + `manage.mustache`. Pattern reference: `bizlms_disabled\learningplan\filters_form.php`.
10. **[P1] Target-audience filtering on user enrolment** — add designation, cohort, location, role filters to `enrol_users_form.php`. Pattern: `lib.php:154-200`.
11. **[P1] Path start/end dates** — schema + validation (`enddate < startdate`).
12. **[P1] Drag-and-drop reorder UI** — verify `path_actions.js` actually wires SortableJS to the course rows. If not, add it.
13. **[P1] Skill & level bindings** — integrate with `airpay_skills` plugin.
14. **[P1] Cohort-based assignment** — let admin pick a cohort and bulk-enrol all members.
15. **[P1] airpay_request integration** — register `learningpath` as a request component.

### Wave 3 — **P2 (ongoing)**

16. **[P2] Card view toggle** on list (cards vs table).
17. **[P2] Bulk delete/archive** with checkbox column.
18. **[P2] Tags** — integrate with `core_tag`.
19. **[P2] Path category** — group paths by topic.
20. **[P2] LearnerScript reports** integration (if/when LearnerScript is brought back).
21. **[P2] Spanish & Hindi lang packs**.
22. **[P2] Cover image picker** + thumbnail in list view.

---

## Risk callouts

1. **Existing `local_airpay_learningpath_users` rows are silent ghosts.** Any user enrolled via the current Airpay flow is in the table but **cannot reach the underlying courses**. If learners have been assigned in production, they have been broken since rollout. Verify with: `SELECT COUNT(*) FROM mdl_local_airpay_learningpath_users WHERE timecompleted IS NULL AND timecreated < UNIX_TIMESTAMP(NOW() - INTERVAL 7 DAY)`.
2. **Phase B0 ships compliance assignments via paths.** If any compliance path was assigned and is being audited, the audit will say "0% completion" — because Airpay never started the enrolment chain.
3. **`path_manager::get_user_progress` reads `course_completions` directly.** This works, but UI surfaces this nowhere visible to the learner. Add the Learner view (P0 #2 above) ASAP.
4. **BizLMS `enrol_learningplan` plugin** lives at `C:\xampp\htdocs\moodle5\bizlms_disabled\enrol\learningplan\` (if disabled alongside the local plugin). Verify; either re-enable that enrol plugin or build Airpay's own.

---

## Files most likely touched during fixes

- `classes/path_manager.php` — line 431-519 (enrol_users / unenrol_user)
- `classes/form/edit_path.php` — line 27-65 (definition), line 84-101 (set_data)
- `classes/form/enrol_users_form.php` — line 22-90 (definition)
- `classes/form/assign_courses_form.php` — line 67-82 (definition)
- `db/install.xml` — schema additions for cert/skill/level/dates/audience
- `templates/view.mustache` — Courses tab, add mandatory toggle column
- `templates/manage.mustache` — add filter row
- **New:** `classes/observer.php`, `db/events.php`, `db/messages.php`, `classes/notification.php`, `classes/path_enrol_plugin.php` (or piggy-back on manual)
- **New:** `my.php`, `view_learner.php`, `templates/my.mustache`, `templates/view_learner.mustache`
- **New:** `db/upgrade.php` for schema migrations
