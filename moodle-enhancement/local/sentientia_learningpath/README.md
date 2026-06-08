# local_sentientia_learningpath

Sequenced learning paths — ordered course sequence with prerequisite
enforcement. Replacement for BizLMS `local_learningplan`.

| Field | Value |
|---|---|
| Component | `local_sentientia_learningpath` |
| Version | 1.3.0 |
| Depends on | `local_airpay_org`, `local_sentientia_courses` |

## What it does

- Learning path definition (ordered list of courses).
- Per-user enrolment in a learning path with progress tracking.
- Prerequisite enforcement: a learner cannot start course N+1 until
  course N is marked complete.
- Assign-courses + enrol-users UI (shipped Phase 5 G-04, commit `fefbe49ce`).
- Standalone CSV export (shipped commit `7652579ae`).

## Tables (3)

- `local_sentientia_learningpath` — path container.
- `local_sentientia_learningpath_courses` — ordered list of courses in a path.
- `local_sentientia_learningpath_users` — user-to-path enrolment.

## Capabilities (6)

`:create`, `:update`, `:delete`, `:enrol`, `:manage`, `:view`.

## Verify after install

PHPUnit tests cover the CRUD + prerequisite enforcement.

## Privacy / GDPR

Provider exists; DSR export bundles user-to-path enrolment rows.

## Open backlog

- Conditional branching (if learner scored ≥85, skip the remedial
  module).
- Per-path certificate (currently certificates are per-course only).
