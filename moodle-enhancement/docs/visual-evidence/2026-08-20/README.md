# Visual evidence — 2026-08-20 (Customer-N demo: ADR-028 Phase 2.1 / ADR-021 Gate B)

**The white-label claim is now demonstrable.** `customer::current()` de-hardwired
(platform 2026082000) — resolves through `tenant_registry::customer_of()` when the
registry is live; byte-identical Phase-0 behaviour (AIRPAY) while dormant, which
is the default and every production deployment today.

## Local execution transcript (CLI, all assertions passed)

1. `seed_tenants.php --dry-run` then live: customer `airpay` (id 1) + tenants
   1/77/177 registered.
2. `parity_check_tenants.php`: **100% PARITY ✓** registry == legacy allow-list.
3. Demo customer per DEMO-TENANT-PLAN §3: `[DEMO] Meridian Financial Services`
   (id 2, shortname `meridian`) + tenant root 500 "Meridian HQ"
   (idnumber DEMO-MERIDIAN) + demo learner `qa_demo_learner` (open_path=/500).
4. `tenant_registry_legacy` flipped **OFF (local only)** — registry LIVE:

   | Check | Result |
   |---|---|
   | valid_roots | [1, 77, 177, 500] |
   | is_valid(500) | true |
   | customer_of(500) / customer_of(1) | 2 / 1 |
   | roots_for_customer(2) | [500] |
   | customer::current() as qa_demo_learner | **2 (Meridian)** |
   | customer::current() as qa_employee | **1 (Airpay)** |
   | per-customer flag (skillsrecs: cust 1 vs cust 2) | **true vs false** |

   The last row also exercised `sentientia.customer_level_flags.enabled`
   (flipped ON locally) — ADR-002's 5-level resolution proven at the
   customer layer for the first time.

## What remains for a full SALES demo (DEMO-TENANT-PLAN, Nitin-gated)

Branding row for Meridian (`local_sentientia_customer_brand` — logo/palette),
seed courses/personas in tenant 500, and the plan's open decision
**[NITIN DECIDES] Option A (demo tenant on product instance) vs Option B
(separate instance)**. The ENGINEERING claim — customer #2 is a config task,
not a project — is closed by this evidence.

PHPUnit: `customer_resolution_test.php` (4 tests: dormant=Phase-0-identical,
live resolution, unscoped callers stay customer-zero, suspended-tenant
fallback) — result recorded in PROJECT-STATE.
