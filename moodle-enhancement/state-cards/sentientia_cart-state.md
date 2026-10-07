# State Card — `local_airpay_cart`

**Component:** `local_airpay_cart`
**Version:** `2026100701` / `1.1.1`  (was `2026052001` / `1.0.2` when this card was first written; see the dated sections below)
**Maturity:** `MATURITY_STABLE`
**Status:** Live on airpay.academy. Course-commerce + invoicing.
**Last refreshed:** 2026-10-07 (owner decisions, finance cluster: finance keys declared, withheld-line refund amounts, notifier in the recipient's language, dev masking; catalogue price source in the catalog card)

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

Registered in `db/feature_flags.php`, both default **OFF** (ADR-032): `sentientia.cart.imported_orders.enabled` and `sentientia.cart.imported_credits.enabled`. No flag was added on 2026-10-07; none was flipped.

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
cart looked enabled. `db/access.php` has listed the `user` archetype for `:purchase` since the plugin
was first written (commit c44256473, then `local_airpay_cart`), so the archetype list did NOT change
(the triage's "archetype list changed" is wrong; corrected in review round 1 below). The role still
lacked the row: most likely the capability was first registered outside `update_capabilities()` (the
earlier CLI patch, or the rename `--migrate-caps` path), and only `update_capabilities()` applies
archetype defaults, and only when it inserts the capability itself. That is inferred from git history and
the rename tooling, not confirmed on the affected database. `employee`-role users (e.g. `vp_learner177`)
could buy, which is why the ZEEA cart step passed.

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

### 2026-09-30 - persona pass D1 review round 1: add_item loads its own price lookup, back-fill hardened (1.0.6 / 2026093002)

Adversarial review of the D1/D2 bundle returned fix-then-ship. Closed here:

- MUST FIX (would have crashed the bridge and the web service): `cart_manager::add_item()` called the
  GLOBAL `local_sentientia_cart_get_course_price()`, defined only in `lib.php`. Moodle loads a plugin's
  `lib.php` only when a callback that plugin defines is looked up, and this one defines only
  `extend_navigation_user_settings`, so on `POST cart.php?action=checkout` and on the
  `local_sentientia_cart_add_item` web service (neither loads it) the call was an undefined function
  (`\Error`, not a `moodle_exception`). Both new test files `require_once`d `lib.php`, which hid it. Fixed at
  the source: the price logic is now `cart_manager::get_course_price()` (autoloaded), `add_item()` calls
  `self::get_course_price()`, and the `lib.php` function is a thin wrapper so `cli/smoke_cart.php` and any
  other caller keep working. Both pre-includes are removed from the tests; `purchase_capability_backfill_test`
  and `storefront_checkout_test` each read `add_item()`'s source and assert it prices through the class (a
  single PHPUnit process cannot prove "lib.php not loaded", because any earlier callback lookup includes
  it). New tests for `get_course_price()` (class and wrapper agree; a disabled instance or zero cost is not a
  price).
- Back-fill: `local_sentientia_cart_backfill_user_purchase()` now also selects the role
  `$CFG->defaultuserroleid` points at (how Moodle itself identifies Authenticated user), so a restored
  BizLMS database that renamed the role and cleared its archetype is still covered. Same rules: only a
  missing row is filled; an existing ALLOW, PREVENT or PROHIBIT is never touched. Two new tests (renamed
  default role is granted and a non-default custom role is not; a PROHIBIT on the default role stands).
- Test that proved nothing: `test_the_guest_role_does_not_gain_purchase` asserted `has_capability()` for the
  guest user, which refuses every write capability whatever the roles say. Replaced by
  `test_the_guest_role_gains_no_purchase_row`, which asserts the guest role has no `role_capabilities` row for
  `:purchase` after the back-fill.
- Wording: `db/access.php` header pointed at `local_sentientia_cart_after_install()`, a function that has
  never existed; it now points at `xmldb_local_sentientia_cart_install()` (db/install.php) and
  `local_sentientia_cart_backfill_user_purchase()`. The root cause in `db/access.php`, `db/upgrade.php`,
  `db/upgradelib.php`, `db/install.php`, the README, the test docblock and the D1 entry above is corrected
  (the `user` archetype was always in the list; see above).
- VERSION BUMP 2026093001 -> 2026093002 / 1.0.6: the back-fill helper changed (default user role), so
  `db/upgrade.php` gains step 2026093002, which runs the same idempotent helper again for any site that
  already took 2026093001. PHPUnit needs a re-init before it runs (the lead re-inits once after the bundles
  merge). Nothing was copied to the served tree.
- UAT, when this is deployed: re-run the public77 cart steps (#81-#83, the `local_sentientia_cart_add_item`
  web service) with a WARM cache, i.e. not straight after a cache purge, since the bug depended on which
  plugins' `lib.php` the callback cache had loaded. Both trees.

### 2026-09-30 - ADR-032: BizLMS cart importer (1.1.0 / 2026100101)

The `cart` feature of the BizLMS import (mapping doc section 13, ADR-032) is built: the BizLMS orders, ledger,
invoices and credit journal become Sentientia cart history, as **frozen, admin-only money history**. Both trees.
Nothing was copied to the served tree and PHPUnit was not run (the lead re-inits once for every version bump and
runs the whole `bizlms_import` group).

- **Importer** (`classes/bizlms/`, registered in `db/bizlms_import.php`, depends on nothing, not atomic):
  - `cart.history`: `local_biz_cart_history` -> one `local_sentientia_cart_history` row per order. A GROUPED
    non-derived step on `identifier`: the first line (lowest id) becomes the order, every other line is `merged`
    into it (reason `order_line`), so every source row has exactly one primary map row. Order number =
    identifier; tenant = the buyer's current root (`costcenterid`, an INT, 0 when unresolved: pathless, never
    guessed); totals in integer paise (`itempriceisnet` gross/net handled); `items_json` snapshot per line with
    its own status; status paid / cancelled / part_cancelled / abandoned and NEVER open, pending, failed,
    refunded or partial_refund; gateway and gateway_ref from the paygw evidence (the attempt with status 2,
    its enrolment-log transaction id, else `payments:<id>`); `timepaid` from the earliest sale ledger row, else
    the paid lines' last touch, else the gateway log, NULL when nobody paid; `legacy_source = 'bizlms'`.
  - `cart.id`: `local_biz_cart_id` rows fold into the order they numbered (identifier = `uniqueidentifier` + id),
    or are archived when they numbered none. NOT a PRESERVE step (see doc corrections).
  - `cart.ledger`: 1:1 into `local_sentientia_cart_ledger` with the mapping doc's event types, an order found by
    identifier else by `schistoryid`, everything else in `payload_json` (buyer's user id FIRST, so the privacy
    provider finds a row by prefix). No row is synthesized (decision `cart.synthesize_ledger` = false).
  - `cart.invoices`: grouped on `invoiceid`; imported as `ERPNEXT-<id>`, status `legacy_external`, the order's own
    buyer / tenant / items / totals, no tax split, no link-out.
  - `cart.credits`: grouped on the user; one `local_sentientia_cart_credit_txn` row per INR booking (event type
    matched to a ledger row of the same user and amount within 5 s), and the user's balance row written once as a
    sub-row. A booking in another currency is skipped (`currency_not_inr`).
  - `cart.order_numbers` (recompute): numbers a line BizLMS never numbered, above `support::order_floor()`.
  - `finalise()` records the highest order number the import holds (`bizlms_order_floor`).
  - The gateway tables (`paygw_airpay`, `paygw_airpay_errorlog`, `paygw_course_enrolmentlog`) and Moodle's
    `payments` are READ as evidence and declined as tables; nothing writes them. The two finance keys
    (`cart.credit_balances`, `cart.erpnext_invoices_legal`) were NOT declared on 2026-09-30; since 2026-10-07 they
    ARE declared (see the section "2026-10-07 owner decisions (finance cluster)" below).
