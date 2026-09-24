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

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

`count_classrooms()` took a caller-built LIKE pattern. Contract changed to take a path; it builds the bounded filter itself. No callers today, so the signature change is safe.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-24 - Privacy provider fix (erasure audit)

New `anonymise_data_for_user()` for the DPDP flow. It keeps attendance, which is the only record that the employee attended an ILT or compliance session, keyed to the anonymised user, and clears its `notes`. It still deletes the roster row. Core's full erasure (`delete_data_for_user()`) is unchanged.

Found by a read-only audit of all 38 Sentientia privacy providers, run because `local_sentientia_privacy\privacy_manager::process_deletion()` now calls every one of them. Class change only: no version bump. Covered by `local_sentientia_privacy\erasure_scope_test` / `privacy_manager_test`.

## 2026-09-24 - Waiting list: fresh-install table + privacy coverage

`local_sentientia_classroom_waitlist` holds a userid and, after an admin removal, the admin's free-text `reason`. Two defects:

- The provider never touched it. It was not declared, a waitlist-only user got no context, it was not exported, and neither erasure path deleted it, so a DPDP erasure read 'completed' with the rows still there. Now it is declared (`privacy:metadata:waitlist*`, en + hi), reported by `get_contexts_for_userid()` / `get_users_in_context()`, and exported. `delete_data_for_user()`, `delete_data_for_users()` and `delete_data_for_all_users_in_context()` delete it. `anonymise_data_for_user()` deletes it too: a waiting-list place is a queue entry, not a learning record. After the delete, each queue the user was still waiting in is renumbered, so the people behind them move up (`waitlist_manager::renumber_positions()` is now public for this). Every access is guarded by `table_exists()`.
- Only `db/upgrade.php` (step 2026051130) created it. `db/install.xml` did not, so a fresh install, and the PHPUnit database, had no waiting list. It is now in `install.xml`, identical field by field to that step (checked with Moodle's own XMLDB loader). A site already installed fresh (UAT's 5.2 install is one) would still lack it, so new upgrade step 2026092400 creates it where missing, with the same definition, and is a no-op everywhere else. Version 2026092400 / release 1.10.3: the deploy needs the Notifications upgrade run.

Tests: `tests/privacy_waitlist_test.php` (+ `tests/fixtures/provider_without_waitlist.php`, a double that reports the table absent). Written, not yet run: the shared PHPUnit database is being rebuilt. It must be re-initialised from this `install.xml` first. Until then the table is missing there, and core's `core_privacy\privacy\provider_test::test_metadata_provider` fails for this plugin, because it asserts that every declared table exists.

Still open (not in this change): `local_sentientia_locations` and the `locationid` columns (upgrade step 2026051160) are also missing from `install.xml`. The provider does not anonymise the actor columns `attendance.markedby` / `users.enrolledby`. The `markedat` field it declares does not exist.
