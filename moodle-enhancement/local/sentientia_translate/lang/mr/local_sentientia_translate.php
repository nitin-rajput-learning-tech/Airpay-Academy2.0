<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Marathi strings for local_sentientia_translate.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_translate
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI कंटेंट भाषांतर';
$string['sentientia_translate:translate'] = 'Anthropic Claude वापरून कंटेंटचे भाषांतर करा';
$string['sentientia_translate:manage_brands'] = 'प्रत्येक कस्टमरसाठी ब्रँड-नाव ओव्हरराइड्स मॅनेज करा';
$string['sentientia_translate:manage_all'] = 'सर्व मालकांची भाषांतर हिस्ट्री मॅनेज करा';
$string['privacy:metadata'] = 'Sentientia LMS AI भाषांतर प्लगइन मूळ मजकूर आणि त्याची भाषांतरे साठवते. लाइव्ह API फ्लॅग ON असताना, मूळ मजकूर प्रोसेसिंगसाठी Anthropic कडे पाठवला जातो.';
$string['privacy:metadata:tr'] = 'युजर्सनी तयार केलेली कंटेंट भाषांतरे.';
$string['privacy:metadata:tr:ownerid'] = 'भाषांतर तयार करणारा युजर.';
$string['privacy:metadata:tr:sourcetext'] = 'भाषांतरासाठी दिलेला मूळ मजकूर, जसाच्या तसा.';
$string['privacy:metadata:tr:translatedtext'] = 'भाषांतराचे आउटपुट.';
$string['privacy:metadata:tr:targetlang'] = 'टार्गेट भाषेचा कोड.';
$string['privacy:metadata:tr:title'] = 'युजरने दिलेले भाषांतराचे शीर्षक.';
$string['privacy:metadata:tr:tokens'] = 'खर्चाच्या हिशोबासाठी Anthropic API टोकन वापर.';
$string['privacy:metadata:tr:timecreated'] = 'भाषांतर कधी तयार केले गेले.';
$string['privacy:metadata:tr:timemodified'] = 'भाषांतरात शेवटचा बदल कधी केला गेला.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — लाइव्ह API फ्लॅग चालू असताना, मूळ मजकूर भाषांतरासाठी Anthropic कडे पाठवला जातो. प्रत्येक API कॉलपूर्वी कन्फर्मेशनची पायरी असते.';
$string['privacy:metadata:anthropic:sourcetext'] = 'भाषांतर करायचा मूळ मजकूर.';
$string['privacy:metadata:anthropic:targetlang'] = 'मागितलेल्या टार्गेट भाषेचा कोड.';
$string['privacy:metadata:anthropic:model'] = 'वापरलेला Anthropic मॉडेल आयडेंटिफायर.';
$string['nav_translate'] = 'कंटेंटचे भाषांतर करा';
$string['lang_hi'] = 'हिंदी (हिन्दी)';
$string['lang_mr'] = 'मराठी (मराठी)';
$string['lang_kn'] = 'कन्नड (ಕನ್ನಡ)';
$string['lang_sw'] = 'स्वाहिली (Kiswahili)';
$string['translate_page_title'] = 'कंटेंटचे भाषांतर करा';
$string['translate_page_heading'] = 'AI वापरून कोर्स कंटेंटचे भाषांतर करा';
$string['translate_intro'] = 'इंग्रजी मूळ कंटेंट पेस्ट करा, टार्गेट भाषा निवडा, आणि Anthropic Claude त्या भाषेच्या लिपीत भाषांतर तयार करेल. तुमच्या कस्टमर ओव्हरराइड्सनुसार ब्रँड नावे जपली जातात. सेव्ह करण्यापूर्वी शेजारी-शेजारी दिसणारा डिफ रिव्ह्यू करा.';
$string['form_title'] = 'भाषांतराचे शीर्षक';
$string['form_targetlang'] = 'टार्गेट भाषा';
$string['form_source'] = 'मूळ कंटेंट (इंग्रजी)';
$string['form_source_help'] = 'जास्तीत जास्त {$a} शब्द. पेस्ट करण्यापूर्वी कोणतीही PII (कर्मचाऱ्यांची नावे, ID, कस्टमर डेटा) काढून टाका.';
$string['form_model'] = 'मॉडेल';
$string['form_confirm_label'] = 'मला या कंटेंटसह Anthropic ला कॉल करायचा आहे, हे मी कन्फर्म करत आहे';
$string['form_confirm_help'] = 'हे प्रत्येक कॉलसाठीचे कन्फर्मेशन गेट आहे. लाइव्ह API फ्लॅग ON असताना, या सबमिशनसाठी मूळ मजकुराच्या लांबीनुसार प्रत्यक्ष पैसे खर्च होतील. रद्द करण्यासाठी टिक काढा.';
$string['form_submit'] = 'भाषांतर करा';
$string['form_cancel'] = 'रद्द करा';
$string['diff_heading'] = 'भाषांतर रिव्ह्यू करा';
$string['diff_meta'] = 'टार्गेट भाषा: {$a->lang} | लागू केलेले ब्रँड बदल: {$a->brands} | मोड: {$a->mode}';
$string['diff_source'] = 'मूळ (इंग्रजी)';
$string['diff_translation'] = 'भाषांतर';
$string['action_save'] = 'भाषांतर सेव्ह करा';
$string['action_discard'] = 'टाकून द्या';
$string['back_to_translate'] = 'भाषांतराकडे परत जा';
$string['saved_notice'] = 'भाषांतर सेव्ह झाले.';
$string['discarded_notice'] = 'भाषांतर टाकून दिले.';
$string['status_label'] = 'स्टेटस';
$string['status_pending'] = 'प्रलंबित';
$string['status_translated'] = 'भाषांतर झाले (रिव्ह्यू प्रलंबित)';
$string['status_saved'] = 'सेव्ह झाले';
$string['status_failed'] = 'अयशस्वी';
$string['status_discarded'] = 'टाकून दिले';
$string['mode_mock_badge'] = 'मॉक मोड — लाइव्ह API कॉल नाही (खऱ्या भाषांतरासाठी sentientia.translate.live_api = ON सेट करा)';
$string['mode_live_badge'] = 'लाइव्ह API — Anthropic कॉलचे बिल तुमच्या अकाउंटवर लागते';
$string['mode_disabled_badge'] = 'फीचर बंद आहे — Switchboard मध्ये sentientia.translate.enabled = ON सेट करा';
$string['mode_no_apikey_badge'] = 'API की कॉन्फिगर केलेली नाही — साइट ॲडमिनमध्ये local_sentientia_translate | api_key सेट करा';
$string['err_feature_off'] = 'AI भाषांतर फीचर बंद आहे. sentientia.translate.enabled सुरू करण्यासाठी ॲडमिनला सांगा.';
$string['err_no_capability'] = 'तुम्हाला कंटेंटचे भाषांतर करण्याची परवानगी नाही.';
$string['err_source_empty'] = 'मूळ कंटेंट आवश्यक आहे.';
$string['err_source_too_long'] = 'मूळ कंटेंट ठरवलेल्या शब्दमर्यादेपेक्षा जास्त आहे. सबमिट करण्यापूर्वी तो कमी करा.';
$string['err_source_contains_pii'] = 'मूळ कंटेंटमध्ये PII (आधार किंवा PAN नंबर) असल्याचे दिसते. ते काढून पुन्हा सबमिट करा.';
$string['err_unsupported_lang'] = 'ही टार्गेट भाषा सपोर्टेड नाही. हिंदी, मराठी, कन्नड किंवा स्वाहिली निवडा.';
$string['err_confirm_required'] = 'API ला कॉल करण्यासाठी कन्फर्मेशन चेकबॉक्सवर टिक करणे आवश्यक आहे.';
$string['err_api_key_not_set'] = 'कोणतीही Anthropic API की कॉन्फिगर केलेली नाही. लाइव्ह कॉल करता येणार नाही.';
$string['err_api_failed'] = 'Anthropic API कॉल अयशस्वी झाला: {$a}';
$string['err_cost_cap_reached'] = 'तुमच्या कस्टमर अकाउंटने आज {$a->used} टोकन वापरले आहेत (रोजची सॉफ्ट मर्यादा {$a->cap} आहे). उद्या पुन्हा प्रयत्न करा किंवा मर्यादा वाढवण्यासाठी ॲडमिनला सांगा.';
$string['err_row_not_found'] = 'भाषांतर सापडले नाही किंवा ते पाहण्याची तुम्हाला परवानगी नाही.';
$string['brands_page_title'] = 'ब्रँड-नाव ओव्हरराइड्स';
$string['brands_page_heading'] = 'ब्रँड-नाव ओव्हरराइड्स';
$string['brands_intro'] = 'प्रत्येक टार्गेट भाषेत ब्रँड नावे कशी दिसावीत ते कॉन्फिगर करा. एखाद्या ब्रँडसाठी एखाद्या भाषेचा ओव्हरराइड असेल, तर भाषांतरात तो जिथे जिथे येईल तिथे टार्गेट लिपीतील रूपाने बदलला जातो. ओव्हरराइड नसलेले ब्रँड जसेच्या तसे ठेवले जातात.';
$string['brands_protected_label'] = 'नेहमी जपले जातात (ओव्हरराइड नसल्यास जसेच्या तसे)';
$string['brands_empty'] = 'अजून कोणतेही ब्रँड ओव्हरराइड कॉन्फिगर केलेले नाहीत. खाली एक जोडा.';
$string['brands_add_heading'] = 'ब्रँड ओव्हरराइड जोडा';
$string['brand_source'] = 'ब्रँड (इंग्रजी)';
$string['brand_lang'] = 'भाषा';
$string['brand_target'] = 'टार्गेट लिपीतील रूप';
$string['brand_add'] = 'ओव्हरराइड जोडा';
$string['brand_delete'] = 'डिलीट करा';
$string['brand_saved'] = 'ब्रँड ओव्हरराइड सेव्ह झाला.';
$string['brand_invalid'] = 'ब्रँड ओव्हरराइड चुकीचा आहे. मूळ, टार्गेट आणि सपोर्टेड भाषा — तिन्ही आवश्यक आहेत.';
$string['brand_deleted'] = 'ब्रँड ओव्हरराइड डिलीट झाला.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic Claude API साठी क्रेडेन्शियल्स. sentientia.translate.enabled आणि sentientia.translate.live_api दोन्ही ON असतानाच ही की वापरली जाते.';
$string['setting_api_key'] = 'Anthropic API की';
$string['setting_api_key_desc'] = 'console.anthropic.com वरून API की पेस्ट करा. ती एन्क्रिप्ट करून साठवली जाते. ही व्हॅल्यू कधीही सोर्स कंट्रोलमध्ये कमिट करू नका.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'भाषांतर करणाऱ्याने मॉडेल सांगितले नसल्यास वापरले जाणारे Anthropic मॉडेल. शिफारस: claude-sonnet-4-6.';
$string['setting_max_output_tokens'] = 'जास्तीत जास्त आउटपुट टोकन';
$string['setting_max_output_tokens_desc'] = 'प्रत्येक कॉलसाठी Anthropic max_tokens वर कडक मर्यादा. डीफॉल्ट 8192 (भाषांतरे मोठी असू शकतात).';
$string['settings_heading_limits'] = 'मर्यादा आणि कोटा';
$string['settings_heading_limits_desc'] = 'खर्च अंदाजात राहावा यासाठी प्रत्येक रिक्वेस्ट आणि प्रत्येक कस्टमरसाठी मर्यादा.';
$string['setting_max_source_words'] = 'प्रत्येक भाषांतरासाठी जास्तीत जास्त मूळ शब्द';
$string['setting_max_source_words_desc'] = 'मूळ मजकूर या शब्दसंख्येपेक्षा जास्त असल्यास नाकारला जातो. डीफॉल्ट 4000 — साधारण 8 पाने.';
$string['setting_daily_cost_cap'] = 'प्रत्येक कस्टमरसाठी रोजची टोकन मर्यादा';
$string['setting_daily_cost_cap_desc'] = 'प्रत्येक कस्टमरने दररोज भाषांतरासाठी वापरलेल्या टोकनवरील (इनपुट + आउटपुट) सॉफ्ट मर्यादा. मर्यादा ओलांडल्यावर, मध्यरात्रीपर्यंत translate.php एरर देते.';
$string['settings_heading_prompt'] = 'प्रॉम्प्ट कॉन्फिगरेशन';
$string['settings_heading_prompt_desc'] = 'प्रॉम्प्टचे व्हर्जन कोडमध्येच ठेवले जातात (prompt_builder::VERSION). हे फ्री-टेक्स्ट फील्ड फक्त माहितीसाठी आहे.';
$string['setting_prompt_template_note'] = 'प्रॉम्प्ट टेम्पलेट नोट';
$string['setting_prompt_template_note_desc'] = 'सध्याच्या प्रॉम्प्ट टेम्पलेटबद्दल मोकळ्या नोट्स — फक्त ॲडमिन UI मध्ये दिसतात. हे prompt_builder ला ओव्हरराइड करत नाही.';
$string['source_word_count'] = 'शब्दसंख्या: {$a}';
$string['tokens_used_today'] = 'आज वापरलेले टोकन (संपूर्ण कस्टमर): {$a->used} / {$a->cap}';
$string['admin_index_title'] = 'AI भाषांतर';
$string['admin_index_intro'] = 'AI-आधारित कंटेंट भाषांतरे मॅनेज करा. नवीन भाषांतर जॉब सबमिट करा, प्रलंबित डिफ रिव्ह्यू करा आणि मागील ॲक्टिव्हिटीचे ऑडिट करा.';
$string['admin_index_flag_off_notice'] = 'AI भाषांतर फीचर फ्लॅग (sentientia.translate.enabled) सध्या OFF आहे. भाषांतरे अजूनही रिव्ह्यू करता येतील, पण नवीन Anthropic कॉल चालणार नाहीत.';
$string['admin_index_queue'] = 'अलीकडची भाषांतरे';
$string['admin_index_empty'] = 'सध्याच्या फिल्टर्सशी जुळणारे कोणतेही भाषांतर नाही.';
$string['admin_index_truncated'] = 'सर्वात अलीकडच्या 25 रो दाखवत आहोत. आणखी कमी करण्यासाठी फिल्टर्स बदला.';
$string['admin_index_quicknav'] = 'क्विक नेव्हिगेशन';
$string['admin_index_link_translate'] = 'नवीन भाषांतर';
$string['admin_index_link_translate_desc'] = 'इंग्रजी मूळ कंटेंट पेस्ट करा आणि हिंदी, मराठी, कन्नड किंवा स्वाहिलीमध्ये भाषांतर जॉब चालवा.';
$string['admin_index_link_brands'] = 'ब्रँड ओव्हरराइड मॅप';
$string['admin_index_link_brands_desc'] = 'प्रत्येक कस्टमरसाठी ब्रँड-नाव बदल मॅनेज करा (उदा. "Airpay" जसेच्या तसे ठेवणे किंवा टार्गेट लिपीत दाखवणे).';
$string['admin_index_link_settings'] = 'भाषांतर सेटिंग्ज';
$string['admin_index_link_settings_desc'] = 'Anthropic API की, डीफॉल्ट मॉडेल, प्रत्येक कस्टमरसाठी रोजची टोकन मर्यादा आणि मूळ शब्दांची मर्यादा.';
$string['stats_total'] = 'एकूण भाषांतरे';
$string['stats_pending'] = 'प्रलंबित / रिव्ह्यूच्या प्रतीक्षेत';
$string['stats_saved'] = 'सेव्ह केलेले (स्वीकारलेले)';
$string['stats_failed'] = 'अयशस्वी';
$string['filter_status'] = 'स्टेटस:';
$string['filter_lang'] = 'टार्गेट भाषा:';
$string['filter_all'] = 'सर्व';
$string['filter_apply'] = 'लागू करा';
$string['filter_reset'] = 'रीसेट करा';
$string['col_title'] = 'शीर्षक';
$string['col_lang'] = 'टार्गेट';
$string['col_status'] = 'स्टेटस';
$string['col_tokens'] = 'टोकन (इन + आउट)';
$string['col_created'] = 'तयार केल्याची तारीख';
$string['col_actions'] = 'ॲक्शन्स';
$string['action_review'] = 'डिफ रिव्ह्यू करा';
$string['action_open'] = 'उघडा';
