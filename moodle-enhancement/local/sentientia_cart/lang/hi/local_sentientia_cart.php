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
$string['privacy:metadata:local_sentientia_cart_invoices']                 = 'जारी किए गए जीएसटी टैक्स इनवॉइस। कानून के अनुसार इन्हें रखना आवश्यक है (CGST अधिनियम धारा 36), इसलिए ये प्रतिधारण अवधि तक अपरिवर्तित रखे जाते हैं; मिटाने का अनुरोध करने पर आपका विवरण केवल उससे पुराने इनवॉइस से हटाया जाता है।';
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

// ── जीएसटी टैक्स इनवॉइस, क्रेडिट नोट और खरीद रसीदें (2026-10-14) ──

// क्षमता, संदेश प्रदाता और निर्धारित कार्य।
$string['sentientia_cart:manageinvoices'] = 'ई-इनवॉइस IRN दर्ज करें और IRN की प्रतीक्षा कर रहे इनवॉइस देखें';
$string['messageprovider:gst_admin_warning'] = 'जीएसटी इनवॉइसिंग पर ध्यान आवश्यक (एडमिन)';
$string['task_issue_due_documents'] = 'देय जीएसटी टैक्स इनवॉइस और क्रेडिट नोट जारी करें';

// सेटिंग्स।
$string['settings_gst']                    = 'जीएसटी टैक्स इनवॉइस और क्रेडिट नोट';
$string['settings_gst_desc']               = 'केवल तब उपयोग होता है जब "जीएसटी टैक्स इनवॉइस" फ़्लैग चालू हो। कंपनी का नाम, कंपनी का पता, हमारा GSTN या SAC कोड खाली होने पर, या जीएसटी राज्य मान्य न होने पर इनवॉइसिंग शुरू नहीं होती। दस्तावेज़ देय बने रहते हैं और साइट एडमिन को दिन में एक बार चेतावनी भेजी जाती है।';
$string['settings_saccode']                = 'सेवा का SAC कोड';
$string['settings_saccode_desc']           = 'हर टैक्स इनवॉइस पंक्ति पर छपने वाला सेवा लेखा कोड (SAC)। अनिवार्य, कोई डिफ़ॉल्ट नहीं: आपकी फ़ाइनेंस टीम इसकी पुष्टि करती है।';
$string['settings_supplierstate']          = 'हमारा जीएसटी राज्य';
$string['settings_supplierstate_desc']     = 'जिस राज्य से हमारी आपूर्ति की जाती है। हमारे GSTN के पहले दो अंक उपयोग करने के लिए "हमारे GSTN से" चुनें।';
$string['settings_supplierstate_fromgstn'] = 'हमारे GSTN से';
$string['settings_creditnoteprefix']       = 'क्रेडिट नोट नंबर का उपसर्ग';
$string['settings_creditnoteprefix_desc']  = 'उदाहरण: AIRCN से AIRCN-2026-0001 बनता है। पूरा नंबर 16 अक्षरों से अधिक नहीं हो सकता, इसलिए वर्ष और चार अंकों के साथ उपसर्ग में अधिकतम 6 अक्षर हो सकते हैं।';
$string['settings_seriesperiod']           = 'क्रमांक श्रृंखला का वर्ष';
$string['settings_seriesperiod_desc']      = 'कैलेंडर वर्ष में नंबर हर जनवरी से फिर शुरू होते हैं; वित्तीय वर्ष में हर अप्रैल से (नंबर में वर्ष वह होता है जिसमें वित्तीय वर्ष शुरू होता है)।';
$string['settings_seriesperiod_calendar']  = 'कैलेंडर वर्ष (जनवरी से दिसंबर)';
$string['settings_seriesperiod_financial'] = 'वित्तीय वर्ष (अप्रैल से मार्च)';
$string['settings_einvoiceb2b']            = 'B2B दस्तावेज़ों के लिए ई-इनवॉइस IRN आवश्यक';
$string['settings_einvoiceb2b_desc']       = 'जब खरीदार GSTIN देता है, तो इनवॉइस या क्रेडिट नोट अपने IRN की प्रतीक्षा करता है, जिसे फ़ाइनेंस ई-इनवॉइस पृष्ठ पर दर्ज करता है। केवल तभी बंद करें जब टर्नओवर ई-इनवॉइसिंग की सीमा से कम हो।';
$string['settings_retentionyears']         = 'टैक्स दस्तावेज़ रखने के वर्ष';
$string['settings_retentionyears_desc']    = 'मिटाने के अनुरोध पर खरीदार का विवरण केवल इससे पुराने इनवॉइस और क्रेडिट नोट से हटाया जाता है; नए दस्तावेज़ कानून (CGST अधिनियम धारा 36) के अनुसार रखे जाते हैं। डिफ़ॉल्ट 8।';
$string['settings_signatory']              = 'हस्ताक्षरकर्ता पंक्ति';
$string['settings_signatory_desc']         = 'हर दस्तावेज़ के अंत में कंपनी के नाम के नीचे छपने वाली पंक्ति।';
$string['einvoice_link']                   = 'ई-इनवॉइस IRN पृष्ठ';
$string['signatory_default']               = 'अधिकृत हस्ताक्षरकर्ता';
$string['settings_problem_company_name']    = 'इनवॉइस पर कंपनी का नाम';
$string['settings_problem_company_address'] = 'कंपनी का पता';
$string['settings_problem_our_gstn']        = 'हमारा GSTN';
$string['settings_problem_sac_code']        = 'सेवा का SAC कोड';
$string['settings_problem_supplier_state']  = 'हमारा जीएसटी राज्य';

