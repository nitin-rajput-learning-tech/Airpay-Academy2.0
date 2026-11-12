# DRAFT: e-mail to Airpay Finance on GST invoices (not sent)

**Status:** draft for Nitin to review and send himself. Nothing has been sent.
**Why:** decision C2 of 12 November 2026 (`NITIN-DECISIONS-2026-11-12.md`). Native GST invoices stay on hold until Finance answers.
**How to use:** the numbers in square brackets point to the source list at the end. Delete the brackets and the list before sending.

---

**To:** Airpay Finance
**From:** Nitin Rajput
**Subject:** Sentientia LMS course sales: nine GST points for Finance to confirm

Dear Finance team,

Sentientia LMS, the platform that will replace airpay.academy, can sell courses to learners. It is not live yet [6]. We
have built GST tax invoices, credit notes and payment receipts into it, but they are switched off, and they stay off until
you confirm the points below [1][3]. Until then Sentientia issues no GST tax invoice, and its course shop is set to open
only for the Public and ZEEA tenants [3][4].

Some background: the old platform's shop was hardly used. The April 2026 copy of production shows five orders in fifteen
months. One was paid (₹10, on 10 January 2025, no GST charged); it was my own test payment, so no invoice is owed for it [2].

**What Sentientia will do once you confirm** [1]
- Issue the tax invoice after the payment has gone through, with a gap-free number.
- Show the GST as CGST and SGST when the buyer is in our state, as IGST otherwise, using the tax the buyer actually paid.
- Issue a credit note for every refund of an order that has a tax invoice, in its own series, never more than the invoice
  total.
- Give every buyer a payment receipt that says it is not a tax invoice.
- Keep tax invoices and credit notes unchanged for the retention period, even if the buyer asks for their personal data to
  be erased; after that period, an erasure request blanks the buyer's details.

**Please confirm**

1. **GSTIN and supplier details.** The GSTIN to print as ours, and its state (by default we take the state from the first
   two digits of the GSTIN) [1]. We have pre-filled the legal name as "Airpay Payment Services Pvt Ltd" and the address as
   "Airpay House, A-wing, Office Building, Mumbai 400063, India" [4]. Please correct either if GST records differ.
2. **SAC code** for online course sales. There is no default, and nothing is issued without it [1]. Please also confirm the
   rate: the setting is 18% [4].
3. **Invoice series.** Numbers look like `AIRPAY-2026-0001`, at most 16 characters (CGST Rule 46(b)) [1].
   - With the prefix `AIRPAY`, one series holds at most 9,999 invoices. The next number, `AIRPAY-2026-10000`, would be
     17 characters, so invoicing would stop and our admins would be warned [1]. A shorter prefix allows more: a
     five-letter prefix allows 99,999.
   - Credit notes use `AIRCN-YYYY-NNNN` for the same reason [1][5].
   - Please confirm the prefixes (`AIRPAY` for invoices, `AIRCN` for credit notes) or give shorter ones [1].
   - Should YYYY follow the calendar year (restarting each January, our current default) or the financial year (April to
     March) [1]?
4. **E-invoicing (IRN).** Our understanding is that B2B invoices need an IRN when aggregate turnover is above ₹5 crore [1].
   Please confirm this applies to us. Sentientia has no connection to the government invoice registration portal. A B2B
   invoice therefore waits as "awaiting IRN", and the buyer does not see it, until someone in Finance enters the IRN, the
   acknowledgement number and date, and the signed QR string on a Sentientia admin page [1]. Who should do this, and how
   soon after the sale?
5. **B2C dynamic QR.** Our understanding is that it is needed only above ₹500 crore turnover. It is not built; please tell
   us if we need it [1].
6. **Retention.** The default is 8 years. Our spec cites CGST Act section 36 (72 months from the due date of the annual
   return) [1]. Please confirm the period, and that buyer details on these documents must stay unchanged for that period
   even when the buyer asks for erasure.
7. **Signature.** The PDF shows "Authorised signatory" under the company name, and the line "This is a computer-generated
   invoice." [1] Is that enough, or must the PDF carry a digital signature?
8. **Credit balances (old platform).** The old shop can hold money on account for a learner. The April copy holds none
   (₹0) [2]. The final copy taken at cutover could show some. If it does, I will send you the count, the total in ₹ and
   the tenant before cutover. Please confirm that Finance will then decide, for each, whether it is honoured, paid out or
   written off, and who owns the liability. Also: if a learner who holds a balance asks for their data to be erased,
   should that wait until the balance is settled? Today erasure deletes the balance row [2].
