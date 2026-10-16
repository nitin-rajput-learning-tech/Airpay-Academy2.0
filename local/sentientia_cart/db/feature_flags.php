<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Feature flag registry for local_sentientia_cart.
 *
 * ADR-032 (2026-10-01): the BizLMS cart history (orders, ledger, invoices, credit journal) is imported into this
 * plugin's tables by the cart importer, as frozen, admin-only history. The import has no user-visible surface and
 * never flips a flag. What an administrator SEES of the imported rows is a reader change, and every reader change
 * ships behind its own flag, default OFF, so the cart shows exactly what it showed before the import until
 * somebody decides otherwise. Turning them ON for a customer is Nitin's call, after he has reviewed the visual
 * evidence (decisions file key framework.reader_flags_airpay_at_cutover).
 *
 * Resolution is performed by \local_sentientia_platform\feature_flags.
 *
 * @package local_sentientia_cart
 */

defined('MOODLE_INTERNAL') || die();

$flags = [

    'sentientia.cart.imported_orders.enabled' => [
        'default'     => false,
        'description' => 'Shows the order history imported from BizLMS (ADR-032) to order
                          administrators: the order list, the order detail, the "Issued in ERPNext
                          as" reference that stands in for an invoice, and the imported payments in
                          the daily sums report. When OFF the cart lists and sums only what
                          Sentientia itself recorded, exactly as before the import. When ON an
                          administrator who holds the view-all-orders capability sees imported
                          orders of their own tenant (a row whose tenant could not be resolved is
                          for cross-tenant administrators only). The owner of an imported order
                          never sees it, whatever the flag says: it is frozen, admin-only money
                          history, and no order, payment or refund of it can be changed. Default OFF.',
    ],

    'sentientia.cart.imported_credits.enabled' => [
        'default'     => false,
        'description' => 'Adds the admin credits page (Credits, linked from All orders) that lists
                          the credit bookings and balances imported from BizLMS (ADR-032), per
                          tenant. Frozen history for finance: nothing in Sentientia acts on a
                          balance until finance decides whether to honour, pay out or write it off.
                          Needs the view-all-orders capability, and a scoped tenant admin sees only
                          their own tenant. With it OFF the page answers as if it did not exist.
                          Default OFF.',
    ],

    // 2026-10-14 (spec GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md, section 2): Nitin, OWNER-DECISIONS section 11, Q6.
    // Both stay OFF until Airpay Finance has confirmed the points in section 10 of the spec (the GSTIN, the SAC code,
    // the series, the e-invoice IRN, the retention period). Resolved for the ORDER's customer and tenant, not for
    // whoever happens to be logged in (a payment callback and the cron task run as no one).
    'sentientia.cart.purchase_receipts' => [
        'default'     => false,
        'description' => 'Gives the buyer a payment receipt for every paid order: the receipt page and its PDF
                          (receipt.php), a Receipt link in the order history and on the order page, and a link to
                          it in the order-paid message. The receipt says it is a payment receipt and not a tax
                          invoice, and points to the GST tax invoice when there is one. Resolved for the tenant of
                          the order. Default OFF.',
    ],

    'sentientia.cart.gst_tax_invoices' => [
        'default'     => false,
        'description' => 'Issues GST tax invoices and credit notes for course sales. While OFF NO invoice and NO
                          credit note is issued for any order (this is the hold decision: before this flag, an
                          invoice was issued for every paid order, inside the payment). While ON, a paid order is
                          marked invoice-due and the invoice is issued right after the payment commits, or by the
                          15-minute task; a refund of an invoiced order raises a credit note. Also adds the
                          required state field to checkout, and the e-invoice (IRN) page for B2B documents.
                          Invoices that already exist stay viewable. Needs the GSTIN, the SAC code and the
                          series settings, and the confirmations in section 10 of the spec, before it is turned
                          on. Resolved for the tenant of the order. Default OFF.',
    ],
];
