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
$string['privacy:metadata:local_sentientia_cart_invoices'] = 'Issued invoices';
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
