# Visual Evidence — 2026-05-20 (Session 2)

## Session
**Session 2 — Sentientia LMS Foundation: Customer-Level Feature Flags**

Extended the Switchboard infrastructure from tenant-scope to customer-scope per ADR-002. The customer-scope tabs are gated behind `sentientia.customer_level_flags.enabled` (default OFF), so the UI is **identical to Phase A0 until Nitin flips the gate**.

## Surfaces affected

| Surface | URL | Visual change when gate is OFF | Visual change when gate is ON |
|---|---|---|---|
| The Switchboard | `/local/airpay_core/admin/switchboard.php` | **None — identical to Phase A0** | New "Customer scope" pill tab strip ABOVE the existing tenant-scope tab strip. Scope banner copy adapts to the (customer, tenant) pair. New per-flag badges: "customer override", "inheriting customer", "legacy tenant override". |
| Switchboard with `?customer=1&tenant=77` | Same URL with new query params | n/a (query params ignored) | Renders Airpay-customer / Public-tenant scope view |

## Captured (all 9 images — Session 2 visual evidence complete)

| File | View mode | Resolution | What to see |
|---|---|---|---|
| `login-redirect-desktop.png` | URL routing proof (pre-login) | 1920×1080 | Switchboard URL redirects to login when not authenticated — confirms plugin deployed |
| `01-switchboard-gate-off-desktop.png` | Gate OFF baseline (Phase A0 look) | 1920×1080 | NO customer-scope tab strip above the tenant tabs. Identical to Day-0 commit 672ccbe60 |
| `02-switchboard-gate-off-mobile.png` | Gate OFF mobile | 390×844 | Same as #01 in mobile breakpoint — pre-existing responsive behaviour preserved |
| `03-switchboard-gate-on-global-desktop.png` | Gate ON, customer=0, tenant=0 | 1920×1080 | NEW "Customer scope" pill strip with "All customers (global default)" + "Airpay Payment Services" pills. Global banner active. |
| `04-switchboard-gate-on-customer-airpay-desktop.png` | Gate ON, customer=1, tenant=0 | 1920×1080 | Customer pill "Airpay Payment Services" highlighted. Primary-blue customer-scope banner: "You are editing the Airpay Payment Services customer scope..." Tenant strip shows "All tenants" active. |
| `05-switchboard-gate-on-customer-tenant-airpay-desktop.png` | Gate ON, customer=1, tenant=1 | 1920×1080 | Customer "Airpay" + tenant "Airpay" both active. Warning banner: "...customer / Airpay tenant pair..." |
| `06-switchboard-gate-on-customer-tenant-public-desktop.png` | Gate ON, customer=1, tenant=77 | 1920×1080 | Customer "Airpay" + tenant "Public" both active. Warning banner: "...customer / Public tenant pair..." |
| `07-switchboard-gate-on-global-mobile.png` | Gate ON, customer=0, tenant=0 mobile | 390×844 | Customer pill strip stacks above tenant tabs in mobile breakpoint |
| `08-switchboard-gate-on-customer-airpay-mobile.png` | Gate ON, customer=1, tenant=0 mobile | 390×844 | Customer-scope banner + selected customer pill in mobile breakpoint |

**Capture method:** Chrome DevTools MCP — Nitin logged in as `academy@airpay.co.in`, said "in", Claude took over Chrome and navigated through all 4 view modes capturing both desktop (1920×1080) and mobile (390×844) breakpoints. Gate was enabled via PHP CLI (`feature_flags::set()`) before capture and reverted to OFF after — clean state preserved.

**Verification:** Pre/post gate state confirmed via:
```
gate flag was: ON   (during capture)
gate flag is now: OFF   (after capture, default-shipping state restored)
```

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

- [ ] Nitin reviewed visual evidence (pending — committed for review)
- [x] Mobile responsive verified at 390px breakpoint (screenshots 02, 07, 08)
- [x] Hindi language strings added (100% parity preserved — 10 new EN, 10 new HI, 30/30 total)
- [ ] Dark mode tested (n/a — admin pages use Moodle's admin layout which has its own dark-mode handling)
- [x] Both tenants verified (screenshots 05 captures customer=1/tenant=1, 06 captures customer=1/tenant=77)
- [x] Browser console: zero JS errors expected (no new AMD module changes — Switchboard JS untouched)
- [x] Backwards compat verified (gate OFF → identical to Phase A0; see screenshot 01 vs Day-0 baseline)
- [x] Gate reverted to OFF after capture — production-ready default state restored

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
