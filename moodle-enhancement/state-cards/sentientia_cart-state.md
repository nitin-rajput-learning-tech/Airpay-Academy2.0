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

## 2026-09-26 - ADR-031 decision 3: a learner buys only what the catalogue shows them (1.0.4, 2026092500)

**Defect.** `add_item()`, `checkout()` and `mark_paid()` never looked at the purchased course's
tenant. A /1 learner could post a /177 priced course id to `local_sentientia_cart_add_item`, pay,
and be enrolled in a course their catalogue does not list (and a /77 self-registered learner could
buy an internal Airpay course the same way).

**Decision** (delegated by Nitin, recommended option): the cart sells a buyer only a course the
catalogue shows them. `cart_manager::can_buy_course($courseid, $buyerid)` CALLS
`\local_sentientia_catalog\catalog_manager::assert_course_visible_to_viewer()` rather than copying
it, so the cart cannot drift from the catalogue: visible, and owned by the buyer's tenant tree
(`/`-bounded) or actively shared to it (`local_sentientia_courses_tenant_share`); a guest is the
Public tenant; a buyer with no resolvable tenant buys nothing; a course with no `open_path` is not
sold to a scoped buyer (the catalogue does not list it to them). Cross-tenant buyers (site admin,
`:crosstenant`) return true before any check, exactly as before. The cart does not declare a hard
dependency on the catalogue: without it, `tenant_rule_allows()` applies the same rule check for
check (a test holds the two to the same answers). NOT the looser `tenant::path_filter(..., true)`
NULL-path tolerance: that would sell tenantless courses the catalogue never shows.

Security fix under ADR-031, so NOT feature-flagged. Enforced at every entry point:

- `add_item()`: first, with the same `error_courseunavailable` as "not for sale", so a probe cannot
  tell another tenant's course from a missing one.
- `checkout()`: re-checks every line (`prune_unavailable_items()`): lines no longer buyable (added
  before the gate, or a share withdrawn since) are dropped, totals recomputed, and the checkout is
  REFUSED once with the new `error_itemsunavailable` so the buyer sees the new basket before paying.
  `checkout.php` re-reads the cart after that refusal (and only that one, since 2026-09-29) so the
  page shows what the next submit charges.
- `mark_paid()` (payment callback): enrols only lines `can_buy_course()` allows. The money has been
  taken by then, so the payment is still recorded, invoiced and marked paid; withheld course ids go
  in the order `notes` for a refund, and (since 2026-09-29, below) the buyer and the site admins are
  told. Reachable whenever checkout's check never ran or its answer changed before the gateway
  called back: an order placed before the gate shipped (pending at deploy); a pre-deploy order that
  failed and is retried failed -> paid; a share withdrawn between checkout and payment; the course
  hidden during the gateway window; the buyer's `open_path` changed by the HRMS sync during the
  window.

Airpay's in-tenant flow is unchanged for own-tenant and shared-in courses
(`test_the_in_tenant_purchase_flow_is_unchanged_end_to_end`). Two narrow changes follow from "what
the catalogue shows" and are deliberate: a hidden (`visible = 0`) course and a course with no
`open_path` are no longer sold to a scoped learner (neither is listed to them, and the catalogue's own
enrol path already refuses both). Worth a check on UAT that no priced Airpay course has a NULL
`open_path`. `cli/smoke_cart.php` now picks a test course its /77 user may buy.
No db/ change, so no version bump (already 2026092500). New lang string `error_itemsunavailable`
(en + hi). Tests: `tests/purchase_gate_test.php` (`@group tenant_isolation`), NOT run locally
(low-CPU mode) - CI to run. Both trees.

## 2026-09-29 - ADR-031 decision 3 follow-up: a withheld line is told, not buried (1.0.4, 2026092500)

**Defect (review of the 2026-09-26 change).** When `mark_paid()` withheld enrolment for a line
`can_buy_course()` refused, the only trace was "Refund due" in `history.notes`, which nothing
displayed, and `notifier::order_paid()` still told the buyer every course was accessible.

- `notifier::order_paid($cart, $withheld)`: `mark_paid()` passes the withheld course ids.
  (1) The buyer's `payment_received` message lists only the granted courses; a withheld one is not
  listed, and the new `paid_withheld` line says N course(s) cannot be accessed, were not enrolled and
  will be refunded (no "you can now access your courses" line when nothing was granted).
  (2) The `admin_new_order` message to the site admins (`get_admins()`) gets " - Refund due" in the
  subject and the new `admin_withheld` line naming the order number and the withheld course id(s),
  with the refund instruction (partial refund; a full refund also unenrols the granted courses).
  The admin body no longer dereferences a missing buyer record.
  (3) `get_order` and `list_orders` return `notes`, to `:viewallorders` holders only (the buyer gets
  ''; the buyer is told through the message); `admin_orders.php` shows it in a new "Staff notes"
  column (plain text, escaped by the datatable; no template or AMD change).
