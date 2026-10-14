# local_sentientia_cart

Full e-commerce stack for the external Public-tenant (id=77) and ZEEA
(id=177). Airpay-tenant employees consume training as a benefit (cart
disabled by setting). Built Phase 1 (2026-05-11), hardened Phase 8.1
(2026-05-12).

| Field | Value |
|---|---|
| Component | `local_sentientia_cart` |
| Version | `2026051201` (1.0.1) |
| Requires | Moodle 4.5+ (`2024042200`) |
| Maturity | `MATURITY_STABLE` |
| Depends on | `local_sentientia_org`, `local_sentientia_emails`, `local_sentientia_platform` |

## What it does

1. Maintain one open cart per user (`local_sentientia_cart_history` row, `status='open'`).
2. Add courses → snapshot price, compute GST split (CGST+SGST for intra-Maharashtra, IGST otherwise).
3. Checkout → reserve sequential order id, transition `open → pending`, redirect to gateway.
4. Receive webhook callback → verify signature + amount + currency → `mark_paid` → enrol user in courses → issue invoice → notify.
5. Refund (full or partial) → reverse-direction ledger entry + unenrol (on full refund).
6. Admin reports: daily sums per gateway+currency, all-orders datatable, CSV export.

## Capabilities

| Capability | Type | Granted to (archetype) | Context |
|---|---|---|---|
| `local/sentientia_cart:view` | read | user, manager, editingteacher, teacher, student | system |
| `local/sentientia_cart:purchase` | write | user, manager, student | system |
| `local/sentientia_cart:viewallorders` | read | manager | system |
| `local/sentientia_cart:refund` | write | _(siteadmin only)_ | system |
| `local/sentientia_cart:manageprices` | write | manager, editingteacher | **course** ← Phase 8.1 B9 fix |

Custom roles `employee` + `administrator` get grants via the
`db/install.php` hook on plugin install.

`:purchase` on the `user` archetype (Authenticated user) is what lets a real
public-storefront (/77) or ZEEA (/177) learner, who holds no other system role,
add to cart and check out. `db/access.php` has listed the `user` archetype since
the plugin was written, but the role still lacked the row on the persona-pass
site (most likely the capability was first registered outside
`update_capabilities()`, the only thing that applies archetype defaults), so
upgrade step `2026093001` and the install hook back-fill the grant
(`db/upgradelib.php`, `local_sentientia_cart_backfill_user_purchase()`, which
also covers the role `$CFG->defaultuserroleid` points at): idempotent, fills a
missing grant only, and never overrides an administrator's PREVENT or PROHIBIT. Holding
the capability does not widen what anyone can buy: the cart still has to be
switched on for the buyer's tenant (`enabled_tenants`) and the course must be one
the buyer's own catalogue shows (`cart_manager::can_buy_course()`, ADR-031).

## Tables

| Table | Purpose |
|---|---|
| `local_sentientia_cart_history` | One row per cart/order (open → pending → paid/failed/refunded) |
| `local_sentientia_cart_id` | Sequential order id reservation table |
| `local_sentientia_cart_ledger` | Immutable INSERT-only payment events (payment_received, refund_full, refund_partial) |
| `local_sentientia_cart_invoices` | GST tax invoices (gap-free `PREFIX-YYYY-NNNN` numbers from `local_sentientia_cart_docseq`; B2B ones wait for the e-invoice IRN) |
| `local_sentientia_cart_creditnotes` | GST credit notes raised by refunds (CGST Act s.34), their own number series |
| `local_sentientia_cart_docseq` | One counter row per number series (`AIRPAY-2026`); the gap-free allocator |
| `local_sentientia_cart_credits` | Customer credits / wallet balance |
| `local_sentientia_cart_credit_txn` | Credit bookings imported from BizLMS (frozen history, ADR-032) |

## Web services

12 endpoints registered in `db/services.php`:

| Function | Purpose |
|---|---|
| `local_sentientia_cart_add_item` | Add a course to user's cart |
| `local_sentientia_cart_remove_item` | Remove a course from user's cart |
| `local_sentientia_cart_get_cart` | Fetch current cart with totals |
| `local_sentientia_cart_checkout` | Reserve order + transition status |
| `local_sentientia_cart_get_order` | Fetch a specific order (tenant-scoped) |
| `local_sentientia_cart_list_orders` | Paginated order list (tenant-scoped for non-admin) |
| `local_sentientia_cart_refund_order` | Admin/manager refund (tenant-scoped) |
| `local_sentientia_cart_daily_sums` | Finance report (tenant-scoped) |
| `local_sentientia_cart_set_course_price` | Cap at CONTEXT_COURSE ← Phase 8.1 B9 |
| `local_sentientia_cart_get_course_price` | Read current price for a course |
| `local_sentientia_cart_my_invoices` | List own invoices |
| `local_sentientia_cart_get_invoice` | Render one invoice (HTML + print-to-PDF UI) |