- **Frozen and admin-only** (decisions `cart.imported_visibility`, `cart.admin_refund_imported_orders`):
  `cart_manager::mark_paid`, `mark_failed`, `refund` and `invoicer::issue_for_order` refuse a row with
  `legacy_source` (`error_invalidstate`); `list_orders` hides imported rows from their owner always and from
  administrators unless the flag is on; `get_order`, `return.php`, `invoice.php` follow; `invoicer` refuses the
  prefix `ERPNEXT`; the daily sums leave the import out while the flag is off and count it as BizLMS did when on
  (credit-paid sales and corrections excluded, cash drawer and payouts by sign, unknown types skipped before their
  bucket is made, LEFT JOIN so a ledger row with no order reaches cross-tenant viewers only).
- **Flags (default OFF, `db/feature_flags.php`)**: `sentientia.cart.imported_orders.enabled` (administrators see
  imported orders, ledger rows and invoice references) and `sentientia.cart.imported_credits.enabled` (the new
  admin page `credits.php`, linked from All orders when on). Reader class `imported_history`.
- **Code fixes of the map**: 1 (refuse imported rows), 2 (owners never see abandoned), 3 (buyer name and email
  joined for an order with no billing details, searchable), 4 (`history.php` columns read the keys `list_orders`
  returns), 5 (`return.php` and its template render every status and each line's own status; `legacy_external`
  renders "Issued in ERPNext as ..."), 6 (daily sums), 7 (privacy provider), 8 (the credits page, behind its flag).
