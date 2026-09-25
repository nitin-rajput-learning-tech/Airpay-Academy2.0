# State Card — `local_airpay_reports`

**Component:** `local_airpay_reports`
**Version:** `2026052001` / `1.1.1`  (+P1 #52 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Saved-report builder + scheduler.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Saved-report builder — define a SQL-shaped report once (joins,
filters, columns), then run it on demand or via schedule. Surfaces a
"Reports" hub for L&D + compliance teams (replaces the BizLMS
LearnerScript pattern with a Moodle-native one).

LearnerScript blocks are still in the tree (see
`blocks/learnerscript_lib_PATCHED.php` + `blocks/reportdashboard_dashboard_PATCHED.php`)
during the transition.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_airpay_reports` | Saved report definitions (name, SQL template, params, schedule, recipient list) |

## Capabilities (3)

`local/airpay_reports:` `view`, `manage`, `export`. The `:export` cap
is read-only-with-data — compliance auditors can export but not edit
the underlying definition.

## Feature flags

None registered.

## Key files

```
local/airpay_reports/
├── version.php                                   2026052001 / 1.1.1
├── README.md
├── index.php                                      Reports hub
├── run.php                                        Run a report (live results)
├── export.php                                     CSV / XLSX export
├── classes/
│   ├── report_manager.php                        Report CRUD + run engine
│   ├── external/                                  WS endpoints
│   └── form/                                      Edit / run forms
├── db/
│   ├── install.xml                                1 table
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── amd/
├── templates/
├── lang/
│   ├── en/local_airpay_reports.php
│   └── hi/local_airpay_reports.php                (100% parity post-P1 #52)
└── tests/                                         1 PHPUnit class / 5 methods
```

## Tests

1 PHPUnit class, 5 methods. Smoke on report CRUD + run.

## Open items

- [ ] Scheduled-run pipeline (today: on-demand only; rule registry is
      ready but cron task not wired)
- [ ] Per-tenant report library (today: all reports visible to
      capability holder)
- [ ] LearnerScript transition removal — keep blocks/*PATCHED until
      reports parity is verified
- [ ] Chart rendering (today: table-only)
- [ ] Email-attached PDF report delivery (depends on
      `local_airpay_emails` template extension)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031 tenant scope (1.2.0, 2026092500)

Sweep hits 47, 48, 49 and the reports half of 67 (CROSS-TENANT-AUTHORITY-SWEEP-2026-09-25).
`:view`, `:export` and `:manage` keep their manager defaults; the missing guards are added.

- `report_manager::require_report_access()` is the one guard: cross-tenant callers pass; for
  anyone else an "All organisations" report (empty open_path) is refused, then
  `tenant::require_path_access()`. It runs in `run_report()` itself, in `run.php` and `export.php`
  (which ran ANY report id), in `update()`, the edit form, `delete_report` and `toggle_status`
  (whose inline copies threw a lang string that did not exist; they now throw
  `local_sentientia_platform/error_outoftenant`).
- `create()` / `update()` refuse an org outside the caller's tenant, and "All organisations", for
  scoped callers. The edit form lists only the caller's tenant's orgs and drops "All organisations".
- `list_reports`: the org cascade now narrows the tenant filter instead of replacing it.
- The index KPI tiles count the caller's tenant's reports only.

`local_sentientia_org\org_manager::cascade_where_sql()` itself still does not clamp to the caller's
tenant; that fix belongs to the org plugin (the other five callers share it).
Tests: `tests/tenant_scope_test.php` (`@group tenant_isolation`); `delete_report_test` now expects
the platform string.

## 2026-09-25 - ADR-031 follow-up (still 1.2.0, 2026092500)

Reviewer item on wave 1 (branch `claude/adr031-comms-ff`). `list_reports` now clamps the
client-chosen `perpage` to `1..list_reports::MAX_PERPAGE` (100) and `page` to `>= 0`. The page
size used to be unbounded, and 0 or a negative value broke the offset. The datatable asks for 25.
The test runs as a tenant admin and checks the clamp and that the rows stay in the caller's tenant.
Still open, because the fix belongs to the org plugin: sweep hit 67.
`org_manager::cascade_where_sql()` does not clamp to the caller's tenant, and the programs,
classroom, evaluation, exams and learningpath callers still let it REPLACE the tenant filter.
Only this plugin's caller narrows it.
