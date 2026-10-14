# GST tax invoices, credit notes and purchase receipts (local_sentientia_cart) - spec, 2026-10-14

**Why:** Nitin, 2026-10-14 (OWNER-DECISIONS section 11, Q6): "build the GST invoice mechanism, course purchase receipt
for buyers". Sentientia issues the GST tax invoice for LMS course sales (Q6 point 1). Points 2 to 6 (GSTIN, series,
credit notes, e-invoice IRN, retention) are settings with conservative defaults. **Both flags stay OFF until Airpay
Finance has confirmed them** (section 9).

## 1. What exists today and what is wrong with it

`classes/invoicer.php` issues an invoice for every paid order, inside the payment transaction
(`cart_manager::mark_paid`, around line 536). It has these problems:
- **The payment depends on the invoice.** Any invoice failure rolls back the payment that the gateway has already
  taken.
- **Duplicate numbers.** The number is COUNT-based with an existence check, so two payments at the same moment can
  get the same number. Nothing makes the series gap-free.
- **Wrong tax split.** A buyer with no GSTIN always gets IGST, even a Maharashtra buyer, who owes CGST and SGST.
  Checkout does not collect the buyer's state, and the head-office state is hard-coded as 27.
- **Wrong tax figures.** Tax is recomputed at today's `gst_rate`, not taken from the `tax_amount` the buyer was
  charged.
- **Missing Rule 46 particulars:** SAC, place of supply, reverse charge, amount in words, signatory.
- No credit note when an order is refunded.
- No purchase receipt.
- Privacy erasure blanks the buyer's details on tax documents at once. GST law requires those records to be kept.

## 2. Feature flags (both default OFF; per customer and tenant as usual)

- `sentientia.cart.purchase_receipts`: the receipt page, its PDF, and the links from the order history and from the
  order-paid notification.
- `sentientia.cart.gst_tax_invoices`: GST tax invoices and credit notes.
  - While OFF, **no invoice and no credit note is issued.** This implements the "hold" decision; until now an invoice
    was issued for every paid order.
  - Invoices and imported ERPNext references that already exist stay viewable exactly as now.

## 3. Settings (`local_sentientia_cart`)

**Existing settings, kept:** `company_name`, `company_address`, `our_gstn`, `invoice_prefix` (default `AIRPAY`;
`ERPNEXT` stays reserved), `gst_rate`.

**New settings:**
- `sac_code`: the SAC of the service, required. No default value; Finance confirms it.
- `supplier_state_code`: blank means the first two digits of `our_gstn`.
- `credit_note_prefix`: default `AIRPAYCN`.
- `series_period`: `calendar` (default; the AIRPAY-YYYY-NNNN series already put to Finance) or `financial`
  (April to March, YYYY = the year the financial year starts).
- `einvoice_b2b`: default yes. When the buyer gives a GSTIN, the document needs an IRN; see section 6.
- `invoice_retention_years`: default 8.
- `signatory_text`: default "Authorised signatory".

**Invoicing refuses to start** while `company_name`, `company_address`, `our_gstn` or `sac_code` is empty, or while
the supplier state is not a valid GST state code. The document stays due, and every site admin is warned once a day
by the issuing task until the settings are fixed.

## 4. Numbering (gap-free, at most 16 characters, CGST Rule 46(b))

**New table `local_sentientia_cart_docseq`:** `id`, `series` char(40) UNIQUE, `lastno` int, `timemodified`.

**Allocation** happens inside the transaction that inserts the document:
1. Insert the series row if it is missing; a duplicate-key error means another process inserted it, so continue.
2. `UPDATE {local_sentientia_cart_docseq} SET lastno = lastno + 1 WHERE series = :series`. The InnoDB row lock
   serialises concurrent issuers.
3. Read `lastno`.
4. Insert the document.
5. Commit.

A rollback releases the number, so there are no gaps.

**Format:** `PREFIX-YYYY-NNNN` (NNNN zero-padded to at least 4 digits). If it would be longer than 16 characters,
nothing is issued, the document stays due and the admin warning names the cause.

**Seeding:** the first allocation in a series starts after the highest existing number of that series, so invoices
already issued on UAT are not reissued.

