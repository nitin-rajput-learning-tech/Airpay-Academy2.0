# Visual evidence: GST tax invoices, credit notes and purchase receipts (2026-10-14)

Branch `claude/gst-invoices` (plugin `local_sentientia_cart` 2026101400, release 1.2.0). Spec:
`docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md`, section 11 "Afterwards" (receipt; B2C intra-state and
inter-state invoices; a B2B invoice with IRN; a credit note; the checkout state field; `einvoice.php`; desktop and 590 px).

Taken by Claude on the LOCAL XAMPP site (http://localhost:8080, Moodle 5.1.3+), headless Chrome through Playwright, 1440 px
and 590 px wide, whole page. Nitin approved a local evidence session on 2026-10-14. Nothing here touched UAT, production or
any outside host (the browser aborts every non-local request; `noemailever` was set throughout).

## What was switched on, for one tenant only

| Flag | Tenant | During the pass | Now |
|------|--------|-----------------|-----|
| `sentientia.cart.purchase_receipts` | /77 (Public) | ON | OFF (override row removed; read back from the database) |
| `sentientia.cart.gst_tax_invoices` | /77 (Public) | ON | OFF (override row removed; read back from the database) |

Tenant /77 because the cart is enabled on this box only for tenants 77 and 177 (`enabled_tenants`), and `vp_public77` is the
buyer persona there. Local test settings of `local_sentientia_cart` (left in place; the values they replaced are in brackets):
`company_name` "Airpay Payment Services (TEST)" (was "Airpay Payment Services Pvt Ltd"), `company_address` a three-line test
address (was "Airpay House / A-wing, Office Building / Mumbai 400063, India"), `our_gstn` 27AAAAA0000A1Z5 (was empty),
`sac_code` 999293 (was empty). Everything else is the default (prefixes AIRPAY and AIRCN, calendar series, 18 %, B2B needs the
IRN, retention 8 years).

## Test data (all through the real code paths)

`seed_gst_evidence.php` uses `cart_manager::add_item`, `checkout` (state and GSTIN checked), `mark_paid` (the invoice is
issued after the payment commits by `invoicer::issue_for_order`) and `refund` (the credit note by
`credit_notes::after_refund`). Buyer `vp_public77`; five test courses "VP GST Course A" to "E" priced in tenant /77.

| Order | Kind | Buyer state / GSTIN | Lines | Total | Invoice | Credit note (partial refund) |
|-------|------|---------------------|-------|-------|---------|------------------------------|
| #12 | B2C intra-state | Maharashtra (27) | A 2,499.50 + B 1,001.00 (odd paisa in the tax) | 4,130.59 | AIRPAY-2026-0013, CGST + SGST | AIRCN-2026-0001, 1,181.18 |
| #13 | B2C inter-state | Karnataka (29) | C 4,999.00 | 5,898.82 | AIRPAY-2026-0014, IGST | AIRCN-2026-0002, 1,000.00 |
| #14 | B2B | Maharashtra (27), GSTIN 27BBBBB1111B1Z8 | D 7,500.00 with a 10 % line discount | 7,965.00 | AIRPAY-2026-0015, awaiting the IRN, then issued | AIRCN-2026-0003, 2,360.00, awaiting the IRN, then issued |

The IRNs were recorded through the real form on `einvoice.php` as the site admin. They are TEST values: the IRN is a SHA-256 of a
label, the acknowledgement numbers are made up, and the "signed QR" is a dummy ASCII string. Nothing came from a government
portal; the QR codes on the documents do not verify anywhere. Invoice order numbers #12 and above appear because earlier test
orders already use the numbers below.

## Screenshots

`-desktop.png` is 1440 px wide, `-mobile.png` 590 px wide. `results.json` holds the machine record (HTTP status, console
errors, horizontal overflow at both widths: 0 everywhere, text excerpt).

| File stem | Page | Persona | What it shows |
|-----------|------|---------|---------------|
| `01-checkout-state-field` | `checkout.php` | vp_public77 | Checkout with the invoice flag ON: the required State select (place of supply) between the address and the GST number |
| `02-checkout-state-gstin-mismatch` | `checkout.php` (POST, refused) | vp_public77 | State Karnataka with a Maharashtra GSTIN: checkout refuses with "The state in your GST number does not match the state you chose", keeps what was typed; no order number reserved, no gateway call |
| `03-order-history-documents-before-irn` | `history.php` | vp_public77 | The new Documents column: Receipt, Tax invoice, Credit note per order; the B2B order has only Receipt while it awaits the IRN |
| `04-receipt-b2c-intra` | `receipt.php?id=20` | vp_public77 | Receipt of order #12: items, GST charged, gateway and reference, refunds, "not a tax invoice", links to the invoice and credit note |
| `05-receipt-b2c-inter` | `receipt.php?id=22` | vp_public77 | Receipt of order #13 |
| `06-receipt-b2b-awaiting-irn` | `receipt.php?id=23` | vp_public77 | Receipt of the B2B order with the discount shown and "Your GST tax invoice will follow" |
| `07-invoice-b2b-awaiting-as-buyer` | `invoice.php?id=16` | vp_public77 | What the buyer gets by URL for an invoice awaiting the IRN: "This page is not available" (HTTP 404). The stack trace is the local developer-debug setting, not a product screen |
| `08-invoice-b2c-intra-state` | `invoice.php?id=14` | vp_public77 | Tax invoice, CGST + SGST, place of supply, reverse charge No, amount in words, signatory |
| `09-invoice-b2c-inter-state` | `invoice.php?id=15` | vp_public77 | Tax invoice, IGST |
| `10-creditnote-b2c-intra-state` | `creditnote.php?id=1` | vp_public77 | Credit note: the original invoice number and date, the reason, CGST + SGST |
| `11-creditnote-b2c-inter-state` | `creditnote.php?id=2` | vp_public77 | Credit note, IGST |
| `12-einvoice-before-recording` | `einvoice.php` | vp_siteadmin | The B2B tax invoice and credit note waiting for their IRN, one form each |
| `13-einvoice-form-filled` | `einvoice.php` | vp_siteadmin | The same page with the TEST IRN, acknowledgement number, date and QR typed in |
| `14-einvoice-after-invoice-recorded` | `einvoice.php` | vp_siteadmin | After recording the invoice: success message, the invoice leaves the list, the credit note still waits |
| `15-einvoice-after-all-recorded` | `einvoice.php` | vp_siteadmin | After the credit note too: "No documents are waiting for an IRN." |
| `16-order-history-documents-after-irn` | `history.php` | vp_public77 | The B2B order now has Receipt, Tax invoice and Credit note |
| `17-receipt-b2b-after-irn` | `receipt.php?id=23` | vp_public77 | Receipt of the B2B order with the link to the tax invoice instead of "will follow" |
| `18-invoice-b2b-irn-qr` | `invoice.php?id=16` | vp_public77 | B2B tax invoice: buyer GSTIN, IRN, acknowledgement number and date, QR |
| `19-creditnote-b2b-irn-qr` | `creditnote.php?id=3` | vp_public77 | B2B credit note with its own IRN and QR |
| `off-01-checkout-no-state-field` | `checkout.php` | vp_public77 | Both flags OFF: checkout as it always was, no State select |
| `off-02-order-history-no-documents` | `history.php` | vp_public77 | Both flags OFF: no Documents column |
| `off-03-receipt-refused` | `receipt.php?id=20` | vp_public77 | Both flags OFF: "This page is not available" |
| `off-04-invoice-still-viewable` | `invoice.php?id=14` | vp_public77 | Both flags OFF: an invoice already issued stays viewable (spec section 2) |
| `off-05-einvoice-refused` | `einvoice.php` | vp_siteadmin | Both flags OFF: the e-invoice page is refused even for a site admin ("GST tax invoices are switched off wherever you work, so there is nothing to record here."; HTTP 404; the Debug info and Stack trace boxes are this box's developer-debug setting) |

`pdf/`: the PDFs as downloaded through the Download PDF buttons, and `-page1.png` renders of their first page (90 dpi):
`invoice-AIRPAY-2026-0013.pdf` (B2C intra-state), `invoice-AIRPAY-2026-0014.pdf` (inter-state), `invoice-AIRPAY-2026-0015.pdf`
(B2B with IRN and QR), `creditnote-AIRCN-2026-0001.pdf`, `creditnote-AIRCN-2026-0003.pdf`, `receipt-order-12.pdf`.

## Things that look wrong or odd (for Nitin; none was changed)

1. **Checkout State select is unstyled** (`01`, `02`). The template gives it `class="form-select"`, a Bootstrap 5 class the
   Bootstrap 4.6 based theme does not style. The select is a narrow native control and its label sits on the same line, while
   every other field has its label above and a full-width box. `custom-select` or `form-control` would match the rest. (The
   mobile shot shows the same.)
2. **"Placed" in the order history is the date the cart was opened, not the date of the order.** Order #12 shows 30 Sept 2026
   although it was paid on 14 Oct 2026 (the receipt says so): it reused a cart row opened on 30 Sept, and the column reads the
   row's `timecreated`. Existing behaviour, not part of the GST change; it will show wrong dates for anyone whose cart sits a
   while. The history also prints the status raw (`partial_refund`) and the total without a currency symbol (4130.59).
3. **The receipt names the gateway by its internal key**: "Payment method: airpay" (HTML and PDF) instead of the label checkout
   uses ("Airpay (Cards / UPI / Net Banking)").
4. **Receipt PDF** (`pdf/receipt-order-12-page1.png`): the "Refunds" heading sits to the left of its table rather than above it;
   the tax invoice is mentioned only as a bare number (AIRPAY-2026-0013) with no label, because a link has no text in the PDF;
   the credit note, which the HTML page lists, is not mentioned.
5. **`einvoice.php` shows its title twice** ("E-invoice IRN" in the page header and again as the first heading of the card) and,
   because the page uses the `admin` layout, the Site administration tab strip (General, Users, Courses, ...), which has nothing
   to do with this page.
6. **A first take of `off-05` showed the raw text "local_sentientia_cart/error_einvoiceoff"** instead of the message. The string
   exists in both language files; the local language-string cache was stale (the string came with the last review commit,
   which did not bump `version.php`, so nothing purged the cache here). `php admin/cli/purge_caches.php --lang` fixed it and
   `off-05` was taken again. It is a deploy note, not a code defect: a string-only change needs the "purge caches" step of
   the deploy (the pages taken earlier were unaffected).
7. The invoice does not print the line's list price and discount for the B2B order (taxable value 6,750.00 only; the receipt
   does show the 750.00 discount). That follows the spec (section 8 prints taxable value), so it is a note, not a defect.

Checked and fine: every figure on the six documents (line CGST/SGST sum to the totals, the odd paisa goes to SGST, the credit
note taxable value is amount / 1.18, the amounts in words), no horizontal overflow at 590 px, no console errors from the
pages (the 404 pages `07`, `off-03` and `off-05` log the failed resource of a refusal; three `pageerror` entries in `results.json` on 02, 14 and 15 come from the script polling a page mid-navigation, not
from the site), no missing-string or unrendered-template markers.

## Not captured

- A B2C dynamic QR (spec section 10, point 5) is not built, so there is nothing to show.
- Hindi screens of the documents: the PDFs and pages are English only by design (spec section 11 says so for the PDFs); no Hindi shot was taken.

## How to reproduce (local only, cwd = `C:/xampp/htdocs/moodle5/public` for the PHP scripts)

```
php provision_local_personas.php                 (once; writes .personas.local.json, gitignored)
php gst_evidence_flags.php on cart 77
php seed_gst_evidence.php
node gst_checks.mjs --phase main
php gst_evidence_flags.php off cart 77           (removes the override rows)
node gst_checks.mjs --phase off
```

Scripts are in `moodle-enhancement/tools/visual-pass/`: `gst_evidence_flags.php`, `seed_gst_evidence.php`, `gst_checks.mjs`
and the shared `evidence_lib.mjs`. Each PHP script refuses unless `wwwroot` is localhost and `noemailever` is set.

## Polish re-capture (2026-10-15, after the first pass's UI notes)

The first pass found four defects on the new GST pages. They are fixed on this branch, and the four pages were
re-captured with `tools/visual-pass/gst_polish_checks.mjs`. As before, both cart flags were ON for /77 only and were
switched OFF again afterwards (verified: no override rows). The original shots stay as the record of what the first
pass found.

| File | Was | Now |
|---|---|---|
| `p-01-checkout-state-field-{desktop,mobile}.png` | The State select was an unstyled native control (`form-select` is Bootstrap 5; this theme is 4.6) | `custom-select`, styled like the other fields |
| `p-04-receipt-b2c-intra-{desktop,mobile}.png` | The payment method showed the gateway key `airpay` | "Airpay" (string `gateway_name_<key>`; "Manual payment" for the manual gateway) |
| `pdf/p-receipt-order-12.pdf` | The "Refunds" heading sat beside its table, the invoice was a bare number, the credit notes were missing | The heading is on its own line, then "Tax invoice: <number>", then "Credit notes: <numbers>" |
| `p-12-einvoice-page-{desktop,mobile}.png` | The title appeared twice, under the Site administration tab strip | One title (the page uses the standard layout) |

Still open, and older than this branch (for Nitin, not fixed here):
- The order history's "Placed" column shows when the cart was opened, not when the order was paid.
- The order history shows the raw `partial_refund` status.
- The order history shows totals without the currency symbol.
