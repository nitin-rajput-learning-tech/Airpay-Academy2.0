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

## Tables

| Table | Purpose |
|---|---|
| `local_sentientia_cart_history` | One row per cart/order (open → pending → paid/failed/refunded) |
| `local_sentientia_cart_id` | Sequential order id reservation table |
| `local_sentientia_cart_ledger` | Immutable INSERT-only payment events (payment_received, refund_full, refund_partial) |
| `local_sentientia_cart_invoices` | GST-compliant invoices (per-year sequential numbering) |
| `local_sentientia_cart_credits` | Customer credits / wallet balance |

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
| `invoice_prefix` | E.g. `AIRPAY` → `AIRPAY-2026-0001` |

## Scheduled tasks

None directly; relies on `sentientia_notifications` dispatcher for queued
messages.

## Phase 8.1 security hardening

- **B4** (CVSS 9.1): `callback.php` compares `payload.amount` + `currency` to server-side `cart->total_amount/currency` BEFORE `mark_paid`.
- **B11** (CVSS 5.4): Generic 500 (no PHP-error leak), optional `airpay_callback_iplist` allow-list with silent 404 on un-listed sources.
- **B1** (CVSS 8.6): `cart_manager::get_order` + `refund_order` + `list_orders` + `daily_sums` all enforce tenant equality via `\local_sentientia_platform\tenant::require_access` / `::sql_filter`.
- **B5** (CVSS 7.4): Invoice template addresses go through `html_writer::div(s($x), ['style' => 'white-space: pre-line'])` instead of the fragile `nl2br(s($x))+{{{ }}}` pattern.
- **B9** (CVSS 7.1): `:manageprices` cap migrated `CONTEXT_SYSTEM → CONTEXT_COURSE`. Re-grant any custom-role assignments post-upgrade per `db/upgrade.php` comments.

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
