<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi language strings — Sentientia LMS GenAI Authoring Studio.
 * 100% parity with lang/en. Technical proper nouns (Anthropic, ElevenLabs,
 * API, PDF, JSON, mrq, match, PII, Aadhaar, PAN, token) stay in Latin script.
 *
 * @package local_sentientia_authoring
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia GenAI ऑथरिंग स्टूडियो';

// Capabilities.
$string['sentientia_authoring:generate'] = 'ऑथरिंग स्टूडियो में कोर्स ड्राफ्ट तैयार करें';
$string['sentientia_authoring:review'] = 'तैयार ड्राफ्ट की समीक्षा, सम्पादन एवं अंतिम रूप दें';
$string['sentientia_authoring:managetemplates'] = 'अनुदेशात्मक-डिज़ाइन टेम्पलेट बनाएँ एवं सम्पादित करें';
$string['sentientia_authoring:manage_all'] = 'सभी स्वामियों एवं tenants के समस्त ड्राफ्ट एवं टेम्पलेट प्रबंधित करें';

// Navigation.
$string['nav_studio'] = 'ऑथरिंग स्टूडियो';
$string['nav_templates'] = 'डिज़ाइन टेम्पलेट';

// Mode badges.
$string['mode_disabled_badge'] = 'ऑथरिंग स्टूडियो अक्षम है — sentientia.authoring.enabled feature flag चालू करें।';
$string['mode_mock_badge'] = 'MOCK MODE — जनरेशन नियतात्मक placeholder सामग्री लौटाता है। कोई Anthropic कॉल नहीं की जाती और कोई शुल्क नहीं लगता।';
$string['mode_no_apikey_badge'] = 'Live API flag चालू है परन्तु कोई Anthropic API key विन्यस्त नहीं है — key सेट होने तक जनरेशन mock mode में चलेगा।';
$string['mode_live_badge'] = 'LIVE MODE — जनरेशन Anthropic को कॉल करेगा। प्रत्येक run प्रति token शुल्क योग्य है।';

// Index page.
$string['index_page_title'] = 'ऑथरिंग स्टूडियो';
$string['index_page_heading'] = 'ऑथरिंग स्टूडियो — मेरे कोर्स ड्राफ्ट';
$string['index_empty'] = 'अभी आपके पास कोई कोर्स ड्राफ्ट नहीं है। अपना पहला microlearning मॉड्यूल तैयार करने हेतु स्टूडियो खोलें।';
$string['index_col_title'] = 'शीर्षक';
$string['index_col_status'] = 'स्थिति';
$string['index_col_cards'] = 'कार्ड';
$string['index_col_questions'] = 'प्रश्न';
$string['index_col_created'] = 'निर्मित';
$string['index_col_actions'] = 'क्रियाएँ';
$string['index_review_link'] = 'समीक्षा';

// Studio (generate) page.
$string['studio_page_title'] = 'ऑथरिंग स्टूडियो — जनरेट';
$string['studio_page_heading'] = 'एक microlearning मॉड्यूल तैयार करें';
$string['studio_intro'] = 'एक prompt, चिपकाए गए दस्तावेज़, या निकाले गए PDF पाठ को एक पूर्ण microlearning मॉड्यूल में बदलें — इंटरैक्टिव कार्ड एवं मिश्रित-प्रकार का मूल्यांकन। प्रकाशन से पूर्व प्रत्येक ड्राफ्ट मानव-समीक्षा द्वार से गुज़रता है।';
$string['studio_form_title'] = 'मॉड्यूल शीर्षक';
$string['studio_form_template'] = 'अनुदेशात्मक-डिज़ाइन टेम्पलेट';
$string['studio_form_template_help'] = 'वैकल्पिक। संरचना तय करने हेतु टेम्पलेट चुनें, या freeform छोड़ें।';
$string['studio_form_template_none'] = 'Freeform (कोई टेम्पलेट नहीं)';
$string['studio_form_sourcetype'] = 'स्रोत प्रकार';
$string['studio_form_language'] = 'आउटपुट भाषा';
$string['studio_form_language_help'] = 'अंग्रेज़ी एवं हिन्दी के अतिरिक्त भाषाएँ अंग्रेज़ी में तैयार की जाती हैं और उपलब्ध होने पर translation plugin द्वारा स्थानीयकृत की जाती हैं।';
$string['studio_form_source'] = 'स्रोत सामग्री';
$string['studio_form_source_help'] = 'अपना prompt, दस्तावेज़ पाठ, या PDF extract यहाँ चिपकाएँ (अधिकतम {$a} शब्द)। कर्मचारी PII न चिपकाएँ।';
$string['studio_form_numcards'] = 'कार्डों की संख्या';
$string['studio_form_numq'] = 'प्रश्नों की संख्या';
$string['studio_form_mastery'] = 'दक्षता अंक (%)';
$string['studio_form_model'] = 'Model';
$string['studio_confirm_label'] = 'मैं इस जनरेशन को चलाने की पुष्टि करता/करती हूँ। (live mode में यह Anthropic को कॉल करता है और प्रति token शुल्क योग्य है।)';
$string['studio_confirm_help'] = 'यह [CONFIRM] द्वार प्रत्येक जनरेशन हेतु अनिवार्य है, mock-mode runs सहित, ताकि कार्यप्रवाह दोनों modes में समान रहे।';
$string['studio_submit'] = 'ड्राफ्ट तैयार करें';

