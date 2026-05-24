# State Card — `local_airpay_notifications`

**Component:** `local_airpay_notifications`
**Version:** `2026052001` / `1.4.1`  (+P1 #48 Hindi top-up)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Generic notification rule engine.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Generic, rule-driven notification dispatcher — the abstraction layer
that `local_airpay_emails`, `local_airpay_whatsapp`, and
`local_sentientia_pwa` plug into. Lets admins define WHEN + WHAT to
send without per-channel forking; the rule engine routes through
whichever channel is enabled for the user.

Phase C (notifications cleanup) gave this plugin its current shape;
Phase C.1 (2026-05-21) added the WhatsApp / SMS hook.

## DB tables (3)

| Table | Purpose |
|-------|---------|
| `local_airpay_notif_rules` | Rule definitions (trigger, audience, channel-allowlist, template) |
| `local_airpay_notif_log` | Send-attempt audit (per-channel result) |
| `local_airpay_notif_prefs` | Per-user channel-routing prefs (uses tenant defaults if missing) |

## Capabilities (3)

`local/airpay_notifications:` `view`, `manage`, `viewlogs`. The log
read cap is split so compliance can see delivery without edit rights.

## Feature flags

None registered directly. Consumes channel-master flags from
`local_airpay_core`:
- `engagement.whatsapp.enabled`
- `engagement.sms.enabled`
- `engagement.whatsapp.reminders` (Phase C.1)
- `engagement.whatsapp.overdue` (Phase C.1)

## Key files

```
local/airpay_notifications/
├── version.php                                    2026052001 / 1.4.1
├── README.md
├── lib.php
├── index.php                                       Rule registry admin
├── log_detail.php                                  Per-send detail page
├── logs.php                                        Log table
├── cli/                                            CLI tools (replay, dedup)
├── classes/
│   ├── rule_engine.php                            Trigger resolver + send dispatcher
│   ├── rule_manager.php                           Rule CRUD
│   ├── prefs_manager.php                          User pref CRUD
│   ├── external/                                  WS endpoints
│   ├── form/                                      Forms
│   ├── task/                                      Scheduled rule walker
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                3 tables
│   ├── upgrade.php
│   └── access.php                                 3 capabilities
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_notifications.php
│   └── hi/local_airpay_notifications.php          (100% parity post-P1 #48)
└── tests/
    ├── crud_test.php                              5 methods
    ├── rule_engine_phase_c_test.php               10 methods (Phase C)
    └── external/list_rules_test.php               5 methods (20 total)
```

## Tests

3 PHPUnit classes, 20 methods. `rule_engine_phase_c_test` is the
deepest — covers the channel-routing matrix.

## Open items

- [ ] Per-customer channel-allowlist (today: per-tenant only)
- [ ] Inbound-reply handling (WhatsApp / SMS) — Phase C.2
- [ ] Cohort-scoped rules (today: tenant + role-archetype only)
- [ ] Behat coverage of the rule editor form
- [ ] Cron health surface — overdue rule walker showing inside
      `block_airpay_cron_health`
- [ ] Per-channel retry policy (today: best-effort once)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. Phase C cleanup gave this plugin
its current rule-engine shape; Phase C.1 added the WhatsApp hooks.
