# State Card — `local_airpay_analytics`

**Component:** `local_airpay_analytics`
**Version:** `2026052001` / `1.0.1-beta`
**Maturity:** `MATURITY_BETA`
**Status:** Live on airpay.academy. L&D analytics dashboards.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

L&D analytics dashboards — aggregate views of engagement,
completion rate, time-to-completion, top courses, top tenants. Reads
from core Moodle tables + Airpay tables; no own schema.

Distinct from `local_airpay_reports` (which is a saved-report builder
for ad-hoc queries); this plugin is a curated dashboard with built-in
KPI tiles + drill-down.

## DB tables

None — read-only across `mdl_course`, `mdl_course_completions`,
`mdl_logstore_standard_log`, `local_airpay_user_skill_hist`, etc.

## Capabilities

None declared explicitly. Gate is `moodle/site:viewreports` from core.

## Feature flags

None registered.

## Key files

```
local/airpay_analytics/
├── README.md
├── index.php                                     Dashboard landing
├── drilldown.php                                 Per-KPI drill-down
├── export.php                                    CSV export
├── styles.css
├── classes/
│   ├── analytics_manager.php                     KPI aggregation queries
│   └── privacy/                                  GDPR / DPDP
├── db/
│   └── (no install.xml — read-only plugin; no version.php at top
│         level either, plugin uses defaults from settings.php)
├── templates/
├── lang/
│   ├── en/local_airpay_analytics.php
│   └── hi/local_airpay_analytics.php
└── tests/                                       1 PHPUnit class / 5 methods
```

## Tests

1 PHPUnit class, 5 methods.

## Open items

- [ ] PHPUnit extension for `analytics_manager` query correctness
- [ ] Per-tenant + per-customer dashboard scoping (today: site-wide
      for admin / per-tenant for tenant manager)
- [ ] Time-series charts (today: snapshot tiles only)
- [ ] Behat coverage of the drill-down flow
- [ ] Caching layer — KPI queries against 3,500+ users get slow at peak
- [ ] Replace LearnerScript blocks (legacy reports surface)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass.
