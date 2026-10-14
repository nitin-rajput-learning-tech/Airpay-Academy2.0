<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Cart';

// Navigation.
$string['cart']         = 'Cart';
$string['mycart']       = 'My Cart';
$string['mycartlong']   = 'My cart and orders';
$string['checkout']     = 'Checkout';
$string['orderhistory'] = 'Order history';
$string['adminorders']  = 'Manage orders';

// Capabilities.
$string['sentientia_cart:view']        = 'View own cart';
$string['sentientia_cart:purchase']    = 'Add items to cart and purchase';
$string['sentientia_cart:viewallorders'] = 'View all orders (admin)';
$string['sentientia_cart:refund']      = 'Process refunds';
$string['sentientia_cart:manageprices'] = 'Manage course pricing';

// Cart UI.
$string['emptycart']       = 'Your cart is empty.';
$string['emptycart_hint']  = 'Browse the catalog and add courses to your cart.';
$string['browsecatalog']   = 'Browse catalog';
$string['itemstotal']      = '{$a} item(s)';
$string['subtotal']        = 'Subtotal';
$string['tax']             = 'Tax (GST 18%)';
$string['total']           = 'Total';
$string['remove']          = 'Remove';
$string['addtocart']       = 'Add to cart';
$string['inyourcart']      = 'In your cart';
$string['proceedtocheckout'] = 'Proceed to checkout';

// Checkout.
$string['paymentmethod']     = 'Payment method';
$string['paymentmethod_airpay'] = 'Airpay (Cards / UPI / Net Banking)';
$string['paymentmethod_manual'] = 'Bank transfer (manual approval)';
$string['billingdetails']    = 'Billing details';
$string['billingname']       = 'Name';
$string['billingemail']      = 'Email';
$string['billingphone']      = 'Phone';
$string['billingaddress']    = 'Address';
$string['billinggstn']       = 'GST number (optional)';
$string['placeorder']        = 'Place order';
$string['orderconfirmation'] = 'Order confirmation';
$string['ordernumber']       = 'Order #{$a}';
$string['ordersuccess']      = 'Your order has been placed successfully.';
$string['orderpending']      = 'Your order is pending payment confirmation.';
$string['orderfailed']       = 'Payment failed: {$a}';
$string['downloadreceipt']   = 'Download receipt';
$string['downloadinvoice']   = 'Download invoice';

// Order status.
$string['status_pending']    = 'Pending';
$string['status_paid']       = 'Paid';
$string['status_failed']     = 'Failed';
$string['status_refunded']   = 'Refunded';
$string['status_cancelled']  = 'Cancelled';
$string['status_partial_refund'] = 'Partial refund';

// History.
$string['orderhistory_empty'] = 'You have no orders yet.';
$string['ordered_on']         = 'Ordered on';
$string['order_amount']       = 'Amount';
$string['order_courses']      = 'Courses';

// Admin.
$string['allorders']         = 'All orders';
$string['filter_status']     = 'Status';
$string['filter_tenant']     = 'Tenant';
$string['filter_daterange']  = 'Date range';
$string['exportcsv']         = 'Export CSV';
$string['exportreport_daily'] = 'Daily sums report';
$string['refund_full']        = 'Full refund';
$string['refund_partial']     = 'Partial refund';
$string['refund_amount']      = 'Refund amount';
$string['refund_reason']      = 'Reason';
$string['refund_confirm']     = 'Confirm refund';

// Pricing.
$string['price']              = 'Price';
$string['price_inr']          = '₹{$a}';
$string['price_free']         = 'FREE';
$string['price_strikethrough'] = '<s>₹{$a}</s>';
$string['discount']           = 'Discount';
$string['discount_pct']       = '{$a}% off';