$string['sourcetype_prompt'] = 'Prompt';
$string['sourcetype_doc'] = 'दस्तावेज़ पाठ';
$string['sourcetype_pdf'] = 'PDF extract';
$string['language_en'] = 'अंग्रेज़ी';
$string['language_hi'] = 'हिन्दी';
$string['template_builtin_suffix'] = '(अंतर्निहित)';

$string['tokens_used_today'] = 'आज प्रयुक्त tokens: {$a->cap} में से {$a->used}।';
$string['source_word_count'] = '{$a} शब्द';

// Review page.
$string['review_page_title'] = 'ऑथरिंग स्टूडियो — समीक्षा';
$string['review_page_heading'] = 'कोर्स ड्राफ्ट की समीक्षा करें';
$string['review_meta'] = 'स्थिति: {$a->status} · Mode: {$a->mode} · {$a->cards} कार्ड · {$a->questions} प्रश्न · दक्षता: {$a->mastery}%';
$string['review_gate_notice'] = 'मानव-समीक्षा द्वार: अंतिम रूप देने से पूर्व कम-से-कम एक कार्ड स्वीकृत या सम्पादित करें। यहाँ तैयार कोई भी वस्तु स्वतः प्रकाशित नहीं होती।';
$string['review_failed'] = 'जनरेशन विफल: {$a}';
$string['review_back_to_studio'] = 'स्टूडियो पर वापस जाएँ';
$string['review_cards_heading'] = 'इंटरैक्टिव कार्ड';
$string['review_questions_heading'] = 'मूल्यांकन प्रश्न';
$string['review_flip_back'] = 'Flip पिछला:';
$string['review_narration'] = 'Narration:';
$string['review_correct_mark'] = 'सही';
$string['review_feedback_correct'] = 'प्रतिक्रिया (सही):';
$string['review_feedback_incorrect'] = 'प्रतिक्रिया (गलत):';
$string['review_note_placeholder'] = 'समीक्षक टिप्पणी (वैकल्पिक)';
$string['review_btn_approved'] = 'स्वीकृत करें';
$string['review_btn_edited'] = 'सम्पादित चिह्नित करें';
$string['review_btn_rejected'] = 'अस्वीकृत करें';
$string['review_finalise'] = 'समीक्षा अंतिम रूप दें';
$string['review_finalised_approved'] = 'समीक्षा अंतिम — ड्राफ्ट स्वीकृत। अब इसे प्रकाशित या voiced किया जा सकता है।';
$string['review_finalised_rejected'] = 'समीक्षा अंतिम — ड्राफ्ट अस्वीकृत (कोई कार्ड स्वीकृत नहीं)।';
$string['review_voiceover_link'] = 'इस कार्ड का voiceover करें';

$string['item_status_generated'] = 'समीक्षा प्रतीक्षित';
$string['item_status_approved'] = 'स्वीकृत';
$string['item_status_edited'] = 'सम्पादित';
$string['item_status_rejected'] = 'अस्वीकृत';

