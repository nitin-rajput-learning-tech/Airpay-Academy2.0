<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
// हिन्दी translations — local_airpay_whatsapp
// Machine quality. Native-speaker review recommended before high-traffic deploy.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे WhatsApp और SMS';

// Page chrome
$string['preferences_pagetitle']   = 'संचार प्राथमिकताएँ';
$string['preferences_nav']         = 'संचार प्राथमिकताएँ';
$string['preferences_heading']     = 'एयरपे एकेडमी आप तक कैसे पहुँचे?';
$string['preferences_intro']       = 'चुनें कि एयरपे एकेडमी किन चैनलों से आपको कोर्स अपडेट, डेडलाइन रिमाइंडर और सर्टिफिकेट अलर्ट भेज सकता है। ईमेल हमेशा चालू रहता है — यह वह आधार चैनल है जो बाकी चैनलों के न पहुँच पाने पर भी संदेश पहुँचाता है।';

// Channel labels
$string['channel_email']           = 'ईमेल';
$string['channel_whatsapp']        = 'WhatsApp';
$string['channel_sms']             = 'SMS';
$string['channel_email_desc']      = 'हमेशा सक्रिय। आपका कार्यालय ईमेल आधार चैनल है।';
$string['channel_whatsapp_desc']   = 'सबसे तेज़ ओपन रेट। टेम्पलेट भारत में DLT के तहत पूर्व-स्वीकृत हैं।';
$string['channel_sms_desc']        = 'एक घंटे में 95% ओपन रेट। बिना इंटरनेट के काम करता है, फील्ड स्टाफ़ के लिए उत्तम।';

// Mobile capture
$string['mobile_label']            = 'मोबाइल नंबर';
$string['mobile_hint']             = 'WhatsApp और SMS के लिए ज़रूरी। देश कोड शामिल करें (जैसे भारत के लिए +91)।';
$string['mobile_invalid']          = 'कृपया देश कोड के साथ वैध मोबाइल नंबर दर्ज करें (जैसे +919876543210)।';

// Primary preference
$string['prefer_label']            = 'मुख्य चैनल';
$string['prefer_hint']             = 'जब एक से अधिक चैनल उपलब्ध हों, तो यह पहले प्रयास किया जाता है। डिलीवरी विफल होने पर सिस्टम ईमेल पर वापस आ जाता है।';

// DLT consent
$string['dlt_consent_heading']     = 'सहमति (भारत में WhatsApp/SMS के लिए आवश्यक)';
$string['dlt_consent_body']        = 'ऑप्ट-इन करके मैं TCCCPR 2018 और DPDP अधिनियम 2023 के अनुसार ऊपर चुने गए चैनलों पर एयरपे एकेडमी से लेन-देन और सेवा संदेश प्राप्त करने पर सहमत हूँ। मैं किसी भी समय इस पृष्ठ को संपादित करके सहमति वापस ले सकता/सकती हूँ।';
$string['dlt_consent_required']    = 'WhatsApp या SMS डिलीवरी सक्षम करने के लिए आपको सहमति विवरण स्वीकार करना होगा।';
$string['dlt_consent_logged_at']   = 'सहमति दर्ज की गई: {$a}';

// Disabled-by-tenant
$string['channel_disabled_tenant'] = 'यह चैनल अभी आपके संगठन के लिए अक्षम है। यदि आप इसे सक्षम कराना चाहते हैं तो अपने व्यवस्थापक से संपर्क करें।';

// Action buttons
$string['save_preferences']        = 'प्राथमिकताएँ सहेजें';
$string['preferences_saved']       = 'संचार प्राथमिकताएँ अपडेट हो गईं।';
$string['preferences_unchanged']   = 'कोई परिवर्तन सहेजने को नहीं।';

