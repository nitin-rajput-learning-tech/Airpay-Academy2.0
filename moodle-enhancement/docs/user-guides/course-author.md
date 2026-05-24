# Course Author user guide — Sentientia LMS / Airpay Academy

**Audience:** Course Author (can create + edit + publish their assigned courses across one or more tenants).
**Status:** v1 draft (2026-05-24).
**Cross-references:** `tenant-admin.md`, `learner.md`, `site-admin.md`.

---

## 1. Creating a course

`/course/edit.php?category=<your-category>` opens the standard Moodle
course-edit form with Sentientia additions:

### Required fields

- **Full name** — shown in catalogue + breadcrumb.
- **Short name** — unique identifier; used in URLs + reports.
- **Category** — pick your tenant's category tree.
- **Course visibility** — start as "Hidden" until you've completed
  Sections 2-7 below; only flip to "Visible" before publishing.
- **Course start date** — affects "My Courses" sort + analytics windowing.
- **Course end date** — drives `local_airpay_courses` deadline-reminder cron.

### Sentientia-specific fields

- **Cohort filter** — restrict enrolment to specific cohorts.
- **Target audience** — designations, roles, departments. Combine with
  cohort for fine-grained access.
- **Completion days** — `local_airpay_courses` deadline cron uses this
  to send reminders N/3, 2N/3, and N days after enrolment.

---

## 2. Adding activities

Sentientia supports the standard Moodle activity types PLUS Airpay-specific:

| Activity | Module | When to use |
|----------|--------|-------------|
| SCORM package | `mod_scorm` | Auto-generated content from SOP → SCORM pipeline; vendor-supplied courses |
| Quiz | `mod_quiz` | Multi-question assessments with grading |
| Feedback | `mod_feedback` | Post-course surveys (anonymous OK) |
| Evaluation | `local_airpay_evaluation` | Structured assessments with conditional questions, multi-template library, auto-assign |
| Exam | `local_airpay_exams` | Time-boxed, often proctored (see `quizaccess_airpay_proctoring`) |
| Resource files | `mod_resource` | PDFs, slides, videos |
| URL | `mod_url` | External links (videos, articles) |
| Assignment | `mod_assign` | File submissions + grading rubric |

For each activity, set:

- **Completion criteria** — must complete by viewing, or by score threshold, or by submission
- **Grade weighting** — only matters if course has an aggregated grade
- **Restrict access** — chain on previous activity completion if you want a linear flow

---

## 3. Target audiences + cohort filtering

Use target audiences when you want a course visible to specific people
without manually enrolling each one.

### Cohort attach

`Course → Users → Enrolment methods → Add method → Cohort sync`. Pick
the cohort + role (usually "Student"). Every current + future cohort
member is auto-enrolled.

### Target audience editor

`/local/airpay_courses/audience.php?courseid=<id>` — pick designations,
departments, manager hierarchy. The "Bulk-enrol" button then enrols
everyone matching the criteria. Useful for ad-hoc one-time pushes.

---

## 4. Setting completion criteria

`Course settings → Completion tracking`. Two patterns:

### Activity completion
Each activity has its own "Completion conditions" — student must view,
or submit, or hit a grade. The course completes when ALL or N activities
complete (your call).

### Course completion via grade
Set "Completion based on" → "Course grade ≥ X". Then activities feed
into the course gradebook (use weighted aggregate or simple weighted
mean).

---

## 5. Deadline reminders

The `local_airpay_courses` plugin includes two crons:

| Cron | Frequency | Recipients | Trigger |
|------|-----------|------------|---------|
| Learner reminder | daily | Learner | N/3 days, 2N/3 days, N days from enrolment where N = course completion days |
| Manager escalation | daily | Manager (line-manager from HRMS sync) | When learner crosses deadline without completion |

You don't have to configure these — they fire automatically if the
course has `completion days` set + the learner has a manager populated
in their profile.

---

## 6. Grade reports

| Report | URL | Notes |
|--------|-----|-------|
| Grader report | `/grade/report/grader/index.php?id=<courseid>` | Per-learner grid; supports inline editing |
| Course overview | `/grade/report/overview/index.php?id=<courseid>` | Summary stats for ALL your courses |
| Single view | `/grade/report/singleview/index.php?id=<courseid>` | Drill into one student × one grade item |
| User report | `/grade/report/user/index.php?id=<courseid>&userid=<uid>` | One student × all items |

Hindi UI: the grader supports per-learner Hindi rendering automatically.

---

## 7. Publishing + tenant visibility

When everything's ready:

1. Course settings → set Visibility to "Show"
2. Verify your cohort/audience filters are correct
3. Catalogue presence — `/local/airpay_catalog/mycourses.php` should
   show your course to the target audience
4. Smoke-test as a Learner — log in as a test user in the target audience
   (or use the "Switch role to" → "Student" admin feature)
5. Once stable, announce via WhatsApp / email blast (Tenant Admin or
   Manager can do this)

---

## 8. Hindi content readiness checklist

If your tenant has Hindi users (anyone with `lang=hi` in their profile):

- [ ] Course full name + summary translated to Hindi (use the language-pack-aware multi-lang filter `{mlang}`)
- [ ] All activity titles translated
- [ ] SCORM content has Hindi audio + slide track (if applicable)
- [ ] PDFs/resources have Hindi versions linked
- [ ] Quiz/evaluation questions have Hindi versions
- [ ] Manager-escalation email template translated (look in
      `local_airpay_courses/lang/hi/`)

`/local/airpay_courses/hindi_audit.php?courseid=<id>` — automated audit
that flags missing translations.

---

## 9. References

- `learner.md` — what your students see
- `site-admin.md` §5 SCORM upload + validation
- `tenant-admin.md` §3 — bulk-enrol pattern

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
