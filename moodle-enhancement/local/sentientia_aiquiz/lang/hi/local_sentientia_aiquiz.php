<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI क्विज़ निर्माण';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_aiquiz:generate']    = 'AI द्वारा निर्मित क्विज़ प्रश्न तैयार करें';
$string['sentientia_aiquiz:review']      = 'AI द्वारा निर्मित क्विज़ प्रश्नों की समीक्षा करें';
$string['sentientia_aiquiz:manage_all']  = 'सभी स्वामियों के AI क्विज़ ड्राफ्ट प्रबंधित करें';

// ── Privacy strings ────────────────────────────────────────────────
$string['privacy:metadata'] = 'Sentientia LMS AI क्विज़ प्लगइन क्विज़ ड्राफ्ट और उन्हें बनाने के लिए उपयोग किया गया स्रोत-पाठ संग्रहीत करता है। जब लाइव API फ़्लैग ON हो, तब स्रोत-पाठ प्रसंस्करण के लिए Anthropic को भेजा जाता है।';
$string['privacy:metadata:draft']            = 'उपयोगकर्ताओं द्वारा बनाए गए AI क्विज़ ड्राफ्ट।';
$string['privacy:metadata:draft:ownerid']    = 'वह उपयोगकर्ता जिसने ड्राफ्ट बनाया।';
$string['privacy:metadata:draft:sourcetext'] = 'उपयोगकर्ता द्वारा निर्माण के लिए दिया गया वास्तविक स्रोत-पाठ।';
$string['privacy:metadata:draft:title']      = 'उपयोगकर्ता द्वारा दिया गया ड्राफ्ट का शीर्षक।';
$string['privacy:metadata:draft:tokens']     = 'लागत-गणना के लिए Anthropic API टोकन उपयोग।';
$string['privacy:metadata:draft:reviewed_by'] = 'वह उपयोगकर्ता जिसने ड्राफ्ट की समीक्षा की।';
$string['privacy:metadata:draft:reviewed_at'] = 'ड्राफ्ट की समीक्षा कब हुई।';
$string['privacy:metadata:draft:timecreated']  = 'ड्राफ्ट कब बनाया गया।';
$string['privacy:metadata:draft:timemodified'] = 'ड्राफ्ट अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:question']         = 'किसी ड्राफ्ट से सम्बद्ध व्यक्तिगत AI-निर्मित प्रश्न।';
$string['privacy:metadata:question:draftid'] = 'मूल ड्राफ्ट जिससे यह प्रश्न सम्बद्ध है।';
$string['privacy:metadata:question:qtext']   = 'प्रश्न का पाठ।';
$string['privacy:metadata:question:qoptions'] = 'बहुविकल्पीय प्रश्नों के लिए JSON-एन्कोडेड विकल्प।';
$string['privacy:metadata:question:reviewer_note'] = 'प्रश्न पर समीक्षक की टिप्पणी।';
$string['privacy:metadata:question:timecreated']   = 'प्रश्न कब तैयार हुआ।';
$string['privacy:metadata:question:timemodified']  = 'प्रश्न अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:anthropic']             = 'Anthropic Claude API — जब लाइव API फ़्लैग सक्षम हो, स्रोत-पाठ क्विज़ निर्माण के लिए Anthropic को भेजा जाता है। प्रत्येक API कॉल प्रति-कॉल पुष्टि चरण द्वारा सुरक्षित है।';
$string['privacy:metadata:anthropic:sourcetext']  = 'प्रशिक्षक द्वारा दिया गया स्रोत-पाठ।';
$string['privacy:metadata:anthropic:model']       = 'उपयोग किया गया Anthropic मॉडल पहचानकर्ता।';

// ── Navigation ─────────────────────────────────────────────────────
$string['nav_generate'] = 'AI क्विज़ तैयार करें';
$string['nav_review']   = 'AI क्विज़ ड्राफ्ट की समीक्षा करें';