// Settings.
$string['settings_general']   = 'General';
$string['settings_payment']   = 'Payment';
$string['settings_tax']       = 'Tax & invoicing';
$string['settings_email']     = 'Email notifications';
$string['settings_gateway_airpay'] = 'Airpay Gateway';
$string['settings_gateway_airpay_endpoint'] = 'API endpoint';
$string['settings_gateway_airpay_endpoint_desc'] = 'Airpay Payment Services API URL (e.g. https://payments.airpay.co.in/pay/index.php)';
$string['settings_gateway_airpay_merchantid'] = 'Merchant ID';
$string['settings_gateway_airpay_secret'] = 'Secret key';
$string['settings_gateway_airpay_secret_desc'] = 'Used to sign payloads. Store in environment variable in production.';
$string['settings_currency']  = 'Currency';
$string['settings_gstrate']   = 'GST rate (%)';
$string['settings_gstn']      = 'Our GSTN';
$string['settings_companyname'] = 'Company name on invoice';
$string['settings_companyaddress'] = 'Company address';
$string['settings_invoiceprefix'] = 'Invoice number prefix';
$string['settings_enabled_tenants'] = 'Tenants where cart is enabled';
$string['settings_enabled_tenants_desc'] = 'Comma-separated tenant root IDs (e.g. "77,177"). Leave empty to enable for all tenants. Airpay tenant (id=1) typically does not need cart since training is a benefit.';
$string['settings_callback_iplist'] = 'Gateway callback IP allow-list';
$string['settings_callback_iplist_desc'] = 'Comma-separated CIDR ranges or single IPs allowed to POST to /local/sentientia_cart/callback.php. Empty = accept from anywhere (legacy). When configured, requests from other sources are silently dropped with HTTP 404. Confirm gateway IPs with Airpay before enabling.';

// Messages (notifications).
$string['messageprovider:order_placed']    = 'Order placed confirmation';
$string['messageprovider:payment_received'] = 'Payment received';
$string['messageprovider:order_failed']    = 'Order failed';
$string['messageprovider:refund_processed'] = 'Refund processed';
$string['messageprovider:admin_new_order']  = 'New order (admin)';

// ADR-031 decision 3 (2026-09-29): a paid line mark_paid() did not enrol.
$string['refunddue']      = 'Refund due';
$string['paid_withheld']  = 'This order also included {$a} course(s) that are no longer available to you. You cannot access them and you have not been enrolled in them. They will be refunded to you.';
$string['admin_withheld'] = 'Refund due: order #{$a->orderid} was paid, but the buyer was NOT enrolled in course id(s) {$a->courseids}, which they may no longer buy (ADR-031). Refund those lines with a partial refund; a full refund also unenrols the buyer from the courses they were granted.';
$string['ordernotes']     = 'Staff notes';

// cart.withheld_line_refund (2026-10-07): what each withheld line was charged, for the administrator's review.
$string['admin_withheld_amounts'] = 'For review, not an invoice: the amount charged for each withheld line (price - discount + GST share = charged). The GST share is worked out from the order\'s recorded tax with the same paise rounding as the order total, so it can differ from the invoice by a paisa. You decide the refund, with a partial refund; Finance issues any GST credit note.';
$string['admin_withheld_line']    = '- course id {$a->courseid} ({$a->name}): {$a->price} - {$a->discount} + GST {$a->tax} = {$a->total} {$a->currency}';
$string['admin_withheld_total']   = 'Withheld lines total: {$a->total} {$a->currency} (order total {$a->ordertotal} {$a->currency}).';

// Order notifications (classes/notifier.php): each subject and body is built in the recipient's own language.
$string['notify_paid_intro']      = 'Thank you! Your order #{$a} has been confirmed.';
$string['notify_paid_courses']    = 'Courses:';
$string['notify_paid_total']      = 'Total: {$a->currency} {$a->amount}';
$string['notify_paid_access']     = 'You can now access your courses from the catalog.';
$string['notify_failed_subject']  = 'Order #{$a} failed';
$string['notify_failed_body']     = 'Your order could not be processed. Reason: {$a}';
$string['notify_failed_hint']     = 'Please try again from your cart, or contact support.';
$string['notify_refund_subject']  = 'Refund processed';
$string['notify_refund_full']     = 'Your order #{$a->orderid} has been fully refunded ({$a->currency} {$a->amount}).';
$string['notify_refund_partial']  = 'A partial refund of {$a->currency} {$a->amount} has been processed for order #{$a->orderid}.';
$string['notify_admin_subject']   = 'New order #{$a}';
$string['notify_admin_body']      = 'Order #{$a->orderid} placed by {$a->name} ({$a->email}) for {$a->currency} {$a->amount}.';
$string['notify_admin_unknown_buyer'] = 'unknown';

