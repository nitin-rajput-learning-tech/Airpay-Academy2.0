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
 * Marathi strings for local_sentientia_skillsai.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_skillsai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'स्किल्स इंटेलिजन्स (AI)';
$string['sentientia_skillsai:extract'] = 'AI वापरून स्किल्स काढा';
$string['sentientia_skillsai:review'] = 'काढलेल्या स्किल्सचा रिव्ह्यू करा आणि मंजूर करा';
$string['sentientia_skillsai:manage_taxonomy'] = 'स्किल्स टॅक्सॉनॉमी आणि इम्पॅक्ट मॅपिंग्ज सांभाळा';
$string['sentientia_skillsai:viewgaps'] = 'स्किल्स-गॅप फीड्स पाहा';
$string['sentientia_skillsai:manage_all'] = 'सर्व मालक आणि टेनंट्सचे सर्व एक्सट्रॅक्शन जॉब्स मॅनेज करा';
$string['nav_extract'] = 'स्किल्स काढा';
$string['nav_taxonomy'] = 'स्किल्स टॅक्सॉनॉमी';
$string['nav_gaps'] = 'स्किल्स गॅप्स';
$string['queue_page_title'] = 'स्किल्स एक्सट्रॅक्शन क्यू';
$string['queue_page_heading'] = 'स्किल्स एक्सट्रॅक्शन क्यू';
$string['queue_empty'] = 'अजून कोणतेही एक्सट्रॅक्शन जॉब्स नाहीत. सुरू करण्यासाठी "स्किल्स काढा" वर क्लिक करा.';
$string['col_title'] = 'शीर्षक';
$string['col_sourcekind'] = 'सोर्स';
$string['col_status'] = 'स्टेटस';
$string['col_extracted'] = 'स्किल्स';
$string['col_actions'] = 'ॲक्शन्स';
$string['action_review'] = 'रिव्ह्यू करा';
$string['sourcekind_scorm'] = 'SCORM ट्रान्सक्रिप्ट';
$string['sourcekind_narration'] = 'नरेशनचा मजकूर';
$string['sourcekind_sop'] = 'SOP चा भाग';
$string['sourcekind_manual'] = 'पेस्ट केलेला मजकूर';
$string['jobstatus_pending'] = 'प्रलंबित';
$string['jobstatus_extracted'] = 'रिव्ह्यूच्या प्रतीक्षेत';
$string['jobstatus_reviewed'] = 'रिव्ह्यू झाला';
$string['jobstatus_failed'] = 'अयशस्वी';
$string['extract_page_title'] = 'AI वापरून स्किल्स काढा';
$string['extract_page_heading'] = 'AI वापरून स्किल्स काढा';
$string['extract_intro'] = 'कोर्स/SCORM ट्रान्सक्रिप्ट, SOP चा भाग किंवा नरेशनचा मजकूर पेस्ट करा. त्यातून कोणती स्किल्स शिकवली जातात ते Claude सुचवतो; प्रत्येक सुचवलेले स्किल टॅक्सॉनॉमीमध्ये जाण्यापूर्वी एखाद्या व्यक्तीच्या रिव्ह्यूमध्ये पास व्हायलाच हवे.';
$string['extract_form_title'] = 'एक्सट्रॅक्शनचे शीर्षक';
$string['extract_form_course'] = 'संबंधित कोर्स (ऐच्छिक)';
$string['extract_form_course_none'] = 'कोणत्याही कोर्सशी जोडलेले नाही';
$string['extract_form_sourcekind'] = 'सोर्सचा प्रकार';
$string['extract_form_language'] = 'भाषा';
$string['extract_form_language_en'] = 'इंग्रजी';
$string['extract_form_language_hi'] = 'हिंदी (हिन्दी)';
$string['extract_form_source'] = 'मूळ कंटेंट';
$string['extract_form_source_help'] = 'जास्तीत जास्त {$a} शब्द. वैयक्तिक ओळख पटवणारी माहिती (कर्मचाऱ्यांची नावे, ID, पगार, कस्टमर डेटा) पेस्ट करू नका.';
$string['extract_prompt_preview_summary'] = 'Claude ला दिसणाऱ्या प्रॉम्प्टचा प्रीव्ह्यू पाहा (व्हर्जन: {$a->version})';
$string['extract_prompt_preview_help'] = 'मॉडेलला दिशा देणारा हाच नेमका सिस्टम प्रॉम्प्ट आहे. कन्फर्म करण्यापूर्वी तो रिव्ह्यू करा.';
$string['extract_prompt_preview_custom_badge'] = 'कस्टमर-विशिष्ट प्रॉम्प्ट टेम्पलेट वापरात आहे';
$string['extract_confirm_label'] = 'या मूळ मजकुरात कोणताही वैयक्तिक डेटा नाही हे मी कन्फर्म करत आहे आणि AI एक्सट्रॅक्शनला परवानगी देत आहे.';
$string['extract_confirm_help'] = 'लाइव्ह API कॉलसाठी प्रत्येक टोकनमागे पैसे लागतात. मॉक मोड (डीफॉल्ट) मोफत आहे आणि सुरक्षिततेसाठी त्याला कन्फर्मेशनची गरज नाही, तरीही कन्फर्मेशन नेहमीच आवश्यक आहे.';
$string['extract_submit'] = 'स्किल्स काढा';
$string['extract_cancel'] = 'रद्द करा';
$string['mode_disabled_badge'] = 'स्किल्स इंटेलिजन्स बंद आहे (फीचर फ्लॅग OFF).';
$string['mode_mock_badge'] = 'मॉक मोड — कोणताही Anthropic कॉल होणार नाही (लाइव्ह API फ्लॅग OFF). शून्य खर्च.';
$string['mode_no_apikey_badge'] = 'लाइव्ह API फ्लॅग ON आहे पण कोणतीही API की कॉन्फिगर केलेली नाही — की सेट होईपर्यंत कॉल अयशस्वी होतील.';
$string['mode_live_badge'] = 'लाइव्ह मोड — एक्सट्रॅक्शन Anthropic ला POST करेल आणि टोकनचा खर्च येईल.';
$string['tokens_used_today'] = 'आज वापरलेले टोकन: {$a->cap} पैकी {$a->used}.';
$string['source_word_count'] = '{$a} शब्द';
$string['review_page_title'] = 'काढलेल्या स्किल्सचा रिव्ह्यू करा';
$string['review_page_heading'] = 'काढलेल्या स्किल्सचा रिव्ह्यू करा';
$string['review_intro'] = 'सुचवलेले प्रत्येक स्किल मंजूर करा, एडिट करा किंवा नाकारा. मंजूर किंवा एडिट केल्यावर ते टेनंटच्या अधिकृत टॅक्सॉनॉमीमध्ये जाते. तुमच्या निर्णयाशिवाय काहीही अधिकृत होत नाही.';
$string['review_saved'] = 'स्किलचा निर्णय सेव्ह झाला.';
$string['review_finalised'] = 'रिव्ह्यू पूर्ण झाला.';
$string['review_finalise_submit'] = 'रिव्ह्यू पूर्ण करा';
$string['review_job_failed'] = 'हे एक्सट्रॅक्शन अयशस्वी झाले: {$a}';
$string['back_to_queue'] = 'क्यूकडे परत जा';
$string['cand_name'] = 'स्किलचे नाव';
$string['cand_description'] = 'वर्णन';
$string['cand_category'] = 'कॅटेगरी';
$string['cand_level'] = 'शिकवायची लेव्हल';
$string['cand_note'] = 'रिव्ह्यूअरची टीप';
$string['cand_confidence'] = 'कॉन्फिडन्स {$a}';
$string['cand_promoted'] = 'टॅक्सॉनॉमीमध्ये आहे';
$string['candstatus_proposed'] = 'सुचवलेले';
$string['candstatus_approved'] = 'मंजूर';
$string['candstatus_edited'] = 'एडिट केलेले';
$string['candstatus_rejected'] = 'नाकारले';
$string['verdict_approve'] = 'मंजूर करा';
$string['verdict_edit'] = 'बदल सेव्ह करा';
$string['verdict_reject'] = 'नाकारा';
$string['nav_taxonomy_short'] = 'टॅक्सॉनॉमी';
$string['taxonomy_page_title'] = 'स्किल्स टॅक्सॉनॉमी';
$string['taxonomy_page_heading'] = 'स्किल्स टॅक्सॉनॉमी आणि बिझनेस इम्पॅक्ट';
$string['taxonomy_intro'] = 'या टेनंटसाठी माणसांनी मंजूर केलेली अधिकृत स्किल्स. प्रत्येक स्किल ज्या बिझनेस मेट्रिक्सवर परिणाम करते त्यांच्याशी ते मॅप करा, म्हणजे स्किल्स गॅप्स बिझनेस प्राधान्यानुसार रँक करता येतील.';
$string['taxonomy_empty'] = 'अजून कोणतीही मंजूर स्किल्स नाहीत. टॅक्सॉनॉमी तयार करण्यासाठी काढलेली स्किल्स मंजूर करा.';
$string['impact_flag_off'] = 'बिझनेस-इम्पॅक्ट मॅपिंग बंद आहे (फीचर फ्लॅग OFF). ते सुरू होईपर्यंत टॅक्सॉनॉमी फक्त वाचण्यासाठी आहे.';
$string['impact_metric'] = 'बिझनेस मेट्रिक';
$string['impact_detail'] = 'याचा मेट्रिकवर कसा परिणाम होतो';
$string['impact_weight'] = 'प्राधान्य';
$string['impact_add'] = 'जोडा';
$string['impact_added'] = 'बिझनेस-इम्पॅक्ट मॅपिंग जोडले.';
$string['impact_weight_badge'] = 'प्राधान्य {$a}';
$string['gaps_page_title'] = 'स्किल्स गॅप्स';
$string['gaps_page_heading'] = 'स्किल्स गॅप्स';
$string['gaps_summary_heading'] = 'टेनंट स्किल्स-गॅप सारांश';
$string['gaps_summary_intro'] = 'ज्या स्किल्समध्ये लर्नर्स त्यांच्या रोलच्या गरजेपेक्षा कमी पडतात, ती आधी बिझनेस प्राधान्यानुसार आणि मग प्रभावित लोकांच्या संख्येनुसार रँक केलेली आहेत.';
$string['gaps_summary_none'] = 'कोणतेही ओपन स्किल्स गॅप्स नाहीत. हा व्ह्यू भरण्यासाठी एखाद्या लर्नरचे फीड रीबिल्ड करा.';
$string['gaps_user_heading'] = '{$a} यांचे स्किल्स गॅप्स';
$string['gaps_user_none'] = 'या लर्नरसाठी कोणतेही ओपन स्किल्स गॅप्स नाहीत — रोलची प्रत्येक गरज पूर्ण झाली आहे.';
$string['gaps_rebuild_user'] = 'या लर्नरचे गॅप फीड रीबिल्ड करा';
$string['gaps_rebuilt'] = 'गॅप फीड रीबिल्ड झाले: {$a} ओपन गॅप्स.';
$string['gaps_back_summary'] = 'सारांशाकडे परत जा';
$string['col_skill'] = 'स्किल';
$string['col_required'] = 'आवश्यक';
$string['col_held'] = 'सध्याचे';
$string['col_gap'] = 'गॅप';
$string['col_impact'] = 'इम्पॅक्ट';
$string['col_affected'] = 'प्रभावित लर्नर्स';
$string['col_maxgap'] = 'सर्वात मोठा गॅप';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic API की + मॉडेल कॉन्फिगर करा. लाइव्ह-API फीचर फ्लॅग ON असेल तेव्हाच की वापरली जाते; मॉक मोडमध्ये की लागत नाही.';
$string['setting_api_key'] = 'API की';
$string['setting_api_key_desc'] = 'तुमची Anthropic API की. मास्क करून साठवली जाते. सोर्स कंट्रोलमध्ये कधीही कमिट होत नाही. मॉक मोडमध्येच राहण्यासाठी रिकामे ठेवा.';
$string['setting_default_model'] = 'डीफॉल्ट मॉडेल';
$string['setting_default_model_desc'] = 'एक्सट्रॅक्शनसाठी वापरला जाणारा Anthropic मॉडेल आयडेंटिफायर (उदा. claude-sonnet-4-6).';
$string['settings_heading_limits'] = 'मर्यादा';
$string['settings_heading_limits_desc'] = 'बेसुमार खर्चापासून संरक्षण करणाऱ्या प्रति-रिक्वेस्ट आणि प्रति-दिवस मर्यादा.';
$string['setting_max_skills'] = 'प्रति एक्सट्रॅक्शन जास्तीत जास्त स्किल्स';
$string['setting_max_skills_desc'] = 'एक एक्सट्रॅक्शन जास्तीत जास्त किती कॅन्डिडेट स्किल्स मागेल.';
$string['setting_daily_token_cap'] = 'रोजची टोकन मर्यादा (प्रति युजर)';
$string['setting_daily_token_cap_desc'] = 'एखाद्या ऑथरने आज इतके टोकन वापरले की, उद्यापर्यंत पुढील एक्सट्रॅक्शन्स ब्लॉक केली जातात.';
$string['setting_max_source_words'] = 'जास्तीत जास्त मूळ शब्द';
$string['setting_max_source_words_desc'] = 'यापेक्षा मोठा मूळ मजकूर पेस्ट करतानाच नाकारला जातो.';
$string['settings_heading_customer_prompts'] = 'प्रति-कस्टमर प्रॉम्प्ट टेम्पलेट';
$string['settings_heading_customer_prompts_desc'] = 'सिस्टम प्रॉम्प्टच्या मुख्य भागासाठी ऐच्छिक ओव्हरराइड, या कस्टमरला लागू. बिल्ट-इन बेसलाइन प्रॉम्प्ट वापरण्यासाठी रिकामे ठेवा.';
$string['setting_customer_1_prompt_template'] = 'Airpay एक्सट्रॅक्शन प्रॉम्प्ट टेम्पलेट';
$string['setting_customer_1_prompt_template_desc'] = 'हे सेट केल्यावर, Airpay एक्सट्रॅक्शन्ससाठी हा मजकूर बिल्ट-इन सिस्टम प्रॉम्प्टची जागा घेतो. युजर-मेसेज रॅपर मात्र निवडलेल्या भाषेनुसारच चालतो.';
$string['task_rebuild_gap_feed'] = 'स्किल्स-गॅप फीड्स रीबिल्ड करा';
$string['err_feature_off'] = 'या साइटवर स्किल्स इंटेलिजन्स चालू नाही.';
$string['err_gap_feature_off'] = 'या साइटवर स्किल्स-गॅप इंजिन चालू नाही.';
$string['err_source_empty'] = 'मूळ कंटेंट रिकामा असू शकत नाही.';
$string['err_source_too_long'] = 'मूळ कंटेंट शब्दांच्या कमाल मर्यादेपेक्षा जास्त आहे.';
$string['err_source_contains_pii'] = 'मूळ कंटेंटमध्ये वैयक्तिक ओळख पटवणारी माहिती असल्याचे दिसते (उदा. आधार किंवा PAN नंबर). एक्सट्रॅक्शनपूर्वी ती काढून टाका.';
$string['err_confirm_required'] = 'स्किल्स काढण्यापूर्वी कन्फर्मेशन बॉक्सवर टिक करणे आवश्यक आहे.';
$string['err_token_cap_reached'] = 'रोजची टोकन मर्यादा गाठली ({$a->cap} पैकी {$a->used}). उद्या पुन्हा प्रयत्न करा.';
$string['err_api_failed'] = 'एक्सट्रॅक्शन कॉल अयशस्वी झाला: {$a}';
$string['err_job_not_found'] = 'तो एक्सट्रॅक्शन जॉब अस्तित्वात नाही किंवा तुम्हाला त्याचा ॲक्सेस नाही.';
$string['err_candidate_not_found'] = 'ते कॅन्डिडेट स्किल या एक्सट्रॅक्शन जॉबचे नाही.';
$string['err_candidate_not_approved'] = 'फक्त मंजूर किंवा एडिट केलेली कॅन्डिडेट स्किल्सच टॅक्सॉनॉमीमध्ये प्रमोट करता येतात.';
$string['privacy:metadata:job'] = 'युजर्सनी मागवलेले आणि रिव्ह्यू केलेले स्किल-एक्सट्रॅक्शन जॉब्स.';
$string['privacy:metadata:job:ownerid'] = 'एक्सट्रॅक्शन मागवणारा युजर.';
$string['privacy:metadata:job:reviewed_by'] = 'एक्सट्रॅक्शनचा रिव्ह्यू करणारा युजर.';
$string['privacy:metadata:job:title'] = 'एक्सट्रॅक्शन जॉबचे शीर्षक.';
$string['privacy:metadata:job:status'] = 'एक्सट्रॅक्शन जॉबचा स्टेटस.';
$string['privacy:metadata:job:timecreated'] = 'एक्सट्रॅक्शन कधी तयार झाले.';
$string['privacy:metadata:gap'] = 'प्रति-युजर स्किल्स-गॅप फीडचे रो.';
$string['privacy:metadata:gap:userid'] = 'ज्या युजरचा हा स्किल्स गॅप आहे तो युजर.';
$string['privacy:metadata:gap:skillid'] = 'ज्या स्किलवर गॅप आहे ते स्किल.';
$string['privacy:metadata:gap:required_level'] = 'रोलसाठी आवश्यक लेव्हल.';
$string['privacy:metadata:gap:held_level'] = 'युजरची सध्याची लेव्हल.';
$string['privacy:metadata:gap:timecreated'] = 'गॅप रो कधी मोजला गेला.';
$string['privacy:metadata:anthropic'] = 'स्किल एक्सट्रॅक्शनसाठी Anthropic Claude API ला पाठवलेला मूळ लर्निंग मजकूर (फक्त लाइव्ह-API फ्लॅग ON असताना).';
$string['privacy:metadata:anthropic:sourcetext'] = 'एक्सट्रॅक्शनसाठी सबमिट केलेला लर्निंग मटेरियलचा मजकूर (पेस्ट करतानाच वैयक्तिक डेटासाठी तपासला जातो).';
$string['privacy:metadata:anthropic:model'] = 'वापरलेले Anthropic मॉडेल.';
$string['privacy:export:jobs'] = 'एक्सट्रॅक्शन जॉब्स';
$string['privacy:export:gaps'] = 'स्किल्स गॅप्स';
$string['privacy:metadata:skai_taxonomy'] = 'शेअर केलेली स्किल्स टॅक्सॉनॉमी, प्रत्येक एंट्री कोणी मंजूर केली त्यासह.';
$string['privacy:metadata:skai_taxonomy:approved_by'] = 'स्किल मंजूर करणाऱ्या युजरचा ID.';
$string['privacy:metadata:skai_taxonomy:name'] = 'स्किलचे नाव.';
$string['privacy:metadata:skai_impact'] = 'एखाद्या स्किलला जोडलेले बिझनेस-इम्पॅक्ट मेट्रिक्स, त्यांच्या ऑथरसह.';
$string['privacy:metadata:skai_impact:createdby'] = 'मेट्रिक तयार करणाऱ्या युजरचा ID.';
$string['privacy:metadata:skai_impact:metric_name'] = 'मेट्रिकचे नाव.';
