<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI सामग्री अनुवाद';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_translate:translate']     = 'Anthropic Claude से सामग्री का अनुवाद करें';
$string['sentientia_translate:manage_brands'] = 'प्रति-कस्टमर ब्रांड-नाम ओवरराइड प्रबंधित करें';
$string['sentientia_translate:manage_all']    = 'सभी स्वामियों के अनुवाद-इतिहास का प्रबंधन करें';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'Sentientia LMS AI अनुवाद प्लगइन स्रोत-पाठ और उसके अनुवाद संग्रहीत करता है। जब लाइव API फ़्लैग ON हो, तब स्रोत-पाठ प्रसंस्करण हेतु Anthropic को भेजा जाता है।';
$string['privacy:metadata:tr']               = 'उपयोगकर्ताओं द्वारा बनाए गए सामग्री अनुवाद।';
$string['privacy:metadata:tr:ownerid']       = 'वह उपयोगकर्ता जिसने अनुवाद बनाया।';
$string['privacy:metadata:tr:sourcetext']    = 'अनुवाद हेतु दिया गया वास्तविक स्रोत-पाठ।';
$string['privacy:metadata:tr:translatedtext'] = 'अनुवादित आउटपुट।';
$string['privacy:metadata:tr:targetlang']    = 'लक्षित भाषा कोड।';
$string['privacy:metadata:tr:title']         = 'उपयोगकर्ता द्वारा दिया गया अनुवाद का शीर्षक।';
$string['privacy:metadata:tr:tokens']        = 'लागत-गणना के लिए Anthropic API टोकन उपयोग।';
$string['privacy:metadata:tr:timecreated']   = 'अनुवाद कब बनाया गया।';
$string['privacy:metadata:tr:timemodified']  = 'अनुवाद अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:anthropic']             = 'Anthropic Claude API — जब लाइव API फ़्लैग सक्षम हो, स्रोत-पाठ अनुवाद के लिए Anthropic को भेजा जाता है। प्रत्येक API कॉल प्रति-कॉल पुष्टि चरण द्वारा सुरक्षित है।';
$string['privacy:metadata:anthropic:sourcetext']  = 'अनुवाद हेतु स्रोत-पाठ।';
$string['privacy:metadata:anthropic:targetlang']  = 'अनुरोधित लक्षित भाषा कोड।';
$string['privacy:metadata:anthropic:model']       = 'उपयोग किया गया Anthropic मॉडल पहचानकर्ता।';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_translate'] = 'सामग्री का अनुवाद करें';

// ── Target languages ───────────────────────────────────────────────
$string['lang_hi'] = 'हिन्दी (Hindi)';
$string['lang_mr'] = 'मराठी (Marathi)';
$string['lang_kn'] = 'कन्नड़ (ಕನ್ನಡ)';
$string['lang_sw'] = 'स्वाहिली (Kiswahili)';

// ── Translate page ─────────────────────────────────────────────────
$string['translate_page_title']   = 'सामग्री का अनुवाद करें';
$string['translate_page_heading'] = 'AI से कोर्स सामग्री का अनुवाद करें';
$string['translate_intro']        = 'अंग्रेज़ी स्रोत-सामग्री पेस्ट करें, एक लक्षित भाषा चुनें, और Anthropic Claude मूल लिपि में अनुवाद तैयार करता है। आपके कस्टमर ओवरराइड के अनुसार ब्रांड-नाम सुरक्षित रहते हैं। सहेजने से पहले साथ-साथ अंतर (diff) देखें।';
$string['form_title']             = 'अनुवाद का शीर्षक';
$string['form_targetlang']        = 'लक्षित भाषा';
$string['form_source']            = 'स्रोत सामग्री (अंग्रेज़ी)';
$string['form_source_help']       = 'अधिकतम {$a} शब्द। पेस्ट करने से पहले किसी भी PII (कर्मचारी नाम, ID, ग्राहक डेटा) को हटा दें।';
$string['form_model']             = 'मॉडल';
$string['form_confirm_label']     = 'मैं इस सामग्री के साथ Anthropic को कॉल करने की पुष्टि करता/करती हूँ';
$string['form_confirm_help']      = 'यह प्रति-कॉल पुष्टि-गेट है। यदि लाइव API फ़्लैग ON हो, तो यह सबमिशन स्रोत-लम्बाई के अनुसार वास्तविक धन-लागत उत्पन्न करेगा। रद्द करने हेतु निशान हटा दें।';
$string['form_submit']            = 'अनुवाद करें';
$string['form_cancel']            = 'रद्द करें';

