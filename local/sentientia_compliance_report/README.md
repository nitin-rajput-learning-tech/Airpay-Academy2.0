# local_sentientia_compliance_report

Compliance dashboard with the six-state status engine. Replacement for
BizLMS compliance overlays. The audit-facing view that Reserve Bank of
India / POSH committee / DPO consume for statutory reporting.

| Field | Value |
|---|---|
| Component | `local_sentientia_compliance_report` |
| Version | 1.0.0 |
| Depends on | `local_sentientia_org`, `local_sentientia_recompletion` |

## What it does

- Aggregates statutory training coverage across POSH, AML/KYC, DPDP,
  IT Act, RBI circulars.
- Six-state engine per user × course pair:
  `not_enrolled / enrolled_not_started / in_progress / completed_current
  / completed_expiring / completed_expired`.
- CSV export formatted for statutory returns.
- Hourly refresh of the aggregate table; on-demand recompute via cron task.

## Tables

Aggregate-table cache plus rule-mapping table.

## Scheduled tasks

`\local_sentientia_compliance_report\task\refresh_aggregates` — hourly.

## Message providers

`compliance_dashboard_alert` — daily summary to the Compliance Officer
listing users moving into the `completed_expiring` state.

## Verify after install

```powershell
# CLI manual refresh:
php "C:/xampp/htdocs/moodle5/admin/cli/scheduled_task.php" \
    --execute=\\local_sentientia_compliance_report\\task\\refresh_aggregates
```

## Phase 8.1 dependency

Reads from `local_sentientia_recompletion_history` for the audit trail of
when a user moved from `completed_expired` back to `completed_current`.
Phase 8.1 B6 made the recompletion engine tenant-aware; this dashboard
inherits the correctness.

## Privacy / GDPR

Privacy provider exists. The dashboard is read-only on user data; no
PII written.

## Open backlog (master-doc Section 10.5)

- Scheduled compliance-report email-out (currently the officer pulls
  manually).
- Audit-export bundle (PDF + CSV + signed manifest) for RBI returns.
