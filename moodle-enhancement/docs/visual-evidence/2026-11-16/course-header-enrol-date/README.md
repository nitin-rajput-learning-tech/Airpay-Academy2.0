# Course header: enrolment-date countdown from the viewer's own enrolment (theme 2026111602)

Local only (http://localhost:8080, Moodle 5.1.3+, MariaDB 10.11, PHP 8.2.12, server timezone Asia/Kolkata).
Course 448 ("VP Journey Free Course", persona-journey test data), test personas only, 1440 px, plus 390 and 590 px
AFTER shots added in review (see "Review fixes" at the end).
**Committed on 2026-11-16 on Nitin's word** ("commit the 2026111602 countdown fix"). UAT and live untouched.
The PHPUnit test `theme/sentientia/tests/enrolment_countdown_test.php` is written and not yet run (it runs after the
night PHPUnit init).

## What was wrong

`theme/sentientia/classes/output/core_renderer.php`, `full_header()`. For a non-admin on a course whose BizLMS
column `{course}.open_coursecompletiondays` is set and not 0, the course banner shows "Due In : N days" or
"Overdue by : N days" (`templates/course_full_header.mustache:99`, `{{{display_duedays}}}`). It read the
enrolment date with:

```php
$DB->get_field_sql("SELECT max(ue.timecreated) as enrolldate FROM {course} course
    JOIN {enrol} e ON e.courseid = course.id JOIN {user_enrolments} ue ON ue.enrolid = e.id
    JOIN {user} u ON u.id = $USER->id AND course.id = $COURSE->id ");
```

1. `$USER->id` and `$COURSE->id` were interpolated into the SQL text (`.claude/rules/database.md`: bound
   `:named` params only). Both are server-side ints, so this broke the rule but was not an injection.
2. Nothing tied `ue.userid` to the viewer. The `{user}` join only checked that the viewer exists, so the query
   returned the latest enrolment of ANY user in the course. Every learner saw the same countdown, measured from
   somebody else's enrolment. A viewer who is not enrolled (a tenant admin who can edit the course, the guest
   user, a manager) also saw one.
3. `$duedays` was set only in the "due" and "overdue" branches. On the enrolment day (0 days) and on the due day
   (days = N), `if($duedays !=0 )` read an undefined variable. Shown below: two `PHP Warning: Undefined variable
   $duedays` lines in the Apache error log.

## What changed

| File | Change |
|---|---|
| `theme/sentientia/classes/local/enrolment_countdown.php` (new) | `latest_enrolment_time(int $courseid, int $userid): ?int` runs `SELECT MAX(ue.timecreated) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :courseid AND ue.userid = :userid`. That covers any enrolment method and any status, as before. It returns null when the user has no enrolment or the value is 0, the same as the old `!empty()` check. `due_text(int $enroltime, int $now, int $completiondays): ?string` is the old arithmetic moved unchanged, with `$now` injected. The docblock explains why. Theme code, so no core mod, no ADR and no SENTIENTIA-CORE-MOD tag. |
| `theme/sentientia/classes/output/core_renderer.php` `full_header()` | The SQL and arithmetic (old lines 958-975) are replaced by calls to the class. `$duedays = null` is set first. It is null and not `''` because in PHP 8 `'' != 0` is true, which would print an empty cell. The `is_siteadmin()` gate, the `isset(...) && != 0` guard, `!empty($userenroldate)`, `if($duedays !=0 )` and the HTML literal are unchanged. Tokenizer check: both HTML string literals are byte-identical before and after (485 and 126 bytes, same sha1, see `verify-literal.txt`). Lines are CRLF as before. |
| `theme/sentientia/layout/dashboard.php` (learner recommendations fallback, about line 1063) | Same defect class: `implode(',', array_map('intval', $enrolledids))` was concatenated into an `IN (...)` list. It now uses `$DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'enc')`. Read-only check over every user with an active enrolment: same categories for all 2,687 (`dashboard-equiv.txt`). |
| `theme/sentientia/tests/enrolment_countdown_test.php` (new) | PHPUnit test, written but not run. See "Not covered". |
| `theme/sentientia/version.php` | 2026111601 → 2026111602, release 1.0.66-beta → 1.0.67-beta. Only those two lines changed. Each comment adds a description of this change in front of the old text, which stays byte-identical. |

No other site in `theme/sentientia/classes/` has the defect class. A tokenizer sweep found the old
`core_renderer.php:959` and nothing else in `classes/`. In `layout/` it found only the `dashboard.php` implode
(fixed above). `lib.php` has no SQL. Every other SQL string carries only bound params, `get_in_or_equal`
placeholders, tenant_scope fragments with their params, the fields-API select list or constant literals.