// Templates page.
$string['templates_page_title'] = 'ऑथरिंग स्टूडियो — डिज़ाइन टेम्पलेट';
$string['templates_page_heading'] = 'अनुदेशात्मक-डिज़ाइन टेम्पलेट';
$string['templates_new_button'] = 'नया टेम्पलेट';
$string['templates_new_heading'] = 'नया टेम्पलेट';
$string['templates_edit_heading'] = 'टेम्पलेट सम्पादित करें';
$string['templates_empty'] = 'अभी कोई टेम्पलेट नहीं। स्टूडियो किसी मॉड्यूल की संरचना कैसे करे — यह तय करने हेतु एक बनाएँ।';
$string['templates_col_name'] = 'नाम';
$string['templates_col_description'] = 'विवरण';
$string['templates_col_actions'] = 'क्रियाएँ';
$string['templates_form_name'] = 'टेम्पलेट नाम';
$string['templates_form_description'] = 'संक्षिप्त विवरण';
$string['templates_form_body'] = 'टेम्पलेट body';
$string['templates_form_body_help'] = 'वह संरचना एवं शैली वर्णित करें जिसका जनरेटर अनुसरण करे (कार्ड अनुक्रम, प्रश्न-मिश्रण, रजिस्टर)।';
$string['templates_archive'] = 'संग्रहित करें';
$string['templates_saved'] = 'टेम्पलेट सहेजा गया।';
$string['templates_created'] = 'टेम्पलेट बनाया गया।';
$string['templates_archived'] = 'टेम्पलेट संग्रहित किया गया।';

// Voiceover page.
$string['voiceover_page_title'] = 'ऑथरिंग स्टूडियो — voiceover';
$string['voiceover_page_heading'] = 'TTS voiceover तैयार करें';
$string['voiceover_mode_mock'] = 'MOCK MODE — एक नियतात्मक placeholder तैयार होता है। कोई ElevenLabs कॉल नहीं की जाती और कोई शुल्क नहीं लगता।';
$string['voiceover_mode_live'] = 'LIVE MODE — यह ElevenLabs को कॉल करेगा। प्रति अक्षर शुल्क योग्य।';
$string['voiceover_cost_estimate'] = 'Narration लम्बाई: {$a->chars} अक्षर। अनुमानित live लागत: ${$a->cost}।';
$string['voiceover_confirm_label'] = 'मैं इस voiceover को तैयार करने की पुष्टि करता/करती हूँ। (live mode में यह ElevenLabs को कॉल करता है और प्रति अक्षर शुल्क योग्य है।)';
$string['voiceover_confirm_help'] = 'यह [CONFIRM] द्वार प्रत्येक voiceover हेतु अनिवार्य है, mock-mode runs सहित।';
$string['voiceover_submit'] = 'Voiceover तैयार करें';
$string['voiceover_no_narration'] = 'इस कार्ड में voice करने हेतु कोई narration script नहीं है।';
$string['voiceover_done_mock'] = 'Mock voiceover दर्ज — कोई ElevenLabs कॉल नहीं की गई।';
$string['voiceover_done_live'] = 'Voiceover तैयार हुआ।';
$string['voiceover_failed'] = 'Voiceover विफल: {$a}';

// Localization strategy labels.
$string['localize_native'] = 'लक्ष्य भाषा में सीधे तैयार किया गया।';
$string['localize_translate'] = 'अंग्रेज़ी में तैयार, फिर translation plugin द्वारा स्थानीयकृत।';
$string['localize_degraded'] = 'Translation plugin अनुपलब्ध — आउटपुट अंग्रेज़ी में ही रहता है।';

