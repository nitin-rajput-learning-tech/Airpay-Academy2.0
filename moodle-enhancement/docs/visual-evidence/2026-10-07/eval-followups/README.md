# Visual evidence: local_sentientia_evaluation follow-ups (branch claude/eval-followups)

**Status: screenshots OUTSTANDING.** The build session could not browse or deploy anything (no copy into XAMPP, no web
session). This folder holds the list of shots Nitin needs, how to reach each page, and the one thing that needs no
screenshot (the CSV header). Do not merge the UI items below before the shots exist.

State card: `moodle-enhancement/state-cards/sentientia_evaluation-state.md`, sections "2026-10-01 - evaluation
follow-ups" and "2026-10-07 - evaluation decisions implemented". The first set of items (EV-02, 03, 05, 09, 31 and the
review round) has no flag of its own (EV-FIX-FLAGS, decided A: visual evidence only). The 2026-10-07 decisions add two
flags and a schema change, so their rows below say which flag has to be ON on the test site (turning a flag on is
Nitin's call, on a test site only): `sentientia.evaluation.response_drilldown` (EV-06, new, default OFF) and
`sentientia.evaluation.learner_history` (ADR-032, default OFF, needed for the EV-18 set). Plugin version `2026100702`
(`evaluationmode`, EV-17, and its back-fill) on platform `2026100701`: run the upgrade first.

**Merge gate (EV-FIX-FLAGS):** `claude/eval-followups` is merged only after PHPUnit and the shots in this list. The
`response_list` and `response_detail` shots wait for the EV-06 flag to be ON on a test site.

How to take them: log in as a TENANT admin (the manager role, not the site admin, so the ADR-031 scope applies), 590 px
wide for the mobile shot (the theme's primary mobile breakpoint) and a normal desktop width for the other. Name the files
`<item>-<page>-desktop.png` and `<item>-<page>-mobile.png`.

| Item | Page | What to look at | Needs first |
|------|------|-----------------|-------------|
| EV-03 | `responses.php?id=<form>` | A form that holds a number question, a tick-all-that-apply question and one older type: the average with "Lowest x / Highest y" (shown as stored: a 7.125 is not "7.13") and the allowed range; the bars for 1..5; the tick-all bars with the "one person can tick several options" note; the quoted text with an ampersand shows once-escaped. The page title, the heading and the name above the figures also show an ampersand in the form's name once-escaped | A form with answers, named with an ampersand ("Tom & Jerry"). Also the imported rehearsal form (April form 3) after an import, if the rehearsal copy is available |
| EV-03 (review) | `responses.php?id=<form>` | A number question that holds a stored answer that is not a number: the line "Stored answers that are not numbers are left out of these figures: N", figures of the numeric answers only, no bars; and one that holds only such answers: "No answers yet" and the line, no "Lowest 0, highest 0" | A number question with a text answer put into `response_data` by hand (or by the import) |
| Review | `responses.php?id=<form that once collected anonymous answers>` | The "Anonymous responses" badge shows although the form's anonymous flag is off | A form with one answer stored with user id 0 and the flag unticked |
| EV-09 | `index.php` | One imported row (an "Imported" badge, only Questions and Responses actions) next to one native row (all actions) | An imported form and a native one |
| EV-09 | `questions.php?id=<imported numeric form>` | The info notice, no Add / drag / row menu, the number type named "Number" with its range, the position badge counting 1..n. The heading, the breadcrumb and the page title show the form's name once-escaped: the ampersand reads "&" (not "&amp;amp;"), and the breadcrumb reads exactly as the heading does, with no bold "x" in the crumb alone (the breadcrumb's last crumb is printed raw by the theme, so it only matches the heading when the page formats the name itself). Zoom the breadcrumb for this shot | A form named `Tom & Jerry <b>x</b>`, made through `import_template.php` (a template JSON whose evaluation `name` is that text: the template import stores the name as uploaded, the edit form would clean it first), then opened on `questions.php`. The ampersand alone does not prove the breadcrumb is escaped; the tag does |
| EV-31 | `respond.php?id=<named form>` as the invited learner | The form itself, not the thank-you page | A named, non-pulse form with a fired trigger (so a pending shell exists) for that learner |
| EV-31 | `responses.php?id=<same form>` | "Total Responses" does not count the invitation | The same form |
| Review | `respond.php?id=<form>` as a learner | The position badges count 1..n (they printed the question id + 1); a number question with a lower bound of 0 and a multiple-choice question with an ampersand in its text and one option: the ampersand shows once-escaped, the number box carries its range, and the bounds of a number question are not offered as options; the form's name in the heading, with an ampersand, once-escaped | A form that is not the first in the database (so ids are not 1, 2, 3), with a number question 0..10, a choice question and an ampersand in the name |
| EV-02 | `response_list.php?id=<imported supervisor form>` | A Respondent and a Subject column, both in the site's name format (as the CSV prints them); "(deleted user)" for a subject whose account is gone; no row for an invitation that was never answered. The form must be marked `SP` (`evaluationmode`, EV-17), or the Subject column is not drawn | The EV-06 flag ON, a tenant admin (manager role), and an imported supervisor form (`SP`, named, not anonymous) |
| EV-05 | `response_detail.php?id=<response>` | The respondent's own answer highlighted, option histograms for choice questions (a choice whose text is "0" included), the numeric average, and the respondent named in the site's name format (EV-06: `fullname()`, as the list and the CSV do). A second shot, of an id that is an unanswered invitation (a trigger shell, `timesubmitted` 0): it answers "Response not found." This is the only page-level proof, because the PHPUnit test covers the helper `require_submitted_response()`, not that `response_detail.php` calls it | The EV-06 flag ON, a tenant admin, and a form with a fired trigger so a shell exists |
| EV-06 | `response_list.php?id=<form>` and `response_detail.php?id=<response>` with the flag OFF | The "not available" notice (`response_drilldown_unavailable`), for a tenant admin, exactly as the learner history page answers when its flag is OFF; and `responses.php` with NO "Individual responses" link | The EV-06 flag OFF (the default), a tenant admin |
| EV-06 | `responses.php?id=<form>` with the flag ON | The "Individual responses" button beside "Export CSV", and where it leads | The EV-06 flag ON, a tenant admin |
| EV-06 (review round 2026-10-07) | `response_list.php?id=<form that once collected an anonymous answer>` and `response_detail.php?id=<one of its responses>` | A protected form offers NO individual responses: each page shows the info notice ("The responses to this evaluation are anonymous, in whole or in part, so individual responses are not available: only the totals are shown...") and a "Back to the totals" button that leads to `responses.php`; no list, no answers, no respondent. (Until this round the pages named nobody but still listed each response with its day and its course, program or classroom, and printed one person's free text beside it.) Also the Hindi notice | The EV-06 flag ON, a tenant admin, a form with one answer stored with user id 0 |
| EV-06 (review round 2026-10-07) | `responses.php?id=<same protected form>` | No "Individual responses" button, and a muted line under the header ("Individual responses are not available for this evaluation because its responses are anonymous, in whole or in part. The totals below are still shown."); the totals are unchanged. A named form still draws the button | The EV-06 flag ON, a tenant admin, one protected and one named form |
| EV-06 (review round 2026-10-07) | `response_list.php?id=<form of tenant B>` as a SITE admin, with the flag ON for tenant A only | "Not available" for the tenant whose flag is OFF (the flag is read for the EVALUATION's tenant, not the viewer's); the other tenant's form opens. Needs a per-tenant override of the flag, which is Nitin's call on a test site | The flag set for one tenant only (a test site), a site administrator |
| EV-06 | `response_list.php?id=<form>` as a TRAINER (teacher archetype) and as an employee | The refusal (no permission), flag ON: trainers and employees get nothing | The EV-06 flag ON, a trainer persona and an employee persona |
| EV-17 | `my_evaluations.php` as the PERSON EVALUATED | The imported supervisor forms are NOT listed: a named one, an anonymous one (no subject kept) and one from before `evaluatedby` existed; their self evaluations are | The `learner_history` flag ON, imported forms with `evaluationmode` SP and SE for one test person |
| EV-18 and EV-20-NOTE | `my_evaluations.php` as a learner, en and hi | The set Nitin reviews before the production flip: named responded, anonymous with the NEW note ("your name is not shown with your answers in its results"), waiting, closed, and the imported badge; desktop and 590 px, one Hindi shot of the note. A test persona and test forms only: the local copy holds real users, so no real name may appear | The `learner_history` flag ON on a test site (Nitin's call) |

EV-11, EV-13, EV-14, EV-20, EV-21, EV-28 and EV-32 have no UI. Of the 2026-10-07 decisions, EV-16, EV-TENANT, EV-36 and the three framework items (EV-26, the row-bound acceptances and the child-table preflight) have no UI and need no screenshot (EV-36 changes only what a web service accepts; the form already refused an empty rule). EV-33 is tests only. The review round of 2026-10-07 (the
rows marked "Review" and the rows it extended) changed `respond.php`, `responses.php`, `questions.php`,
`response_list.php` and `response_detail.php`; `exportcsv.php` changed only in the Subject header (below) and in how it
reads names (the respondents and subjects in one query each: the file it writes is the same).

## CSV export (EV-02): a sample, no screenshot needed

`exportcsv.php?id=<form>`. A native form is unchanged:

```
Submitted,Respondent,Email,Course ID,Program ID,Classroom ID,Q1: <question text>,...
```

A form where some response names a person it is about (an imported supervisor evaluation that is not anonymous) gets one
more column, after Email:

```
Submitted,Respondent,Email,Subject,Course ID,Program ID,Classroom ID,Q1: <question text>,...
```

The Subject cell holds that person's name (in the site's name format, as `response_list.php` prints it), is empty for a
response with no subject, reads `(deleted user)` when the account is gone, and is never present on an anonymous or
otherwise identity-protected form. Every header is plain English, `Subject` included, whatever the language of the admin
who exports it (the page's own column heading is the translated string `responses_col_subject`).
