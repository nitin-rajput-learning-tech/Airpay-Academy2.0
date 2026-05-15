# airpay_evaluation vs BizLMS local_evaluation — Parity Audit

**Audit date:** 2026-05-15
**Auditor:** Claude (Opus 4.7, 1M)
**Verdict:** **MIXED — Airpay deliberately reduced surface but kept all the load-bearing flows.** Airpay covers the L&D-specific 80% (Kirkpatrick, 5 question types, anonymous toggle, template I/O, course/program/classroom context). BizLMS shipped the full Moodle Feedback-module fork with 9 question types, conditional branching, sitewide templates, course/site mapping, and a public-template library — most of which were never used in Airpay practice. Three P0s, none catastrophic.

---

## Source paths + size

- **BizLMS**: `C:\xampp\htdocs\moodle5\bizlms_disabled\evaluation\` — **83 PHP files, 19,635 LOC**
  - This is a near-verbatim fork of Moodle's `mod_feedback` activity, re-namespaced as a `local_` plugin and bolted onto BizLMS hierarchy
  - Entry points: `index.php` (234), `edit.php` (172), `complete.php` (199), `analysis.php` (106), `eval_view.php` (188), `show_entries.php` (215), `show_nonrespondents.php` (264), `users_assign.php` (453), `delete_template.php` (90), `use_templ.php`, `export.php` (165), `import.php` (289), `analysis_to_excel.php`, `reportajax.php` (50), `ajax.php` (59), `renderer.php` (441)
  - Library: `lib.php` (3,259 — gigantic procedural API), `evaluation_form.php`, `edit_form.php`, `import_form.php`
  - Question type plugins (each is its own mini-module): `item/captcha` (385), `item/info` (347), `item/label` (300), `item/multichoice` (637), `item/multichoicerated` (680), `item/numeric` (423), `item/textarea` (293), `item/textfield` (293) — **9 question types**
  - Classes: `evaluation.php`, `structure.php`, `completion.php`, `complete_form.php`, `templates_table.php`, `responses_table.php`, `responses_anon_table.php`, `notification.php`, `observer.php`, `external.php`, `event/*`, `output/*`, `search/*`, `lib/*`, `local/*`, `task/*`, `templates/*`
  - Templates: 12 mustache (dashboard_innercontent, evaluations_catalog_list, evaluations_list, evalview, summary, tagview, userdashboard_*[7])
  - Languages: en + multi-locale
  - 7 DB tables: `local_evaluations`, `local_evaluation_template`, `local_evaluation_item`, `local_evaluation_completed`, `local_eval_completedtmp`, `local_evaluation_users`, `local_evaluation_value`, `local_eval_valuetmp`, `local_eval_sitecourse_map`

- **Airpay**: `C:\xampp\htdocs\moodle5\public\local\airpay_evaluation\` — **31 PHP files, 4,013 LOC** (≈20% of BizLMS)
  - Entry points: `index.php` (89), `questions.php` (89), `respond.php` (115), `analysis.php` (112), `responses.php` (190), `response_list.php` (74), `response_detail.php` (151), `export_template.php` (32), `import_template.php` (81), `exportcsv.php` (90)
  - Library: `classes/evaluation_manager.php` (1,046) — clean OO API
  - Forms: `classes/form/edit_evaluation.php`, `classes/form/edit_question.php`, `classes/form/import_template_form.php`
  - External services
  - Templates: 7 mustache (analysis, manage, questions, respond, response_detail, response_list, responses)
  - 5 question types (rating, nps, yesno, multichoice, text) — explicitly **Kirkpatrick-aware**
  - 3 DB tables: `local_airpay_evaluation`, `local_airpay_evaluation_questions`, `local_airpay_evaluation_responses`
  - 2 CLI smoke tests + PHPUnit tests

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| 1 | **Question type — Rating (Likert)** | "rated multichoice" + "radio" with custom 1-5 / 1-10 scales (`item/multichoicerated/lib.php`, 680 LOC) | Single fixed 5-point rating type, 1=Strongly Disagree → 5=Strongly Agree | Airpay is more opinionated but simpler. Scale not configurable | **P2** |
| 2 | **Question type — Free text** | `textfield` (single-line) + `textarea` (multi-line) — two distinct types with own validation | Single `text` type | Cannot enforce single-line vs paragraph | **P2** |
| 3 | **Question type — Multiple choice** | `multichoice` (637 LOC) with single+multiple-select, options, hide-no-select option | `multichoice` single-select only, 1-line option entry, no multi-select | Multi-select questions ("check all that apply") **lost** | **P1** |
| 4 | **Question type — NPS (0-10)** | Not built-in; emulated via multichoicerated 0-10 scale | First-class `nps` type with promoter/passive/detractor calculation | Airpay is **better** here | none |
| 5 | **Question type — Yes/No** | Not built-in (had to use multichoice 2-option) | First-class `yesno` type | Airpay is **better** here | none |
| 6 | **Question type — Numeric** | `numeric` (423 LOC) with min/max validation | Not present | Cannot collect age, salary band, # employees, etc. as native int question | **P1** |
| 7 | **Question type — Info display** | `info` type (347 LOC) inserts read-only timestamp/username/courseid metadata into the form | Not present | Can't auto-tag responses with context shown to learner | **P2** |
| 8 | **Question type — Label / Section header** | `label` type (300 LOC) — non-input visual divider for grouping questions | Not present | Long evaluations cannot be sectioned visually | **P2** |
| 9 | **Question type — CAPTCHA** | `captcha` type (385 LOC) for anti-bot on public surveys | Not present | If evaluation goes public (anonymous=1), no spam protection | **P1** |
| 10 | **Conditional branching (dependitem/dependvalue)** | Each item has `dependitem` (parent question) + `dependvalue` — "show this question only if Q3 = Yes" (`item/evaluation_item_class.php`, schema `local_evaluation_item`) | Not present. All questions always shown | Cannot build adaptive evaluations | **P1** |
| 11 | **Reusable templates (system-wide)** | `local_evaluation_template` table with `ispublic` flag → admin saves form as template; others reuse via `use_templ.php` | Airpay supports **import/export JSON template** (1 evaluation → JSON file → re-import as new evaluation), but **no in-DB template library** | Admin must manually share JSON files. No "use existing template" picker in form builder | **P1** |
| 12 | **Multi-step form completion (resume later)** | `local_eval_completedtmp` + `local_eval_valuetmp` — partial responses saved between sessions | All-or-nothing single-page form | Long evaluation cannot be paused | **P2** |
| 13 | **Anonymous responses** | `local_evaluations.anonymous` flag → `userid=0` on `local_evaluation_completed` | `local_airpay_evaluation.anonymous` flag + per-question `anonymous` flag (G.2 — finer-grained) | **Airpay is better** — supports per-question anonymity | none |
| 14 | **Multiple submissions allowed** | `local_evaluations.multiple_submit` flag | Implicit: anonymous=1 allows resubmit (path: `has_user_responded` returns false for anon); for non-anon → blocked. **Cannot toggle independently** | "Pulse survey: same person, weekly" workflow lost | **P1** |
| 15 | **Time-bounded availability** | `timeopen` + `timeclose` columns; respondent gets "this evaluation has not started / has closed" error | Not present. Status (draft/active/archived) is the only gate | Can't schedule "active 2026-06-01 to 2026-06-30" | **P1** |
| 16 | **Course/Program/Classroom context binding** | `local_eval_sitecourse_map` — N evaluations can be attached to M courses; on course completion, evaluations fire | `local_airpay_evaluation_responses` stores `courseid + programid + classroomid` at submit-time; `evaluation_manager::TRIGGER_EVENTS` has `course_completion`, `program_completion`, `classroom_end` constants — **but trigger firing is not implemented (no observer)** | TRIGGER_EVENTS constants defined but **no observer enrols learners or sends them to the evaluation**. Evaluations only run if admin manually shares the link | **P0** |
| 17 | **Email notification to admin on response** | `email_notification` flag → admin gets summary on each submission; `receivemail` capability | Not present. No notification on response | Admin must poll `/responses.php` to see new feedback | **P1** |
| 18 | **Show-stats-to-respondent on submit (publish_stats)** | `publish_stats` flag — after submit, learner sees aggregate stats of all previous responses | Not present. Learner gets thank-you only | "See how your team voted" engagement feature lost | **P2** |
| 19 | **Post-submission redirect / page** | `site_after_submit` URL + `page_after_submit` HTML/text | Not present. Always redirects to a generic thanks page | Cannot drive learner to next-step CTA | **P2** |
| 20 | **Show-non-respondents view** | `show_nonrespondents.php` (264 LOC) — admin sees who was assigned but hasn't responded; bulk-remind button | Not present. Admin only sees who DID respond | "Chase the 18 people who haven't filled this out" workflow lost | **P1** |
| 21 | **User assignment / enrolment to evaluation** | `users_assign.php` (453 LOC) with target audience filter; `local_evaluation_users` table; admin assigns evaluation to specific users | Not present. Anyone with `view` cap can respond; admin doesn't pick who | "Assign evaluation only to certified-stage learners" lost | **P1** |
| 22 | **Trainer-evaluation mode vs trainee-evaluation mode** | `evaluation.evaluatedby` column + `evaluationmode` ('SE' self / 'TE' trainer) → admin chooses who evaluates whom | Not present. Always learner-self-fills | Cannot run "trainer rates each learner" forms | **P2** |
| 23 | **Random response ordering** | `random_response` column — randomizes question order per learner to mitigate primacy bias | Not present. Fixed sortorder | Survey bias possible | **P2** |
| 24 | **Auto-numbering of questions** | `autonumbering` flag → questions display as "1. ...", "2. ..." in respond page | Manual — handled by template loop | Trivial difference | **P2** |
| 25 | **Multilingual (en/es/hi)** | en + es + hi | en only | Hindi UI gone | **P2** |
| 26 | **Capabilities (granularity)** | 19 capabilities (addinstance, view, complete, viewanalysepage, deletesubmissions, edititems, createprivatetemplate, createpublictemplate, deletetemplate, viewreports, receivemail, ownevaluations, allevaluations, alltemplates, enroll_users, delete, manage_multiorganizations, manage_ownorganization, manage_owndepartments, evaluationmode, create_update_question) | 4 capabilities (manage, view, respond, viewresponses) | Sub-permission granularity for partner managers lost | **P2** |
| 27 | **Tags** | `tagview.mustache` + tag integration | Not present | Tag-based discovery lost | **P2** |
| 28 | **Search integration** | `classes/search/` indexes evaluation names + intros into Moodle global search | Not present. Evaluations not searchable | "Find evaluation X" via global search lost | **P2** |
| 29 | **Kirkpatrick evaluation level (L1-L4)** | Not present | First-class field on form (1=Reaction, 2=Learning, 3=Behaviour, 4=Results) | Airpay is **better** — aligns to L&D theory | none |
| 30 | **Trigger event metadata** | Implicit via sitecourse_map | Explicit `trigger_event` column + `days_after` (e.g., "fire 30 days after course completion") | Airpay is **better** but not wired (#16 above) | none |
| 31 | **Response analysis (per-question aggregates)** | `analysis.php` (106) + `analysis_to_excel.php` for Excel export with per-question pivot | `analysis.php` (112) — covers same surface for the 5 supported types, no Excel (CSV only via `exportcsv.php`) | Format change (XLSX → CSV) is minor. **Airpay is OK** | **P2** |
| 32 | **Per-tenant scoping (open_path)** | `local_evaluations.open_path` + audience filters (designation, group, states/district/subdistrict/village — `db/install.xml:36-42`) | `local_airpay_evaluation.open_path + costcenterid` only | Geographic / designation filters lost | **P2** |
| 33 | **Template import/export (JSON)** | Not built-in (had Excel-based "import" via `import.php`) | First-class JSON template format with versioning (`evaluation_manager.php:212-333`) | Airpay is **better** | none |
| 34 | **Form intro / description editor (rich-text)** | `intro` + `introformat` rich-text fields | `description` plain text | Rich onboarding text lost | **P2** |
| 35 | **Capability — anonymise after submission** | `evaluation:deletesubmissions` allowed admin to anonymise specific responses for GDPR | Privacy provider exists (`classes/privacy/provider.php`), but no in-UI "anonymise this response" button | Right-to-be-forgotten requires CLI / SQL | **P2** |

---

## User flows (multi-step tasks) — works/broken trace

### Flow 1: Admin creates a multi-section evaluation with branching
**BizLMS:**
1. Click "+ Create evaluation" → name + intro + anonymous toggle + course/program target.
2. Open edit screen → click "+ add question" → pick from 9 types (label/info/multichoice/multichoicerated/numeric/textarea/textfield/captcha).
3. For multichoice / multichoicerated: enter options as `|`-separated; set `hidenoselect` toggle; for multichoicerated → assign weights for averaging.
4. Drag to reorder; set `required` + `dependitem` (Q2 only shows if Q1='Yes').
5. Optionally clone from a saved template via "use template".
6. Save.

**Airpay:**
1. Create → name + Kirkpatrick level + trigger_event + anonymous + costcenter.
2. Add question → pick from 5 types (rating/nps/yesno/multichoice/text).
3. Multichoice → one option per line.
4. Drag-reorder via `path_actions`-style AMD (questions.php).
5. Save.

**Result:** Steps 1, 2-flat, 4 work. Step 3 multi-select **gone**. Step 4 conditional logic **gone**. Step 5 template-picker **gone** (JSON re-import only). **DEGRADED — P1**

### Flow 2: Learner completes an evaluation embedded after a course
**BizLMS:** Learner finishes course → `local_eval_sitecourse_map` triggers evaluation appearing on their dashboard → click → multi-step form (Save & Continue) → submit → optional "see how others rated" stats screen.

**Airpay:** Learner finishes course → **nothing triggers**. Admin must manually send `respond.php?id=N` link. Learner submits → generic thank-you.

**Result:** **BROKEN end-to-end — the evaluation flow is missing the "fire on completion" mechanism. P0** — feature shipped half-built.

### Flow 3: Admin reviews response analytics
**BizLMS:** `analysis.php` shows per-question pivot table + average + chart placeholders; export to Excel; filter by date range, course, user.

**Airpay:** `analysis.php` shows per-question stats (rating avg + distribution, NPS score, multichoice distribution, yes-pct, text samples), supports date/course/program/classroom filter set (`build_response_filter` in evaluation_manager.php:728-759). CSV export.

**Result:** Roughly **parity, Airpay simpler but adequate.** Excel-vs-CSV is minor. **PARITY OK.**

### Flow 4: Admin chases non-respondents
**BizLMS:** `show_nonrespondents.php` → list of assigned users without a `completed` row → bulk "Send reminder" button.

**Airpay:** No equivalent. Admin has no view of "who hasn't responded".

**Result:** **DEGRADED — P1.** Especially painful for compliance evaluations.

### Flow 5: Admin assigns evaluation to a target audience (e.g., "all West-region branch managers")
**BizLMS:** `users_assign.php` → hierarchy + designation + state/district filters → bulk insert into `local_evaluation_users`.

**Airpay:** No assignment surface. Evaluation is "open" — anyone with `view` cap can respond.

**Result:** **DEGRADED — P1.** Targeted distribution gone.

### Flow 6: Template reuse across tenants
**BizLMS:** Save evaluation as template → publish public-template flag → other tenants pick from dropdown.

**Airpay:** Admin clicks export → downloads JSON → emails to other tenant → other admin uploads via `import_template.php`. Manual.

**Result:** **DEGRADED — P1** (workaround OK).

### Flow 7: Time-bounded survey
**BizLMS:** Set `timeopen + timeclose` → respondent gets "closed" message outside window.

**Airpay:** Admin must manually flip status active → archived at the right times.

**Result:** **DEGRADED — P1.** Compliance "30 days post-course" window cannot be programmatic.

---

## Severity legend
- **P0** = blocks enterprise use
- **P1** = important workflow degraded but a manual workaround exists
- **P2** = polish / ergonomics

---

## Recommended fixes (prioritised)

### Wave 1 — **P0 unblockers (this week)**

1. **[P0] Wire trigger events to actually fire** — the `TRIGGER_EVENTS` constants are declared in `evaluation_manager.php:36-42` but nothing observes them.
   - **Start at:** create `classes/observer.php` with handlers for `\core\event\course_completed` + `\local_airpay_programs\event\program_completed` + `\local_airpay_classroom\event\session_ended` events.
   - Each handler: find evaluations with matching `trigger_event` whose `days_after` either ==0 (immediate) or schedule via `task` plugin (`classes/task/send_delayed_evaluations.php`).
   - Email link to learner + record assignment in a new table `local_airpay_evaluation_assignments (id, evaluationid, userid, trigger_event, due_at, status, timecreated)`.
   - **Reference pattern:** `bizlms_disabled/evaluation/classes/observer.php` + `bizlms_disabled/evaluation/db/events.php`.
   - Estimate: 2 days.

### Wave 2 — **P1 (next week)**

2. **[P1] Add "multi-select multichoice" question type.** Schema already supports it — just add to QUESTION_TYPES at `evaluation_manager.php:191-197` and update `validate_answer` (line 543) to accept `array<string>` for the new type.
3. **[P1] Add `numeric` question type** with min/max validation; pattern from `bizlms_disabled/evaluation/item/numeric/lib.php`.
4. **[P1] Add CAPTCHA on anonymous evaluations** — Moodle has `\core\captcha\hcaptcha`; integrate.
5. **[P1] Add show-non-respondents view** — for evaluations with an `assignments` table (from #1 above), `assigned-not-responded.php` + reminder email button.
6. **[P1] Add `timeopen / timeclose`** schema fields + validation in `submit_response`. Schema upgrade in `db/upgrade.php`.
7. **[P1] Add `multiple_submit`** column to override the "one submission per user" rule for pulse-style surveys.
8. **[P1] Email-on-response notification** for admin.
9. **[P1] Conditional question display** (`depends_on_qid`, `depends_on_value` columns); UI in question editor.
10. **[P1] Reusable template library** — convert JSON template format into a DB table `local_airpay_evaluation_templates` with a "Use template" dropdown in `index.php`.
11. **[P1] Target-audience assignment screen** — copy `users_assign.php` (453) but cut down: just costcenter + designation + cohort.

### Wave 3 — **P2 (ongoing)**

12. **[P2] Configurable rating scale** (1-5 / 1-7 / 1-10) — add `scale_min, scale_max` columns to questions.
13. **[P2] `textfield` vs `textarea`** — split into two types with single-line vs paragraph rendering.
14. **[P2] `label` (section header)** type — non-input visual divider.
15. **[P2] `info` (metadata stamp)** type — auto-tag response with course/user/time at form-render.
16. **[P2] Resume-later partial responses** — add `local_airpay_evaluation_partial` table for in-progress answers.
17. **[P2] `random_response` order** flag.
18. **[P2] Post-submit redirect URL + thank-you page editor**.
19. **[P2] Trainer-evaluation mode** (someone-else-rates-the-learner).
20. **[P2] Search integration** — index evaluation names + descriptions for Moodle global search.
21. **[P2] Rich-text description editor**.
22. **[P2] Per-response GDPR delete/anonymise UI button**.
23. **[P2] Hindi + Spanish lang packs**.
24. **[P2] Capability granularity** — split `manage` into `create/edit/delete/assign/template_admin`.

---

## Risk callouts

1. **`trigger_event` is a half-built feature.** The constants are documented in `evaluation_manager.php:36-42`, the column exists in install.xml, the UI exposes the dropdown, but **no code observes any event**. From a learner's POV, evaluations never appear automatically. Compliance teams expecting "fire 7 days after course completion" will not see anything.
2. **Anonymous evaluations have no CAPTCHA.** If admin marks an evaluation as anonymous AND makes the link publicly accessible (or shares it externally), it's open to spam. Mitigation: keep `anonymous=1` evaluations behind `require_login()` only.
3. **No "who was assigned" model.** Without #1 above, there's also no `evaluation_assignments` table to know **who SHOULD respond**. This means: cannot compute response rate. The numerator (responses) is recorded; the denominator (eligible respondents) is unknown.
4. **`days_after = 0` is the only safe setting today.** Until #1 lands, any value of `days_after` is decorative.

---

## Files most likely touched during fixes

- `classes/evaluation_manager.php` — add `submit_response_validated`, `assign_users`, `mark_assigned_completed`
- **New:** `classes/observer.php`, `db/events.php` (event listeners)
- **New:** `classes/task/send_delayed_evaluations.php` (ad-hoc cron)
- **New:** `db/install.xml` table `local_airpay_evaluation_assignments`
- **New:** `db/upgrade.php` for `timeopen/timeclose/multiple_submit` columns
- **New:** `db/messages.php` + lang strings for invite + reminder + admin-on-response
- `classes/form/edit_question.php` — add `numeric` type, `multichoice_multi` type, `depends_on_*` fields
- `templates/respond.mustache` — add CAPTCHA element for anonymous
- **New:** `non_respondents.php` + `templates/non_respondents.mustache`
- **New:** `assign_users.php` + `templates/assign_users.mustache`