// Errors.
$string['error_courseunavailable'] = 'This course is no longer available for purchase.';
$string['error_alreadyenrolled']    = 'You are already enrolled in this course.';
$string['error_emptycart']          = 'Your cart is empty.';
$string['error_itemsunavailable']   = 'Some courses in your cart are no longer available to you and have been removed. Please check your new total before you pay.';
$string['error_gatewaydown']        = 'Payment gateway is currently unavailable. Please try again.';
$string['error_invalidsignature']    = 'Payment verification failed.';
$string['error_invalidstate']        = 'Invalid order state for this action.';
$string['error_outoftenant']         = 'This action is not allowed across tenants.';

// Privacy.
$string['privacy:metadata:local_sentientia_cart_history'] = 'Cart and order history';
$string['privacy:metadata:local_sentientia_cart_history:userid'] = 'The user who placed the order';
$string['privacy:metadata:local_sentientia_cart_history:items'] = 'Course IDs and prices at time of purchase';
$string['privacy:metadata:local_sentientia_cart_history:totalamount'] = 'Order total';
$string['privacy:metadata:local_sentientia_cart_history:status'] = 'Order status';
$string['privacy:metadata:local_sentientia_cart_history:timecreated'] = 'When the order was placed';
$string['privacy:metadata:local_sentientia_cart_invoices'] = 'Issued GST tax invoices. The law requires them to be kept (CGST Act section 36), so they are kept unchanged for the retention period; your details are blanked only on invoices older than that when you ask for erasure.';
$string['privacy:metadata:local_sentientia_cart_invoices:userid'] = 'The user the invoice was issued to';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_name'] = 'Billing name on invoice';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_email'] = 'Billing email on invoice';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_phone'] = 'Billing phone on invoice';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_address'] = 'Billing address on invoice';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_gstn'] = 'Customer GSTN if provided';
$string['privacy:metadata:local_sentientia_cart_ledger'] = 'Payment ledger (immutable audit log)';
$string['privacy:metadata:gateway'] = 'Payment data transmitted to gateway';
$string['privacy:metadata:gateway:email'] = 'Email for payment receipts';
$string['privacy:metadata:gateway:name'] = 'Name for billing';
$string['privacy:metadata:gateway:amount'] = 'Amount to charge';

// Privacy metadata for tables this plugin owned but never declared
// (added 2026-09-22, see classes/privacy/provider.php).
$string['privacy:metadata:local_sentientia_cart_id'] = 'The shopping basket you have open but have not yet paid for.';
$string['privacy:metadata:local_sentientia_cart_id:userid'] = 'The ID of the user the basket belongs to.';
$string['privacy:metadata:local_sentientia_cart_id:reserved'] = 'Internal basket state.';
$string['privacy:metadata:local_sentientia_cart_credits'] = 'Your training-credit balance and what you have earned and spent.';
$string['privacy:metadata:local_sentientia_cart_credits:userid'] = 'The ID of the user the balance belongs to.';
$string['privacy:metadata:local_sentientia_cart_credits:balance'] = 'The current credit balance.';
$string['privacy:metadata:local_sentientia_cart_credits:currency'] = 'The currency the balance is held in.';
$string['privacy:metadata:local_sentientia_cart_credits:lifetime_earned'] = 'Total credit earned to date.';
$string['privacy:metadata:local_sentientia_cart_credits:lifetime_spent'] = 'Total credit spent to date.';
$string['privacy:metadata:local_sentientia_cart_credits:timemodified'] = 'When the balance last changed.';