// ── Diff page ──────────────────────────────────────────────────────
$string['diff_heading']      = 'अनुवाद की समीक्षा करें';
$string['diff_meta']         = 'लक्षित भाषा: {$a->lang} | लागू ब्रांड-प्रतिस्थापन: {$a->brands} | मोड: {$a->mode}';
$string['diff_source']       = 'स्रोत (अंग्रेज़ी)';
$string['diff_translation']  = 'अनुवाद';
$string['action_save']       = 'अनुवाद सहेजें';
$string['action_discard']    = 'खारिज करें';
$string['back_to_translate'] = 'अनुवाद पर वापस';
$string['saved_notice']      = 'अनुवाद सहेजा गया।';
$string['discarded_notice']  = 'अनुवाद खारिज किया गया।';

// ── Status ─────────────────────────────────────────────────────────
$string['status_label']      = 'स्थिति';
$string['status_pending']    = 'लम्बित';
$string['status_translated'] = 'अनुवादित (समीक्षा प्रतीक्षित)';
$string['status_saved']      = 'सहेजा गया';
$string['status_failed']     = 'विफल';
$string['status_discarded']  = 'खारिज';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']      = 'मॉक मोड — कोई लाइव API कॉल नहीं (वास्तविक अनुवाद हेतु sentientia.translate.live_api = ON करें)';
$string['mode_live_badge']      = 'लाइव API — Anthropic कॉल आपके खाते से बिल होगी';
$string['mode_disabled_badge']  = 'सुविधा अक्षम — Switchboard में sentientia.translate.enabled = ON करें';
$string['mode_no_apikey_badge'] = 'कोई API कुंजी कॉन्फ़िगर नहीं — Site admin में local_sentientia_translate | api_key सेट करें';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']        = 'AI अनुवाद सुविधा अक्षम है। प्रशासक से sentientia.translate.enabled सक्षम करवाएँ।';
$string['err_no_capability']      = 'आपको सामग्री का अनुवाद करने की अनुमति नहीं है।';
$string['err_source_empty']       = 'स्रोत सामग्री आवश्यक है।';
$string['err_source_too_long']    = 'स्रोत सामग्री शब्द-सीमा से अधिक है। सबमिट करने से पहले छोटा करें।';
$string['err_source_contains_pii'] = 'स्रोत सामग्री में PII (Aadhaar या PAN) प्रतीत होता है। हटाकर पुनः सबमिट करें।';
$string['err_unsupported_lang']   = 'असमर्थित लक्षित भाषा। हिन्दी, मराठी, कन्नड़ या स्वाहिली चुनें।';
$string['err_confirm_required']   = 'API को कॉल करने हेतु पुष्टि-चेकबॉक्स पर निशान लगाएँ।';
$string['err_api_key_not_set']    = 'कोई Anthropic API कुंजी कॉन्फ़िगर नहीं है। लाइव कॉल नहीं की जा सकती।';
$string['err_api_failed']         = 'Anthropic API कॉल विफल: {$a}';
$string['err_cost_cap_reached']   = 'आपके कस्टमर खाते ने आज {$a->used} टोकन उपयोग किए हैं (दैनिक सॉफ्ट सीमा {$a->cap} है)। कल पुनः प्रयास करें या प्रशासक से सीमा बढ़वाएँ।';
$string['err_row_not_found']      = 'अनुवाद नहीं मिला अथवा आपको देखने की अनुमति नहीं है।';

