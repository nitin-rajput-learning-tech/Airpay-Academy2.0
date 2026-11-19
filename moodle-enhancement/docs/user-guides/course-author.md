# Course Author user guide — Sentientia LMS / Airpay Academy

**Audience:** Course Author (can create + edit + publish their assigned courses across one or more tenants).
**Status:** v1 draft (2026-05-24), superseded by [`course-author-guide.md`](course-author-guide.md).
Course fields corrected 2026-11-19 (post-demo plan 2.4): §1, §3, §5 and §8 used to list fields and pages that
do not exist. The full, current field list is in `course-author-guide.md` §5.
**Cross-references:** `tenant-admin.md`, `learner.md`, `site-admin.md`.

---

## 1. Creating a course

Two routes:

- **Manage Courses** (`/local/sentientia_courses/index.php` → **New Course**), for
  holders of `local/sentientia_courses:create` (L&D and tenant admins). The pop-up
  has: course full name*, short name*, ID number, category*, organisation (tenant),
  description, course format, number of sections (new courses only), visible to
  learners, start date, end date (optional) and **Completion deadline (days from
  enrolment)**. * = required.
- **Moodle's course form** (`/course/edit.php?category=<your-category>`), for
  holders of `moodle/course:create` in that category. It is the standard Moodle
  form; Sentientia adds no fields to it.

Start with the course hidden until you have completed Sections 2-7 below.

There is **no** cohort filter, target audience, mandatory-training or Hindi
parity field on either form (earlier versions of this page said so). The
course end date does not drive reminders: the Completion deadline (days) does,
counted from the enrolment date (§5).

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

### Target audience editor (does not exist)

There is no course audience editor (`/local/airpay_courses/audience.php` was
never built; a course audience model is planned, post-demo plan A3). Today use
cohort sync, the Enrol users icon or **Mass enrol** on Manage Courses, or a
learning path's audience enrolment (it enrols people in every course on the path).

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

The `local_sentientia_courses` plugin includes two scheduled tasks, both **off
by default** (a Site Admin turns them on):

| Task | Default time | Recipients | Trigger |
|------|--------------|------------|---------|
| Deadline reminder | 09:00 daily | Learner | 7, 3 and 1 days before the deadline |
| Overdue escalation | 09:30 daily | The learner's supervisor (`open_supervisorid`) | 1, 7 and 14 days after the deadline |

The deadline is the enrolment date plus the course's Completion deadline
(days from enrolment). A course set to 0 has no deadline. See
`course-author-guide.md` §14.

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
- [ ] Nothing to do for the deadline reminder and supervisor escalation messages:
      their Hindi text ships with the platform (`local_sentientia_courses/lang/hi/`)

There is no automated Hindi audit page (`/local/airpay_courses/hindi_audit.php`
was specified but never built); use the checklist above.

---

## 9. References

- `learner.md` — what your students see
- `site-admin.md` §5 SCORM upload + validation
- `tenant-admin.md` §3 — bulk-enrol pattern

| Version | Date | Author | Notes |
|---------|------|--------|-------|
| v1 draft | 2026-05-24 | Claude (autonomous night-run) | Initial scaffold |
| v1 draft, corrected | 2026-11-19 | Post-demo plan 2.4 (session 1) | Removed fields and pages that do not exist (§1, §3, §5, §8) |