// ADR-032 (2026-10-01): orders, invoices and credits imported from BizLMS.
$string['status_open']            = 'Open';
$string['status_abandoned']       = 'Abandoned';
$string['status_part_cancelled']  = 'Partly cancelled';
$string['linestatus_paid']        = 'Paid';
$string['linestatus_cancelled']   = 'Cancelled';
$string['linestatus_not_completed'] = 'Not completed';
$string['legacyinvoice']          = 'Issued in ERPNext as {$a}';
$string['legacyinvoicedate']      = 'Invoice date';
$string['legacyinvoicenote']      = 'ERPNext issued this invoice before the move to Sentientia. This page is a reference only: Sentientia does not issue, change or re-issue it.';
$string['ordernumberlabel']       = 'Order number';
$string['creditsadmin']           = 'Credits';
$string['creditsadmin_title']     = 'Credits imported from BizLMS';
$string['creditsadmin_note']      = 'Frozen history for finance. Nothing in Sentientia pays out, honours or writes off a balance until finance decides.';
$string['credits_balances']       = 'Balances';
$string['credits_transactions']   = 'Transactions';
$string['credits_user']           = 'User';
$string['credits_balance']        = 'Balance';
$string['credits_earned']         = 'Earned';
$string['credits_spent']          = 'Spent';
$string['credits_event']          = 'Event';
$string['credits_amount']         = 'Amount';
$string['credits_balance_after']  = 'Balance after';
$string['credits_when']           = 'Date';
$string['credits_none']           = 'No imported credits.';
$string['credit_event_earned_cancellation'] = 'Earned by a cancellation';
$string['credit_event_redeemed']  = 'Redeemed';
$string['credit_event_payout']    = 'Paid out';
$string['credit_event_correction'] = 'Correction';
$string['credit_event_legacy_unclassified'] = 'Unclassified';
$string['error_notavailable']     = 'This page is not available.';

// Privacy metadata for what the BizLMS import added (classes/privacy/provider.php).
$string['privacy:metadata:local_sentientia_cart_ledger:initiatedby'] = 'The ID of the user who made the booking: a refund, or a booking imported from the previous system.';
$string['privacy:metadata:local_sentientia_cart_ledger:payload'] = 'What the previous system recorded for an imported booking: the buyer, the item and the payment details.';
$string['privacy:metadata:local_sentientia_cart_ledger:amount'] = 'The amount of the payment, refund or booking.';
$string['privacy:metadata:local_sentientia_cart_ledger:timecreated'] = 'When the booking was made.';
$string['privacy:metadata:local_sentientia_cart_credit_txn'] = 'Credit bookings imported from the previous system.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:userid'] = 'The ID of the user who holds the credit.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:initiatedby'] = 'The ID of the user who made the booking.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:amount'] = 'The change the booking made to the balance.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:balance_after'] = 'The balance after the booking.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:event_type'] = 'The kind of booking.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:reason'] = 'The reason given for the booking.';
$string['privacy:metadata:local_sentientia_cart_credit_txn:timecreated'] = 'When the booking was made.';

// ── GST tax invoices, credit notes and purchase receipts (2026-10-14; spec GST-TAX-INVOICE-AND-RECEIPT-SPEC-2026-10-14.md) ──

// Capability, message provider and scheduled task.
$string['sentientia_cart:manageinvoices'] = 'Record e-invoice IRNs and see the invoices that wait for them';
$string['messageprovider:gst_admin_warning'] = 'GST invoicing needs attention (admin)';
$string['task_issue_due_documents'] = 'Issue due GST tax invoices and credit notes';