// ── Generate page ──────────────────────────────────────────────────
$string['generate_page_title']    = 'AI क्विज़ तैयार करें';
$string['generate_page_heading']  = 'कोर्स सामग्री से क्विज़ ड्राफ्ट तैयार करें';
$string['generate_intro']         = 'नीचे स्रोत-सामग्री (SCORM ट्रांसक्रिप्ट, नैरेशन पाठ, SOP अंश) पेस्ट करें। Sentientia LMS इसे Anthropic Claude को भेजकर क्विज़ ड्राफ्ट तैयार करता है। किसी भी प्रश्न को कोर्स क्विज़ में भेजने से पहले प्रत्येक ड्राफ्ट की मानवीय समीक्षा अनिवार्य है।';
$string['generate_form_title']    = 'ड्राफ्ट का शीर्षक';
$string['generate_form_title_help'] = 'एक छोटा लेबल ताकि आप बाद में समीक्षा-सूची में यह ड्राफ्ट ढूँढ़ सकें।';
$string['generate_form_course']   = 'कोर्स';
$string['generate_form_course_help'] = 'परिणामी क्विज़ किस कोर्स से सम्बद्ध होगा। समीक्षा के समय निर्णय टालने के लिए "साइट-व्यापी / अब तक अनसौंपा" चुनें।';
$string['generate_form_course_none'] = 'साइट-व्यापी / अब तक अनसौंपा';
$string['generate_form_source']   = 'स्रोत सामग्री';
$string['generate_form_source_help'] = 'अधिकतम {$a} शब्द। पेस्ट करने से पहले किसी भी PII (कर्मचारी नाम, ID, ग्राहक डेटा) को हटा दें।';
$string['generate_form_num']      = 'अनुरोधित प्रश्नों की संख्या';
$string['generate_form_num_help'] = '1 और {$a} के बीच। यदि स्रोत बहुत छोटा हो तो Claude कम प्रश्न लौटा सकता है।';
$string['generate_form_model']    = 'मॉडल';
$string['generate_form_model_help'] = 'Anthropic मॉडल पहचानकर्ता। डिफ़ॉल्ट: claude-sonnet-4-6।';
$string['generate_confirm_label'] = 'मैं इस सामग्री के साथ Anthropic को कॉल करने की पुष्टि करता/करती हूँ';
$string['generate_confirm_help']  = 'यह प्रति-कॉल पुष्टि-गेट है। यदि लाइव API फ़्लैग ON हो, तो यह सबमिशन स्रोत-लम्बाई के अनुसार वास्तविक धन-लागत उत्पन्न करेगा। रद्द करने हेतु निशान हटा दें।';
$string['generate_submit']        = 'क्विज़ ड्राफ्ट तैयार करें';
$string['generate_cancel']        = 'रद्द करें';

// ── Result / status badges ─────────────────────────────────────────
$string['mode_mock_badge']        = 'मॉक मोड — कोई लाइव API कॉल नहीं (वास्तविक निर्माण हेतु sentientia.aiquiz.live_api = ON करें)';
$string['mode_live_badge']        = 'लाइव API — Anthropic कॉल आपके खाते से बिल होगी';
$string['mode_disabled_badge']    = 'सुविधा अक्षम — Switchboard में sentientia.aiquiz.enabled = ON करें';
$string['mode_no_apikey_badge']   = 'कोई API कुंजी कॉन्फ़िगर नहीं — Site admin में local_sentientia_aiquiz | api_key सेट करें';

