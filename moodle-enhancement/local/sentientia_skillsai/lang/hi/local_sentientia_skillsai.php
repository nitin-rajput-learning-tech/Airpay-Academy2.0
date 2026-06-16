<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi (हिन्दी) language strings for local_sentientia_skillsai.
 *
 * 100% key parity with lang/en — every key in the English pack has a
 * Devanagari translation here. Technical proper nouns (Anthropic, Claude,
 * SCORM, SOP, KYC, PAN, Aadhaar, API, AI) stay in Latin per L&D convention.
 *
 * @package local_sentientia_skillsai
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'कौशल इंटेलिजेंस (AI)';

// Capabilities.
$string['sentientia_skillsai:extract'] = 'AI से कौशल निकालें';
$string['sentientia_skillsai:review'] = 'निकाले गए कौशलों की समीक्षा एवं अनुमोदन करें';
$string['sentientia_skillsai:manage_taxonomy'] = 'कौशल वर्गीकरण एवं प्रभाव-मानचित्रण का प्रबंधन करें';
$string['sentientia_skillsai:viewgaps'] = 'कौशल-अंतराल फ़ीड देखें';
$string['sentientia_skillsai:manage_all'] = 'सभी स्वामियों एवं tenants के निष्कर्षण कार्य प्रबंधित करें';

// Navigation.
$string['nav_extract'] = 'कौशल निकालें';
$string['nav_taxonomy'] = 'कौशल वर्गीकरण';
$string['nav_gaps'] = 'कौशल अंतराल';

// Queue / index page.
$string['queue_page_title'] = 'कौशल निष्कर्षण कतार';
$string['queue_page_heading'] = 'कौशल निष्कर्षण कतार';
$string['queue_empty'] = 'अभी तक कोई निष्कर्षण कार्य नहीं। आरम्भ करने हेतु "कौशल निकालें" पर क्लिक करें।';
$string['col_title'] = 'शीर्षक';
$string['col_sourcekind'] = 'स्रोत';
$string['col_status'] = 'स्थिति';
$string['col_extracted'] = 'कौशल';
$string['col_actions'] = 'क्रियाएँ';
$string['action_review'] = 'समीक्षा';

// Source kinds.
$string['sourcekind_scorm'] = 'SCORM transcript';
$string['sourcekind_narration'] = 'Narration पाठ';
$string['sourcekind_sop'] = 'SOP अंश';
$string['sourcekind_manual'] = 'चिपकाया गया पाठ';

// Job status labels.
$string['jobstatus_pending'] = 'लंबित';
$string['jobstatus_extracted'] = 'समीक्षा प्रतीक्षित';
$string['jobstatus_reviewed'] = 'समीक्षित';
$string['jobstatus_failed'] = 'विफल';

// Extract page.
$string['extract_page_title'] = 'AI से कौशल निकालें';
$string['extract_page_heading'] = 'AI से कौशल निकालें';
$string['extract_intro'] = 'कोई course/SCORM transcript, SOP अंश या narration पाठ चिपकाएँ। Claude उन कौशलों को प्रस्तावित करता है जो यह सिखाता है; प्रत्येक प्रस्ताव को वर्गीकरण में जुड़ने से पूर्व मानवीय समीक्षा से गुज़रना अनिवार्य है।';
$string['extract_form_title'] = 'निष्कर्षण शीर्षक';
$string['extract_form_course'] = 'सम्बद्ध पाठ्यक्रम (वैकल्पिक)';
$string['extract_form_course_none'] = 'किसी पाठ्यक्रम से सम्बद्ध नहीं';
$string['extract_form_sourcekind'] = 'स्रोत प्रकार';
$string['extract_form_language'] = 'भाषा';
$string['extract_form_language_en'] = 'अंग्रेज़ी (English)';
$string['extract_form_language_hi'] = 'हिन्दी';
$string['extract_form_source'] = 'स्रोत सामग्री';
$string['extract_form_source_help'] = 'अधिकतम {$a} शब्द। कोई व्यक्तिगत पहचान-योग्य सूचना (कर्मचारी का नाम, ID, वेतन, ग्राहक-डेटा) न चिपकाएँ।';
$string['extract_prompt_preview_summary'] = 'वह prompt देखें जो Claude को दिखेगा (संस्करण: {$a->version})';
$string['extract_prompt_preview_help'] = 'यह वही system prompt है जो मॉडल को निर्देशित करता है। पुष्टि करने से पूर्व इसे देख लें।';
$string['extract_prompt_preview_custom_badge'] = 'ग्राहक-विशिष्ट prompt template प्रयोग में है';
$string['extract_confirm_label'] = 'मैं पुष्टि करता/करती हूँ कि इस स्रोत में कोई व्यक्तिगत डेटा नहीं है और मैं AI निष्कर्षण को अधिकृत करता/करती हूँ।';
$string['extract_confirm_help'] = 'live API कॉल प्रति-token शुल्क लेती है। Mock mode (default) निःशुल्क है और सुरक्षित रहने हेतु पुष्टि की आवश्यकता नहीं, परन्तु पुष्टि सदैव अनिवार्य है।';
$string['extract_submit'] = 'कौशल निकालें';
$string['extract_cancel'] = 'रद्द करें';

