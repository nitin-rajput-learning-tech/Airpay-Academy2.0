# local_sentientia_reports

Reporting overlay on top of the bundled LearnerScript block. Adds
org-filter + tenant-scoping + saved-report management on top of
LearnerScript's report-builder.

| Field | Value |
|---|---|
| Component | `local_sentientia_reports` |
| Version | 1.1.0 |
| Depends on | `local_airpay_org` |

## What it does

- Saved-report definitions persisted per user.
- Org-tree filter on every report.
- Tenant-scoping for non-admin viewers.
- `list-reports` web service for the dashboard's recent-reports widget.

## Tables

`local_sentientia_reports` — saved-report definitions.

## Capabilities (3)

`:view`, `:generate`, `:manage`.

## LearnerScript-P3 deferral

The deeper LearnerScript-P3 integration is documented as deferred in
`moodle-enhancement/LEARNERSCRIPT-P3-DEFERRAL.md`. The current overlay
covers the core L&D reporting need; P3-specific features remain via
the underlying block.

## Privacy / GDPR

The reports themselves contain PII by definition; permissions enforce
that only authorised viewers can produce reports. Audit-log helper
(Phase 9, `\local_airpay_core\audit_log`) captures report generation
events for the sensitive-actions feed.

## Open backlog

- Scheduled report email-out.
- Report sharing with another user.
- Per-report subscriber list.
