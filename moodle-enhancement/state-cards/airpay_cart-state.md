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