// ── Brand overrides page ───────────────────────────────────────────
$string['brands_page_title']     = 'ब्रांड-नाम ओवरराइड';
$string['brands_page_heading']   = 'ब्रांड-नाम ओवरराइड';
$string['brands_intro']          = 'कॉन्फ़िगर करें कि प्रत्येक लक्षित भाषा में ब्रांड-नाम कैसे प्रदर्शित हों। जब किसी ब्रांड के लिए किसी भाषा का ओवरराइड हो, तो अनुवाद में उसकी प्रत्येक उपस्थिति लक्षित-लिपि रूप से प्रतिस्थापित होती है। बिना ओवरराइड वाले ब्रांड यथावत संरक्षित रहते हैं।';
$string['brands_protected_label'] = 'सदैव संरक्षित (ओवरराइड न होने पर यथावत)';
$string['brands_empty']          = 'अभी तक कोई ब्रांड ओवरराइड कॉन्फ़िगर नहीं। नीचे एक जोड़ें।';
$string['brands_add_heading']    = 'एक ब्रांड ओवरराइड जोड़ें';
$string['brand_source']          = 'ब्रांड (अंग्रेज़ी)';
$string['brand_lang']            = 'भाषा';
$string['brand_target']          = 'लक्षित-लिपि रूप';
$string['brand_add']             = 'ओवरराइड जोड़ें';
$string['brand_delete']          = 'हटाएँ';
$string['brand_saved']           = 'ब्रांड ओवरराइड सहेजा गया।';
$string['brand_invalid']         = 'अमान्य ब्रांड ओवरराइड। स्रोत, लक्ष्य और एक समर्थित भाषा तीनों आवश्यक हैं।';
$string['brand_deleted']         = 'ब्रांड ओवरराइड हटाया गया।';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']                = 'Anthropic API';
$string['settings_heading_api_desc']           = 'Anthropic Claude API के लिए क्रेडेन्शियल्स। कुंजी का उपयोग केवल तभी होता है जब sentientia.translate.enabled और sentientia.translate.live_api दोनों ON हों।';
$string['setting_api_key']                     = 'Anthropic API कुंजी';
$string['setting_api_key_desc']                = 'console.anthropic.com से API कुंजी पेस्ट करें। रेस्ट-पर एन्क्रिप्टेड संग्रहीत। इस मान को सोर्स कंट्रोल में कभी कमिट न करें।';
$string['setting_default_model']               = 'डिफ़ॉल्ट मॉडल';
$string['setting_default_model_desc']          = 'जब अनुवादक कोई मॉडल निर्दिष्ट न करे तब उपयोग होने वाला Anthropic मॉडल। अनुशंसित: claude-sonnet-4-6।';
$string['setting_max_output_tokens']           = 'अधिकतम आउटपुट टोकन';
$string['setting_max_output_tokens_desc']      = 'प्रति कॉल Anthropic max_tokens की कठोर सीमा। डिफ़ॉल्ट 8192 (अनुवाद लम्बे हो सकते हैं)।';
$string['settings_heading_limits']             = 'सीमाएँ और कोटा';
$string['settings_heading_limits_desc']        = 'लागत पूर्वानुमेय रखने हेतु प्रति-अनुरोध और प्रति-कस्टमर सीमाएँ।';
$string['setting_max_source_words']            = 'प्रति अनुवाद अधिकतम स्रोत-शब्द';
$string['setting_max_source_words_desc']       = 'इस शब्द-गणना से अधिक होने पर स्रोत-पाठ अस्वीकृत होता है। डिफ़ॉल्ट 4000 — लगभग 8 पृष्ठ।';
$string['setting_daily_cost_cap']              = 'प्रति-कस्टमर दैनिक टोकन सीमा';
$string['setting_daily_cost_cap_desc']         = 'प्रति कस्टमर प्रति दिन अनुवाद द्वारा खर्च किए जाने वाले टोकन (इनपुट + आउटपुट) की सॉफ्ट सीमा। सीमा पार होने पर translate.php मध्यरात्रि तक त्रुटि लौटाता है।';
$string['settings_heading_prompt']             = 'प्रॉम्प्ट कॉन्फ़िगरेशन';
$string['settings_heading_prompt_desc']        = 'प्रॉम्प्ट कोड में संस्करणित हैं (prompt_builder::VERSION)। यह फ़्री-टेक्स्ट फ़ील्ड केवल सूचनात्मक है।';
$string['setting_prompt_template_note']        = 'प्रॉम्प्ट टेम्पलेट टिप्पणी';
$string['setting_prompt_template_note_desc']   = 'वर्तमान प्रॉम्प्ट टेम्पलेट के बारे में फ़्री-फ़ॉर्म टिप्पणी — केवल admin UI में दिखती है। prompt_builder को ओवरराइड नहीं करती।';

