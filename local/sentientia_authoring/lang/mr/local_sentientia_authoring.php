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
 * Marathi strings for local_sentientia_authoring.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_authoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia GenAI ऑथरिंग स्टुडिओ';
$string['sentientia_authoring:generate'] = 'ऑथरिंग स्टुडिओमध्ये कोर्स ड्राफ्ट जनरेट करा';
$string['sentientia_authoring:review'] = 'जनरेट झालेले ड्राफ्ट रिव्ह्यू करा, एडिट करा आणि फायनल करा';
$string['sentientia_authoring:managetemplates'] = 'इन्स्ट्रक्शनल-डिझाइन टेम्पलेट तयार करा आणि एडिट करा';
$string['sentientia_authoring:manage_all'] = 'सर्व मालक आणि टेनंट्समधील सर्व ड्राफ्ट आणि टेम्पलेट मॅनेज करा';
$string['nav_studio'] = 'ऑथरिंग स्टुडिओ';
$string['nav_templates'] = 'डिझाइन टेम्पलेट';
$string['mode_disabled_badge'] = 'ऑथरिंग स्टुडिओ बंद आहे — sentientia.authoring.enabled फीचर फ्लॅग चालू करा.';
$string['mode_mock_badge'] = 'मॉक मोड — जनरेशन ठरलेला प्लेसहोल्डर कंटेंट देते. कोणताही Anthropic कॉल होत नाही आणि कोणतेही शुल्क लागत नाही.';
$string['mode_no_apikey_badge'] = 'लाइव्ह API फ्लॅग ON आहे पण कोणतीही Anthropic API की कॉन्फिगर केलेली नाही — की सेट होईपर्यंत जनरेशन मॉक मोडमध्ये चालेल.';
$string['mode_live_badge'] = 'लाइव्ह मोड — जनरेशन Anthropic ला कॉल करेल. प्रत्येक रनचे बिल टोकननुसार लागते.';
$string['index_page_title'] = 'ऑथरिंग स्टुडिओ';
$string['index_page_heading'] = 'ऑथरिंग स्टुडिओ — माझे कोर्स ड्राफ्ट';
$string['index_empty'] = 'तुमचे अजून कोणतेही कोर्स ड्राफ्ट नाहीत. तुमचा पहिला मायक्रोलर्निंग मॉड्यूल जनरेट करण्यासाठी स्टुडिओ उघडा.';
$string['index_col_title'] = 'शीर्षक';
$string['index_col_status'] = 'स्टेटस';
$string['index_col_cards'] = 'कार्ड्स';
$string['index_col_questions'] = 'प्रश्न';
$string['index_col_created'] = 'तयार केल्याची तारीख';
$string['index_col_actions'] = 'ॲक्शन्स';
$string['index_review_link'] = 'रिव्ह्यू करा';
$string['studio_page_title'] = 'ऑथरिंग स्टुडिओ — जनरेट';
$string['studio_page_heading'] = 'मायक्रोलर्निंग मॉड्यूल जनरेट करा';
$string['studio_intro'] = 'एखादा प्रॉम्प्ट, पेस्ट केलेले डॉक्युमेंट किंवा PDF मधून काढलेला मजकूर यांचे पूर्ण मायक्रोलर्निंग मॉड्यूलमध्ये रूपांतर करा — इंटरॲक्टिव्ह कार्ड्स आणि त्यासोबत वेगवेगळ्या प्रकारच्या प्रश्नांचे असेसमेंट. प्रत्येक ड्राफ्ट पब्लिश होण्यापूर्वी ह्यूमन-रिव्ह्यू गेटमधून जातो.';
$string['studio_form_title'] = 'मॉड्यूलचे शीर्षक';
$string['studio_form_template'] = 'इन्स्ट्रक्शनल-डिझाइन टेम्पलेट';
$string['studio_form_template_help'] = 'ऐच्छिक. रचना ठरवण्यासाठी एखादे टेम्पलेट निवडा, किंवा फ्रीफॉर्म ठेवा.';
$string['studio_form_template_none'] = 'फ्रीफॉर्म (टेम्पलेट नाही)';
$string['studio_form_sourcetype'] = 'सोर्सचा प्रकार';
$string['studio_form_language'] = 'आउटपुट भाषा';
$string['studio_form_language_help'] = 'इंग्रजी आणि हिंदी व्यतिरिक्त इतर भाषांमधील आउटपुट आधी इंग्रजीत तयार केले जाते आणि उपलब्ध असल्यास भाषांतर प्लगइनद्वारे लोकलाइज केले जाते.';
$string['studio_form_source'] = 'मूळ कंटेंट';
$string['studio_form_source_help'] = 'तुमचा प्रॉम्प्ट, डॉक्युमेंटचा मजकूर किंवा PDF एक्सट्रॅक्ट इथे पेस्ट करा (जास्तीत जास्त {$a} शब्द). कर्मचाऱ्यांची PII पेस्ट करू नका.';
$string['studio_form_numcards'] = 'कार्ड्सची संख्या';
$string['studio_form_numq'] = 'प्रश्नांची संख्या';
$string['studio_form_mastery'] = 'मास्टरी स्कोअर (%)';
$string['studio_form_model'] = 'मॉडेल';
$string['studio_confirm_label'] = 'मला हे जनरेशन रन करायचे आहे, हे मी कन्फर्म करत आहे. (लाइव्ह मोडमध्ये हे Anthropic ला कॉल करते आणि टोकननुसार बिल लागते.)';
$string['studio_confirm_help'] = 'मॉक-मोड रनसह प्रत्येक जनरेशनसाठी हा [CONFIRM] गेट आवश्यक आहे, त्यामुळे दोन्ही मोडमध्ये वर्कफ्लो सारखाच राहतो.';
$string['studio_submit'] = 'ड्राफ्ट जनरेट करा';
$string['sourcetype_prompt'] = 'प्रॉम्प्ट';
$string['sourcetype_doc'] = 'डॉक्युमेंटचा मजकूर';
$string['sourcetype_pdf'] = 'PDF एक्सट्रॅक्ट';
$string['language_en'] = 'इंग्रजी';
$string['language_hi'] = 'हिंदी (हिन्दी)';
$string['template_builtin_suffix'] = '(बिल्ट-इन)';
$string['tokens_used_today'] = 'आज वापरलेले टोकन: {$a->cap} पैकी {$a->used}.';
$string['source_word_count'] = '{$a} शब्द';
$string['review_page_title'] = 'ऑथरिंग स्टुडिओ — रिव्ह्यू';
$string['review_page_heading'] = 'कोर्स ड्राफ्ट रिव्ह्यू करा';
$string['review_meta'] = 'स्टेटस: {$a->status} · मोड: {$a->mode} · {$a->cards} कार्ड्स · {$a->questions} प्रश्न · मास्टरी: {$a->mastery}%';
$string['review_gate_notice'] = 'ह्यूमन-रिव्ह्यू गेट: रिव्ह्यू पूर्ण करण्यापूर्वी किमान एक कार्ड मंजूर किंवा एडिट करा. इथे जनरेट झालेले काहीही आपोआप पब्लिश होत नाही.';
$string['review_failed'] = 'जनरेशन अयशस्वी: {$a}';
$string['review_back_to_studio'] = 'स्टुडिओकडे परत जा';
$string['review_cards_heading'] = 'इंटरॲक्टिव्ह कार्ड्स';
$string['review_questions_heading'] = 'असेसमेंट प्रश्न';
$string['review_flip_back'] = 'मागची बाजू:';
$string['review_narration'] = 'नरेशन:';
$string['review_correct_mark'] = 'बरोबर';
$string['review_feedback_correct'] = 'फीडबॅक (बरोबर):';
$string['review_feedback_incorrect'] = 'फीडबॅक (चुकीचे):';
$string['review_note_placeholder'] = 'रिव्ह्यूअरची टीप (ऐच्छिक)';
$string['review_btn_approved'] = 'मंजूर करा';
$string['review_btn_edited'] = 'एडिट केलेले म्हणून मार्क करा';
$string['review_btn_rejected'] = 'नाकारा';
$string['review_finalise'] = 'रिव्ह्यू पूर्ण करा';
$string['review_finalised_approved'] = 'रिव्ह्यू पूर्ण झाला — ड्राफ्ट मंजूर झाला. आता तो पब्लिश करता येईल किंवा त्याला व्हॉइसओव्हर देता येईल.';
$string['review_finalised_rejected'] = 'रिव्ह्यू पूर्ण झाला — ड्राफ्ट नाकारला (एकही कार्ड मंजूर झाले नाही).';
$string['review_voiceover_link'] = 'या कार्डला व्हॉइसओव्हर द्या';
$string['item_status_generated'] = 'रिव्ह्यूच्या प्रतीक्षेत';
$string['item_status_approved'] = 'मंजूर';
$string['item_status_edited'] = 'एडिट केलेले';
$string['item_status_rejected'] = 'नाकारले';
$string['templates_page_title'] = 'ऑथरिंग स्टुडिओ — डिझाइन टेम्पलेट';
$string['templates_page_heading'] = 'इन्स्ट्रक्शनल-डिझाइन टेम्पलेट';
$string['templates_new_button'] = 'नवीन टेम्पलेट';
$string['templates_new_heading'] = 'नवीन टेम्पलेट';
$string['templates_edit_heading'] = 'टेम्पलेट एडिट करा';
$string['templates_empty'] = 'अजून कोणतेही टेम्पलेट नाहीत. स्टुडिओ मॉड्यूलची रचना कशी करेल हे ठरवण्यासाठी एक टेम्पलेट तयार करा.';
$string['templates_col_name'] = 'नाव';
$string['templates_col_description'] = 'वर्णन';
$string['templates_col_actions'] = 'ॲक्शन्स';
$string['templates_form_name'] = 'टेम्पलेटचे नाव';
$string['templates_form_description'] = 'थोडक्यात वर्णन';
$string['templates_form_body'] = 'टेम्पलेट बॉडी';
$string['templates_form_body_help'] = 'जनरेटरने कोणती रचना आणि टोन पाळावा ते लिहा (कार्ड्सचा क्रम, प्रश्नांचे मिश्रण, भाषेची शैली).';
$string['templates_archive'] = 'आर्काइव्ह करा';
$string['templates_saved'] = 'टेम्पलेट सेव्ह झाले.';
$string['templates_created'] = 'टेम्पलेट तयार झाले.';
$string['templates_archived'] = 'टेम्पलेट आर्काइव्ह झाले.';
$string['voiceover_page_title'] = 'ऑथरिंग स्टुडिओ — व्हॉइसओव्हर';
$string['voiceover_page_heading'] = 'TTS व्हॉइसओव्हर जनरेट करा';
$string['voiceover_mode_mock'] = 'मॉक मोड — ठरलेला प्लेसहोल्डर तयार होतो. कोणताही ElevenLabs कॉल होत नाही आणि कोणतेही शुल्क लागत नाही.';
$string['voiceover_mode_live'] = 'लाइव्ह मोड — हे ElevenLabs ला कॉल करेल. प्रत्येक कॅरेक्टरनुसार शुल्क लागते.';
$string['voiceover_cost_estimate'] = 'नरेशनची लांबी: {$a->chars} कॅरेक्टर्स. अंदाजे लाइव्ह खर्च: ${$a->cost}.';
$string['voiceover_confirm_label'] = 'मला हा व्हॉइसओव्हर जनरेट करायचा आहे, हे मी कन्फर्म करत आहे. (लाइव्ह मोडमध्ये हे ElevenLabs ला कॉल करते आणि प्रत्येक कॅरेक्टरनुसार बिल लागते.)';
$string['voiceover_confirm_help'] = 'मॉक-मोड रनसह प्रत्येक व्हॉइसओव्हरसाठी हा [CONFIRM] गेट आवश्यक आहे.';
$string['voiceover_submit'] = 'व्हॉइसओव्हर जनरेट करा';
$string['voiceover_no_narration'] = 'या कार्डमध्ये व्हॉइस करण्यासाठी कोणतीही नरेशन स्क्रिप्ट नाही.';
$string['voiceover_done_mock'] = 'मॉक व्हॉइसओव्हर नोंदवला — कोणताही ElevenLabs कॉल झाला नाही.';
$string['voiceover_done_live'] = 'व्हॉइसओव्हर जनरेट झाला.';
$string['voiceover_failed'] = 'व्हॉइसओव्हर अयशस्वी: {$a}';
$string['localize_native'] = 'थेट टार्गेट भाषेतच जनरेट केले.';
$string['localize_translate'] = 'आधी इंग्रजीत जनरेट केले, नंतर भाषांतर प्लगइनद्वारे लोकलाइज केले.';
$string['localize_degraded'] = 'भाषांतर प्लगइन उपलब्ध नाही — आउटपुट इंग्रजीतच राहील.';
$string['settings_heading_ai'] = 'AI जनरेशन (Anthropic)';
$string['settings_heading_ai_desc'] = 'लाइव्ह कोर्स जनरेशनसाठी क्रेडेन्शियल्स. live_api फीचर फ्लॅग ON असल्याशिवाय आणि [CONFIRM] गेट पार केल्याशिवाय हे वापरले जात नाहीत. डीफॉल्ट फ्लॅग OFF असताना स्टुडिओ मॉक मोडमध्ये चालतो आणि हे कधीही वाचले जात नाहीत.';
$string['setting_anthropic_api_key'] = 'Anthropic API की';
$string['setting_anthropic_api_key_desc'] = 'सुरक्षितपणे सेव्ह केली जाते. कधीही लॉग होत नाही. फक्त लाइव्ह, [CONFIRM]-गेटेड जनरेशनमध्येच वाचली जाते.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'जनरेशनसाठी वापरला जाणारा Anthropic मॉडेल आयडेंटिफायर (उदा. claude-sonnet-4-6).';
$string['settings_heading_tts'] = 'TTS व्हॉइसओव्हर (ElevenLabs)';
$string['settings_heading_tts_desc'] = 'लाइव्ह TTS साठी क्रेडेन्शियल्स. tts आणि live_api हे दोन्ही फ्लॅग ON असल्याशिवाय आणि [CONFIRM] गेट पार केल्याशिवाय हे वापरले जात नाहीत.';
$string['setting_elevenlabs_api_key'] = 'ElevenLabs API की';
$string['setting_elevenlabs_api_key_desc'] = 'सुरक्षितपणे सेव्ह केली जाते. कधीही लॉग होत नाही. फक्त लाइव्ह, [CONFIRM]-गेटेड व्हॉइसओव्हरमध्येच वाचली जाते.';
$string['setting_elevenlabs_voice_id'] = 'ElevenLabs व्हॉइस ID';
$string['setting_elevenlabs_voice_id_desc'] = 'लाइव्ह TTS साठी वापरला जाणारा व्हॉइस. मॉक मोडमध्ये याकडे दुर्लक्ष केले जाते.';
$string['settings_heading_limits'] = 'मर्यादा';
$string['settings_heading_limits_desc'] = 'जनरेशनचा खर्च आणि मॉड्यूलचा आकार मर्यादित ठेवणाऱ्या प्रति-रिक्वेस्ट आणि प्रति-दिवस कमाल मर्यादा.';
$string['setting_max_cards'] = 'प्रति मॉड्यूल कमाल कार्ड्स';
$string['setting_max_cards_desc'] = 'एका जनरेशनमध्ये मागता येणाऱ्या कार्ड्सची कमाल संख्या.';
$string['setting_max_questions'] = 'प्रति मॉड्यूल कमाल प्रश्न';
$string['setting_max_questions_desc'] = 'एका जनरेशनमध्ये मागता येणाऱ्या प्रश्नांची कमाल संख्या.';
$string['setting_max_source_words'] = 'कमाल मूळ शब्द';
$string['setting_max_source_words_desc'] = 'यापेक्षा जास्त शब्दांचा मूळ कंटेंट नाकारा.';
$string['setting_daily_token_cap'] = 'रोजची टोकन मर्यादा (प्रति युजर)';
$string['setting_daily_token_cap_desc'] = 'युजरने आज इतके टोकन वापरल्यानंतर पुढील जनरेशन ब्लॉक करा.';
$string['setting_default_mastery_score'] = 'डीफॉल्ट मास्टरी स्कोअर (%)';
$string['setting_default_mastery_score_desc'] = 'जनरेट केलेल्या असेसमेंटसाठी पास होण्याचा डीफॉल्ट किमान स्कोअर (CLAUDE.md §8 डीफॉल्ट 70).';
$string['err_feature_off'] = 'ऑथरिंग स्टुडिओ चालू नाही.';
$string['err_tts_off'] = 'TTS व्हॉइसओव्हर चालू नाही.';
$string['err_source_empty'] = 'मूळ कंटेंट रिकामा असू शकत नाही.';
$string['err_source_too_long'] = 'मूळ कंटेंटने शब्द मर्यादा ओलांडली आहे.';
$string['err_source_contains_pii'] = 'मूळ कंटेंटमध्ये PII (आधार किंवा PAN) असल्याचे दिसते. जनरेट करण्यापूर्वी ती काढून टाका.';
$string['err_invalid_cards'] = 'कार्ड्सची संख्या {$a->min} ते {$a->max} दरम्यान असली पाहिजे.';
$string['err_invalid_questions'] = 'प्रश्नांची संख्या {$a->min} ते {$a->max} दरम्यान असली पाहिजे.';
$string['err_invalid_mastery'] = 'मास्टरी स्कोअर 0 ते 100 दरम्यान असला पाहिजे.';
$string['err_confirm_required'] = 'पुढे जाण्यापूर्वी कन्फर्मेशन चेकबॉक्सवर टिक करणे आवश्यक आहे.';
$string['err_token_cap_reached'] = 'रोजची टोकन मर्यादा गाठली ({$a->cap} पैकी {$a->used}). उद्या पुन्हा प्रयत्न करा.';
$string['err_api_failed'] = 'जनरेशन कॉल अयशस्वी: {$a}';
$string['err_template_not_found'] = 'टेम्पलेट सापडले नाही किंवा तुम्हाला त्याचा ॲक्सेस नाही.';
$string['err_template_builtin'] = 'बिल्ट-इन टेम्पलेट आर्काइव्ह किंवा डिलीट करता येत नाहीत.';
$string['err_template_readonly'] = 'तुम्ही हे टेम्पलेट वापरू शकता पण बदलू शकत नाही. बिल्ट-इन टेम्पलेट सर्व टेनंट्स शेअर करतात, त्यामुळे फक्त प्लॅटफॉर्म ॲडमिनच ते एडिट करू शकतो.';
$string['err_draft_not_found'] = 'ड्राफ्ट सापडला नाही किंवा तुम्हाला त्याचा ॲक्सेस नाही.';
$string['err_card_not_found'] = 'या ड्राफ्टमध्ये कार्ड सापडले नाही.';
$string['err_publish_not_approved'] = 'ड्राफ्ट पब्लिश करण्यापूर्वी त्याचा रिव्ह्यू होऊन तो मंजूर झाला पाहिजे.';
$string['privacy:path:drafts'] = 'कोर्स ड्राफ्ट';
$string['privacy:path:templates'] = 'डिझाइन टेम्पलेट';
$string['privacy:metadata:template'] = 'युजरने तयार केलेले इन्स्ट्रक्शनल-डिझाइन टेम्पलेट.';
$string['privacy:metadata:template:ownerid'] = 'टेम्पलेट तयार करणारा युजर.';
$string['privacy:metadata:template:name'] = 'टेम्पलेटचे नाव.';
$string['privacy:metadata:template:body'] = 'टेम्पलेट बॉडीमधील कंटेंट.';
$string['privacy:metadata:template:timecreated'] = 'टेम्पलेट कधी तयार केले.';
$string['privacy:metadata:template:timemodified'] = 'टेम्पलेट शेवटचे कधी बदलले.';
$string['privacy:metadata:draft'] = 'युजरने तयार केलेले कोर्स-जनरेशन ड्राफ्ट.';
$string['privacy:metadata:draft:ownerid'] = 'ड्राफ्ट तयार करणारा युजर.';
$string['privacy:metadata:draft:title'] = 'ड्राफ्टचे शीर्षक.';
$string['privacy:metadata:draft:sourcetext'] = 'जनरेशनसाठी सबमिट केलेला मूळ कंटेंट.';
$string['privacy:metadata:draft:model'] = 'वापरलेला AI मॉडेल आयडेंटिफायर.';
$string['privacy:metadata:draft:tokens'] = 'खर्च ट्रॅक करण्यासाठी नोंदवलेली टोकन संख्या.';
$string['privacy:metadata:draft:reviewed_by'] = 'ड्राफ्ट रिव्ह्यू करणारा युजर.';
$string['privacy:metadata:draft:reviewed_at'] = 'रिव्ह्यू कधी पूर्ण झाला.';
$string['privacy:metadata:draft:timecreated'] = 'ड्राफ्ट कधी तयार केला.';
$string['privacy:metadata:draft:timemodified'] = 'ड्राफ्ट शेवटचा कधी बदलला.';
$string['privacy:metadata:card'] = 'ड्राफ्टसाठी जनरेट केलेली इंटरॲक्टिव्ह कार्ड्स.';
$string['privacy:metadata:card:draftid'] = 'मुख्य ड्राफ्ट.';
$string['privacy:metadata:card:body'] = 'कार्डमधील कंटेंट.';
$string['privacy:metadata:card:reviewer_note'] = 'कार्डवरील रिव्ह्यूअरची टीप.';
$string['privacy:metadata:card:timecreated'] = 'कार्ड कधी तयार केले.';
$string['privacy:metadata:card:timemodified'] = 'कार्ड शेवटचे कधी बदलले.';
$string['privacy:metadata:question'] = 'ड्राफ्टसाठी जनरेट केलेले असेसमेंट प्रश्न.';
$string['privacy:metadata:question:draftid'] = 'मुख्य ड्राफ्ट.';
$string['privacy:metadata:question:qtext'] = 'प्रश्नाचा मुख्य मजकूर.';
$string['privacy:metadata:question:qoptions'] = 'प्रश्नाचे पर्याय किंवा जोड्या.';
$string['privacy:metadata:question:reviewer_note'] = 'प्रश्नावरील रिव्ह्यूअरची टीप.';
$string['privacy:metadata:question:timecreated'] = 'प्रश्न कधी तयार केला.';
$string['privacy:metadata:question:timemodified'] = 'प्रश्न शेवटचा कधी बदलला.';
$string['privacy:metadata:anthropic'] = 'कोर्स जनरेशनसाठी मूळ कंटेंट Anthropic ला पाठवला जाऊ शकतो, पण फक्त live_api फीचर फ्लॅग ON असतानाच (डीफॉल्टनुसार तो OFF असतो).';
$string['privacy:metadata:anthropic:sourcetext'] = 'जनरेशनसाठी पाठवलेला मूळ कंटेंट.';
$string['privacy:metadata:anthropic:model'] = 'रिक्वेस्ट ज्या मॉडेलसाठी होती ते मॉडेल.';
$string['privacy:metadata:elevenlabs'] = 'व्हॉइसओव्हरसाठी नरेशनचा मजकूर ElevenLabs ला पाठवला जाऊ शकतो, पण फक्त live_api फीचर फ्लॅग ON असतानाच (डीफॉल्टनुसार तो OFF असतो).';
$string['privacy:metadata:elevenlabs:narration'] = 'सिंथेसिससाठी पाठवलेला नरेशनचा मजकूर.';
$string['publish_to_course'] = 'कोर्स म्हणून पब्लिश करा';
$string['publish_selectcategory'] = 'कोर्स कॅटेगरी';
$string['publish_disabled'] = 'कोर्स पब्लिशिंग बंद आहे (फ्लॅग sentientia.authoring.publish.enabled OFF आहे) — ninja/staging वर पडताळणी झाल्यानंतर तो ON केला जाईल.';
$string['publish_success'] = 'कोर्स "{$a->shortname}" तयार झाला (हिडन), त्यात {$a->cardcount} कंटेंट कार्ड्स आणि {$a->questioncount} क्विझ प्रश्न आहेत. तो रिव्ह्यू करा, मग व्हिजिबल करा.';
$string['publish_failed'] = 'पब्लिश अयशस्वी: {$a}';
$string['publish_course_summary'] = 'ड्राफ्ट #{$a->draftid} वरून Sentientia ऑथरिंग स्टुडिओने जनरेट केला (मॉडेल: {$a->model}) आणि पब्लिश करण्यापूर्वी एका व्यक्तीने रिव्ह्यू केला.';
$string['publish_book_name'] = 'कोर्स कंटेंट';
$string['publish_card_untitled'] = 'कार्ड {$a}';
$string['publish_card_keypoint'] = 'मुख्य मुद्दा:';
$string['publish_card_transcript'] = 'नरेशन ट्रान्सक्रिप्ट';
$string['publish_quiz_name'] = 'मास्टरी चेक (पास होण्यासाठी {$a}%)';
$string['publish_quiz_intro'] = 'खालील प्रश्नांची उत्तरे द्या. हा कोर्स पास होण्यासाठी तुम्हाला {$a}% मिळायला हवेत.';
$string['err_publish_nocards'] = 'पब्लिश करण्यासाठी कोणतीही मंजूर किंवा एडिट केलेली कार्ड्स नाहीत.';
$string['err_publish_nobank'] = 'कोर्सची प्रश्न बँक शोधता आली नाही.';
$string['err_publish_importcount'] = 'प्रश्न इम्पोर्टमध्ये फरक: अपेक्षित {$a->expected}, इम्पोर्ट झाले {$a->actual}. काहीही पब्लिश झाले नाही.';
$string['err_publish_badoptions'] = 'प्रश्न {$a} मध्ये दोनपेक्षा कमी उत्तर पर्याय आहेत.';
$string['err_publish_badanswer'] = 'प्रश्न {$a} चा बरोबर-उत्तर इंडेक्स त्याच्या पर्यायांच्या बाहेर आहे.';
$string['err_publish_tempfile'] = 'इम्पोर्ट वर्क फाइल लिहिता आली नाही.';
$string['err_publish_import'] = 'प्रश्नांचा इम्पोर्ट अयशस्वी झाला. काहीही पब्लिश झाले नाही.';
$string['err_publish_notenant'] = 'हा ड्राफ्ट कोणत्याही टेनंटशी जोडलेला नाही, त्यामुळे तो पब्लिश करता येणार नाही: तसे केल्यास कोर्स प्रत्येक टेनंटच्या कॅटलॉगमध्ये दिसेल. तो पब्लिश करण्यासाठी प्लॅटफॉर्म ॲडमिनला सांगा.';