- **Schema** (`db/install.xml` + `db/upgrade.php` step 2026100101, idempotent): `local_sentientia_cart_history`
  `legacy_source` char(20) NULL; NEW `local_sentientia_cart_credit_txn`; status and event_type comments list the new
  values. Plugin 2026093002 -> **2026100101**, release 1.1.0; requires platform 2026093001 (the framework).
- **Privacy** (`classes/privacy/provider.php`): the credit journal and the ledger's `initiatedby` and
  `payload_json` are declared, exported, and anonymised on erasure (rows stay: money records); the order slot a
  paid order refers to is anonymised, never deleted; imported orders and invoice references are left alone (no
  billing details to blank). en and hi strings for everything new.
- **Tests**: `tests/bizlms_import_test.php` (contract traits + the mapping doc's fixture: statuses, paise totals,
  items, gateway reference, ledger types, ERPNEXT invoices, credit journal and balances, number floor, refusal),
  `tests/imported_history_reader_test.php` (flags, owner never sees, tenant isolation, daily sums, invoice
  reference), `tests/imported_privacy_test.php`; fixture `tests/fixtures/bizlms/biz_cart.install.xml` (verbatim
  copies of the BizLMS cart and gateway install files, sha1 in its header).
- **Doc corrections** (the mapping doc and the ADR say otherwise; the framework as built decides):
  1. The history group is not a derived `#local_biz_cart_history.identifier` step with merged line rows: a derived
     step has exactly one primary row per group and cannot give its lines a primary row. It is a grouped step on the
     table itself, so every line has one (accounting identity holds, and the contract test checks it).
  2. `local_biz_cart_id` is not PRESERVE: the framework's PRESERVE writes the SOURCE row id, which is the identifier
     only when the BizLMS base is 0, and an importer cannot insert a placeholder row or reset a sequence outside the
     runner. The "never reuse a legacy number" guarantee is `finalise()` + `cart_manager::reserve_order_number()`.
  3. A sale that belongs to no imported order gets event type `legacy_sale_without_order` (not `payment_received`),
     so it stays on the imported side of the flag.
- **Open**: the plugin dependency on the platform framework version, the Stage B counts (I-20), whether
  `paygw_airpay` is deployed on 5.2, production `config_plugins local_biz_cart` values (uniqueidentifier,
  itempriceisnet, globalcurrency). (Closed 2026-10-07: `paygw_airpay` is deployed, the April config is known, and `cli/mask_pii_for_dev.php` masks the new columns through `dev_mask`; see the next section.)

