<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Cart';

// Navigation.
$string['cart']         = 'Cart';
$string['mycart']       = 'My Cart';
$string['mycartlong']   = 'My cart and orders';
$string['checkout']     = 'Checkout';
$string['orderhistory'] = 'Order history';
$string['adminorders']  = 'Manage orders';

// Capabilities.
$string['airpay_cart:view']        = 'View own cart';
$string['airpay_cart:purchase']    = 'Add items to cart and purchase';
$string['airpay_cart:viewallorders'] = 'View all orders (admin)';
$string['airpay_cart:refund']      = 'Process refunds';
$string['airpay_cart:manageprices'] = 'Manage course pricing';

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

// Messages (notifications).
$string['messageprovider:order_placed']    = 'Order placed confirmation';
$string['messageprovider:payment_received'] = 'Payment received';
$string['messageprovider:order_failed']    = 'Order failed';
$string['messageprovider:refund_processed'] = 'Refund processed';
$string['messageprovider:admin_new_order']  = 'New order (admin)';

// Errors.
$string['error_courseunavailable'] = 'This course is no longer available for purchase.';
$string['error_alreadyenrolled']    = 'You are already enrolled in this course.';
$string['error_emptycart']          = 'Your cart is empty.';
$string['error_gatewaydown']        = 'Payment gateway is currently unavailable. Please try again.';
$string['error_invalidsignature']    = 'Payment verification failed.';
$string['error_invalidstate']        = 'Invalid order state for this action.';
$string['error_outoftenant']         = 'This action is not allowed across tenants.';

// Privacy.
$string['privacy:metadata:local_airpay_cart_history'] = 'Cart and order history';
$string['privacy:metadata:local_airpay_cart_history:userid'] = 'The user who placed the order';
$string['privacy:metadata:local_airpay_cart_history:items'] = 'Course IDs and prices at time of purchase';
$string['privacy:metadata:local_airpay_cart_history:totalamount'] = 'Order total';
$string['privacy:metadata:local_airpay_cart_history:status'] = 'Order status';
$string['privacy:metadata:local_airpay_cart_history:timecreated'] = 'When the order was placed';
$string['privacy:metadata:local_airpay_cart_invoices'] = 'Issued invoices';
$string['privacy:metadata:local_airpay_cart_invoices:userid'] = 'The user the invoice was issued to';
$string['privacy:metadata:local_airpay_cart_invoices:billing_name'] = 'Billing name on invoice';
$string['privacy:metadata:local_airpay_cart_invoices:billing_email'] = 'Billing email on invoice';
$string['privacy:metadata:local_airpay_cart_invoices:billing_phone'] = 'Billing phone on invoice';
$string['privacy:metadata:local_airpay_cart_invoices:billing_address'] = 'Billing address on invoice';
$string['privacy:metadata:local_airpay_cart_invoices:billing_gstn'] = 'Customer GSTN if provided';
$string['privacy:metadata:local_airpay_cart_ledger'] = 'Payment ledger (immutable audit log)';
$string['privacy:metadata:gateway'] = 'Payment data transmitted to gateway';
$string['privacy:metadata:gateway:email'] = 'Email for payment receipts';
$string['privacy:metadata:gateway:name'] = 'Name for billing';
$string['privacy:metadata:gateway:amount'] = 'Amount to charge';