// Mode badges.
$string['mode_disabled_badge'] = 'कौशल इंटेलिजेंस निष्क्रिय है (feature flag OFF)।';
$string['mode_mock_badge'] = 'Mock mode — कोई Anthropic कॉल नहीं होगी (live API flag OFF)। शून्य लागत।';
$string['mode_no_apikey_badge'] = 'live API flag ON है परन्तु कोई API key विन्यस्त नहीं — key सेट होने तक कॉल विफल होंगी।';
$string['mode_live_badge'] = 'Live mode — निष्कर्षण Anthropic को POST करेगा और token लागत लगेगी।';

// Token cap.
$string['tokens_used_today'] = 'आज प्रयुक्त tokens: {$a->cap} में से {$a->used}।';
$string['source_word_count'] = '{$a} शब्द';

// Review page.
$string['review_page_title'] = 'निकाले गए कौशलों की समीक्षा';
$string['review_page_heading'] = 'निकाले गए कौशलों की समीक्षा';
$string['review_intro'] = 'प्रत्येक प्रस्तावित कौशल को अनुमोदित करें, संपादित करें या अस्वीकार करें। अनुमोदन या संपादन उसे tenant-वार canonical वर्गीकरण में पदोन्नत करता है। आपके निर्णय के बिना कुछ भी canonical नहीं होता।';
$string['review_saved'] = 'कौशल निर्णय सहेजा गया।';
$string['review_finalised'] = 'समीक्षा अंतिम की गई।';
$string['review_finalise_submit'] = 'समीक्षा अंतिम करें';
$string['review_job_failed'] = 'यह निष्कर्षण विफल रहा: {$a}';
$string['back_to_queue'] = 'कतार पर लौटें';

// Candidate fields + verdicts.
$string['cand_name'] = 'कौशल नाम';
$string['cand_description'] = 'विवरण';
$string['cand_category'] = 'श्रेणी';
$string['cand_level'] = 'सिखाने का स्तर';
$string['cand_note'] = 'समीक्षक टिप्पणी';
$string['cand_confidence'] = 'Confidence {$a}';
$string['cand_promoted'] = 'वर्गीकरण में';
$string['candstatus_proposed'] = 'प्रस्तावित';
$string['candstatus_approved'] = 'अनुमोदित';
$string['candstatus_edited'] = 'संपादित';
$string['candstatus_rejected'] = 'अस्वीकृत';
$string['verdict_approve'] = 'अनुमोदित करें';
$string['verdict_edit'] = 'संपादन सहेजें';
$string['verdict_reject'] = 'अस्वीकार करें';

// Taxonomy page.
$string['nav_taxonomy_short'] = 'वर्गीकरण';
$string['taxonomy_page_title'] = 'कौशल वर्गीकरण';
$string['taxonomy_page_heading'] = 'कौशल वर्गीकरण एवं व्यावसायिक प्रभाव';
$string['taxonomy_intro'] = 'इस tenant हेतु canonical, मानव-अनुमोदित कौशल। प्रत्येक कौशल को उन व्यावसायिक metrics से जोड़ें जिन्हें वह प्रभावित करता है ताकि कौशल-अंतराल को व्यावसायिक प्राथमिकता के अनुसार क्रमित किया जा सके।';
$string['taxonomy_empty'] = 'अभी तक कोई अनुमोदित कौशल नहीं। वर्गीकरण बनाने हेतु निकाले गए कौशलों को अनुमोदित करें।';

