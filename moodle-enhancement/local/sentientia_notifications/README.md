# local_sentientia_notifications

In-platform notification rule engine. Complements the email dispatcher
(`local_sentientia_emails`) — this plugin produces Moodle native messages
which appear in the navbar bell + the message drawer.

| Field | Value |
|---|---|
| Component | `local_sentientia_notifications` |
| Version | `2026050900` (1.4.0) |
| Depends on | `local_airpay_org` |

## What it does

- Rule definitions: trigger event + recipient predicate + message text.
- 15+ active rule types in production: course enrolment, completion,
  certificate expiring, training overdue, manager weekly summary, peer
  completion celebration, classroom session reminder, request
  submitted/decided/escalated, recompletion due/reset.
- Per-user preference override (mute specific rule types).
- Dispatcher cron every 5 min.
- Delivery log + detailed per-message tracking.

## Capabilities (3)

`:manage`, `:view`, `:viewlogs`.

## Tables (3)

`local_sentientia_notif_log`, `local_sentientia_notif_rules`,
`local_sentientia_notif_prefs`.

## Message providers

15+ providers, one per rule type. All migrated to the Moodle 5
`MESSAGE_DEFAULT_ENABLED` constant in Phase 8.2.

## Verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_notifications/cli/smoke_prefs.php"
php "C:/xampp/htdocs/moodle5/public/local/sentientia_notifications/cli/smoke_preview_send.php"
```

## Phase 8.1 dependency

This plugin is consumed by all four Phase 8.1 plugins (cart, proctoring,
request, recompletion) for their per-domain notifications. Adding a new
rule type means: (a) define a message provider in your plugin's
db/messages.php, (b) register the rule in this plugin's admin UI,
(c) trigger via the platform-wide `notify()` helper.