## Settings (Site admin → Plugins → Local plugins → Airpay Cart)

| Setting | Purpose |
|---|---|
| `enabled_tenants` | CSV of tenant root ids (default `77,177`) |
| `currency` | Primary currency (INR/USD/EUR/GBP) |
| `airpay_endpoint` | Payment gateway URL |
| `airpay_merchantid` | Merchant id (PARAM_ALPHANUMEXT) |
| `airpay_secret` | Signing secret (configpasswordunmask) |
| `airpay_callback_iplist` | Optional CIDR allow-list for webhook ← Phase 8.1 B11 |
| `gst_rate` | Default 18% |
| `our_gstn` | Company GSTN on invoices |
| `company_name` | Issuer name on invoices |
| `company_address` | Issuer address (multiline) |
| `invoice_prefix` | E.g. `AIRPAY` → `AIRPAY-2026-0001` (`ERPNEXT` is reserved for invoices imported from BizLMS) |
| `sac_code` | SAC of the service. **Required, no default**: Finance confirms it |
| `supplier_state_code` | GST state we supply from; blank = first two digits of `our_gstn` |
| `credit_note_prefix` | Default `AIRCN` (see "GST tax invoices" below for why not `AIRPAYCN`) |
| `series_period` | `calendar` (default) or `financial` (April to March, year = the year it starts) |
| `einvoice_b2b` | Default yes: a buyer with a GSTIN needs an e-invoice IRN |
| `invoice_retention_years` | Default 8: erasure blanks buyer details only on documents older than this |
| `signatory_text` | Default "Authorised signatory" |

## Scheduled tasks

| Task | Schedule | Purpose |
|---|---|---|
| `local_sentientia_cart\task\issue_due_documents` | every 15 minutes | Issues every GST tax invoice and credit note still due; warns the site administrators once a day while the tax settings are incomplete |

Order messages themselves rely on the `sentientia_notifications` dispatcher for queued
messages.

## GST tax invoices, credit notes and purchase receipts (1.2.0, 2026-10-14)