// Settings.
$string['settings_gst']                    = 'GST tax invoices and credit notes';
$string['settings_gst_desc']               = 'Used only while the GST tax invoices flag is ON. Invoicing refuses to start while the company name, the company address, our GSTN or the SAC code is empty, or the GST state is not valid. The documents then stay due, and the site administrators are warned once a day.';
$string['settings_saccode']                = 'SAC code of the service';
$string['settings_saccode_desc']           = 'The Services Accounting Code printed on every tax invoice line. Required, with no default: your Finance team confirms it.';
$string['settings_supplierstate']          = 'Our GST state';
$string['settings_supplierstate_desc']     = 'The state our supplies are made from. Leave it on "From our GSTN" to use the first two digits of our GSTN.';
$string['settings_supplierstate_fromgstn'] = 'From our GSTN';
$string['settings_creditnoteprefix']       = 'Credit note number prefix';
$string['settings_creditnoteprefix_desc']  = 'E.g. AIRCN gives AIRCN-2026-0001. The whole number may not be longer than 16 characters, so with the year and four digits the prefix may have at most 6 characters.';
$string['settings_seriesperiod']           = 'Numbering series year';
$string['settings_seriesperiod_desc']      = 'With the calendar year the numbers start again each January; with the financial year each April (the year in the number is the year the financial year starts).';
$string['settings_seriesperiod_calendar']  = 'Calendar year (January to December)';
$string['settings_seriesperiod_financial'] = 'Financial year (April to March)';
$string['settings_einvoiceb2b']            = 'B2B documents need an e-invoice IRN';
$string['settings_einvoiceb2b_desc']       = 'When the buyer gives a GSTIN, the invoice or credit note waits for its IRN, which Finance records on the e-invoice page. Turn it off only if the turnover is below the e-invoicing limit.';
$string['settings_retentionyears']         = 'Years to keep tax documents';
$string['settings_retentionyears_desc']    = 'An erasure request blanks the buyer details only on invoices and credit notes older than this; younger ones are kept, as the law requires (CGST Act section 36). Default 8.';
$string['settings_signatory']              = 'Signatory line';
$string['settings_signatory_desc']         = 'The line printed under the company name at the foot of each document.';
$string['einvoice_link']                   = 'E-invoice IRN page';
$string['signatory_default']               = 'Authorised signatory';
$string['settings_problem_company_name']    = 'Company name on invoice';
$string['settings_problem_company_address'] = 'Company address';
$string['settings_problem_our_gstn']        = 'Our GSTN';
$string['settings_problem_sac_code']        = 'SAC code of the service';
$string['settings_problem_supplier_state']  = 'Our GST state';

// Errors.
$string['error_invoicesettings'] = 'GST invoicing cannot start. These settings are missing or invalid: {$a}.';
$string['error_numbertoolong']   = 'The document number {$a->number} would be {$a->length} characters long, and the limit is {$a->max}. Shorten the number prefix in the settings.';
$string['error_creditnotelimit'] = 'The credit notes of an invoice cannot total more than the invoice.';
$string['error_statemissing']    = 'Please choose your state.';
$string['error_statenotvalid']   = 'This is not a valid GST state code.';
$string['error_gstinformat']     = 'This does not look like a GST number. It has 15 letters and digits and starts with the two-digit state code.';
$string['error_gstinstate']      = 'The state in your GST number does not match the state you chose. Please check both.';
$string['error_irninvalid']      = 'The IRN must be exactly 64 hexadecimal characters (0-9 and a-f).';
$string['error_ackinvalid']      = 'The acknowledgement number must be 1 to 40 letters, digits or hyphens.';
$string['error_ackdateinvalid']  = 'Enter the acknowledgement date and time shown on the portal; it cannot be in the future.';
$string['error_qrinvalid']       = 'Paste the signed QR string from the portal: printable characters without spaces, at most 2900.';
$string['error_irnduplicate']    = 'This IRN is already recorded on another document.';
$string['error_irnnotawaiting']  = 'This document is not waiting for an IRN.';

// Checkout (the state field) and the order history / order page links.
$string['billingstate']        = 'State';
$string['billingstate_choose'] = 'Select your state';
$string['billingstate_hint']   = 'Used to work out the GST on your tax invoice. If you enter a GST number, its first two digits must match this state.';
$string['history_documents']   = 'Documents';
$string['history_receipt']     = 'Receipt';
$string['history_taxinvoice']  = 'Tax invoice';
$string['history_creditnote']  = 'Credit note';
$string['viewreceipt']         = 'View receipt';

// Messages.
$string['notify_paid_receipt'] = 'Your payment receipt: {$a}';
$string['notify_gst_subject']  = 'GST invoicing needs attention';
$string['notify_gst_body']     = 'Some GST tax invoices or credit notes could not be issued. They stay due, and are issued automatically within 15 minutes once the cause is fixed:
{$a}

Check the tax settings under Site administration > Plugins > Local plugins > Sentientia Cart (GSTN, SAC code, GST state, number prefixes). This warning is sent once a day while the cause remains.';