// Impact mapping.
$string['impact_flag_off'] = 'व्यावसायिक-प्रभाव मानचित्रण निष्क्रिय है (feature flag OFF)। सक्षम होने तक वर्गीकरण केवल-पठन है।';
$string['impact_metric'] = 'व्यावसायिक metric';
$string['impact_detail'] = 'यह metric को कैसे प्रभावित करता है';
$string['impact_weight'] = 'प्राथमिकता';
$string['impact_add'] = 'जोड़ें';
$string['impact_added'] = 'व्यावसायिक-प्रभाव मानचित्रण जोड़ा गया।';
$string['impact_weight_badge'] = 'प्राथमिकता {$a}';

// Gaps page.
$string['gaps_page_title'] = 'कौशल अंतराल';
$string['gaps_page_heading'] = 'कौशल अंतराल';
$string['gaps_summary_heading'] = 'Tenant कौशल-अंतराल सारांश';
$string['gaps_summary_intro'] = 'वे कौशल जहाँ शिक्षार्थी अपनी भूमिका की आवश्यकताओं से पीछे रह जाते हैं, व्यावसायिक प्राथमिकता एवं प्रभावित व्यक्तियों की संख्या के अनुसार क्रमित।';
$string['gaps_summary_none'] = 'कोई खुला कौशल-अंतराल नहीं। इस दृश्य को भरने हेतु किसी शिक्षार्थी की फ़ीड पुनर्निर्मित करें।';
$string['gaps_user_heading'] = '{$a} हेतु कौशल अंतराल';
$string['gaps_user_none'] = 'इस शिक्षार्थी हेतु कोई खुला कौशल-अंतराल नहीं — प्रत्येक भूमिका-आवश्यकता पूरी है।';
$string['gaps_rebuild_user'] = 'इस शिक्षार्थी की अंतराल-फ़ीड पुनर्निर्मित करें';
$string['gaps_rebuilt'] = 'अंतराल-फ़ीड पुनर्निर्मित: {$a} खुले अंतराल।';
$string['gaps_back_summary'] = 'सारांश पर लौटें';
$string['col_skill'] = 'कौशल';
$string['col_required'] = 'आवश्यक';
$string['col_held'] = 'धारित';
$string['col_gap'] = 'अंतराल';
$string['col_impact'] = 'प्रभाव';
$string['col_affected'] = 'प्रभावित शिक्षार्थी';
$string['col_maxgap'] = 'सबसे बड़ा अंतराल';

// Settings.
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Anthropic API key एवं मॉडल विन्यस्त करें। key केवल तब प्रयुक्त होती है जब live-API feature flag ON हो; mock mode में किसी key की आवश्यकता नहीं।';
$string['setting_api_key'] = 'API key';
$string['setting_api_key_desc'] = 'आपकी Anthropic API key। मास्क करके संग्रहीत। source control में कभी commit न करें। mock mode में रहने हेतु रिक्त छोड़ें।';
$string['setting_default_model'] = 'डिफ़ॉल्ट मॉडल';
$string['setting_default_model_desc'] = 'निष्कर्षण हेतु प्रयुक्त Anthropic मॉडल पहचानकर्ता (जैसे claude-sonnet-4-6)।';
$string['settings_heading_limits'] = 'सीमाएँ';
$string['settings_heading_limits_desc'] = 'प्रति-अनुरोध एवं प्रति-दिन सुरक्षा-सीमाएँ जो अनियंत्रित लागत से रक्षा करती हैं।';
$string['setting_max_skills'] = 'प्रति निष्कर्षण अधिकतम कौशल';
$string['setting_max_skills_desc'] = 'एकल निष्कर्षण अधिकतम कितने candidate कौशल मांगेगा।';
$string['setting_daily_token_cap'] = 'दैनिक token सीमा (प्रति उपयोगकर्ता)';
$string['setting_daily_token_cap_desc'] = 'जब कोई लेखक आज इतने tokens उपयोग कर ले, तो आगे के निष्कर्षण कल तक अवरुद्ध रहते हैं।';
$string['setting_max_source_words'] = 'अधिकतम स्रोत शब्द';
$string['setting_max_source_words_desc'] = 'इससे लंबा स्रोत पाठ चिपकाते समय अस्वीकार कर दिया जाता है।';
$string['settings_heading_customer_prompts'] = 'प्रति-ग्राहक prompt template';
$string['settings_heading_customer_prompts_desc'] = 'system prompt के मुख्य भाग हेतु एक वैकल्पिक override, इस ग्राहक पर लागू। अंतर्निहित baseline prompt प्रयोग हेतु रिक्त छोड़ें।';
$string['setting_customer_1_prompt_template'] = 'Airpay निष्कर्षण prompt template';
$string['setting_customer_1_prompt_template_desc'] = 'सेट होने पर यह पाठ Airpay निष्कर्षणों हेतु अंतर्निहित system prompt को प्रतिस्थापित करता है। user-message wrapper फिर भी चुनी गई भाषा का अनुसरण करता है।';