// त्रुटियाँ।
$string['error_invoicesettings'] = 'जीएसटी इनवॉइसिंग शुरू नहीं हो सकती। ये सेटिंग्स अनुपस्थित या अमान्य हैं: {$a}।';
$string['error_numbertoolong']   = 'दस्तावेज़ नंबर {$a->number} में {$a->length} अक्षर होंगे, जबकि सीमा {$a->max} है। सेटिंग्स में नंबर का उपसर्ग छोटा करें।';
$string['error_creditnotelimit'] = 'किसी इनवॉइस के क्रेडिट नोट का कुल योग इनवॉइस से अधिक नहीं हो सकता।';
$string['error_statemissing']    = 'कृपया अपना राज्य चुनें।';
$string['error_statenotvalid']   = 'यह मान्य जीएसटी राज्य कोड नहीं है।';
$string['error_gstinformat']     = 'यह जीएसटी नंबर जैसा नहीं दिखता। इसमें 15 अक्षर और अंक होते हैं और यह दो अंकों के राज्य कोड से शुरू होता है।';
$string['error_gstinstate']      = 'आपके जीएसटी नंबर का राज्य आपके चुने हुए राज्य से मेल नहीं खाता। कृपया दोनों जाँचें।';
$string['error_irninvalid']      = 'IRN ठीक 64 हेक्साडेसिमल अक्षर (0-9 और a-f) का होना चाहिए।';
$string['error_ackinvalid']      = 'पावती संख्या 1 से 40 अक्षर, अंक या हाइफ़न की होनी चाहिए।';
$string['error_ackdateinvalid']  = 'पोर्टल पर दिखाई गई पावती की तिथि और समय दर्ज करें; यह भविष्य का नहीं हो सकता।';
$string['error_qrinvalid']       = 'पोर्टल से हस्ताक्षरित QR स्ट्रिंग चिपकाएँ: बिना रिक्त स्थान के छपने योग्य अक्षर, अधिकतम 2900।';
$string['error_irnduplicate']    = 'यह IRN पहले से किसी अन्य दस्तावेज़ पर दर्ज है।';
$string['error_irnnotawaiting']  = 'यह दस्तावेज़ IRN की प्रतीक्षा में नहीं है।';

// चेकआउट (राज्य का फ़ील्ड) और ऑर्डर इतिहास / ऑर्डर पृष्ठ के लिंक।
$string['billingstate']        = 'राज्य';
$string['billingstate_choose'] = 'अपना राज्य चुनें';
$string['billingstate_hint']   = 'आपके टैक्स इनवॉइस पर जीएसटी तय करने के लिए। यदि आप जीएसटी नंबर दर्ज करते हैं, तो उसके पहले दो अंक इस राज्य से मेल खाने चाहिए।';
$string['history_documents']   = 'दस्तावेज़';
$string['history_receipt']     = 'रसीद';
$string['history_taxinvoice']  = 'टैक्स इनवॉइस';
$string['history_creditnote']  = 'क्रेडिट नोट';
$string['viewreceipt']         = 'रसीद देखें';

