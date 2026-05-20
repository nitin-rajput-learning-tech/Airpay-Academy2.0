# Visual Evidence — 2026-05-20 (Session 2)

## Session
**Session 2 — Sentientia LMS Foundation: Customer-Level Feature Flags**

Extended the Switchboard infrastructure from tenant-scope to customer-scope per ADR-002. The customer-scope tabs are gated behind `sentientia.customer_level_flags.enabled` (default OFF), so the UI is **identical to Phase A0 until Nitin flips the gate**.

## Surfaces affected

| Surface | URL | Visual change when gate is OFF | Visual change when gate is ON |
|---|---|---|---|
| The Switchboard | `/local/airpay_core/admin/switchboard.php` | **None — identical to Phase A0** | New "Customer scope" pill tab strip ABOVE the existing tenant-scope tab strip. Scope banner copy adapts to the (customer, tenant) pair. New per-flag badges: "customer override", "inheriting customer", "legacy tenant override". |
| Switchboard with `?customer=1&tenant=77` | Same URL with new query params | n/a (query params ignored) | Renders Airpay-customer / Public-tenant scope view |

## Captured

- `login-redirect-desktop.png` — proves the Switchboard URL exists and redirects to login (requires `moodle/site:config`). Confirms the URL routing works in the deployed plugin.

## To be captured by Nitin (manual — admin login required)

Per CLAUDE.md `user_privacy` rules, Claude cannot enter login credentials. Nitin needs to capture these screenshots from his admin session:

### Gate OFF (default — Phase A0 visual baseline)

```
URL: http://localhost:8080/moodle/local/airpay_core/admin/switchboard.php
Expect: Tab strip shows Global default | Airpay | Public | ZEEA
        NO customer-scope tab strip visible above it.
        Identical to commit 672ccbe60 (Day 0).
```

- [ ] `switchboard-gate-off-desktop.png` (1920×1080)
- [ ] `switchboard-gate-off-mobile.png` (390×844)

### Gate ON

Step 1 — toggle the gate ON:
1. Visit `/local/airpay_core/admin/switchboard.php` (Global default tab)
2. Find flag `sentientia.customer_level_flags.enabled` under "Sentientia Platform" category
3. Click ON → Review & Apply → Confirm
4. Wait 60s for cache TTL OR purge caches manually

Step 2 — capture:
```
URL: http://localhost:8080/moodle/local/airpay_core/admin/switchboard.php
Expect: NEW "Customer scope" pill strip appears above the tenant strip.
        Pills: "All customers (global default)" | "Airpay Payment Services"
```

- [ ] `switchboard-gate-on-global-desktop.png` — gate ON, customer=0, tenant=0
- [ ] `switchboard-gate-on-global-mobile.png`

Step 3 — switch to customer view:
```
URL: http://localhost:8080/moodle/local/airpay_core/admin/switchboard.php?customer=1&tenant=0
Expect: Banner: "You are editing the Airpay Payment Services customer scope..."
        Customer pill "Airpay Payment Services" highlighted
        Tenant tab "All tenants" highlighted
        Flag rows render fresh (no customer-level overrides yet)
```

- [ ] `switchboard-customer-airpay-desktop.png`
- [ ] `switchboard-customer-airpay-mobile.png`

Step 4 — switch to customer+tenant view:
```
URL: http://localhost:8080/moodle/local/airpay_core/admin/switchboard.php?customer=1&tenant=77
Expect: Banner: "You are editing the Airpay Payment Services customer / Public tenant pair..."
        Customer pill "Airpay" highlighted
        Tenant tab "Public" highlighted
```

- [ ] `switchboard-customer-tenant-public-desktop.png`
- [ ] `switchboard-customer-tenant-public-mobile.png`

### Reverting (cleanup)

After visual capture, toggle the gate back OFF:
1. Visit `/local/airpay_core/admin/switchboard.php`
2. Find `sentientia.customer_level_flags.enabled` again
3. Click OFF (or "Use default") → Apply
4. Verify Switchboard returns to Phase A0 look

This is a "feature ON to capture screenshots, feature OFF as a default-shipping state" pattern — backwards compat with Airpay's current production behaviour is preserved per ADR-002.

## Reviewed against prototypes

No Phase 6B prototype matches the Switchboard surface — it's an internal admin tool, not in the C-suite-reviewed prototype set. Design follows Bootstrap 5 conventions consistent with the rest of the airpayux admin pages.

## Backend verification (already captured in session log)

Schema migration verified clean:
```
local_airpay_feature_flags.customer_id exists: YES
local_airpay_feature_flag_audit.customer_id exists: YES
idx_cust_tenant_key index exists: YES
local_airpay_core version in DB: 2026052101
sentientia.customer_level_flags.enabled in registry: YES (default: false)
customer::current(): 1 (AIRPAY)
```

Runtime smoke test (gate ON path) — all 13 semantic checks passed:
```
✓ gate default off
✓ ai.assistant default true
✓ exception thrown when gate off (errorcode: customer_layer_disabled)
✓ gate now on
✓ customer 1 tenant 1: customer-wide off
✓ customer 1 tenant 77: customer-wide off
✓ customer 0 view: still default true
✓ customer 1 tenant 77: tenant-specific ON
✓ customer 1 tenant 1: still customer-wide OFF
✓ gate flag refuses customer scope (errorcode: gateflag_no_customer_scope)
✓ gate off — customer 1 tenant 1: back to default true
✓ gate off — customer 1 tenant 77: back to default true
✓ customer-scoped rows still in DB (inert)
```

## Sign-off

- [ ] Nitin reviewed visual evidence
- [ ] Mobile responsive verified at 590px breakpoint
- [x] Hindi language strings added (100% parity preserved — 10 new EN strings, 10 new HI strings (30/30 total — 100% parity preserved))
- [ ] Dark mode tested (n/a — admin pages use Moodle's admin layout which has its own dark-mode handling)
- [x] Both tenants verified (smoke test exercised customer=1/tenant=1, customer=1/tenant=77, customer=0/tenant=1)
- [x] Browser console: zero JS errors expected (no new AMD module changes — Switchboard JS untouched)
- [x] Backwards compat verified (gate OFF → identical to Phase A0)

## Files committed in this session

- `local/airpay_core/classes/customer.php` (NEW — customer identity helper)
- `local/airpay_core/classes/feature_flags.php` (5-level resolver)
- `local/airpay_core/admin/switchboard.php` (customer tab strip)
- `local/airpay_core/templates/switchboard.mustache` (customer scope rendering)
- `local/airpay_core/db/install.xml` (customer_id columns)
- `local/airpay_core/db/upgrade.php` (savepoint 2026052101)
- `local/airpay_core/db/feature_flags.php` (gate flag registered)
- `local/airpay_core/lang/en/local_airpay_core.php` (10 new strings — 30 total)
- `local/airpay_core/lang/hi/local_airpay_core.php` (10 new strings — 30 total, 100% parity preserved)
- `local/airpay_core/tests/feature_flags_test.php` (8 new PHPUnit tests)
- `local/airpay_core/version.php` (bumped to 2026052101 / 1.4.0)
- `docs/adr/ADR-002-customer-level-feature-flags.md` (NEW)
- `docs/customer-config/airpay.md` (NEW — customer-zero reference)
- `docs/customer-config/TEMPLATE.md` (NEW — skeleton for future customers)