- `checkout.php`: the catch that re-reads the cart now fires ONLY for `error_itemsunavailable`. Any
  other `moodle_exception` (a gateway error after `checkout()` moved the order to 'pending', a billing
  error) keeps the page and shows that error, instead of opening a fresh empty cart and redirecting to
  "your cart is empty".
- `cli/smoke_cart.php`: loud header (it re-prices a real course, consumes order and GST invoice
  numbers, writes ledger rows, enrols/unenrols a learner, messages the site admins) and a hard
  refusal unless `$CFG->wwwroot` is a local development host (localhost, 127.0.0.1, [::1],
  *.localhost, *.test). No override flag.
- `mark_paid()` docblock and the reachability text above corrected.

New lang strings `refunddue`, `paid_withheld`, `admin_withheld`, `ordernotes` (en + hi). No db/
change, no version bump (2026092500). Tests (`tests/purchase_gate_test.php`, `@group
tenant_isolation`): the withheld-line test now captures messages (`redirectMessages()`) and asserts
the admin message names the withheld id with "Refund due" and the buyer message does not list the
withheld course; the in-tenant end-to-end test asserts no refund line; new
`test_the_refund_due_note_reaches_order_admins_not_the_buyer` (get_order notes). NOT run locally
(low-CPU mode) - CI to run. Screenshots of the new admin_orders column pending (see
`docs/visual-evidence/2026-09-29/README.md`). Both trees.

### 2026-09-29 (later) - review must-fixes: notes PARAM_RAW, admin orders columns, order # in the note

- `list_orders` / `get_order` declare `notes` as PARAM_RAW: `mark_failed()` stores the raw gateway payload
  there, and PARAM_TEXT made `clean_returnvalue()` throw invalid_response for any note with markup, taking
  the whole order list down. The datatable escapes plain columns; `get_order` callers s() the value.
- `admin_orders.php`: the #, User, Total and Status columns read keys list_orders never returned
  (orderid_link, user_link, total_str, statuslabel) and rendered empty (pre-existing); they now read
  orderid, billing_email, total_amount, status. The empty "actions" column is dropped.
- The ADR-031 refund-due note names the order: "ADR-031: order #N: payment recorded, ...".
- Test: `test_a_gateway_failure_note_with_markup_does_not_break_the_order_lists`; the refund-note test
  asserts the order number.

### 2026-09-30 - persona pass D1: `:purchase` reaches the authenticated-user role (1.0.5 / 2026093001)

Persona pass finding D1 (P0): a real public (/77) or ZEEA (/177) learner holds no system role but
Authenticated user. The role held `local/sentientia_cart:view` (cart page and order history opened)
and NOT `:purchase`, so add-to-cart, remove and checkout were refused with `nopermissions` while the
cart looked enabled. `db/access.php` already listed the `user` archetype (and the README said so), but
Moodle applies archetype defaults only when a capability is FIRST registered, so a site that registered
it earlier never got the grant. `employee`-role users (e.g. `vp_learner177`) could buy, which is why
the ZEEA cart step passed.

- New `db/upgradelib.php::local_sentientia_cart_backfill_user_purchase()`: every role of archetype
  `user` (plus the role with shortname `user`) gets ALLOW for `:purchase` at system context ONLY where it
  has no setting yet. An existing ALLOW, and an administrator's PREVENT or PROHIBIT, are never touched;
  idempotent; only `:purchase` is granted (`:viewallorders`, `:refund`, `:manageprices` are not); the
  guest role, the student archetype and custom roles are left alone.
- `db/upgrade.php` step `2026093001` calls it; `db/install.php` calls it after the rolemap so a fresh
  install and an upgraded site end identically. Comment added to `db/access.php`; README updated.
- Owner decision (Nitin, 2026-09-30, "as recommended"): buying stays gated exactly where it was -
  `cart_manager::is_enabled_for_user()` (`enabled_tenants`) and the ADR-031 catalogue purchase gate
  (`cart_manager::can_buy_course()`); holding the capability widens nothing.
- New `tests/purchase_capability_backfill_test.php` (`@group tenant_isolation`, 13 tests): grant,
  before/after `has_capability` for a /77 learner, idempotence, PREVENT and PROHIBIT stand, existing
  ALLOW untouched, other archetypes and the other cart capabilities untouched, guest still refused, a
  /77 learner can add their own course through the web service and is still refused a /1 or /177 course
  (`error_courseunavailable`), `enabled_tenants` still refuses a tenant it is off for, refusal by
  capability without the back-fill, access.php/upgrade/install/version wiring. NOT RUN (low-CPU
  mode); PHPUnit needs a re-init first because the plugin version changed.
- The storefront-basket to order-cart bridge that makes this reachable from `cart.php` lives in
  `local_sentientia_catalog` (`classes/checkout_bridge.php`, flag
  `sentientia.catalog.storefront_checkout.enabled`, default OFF). Commerce stays dark at go-live: keep the
  flag OFF until the payment gateway has been verified in sandbox.
- UAT: after upgrade, `local/sentientia_cart:purchase` on role `user` should read ALLOW under
  Site administration > Users > Permissions > Define roles; re-run the public77 cart steps (#81-#83).
  Both trees.
