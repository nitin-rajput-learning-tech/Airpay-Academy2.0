# local_sentientia_exams

Examination management — thin wrapper around Moodle `mod_quiz` adding
examination metadata (exam code, attempt window, proctoring flag,
mastery score). Replacement for BizLMS `local_onlineexams`.

| Field | Value |
|---|---|
| Component | `local_sentientia_exams` |
| Version | 1.3.0 |
| Depends on | `local_sentientia_org` |

## What it does

- Exam list with filter / search / sort.
- Exam-detail view with sub-tabs (Overview / Attempts / Roster / Analytics).
- Create / edit / delete exam metadata on top of an underlying Moodle quiz.
- Native enrol UI mirroring `sentientia_courses` Phase F.5.
- No proctoring integration (corrected 2026-11-19): the create-exam form has no
  proctoring toggle and this plugin never reads or writes the proctoring setting.
  Proctoring is the quiz's own "Require proctoring" setting
  (`quizaccess_sentientia_proctoring`), which must stay No until post-demo session 4
  (see that plugin's README).

## Tables

`local_sentientia_exams` — exam metadata associated 1:1 with a Moodle quiz.

`local_sentientia_exams_remind_sent` — dedupe log of the deadline-reminder and
overdue-escalation tasks (one row per learner, exam, bucket and deadline; an
overdue bucket is a negative number of days).

## BizLMS import (ADR-032)

A BizLMS online exam is a COURSE with `open_module = 'online_exams'` and
`open_coursetype = 1` that holds a quiz; BizLMS has no exam table. At cutover
`php local/sentientia_platform/cli/import_bizlms.php --feature=exams` wraps each
quiz of such a course in an exam row (`classes/bizlms/`, registered in
`db/bizlms_import.php`) and marks the overdue escalation of every deadline that
passed before the import as already sent, so enabling `exam_overdue` later cannot
message supervisors about it. Attempts, grades and completions are core data and are
not touched. The legacy `local_onlinetests` table is not read by any reader; the
import refuses to run if it exists with rows. The importer has no UI: the CLI guard
gates it (no feature flag).

## Capabilities (3)

`:view`, `:manage`, `:enrol`.

## Proctoring (corrected 2026-11-19)

Earlier versions of this README said a proctoring toggle on the create-exam form
drives the quiz's proctoring flag. No such toggle exists in this plugin. To harden
an exam today, use the core quiz settings in
`moodle-enhancement/docs/guides/ASSESSMENT-INTEGRITY-ADMIN-GUIDE.md`; the planned
"Security level" choice on the create-exam form is post-demo item B3 (session 9,
flag `sentientia.assessment.seb_preset.enabled`).

## Privacy / GDPR

Privacy provider exists; provides export + delete for the per-user
exam-attempt link table.

## Open backlog

- Standalone "Attempts" tab populated from `mod_quiz` attempt rows.
- Per-exam analytics (pass rate, average score, time-to-attempt).
- Cohort-based exam roster (enrol a cohort rather than individual users).
