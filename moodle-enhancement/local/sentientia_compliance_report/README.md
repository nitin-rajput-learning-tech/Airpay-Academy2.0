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

**Corrected 2026-09-30 (ADR-032).** This README used to say the dashboard reads
`local_sentientia_recompletion_history` for the audit trail of when a user moved
from `completed_expired` back to `completed_current`. No code here has ever read
that table: the status of a user and course comes from Moodle's own
`course_completions` and the enrolment rows (`compliance_engine.php`). The reset audit trail lives in `local_sentientia_recompletion`
(`history.php`, and for what each reset deleted `history_detail.php` behind the
flag `sentientia.recompletion.evidence_view`, default OFF), tenant-scoped the
same way as this dashboard. The two plugins are related only by the completion
state that a reset clears. Reading the history here (a "last reset" column, or
the earlier cycles of a learner) is a possible follow-up, not a feature.

## Privacy / GDPR

Privacy provider exists. The dashboard is read-only on user data; no
PII written.

## Open backlog (master-doc Section 10.5)

- Scheduled compliance-report email-out (currently the officer pulls
  manually).
- Audit-export bundle (PDF + CSV + signed manifest) for RBI returns.
