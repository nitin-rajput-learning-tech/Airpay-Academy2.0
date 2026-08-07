# State Card — `local_sentientia_integrations`

**Component:** `local_sentientia_integrations`
**Version:** `2026080700` / `1.2.0-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. External-service integration hub.
KeKa JML surface hardened + flag-gated (dark) as of 2026-08-07.
**Last refreshed:** 2026-08-07 (KeKa JML hardening — ADR-029)

---

## Mission

Single home for integrations with external services that don't warrant
their own plugin. Today: KeKa (Airpay HRMS) inbound JML sync, Microsoft
Teams (webhook notifier), web-push fallback, AI-driven course recommender
stub. Acts as an audit funnel — inbound webhooks + reconcile runs write
rows to `local_sentientia_integration_log`.

## DB tables (1)

| Table | Purpose |
|-------|---------|
| `local_sentientia_integration_log` | Append-only audit of inbound webhook + sync events (source, event_type, payload, status, errormsg) |

## Capabilities

None declared. Surfaces gate on `moodle/site:config`.

## Feature flags (`db/feature_flags.php`, added 2026-08-07)

| Flag | Default | Gates |
|---|---|---|
| `sentientia.hrms.webhook.enabled` | OFF | `webhook.php` (plus `hrms_enable` setting) |
| `sentientia.hrms.reconcile.enabled` | OFF | `task\keka_reconcile` (plus `hrms_enable` + task registered disabled) |

## KeKa JML surface (2026-08-07 — ADR-029)

- `webhook.php`: flag+setting gate, `hash_equals` on the
  `X-Webhook-Secret` header only (`?secret=` GET path removed), log row
  updated by insert id with `errormsg` on failure.
- `keka_client::upsert_employee()` — the ONE canonical JML write path
  (webhook + reconcile task): `user_create_user`/`user_update_user`,
  identity by `open_employeeid` → email, dept-code → org-shortname tenant
  mapping with validated `keka_default_orgpath` default (`/1`),
  `reportsTo` → `open_supervisorid` two-pass manager sync.
- Leaver: employeeId fallback lookup, suspend via `user_update_user`,
  sessions destroyed.
- `task\keka_reconcile` — nightly 02:30 reconciliation backstop,
  registered disabled. NOT a resurrection of the 2026-05-07-deleted
  duplicate task (INTEGRATIONS-AUDIT.md §3.2 addendum).
- **OPEN (external):** live KeKa contract verification — event names,
  payload shapes, `get_employee` envelope, egress IPs for a reverse-proxy
  allowlist. Assumptions marked in code + README. Blocks production enable.

## Key files

```
local/sentientia_integrations/
├── version.php                          2026080700 / 1.2.0-beta
├── README.md                            Rewritten 2026-08-07 (stale claims fixed)
├── lib.php
├── settings.php                         KeKa section on lang strings + keka_default_orgpath
├── webhook.php                          KeKa JML inbound receiver (gated)
├── classes/
│   ├── keka_client.php                  KeKa HRMS API client + canonical upsert
│   ├── task/keka_reconcile.php          Nightly reconciliation (disabled by default)
│   ├── teams_notifier.php               Microsoft Teams webhook poster
│   ├── web_push.php                     Browser push fallback (parallel to sentientia_pwa)
│   ├── ai_recommender.php               AI course recommender stub
│   └── privacy/                         GDPR / DPDP
├── db/
│   ├── install.xml                      1 table
│   ├── upgrade.php
│   ├── tasks.php                        keka_reconcile registration (disabled)
│   └── feature_flags.php                2 flags, default OFF
├── lang/
│   ├── en/local_sentientia_integrations.php
│   └── hi/local_sentientia_integrations.php   (100% parity)
└── tests/
    ├── schema_test.php                  5 methods
    ├── ai_recommender_test.php          4 methods
    └── keka_client_test.php             12 methods (JML paths, 2026-08-07)
```

## Tests

3 PHPUnit classes, 21 methods. `keka_client_test.php` covers the JML
paths with `bizlms_fixture` (gating, joiner/mover/leaver, identity
matching, tenant placement, manager two-pass, webhook-source regression
locks). Outbound HTTP paths (OAuth, paged pull) remain network-bound and
untested locally.

## Open items

- [ ] **Live KeKa contract verification** (see above) — external, blocks
      production flag flip
- [ ] Phase 2 — fold `web_push.php` into `local_sentientia_pwa`
- [ ] Behat coverage of the inbound webhook handler
- [ ] Latency + error-rate dashboard widget
- [ ] Slack notifier (companion to teams_notifier)
- [ ] Cleanup pass — `ai_recommender.php` is stubbed; real recommender
      shipped in Phase B (different plugin)