// संदेश।
$string['notify_paid_receipt'] = 'आपकी भुगतान रसीद: {$a}';
$string['notify_gst_subject']  = 'जीएसटी इनवॉइसिंग पर ध्यान आवश्यक';
$string['notify_gst_body']     = 'कुछ जीएसटी टैक्स इनवॉइस या क्रेडिट नोट जारी नहीं हो सके। वे देय बने रहते हैं और कारण ठीक होने के 15 मिनट के भीतर अपने आप जारी हो जाएँगे:
{$a}

साइट एडमिनिस्ट्रेशन > प्लगइन्स > लोकल प्लगइन्स > सेंटिएंटिया कार्ट के अंतर्गत कर सेटिंग्स (GSTN, SAC कोड, जीएसटी राज्य, नंबर उपसर्ग) जाँचें। कारण बने रहने तक यह चेतावनी दिन में एक बार भेजी जाती है।';

// टैक्स इनवॉइस और क्रेडिट नोट (दस्तावेज़ का हर शब्द)।
$string['doc_taxinvoice']  = 'टैक्स इनवॉइस';
$string['doc_creditnote']  = 'क्रेडिट नोट';
$string['doc_invoiceno']   = 'इनवॉइस सं.';
$string['doc_creditnoteno'] = 'क्रेडिट नोट सं.';
$string['doc_date']        = 'दिनांक';
$string['doc_supplier']    = 'आपूर्तिकर्ता';
$string['doc_buyer']       = 'खरीदार';
$string['doc_gstin']       = 'GSTIN';
$string['doc_state']       = 'राज्य';
$string['doc_placeofsupply'] = 'आपूर्ति का स्थान';
$string['doc_srno']        = 'क्र.';
$string['doc_description'] = 'विवरण';
$string['doc_sac']         = 'SAC';
$string['doc_taxable']     = 'कर योग्य मूल्य';
$string['doc_rate']        = 'दर';
$string['doc_cgst']        = 'CGST';
$string['doc_sgst']        = 'SGST';
$string['doc_igst']        = 'IGST';
$string['doc_total']       = 'कुल';
$string['doc_totaltaxable'] = 'कुल कर योग्य मूल्य';
$string['doc_totaltax']    = 'कुल जीएसटी';
$string['doc_amountinwords'] = 'शब्दों में राशि';
$string['doc_reversecharge'] = 'रिवर्स चार्ज';
$string['doc_no']          = 'नहीं';
$string['doc_irn']         = 'IRN';
$string['doc_ackno']       = 'पावती सं.';
$string['doc_ackdate']     = 'पावती दिनांक';
$string['doc_qralt']       = 'हस्ताक्षरित ई-इनवॉइस QR कोड';
$string['doc_computergenerated_invoice']    = 'यह कंप्यूटर द्वारा बनाया गया इनवॉइस है।';
$string['doc_computergenerated_creditnote'] = 'यह कंप्यूटर द्वारा बनाया गया क्रेडिट नोट है।';
$string['doc_againstinvoice'] = 'टैक्स इनवॉइस के विरुद्ध';
$string['doc_dated']       = 'दिनांक';
$string['doc_reason']      = 'कारण';
$string['doc_print']       = 'प्रिंट करें / PDF के रूप में सहेजें';
$string['doc_downloadpdf'] = 'PDF डाउनलोड करें';
$string['doc_pendingirn']  = 'यह दस्तावेज़ अपने ई-इनवॉइस IRN की प्रतीक्षा में है और अभी खरीदार को दिखाई नहीं देता।';
$string['doc_email']       = 'ईमेल';
$string['doc_phone']       = 'फ़ोन';
$string['doc_grandtotal']  = 'कुल योग';
$string['doc_tax']         = 'जीएसटी';
$string['doc_for']         = '{$a} के लिए';
$string['doc_creditline']  = 'ऑर्डर #{$a} के रिफ़ंड के लिए क्रेडिट';

