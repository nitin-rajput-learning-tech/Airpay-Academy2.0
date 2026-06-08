# local_sentientia_classroom

Instructor-led training. Sessions, attendance, locations, waiting list,
trainer assignments. Replacement for BizLMS `local_classroom`.

| Field | Value |
|---|---|
| Component | `local_sentientia_classroom` |
| Version | `2026050900` (1.6.0) |
| Depends on | `local_airpay_org`, `local_sentientia_evaluation` |

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

## Tables (4)

`local_sentientia_classroom`, `local_sentientia_classroom_sessions`,
`local_sentientia_classroom_attendance`, `local_sentientia_classroom_users`.

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
  `ENTERPRISE-GRADE-PLAN.md` A.5). A standalone `local_airpay_locations`
  is not planned.