9. **Buyers outside India.** The shop is set to open for the ZEEA tenant, our partner in Tanzania, as well as for Public
   [3][4][7]. Checkout asks for an Indian state (GST state codes 01 to 38, or 97 for "Other Territory"), and the only
   currency is ₹ [1][2]. How should a sale to a buyer outside India be invoiced (place of supply, export of services)? Or
   should the shop stay closed for ZEEA until you answer?

For information: if the final copy holds any invoice numbers from ERPNext (the April copy holds none), Sentientia keeps them
only as references that admins can see, and ERPNext stays the system that holds those invoices [2]. Please tell us if that
is not right.

A short reply against numbers 1 to 9 is enough. I am happy to walk you through the screens.

Regards,
Nitin Rajput
Head of L&D, Airpay Payment Services

---

## Sources (for Nitin; delete before sending)

| Ref | File | What it supports |
|---|---|---|
| [1] | `moodle-enhancement/docs/integrations/GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md` | Status line (built, flags OFF until Finance confirms); s.2 flags (OFF = no invoice, no credit note); s.3 settings (`sac_code` no default, state from GSTIN, `series_period` calendar default, `invoice_retention_years` 8, signatory text; invoicing refuses to start without GSTIN or SAC); s.4 numbering (16 characters, Rule 46(b), gap-free); s.5 checkout `billing_state` with GST state codes 01 to 38 and 97, tax split from the recorded tax; s.6 issued after the payment, `awaiting_irn`, admin page `einvoice.php`, no IRP connection; s.7 credit notes only for refunds of orders with an issued or `awaiting_irn` invoice, cumulative limit; s.8 receipt wording, "Authorised signatory", "computer-generated invoice"; s.9 retention, and blanking only on erasure of documents older than the period; s.10 the seven points (item 3: prefixes and series period for Finance; 9,999 per series with `AIRPAY`, `AIRPAY-2026-10000` is 17 characters, `AIRCN`, IRN above ₹5 crore, B2C QR above ₹500 crore not built, retention, signature) |
| [2] | `moodle-enhancement/docs/cutover/OWNER-DECISIONS-2026-10-07.md` | s.4 Finance explainer (five orders in fifteen months, one paid ₹10 on 10 Jan 2025, no GST; credit balances none on the April copy, ₹0; the cart's only currency is INR; ERPNext numbers none); s.6 Q4 (balances: honour, pay out or write off, liability, erasure deletes the row), Q5(b) (ERPNext as the system of record); s.11 (₹10 sale was Nitin's own test payment, no invoice owed; balances stay `frozen_pending_finance`) |
| [3] | `moodle-enhancement/docs/cutover/NITIN-DECISION-BRIEF-2026-10-14.md` | s.3: invoices on hold (`cart.native_tax_invoices = hold`), cart open only for `enabled_tenants` 77 and 177 |
| [4] | `moodle-enhancement/local/sentientia_cart/settings.php` | Defaults: `enabled_tenants` '77,177'; `gst_rate` 18; `company_name` "Airpay Payment Services Pvt Ltd"; `company_address` "Airpay House / A-wing, Office Building / Mumbai 400063, India"; `invoice_prefix` AIRPAY |
| [5] | `moodle-enhancement/state-cards/sentientia_cart-state.md` | 2026-10-14 section: `credit_note_prefix` defaults to `AIRCN` because `AIRPAYCN-2026-0001` is 18 characters |
| [6] | `moodle-enhancement/docs/cutover/SENTIENTIA-MIGRATION-PLAN-2026-09-04.md` | s.0a: Sentientia is not live; all user data on UAT and local is a test import |
| [7] | `moodle-enhancement/docs/cutover/NITIN-DECISIONS-2026-11-12.md` | B4: ZEEA (Tanzania) |

Tenant ids (77 = Public, 177 = ZEEA): `CLAUDE.md` section 2. The five-letter prefix figure is arithmetic on the 16-character
limit (5 + 1 + 4 + 1 + 5 = 16), not a statement from the spec.

**Open in the repo, not asked here:** Q5(b) (ERPNext as the system holding legal invoices) has no recorded answer in
`OWNER-DECISIONS-2026-10-07.md` s.11; the e-mail states the current handling and invites Finance to object.

**Not in spec s.10:** question 9 (buyers outside India) is this draft's addition. It follows from the Indian-only state
list at checkout (spec s.5), the INR-only cart and the default `enabled_tenants` that include ZEEA.
