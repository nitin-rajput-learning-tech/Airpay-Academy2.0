# local_sentientia_programs

Multi-level certification programmes. Each programme has sequential
levels; each level contains courses; learners progress through levels
in order. Replacement for BizLMS `local_program`.

| Field | Value |
|---|---|
| Component | `local_sentientia_programs` |
| Version | 1.4.0 |
| Depends on | `local_airpay_org`, `local_sentientia_courses` |

## What it does

- Programme container.
- Sequential levels within a programme.
- Courses assigned to a level.
- Per-user enrolment in a programme with level-by-level progress.
- Status workflow: `new → active → (hold ↔ active) → completed / cancelled`.
- Cohort-enrolment (enrol a whole cohort into a programme in one action).

## Tables (4)

- `local_sentientia_programs` — programme containers.
- `local_sentientia_programs_levels` — sequential levels.
- `local_sentientia_programs_courses` — courses assigned per level.
- `local_sentientia_programs_users` — user-to-programme enrolment.

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

Provider exists.

## Open backlog

- Programme-level certificate (currently per-course only).
- Predictive completion-time estimate for a programme based on similar-
  cohort historical data.
