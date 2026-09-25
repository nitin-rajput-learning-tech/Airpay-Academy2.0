# State Card — `local_airpay_programs`

**Component:** `local_airpay_programs`
**Version:** `2026052001` / `1.8.1`  (+P1 #45 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Replaces BizLMS `local_program`.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Multi-level certification programs — sequential tiers of courses
("Foundation" → "Practitioner" → "Expert"). Each level can contain
multiple required + optional courses; completing a level unlocks the
next.

Sibling to `local_airpay_learningpath` (which is a flat sequence);
programs add the level + tiered-certification layer.

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_airpay_programs` | Program definition (name, status, certification authority) |
| `local_airpay_programs_levels` | Levels within a program (ordered) |
| `local_airpay_programs_courses` | Courses assigned to a level (with required / optional flag) |
| `local_airpay_programs_users` | User enrolments in programs (current level + status) |

## Capabilities (6)

`local/airpay_programs:` `view`, `manage`, `create`, `update`, `delete`,
`enrol`.

## Feature flags

None registered.

## Key files

```
local/airpay_programs/
├── version.php                                   2026052001 / 1.8.1
├── README.md
├── lib.php
├── index.php                                      Admin list
├── levelcourses.php                               Per-level course assignment UI
├── cli/                                            Operations
├── classes/
│   ├── program_manager.php                       Program CRUD + level orchestration
│   ├── program_audience_enroller.php              Bulk enrol via audience rules
│   ├── observer.php                               course_completed → re-evaluate program progress
│   ├── event/                                     Audit events
│   ├── external/                                  WS endpoints
│   ├── form/                                      Forms
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                4 tables
│   ├── upgrade.php
│   ├── access.php                                 6 capabilities
│   └── services.php                               WS function registry
├── amd/
├── templates/
├── lang/
│   ├── en/local_airpay_programs.php
│   └── hi/local_airpay_programs.php               (100% parity post-P1 #45)
└── tests/
    ├── crud_test.php                              6 methods
    ├── levels_test.php                            17 methods
    ├── external/list_programs_test.php            5 methods
    └── external/levels_external_test.php          13 methods (41 total)
```

## Tests

4 PHPUnit classes, 41 methods. `levels_test.php` is the deepest —
covers the level-unlock state machine.

## Open items

- [ ] Per-level capability gate (today: program-wide caps only)
- [ ] Cohort-scoped enrolment (today: tenant-scoped only)
- [ ] Program certificate template integration with `tool_certificate`
- [ ] Manager program-progress view (depends on `local_airpay_manager`
      reporting-line resolver)
- [ ] Mobile program-detail polish

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.


## 2026-09-24 - DPDP erasure kept deleting certification records

**Defect.** `local_sentientia_privacy\privacy_manager::process_deletion()` (the DPDP
right-to-erasure flow) calls a Sentientia provider's `anonymise_data_for_user()` when it has
one and `delete_data_for_user()` otherwise. This provider had no anonymise hook, so every
approved erasure deleted the person's `local_sentientia_programs_users` rows: the
certification-program enrolment and COMPLETION record (status 2 + `timecompleted`). That flow
promises to keep completions, anonymised, against the user row it anonymises in place.

**Fix.** `privacy\provider::anonymise_data_for_user()` added. Table by table:
- `local_sentientia_programs_users`: kept unchanged, keyed to the anonymised user. Every column
  is record data (ids, status code, timestamps); no free text to blank. In-progress enrolments
  are kept too (`currentlevelid` is part of the record).
- `local_sentientia_programs`, `_levels`, `_courses`: no user column; nothing to do.

The method body is deliberately empty: its existence is what routes the DPDP flow away from
the delete. `delete_data_for_user()` is unchanged and stays core's full erasure.

**Test.** `tests/privacy_anonymise_test.php`: the rows (and their status, completion time,
level) survive `anonymise_data_for_user()`; `delete_data_for_user()` still erases them and only
them. Written, not yet run (shared test DB being rebuilt). No version bump (class change only).
Both trees.

## 2026-09-25 - ADR-031: programs tenant-scoped; cohort enrol takes only the caller's tenant

Cross-tenant authority sweep (docs/audits/CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25.md), 5 confirmed hits. `:view`, `:enrol`, `:update` and `:create` default to the manager archetype (tenant admins hold one at system context), and every programid/levelid-keyed endpoint checked only the capability: any tenant admin could list any tenant's program roster (names, emails, employee ids) and archive, restructure, enrol into or unenrol from any tenant's program. The cohort form enrolled every member of any site cohort, so another tenant's users could be pulled into a program.

- New guards in `program_manager`: `require_program_access()`, `require_level_access()`, `assert_program_in_scope()` (fails closed for no tenant and for a program with no `open_path`), `require_users_in_scope()`, `course_scope_sql()` / `require_courses_in_scope()`, `org_path_for_caller()`. `enrol_cohort()` takes an optional scope path (the form passes the caller's tenant; CLI and internal callers unchanged).
- Called in every id-keyed web service (list_program_users/levels, list_level_courses, change_status, delete_program, delete_level, reorder_levels, unassign_level_course, unenrol_program_user, bulk_enrol_by_audience), every dynamic form's access check, view.php and levelcourses.php (the inline `$top > 0` checks are gone). Index KPI tiles count the caller's tenant.
- Writes check their targets: enrolled/unenrolled users and level courses must be the caller's tenant's; the course picker lists only those (was up to 5000 courses from every tenant).
- `list_programs`: tenant filter always applies; the org cascade only narrows it.
- Edit form: org picker limited to the caller's tenant; scoped "No specific organisation" stamps the tenant root.
- Enrol picker, cohort pickers and `program_audience_enroller`: a caller with no tenant gets nobody (was: everyone).
- Capabilities unchanged (legitimate in-tenant functions). 1.8.2 / 2026092500, depends on local_sentientia_platform 2026092500. Tests: `tests/tenant_scope_test.php` (@group tenant_isolation). Written, not run (shared PHPUnit DB). Both trees.

## 2026-09-25 - ADR-031 wave-1 review follow-up: own-program roster, cohort picker limit, legacy unenrol

- **The roster of a program that IS the caller's was not tenant-filtered.** Learners enrolled by the pre-fix cohort path, the no-tenant fail-open, a site admin or an approval flow still appeared in `list_program_users`, with name, email, employee id and designation. `program_manager::get_enrolled_users()` and `count_enrolled_filtered()` now take `bool $callerscope = false`. When it is true, they AND `program_manager::roster_scope()` (`tenant::path_filter('u')`). The web service passes true. Library callers are unchanged. The view.php badge still counts the whole roster (a headcount, not PII).
- **Cohort picker:** `enrol_program_cohort` applied "has a member in my tenant" in PHP AFTER `LIMIT 500`, so on a site with more than 500 visible cohorts a tenant's own cohorts could drop off the list. The query moved to `program_manager::cohort_options($limit)`, where the in-tenant EXISTS test is in the WHERE clause and runs before the limit. The member counts are unchanged: only the members `enrol_cohort()` would take. Cross-tenant callers still see every visible cohort with its full count.
- **A tenant admin could not remove a legacy out-of-tenant or pathless learner from their own program** (wave-1 deviation 7). `unenrol_program_user` now calls `program_manager::require_unenrol_target()`. That check passes for anyone already on the (in-tenant) roster, and otherwise keeps the `require_same_tenant_user()` refusal. UAT note: those learners no longer show on the Users tab for a tenant admin, so today the removal is reachable only through the web service.
- Still open, and outside this plugin: `local_sentientia_manager` approval_manager calls `program_manager::enrol_users()` without checking the tenant of the target program.
- No version bump (already 2026092500; no upgrade step). Tests: `tests/tenant_scope_test.php` adds three tests, for the roster, the cohort limit and the legacy unenrol. Written, not run. Both trees.
