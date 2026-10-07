<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #57 (2026-05-20) — Hindi (hi) translations for local_sentientia_cart.
// Scope: shopping cart, checkout, payment, order history, admin orders,
// pricing, settings (Airpay gateway / tax / email / IP allow-list),
// notifications, errors, privacy metadata.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया कार्ट';

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

// ADR-031 decision 3 (2026-09-29): a paid line mark_paid() did not enrol.
$string['refunddue']      = 'रिफ़ंड देय';
$string['paid_withheld']  = 'इस ऑर्डर में {$a} ऐसे कोर्स भी थे जो अब आपके लिए उपलब्ध नहीं हैं। आप उन्हें एक्सेस नहीं कर सकते और आपको उनमें नामांकित नहीं किया गया है। उनकी राशि आपको रिफ़ंड की जाएगी।';
$string['admin_withheld'] = 'रिफ़ंड देय: ऑर्डर #{$a->orderid} का भुगतान हो गया, लेकिन खरीदार को कोर्स ID {$a->courseids} में नामांकित नहीं किया गया, क्योंकि वे अब उन्हें नहीं खरीद सकते (ADR-031)। उन लाइनों का आंशिक रिफ़ंड करें; पूर्ण रिफ़ंड खरीदार को दिए गए कोर्स से भी अनामांकित कर देता है।';
$string['ordernotes']     = 'स्टाफ़ नोट्स';

// cart.withheld_line_refund (2026-10-07): रोकी गई हर लाइन से कितनी राशि वसूली गई, प्रशासक की समीक्षा के लिए।
$string['admin_withheld_amounts'] = 'समीक्षा हेतु, इनवॉइस नहीं: रोकी गई हर लाइन से वसूली गई राशि (मूल्य - छूट + GST हिस्सा = वसूली गई राशि)। GST हिस्सा ऑर्डर के दर्ज कर से, ऑर्डर कुल जैसे ही पैसे तक पूर्णांकन के साथ निकाला गया है, इसलिए इनवॉइस से एक पैसे का अंतर हो सकता है। रिफ़ंड का निर्णय आप आंशिक रिफ़ंड से करते हैं; GST क्रेडिट नोट, यदि आवश्यक हो, वित्त जारी करता है।';
$string['admin_withheld_line']    = '- कोर्स ID {$a->courseid} ({$a->name}): {$a->price} - {$a->discount} + GST {$a->tax} = {$a->total} {$a->currency}';
$string['admin_withheld_total']   = 'रोकी गई लाइनों का कुल: {$a->total} {$a->currency} (ऑर्डर कुल {$a->ordertotal} {$a->currency})।';

// ऑर्डर सूचनाएँ (classes/notifier.php): हर विषय और संदेश प्राप्तकर्ता की अपनी भाषा में बनता है।
$string['notify_paid_intro']      = 'धन्यवाद! आपका ऑर्डर #{$a} पुष्ट हो गया है।';
$string['notify_paid_courses']    = 'कोर्स:';
$string['notify_paid_total']      = 'कुल: {$a->currency} {$a->amount}';
$string['notify_paid_access']     = 'अब आप कैटलॉग से अपने कोर्स एक्सेस कर सकते हैं।';
$string['notify_failed_subject']  = 'ऑर्डर #{$a} विफल रहा';
$string['notify_failed_body']     = 'आपका ऑर्डर प्रोसेस नहीं हो सका। कारण: {$a}';
$string['notify_failed_hint']     = 'कृपया अपने कार्ट से फिर प्रयास करें, या सहायता से संपर्क करें।';
$string['notify_refund_subject']  = 'रिफ़ंड प्रोसेस किया गया';
$string['notify_refund_full']     = 'आपके ऑर्डर #{$a->orderid} का पूरा रिफ़ंड कर दिया गया है ({$a->currency} {$a->amount})।';
$string['notify_refund_partial']  = 'ऑर्डर #{$a->orderid} के लिए {$a->currency} {$a->amount} का आंशिक रिफ़ंड प्रोसेस किया गया है।';
$string['notify_admin_subject']   = 'नया ऑर्डर #{$a}';
$string['notify_admin_body']      = 'ऑर्डर #{$a->orderid} {$a->name} ({$a->email}) ने {$a->currency} {$a->amount} के लिए दिया है।';
$string['notify_admin_unknown_buyer'] = 'अज्ञात';

// Errors.
$string['error_courseunavailable'] = 'यह कोर्स अब खरीद के लिए उपलब्ध नहीं है।';
$string['error_alreadyenrolled']   = 'आप इस कोर्स में पहले से नामांकित हैं।';
$string['error_emptycart']         = 'आपका कार्ट खाली है।';
$string['error_itemsunavailable']  = 'आपके कार्ट के कुछ कोर्स अब आपके लिए उपलब्ध नहीं हैं और हटा दिए गए हैं। भुगतान करने से पहले कृपया अपनी नई कुल राशि देखें।';
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

