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
 * Marathi strings for local_sentientia_aiquiz.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_aiquiz
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — AI क्विझ जनरेशन';
$string['sentientia_aiquiz:generate'] = 'AI ने ड्राफ्ट केलेले क्विझ प्रश्न जनरेट करा';
$string['sentientia_aiquiz:review'] = 'AI ने ड्राफ्ट केलेले क्विझ प्रश्न रिव्ह्यू करा';
$string['sentientia_aiquiz:manage_all'] = 'सर्व मालकांचे AI क्विझ ड्राफ्ट मॅनेज करा';
$string['privacy:metadata'] = 'Sentientia LMS AI क्विझ प्लगइन क्विझ ड्राफ्ट आणि ते जनरेट करण्यासाठी वापरलेला मूळ मजकूर साठवते. लाइव्ह API फ्लॅग ON असताना, मूळ मजकूर प्रोसेसिंगसाठी Anthropic कडे पाठवला जातो.';
$string['privacy:metadata:draft'] = 'युजर्सनी तयार केलेले AI क्विझ ड्राफ्ट.';
$string['privacy:metadata:draft:ownerid'] = 'ड्राफ्ट तयार करणारा युजर.';
$string['privacy:metadata:draft:sourcetext'] = 'जनरेशनसाठी युजरने दिलेला मूळ मजकूर, जसाच्या तसा.';
$string['privacy:metadata:draft:title'] = 'युजरने दिलेले ड्राफ्टचे शीर्षक.';
$string['privacy:metadata:draft:tokens'] = 'खर्चाच्या हिशोबासाठी Anthropic API टोकन वापर.';
$string['privacy:metadata:draft:reviewed_by'] = 'ड्राफ्ट रिव्ह्यू करणारा युजर.';
$string['privacy:metadata:draft:reviewed_at'] = 'ड्राफ्ट कधी रिव्ह्यू झाला.';
$string['privacy:metadata:draft:timecreated'] = 'ड्राफ्ट कधी तयार झाला.';
$string['privacy:metadata:draft:timemodified'] = 'ड्राफ्ट शेवटचा कधी बदलला.';
$string['privacy:metadata:question'] = 'एखाद्या ड्राफ्टमधील वेगवेगळे AI-जनरेटेड प्रश्न.';
$string['privacy:metadata:question:draftid'] = 'हा प्रश्न ज्या मुख्य ड्राफ्टचा भाग आहे तो ड्राफ्ट.';
$string['privacy:metadata:question:qtext'] = 'प्रश्नाचा मुख्य मजकूर.';
$string['privacy:metadata:question:qoptions'] = 'मल्टिचॉइस प्रश्नांचे JSON-एन्कोड केलेले पर्याय.';
$string['privacy:metadata:question:reviewer_note'] = 'प्रश्नावरील रिव्ह्यूअरच्या कमेंट्स.';
$string['privacy:metadata:question:timecreated'] = 'प्रश्न कधी जनरेट झाला.';
$string['privacy:metadata:question:timemodified'] = 'प्रश्न शेवटचा कधी बदलला.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — लाइव्ह API फ्लॅग चालू असताना, क्विझ जनरेशनसाठी मूळ मजकूर Anthropic कडे पाठवला जातो. प्रत्येक API कॉलपूर्वी कन्फर्मेशनची पायरी असते.';
$string['privacy:metadata:anthropic:sourcetext'] = 'ट्रेनरने दिलेला मूळ मजकूर.';
$string['privacy:metadata:anthropic:model'] = 'वापरलेला Anthropic मॉडेल आयडेंटिफायर.';
$string['nav_generate'] = 'AI क्विझ जनरेट करा';
$string['nav_review'] = 'AI क्विझ ड्राफ्ट रिव्ह्यू करा';
$string['generate_page_title'] = 'AI क्विझ जनरेट करा';
$string['generate_page_heading'] = 'कोर्स कंटेंटमधून क्विझ ड्राफ्ट जनरेट करा';
$string['generate_intro'] = 'मूळ मटेरियल (SCORM ट्रान्सक्रिप्ट, नरेशनचा मजकूर, SOP चा भाग) खाली पेस्ट करा. क्विझ ड्राफ्ट जनरेट करण्यासाठी Sentientia LMS ते Anthropic Claude कडे पाठवते. कोणताही प्रश्न कोर्स क्विझमध्ये पुश करण्यापूर्वी प्रत्येक ड्राफ्ट एखाद्या व्यक्तीने रिव्ह्यू करणे आवश्यक आहे.';
$string['generate_form_title'] = 'ड्राफ्टचे शीर्षक';
$string['generate_form_title_help'] = 'नंतर रिव्ह्यू क्यूमध्ये हा ड्राफ्ट सहज शोधता यावा म्हणून एक छोटे लेबल.';
$string['generate_form_course'] = 'कोर्स';
$string['generate_form_course_help'] = 'तयार होणारी क्विझ कोणत्या कोर्सची आहे. ही निवड रिव्ह्यूच्या वेळेपर्यंत पुढे ढकलण्यासाठी \\"साइट-वाइड / अजून असाइन केलेले नाही\\" निवडा.';
$string['generate_form_course_none'] = 'साइट-वाइड / अजून असाइन केलेले नाही';
$string['generate_form_source'] = 'मूळ कंटेंट';
$string['generate_form_source_help'] = 'जास्तीत जास्त {$a} शब्द. पेस्ट करण्यापूर्वी कोणतीही PII (कर्मचाऱ्यांची नावे, ID, कस्टमर डेटा) काढून टाका.';
$string['generate_form_num'] = 'किती प्रश्न हवे आहेत';
$string['generate_form_num_help'] = '1 ते {$a} दरम्यान. मूळ मजकूर खूप छोटा असल्यास Claude कमी प्रश्न देऊ शकतो.';
$string['generate_form_model'] = 'मॉडेल';
$string['generate_form_model_help'] = 'Anthropic मॉडेल आयडेंटिफायर. डीफॉल्ट: claude-sonnet-4-6.';
$string['generate_confirm_label'] = 'मला या कंटेंटसह Anthropic ला कॉल करायचा आहे, हे मी कन्फर्म करत आहे';
$string['generate_confirm_help'] = 'हा प्रत्येक कॉलसाठीचा कन्फर्मेशन गेट आहे. लाइव्ह API फ्लॅग ON असताना, या सबमिशनसाठी मूळ मजकुराच्या लांबीनुसार खरे पैसे खर्च होतील. रद्द करण्यासाठी टिक काढा.';
$string['generate_submit'] = 'क्विझ ड्राफ्ट जनरेट करा';
$string['generate_cancel'] = 'रद्द करा';
$string['generate_form_language'] = 'क्विझची भाषा';
$string['generate_form_language_help'] = 'Claude ला पाठवला जाणारा सिस्टम प्रॉम्प्ट निवडते. इंग्रजीसाठी v1 बेसलाइन प्रॉम्प्ट वापरला जातो. हिंदीसाठी v2-hindi प्रॉम्प्ट वापरला जातो आणि Claude ला प्रश्न देवनागरीत द्यायला सांगितले जाते.';
$string['generate_form_language_en'] = 'इंग्रजी (v1)';
$string['generate_form_language_hi'] = 'हिन्दी / Hindi (v2-hindi)';
$string['generate_prompt_preview_summary'] = 'Claude ला दिसणाऱ्या सिस्टम प्रॉम्प्टचा प्रीव्ह्यू पाहा (प्रॉम्प्ट व्हर्जन: {$a->version}, कस्टमर: {$a->customer})';
$string['generate_prompt_preview_help'] = 'लाइव्ह API फ्लॅग ON असताना तुम्ही जनरेट वर क्लिक केल्यास Anthropic कडे पाठवला जाणारा अचूक सिस्टम प्रॉम्प्ट हा आहे. मॉक मोड बनावट प्रश्न तयार करतो आणि API ला कॉल करत नाही. खाली प्रति-कस्टमर टेम्पलेट कॉन्फिगर केलेले असल्यास, ते जसेच्या तसे बेसलाइन प्रॉम्प्टच्या मुख्य भागाची जागा घेते.';
$string['generate_prompt_preview_custom_badge'] = 'कस्टम प्रति-कस्टमर टेम्पलेट ॲक्टिव्ह आहे';
$string['mode_mock_badge'] = 'मॉक मोड — लाइव्ह API कॉल नाही (खऱ्या जनरेशनसाठी sentientia.aiquiz.live_api = ON सेट करा)';
$string['mode_live_badge'] = 'लाइव्ह API — Anthropic कॉलचे बिल तुमच्या अकाउंटवर लागेल';
$string['mode_disabled_badge'] = 'फीचर बंद आहे — Switchboard मध्ये sentientia.aiquiz.enabled = ON सेट करा';
$string['mode_no_apikey_badge'] = 'API की कॉन्फिगर केलेली नाही — साइट ॲडमिनमध्ये local_sentientia_aiquiz | api_key सेट करा';
$string['err_feature_off'] = 'AI क्विझ फीचर बंद आहे. sentientia.aiquiz.enabled सुरू करण्यासाठी ॲडमिनला सांगा.';
$string['err_no_capability'] = 'तुम्हाला AI क्विझ ड्राफ्ट जनरेट करण्याची परवानगी नाही.';
$string['err_source_empty'] = 'मूळ कंटेंट आवश्यक आहे.';
$string['err_source_too_long'] = 'मूळ कंटेंट ठरवलेल्या शब्दमर्यादेपेक्षा जास्त आहे. सबमिट करण्यापूर्वी तो कमी करा.';
$string['err_source_contains_pii'] = 'मूळ कंटेंटमध्ये PII (आधार किंवा PAN नंबर) असल्याचे दिसते. ते काढून पुन्हा सबमिट करा.';
$string['err_invalid_count'] = 'प्रश्नांची संख्या {$a->min} ते {$a->max} दरम्यान असली पाहिजे.';
$string['err_token_cap_reached'] = 'तुमच्या अकाउंटने आज {$a->used} टोकन वापरले आहेत (रोजची सॉफ्ट मर्यादा {$a->cap} आहे). उद्या पुन्हा प्रयत्न करा किंवा मर्यादा वाढवण्यासाठी ॲडमिनला सांगा.';
$string['err_confirm_required'] = 'API ला कॉल करण्यासाठी तुम्ही कन्फर्मेशन चेकबॉक्सवर टिक करणे आवश्यक आहे.';
$string['err_api_key_not_set'] = 'कोणतीही Anthropic API की कॉन्फिगर केलेली नाही. लाइव्ह कॉल करता येणार नाही.';
$string['err_api_failed'] = 'Anthropic API कॉल अयशस्वी: {$a}';
$string['err_parser_zero'] = 'Claude ने उत्तर दिले, पण वापरता येतील असे कोणतेही प्रश्न पार्स करता आले नाहीत. जास्त मोठा किंवा जास्त स्पष्ट मूळ मजकूर वापरून पाहा.';
$string['review_page_title'] = 'AI क्विझ ड्राफ्ट रिव्ह्यू करा';
$string['review_page_heading'] = 'ड्राफ्ट #{$a} रिव्ह्यू करा';
$string['review_intro'] = 'खालील प्रत्येक जनरेट केलेला प्रश्न रिव्ह्यू करा. मंजूर करा, एडिट करा किंवा नाकारा — फक्त मंजूर किंवा एडिट केलेले प्रश्नच कोर्स क्विझमध्ये पुश करता येतात.';
$string['review_no_draft'] = 'ड्राफ्ट सापडला नाही किंवा तो पाहण्याची तुम्हाला परवानगी नाही.';
$string['review_meta_owner'] = 'मालक';
$string['review_meta_course'] = 'कोर्स';
$string['review_meta_model'] = 'मॉडेल';
$string['review_meta_prompt'] = 'प्रॉम्प्ट व्हर्जन';
$string['review_meta_tokens'] = 'वापरलेले टोकन';
$string['review_meta_generated_at'] = 'जनरेट केल्याची वेळ';
$string['review_meta_mode'] = 'जनरेशन मोड';
$string['review_status'] = 'स्टेटस';
$string['review_status_pending'] = 'जनरेशन प्रलंबित';
$string['review_status_generated'] = 'रिव्ह्यूच्या प्रतीक्षेत';
$string['review_status_approved'] = 'मंजूर (पुश करण्यासाठी तयार)';
$string['review_status_pushed'] = 'क्विझ #{$a} मध्ये पुश केले';
$string['review_status_rejected'] = 'नाकारले';
$string['review_status_failed'] = 'जनरेशन अयशस्वी';
$string['review_question_label'] = 'प्रश्न {$a}';
$string['review_question_answer'] = 'बरोबर उत्तर';
$string['review_question_explanation'] = 'स्पष्टीकरण';
$string['review_action_approve'] = 'मंजूर करा';
$string['review_action_edit'] = 'एडिट करा';
$string['review_action_reject'] = 'नाकारा';
$string['review_action_save_edit'] = 'बदल सेव्ह करा';
$string['review_action_cancel_edit'] = 'एडिट रद्द करा';
$string['review_q_status_generated'] = 'अजून रिव्ह्यू झालेला नाही';
$string['review_q_status_approved'] = 'मंजूर';
$string['review_q_status_edited'] = 'एडिट केलेले';
$string['review_q_status_rejected'] = 'नाकारले';
$string['review_finalise'] = 'रिव्ह्यू पूर्ण करा';
$string['review_finalise_help'] = 'हा ड्राफ्ट पूर्णपणे रिव्ह्यू झाल्याचे मार्क करते. किमान एक प्रश्न मंजूर किंवा एडिट केलेला असल्यास स्टेटस \\"मंजूर\\" होतो, नाहीतर \\"नाकारले\\".';
$string['review_push_to_quiz'] = 'मंजूर प्रश्न कोर्स क्विझमध्ये पुश करा';
$string['review_push_disabled'] = 'mod_quiz मध्ये पुश करणे sentientia.aiquiz.auto_push फ्लॅगवर अवलंबून आहे (डीफॉल्ट OFF).';
$string['review_push_success'] = 'मंजूर प्रश्न क्विझ #{$a->quizid} मध्ये पुश केले ({$a->count} प्रश्न).';
$string['review_no_questions'] = 'या ड्राफ्टसाठी कोणतेही प्रश्न जनरेट झाले नाहीत.';
$string['review_empty_state'] = 'तुमच्याकडे अजून कोणतेही AI क्विझ ड्राफ्ट नाहीत. नवीन तयार करण्यासाठी जनरेट पेज वापरा.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic Claude API साठी क्रेडेन्शियल्स. sentientia.aiquiz.enabled आणि sentientia.aiquiz.live_api दोन्ही ON असतील तेव्हाच की वापरली जाते.';
$string['setting_api_key'] = 'Anthropic API की';
$string['setting_api_key_desc'] = 'console.anthropic.com वरून API की पेस्ट करा. Moodle च्या configpasswordunmask द्वारे एन्क्रिप्ट करून साठवली जाते. ही व्हॅल्यू कधीही सोर्स कंट्रोलमध्ये कमिट करू नका.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'जनरेटरने मॉडेल न सांगितल्यास वापरले जाणारे Anthropic मॉडेल. शिफारस: claude-sonnet-4-6.';
$string['settings_heading_limits'] = 'मर्यादा आणि कोटा';
$string['settings_heading_limits_desc'] = 'खर्च अंदाजाप्रमाणे राहावा म्हणून प्रति-युजर आणि प्रति-रिक्वेस्ट मर्यादा.';
$string['setting_max_questions'] = 'प्रति रिक्वेस्ट जास्तीत जास्त प्रश्न';
$string['setting_max_questions_desc'] = 'प्रत्येक जनरेशनमधील प्रश्नांची कमाल मर्यादा. डीफॉल्ट 10.';
$string['setting_daily_token_cap'] = 'प्रति युजर रोजची टोकन मर्यादा';
$string['setting_daily_token_cap_desc'] = 'एक युजर दिवसाला खर्च करू शकेल अशा टोकनवरील (इनपुट + आउटपुट) सॉफ्ट मर्यादा. मर्यादा ओलांडल्यावर, मध्यरात्रीपर्यंत generate.php एरर दाखवते.';
$string['setting_max_source_words'] = 'प्रति ड्राफ्ट जास्तीत जास्त मूळ शब्द';
$string['setting_max_source_words_desc'] = 'मूळ मजकूर या शब्दसंख्येपेक्षा जास्त असल्यास नाकारला जातो. डीफॉल्ट 4000 — सुमारे 8 पाने.';
$string['settings_heading_customer_prompts'] = 'प्रति-कस्टमर प्रॉम्प्ट टेम्पलेट';
$string['settings_heading_customer_prompts_desc'] = 'प्रत्येक Sentientia LMS कस्टमरसाठी Claude ला पाठवल्या जाणाऱ्या सिस्टम प्रॉम्प्टचा ऐच्छिक ओव्हरराइड. सेट केल्यास, टेक्स्टएरियातील मजकूर बेसलाइन v1 (इंग्रजी) किंवा v2-hindi प्रॉम्प्टच्या मुख्य भागाची जसाच्या तसा पूर्णपणे जागा घेतो. कोडमधील बेसलाइन वापरण्यासाठी रिकामे ठेवा. युजर-मेसेज रॅपर (begin/end मार्कर + "नेमके N प्रश्न" सूचना) नेहमी जनरेट फॉर्मवरील भाषा निवडीनुसारच चालतो.';
$string['setting_customer_1_prompt_template'] = 'Airpay (कस्टमर 1) — कस्टम प्रॉम्प्ट टेम्पलेट';
$string['setting_customer_1_prompt_template_desc'] = 'Airpay साठी कस्टमर-विशिष्ट सिस्टम प्रॉम्प्ट पेस्ट करा. v1/v2-hindi बेसलाइन वापरण्यासाठी रिकामे ठेवा. local_sentientia_platform/customer_1_aiquiz_prompt_template अंतर्गत साठवले जाते — \\local_sentientia_platform\\customer::get_customer_config() द्वारे वाचले जाते. कस्टम टेम्पलेट ॲक्टिव्ह असताना जनरेट झालेल्या ड्राफ्ट्सचे prompt_version "custom:v1" किंवा "custom:v2-hindi" म्हणून नोंदवले जाते.';
$string['source_word_count'] = 'शब्दसंख्या: {$a}';
$string['tokens_used_today'] = 'आज वापरलेले टोकन: {$a->used} / {$a->cap}';
$string['back_to_drafts'] = 'ड्राफ्ट लिस्टकडे परत जा';
$string['drafts_list_title'] = 'तुमचे AI क्विझ ड्राफ्ट';
$string['push_selectcourse'] = 'टार्गेट कोर्स';
$string['push_default_quizname'] = 'AI जनरेटेड क्विझ (ड्राफ्ट {$a})';
$string['push_quiz_intro'] = 'AI क्विझ ड्राफ्ट #{$a->draftid} मधून जनरेट केली (मॉडेल: {$a->model}) आणि पब्लिश करण्यापूर्वी एका व्यक्तीने रिव्ह्यू केली. ही क्विझ हिडन म्हणून तयार केली आहे — तिची जागा आणि सेटिंग्ज रिव्ह्यू करा, मग ती व्हिजिबल करा.';
$string['push_success_real'] = 'क्विझ "{$a->quizname}" तयार झाली (id {$a->quizid}), त्यात {$a->count} प्रश्न आहेत. ती हिडन आहे — कोर्समध्ये रिव्ह्यू करा, मग व्हिजिबल करा.';
$string['push_err_nocourse'] = 'पुश करण्यापूर्वी टार्गेट कोर्स निवडा — हा ड्राफ्ट कोर्स कॉन्टेक्स्टशिवाय जनरेट झाला होता.';
$string['push_err_notapproved'] = 'फक्त फायनल केलेला (मंजूर) ड्राफ्टच पुश करता येतो. आधी रिव्ह्यू पूर्ण करा.';
$string['push_err_noquestions'] = 'पुश करण्यासाठी कोणतेही मंजूर किंवा एडिट केलेले प्रश्न नाहीत.';
$string['push_err_nobank'] = 'कोर्सची प्रश्न बँक शोधता आली नाही.';
$string['push_err_importcount'] = 'प्रश्न इम्पोर्टमध्ये तफावत: अपेक्षित {$a->expected}, इम्पोर्ट झाले {$a->actual}. काहीही पब्लिश केले नाही.';
$string['push_err_badoptions'] = 'प्रश्न {$a} मध्ये दोनपेक्षा कमी उत्तर पर्याय आहेत.';
$string['push_err_badanswer'] = 'प्रश्न {$a} चा बरोबर-उत्तर इंडेक्स त्याच्या पर्यायांच्या बाहेर आहे.';
$string['push_err_tempfile'] = 'इम्पोर्ट वर्क फाइल लिहिता आली नाही.';
$string['push_err_import'] = 'प्रश्नांचा इम्पोर्ट अयशस्वी झाला. काहीही पब्लिश केले नाही.';
$string['push_err_failed'] = 'पुश अयशस्वी: {$a}';
$string['review_notfound'] = 'ड्राफ्ट सापडला नाही, किंवा तुम्हाला त्याचा ॲक्सेस नाही.';
