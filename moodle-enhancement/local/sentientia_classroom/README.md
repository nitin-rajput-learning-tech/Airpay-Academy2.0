# local_sentientia_classroom

Instructor-led training. Sessions, attendance, locations, waiting list,
trainer assignments. Replacement for BizLMS `local_classroom`.

| Field | Value |
|---|---|
| Component | `local_sentientia_classroom` |
| Version | `2026050900` (1.6.0) |
| Depends on | `local_sentientia_org`, `local_sentientia_evaluation` |

## What it does

- Classroom container (one classroom = one training subject).
- Multiple session instances per classroom (date, time, location).
- Roster (users enrolled in a classroom — precondition for attendance).
- Per-session attendance marking.
- Waiting list — auto-promote on cancellation.
- Target audience tab (separate from enrolled-users tab).
- Feedback collection via `sentientia_evaluation` integration.
- ICS calendar invite generation (`smoke_ics.php`).

## Capabilities (6)

`:view`, `:create`, `:update`, `:delete`, `:manage`, `:attendance`.

## Tables

`local_sentientia_classroom`, `local_sentientia_classroom_sessions`,
`local_sentientia_classroom_attendance`, `local_sentientia_classroom_users`,
`local_sentientia_classroom_waitlist`, `local_sentientia_locations`, and, since 2026093002 (ADR-032, the
BizLMS import), `local_sentientia_classroom_trainers` and `local_sentientia_classroom_courses`.

## BizLMS import (ADR-032)

`db/bizlms_import.php` registers the `classroom` importer (`classes/bizlms/`). It moves the history held in
BizLMS `local_classroom_*` and `local_location_*` into these tables and is run only by
`local/sentientia_platform/cli/import_bizlms.php` behind its CLI guard. Classroom and session ids are kept;
the trainer of each classroom and session is carried so trainers keep their attendance access (the primary
trainer, the session's trainer and every co-trainer listed in `local_sentientia_classroom_trainers` may open
and mark the classroom's sessions). A classroom or session the import brought in cannot be deleted, and a
learner whose roster row or attendance the import brought in cannot be unenrolled, from the pages
(`error_protected_history`). The readers of the imported history (overview, roster completion, "My
classrooms" at `my.php`, the logo) are behind the default-OFF flag `sentientia.classroom.import_history`.

## Web services (~15)

Classroom + session CRUD + attendance mark + waitlist promote +
trainer assign + roster manage.

## Message providers

`waitlist_promoted` — sent when a cancellation promotes someone off the
waitlist. Migrated to Moodle 5 message constants in Phase 8.2.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_classroom/cli/smoke_waitlist.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_classroom/cli/smoke_ics.php"
```

## Open backlog

- Location records currently live in this plugin (embedded per
  `ENTERPRISE-GRADE-PLAN.md` A.5). A standalone `local_sentientia_locations`
  is not planned.