Invoices and credit notes have separate series (`invoice_prefix`, `credit_note_prefix`).

## 5. Place of supply and the tax split

**Checkout** gets a required `billing_state` field: a select of the Indian states and union territories with their
GST state codes (01 to 38, and 97 for "Other Territory").
- When a GSTIN is given, its first two digits must equal the selected state. Otherwise checkout refuses, with a
  string telling the buyer the GSTIN and the state disagree.
- The state is stored on the order (new `billing_state` column on `local_sentientia_cart_history`) and on the invoice.

**The rate charged** is stored on the order at checkout: new column `gst_rate` on history, set where the tax is
computed (`cart_manager` around line 333). Every figure on the invoice comes from what the order recorded:
- taxable = subtotal - discount_amount;
- tax = tax_amount;
- total = total_amount.

**Lines** are spread with the existing per-line method (`cart_manager` around line 558, the one used for withheld
lines).

**The split:**
- Intra-state, where the place of supply equals the supplier state: CGST and SGST, each half the tax (the odd paisa
  goes to SGST).
- Otherwise IGST.
- For B2C the place of supply is the buyer's state; for B2B it is the state in the GSTIN (the two agree, see above).

## 6. Issuing invoices: after the payment, never inside it

**At payment,** while the invoice flag is ON for the order's customer and tenant, the order is marked
`invoice_due = 1` (new column on history) in the payment transaction. Nothing else changes there, and
`invoicer::issue_for_order` is no longer called inside it.

**After the commit,** the same request tries to issue the invoice at once, in its own transaction. A failure is
logged (debugging plus `callback_logger`) and never reaches the payment.

**New scheduled task `issue_due_documents`** (every 15 minutes) issues every due invoice and credit note. While the
settings are incomplete it warns the site admins once a day.

**B2B documents** (buyer GSTIN present and `einvoice_b2b` on) get the status `awaiting_irn`:
- The buyer does not see such an invoice. Their receipt says the tax invoice will follow.
- New admin page `einvoice.php` lists them for the tenant's admins. Capability
  `local/sentientia_cart:manageinvoices`, with no archetype, plus site admins.
- There Finance records the IRN (64 hex characters), the acknowledgement number, the acknowledgement date and the
  signed QR string from the government invoice registration portal (IRP). Sentientia has no IRP connection.
- The status then becomes `issued`, and the IRN and QR print on the invoice. The QR is rendered with `core_qrcode`.

**B2C documents** are `issued` at once.

## 7. Credit notes (CGST Act section 34)

**New table `local_sentientia_cart_creditnotes`:** `id`, `invoiceid`, `historyid`, `cn_number` (unique), `userid`,
`costcenterid`, the billing snapshot fields, `taxable`, `cgst`, `sgst`, `igst`, `total`, `reason`, `status`
(`due`, `issued` or `awaiting_irn`), `irn`, `ack_no`, `ack_date`, `signed_qr`, `timecreated`.

**When one is created:** when `cart_manager::refund()` refunds an order that has an issued (or `awaiting_irn`)
invoice while the invoice flag is ON. The credit note is created after the refund commits, the same way invoices are
(due, then the task).

**Amounts:** the refunded amount is gross. taxable = round(amount / (1 + rate / 100), 2); tax = amount - taxable,
split the way the original invoice was. The credit note references the original invoice's number and date.

**Limit:** the credit notes of an invoice may not total more than the invoice total.

## 8. The documents

**Tax invoice** (HTML page `invoice.php` plus a PDF download):
- Supplier: name, address, GSTIN, state name and code.
- "Tax Invoice"; the number and the date.
- Buyer: name, address, GSTIN when given, state name and code.
- Place of supply (state name and code).
- Per line: description, SAC, taxable value, rate, and CGST, SGST and IGST amounts.
- Totals, and the total in words ("Rupees ... only", Indian grouping: lakh and crore).
- Reverse charge: No.
- IRN, acknowledgement number and date, and the QR, when present.
- The signatory block (`company_name`, then `signatory_text`).
- "This is a computer-generated invoice."

**Credit note:** the same layout, titled "Credit Note", with the original invoice's number and date and the reason.

