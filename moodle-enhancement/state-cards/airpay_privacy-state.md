# State Card — `local_airpay_privacy`

**Component:** `local_airpay_privacy`
**Version:** `2026052001` / `1.0.1`
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. GDPR / DPDP request hub.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Privacy request hub — receives data-export / data-deletion requests
from users and orchestrates the response across every other plugin's
privacy provider. Pairs with Moodle's built-in `tool_dataprivacy`
flow but adds an Airpay-specific consent-log table for marketing /
analytics opt-ins.

## DB tables (2)

| Table | Purpose |
|-------|---------|
| `local_privacy_requests` | Per-user data-export / data-deletion request rows |
| `local_privacy_consent_log` | Append-only consent ledger (marketing opt-in, analytics opt-in, etc.) |

## Capabilities (2)

`local/airpay_privacy:` `view`, `manage`.

## Feature flags

None registered.

## Key files

```
local/airpay_privacy/
├── version.php                                  2026052001 / 1.0.1
├── README.md
├── index.php                                     Privacy request dashboard
├── styles.css
├── classes/
│   └── privacy_manager.php                       Request orchestrator
├── db/
│   ├── install.xml                              2 tables
│   └── upgrade.php
├── templates/
└── lang/
    ├── en/local_airpay_privacy.php
    └── hi/local_airpay_privacy.php
```

## Tests

None at the plugin level. Per-plugin privacy providers are tested in
their own `tests/privacy/provider_test.php` files.

## Open items

- [ ] PHPUnit smoke for `privacy_manager` orchestrator (priority)
- [ ] Per-customer consent banner customisation
- [ ] Auto-expire stale data-export request files
- [ ] Behat coverage of the request dashboard
- [ ] Integration audit — verify every `local_airpay_*` and
      `local_sentientia_*` plugin has a privacy/provider.php class
- [ ] Reference card: how to extend Moodle's privacy provider
      pattern with Airpay-specific consent records

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.
