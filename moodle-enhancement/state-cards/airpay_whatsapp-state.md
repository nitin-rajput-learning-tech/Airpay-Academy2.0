# State Card — `local_airpay_whatsapp`

**Component:** `local_airpay_whatsapp`
**Version:** `2026052101` / `0.3.0-alpha`  — Stream C / Phase C.1 (notification_bridge + cron hooks)
**Maturity:** `MATURITY_ALPHA`  — mock-mode only; `[CONFIRM]` required before live
**Status:** Mock-mode shipped end-to-end. Live API gated behind core feature flag.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

WhatsApp Business API (+ SMS fallback via DLT-template registry) as a
notification channel for Sentientia LMS. Acts as a parallel channel
alongside the existing email pipeline — `notification_bridge` plugs
into the same triggers as `local_airpay_emails`, so course completions,
overdue reminders, classroom joins, etc. can fire over WhatsApp / SMS
when the relevant sub-flag is ON.

Mock-mode runs the entire pipeline (preference lookup → DLT-template
match → render → "send" → log) without hitting WhatsApp Business API.
Live mode requires `[CONFIRM]` per CLAUDE.md §10.

## DB tables (4)

| Table | Purpose |
|-------|---------|
| `local_airpay_user_channel_prefs` | Per-user channel opt-in (whatsapp / sms / email; per rule type) |
| `local_airpay_user_channel_audit` | Append-only audit of preference changes |
| `local_airpay_dlt_templates` | DLT-approved template registry (India regulatory requirement for SMS) |
| `local_airpay_send_log` | Per-send audit (channel, template, recipient, status, vendor message-id) |

## Capabilities

None declared explicitly in `db/access.php` (the plugin is read-only
from a per-user perspective; admin surfaces gate on
`moodle/site:config`).

## Feature flags

Consumed (registered in `local_airpay_core`):
- `engagement.whatsapp.enabled` (master switch — default OFF)
- `engagement.sms.enabled` (SMS fallback — default OFF)
- `engagement.whatsapp.reminders` (sub-channel: incomplete-course reminders — Phase C.1)
- `engagement.whatsapp.overdue` (sub-channel: manager overdue alerts — Phase C.1)

## Key files

```
local/airpay_whatsapp/
├── version.php                                   2026052101 / 0.3.0-alpha
├── lib.php
├── settings.php                                   Admin API key + DLT config
├── preferences.php                                Per-user channel opt-in UI
├── styles.css
├── admin/                                         Admin operations surfaces
├── cli/                                           Diagnostics + mock-send smoke
├── classes/
│   ├── notification_bridge.php                    Hooks Moodle message_send → channel router
│   ├── channel_router.php                         Pick channel based on prefs + flags + template availability
│   ├── whatsapp_client.php                        WhatsApp Business API client (mock + live)
│   ├── sms_client.php                             SMS provider client (mock + live)
│   ├── dlt_template_registry.php                  DLT-approved template lookup + render
│   ├── preference_manager.php                     User pref CRUD
│   ├── send_log.php                               Audit-log writer
│   ├── analytics.php                              Delivery rate + bounce summary
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                4 tables
│   ├── install.php                                Post-install seed (default DLT templates)
│   └── upgrade.php
├── templates/
├── lang/
│   ├── en/local_airpay_whatsapp.php
│   └── hi/local_airpay_whatsapp.php
└── tests/
    ├── dlt_template_registry_test.php             9 methods
    ├── preference_manager_test.php                13 methods
    └── channel_router_test.php                    6 methods (28 total)
```

## Tests

3 PHPUnit classes, 28 methods. All run against the mock clients —
no live API calls.

## Open items / next phase

- [ ] Phase C.2 — SSO + WhatsApp OTP for passwordless login
- [ ] Live API flip — requires `[CONFIRM]` + DLT-approved template
      submission to Reliance Jio / Vodafone-Idea TRAI registry
- [ ] Inbound message handling (reply-to-confirm flows)
- [ ] Per-tenant DLT template override
- [ ] WhatsApp media template support (currently text only)
- [ ] Capability decoupling: `:manage_dlt_templates` (compliance) vs
      `:view_send_log` (HR / audit)

## State card created — 2026-05-24

Initial state card. Plugin is in Phase C.1 — mock-mode complete + cron
hooks live; live API still default OFF behind two feature flags +
admin API key requirement.