## 2026-10-07 - owner decisions, finance cluster (1.1.1 / 2026100701)

Branch `claude/owner-decisions-y`. Basis: Nitin's delegation of 2026-10-07 ("self review and decide recommended option")
plus the signed "do everything as recommended". **Airpay Finance was NOT consulted on any of these**; where a finance key is
now `accepted`, that records the owner's delegated answer and is not a Finance sign-off. No flag was added or flipped, no
row or file was deleted, nothing was copied to the served tree and **PHPUnit was not run** (the lead re-initialises once
for the version bumps). Both trees. The decisions-file entries (`bizlms-import-decisions.json` and its fixture copies) are
written by a separate commit that must merge before or with this one.

### Code shipped

- **`cart.finance_keys_status` = accept_and_declare.** `bizlms\importer::decisions()` declares `cart.credit_balances`
  (only `frozen_pending_finance`) and `cart.erpnext_invoices_legal` (only `reference_only_pending_finance`). A later
  different value (for example `write_off`) now blocks preflight instead of being ignored, and the run report no longer
  lists two "not accepted" decisions the import never depended on. The importer docblocks, README and this card say the
  answer was delegated. Tests: `bizlms_import_test` (SIGNED values, both keys accepted and declared with one allowed
  value, a value the importer cannot do blocks, the signed values do not block) and the platform
  `bizlms_decisions_test` (the signed file now has no open decision; each why says delegated and not consulted; the
  finance-confirm blocking mechanism stays held by `decisions.sample.json` and `bizlms_runner_test`).
- **`cart.withheld_line_refund` = state_amounts.** `cart_manager::withheld_line_amounts()`; `mark_paid()` writes the amounts
  into `history.notes` and `notifier::order_paid()` into the `admin_new_order` message, marked "for review, not an invoice":
  per withheld line price, discount, GST share (the order's RECORDED `tax_amount` spread over its taxable amount, paise
  rounding) and the sum, plus the withheld total against the order total. The GST share can differ from the invoice by a
  paisa (each line is rounded on its own; the tests pin a basket where the lines add up to one paisa over). The
  administrator still refunds through a partial `refund()`; Finance issues any GST credit note; nothing is automatic and
  the buyer's message states no amounts. The first sentence of the old note and message is unchanged. Tests:
  `purchase_gate_test`. Ship before the native cart takes real paid orders.
- **Notifier in the recipient's language** (fix:cart unresolved 4). Every buyer and admin subject and body is a lang string
  (20 new keys, en and hi) built per recipient through the string manager (`$user->lang`, else the site default), not the
  session that called `mark_paid()`. Tests: `notifier_test` (English unchanged; per-recipient Hindi where the Hindi pack is
  installed, skipped otherwise; site default; en/hi keys and placeholders; no English literal left in the class).
- **Dev masking.** New `classes/dev_mask.php`, called from `local_sentientia_platform/cli/mask_pii_for_dev.php` (Step 3b,
  only where the cart is installed): ledger and credit-journal `reason` set to NULL, both `initiatedby` set to 0, and every
  `userid` / `usermodified` key inside `local_sentientia_cart_ledger.payload_json` set to 0 (the key and its position stay;
  a payload that is not JSON becomes `{}`). Idempotent. Tests: `dev_mask_test`. Not masked, and not asked for: the billing
  name, e-mail, phone and address on `local_sentientia_cart_invoices` (native invoices) and `history.notes`; flagged for
  whoever next edits the CLI. The comms-side gaps in the same script (the `to_email` UPDATE of a column that does not exist,
  imported e-mail subjects and bodies) belong to the comms change and were not touched here.
- **`cart.price_source`** (the catalogue reads `enrol_fee`; `enrol_now()` refuses a paid course) is in the catalog plugin: see
  `sentientia_catalog-state.md`. It closes a revenue hole that this plugin's order cart was never exposed to.

