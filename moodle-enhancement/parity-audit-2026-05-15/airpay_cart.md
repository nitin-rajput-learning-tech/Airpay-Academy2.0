# airpay_cart vs BizLMS local_biz_cart — Parity Audit
Generated: 2026-05-15 | Auditor: feature-parity cluster 4 | Stakes: CRITICAL (revenue)

## Source paths + size

| | BizLMS `local_biz_cart` | Airpay `local_airpay_cart` |
|---|---|---|
| Path | `C:\xampp\htdocs\moodle5\bizlms_disabled\biz_cart\` | `C:\xampp\htdocs\moodle5\public\local\airpay_cart\` |
| PHP files | ~80 | 30 |
| Total LOC (PHP only) | **17,167** | **3,467** |
| Tables | `local_biz_cart_history`, `local_biz_cart_credits`, `local_biz_cart_ledger`, `local_biz_cart_id`, `local_biz_cart_invoices` (5) | `local_airpay_cart_history`, `local_airpay_cart_credits`, `local_airpay_cart_ledger`, `local_airpay_cart_id`, `local_airpay_cart_invoices` (5) |
| Capabilities | `cashier`, `cashtransfer`, `cashiermanualrebook`, `history`, `canbuy` (5) | `view`, `purchase`, `viewallorders`, `refund`, `manageprices` (5) |
| Cron tasks | `delete_item_task` (ad-hoc, expires unbought cart items), `create_invoice_task` (post-payment invoice creation to ERPNext) | NONE — synchronous |
| Events | 9 events (`checkout_completed`, `item_added`, `item_bought`, `item_canceled`, `item_deleted`, `item_expired`, `item_notbought`, `payment_added`, `payment_rebooked`) | NONE — only message-provider notifications |
| External services | 17 (`add_item`, `delete_item`, `delete_all_items`, `cancel_purchase`, `confirm_cash_payment`, `credit_paid_back`, `get_price`, `get_history_items`, `get_history_item`, `get_quota_consumed`, `get_shopping_cart_items`, `mark_for_rebooking`, `search_users`, `transactions_view`, `transactions_view_for_admin`, `view_course_transaction_log`, `view_course_standard_log`) | 9 (`add_item`, `remove_item`, `get_cart`, `checkout`, `list_orders`, `get_order`, `refund`, `set_price`, `daily_sums`) |
| Payment gateway | Moodle's `core_payment` (any registered account) — supports paypal, stripe, custom plugins via Moodle's payment API | Airpay Payment Services gateway hardcoded (one gateway plugin built-in) + manual gateway stub |
| Multi-currency | yes — `globalcurrency` + per-row `currency` | partial — currency column exists but conversion never implemented; single-currency-per-instance assumed |
| Discounts | yes — multiple discount strategies (`modal_add_discount_to_item`, tax categories) | partial — `discount_pct` field per item, no admin UI to apply |
| Booking fees | yes — `biz_cart_bookingfee` adds first-time-buyer fee; admin-configurable | NO | LOST | P1 |
| Rebooking credits | yes — `biz_cart_rebookingcredit` system: refund creates credit, credit applied to next purchase | NO | LOST | **P0** for B2B |
| Cashier UI | yes — `cashier.php` + `amd/src/cashier.js` — cashier can buy on behalf of any user, mark cash paid, transfer cash, manual rebook | NO | LOST | **P0** for ILT pay-at-desk |
| Cashout (daily cash reconciliation) | yes — `cashout.js`, `modal_cashout.php` — cashier closes the till, generates daily sum PDF | NO | LOST | **P1** for venue |
| Daily sums report | yes — `daily_sums_pdf.php`, `daily_sums_csv` via Moodle table API | partial — `daily_sums.php` + `daily_sums.mustache` exist, basic table only, no PDF | weaker | P2 |
| Invoice with sequential numbering | yes — but external (ERPNext via `erpnext_invoice` class) | yes — **internal** numbering `AIRPAY-2026-NNNN` via `invoicer.php` with auto-increment table | **PARITY (different impl)** | — |
| GST-compliant invoice (CGST/SGST/IGST) | partial — taxcategories admin setting | yes — `invoicer::compute_gst_split()` HQ-state-aware | **BETTER** | — |
| Refund flow | yes — cancel_purchase external; partial-refund OR full via cancel-with-fee | yes — `cart_manager::refund()` supports full + partial with audit ledger | **PARITY** | — |
| Quota / sales caps | yes — `get_quota_consumed` external (limit X sales per period) | NO | LOST | **P1** for capped seats |
| Tax categories | yes — `admin_setting_taxcategories` with multi-category override per cost-center | partial — single `gst_rate` setting | weaker | **P1** |
| Cart expiration | yes — items auto-released after `expirationtime` config (via `delete_item_task` ad-hoc) | NO — items sit in cart indefinitely | LOST | P1 |
| Cart cache layer | yes — uses Moodle MUC `cacheshopping` for in-flight cart state, DB only at checkout | NO — cart state always in DB | minor (perf) | P2 |
| Mixed-tenant cart prevention | yes — `samecostcenter` setting + `LOCAL_BIZCART_CARTPARAM_COSTCENTER` rejection | yes — `costcenterid` snapshot on cart open + enforced via tenant equality | **PARITY** | — |
| Multi-language | en, de | en | **less coverage** | P2 |
| Behat / phpunit | yes — `tests/behat/`, `tests/cartitem_test.php` | NO formal — only `cli/smoke_cart.php` | weaker | P2 |
| Privacy provider | yes | yes | **PARITY** | — |
| Tenant-cap fix | n/a (single tenant assumption) | yes — Phase 8.1 B1 fix: cap-holders must be in same tenant as order (`cart_manager.php:430-433`) | **BETTER** | — |
| IP allowlist on payment webhook | NO | yes — `airpay_callback_iplist` setting + `ip_check.php` class | **BETTER** | — |
| Manual gateway (record-only) | yes — through Moodle's core_payment | yes — `manual_gateway.php` | **PARITY** | — |
| Stripe gateway | available via core_payment | NO direct, would need new gateway class | weaker | P2 |
| PayPal gateway | available via core_payment | NO | weaker | P2 |

---

## Architecture summary

**BizLMS biz_cart — full-featured Moodle India "Wunderbyte"-derived shopping cart:**
1. Generic shopping cart that any Moodle plugin can sell into via the `\local_biz_cart\shopping_cart\service_provider` interface (so it sells courses, ILT sessions, programs, etc.).
2. Items live in Moodle MUC cache for speed; flushed to DB only on checkout.
3. Cart respects `maxitems`, single-cost-center constraint, fullybooked/alreadybooked checks via per-component callbacks.
4. Booking fee added once on first non-fee item; deleted if total drops to 0.
5. Rebooking credits issued for cancelled bookings → applied to next purchase. Credit balances tracked in `local_biz_cart_credits` with strict balance-equals-sum invariant (developer-mode assertion).
6. Cashier UI lets staff buy for any user — cash transfers between cashiers, manual mid-flow rebook for ILT moves between dates.
7. Payment routed through Moodle's `core_payment` plugin system — any gateway is supported (Stripe/PayPal/local via separate plugins).
8. Invoices created via async cron task `create_invoice_task` that POSTs to ERPNext.
9. Tax categories per cost center (different tax rules for different tenants).
10. Events fire on every state transition — observable via Moodle Reports → Logs.

**Airpay airpay_cart — focused, Airpay-Gateway-native, GST-compliant B2C cart:**
1. **Courses only** — no generic component plugin sells through this; you can't sell ILT sessions / programs / certifications.
2. Cart state directly in DB row (`local_airpay_cart_history` with status='open'). No MUC cache.
3. Tenant snapshot at cart open — prevents mid-cart tenant moves.
4. **Airpay Payment Services gateway is built-in** as `airpay_gateway` class with checksum signing, callback verification with IP allow-list, refund stub.
5. `manual_gateway` for record-only flow (cash etc.).
6. GST-aware invoicer: CGST+SGST for intra-Maharashtra (HQ); IGST for inter-state. Sequential numbering with year prefix.
7. Refund via `cart_manager::refund()` writes to ledger (append-only), updates history status, unenrols user on full refund.
8. Daily sums report shows finance totals by day (basic table, no PDF).
9. Admin can set per-course price via `set_price.php` (UI uses customfield_data with shortname='price').
10. Order detail view enforces tenant equality even for cap holders (Phase 8.1 B1 fix).

---

## Feature parity matrix

| # | Feature | BizLMS had | Airpay has | Gap | Severity |
|---|---------|-----------|-----------|-----|----------|
| **CART CORE** | | | | | |
| 1 | Add item to cart | yes (any component) | yes (course only) | **course-only** | **P0** |
| 2 | Remove item | yes | yes | parity | — |
| 3 | Empty cart | yes — `delete_all_items_from_cart` external | NO direct (must remove one-by-one) | weak | P2 |
| 4 | Max items per cart | yes — `maxitems` admin setting | NO | LOST | P2 |
| 5 | Cart expiration (auto-drop after N min) | yes — ad-hoc task | NO | LOST | P1 |
| 6 | Same-tenant constraint enforcement | yes — `samecostcenter` config | yes — `costcenterid` snapshot | parity | — |
| 7 | Booking fee (one-time per first purchase) | yes | NO | LOST | P1 |
| 8 | Generic component selling (ILT/program/cert) | yes — `service_provider` interface | NO — hardcoded course only | **LOST** | **P0** |
| **PAYMENT GATEWAY** | | | | | |
| 9 | Moodle `core_payment` integration (works with any gateway) | yes | NO — replaced with custom airpay_gateway | **LOST** generality | **P1** |
| 10 | Airpay Payment Services gateway | NO direct | yes — built-in | **NEW** | — |
| 11 | Stripe gateway | yes (via core_payment) | NO | LOST | P2 |
| 12 | PayPal gateway | yes (via core_payment) | NO | LOST | P2 |
| 13 | Manual / cash gateway | yes | yes | parity | — |
| 14 | Cashier UI ("buy for user", record cash) | yes — full UI with cashier_modal, cashout, cashtransfer | NO | **LOST** | **P0** for ILT-pay-at-desk |
| 15 | Cash transfer between cashiers | yes | NO | LOST | P1 |
| 16 | Confirm-cash-payment external | yes | NO | LOST | P1 |
| 17 | Webhook callback handler | n/a (handled by gateway plugins) | yes — `callback.php` + `verify_callback` + IP allow-list | **NEW (better security)** | — |
| 18 | Webhook IP allow-list | NO | yes — `airpay_callback_iplist` setting | **NEW** | — |
| 19 | Checksum verification of gateway callback | n/a | yes — `hash_equals()` constant-time | **NEW** | — |
| **PRICING** | | | | | |
| 20 | Admin set per-course price | yes — through booking option | yes — via custom field `price` + UI at `/local/airpay_cart/set_price.php` | parity | — |
| 21 | Discount per cart item | yes — modal_add_discount_to_item | partial — `discount_pct` field but no UI to apply | weaker | P1 |
| 22 | Coupon / promo codes | NO direct | NO | n/a | — |
| 23 | Quota / sales cap per item | yes — `get_quota_consumed` | NO | **LOST** | **P1** for capped batches |
| 24 | Time-windowed availability | yes (component-dependent) | NO | LOST | P2 |
| **CREDIT / WALLET SYSTEM** | | | | | |
| 25 | User credit balance | yes — `biz_cart_credits` | yes — table exists (`local_airpay_cart_credits`) but **no logic uses it** | partial — table without code | **P1** |
| 26 | Credit earned from cancellation refund | yes — `biz_cart_rebookingcredit` | NO | **LOST** | **P0** for B2B repeat customers |
| 27 | Pay with credits | yes — usecredit flag | NO | **LOST** | **P0** |
| 28 | Credit transactions audit | yes — full credit history | NO (table not used) | LOST | P1 |
| 29 | Multi-currency credit | yes (limited — throws if more than 1) | NO | LOST | P2 |
| **REBOOKING** | | | | | |
| 30 | Mark history item for rebooking | yes — `mark_for_rebooking` external | NO | LOST | P1 |
| 31 | Cashier manual rebook (move user to new ILT date) | yes — `modal_cashier_manual_rebook.php` | NO | LOST | **P1** for ILT |
| 32 | Auto-apply rebook credit at checkout | yes | NO | LOST | P1 |
| **REFUNDS** | | | | | |
| 33 | Full refund | yes | yes — `cart_manager::refund` | parity | — |
| 34 | Partial refund | yes (with fee) | yes — partial supported | parity | — |
| 35 | Cancellation fee (kept portion) | yes — `fee` column in ledger | NO — refund is all-or-nothing in $ | LOST | P1 |
| 36 | Cancel-until deadline | yes — `canceluntil` column | NO | LOST | P1 |
| 37 | Issue refund-as-credit option | yes (rebookingcredit) | NO | LOST | P1 |
| 38 | Refund triggers automatic unenrol | yes (via component callback) | yes — `unenrol_user_from_course` in cart_manager.php:398 | parity | — |
| **INVOICING** | | | | | |
| 39 | Issue invoice after payment | yes — async via cron `create_invoice_task` | yes — sync via `invoicer::issue_for_order` | parity (different timing) | — |
| 40 | Sequential invoice numbering | yes (via ERPNext) | yes (internal: `AIRPAY-2026-NNNN`) | parity | — |
| 41 | ERPNext integration | yes — `erpnext_invoice` class POSTs to ERPNext API | NO | LOST | **P1** (depends on finance setup) |
| 42 | GST-compliant CGST/SGST/IGST split | partial via taxcategories | yes — automatic intra/inter-state detection from GSTN prefix | **BETTER** | — |
| 43 | Customer GSTN field for B2B | yes (in user profile) | yes — `billing_gstn` on order | parity | — |
| 44 | PDF invoice download | partial | partial — `invoice.php` exists; PDF generation column `pdf_filename` declared but generation logic absent | weaker | **P1** |
| 45 | Credit note for refund | NO direct | partial — invoice status can be `credit_note_issued` but generation absent | weaker | P1 |
| **HISTORY / REPORTING** | | | | | |
| 46 | User order history | yes — `history.php` | yes — `history.php` | parity | — |
| 47 | Admin all-orders view | yes — `report.php` | yes — `admin_orders.php` | parity | — |
| 48 | Daily sums report | yes — `daily_sums_pdf.php` (downloadable PDF + CSV) | partial — `daily_sums.php` (table only, no download links wired) | weaker | P2 |
| 49 | Per-course transaction log | yes — `view_course_transaction_log` external | NO | LOST | P1 |
| 50 | Per-course standard log | yes — `view_course_standard_log` | NO | LOST | P1 |
| 51 | Cash report (cashier daily reconciliation) | yes — `download_cash_report.php` + `cash_report_table` | NO | LOST | **P1** for venue |
| 52 | Tax category report | yes | NO | LOST | P1 |
| 53 | Search users (for cashier buy-for-user) | yes — `search_users` external | NO | LOST | P1 |
| **EVENTS / AUDIT** | | | | | |
| 54 | Moodle event on item add | yes — `item_added` | NO event fired | LOST | **P1** for audit |
| 55 | Moodle event on item delete | yes — `item_deleted` | NO | LOST | P1 |
| 56 | Moodle event on checkout complete | yes — `checkout_completed` | NO | LOST | **P1** |
| 57 | Moodle event on item bought | yes — `item_bought` | NO | LOST | P1 |
| 58 | Moodle event on payment added | yes — `payment_added` | NO | LOST | **P1** |
| 59 | Moodle event on cancellation | yes — `item_canceled` | NO | LOST | P1 |
| 60 | Append-only ledger | yes — `local_biz_cart_ledger` | yes — `local_airpay_cart_ledger` (with FK to history) | parity | — |
| **TECHNICAL** | | | | | |
| 61 | Cart state in MUC cache (fast) | yes | NO (always hits DB) | minor perf | P2 |
| 62 | Idempotent payment-received handler | yes | yes — `mark_paid` returns true on already-paid | **PARITY** | — |
| 63 | Race-safe payment processing | yes (transactions) | yes — `start_delegated_transaction` in `mark_paid` | parity | — |
| 64 | Webhook replay attack protection | partial | partial — IP allow-list but no nonce/timestamp check | similar | P2 |
| 65 | Behat tests | yes — full suite | NO | LOST | P2 |
| 66 | phpunit tests | yes — `cartitem_test` etc. | NO | LOST | **P1** |
| 67 | Privacy provider | yes | yes | parity | — |

---

## User flows (multi-step tasks)

### Flow 1: Public-tenant user buys 1 course via online payment
**BizLMS** (via core_payment + biz_cart):
1. User browses catalog → clicks "Buy" on course
2. `add_item_to_cart` external → MUC cache + booking fee added → `item_added` event triggered
3. User reviews cart → clicks Checkout → routed to core_payment selector (Stripe/PayPal etc.)
4. Pays on gateway hosted page → gateway plugin handles return → biz_cart receives `service_provider::deliver_order` → enrols user → `item_bought` + `checkout_completed` events
5. ERPNext invoice generated async via cron → posted to ERPNext
6. User downloads receipt from `receipt.php`

**Airpay** (via airpay_cart + Airpay gateway):
1. Browse catalog → click Buy on course → `add_item` external inserts row into `local_airpay_cart_history` (status=open)
2. `get_cart` external displays cart; user clicks Checkout
3. `checkout` external runs `cart_manager::checkout` → assigns orderid, sets status=pending, requires billing_name+billing_email
4. UI renders `redirect_to_gateway.mustache` — form POSTs to Airpay endpoint with checksummed payload
5. User pays on Airpay hosted page → Airpay POSTs to `callback.php` → IP-allow-list check → checksum verify → `mark_paid` runs in transaction: ledger row inserted, history updated, manual enrol, invoice issued with CGST/SGST/IGST split, `order_paid` message sent
6. User returns via `return.php` → sees success or fail; clicks invoice link to download

For pure course-buying via the Airpay gateway: **WORKS, with GST compliance better than BizLMS.**

### Flow 2: Receptionist at venue takes cash for a learner who walks up at a Mumbai ILT
**BizLMS** (works):
1. Receptionist opens `/local/biz_cart/cashier.php`
2. Searches for/creates the user
3. Buys the ILT session on their behalf
4. Confirms cash payment → `confirm_cash_payment` external → user enrolled → cash logged in ledger
5. At end of day, receptionist runs cashout → daily PDF generated → matches against till count

**Airpay** (BROKEN — no cashier flow):
1. Receptionist has no UI to buy on behalf of someone else.
2. **Workaround**: log in as the user (a security violation), put it in cart, run through `manual_gateway`, manually mark paid via admin. Not practical at a busy venue.

### Flow 3: User cancels ILT 5 days before — should get partial refund OR credit
**BizLMS** (works):
1. User clicks Cancel in history
2. `cancel_purchase` external → BizLMS checks `canceluntil` → if within window, processes refund minus `fee`
3. Refund issued OR credit added to wallet (admin-configured per item)
4. Component callback un-enrols user

**Airpay** (BROKEN for cancel-with-credit):
1. User has no Cancel button. Only admins can refund.
2. Admin runs `refund_order` external — full $ refund, no fee subtraction, no credit option, no canceluntil check.
3. **Workaround**: process refund externally via finance, no cancellation fee, no credit balance for the user.

### Flow 4: B2B customer (corporate) pays for 15 employees
**BizLMS** (works):
1. Cashier or B2B admin opens cashier UI
2. For each of 15 employees: search → add to cart → checkout with `payment=invoice` → `item_bought` event
3. One ERPNext invoice generated covering all 15

**Airpay** (BROKEN):
1. No cashier UI.
2. No bulk-buy-for-users.
3. Each employee would need to register, login, buy individually with their own billing details.

### Flow 5: Finance reconciles October 2026 revenue
**BizLMS** (works):
1. Admin → Reports → `local_biz_cart_transactions_view_for_admin` external
2. CSV export by date range, by course, by payment type
3. Cross-check with ERPNext entries

**Airpay** (PARTIAL):
1. Admin → `/local/airpay_cart/admin_orders.php` → datatable with all orders
2. `daily_sums.php` shows by-day totals (no PDF/CSV export wired)
3. Manual `SELECT FROM mdl_local_airpay_cart_ledger` query in DB tool. Not finance-friendly.

### Flow 6: User completes 1 of 3 ILT sessions; refund only 2 unused
**BizLMS** (works): per-item refund via `cancel_purchase` per `history.id`. Each item canceled individually.
**Airpay** (BROKEN): refund operates at order-level only. To refund 2/3 you'd need a partial $ refund proportional and manually un-enrol the user — no per-line-item refund UI.

---

## Severity legend
- **P0** = blocks enterprise use OR blocks revenue (cashier/wallet/non-course selling)
- **P1** = important workflow degraded (no events, no ERPNext, no Stripe fallback, no quota)
- **P2** = polish

---

## Recommended fixes (prioritised)

### P0 — Restore non-course selling

1. **Make cart polymorphic.** Add `component` + `area` (BizLMS conventions) to cart items JSON. Modify:
   - `db/install.xml`: add `component` field (or in items_json)
   - `classes/cart_manager.php:88` `add_item()` → accept `(string $component, string $area, int $itemid)` not just courseid.
   - Wire airpay_classroom, airpay_programs, airpay_learningpath each to implement an `\local_airpay_cart\service_provider` interface for `load_cartitem` (price + name) and `deliver_order` (enrol on payment).
   - **Start at:** new file `classes/service_provider_interface.php` with methods `load_cartitem`, `deliver_order`, `cancel_order`.

### P0 — Restore cashier ("buy for user") flow

2. **Create cashier UI** `cashier.php` mirroring BizLMS:
   - Search user (new external `search_users`)
   - Open buying session as that user
   - Confirm cash payment via `manual_gateway` → mark paid + enrol
   - Add capability `local/airpay_cart:cashier`
   - **Start at:** new `cashier.php` + `amd/src/cashier.js` + `classes/external/search_users.php` + `classes/external/confirm_cash_payment.php`.

### P0 — Implement credit / wallet system

3. **Wire up `local_airpay_cart_credits` table.** Currently the table exists in `db/install.xml:125` but no PHP code reads from or writes to it.
   - Implement `cart_manager::get_balance(int $userid): float` reading `local_airpay_cart_credits.balance`.
   - In `cart_manager::add_item()`, expose a checkout option "Pay with credits".
   - In `cart_manager::refund()` at line 305, add `$as_credit = false` param; when true, instead of negative ledger entry → credit the user's balance.
   - Add UI on cart page: "Available balance: ₹X — use it?"
   - **Start at:** new `classes/wallet.php` + extend `cart_manager.php:236-278`.

### P1 — Restore event emission

4. **Create event classes** for the 6 main lifecycle transitions:
   - `classes/event/item_added.php`
   - `classes/event/item_removed.php`
   - `classes/event/order_placed.php` (after `mark_paid`)
   - `classes/event/order_failed.php`
   - `classes/event/refund_issued.php`
   - `classes/event/invoice_issued.php`

   Fire them in `cart_manager.php` after each respective DB write. This restores SOX-compatible audit trail through `mdl_logstore_standard_log`.
   **Start at:** new `classes/event/order_placed.php` + a `::create([...])->trigger()` call inside `cart_manager::mark_paid()` line 272.

### P1 — Restore ERPNext integration

5. **Add `erpnext_pusher` class** that, on `mark_paid`, also POSTs the invoice to the customer's ERPNext instance:
   - New file `classes/erpnext_pusher.php`
   - Use Moodle's `\curl` client; reuse `local_airpay_integrations` plugin if available
   - Settings: ERPNext URL, API key, retry queue
   - Async via ad-hoc task to avoid blocking checkout
   - **Start at:** new `classes/task/push_invoice_to_erpnext.php`.

### P1 — Restore Moodle `core_payment` compatibility (Stripe/PayPal)

6. **Implement Moodle `payment\service_provider` interface** so that the airpay_cart can ALSO sell via Stripe/PayPal/other plugins. This means:
   - Add `classes/payment/service_provider.php` implementing `\core_payment\local\callback\service_provider`.
   - On Moodle core_payment delivery callback, run `cart_manager::mark_paid` (no signature/IP check needed — Moodle's payment API handles those).
   - **Start at:** `classes/payment/service_provider.php` (NEW).

### P1 — Restore cancellation fee + cancel-until deadlines

7. **Add `canceluntil` field to history table** + `cancellation_fee_pct` to per-course price config:
   - Modify `db/install.xml` → add field.
   - In `cart_manager::refund()` line 305: check `canceluntil`; if past, reject refund; compute fee = total * pct/100; user receives total - fee.
   - **Start at:** new field + `cart_manager.php:305` refactor.

### P1 — Restore quota / sales cap

8. **Add quota fields** to course price custom-fields: `quota_total`, `quota_consumed`. Counter increments on `mark_paid`. New external `get_quota_consumed`. `cart_manager::add_item()` rejects when quota_consumed >= quota_total.
   **Start at:** custom-field definitions + new method `cart_manager::check_quota()`.

### P1 — Restore cart expiration

9. **Add ad-hoc task** `delete_unbought_cart_task` registered in `db/tasks.php`:
   - Find `local_airpay_cart_history` rows with status=open and timecreated < now - expiration_minutes
   - Set status=expired
   - Free up any reserved seat quotas
   - **Start at:** new `classes/task/delete_unbought_cart_task.php`.

### P1 — Restore PDF invoice generation

10. **Add PDF generator** to `invoicer.php`. Use Moodle's TCPDF wrapper:
    - Method `generate_pdf(\stdClass $invoice): string` → moodledata path
    - Stores pdf_filename in `local_airpay_cart_invoices.pdf_filename`
    - `invoice.php` serves the PDF for download
    - **Start at:** `classes/invoicer.php` new method `generate_pdf()`.

### P2 — Polish

11. **Add daily sums PDF/CSV export** in `daily_sums.php` — wire buttons to a download endpoint.
12. **Add MUC cache layer** for cart contents to reduce DB hits.
13. **Add phpunit + behat** test suite.
14. **Add nonce + timestamp on Airpay gateway callback** to defeat replay attacks (currently only IP + checksum).
15. **Add multi-language** (Hindi at minimum — Airpay's biggest user base is Indian).
16. **Add admin search-users external** for cashier UI workflow.
17. **Add per-course transaction log** external + page (BizLMS `view_course_transaction_log`).

---

## Summary verdict for stakeholder

**Status: REGRESSION for B2B/ILT/walk-in revenue paths; PARITY+ for B2C online-card course purchases.**

What Airpay GAINED:
- **GST-compliant invoicing** built-in (CGST+SGST or IGST auto-detected) — was an addon in BizLMS
- **Native Airpay Payment Services gateway** with checksum + IP allow-list — was an addon in BizLMS
- **Tenant-equality enforcement** even when viewer holds `:viewallorders` cap (Phase 8.1 B1 fix)
- **`manageprices` moved to CONTEXT_COURSE** so tenant managers can't re-price out-of-tenant courses (Phase 8.1 B9)
- Append-only ledger with FK to history
- Idempotent `mark_paid` with transaction wrapping
- Webhook callback signature verification with `hash_equals` (constant-time)

What Airpay LOST:
- **Cannot sell ILT classroom, programs, certifications** — only courses
- **No cashier UI** — walk-in ILT pay-at-desk is impossible; venue staff cannot record cash
- **No credit wallet** — table exists but no code path uses it; refunds-as-credit (essential for B2B repeat customers) gone
- **No rebooking credit** — ILT date moves can't be processed
- **No `core_payment` integration** — Stripe/PayPal/etc are not pluggable
- **No ERPNext integration** — finance reconciliation broken
- **No event emission** — `mdl_logstore_standard_log` empty for cart events, SOX evidence missing
- **No quota / sales cap** — capped-seat batches cannot reject the 21st buyer
- **No cancellation fee** — refund is all-or-nothing in $, can't keep e.g. 25% as cancellation fee
- **No cancel-until deadline enforcement**
- **No cart expiration** — open carts accumulate forever, distorting analytics
- **No per-item refund** in a multi-item order
- **Daily sums export missing** (PDF/CSV buttons absent)
- **No PDF invoice generation** wired up (column exists, code absent)

Before public-tenant production launch, fixes 1-3 are mandatory (polymorphic cart, cashier, wallet). Fixes 4-10 are required within Q1 (events for audit, ERPNext, Stripe fallback, fee + quota + expiry + PDF). For Airpay's internal tenant which doesn't sell, the cart plugin is mostly cosmetic; for **Public tenant (id=77) which DOES sell to external B2C learners**, this gap is the difference between "we can take card payments online" and "we can run an LMS business".
