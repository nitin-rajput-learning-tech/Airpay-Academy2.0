<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #57 (2026-05-20) — Hindi (hi) translations for local_sentientia_cart.
// Scope: shopping cart, checkout, payment, order history, admin orders,
// pricing, settings (Airpay gateway / tax / email / IP allow-list),
// notifications, errors, privacy metadata.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे कार्ट';

// Navigation.
$string['cart']         = 'कार्ट';
$string['mycart']       = 'मेरा कार्ट';
$string['mycartlong']   = 'मेरा कार्ट और ऑर्डर';
$string['checkout']     = 'चेकआउट';
$string['orderhistory'] = 'ऑर्डर इतिहास';
$string['adminorders']  = 'ऑर्डर प्रबंधित करें';

// Capabilities.
$string['sentientia_cart:view']         = 'अपना कार्ट देखें';
$string['sentientia_cart:purchase']     = 'कार्ट में आइटम जोड़ें और खरीदें';
$string['sentientia_cart:viewallorders'] = 'सभी ऑर्डर देखें (एडमिन)';
$string['sentientia_cart:refund']       = 'रिफ़ंड प्रोसेस करें';
$string['sentientia_cart:manageprices'] = 'कोर्स मूल्य निर्धारण प्रबंधित करें';

// Cart UI.
$string['emptycart']         = 'आपका कार्ट खाली है।';
$string['emptycart_hint']    = 'कैटलॉग ब्राउज़ करें और कार्ट में कोर्स जोड़ें।';
$string['browsecatalog']     = 'कैटलॉग ब्राउज़ करें';
$string['itemstotal']        = '{$a} आइटम';
$string['subtotal']          = 'उप-योग';
$string['tax']               = 'कर (GST 18%)';
$string['total']             = 'कुल';
$string['remove']            = 'हटाएँ';
$string['addtocart']         = 'कार्ट में जोड़ें';
$string['inyourcart']        = 'आपके कार्ट में';
$string['proceedtocheckout'] = 'चेकआउट पर जाएँ';

// Checkout.
$string['paymentmethod']        = 'भुगतान विधि';
$string['paymentmethod_airpay'] = 'एयरपे (कार्ड / UPI / नेट बैंकिंग)';
$string['paymentmethod_manual'] = 'बैंक ट्रांसफ़र (मैनुअल अनुमोदन)';
$string['billingdetails']       = 'बिलिंग विवरण';
$string['billingname']          = 'नाम';
$string['billingemail']         = 'ईमेल';
$string['billingphone']         = 'फ़ोन';
$string['billingaddress']       = 'पता';
$string['billinggstn']          = 'GST नंबर (वैकल्पिक)';
$string['placeorder']           = 'ऑर्डर दें';
$string['orderconfirmation']    = 'ऑर्डर पुष्टि';
$string['ordernumber']          = 'ऑर्डर #{$a}';
$string['ordersuccess']         = 'आपका ऑर्डर सफलतापूर्वक दिया गया है।';
$string['orderpending']         = 'आपका ऑर्डर भुगतान पुष्टि की प्रतीक्षा में है।';
$string['orderfailed']          = 'भुगतान विफल: {$a}';
$string['downloadreceipt']      = 'रसीद डाउनलोड करें';
$string['downloadinvoice']      = 'इनवॉइस डाउनलोड करें';

// Order status.
$string['status_pending']        = 'लंबित';
$string['status_paid']           = 'भुगतान किया गया';
$string['status_failed']         = 'विफल';
$string['status_refunded']       = 'रिफ़ंड किया गया';
$string['status_cancelled']      = 'रद्द';
$string['status_partial_refund'] = 'आंशिक रिफ़ंड';

// History.
$string['orderhistory_empty'] = 'आपके पास अभी तक कोई ऑर्डर नहीं है।';
$string['ordered_on']         = 'ऑर्डर किया गया';
$string['order_amount']       = 'राशि';
$string['order_courses']      = 'कोर्स';

// Admin.
$string['allorders']          = 'सभी ऑर्डर';
$string['filter_status']      = 'स्थिति';
$string['filter_tenant']      = 'टेनेंट';
$string['filter_daterange']   = 'तिथि सीमा';
$string['exportcsv']          = 'CSV निर्यात करें';
$string['exportreport_daily'] = 'दैनिक योग रिपोर्ट';
$string['refund_full']        = 'पूर्ण रिफ़ंड';
$string['refund_partial']     = 'आंशिक रिफ़ंड';
$string['refund_amount']      = 'रिफ़ंड राशि';
$string['refund_reason']      = 'कारण';
$string['refund_confirm']     = 'रिफ़ंड की पुष्टि करें';

// Pricing.
$string['price']               = 'मूल्य';
$string['price_inr']           = '₹{$a}';
$string['price_free']          = 'मुफ़्त';
$string['price_strikethrough'] = '<s>₹{$a}</s>';
$string['discount']            = 'छूट';
$string['discount_pct']        = '{$a}% की छूट';