### Decisions recorded, no code (and what triggers the next step)

- **`cart.credit_balances` = frozen_pending_finance.** Frozen, admin-only history behind the default-OFF flag
  `sentientia.cart.imported_credits.enabled`. Nothing in Sentientia honours, spends, pays out or writes off a balance: native
  checkout never reads the credit tables. April 2026 copy: 0 credit bookings, 0 ledger rows, 0 holders, INR 0. **Open Finance
  question (needs Nitin to put it to Finance):** if the Stage B rehearsal on the final live backup shows any learner with a
  non-zero balance, send Finance the count, the total in INR and the tenant, for it to decide honour, pay out or write off, and
  who owns the liability; and whether a holder's erasure request must wait until the balance is settled. Today erasure
  deletes the balance row (`privacy\provider`, around line 301) and anonymises the journal, so a balance owed to a person
  would lose its holder. If Stage B shows a balance, the privacy change (keep the balance row against userid 0 until Finance
  settles it) is decided with Finance's answer. A Finance answer is a re-approval event.
- **`cart.erpnext_invoices_legal` = reference_only_pending_finance.** A stored ERPNext number imports as `ERPNEXT-<id>`, status
  `legacy_external`, shown to order admins as "Issued in ERPNext as <number>" behind `sentientia.cart.imported_orders.enabled`,
  no link-out. Sentientia never issues an invoice number for a BizLMS sale. April copy: 0 invoice rows, no ERPNext connector.
  **Open Finance question:** (a) the only completed BizLMS cart sale (INR 10, 10 Jan 2025, Public tenant, no GST charged) has no
  tax invoice in BizLMS or ERPNext: a test payment, or does Finance raise one in its own system (Sentientia will not)? (b) if
  the final live backup holds stored ERPNext numbers, does Finance confirm ERPNext as the system that holds those legal
  invoices?
- **`cart.native_tax_invoices` = HOLD.** Sentientia issues no tax invoice to a real buyer until Finance answers six points:
  (1) Sentientia, not Finance's own system, issues tax invoices for LMS course sales; (2) the GSTIN for
  `local_sentientia_cart/our_gstn`; (3) the AIRPAY-YYYY-NNNN series (16 characters, restarting each January); (4) how refunds
  get a GST credit note, since Sentientia issues none; (5) whether B2B invoices need an e-invoice IRN; (6) how long issued
  invoices must be kept unredacted. An erasure request currently blanks the buyer's name, e-mail, phone, address and GSTIN on
  native invoices and order history (`privacy\provider::redact_for_user()`), and tax law requires invoices to be kept for a
  statutory period (CGST Act s.36). When Finance answers (6), change `redact_for_user()` to keep issued tax invoices
  unredacted for that period if Finance says so; that code must ship before the first invoice. Until then, which tenants should
  `local_sentientia_cart/enabled_tenants` open at cutover? The default is `77,177`. No code now.
- **`cart.accepted_reasons` = none_now_then_actuals.** `accepted_reasons` stays empty. After the Stage B rehearsal, for each
  `cart:<code>` with a count above 0 in the report (`mixed_buyers`, `orphan_user`, `currency_invalid`, `currency_not_inr`,
  `invoice_without_order`, `invoice_without_number`, `invoice_number_too_long`; `tenant_unresolved` only if
  `tenant.unresolved.cart` is `skip`), Nitin reviews the ids and adds the code; the count is written into the approval note
  (the list has no counts, so accepting a code accepts any count); that is a re-approval event and the new hash is pinned.
  April: none of the seven would fire.
