<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — PWA';

// Privacy.
$string['privacy:metadata'] = 'Sentientia LMS PWA प्लगइन प्रति-यूज़र वेब-पुश सब्सक्रिप्शन एंडपॉइंट संग्रहीत करता है (Phase B.2+)। Phase B.1 (केवल service worker) कोई व्यक्तिगत डेटा संग्रहीत नहीं करता।';

// ── Phase B.2.b — subscribe UI strings ────────────────────────────────

// Navigation.
$string['nav_label'] = 'ब्राउज़र सूचनाएं';

// User preferences page.
$string['preferences_page_title']    = 'ब्राउज़र सूचनाएं';
$string['preferences_page_heading']  = 'ब्राउज़र सूचनाएं';
$string['preferences_page_intro']    = 'इस ब्राउज़र पर पुश सूचनाएं चालू करें ताकि रिमाइंडर, कोर्स अपडेट और असाइनमेंट अलर्ट बिना Sentientia LMS को टैब में खुला रखे मिलें।';
$string['preferences_install_heading'] = 'Sentientia LMS को ऐप की तरह इंस्टॉल करें';
$string['preferences_install_intro']   = 'सर्वश्रेष्ठ मोबाइल अनुभव के लिए, अपने ब्राउज़र मेनू से Sentientia LMS को होम स्क्रीन पर इंस्टॉल करें। इंस्टॉल करने के बाद, पुश सूचनाएं किसी भी अन्य ऐप की तरह दिखाई देती हैं — भले ही ब्राउज़र बंद हो।';

// Subscribe widget.
$string['subscribe_section_title']  = 'पुश सूचनाएं';
$string['subscribe_intro']          = 'इस ब्राउज़र में डेडलाइन रिमाइंडर, कोर्स अपडेट और असाइनमेंट अलर्ट प्राप्त करें। आप इन्हें कभी भी बंद कर सकते हैं।';
$string['subscribe_enable']         = 'ब्राउज़र सूचनाएं चालू करें';
$string['subscribe_disable']        = 'ब्राउज़र सूचनाएं बंद करें';
$string['subscribe_unsupported']    = 'आपका ब्राउज़र पुश सूचनाओं का समर्थन नहीं करता';
$string['subscribe_denied']         = 'आपके ब्राउज़र ने सूचनाएं ब्लॉक की हैं';
$string['subscribe_privacy_note']   = 'पुश संदेश आपके ब्राउज़र वेंडर (Google, Mozilla या Apple) के माध्यम से रूट होते हैं। हम वेंडर के साथ कभी भी संदेश सामग्री साझा नहीं करते — केवल एक एन्क्रिप्टेड ब्लॉब जिसे आपका ब्राउज़र स्थानीय रूप से डिक्रिप्ट करता है।';

// VAPID not-set-up notices.
$string['vapid_not_setup_title']    = 'पुश सूचनाएं अभी कॉन्फ़िगर नहीं हैं';
$string['vapid_not_setup_body']     = 'इस सर्वर पर VAPID कीपेयर अभी तक जनरेट नहीं हुआ है। अपने एडमिन से एक बार "php local/sentientia_pwa/cli/generate_vapid_keys.php" चलाने के लिए कहें।';
$string['push_flag_off_notice']     = 'पुश डिलीवरी अभी आपके एडमिन ने बंद की है। आप इसे Switchboard से दोबारा चालू कर सकते हैं।';
$string['pwa_disabled_redirect']    = 'PWA फ़ीचर अभी बंद है। अपने एडमिन से संपर्क करें।';

// Error strings.
$string['vapid_already_exists']     = 'VAPID कीपेयर पहले से मौजूद है। ओवरराइट के लिए --regenerate पास करें (मौजूदा सब्सक्रिप्शन अमान्य हो जाएंगे)।';
$string['vapid_openssl_required']   = 'VAPID कीज़ जनरेट करने के लिए PHP openssl एक्सटेंशन ज़रूरी है।';
$string['vapid_generation_failed']  = 'VAPID कीज़ जनरेशन विफल: {$a}';

// Misc.
$string['missingrequiredfields']    = 'कोई आवश्यक फ़ील्ड गायब है।';

// ── Phase B.2.b — admin settings strings ──────────────────────────────