// भुगतान रसीद।
$string['receipt_page_title']      = 'ऑर्डर #{$a} की रसीद';
$string['receipt_title']           = 'भुगतान रसीद';
$string['receipt_orderno']         = 'ऑर्डर संख्या';
$string['receipt_paidon']          = 'भुगतान की तिथि';
$string['receipt_buyer']           = 'भुगतानकर्ता';
$string['receipt_email']           = 'ईमेल';
$string['receipt_items']           = 'आइटम';
$string['receipt_item']            = 'आइटम';
$string['receipt_price']           = 'मूल्य';
$string['receipt_discount']        = 'छूट';
$string['receipt_subtotal']        = 'उप-योग';
$string['receipt_gst']             = 'लगाया गया जीएसटी';
$string['receipt_totalpaid']       = 'कुल भुगतान';
$string['receipt_payment']         = 'भुगतान';
$string['receipt_gateway']         = 'भुगतान का तरीका';
$string['receipt_reference']       = 'संदर्भ';
$string['receipt_refunds']         = 'रिफ़ंड';
$string['receipt_refundedon']      = 'रिफ़ंड की तिथि';
$string['receipt_amount']          = 'राशि';
$string['receipt_notinvoice']      = 'यह भुगतान की रसीद है, टैक्स इनवॉइस नहीं।';
$string['receipt_taxinvoice']      = 'टैक्स इनवॉइस';
$string['receipt_viewtaxinvoice']  = 'टैक्स इनवॉइस देखें';
$string['receipt_invoicewillfollow'] = 'आपका जीएसटी टैक्स इनवॉइस बाद में भेजा जाएगा।';
$string['receipt_creditnotes']     = 'क्रेडिट नोट';
$string['receipt_viewcreditnote']  = 'क्रेडिट नोट देखें';
$string['receipt_downloadpdf']     = 'PDF डाउनलोड करें';
$string['receipt_print']           = 'प्रिंट करें';
$string['receipt_status']          = 'स्थिति';

// ई-इनवॉइस (IRN) पृष्ठ।
$string['einvoice_title']          = 'ई-इनवॉइस IRN';
$string['einvoice_intro']          = 'B2B टैक्स दस्तावेज़ अपने ई-इनवॉइस IRN की प्रतीक्षा में यहाँ दिखते हैं। हर दस्तावेज़ को सरकारी इनवॉइस रजिस्ट्रेशन पोर्टल (IRP) पर पंजीकृत करें, फिर उसका IRN, पावती संख्या, पावती की तिथि और हस्ताक्षरित QR स्ट्रिंग यहाँ दर्ज करें। इसके बाद दस्तावेज़ खरीदार को दिखाई देने लगता है। Sentientia का पोर्टल से कोई कनेक्शन नहीं है।';
$string['einvoice_none']           = 'कोई दस्तावेज़ IRN की प्रतीक्षा में नहीं है।';
$string['einvoice_type_invoice']   = 'टैक्स इनवॉइस';
$string['einvoice_type_creditnote'] = 'क्रेडिट नोट';
$string['einvoice_irn']            = 'IRN (64 हेक्साडेसिमल अक्षर)';
$string['einvoice_ackno']          = 'पावती संख्या';
$string['einvoice_ackdate']        = 'पावती की तिथि और समय (IST)';
$string['einvoice_qr']             = 'हस्ताक्षरित QR स्ट्रिंग';
$string['einvoice_record']         = 'IRN दर्ज करें';
$string['einvoice_view']           = 'दस्तावेज़ देखें';
$string['einvoice_recorded']       = '{$a} का IRN दर्ज हो गया है। दस्तावेज़ अब खरीदार को दिखाई देता है।';

