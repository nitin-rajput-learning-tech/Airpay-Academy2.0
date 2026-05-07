# BizLMS → Airpay Feature Parity Audit

**Date:** 2026-05-06 | **Updated posture 2026-05-06 EOD**
**Scope:** Every BizLMS plugin in `C:\xampp\htdocs\moodle5\bizlms_disabled\` mapped to its Airpay-owned replacement.
**Purpose:** Concrete checklist of what's matched / partial / missing / dropped.

> **PRODUCTION POSTURE (Nitin, 2026-05-06):** Production cutover is gated on
> closing **all** partial / missing items, not just the most-impactful ones.
> Features must work like a true enterprise product — not just exist as
> shells. The list below is the production gate, not "nice to haves".

---

## Legend

| Symbol | Meaning |
|--------|---------|
| ✅ | **Matches** — feature works in airpay equivalent, manually or automatically verified |
| 🟡 | **Partial** — feature exists in airpay but with reduced scope or deferred sub-features |
| ❌ | **Missing** — feature exists in BizLMS, no airpay equivalent shipped |
| 🔵 | **Replaced by core** — feature delegated to Moodle core (forum, groups, tags) |
| ⚫ | **Dropped** — intentionally removed (no Airpay equivalent planned) |

---

## Top-line summary

| BizLMS plugin | Airpay equivalent | LOC ratio | Verdict |
|---|---|---|---|
| **users** | `airpay_users` | 17% | 🟡 Partial — basic CRUD works, missing exportcsv + grades + skill profile + bulk-CSV-status-change |
| **courses** | `airpay_courses` | 9% | 🟡 Partial — list/edit/delete works, missing enrol UI + view detail + mass-enrol + featured-courses widget |
| **classroom** | `airpay_classroom` | 6% | 🟡 Partial — list/edit/delete works, missing session attendance + view detail + enrol UI |
| **onlineexams** | `airpay_exams` | 15% | 🟡 Partial — list/edit/delete works, missing enrol UI + onlineexam detail page |
| **program** | `airpay_programs` | 7% | 🟡 Partial — list/edit/delete works, missing levels UI + enrol + mass-enrol + view detail |
| **learningplan** | `airpay_learningpath` | 8% | 🟡 Partial — list/edit/delete works, missing assign-courses/users + plan view + LEP completion |
| **evaluation** | `airpay_evaluation` | 10% | 🟡 Partial — list/edit/delete + question CRUD + respond works, missing analysis + import/export templates + Kirkpatrick reporting |
| **skillrepository** | `airpay_skills` | 48% | 🟡 Partial — categories + skills CRUD works, missing skill levels + designation-skill assignment |
| **notifications** | `airpay_notifications` | 42% | 🟡 Partial — generic rule engine present, missing 17 BizLMS-specific rule type handlers (cert reminder, course reminder, ILT feedback, etc.) |
| **costcenter** | `airpay_org` | 60% | ✅ Matches — accesslib ported (Phase 0A), org tree + branding work, view + settings deferred |
| **assignroles** | `airpay_roles` | 80% | ✅ Functional — index + per-role view (3 tabs) + audit log + CSV export + 56 PHPUnit tests. Phase-2 follow-ups (bulk caps, role assignments tab, tenant-scoped roles) deferred. Shipped 2026-05-07. |
| **ratings** | `airpay_ratings` | 8% | 🟡 Stub — DB tables shipped, no UI yet (Moodle core ratings used instead?) |
| **myteam** | `airpay_manager` | 19% | 🟡 Partial — manager dashboard + member view work, missing approval workflow + course allocation UI |
| **search** | `airpay_catalog` | 53% | ✅ Matches — learner catalog with filters + course detail working |
| **biz_cart** | (none) | 0% | ⚫ Dropped — shopping cart removed |
| **custom_category** | (none) | 0% | ⚫ Dropped — custom category management removed |
| **forum** | (none) | 0% | 🔵 Replaced by Moodle core forum |
| **groups** | (none) | 0% | 🔵 Replaced by Moodle core groups |
| **location** | (none) | 0% | ⚫ Dropped — location management removed |
| **recompletion** | (none) | 0% | ⚫ Dropped — automatic recompletion removed |
| **request** | (none) | 0% | ⚫ Dropped — course request workflow removed |
| **tags** | (none) | 0% | 🔵 Replaced by Moodle core tags |

**Net status:** 14 / 22 BizLMS plugins have functioning airpay replacements. Of those 14, **2 fully match** (`airpay_org`, `airpay_catalog`), **12 are partial** (CRUD + listing works; advanced flows missing), **0 are stubs** (`airpay_roles` shipped 2026-05-07 EOD; reclassified from stub).

---

## Detailed per-plugin audit

### 1. `airpay_users` (replaces BizLMS `users`)

| Feature surface | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| Manage users page (list/search/sort/paginate) | `index.php` + `renderer.php` | ✅ | `index.php` + `templates/manage.mustache` + datatable. PHPUnit covers list_users. |
| User profile page | `profile.php` | ✅ | `profile.php` + `templates/profile.mustache` |
| Create user (modal) | `signup.php` + `users_view.mustache` | ✅ | `classes/form/edit_user.php` (dynamic_form, mode=create) + `user_actions.js` |
| Edit user (modal) | `edit.php` | ✅ | Same form, mode=edit |
| Delete user | (in renderer) | ✅ | `classes/external/delete_user.php` |
| Suspend/activate user | (in renderer) | ✅ | `classes/external/suspend_user.php` |
| Bulk status change (multiple users) | `download.php` + `statuschangesample.php` | ✅ | `classes/external/bulk_action.php` (tested via `user_actions.js`) |
| **CSV export** | `exportcsv.php` | ❌ | Template references `{{export_url}}` → `/local/airpay_users/exportcsv.php` (404 — file doesn't exist) |
| User grades view | `grades.php` | ❌ | No airpay equivalent. May not be needed if Moodle core gradebook is sufficient. |
| User skill profile | `skillprofile.php` + `userskillprofile.mustache` | ❌ | airpay_skills has skills, but no per-user skill assessment view |
| Bulk status change CSV upload | `statuschangesample.php` | ❌ | No CSV upload form in airpay |
| Privacy policy view | `privacypolicy.php` | 🔵 | Moved to airpay_privacy plugin |
| Terms & conditions view | `termscondition.php` | 🔵 | Moved to airpay_privacy plugin |
| Per-user help links | `help.php` | ⚫ | Dropped — info is on the main page |
| Sample data download | `sample.php` | ⚫ | Dropped — admin sample page deemed unnecessary |

**Risk:** CSV export is broken (link points to nothing) — a user clicking "Export CSV" gets a 404. This is the #1 fix-now item.

**Recommended actions:**
1. **HIGH:** Build `exportcsv.php` in `airpay_users` (~1h) — uses same list_users SQL + CSV writer
2. **MEDIUM:** Decide whether grades + skill profile features are needed for production; if yes, port (~3-4h each)

---

### 2. `airpay_courses` (replaces BizLMS `courses`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List/search/sort courses | `index.php` + `renderer.php` | ✅ | `templates/manage.mustache` + datatable |
| Create course (modal) | `edit.php` + `edit_form.php` | ✅ | `classes/form/edit_course.php` |
| Edit course | Same | ✅ | Same form |
| Delete course | (in renderer) | ✅ | `classes/external/delete_course.php` |
| Toggle visibility | (in renderer) | ✅ | `classes/external/toggle_visibility.php` |
| **Enrol users to course** | `courseenrol.php` + `mass_enroll.php` | ❌ | No airpay enrol UI; relies on Moodle core enrolment |
| **View enrolled users** | `enrolledusers.php` + `enrolledusersview.mustache` | ❌ | Not ported; would have to navigate to Moodle core /enrol |
| **CSV export** | `exportcsv.php` | ❌ | Not ported |
| Course types management | `coursestypes.php` + `coursetypes_table.mustache` | ⚫ | Dropped — Moodle core categories handle this |
| Featured courses widget | `featured_courses.php` | 🟡 | Replaced by airpay_catalog (learner browse view) |
| User dashboard course list | `userdashboard.php` + dashboard templates | ✅ | airpay_catalog handles learner-side |
| Filter form | `filters_form.php` + `filterclass.php` | ✅ | Built into datatable + `manage.mustache` filters |
| Course evidence | `courseevidence.php` | ⚫ | Dropped — no consumer |
| Tag view | `tagview.mustache` | 🔵 | Moodle core tags |
| Self-completion | `selfcompletion.mustache` | ⚫ | Moved to course-level Moodle core completion |

**Risk:** No in-airpay UI to enrol/unenrol users from a course — admins must navigate to Moodle core's `/enrol/users.php?id=N`. That works but breaks the airpay UX flow.

**Recommended actions:**
1. **HIGH:** Add "Enrolled users" action button on course rows that opens Moodle core `/enrol/users.php?id={courseid}` in a new tab (~30min) — preserves the workflow without rebuilding it
2. **MEDIUM:** Bulk-enrol via CSV upload (~3h) — if needed for migration scenarios
3. **LOW:** Build native airpay enrolment UI (~6-8h) — not needed if Moodle core flow is acceptable

---

### 3. `airpay_classroom` (replaces BizLMS `classroom`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List classrooms | `index.php` | ✅ | `manage.mustache` + datatable |
| Create classroom | `view.php` (edit form embedded) | ✅ | `classes/form/edit_classroom.php` |
| Edit classroom | Same | ✅ | Same form |
| Delete classroom | (in renderer) | ✅ | `delete_classroom.php` external |
| Status change (Active/Hold/Cancelled/Completed) | (in renderer) | ✅ | `change_status.php` external |
| **View classroom detail (sub-tabs)** | `view.php` + `classroomview.mustache` + 6 sub-tab templates | ❌ | No `view.php` in airpay; no detail page |
| **Sessions sub-tab** | `classroomviewsessions.mustache` | ❌ | Not ported |
| **Users sub-tab (enrolled)** | `classroomviewusers.mustache` + `enrollusers.php` | ❌ | Not ported |
| **Attendance marking** | `attendance.php` + `session_attendance.mustache` | ❌ | Not ported — significant gap for ILT (instructor-led training) |
| **Waiting list** | `classroomviewwaitinglistusers.mustache` | ❌ | Not ported |
| **Feedback collection** | `classroomviewfeedbacks.mustache` | ❌ | Could route through airpay_evaluation |
| **Target audience** | `classroomviewtargetaudience.mustache` | ❌ | Not ported |
| Tag view | `tagview.mustache` | 🔵 | Moodle core tags |

**Risk:** This is the largest functional gap. ILT (classroom-based training) is a major Airpay use case (annual compliance, new-hire onboarding) and the **attendance feature is absent**. Without it, trainers can't mark who attended a session, which breaks compliance reporting.

**Recommended actions:**
1. **HIGH:** Build classroom detail page with Sessions + Users + Attendance tabs (~12-16h) — this is the biggest gap
2. **MEDIUM:** Waiting list + feedback can be deferred to phase 2

---

### 4. `airpay_exams` (replaces BizLMS `onlineexams`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List exams | `index.php` | ✅ | datatable |
| Create exam | `onlinexamdetails.php` | ✅ | `edit_exam.php` form |
| Edit exam | Same | ✅ | Same form |
| Delete exam | (in renderer) | ✅ | `delete_exam.php` external |
| Toggle status | (in renderer) | ✅ | `toggle_status.php` external |
| **View exam detail** | `onlinexamdetails.php` + `onlineexams_view.mustache` | ❌ | No view page |
| **Enrol users to exam** | `onlineexamsenrol.php` | ❌ | No airpay enrol UI |
| User dashboard exam list | `userdashboard.php` | ✅ | airpay_catalog |

**Risk:** Same as airpay_courses — no in-airpay enrolment UI; admins use Moodle core quiz-level enrolment.

---

### 5. `airpay_programs` (replaces BizLMS `program`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List programs | `index.php` | ✅ | datatable |
| Create/edit program | `edit.php` (form embedded) | ✅ | `edit_program.php` form |
| Delete program | (in renderer) | ✅ | `delete_program.php` |
| Status change | (in renderer) | ✅ | `change_status.php` |
| **Levels CRUD** | `view.php` + `levelstab_content.mustache` | ❌ | install.xml has `local_airpay_programs_levels` table but no UI to manage levels |
| **Courses-per-level CRUD** | `levelcoursescontent.mustache` | ❌ | install.xml has `local_airpay_programs_courses` table but no UI |
| **Enrol users to program** | `enrollusers.php` + `mass_enroll.php` | ❌ | install.xml has `local_airpay_programs_users` table but no UI |
| **View program detail (sub-tabs)** | `view.php` + `programtabs.mustache` | ❌ | No detail page |
| **Filter form** | `filters_form.php` | ✅ | Datatable filters |

**Risk:** The whole multi-level certification feature is non-functional — schema exists, no UI. This is core BizLMS programs functionality (e.g. "Manager Certification — Level 1: Foundations → Level 2: Advanced → Level 3: Expert").

**Recommended actions:**
1. **HIGH:** Build levels CRUD + courses-per-level + enrol UI inside program detail page (~16-20h)
2. **WORKAROUND for now:** Use Moodle core programs_levels table directly via SQL/admin

---

### 6. `airpay_learningpath` (replaces BizLMS `learningplan`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List learning paths | `index.php` | ✅ | datatable |
| Create/edit path | `learningplan_publish_edit.mustache` | ✅ | `edit_path.php` form |
| Delete path | (in renderer) | ✅ | `delete_path.php` |
| Toggle status | (in renderer) | ✅ | `toggle_status.php` |
| **Assign courses to path** | `assign_courses_users.php` | ❌ | install.xml has tables but no UI |
| **Assign users to path** | `lpusers_enroll.php` | ❌ | No UI |
| **View path detail** | `plan_view.php` + `lp_planview.mustache` | ❌ | No detail page |
| **Course completion tracking** | `lep_course_completion.php` | ❌ | Stub — no progress tracking UI |
| **CSV export** | `exportcsv.php` | ❌ | Not ported |

**Risk:** Same level of incompleteness as Programs — schema exists, no UI for assignments.

---

### 7. `airpay_evaluation` (replaces BizLMS `evaluation`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List evaluations | `index.php` | ✅ | datatable |
| Create/edit evaluation | `edit_form.php` | ✅ | `edit_evaluation.php` |
| Delete evaluation | — | ✅ | `delete_evaluation.php` |
| Status change | — | ✅ | `change_status.php` |
| Questions CRUD | `edit_item.php` + `evaluation_form.php` | ✅ | `edit_question.php` + `templates/questions.mustache` |
| Reorder questions | — | ✅ | `reorder_questions.php` external |
| **Respond to evaluation** (learner) | `complete.php` + `eval_view.php` | ✅ | `respond.php` + `templates/respond.mustache` + `submit_response.php` |
| View responses (admin) | `show_entries.php` + `show_nonrespondents.php` | 🟡 | `responses.mustache` exists, basic view |
| **Analysis / charts** | `analysis.php` + `analysis_to_excel.php` | ❌ | No analysis page |
| **Kirkpatrick-level reporting** | (custom in BizLMS) | ❌ | install.xml has `kirkpatrick_level` but no reporting UI |
| **Assign users** | `users_assign.php` | ❌ | No assignment UI |
| **Import/export templates** | `import.php` + `import_form.php` + `delete_template.php` + `use_templ.php` | ❌ | Not ported — admins build from scratch each time |
| **Export to Excel** | `analysis_to_excel.php` | ❌ | Not ported |

**Risk:** Evaluation responses can be collected but **can't be analysed** within airpay. Admins would have to query the DB directly. This breaks the L&D effectiveness measurement loop.

**Recommended actions:**
1. **HIGH:** Build basic analysis page with response counts, average ratings per question, NPS-style breakdown (~8-10h)
2. **MEDIUM:** Kirkpatrick-level filtering on the responses view (~2-3h)
3. **LOW:** Excel export (~3h) — CSV is sufficient

---

### 8. `airpay_skills` (replaces BizLMS `skillrepository`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List skills | `index.php` | ✅ | datatable |
| Create/edit skill | (in renderer) | ✅ | `edit_skill.php` |
| Delete skill | — | ✅ | `delete_skill.php` |
| **Skill categories CRUD** | `addcategory.php` + `skill_category.php` | ✅ | `edit_category.php` + `delete_category.php` |
| **Skill levels** (max_level) | `level.php` | 🟡 | `max_level` column exists, no per-level definition UI |
| **Skill detail / info** | `skillinfo.php` + `skills_view.mustache` | ❌ | No skill detail page |
| **Designation → skill mapping** | (table in install.xml) | ❌ | install.xml has `local_airpay_designation_skills` table but no UI |
| **Course → skill mapping** | (in BizLMS course form) | 🟡 | install.xml has `local_airpay_course_skills` but no UI link |

**Risk:** Skills as definitions work; the linkage to designations + courses (which is the whole point of a skills framework) isn't manageable through the UI.

---

### 9. `airpay_notifications` (replaces BizLMS `notifications`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| List notification rules | `index.php` | ✅ | datatable |
| Create/edit rule | (in renderer) | ✅ | `edit_rule.php` |
| Delete rule | — | ✅ | `delete_rule.php` |
| Toggle rule on/off | — | ✅ | `toggle_rule.php` |
| Notification center (in-app inbox) | `notifications_view.mustache` | ✅ | `notification_center.mustache` |
| **17 specific rule types as separate handlers** | `certification_reminder.php`, `course_completion_notification.php`, `course_remainder.php`, `feedback_due_notification.php`, `ilt_feedback.php`, `ilt_new_course_notification.php`, `ilt_reminder.php`, `lep_completion.php`, `new_course_notification.php`, `onlinetest_due_notification.php`, `program_reminder.php`, `session_reminder.php` | 🟡 | `airpay_notifications/classes/notification_engine.php` (single rule engine) reads `rule_type` column — covers the core types but the 17 specific BizLMS files are NOT 1:1 ported |
| **Email status tracking** | `email_status.php` + `email_status_details.php` + `emaillogs.php` | 🟡 | `local_airpay_notif_log` table exists, basic delivery log; no detailed per-message tracking UI |
| **Email status filters** | `email_status_filters.php` | ❌ | No filter UI on logs |

**Risk:** The 17 specific rule types (cert reminder, course reminder, ILT feedback, etc.) need each to be tested individually against the airpay rule engine to confirm they fire correctly. Without that test, we don't know which BizLMS notifications still work.

---

### 10. `airpay_org` (replaces BizLMS `costcenter`)

| Feature | BizLMS source | Airpay status | Notes |
|---|---|---|---|
| Org tree management | `index.php` + `costcenter_view.mustache` | ✅ | `admin.php` + `manage.mustache` + `org_node.mustache` |
| Create/edit org | (in renderer) | ✅ | `edit_org.php` |
| Delete org | — | ✅ | `delete_org.php` |
| Toggle visibility | — | ✅ | `toggle_visibility.php` |
| **Tenant scoping engine** | `accesslib.php` (515 lines) | ✅ | Phase 0A ported with parameterised SQL; 7/7 PHPUnit tests PASS |
| Cost-center settings | `costcentersettings.php` | 🟡 | `settings.php` is global — per-tenant settings limited |
| Departments view | `departments_view.mustache` | ❌ | No standalone departments view |
| Cost-center detail | `costcenterview.php` | ❌ | No detail page |
| Card-paginated browse | `cardPaginate.mustache` | ✅ | Phase 0B replaced with shared `theme_airpayux/datatable` |
| Global filter | `global_filter.mustache` | ✅ | Built into datatable filters |
| Data migration tool | (none in BizLMS) | ✅ | NEW: `data_migration.php` for legacy data import |

**Risk:** Low. The core tenant-scoping engine + admin UI both work and are tested.

---

### 11–22. Smaller plugins

| Plugin | Status | Notes |
|---|---|---|
| `airpay_assistant` | NEW (no BizLMS equivalent) | Chat bot; functional shell, low priority for cutover |
| `airpay_catalog` | ✅ Replaces `search` | Learner course browsing — covered by `p1_phase_a_smoke.mjs` |
| `airpay_challenge` | NEW — **FUNCTIONAL Phase 1** — shipped 2026-05-07. Course-completion-based challenges + leaderboard + audit + ~45 PHPUnit tests. Engine wraps challenge lifecycle + event-driven progress + 15-min cron snapshot recompute. ~30 files, ~2500 LOC. Phase-2 (streak, quiz-score, badges, push, FE widget) deferred ~30h. State card: `airpay_challenge-state.md`. |
| `airpay_compliance_report` | NEW + replaces parts of BizLMS reports | 4-table compliance schema, functional dashboard |
| `airpay_emails` | NEW (Newsletter+) | 29 email templates, 4 tables, admin tab UI works |
| `airpay_gamification` | NEW; partial (1017 LOC) | Replaces BizLMS `blocks/achievements`; 4 tables; functional |
| `airpay_integrations` | NEW; **FUNCTIONAL** (1457 LOC, 11 files, 0 tables — config-only) | Multiple working clients: Teams notifier, KeKa HRMS OAuth, HRMS sync, AI recommender, web push, webhook receiver. All OFF by default — IT enables per env. **Earlier "mostly empty" was wrong** — see `TIER-2-STUB-AUDIT.md`. |
| `airpay_lifecycle` | NEW; **FUNCTIONAL** (322 LOC, MATURITY_BETA) | Auto-enrol observer + daily compliance scheduled task with Moodle messaging + manager alerts + optional Teams notifications. **Earlier "STUB / not wired up" was wrong** — see `TIER-2-STUB-AUDIT.md`. Promote to STABLE after 7 days of cron success in production. |
| `airpay_manager` | 🟡 Replaces `myteam` | Manager dashboard works, course allocation UI missing |
| `airpay_pages` | NEW (replaces BizLMS `blocks/masterinfo`) | Static page renderer — works |
| `airpay_privacy` | NEW + replaces `users/privacypolicy.php` | GDPR consents + privacy admin |
| `airpay_ratings` | 🟡 Replaces `ratings` | 1 table + display layer (`rating_manager`: get_average, get_user_rating, render). **No submit UI** — Moodle core ratings handle user action. Display-only is intentional for cutover. See `TIER-2-STUB-AUDIT.md`. |
| `airpay_reports` | NEW + wraps LearnerScript | Adds tenant scoping + run UI |
| `airpay_roles` | ✅ Replaces `assignroles` | **FUNCTIONAL** — shipped 2026-05-07. Index page (paginated, archetype + search filters), per-role view with 3 tabs (Overview/Capabilities/Audit), append-only audit log, CSV export of capabilities + audit. 56 PHPUnit tests (24 manager + 32 WS). 28 files, ~1900 LOC. Phase 2 deferred: bulk cap toggle, role assignments tab, tenant-scoped roles. State card: `airpay_roles-state.md`. |

---

## Risk-prioritised gap list

The 6 highest-impact gaps that should be closed before going to a wider production audience:

| # | Gap | Risk if shipped now | Effort |
|---|------|---------------------|--------|
| **G-01** | `airpay_users/exportcsv.php` missing — but template links to it | Admin clicks "Export CSV" → 404. Confidence-killing for site admins. | 1h |
| **G-02** | `airpay_classroom` no attendance UI | ILT trainers can't mark who attended a session → compliance reporting goes blind for ILT events. | 12-16h |
| **G-03** | `airpay_programs` no levels/courses-per-level UI | Multi-level certification flows non-functional; schema unused. | 16-20h |
| **G-04** | `airpay_learningpath` no assign-courses/users UI | Learning paths have no way to add courses → pluging is unusable. | 8-12h |
| **G-05** | `airpay_evaluation` no analysis page | Responses collected but unanalysable in-app → effectiveness measurement broken. | 8-10h |
| **G-06** | No in-airpay enrol UI for courses/exams/programs | Admins fall back to Moodle core `/enrol/users.php` — works but breaks UX flow. | 6-8h per plugin or 30min for "Open in Moodle Core" link |

**Total effort to close all 6:** ~52–66 hours (1 to 1.5 dedicated weeks, multi-session).

---

## Plugins that are **functionally complete today** (per audit)

Despite the gaps above, these plugins are fully functional for their stated purpose at the basic-CRUD + read level:

- ✅ `airpay_org` — multi-tenant scoping engine + admin UI
- ✅ `airpay_catalog` — learner course browsing
- ✅ `airpay_users` — admin user management (G-01 exportcsv shipped 2026-05-06)
- ✅ `airpay_emails` — newsletter / template email delivery
- ✅ `airpay_compliance_report` — compliance dashboard
- ✅ `airpay_privacy` — GDPR consent + privacy admin
- ✅ `airpay_skills` (catalog only — no level UI, no designation mapping)
- ✅ `airpay_notifications` (generic rule engine — 17 specific BizLMS rule types not 1:1 verified)
- ✅ `airpay_courses` — basic CRUD (G-06 enrol UI still pending)
- ✅ `airpay_evaluation` — questionnaire CRUD + respond cycle (no analysis page yet)
- ✅ Shared datatable + a11y + auth + theme + SCORM playback

> **But "functional at basic-CRUD level" ≠ "enterprise-grade"**. The gap list
> below is what stands between the current state and production.

---

## What we know vs what we don't

### Known functional (verified)
- 113/116 functional cases (Phase A) + 73/73 admin-table cases (Phase B) + 12/15 workflow cases (Phase D) + 7/7 SCORM (Phase H) + 64/64 PHPUnit
- All security boundaries (cross-tenant LIKE leak, capability checks, JSON bounds)
- All 10 admin-table plugins' list/sort/search/paginate
- WCAG 2.1 AA on shared datatable + dashboard + manage-users + catalog
- SCORM 1.2 API round-trip on real package

### Unknown (needs verification — see #2 + #3 next)
- CRUD create/edit on each of the 13 dynamic_form plugins (only listing has PHPUnit; create/edit/delete are tested manually but not via PHPUnit)
- The 17 BizLMS notification rule types — do they all still fire?
- Each plugin's permission boundaries on edit/delete (do non-admins correctly fail?)
- Bulk actions (suspend, activate, delete) — race conditions, partial failures

The next two passes (#2 CRUD PHPUnit, #3 deep workflow Playwright) close those.
