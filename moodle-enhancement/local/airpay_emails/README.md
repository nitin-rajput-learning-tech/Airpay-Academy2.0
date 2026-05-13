# local_airpay_emails

Email template engine + rule-driven dispatcher. 19 templates × 4
languages (English, Hindi, Marathi, Kannada + Swahili variants).

| Field | Value |
|---|---|
| Component | `local_airpay_emails` |
| Version | `2026050800` (1.0) |
| Depends on | `local_airpay_org` |

## What it does

- Templates persisted in DB, editable in admin UI.
- Rule engine: when event X happens, send template Y to recipients
  matching predicate Z. Examples: course enrolment, completion, cert
  expiry, manager weekly summary, classroom session reminder.
- Preview + test-send UI per template.
- Delivery log with status tracking (queued / sent / failed / bounced).
- Per-user override (opt-out of specific rule types).
- Scheduled dispatcher cron.

## Capabilities (6)

`:manage`, `:manage_rules`, `:manage_settings`, `:manage_templates`,
`:preview`, `:view_logs`.

## Tables (4)

`local_airpay_email_log`, `local_airpay_email_rules`,
`local_airpay_email_overrides`, `local_airpay_email_prefs`.

## Scheduled tasks

Dispatcher every 5 minutes; cleanup of old log rows weekly.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/airpay_emails/cli/[smoke].php"
# (Email dispatch is gated by Moodle's noemailever setting in dev.
#  Production dispatcher sends via configured SMTP.)
```

## Open backlog

- Teams notification dispatcher — designed but not built (Workstream C).
- Template versioning + rollback.
