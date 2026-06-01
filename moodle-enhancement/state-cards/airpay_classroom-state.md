# State Card — `local_airpay_classroom`

**Component:** `local_airpay_classroom`
**Version:** `2026052001` / `1.10.1`  (+P1 #44 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS `local_classroom`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Instructor-led training (ILT) — classroom sessions, attendance,
ICS feed, waitlist. Replaces BizLMS `local_classroom` with the
multi-tenant + multi-customer conventions; the data source for
`local_sentientia_calendar`'s classroom event category and for
`block_airpay_trainer`.

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_airpay_classroom` | Classroom (course-attached ILT activity) |
| `local_airpay_classroom_sessions` | Session instances (dates, times, trainer assignment) |
| `local_airpay_classroom_users` | Roster — users enrolled in a classroom |
| `local_airpay_classroom_attendance` | Per-session attendance records (status + timestamp + recorded-by) |

## Capabilities (6)

`local/airpay_classroom:` `view`, `manage`, `attendance`, `create`,
`update`, `delete`. Attendance is a separate cap so a trainer can mark
attendance without full manage rights.

## Feature flags

None registered directly. `local_sentientia_calendar.events.classroom`
gates whether classroom sessions appear in the ICS feed (toggled in
the calendar plugin's flags).

## Key files

```
local/airpay_classroom/
├── version.php                                    2026052001 / 1.10.1
├── README.md
├── lib.php
├── index.php                                       Admin list
├── view.php                                        Classroom detail
├── attendance.php                                  Per-session attendance UI
├── ics.php                                         Classroom-scoped ICS feed
├── cli/                                            Operations
├── classes/
│   ├── session_manager.php                         Session CRUD + scheduling
│   ├── classroom_audience_enroller.php             Bulk enrolment (audience rules)
│   ├── waitlist_manager.php                        Waitlist + promotion
│   ├── ics_builder.php                             RFC 5545 builder for the per-classroom feed
│   ├── event/                                      Audit events
│   ├── external/                                   WS endpoints
│   ├── form/                                       Edit + session forms
│   └── privacy/                                    GDPR / DPDP
├── db/
│   ├── install.xml                                 4 tables
│   ├── upgrade.php
│   ├── access.php                                  6 capabilities
│   ├── services.php                                WS function registry
│   └── tasks.php                                   Scheduled tasks
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_classroom.php
│   └── hi/local_airpay_classroom.php               (100% parity post-P1 #44)
└── tests/
    ├── crud_test.php                               6 methods
    ├── sessions_test.php                           18 methods
    ├── enrolment_window_test.php                   6 methods
    ├── external/list_classrooms_test.php           5 methods
    └── external/sessions_external_test.php         14 methods (49 total)
```

## Tests

5 PHPUnit classes, 49 methods. `sessions_test.php` is the deepest —
exercises scheduling, conflict detection, trainer assignment.

## Open items

- [ ] Recurring sessions (every Monday for 6 weeks) — Phase 2
- [ ] Mobile attendance scan-in (QR code on per-session join page)
- [ ] Trainer self-service: "create new session for this classroom"
      from the trainer dashboard (currently admin-only)
- [ ] WhatsApp reminder integration (Phase C.1) — already wired via
      `local_airpay_emails`; add WhatsApp channel
- [ ] Per-tenant default location list (today: free-text)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. The 4-table schema and 6 capabilities
are the public contract for `local_sentientia_calendar`,
`block_airpay_trainer`, and the ILT reporting layer.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.