// निजता मेटाडेटा (classes/privacy/provider.php): टैक्स दस्तावेज़ प्रतिधारण अवधि तक रखे जाते हैं।
$string['privacy:metadata:local_sentientia_cart_history:billing_state'] = 'चेकआउट पर आपका चुना हुआ राज्य।';
$string['privacy:metadata:local_sentientia_cart_invoices:billing_state'] = 'खरीदार का राज्य।';
$string['privacy:metadata:local_sentientia_cart_invoices:place_of_supply'] = 'बिक्री की आपूर्ति का स्थान, जीएसटी राज्य कोड के रूप में।';
$string['privacy:metadata:local_sentientia_cart_invoices:line_items'] = 'क्या खरीदा गया, उसके मूल्य और कर सहित।';
$string['privacy:metadata:local_sentientia_cart_invoices:irn'] = 'B2B इनवॉइस का ई-इनवॉइस संदर्भ क्रमांक।';
$string['privacy:metadata:local_sentientia_cart_creditnotes'] = 'ऑर्डर रिफ़ंड होने पर जारी किए गए जीएसटी क्रेडिट नोट। जिस इनवॉइस को वे घटाते हैं उसी की तरह, वे कर कानून द्वारा आवश्यक प्रतिधारण अवधि (CGST अधिनियम धारा 36) तक अपरिवर्तित रखे जाते हैं; मिटाने का अनुरोध करने पर आपका विवरण केवल उससे पुराने दस्तावेज़ों से हटाया जाता है।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:userid'] = 'जिस यूज़र को क्रेडिट नोट जारी किया गया।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_name'] = 'क्रेडिट नोट पर बिलिंग नाम।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_email'] = 'क्रेडिट नोट पर बिलिंग ईमेल।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_phone'] = 'क्रेडिट नोट पर बिलिंग फ़ोन।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_address'] = 'क्रेडिट नोट पर बिलिंग पता।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_gstn'] = 'ग्राहक का GSTN, यदि दिया गया।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:billing_state'] = 'खरीदार का राज्य।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:total'] = 'रिफ़ंड की गई राशि।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:reason'] = 'रिफ़ंड के लिए दिया गया कारण।';
$string['privacy:metadata:local_sentientia_cart_creditnotes:irn'] = 'B2B क्रेडिट नोट का ई-इनवॉइस संदर्भ क्रमांक।';

// जीएसटी राज्य कोड (classes/gst_states.php): 01 से 38 और 97।
$string['gst_state_01'] = 'जम्मू और कश्मीर';
$string['gst_state_02'] = 'हिमाचल प्रदेश';
$string['gst_state_03'] = 'पंजाब';
$string['gst_state_04'] = 'चंडीगढ़';
$string['gst_state_05'] = 'उत्तराखंड';
$string['gst_state_06'] = 'हरियाणा';
$string['gst_state_07'] = 'दिल्ली';
$string['gst_state_08'] = 'राजस्थान';
$string['gst_state_09'] = 'उत्तर प्रदेश';
$string['gst_state_10'] = 'बिहार';
$string['gst_state_11'] = 'सिक्किम';
$string['gst_state_12'] = 'अरुणाचल प्रदेश';
$string['gst_state_13'] = 'नागालैंड';
$string['gst_state_14'] = 'मणिपुर';
$string['gst_state_15'] = 'मिज़ोरम';
$string['gst_state_16'] = 'त्रिपुरा';
$string['gst_state_17'] = 'मेघालय';
$string['gst_state_18'] = 'असम';
$string['gst_state_19'] = 'पश्चिम बंगाल';
$string['gst_state_20'] = 'झारखंड';
$string['gst_state_21'] = 'ओडिशा';
$string['gst_state_22'] = 'छत्तीसगढ़';
$string['gst_state_23'] = 'मध्य प्रदेश';
$string['gst_state_24'] = 'गुजरात';
$string['gst_state_25'] = 'दमन और दीव';
$string['gst_state_26'] = 'दादरा और नगर हवेली और दमन और दीव';
$string['gst_state_27'] = 'महाराष्ट्र';
$string['gst_state_28'] = 'आंध्र प्रदेश (पुराना)';
$string['gst_state_29'] = 'कर्नाटक';
$string['gst_state_30'] = 'गोवा';
$string['gst_state_31'] = 'लक्षद्वीप';
$string['gst_state_32'] = 'केरल';
$string['gst_state_33'] = 'तमिलनाडु';
$string['gst_state_34'] = 'पुदुच्चेरी';
$string['gst_state_35'] = 'अंडमान और निकोबार द्वीप समूह';
$string['gst_state_36'] = 'तेलंगाना';
$string['gst_state_37'] = 'आंध्र प्रदेश';
$string['gst_state_38'] = 'लद्दाख';
$string['gst_state_97'] = 'अन्य क्षेत्र';
$string['error_einvoiceoff'] = 'जहाँ आप काम करते हैं वहाँ GST कर चालान बंद हैं, इसलिए यहाँ दर्ज करने के लिए कुछ नहीं है।';
$string['gateway_name_airpay'] = 'Airpay';
$string['gateway_name_manual'] = 'मैनुअल भुगतान';
