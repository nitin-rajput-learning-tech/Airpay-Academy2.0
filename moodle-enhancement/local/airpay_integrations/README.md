# local_airpay_integrations

External-system integration adapter. Today: KeKa HRMS sync (user
provisioning, joiner/mover/leaver events). Designed to host additional
integrations as they come online.

| Field | Value |
|---|---|
| Component | `local_airpay_integrations` |
| Version | beta 1.1.0 |
| Depends on | `local_airpay_org`, `local_airpay_lifecycle` |

## What it does

- Webhook receiver at `/local/airpay_integrations/keka_webhook.php` —
  accepts joiner/mover/leaver events from KeKa with bearer-token auth.
- Event log table records every inbound event for replay + audit.
- Forwards events to `local_airpay_lifecycle` which executes the
  onboarding / move / offboarding workflow.
- Outbound scheduled sync: nightly pull from KeKa for any users
  modified since the last run.

## Tables

`local_airpay_integration_log` — append-only inbound + outbound event
log.

## Scheduled tasks

`\local_airpay_integrations\task\sync_keka_users` — daily nightly pull.

## Step-0 cleanup

Commit `c2b0d7301` (7 May 2026) shipped the pre-cutover audit-driven
fixes: tightened the bearer-token check, added structured event-log
fields, and added the dry-run flag on the manual sync trigger.

## Privacy / GDPR

The webhook receives PII (names, employee IDs, manager links) which is
the canonical source for user provisioning. Provider exports the event
log for a userid; delete redacts events to anonymous after the
seven-year statutory hold.

## Open backlog

- Additional integrations slated for Workstream C: Microsoft Graph
  (SharePoint / Teams), Azure AD provisioning.
- Outbound: KeKa receives course-completion notifications back from the
  platform (currently one-way only — KeKa → platform).
