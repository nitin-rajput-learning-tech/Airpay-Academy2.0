# State Card — `local_airpay_core`

**Component:** `local_airpay_core`
**Version:** `2026052303` / `1.5.3`  — +P0 #11 backup_filename helper + admin setting
**Status:** STABLE — foundation plugin; everything else depends on this
**Maturity:** `MATURITY_STABLE`
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

The foundation plugin every other `local_airpay_*` / `local_sentientia_*`
plugin depends on. Owns four cross-cutting concerns:

1. **Feature flags** — the resolver that every other plugin queries via
   `\local_airpay_core\feature_flags::is_enabled(...)`. Walks every
   plugin's `db/feature_flags.php`, merges with the customer + tenant
   DB override layer, returns the effective state per call.
2. **Customer + tenant helpers** — `\local_airpay_core\customer` and
   `\local_airpay_core\tenant`. Multi-customer routing + tenant root
   detection (`open_path` parser, customer-id from URL/host/cookie).
3. **Cross-cutting infrastructure** — backup filename helper (P0 #11),
   structured logger, mobile bottom-nav hook, cron health summary,
   feature flag audit log, PII-masking CLI for dev databases.
4. **Customer branding** — `local_airpay_customer_brand` table + admin
   UI for per-customer logo / colour / favicon overrides (ADR-008).

## Architecture decision

See [ADR-002 — Customer-level feature flags](../docs/adr/ADR-002-customer-level-feature-flags.md)
for the flag resolver design and [ADR-008](../docs/adr/) for the
customer-brand table design.

## Database schema (3 tables)

| Table | Purpose |
|-------|---------|
| `local_airpay_feature_flags` | Customer + tenant overrides for declared flags. Empty by default — flags use their declared default until a row is inserted here. |
| `local_airpay_feature_flag_audit` | Append-only audit of every flag toggle (who, when, customer/tenant scope, from→to). |
| `local_airpay_customer_brand` | Per-customer branding overrides (logo URLs, primary/accent/bg colours, favicon, font family). |

## Capabilities

None declared in `db/access.php` (file doesn't exist). Admin surfaces
(switchboard + style guide) gate on `moodle/site:config` directly.

## Feature flags registered (11 in this plugin)

| Flag | Default | Notes |
|------|---------|-------|
| `ai.assistant.enabled` | ON | Master switch for AI chat assistant drawer. |
| `ai.sentientia.enabled` | OFF | SOP→SCORM authoring pipeline (Phase B1). |
| `ai.recommendations.enabled` | OFF | Personalised course recommendations (Phase B). |
| `engagement.gamification.enabled` | ON | Master switch for points, badges, streaks. |
| `engagement.gamification.confetti` | ON | Confetti animation on course completion. |
| `engagement.whatsapp.enabled` | OFF | WhatsApp Business API notification channel (Phase Α1). |
| `engagement.sms.enabled` | OFF | SMS fallback channel via Twilio (Phase Α1). |
| `engagement.whatsapp.reminders` | OFF | Sub-channel: incomplete-course reminders (Phase C.1). |
| `engagement.whatsapp.overdue` | OFF | Sub-channel: manager overdue alerts (Phase C.1). |
| `identity.sso.enabled` | OFF | SSO master switch (Phase C.2). |
| `sentientia.customer_level_flags.enabled` | ON | Whether the customer-level override layer is active. When OFF, only system + tenant overrides resolve. |

## Key files

```
local/airpay_core/
├── version.php                              2026052303 / 1.5.3
├── README.md
├── settings.php                              Admin settings
├── admin/
│   ├── styleguide.php                        Design-system reference UI
│   └── switchboard.php                       Feature-flag toggle UI
├── classes/
│   ├── feature_flags.php                     Resolver + audit writer
│   ├── customer.php                          Customer-id detection + lookup
│   ├── tenant.php                            Tenant-root from open_path
│   ├── customer_brand.php (implied)          Per-customer branding helper
│   ├── backup_filename.php                   P0 #11 — branded backup filenames
│   ├── cron_health.php                       Cron stuck-task summary (used by block_airpay_cron_health)
│   ├── cm_navigation.php                     Course-module nav helper
│   ├── audit_log.php                         Generic append-only audit-log writer
│   ├── structured_logger.php                 JSON logger for structured ops events
│   ├── user_status.php                       Combined user-status helper
│   ├── hook_callbacks.php                    Mobile bottom-nav injector
│   ├── task/                                 Scheduled tasks
│   └── phpunit/                              Test helpers shared with other plugins
├── cli/
│   ├── cron_health.php                       CLI mirror of the dashboard widget
│   ├── mask_pii_for_dev.php                  Dev-DB PII scrub
│   └── verify_brand_resolver.php             Sanity-check customer-brand resolution
├── db/
│   ├── install.xml                           3 tables
│   ├── install.php                           Post-install seed
│   ├── upgrade.php
│   ├── caches.php                            Application caches definitions
│   ├── feature_flags.php                     11 flags shipped in this plugin
│   ├── hooks.php                             before_footer_html_generation callback
│   └── tasks.php                             Scheduled task registry
├── pix/                                       Brand pix
├── templates/
├── amd/
├── lang/
│   ├── en/local_airpay_core.php
│   └── hi/local_airpay_core.php              (100% parity)
└── tests/
    ├── feature_flags_test.php                20 methods
    ├── customer_brand_test.php               19 methods
    ├── tenant_test.php                       17 methods
    ├── backup_filename_test.php              10 methods
    ├── user_status_test.php                  9 methods
    ├── structured_logger_test.php            8 methods
    ├── cron_health_test.php                  6 methods
    ├── cm_navigation_test.php                5 methods
    └── audit_log_test.php                    4 methods (98 total)
```

## Tests

98 PHPUnit methods across 9 classes. Each cross-cutting class has its
own test file. `tests/phpunit/` (under `classes/`) provides shared
testing helpers other plugins reuse (data generators, etc.).

## Public API consumers

Every other `local_airpay_*` and `local_sentientia_*` plugin depends on:

- `\local_airpay_core\feature_flags::is_enabled($flag_key, $customerid?, $tenantid?)` — primary gate
- `\local_airpay_core\customer::current_id()` — request-scoped customer id
- `\local_airpay_core\tenant::root_for_current_user()` — tenant root from `open_path`
- `\local_airpay_core\backup_filename` — branded backup file names (P0 #11)
- `\local_airpay_core\cron_health::summary()` — used by `block_airpay_cron_health`

## Open items

- [ ] Customer-brand admin UI is currently CLI-only (`verify_brand_resolver.php`);
      build a `/local/airpay_core/admin/brands.php` page (Phase D)
- [ ] Switchboard pagination — currently renders every flag on one page
- [ ] Per-tenant flag-override UI (only customer + system overrides have UI today)
- [ ] Capability split: separate `:manage_flags` from `:view_flags` so
      compliance auditors can read the switchboard without edit rights

## State card created — 2026-05-24

Initial state card. This plugin pre-dates the state-card convention
but is foundational to everything else, so creating a card now lets
parallel chips reference it without re-deriving the inventory.
