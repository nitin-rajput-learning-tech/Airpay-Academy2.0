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

## 2026-09-25 - ADR-031: classrooms tenant-scoped; no-tenant callers get nothing

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 3 confirmed hits. `:view`, `:update`, `:attendance` and `:create` default to the manager archetype, and tenant admins hold a manager-archetype role at system context. Every web service checked only the capability and then acted on whatever `classroomid`/`sessionid` it was sent, so any tenant admin could read every tenant's rosters, attendance and waitlists (names, emails, employee ids) and cancel classrooms, delete sessions, unenrol learners and falsify attendance in any tenant.

- New guards in `session_manager`: `require_classroom_access()`, `require_session_access()`, `assert_classroom_in_scope()` (fails closed for a caller with no tenant and for a classroom with no `open_path` - `tenant::require_path_access()` alone lets '' through), `require_users_in_scope()`, `org_path_for_caller()`.
- Called in every id-keyed web service (list_classroom_users/sessions, list_session_attendance, list_waitlist, waitlist_join, change_status, delete_classroom, delete_session, unenrol_classroom_user, mark/bulk_mark_attendance, bulk_enrol_by_audience), in every dynamic form's `check_access_for_dynamic_submission()`, and in view.php, attendance.php and ics.php (non-members). The inline `$top > 0` page checks are gone.
- Writes that name a learner check the learner's tenant (attendance, unenrol, enrol picker submit). The trainer picker refuses a trainer from another tenant when the trainer changes.
- `list_classrooms`: the tenant `path_filter` always applies; the org cascade only narrows it (it used to replace it, so `filters={"org_l1":77}` listed another tenant). Index KPI tiles count the caller's tenant.
- Edit form: org picker limited to the caller's tenant; "No specific organisation" stamps the caller's tenant root for scoped callers (`create()`/`update()` take an optional fallback path; cross-tenant callers unchanged).
- `classroom_audience_enroller`: a caller with no tenant resolves to nobody (was: everyone); the target classroom is tenant-checked. `:enrol` is still undeclared (the audience surface stays dead for everyone, unchanged); it is now safe to declare.
- Capabilities unchanged (legitimate in-tenant functions). 1.10.4 / 2026092500, depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees.

## 2026-09-25 - ADR-031 wave-1 review follow-up: own-classroom rosters, attendance save, legacy unenrol

The wave-1 review found three gaps on classrooms that ARE the caller's. A roster can still hold other tenants' or pathless learners (put there by a site admin, a request/approval flow, or the pre-fix fail-open).

- **Roster reads were not tenant-filtered.** `list_classroom_users`, `list_session_attendance`, `list_waitlist` and `attendance.php` listed those learners' names, emails, employee ids and designations to the tenant admin. `session_manager::get_enrolled_users()`, `count_enrolled_filtered()` and `get_session_attendance()`, and `waitlist_manager::list_waiting()`, now take `bool $callerscope = false`. When it is true, the query ANDs `session_manager::roster_scope()` (`tenant::path_filter('u')`: '1=1' for cross-tenant callers, '1=0' for a caller with no tenant). The web services and attendance.php pass true. Library callers (cron, privacy, unit tests with no user) keep the default and are unchanged. Tab badges (`count_enrolled`) still count the whole roster: seats against capacity must include everyone.
- **One such learner blocked the whole attendance Save.** `bulk_mark_attendance` now skips any mark for a learner outside the caller's tenant and still saves the in-tenant marks. It no longer refuses the batch. The return gains `skipped`, and the message says how many were not marked (new string `attendance_skipped_outoftenant`, en + hi). The grid no longer renders those rows, so a normal save skips none. The single-mark `mark_session_attendance` still refuses them. New helper: `session_manager::users_in_scope()`; `require_users_in_scope()` is built on it, with the same semantics.
- **A tenant admin could not remove such a learner from their own classroom** (wave-1 deviation 7). `unenrol_classroom_user` now calls `session_manager::require_unenrol_target()`. That check passes for anyone already on the (in-tenant) roster, and otherwise keeps the `require_same_tenant_user()` refusal. UAT note: the Users tab no longer shows those learners to a tenant admin, so today the removal is reachable only through the web service. A site admin still sees and removes them from the UI.
- No version bump (already 2026092500 from wave 1; no upgrade step). The JS is unchanged (it reads only `message`). Tests: `tests/tenant_scope_test.php` adds three tests, for the save, the roster reads and the legacy unenrol. Written, not run. Both trees.

## 2026-09-25 - Locations schema: fresh install and upgraded site now match

Branch `claude/adr031-learning3-ff`. This closes the "Still open" item above about
`local_sentientia_locations` and the `locationid` columns.

- **The divergence.** Step 2026051160 was the only thing that created `local_sentientia_locations`
  and `locationid` on `local_sentientia_classroom` / `_sessions`. install.xml never declared them, so
  a site installed fresh after that step (PHPUnit init, UAT if it was installed fresh on 5.2, any new
  customer) had none of them. An upgraded site had all of them. The same step passed the decimals as a
  10th argument (`'equipment', null, '6'`) to `xmldb_table::add_field()`, which takes 8, so latitude
  and longitude were created NUMBER(10,0).
- **The fix.** install.xml now declares the table (latitude / longitude NUMBER(10,6)) and `locationid`
  (int 10, nullable) on both classroom tables. Step 2026051160 passes `'10, 6'`. New step
  **2026092501** calls `db/upgradelib.php` `local_sentientia_classroom_ensure_location_schema()`.
  It creates the table where it is missing, adds `locationid` where missing, and widens latitude /
  longitude where their scale is below 6. Nothing is dropped and no row is touched. No code reads or
  writes these yet.
- **Why keep rather than drop** (the verifier's smaller option). ENTERPRISE-GRADE-PLAN.md A.5 plans
  this table: a session-form dropdown, a Leaflet map and capacity validation. Dropping it would be a
  product decision and a DROP on live. Keeping it is additive.
- 1.10.5 / 2026092501. Tests: new `tests/location_schema_test.php` (`@group tenant_isolation`). It
  covers fresh-install parity, a "fresh before the fix" site (table and columns dropped, then
  recreated), and a replay of `xmldb_local_sentientia_classroom_upgrade(2026092500)` on NUMBER(10,0)
  columns, with a coordinate round trip. The tests run DDL, and `tearDown` restores the table and
  columns independently of the helper. Written, not run. Both trees.
