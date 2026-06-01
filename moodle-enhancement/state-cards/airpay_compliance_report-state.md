# State Card — `local_airpay_compliance_report`

**Component:** `local_airpay_compliance_report`
**Version:** `2026041200` / `1.0.0`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Compliance training audit + escalation.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Compliance training audit — generate per-tenant snapshots of who's
completed mandatory compliance training (cyber security, sexual
harassment, KYC/AML, etc.), flag overdue learners, send escalation
emails, and surface a manager-facing exemption workflow.

Pairs with `block_airpay_cert_health` (dashboard widget) and
`local_airpay_emails` (delivery pipeline).

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_compliance_courses` | Which courses are flagged as compliance-mandatory (per tenant) |
| `local_compliance_snapshot` | Periodic snapshot of per-(user × course) compliance state |
| `local_compliance_exemptions` | Manager-granted exemptions (with rationale + expiry) |
| `local_compliance_email_log` | Email-send audit (joined with `local_airpay_email_log` for cert PDFs) |

## Capabilities

None declared explicitly. Admin surfaces gate on `moodle/site:config`;
manager surfaces gate on the upstream `local_airpay_manager` cap layer.

## Feature flags

None registered.

## Key files

```
local/airpay_compliance_report/
├── version.php                                   2026041200 / 1.0.0
├── README.md
├── settings.php                                   Admin: tenant exemption defaults
├── styles.css
├── index.php                                      Compliance summary table
├── export.php                                     CSV export
├── classes/
│   ├── compliance_engine.php                     Snapshot generator + overdue rules
│   ├── task/                                      Scheduled snapshot run
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                4 tables
│   └── upgrade.php
├── templates/
├── lang/
│   ├── en/local_airpay_compliance_report.php
│   └── hi/local_airpay_compliance_report.php
└── tests/                                         4 PHPUnit methods (single file)
```

## Tests

1 PHPUnit class, 4 methods. Snapshot-engine smoke. Most logic is
exercised indirectly via the integration tests on
`local_airpay_emails` + `block_airpay_cert_health`.

## Open items

- [ ] Cohort-scoped compliance — today: per-course, per-tenant only
- [ ] Manager exemption SLA — auto-expire stale exemptions
- [ ] Compliance-by-supervisor view (today: by-tenant + by-learner only)
- [ ] PHPUnit coverage extension (today: minimal)
- [ ] Per-tenant overdue-threshold (today: 7 days hardcoded)

## State card created — 2026-05-24

Initial state card. Plugin has been live since 2026-04-12; created
now as part of the P1 state-card pass.

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.