// Scheduled task.
$string['task_rebuild_gap_feed'] = 'कौशल-अंतराल फ़ीड पुनर्निर्माण';

// Errors.
$string['err_feature_off'] = 'इस साइट पर कौशल इंटेलिजेंस सक्षम नहीं है।';
$string['err_gap_feature_off'] = 'इस साइट पर कौशल-अंतराल इंजन सक्षम नहीं है।';
$string['err_source_empty'] = 'स्रोत सामग्री रिक्त नहीं हो सकती।';
$string['err_source_too_long'] = 'स्रोत सामग्री अधिकतम शब्द-सीमा से अधिक है।';
$string['err_source_contains_pii'] = 'स्रोत सामग्री में व्यक्तिगत पहचान-योग्य सूचना (जैसे Aadhaar या PAN संख्या) प्रतीत होती है। निष्कर्षण से पूर्व उसे हटाएँ।';
$string['err_confirm_required'] = 'निष्कर्षण से पूर्व आपको पुष्टि-बॉक्स पर निशान लगाना होगा।';
$string['err_token_cap_reached'] = 'दैनिक token सीमा पहुँच गई ({$a->cap} में से {$a->used})। कल पुनः प्रयास करें।';
$string['err_api_failed'] = 'निष्कर्षण कॉल विफल रही: {$a}';
$string['err_job_not_found'] = 'वह निष्कर्षण कार्य मौजूद नहीं है या आपके पास उस तक पहुँच नहीं है।';
$string['err_candidate_not_found'] = 'वह candidate कौशल इस निष्कर्षण कार्य से सम्बद्ध नहीं है।';
$string['err_candidate_not_approved'] = 'केवल अनुमोदित या संपादित candidates को वर्गीकरण में पदोन्नत किया जा सकता है।';

// Privacy.
$string['privacy:metadata:job'] = 'उपयोगकर्ताओं द्वारा अनुरोधित एवं समीक्षित कौशल-निष्कर्षण कार्य।';
$string['privacy:metadata:job:ownerid'] = 'वह उपयोगकर्ता जिसने निष्कर्षण का अनुरोध किया।';
$string['privacy:metadata:job:reviewed_by'] = 'वह उपयोगकर्ता जिसने निष्कर्षण की समीक्षा की।';
$string['privacy:metadata:job:title'] = 'निष्कर्षण कार्य का शीर्षक।';
$string['privacy:metadata:job:status'] = 'निष्कर्षण कार्य की स्थिति।';
$string['privacy:metadata:job:timecreated'] = 'निष्कर्षण कब बनाया गया।';
$string['privacy:metadata:gap'] = 'प्रति-उपयोगकर्ता कौशल-अंतराल फ़ीड पंक्तियाँ।';
$string['privacy:metadata:gap:userid'] = 'वह उपयोगकर्ता जिसका यह कौशल-अंतराल है।';
$string['privacy:metadata:gap:skillid'] = 'वह कौशल जिस पर अंतराल है।';
$string['privacy:metadata:gap:required_level'] = 'वह स्तर जो भूमिका आवश्यक करती है।';
$string['privacy:metadata:gap:held_level'] = 'वह स्तर जो उपयोगकर्ता वर्तमान में धारित करता है।';
$string['privacy:metadata:gap:timecreated'] = 'अंतराल पंक्ति कब परिकलित हुई।';
$string['privacy:metadata:anthropic'] = 'कौशल निष्कर्षण हेतु Anthropic Claude API को भेजा गया स्रोत अधिगम-पाठ (केवल जब live-API flag ON हो)।';
$string['privacy:metadata:anthropic:sourcetext'] = 'निष्कर्षण हेतु प्रस्तुत अधिगम-सामग्री पाठ (चिपकाते समय व्यक्तिगत डेटा हेतु जाँचा गया)।';
$string['privacy:metadata:anthropic:model'] = 'प्रयुक्त Anthropic मॉडल।';
$string['privacy:export:jobs'] = 'निष्कर्षण कार्य';
$string['privacy:export:gaps'] = 'कौशल अंतराल';