// ── Errors ─────────────────────────────────────────────────────────
$string['err_feature_off']       = 'AI क्विज़ सुविधा अक्षम है। प्रशासक से sentientia.aiquiz.enabled सक्षम करवाएँ।';
$string['err_no_capability']     = 'आपको AI क्विज़ ड्राफ्ट तैयार करने की अनुमति नहीं है।';
$string['err_source_empty']      = 'स्रोत सामग्री आवश्यक है।';
$string['err_source_too_long']   = 'स्रोत सामग्री शब्द-सीमा से अधिक है। सबमिट करने से पहले छोटा करें।';
$string['err_source_contains_pii'] = 'स्रोत सामग्री में PII (Aadhaar या PAN) प्रतीत होता है। हटाकर पुनः सबमिट करें।';
$string['err_invalid_count']     = 'प्रश्न संख्या {$a->min} और {$a->max} के बीच होनी चाहिए।';
$string['err_token_cap_reached'] = 'आपके खाते ने आज {$a->used} टोकन उपयोग किए हैं (दैनिक सॉफ्ट सीमा {$a->cap} है)। कल पुनः प्रयास करें या प्रशासक से सीमा बढ़वाएँ।';
$string['err_confirm_required']  = 'API को कॉल करने हेतु पुष्टि-चेकबॉक्स पर निशान लगाएँ।';
$string['err_api_key_not_set']   = 'कोई Anthropic API कुंजी कॉन्फ़िगर नहीं है। लाइव कॉल नहीं की जा सकती।';
$string['err_api_failed']        = 'Anthropic API कॉल विफल: {$a}';
$string['err_parser_zero']       = 'Claude ने उत्तर दिया परन्तु कोई उपयोग-योग्य प्रश्न पार्स नहीं हुआ। लम्बा या स्पष्ट स्रोत आज़माएँ।';

// ── Review page ────────────────────────────────────────────────────
$string['review_page_title']     = 'AI क्विज़ ड्राफ्ट की समीक्षा करें';
$string['review_page_heading']   = 'ड्राफ्ट #{$a} की समीक्षा करें';
$string['review_intro']          = 'नीचे प्रत्येक तैयार प्रश्न की समीक्षा करें। स्वीकृत, सम्पादित या अस्वीकृत करें — केवल स्वीकृत या सम्पादित प्रश्न ही कोर्स क्विज़ में भेजे जा सकते हैं।';
$string['review_no_draft']       = 'ड्राफ्ट नहीं मिला अथवा आपको देखने की अनुमति नहीं है।';
$string['review_meta_owner']     = 'स्वामी';
$string['review_meta_course']    = 'कोर्स';
$string['review_meta_model']     = 'मॉडल';
$string['review_meta_prompt']    = 'प्रॉम्प्ट संस्करण';
$string['review_meta_tokens']    = 'उपयोग किए गए टोकन';
$string['review_meta_generated_at'] = 'निर्माण समय';
$string['review_meta_mode']      = 'निर्माण मोड';
$string['review_status']         = 'स्थिति';
$string['review_status_pending']   = 'निर्माण लम्बित';
$string['review_status_generated'] = 'समीक्षा प्रतीक्षित';
$string['review_status_approved']  = 'स्वीकृत (पुश के लिए तैयार)';
$string['review_status_pushed']    = 'क्विज़ #{$a} में पुश किया गया';
$string['review_status_rejected']  = 'अस्वीकृत';
$string['review_status_failed']    = 'निर्माण विफल';
$string['review_question_label'] = 'प्रश्न {$a}';
$string['review_question_answer'] = 'सही उत्तर';
$string['review_question_explanation'] = 'व्याख्या';
$string['review_action_approve'] = 'स्वीकृत करें';
$string['review_action_edit']    = 'सम्पादित करें';
$string['review_action_reject']  = 'अस्वीकृत करें';
$string['review_action_save_edit'] = 'सम्पादन सहेजें';
$string['review_action_cancel_edit'] = 'सम्पादन रद्द करें';
$string['review_q_status_generated'] = 'अब तक समीक्षित नहीं';
$string['review_q_status_approved']  = 'स्वीकृत';
$string['review_q_status_edited']    = 'सम्पादित';
$string['review_q_status_rejected']  = 'अस्वीकृत';
$string['review_finalise']       = 'समीक्षा अंतिम करें';
$string['review_finalise_help']  = 'इस ड्राफ्ट को पूर्ण-समीक्षित चिन्हित करता है। यदि कम-से-कम एक प्रश्न स्वीकृत या सम्पादित हो तो स्थिति "स्वीकृत" अन्यथा "अस्वीकृत" हो जाती है।';
$string['review_push_to_quiz']   = 'स्वीकृत प्रश्नों को कोर्स क्विज़ में पुश करें';
$string['review_push_disabled']  = 'mod_quiz में पुश sentientia.aiquiz.auto_push (डिफ़ॉल्ट OFF) द्वारा सुरक्षित है।';
$string['review_push_success']   = 'स्वीकृत प्रश्न क्विज़ #{$a->quizid} में पुश हुए ({$a->count} प्रश्न)।';
$string['review_no_questions']   = 'इस ड्राफ्ट के लिए कोई प्रश्न नहीं बने।';
$string['review_empty_state']    = 'आपका अभी तक कोई AI क्विज़ ड्राफ्ट नहीं है। एक बनाने हेतु Generate पृष्ठ का उपयोग करें।';