// Settings.
$string['settings_heading_ai'] = 'AI जनरेशन (Anthropic)';
$string['settings_heading_ai_desc'] = 'Live कोर्स जनरेशन हेतु credentials। तब तक निष्क्रिय जब तक live_api feature flag चालू न हो एवं [CONFIRM] द्वार पारित न हो। डिफ़ॉल्ट flags OFF होने पर स्टूडियो mock mode में चलता है और ये कभी नहीं पढ़े जाते।';
$string['setting_anthropic_api_key'] = 'Anthropic API key';
$string['setting_anthropic_api_key_desc'] = 'सुरक्षित रूप से संग्रहित। कभी log नहीं किया जाता। केवल live, [CONFIRM]-gated जनरेशन पर पढ़ा जाता है।';
$string['setting_default_model'] = 'डिफ़ॉल्ट model';
$string['setting_default_model_desc'] = 'जनरेशन हेतु प्रयुक्त Anthropic model पहचानकर्ता (जैसे claude-sonnet-4-6)।';
$string['settings_heading_tts'] = 'TTS voiceover (ElevenLabs)';
$string['settings_heading_tts_desc'] = 'Live TTS हेतु credentials। तब तक निष्क्रिय जब तक tts एवं live_api दोनों flags चालू न हों एवं [CONFIRM] द्वार पारित न हो।';
$string['setting_elevenlabs_api_key'] = 'ElevenLabs API key';
$string['setting_elevenlabs_api_key_desc'] = 'सुरक्षित रूप से संग्रहित। कभी log नहीं किया जाता। केवल live, [CONFIRM]-gated voiceover पर पढ़ा जाता है।';
$string['setting_elevenlabs_voice_id'] = 'ElevenLabs voice ID';
$string['setting_elevenlabs_voice_id_desc'] = 'Live TTS हेतु प्रयुक्त voice। Mock mode में अनदेखा।';
$string['settings_heading_limits'] = 'सीमाएँ';
$string['settings_heading_limits_desc'] = 'प्रति-अनुरोध एवं प्रति-दिन सीमाएँ जो जनरेशन लागत एवं मॉड्यूल आकार को बाँधती हैं।';
$string['setting_max_cards'] = 'प्रति मॉड्यूल अधिकतम कार्ड';
$string['setting_max_cards_desc'] = 'एकल जनरेशन में अनुरोधित कार्डों की संख्या की ऊपरी सीमा।';
$string['setting_max_questions'] = 'प्रति मॉड्यूल अधिकतम प्रश्न';
$string['setting_max_questions_desc'] = 'एकल जनरेशन में अनुरोधित प्रश्नों की संख्या की ऊपरी सीमा।';
$string['setting_max_source_words'] = 'अधिकतम स्रोत शब्द';
$string['setting_max_source_words_desc'] = 'इतने शब्दों से लम्बी स्रोत सामग्री अस्वीकृत करें।';
$string['setting_daily_token_cap'] = 'दैनिक token सीमा (प्रति उपयोगकर्ता)';
$string['setting_daily_token_cap_desc'] = 'किसी उपयोगकर्ता द्वारा आज इतने tokens उपभोग करने पर आगे जनरेशन रोकें।';
$string['setting_default_mastery_score'] = 'डिफ़ॉल्ट दक्षता अंक (%)';
$string['setting_default_mastery_score_desc'] = 'तैयार मूल्यांकन हेतु डिफ़ॉल्ट उत्तीर्ण सीमा (CLAUDE.md §8 डिफ़ॉल्ट 70)।';

// Errors.
$string['err_feature_off'] = 'ऑथरिंग स्टूडियो सक्षम नहीं है।';
$string['err_tts_off'] = 'TTS voiceover सक्षम नहीं है।';
$string['err_source_empty'] = 'स्रोत सामग्री रिक्त नहीं हो सकती।';
$string['err_source_too_long'] = 'स्रोत सामग्री शब्द-सीमा से अधिक है।';
$string['err_source_contains_pii'] = 'स्रोत सामग्री में PII (Aadhaar या PAN) प्रतीत होता है। जनरेट करने से पूर्व हटाएँ।';
$string['err_invalid_cards'] = 'कार्डों की संख्या {$a->min} से {$a->max} के बीच होनी चाहिए।';
$string['err_invalid_questions'] = 'प्रश्नों की संख्या {$a->min} से {$a->max} के बीच होनी चाहिए।';
$string['err_invalid_mastery'] = 'दक्षता अंक 0 से 100 के बीच होना चाहिए।';
$string['err_confirm_required'] = 'आगे बढ़ने से पूर्व पुष्टि checkbox पर निशान लगाना अनिवार्य है।';
$string['err_token_cap_reached'] = 'दैनिक token सीमा पूर्ण ({$a->cap} में से {$a->used})। कल पुनः प्रयास करें।';
$string['err_api_failed'] = 'जनरेशन कॉल विफल: {$a}';
$string['err_template_not_found'] = 'टेम्पलेट नहीं मिला या पहुँच-योग्य नहीं।';
$string['err_template_builtin'] = 'अंतर्निहित टेम्पलेट संग्रहित या हटाए नहीं जा सकते।';
$string['err_draft_not_found'] = 'ड्राफ्ट नहीं मिला या पहुँच-योग्य नहीं।';
$string['err_card_not_found'] = 'इस ड्राफ्ट में कार्ड नहीं मिला।';
$string['err_publish_not_approved'] = 'प्रकाशन से पूर्व किसी ड्राफ्ट की समीक्षा एवं स्वीकृति आवश्यक है।';