// Privacy metadata for tables this plugin owned but never declared
// (added 2026-09-22, see classes/privacy/provider.php).
$string['privacy:metadata:local_sentientia_cart_id'] = 'आपकी खुली हुई टोकरी जिसका भुगतान अभी नहीं हुआ।';
$string['privacy:metadata:local_sentientia_cart_id:userid'] = 'टोकरी के मालिक उपयोगकर्ता की आईडी।';
$string['privacy:metadata:local_sentientia_cart_id:reserved'] = 'आंतरिक टोकरी स्थिति।';
$string['privacy:metadata:local_sentientia_cart_credits'] = 'आपका प्रशिक्षण-क्रेडिट शेष और अर्जित/खर्च किया गया।';
$string['privacy:metadata:local_sentientia_cart_credits:userid'] = 'शेष के मालिक उपयोगकर्ता की आईडी।';
$string['privacy:metadata:local_sentientia_cart_credits:balance'] = 'वर्तमान क्रेडिट शेष।';
$string['privacy:metadata:local_sentientia_cart_credits:currency'] = 'शेष की मुद्रा।';
$string['privacy:metadata:local_sentientia_cart_credits:lifetime_earned'] = 'अब तक अर्जित कुल क्रेडिट।';
$string['privacy:metadata:local_sentientia_cart_credits:lifetime_spent'] = 'अब तक खर्च किया गया कुल क्रेडिट।';
$string['privacy:metadata:local_sentientia_cart_credits:timemodified'] = 'शेष अंतिम बार कब बदला।';

// ADR-032 (2026-10-01): BizLMS से आयात किए गए ऑर्डर, इनवॉइस और क्रेडिट।
$string['status_open']            = 'खुला';
$string['status_abandoned']       = 'छोड़ा गया';
$string['status_part_cancelled']  = 'आंशिक रूप से रद्द';
$string['linestatus_paid']        = 'भुगतान किया गया';
$string['linestatus_cancelled']   = 'रद्द';
$string['linestatus_not_completed'] = 'पूर्ण नहीं हुआ';
$string['legacyinvoice']          = 'ERPNext में {$a} के रूप में जारी';
$string['legacyinvoicedate']      = 'इनवॉइस की तिथि';
$string['legacyinvoicenote']      = 'यह इनवॉइस Sentientia पर आने से पहले ERPNext द्वारा जारी किया गया था। यह पृष्ठ केवल संदर्भ के लिए है: Sentientia इसे न जारी करता है, न बदलता है और न दोबारा जारी करता है।';
$string['ordernumberlabel']       = 'ऑर्डर नंबर';
$string['creditsadmin']           = 'क्रेडिट';
$string['creditsadmin_title']     = 'BizLMS से आयात किए गए क्रेडिट';
$string['creditsadmin_note']      = 'वित्त के लिए स्थिर इतिहास। वित्त के निर्णय तक Sentientia किसी भी शेष को न चुकाता है, न मान्य करता है और न बट्टे खाते में डालता है।';
$string['credits_balances']       = 'शेष';
$string['credits_transactions']   = 'लेन-देन';
$string['credits_user']           = 'उपयोगकर्ता';
$string['credits_balance']        = 'शेष राशि';
$string['credits_earned']         = 'अर्जित';
$string['credits_spent']          = 'खर्च';
$string['credits_event']          = 'घटना';
$string['credits_amount']         = 'राशि';
$string['credits_balance_after']  = 'इसके बाद शेष';
$string['credits_when']           = 'तिथि';
$string['credits_none']           = 'कोई आयातित क्रेडिट नहीं।';
$string['credit_event_earned_cancellation'] = 'रद्दीकरण से अर्जित';
$string['credit_event_redeemed']  = 'उपयोग किया गया';
$string['credit_event_payout']    = 'भुगतान किया गया';
$string['credit_event_correction'] = 'सुधार';
$string['credit_event_legacy_unclassified'] = 'अवर्गीकृत';
$string['error_notavailable']     = 'यह पृष्ठ उपलब्ध नहीं है।';

// BizLMS आयात द्वारा जोड़े गए डेटा के लिए गोपनीयता मेटाडेटा (classes/privacy/provider.php)।
$string['privacy:metadata:local_sentientia_cart_ledger:initiatedby'] = 'उस उपयोगकर्ता की आईडी जिसने प्रविष्टि की: रिफ़ंड, या पिछली प्रणाली से आयातित प्रविष्टि।';
$string['privacy:metadata:local_sentientia_cart_ledger:payload'] = 'पिछली प्रणाली ने आयातित प्रविष्टि के लिए जो दर्ज किया: खरीदार, वस्तु और भुगतान विवरण।';
$string['privacy:metadata:local_sentientia_cart_ledger:amount'] = 'भुगतान, रिफ़ंड या प्रविष्टि की राशि।';
$string['privacy:metadata:local_sentientia_cart_ledger:timecreated'] = 'प्रविष्टि कब की गई।';
$string['privacy:metadata:local_sentientia_cart_credit_txn'] = 'पिछली प्रणाली से आयातित क्रेडिट प्रविष्टियाँ।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:userid'] = 'क्रेडिट धारक उपयोगकर्ता की आईडी।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:initiatedby'] = 'उस उपयोगकर्ता की आईडी जिसने प्रविष्टि की।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:amount'] = 'प्रविष्टि से शेष में हुआ परिवर्तन।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:balance_after'] = 'प्रविष्टि के बाद का शेष।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:event_type'] = 'प्रविष्टि का प्रकार।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:reason'] = 'प्रविष्टि के लिए दिया गया कारण।';
$string['privacy:metadata:local_sentientia_cart_credit_txn:timecreated'] = 'प्रविष्टि कब की गई।';
