# State Card — `local_airpay_cart`

**Component:** `local_airpay_cart`
**Version:** `2026052001` / `1.0.2`  (+P1 #57 Hindi pack)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Course-commerce + invoicing.
**Last refreshed:** 2026-05-24 (P1 state-card pass)

---

## Mission

Course commerce — shopping cart, checkout, payment gateway integration,
GST-compliant invoicing, refunds, credit ledger. Sibling to
`paygw_airpay` which is the gateway-side half (this plugin owns the
cart + order lifecycle; paygw_airpay processes the actual charge).

## DB tables (5)

| Table | Purpose |
|-------|---------|
| `local_airpay_cart_id` | Order number sequence (atomic counter) |
| `local_airpay_cart_history` | Cart + order history (one row per order with line items in JSON) |
| `local_airpay_cart_ledger` | Append-only payment ledger (charges, refunds, credits) |
| `local_airpay_cart_credits` | Per-user credit balances (refunds, promotional credits) |
| `local_airpay_cart_invoices` | Issued invoices (GST-compliant, India regulatory) |

## Capabilities (5)

`local/airpay_cart:` `view`, `purchase`, `viewallorders`, `refund`,
`manageprices`.

## Feature flags

None registered.

## Key files

```
local/airpay_cart/
├── version.php                                  2026052001 / 1.0.2
├── README.md
├── admin_orders.php                              Admin order list
├── checkout.php                                  Checkout flow
├── callback.php                                  Payment callback
├── daily_sums.php                                Daily sums report
├── daily_sums_csv.php                            CSV export
├── cli/                                            Operations
├── classes/
│   ├── cart_manager.php                          Cart CRUD + price calc
│   ├── invoicer.php                              GST-compliant invoice generator
│   ├── notifier.php                              Order notification dispatcher
│   ├── callback_logger.php                       Gateway callback audit
│   ├── ip_check.php                              IP-allowlist gate for gateway callback
│   ├── gateway/                                  Gateway abstraction layer
│   ├── external/                                  WS endpoints
│   └── privacy/                                   GDPR / DPDP
├── db/
│   ├── install.xml                                5 tables
│   ├── upgrade.php
│   └── access.php                                 5 capabilities
├── lang/
│   ├── en/local_airpay_cart.php
│   └── hi/local_airpay_cart.php                   (100% parity post-P1 #57)
└── (tests/ — sparse coverage to extend; see Open Items)
```

## Tests

Sparse PHPUnit coverage today. Gateway-side tests live in
`paygw_airpay/tests/` (checksum, helper, gateway interface).

## Open items

- [ ] PHPUnit for `cart_manager` price-calc + ledger writes (priority)
- [ ] PHPUnit for `invoicer` GST-rate matrix
- [ ] Behat coverage of the checkout flow
- [ ] Per-customer pricing rules (today: per-course flat)
- [ ] Subscription / recurring billing (today: one-off only)
- [ ] WhatsApp / SMS payment receipt (Phase C.1)

## State card created — 2026-05-24

Initial state card. Plugin has been live for many phases; created now
as part of the P1 state-card pass. Pairs with `paygw_airpay`.

## 2026-09-22 - Tenant path-boundary sweep (platform-wide)

A repo-wide scan for unbounded tenant/org path prefixes found this plugin among them. A materialised
path prefix must be `/`-terminated AND match the node itself; `'/1' . '%'` also matches `/177`, so an
Airpay-scoped query silently included the ZEEA tenant. The same defect had already shipped four times
(admin dashboard, compliance BU filter, department scorecard, org-children picker) and is invisible in
use: nothing errors, only the numbers come out wrong.

Smoke CLI used a literal `LIKE '/77%'`.

Fixed via the new `\local_sentientia_platform	enant::path_descendant_filter()` (exact-or-descendant
for an arbitrary path), locked by a DB-level boundary suite in `tenant_test.php`, and prevented from
returning by `tools/check-path-boundary.php` - pre-commit CHECK 18 and the `path-boundary-check` CI job.


## 2026-09-22 - First test suite (W1-04). It had none.

This is the only surface in the product that moves money and grants a paid
entitlement, and it shipped with **no `tests/` directory at all**.

`tests/payment_callback_test.php` now covers the callback path. The case that
matters:

```php
// airpay_gateway::verify_callback(), before 2026-09-22
$secret = get_config('local_sentientia_cart', 'airpay_secret');  // ships ''
$expected = self::compute_checksum($payload, $secret);
return hash_equals($expected, $payload['checksum']);
```

With the shipped default secret of `''`, `compute_checksum($payload, '')` is
fully computable by anyone who has read this open-source file. A
self-registered learner could sign their own callback and receive a free
enrolment plus a genuine tax invoice. The IP allowlist in `callback.php`
narrows who can reach the endpoint; it is not a signature check, and it is
empty by default too.

`test_an_unconfigured_gateway_refuses_a_self_signed_callback()` performs that
exact attack and asserts it now fails. The fail-closed guard landed in commit
`bc6178610`; this suite is what stops it being refactored away.

Also covered: a whitespace-only secret is still unconfigured; a correctly
signed callback is still accepted (so the suite cannot pass with
`verify_callback()` hardcoded to `false`); mutating `amount`, `order_id`,
`currency_code` or the status after signing invalidates the checksum; a missing
or empty checksum is refused; the wrong secret is refused; only an explicit
success status enrols; the transaction reference is read from all three field
spellings; a **replayed** callback writes no second ledger row and issues no
second invoice; a refunded order cannot be re-marked paid; and a failed order
can still be paid on a genuine retry.


## 2026-09-22 - Privacy provider did not declare every table it owns

`privacy_coverage_test` (new, in `local_sentientia_platform`) walks every Sentientia plugin's
`install.xml` and fails the build when a plugin holding a user-identifying column does not declare
it. It found eleven such tables across six plugins on its first run. This plugin held two:

- `local_sentientia_cart_id` - the open shopping basket
- `local_sentientia_cart_credits` - the training-credit balance

This is the harder version of the `null_provider` bug. A provider that declares *some* of its
tables makes the Privacy registry page read as complete, so nobody looks again. A subject-access
request returned a partial answer and an erasure request left rows behind, in both cases reporting
success.

Neither is a tax record, so unlike the invoice and ledger rows this provider deliberately preserves, both are **deleted** on erasure rather than redacted. Keeping a redacted shopping basket serves no audit purpose and still links a row to a user id. The existing `redact_for_user()` reasoning was extended, not replaced.

`get_contexts_for_userid()` and `get_users_in_context()` also only ever looked at `cart_history`, so a user who had only filled a basket, or who held a credit balance and had never ordered, was reported as having no data here at all.

Version bumped to 2026092202 so the cached privacy registry picks up the new declarations. en + hi
strings added at parity.


## 2026-09-24 - White-label display name (W2-06)

`pluginname` no longer carries the Airpay brand: "Airpay X" became "Sentientia X", and in Hindi
"एयरपे" became "सेंटिएंटिया". Where one tree already had a Sentientia name it was reused, so both trees now
agree. Lang-string change only: no version bump is needed, and the deploy's cache purge picks it up.
Part of the 36-plugin rename that makes Site administration > Plugins show no customer brand on a
white-label product. `paygw_airpay` keeps "Airpay", correctly: it is named after the payment company.

## 2026-09-25 - ADR-031: admin surfaces scoped to the caller's tenant (1.0.4, 2026092500)

Tenant admins hold a manager-archetype role at system context, so they hold `:viewallorders` and
`:manageprices` (both kept: they are legitimate in-tenant functions). Four surfaces let those
capabilities decide WHERE as well as WHAT:

- `daily_sums_csv.php` ran its own copy of the daily-sums query with no tenant join: every tenant's
  payment and refund totals. It now shares `cart_manager::daily_sums()` with the web service
  (tenant-scoped, fails closed; read through a recordset so two gateways on one day are both kept).
- `invoice.php` let any `:viewallorders` holder open any tenant's invoice (billing name, email,
  address, GSTIN) by sequential id. Now `invoicer::require_view_access()`: the owner, or a holder in
  the invoice's tenant.
- `set_course_price` checked the cap at course context, which a system-level grant satisfies for every
  course in every tenant, so a tenant admin could disable, re-price or add a fee on any tenant's
  course. Now `cart_manager::require_course_in_tenant()` (a course with no `open_path` is refused for a
  scoped caller). `set_price.php` lists only the caller's tenant (`cart_manager::list_course_prices()`).
- `tenant::require_access()` matched a tenantless viewer against every tenant-0 order. `get_order`,
  `refund_order` and invoices use `cart_manager::require_order_tenant()`, which refuses that.

The false "system-level grants silently no-op" comment in `db/upgrade.php` and the README B9 note are
corrected. Site admins (and `local/sentientia_platform:crosstenant` holders) are unchanged. No
capability change, so no revoke step. Depends on platform 2026092500. Tests:
`tests/tenant_scope_test.php` (`@group tenant_isolation`). Both trees.