Spec: `docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md`. Two feature flags, **both default OFF** (and
resolved for the *order's* customer and tenant, not for whoever is logged in):

- `sentientia.cart.gst_tax_invoices`: GST tax invoices and credit notes. While OFF **no invoice and no credit note is
  issued** (the "hold" decision; before the flag an invoice was issued for every paid order). Invoices that already
  exist stay viewable exactly as before (a row without a place of supply is drawn by the old template).
- `sentientia.cart.purchase_receipts`: the buyer's receipt page and PDF, the Documents column of the order history,
  and the receipt link in the order-paid message.

How it works:

- **The payment never depends on the invoice.** `mark_paid()` only marks the order `invoice_due` (flag ON) inside the
  payment transaction. The invoice is issued after the commit, in its own transaction, by the same request; a failure
  is logged (`debugging` plus `callback_logger`) and the 15-minute task retries. A refund works the same way for credit
  notes (`credit_notes::after_refund()`).
- **Numbers** (`docseq`): `UPDATE lastno = lastno + 1` on one row per series, inside the transaction that inserts the
  document: the row lock serialises issuers and a rollback releases the number, so there are no gaps. Format
  `PREFIX-YYYY-NNNN`, at most 16 characters (CGST Rule 46(b)); a longer one is refused and the document stays due. The
  first allocation of a series starts after the highest existing number. Credit notes have their own prefix.
  *Deviation from the spec:* the default credit note prefix is `AIRCN`, not `AIRPAYCN`, because `AIRPAYCN-2026-0001` is
  18 characters and could never be issued.
- **Place of supply and the split** (`gst`): CGST + SGST (each half of the tax, odd paisa to SGST) when the place of
  supply is the supplier's state, else IGST. B2C: the buyer's state (checkout asks for it, a select of the GST state
  codes 01 to 38 and 97, required while the invoice flag is ON). B2B: the state in the GSTIN, which must equal the chosen
  state. Every figure is what the order recorded (`gst_rate` is stored with `tax_amount` when the totals are computed).
- **B2B and the e-invoice IRN** (`einvoice`, `einvoice.php`): a document for a buyer with a GSTIN is numbered but
  `awaiting_irn` and hidden from the buyer; Finance (capability `local/sentientia_cart:manageinvoices`, no archetype)
  registers it on the government portal and records the IRN, acknowledgement and signed QR here. Sentientia has no IRP
  connection. The QR is drawn with `core_qrcode`.
- **Documents**: `invoice.php`, `creditnote.php`, `receipt.php` (HTML, and `&download=pdf`). PDFs use Moodle's `pdf`
  class, A4, and are always in English (TCPDF cannot shape Devanagari); the HTML follows the viewer's language.
- **Privacy**: erasure keeps a document younger than `invoice_retention_years` unchanged and blanks the buyer's
  name, e-mail, phone, address and GSTIN on an older one; export gives every invoice, credit note and receipt.

Before either flag goes on, Airpay Finance confirms section 10 of the spec: the GSTIN, the SAC code, the series, the
IRN, the B2C QR, the retention period, the digital signature.

## Phase 8.1 security hardening

- **B4** (CVSS 9.1): `callback.php` compares `payload.amount` + `currency` to server-side `cart->total_amount/currency` BEFORE `mark_paid`.
- **B11** (CVSS 5.4): Generic 500 (no PHP-error leak), optional `airpay_callback_iplist` allow-list with silent 404 on un-listed sources.
- **B1** (CVSS 8.6): `cart_manager::get_order` + `refund_order` + `list_orders` + `daily_sums` all enforce tenant equality via `\local_sentientia_platform\tenant::require_access` / `::sql_filter`.
- **B5** (CVSS 7.4): Invoice template addresses go through `html_writer::div(s($x), ['style' => 'white-space: pre-line'])` instead of the fragile `nl2br(s($x))+{{{ }}}` pattern.
- **B9** (CVSS 7.1): `:manageprices` cap migrated `CONTEXT_SYSTEM → CONTEXT_COURSE`. Re-grant any custom-role assignments post-upgrade per `db/upgrade.php` comments.
  **Correction (ADR-031, 2026-09-25):** the context move alone scoped nothing. A system-level grant (every tenant admin's manager-archetype role) is inherited by every course, so the tenant bound is now enforced in code: `set_course_price` refuses a course outside the caller's tenant (or with no `open_path`), and `set_price.php` lists only the caller's tenant's courses. `daily_sums_csv.php` and `invoice.php`, which skipped the tenant check, are scoped too.

## How to verify after install

```powershell
php "C:/xampp/htdocs/moodle5/public/local/sentientia_cart/cli/smoke_cart.php"
# Expected: 26/26 cases pass
```

## Privacy / GDPR

Full provider in `classes/privacy/provider.php`:
- `_get_metadata` lists every PII column (billing_name, email, phone, address, gstn).
- `_export_user_data` exports cart history + invoices for a userid.
- `_delete_data_for_user` redacts PII on history rows but preserves the
  ledger for finance audit (legal hold).
- `_delete_data_for_users` bulk variant for tenant-wide DSR runs.

## BizLMS import (ADR-032)

`classes/bizlms/` holds the cart importer, registered in `db/bizlms_import.php` and run by
`local/sentientia_platform/cli/import_bizlms.php --feature=cart`. It copies the BizLMS orders, ledger, invoices and
credit journal into the tables above as **frozen, admin-only history**: an imported order has
`local_sentientia_cart_history.legacy_source = 'bizlms'`, and `cart_manager::mark_paid()`, `mark_failed()` and
`refund()` refuse it. Its owner never sees it; an order administrator sees it only while
`sentientia.cart.imported_orders.enabled` is on (default OFF), and the admin credits page (`credits.php`) is behind
`sentientia.cart.imported_credits.enabled` (default OFF). Mapping: `docs/cutover/BIZLMS-IMPORT-MAPPING-2026-09-29.md`
section 13. The gateway tables are read as evidence and never imported.

The two finance questions are recorded as accepted decisions, declared by the importer with only the value it
implements (owner, 2026-10-07, delegated; Airpay Finance was NOT consulted, so `accepted` is not a Finance sign-off):
`cart.credit_balances = frozen_pending_finance` (the credit journal and balances are frozen, admin-only history; nothing
honours, spends, pays out or writes off a balance) and `cart.erpnext_invoices_legal = reference_only_pending_finance`
(a stored ERPNext number imports as the reference `ERPNEXT-<id>`, status `legacy_external`; Sentientia never issues a
number for a BizLMS sale). A different value in the file (for example `write_off`) blocks the feature at preflight.
April 2026 rehearsal copy: 0 credit rows, 0 ledger rows, 0 invoice rows, no ERPNext connector. If Stage B shows any
non-zero balance or any invoice row, the count and total go to Airpay Finance before cutover (see the state card).
