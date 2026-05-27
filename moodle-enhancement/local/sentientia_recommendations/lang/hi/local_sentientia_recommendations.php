<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI कोर्स अनुशंसाएँ';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_recommendations:view']       = 'डैशबोर्ड पर AI कोर्स अनुशंसाएँ देखें';
$string['sentientia_recommendations:generate']   = 'किसी शिक्षार्थी के लिए AI कोर्स अनुशंसाएँ तैयार करें';
$string['sentientia_recommendations:manage_all'] = 'सभी शिक्षार्थियों के AI अनुशंसा-इतिहास का प्रबंधन करें';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'Sentientia LMS AI अनुशंसा प्लगइन व्यक्तिगत कोर्स अनुशंसाएँ और प्रत्येक के लिए संक्षिप्त तर्क संग्रहीत करता है। जब लाइव API फ़्लैग ON हो, तब एक अनामीकृत शिक्षार्थी प्रोफ़ाइल (भूमिका, टेनेंट, पूर्ण किए गए कोर्स IDs, कौशल टैग) प्रसंस्करण हेतु Anthropic को भेजी जाती है।';
$string['privacy:metadata:rec']               = 'प्रति-शिक्षार्थी AI-निर्मित कोर्स अनुशंसाएँ।';
$string['privacy:metadata:rec:userid']        = 'वह शिक्षार्थी जिसके लिए अनुशंसा है।';
$string['privacy:metadata:rec:courseid']      = 'अनुशंसित कोर्स।';
$string['privacy:metadata:rec:score']         = 'इस अनुशंसा को Claude द्वारा दिया गया विश्वास-स्कोर।';
$string['privacy:metadata:rec:reasoning']     = 'यह कोर्स अगला उपयुक्त चरण क्यों है, इसका संक्षिप्त तर्क।';
$string['privacy:metadata:rec:tokens']        = 'लागत-गणना के लिए Anthropic API टोकन उपयोग।';
$string['privacy:metadata:rec:status']        = 'अनुशंसा का जीवन-चक्र: सक्रिय, खारिज, नामांकित, समाप्त।';
$string['privacy:metadata:rec:generated_at']  = 'अनुशंसा कब तैयार हुई।';
$string['privacy:metadata:rec:timecreated']   = 'अनुशंसा कब बनाई गई।';
$string['privacy:metadata:rec:timemodified']  = 'अनुशंसा अंतिम बार कब संशोधित हुई।';
$string['privacy:metadata:anthropic']                 = 'Anthropic Claude API — जब लाइव API फ़्लैग सक्षम हो, एक अनामीकृत शिक्षार्थी प्रोफ़ाइल अनुशंसा निर्माण के लिए Anthropic को भेजी जाती है। प्रत्येक API कॉल प्रति-कॉल पुष्टि चरण द्वारा सुरक्षित है।';
$string['privacy:metadata:anthropic:profile_role']      = 'शिक्षार्थी भूमिका लेबल (उदा. शिक्षार्थी, प्रबंधक)।';
$string['privacy:metadata:anthropic:profile_completed'] = 'शिक्षार्थी द्वारा पूर्ण किए गए कोर्स IDs।';
$string['privacy:metadata:anthropic:profile_skills']    = 'शिक्षार्थी के लिए वर्तमान में दर्ज कौशल टैग।';
$string['privacy:metadata:anthropic:model']             = 'उपयोग किया गया Anthropic मॉडल पहचानकर्ता।';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_generate'] = 'AI अनुशंसाएँ तैयार करें';

