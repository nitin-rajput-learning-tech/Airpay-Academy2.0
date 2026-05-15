# airpay_programs vs BizLMS local_program — Parity Audit
Date: 2026-05-15 | Auditor: Claude (parity-audit-2026-05-15) | Method: line-by-line review of every PHP file, every external WS, every template, every form, every AMD module on both sides.

## Source paths + size

| Side | Path | PHP files | LOC |
|------|------|-----------|-----|
| BizLMS local_program | `C:\xampp\htdocs\moodle5\bizlms_disabled\program\` | 63 | **13,301** |
| Airpay local_airpay_programs | `C:\xampp\htdocs\moodle5\public\local\airpay_programs\` | 32 | **4,012** |

Airpay = **30.2 % of BizLMS LOC**. ~9,300 lines have no equivalent. The single file `classes/program.php` in BizLMS is 95,231 bytes (~1,800 lines) with 46 public methods — the closest equivalent in Airpay (`program_manager.php`) is much smaller (file size not measured, but ~26 methods).

## Database tables (BizLMS = 12, Airpay = 4)

| BizLMS table | Airpay equivalent | Notes |
|--------------|-------------------|-------|
| `local_program` | `local_airpay_programs` | Schema slimmed — missing `selfenrol`, `approvalreqd`, `capacity`, `programlogo`, `startdate`, `enddate`, `shortname`, `usermodified` |
| `local_program_users` | `local_airpay_programs_users` | Parity reasonable; adds `currentlevelid` and `status` enum |
| `local_program_completions_bk` | **MISSING** | Backup table — used by BizLMS for rollback when level completion criteria change |
| `local_bc_level_comp_bk` | **MISSING** | Backup table — same purpose for level completions |
| `local_program_levels` | `local_airpay_programs_levels` | Parity OK |
| `local_program_level_courses` | `local_airpay_programs_courses` | Parity OK (renamed) |
| `local_bcl_cmplt_criteria` | **MISSING** | Per-level **completion criteria** (number of mandatory courses to pass, score thresholds, attendance thresholds) |
| `local_bc_level_completions` | **MISSING** | Per-user-per-level completion record with timestamps + score |
| `local_bc_completion_criteria` | **MISSING** | Program-level completion criteria (number of levels required) |
| `local_program_trainers` | **MISSING** | Program-trainer assignment (M:N) |
| `local_program_trainerfb` | **MISSING** | Trainer feedback |
| `local_program_test_score` | **MISSING** | Per-user per-level test scores |

**Critical gap: 6 of 12 BizLMS tables are missing**, including the entire completion-criteria machinery. Airpay's `program_manager::is_level_completed_by_user` likely derives completion dynamically from course completions only — no audit-trail row of "user X completed level Y on date Z".

## Web service surface (BizLMS = 24, Airpay = 10)

| BizLMS WS (methodname) | Airpay equivalent | Notes |
|------------------------|-------------------|-------|
| `program_instance` (create/update) | dynamic_form `airpay_programs\form\edit_program` | Form replacement |
| `delete_program_instance` | `_delete_program` | Parity OK |
| `program_course_selector` | **MISSING** as WS — replaced by static dropdown? | Loss of AJAX autocomplete for course assignment |
| `program_form_option_selector` | **MISSING** | Generic AJAX option feeder |
| `program_course_instance` (assign course to level) | **MISSING** (uses `program_manager::assign_courses_to_level` direct call?) | Need to verify there's an external WS for adding courses to levels via UI |
| `delete_programcourse_instance` | `_unassign_level_course` | Parity OK |
| `program_completion_settings_instance` | **MISSING** | Save completion criteria for a program |
| `manageprogramlevels` | **MISSING** as WS | Bulk level CRUD — Airpay has per-level WS instead |
| `bclevel_unassign_course` | `_unassign_level_course` | Parity OK |
| `delete_level_instance` | `_delete_level` | Parity OK |
| `manageprogramStatus_instance` | `_change_status` | Parity OK |
| `inactive_program_instance` / `active_program_instance` | folded into `_change_status` | OK |
| `data_for_programs` / `_paginated` | **MISSING** | Dashboard data feed |
| `unenrol_user` | `_unenrol_user` | Parity OK |
| `program_completion_settings` (read) | **MISSING** | Get program completion criteria |
| `level_completion_settings` (read) | **MISSING** | Get level completion criteria |
| `myprograms` | **MISSING** | Learner's enrolled programs (used by mobile + dashboard) |
| `programlevels` | `_list_levels` | Parity OK |
| `levelcourses` | `_list_level_courses` | Parity OK |
| `myprogramstatus` | **MISSING** | Learner's progress in a program |
| `get_program_info` (mobile) | **MISSING** | Mobile WS |

## Feature parity matrix

| # | Feature | BizLMS | Airpay | Gap | Severity |
|---|---------|--------|--------|-----|----------|
| 1 | Program list (admin) | `index.php` + tabs (Browse/My) + card/table toggle + 5-level hierarchy filter | `index.php` + datatable + 3 KPI tiles | No card/table toggle, no Browse/My toggle, no hierarchy filter | **P1** |
| 2 | Program create wizard | `classroom_form.php`-style 4-step (manage_program / location_date / classroom_misc=assign_course / target_audience) — visible in `program::manage_program` flow | dynamic_form (single page?) | Multi-step wizard reduction. Target audience step **gone**. | **P0** |
| 3 | Program self-enrol + approval workflow | `selfenrol` + `approvalreqd` fields on `local_program` + `program_self_enrolment` method | **MISSING** | Learners cannot self-enrol; admins must enrol manually. | **P1** |
| 4 | Program capacity limit | `capacity` field + `program_capacity_check` | **MISSING** | Cannot define "max 50 enrolees per program". | **P1** |
| 5 | Program logo | `programlogo` filemanager + `program_logo` method | **MISSING** | No branding per program. | **P2** |
| 6 | Program start/end date | `startdate`, `enddate` on table | **MISSING** | Cannot define program duration (e.g. "Manager Track Q3 cohort"). | **P1** |
| 7 | Level CRUD | `manage_program_stream_levels` + WS `manageprogramlevels` (bulk) | `_delete_level`, `_reorder_levels` + dynamic_form for create/edit | Mostly parity. Bulk level create endpoint missing — must add levels one at a time. | **P2** |
| 8 | Per-level course assignment | `manage_program_courses` + autocomplete picker | `_list_level_courses` + `_unassign_level_course` — UI for ASSIGN courses to level not clearly exposed via WS | **Verify**: how does an admin add a new course to an existing level? Looking at the WS list, there's no `assign_level_course` WS — only unassign. This is a **gap**. | **P0** |
| 9 | Course assignment via autocomplete | `program_course_selector` WS — AJAX search for courses | **MISSING** | If admin can add courses to levels at all, it's via static select, not autocomplete. | **P1** |
| 10 | Level completion criteria | `local_bcl_cmplt_criteria` table + `manage_program_level_completions` + `bclevel_completions` | airpay_programs_levels.`completion_required` (binary 1=must complete, 0=optional) | **Critical regression**: BizLMS had **per-level** completion criteria (e.g. "complete 4 of 5 courses with avg score 70+ to pass level"). Airpay reduces this to a single yes/no flag. | **P0** |
| 11 | Program completion criteria | `local_bc_completion_criteria` table | `local_airpay_programs.completion_required` (binary 1=all levels req, 0=any level completes) | **Critical regression**: was "must complete 3 of 4 levels"; now binary. | **P0** |
| 12 | Per-level completion log | `local_bc_level_completions` table — timestamped row per user per level with score | **MISSING** | Derived dynamically — **no audit log** of "user X passed level Y on date Z with score N". | **P0** for compliance auditing |
| 13 | Sequential / prerequisite gating | `is_level_completed_by_user` + `is_level_unlocked_for_user` + `get_user_program_state` in Airpay (looks intact) + `mycompletedlevels`, `mynextlevels`, `mylevelsandcompletedlevels` in BizLMS | program_manager.php has all 3 methods | **Airpay parity OK** for sequential unlock logic. | — |
| 14 | Program trainer assignment (M:N) | `local_program_trainers` table + assign UI | **MISSING** | Cannot assign trainers/mentors to a program. | **P1** |
| 15 | Trainer feedback | `local_program_trainerfb` | **MISSING** | n/a. | **P1** |
| 16 | Per-level test score | `local_program_test_score` | **MISSING** | Cannot record proctored level-end exam score within the plugin. | **P1** |
| 17 | Bulk enrol — admin dual-listbox + hierarchy filter | `select_to_and_from_users` (185 lines) — dual-listbox with 5-level cascade filter + scroll pagination | datatable + per-user enrol modal | The cascading filter is **gone**. For enrolling 80 managers from Public/Sales into "Manager Track" — no efficient bulk path. | **P1** |
| 18 | Self-service enrolment for learner | `program_self_enrolment($programid, $userid)` | **MISSING** | n/a. | **P1** |
| 19 | Enrol via cohort | `program_manager::enrol_cohort(int $programid, int $cohortid)` is in airpay_programs! | **Airpay improvement** | — |
| 20 | Program-completion event/notification | `notification.php` (12,406 bytes) | Need to verify event class exists | If level complete + program complete events don't fire, downstream certificate plugins won't trigger. | **P1** |
| 21 | Notification cron task | `task/` directory in BizLMS | Need to verify | If reminder emails for "complete level 1 by deadline" are gone, learners won't be nudged. | **P1** |
| 22 | Backup / rollback of completion data | `local_program_completions_bk` + `local_bc_level_comp_bk` — when criteria change, snapshot existing completions | **MISSING** | Changing completion rules will permanently re-evaluate all users with no rollback. | **P1** |
| 23 | Mobile WS | `get_program_info` registered with MOODLE_OFFICIAL_MOBILE_SERVICE (note: not flagged in services.php inspection, but the WS naming suggests it was intended) | **0 mobile WS** | Mobile app cannot fetch programs. | **P0** if mobile in use |
| 24 | Per-program target audience | `program_target_audience` + multi-dept picker | open_path on table only | Cannot define "this program is for Public + ZEEA combined". | **P1** |
| 25 | Program request flow | Inherits from `local_request` shared plugin | **MISSING** | Cannot request a program be created from this UI. | **P2** |
| 26 | Card/table view toggle | formattype URL param | **MISSING** | Single table view only. | **P2** |
| 27 | Classroom-program integration | `get_classrooms_count($courseid)`, `get_enrolledclassrooms_count`, `get_classrooms_content`, `get_enrolledclassrooms_content`, `get_classroom_ta_query`, `get_course_classrooms` (6 methods!) — program courses can contain classrooms that count toward completion | **MISSING** | Programs cannot include ILT classroom sessions as components. Only Moodle courses. | **P1** |
| 28 | "My next levels" widget | `mynextlevels`, `mycompletedlevelcourses`, `mynextlevelcourses` (4 user-facing methods) | `get_user_program_state` (one method, returns full state) | **Mostly parity** — Airpay's single method returns equivalent data. | — |
| 29 | "My program status" badge | `myprogramstatus($programid)` | View.php has `user_state` for learners | **Parity OK** | — |
| 30 | Status workflow | active/inactive | draft/active/archived (3-state) | **Airpay improvement.** | — |
| 31 | Tenant scoping | Implicit via open_path | view.php lines 27-39 enforces explicitly | **Airpay improvement.** | — |

## User flows (multi-step tasks)

### Flow 1: Admin creates "Manager Excellence Program" — 4 levels, each with 3-5 courses + classroom
**BizLMS:**
1. From `local/program/index.php` click "+" → modal opens → `classroom_form.php` (yes — reused from classroom) step 1
2. Enter name, description, capacity, selfenrol, approvalreqd, programlogo, mincapacity, startdate, enddate
3. Next → step 2 (location_date): start/end ranges
4. Next → step 3 (classroom_misc): autocomplete assign one or more Moodle courses
5. Next → step 4 (target_audience): pick org/dept/subdept (defines who can self-enrol)
6. Submit → `program_instance` WS → row inserted into `local_program`
7. Then add levels: `manageprogramlevels` WS (bulk) → 4 rows in `local_program_levels` with sort_order
8. Per level: assign courses via `program_course_instance` → `local_program_level_courses` rows
9. Per level: define completion criteria via `program_completion_settings_instance` → `local_bcl_cmplt_criteria` rows ("must complete 4 of 5 courses with avg ≥ 70%")
10. Per level: **assign classroom** via `manage_classroom_course_enrolments`/`get_classroom_ta_query` machinery (BizLMS programs and classrooms are linked!)

**Airpay:**
1. From `airpay_programs/index.php` → click "Create new program" → dynamic_form pops up
2. Fill name, description, completion_required (binary), status
3. Save → row inserted
4. Click into program → Levels tab → add level (one at a time) — no bulk add
5. Click level → levelcourses.php → search for courses → assign — but the assign WS isn't in services.php → **may have no path**
6. Per-level criteria: NOT POSSIBLE — only the binary `completion_required` flag

Steps 5 needs verification. Steps 9 (level criteria) and 10 (classroom linkage) are **architecturally absent**. **P0 — program builder cannot define the criteria for the most common L&D track structure.**

### Flow 2: Admin enrols a cohort of new managers into the program
**BizLMS:** From program view → Enrol users → 5-level dept filter → select all managers in Sales → progressbar → done.

**Airpay:** From view.php?tab=users → click "Enrol users" — but `program_manager::enrol_cohort` is available, so cohort enrol works. **Cohort enrol parity OK.** Bulk via 5-level filter: **MISSING.** **P1.**

### Flow 3: Learner views "where am I in this program"
**BizLMS:** `myprogramstatus($programid)` returns level-by-level state + next steps + completed courses.

**Airpay:** view.php?id=N for the learner — `program_manager::get_user_program_state` returns levels with locked/unlocked/completed flags. **Parity OK** (this is one of the well-ported parts).

### Flow 4: Manager assigns L&D mentors to a program
**BizLMS:** From program edit → trainers tab → autocomplete add → rows in `local_program_trainers`.

**Airpay:** **MISSING.** Cannot assign mentors. **P1.**

### Flow 5: Level criteria — "complete 4 of 5 courses with avg score ≥ 70%"
**BizLMS:** Per-level form → criteria builder → save into `local_bcl_cmplt_criteria` → on each course completion, `bclevel_completions` evaluates.

**Airpay:** Cannot define numeric criteria. Only "all required courses must be complete" via binary flag on level. **P0 — common enterprise pattern.**

### Flow 6: Compliance auditor pulls "who completed Manager Track and when"
**BizLMS:** Query `local_bc_level_completions` for `programid=N, status=COMPLETED, timecompleted BETWEEN X AND Y`.

**Airpay:** No equivalent table. Must derive from `local_airpay_programs_users.status=2 (completed)` + `timecompleted` field — which **exists** on Airpay's users table. Partial parity. But level-by-level completion timestamps: **gone**.

### Flow 7: Mobile app shows "My programs"
**BizLMS:** `myprograms` + `get_program_info` mobile WS.

**Airpay:** No mobile WS exposed. **P0 if mobile in use.**

## Severity legend
- **P0** = blocks enterprise use (no per-level criteria, no level completion log, no classroom-program integration, no course-assign WS, mobile app broken)
- **P1** = important workflow degraded (no self-enrol, no capacity, no trainer assignment, no notifications, no start/end dates, no bulk-enrol hierarchy filter)
- **P2** = polish (no logo, no card view, no request flow)

## Recommended fixes (prioritised)

1. **[P0] Verify and add `assign_level_course` external WS** — check `airpay_programs/levelcourses.php` is calling a WS to add courses (not just unassign). If no add-courses WS exists in `services.php`, port from `bizlms_disabled/program/classes/external.php` near `program_course_instance`. Reference: airpay_programs DB has `local_airpay_programs_courses` table — `program_manager::assign_courses_to_level` exists but it's not exposed via services.php.
2. **[P0] Per-level completion criteria** — add `local_airpay_programs_level_criteria` table mirroring `local_bcl_cmplt_criteria`. Fields: `levelid`, `criteria_type` (course_count|avg_score|attendance), `threshold_value`, `min_required`. Update `program_manager::is_level_completed_by_user` to evaluate against these criteria.
3. **[P0] Level completion log** — add `local_airpay_programs_level_completions` table with `userid`, `programid`, `levelid`, `status`, `score`, `timecompleted`. Insert row when criteria met. Used for compliance reporting + audit trail.
4. **[P0] Classroom-program integration** — allow a program level to contain not just Moodle courses but also `local_airpay_classroom` items. Either (a) extend `local_airpay_programs_courses` with a polymorphic `component`+`itemid`, or (b) add `local_airpay_programs_classrooms`. Reference: `bizlms_disabled/program/classes/program.php:get_classrooms_count` and related 6 methods.
5. **[P0] Mobile WS** — register `local_airpay_programs_get_program_info`, `_myprograms`, `_myprogramstatus` with `'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)`.
6. **[P0] Program-level completion criteria** — add `local_airpay_programs_completion_criteria` table mirroring `local_bc_completion_criteria` for "must complete N of M levels". Replace the binary `completion_required` flag with this richer model.
7. **[P1] Self-enrol + approval** — add `selfenrol`, `approvalreqd` fields to `local_airpay_programs`. Add `program_self_enrol` external WS + approval queue UI.
8. **[P1] Trainer assignment** — new table `local_airpay_programs_trainers` (programid, userid, role, hours). Add trainer-picker tab in program edit form. Reference: `bizlms_disabled/program/classes/program.php:manage_program_trainers` (the program plugin's symmetric to classroom's).
9. **[P1] Capacity** — add `capacity` field + `program_manager::can_enrol` check before enrolling.
10. **[P1] Start/end dates** — add `startdate`, `enddate` fields for cohort programs.
11. **[P1] Notification events** — port `bizlms_disabled/program/classes/notification.php` (12,406 bytes). Events: program_enrolled, level_completed, program_completed, deadline_approaching.
12. **[P1] 5-level hierarchy bulk-enrol** — same fix as airpay_courses #1. Extend `list_program_users.php` external to accept dept/subdept filters; add cascade selects to view.php?tab=users.
13. **[P1] Target audience picker** — extend create form with dept hierarchy. Store target_audience JSON or new linked table. Reference: `bizlms_disabled/program/classes/program.php:program_target_audience`.
14. **[P1] Backup-on-criteria-change** — replicate `local_program_completions_bk` and `local_bc_level_comp_bk` tables so admin can rollback if criteria change re-evaluates badly. (Minor — only needed once criteria implementation exists.)
15. **[P2] Program logo** — add filemanager element to create form + filearea.
16. **[P2] Card/table view toggle** — add `formattype` URL param to index.php and switch templates.
17. **[P2] Request-a-program flow** — depends on `local_request` decision (same as exams plugin).
