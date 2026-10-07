# Visual evidence owed - courses cluster owner decisions (2026-10-07)

**Status: NOT CAPTURED.** The session that made these changes had no browser access to a build that carries them (the
branch `claude/owner-decisions-y` is not deployed anywhere, and the local XAMPP tree must not be overwritten from a
worktree). CLAUDE.md section 5 requires desktop and 590 px screenshots for every UI change, so Nitin does not review these
changes as finished until the captures below exist. Capture them on the UAT build after the lead merges, save the images in
this folder (`NNN-<what>-desktop.png`, `NNN-<what>-mobile.png`) and tick the list.

Every flag below stays OFF until Nitin has seen the images (`framework.reader_flags_airpay_at_cutover`). No flag was flipped.

## CRS-14 - exam pseudo-courses (catalog 1.0.9-beta, no flag)

- [ ] Guest storefront (`/local/sentientia_catalog/public.php`, not logged in) in a Public tenant that has an exam course
      (April 2026 copy: 5): the exam is NOT listed, the course count is right, the popular rail has no exam.
- [ ] A learner enrolled in an exam course: "Continue learning" / in-progress rail shows the course labelled "Exam"
      (a forum pseudo-course "Forum"; an ordinary course "E-Learning"). Also with the interface language set to Hindi.

## CRS-11 / CRS-12 - course page stars (ratings 1.2.1, flags `sentientia.ratings.widget` and `sentientia.ratings.reactions`)

Course header and course drawer (the two places the theme draws the stars), as a signed-in learner and as a guest:

- [ ] Both flags OFF (default): read-only stars, no focus ring on them (Tab skips them), text alternative present.
- [ ] `sentientia.ratings.widget` ON: interactive stars - hover preview, click saves, the average text refreshes, a second
      click revises; the keyboard (Tab, Enter) works; a guest still sees read-only stars.
- [ ] `sentientia.ratings.reactions` ON, on an item that has likes or dislikes: the thumbs-up / thumbs-down counts sit beside
      the stars without wrapping at 590 px; on an item with none, nothing extra is shown.

## Exams analytics tab (exams 1.7.1, no flag)

- [ ] `/local/sentientia_exams/view.php?id=N&tab=analytics` for an exam where one learner needed several attempts:
      "Pass Rate" is learners who passed over learners who attempted; "Total Attempts" still counts attempts.

## Not UI (nothing to capture)

CRS-01 / CRS-02 / CRS-03 (the enrolments importer and its trail), the privacy providers (emails, talent, core), the parity
check and the enrolment-count readers (analytics KPI, recommender) change no page. The Stage B report for CRS-01 is the
read-only CLI `php local/sentientia_courses/cli/enrolments_access_report.php`; paste its output into the rehearsal report.