// Tax invoice and credit note (every word on the document).
$string['doc_taxinvoice']  = 'Tax Invoice';
$string['doc_creditnote']  = 'Credit Note';
$string['doc_invoiceno']   = 'Invoice No.';
$string['doc_creditnoteno'] = 'Credit Note No.';
$string['doc_date']        = 'Date';
$string['doc_supplier']    = 'Supplier';
$string['doc_buyer']       = 'Buyer';
$string['doc_gstin']       = 'GSTIN';
$string['doc_state']       = 'State';
$string['doc_placeofsupply'] = 'Place of supply';
$string['doc_srno']        = 'No.';
$string['doc_description'] = 'Description';
$string['doc_sac']         = 'SAC';
$string['doc_taxable']     = 'Taxable value';
$string['doc_rate']        = 'Rate';
$string['doc_cgst']        = 'CGST';
$string['doc_sgst']        = 'SGST';
$string['doc_igst']        = 'IGST';
$string['doc_total']       = 'Total';
$string['doc_totaltaxable'] = 'Total taxable value';
$string['doc_totaltax']    = 'Total GST';
$string['doc_amountinwords'] = 'Amount in words';
$string['doc_reversecharge'] = 'Reverse charge';
$string['doc_no']          = 'No';
$string['doc_irn']         = 'IRN';
$string['doc_ackno']       = 'Ack. No.';
$string['doc_ackdate']     = 'Ack. date';
$string['doc_qralt']       = 'Signed e-invoice QR code';
$string['doc_computergenerated_invoice']    = 'This is a computer-generated invoice.';
$string['doc_computergenerated_creditnote'] = 'This is a computer-generated credit note.';
$string['doc_againstinvoice'] = 'Against tax invoice';
$string['doc_dated']       = 'dated';
$string['doc_reason']      = 'Reason';
$string['doc_print']       = 'Print / Save as PDF';
$string['doc_downloadpdf'] = 'Download PDF';
$string['doc_pendingirn']  = 'This document is awaiting its e-invoice IRN and is not visible to the buyer yet.';
$string['doc_email']       = 'Email';
$string['doc_phone']       = 'Phone';
$string['doc_grandtotal']  = 'Grand total';
$string['doc_tax']         = 'GST';
$string['doc_for']         = 'For {$a}';
$string['doc_creditline']  = 'Credit for the refund of order #{$a}';

// Payment receipt.
$string['receipt_page_title']      = 'Receipt for order #{$a}';
$string['receipt_title']           = 'Payment receipt';
$string['receipt_orderno']         = 'Order number';
$string['receipt_paidon']          = 'Paid on';
$string['receipt_buyer']           = 'Paid by';
$string['receipt_email']           = 'Email';
$string['receipt_items']           = 'Items';
$string['receipt_item']            = 'Item';
$string['receipt_price']           = 'Price';
$string['receipt_discount']        = 'Discount';
$string['receipt_subtotal']        = 'Subtotal';
$string['receipt_gst']             = 'GST charged';
$string['receipt_totalpaid']       = 'Total paid';
$string['receipt_payment']         = 'Payment';
$string['receipt_gateway']         = 'Payment method';
$string['receipt_reference']       = 'Reference';
$string['receipt_refunds']         = 'Refunds';
$string['receipt_refundedon']      = 'Refunded on';
$string['receipt_amount']          = 'Amount';
$string['receipt_notinvoice']      = 'This is a payment receipt, not a tax invoice.';
$string['receipt_taxinvoice']      = 'Tax invoice';
$string['receipt_viewtaxinvoice']  = 'View tax invoice';
$string['receipt_invoicewillfollow'] = 'Your GST tax invoice will follow.';
$string['receipt_creditnotes']     = 'Credit notes';
$string['receipt_viewcreditnote']  = 'View credit note';
$string['receipt_downloadpdf']     = 'Download PDF';
$string['receipt_print']           = 'Print';
$string['receipt_status']          = 'Status';

// E-invoice (IRN) page.
$string['einvoice_title']          = 'E-invoice IRN';
$string['einvoice_intro']          = 'B2B tax documents wait here for their e-invoice IRN. Register each one on the government Invoice Registration Portal (IRP), then record its IRN, acknowledgement number, acknowledgement date and signed QR string here. The document then becomes visible to the buyer. Sentientia has no connection to the portal.';
$string['einvoice_none']           = 'No documents are waiting for an IRN.';
$string['einvoice_type_invoice']   = 'Tax invoice';
$string['einvoice_type_creditnote'] = 'Credit note';
$string['einvoice_irn']            = 'IRN (64 hexadecimal characters)';
$string['einvoice_ackno']          = 'Acknowledgement number';
$string['einvoice_ackdate']        = 'Acknowledgement date and time (IST)';
$string['einvoice_qr']             = 'Signed QR string';
$string['einvoice_record']         = 'Record IRN';
$string['einvoice_view']           = 'View document';
$string['einvoice_recorded']       = 'The IRN of {$a} is recorded. The document is now visible to the buyer.';

