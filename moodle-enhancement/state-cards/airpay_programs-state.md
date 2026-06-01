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