// Privacy.
$string['privacy:path:drafts'] = 'कोर्स ड्राफ्ट';
$string['privacy:path:templates'] = 'डिज़ाइन टेम्पलेट';
$string['privacy:metadata:template'] = 'उपयोगकर्ता द्वारा बनाए गए अनुदेशात्मक-डिज़ाइन टेम्पलेट।';
$string['privacy:metadata:template:ownerid'] = 'वह उपयोगकर्ता जिसने टेम्पलेट रचा।';
$string['privacy:metadata:template:name'] = 'टेम्पलेट नाम।';
$string['privacy:metadata:template:body'] = 'टेम्पलेट body सामग्री।';
$string['privacy:metadata:template:timecreated'] = 'टेम्पलेट कब बनाया गया।';
$string['privacy:metadata:template:timemodified'] = 'टेम्पलेट अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:draft'] = 'उपयोगकर्ता द्वारा बनाए गए कोर्स-जनरेशन ड्राफ्ट।';
$string['privacy:metadata:draft:ownerid'] = 'वह उपयोगकर्ता जिसने ड्राफ्ट बनाया।';
$string['privacy:metadata:draft:title'] = 'ड्राफ्ट शीर्षक।';
$string['privacy:metadata:draft:sourcetext'] = 'जनरेशन हेतु प्रस्तुत स्रोत सामग्री।';
$string['privacy:metadata:draft:model'] = 'प्रयुक्त AI model पहचानकर्ता।';
$string['privacy:metadata:draft:tokens'] = 'लागत-ट्रैकिंग हेतु दर्ज token गणनाएँ।';
$string['privacy:metadata:draft:reviewed_by'] = 'वह उपयोगकर्ता जिसने ड्राफ्ट की समीक्षा की।';
$string['privacy:metadata:draft:reviewed_at'] = 'समीक्षा कब पूर्ण हुई।';
$string['privacy:metadata:draft:timecreated'] = 'ड्राफ्ट कब बनाया गया।';
$string['privacy:metadata:draft:timemodified'] = 'ड्राफ्ट अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:card'] = 'किसी ड्राफ्ट हेतु तैयार इंटरैक्टिव कार्ड।';
$string['privacy:metadata:card:draftid'] = 'मूल ड्राफ्ट।';
$string['privacy:metadata:card:body'] = 'कार्ड सामग्री।';
$string['privacy:metadata:card:reviewer_note'] = 'कार्ड पर समीक्षक टिप्पणी।';
$string['privacy:metadata:card:timecreated'] = 'कार्ड कब बनाया गया।';
$string['privacy:metadata:card:timemodified'] = 'कार्ड अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:question'] = 'किसी ड्राफ्ट हेतु तैयार मूल्यांकन प्रश्न।';
$string['privacy:metadata:question:draftid'] = 'मूल ड्राफ्ट।';
$string['privacy:metadata:question:qtext'] = 'प्रश्न का stem।';
$string['privacy:metadata:question:qoptions'] = 'प्रश्न के विकल्प या pairs।';
$string['privacy:metadata:question:reviewer_note'] = 'प्रश्न पर समीक्षक टिप्पणी।';
$string['privacy:metadata:question:timecreated'] = 'प्रश्न कब बनाया गया।';
$string['privacy:metadata:question:timemodified'] = 'प्रश्न अंतिम बार कब संशोधित हुआ।';
$string['privacy:metadata:anthropic'] = 'स्रोत सामग्री कोर्स जनरेशन हेतु Anthropic को भेजी जा सकती है, परन्तु केवल तब जब live_api feature flag चालू हो (यह डिफ़ॉल्ट रूप से OFF है)।';
$string['privacy:metadata:anthropic:sourcetext'] = 'जनरेशन हेतु भेजी गई स्रोत सामग्री।';
$string['privacy:metadata:anthropic:model'] = 'अनुरोध द्वारा लक्षित model।';
$string['privacy:metadata:elevenlabs'] = 'Narration पाठ voiceover हेतु ElevenLabs को भेजा जा सकता है, परन्तु केवल तब जब live_api feature flag चालू हो (यह डिफ़ॉल्ट रूप से OFF है)।';
$string['privacy:metadata:elevenlabs:narration'] = 'संश्लेषण हेतु भेजा गया narration पाठ।';
