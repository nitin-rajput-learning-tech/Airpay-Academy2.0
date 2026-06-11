# State Card â€” `local_airpay_compliance_report`

**Component:** `local_airpay_compliance_report`
**Version:** `2026052900` / `1.0.0`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Compliance training audit + escalation.
**Last refreshed:** 2026-06-02 (export gated on a capability â€” PII protection)

---

## Mission

Compliance training audit â€” generate per-tenant snapshots of who's
completed mandatory compliance training (cyber security, sexual
harassment, KYC/AML, etc.), flag overdue learners, send escalation
emails, and surface a manager-facing exemption workflow.

Pairs with `block_airpay_cert_health` (dashboard widget) and
`local_airpay_emails` (delivery pipeline).

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_compliance_courses` | Which courses are flagged as compliance-mandatory (per tenant) |
| `local_compliance_snapshot` | Periodic snapshot of per-(user Ã— course) compliance state |
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
â”œâ”€â”€ version.php                                   2026041200 / 1.0.0
â”œâ”€â”€ README.md
â”œâ”€â”€ settings.php                                   Admin: tenant exemption defaults
â”œâ”€â”€ styles.css
â”œâ”€â”€ index.php                                      Compliance summary table
â”œâ”€â”€ export.php                                     CSV export
â”œâ”€â”€ classes/
â”‚   â”œâ”€â”€ compliance_engine.php                     Snapshot generator + overdue rules
â”‚   â”œâ”€â”€ task/                                      Scheduled snapshot run
â”‚   â””â”€â”€ privacy/                                   GDPR / DPDP
â”œâ”€â”€ db/
â”‚   â”œâ”€â”€ install.xml                                4 tables
â”‚   â””â”€â”€ upgrade.php
â”œâ”€â”€ templates/
â”œâ”€â”€ lang/
â”‚   â”œâ”€â”€ en/local_airpay_compliance_report.php
â”‚   â””â”€â”€ hi/local_airpay_compliance_report.php
â””â”€â”€ tests/                                         4 PHPUnit methods (single file)
```

## Tests

1 PHPUnit class, 4 methods. Snapshot-engine smoke. Most logic is
exercised indirectly via the integration tests on
`local_airpay_emails` + `block_airpay_cert_health`.

## Open items

- [ ] Cohort-scoped compliance â€” today: per-course, per-tenant only
- [ ] Manager exemption SLA â€” auto-expire stale exemptions
- [ ] Compliance-by-supervisor view (today: by-tenant + by-learner only)
- [ ] PHPUnit coverage extension (today: minimal)
- [ ] Per-tenant overdue-threshold (today: 7 days hardcoded)

## State card created â€” 2026-05-24

Initial state card. Plugin has been live since 2026-04-12; created
now as part of the P1 state-card pass.

## ADR-018 Wave 2 â€” open_path â†’ tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical â€” the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## 2026-06-02 â€” Export gated on a capability (PII protection)

The full-matrix export (every employee's compliance status + name/email/employeeid/
department â€” bulk PII) is now gated on a dedicated capability
`local/airpay_compliance_report:export` (RISK_PERSONAL) instead of the old
`is_siteadmin() || has_capability('local/courses:manage')` inline check.

- `classes/permission.php` â€” `can_export()` checks the cap at SYSTEM context AND every
  `CONTEXT_COURSECAT` where the user holds a role (the BizLMS Compliance Officer / OrgAdmin
  shell is assigned at category context, so a system-only check would miss it).
  `grant_export_to_default_roles()` (idempotent; db/install.php + db/upgrade.php) preserves
  the pre-capability access set (`local/courses:manage` holders + Compliance Officer role 9).
- `db/access.php` â€” the capability (manager archetype default).
- `export.php` server gate + `index.php` / `dashboard.mustache` button-visibility call the
  SAME `can_export()`, so they cannot disagree. Line managers VIEW but are NOT granted export.
- lang en + hi (100% parity). version 2026041200 â†’ 2026052900. `tests/permission_test.php`
  6/6 green.

## 2026-06-11 overnight (foolproof WF-008)

- `compliance_engine.php:336` — fixed PHP 8 warning: `\->deadline_date` does not exist on `local_compliance_courses` rows (schema has `deadline_days`); now `!empty()`-guarded.
- Cold-run scale finding recorded in WORKFLOW-TEST-MATRIX (WF-008): `rebuild_snapshot()` sends escalation messages inline per overdue user — thousands of `message_send()` calls on a cold clone; queue/chunk hardening is a follow-up. The 539MB blow-up root cause was the stale-capability debugging-backtrace flood (fixed via repair CLI §2d) + max_allowed_packet=1M local (now 64M).

