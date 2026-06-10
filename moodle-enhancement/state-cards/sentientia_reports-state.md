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
