# State Card — `local_airpay_integrations`

**Component:** `local_airpay_integrations`
**Version:** `2026052001` / `1.1.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. External-service integration hub.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Single home for outbound integrations with external services that
don't warrant their own plugin. Today: Keka (Airpay HRMS), Microsoft
Teams (webhook notifier), web-push fallback, AI-driven course
recommender stub. Each client is independent and runs only when its
caller's flag is ON.

Acts as an audit-funnel — every external call writes a row to
`local_airpay_integration_log` for diagnostics.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_airpay_integration_log` | Append-only audit of every outbound call (service, endpoint, status, response excerpt, latency) |

## Capabilities

None declared. Surfaces gate on `moodle/site:config`.

## Feature flags

None registered directly. Consumed flags depend on the specific
integration (e.g. `engagement.whatsapp.enabled` for some teams_notifier
paths — handled inside `local_airpay_whatsapp` though).

## Key files

```
local/airpay_integrations/
├── version.php                                  2026052001 / 1.1.1-beta
├── README.md
├── lib.php
├── settings.php
├── webhook.php                                   Generic inbound webhook receiver
├── classes/
│   ├── keka_client.php                          Keka HRMS API client
│   ├── teams_notifier.php                        Microsoft Teams webhook poster
│   ├── web_push.php                              Browser push fallback (parallel to sentientia_pwa)
│   ├── ai_recommender.php                        AI course recommender stub
│   └── privacy/                                  GDPR / DPDP
├── db/
│   ├── install.xml                              1 table
│   └── upgrade.php
├── lang/
│   ├── en/local_airpay_integrations.php
│   └── hi/local_airpay_integrations.php
└── tests/
    ├── schema_test.php                          5 methods
    ├── ai_recommender_test.php                  4 methods
    └── keka_client_test.php                     2 methods (11 total)
```

## Tests

3 PHPUnit classes, 11 methods. All clients mock outbound HTTP via
PHPUnit fixtures.

## Open items

- [ ] Phase 2 — fold `web_push.php` into `local_sentientia_pwa`
      (today: redundant fallback paths)
- [ ] Behat coverage of the inbound webhook handler
- [ ] Per-integration enable/disable feature flags
- [ ] Latency + error-rate dashboard widget
- [ ] Slack notifier (companion to teams_notifier)
- [ ] Cleanup pass — `ai_recommender.php` is stubbed; real recommender
      shipped in Phase B (different plugin)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.
