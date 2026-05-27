# Course Author / SME User Guide

**Persona:** Course Author / Subject-Matter Expert (SME)
**Platform:** Sentientia LMS / Airpay Academy — Theme `airpayux` v1.0.37-beta
**Audience:** Trainers, SMEs, and content owners building + maintaining courses they teach
**Status:** v1.0 (2026-05-25) — supersedes the v1-draft skeleton at `course-author.md`
**Test account referenced:** `asif.ansari@airpay.co.in` — Airpay tenant id=1, open_path `/1/79/197/200`, 33 courses owned. Local password (XAMPP only): `AcademyAudit2026!`
**Local URL:** `http://localhost:8080/moodle/`

> **Sibling guides:** [`learner-guide.md`](learner-guide.md) · [`tenant-admin-guide.md`](tenant-admin-guide.md) · [`compliance-officer-guide.md`](compliance-officer-guide.md) · [`site-admin.md`](site-admin.md)

---

## Table of contents

1. [Who is a Course Author here?](#1-who-is-a-course-author)
2. [First login + landing experience](#2-first-login)
3. [Finding "your" courses](#3-finding-your-courses)
4. [Anatomy of a course (sections + activities)](#4-anatomy)
5. [Creating a new course](#5-create-course)
6. [Adding activities — standard Moodle](#6-add-standard-activities)
7. [Adding activities — Sentientia-specific](#7-add-sentientia-activities)
8. [Adding resources](#8-add-resources)
9. [SCORM upload workflow](#9-scorm)
10. [Sentientia Live (Mentimeter clone) — running a session](#10-sentientia-live)
11. [AI Quiz draft → review → publish workflow](#11-ai-quiz)
12. [Audience targeting + cohort filtering](#12-audience)
13. [Completion criteria configuration](#13-completion)
14. [Deadline reminders + manager escalation](#14-deadlines)
15. [Gradebook + reports](#15-gradebook)
16. [Hindi content readiness checklist](#16-hindi-readiness)
17. [Course completion + certificate template](#17-certificates)
18. [Publishing + tenant visibility](#18-publishing)
19. [Backup / restore / duplicate course](#19-backup)
20. [What's new in v1.0.37-beta — affecting Course Authors](#20-whats-new)
21. [Troubleshooting common issues](#21-troubleshooting)
22. [Screenshot capture sequence](#22-screenshot-sequence)
23. [References](#23-references)

---

## 1. Who is a Course Author here? <a id="1-who-is-a-course-author"></a>

In Sentientia LMS / Airpay Academy a Course Author is **not** a separate
top-level persona with its own dashboard. The audit (`docs/visual-audit-2026-05-22/AUDIT-REPORT.md`
§"Course Author / SME finding") explicitly concluded:

> "The platform does NOT have a dedicated 'course author' dashboard. The
> persona is the Learner persona with extra `editingteacher` capabilities."

Practically:
- Your home page is the **Learner dashboard**.
- A second section appears on `/grade/report/overview/` titled "Courses I am
  teaching", with quick links into each course's gradebook + edit page.
- Inside each course you teach, the **gear menu** (`⋮` or "More") gives you
  edit / activities / grades.

If you teach 33 courses (as Asif does), navigation reality is:
1. **Dashboard** → Continue learning + your own L&D progress
2. **Catalog → My Courses (Teaching tab)** → 33 courses sorted by recency
3. **Gradebook overview** → quick grade across all 33

Capabilities you carry (typical Course Author / SME):
- `moodle/course:manageactivities` — add / edit / delete activities
- `moodle/course:update` — edit course settings
- `mod/quiz:manage` — create + manage quizzes
- `mod/scorm:addinstance` — add SCORM activities
- `local/airpay_evaluation:create_template`
- `local/airpay_exams:create_template`
- `local/sentientia_live:create_session`
- `local/sentientia_aiquiz:generate` — when `sentientia.aiquiz.enabled` is ON

Capabilities you do **not** carry:
- `moodle/site:config`, `moodle/category:manage`, `moodle/user:create`
- Site-admin or Tenant-admin scopes — escalate to those personas.

📸 **Screenshot 01:** `screenshots/course-author/01-dashboard.png` — asif.ansari's
landing page (Learner shape, with "Continue Learning" populated by his own
training requirements).

---

## 2. First login + landing experience <a id="2-first-login"></a>

1. Browse to `http://localhost:8080/moodle/login/index.php`.
2. Enter `asif.ansari@airpay.co.in` / `AcademyAudit2026!`.
3. Click **Sign in**.

📸 **Screenshot 02:** `screenshots/course-author/02-login.png`

You land on `/my/dashboard.php`. The dashboard is the **Learner shape**:
- Welcome header — "Welcome back, Asif"
- Continue Learning card (any in-progress training YOU are taking)
- Deadlines + upcoming activities tile
- Skill rating progress
- Top courses (catalogue-wide popular)
- Calendar widget
- Achievements / badges strip

Because you have `editingteacher` on 33 courses, you also see (a) an extra
**"Courses I am teaching"** band on `/grade/report/overview/index.php` and
(b) per-course gear menus.

📸 **Screenshot 03:** `screenshots/course-author/03-dashboard-asif.png`

---

## 3. Finding "your" courses <a id="3-finding-your-courses"></a>

### 3.1 Via the Catalog → My Courses → Teaching tab

`/local/airpay_catalog/mycourses.php` → switch to the **Teaching** tab. You
see a 33-row grid, paginated 12 / page, sorted by last-modified desc by default.

Columns:
- Thumbnail (course banner image)
- Full name
- Category
- Visibility (Visible / Hidden / In review)
- Enrolment count
- Last-modified date

📸 **Screenshot 04:** `screenshots/course-author/04-myteaching-list.png`

### 3.2 Via the Grade overview

`/grade/report/overview/index.php` — Goal A.x restyled (chip-#259 state-card
refresh). Two sections:
- Top: My grades (courses YOU are enrolled in)
- Bottom: **Courses I am teaching** — quick links into each gradebook

### 3.3 Direct URL

`/course/view.php?id=<id>` opens any course you have access to. Edit-mode is
toggled by the **Edit mode** switch in the top-right (visible if you have
`moodle/course:update`).

---

## 4. Anatomy of a course (sections + activities) <a id="4-anatomy"></a>

A Moodle / Sentientia course is a stack of **sections** (Topics / Weeks /
Single-activity format). Each section holds **activities** (interactive — quizzes,
SCORM, etc.) and **resources** (passive — PDFs, URLs, videos).

```
+-----------------------------------------------------------+
| Course banner image — fullbleed                           |
| Course title + breadcrumb                                 |
+-----------------------------------------------------------+
| Section 0 — "General"                                     |
|   • Announcements forum                                   |
| Section 1 — "Module 1: Intro"                             |
|   • SCORM: Welcome video                                  |
|   • Quiz: Module 1 check                                  |
| Section 2 — "Module 2: ..."                               |
|   ...                                                     |
+-----------------------------------------------------------+
| Course completion progress bar (Sentientia surface)        |
+-----------------------------------------------------------+
```

📸 **Screenshot 05:** `screenshots/course-author/05-course-view.png` —
`/course/view.php?id=42` (use any course you own); capture the section list
with edit-mode OFF.

📸 **Screenshot 06:** `screenshots/course-author/06-course-edit-mode.png` —
same URL with **Edit mode** ON; capture the green + add buttons under each
section and the drag handles next to each activity.

---

## 5. Creating a new course <a id="5-create-course"></a>

`/course/management.php` → pick your category → "Create new course".

You CAN create courses inside categories where you have `moodle/course:create`.
On Airpay Academy customer-zero, Course Authors typically have this on their
team-specific category tree (e.g. `/Airpay/Operations/Risk and Compliance/`).
If you do not see "Create new course", escalate to your Tenant Admin to
extend your category scope.

### Required fields

| Field            | Notes                                                              |
|------------------|--------------------------------------------------------------------|
| Full name        | Shown in catalogue + breadcrumb. Use [mlang] tags if bilingual.    |
| Short name       | Unique identifier. Used in URLs + reports.                         |
| Category         | Pick the tree node where this course belongs.                      |
| Course visibility| Start as "Hidden" until §18 publishing checklist passes.           |
| Course start date| Drives "My Courses" sort + analytics window.                       |
| Course end date  | Drives `local_airpay_courses` deadline-reminder cron.              |

### Sentientia-specific fields (added by `local_airpay_courses`)

| Field                | Notes                                                              |
|----------------------|--------------------------------------------------------------------|
| Cohort filter        | Restrict enrolment to specific cohorts.                            |
| Target audience      | Designations / departments / roles.                                |
| Completion days (N)  | Reminder cron sends at N/3, 2N/3, N days after enrolment.          |
| Mandatory training   | Flag — if ticked, course shows in Compliance dashboard.            |
| Hindi parity         | Flag — if ticked, `hindi_audit.php` enforces 100% before publish. |

📸 **Screenshot 07:** `screenshots/course-author/07-create-course-form.png`

---

## 6. Adding activities — standard Moodle <a id="6-add-standard-activities"></a>

Inside the course, edit-mode ON, click **+ Add an activity or resource** under
any section. The activity picker opens (left rail = type list, right rail =
description + "Add" button).

| Activity     | When to use                                                                              | Notes                                                |
|--------------|------------------------------------------------------------------------------------------|------------------------------------------------------|
| Quiz         | Multi-question assessments with grading.                                                 | Supports time limit, password, proctoring (via `quizaccess_airpay_proctoring`). |
| Assignment   | File submissions + rubric grading.                                                       | Rubric is per-criterion; supports peer review.       |
| Feedback     | Post-course surveys; can be anonymous.                                                   | Session-ID tied; anonymous is genuine.               |
| Forum        | Asynchronous discussion.                                                                 | Default subscription: optional.                      |
| Workshop     | Peer-review submissions (5-phase: setup → submit → assess → grading → close).            | Heavyweight; document expectations carefully.        |
| Lesson       | Branching content — answers guide which page comes next.                                | Use sparingly; complex to author.                    |
| Choice       | Single yes/no/multiple-choice poll (fast).                                               | Lighter than Feedback.                               |
| URL          | External link.                                                                           | Sets target=_blank by default.                       |

📸 **Screenshot 08:** `screenshots/course-author/08-activity-picker.png`

### 6.1 Quiz — the most common activity

```
Add activity → Quiz → Name + intro → Timing + Layout → Save & display
→ Click into the activity → "Add question" → Build questions in question bank
→ "Edit quiz" → Drag questions into the quiz → Save
```

Question types: Multiple choice / True-False / Short answer / Essay / Matching /
Numerical / Cloze / Drag-and-drop. The **AI quiz drafts** (§11) accelerate
the multiple-choice case.

📸 **Screenshot 09:** `screenshots/course-author/09-quiz-edit.png`

📸 **Screenshot 10:** `screenshots/course-author/10-quiz-attempt-preview.png` —
click "Attempt quiz" in preview mode to verify the experience as a learner.

### 6.2 Assignment

Supports file upload (size / type restrictions per-activity), online text,
and team submissions. The Goal-A audit confirmed assignment grading uses
the standard Moodle gradebook (not a Sentientia-leak surface).

📸 **Screenshot 11:** `screenshots/course-author/11-assignment-edit.png`

---

## 7. Adding activities — Sentientia-specific <a id="7-add-sentientia-activities"></a>

These activity modules ship as Sentientia / Airpay plugins. They appear in the
same activity picker, scrollable below the standard Moodle types.

| Activity            | Module                          | When to use                                                        |
|---------------------|---------------------------------|--------------------------------------------------------------------|
| Evaluation          | `local_airpay_evaluation`       | Structured assessments with conditional questions; template library. |
| Exam                | `local_airpay_exams`            | Time-boxed, often proctored.                                       |
| Classroom (ILT)     | `local_airpay_classroom`        | Instructor-led training session with attendance + rating.          |
| Live engagement     | `local_sentientia_live`         | Mentimeter-style real-time polls / quizzes / wordclouds.           |
| AI Quiz Generation  | `local_sentientia_aiquiz`       | Generate multichoice quiz drafts from source content + review.     |
| Leaderboard         | `local_sentientia_leaderboard` + `block_sentientia_leaderboard` | SSE-driven realtime ranking (quiz / completion / skill).           |
| Calendar Sync       | `local_sentientia_calendar`     | Per-user outbound ICS feed (no per-activity instance).             |

### 7.1 Evaluation

`Add activity → Evaluation` → pick a template from the library (or start blank)
→ configure target audience + auto-expire window + Hindi parity flag.

Templates seed common patterns (5-question post-training feedback, 10-question
training-effectiveness, etc.). You can save your own template via
`/local/airpay_evaluation/template_save.php`.

📸 **Screenshot 12:** `screenshots/course-author/12-evaluation-edit.png`

### 7.2 Exam (proctored)

`Add activity → Exam` → set time-box + access rule
`quizaccess_airpay_proctoring`. The proctoring rule requires the learner to
grant webcam + face-detection consent before the timer starts.

Chip-A1 fixed the proctoring upgrade migration (defensive
`$dbman->create_table()` guard).

📸 **Screenshot 13:** `screenshots/course-author/13-exam-edit.png`

### 7.3 Classroom (instructor-led)

`Add activity → Classroom` → pick date + duration + capacity. Learners enrol
via the catalogue; you mark attendance + capture ratings post-session.

📸 **Screenshot 14:** `screenshots/course-author/14-classroom-edit.png`

### 7.4 Live engagement (Sentientia Live)

See §10 for full walkthrough.

---

## 8. Adding resources <a id="8-add-resources"></a>

| Resource    | When to use                                                                  |
|-------------|------------------------------------------------------------------------------|
| File        | Upload PDFs, slides, docs. Mobile-friendly viewer for most formats.          |
| Folder      | Group multiple files (e.g. all SOPs for one module).                         |
| Page        | Inline Moodle-edited HTML page. Use for short notes.                         |
| URL         | External link.                                                               |
| Book        | Multi-chapter inline content with table-of-contents nav.                     |
| Label       | Inline text inside the section list (no separate page).                      |

### 8.1 File — multilingual upload pattern

For Hindi parity, upload both `module-1.pdf` and `module-1-hi.pdf`, then on
the file resource edit screen put the file name through `{mlang}`:

```mustache
{mlang en}Module 1 — Introduction{mlang}{mlang hi}मॉड्यूल 1 — परिचय{mlang}
```

📸 **Screenshot 15:** `screenshots/course-author/15-resource-file-upload.png`

---

## 9. SCORM upload workflow <a id="9-scorm"></a>

SCORM 1.2 is the canonical packaging format for Airpay courseware (output of
the SENTIENTIA SOP → SCORM pipeline).

### 9.1 The validation gate

Before adding a SCORM activity, the ZIP must pass the airpay validator:

```
imsmanifest.xml at ZIP root          ← MUST
<organizations default="ORG_01">     ← MUST
items + href real files              ← MUST
masteryscore = 70                    ← Airpay default
all files in manifest exist in ZIP   ← MUST
```

The validator runs server-side in `/local/airpay_courses/upload_scorm.php`
BEFORE the file lands in your course's draft area. If it fails, you see a
list of which gate broke and the upload is rejected.

📸 **Screenshot 16:** `screenshots/course-author/16-scorm-upload.png`

### 9.2 The SCORM activity

Inside the course, edit-mode ON, **+ Add activity → SCORM package**:

| Field                     | Notes                                                                |
|---------------------------|----------------------------------------------------------------------|
| Name + intro              | Public-facing; can be `{mlang}` bilingual.                           |
| Package file              | Upload the validated ZIP (or pick from "Choose a file").             |
| Display package           | "Open in new window" recommended for SCORM 1.2 compatibility.        |
| Width / height            | 100% / 600 typical.                                                  |
| Grading method            | Highest grade / Average / First attempt / Last attempt.              |
| Max number of attempts    | Unlimited by default; restrict for proctored / exam-class SCORM.     |
| Force completed           | OFF (learner control).                                               |
| Attempt prevent on completion | ON if assessment is one-shot.                                    |
| Completion tracking       | "Require status: Completed/Passed" + score threshold.                |

📸 **Screenshot 17:** `screenshots/course-author/17-scorm-activity-edit.png`

### 9.3 Reports inside SCORM

Open the SCORM activity → "Reports" link in the top-right. Per-attempt grid:
who attempted, when, status, score, time spent. Drill into any row to see the
attempt's interaction log (every question + every answer + every time).

📸 **Screenshot 18:** `screenshots/course-author/18-scorm-report.png`

---

## 10. Sentientia Live (Mentimeter clone) — running a session <a id="10-sentientia-live"></a>

Source plugin: `local_sentientia_live`. Documented in ADR-004 + state-card +
chip-P2-H (NVDA verification).

### 10.1 Create a session

`/local/sentientia_live/trainer/dashboard.php` → "New session" → form:

| Field               | Notes                                                              |
|---------------------|--------------------------------------------------------------------|
| Name                | Public-facing.                                                     |
| Course context      | Optional — pick a course to attach session results to.             |
| Audience tenant     | Defaults to your tenant; cannot exceed your scope.                 |
| Anonymous join      | If ON, audience joins without login (tokenised code).              |
| Slides              | Add via "Add slide" — polymorphic editor with 6 question types.   |

Question types (chip-P3-R question-type-stubs landed):
- Multiple choice (single / multi-select)
- Quiz (correct answer captured)
- Rating (1-5 stars or 0-10 NPS)
- Wordcloud
- Open-ended
- Ranking

📸 **Screenshot 19:** `screenshots/course-author/19-live-create.png`

### 10.2 Run the session

`/local/sentientia_live/trainer/run.php?sessionid=<id>` — the trainer view.
On the left a slide-list; centre shows the active slide + the live result
panel; right shows the join URL + QR code + participant count.

Audience members open `/local/sentientia_live/audience/join.php` (or scan the
QR), enter the session code, then watch slides as you advance them. Each
response writes to `local_sentientia_live_responses` and triggers an SSE event;
your screen's chart updates in place via `chart_updater` AMD module.

📸 **Screenshot 20:** `screenshots/course-author/20-live-run.png`

📸 **Screenshot 21:** `screenshots/course-author/21-live-audience.png` —
parallel browser window simulating an audience member.

### 10.3 Result panels per question type

Each question type has its own result template (chip-E4):
- Multiple choice / Quiz → horizontal bar chart with response counts + percentages
- Rating → histogram + mean / median annotation
- Wordcloud → font-size weighted by frequency
- Open-ended → scrolling list (newest first)
- Ranking → ordered table with mean rank position

### 10.4 Quiz leaderboard

For `Quiz` type questions, the right-rail leaderboard shows top responders by
correctness + time-to-answer. Trainer-only — audience view hides the
leaderboard (chip-E6).

📸 **Screenshot 22:** `screenshots/course-author/22-live-leaderboard.png`

### 10.5 Accessibility (a11y)

The plugin ships 9 `aria-live="polite"` regions + 1 visually-hidden tally
summary written by `chart_updater.js`. NVDA verification procedure documented
in `docs/qa/NVDA-VERIFICATION-PROCEDURE.md` (chip P2-H).

---

## 11. AI Quiz draft → review → publish workflow <a id="11-ai-quiz"></a>

Source plugin: `local_sentientia_aiquiz` v0.1.0-alpha (Phase G.0 MVP).

### 11.1 Generate a draft (MOCK mode by default)

`/local/sentientia_aiquiz/generate.php`

| Step                                                                                |
|-------------------------------------------------------------------------------------|
| 1. Paste source content (max 2000 words; PII heuristic auto-flags).                 |
| 2. Pick number of questions (3 / 5 / 10 / 15).                                      |
| 3. Pick difficulty (easy / medium / hard / mixed).                                  |
| 4. Pick language (en / hi).                                                         |
| 5. Tick the **[CONFIRM] — I have permission to send this content** checkbox.        |
| 6. Click **Generate**.                                                              |

In MOCK mode (`sentientia.aiquiz.live_api` flag OFF — default), the response
is deterministic mock JSON; no Anthropic API call is made; no cost incurred.

In LIVE mode, the prompt is submitted to Anthropic Claude with versioned
system prompt (v1 today; v2-hindi planned for Phase G.1). Cost ~$0.01 per
5-question draft on Claude Sonnet 4.6.

📸 **Screenshot 23:** `screenshots/course-author/23-aiquiz-generate.png`

### 11.2 Review the draft

`/local/sentientia_aiquiz/review.php?draftid=<id>`

Per-question:
- **Approve** — keep as-is, ready to push
- **Edit** — open the inline editor; correct wording / options / answer
- **Reject** — drop from the draft

Below the per-question rows, a "Finalise + push" button appears once every
question is in an `approved` or `rejected` state. Pushing creates a new
`mod_quiz` activity in the chosen course with the approved questions.

📸 **Screenshot 24:** `screenshots/course-author/24-aiquiz-review.png`

📸 **Screenshot 25:** `screenshots/course-author/25-aiquiz-push.png`

### 11.3 Cost defence

Phase G.0 ships 4-layer cost defence:
1. `[CONFIRM]` checkbox in form — rejects submission when unticked
2. `sentientia.aiquiz.live_api` flag gates the actual POST
3. Per-customer token quota (Phase G.3, not yet shipped)
4. Per-user-per-day count cap (default 5 drafts; configurable)

---

## 12. Audience targeting + cohort filtering <a id="12-audience"></a>

Same three patterns as the Tenant Admin guide §10:
1. **Cohort sync** via `Course → Users → Enrolment methods`
2. **Audience editor** via `/local/airpay_courses/audience.php?courseid=<id>`
3. **Self-enrol + capability gate** for self-driven catalogues

If your tenant does not have the cohort you need, request your Tenant Admin
to create it (Site Admin scope for cohort definitions). You attach to your
course once the cohort exists.

📸 **Screenshot 26:** `screenshots/course-author/26-audience-editor.png`

---

## 13. Completion criteria configuration <a id="13-completion"></a>

`Course settings → Completion tracking`

Two patterns:

### 13.1 Activity-completion aggregate

Each activity has its own "Completion conditions" (Marked complete / Receive
grade / Submit assignment / Pass quiz). The course completes when ALL or N
of the configured activities are complete.

### 13.2 Course-grade threshold

Set "Completion based on" → "Course grade ≥ X". Activities feed into the
course gradebook (weighted aggregate or simple mean).

📸 **Screenshot 27:** `screenshots/course-author/27-completion-settings.png`

### 13.3 Restrict access (linear flow)

`Activity → Edit settings → Restrict access` → "Add restriction" → "Activity
completion" → pick the prerequisite. Use sparingly — overly-linear courses
frustrate learners who want to bookmark + return.

---

## 14. Deadline reminders + manager escalation <a id="14-deadlines"></a>

Two crons in `local_airpay_courses`:

| Cron                          | When            | Recipients   | Trigger condition                                         |
|-------------------------------|-----------------|--------------|-----------------------------------------------------------|
| Learner reminder              | daily 03:30 IST | Learner      | N/3, 2N/3, N days after enrolment (N = completion_days)   |
| Manager escalation            | daily 04:00 IST | Line manager | Learner crossed deadline without completion               |

Triple-fan-out per recipient: email + push (PWA) + WhatsApp (if opt-in).
Push and WhatsApp gated by:
- `sentientia.pwa.push.reminders` / `.overdue` (PWA)
- `engagement.whatsapp.reminders` / `.overdue` (WhatsApp)

Default OFF on customer-zero today; Site Admin flips ON post-rollout.

You do not configure the crons — they fire automatically if the course has
`completion_days` set + the learner has a manager populated in their profile.

📸 **Screenshot 28:** `screenshots/course-author/28-deadline-config.png`

---

## 15. Gradebook + reports <a id="15-gradebook"></a>

| Report                | URL                                                                  | Notes                                |
|-----------------------|----------------------------------------------------------------------|--------------------------------------|
| Grader report         | `/grade/report/grader/index.php?id=<courseid>`                       | Per-learner grid; inline editing.    |
| Course overview       | `/grade/report/overview/index.php?id=<courseid>`                     | Summary across all your courses.     |
| Single view           | `/grade/report/singleview/index.php?id=<courseid>`                   | Drill one student × one grade item.  |
| User report           | `/grade/report/user/index.php?id=<courseid>&userid=<uid>`           | One student × all items.             |
| Outcomes report       | `/grade/report/outcomes/index.php?id=<courseid>`                     | If outcomes are enabled site-wide.   |

Grader report was restyled in chip-#259 wave (Goal A.9). Pre-chip the grader
table looked Moodle-stock; post-chip it inherits Sentientia tokens.

📸 **Screenshot 29:** `screenshots/course-author/29-grader-report.png`

📸 **Screenshot 30:** `screenshots/course-author/30-grader-mobile.png` — capture
the grader at 590px to verify horizontal scroll inside the card.

### CSV export

Top-right Export tab → CSV / XLS / OpenDocument. Same UTF-8 + BOM + semicolon
convention as Sentientia analytics.

---

## 16. Hindi content readiness checklist <a id="16-hindi-readiness"></a>

If your tenant has Hindi users (anyone with `lang=hi` in profile), your course
must pass the Hindi audit before publishing.

Run `/local/airpay_courses/hindi_audit.php?courseid=<id>` (Note: as of this
release the script exists in spec only — chip-#H1772 noted it as missing
from production, with a documented manual-diff fallback. Once the chip ships,
the URL becomes live).

Manual checklist meanwhile:

- [ ] Course full name has `{mlang}` block for `en` + `hi`
- [ ] Course summary likewise
- [ ] All activity names (`format=mlang`)
- [ ] SCORM content has Hindi audio + slide track (or a Hindi sibling SCORM)
- [ ] PDFs/resources have Hindi versions linked
- [ ] Quiz/evaluation question stems + options translated
- [ ] Manager-escalation email subject + body translated
- [ ] Welcome email tokens translated (Tenant Admin handles `welcome-1-hi.md`)

📸 **Screenshot 31:** `screenshots/course-author/31-hindi-audit.png`

---

## 17. Course completion + certificate template <a id="17-certificates"></a>

Sentientia uses Moodle's `tool_certificate` (with chip-#1.1.2 fix landed
2026-05-22 for the non-image-file TypeError).

### 17.1 Certificate template

`/admin/tool/certificate/template.php?id=N` (Site Admin or `Certificate
manager` role). As a Course Author you can request a new template via your
Tenant Admin; you typically do not author templates directly.

### 17.2 Wire the template to your course

`Course settings → Completion → Course completion certificate` → pick a
template → save. On course completion, the certificate is auto-issued.

### 17.3 Verification

Every issued certificate has a unique verification ID. Anyone — including
external HR / partner orgs — can verify at:

```
/admin/tool/certificate/verify.php?code=<id>
```

📸 **Screenshot 32:** `screenshots/course-author/32-certificate-issued.png`

---

## 18. Publishing + tenant visibility <a id="18-publishing"></a>

Checklist before flipping a course from Hidden to Visible:

- [ ] All activities have completion conditions configured
- [ ] Audience filter set (cohort / designation / etc.)
- [ ] Completion days (N) set if you want deadline reminders
- [ ] Mandatory training flag set if statutory (Airpay only)
- [ ] Hindi audit passed (Airpay tenant only)
- [ ] At least one full smoke-test as a Learner role (use "Switch role to" → "Student")
- [ ] Backup taken (§19)

Once stable, announce via:
- Email blast (Tenant Admin or Manager)
- WhatsApp blast (if DLT template approved for the announcement)
- Catalogue feature flag (Site Admin can promote to Featured tile)

📸 **Screenshot 33:** `screenshots/course-author/33-publish-checklist.png`

---

## 19. Backup / restore / duplicate course <a id="19-backup"></a>

### 19.1 Backup

`Course settings → Backup` → tick "Include enrolled users" if doing a true
clone; un-tick for a template. Confirm sections → Execute.

The `.mbz` (Moodle backup) file lives in your private files area for 7 days
before pruning. Download immediately if you need to keep it long-term.

📸 **Screenshot 34:** `screenshots/course-author/34-course-backup.png`

### 19.2 Restore as new course

`/backup/restorefile.php` → upload `.mbz` → pick category → restore as new
course (NOT into existing). Useful for cloning a working course.

### 19.3 Duplicate

`/course/management.php` → pick course → "Duplicate" row action. Faster than
backup-restore for in-tenant clones.

---

## 20. What's new in v1.0.37-beta — affecting Course Authors <a id="20-whats-new"></a>

The Day-0 chip wave (21 merges, 2026-05-24) touched the Course Author surface
in these places:

| # | Chip | Surface affected | What you'll notice |
|---|------|------------------|--------------------|
| 1 | A — Orphan SCSS `Claude` deleted | Theme build | Faster page paint (98 KB stripped). |
| 2 | B — `MONOLITH_BACKUP.scss` archived | Theme build | Smaller compile target. |
| 3 | B — Navbar i18n | Navbar | Nav pills render in your locale. |
| 4 | B — Footer i18n | Footer | Footer links localised. |
| 5 | C — Dashboard inline-style cleanup | Dashboard | Dark mode now works on KPI tiles you see as a Learner. |
| 6 | C / F-06 — Footer attribution styled via SCSS | Footer | Footer theme-aware in dark mode. |
| 7 | #255 — All 5 locales at 178 strings | Every UI string | Hindi / regional learners get full translations of theme chrome. |
| 8 | E — Sentientia Live `aria-live` regions + sr-only tally | Live engagement plugin | NVDA-verified screen-reader support for audience. |
| 9 | F / F-02 — Navbar cart-badge IIFE → AMD module | Navbar | CSP-strict customers can use cart badge. |
| 10 | J — `_surface-profile.scss` split (profile / badges / grade-report / calendar / preferences) | Profile + Badges + Grade overview + Calendar surfaces | Same UI; faster, cleaner cascade. |
| 11 | K — `_surface-login.scss` `!important` cleanup | Login | Easier future restyling. |
| 12 | P1 #12 + H — `:focus-visible` across surface partials | Every interactive element | Mouse-click stops flashing focus ring; keyboard nav unchanged. |
| 13 | I — `dark_mode.scss` token cascade refactor | Every dark-mode surface | Dark mode respects token cascade; brand-colour overrides propagate. |
| 14 | L — Footer mobile breakpoint | Footer mobile | No more overflow on small Galaxy S devices. |
| 15 | M — Sentientia Live BEM tokens replace Bootstrap utilities | Live engagement | Buttons / badges match brand at all sizes + modes. |
| 16 | G — Dashboard 11 i18n strings | Dashboard | KPI labels + chart titles translate. |
| 17 | N — Chart.js vendored + `{{#js}}` init | Dashboard | No external CDN; works on CSP-strict networks. |
| 18 | #18 — `_moodle-overrides.scss` `!important` trim | Site-wide chrome | Cascade reads correctly. |
| 19 | #19 + D — `prefers-reduced-motion` stylelint + inline timing → tokens | All animations | WCAG 2.3.3 vestibular safety. |
| 20 | Q — `coursebannerimage` CSS-url injection safety doc | Course banner | Doc-only; safe-as-was. |
| 21 | O / #21 — Footer removed-badge comment trim | Footer | Template hygiene. |

### Bonus highlighted for Course Authors

- **P3-M / P3-R** — AI Quiz scaffold + Live question-type stubs (you can
  experiment in MOCK mode today; live mode lights up when Site Admin flips
  `sentientia.aiquiz.live_api`).
- **P2-J** — Cutover-day smoke test (`scripts/cutover-smoke-test.py`) covers
  the SCORM upload + launch surfaces; if SCORM breaks on a deploy, this test
  catches it before learners see it.
- **#257** — `deploy-to-xampp.ps1` (Site Admin) shortens iteration from save →
  see on local from ~3 min to ~30 s.

📸 **Screenshot 35:** `screenshots/course-author/35-whats-new-diff.png`

---

## 21. Troubleshooting common issues <a id="21-troubleshooting"></a>

### "I cannot edit this course"

| Cause                                                | Resolution                                                                          |
|------------------------------------------------------|-------------------------------------------------------------------------------------|
| You are not enrolled as `editingteacher`             | Tenant Admin enrols you via course → Users → Enrolment methods.                     |
| Course is in a category outside your scope           | Tenant Admin grants `moodle/course:update` at category context.                     |
| Edit mode is off                                     | Toggle "Edit mode" switch top-right.                                                |
| Course is locked for maintenance                     | Site Admin set the course to read-only; wait or escalate.                           |

### "Quiz question bank is empty"

The question bank is course-scoped. If you imported the quiz from another
course without questions, the bank is empty. Solutions:
- Import the questions (Question bank → Import → Moodle XML / GIFT / Aiken)
- Author directly in `Course → Question bank → Create new question`
- Generate via AI Quiz (§11) and push the result into the quiz

### "SCORM uploads but does not launch"

Open the SCORM activity → Reports → "View" link inside an attempt row.
Common causes:
- imsmanifest.xml not at ZIP root (re-zip flat, see CLAUDE.md §8)
- SCORM API methods called before page load (older content packs); set
  "Display package" to "Open in new window" instead of "in iframe"
- masteryscore mismatch (Sentientia default 70; some content packs hardcode 80)

### "Activity completion not ticking"

| Cause                                                | Resolution                                                                |
|------------------------------------------------------|---------------------------------------------------------------------------|
| Activity has no completion condition                 | Edit settings → Completion → tick at least one condition.                 |
| Learner did not meet the condition                   | Verify in `/admin/report/log/index.php` what event fired.                 |
| Cron has not run                                     | Site Admin runs `php admin/cli/cron.php`; activity completion is async.  |
| Restrict-access prerequisite still locked            | Verify learner met the prereq (gradebook + activity completion log).      |

### "Sentientia Live SSE not updating in real time"

Open the audience window in DevTools → Network → look for the `stream.php` SSE
connection. It should be in "EventStream" tab + show heartbeat (`:keepalive`)
every 15 s. If absent:
- Check `local_sentientia_live.feature_flags.sse_enabled` is ON
- Check the trainer page console for SSE URL resolution errors (chip-VIS-10 fix:
  the URL must resolve against `M.cfg.wwwroot`, not domain root)
- Check Moodle session is alive (5-min wall-clock budget in ADR-004)

### "AI Quiz generates only mock content"

Default — live API is OFF. Site Admin flips `sentientia.aiquiz.live_api` ON
with a `[CONFIRM]`. Verify the flag in `/local/airpay_core/switchboard.php`.

### "Mobile preview shows broken layout on my course"

If you used inline `style=` in a Label resource, dark mode + mobile may not
adapt. Replace inline styles with classes (e.g. `.lead`, `.text-muted`).
Goal-A chip-#256 cleaned up similar issues in `_surface-*.scss`.

---

## 22. Screenshot capture sequence <a id="22-screenshot-sequence"></a>

```powershell
# 1. XAMPP up + caches purged
Set-Location C:\xampp\htdocs\moodle5\public
php ..\admin\cli\purge_caches.php

# 2. Open Chrome at 1440x900 canonical capture viewport
"C:\Program Files\Google\Chrome\Application\chrome.exe" `
    --user-data-dir="C:\tmp\chrome-airpay-capture-author" `
    --window-size=1440,900 `
    http://localhost:8080/moodle/login/index.php

# 3. Sign in as asif.ansari@airpay.co.in / AcademyAudit2026!

# 4. Walk the URL sequence from §3 → §19 of this guide.

# 5. Save each PNG to:
#    moodle-enhancement/docs/user-guides/screenshots/course-author/NN-<slug>.png
#    matching the NN numbers referenced in this guide.

# 6. For mobile shots (Screenshot 30 grader-mobile + any others noted),
#    Ctrl+Shift+M → 590px → recapture.

# 7. For SCORM upload (Screenshot 16), use a known-good SCORM ZIP from
#    content/scorm-output/. If none exists, generate one via:
#       Set-Location D:\Claude Local\airpay-ld-os\content\scorm-output\sample-course
#       Compress-Archive -Path * -DestinationPath ..\sample-course-scorm.zip

# 8. Commit + push:
git add docs/user-guides/screenshots/course-author/
git commit -m "docs(user-guides): course-author screenshots capture"
git push -u origin claude/friendly-gates-10iUM
```

Total captures: ~35 desktop + 2 mobile + 1 dark-mode = ~38 PNG.

---

## 23. References <a id="23-references"></a>

- [`learner-guide.md`](learner-guide.md) — what your students see
- [`tenant-admin-guide.md`](tenant-admin-guide.md) — admin overlay context
- [`compliance-officer-guide.md`](compliance-officer-guide.md) — compliance gate
- [`site-admin.md`](site-admin.md) — full-scope admin
- [`README.md`](README.md) — guide index
- [`course-author.md`](course-author.md) — v1-draft scaffold (superseded by depth here)
- `state-cards/local_sentientia_live-state.md` — Live engagement plugin
- `state-cards/local_sentientia_aiquiz-state.md` — AI quiz pipeline
- `state-cards/local_airpay_evaluation-state.md` — Evaluation activity
- `state-cards/local_airpay_exams-state.md` — Exam activity
- `state-cards/local_airpay_courses-state.md` — Reminder + escalation crons
- `docs/adr/ADR-004-realtime-mechanism-for-sentientia-live.md` — SSE choice
- `docs/adr/ADR-012-ai-quiz-generation.md` — Anthropic Claude integration + cost defence
- `docs/qa/NVDA-VERIFICATION-PROCEDURE.md` — Live a11y rubric
- `CLAUDE.md` (root) — SOP → SCORM pipeline rules + escalation
- `.claude/rules/frontend.md` — token + BEM discipline for any UI you author
- `.claude/rules/api.md` — Anthropic / ElevenLabs / Gamma confirmation gates

---

| Version | Date       | Author                       | Notes                                                  |
|---------|------------|------------------------------|--------------------------------------------------------|
| v1.0    | 2026-05-25 | Wave D3 P3 testing-and-docs chip | Full ≥20-page guide; supersedes v1-draft scaffold |
