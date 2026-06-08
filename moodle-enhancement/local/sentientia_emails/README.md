# local_sentientia_emails

Email template engine + rule-driven dispatcher. 19 templates × 5
languages (English, Hindi, Marathi, Kannada, Swahili).

| Field | Value |
|---|---|
| Component | `local_sentientia_emails` |
| Version | `2026051302` (1.1.1 — Sprint B hotfix) |
| Depends on | `local_airpay_org` |

## What it does

- Templates persisted in DB, editable in admin UI.
- Rule engine: when event X happens, send template Y to recipients
  matching predicate Z. Examples: course enrolment, completion, cert
  expiry, manager weekly summary, classroom session reminder.
- Preview + test-send UI per template.
- Delivery log with status tracking (queued / sent / failed / bounced
  / **suppressed_completion** — Sprint B).
- Per-user override (opt-out of specific rule types).
- Scheduled dispatcher cron.

### Sprint B additions (2026-05-13)

- **`\core\event\course_completed` observer** (`db/events.php` +
  `classes/observer.php`). Sends a polished congratulations email
  with the user's `tool_certificate` PDF attached, then stamps any
  pre-existing reminder rows for the (user, course) as
  `status='suppressed_completion'` so the dashboards correctly
  show "the learner finished".
- **`certificate_helper`** wraps `tool_certificate\template::get_issue_file()`
  and materialises the PDF into `$CFG->tempdir/sentientia_emails/` for
  `email_to_user($attachment)`. Cleanup is best-effort post-send.
- **`course_incomplete` rule type** in `process_rules` — ramping
  cadence (default `[1,3,7,14,21]` days from enrolment), per-user
  cap, and `auto_stop_on_completion` so reminders cease the moment
  the user finishes.
- **Schema additions** to `local_sentientia_email_log`:
  `attachment_filename` (char 255 nullable), `certificate_issue_id`
  (int 10 nullable). To `local_sentientia_email_rules`:
  `cadence_days_json` (char 255), `max_reminders_per_user` (int
  default 0 = unlimited), `auto_stop_on_completion` (int default 1).
- **`status` column widened** from char(20) to char(32) — the new
  `suppressed_completion` enum value is 21 chars (Sprint B hotfix).
- **CLI: `cli/cert_emails_report.php`** — operations-friendly query
  tool with `--since=YYYY-MM-DD`, `--tenant=N`, `--status=X`,
  `--detail`, `--csv` flags. Answer "did this learner receive their
  certificate?" without DB console access.

## Capabilities (6)

`:manage`, `:manage_rules`, `:manage_settings`, `:manage_templates`,
`:preview`, `:view_logs`.

## Tables (4)

| Table | Notes |
|-------|-------|
| `local_sentientia_email_log` | + Sprint B: `attachment_filename`, `certificate_issue_id`, `status` widened to char(32) |
| `local_sentientia_email_rules` | + Sprint B: `cadence_days_json`, `max_reminders_per_user`, `auto_stop_on_completion` |
| `local_sentientia_email_overrides` | per-tenant template overrides |
| `local_sentientia_email_prefs` | per-user opt-out per rule type |

## Scheduled tasks

Dispatcher every 5 minutes; cleanup of old log rows weekly.

## CLI tools

| Command | Purpose |
|---------|---------|
| `cli/cert_emails_report.php` | Audit certificate email deliveries (--since / --tenant / --status / --detail / --csv) |

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_emails/cli/[smoke].php"
# (Email dispatch is gated by Moodle's noemailever setting in dev.
#  Production dispatcher sends via configured SMTP.)
```

## Open backlog

- Teams notification dispatcher — designed but not built (Workstream C).
- Template versioning + rollback.