$string['settings_vapid_heading']         = 'VAPID कीपेयर स्थिति';
$string['settings_vapid_ready']           = 'VAPID कीपेयर सक्रिय है। पुश डिलीवरी Switchboard से चालू की जा सकती है।';
$string['settings_vapid_not_setup']       = 'कोई VAPID कीपेयर जनरेट नहीं हुआ। जब तक आप keygen CLI नहीं चलाएंगे, पुश सूचनाएं काम नहीं करेंगी।';
$string['settings_vapid_cli_instruction'] = 'सर्वर पर एक बार keygen CLI चलाएं (आमतौर पर वेब होस्ट पर, apache यूज़र के तौर पर):';
$string['settings_vapid_public_label']    = 'पब्लिक की (base64url)';
$string['settings_vapid_generated_label'] = 'जनरेट किया गया';
$string['settings_active_subs_label']     = 'सक्रिय सब्सक्रिप्शन';

$string['settings_vapid_subject_label']   = 'VAPID सब्जेक्ट';
$string['settings_vapid_subject_desc']    = 'पुश प्रदाताओं (Google FCM, Mozilla autopush) को JWT में भेजा गया कॉन्टैक्ट आइडेंटिफ़ायर। mailto: या https: URL होना चाहिए। पुश वेंडर इसका इस्तेमाल आपसे एब्यूज़ शिकायतों के बारे में संपर्क करने के लिए करते हैं।';

$string['settings_push_defaults_heading'] = 'पुश सूचना डिफ़ॉल्ट';
$string['settings_push_defaults_desc']    = 'प्रत्येक पुश डिलीवरी पर लागू होने वाले सर्वर-साइड डिफ़ॉल्ट। व्यक्तिगत संदेश इन्हें ओवरराइड कर सकते हैं।';

$string['settings_default_ttl_label']     = 'डिफ़ॉल्ट TTL (सेकंड)';
$string['settings_default_ttl_desc']      = 'अगर डिवाइस ऑफ़लाइन है तो पुश प्रदाता को कब तक डिलीवरी प्रयास करते रहना चाहिए। डिफ़ॉल्ट 86400 (24 घंटे)। पहले प्रयास में अनडिलीवर्ड संदेश छोड़ने के लिए 0 पर सेट करें।';

$string['settings_max_payload_label']     = 'अधिकतम पेलोड साइज़ (बाइट्स)';
$string['settings_max_payload_desc']      = 'प्रति पुश अधिकतम अनुमत पेलोड। Web Push स्पेक एन्क्रिप्शन के बाद ≤ 4096 बाइट्स अनिवार्य करता है। डिफ़ॉल्ट 3500 एन्क्रिप्शन ओवरहेड के लिए जगह छोड़ता है। बड़े पेलोड चुपचाप कट जाएंगे।';

// ── Phase B.3.c — push delivery log strings ──────────────────────────

$string['settings_log_retention_label']   = 'लॉग रिटेंशन (दिन)';
$string['settings_log_retention_desc']    = '<code>mdl_local_sentientia_push_log</code> में पंक्तियाँ कितने दिन रखें। दैनिक क्रॉन 02:00 पर पुरानी पंक्तियाँ हटाता है। असीमित रिटेंशन के लिए 0 पर सेट करें (व्यस्त डिप्लॉयमेंट पर अनुशंसित नहीं — टेबल तेज़ी से बढ़ती है)।';

$string['settings_push_log_link']         = 'पुश डिलीवरी लॉग देखें';
$string['settings_push_log_link_desc']    = 'हर पुश प्रयास का ऑपरेशनल लॉग। परिणाम, यूज़र और समय विंडो से फ़िल्टर करें।';

$string['task_push_log_retention']        = 'PWA पुश लॉग रिटेंशन (दैनिक purge)';

