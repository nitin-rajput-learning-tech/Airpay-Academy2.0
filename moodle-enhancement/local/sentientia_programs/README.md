# local_sentientia_programs

Multi-level certification programmes. Each programme has sequential
levels; each level contains courses; learners progress through levels
in order. Replacement for BizLMS `local_program`.

| Field | Value |
|---|---|
| Component | `local_sentientia_programs` |
| Version | 1.9.0 |
| Depends on | `local_sentientia_org`, `local_sentientia_courses` |

## What it does

- Programme container.
- Sequential levels within a programme.
- Courses assigned to a level.
- Per-user enrolment in a programme with level-by-level progress.
- Status workflow: `new → active → (hold ↔ active) → completed / cancelled`.
- Cohort-enrolment (enrol a whole cohort into a programme in one action).

## Tables (7)

- `local_sentientia_programs` — programme containers.
- `local_sentientia_programs_levels` — sequential levels (`completion_rule`: `all` or `any`).
- `local_sentientia_programs_courses` — courses assigned per level.
- `local_sentientia_programs_users` — user-to-programme enrolment (`enrolledby` names who enrolled the learner).
- `local_sentientia_programs_lvlcomp` — stored level completions (BizLMS history, ADR-032).
- `local_sentientia_programs_trainers`, `_trainerfb` — trainers and feedback on them (carried by the import; no writer yet).

## BizLMS import (ADR-032)

`classes/bizlms/` holds the `program` importer for the 12 tables of BizLMS `local_program`, registered in
`db/bizlms_import.php` and run by `local_sentientia_platform/cli/import_bizlms.php`. See the state card
(2026-09-30) and mapping doc section 16. Two default-OFF flags cover the readers of imported history:
`sentientia.programs.learner.enabled` (the learner page `myprograms.php`) and
`sentientia.programs.history.enabled` ("Completed on" column and program logo on the admin pages).

## Capabilities (6)

`:create`, `:update`, `:delete`, `:enrol`, `:manage`, `:view`.

## Phase 5 work

G-03 (commit `771508688`) — levels CRUD + courses + enrol UI shipped.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_programs/cli/smoke_prereq.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_programs/cli/smoke_enrol_cohort.php"
```

## Privacy / GDPR

`classes/privacy/provider.php` covers `local_sentientia_programs_users`
(enrolment + completion). Core's erasure (`delete_data_for_user`) deletes the
rows. The Sentientia DPDP erasure (`local_sentientia_privacy`) calls
`anonymise_data_for_user` instead (2026-09-24), which keeps them - the
certification record - keyed to the anonymised user row.

## Open backlog

- Programme-level certificate (currently per-course only).
- Predictive completion-time estimate for a programme based on similar-
  cohort historical data.
