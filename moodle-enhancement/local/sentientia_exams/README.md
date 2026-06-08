# local_sentientia_exams

Examination management — thin wrapper around Moodle `mod_quiz` adding
examination metadata (exam code, attempt window, proctoring flag,
mastery score). Replacement for BizLMS `local_onlineexams`.

| Field | Value |
|---|---|
| Component | `local_sentientia_exams` |
| Version | 1.3.0 |
| Depends on | `local_airpay_org` |

## What it does

- Exam list with filter / search / sort.
- Exam-detail view with sub-tabs (Overview / Attempts / Roster / Analytics).
- Create / edit / delete exam metadata on top of an underlying Moodle quiz.
- Native enrol UI mirroring `sentientia_courses` Phase F.5.
- Integration with `local_sentientia_proctoring` via the `:enrol` cap +
  proctoring toggle on the create-exam form.

## Tables

`local_sentientia_exams` — exam metadata associated 1:1 with a Moodle quiz.

## Capabilities (3)

`:view`, `:manage`, `:enrol`.

## Phase 8.1 dependency

The proctoring toggle on the create-exam form drives the `quiz_X_enabled`
flag in `quizaccess_airpay_proctoring`. Phase 9 N7 migrated that flag
from `mdl_config_plugins` to a relational table — the integration
remains transparent to this plugin.

## Privacy / GDPR

Privacy provider exists; provides export + delete for the per-user
exam-attempt link table.

## Open backlog

- Standalone "Attempts" tab populated from `mod_quiz` attempt rows.
- Per-exam analytics (pass rate, average score, time-to-attempt).
- Cohort-based exam roster (enrol a cohort rather than individual users).
