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

];