- **`cart.order_number_floor` = setting_plus_runtime_placeholder.** No code: `finalise()` sets
  `local_sentientia_cart/bizlms_order_floor`, and `reserve_order_number()` puts one placeholder row at the floor at the first
  native checkout (tested: a new checkout gets a number above the floor). April: base 0, identifiers 1..5, floor 5. Stage B
  runbook checks: after the import `bizlms_order_floor` equals the highest imported order number, and one rehearsal-only
  native test order gets a number above it. The mapping doc's finalise() "placeholder row" sentence is to be replaced by that
  wording (documents are the docs owner's change).
- **`cart.credit_sale_classification` = keep_unclassified.** A credit booking that matches a sale ledger row stays
  `legacy_unclassified` with a `credit_unclassified` warning (rule R8: the importer never guesses). April has no credit
  rows. If the Stage B report's warning count is above 0, revisit with the real rows (`credits_step.php::EVENT_OF`, both trees).

### Owed

- **Visual evidence** (CLAUDE.md section 5), on the UAT build, desktop and 590 px, into `docs/visual-evidence/<date>/` with a
  README, before any flag flip: `credits.php`, the "Issued in ERPNext as" invoice view (`invoice_legacy.mustache`),
  `return.php` and `history.php` status rendering, the `admin_orders` Staff notes column (now longer: the per-line amounts),
  and the checkout error path. Both imported-history flags stay OFF until Nitin has reviewed them. Not captured in this
  session (no browser access to the UAT build).
- **Stage B runbook** (cart-specific checks): per-table counts against April (history 5 lines and 5 orders, cart_id 5, ledger 0,
  invoices 0, credits 0); `bizlms_order_floor` equals the highest imported order number (April 5); one rehearsal-only native
  order gets a number above it; the report's `decisions_not_accepted` is empty; any credit or invoice row goes to Finance per
  the two open questions above.
- **ADR-032 lines 957-964 and 1043-1044, the mapping doc (section 13 open questions and the placeholder sentence, section 23's
  table) and the dev-masking gaps list** still describe the finance keys as open; the docs owner rewrites them to the accepted
  decisions with the April facts (0 credit, 0 invoice, 0 ledger rows; ERPNext never configured; config has only accountid,
  expirationtime, globalcurrency=INR, maxitems and version).
- **PHPUnit never ran** for `bizlms_import_test`, `imported_history_reader_test`, `imported_privacy_test`,
  `purchase_gate_test`, and the new `notifier_test` and `dev_mask_test`: run them in CI after the re-init; fix fixture-level
  failures only. The new class `dev_mask` needs a caches purge on deploy (class map).
- **After Stage B, not needed at April size:** `legacy_reader::fetch_by(column, values)` and an `importer_contract` assertion
  that each person column of `target_tables` is declared in the privacy metadata.

## 2026-10-07 - fix round 1 (review of stream Y): dev masking covers the buyer's details on the header and the invoices

Branch `claude/owner-decisions-y`, both trees. **No version bump** (a class and a test; the new class code needs a cache purge on deploy like the first one). **Written, not run.**

`\local_sentientia_cart\dev_mask::run()` now also masks, on `local_sentientia_cart_history` (the order header) and `local_sentientia_cart_invoices` (native invoices): `billing_name` becomes "Dev Buyer" (NOT NULL on the invoice, so a placeholder, not NULL), and `billing_email`, `billing_phone`, `billing_address` become NULL; the header's free-text `notes` (staff notes) become NULL. An empty string is left as it is, a second run changes nothing, and `billing_gstn` (a company's tax number) is kept. The header comment of `local_sentientia_platform/cli/mask_pii_for_dev.php` said the name and e-mail were "already masked via mdl_user"; they are copies the buyer typed at checkout, and the comment is corrected. The two lines the CLI already ran (header phone and address) stay as a fallback. Test: `dev_mask_test::test_the_buyers_details_on_the_header_and_the_invoice_are_masked` and the exact-keys assertion of the second-run test.

**Still open, and not the cart's:** the comms-side gaps of the same script (critic item 88: the `to_email` UPDATE of a column that does not exist, imported e-mail subjects and bodies, request decision notes, the admin log description) belong to the comms change. Close them before any dev or UAT copy is built from a Stage B database.