// Admin viewer page.
$string['push_log_page_title']            = 'PWA पुश डिलीवरी लॉग';
$string['push_log_page_heading']          = 'PWA पुश डिलीवरी लॉग';
$string['push_log_stats_24h']             = 'पिछले 24 घंटे';
$string['push_log_stats_line']            = '<strong>{$a->total_24h}</strong> प्रयास — <span class="text-success"><strong>{$a->sent_24h}</strong> भेजे गए</span>, <span class="text-warning">{$a->gone_24h} समाप्त</span>, <span class="text-danger">{$a->failed_24h} विफल</span>। <strong>{$a->unique_users_24h}</strong> विशिष्ट यूज़र।';
$string['push_log_filter_apply']          = 'लागू करें';
$string['push_log_filter_result']         = 'परिणाम';
$string['push_log_filter_since']          = 'इसके बाद से';
$string['push_log_filter_userid']         = 'यूज़र ID';
$string['push_log_filter_any']            = 'कोई भी';
$string['push_log_filter_sent']           = 'भेजे गए';
$string['push_log_filter_failed']         = 'विफल';
$string['push_log_filter_gone']           = 'समाप्त (sub डिलीट)';
$string['push_log_filter_truncated']      = 'काटा गया';
$string['push_log_since_1h']              = 'पिछला 1 घंटा';
$string['push_log_since_24h']             = 'पिछले 24 घंटे';
$string['push_log_since_7d']              = 'पिछले 7 दिन';
$string['push_log_since_30d']             = 'पिछले 30 दिन';
$string['push_log_since_all']             = 'सभी समय';
$string['push_log_no_results']            = 'फ़िल्टर से कोई पुश डिलीवरी मेल नहीं खाती।';
$string['push_log_total_count']           = '{$a} मेल खाती डिलीवरी';
$string['push_log_col_when']              = 'कब';
$string['push_log_col_user']              = 'यूज़र';
$string['push_log_col_host']              = 'पुश होस्ट';
$string['push_log_col_title']             = 'शीर्षक';
$string['push_log_col_result']            = 'परिणाम';
$string['push_log_col_http']              = 'HTTP';
$string['push_log_col_error']             = 'त्रुटि विवरण';

// ── Phase B.3.d — iOS install hint banner ─────────────────────────────

$string['ios_hint_title']   = 'पुश सूचनाएं चालू करने के लिए Sentientia LMS इंस्टॉल करें';
$string['ios_hint_body']    = 'iOS Safari पर, पुश सूचनाएं तभी काम करती हैं जब यह साइट होम स्क्रीन पर जोड़ी जाए:';
$string['ios_hint_step1']   = 'स्क्रीन के नीचे शेयर बटन पर टैप करें।';
$string['ios_hint_step2']   = 'नीचे स्क्रॉल करें और "होम स्क्रीन पर जोड़ें" चुनें।';
$string['ios_hint_step3']   = 'होम स्क्रीन से Sentientia LMS खोलें और सूचनाएं चालू करने का दोबारा प्रयास करें।';
$string['ios_hint_dismiss'] = 'खारिज करें';

// ── Audit 2026-05-21 — subscription validation errors ─────────────
$string['invalid_subscription_endpoint']  = 'सब्सक्रिप्शन एंडपॉइंट URL किसी मान्य पुश सेवा से नहीं है।';
$string['invalid_subscription_key_p256dh']= 'सब्सक्रिप्शन कुंजी (p256dh) विकृत है।';
$string['invalid_subscription_key_auth']  = 'सब्सक्रिप्शन auth secret विकृत है।';
$string['vapid_master_key_missing']       = 'VAPID private key डिस्क पर एन्क्रिप्ट है लेकिन master key कॉन्फ़िगर नहीं है। SENTIENTIA_VAPID_MASTER_KEY env var या $CFG->sentientia_vapid_master_key सेट करें।';
$string['vapid_pem_decrypt_failed']       = 'संग्रहित VAPID private key को डिक्रिप्ट नहीं किया जा सका। या तो master key बदली है या एन्क्रिप्टेड डेटा बदल गया है।';

// ── Phase D.1.b — Install CTA स्ट्रिंग्स ──────────────────────────
$string['install_cta_title']        = 'Sentientia LMS इंस्टॉल करें';
$string['install_cta_body']         = 'एक-टैप एक्सेस, पुश सूचना और ऑफ़लाइन सपोर्ट के लिए ऐप को होम स्क्रीन पर जोड़ें।';
$string['install_cta_install_btn']  = 'इंस्टॉल करें';
$string['install_cta_dismiss']      = 'अभी नहीं';
$string['install_cta_dismiss_aria'] = 'इंस्टॉल प्रॉम्प्ट खारिज करें';
$string['install_cta_aria_label']   = 'Sentientia LMS ऐप इंस्टॉल करें';
$string['install_cta_gotit']        = 'समझ गया';
