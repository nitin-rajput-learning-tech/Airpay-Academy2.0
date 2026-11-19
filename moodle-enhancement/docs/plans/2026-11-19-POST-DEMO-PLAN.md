# Post-demo plan: your UAT feedback of 19 Nov 2026

**For:** Head of L&D (product owner) | **Date:** 2026-11-19 | **Status:** draft for your decisions
**Scope:** every point in your UAT review this morning: course editing, assessment integrity, training
programs, external trainers, learning-path changes, Programs, analytics, Skills UI, surveys, certificate
verification, email templates, native mobile apps.

Ground rules for everything below:
- UAT was not touched today (the C-suite demo is running on it). Every change is in the local working
  tree. Nothing is committed or deployed. UAT deploys and flag flips stay your call.
- New user-visible features ship behind feature flags that default to OFF. Fixes to existing pages ship
  without a flag.
- Effort: S = up to 1.5 days, M = 2 to 4 days, L = 5 days or more. Days are developer days.

---

## 1. Summary

**Done now (5 quick wins, local only, not yet run in a browser or PHPUnit):**
1. **Email previews fixed.** All 20 previews failed with "Template error". Root cause found and fixed. A
   second bug also fixed: saving an edited template silently removed the course card and support footer
   from every email sent from it.
2. **Certificate verification page.** Verification works. The red "Required" under the button was Moodle's
   form legend, not an error; it is gone, and "Not verified" now explains itself.
3. **Training ROI card** now shows its figures, formulas and all 7 assumptions, with a settings page.
   **Skill Gap** counts fixed (they were too low, and "All business units" meant Airpay only).
4. **Skills page spacing** ("tight spaces") fixed: buttons, icons and chips now have real gaps.
5. **Modal form labels** (Edit course, Add classroom, Edit program, and every other pop-up form) no longer
   wrap with an odd indent.

**Four risks you should know about before anything else ships:**

| Risk | What happens | What to do |
|---|---|---|
| Compliance auto-enrol crosses tenants | The 5 mandatory courses (POSH 2025, Infosec, AML, Phishing, POSH for IC Members) are set to "all tenants". When the hourly task runs, every Public and ZEEA user is enrolled into Airpay courses, and everyone into the IC-members-only course. It has never run locally. Not checked on UAT. | Fix first (session 1). After the demo, check whether that task has run on UAT. |
| Proctoring does not work end to end | Learners cannot start a proctored quiz (the Start button is hidden), and webcam monitoring stops after the first page. Teachers previewing it would not notice. | Keep it off and do not demo it until session 4 lands. |
| ROI is negative by design | With the default assumptions, the card shows about -88% for year to date, because it charges the build cost of all ~418 courses to every period. | Keep the ROI flag off for C-suite audiences until model v2 (decision D20). |
| Learning-path learners never complete | Learners enrolled natively never get "Completed", so the "path stalled" reminder nudges them forever. | Fixed by the learning-path completion engine (session 8). |

**Planned:** 8 categories (A to H below) plus analytics follow-ups, about **120 to 145 developer days**
in total, plus 15 to 20 days for items marked "later":
- **LMS track:** 28 sessions, about 75 to 90 days (section 5).
- **Mobile app MVP:** 10 sessions, about 45 to 55 days, on its own track with a separate mobile
  developer.
- **Revised 19 Nov, after your answers in 6a:** LMS track about 100 to 120 days; mobile track about 59 to 79
  days from today (offline in v1, built by us). See section 5 and the three documents listed at the top of
  section 4.

**Needs your decision now** (full list in section 6). These six unblock the first month:
- **D1:** whether the course audience controls catalogue visibility, mandatory assignment, or both.
- **D4:** the compliance fix for the current rows (pin to Airpay, restrict course 400).
- **D8:** the assessment security level for each type of assessment.
- **D12:** build Training Programs on Classroom.
- **D17:** learning-path revisions, Option D.
- **D27:** start the D-U-N-S and app-store enrolments now (longest lead time).

---

## 2. Quick wins done now

All five are uncommitted in the working tree. Where a plugin exists in both trees (`moodle-enhancement/`
and the top-level copy), both copies are byte-identical. None was run in Moodle: PHPUnit and the browser
were out of scope for this workflow. New tests were written for each one; they still need running.

### 2.1 Status table