// Settings.
$string['settings_general']        = 'सामान्य';
$string['settings_payment']        = 'भुगतान';
$string['settings_tax']            = 'कर और बिलिंग';
$string['settings_email']          = 'ईमेल सूचनाएँ';
$string['settings_gateway_airpay'] = 'एयरपे गेटवे';
$string['settings_gateway_airpay_endpoint']      = 'API एंडपॉइंट';
$string['settings_gateway_airpay_endpoint_desc'] = 'एयरपे पेमेंट सर्विसेज़ API URL (जैसे https://payments.airpay.co.in/pay/index.php)';
$string['settings_gateway_airpay_merchantid']    = 'मर्चेंट ID';
$string['settings_gateway_airpay_secret']        = 'सीक्रेट कुंजी';
$string['settings_gateway_airpay_secret_desc']   = 'पेलोड पर हस्ताक्षर करने के लिए इस्तेमाल होती है। प्रोडक्शन में environment variable में संग्रहीत करें।';
$string['settings_currency']         = 'मुद्रा';
$string['settings_gstrate']          = 'GST दर (%)';
$string['settings_gstn']             = 'हमारा GSTN';
$string['settings_companyname']      = 'इनवॉइस पर कंपनी का नाम';
$string['settings_companyaddress']   = 'कंपनी का पता';
$string['settings_invoiceprefix']    = 'इनवॉइस नंबर उपसर्ग';
$string['settings_enabled_tenants']  = 'टेनेंट जहाँ कार्ट सक्षम है';
$string['settings_enabled_tenants_desc'] = 'कॉमा-सेपरेटेड टेनेंट रूट ID (जैसे "77,177")। सभी टेनेंट के लिए सक्षम करने हेतु खाली छोड़ें। एयरपे टेनेंट (id=1) को आमतौर पर कार्ट की आवश्यकता नहीं होती क्योंकि प्रशिक्षण एक लाभ है।';
$string['settings_callback_iplist']      = 'गेटवे कॉलबैक IP अनुमति-सूची';
$string['settings_callback_iplist_desc'] = 'कॉमा-सेपरेटेड CIDR रेंज या एकल IP जिन्हें /local/sentientia_cart/callback.php पर POST करने की अनुमति है। खाली = कहीं से भी स्वीकार (विरासत)। कॉन्फ़िगर होने पर, अन्य स्रोतों से अनुरोध HTTP 404 के साथ मौन रूप से छोड़ दिए जाते हैं। सक्षम करने से पहले एयरपे के साथ गेटवे IP की पुष्टि करें।';

// Notifications.
$string['messageprovider:order_placed']     = 'ऑर्डर दिया गया पुष्टि';
$string['messageprovider:payment_received'] = 'भुगतान प्राप्त हुआ';
$string['messageprovider:order_failed']     = 'ऑर्डर विफल';
$string['messageprovider:refund_processed'] = 'रिफ़ंड प्रोसेस किया गया';
$string['messageprovider:admin_new_order']  = 'नया ऑर्डर (एडमिन)';

// Errors.
$string['error_courseunavailable'] = 'यह कोर्स अब खरीद के लिए उपलब्ध नहीं है।';
$string['error_alreadyenrolled']   = 'आप इस कोर्स में पहले से नामांकित हैं।';
$string['error_emptycart']         = 'आपका कार्ट खाली है।';
$string['error_gatewaydown']       = 'भुगतान गेटवे वर्तमान में अनुपलब्ध है। कृपया फिर से प्रयास करें।';
$string['error_invalidsignature']  = 'भुगतान सत्यापन विफल।';
$string['error_invalidstate']      = 'इस कार्रवाई के लिए अमान्य ऑर्डर स्थिति।';
$string['error_outoftenant']       = 'यह कार्रवाई टेनेंट के पार अनुमत नहीं है।';

// Privacy — cart_history.
$string['privacy:metadata:local_sentientia_cart_history']             = 'कार्ट और ऑर्डर इतिहास';
$string['privacy:metadata:local_sentientia_cart_history:userid']      = 'ऑर्डर देने वाला यूज़र';
$string['privacy:metadata:local_sentientia_cart_history:items']       = 'खरीद के समय कोर्स ID और मूल्य';
$string['privacy:metadata:local_sentientia_cart_history:totalamount'] = 'ऑर्डर कुल';
$string['privacy:metadata:local_sentientia_cart_history:status']      = 'ऑर्डर स्थिति';
$string['privacy:metadata:local_sentientia_cart_history:timecreated'] = 'ऑर्डर कब दिया गया';

// Privacy — invoices.
$string['privacy:metadata:local_sentientia_cart_invoices']                 = 'जारी किए गए इनवॉइस';
$string['privacy:metadata:local_sentientia_cart_invoices:userid']          = 'जिस यूज़र को इनवॉइस जारी किया गया';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_name']    = 'इनवॉइस पर बिलिंग नाम';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_email']   = 'इनवॉइस पर बिलिंग ईमेल';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_phone']   = 'इनवॉइस पर बिलिंग फ़ोन';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_address'] = 'इनवॉइस पर बिलिंग पता';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_gstn']    = 'यदि प्रदान किया गया तो ग्राहक GSTN';

// Privacy — ledger + gateway sub-provider.
$string['privacy:metadata:local_sentientia_cart_ledger'] = 'भुगतान खाता-बही (अपरिवर्तनीय ऑडिट लॉग)';
$string['privacy:metadata:gateway']                  = 'गेटवे को संचारित भुगतान डेटा';
$string['privacy:metadata:gateway:email']            = 'भुगतान रसीदों के लिए ईमेल';
$string['privacy:metadata:gateway:name']             = 'बिलिंग के लिए नाम';
$string['privacy:metadata:gateway:amount']           = 'चार्ज करने के लिए राशि';
