# Customer Configuration Reference — Sentientia LMS

Each Sentientia LMS customer (today: just Airpay) has a configuration file
recording their feature flags, branding tokens, integrations, and SLAs.

## Why this exists

ADR-001 commits to multi-customer architecture from Day 0. Even though Airpay
is the only customer today, we encode their configuration as a reference
implementation that future customers can copy + adapt.

## Folder structure

```
customer-config/
├── README.md (this file)
├── airpay.md (customer-zero — Airpay Payment Services)
└── TEMPLATE.md (for adding future customers)
```

## What a customer-config records

For each customer:

1. **Identity:** legal name, primary domain, tier (Enterprise / Mid-market / SMB)
2. **Tenant tree:** top-level open_path + child tenants
3. **Feature flags:** which Sentientia LMS features are enabled
4. **Branding:** logo URLs, colour palette, typography
5. **Integrations:** which external services are wired (M365, Slack, Teams, WhatsApp, etc.)
6. **SLA / contracts:** uptime targets, support tier, escalation contacts
7. **Compliance:** data residency, retention policies, audit obligations
8. **Pricing:** plan tier, billing model (informational — billing infra is elsewhere)

## Customer-zero record

[`airpay.md`](airpay.md) — Airpay Payment Services configuration

(Will be populated in Session 2 when we extend `local_airpay_core` Switchboard
with customer-level flags.)

## Template for future customers

[`TEMPLATE.md`](TEMPLATE.md) — copy-paste skeleton

(Will be populated in Session 2.)

## Relationship to feature flags

Each entry under "Feature flags" in a customer-config corresponds to a row in
the `local_airpay_feature_flags` table with customer-scope (not just tenant-
scope). The `local_airpay_core\feature_flags::is_enabled($key, $customer, $tenant)`
resolver consults these.

Per-tenant overrides within a customer (e.g., "Airpay tenant gets feature X,
Public tenant doesn't") are also supported — captured at the tenant level
under the customer config.