| ID | Your feedback | Status | What changed | Version |
|---|---|---|---|---|
| QW-EMAIL | "Check template errors in email previews" | **Fixed**, reviewed (3 minor review findings also fixed) | Preview page read the template name with a filter that removed the "/" every name contains, so all 20 previews failed. Names are now checked against the list of 20 known templates (this also closes a gap where a crafted name could read or override any template file). Edited templates now keep the course card and support footer. Preview shows the edited version, not the file, and says which one it shows. | emails 2026111900 / 1.4.1 |
| QW-CERT-VERIFY | "Verify if certificate verification works" | **Partly fixed**, reviewed (pass) | Theme hides the misleading "(!) Required" legend on this one page only, and adds a clear "No certificate with this code was issued on this site" message (en + hi). The vendored certificate plugin was not edited (see 2.2). | theme 2026111900 / 1.0.71-beta |
| QW-ROI | "How do Skill Gap Projection and Training ROI work?" | **Fixed** (review outcome not included in this plan's inputs; treat as pending review) | ROI card: the page and template used different field names, so nothing printed. Now shows ROI %, net benefit, each benefit and cost line with its formula, all 7 assumptions (marked "default" until changed), Hindi text, and honest empty states. New settings page for the 7 assumptions. Skill Gap: counts were too low (one category per team), "All business units" meant Airpay only, and the tags printed N times. Both cards had no CSS; added. | analytics 2026111900 / 1.2.7-beta |
| QW-SKILLS-UI | "Check Skills page UI, tight spaces in elements" | **Fixed** (review outcome not included in this plan's inputs) | The theme uses Bootstrap 4.6, which has no `gap-*` classes, so the templates' gap classes rendered as 0 px. Spacing now comes from the theme's spacing tokens in the plugin CSS (manage page, dashboard chips, self-rate panel, designation matrix). The skills table also gets its missing Actions column header. | skills 2026111900 / 1.7.2 |
| QW-MODAL-FORMS | Part of "look at edit course option" | **Fixed** (review outcome not included in this plan's inputs) | In pop-up forms, a core style indented the first line of every label in a collapsible section by 2.5rem, so labels wrapped oddly and the required/help icons landed at the end of the second line. Now the labels are clean, icons sit beside the label, and wide dialogs use a 30/70 label/field layout from 992 px. Page forms are unchanged. | theme 2026111900 (shared with QW-CERT-VERIFY) |

No new feature flags: all five fix existing pages. The ROI and Skill Gap cards remain behind their
existing flags (`sentientia.analytics.roi.enabled`, `sentientia.analytics.predictive.enabled`, both
default OFF).

### 2.2 Spec only (not applied, needs your approval)

**Certificate links do not show a result on arrival.** QR codes on certificates, the gallery's "Verify"
button and the LinkedIn link all open `admin/tool/certificate/index.php?code=...`. The page shows the
code filled in but no result until the visitor presses Verify again. The same URL also adds a second
hidden "code" field, which can make a false "Required" error appear under the field. The fix is two
small changes to the vendored `tool_certificate` plugin (show the result when a code arrives in the link;
post the form to the bare URL). Because it edits third-party code, it needs a core-mod record and your
approval. Effort: S, 0.5 day. Proposed record:
`moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md`.

### 2.3 How to verify

Local first (deploy the 4 plugins and the theme to XAMPP, run the upgrade, purge caches). Then, after
the demo and on your go, the same checks on UAT (`tools/uat/deploy_to_uat.sh`, dry run first).

| ID | Check | Expected |
|---|---|---|
| QW-EMAIL | Email templates > Templates tab > Preview; click all 20 sidebar entries | Each shows a branded email; none shows "Template error" |
| QW-EMAIL | Design Studio > open "Deadline Warning" > Save without editing > Full Preview | "Showing this tenant's edited version", with the course card **and** the support footer still there. Revert afterwards. |
| QW-CERT-VERIFY | Logged out, open `/admin/tool/certificate/index.php`, enter a made-up code | No "(!) Required" under the button; red "Not verified" with an explanation |
| QW-CERT-VERIFY | As a learner, My certificates > Verify on a card > press Verify | Green "This certificate is valid" and the details table |
| QW-ROI | Analytics, YTD, All business units (flags on) | ROI card shows %, totals, formula lines and 7 assumptions; "Set ROI assumptions" link for admins only |
| QW-ROI | Skill Gap card, same page | Teams from all tenants; one "Uncovered skills" row per team |
| QW-SKILLS-UI | Skills Management (admin page) | Header buttons separated; category icons not touching their names; table header has an Actions column |
| QW-MODAL-FORMS | Manage Courses > Edit (and Add classroom) | Labels start flush, icons beside the label, two-column layout on desktop |

**Owed before merge** (CLAUDE.md rules): desktop and mobile screenshots in
`docs/visual-evidence/2026-11-19/` for all five; a PHPUnit run of the new tests; PROJECT-STATE.md update.

### 2.4 Further quick wins found (each under 1 hour, not done yet)

These go into the first session (section 5, session 1), because UAT was out of bounds today.

| Area | Quick win |
|---|---|
| Courses | Hide the dead "Number of sections" field when editing a course (saving ignores it). |
| Courses | Compliance cross-tenant guard: skip a mandatory course outside the user's tenant unless it is shared to that tenant. |
| Courses | "Open course" and "Advanced settings" row actions on Manage Courses (flag `sentientia.courses.advanced_actions.enabled`). |
| Courses | Correct the course author guide, which lists fields that do not exist. |
| Proctoring | One-page admin guide for hiring and skill presets using core quiz settings only (question banks, single attempt, review after close, browser security, password and IP subnet for walk-in tests). |
| Proctoring | Correct three stale docs (two claim features that do not exist; one says proctoring is live on airpay.academy). |
| Training / feedback | Feedback invitation links carry the classroom, course or program, so responses are tied to it (today they are stored with no context). |
| Programs | Add Programs to the L&D admin sidebar (today it is in the site-admin menu only). |
| External trainer | One-page interim runbook (today's process and its risks). |
| Learning paths | Correct confirm-dialog titles; a clear message for imported BizLMS course rows; learner enrolment email links to My courses (it links to an admin page learners cannot open); course numbers start at 1. |
| Surveys | Move hard-coded English on the response page to en + hi strings; Preview and Copy-link buttons; link the Assign / Non-respondents page (it is linked from nowhere); Excel export. |
| Analytics | YTD compared with the same span last year; skillsai nightly task processes all users (today only the first 500); cache lifetime matches the hourly refresh. |
| Email | The rule editor accepts any template name (same gap as the preview page had); run it through the same whitelist. |
| Mobile | Remove the live BizLMS address from the app scaffold; correct two wrong docs about push. |

---

## 3. Answers to your questions

### 3.1 How Programs work, and how to add content

A **Program** today is a **certification ladder**: an ordered set of **Levels**, each holding Moodle
**courses**. Levels unlock one after another. A level completes when all (or any) of its courses are done.
The program completes when its required levels are done.

How an admin builds one (site admins only today; the L&D admin menu has no Programs link):
1. Sidebar > **Programs** > **Create program** (name, description, organisation, completion rule, dates,
   status).
2. Open the program > **Levels** tab > **Add level** (name, description, required or optional).
3. Click the level > **Add courses** (multi-select). Every course is added as mandatory, at the end.
4. **Users** tab > enrol individually, by cohort, or by audience (designation, region, employment type).

What does not work yet:
- **Enrolling someone in a program does not enrol them in its courses.** They have to be enrolled in
  each course separately. Learning paths already do this automatically.
- Learners cannot see their programs: the "My programs" page is behind a flag that is OFF.
- A level can hold courses only: no classroom session, assessment or feedback step.
- Courses inside a level cannot be reordered or made optional.

How Programs, Learning Paths and Classrooms differ:

| | Classroom | Learning Path | Program |
|---|---|---|---|
| What it is | One instructor-led training | One ordered list of courses | A multi-level certification track |
| Holds | Sessions, roster, attendance, waitlist, trainer, feedback | Courses | Levels > courses |
| Enrols learners into the courses | n/a | Yes, and back-fills courses added later | **No** |
| Learner page | Behind a flag (OFF) | Yes | Behind a flag (OFF) |

The fixes are in category E. Your training-program example (pre-assessment, pre-read, sessions,
post-assessment, feedback) fits none of the three today; it is category C.

### 3.2 How Analytics works: Skill Gap Projection and Training ROI

**The dashboard overall.** It reads the LMS's own records (logins, enrolments, completions, certificates,
the hourly compliance snapshot). Nothing is typed in by hand. Each viewer sees their own tenant; only a
platform admin sees all three. Figures are recalculated on page load and cached for 5 to 10 minutes.
The 7d / 30d / 90d / YTD buttons change **only** the four headline cards and the ROI card; the funnel,
compliance and course tables are always all-time or current. On today's test data (latest completion
4 April 2026), **only YTD shows figures**. Choose YTD when demonstrating.

**Skill Gap Projection: what it measures today.** It does **not** use the skills catalogue yet. It uses
course categories as a stand-in for skills, per team (an org unit at level 3):
- **Required** = course categories that team members are enrolled in.
- **Covered** = those categories where any member has completed any course.
- **Gap %** = (Required − Covered) ÷ Required × 100. High is 60% or more; Medium is 30% or more.
- "Uncovered skills" are the uncovered category names.

So treat it as a **coverage signal, not a skills gap**. The card's description now says this honestly.
The real skills data model exists (required level per designation, skill per course, each user's held
level), but it is not connected, and it is almost empty locally: 1 designation of about 105 has role
skills mapped, and no user has a recorded skill level. The fix is a code change plus L&D mapping work
(section 4, analytics follow-ups).

Why your UAT card was empty is not confirmed (UAT was out of bounds). Most likely no level-3 team had a
member with an enrolment at the time it was cached. There is a read-only check for after the demo.

**Training ROI: how it is computed.** This is a **model estimate, not an audited ROI.**
- **ROI %** = (total benefit − total cost) ÷ total cost × 100.
- **Benefit 1, productivity** = completions in the period × 2 hours saved × ₹500 per hour.
- **Benefit 2, compliance** = completions on or before a course's end date × ₹1,000 penalty avoided.
- **Cost 1, L&D staff** = every visible course × 40 development hours × ₹500 per hour, **charged in
  full to every period**.
- **Cost 2, platform** = a flat ₹50,000, the same for 7 days or YTD.
- **Cost 3, content** = learners who started in the period × 3 hours × ₹50 per learner-hour.

All 7 numbers are now site-wide settings (Site administration > Plugins > Local plugins > Sentientia
Advanced Analytics). With the defaults, YTD locally is about benefit ₹10.2 lakh against cost ₹84.2 lakh,
**ROI about -88%**. The model has weaknesses a CFO would raise:
- Costs are not scaled to the period.
- "Mandatory" means any course with an end date, not the compliance list, and those completions are
  counted in both benefit lines.
- There are no real spend figures.

Recommendation: keep the ROI flag off for C-suite audiences until model v2 and your own figures are in
(decision D20).

### 3.3 Is certificate verification working?

**Yes.** Valid codes come back green; unknown codes come back "Not verified". It also works for visitors
who are not logged in.
- The code you tested is not on UAT (UAT holds only 7 test certificates). It is also not in the April
  production import, so "Not verified" was the correct answer. It may be a newer certificate on live, a
  typo, or a code from another site. Only the live BizLMS site can tell, so please check it there.
- **Legacy certificates will keep verifying after cutover.** BizLMS already used the same certificate
  plugin: 11,048 issued codes (Aug 2023 to Apr 2026) are in the same table. Stage B upgrades that
  database in place, and printed QR codes keep working if the site address stays `www.airpay.academy`.
- The red "Required" you saw was Moodle's legend for required fields, sitting under the button. It is
  now hidden on this page (QW-CERT-VERIFY).
- Open item: links that carry a code (QR, gallery, LinkedIn) need one more press of Verify. The fix is in
  2.2 and needs your approval.

### 3.4 Email templates: drafts and editing

- **Errors:** every preview failed because of one input-filter bug. Fixed (QW-EMAIL). All 20 templates
  exist, have real content, and render with sample data, so no missing drafts had to be written.
  The standard drafts are the template files; "Revert" in the editor returns to them.
- **Who can edit:** site admins and anyone holding the "manage email templates" capability, in the
  **Design Studio** (Email templates > Templates tab > Design Studio).
- **Tenant scope:** a tenant admin edits only their own tenant's version. A global version (all tenants)
  is for site and cross-tenant admins only. At send time the recipient's tenant version wins, then the
  global one, then the standard file.
- **Gaps still open** (category G):
  1. The edited **subject** is saved but never sent; senders use their own subjects.
  2. All 20 bodies are English only, so Hindi users get English.
  3. The placeholder buttons insert nothing.
  4. A body containing `</textarea>` can break the editor.
  5. There is no version history, only who changed it last and when.

---

## 4. Plan by category

**New documents (19 Nov, after your answers in 6a).** Each ends with its decisions written as questions with
recommended answers, ready for you to choose and approve:
- [`2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`](2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md):
  benchmark against LinkedIn Learning, Coursera, Udemy, Disprz and the Moodle app; must / should / could feature
  list; architecture options compared; recommendation for **D28**; store release before cutover (**D30**); team
  and tooling (**D31**). Supersedes the phases and offline timing in H below.
- [`2026-11-19-APP-STORE-PAPERWORK-PACK.md`](2026-11-19-APP-STORE-PAPERWORK-PACK.md): for CTO review (**D27**):
  checklist with owners (Airpay or us), lead times and costs; store-compliant payments design for India
  (**D29**); draft Apple privacy labels and Google Data safety answers.
- [`2026-11-19-SURVEYS-SCOPE.md`](2026-11-19-SURVEYS-SCOPE.md): complete scope for **D23 / D24** (Microsoft
  Forms parity, external form links, public no-login links, file uploads), sessions and effort. Supersedes F5
  below.

Each table row is one buildable item. "Session" refers to the numbered schedule in section 5.

### A. Course editing parity with BizLMS (content and applicability)

**Your words:** "look at edit course option, e.g. POSH course, how do the admin add/update contents to the
course, applicability etc, feels like we degraded from course edit features we had on BizLMS."

**Today.** You are right; it degraded in three ways:
- **(1) Settings.** The Manage Courses edit pop-up has 4 sections. It lacks course type, skill, level,
  certificate template, course image and enrolment policy. Production data uses these on almost every
  course: type 398 of 422, certificate 388, level 394. POSH 2025 carries a type, a skill, a level and a
  certificate template, and none of them can be seen or changed in Sentientia.
- **(2) Content.** Admins add content through the course's Edit mode (standard Moodle editor). The
  admin icon strip (settings, participants, completion, gradebook, backup) shows only to course creators
  and site admins, so a **trainer has no route to course settings, participants or completion**.
- **(3) Applicability.**
  - BizLMS listed only self-enrol courses, on the learner's own org branch.
  - Sentientia lists every visible course to the whole tenant, so 48 assign-only courses (for example
    POSH for IC Members) now appear in the catalogue.
  - The admin "Enrol users" pop-up shows the first 2,000 users alphabetically, with no filters.
  - Audience-based bulk enrolment already exists, three times (learning paths, classroom, evaluations),
    but not for courses.

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| A1 | Compliance cross-tenant defect; overdue learners locked out (enrolment ends at the deadline) | Tenant guard in the hourly task, no enrolment end date, tenant isolation test | S, 0.5 d | none | none (bug fix) | 1 |
| A2 | Settings parity | One shared field builder used by both the Manage Courses pop-up (now stepped: General, Classification, Completion & certificate, Enrolment policy) and core "Edit course settings" through Moodle's course-form hooks (no core change). Adds type, skill and level, certificate template, course image, completion tracking. | M, 2 d | none | `sentientia.courses.edit_v2.enabled` | 3 |
| A3 | Audience model | One audience table per course (org node at any depth, designation, grade, employment type, cohort) with a live preview count and one-shot assignment beyond the 2,000 cap. The three existing audience resolvers merge into one platform resolver. | L, 3 d | A2, ADR-037 | `sentientia.courses.audience.enabled` | 6 |
| A4 | Standing assignment + compliance | Hourly reconcile plus joiner and mover hooks; the compliance report reads the course audience (one source of truth); catalogue visibility by audience | M, 2 d | A3 | `sentientia.courses.audience_catalog.enabled` | 12 |
| A5 | Course page admin for trainers | A "Course admin" menu for everyone who can update the course (settings, participants, completion, gradebook, backup and import), plus the core course index in Edit mode for non-admin editors | M, 1.5 d | decision D7 | `sentientia.courses.admin_menu.enabled` | 13 |
| A6 | Enrolment policy per course | Open / request / assign-only, seeded from the BizLMS self-enrol value (takes the 48 assign-only courses back out of the catalogue) | S, 1 d | A2 | `sentientia.courses.enrol_policy.enabled` | 13 |

Total: about 10 days. Course types admin page: not now (decision D6).

### B. Assessment integrity and proctoring

**Your words:** "it can be cheated easily, copy paste of questions, switching of tabs etc, for hiring
assessments or skill assessments, this could be a high risk ... like entering into proctoring mode, the way
microsoft certification happens."

**Today.** Our proctoring plugin is not usable: learners are locked out; copy, paste and tab switches are
only logged, never blocked; nothing forces fullscreen; there is no recording or face check; the browser
can forge every signal. Of the 235 BizLMS quizzes, 7 use Moodle's basic browser security. None uses a
password, IP restriction, Safe Exam Browser or proctoring. Only 28 draw random questions from a bank, and
120 show the questions again straight after submitting.

**How Microsoft does it (Pearson VUE OnVUE):**
- A locked-down app plus a system test.
- At check-in: ID photo, headshot and a room scan.
- A live human proctor on webcam and microphone for the whole exam.
- One monitor only.
- Our equivalents: the locked-down app is **Safe Exam Browser** (free, already built into Moodle), the
  check-in is our identity step, and the live proctor would be **bought from a vendor**.

No browser lockdown can see a phone running an AI chatbot. Only a camera, a live proctor or good question
design reduces that risk.

**Recommended model: three security levels, chosen per assessment.**

| Level | For | What it uses |
|---|---|---|
| Standard | Compliance, POSH | Question design (banks, single attempt, review after close) + browser hardening |
| Secure | Skill assessments | Standard + Safe Exam Browser kiosk (one display, no screen capture, no other apps) |
| Proctored | Hiring, skill certification | Safe Exam Browser + our webcam record-and-review |

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| B0 | Question design guide | Admin guide with hiring and skill presets on core settings; consider turning off "review immediately after" in the site quiz defaults (decision D11) | S, 0.5 d | none | none (docs) | 1 |
| B1 | Fix the foundation | Move consent and identity into the quiz's own pre-start check (learners no longer locked out); monitoring restarts on every page; end-of-attempt processing runs on the server; strings to lang (en + hi); tests | M, 3-4 d | ADR-038 | `sentientia.proctoring.enabled` | 4 |
| B2 | Secure assessment mode | Fullscreen gate (questions hidden outside fullscreen); block copy, cut, paste, right-click, print and shortcuts; candidate-name watermark; tab-switch log with server timestamps; warn, then flag, then optional auto-submit; heartbeat so blocking our script is itself flagged; integrity report on the attempt | M, 3-5 d | B1 | `sentientia.assessment.secure_mode.enabled` | 7 |
| B3 | Safe Exam Browser preset | "Security level" in the Exams create form; SEB preset (1 display, no screen capture, camera allowed, quit password); candidate pre-check page; tested on Windows | S-M, 2-3 d | B1 | `sentientia.assessment.seb_preset.enabled` | 9 |
| B4 | Record and review | Live webcam selfie (no ID upload); in-browser face detection (no face, several faces); webcam recording in chunks to India-region storage; periodic re-check; reviewer timeline | L, 5-8 d | B1, D9, D10 | `sentientia.proctoring.recording.enabled` | 15 |
| B5 | Hiring candidate flow | Invite-only candidate accounts that expire; DPDP notice and consent (en + hi); retention task; results to HR; walk-in setup (office laptops with SEB + IP subnet + quiz password) | M, 3-4 d | B3, decision D9 | `sentientia.assessment.candidates.enabled` | 17 |
| B6 | Vendor quote for live proctoring | Talview (India, has a Moodle plugin) and Mercer Mettl (India, hiring focus), for occasional live-proctored roles only | S (you lead) | none | n/a | parallel |

Total build: about 16 to 24 days. B1 must come first; B2 and B3 can run in parallel; B4 needs B1.

### C. Training Program blueprint and external trainers

**Your words:** "L&D team or trainer has scheduled training program: Pre Assessment; Pre Read, watch
material shared ahead of the program, it could be pre work activity as well; Online Training Sessions
Scheduled on MS Teams or ZOOM, or Physical Classroom Session; Post Assessment; Feedback for the training
and Trainer. We need this capability built." And: "In classroom training, how can we add external trainer
account?"

**Today.** About 60% of this exists in **Classroom**: sessions with venue or a Teams/Zoom link field,
roster, waitlist, attendance (with QR check-in behind a flag), calendar files, trainer per classroom and
per session, and feedback forms triggered when an admin marks the classroom Completed. Missing:
- Pre and post assessment links. BizLMS classrooms had pre-test and post-test links; the import dropped
  them.
- A place for pre-read material: classrooms have no linked course.
- Learner completion.
- Session reminders.
- A learner "My trainings" page.
- Feedback bound to a specific classroom and trainer. Today one feedback form fires for every classroom,
  goes to the whole roster rather than the attendees, and can be answered only once per person ever.

**External trainer today:**
1. An admin creates a normal user (Manage Users > Add user, manual login).
2. The admin assigns the Trainer role at system level.
3. The admin picks that user as trainer on the classroom or session.

**Risk:** that person then sees **every classroom and roster in the tenant**, can manage classrooms and
run live sessions, and their account never expires.

**Recommendation:** build "Training Program" as a Classroom with ordered **stages**, plus an
automatically linked **companion Moodle course** that holds the pre-read and the quizzes. Moodle's quiz,
completion and gradebook then work as usual (and in the future mobile app). Sessions, attendance and
feedback stay where they already work. Relabel the existing Programs as **Certification Programs**
(decision D13). Record it in ADR-036.

Stages: pre-assessment, pre-work, session(s), post-assessment, feedback on the training, feedback on the
trainer. Each stage has open/close offsets relative to the first session and an optional pass mark.
Default completion rule: attendance at least 75%, post-assessment at least 70%, both feedback forms
submitted. Report: attendance %, pre and post averages, uplift, training and trainer rating, NPS.

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| C1 | Stages + companion course | Schema, admin "Stages" tab (add, reorder, bind a quiz, activity, session or feedback form) | M, 3 d | ADR-036, D12 | `sentientia.classroom.stages.enabled` | 10 |
| C2 | Feedback for training and trainer | Forms bound to a classroom, course or trainer; attendees only; trainer as the subject; one answer per person **per linked item**; trainer rating. Shared with F1. | M, 2-3 d | ADR-036, D23 (can run beside C1) | `sentientia.evaluation.distribution` | 11 |
| C3 | Enrolment + learner journey | Roster enrols into the companion course; "My trainings" timeline with locks; Join button from 15 minutes before start; calendar file; manager nomination with approval (capacity, waitlist) | M, 3 d | C1 | `sentientia.classroom.learner_journey.enabled` | 14 |
| C4 | Completion + reminders | Scheduled stage status and per-learner completion (certificate and gamification hooks); reminders T-7 pre-work, T-1 session, T+0 post-assessment, T+1 feedback, as editable email templates | M, 2 d | C3 | `sentientia.classroom.completion.enabled` | 16 |
| C5 | Reports | Per training and per trainer: enrolled, pre-work %, attendance %, pre/post average, uplift, pass rate, ratings, response rate; CSV | S-M, 1.5 d | C4, C2 | `sentientia.classroom.reports.enabled` | 18 |
| C6 | External trainer accounts | New user type "external trainer" (amends ADR-017); restricted role (no manage or create); sees own classrooms only, names-only rosters; email invite, manual login; expires at last session + 30 days; audit log. Also limit internal trainers to their own classrooms (D15). | M, 2.5 d | ADR-036 | `sentientia.users.external_trainer.enabled`, `sentientia.classroom.trainer_own_scope.enabled` | 5 |
| C7 | Blueprints | "Save as blueprint" / "Create from blueprint": clones the template course, stages and feedback forms and shifts the dates | M, 2 d | C1, C4 | `sentientia.classroom.blueprints.enabled` | 19 |
| C8 | Teams / Zoom integration (v2) | Create the meeting and pull attendance through the Microsoft Graph and Zoom APIs. Needs IT: Azure app registration, admin consent, Teams application access policy, Zoom server-to-server app. Before then (v1): paste the link, attendance by trainer, QR or self check-in. | L, 5 d | IT setup, [CONFIRM] for live calls | `sentientia.classroom.meeting_api.enabled` | later |

Total: about 21 days (16 without Teams/Zoom). C6 is independent and can go early because of the
visibility risk.

### D. Learning path change management

**Your words:** "once we define contents of learning paths and users are enrolled, we cannot modify the
learning path, but sometimes individual contents of the learning path may need update/replacement with
new, or adding new content."

**Today.** The lock you remember is BizLMS behaviour: once anyone is enrolled, the add, remove and
reorder buttons disappear. Sentientia does not lock, but it is crude:
- Every added course is mandatory and applies to everyone, including learners who already finished.
- Removing a course is a hard delete with no record.
- Imported BizLMS course rows cannot be removed at all.
- There is no replace, no reorder screen, no mandatory/optional switch and no change history.
- Learners enrolled in Sentientia are **never marked Completed**, so they get "path stalled" reminders
  forever.

**Recommendation (Option D, "revisions"):**
- Every published change bumps the path's revision.
- Learners who have not started or are in progress move to the new revision (decision D19).
- Completed learners stay completed, with a frozen record of what they completed.
- **Replace** links the new course to the old one, and the admin chooses each time: "counts as
  completed" (content refresh) or "requires retraining" (for example, a new POSH policy).
- **Remove** becomes "retire": the row and its history stay. This also works for imported rows.
- Every change is logged, and learners whose required set changed get a notice.
- Course certificates are never revoked by a path edit.

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| D1 | Completion engine | Marks native learners In progress / Completed, freezes their list, fires a path-completed event; stops endless "stalled" nudges and fixes the KPI tile. Imported learners untouched. | M, 2 d | none | `sentientia.learningpath.completion_engine.enabled` | 8 |
| D2 | Revision schema + one reader | Revision columns, frozen-list and change-log tables; about 6 places in 4 plugins switch to one helper; flag OFF behaves exactly as today | M, 2-2.5 d | D1, ADR-039 | `sentientia.learningpath.revisions.enabled` | 20 |
| D3 | Admin change screen | Add (mandatory/optional; who it applies to), replace (with equivalence), retire, mandatory toggle, reorder arrows; impact preview counts; publish in the background; History tab | M-L, 2.5-3 d | D2 | same | 21 |
| D4 | Learner side + notices | "What changed" banner on My paths; daily digest email to affected learners only (en + hi template) | S-M, 1-1.5 d | D3 | `sentientia.learningpath.change_digest.enabled` | 22 |
| D5 | Hardening | ADR, tests for every combination, screenshots, UAT rehearsal script | S, 1 d | D4 | n/a | 22 |

Total: about 9 to 11 days. D1 is valuable on its own and should go early.

### E. Programs

**Your words:** "While I am looking at Programs, how the programs work, how do we add content to it?"
(answered in 3.1).

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| E1 | Programs hardening | Enrolling into a program also enrols into its level courses (and back-fills courses added later), as learning paths do; reorder courses inside a level; optional toggle; the all/any rule in the level form; English literals to en + hi strings; Programs link for L&D admins (quick win in session 1) | M, 2 d | D16 | `sentientia.programs.course_enrol.enabled` | 23 |
| E2 | Learner "My programs" | Review and switch on the existing page after E1 (local first, then UAT on your call) | S, 0.5 d | E1 | existing `sentientia.programs.learner.enabled` | 23 |
| E3 | A level item can be a Training Program | So a certification program can include a scheduled training as a step, not just courses. Designed in ADR-036 with C, so there is one model, not three. | M, 3 d | C1-C4 | `sentientia.programs.typed_items.enabled` | later |

### F. Surveys and evaluations like Microsoft Forms

**Your words:** "Surveys or Evaluations should have capabilities like Microsoft Form/Survey."

**Today.** What we already have:
- 7 question types: rating, NPS, yes/no, single and multiple choice, number, long text.
- Show/hide on one earlier answer, drag-and-drop ordering, open/close dates.
- Anonymity at form and question level (stronger than Forms), CSV export, Kirkpatrick analysis.

Missing compared with Forms:
- Likert grid, adjustable rating scale, date, ranking, dropdown.
- **Sections and page breaks.** BizLMS had page breaks, so this went backwards.
- Branching to a section.
- Share link and QR code, reminders, charts, Excel export, a template gallery.
- Binding a form to a specific course, classroom or trainer.

Recommendation: extend our evaluation plugin, not Moodle's Feedback module or Questionnaire (neither is
tenant-aware, and Questionnaire has no confirmed Moodle 5.3 support). Reuse core Moodle for charts,
Excel and QR codes.

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| F0 | Quick wins | See 2.4 | S, 0.5 d | none | `sentientia.evaluation.admin_shortcuts` (new links only) | 2 |
| F1 | Distribution and binding | Bind a form to a course, program, classroom or trainer; share link + QR; invitations on manual assign (today none is sent); reminders at 3 and 7 days; owner and tenant L&D admins notified, not site admins; duplicate form; custom thank-you; template library page with 5 seeded templates (training reaction, trainer feedback, pre-training needs, 30-day behaviour follow-up, pulse/eNPS) | M, 3-4 d | ADR-036 (shared with C2) | `sentientia.evaluation.distribution` | 11 |
| F2 | Question types v2 | Adjustable rating (2-10, stars or numbers, own labels), Likert grid, date, ranking, dropdown, short text, decimals, info/label blocks, optional weighted choices (BizLMS parity) | M, 2-3 d | F1 | `sentientia.evaluation.qtypes_v2` | 24 |
| F3 | Sections and branching | Sections, multi-page with progress bar, branch to a section or the end, server-side path check | M, 2-3 d | F2 | `sentientia.evaluation.sections` | 25 |
| F4 | Results v2 | Charts per question, stacked Likert bars, NPS breakdown, 30-second refresh, filters by course, classroom and trainer, Excel with summary sheet, trainer sees own results (minimum 5 responses) | M, 2 d | F1 | `sentientia.evaluation.results_v2` | 26 |
| F5 | Later | File-upload question, public no-login link with CAPTCHA, per-form header image, course-activity wrapper | L, 3-5 d | decisions D23, D24 | one per feature | later |

Total: about 15 to 19 days.

### G. Email templates

**Your words:** "build all templates with standard content drafts, which can be further edited by admins
other users who have access to it."

**Today** (see 3.4): all 20 templates exist with content and are editable in the Design Studio; the
preview error and the "edited templates lose their footer" bug are fixed.

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| G1 | Security tidy-up | Rule editor whitelist (quick win); the editor textarea no longer prints the body raw (a `</textarea>` breaks out); subject no longer double-escaped | S, 0.5 d | none | none (bug fix) | 1 |
| G2 | Hindi standard drafts | Hindi versions of all 20 bodies, sent to Hindi-language recipients; L&D reviews the wording | S-M, 1.5 d | your reviewer for Hindi copy | none (content) | 27 |
| G3 | Edited subjects are delivered | Placeholder-based default subjects (en + hi); senders use the template's subject | S, 1 d | G2 | `sentientia.emails.subject_override.enabled` | 27 |
| G4 | Editor usability | Working placeholder buttons; version history with restore; preview of the global version from Global scope | S, 1 d | none | none (admin page fix) / history behind `sentientia.emails.history.enabled` | 27 |
| G5 | New templates for new features | Training reminders (C4), learning-path digest (D4), survey reminders (F1) | in those sessions | C4, D4, F1 | their flags | C4, D4, F1 |

### H. Native mobile apps (Android and iOS, learner first)

**Your words:** "We need to build Mobile apps for Android and iOS, instead of using moodle mobile app,
which airpay will launch using their developer accounts, scope everything needed for it. Focus first on
learner facing apps."

**Today:**
- **The app scaffold has never been built.**
  - It points at the live BizLMS site.
  - It uses a mode that Capacitor says is not for production and that Apple may reject as a "repackaged
    website".
  - It is on Capacitor 5, which is out of support.
  - It targets an Android level that Google Play no longer accepts.
- **Native push would be rejected by our server**, and two docs describe a Google API that was shut down
  in 2024.
- **The planned "22 mobile-ready" web services are admin data-table endpoints, not learner ones.** Do
  not run that plan as written.
- **The learner dashboard data is built inside the theme**, so no app can call it.

**Recommendation: a hybrid app on Capacitor 8**, with the app screens bundled into it (Ionic React +
TypeScript):
- **Native screens:** login, home, my learning, catalogue, certificates, inbox, profile.
- **Course content in an in-app logged-in browser view:** SCORM, quiz, H5P, video.
- **Push** through Firebase (FCM HTTP v1) via a new Moodle message output, so every existing
  notification and the user's preferences work automatically.
- **Proctored and hiring assessments show "desktop required".**
- **No purchasing in the app.** Apple and Google would require their own billing; buying stays on the
  web.

**Store checklist (Airpay side):**

| Item | Detail |
|---|---|
| D-U-N-S number | For Airpay Payment Services (look up an existing record first). Longest lead time. |
| Apple Developer Program | Organisation account, $99/year. Standard program, not Enterprise. |
| Google Play Console | Organisation account, $25 one-time. Target API 36 required for new apps and updates. |
| Firebase project | Push for both platforms; APNs key uploaded to Firebase |
| Public web pages | Privacy policy, support URL, account-deletion request page |
| Store forms | Apple privacy labels + privacy manifest file; Google Data safety form |
| Review account | A demo login with synthetic data only, never real employee data |
| Signing and CI | GitHub Actions + Fastlane on a macOS runner (the build box is Windows); Play App Signing |
| Domain files | App links files on the final Sentientia domain (still BizLMS until cutover) |
| Name and bundle ID | "Airpay Academy", `academy.airpay.sentientia` (permanent once published) |
| Login | Password + Azure AD single sign-on through the browser (needs Azure AD sign-in configured on Sentientia) |

**Phases:**

| Phase | Scope | Effort |
|---|---|---|
| 0 (now, parallel) | Airpay admin: D-U-N-S, Apple + Google enrolment, Firebase, privacy/support pages | 0.5 d dev + 2-4 weeks admin lead time |
| MVP (v1) | Sessions M1-M10 below; beta through TestFlight and Play internal testing against UAT | L, about 45-55 days: 9-11 weeks with 1 mobile dev + 1 Moodle dev + QA, plus 1-2 weeks store review |
| Store release | Only after Sentientia cutover onto the production domain | gated |
| v2 | Offline SCORM download and sync (10-15 d); learner actions: classroom check-in, skills self-rate, ratings, live polls, gamification (6-8 d) | L |
| v3 | White-label builds per customer (5-8 d) | M |

| # | Mobile session (one deliverable each) | Effort | Flag (default OFF) |
|---|---|---|---|
| M1 | ADR-040 + API contract + flag registry | S, 1-2 d | n/a |
| M2 | Move the dashboard logic out of the theme into a class; site config, home and my-learning services | M, 5-6 d | `sentientia.mobile.api.enabled` |
| M3 | Catalogue, certificates, pending evaluations, token revoke, custom service, capability review | M, 4-5 d | same |
| M4 | Push output (FCM HTTP v1), device registry, preferences | M, 4-5 d | `sentientia.mobile.push.enabled` |
| M5 | Theme "app mode" (no sidebar or topbar inside the app), logged-in content view | S, 2-3 d | `sentientia.theme.app_mode.enabled` |
| M6 | Capacitor 8 rebuild, login (password + SSO), secure token storage, navigation | M, 5-6 d | n/a (app) |
| M7 | Screens: home, my learning, course outline, content view, proctored-quiz guard | L, 6-8 d | n/a |
| M8 | Screens: catalogue, certificates (download, share, verify), inbox + push + deep links, profile (en/hi) | M, 5-6 d | n/a |
| M9 | CI/CD with Fastlane, signing, TestFlight / internal testing against UAT | M, 3-4 d | n/a |
| M10 | Store readiness: privacy manifest, data safety, labels, screenshots, review notes, device QA | M, 5-7 d | n/a |

### Analytics follow-ups (from your questions)

| # | Item | Recommendation | Effort | Depends on | Flag (default OFF) | Session |
|---|---|---|---|---|---|---|
| AN1 | Skill Gap v2 on real skills data | Required level by designation against each person's held level, per team; top missing skills; a projection column (gaps that close when members finish courses they are already enrolled in that teach the skill). Category view kept only as a labelled fallback. | M, 2-3 d | AN2 data | `sentientia.analytics.skillgap.skills_source` | 24* |
| AN2 | L&D mapping work (not code) | Map the top 10 designations by headcount to skills, and mandatory/core courses to skills, in the existing Skills admin (AI suggestions in mock mode if wanted) | 1-2 weeks elapsed, L&D team | none | n/a | parallel |
| AN3 | ROI model v2 | Annual costs per customer spread over the selected period; development cost only for courses created or updated in the period; "mandatory" from the compliance list; no double counting; CFO wording ("Estimated training value") | M, 2-3 d | D20, D21 | under `sentientia.analytics.roi.enabled` | 26* |
| AN4 | Per-training ROI (Phillips method) | Actual costs per training (trainer, venue, vendor, participant hours from attendance) + feedback + pre/post uplift | L | C5 | with C | later |
| AN5 | Department view for managers | Reuse the compliance viewer scope (L2 managers see direct and extended teams) | M, 2 d | decision D22 | `sentientia.analytics.team_scope.enabled` | later |

\* Can be done in the same week as that session; each is its own deliverable.

---

## 5. Proposed session schedule (revised 19 Nov, after your answers in 6a)

Same rules as before: one session = one deliverable, with tests, both plugin trees in sync, en + hi strings,
a state card, screenshots for UI work and a PROJECT-STATE update. Everything is built and verified locally.
UAT deploys are bundled and happen on your call. Rows marked **changed** or **new** differ from the original
schedule, which is kept below as 5.0. All day counts are estimates.

**What changed and why**
- **D5 / D6:** course settings split into 3a (types, catalogue categories and levels admin) and 3b (every
  BizLMS course field with data, tags, enrolment policy and price).
- **D2:** location joins the audience model (session 6).
- **D18:** learning-path revisions reopen completed learners when a required course is added (20-22).
- **D20 / D21 / D22:** ROI v2 is a full build (26a-26c) plus the manager department view (26d).
- **D23 / D24:** survey reach sessions 11b, 28, 29a, 29b and 30 (`2026-11-19-SURVEYS-SCOPE.md`).
- **D32:** the certificate core-mod moves into session 0.
- **D28 / D29 / D30 / D31 (mobile):** offline SCORM in v1 with a 3-day spike first; no commerce in the app
  (redirecting to Airpay's gateway is not store-compliant in India); store approval before cutover, release on
  cutover day; we build it (`2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`,
  `2026-11-19-APP-STORE-PAPERWORK-PACK.md`).

**Three findings from the re-scope that change priorities** (counts from the 6 April 2026 production backup,
aggregate only):
1. **Course completion certificates may not be issued on Sentientia.** BizLMS issues the course's template
   certificate on every completion (`admin/tool/certificate/classes/observer.php:61-67` in the BizLMS backup).
   Sentientia's copy of `tool_certificate` registers only the `course_content_deleted` observer, and none of the
   9 `course_completed` observers in `moodle-enhancement/local` issues one. 388 of 411 courses have a template.
   This would be a regression at cutover. Verify locally first (Q9, session 1b).
2. **Location data is not where the code looks.** `open_location` and `city` are empty for all 1,407 active
   users. The work city is in `open_region` (83% of Airpay employees, about 237 distinct values, 110 say just
   "India"). The learning-path audience filter matches location on `open_location`, so it matches nobody today.
3. **Grade is empty in production** (0 of 735 Airpay users), so the "grade" audience dimension matches nobody
   until HR sends it.

### 5.1 Track 1: LMS (revised)

| # | Session | Delivers | Days | Needs first |
|---|---|---|---|---|
| 0 | Land today's quick wins (**changed**) | Local deploy + upgrade, PHPUnit, screenshots, commit; **plus the D32 `tool_certificate` verify-on-arrival core-mod** (ADR-041); UAT on your go | 1.5 | XAMPP free (another workflow is deploying) |
| 1 | Defect sweep | A1 compliance guard, G1 email security, 2.4 quick wins, B0 guide, external-trainer runbook | 1.5 | D4 (answered) |
| 1b | Course certificate issuer (**new**) | Confirm the gap; issue the course's template certificate on completion (idempotent, no back-issue) | 1-1.5 | Q9 |
| 2 | Surveys quick wins | F0, including survey defects E2 and E4 (and an interim E1 guard if SV24 = yes) | 0.5 (+0.25) | none |
| 3a | Course lookups admin (**new**, D6) | Course types, catalogue categories and levels on one shared admin component; seeded product defaults | 3-3.5 | Q10-Q13 |
| 3b | Course settings v2 (**changed**, D5) | A2 with every BizLMS field that has data, tags, core course custom fields, **A6 enrolment policy + price** | 4-5 | 3a, Q4-Q8 |
| 4 | Proctoring foundation | B1 | 3-4 | ADR-038, D8 |
| 5 | External trainer accounts | C6 | 2.5 | ADR-036, D14, D15 |
| 6 | Course audience model (**changed**, D2) | A3 + one location helper, per-tenant alias table and alias admin page | 4-4.5 | ADR-037 (amended), Q1-Q3 |
| 7 | Secure assessment mode | B2 | 3-5 | 4, D11 |
| 8 | Learning-path completion engine | D1, storing frozen completed revisions for D18 | 2-2.5 | none |
| 9 | Safe Exam Browser preset | B3 | 2-3 | 4 |
| 10 | Training Program stages + companion course | C1 (+ Certification Programs relabel, D13) | 3 | ADR-036, D12, D13 |
| 11 | Feedback binding + distribution (**changed**) | C2 + F1, **plus survey defect E1 (response context checked on the server) and E3 (notices to owner and tenant L&D)** | 3.5-5 | ADR-036, D23, D25 |
| 11b | External form links (**new**, D23) | Launch with token, privacy notice, self-attest, optional signed callback, setup guide, reports | 3-3.5 | 11, ADR-043, SV1-SV4 |
| 12 | Standing course assignment + compliance source | A4 | 2 | 6, D3 |
| 13 | Course page admin (**changed**) | A5 only (A6 moved to 3b) | 1.5 | D7 |
| 14 | Training learner journey + nomination | C3 | 3 | 10 |
| 15 | Webcam record and review | B4 | 5-8 | 4, D9, D10, budget |
| 16 | Training completion + reminders | C4 | 2 | 14 |
| 17 | Hiring candidate flow | B5 | 3-4 | 9, D9 |
| 18 | Training and trainer reports (**changed**) | C5 + pre/post uplift and ratings fed to ROI as evidence | 2-2.5 | 16, 11 |
| 19 | Training blueprints | C7 | 2 | 16 |
| 20-22 | Learning-path revisions (**changed**, D18) | D2-D5 with always-reopen rules, "Reopened" status, reopen notice (en + hi) | 9-10.5 | 8, ADR-039 (amended), Q14-Q19 |
| 23 | Programs hardening + My programs | E1 + E2 | 2.5 | D16 |
| 24 | Survey question types v2 (+ AN1 Skill Gap v2) | F2 + per-question settings (+ AN1) | 2-3 (+2-3) | 11 (AN2 data) |
| 25 | Survey sections and branching | F3 | 2-3 | 24 |
| 26 | Survey results v2 (**changed**) | F4 (AN3 moved to 26a-26c) | 2 | 11 |
| 26a | ROI v2, part 1 (**new**, D20 / D21) | Cost, benefit and assumption tables; cost entry (annual baseline, per-training costs, impact entries, assumptions, CSV import); privacy | 3-3.5 | ADR-042, Q20-Q27, Finance figures |
| 26b | ROI v2, part 2 (**new**) | v2 engine (proration, no double counting, compliance list, participant time), rollups per training, department and tenant, monthly snapshots | 3-3.5 | 26a |
| 26c | ROI v2, part 3 (**new**) | "How this is calculated" panels, drill-downs, Excel and printable ROI statement, en + hi | 2-3 | 26b |
| 26d | Manager department view (**new**, D22 / AN5) | Reuses the compliance team scope | 2 | 26b |
| 27 | Email: Hindi drafts, delivered subjects, editor | G2-G4 | 3.5 | Hindi reviewer |
| 28 | File-upload question, logged-in users (**new**, D24) | Question type, file serving, permissions, antivirus, retention, privacy | 2-2.5 | 24, SV10-SV13, IT: ClamAV |
| 29a | Public no-login links, anonymous (**new**, D24) | Link admin + QR, public page, reCAPTCHA, honeypot, rate limiter, cap, moderation, audit | 3 | 25, 11, ADR-043, SV5, SV7, SV8, SV22, reCAPTCHA keys |
| 29b | Public links, identified (**new**, D24) | Consent + DPDP notice, erase by e-mail, retention purge (public uploads only if SV10 = b) | 2-2.5 | 29a, 28, SV6, SV9, SV21 |
| 30 | Form look and limits (**new**) | Header image + accent colour, response cap, shuffle and numbering toggles | 1-1.5 | 25, SV15, SV16 |
| later | Teams/Zoom API (C8), Program typed items (E3) | | | IT, ADR-036 |

Track 1 is about **100 to 120 developer days** (was 75 to 90): about 5 to 6 months with one session stream,
about 3.5 months with two. These groups can run side by side: assessments (4, 7, 9, 15, 17); training (10, 11,
14, 16, 18, 19); ROI (26a-26d; 26a needs no code first, only Finance's figures); surveys (11b, 24-30).

### 5.2 Track 2: Mobile (revised; we build it, Airpay teams support)

| # | Session | Delivers | Days | Needs first |
|---|---|---|---|---|
| M0a | Store paperwork pack (D27) | **Drafted 19 Nov** (`2026-11-19-APP-STORE-PAPERWORK-PACK.md`); support the CTO review, then Airpay starts enrolments | 0.5 | none |
| M0b | Store payment rules + D30 options | **Done 19 Nov** (paperwork pack section 2; benchmark section 5) | 0 | none |
| M0c | Competitor research + architecture (D28) | **Done 19 Nov** (`2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`) | 0 | none |
| M0d | Team and tooling setup (D31) | Accounts in place, CI skeleton, test devices, VPN or allow-listed UAT host, signing custody | 1-2 (+ IT lead time) | D31-a to D31-f, CTO review of M0a |
| M0e | Offline SCORM spike (**new**) | Go/no-go: the largest (387.7 MB) and a typical (about 27 MB) SCORM 1.2 package offline on a 2 GB Android 9 phone in a Capacitor 8 test shell; cold start, memory, playback, sync | 3 | D28-c, the package files (local file store is empty), a test phone |
| M1 | ADR-040 + API contract + flag registry (**changed**) | Plus spike result, offline engine design, Apache-2.0 / MIT attribution, no-commerce rule, approve-and-hold release, app host, Moodle-app fork as Plan B | 1-2 | M0e, D28, D29, D30 |
| M2 | Dashboard logic out of the theme; site config, home, my-learning services | as before | 5-6 | M1 |
| M3 | Catalogue, certificates, evaluations, token revoke, capability review | as before | 4-5 | M2 |
| M4 | Push output (FCM HTTP v1), device registry, preferences | as before | 4-5 | M1, Firebase (D27) |
| M5 | Theme app mode, logged-in content view (**changed**) | Plus prices, cart and buy buttons hidden in app mode; cart and checkout routes blocked | 2-3 | M1 |
| M6 | Capacitor 8 rebuild (**changed**) | Replaces the Capacitor 5 scaffold (target API 36, bundled screens); login (password + Azure AD SSO), secure storage, navigation | 5-6 | M0d, M1 |
| M7 | Home, my learning, course outline, online SCORM player, proctored-quiz guard | Low-end phone performance budget | 6-8 | M6, M2 |
| M8 | Catalogue (free + assigned only), certificates, inbox + push + deep links, profile (en / hi / sw) | as before, scope adjusted | 5-6 | M7, M3, M4 |
| M8b | Offline v1 (**new**, D28-e) | Download manager (resume, Wi-Fi-only default, size and free-space checks), app-private storage with wipe, SCORM 1.2 offline runtime, sync queue, "completed offline" time on the server (D28-d), storage manager | 12-18 | M7, spike go |
| M8c | Store compliance (**changed**: was "purchase redirect") | No commerce in the app; commerce messages filtered from inbox and push; iOS hides consumer courses bought on the web; in-app account deletion for self-registered accounts | 2-3 | M8, D29-a to D29-e |
| M9 | CI/CD with Fastlane, signing, TestFlight / Play internal and closed testing on UAT (test accounts only) | as before | 3-4 | M8, D27-g |
| M10 | Store readiness + submission (**changed**) | Privacy manifest, Data safety, labels, screenshots, review tenant + reviewer accounts, security test, device QA; submit 2-3 weeks before cutover and hold | 5-7 | M9, D31-c, D30-a, app host |
| (M-BZ) | Live BizLMS adaptor | Only if D30-a = C (not recommended): throwaway code against 4.1.2 | 10-15 | D30-a |

Track 2 is about **59 to 79 developer days from today** (was 45 to 55): about 9 to 12 weeks elapsed with two
session streams (app side, and Moodle side M2-M5), plus 1 to 2 weeks store review.

### 5.3 ADRs needed (revised)

| ADR | Decision it records | Before session |
|---|---|---|
| ADR-036 Training Programs | as in 5.0 | 5, 10 |
| ADR-037 Course audience (**amended**) | + location source (`open_location`, else normalised `open_region`, else country), alias table, dimensions shown only when the tenant has data | 6 |
| ADR-038 Assessment integrity levels | as in 5.0 | 4 |
| ADR-039 Learning-path revisions (**amended**) | + always-reopen rules; imported BizLMS completions become frozen records (amends ADR-032 for status only) | 20 |
| ADR-040 Native mobile app (**amended**) | Capacitor 8 + bundled Ionic React screens; offline engine; Apache-2.0 / MIT porting; Moodle-app fork as Plan B; no commerce in the app (D29); approve-and-hold release with an app host (D30); we-build team model (D31); one developer account per future customer | M1 |
| ADR-041 Patching vendored plugins | exists; used by D32 | 0 |
| ADR-042 ROI model v2 (**new**) | Cost ledger, formulas, rollups, explainability, financial year | 26a |
| ADR-043 Survey reach (**new**) | External form links, public no-login links, non-user personal data, files and retention | 11b |

### 5.4 What can start this week (20 to 22 Nov)

**No local deploy needed:**
1. CTO review of the paperwork pack; Airpay Legal / Finance looks up the D-U-N-S number (same day if one exists).
2. IT asks: app host DNS and TLS (D30-b), Mac or macOS CI (D31-a), test phones (D31-d), VPN or allow-listed UAT
   host for phones (D31-e), Azure AD registration for the app, ClamAV on UAT and the new server (SV12),
   reCAPTCHA keys check (SV7).
3. ADR drafts: 037 (amended), 039 (amended), 040, 042, 043.
4. Location values export for L&D / HR: distinct `open_region` values with counts only (Q2).
5. Finance request: annual L&D baseline by category, FY 2025-26 and FY 2026-27, per tenant (Q20, Q21).
6. L&D: AN2 skill mapping for the top 10 designations.
7. Mobile spike M0e, as soon as a 2 GB Android phone and the two SCORM package files are available.

**Needs XAMPP, once the other workflow finishes:** session 0 (quick wins + D32), session 1 (defect sweep), the
session 1b check (complete a course that has a template and see whether a certificate is issued, Q9), session 2
(survey quick wins), then session 3a once Q10-Q13 are answered.

UAT stays untouched until you say go.

### 5.5 Questions the revised LMS schedule needs (choose and approve)

Mobile, store and survey decisions are in their own documents: **D28-a to D28-k, D30-a to D30-d, D31-a to
D31-f** (benchmark), **D27-a to D27-h, D29-a to D29-e** (paperwork pack), **SV1 to SV24** (surveys). Earlier draft
questions on D28 research timing, the purchase redirect, survey scoping and store release timing are answered
there.

| ID | Question | Recommended | Alternative | Before |
|---|---|---|---|---|
| Q1 | Which field is "location"? | `open_location`, else normalised `open_region`, else core `country` (one helper) | `open_region` only; or wait for the Keka sync to fill `open_location` | 6 |
| Q2 | Who cleans the ~230 rare values and "India"? | L&D / HR map them once in an alias page (values and counts exported this week); HR fixes the source file | Leave raw values as typed | 6 |
| Q3 | Grade is empty. Keep it in the picker? | Keep, shown only when the tenant has data; HR to send grade | Remove grade until data exists | 6 |
| Q4 | Keep BizLMS catalogue categories next to Moodle categories? | Yes, with a picker and an admin page (session 3a) | Moodle categories only | 3a |
| Q5 | Fields with no data (points, cost, secure course, old audience fields) | Drop from the UI, keep the columns; cost goes to the ROI ledger, audience to A3 | Show them all, as BizLMS did | 3b |
| Q6 | Customer-defined extra course fields | Render core course custom fields in the pop-up (+0.5 d) | Fixed field set | 3b |
| Q7 | Duration, prerequisites, enrolment window | Seeded custom field / core completion rules / enrolment dates | New Sentientia columns | 3b |
| Q8 | Tags / keywords | Core course tags; tenant-scoped catalogue filter; core tag index hidden from learners | Our own tag table | 3b |
| Q9 | Course certificates on completion | Verify locally; if missing, add an issuer in a Sentientia plugin with **no flag** (restores today's live BizLMS behaviour); no back-issue | Behind a default-OFF flag | 1b |
| Q10 | Who manages course types | Tenant L&D admins for their tenant; global types by platform admins | Platform admins only | 3a |
| Q11 | Deleting a type in use | Not allowed: disable, or merge into another (logged) | Delete and clear it from courses | 3a |
| Q12 | Defaults for a new customer | Seed 5 product types; Airpay keeps its imported 7 | Empty list | 3a |
| Q13 | Catalogue-category and level admin pages in the same session | Yes (3a, 3 to 3.5 d) | Types only now (1.5 to 2 d) | 3a |
| Q14 | Learner already completed the added course | Stays completed; the course counts as met | Reopen anyway | 20 |
| Q15 | Due date after reopening | Reopen date + 30 days (per-path setting) | Path's original rule; no due date | 20 |
| Q16 | Reopen imported BizLMS completions too | Yes, keeping the BizLMS completion as the frozen record | Native learners only | 20 |
| Q17 | Path certificate on re-completion | New certificate, new code; the old one stays valid | Re-issue the same code | 20 |
| Q18 | Tell managers | "Reopened" in the team view; no manager e-mail in v1 | Manager e-mail digest | 20 |
| Q19 | Undo | "Revert revision" for 7 days while no reopened learner has progressed | No undo | 20 |
| Q20 | Financial year | April to March, configurable per customer | Calendar year | 26a |
| Q21 | Baseline cost categories | L&D staff, platform, content licences, vendor retainers, other | Your own list | 26a |
| Q22 | Count participant time as a cost | Yes, as its own line, with an "L&D spend only" switch | Exclude | 26b |
| Q23 | Share annual overhead into per-training ROI | Yes, by learner-hours share, shown separately | Direct costs only | 26b |
| Q24 | Benefit sources | Productivity (assumed, per-course override), compliance (list x penalty x likelihood), measured impact entries; uplift and ratings as evidence only | Also turn uplift into money | 26b |
| Q25 | Who enters costs | Tenant L&D admins; platform admins see all | Platform admins only | 26a |
| Q26 | BizLMS classroom costs at Stage B | Import as cost lines if Stage B finds rows (April had none) | Leave in the legacy table | Stage B |
| Q27 | Who sees ROI | L&D admins and leadership; manager department view shows value and cost only where the tenant enables it (default off) | Managers always | 26c |

### 5.0 Original schedule (superseded 19 Nov)

> Kept for reference. Do not plan from it: sections 5.1 to 5.5 above replace it.

One session = one deliverable, with tests, both plugin trees in sync, en + hi strings, a state card,
screenshots for UI work and a PROJECT-STATE update. Everything is built and verified locally. UAT deploys
are bundled and happen on your call.

**Track 1: LMS (one Moodle developer)**

| # | Session | Delivers | Days | Needs first |
|---|---|---|---|---|
| 0 | Land today's quick wins | Local deploy + upgrade, PHPUnit run, screenshots (desktop + mobile), commit; UAT deploy on your go | 1 | none |
| 1 | Defect sweep | A1 compliance guard, G1 email security, the courses / proctoring-docs / LP / analytics / mobile-doc quick wins from 2.4, B0 question-design guide, external-trainer runbook | 1.5 | D4 |
| 2 | Surveys quick wins + evaluation context fix | F0 | 0.5 | none |
| 3 | Course settings parity | A2 | 2 | D5, D6 |
| 4 | Proctoring foundation | B1 | 3-4 | ADR-038, D8 |
| 5 | External trainer accounts | C6 | 2.5 | ADR-036, D14, D15 |
| 6 | Course audience model | A3 | 3 | ADR-037, D1, D2 |
| 7 | Secure assessment mode | B2 | 3-5 | B1, D11 |
| 8 | Learning-path completion engine | D1 | 2 | none |
| 9 | Safe Exam Browser preset | B3 | 2-3 | B1 |
| 10 | Training Program stages + companion course | C1 | 3 | ADR-036, D12, D13 |
| 11 | Feedback binding + distribution | C2 + F1 | 3-4 | ADR-036, D23, D25 |
| 12 | Standing course assignment + compliance source | A4 | 2 | 6, D3 |
| 13 | Course page admin + enrolment policy | A5 + A6 | 2.5 | D7 |
| 14 | Training learner journey + nomination | C3 | 3 | 10 |
| 15 | Webcam record and review | B4 | 5-8 | 4, D9, D10, budget |
| 16 | Training completion + reminders | C4 | 2 | 14 |
| 17 | Hiring candidate flow | B5 | 3-4 | 9, D9 |
| 18 | Training and trainer reports | C5 | 1.5 | 16, 11 |
| 19 | Training blueprints | C7 | 2 | 16 |
| 20-22 | Learning-path revisions (schema, change screen, learner side + hardening) | D2-D5 | 7-8.5 | 8, ADR-039, D17-D19 |
| 23 | Programs hardening + My programs | E1 + E2 | 2.5 | D16 |
| 24 | Survey question types v2 (+ AN1 Skill Gap v2) | F2 (+ AN1) | 2-3 (+2-3) | 11 (AN2 data) |
| 25 | Survey sections and branching | F3 | 2-3 | 24 |
| 26 | Survey results v2 (+ AN3 ROI v2) | F4 (+ AN3) | 2 (+2-3) | 11 (D20, D21) |
| 27 | Email: Hindi drafts, delivered subjects, editor | G2-G4 | 3.5 | Hindi reviewer |
| later | Teams/Zoom API (C8), Program typed items (E3), per-training ROI (AN4), manager analytics (AN5), survey extras (F5), certificate core-mod (2.2, any time after approval) | | | IT, ADRs |

Track 1 is about **75 to 90 developer days**, roughly 4 months with one developer. That shortens to
about 2.5 months with two (sessions 4/7/9/15/17 (assessments) and 10/11/14/16/18/19 (training) can run
side by side).

**Track 2: Mobile (separate mobile developer + Moodle support)**
Phase 0 starts now. Then M1 → M2 → M3 → M4 → M5 → M6 → M7 → M8 → M9 → M10, about 45-55 days.
M2-M5 are Moodle-side work and can overlap with M6 once M1 is agreed.

**ADRs needed**

| ADR | Decision it records | Before session |
|---|---|---|
| ADR-036 Training Programs | Classroom with stages + companion course; blueprints; feedback bound to classroom/trainer; external-trainer user type (amends ADR-017); naming (Training vs Certification Programs); how Program levels can reference a training | 5, 10 |
| ADR-037 Course audience and applicability | One shared audience resolver in the platform plugin; catalogue visibility vs mandatory assignment; compliance reads the audience; per-course enrolment policy | 6 |
| ADR-038 Assessment integrity levels | Standard / Secure / Proctored; Safe Exam Browser; recording storage and retention; candidate accounts; DPDP / SPDI handling of webcam data | 4 |
| ADR-039 Learning-path revisions | Revision ranges; equivalence on replace; soft-retire (amends ADR-032 "protect imported history" for course rows only) | 20 |
| ADR-040 Native mobile app | Hybrid Capacitor 8; `local_sentientia_mobile` service; push output; supersedes ADR-005 Path C and Phase X.1 of the mobile web-service audit | M1 |
| ADR-041 Patching vendored plugins | Rules for tagged hunks in third-party plugins such as `tool_certificate`, with the core-mod record | certificate fix |

---

## 6. Decisions for you

Each has a recommended answer. "Before" is the session that needs it.

| # | Decision | Recommended answer | Before |
|---|---|---|---|
| **Courses** | | | |
| D1 | What does "applicability" control? | Two switches per course: "visible in catalogue to audience" and "assign to audience (mandatory, due in N days)" | 6 |
| D2 | Audience dimensions | Org node at any depth, designation, grade, employment type, cohort. Not location (empty in production data). | 6 |
| D3 | Standing or one-shot assignment | Standing (joiners and movers assigned automatically); never unenrol when a rule changes | 12 |
| D4 | Current compliance rows | Pin POSH 2025, AML, Phishing, Infosec to Airpay; restrict "POSH for IC Members" to an IC cohort; drop the deadline-based enrolment end date | 1 |
| D5 | Classification fields to keep | Course type, level, skills (multi-skill via the existing mapping, with one primary skill), certificate template; Moodle categories only | 3 |
| D6 | Course types admin page | Not now; the imported list is enough until a second customer needs it | 3 |
| D7 | Edit mode for trainers (revisits your decision A2) | Show the core course index in Edit mode to all editors; the viewing sidebar stays as is | 13 |
| **Assessments** | | | |
| D8 | Security level per assessment type | Hiring and skill certification: Proctored. Skill assessments: Secure. Compliance and POSH: Standard. | 4 |
| D9 | Hiring: remote or walk-in | Both. Walk-in on office laptops with Safe Exam Browser + IP subnet + quiz password (strongest and cheapest). | 15, 17 |
| D10 | Webcam proctoring: build or buy; storage | Build record-and-review; get a Talview/Mettl quote for live proctoring only. Store in India region (or Airpay's own server); keep 30 days for candidates, 90 for employees. No automated ID-to-face matching in v1. | 15 |
| D11 | Violations and quiz defaults | Employees: warn and flag only. Hiring: auto-submit after 3 fullscreen or tab violations (configurable). Turn off "review immediately after" in the site quiz defaults for new quizzes. | 7 |
| **Training programs** | | | |
| D12 | Where to build it | Extend Classroom with stages and a companion course (not Programs, not a new plugin) | 10 |
| D13 | Naming | New capability "Training Programs"; relabel today's Programs as "Certification Programs" | 10 |
| D14 | External trainer model | New user type, restricted role, own classrooms only, names-only rosters, email invite with manual login, expires at last session + 30 days, created by tenant L&D admins only | 5 |
| D15 | Limit internal trainers to their own classrooms too | Yes, behind a flag | 5 |
| D16 | Programs: enrol into level courses | Yes, behind a flag, matching learning paths | 23 |
| **Learning paths** | | | |
| D17 | Approach | Option D (revisions), 5 sessions, completion engine first | 8 |
| D18 | Learners who already finished when a course is added | Stay completed with their frozen list; "reopen completed" is an explicit choice with a written reason, `:manage` holders only | 20 |
| D19 | Who moves to a new revision, and when | Not started + In progress move straight away (background task); Completed stay pinned. Imported BizLMS course rows can be retired, not deleted. | 20 |
| **Analytics** | | | |
| D20 | Show ROI before model v2? | No. Keep the ROI flag off for C-suite audiences, or label it "Estimated training value (model)". | 26 |
| D21 | ROI cost basis | Annual figures per customer (L&D staff, platform, content licences) spread over the period; compliance benefit from the compliance list | 26 |
| D22 | Skill Gap source and analytics for managers | Skills catalogue (role level vs held level), category view only as a labelled fallback; L&D maps the top 10 designations first. Managers get a department view using the compliance team scope. | 24 |
| **Surveys** | | | |
| D23 | Engine and binding | Extend our evaluation plugin; one feedback form reusable per course, classroom or trainer (one answer per person per item) | 11 |
| D24 | Public no-login links, file uploads | Not in v1; revisit with external accounts | later |
| D25 | Who sees results | Trainers see aggregate results for their own sessions only, minimum 5 responses; new-response notices go to the form creator and tenant L&D admins | 11 |
| **Email** | | | |
| D26 | Hindi email bodies | Yes, for all 20; L&D reviews the Hindi wording before they go live | 27 |
| **Mobile** | | | |
| D27 | Start store paperwork now | Yes: D-U-N-S lookup, Apple ($99/yr) and Google ($25) organisation accounts, Firebase project | now |
| D28 | Architecture, framework, tenants | Hybrid Capacitor 8 with Ionic React; one app for all three tenants with runtime branding | M1 |
| D29 | Payments, offline, assessments in the app | No purchasing in v1; offline in v2; non-proctored quizzes allowed, proctored and hiring show "desktop required" | M1 |
| D30 | Release timing and Moodle app | Beta against UAT now; store release after cutover; switch off the official Moodle app service 60 days after our app is generally available | M9 |
| D31 | Staffing and spend | One contracted mobile developer, Mac or cloud macOS CI, test devices (a low-end Android, two iPhones); needs the pending budget approval | M1 |
| **Certificates** | | | |
| D32 | Patch `tool_certificate` so links show the result on arrival | Yes, as a recorded core-mod (no flag: it is a bug fix) | any time |
| D33 | The certificate code you tested | Please check it on live BizLMS; if it exists there, it will verify on Sentientia after Stage B | n/a |

---

## 6a. Decisions recorded (Nitin, 19 Nov 2026, ~12:15 IST; D28, D29, D32 updated 15:05)

Nitin answered every decision in section 6 in chat. Where his answer differs from the recommendation it is marked **changed**; those
items need re-scoping before their session (see section 6b). His 15:05 answers to the follow-up questions are marked **15:05**.

| # | Nitin's answer | vs recommendation |
|---|---|---|
| D1 | Both switches (catalogue visibility AND mandatory assignment) | as recommended |
| D2 | Org node, designation, grade, employment type, cohort **plus location** | changed: location included |
| D3 | Standing assignment | as recommended |
| D4 | Pin POSH 2025/AML/Phishing/Infosec to Airpay; IC course to an IC cohort; drop deadline-based end date | as recommended |
| D5 | **All of the above**: recommended set (type, level, skills, certificate template) + every BizLMS course field + tags/keywords | changed |
| D6 | **Build the Course types admin page now** | changed |
| D7 | Show the core course index in Edit mode | as recommended |
| D8 | Tiered: hiring + skill certification Proctored, skill assessments Secure, compliance/POSH Standard | as recommended |
| D9 | Both remote and walk-in (walk-in on office laptops with SEB + IP subnet + password) | as recommended |
| D10 | Build record-and-review; quote live proctoring only; India region; 30/90 days; no auto face match v1 | as recommended |
| D11 | Employees warn/flag; hiring auto-submit after 3 violations; review-immediately off by default | as recommended |
| D12 | Extend Classroom (stages + companion course) | as recommended |
| D13 | "Training Programs" (new) + relabel today's Programs "Certification Programs" | as recommended |
| D14 | Restricted, expiring external-trainer user type | as recommended |
| D15 | Internal trainers limited to own classrooms, behind a flag | as recommended |
| D16 | Program enrolment auto-enrols level courses, behind a flag | as recommended |
| D17 | Revisions (Option D), completion engine first | as recommended |
| D18 | **Always reopen**: adding a required course reopens the path for learners who already finished | changed |
| D19 | Not started + in progress move now (background task); completed stay pinned **except as D18 reopens them** | as recommended (with D18) |
| D20 | **Build ROI fully** (model v2); ROI is skipped in demos as "under development" until then | changed |
| D21 | **Both** cost bases: annual per-customer baseline + per-training costs where entered | changed |
| D22 | Skills catalogue (role vs held level) + manager department view | as recommended |
| D23 | Extend our evaluation plugin **and also allow linking external forms (Microsoft Forms or other tools)** | changed |
| D24 | **Public no-login links and file uploads in v1**, but scope everything first before building | changed |
| D25 | Trainers see aggregates for own sessions, minimum 5 responses | as recommended |
| D26 | Hindi bodies for all 20 emails, L&D reviews wording | as recommended |
| D27 | Start the store paperwork; **Nitin gets it reviewed by Airpay senior developers / CTO** first | changed: review step |
| D28 | **15:05: Nitin: native-class stack, KMP or else Flutter; Capacitor not chosen.** (12:15: not decided; compare with LinkedIn Learning, Disprz, Coursera, Udemy first.) **Updated recommendation (benchmark doc section 4A): Flutter, not KMP.** `webview_flutter` (Google-maintained) plays SCORM with scorm-again (MIT) inside the WebView. Flutter is conditional on a 3-day go/no-go spike on a 2 GB Android 9 phone, which replaces the Capacitor spike M0e. No-go: Capacitor shell, then a Moodle-app fork. Both stacks meet every must; Flutter meets more with ready packages (background downloads, video, push), costs less glue code and has a larger hiring pool in India. Effort about 74-110 days (KMP about 83-126; Capacitor was 59-79). KMP wins only if Airpay's in-house mobile team builds in Kotlin and will co-own the Android app: ask the CTO office before ADR-040 | changed: Capacitor superseded; Flutter recommended, awaiting Nitin's confirmation. Section 5.2 rows M0e, M1, M6 and the ADR-040 row in 5.3 still name Capacitor; update them once confirmed |
| D29 | **15:05: Nitin: consumer purchases in the app via an in-app browser to Airpay PG.** (12:15: redirect out to Airpay's gateway, not in-app purchase; offline access is a must.) | changed. Store risk: iOS India treats this like a link-out (rejection risk); Android allows it only under user choice billing with Play Billing alongside. Mitigations and CTO questions: `2026-11-19-APP-STORE-PAPERWORK-PACK.md` section 2A |
| D30 | **Store release before cutover** | changed |
| D31 | **We build it**, with technical support from Airpay internal teams | changed |
| D32 | Patch tool_certificate as a recorded core-mod. **15:05: extras approved:** `noindex` on the verification page (from the theme), escaping of the holder and certificate names (second core-mod record), ADR-041 accepted | as recommended; extras approved |

D33 is not a decision: Nitin to check certificate code 6342739542AU on live BizLMS.


**D28 update (Nitin, 19 Nov ~16:10): "plan for both".** Run the 3-day go/no-go spike on a 2 GB Android 9 phone in BOTH Flutter and Kotlin Multiplatform (same scope: login, course list, offline SCORM 1.2 player with scorm-again, a video download with pause/resume and speed control, push), compare on the same scorecard, then choose. Plan both spikes in the mobile track (M0e becomes M0e-F and M0e-K).

## 6b. Follow-ups created by the answers

- **D5/D6:** course settings session (3) grows: every BizLMS course field + tags + a Course types admin page. Re-estimate.
- **D18:** learning-path revisions must reopen completed learners when a required course is added (notify them; certificates are still never revoked).
- **D20/D21:** ROI model v2 is a full build with two cost sources (annual baseline + per-training costs); needs a cost-entry UI and per-training rollup.
- **D23/D24:** surveys scope must cover external form links (Microsoft Forms etc.: link, embed, completion tracking options), public no-login links (token, rate limit, spam, privacy notice) and file uploads (storage, virus scanning, retention) before any build. **Done 19 Nov:** `2026-11-19-SURVEYS-SCOPE.md` (questions SV1 to SV24).
- **D27:** prepare the store paperwork pack for CTO review (accounts, D-U-N-S, legal entity, privacy policy, data safety, support URL). **Drafted 19 Nov:** `2026-11-19-APP-STORE-PAPERWORK-PACK.md` (questions D27-a to D27-h).
- **D28:** research comparison with LinkedIn Learning, Disprz, Coursera, Udemy apps; architecture recommendation follows. **Done 19 Nov:** recommendation in `2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md`. **15:05:** Nitin chose a native-class stack (KMP or else Flutter); KMP vs Flutter evaluation and the Flutter recommendation in section 4A of that doc; awaiting his confirmation of Flutter.
- **D29:** check Apple App Store and Google Play rules for steering users to an external payment gateway for digital courses in India (in-app purchase requirements, user-choice billing, reader-app rules); the plan must be store-compliant. **Done 19 Nov:** the redirect is not compliant in India; recommended design and questions D29-a to D29-e in `2026-11-19-APP-STORE-PAPERWORK-PACK.md` section 2. **15:05:** Nitin chose in-app browser checkout to Airpay PG; review risk, mitigations and CTO questions in section 2A of the pack; M8c is re-scoped after the CTO answers.
- **D30:** store release before cutover means the app must work against the live BizLMS site (Moodle 4.1.2) or ship pointed at Sentientia only; scope the dependency. **Done 19 Nov:** approve before cutover, release on cutover day; questions D30-a to D30-d in `2026-11-19-MOBILE-APP-BENCHMARK-AND-ARCHITECTURE.md` section 5.
- **D31:** we build the app in these sessions; Airpay teams give technical support (accounts, signing, review).
- **D32:** implement now with the quick wins (core-mod record + tags). **15:05:** extras approved (theme `noindex`, result escaping, ADR-041 accepted).

---

## 7. Sources

Code and data facts come from the repository, the local XAMPP site (a test import of the April production
backup) and the BizLMS production code backup. UAT was not touched. Several official pages could not be
loaded through the corporate network (certificate errors); facts from those come from search summaries and
should be re-checked before the related session.

**Course editing and audiences**
- Moodle hooks API: https://moodledev.io/docs/apis/core/hooks
- Course form hooks (MDL-73483): https://tracker-old.moodle.org/browse/MDL-73483
- Dynamic cohorts plugin: https://moodle.org/plugins/tool_dynamic_cohorts
- Moodle Workplace dynamic rules: https://docs.moodle.org/en/Dynamic_rules_Use_cases
- Totara audiences: https://totara.help/docs/dynamic-audience-settings

**Assessment integrity**
- Moodle Safe Exam Browser: https://docs.moodle.org/503/en/Safe_exam_browser
- Safe Exam Browser: https://www.safeexambrowser.org/about_overview_en.html
- Microsoft online exams (OnVUE): https://learn.microsoft.com/en-us/credentials/certifications/online-exams and https://www.pearsonvue.com/us/en/microsoft/onvue.html
- Talview Moodle plugin: https://github.com/talview/moodle-quizaccess_proctor
- Respondus Monitor: https://web.respondus.com/respondus-monitor
- Mercer Mettl listing: https://www.capterra.in/software/181233/mercer-mettl-online-examination-and-proctoring-solutions
- MediaPipe face detector (web): https://developers.google.com/edge/mediapipe/solutions/vision/face_detector/web_js
- Window Management API (multi-screen): https://developer.chrome.com/docs/capabilities/web-apis/window-management
- Lockdown browsers and AI assistance: https://elearningindustry.com/why-lockdown-browsers-cannot-see-ai-assistance-and-what-to-do-about-it
- Question design to reduce cheating: https://bookstack.clarkson.edu/books/moodle/page/reducing-the-risk-of-cheating-during-online-quizzes-and-exams
- DPDP Rules 2025: https://static.pib.gov.in/WriteReadData/specificdocs/documents/2025/nov/doc20251117695301.pdf and https://www.taxmann.com/post/blog/spdi-rules-vs-dpdp-act

**Training programs and virtual sessions**
- Graph attendance reports: https://learn.microsoft.com/en-us/graph/api/meetingattendancereport-list
- Graph create online meeting: https://learn.microsoft.com/en-us/graph/api/application-post-onlinemeetings?view=graph-rest-1.0
- Teams application access policy: https://learn.microsoft.com/graph/cloud-communication-online-meeting-application-access-policy
- Zoom participants (developer forum, not official docs): https://devforum.zoom.us/t/meeting-past-participant-list-with-join-leave-times-via-oauth/42232
- mod_zoom and Face-to-face plugins: https://moodle.org/plugins/mod_zoom and https://moodle.org/plugins/mod_facetoface
- Normalised gain (Hake): https://www.physport.org/expert/gain

**Learning-path versioning**
- Moodle learning plans: https://docs.moodle.org/503/en/Learning_plans
- Cornerstone curriculum reversioning (job aid): https://dpm.wi.gov/Documents/JobAids/Cornerstone/Reversion_Curriculum.pdf
- Totara program completion editor: https://totara.help/docs/program-and-certification-completion-editor
- Docebo community on adding courses to live plans: https://community.docebo.com/product-q-a-7/how-to-add-a-new-course-to-a-learning-plan-without-enrolling-existing-users-14317

**Surveys**
- Microsoft Forms branching: https://support.microsoft.com/en-us/office/use-branching-logic-in-microsoft-forms-16634fda-eddb-44da-856d-6a8213f0d8bb
- Microsoft Forms sections: https://support.microsoft.com/en-US/Forms/add-sections-to-your-survey-or-questionnaire-1
- Microsoft Forms settings and results: https://support.microsoft.com/en-us/forms/adjust-your-form-or-quiz-settings-in-microsoft-forms and https://support.microsoft.com/office/check-and-share-your-form-results-02859424-341d-406f-b32a-9a0fbaf357af
- Moodle Feedback activity: https://docs.moodle.org/en/Feedback_activity
- Questionnaire plugin (5.0.3, Moodle 5.0-5.2): https://moodle.org/plugins/pluginversion.php?id=41982

**Analytics and Programs**
- Phillips ROI methodology: https://trainingindustry.com/wiki/measurement-and-analytics/phillips-roi-methodology/
- Moodle Workplace Programs: https://docs.moodle.org/405/en/Workplace_Programs

**Mobile apps**
- Capacitor support policy: https://capacitorjs.com/docs/main/reference/support-policy
- Capacitor 8: https://ionic.io/blog/announcing-capacitor-8
- Apple App Review Guidelines: https://developer.apple.com/app-store/review/guidelines/
- Apple program enrolment (D-U-N-S): https://developer.apple.com/help/account/membership/program-enrollment/
- Google Play target API policy: https://support.google.com/googleplay/android-developer/answer/11926878
- Google Play Data safety: https://support.google.com/googleplay/android-developer/answer/10787469
- Google Play account deletion requirement: https://support.google.com/googleplay/android-developer/answer/13327111
- FCM HTTP v1 migration: https://firebase.google.com/docs/cloud-messaging/migrate-v1
- Moodle app features (URL scheme, SSO): https://docs.moodle.org/503/en/Moodle_app_additional_features
- Moodle app push for custom apps: https://docs.moodle.org/310/en/Mobile_app_Push_Notifications

**Internal records**
- Quick-win state cards: `moodle-enhancement/state-cards/sentientia_emails-state.md`, `sentientia_analytics-state.md`
- Earlier parity audit: `moodle-enhancement/parity-audit-2026-05-15/airpay_courses.md`
- Mobile web-service audit (to be marked superseded): `moodle-enhancement/docs/audits/MOBILE-APP-WS-SURFACE-AUDIT-2026-05-20.md`
- Decision memo (native app funded): `moodle-enhancement/docs/business/DECISION-MEMO-2026-08-04.md`