## Which date, and why

The countdown still uses `ue.timecreated`, and still `MAX` over the viewer's enrolments in the course.

- BizLMS's own active reminder task counts the deadline from each user's `ue.timecreated`: `local/courses/classes/task/course_reminder.php:66-76` in the production backup.
- `ue.timestart` was not used. It is 0 on 9,748 of the 9,776 local enrolments in the affected courses (99.7%), and on the other 28 it equals timecreated.
- MAX keeps the header's own choice. A learner enrolled twice, for example manually and by self enrolment, counts from the later enrolment. Locally no user has more than one enrolment in an affected course, so on this data MAX makes no difference.

## Harness numbers (ids and counts only)

`before-harness.txt` and `after-harness.txt` both come from the read-only CLI harness
`D:/Claude Local/kit-scratch/1116/c/enroldate_harness.php`. They cover course 448 and three courses with the column set:

- course 5: N=1, 1,118 enrolled
- course 49: N=15, 22 enrolled
- course 41: N=1, 932 enrolled

For each course the harness lists every enrolled test persona, a sample of other enrolled user ids and three viewers who are not enrolled. For each it gives four things: the old query's date, the user's true own MAX, the countdown the old code prints and the countdown the deployed code prints.

| | BEFORE (old code) | AFTER (deployed) |
|---|---|---|
| Courses 5, 49 and 41: enrolled users shown their own enrolment date | 30 of 2,072 (only the course-wide latest enrollees) | 2,072 of 2,072 |
| Courses 5, 49 and 41: countdown text differs from the user's own | 2,042 of 2,072 | 0 |
| All 28 affected courses (plus 448 at N=40): lookup = the user's own MAX | 83 of 9,776 by date (diagnosis) | 9,779 of 9,779 (`after-harness.txt` §4) |
| Guest (id 1), vp_manager1 (3429) and vp_admin1 (3427), none enrolled, on courses 5, 49, 41 and 448 (448 at N=40) | the old code computes a countdown on every course, for example "Overdue by : 228 days" on course 5. vp_admin1 can open and edit 448 and saw one on screen | none |
| Course 448 at N=40: vp_learner1 (3430) and vp_author1 (3433), enrolled 47.1 days ago | "Due In : 7 days" (from vp_courseauthor1's date) | "Overdue by : 7 days" |
| Course 448 at N=40: vp_courseauthor1 (3435), enrolled 32.6 days ago and the course-wide latest | "Due In : 7 days" | "Due In : 7 days", cell HTML byte-identical in the DOM |
| Course 448 at N=33: everybody | undefined `$duedays`, a PHP Warning per page, no countdown | vp_learner1 "Overdue by : 14 days"; vp_courseauthor1 (due day) no countdown and no warning |

`arith-equiv.txt` checks `due_text()` against the verbatim old arithmetic: 240,000 random and boundary cases in Asia/Kolkata, Europe/London, Australia/Perth and America/New_York, with N passed to the old code as the DB string. Old !== new in 0 cases. It also lists the PHPUnit table rows with the old code's output.

## Course 448 test value (temporary, restored)

No test persona is enrolled in a course with the column set, and course 448 had it NULL. So for the screenshots `{course}.open_coursecompletiondays` on course 448 only was set to 40, then 33, through `$DB->set_field` (`D:/Claude Local/kit-scratch/1116/c/set448.php`). The original value (NULL) was recorded first. It was restored to NULL and verified: `restore-448.txt` says "restored to NULL (recorded original NULL): VERIFIED", and the harness run after it shows the column NULL (`after-harness.txt` §3). No enrolment was changed. The personas already had different enrolment times.

## Screenshots (`#page-header` of `/course/view.php?id=448`, 1440 px)

In view mode the theme hides the countdown cell (`_course-player.scss:138-142`, `.user_enrollment:not(.flex-column)` under `:not(.editing)`). It shows natively only in edit mode, for non-admin editors. Each view-mode persona therefore has two shots:

- the page as the persona sees it (`…-view-1440.png`: no countdown visible);
- `…-revealed.png`: the hidden cell made visible by a style added in the test browser only, with an orange dashed outline. The cell's DOM text is also in the facts JSON.

| Persona | BEFORE | AFTER |
|---|---|---|
| vp_learner1, view (N=40) | `before-n40-vp_learner1-view-1440(-revealed).png`: Due In : 7 days | `after-n40-…`: Overdue by : 7 days |
| vp_author1, view (N=40) | `before-n40-vp_author1-view-1440(-revealed).png`: Due In : 7 days | `after-n40-…`: Overdue by : 7 days |
| vp_courseauthor1, edit (N=40) | `before-n40-vp_courseauthor1-edit-1440.png`: Due In : 7 days | `after-n40-…`: Due In : 7 days (unchanged, own date) |
| vp_admin1, edit, not enrolled (N=40) | `before-n40-vp_admin1-edit-1440.png`: Due In : 7 days | `after-n40-…`: no countdown |
| vp_learner1, view (N=33) | `before-n33-vp_learner1-view-1440.png`: nothing printed (undefined `$duedays`) | `after-n33-…(-revealed).png`: Overdue by : 14 days |
| vp_courseauthor1, view (N=33) | `before-n33-vp_courseauthor1-view-1440.png`: nothing printed (undefined `$duedays`) | `after-n33-…`: nothing printed (the due day), no warning |

Edit mode was switched back off for vp_courseauthor1 and vp_admin1 after each run (`editBackOff: true` in the facts files). There were no console errors and no PHP messages on any page. `php-warnings.txt` shows two `Undefined variable $duedays` warnings in the Apache error log BEFORE at N=33, and none from `core_renderer.php`, `enrolment_countdown` or `dashboard.php` in any AFTER run.

## Deploy (local XAMPP)

Five files were copied to `C:/xampp/htdocs/moodle5/public/theme/sentientia/`, and `cmp` shows each identical to the repo. Then, with cwd `public`:

- `php ../admin/cli/upgrade.php --non-interactive`: 2026111602 Success. It printed the known scssphp `Array to string conversion` warning (`Compiler.php:927`), also seen in the morning's 1116 deploys.
- `php ../admin/cli/purge_caches.php`.

## Regression

`theme_1116b_checks.mjs --phase after --out D:/Claude Local/kit-scratch/1116/c/regress-1116b` was run with that folder's `facts-before.json` copied from `../edit-mode-phone-dark-banner/`. Course 448 had been restored to NULL first.

Result: **41 passed, 0 failed** (A 10, B 3, C 8, D 16, other 4; see `regression-1116b.txt`).

The run also loads `/my/` as vp_learner1 at 390 and 1440 px. That learner is enrolled in 2 active courses. The skills-recommendations flag is on for them but returns 0 courses (read-only CLI check), so the dashboard falls through to the patched `get_in_or_equal` fallback. There were no console errors, and the Apache error log has no lines from `theme/sentientia` for the whole run.

## Not covered

- **PHPUnit:** `theme/sentientia/tests/enrolment_countdown_test.php` is written and passes `php -l`, but it has NOT been run. It runs tonight, after the night job re-initialises the PHPUnit environment (the theme version bump makes it stale). It covers:
  - two users enrolled at different times each get their own date, and a later enrolment in another course does not leak in;
  - users who are not enrolled (including the guest) get null;
  - with manual and self enrolment, the later one wins in both orders;
  - a timecreated of 0 gives null;
  - 19 table rows of `due_text()`, including the boundaries: the same day, 1 s before midnight, the due day, N±1, a future enrolment, N=1 and N=15.
- **Learner view:** no screenshot shows the countdown on screen for a learner, because the theme hides it in view mode (see seen-not-fixed). The `-revealed` shots show the DOM content.

## Seen, not fixed (out of scope; behaviour kept as it was)

- `due_text()`: nothing is shown on the enrolment day (0 days) or on the due day (days = N). Only the undefined variable is fixed. 27 of the 28 local courses have N=1, so a learner enrolled yesterday sees nothing.
- The strings are hard-coded English ('Due In : ', ' days ', 'Overdue by : '), with no lang string, no Hindi parity and no plural ("1 days").
- The HTML is built in PHP and printed raw by `{{{display_duedays}}}` (`course_full_header.mustache:99`). Today it holds only literals and numbers. The `details_content` label span is empty, and the `fa-calendar` icon has no `aria-hidden`.
- Dates are computed in the server's PHP timezone, not the user's (`$CFG->forcetimezone` = 99 lets users pick one). In a DST timezone the day count can be fractional ("28.041666666667 days" in Europe/London), and `abs()` counts a future enrolment as days already passed.
- There is no completion check: "Overdue by" keeps showing after the learner completes the course. Both BizLMS reminder tasks skip completed users.
- `if($duedays !=0 )` relies on PHP 8 string/int comparison; under PHP 7 the countdown never rendered.
- `_course-player.scss:138-142` hides the countdown in view mode for every learner. It is visible only to non-admin editors in edit mode, so the people it is meant for never see it.
- No status filter: suspended enrolments and disabled enrolment methods still count toward the latest date. The spec says "any method"; locally there are 0 such rows on the affected courses.
- `local/sentientia_courses/classes/task/course_reminder.php` counts from `ue.timestart` and requires it to be > 0, while BizLMS and this header use `timecreated`. On 99.7% of the local enrolments timestart is 0, so the reminder would skip them. Its two trees also differ (moodle-enhancement copy vs top-level `local/`).
- Local data: 27 of the 28 affected courses have `open_coursecompletiondays` = 1, which looks like a BizLMS placeholder, so every enrolled learner reads as overdue (for example "Overdue by : 1241 days"). This is a data or config question for Nitin.
- `layout/dashboard.php:80`: a hard-coded `ra.roleid = 9` (the BizLMS administrator role) in the onboarding-redirect check. Its params are bound; it is a portability problem, not this defect class.
- `full_header()` still lists `$DB` in its `global` line, though nothing in it uses `$DB` any more.

## Review fixes

The review found one SHOULD-FIX: every screenshot was 1440 px, and CLAUDE.md section 5 asks for desktop and a mobile
breakpoint. The countdown cell does render at phone widths for a non-admin editor in edit mode: the theme hides it
only under `:not(.editing)` (`_course-player.scss:138-142`). The 1116b regression run loaded course 448 at 390 and
590 px only after 448 was back to NULL, so none of its shots has a countdown.

Fix: AFTER shots at 390x844 and 590x1000 with
`D:/Claude Local/kit-scratch/1116/c/countdown_shots_mobile.mjs`. It uses the same DOM read and the same reveal style
as `countdown_shots.mjs`. Edit mode is switched on at 1440 px, as `theme_1116b_checks.mjs` does, and the page is then
reloaded at each phone width. No code changed, so there was no deploy, version bump, cache purge or regression re-run.
The 5 theme files were checked again with `cmp`: served == repo.

Course 448 test value: `set448.php --set=40` before each of the two runs, then `--restore` after each. Both times:
"restored to NULL (recorded original NULL): VERIFIED". The value at the end is NULL. No enrolment was changed.

| Persona, width | Shots | What they show |
|---|---|---|
| vp_courseauthor1, edit, 390 px | `after-n40-vp_courseauthor1-edit-390.png` (`#page-header`), `…-390-viewport.png` (top of the page) | "Due In : 7 days", shown natively, cell inside the viewport width, no sideways scroll |
| vp_courseauthor1, edit, 590 px | `after-n40-vp_courseauthor1-edit-590.png`, `…-590-viewport.png` | the same |
| vp_learner1, view, 390 px | `after-n40-vp_learner1-view-390.png` (as the learner sees it: no countdown), `…-390-revealed.png` | "Overdue by : 7 days" (own enrolment, 47.1 days ago), hidden by the theme in view mode, revealed cell inside the viewport width |
| vp_learner1, view, 590 px | `after-n40-vp_learner1-view-590.png`, `…-590-revealed.png` | the same |

Checks (`review-fix-mobile.txt`, `after-n40-mobile-facts.json`):

- 4 of 4 page loads OK in both runs: no PHP message, no console error, banner present.
- The Apache error log grew by 0 bytes during each run.
- Edit mode was switched back off for vp_courseauthor1 (`editBackOff: true`, it was off before).
- The countdown cell's outerHTML at 390 and 590 px is identical to the 1440 px AFTER facts for both personas. For
  vp_courseauthor1 it is also identical to the 1440 px BEFORE facts. This change alters only which enrolment date
  feeds the text; the cell markup and layout are unchanged at every width.
- There are no BEFORE shots at phone width. Taking them would have meant redeploying the old code, and the
  vp_courseauthor1 cell is byte-identical before and after anyway.

`after-harness.txt` section 5 is the read-only harness re-run for this review. 5a is 448 at 40, during the shots:
vp_learner1 and vp_author1 "Overdue by : 7 days", vp_courseauthor1 "Due In : 7 days", the guest, vp_manager1 and
vp_admin1 no banner. 5b is the all-pairs check: 9,779 of 9,779 pairs get their own MAX. 5c is 448 restored to NULL:
the banner is not computed, and the courses 5, 49 and 41 totals are 2,072 of 2,072.

Seen at phone width, not fixed (theme layout, not from this change):

- At 390 and 590 px the countdown text breaks across two lines ("Due In :" over "7 days", "Overdue" over
  "by : 7 days"), although the cell is 380 and 580 px wide. Likely cause, not verified in the browser:
  `custom_media.scss:710-713` gives every `.details_content` `width: 100% !important` at 768 px and below. That rule
  is in the "Manage Company" card block, and the countdown's empty `details_content` label span then takes the row's
  width.
- In edit mode at 390 and 590 px the fixed top bar covers the banner's title card, at the top of the page too. The
  1116b regression run's `01-courseauthor-edit-390.png` (course 448 at NULL, no countdown) shows the same, so it
  predates this change.