// Settings
$string['settings_pagetitle']         = 'एयरपे WhatsApp और SMS — सेटिंग्स';
$string['settings_heading_live_mode'] = 'प्रदाता क्रेडेंशियल';
$string['settings_heading_live_mode_desc'] = 'ये कुंजियाँ लाइव WhatsApp/SMS भेजने को सक्षम करती हैं। जब तक दोनों कुंजियाँ सेट नहीं हो जातीं और Switchboard फ़्लैग चालू नहीं होते, प्लगइन मॉक मोड में चलता है — संदेश लॉग होते हैं लेकिन वास्तविक प्रदाता को नहीं भेजे जाते।';
$string['settings_karix_api_key']     = 'Karix WhatsApp API कुंजी';
$string['settings_karix_api_key_desc'] = 'Karix Business API टोकन। WhatsApp को मॉक से लाइव मोड में बदलने के लिए आवश्यक।';
$string['settings_msg91_api_key']     = 'MSG91 SMS API कुंजी';
$string['settings_msg91_api_key_desc'] = 'MSG91 authkey। SMS को लाइव मोड में बदलने के लिए आवश्यक।';
$string['settings_dlt_pe_id']         = 'DLT प्रिंसिपल एंटिटी ID';
$string['settings_dlt_pe_id_desc']    = 'आपके संगठन की DLT-पंजीकृत Principal Entity ID। SMS के लिए आवश्यक।';

// Templates
$string['templates_pagetitle']        = 'DLT टेम्पलेट प्रबंधक';
$string['templates_heading']          = 'DLT-स्वीकृत संदेश टेम्पलेट';
$string['templates_intro']            = 'टेम्पलेट को ऑपरेटर के साथ DLT-पंजीकृत होना चाहिए। केवल `approved` टेम्पलेट उपयोग योग्य हैं।';
$string['template_status_updated']    = 'टेम्पलेट स्थिति अपडेट की गई।';
$string['show_body']                  = 'सामग्री दिखाएँ';
$string['th_template']                = 'टेम्पलेट कुंजी';
$string['th_channel']                 = 'चैनल';
$string['th_status']                  = 'स्थिति';
$string['th_dlt_id']                  = 'DLT ID';
$string['th_body']                    = 'सामग्री';
$string['th_actions']                 = 'क्रियाएँ';
$string['btn_submit']                 = 'DLT पर जमा करें';
$string['btn_approve']                = 'स्वीकृत के रूप में चिह्नित करें';
$string['btn_reject']                 = 'अस्वीकार करें';
$string['btn_redraft']                = 'पुनः ड्राफ़्ट करें';
$string['approved_ready']             = 'भेजने के लिए तैयार';

// Analytics
$string['analytics_pagetitle']        = 'चैनल विश्लेषण';
$string['analytics_heading']          = 'WhatsApp / SMS / ईमेल चैनल मिक्स';

// Privacy
$string['privacy:metadata:local_airpay_user_channel_prefs']
    = 'WhatsApp / SMS / ईमेल चैनलों के लिए प्रति-उपयोगकर्ता ऑप्ट-इन प्राथमिकताएँ, मोबाइल नंबर और DLT सहमति टाइमस्टैम्प सहित।';
$string['privacy:metadata:local_airpay_user_channel_prefs:userid']
    = 'वह उपयोगकर्ता जिससे यह प्राथमिकता संबंधित है।';
$string['privacy:metadata:local_airpay_user_channel_prefs:mobile_number']
    = 'देश कोड के साथ मोबाइल नंबर, WhatsApp और SMS संदेश पहुँचाने के लिए।';
$string['privacy:metadata:local_airpay_user_channel_prefs:whatsapp_optin']
    = 'क्या उपयोगकर्ता ने WhatsApp संदेश प्राप्त करने का ऑप्ट-इन किया है।';
$string['privacy:metadata:local_airpay_user_channel_prefs:sms_optin']
    = 'क्या उपयोगकर्ता ने SMS संदेश प्राप्त करने का ऑप्ट-इन किया है।';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_at']
    = 'टाइमस्टैम्प जब उपयोगकर्ता ने लेन-देन संदेशों के लिए DLT सहमति दी।';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_text']
    = 'ऑप्ट-इन के समय उपयोगकर्ता को प्रस्तुत सहमति भाषा का स्नैपशॉट।';
