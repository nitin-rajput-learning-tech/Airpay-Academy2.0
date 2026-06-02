# State Card — `local_airpay_notifications`

**Component:** `local_airpay_notifications`
**Version:** `2026060200` / `1.4.2`  (+ADR-020 W3.4 org-seam migration of manager digests)
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
├── version.php                                    2026060200 / 1.4.2
├── README.md
├── lib.php
├── index.php                                       Rule registry admin
├── log_detail.php                                  Per-send detail page
├── logs.php                                        Log table
├── cli/                                            CLI tools (replay, dedup)
├── classes/
│   ├── rule_engine.php                            Trigger resolver + send dispatcher (manager digests group via local_sentientia_core\org — ADR-020 W3.4)
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

## ADR-018 Wave 2 — open_path → tenant_identity seam (2026-05-30)

Direct `$USER->open_path` / entity `open_path` parsing in this plugin was migrated
onto the `local_sentientia_core\tenant_identity` seam (`root_for_user` /
`root_for_current_user` / `department_for_user` / `subdepartment_for_user` /
`path_root` / `path_for_user`). Behaviour-identical — the legacy BizLMS parse stays
the default-ON source behind `tenant_identity_legacy`. Shipped via the
feat/wave2-callers-* branches (merged to production 2026-05-30). DEPRECATION-SCHEDULE row 7.

## ADR-020 Wave 3.4 — manager digests → org seam (2026-06-02)

The two manager-aggregate rules — `rule_monthly_summary` (team snapshot) and
`rule_manager_nudge` (3+ overdue) — previously grouped team members by
`u.open_supervisorid` directly in SQL (`GROUP BY open_supervisorid` + a `JOIN` to
the manager row). They now resolve the manager→reports grouping through
`local_sentientia_core\org::reports_by_manager()` (the W3.4 aggregate primitive)
and aggregate the domain data (completions / overdue) over that map.

Behaviour-identical under the default `org_legacy` flag — **proven on the local
prod-data DB: monthly 117 managers with an exact (team_size, completions) match;
nudge 0==0** — and auto-switches to the Sentientia org model at cutover. Deleted /
nonexistent managers are excluded as before (`record_exists`), and a latent
`LIMIT 0` (unset `batch_limit`) in the nudge was fixed to default 500. 20/20
PHPUnit green. version 2026052001 → 2026060200 / 1.4.2.
