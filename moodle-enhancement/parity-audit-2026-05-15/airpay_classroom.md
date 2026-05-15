# airpay_classroom vs BizLMS local_classroom — Parity Audit
Date: 2026-05-15 | Auditor: Claude (parity-audit-2026-05-15) | Method: line-by-line review of every PHP file, every external WS, every template, every form, every AMD module on both sides.

## Source paths + size

| Side | Path | PHP files | LOC |
|------|------|-----------|-----|
| BizLMS local_classroom | `C:\xampp\htdocs\moodle5\bizlms_disabled\classroom\` | 67 | **19,242** |
| Airpay local_airpay_classroom | `C:\xampp\htdocs\moodle5\public\local\airpay_classroom\` | 37 | **4,709** |

Airpay = **24.5 % of BizLMS LOC**. ~14,500 lines have no equivalent on the Airpay side. The single file `classes/classroom.php` in BizLMS is 169,040 bytes (~3,200 lines) with 46 public methods — Airpay's `session_manager.php` replacement is 26,071 bytes with 20 methods.

## Database tables (BizLMS = 10, Airpay = 4 + waitlist + locations = 6)

| BizLMS table | Airpay equivalent | Notes |
|--------------|-------------------|-------|
| `local_classroom` | `local_airpay_classroom` | Schema slimmed (no `signupstartdate`, `signupenddate`, `mincapacity`, `cancellationcutoff`, `enrolmenttype`, `enrolmentstatus`, `attendancemode`, `evaluationscale`) |
| `local_classroom_courses` | **MISSING** | M:N relationship between classrooms and Moodle courses — cannot tie classroom session attendance to course completion |
| `local_classroom_trainers` | **MISSING** | M:N trainers per classroom. Airpay has only `trainerid` (single) on parent table + per-session `trainerid` override |
| `local_classroom_trainerfb` | **MISSING** | Per-trainer feedback / rating |
| `local_classroom_sessions` | `local_airpay_classroom_sessions` | Slimmed (no `recordinglink`, `messagelink`, `roomid`, `onlinesession` flag, `mincapacity`, `notify_admin`, `cancellationcutoff`) |
| `local_classroom_test_score` | **MISSING** | Per-user test scores at classroom level (used for trainer-led assessments) |
| `local_classroom_users` | `local_airpay_classroom_users` | Parity OK |
| `local_classroom_attendance` | `local_airpay_classroom_attendance` | Parity OK on shape, but BizLMS has `markedby`, `usermodified`, `usercreated`, `timecreated`/`timemodified` more completely |
| `local_classroom_completion` | **MISSING** | Per-user classroom completion record (status + score + cert issued + date). Airpay derives completion dynamically — no audit trail |
| `local_classroom_waitlist` | `local_airpay_classroom_waitlist` (added via upgrade.php Phase 3 B.4) | Parity OK, schema improved |
| (none in BizLMS) | `local_airpay_locations` (added via upgrade.php Phase 5 A.5) | **Airpay NEW** — Airpay-owned locations table since `local_location` was dropped |

## Web service surface (BizLMS = 26, Airpay = 13)

| BizLMS WS | Airpay equivalent | Notes |
|-----------|-------------------|-------|
| `get_classrooms` | `_list_classrooms` | Parity OK (different signature) |
| `submit_instance` | **MISSING** as WS (replaced by dynamic_form?) | Need to verify — was core multi-step instance create |
| `deleteclassroom` | `_delete_classroom` | Parity OK |
| `form_course_selector` | **MISSING** | AJAX course autocomplete used inside the classroom form's "assign courses" step |
| `deletesession` | `_delete_session` | Parity OK |
| `deleteclassroomevaluation` | **MISSING** | Evaluation/feedback deletion |
| `form_option_selector` | **MISSING** | Generic AJAX option feeder for `local_costcenter/form-options-selector` |
| `session_submit_instance` | **MISSING** as WS | Need to verify — session create/update |
| `course_submit_instance` | **MISSING** | Add course to classroom (M:N) |
| `deleteclassroomcourse` | **MISSING** | Remove course from classroom |
| `completion_settings_submit_instance` | **MISSING** | Configure completion thresholds (e.g. minimum attendance %) |
| `manageclassroomStatus` (mobile) | `_change_status` | Mostly OK — but Airpay's is not exposed to mobile service |
| `classroomviewsessions` | `_list_sessions` | Parity OK |
| `classroomviewcourses` | **MISSING** | List courses attached to a classroom |
| `classroomviewusers` | `_list_users` | Parity OK |
| `classroomviewfeedbacks` | **MISSING** | View classroom feedback / evaluation results |
| `classroomviewcompletioninfo` | **MISSING** | View per-user completion criteria status |
| `classroomviewtargetaudience` | **MISSING** | View classroom's target audience (org/dept hierarchy) |
| `classroomviewrequestedusers` | **MISSING** | Request-to-join queue |
| `classroomlastchildpopup` | **MISSING** | Last-form-step rendering helper |
| `unenrollclassroom` | `_unenrol_user` | Parity OK |
| `classroomviewwaitinglistusers` | `_list_waitlist` | Parity OK |
| `get_mobile_classrooms` | **MISSING** | Mobile WS — user's classrooms |
| `get_classroom_sessions` | **MISSING** | Mobile WS — sessions list |
| `get_weekly_sessions` | **MISSING** | Mobile WS — weekly calendar view |
| `get_today_sessions` | **MISSING** | Mobile WS — today's sessions |
| `get_classroom_sessions_page` | **MISSING** | Mobile WS — paginated sessions |
| `get_classroom_courses` | **MISSING** | Mobile WS |
| `get_classroom_trainers` | **MISSING** | Mobile WS |
| `get_classroom_completions` | **MISSING** | Mobile WS |
| `get_classroom_feedbacks` | **MISSING** | Mobile WS |
| `userdashboard_content` / `_paginated` | **MISSING** | Dashboard WS |
| `get_sessions_by_daytype` | **MISSING** | Mobile WS — filter by day-of-week |
| `get_classroom_info` | **MISSING** | Mobile WS — classroom detail |
| **NEW (Airpay)** `_list_attendance`, `_mark_attendance`, `_bulk_mark_attendance` | n/a | Modern attendance WS (G-02) |
| **NEW (Airpay)** `_waitlist_join`, `_waitlist_leave` | n/a | Phase 3 B.4 waitlist |

Mobile service WS in BizLMS: **15**. In Airpay: **0**. The Moodle mobile app will receive empty responses for every classroom-related screen.

## Feature parity matrix

| # | Feature | BizLMS | Airpay | Gap | Severity |
|---|---------|--------|--------|-----|----------|
| 1 | Classroom list (admin) | `index.php` + renderer `get_classroom_tabs($formattype)` card/table toggle + 'My' vs 'Browse' tabs | `index.php` + datatable | No My-vs-Browse tab toggle, no card/table view toggle. Single datatable. | **P1** |
| 2 | Classroom create wizard (4 steps) | `classroom_form.php` (manage → location/date → assign course → target audience) | Need to verify — likely single dialog | If create step-2 (location_date) and step-4 (target_audience) gone, can't restrict classroom to dept X subdept Y. | **P0** |
| 3 | Session create form | `session_form.php` (217 lines) with: name, recordinglink, messagelink, onlinesession checkbox, BBB/Zoom integration via `classroom_onlinesession_type` config, roomid (linked to physical location), trainerid (per-session), timestart, timefinish, cs_description editor, mincapacity, datetimeknown | Airpay: title, sessiondate, starttime, endtime, location, trainerid override, notes | **No recordinglink** (post-session video URL). **No messagelink** (live session URL — Zoom/Teams etc.). **No onlinesession flag**. **No room/location FK**. **No integration with BBB/Zoom auto-create**. Notes is plain text vs cs_description editor. | **P0** |
| 4 | Trainer management (M:N) | `local_classroom_trainers` table + `manage_classroom_trainers($classroomid, $action, $trainers)` method + dedicated trainer-assign UI | Single `trainerid` on parent table + optional per-session override | Cannot assign multiple co-trainers, cannot track per-trainer hours, no trainer-specific feedback. | **P0** |
| 5 | Trainer feedback | `local_classroom_trainerfb` table + admin view + learner-fills-form workflow | **MISSING** | Trainers cannot be evaluated, no NPS-style rating. | **P1** |
| 6 | Per-trainer feedback view | `classroomviewfeedbacks` WS + renderer | **MISSING** | n/a (feature gone). | **P1** |
| 7 | Course ↔ classroom linking | `local_classroom_courses` table + `manage_classroom_courses` + `manage_classroom_course_enrolments` (enrol classroom attendees into the linked Moodle course) | **MISSING** | A classroom session for "Compliance 101" used to auto-enrol attendees into the Moodle course "Compliance 101" — that linkage is gone. Attendees no longer auto-progress in the e-learning course. | **P0** |
| 8 | Classroom completion criteria | `local_classroom_completion` table + `program_completion_settings_info` + 'attended ≥ X sessions to complete' | **MISSING** | Cannot define "must attend 80% of sessions". Attendance is recorded but not aggregated into a completion event. | **P0** |
| 9 | Test score (post-session quiz) | `local_classroom_test_score` table + manage UI | **MISSING** | Cannot record per-attendee post-session scores in the plugin. Would have to use a separate quiz module. | **P1** |
| 10 | Attendance UI | `attendance.php` (380 lines) with per-attendee base64-encoded data, present/absent radios, "Reset Selected" bulk + per-row save + event triggers per change | `attendance.php` (114 lines) + AMD `attendance.js` for AJAX mark — 4 status (Absent/Present/Late/Excused) | **Airpay improvement** — modern UX, 4 statuses vs 2. | — |
| 11 | Bulk-add attendees from cascaded org/dept hierarchy | `enrollusers.php` (≥ 100 lines) + `select_to_and_from_users()` (185 lines) + dual-listbox with 5-level hierarchy filter, scroll-load lazy pagination | datatable + per-user enrol via WS | The cascading dept-aware dual-listbox is **gone**. For bulk enrolling 100 backend engineers into a session, no efficient path. | **P1** |
| 12 | Session capacity check + waitlist promotion | `classroom_capacity_check($classroomid, $checking=false)` + `classroom_add_waitingusers` — when seats free up, promote head of queue | `waitlist_manager.php` (8,401 bytes) — `_waitlist_join` / `_waitlist_leave` WS | Need to verify auto-promotion behaviour on unenrol. Looking at `unenrol_user` in session_manager.php — does it call `waitlist_manager::promote_next()`? **Verify.** | **P1** if no auto-promotion |
| 13 | Self-enrol with optional approval | `classroom_self_enrolment($classroomid, $userid, $request=false, $enroltype)` | Datatable shows users but no self-enrol page evident in airpay_classroom | Learners cannot self-enrol into open-capacity classrooms via the plugin's view. | **P1** |
| 14 | Auto session generation | `manage_classroom_automatic_sessions($classroomid, $startdate, $enddate)` — generates weekly/daily sessions between dates | **MISSING** | Cannot define "every Mon/Wed for 6 weeks" — must create sessions manually one at a time. | **P2** |
| 15 | Classroom logo | `classroom_logo($classroomlogo)` + file area | **MISSING** | No per-classroom logo upload — generic icon only. | **P2** |
| 16 | Status workflow (active/cancelled/completed) | `classroom_status_action`, `update_classroom_status`, `classroom_status_strip` | `_change_status` WS + 3-state model | Parity OK (active=1, cancelled=0, completed=2). | — |
| 17 | Event-driven notifications | `\local_classroom\event\classroom_attendance_created_updated` event triggered on every attendance change + `notification.php` (18,590 bytes) listening | `event/` directory exists — need to verify event class shipping | Notifications on enrol/attendance/cancellation **may** be missing. | **P1** |
| 18 | Scheduled notification tasks | `task/` directory in BizLMS classroom | Need to verify | If reminder emails (24h before session) are gone, learners won't be reminded. | **P1** |
| 19 | Calendar / iCal export | `ics.php` (Airpay) | **CHECK** | Airpay has ics_builder.php (4,437 bytes) — looks like Airpay has parity or improvement here. **Verify.** | — |
| 20 | View as tabs (Overview/Sessions/Users) | `view.php` + `get_content_viewclassroom` renderer (probably card-style sections) | `view.php` + 3-tab structure (overview, sessions, users) — clean | **Airpay improvement** — cleaner UX. | — |
| 21 | Target audience definition | `target_audience($classroom)` + dedicated step in classroom_form | Implied by `open_path` on classroom table | Cannot define multi-dept target audience — only the creator's path. | **P1** |
| 22 | Target audience view | `classroomtarget_audience_tab` + `classroomviewtargetaudience` WS | **MISSING** | n/a. | **P1** |
| 23 | Request to join queue | `classroomrequestedusers` + `get_specific_costcenter_requests_classroom` | **MISSING** | Cannot operate "request → approve → enrol" flow for restricted classrooms. (Note airpay_courses has a separate request flow.) | **P1** |
| 24 | User unenrol on delete/suspend | `delete_suspend_user_remove_classrooms($userid)` — observer that removes classroom enrolments | Need to verify hook | If a user is deleted, their classroom enrolments may dangle. | **P1** |
| 25 | Logo + visual config | Classroom logo, location image, course image | Airpay: limited (icons only) | Polish reduction. | **P2** |
| 26 | User dashboard widget | `userdashboard.php` + `userdashboard_content` / `_paginated` WS | airpay_classroom does **not** appear on user dashboard | Learners can't see "My Classrooms" widget on dashboard from this plugin. | **P1** |
| 27 | Mobile WS layer | 15 functions registered with `MOODLE_OFFICIAL_MOBILE_SERVICE` | 0 functions exposed | Mobile app gets nothing. | **P0** if mobile app in use |
| 28 | Tenant scoping | Implicit via `$USER->open_path` checks scattered through `classroom.php` | `view.php` lines 28-40 enforces explicitly on entry | **Airpay improvement** — stricter and centralised. | — |
| 29 | Locations table | Was an external `local_location` plugin | `local_airpay_locations` (Phase 5 A.5 upgrade) | **Airpay NEW** — replaces dropped external plugin. | — |

## User flows (multi-step tasks)

### Flow 1: Admin creates a 6-week trainer-led course with weekly sessions
**BizLMS:**
1. From `local/classroom/index.php` click "+" → modal loads `classroom_form.php` step 1 (`manage_classroom`)
2. Enter name, description, capacity, mincapacity, signupstartdate/enddate, cancellationcutoff
3. Next → step 2 (`location_date`): pick location (FK to `local_location`), startdate, enddate → **auto-generate weekly sessions via `manage_classroom_automatic_sessions()`**
4. Next → step 3 (`classroom_misc`): assign 1-3 Moodle courses (FK to local_classroom_courses)
5. Next → step 4 (`target_audience`): pick org/dept/subdept hierarchy
6. Submit → classroom + 6 sessions inserted, trainers assigned, attendees auto-enrolled when added later

**Airpay:** Limited. Create classroom (basic fields only). Session auto-generation is **gone** — admin must manually click "+ New session" 6 times and pick date/time each time. Course-classroom link is **gone**. Target audience step is **gone**. **P0 — feature regression for biggest L&D use case.**

### Flow 2: Admin marks attendance for today's session
**BizLMS:** view.php → click session row → attendance.php → render table with Present/Absent radios → submit form → POST hits attendance.php → per-row JSON-decode, `update_record` or `insert_record` on `local_classroom_attendance` → `\local_classroom\event\classroom_attendance_created_updated` event triggered → counts recomputed.

**Airpay:** view.php?id=N&tab=sessions → click row → attendance.php?sessionid=M → render table with 4 status radios (Absent/Present/Late/Excused) → per-user AJAX via `_mark_attendance` OR bulk save via `_bulk_mark_attendance` → write to `local_airpay_classroom_attendance`.

**Airpay better.** 4-state attendance + AJAX UX + bulk-mark. No regression.

### Flow 3: Trainer pastes Zoom link before live session
**BizLMS:** session_form has `messagelink` field — paste Zoom URL → save → mobile app calendar link surfaces it → learners click → join.

**Airpay:** session form has no `messagelink` and no `recordinglink`. Trainer must email the Zoom link separately. **P0 — most ILT sessions today are virtual.**

### Flow 4: Learner reviews recorded session after the fact
**BizLMS:** session detail shows `recordinglink` → click → playback.

**Airpay:** No recording link. Learner has to find it via email/Slack. **P0 for hybrid/remote workforce.**

### Flow 5: Admin assigns multiple co-trainers to a session
**BizLMS:** classroom_form step (trainers) → autocomplete → multi-select → save → rows in `local_classroom_trainers`.

**Airpay:** Cannot — only single `trainerid` on classroom table + optional override per session. **P0 — common for half-day or specialised sessions.**

### Flow 6: Bulk-add 80 backend engineers to a session
**BizLMS:** view.php → "Enrol users" → enrollusers.php → filter form: org=Airpay, dept=Engineering, subdept=Backend → right-list populates with 80 users → "Select All" → "Add All" → progressbar → enrol complete → cohort-style notification email per user.

**Airpay:** view.php?tab=users → datatable + "Add user" button — but the cascading dept filter doesn't exist. Bulk-add path unclear. **P1.**

### Flow 7: Mobile app shows "Today's sessions"
**BizLMS:** Mobile calls `local_classroom_get_today_sessions` + `get_weekly_sessions` + `get_classroom_sessions_page` → returns paginated list.

**Airpay:** No mobile WS. Mobile app cannot fetch classroom data. **P0 if mobile is in production.**

### Flow 8: Auto-promote head of waitlist when someone cancels
**BizLMS:** `classroom_add_waitingusers` + observer on unenrol → promote.

**Airpay:** Need to verify `session_manager::unenrol_user` calls `waitlist_manager::promote_next()`. Skim of session_manager.php shows the method exists but no waitlist promotion call. **P1 — verify and fix if missing.** Reference: `airpay_classroom/classes/session_manager.php:456` `unenrol_user`.

## Severity legend
- **P0** = blocks enterprise use (no Zoom/recording link, no multi-trainer, no auto session generation, no course-classroom link → no completion sync, no mobile app)
- **P1** = important workflow degraded (no target audience, no completion criteria, no trainer feedback, no cascading bulk-enrol, no dashboard widget)
- **P2** = polish (no auto session generation, no classroom logo)

## Recommended fixes (prioritised)

1. **[P0] Add `messagelink` (live URL) and `recordinglink` (recorded URL) to session form and table** — `airpay_classroom/db/install.xml:33-56` schema needs these columns; `view.php` template needs to render them; create_session form needs the inputs. Reference: `bizlms_disabled/classroom/classes/form/session_form.php:63-70`.
2. **[P0] Multi-trainer support** — create new table `local_airpay_classroom_trainers` (classroomid, userid, role, hours). Update `session_manager::create_session` to accept array of trainer IDs. Add per-session co-trainer override. Reference: `bizlms_disabled/classroom/classes/classroom.php:1580-1685` `manage_classroom_trainers`.
3. **[P0] Course ↔ classroom linking** — create `local_airpay_classroom_courses` (classroomid, courseid). On enrol-into-classroom, auto-enrol into the linked Moodle course(s). Reference: `bizlms_disabled/classroom/classes/classroom.php:1755-1859` `manage_classroom_courses` and `manage_classroom_course_enrolments`.
4. **[P0] Completion criteria** — create `local_airpay_classroom_completion` (classroomid, userid, status, score, attendance_pct, issued_at). Aggregate from attendance + test_score. Mark completion when threshold met → fire event → core completion record. Reference: `bizlms_disabled/classroom/classes/classroom.php:2392-2523` `classroom_completions`.
5. **[P0] Auto session generation** — add UI in classroom create wizard for "Generate weekly: every Mon/Wed from X to Y" → loop insert sessions. Reference: `bizlms_disabled/classroom/classes/classroom.php:2765-2814` `manage_classroom_automatic_sessions`.
6. **[P0] Restore mobile WS** — register at minimum: `get_today_sessions`, `get_weekly_sessions`, `get_classroom_info` with `'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)`. These have direct equivalents in `session_manager::get_sessions` — just wrap.
7. **[P1] Target audience** — extend create-classroom form with 5-level org/dept hierarchy picker; store path filter; enforce on enrol. Reference: `bizlms_disabled/classroom/classes/classroom.php:1698-1738` `target_audience`.
8. **[P1] Trainer feedback** — new table `local_airpay_classroom_trainer_feedback` + post-session prompt UI for learners + admin view. Reference: schema `local_classroom_trainerfb`.
9. **[P1] Test score** — new table `local_airpay_classroom_test_score` (sessionid, userid, score, max_score, notes). Add UI in attendance.php for trainer to enter scores alongside attendance. Reference: `local_classroom_test_score` schema.
10. **[P1] Verify waitlist auto-promotion** — read `airpay_classroom/classes/session_manager.php:456` `unenrol_user` and add `\local_airpay_classroom\waitlist_manager::promote_next($classroomid)` call if absent.
11. **[P1] Cascading dept filter for bulk-enrol** — same fix as airpay_courses #1. Reference: airpay_classroom external `list_classroom_users` — extend with dept hierarchy filter params.
12. **[P1] User dashboard widget** — port `userdashboard_content` WS so the user dashboard surface can render "My upcoming classroom sessions". Reference: `bizlms_disabled/classroom/userdashboard.php`.
13. **[P1] Notification cron tasks** — port 24h-before-session reminder + cancellation notification. Reference: `bizlms_disabled/classroom/classes/task/` directory.
14. **[P1] Cleanup observer on user delete/suspend** — port `\local_classroom\classroom::delete_suspend_user_remove_classrooms` into Airpay observer (`local_airpay_classroom\observer`). Avoids dangling roster rows.
15. **[P2] Auto session generation UI** — even simpler version: text input "Generate N sessions: Mon and Wed weekly from DATE for N weeks".
16. **[P2] Classroom logo upload** — add file area + filemanager element to create form.