**Purchase receipt** (`receipt.php?id=<historyid>` plus a PDF):
- The order number, the date and time paid, the buyer's name and e-mail.
- The items with their prices, the discount, the GST charged and the total paid.
- The gateway and its reference.
- The refunds so far (amount, date).
- "This is a payment receipt, not a tax invoice." Then a link to the tax invoice when one is issued and visible to
  the buyer, or "Your GST tax invoice will follow" for B2B while it awaits the IRN.
- **Access:** the owner, or `:viewallorders` inside the order's tenant (the same rule as
  `invoicer::require_view_access`). An imported BizLMS order has no receipt (admin-only history).

**PDFs** use Moodle's `pdf` class (`lib/pdflib.php`), A4, and the same data as the HTML. The HTML templates use
`{{ }}` everywhere except the fixed address wrapper that exists today.

**Links:** buyer's order history (`history.php`): "Receipt" and, when visible, "Tax invoice" or "Credit note". The
order-paid notification links the receipt while the receipt flag is ON.

## 9. Privacy (DPDP) and retention

- **Export:** every invoice, credit note and receipt of the user.
- **Erasure:**
  - Documents older than `invoice_retention_years` (counted from the document date) have their buyer name, e-mail,
    phone, address and GSTIN blanked, as erasure does today.
  - Younger documents are kept unchanged: retention is required by law (CGST Act section 36: 72 months from the due
    date of the annual return). The privacy metadata strings say so.
  - The order history follows the existing rules.

## 10. Still for Airpay Finance to confirm before either flag is switched on

1. The GSTIN for `our_gstn` and the supplier state.
2. The SAC code.
3. The series: prefixes, `calendar` or `financial`.
4. Whether B2B needs the IRN (aggregate turnover above INR 5 crore: yes), and who records it.
5. Whether a B2C dynamic QR is required (turnover above INR 500 crore). It is not built; say so if it is needed.
6. The retention period.
7. Whether a digital signature is required on the PDF, or the computer-generated statement is enough.

## 11. Engineering rules for the build

- **Both trees:** `moodle-enhancement/local/sentientia_cart` and `local/sentientia_cart`, kept identical
  (`tools/check-tree-drift.php`).
- **Version:** 2026101400, release 1.2.0. `db/install.xml` and `db/upgrade.php` add the two tables and the history
  columns (`billing_state` char(2), `gst_rate` number(5,2), `invoice_due` int(1) default 0) and the invoice columns
  (`billing_state`, `place_of_supply`, `sac_code`, `gst_rate`, `irn`, `ack_no`, `ack_date`, `signed_qr` text), each
  `if (!field_exists)`.
- **Code standards:** the `$DB` API only, `{table}` names, `get_in_or_equal`, `s()` and `format_string`, and
  `require_login`, `require_capability` and `require_sesskey` on every write.
- **Strings:** en and hi with 100% parity.
- **Capability:** `local/sentientia_cart:manageinvoices` in `db/access.php`.
- **Task:** in `db/tasks.php`. **Flags:** in `db/feature_flags.php`.
- **PHPUnit:**
  - number allocation: sequential, gap-free after a rollback, the 16-character limit, seeding from existing numbers;
  - the tax split: intra and inter state, B2C by state, B2B by GSTIN, a GSTIN and state that disagree, figures from
    the recorded tax;
  - flags: with the invoice flag OFF, nothing is issued;
  - an invoice failure never rolls back the payment;
  - the task issues due documents;
  - incomplete settings: nothing is issued and admins are warned;
  - credit notes on a full and a partial refund, and the cumulative limit;
  - awaiting_irn: hidden from the buyer until the IRN is recorded, and IRN validation;
  - receipt access: owner yes, another tenant's admin no, imported order none;
  - privacy: retention keeps young documents and blanks old ones;
  - amount in words.
- **Afterwards:**
  - `tools/check-siteid-compare.php`, `tools/check-tree-drift.php` and the other pre-commit checks clean;
  - the state card `state-cards/sentientia_cart-state.md` updated;
  - visual evidence: receipt; B2C intra-state and inter-state invoices; a B2B invoice with IRN; a credit note; the
    checkout state field; `einvoice.php`. Desktop and 590 px, in `docs/visual-evidence/2026-10-14/gst/`.