// ── Misc ───────────────────────────────────────────────────────────
$string['source_word_count'] = 'शब्द-गणना: {$a}';
$string['tokens_used_today'] = 'आज उपयोग किए टोकन (कस्टमर-व्यापी): {$a->used} / {$a->cap}';

// ── C16 admin landing/queue UI (Bucket C / 2026-05-28) — 2026-08-04 parity closure ──
$string['admin_index_title']     = 'AI अनुवाद';
$string['admin_index_intro']     = 'AI-संचालित सामग्री अनुवाद प्रबंधित करें। नए अनुवाद कार्य सबमिट करें, लम्बित diff की समीक्षा करें, और पिछली गतिविधि का ऑडिट करें।';
$string['admin_index_flag_off_notice'] = 'AI अनुवाद फ़ीचर फ़्लैग (sentientia.translate.enabled) वर्तमान में बंद है। अनुवादों की समीक्षा अभी भी की जा सकती है, लेकिन कोई नई Anthropic कॉल नहीं चलेगी।';
$string['admin_index_queue']     = 'हाल के अनुवाद';
$string['admin_index_empty']     = 'वर्तमान फ़िल्टर से मेल खाता कोई अनुवाद नहीं।';
$string['admin_index_truncated'] = 'सबसे हाल की 25 पंक्तियाँ दिखाई जा रही हैं। और सीमित करने हेतु फ़िल्टर परिष्कृत करें।';
$string['admin_index_quicknav']  = 'त्वरित नेविगेशन';
$string['admin_index_link_translate']      = 'नया अनुवाद';
$string['admin_index_link_translate_desc'] = 'अंग्रेज़ी स्रोत-सामग्री पेस्ट करें और हिन्दी, मराठी, कन्नड़ या स्वाहिली में अनुवाद कार्य चलाएँ।';
$string['admin_index_link_brands']         = 'ब्रांड ओवरराइड मैप';
$string['admin_index_link_brands_desc']    = 'प्रति-कस्टमर ब्रांड-नाम प्रतिस्थापन प्रबंधित करें (उदाहरण: "Airpay" यथावत संरक्षित या लक्षित लिपि में प्रदर्शित)।';
$string['admin_index_link_settings']       = 'अनुवाद सेटिंग्स';
$string['admin_index_link_settings_desc']  = 'Anthropic API कुंजी, डिफ़ॉल्ट मॉडल, प्रति-कस्टमर दैनिक टोकन सीमा और स्रोत-शब्द सीमाएँ।';

$string['stats_total']   = 'कुल अनुवाद';
$string['stats_pending'] = 'लम्बित / समीक्षा प्रतीक्षित';
$string['stats_saved']   = 'सहेजे गए (स्वीकृत)';
$string['stats_failed']  = 'विफल';

$string['filter_status'] = 'स्थिति:';
$string['filter_lang']   = 'लक्षित भाषा:';
$string['filter_all']    = 'सभी';
$string['filter_apply']  = 'लागू करें';
$string['filter_reset']  = 'रीसेट करें';

$string['col_title']   = 'शीर्षक';
$string['col_lang']    = 'लक्ष्य';
$string['col_status']  = 'स्थिति';
$string['col_tokens']  = 'टोकन (इनपुट + आउटपुट)';
$string['col_created'] = 'बनाया गया';
$string['col_actions'] = 'क्रियाएँ';

$string['action_review'] = 'diff की समीक्षा करें';
$string['action_open']   = 'खोलें';