// Privacy metadata (classes/privacy/provider.php): the tax documents are kept for the retention period.
$string['privacy:metadata:local_sentientia_cart_history:billing_state'] = 'The state you chose at checkout.';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_state'] = 'The state of the buyer.';
$string['privacy:metadata:local_sentientia_cart_invoices:place_of_supply'] = 'The place of supply of the sale, as a GST state code.';
$string['privacy:metadata:local_sentientia_cart_invoices:line_items'] = 'What was bought, with its price and tax.';
$string['privacy:metadata:local_sentientia_cart_invoices:irn'] = 'The e-invoice reference number of a B2B invoice.';
$string['privacy:metadata:local_sentientia_cart_creditnotes'] = 'GST credit notes issued when an order is refunded. Like the invoice they reduce, they are kept unchanged for the retention period that tax law requires (CGST Act section 36); your details are blanked only on documents older than that when you ask for erasure.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:userid'] = 'The user the credit note was issued to.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_name'] = 'Billing name on the credit note.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_email'] = 'Billing email on the credit note.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_phone'] = 'Billing phone on the credit note.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_address'] = 'Billing address on the credit note.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_gstn'] = 'Customer GSTN, if provided.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_state'] = 'The state of the buyer.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:total'] = 'The amount refunded.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:reason'] = 'The reason given for the refund.';
$string['privacy:metadata:local_sentientia_cart_creditnotes:irn'] = 'The e-invoice reference number of a B2B credit note.';

// GST state codes (classes/gst_states.php): 01 to 38 and 97.
$string['gst_state_01'] = 'Jammu and Kashmir';
$string['gst_state_02'] = 'Himachal Pradesh';
$string['gst_state_03'] = 'Punjab';
$string['gst_state_04'] = 'Chandigarh';
$string['gst_state_05'] = 'Uttarakhand';
$string['gst_state_06'] = 'Haryana';
$string['gst_state_07'] = 'Delhi';
$string['gst_state_08'] = 'Rajasthan';
$string['gst_state_09'] = 'Uttar Pradesh';
$string['gst_state_10'] = 'Bihar';
$string['gst_state_11'] = 'Sikkim';
$string['gst_state_12'] = 'Arunachal Pradesh';
$string['gst_state_13'] = 'Nagaland';
$string['gst_state_14'] = 'Manipur';
$string['gst_state_15'] = 'Mizoram';
$string['gst_state_16'] = 'Tripura';
$string['gst_state_17'] = 'Meghalaya';
$string['gst_state_18'] = 'Assam';
$string['gst_state_19'] = 'West Bengal';
$string['gst_state_20'] = 'Jharkhand';
$string['gst_state_21'] = 'Odisha';
$string['gst_state_22'] = 'Chhattisgarh';
$string['gst_state_23'] = 'Madhya Pradesh';
$string['gst_state_24'] = 'Gujarat';
$string['gst_state_25'] = 'Daman and Diu';
$string['gst_state_26'] = 'Dadra and Nagar Haveli and Daman and Diu';
$string['gst_state_27'] = 'Maharashtra';
$string['gst_state_28'] = 'Andhra Pradesh (old)';
$string['gst_state_29'] = 'Karnataka';
$string['gst_state_30'] = 'Goa';
$string['gst_state_31'] = 'Lakshadweep';
$string['gst_state_32'] = 'Kerala';
$string['gst_state_33'] = 'Tamil Nadu';
$string['gst_state_34'] = 'Puducherry';
$string['gst_state_35'] = 'Andaman and Nicobar Islands';
$string['gst_state_36'] = 'Telangana';
$string['gst_state_37'] = 'Andhra Pradesh';
$string['gst_state_38'] = 'Ladakh';
$string['gst_state_97'] = 'Other Territory';
$string['error_einvoiceoff'] = 'GST tax invoices are switched off wherever you work, so there is nothing to record here.';