// ── Settings strings ───────────────────────────────────────────────
$string['settings_heading_api']           = 'Anthropic API';
$string['settings_heading_api_desc']      = 'Anthropic Claude API के लिए क्रेडेन्शियल्स। कुंजी का उपयोग केवल तभी होता है जब sentientia.aiquiz.enabled और sentientia.aiquiz.live_api दोनों ON हों।';
$string['setting_api_key']                = 'Anthropic API कुंजी';
$string['setting_api_key_desc']           = 'console.anthropic.com से API कुंजी पेस्ट करें। Moodle configpasswordunmask द्वारा रेस्ट-पर एन्क्रिप्टेड संग्रहीत। इस मान को सोर्स कंट्रोल में कभी कमिट न करें।';
$string['setting_default_model']          = 'डिफ़ॉल्ट मॉडल';
$string['setting_default_model_desc']     = 'जब जनरेटर कोई मॉडल निर्दिष्ट न करे तब उपयोग होने वाला Anthropic मॉडल। अनुशंसित: claude-sonnet-4-6।';
$string['settings_heading_limits']        = 'सीमाएँ और कोटा';
$string['settings_heading_limits_desc']   = 'लागत पूर्वानुमेय रखने हेतु प्रति-उपयोगकर्ता और प्रति-अनुरोध सीमाएँ।';
$string['setting_max_questions']          = 'प्रति अनुरोध अधिकतम प्रश्न';
$string['setting_max_questions_desc']     = 'प्रति निर्माण प्रश्नों की ऊपरी सीमा। डिफ़ॉल्ट 10।';
$string['setting_daily_token_cap']        = 'प्रति उपयोगकर्ता दैनिक टोकन सीमा';
$string['setting_daily_token_cap_desc']   = 'एकल उपयोगकर्ता प्रति दिन (इनपुट + आउटपुट) कितने टोकन खर्च कर सकता है उसकी सॉफ्ट सीमा। सीमा पार होने पर generate.php मध्यरात्रि तक त्रुटि लौटाता है।';
$string['setting_max_source_words']       = 'प्रति ड्राफ्ट अधिकतम स्रोत-शब्द';
$string['setting_max_source_words_desc']  = 'इस शब्द-गणना से अधिक होने पर स्रोत-पाठ अस्वीकृत होता है। डिफ़ॉल्ट 4000 — लगभग 8 पृष्ठ।';

// ── Misc ───────────────────────────────────────────────────────────
$string['source_word_count'] = 'शब्द-गणना: {$a}';
$string['tokens_used_today'] = 'आज उपयोग किए टोकन: {$a->used} / {$a->cap}';
$string['back_to_drafts']    = 'ड्राफ्ट सूची पर वापस';
$string['drafts_list_title'] = 'आपके AI क्विज़ ड्राफ्ट';
