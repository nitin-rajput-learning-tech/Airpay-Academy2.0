# Visual evidence: local_sentientia_evaluation follow-ups (branch claude/eval-followups)

**Status: screenshots OUTSTANDING.** The build session could not browse or deploy anything (no copy into XAMPP, no web
session). This folder holds the list of shots Nitin needs, how to reach each page, and the one thing that needs no
screenshot (the CSV header). Do not merge the UI items below before the shots exist.

State card: `moodle-enhancement/state-cards/sentientia_evaluation-state.md`, section "2026-10-01 - evaluation
follow-ups". No flag is involved in these items (only the learner history page is flagged, and it is unchanged), no
version bump, no schema change.

How to take them: log in as a TENANT admin (the manager role, not the site admin, so the ADR-031 scope applies), 590 px
wide for the mobile shot (the theme's primary mobile breakpoint) and a normal desktop width for the other. Name the files
`<item>-<page>-desktop.png` and `<item>-<page>-mobile.png`.

| Item | Page | What to look at | Needs first |
|------|------|-----------------|-------------|
| EV-03 | `responses.php?id=<form>` | A form that holds a number question, a tick-all-that-apply question and one older type: the average with "Lowest x / Highest y" and the allowed range; the bars for 1..5; the tick-all bars with the "one person can tick several options" note; the quoted text with an ampersand shows once-escaped | A form with answers. Also the imported rehearsal form (April form 3) after an import, if the rehearsal copy is available |
| EV-09 | `index.php` | One imported row (an "Imported" badge, only Questions and Responses actions) next to one native row (all actions) | An imported form and a native one |
| EV-09 | `questions.php?id=<imported numeric form>` | The info notice, no Add / drag / row menu, the number type named "Number" with its range, the position badge counting 1..n | The same imported form |
| EV-31 | `respond.php?id=<named form>` as the invited learner | The form itself, not the thank-you page | A named, non-pulse form with a fired trigger (so a pending shell exists) for that learner |
| EV-31 | `responses.php?id=<same form>` | "Total Responses" does not count the invitation | The same form |
| EV-02 | `response_list.php?id=<imported supervisor form>` | A Subject column after Respondent | EV-06 decided (the page needs `local/sentientia_evaluation:view`, which `db/access.php` does not declare) and an imported supervisor form |
| EV-05 | `response_detail.php?id=<response>` | The respondent's own answer highlighted, option histograms for choice questions, the numeric average | EV-06 decided |

EV-11, EV-13, EV-14, EV-20, EV-21, EV-28 and EV-32 have no UI. EV-33 is tests only.

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

The Subject cell holds that person's name, is empty for a response with no subject, reads `(deleted user)` when the
account is gone, and is never present on an anonymous or otherwise identity-protected form.
