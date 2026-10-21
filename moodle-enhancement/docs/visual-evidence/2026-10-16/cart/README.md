# Visual evidence: cart UI fixes (branch `claude/gst-invoices`, merged 2026-10-16 as 3cbc555da)

Local XAMPP (Moodle 5.1, the merged code), desktop 1440 px and mobile 590 px. Captured 2026-10-16 by
`tools/visual-pass/fix_branches_checks.mjs --only cart`. The GST invoice and receipt pages themselves were evidenced on
2026-10-14 (`../../2026-10-14/gst/`). Their flags were not touched here.

**What changed**
1. A **Refund** button appears on the all-orders page (`admin_orders.php`) only, and only on paid or partially
   refunded orders. It never appears on refunded or failed orders, and never on a buyer's order history.
2. Order history and all-orders show **Placed** as the payment date, statuses in words (Paid, Partial refund,
   Refunded, Failed), and amounts with the rupee sign.

| Shot | Persona | Shows |
|---|---|---|
| `01-siteadmin-admin-orders` | `vp_siteadmin` | All orders. Refund buttons only on the paid and partial-refund rows; none on refunded or failed rows. |
| `01b-siteadmin-admin-orders-scrolled` (mobile) | `vp_siteadmin` | At 590 px the table scrolls in its own box. Scrolled to the right end, the Status, Staff notes and Refund columns are reachable. |
| `02-siteadmin-refund-dialog` | `vp_siteadmin` | The Refund dialog of a partial-refund order. It was cancelled, and nothing was refunded. |
| `03-siteadmin-order-history-no-refund` | `vp_siteadmin` | `history.php` opened by the same admin: the same columns, no Refund button. |
| `04-admin1-admin-orders` | `vp_admin1` (tenant admin /1) | The tenant's orders only. The paid order has the Refund button; the failed one does not. |
| `05-admin1-refund-dialog-paid-order` | `vp_admin1` | The Refund dialog of the paid order (cancelled). |
| `06-public77-order-history` | `vp_public77` (buyer, /77) | Three partial-refund orders: payment date, status in words, ₹, no Refund button. |
| `07-learner1-order-history` | `vp_learner1` (buyer, /1) | A paid and a failed order, in words, with ₹, and no Refund button. |

**Data and flags:** no seed, and no flag changed. The orders already existed in the local database, from the 10-14 GST
pass and the persona pass. Every dialog was cancelled. A read-only probe found the same order states before and after.

**Privacy:** the local database holds real people. Before every shot, each real name and e-mail address is replaced in
the page (never in the database) by "[name hidden]" or "[email hidden]". Only test personas appear in clear: `vp_*`
accounts and `@visualpass.localhost` / `@example.invalid` addresses. In `results.json` the rupee sign shows as mojibake
in the cell text; that is the console encoding of the extract. The rendered pages show ₹.