// ── Generate page ──────────────────────────────────────────────────
$string['generate_page_title']       = 'AI कोर्स अनुशंसाएँ तैयार करें';
$string['generate_page_heading']     = 'व्यक्तिगत कोर्स अनुशंसाएँ तैयार करें';
$string['generate_intro']            = 'एक शिक्षार्थी चुनें और Anthropic Claude से व्यक्तिगत कोर्स अनुशंसाओं का बैच माँगें। प्रत्येक अनुशंसा के साथ एक संक्षिप्त तर्क आता है जिसे शिक्षार्थी अपने डैशबोर्ड पर देख सकता है।';
$string['generate_form_targetuser']  = 'लक्षित शिक्षार्थी (यूज़र ID)';
$string['generate_form_targetuser_help'] = 'जिस शिक्षार्थी के लिए यह बैच बनेगा उसकी यूज़र ID। डिफ़ॉल्ट रूप से आपकी अपनी यूज़र ID।';
$string['generate_form_num']         = 'अनुशंसाओं की संख्या';
$string['generate_form_num_help']    = '1 और {$a} के बीच। यदि उम्मीदवार-सूची बहुत छोटी हो तो Claude कम अनुशंसाएँ लौटा सकता है।';
$string['generate_form_model']       = 'मॉडल';
$string['generate_confirm_label']    = 'मैं इस शिक्षार्थी प्रोफ़ाइल के साथ Anthropic को कॉल करने की पुष्टि करता/करती हूँ';
$string['generate_confirm_help']     = 'यह प्रति-कॉल पुष्टि-गेट है। यदि लाइव API फ़्लैग ON हो, तो यह सबमिशन प्रोफ़ाइल + सूची-आकार के अनुसार वास्तविक धन-लागत उत्पन्न करेगा। रद्द करने हेतु निशान हटा दें।';
$string['generate_submit']           = 'अनुशंसाएँ तैयार करें';
$string['generate_cancel']           = 'रद्द करें';
$string['generate_success']          = 'अनुशंसा बैच तैयार हुआ। बैच ID: {$a->batchid}। {$a->mode} मोड में {$a->count} अनुशंसाएँ बनीं। टोकन: {$a->tokens_in} इन / {$a->tokens_out} आउट।';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']      = 'मॉक मोड — कोई लाइव API कॉल नहीं (वास्तविक निर्माण हेतु sentientia.recommendations.live_api = ON करें)';
$string['mode_live_badge']      = 'लाइव API — Anthropic कॉल आपके खाते से बिल होगी';
$string['mode_disabled_badge']  = 'सुविधा अक्षम — Switchboard में sentientia.recommendations.enabled = ON करें';
$string['mode_no_apikey_badge'] = 'कोई API कुंजी कॉन्फ़िगर नहीं — Site admin में local_sentientia_recommendations | api_key सेट करें';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']         = 'AI अनुशंसा सुविधा अक्षम है। प्रशासक से sentientia.recommendations.enabled सक्षम करवाएँ।';
$string['err_no_capability']       = 'आपको AI अनुशंसाएँ तैयार करने की अनुमति नहीं है।';
$string['err_invalid_count']       = 'अनुशंसाओं की संख्या {$a->min} और {$a->max} के बीच होनी चाहिए।';
$string['err_confirm_required']    = 'API को कॉल करने हेतु पुष्टि-चेकबॉक्स पर निशान लगाएँ।';
$string['err_api_key_not_set']     = 'कोई Anthropic API कुंजी कॉन्फ़िगर नहीं है। लाइव कॉल नहीं की जा सकती।';
$string['err_api_failed']          = 'Anthropic API कॉल विफल: {$a}';
$string['err_parser_zero']         = 'Claude ने उत्तर दिया परन्तु कोई उपयोग-योग्य अनुशंसा पार्स नहीं हुई।';
$string['err_cost_cap_reached']    = 'आपके कस्टमर खाते ने आज {$a->used} टोकन उपयोग किए हैं (दैनिक सॉफ्ट सीमा {$a->cap} है)। कल पुनः प्रयास करें या प्रशासक से सीमा बढ़वाएँ।';
$string['err_user_not_found']      = 'लक्षित शिक्षार्थी नहीं मिला अथवा हटाया जा चुका है।';
$string['err_candidates_empty']    = 'इस शिक्षार्थी के लिए कोई उम्मीदवार कोर्स उपलब्ध नहीं है।';
$string['err_profile_invalid']     = 'शिक्षार्थी प्रोफ़ाइल अमान्य है।';
$string['err_profile_contains_pii'] = 'शिक्षार्थी प्रोफ़ाइल में PII पैटर्न (Aadhaar या PAN) है। हटाकर पुनः सबमिट करें।';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']                = 'Anthropic API';
$string['settings_heading_api_desc']           = 'Anthropic Claude API के लिए क्रेडेन्शियल्स। कुंजी का उपयोग केवल तभी होता है जब sentientia.recommendations.enabled और sentientia.recommendations.live_api दोनों ON हों।';
$string['setting_api_key']                     = 'Anthropic API कुंजी';
$string['setting_api_key_desc']                = 'console.anthropic.com से API कुंजी पेस्ट करें। रेस्ट-पर एन्क्रिप्टेड संग्रहीत। इस मान को सोर्स कंट्रोल में कभी कमिट न करें।';
$string['setting_default_model']               = 'डिफ़ॉल्ट मॉडल';
$string['setting_default_model_desc']          = 'जब जनरेटर कोई मॉडल निर्दिष्ट न करे तब उपयोग होने वाला Anthropic मॉडल। अनुशंसित: claude-sonnet-4-6।';
$string['setting_max_output_tokens']           = 'अधिकतम आउटपुट टोकन';
$string['setting_max_output_tokens_desc']      = 'प्रति कॉल Anthropic max_tokens की कठोर सीमा। डिफ़ॉल्ट 2048।';
$string['settings_heading_limits']             = 'सीमाएँ और कोटा';
$string['settings_heading_limits_desc']        = 'लागत पूर्वानुमेय रखने हेतु प्रति-बैच और प्रति-कस्टमर सीमाएँ।';
$string['setting_max_recommendations']         = 'प्रति बैच अधिकतम अनुशंसाएँ';
$string['setting_max_recommendations_desc']    = 'प्रति निर्माण अनुशंसाओं की ऊपरी सीमा। डिफ़ॉल्ट 5।';
$string['setting_max_history_items']           = 'अधिकतम पूर्णता-इतिहास आइटम';
$string['setting_max_history_items_desc']      = 'शिक्षार्थी की कितनी हालिया पूर्णताएँ प्रॉम्प्ट में दी जाएँ। डिफ़ॉल्ट 50।';
$string['setting_daily_cost_cap']              = 'प्रति-कस्टमर दैनिक टोकन सीमा';
$string['setting_daily_cost_cap_desc']         = 'प्रति कस्टमर प्रति दिन अनुशंसा निर्माण द्वारा खर्च किए जाने वाले टोकन (इनपुट + आउटपुट) की सॉफ्ट सीमा। सीमा पार होने पर generate.php मध्यरात्रि तक त्रुटि लौटाता है।';
$string['settings_heading_prompt']             = 'प्रॉम्प्ट कॉन्फ़िगरेशन';
$string['settings_heading_prompt_desc']        = 'प्रॉम्प्ट कोड में संस्करणित हैं (prompt_builder::VERSION)। यह फ़्री-टेक्स्ट फ़ील्ड केवल सूचनात्मक है।';
$string['setting_prompt_template_note']        = 'प्रॉम्प्ट टेम्पलेट टिप्पणी';
$string['setting_prompt_template_note_desc']   = 'वर्तमान प्रॉम्प्ट टेम्पलेट के बारे में फ़्री-फ़ॉर्म टिप्पणी — केवल admin UI में दिखती है। prompt_builder को ओवरराइड नहीं करती।';

// ── Block strings ──────────────────────────────────────────────────
$string['block_title']           = 'आपके लिए अनुशंसित';
$string['block_empty']           = 'अभी आपके लिए कोई अनुशंसा नहीं है। बाद में देखें।';
$string['block_disabled']        = 'AI अनुशंसाएँ वर्तमान में अक्षम हैं।';
$string['block_view_all']        = 'सभी देखें';
$string['block_dismiss']         = 'रुचि नहीं';
$string['block_why']             = 'यह क्यों?';
$string['block_score']           = 'मेल';

// ── Misc ───────────────────────────────────────────────────────────
$string['tokens_used_today']     = 'आज उपयोग किए टोकन (कस्टमर-व्यापी): {$a->used} / {$a->cap}';
$string['recommendation_card']   = 'अनुशंसा #{$a}';
