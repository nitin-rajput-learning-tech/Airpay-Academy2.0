# airpay_exams vs BizLMS local_onlineexams — Parity Audit
Date: 2026-05-15 | Auditor: Claude (parity-audit-2026-05-15) | Method: line-by-line review of every PHP file, every external WS, every template, every form, every AMD module on both sides.

## Source paths + size

| Side | Path | PHP files | LOC |
|------|------|-----------|-----|
| BizLMS local_onlineexams | `C:\xampp\htdocs\moodle5\bizlms_disabled\onlineexams\` | 27 | **6,338** |
| Airpay local_airpay_exams | `C:\xampp\htdocs\moodle5\public\local\airpay_exams\` | 17 | **1,424** |

Airpay = **22.5 % of BizLMS LOC**. ~4,900 lines have no equivalent.

**Architecture difference:** BizLMS local_onlineexams **creates a full Moodle course** with `open_module='online_exams'` + an auto-configured `mod_quiz` activity inside it. The plugin is effectively a specialised course builder. Airpay local_airpay_exams is a **thin wrapper** that points to an **existing** `mod_quiz` activity by quizid (FK) — it does not create courses or quizzes itself; it only metadata-tags them.

This is a fundamental contract change with consequences below.

## Database tables

| Side | Tables | Notes |
|------|--------|-------|
| BizLMS | **0 tables** (uses `mdl_course` with discriminator `open_module='online_exams'` + `open_coursetype=1`) | All exams live in the global course table — no dedicated row |
| Airpay | `local_airpay_exams` (1 table) | Foreign-keys to existing `mdl_quiz.id` |

Airpay's table is cleaner. But the discriminator-based BizLMS approach means an "exam" was a full Moodle course (with sections, files, completion, certificates, multiple activities…). Airpay's exam is just one quiz wrapped in a name and a pass-grade. **Big functionality reduction.**

## Web service surface (BizLMS = 8, Airpay = 3)

| BizLMS WS | Airpay equivalent | Notes |
|-----------|-------------------|-------|
| `submit_create_onlineexams_form` | **MISSING** (replaced by `airpay_exams\form\edit_exam` dynamic_form) | Form replacement, but BizLMS' form auto-created a course + quiz; Airpay's only links to existing quiz |
| `delete_onlineexams` | `_delete_exam` | Parity OK |
| `onlineexams_view` | `_list_exams` | Parity OK |
| `course_update_status` | `_toggle_status` | Parity OK |
| `data_for_onlineexams` | **MISSING** | Dashboard data feed |
| `data_for_onlineexams_paginated` | **MISSING** | Dashboard paginated feed |
| `get_users_onlineexams_information` (mobile) | **MISSING** | Mobile WS |
| `global_filters_form_option_selector` | **MISSING** | Cascading hierarchy filter feed |

## Feature parity matrix

| # | Feature | BizLMS | Airpay | Gap | Severity |
|---|---------|--------|--------|-----|----------|
| 1 | Exam list (admin) | `index.php` + card/table toggle + 5-level hierarchy filter form + status filter | `index.php` + datatable + 3 KPI tiles (Total/Active/Inactive) | **No hierarchy filter, no search by org/dept**. Single-table datatable. | **P1** |
| 2 | Create new exam — full workflow | `custom_onlineexams_form.php` (2-step) — step 1 creates a full course (sections, summary, dates, completion days, points, cost, target audience) and step 2 attaches a **brand-new quiz** with sensible defaults (preferred behaviour deferredfeedback, etc.). Quiz is auto-attached to the course via `add_onlineexam_quiz()` in lib.php:726 | airpay_exams: pick an **existing** quizid from a dropdown (`exam_manager::get_quiz_options`), set name, duration, pass-grade. No course creation. | A creator who wants to make a new exam from scratch must (1) go to /course/management.php to make a course, (2) add a quiz mod, (3) configure 14+ quiz settings, (4) come to local_airpay_exams to wrap it. BizLMS did all 4 steps in one form. | **P0** |
| 3 | Quiz settings defaults | BizLMS pre-fills 30 quiz parameters: `preferredbehaviour=deferredfeedback`, `specificfeedbackimmediately`, `generalfeedbackimmediately`, `overallfeedbackimmediately`, `overallfeedbackopen`, `attemptsallowed`, `timelimit`, etc. (lib.php:726-810) | None — admin picks existing quiz | If admin makes a new quiz manually, they get **Moodle core defaults**, not Airpay's curated exam-mode defaults. | **P1** |
| 4 | Exam course content (slides, lecture notes, supplementary files) | Full course → admin can add resource files, URLs, additional activities | One quiz only | Cannot bundle pre-test reading materials with the exam. | **P1** |
| 5 | User enrolment | `onlineexamsenrol.php` (similar to courses/courseenrol.php) — dual-listbox + 5-level cascading hierarchy filter + bulk enrol with capability auto-grant | **NOT PORTED** — Airpay's exam viewer page (`view.php`) only shows the roster of users already enrolled in the wrapping course | Cannot bulk-enrol users into an exam from this plugin. Admins must navigate to the wrapping course in `airpay_courses` or `/enrol/users.php` to manage roster. | **P1** |
| 6 | Mass-enrol CSV | Inherited via `local/courses/mass_enroll.php` (reuse) | **NOT PORTED** | Cannot CSV-import attendees | **P2** |
| 7 | Custom AJAX dashboard | `custom_ajax.php` (188 lines) — page=1 shows assigned users with grade/status; page=2 shows completed users with completion date | **PARTIAL** — `view.php?tab=attempts` shows quiz_attempts; `tab=roster` shows enrolled users; `tab=analytics` shows pass-rate KPIs | Functionally similar but no per-grade pending/completed split. | **P2** |
| 8 | Per-user exam grade view | `custom_ajax.php` queries `grade_items` + `grade_grades` for the quiz, compares against `gradepass` from grade_items | `view.php?tab=attempts` reads `qa.sumgrades` + does threshold check inline | Mostly equivalent but Airpay's reads `quiz_attempts.sumgrades` not `grade_grades.finalgrade` — these can differ if there are grade overrides or recalculations. | **P2** |
| 9 | Pass/fail classification | `if ($usergrade->finalgrade >= $gradepass) { status='completed' } else 'incompleted'` based on **quiz_grades.gradepass** | View.php:64-72 uses **local_airpay_exams.passinggrade** (a separate field on the wrapper). | If admin changes Moodle's quiz_grades.gradepass without updating local_airpay_exams.passinggrade, the two get out of sync. | **P2** |
| 10 | Status workflow | active/inactive (1/0) on course | Same on `local_airpay_exams.status` | Parity OK. | — |
| 11 | Hierarchy/scope filter | 5-level cascading | Tenant via `open_path` only | Cannot bulk-enrol "all Public/HR" into an exam from this plugin. | **P1** |
| 12 | Exam categories | Inherited from `course_categories` (BizLMS exam = Moodle course) | **NOT PRESENT** — no category field on `local_airpay_exams` | Cannot tag exams by topic (e.g. compliance, sales, leadership). | **P1** |
| 13 | Exam pricing/commerce | Inherited (course had price_status, courseprice from courses plugin) | **MISSING** | Cannot sell exams as paid certification tests. | **P2** |
| 14 | Exam completion certificate | Inherited from courses — `open_certificateid` on course | **MISSING** | Cannot auto-issue a certificate on pass without rebuilding the wrapping course. | **P1** |
| 15 | Exam-level completion days | `open_coursecompletiondays` on course | **MISSING** | Cannot define "user must complete within 30 days". | **P1** |
| 16 | Cron reminder | Shared with `local_courses\task\course_reminder.php` | **MISSING** | No deadline reminders. | **P1** |
| 17 | Notifications | `notification.php` (11,749 bytes) | **MISSING** | No "exam available", "exam passed", "exam failed" notifications. | **P1** |
| 18 | Mobile WS | `get_users_onlineexams_information` registered with MOODLE_OFFICIAL_MOBILE_SERVICE | **MISSING** | Mobile app cannot fetch exam data. | **P0** if mobile in use |
| 19 | Custom navigation node | `local_onlineexams_leftmenunode()` + `local_onlineexams_quicklink_node()` | Need to verify | Sidebar may not have an "Exams" entry. | **P2** |
| 20 | Exam detail page (learner) | `onlinexamdetails.php` (220 lines) — full "course detail" treatment with hero banner, description, category, level, skill, grade view, Start Now button | airpay_exams' `view.php` is admin-facing. No learner-facing detail page. Learner sees the underlying `mod/quiz/view.php` only. | Learners get raw Moodle quiz UI. No Airpay-branded landing page with description, prerequisites, attempt history. | **P1** |
| 21 | Exam attempt analytics | Embedded in custom_ajax tabs + readable via grade_items | `view.php?tab=analytics` — proper KPI tiles, pass rate, avg score, avg time, min/max | **Airpay improvement.** | — |
| 22 | Exam request flow (manager requests an exam be created) | Available via shared `local_request` plugin | **MISSING** | No way to request a new exam from this UI. | **P2** |
| 23 | Tenant scoping | open_path on course + costcenterid via course costcenter relation | open_path on `local_airpay_exams` table | Parity OK. | — |

## User flows (multi-step tasks)

### Flow 1: Admin creates a new "Anti Money Laundering" exam from scratch
**BizLMS:**
1. From `local/onlineexams/index.php` click "+" → modal opens → `custom_onlineexams_form.php` step 1
2. Enter exam name (becomes course fullname), short code, category (autocomplete), description (editor with file manager), completion days, format
3. Next → step 2: Skills, Levels, start/end date, **certificate template**, target audience hierarchy
4. Submit → `submit_create_onlineexams_form` WS → `create_course()` + `add_onlineexam_quiz()` (lib.php:726) creates `mod_quiz` instance with 30 pre-configured options (deferredfeedback, immediate feedback, allow review, etc.) → places quiz in course → returns success

**Airpay:**
1. Admin must first manually go to `/course/management.php` → create a course
2. Inside the course → add Quiz activity → configure all 30+ quiz settings manually (no exam-mode defaults)
3. Go to `/local/airpay_exams/` → click "Create new exam"
4. Fill form: name, **pick existing quiz** from dropdown (`exam_manager::get_quiz_options`), duration, passing grade
5. Submit → row inserted into `local_airpay_exams` pointing at quizid

**Time delta: ~30 sec in BizLMS vs ~5 minutes in Airpay**, plus the admin has to know all the right quiz settings. **P0 — admin UX regression.**

### Flow 2: Admin enrols 200 employees in the AML compliance exam
**BizLMS:**
1. From the exams list, click "Enrol users" icon on the exam row
2. → `onlineexamsenrol.php` (similar to courseenrol with dual-listbox + 5-level hierarchy filter)
3. Filter by Org=Airpay, Dept=Compliance
4. Select all → Add all → progressbar → done

**Airpay:**
1. From `airpay_exams/index.php` → view exam → click View Roster (`view.php?tab=roster`) — this is **read-only**
2. Admin must navigate to the wrapping course in airpay_courses and enrol via the standard course enrol flow
3. (No direct enrol button in airpay_exams.)

**P1 — extra navigation, no bulk path from this plugin.**

### Flow 3: Learner takes an exam
**BizLMS:** From dashboard or exams page → click exam tile → `onlinexamdetails.php` → see description, level, skill, grade, "Start Now" button → routes to `/mod/quiz/view.php?id=N` → take quiz.

**Airpay:** From dashboard → no exams widget (mobile WS missing + no dashboard surface from this plugin). Manually navigate to course → quiz → take quiz. Or go to admin-facing `airpay_exams/view.php` (which is wrong audience).

**P1 — no learner-facing landing page.**

### Flow 4: Admin reviews per-user grades
**BizLMS:** custom_ajax.php?id=N&page=1 (assigned) or page=2 (completed) → AJAX → datatable of users with grade, status, completion date.

**Airpay:** view.php?id=N&tab=attempts → datatable of `quiz_attempts` rows. **Airpay equivalent or better** (KPIs, avg time, etc.).

### Flow 5: Mobile app shows "My exams"
**BizLMS:** Mobile calls `local_onlineexams_get_users_onlineexams_information`.

**Airpay:** No mobile WS. App receives nothing. **P0 if mobile is in production.**

### Flow 6: Auto-issue certificate when user passes
**BizLMS:** Course had `open_certificateid` → on completion, `tool_certificate` plugin issues PDF → `tool_certificate_issues` row.

**Airpay:** No certificate linkage on `local_airpay_exams`. Certificates only fire if the underlying course has them configured manually. **P1 if certificates are required (likely for compliance exams).**

## Severity legend
- **P0** = blocks enterprise use (no quiz auto-creation, no mobile WS, admin workflow takes 5x longer)
- **P1** = important workflow degraded (no certificate auto-issue, no learner detail page, no notifications, no hierarchy filter, no enrol path from plugin, no completion days)
- **P2** = polish (no category, no commerce, no learnerscript reports link)

## Recommended fixes (prioritised)

1. **[P0] Quiz auto-creation in exam-create form** — extend `airpay_exams\form\edit_exam` to optionally **create a new quiz** when no quizid is selected, using the 30 BizLMS defaults. Reference: `bizlms_disabled/onlineexams/lib.php:726-815` `add_onlineexam_quiz` and `update_onlineexam_quiz`. This requires a (a) course-id input or auto-pick "Exams" category course, (b) quiz instance via `quiz_add_instance()`, (c) defaults seeded from lib.php.
2. **[P0] Restore mobile WS** — register `get_users_onlineexams_information` equivalent in `db/services.php` with `'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)`. Returns user's enrolled exams with status/grade/passing-grade. Reference: `bizlms_disabled/onlineexams/classes/external.php` near `get_users_onlineexams_information_parameters`.
3. **[P1] Bulk enrol path from plugin** — add `bulk_enrol.php` page mirroring `airpay_courses/enrol_csv.php` (CSV) plus a bulk-enrol modal in `view.php?tab=roster`. Reference: `bizlms_disabled/onlineexams/onlineexamsenrol.php` for the cascade filter shape.
4. **[P1] Learner-facing detail page** — port `bizlms_disabled/onlineexams/onlinexamdetails.php` into `airpay_exams/detail.php` (or rename current view.php and add a learner mode). Show name, description, skills/level, attempt history, "Start now" button. Sniff: detail.php should require_login() but NOT require_capability(...view) — learners should land here.
5. **[P1] Certificate template linkage** — add `certificate_template_id` field on `local_airpay_exams` (FK to `tool_certificate_templates`). On passing-grade completion, fire event that issues certificate.
6. **[P1] Completion-deadline + reminder** — add `completion_days` field + cron task that emails users approaching deadline. Reuse from `airpay_courses` if/when it gets a reminder task (see courses parity audit P1 #7).
7. **[P1] 5-level hierarchy filter for exam roster + enrolment list** — same as airpay_courses recommended fix #1. Reference: `bizlms_disabled/onlineexams/index.php` filter form.
8. **[P1] Exam categories** — add `categoryid` field on `local_airpay_exams`. Wire to course_categories table or define new `local_airpay_exam_categories`. Reuse the picker from edit_course form.
9. **[P1] Notifications** — port `bizlms_disabled/onlineexams/classes/notification.php` (11,749 bytes) — events: exam_assigned, exam_started, exam_completed, exam_passed, exam_failed.
10. **[P2] CSV mass enrol** — symmetric `enrol_csv.php` in airpay_exams. Probably 95% reusable from `airpay_courses/enrol_csv.php`.
11. **[P2] Left menu + quicklink node** — implement `local_airpay_exams_extend_navigation` callback so sidebar gets an "Online Exams" entry.
12. **[P2] Request-an-exam flow** — depends on `local_request` or `airpay_courses` request flow being shared. Skip until that decision is made.
13. **[P2] Pricing/commerce** — only if Airpay sells certifications externally.
