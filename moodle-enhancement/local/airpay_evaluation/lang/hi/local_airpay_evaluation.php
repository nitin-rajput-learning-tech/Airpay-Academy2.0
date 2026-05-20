<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #27 (2026-05-20) — Hindi (hi) translations for local_airpay_evaluation.
// Covers strings added by P1 #17 (availability window + multiple_submit),
// P1 #18 (numeric + multichoice_multi types), and P1 #19 (admin-on-response
// notification), plus the full base lang pack.
//
// Style: formal corporate-Hindi register suitable for L&D / compliance
// contexts. Transliterated proper nouns (Kirkpatrick, NPS) kept in Latin
// script per L&D-content convention.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे मूल्यांकन';

// Capabilities.
$string['airpay_evaluation:manage']  = 'मूल्यांकन फॉर्म प्रबंधित करें';
$string['airpay_evaluation:respond'] = 'मूल्यांकन फॉर्म का उत्तर दें';

// W1-5 (2026-05-15) — observer + trigger queue.
$string['task_process_triggers']                = 'क्यूड मूल्यांकन ट्रिगर्स को प्रोसेस करें';
$string['messageprovider:evaluation_invite']    = 'मूल्यांकन आमंत्रण';
$string['invaliditemid']                        = 'ट्रिगर इवेंट के लिए अमान्य आइटम ID';
$string['invalidratearea']                      = 'अमान्य रेटिंग क्षेत्र';

// P1 #19 (2026-05-16) — email-on-response admin notification.
$string['messageprovider:evaluation_response']  = 'मूल्यांकन प्रतिक्रिया — व्यवस्थापक सूचना';

// CRUD strings.
$string['addevaluation']     = 'मूल्यांकन फॉर्म बनाएँ';
$string['editevaluation']    = 'मूल्यांकन फॉर्म संपादित करें';
$string['deleteevaluation']  = 'मूल्यांकन हटाएँ';
$string['publishevaluation'] = 'मूल्यांकन प्रकाशित करें';
$string['archiveevaluation'] = 'मूल्यांकन संग्रहीत करें';
$string['draftevaluation']   = 'ड्राफ्ट में ले जाएँ';

// Form section headings.
$string['heading_basic']       = 'फॉर्म पहचान';
$string['heading_kirkpatrick'] = 'मूल्यांकन फ्रेमवर्क';
$string['heading_trigger']     = 'कब भेजें';
$string['heading_privacy']     = 'गोपनीयता';
$string['heading_window']      = 'उपलब्धता अवधि (वैकल्पिक)';

// Form labels.
$string['eval_name']              = 'फॉर्म का नाम';
$string['description']            = 'विवरण';
$string['kirkpatrick_level']      = 'Kirkpatrick स्तर';
$string['kirkpatrick_level_help'] = 'Donald Kirkpatrick का 4-स्तरीय प्रशिक्षण मूल्यांकन मॉडल। स्तर 1 (प्रतिक्रिया) तुरंत भेजा जाता है; स्तर 3-4 (व्यवहार/परिणाम) के लिए सामान्यत: नौकरी पर लागू करने को मापने के लिए 30-90 दिनों की देरी चाहिए।';
$string['trigger_event']          = 'ट्रिगर इवेंट';
$string['days_after']             = 'ट्रिगर के बाद के दिन';
$string['days_after_help']        = 'मूल्यांकन भेजने से पहले ट्रिगर इवेंट के बाद कितने दिन प्रतीक्षा करनी है। 0 = तुरंत भेजें। सामान्य पैटर्न: 0 (स्तर 1), 7 (स्तर 2), 30-60 (स्तर 3), 90-180 (स्तर 4)।';
$string['organisation']           = 'संगठन (टेनेंट)';
$string['anonymous']              = 'प्रतिक्रियाएँ गुमनाम रूप से एकत्र करें';
$string['anonymous_help']         = 'चेक करने पर, प्रतिक्रियाएँ किसी विशिष्ट उपयोगकर्ता से जुड़ी नहीं होती हैं। POSH या संस्कृति सर्वेक्षण जैसे संवेदनशील विषयों के लिए सोशल डिज़ायरेबिलिटी बायस को कम करती है, लेकिन व्यक्तिगत उत्तरदाताओं के साथ फॉलो-अप को रोकती है।';
$string['status']                 = 'स्थिति';
$string['status_draft']           = 'मसौदा';
$string['status_active']          = 'सक्रिय';
$string['status_archived']        = 'संग्रहीत';

// Errors.
$string['missingrequiredfields']    = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['invalidkirkpatricklevel']  = 'अमान्य Kirkpatrick स्तर (1-4 होना चाहिए)।';
$string['invalidtrigger']           = 'अमान्य ट्रिगर इवेंट।';
$string['invalidstatus']            = 'अमान्य स्थिति मान।';
$string['days_after_invalid']       = 'दिन 0 या अधिक होने चाहिए।';
$string['confirmdelete']            = 'क्या आप मूल्यांकन "{$a}" हटाना चाहते हैं? यह फॉर्म, इसके सभी प्रश्न, और सभी सबमिट की गई प्रतिक्रियाएँ स्थायी रूप से हटा देगा। इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmpublish']           = '"{$a}" प्रकाशित करें? यह ट्रिगर इवेंट के आधार पर प्रतिक्रियाएँ एकत्र करना शुरू कर देगा।';
$string['confirmarchive']           = '"{$a}" संग्रहीत करें? यह नई प्रतिक्रियाएँ एकत्र करना बंद कर देगा लेकिन पिछला डेटा सुरक्षित रहेगा।';
$string['confirmdraft']             = '"{$a}" को वापस ड्राफ्ट में ले जाएँ? यह प्रतिक्रिया संग्रह को रोक देगा।';

// Success.
$string['evaluationcreated']        = 'मूल्यांकन फॉर्म बनाया गया।';
$string['evaluationupdated']        = 'मूल्यांकन फॉर्म अपडेट किया गया।';
$string['evaluationdeleted']        = 'मूल्यांकन हटा दिया गया।';
$string['evaluationstatuschanged']  = 'मूल्यांकन की स्थिति अपडेट हुई।';

// Question builder.
$string['managequestions']           = 'प्रश्न प्रबंधित करें';
$string['addquestion']               = 'प्रश्न जोड़ें';
$string['editquestion']              = 'प्रश्न संपादित करें';
$string['deletequestion']            = 'प्रश्न हटाएँ';
$string['question_type']             = 'प्रश्न प्रकार';
$string['question_type_help']        = 'चुनें कि सीखने वाले कैसे उत्तर देंगे। L1/L2 मूल्यांकन के लिए रेटिंग सबसे अच्छी है; एडवोकेसी के लिए NPS; फोर्स्ड-चॉइस परिदृश्यों के लिए multichoice; गुणात्मक प्रतिक्रिया के लिए text।';
$string['question_text']             = 'प्रश्न पाठ';
$string['question_options']          = 'उत्तर विकल्प (एक प्रति पंक्ति)';
$string['question_options_help']     = 'केवल मल्टीपल चॉइस प्रश्नों के लिए। प्रत्येक विकल्प को नई पंक्ति पर दर्ज करें। न्यूनतम 2 विकल्प आवश्यक हैं।';
$string['question_required']         = 'आवश्यक (सीखने वाले को उत्तर देना होगा)';

// Question errors.
$string['invalidquestiontype']      = 'अमान्य प्रश्न प्रकार।';
$string['invalidevaluation']        = 'मूल्यांकन फॉर्म नहीं मिला।';
$string['invalidquestion']          = 'प्रश्न नहीं मिला।';
$string['multichoice_needs_options'] = 'मल्टीपल चॉइस प्रश्नों के लिए कम से कम 2 विकल्प चाहिए।';
$string['confirmdeletequestion']    = 'इस प्रश्न को हटाएँ? पिछले सबमिशन से इसकी कोई भी प्रतिक्रिया भी हटा दी जाएगी।';

// Question success.
$string['questioncreated']  = 'प्रश्न जोड़ा गया।';
$string['questionupdated']  = 'प्रश्न अपडेट किया गया।';
$string['questiondeleted']  = 'प्रश्न हटा दिया गया।';
$string['orderupdated']     = 'प्रश्न का क्रम सहेजा गया।';

// G-05: Analysis dashboard + filtered responses + CSV export.
$string['analysis_title']             = 'मूल्यांकन विश्लेषण';
$string['analysis_subtitle']          = 'क्रॉस-मूल्यांकन Kirkpatrick एकत्रीकरण';
$string['filter_from']                = 'सबमिट किया गया (से)';
$string['filter_to']                  = 'सबमिट किया गया (तक)';
$string['filter_courseid']            = 'कोर्स ID';
$string['filter_programid']           = 'प्रोग्राम ID';
$string['filter_classroomid']         = 'क्लासरूम ID';
$string['filter_apply']               = 'फ़िल्टर लागू करें';
$string['filter_clear']               = 'साफ़ करें';
$string['filter_subset_hint']         = 'नीचे दिए गए आँकड़े फ़िल्टर किए गए सबसेट को दर्शाते हैं';
$string['export_csv']                 = 'CSV एक्सपोर्ट करें';
$string['no_responses_filtered']      = 'मौजूदा फ़िल्टर से कोई प्रतिक्रिया मेल नहीं खाती';
$string['no_responses_filtered_help'] = 'ऊपर दिए गए दिनांक सीमा या संदर्भ फ़िल्टर समायोजित करें, या सीखने वालों के मूल्यांकन सबमिट करने की प्रतीक्षा करें।';
$string['avg_rating']                 = 'औसत रेटिंग';
$string['nps_score']                  = 'NPS स्कोर';
$string['stat_evaluations']           = 'मूल्यांकन';
$string['stat_responses']             = 'प्रतिक्रियाएँ';

// Response submission (learner-facing) and admin viewer.
$string['viewresponses']            = 'प्रतिक्रियाएँ देखें';
$string['evaluationnotactive']      = 'यह मूल्यांकन वर्तमान में प्रतिक्रियाएँ स्वीकार नहीं कर रहा है।';
$string['alreadyresponded']         = 'आप पहले ही यह मूल्यांकन सबमिट कर चुके हैं।';
$string['evaluationhasnoquestions'] = 'इस मूल्यांकन में अभी कोई प्रश्न नहीं हैं।';

// P1 #17 (2026-05-16) — availability window + multiple-submit (pulse mode).
$string['timeopen']                = 'खुलने की तिथि';
$string['timeopen_help']           = 'वैकल्पिक। सबसे पहली तिथि जब सीखने वाले प्रतिक्रिया सबमिट कर सकते हैं। मूल्यांकन तुरंत खोलने के लिए चेकबॉक्स को अनटिक छोड़ दें। "1 जून से 30 जून तक कंप्लायंस सर्वे चलता है" जैसे वर्कफ़्लो के लिए इसका उपयोग करें।';
$string['timeclose']               = 'बंद होने की तिथि';
$string['timeclose_help']          = 'वैकल्पिक। वह तिथि जब मूल्यांकन नई प्रतिक्रियाएँ स्वीकार करना बंद कर देता है (मौजूदा प्रतिक्रियाएँ सुरक्षित रहती हैं)। मूल्यांकन को अनिश्चित काल तक खुला रखने के लिए अनटिक छोड़ दें। खुलने की तिथि के बाद या उसी दिन होना चाहिए।';
$string['multiple_submit']         = 'एक ही उपयोगकर्ता को कई प्रतिक्रियाएँ सबमिट करने दें';
$string['multiple_submit_help']    = 'पल्स-स्टाइल सर्वे की अनुमति देने के लिए टिक करें जहाँ एक ही सीखने वाला समय के साथ फिर से सबमिट करता है (साप्ताहिक एंगेजमेंट चेक, मासिक कंप्लायंस टिक, आदि)। अनटिक होने पर (डिफ़ॉल्ट), प्रत्येक उपयोगकर्ता को केवल एक सबमिशन मिलता है। गुमनाम मूल्यांकन हमेशा पुनः सबमिशन की अनुमति देते हैं, इस सेटिंग की परवाह किए बिना।';
$string['eval_window_inverted']    = 'बंद होने की तिथि खुलने की तिथि के बाद या उसी दिन होनी चाहिए।';
$string['evaluationnotyetopen']    = 'यह मूल्यांकन {$a} को खुलेगा — कृपया तब वापस आएँ।';
$string['evaluationclosed']        = 'यह मूल्यांकन {$a} को बंद हो गया।';
$string['eval_notyetopen_heading'] = 'अभी खुला नहीं है';
$string['eval_notyetopen_body']    = 'यह मूल्यांकन {$a} को खुलेगा। कृपया अपनी प्रतिक्रिया सबमिट करने के लिए तब वापस आएँ।';
$string['eval_closed_heading']     = 'मूल्यांकन बंद हो गया';
$string['eval_closed_body']        = 'यह मूल्यांकन {$a} को बंद हो गया। नई प्रतिक्रियाएँ अब स्वीकार नहीं की जातीं।';
$string['eval_pulse_hint']         = 'यह एक आवर्ती (पल्स) मूल्यांकन है — आप हर बार जब आते हैं, एक नई प्रतिक्रिया सबमिट कर सकते हैं।';

// P1 #18 (2026-05-16) — numeric + multi-select multichoice question types.
$string['numeric_min']                 = 'अनुमत न्यूनतम मान';
$string['numeric_min_help']            = 'वैकल्पिक। निचली सीमा न रखने के लिए खाली छोड़ें। सीखने वाला इसे इनपुट के <code>min</code> attribute के रूप में देखता है — इससे कम मान सबमिट समय पर अस्वीकार किए जाते हैं। उदाहरण: "गैर-नकारात्मक पूर्णांक" के लिए <code>0</code>, उम्र के लिए <code>18</code>।';
$string['numeric_max']                 = 'अनुमत अधिकतम मान';
$string['numeric_max_help']            = 'वैकल्पिक। ऊपरी सीमा न रखने के लिए खाली छोड़ें। यदि दोनों सेट हैं, तो न्यूनतम के बराबर या अधिक होना चाहिए। उदाहरण: प्रतिशत के लिए <code>100</code>, उम्र के लिए <code>120</code>।';
$string['numeric_must_be_integer']     = 'पूर्ण संख्या होनी चाहिए (जैसे 0, 5, 100)।';
$string['numeric_min_max_invalid']     = 'अधिकतम न्यूनतम के बराबर या उससे अधिक होना चाहिए।';
$string['invalid_numeric']             = 'इसके लिए उत्तर पूर्ण संख्या होना चाहिए: {$a}';
$string['invalid_numeric_below_min']   = '"{$a->q}" का उत्तर कम से कम {$a->min} होना चाहिए।';
$string['invalid_numeric_above_max']   = '"{$a->q}" का उत्तर अधिकतम {$a->max} होना चाहिए।';
$string['invalid_multichoice_multi']   = 'इसके लिए एक या अधिक चुने गए विकल्प मान्य नहीं हैं: {$a}';
$string['multichoice_multi_hint']      = 'जितने विकल्प लागू होते हैं, सभी पर टिक करें।';

// P1 #19 (2026-05-16) — admin notification on every response.
$string['heading_notifications']             = 'सूचनाएँ';
$string['notify_admin_on_response']          = 'हर प्रतिक्रिया पर साइट एडमिन को ईमेल करें';
$string['notify_admin_on_response_help']     = 'टिक करने पर, हर सफल सबमिशन सभी साइट एडमिन को एक Sentientia LMS सूचना भेजता है। वे अपनी सूचना प्राथमिकताओं में प्रति चैनल (ईमेल / पॉपअप / मोबाइल पुश) ऑप्ट आउट कर सकते हैं। कम-वॉल्यूम रणनीतिक सर्वे (C-suite पल्स, घटना-पश्चात डीब्रीफ) के लिए उपयोगी। उच्च-वॉल्यूम L1 प्रतिक्रिया फॉर्म के लिए बंद रखें — अन्यथा एडमिन मेल में डूब जाएँगे।';
$string['eval_response_subject']             = 'नई मूल्यांकन प्रतिक्रिया: {$a}';
$string['eval_response_small']               = 'नई प्रतिक्रिया: {$a}';
$string['eval_response_body_plain']          = 'मूल्यांकन "{$a->evalname}" के लिए एक नई प्रतिक्रिया सबमिट की गई।

उत्तरदाता: {$a->responder}

सभी प्रतिक्रियाएँ देखें: {$a->url}';
$string['eval_response_body_html']           = '<p>मूल्यांकन <strong>{$a->evalname}</strong> के लिए एक नई प्रतिक्रिया सबमिट की गई।</p><p>उत्तरदाता: {$a->responder}</p><p><a href="{$a->url}">इस मूल्यांकन के लिए सभी प्रतिक्रियाएँ देखें</a></p>';
$string['eval_response_responder_anonymous'] = '(गुमनाम)';
$string['eval_response_responder_unknown']   = '(अज्ञात — उपयोगकर्ता खाता हटा दिया गया)';

$string['answer_required']      = 'आवश्यक उत्तर गुम है: {$a}';
$string['invalid_rating']       = 'रेटिंग 1-5 होनी चाहिए: {$a}';
$string['invalid_nps']          = 'NPS स्कोर 0-10 होना चाहिए: {$a}';
$string['invalid_yesno']        = 'उत्तर हाँ या नहीं होना चाहिए: {$a}';
$string['invalid_multichoice']  = 'चुना गया विकल्प इसके लिए मान्य नहीं है: {$a}';
$string['please_answer_required'] = 'कृपया सबमिट करने से पहले सभी आवश्यक प्रश्नों का उत्तर दें।';
$string['response_submitted']   = 'धन्यवाद — आपकी प्रतिक्रिया दर्ज की गई है।';

// Privacy strings (Phase Z.1).
$string['privacy:metadata:responses']                = 'उपयोगकर्ता-सबमिट किए गए मूल्यांकन प्रतिक्रियाएँ (प्रति सबमिशन एक पंक्ति)।';
$string['privacy:metadata:responses:evaluationid']   = 'जिस मूल्यांकन फॉर्म का उत्तर दिया जा रहा है उसकी ID।';
$string['privacy:metadata:responses:userid']         = 'प्रतिक्रिया सबमिट करने वाले उपयोगकर्ता की ID (गुमनाम होने पर 0)।';
$string['privacy:metadata:responses:response_data']  = 'JSON-encoded उत्तर (questionid → उत्तर)।';
$string['privacy:metadata:responses:timesubmitted']  = 'सबमिशन टाइमस्टैम्प।';

// P1 #30 (2026-05-20) — conditional question display.
$string['heading_dependency']             = 'सशर्त प्रदर्शन (वैकल्पिक)';
$string['dep_none']                       = '— हमेशा दिखाएँ —';
$string['dep_parent']                     = 'यह प्रश्न केवल तब दिखाएँ जब…';
$string['dep_parent_help']                = 'एक पैरेंट प्रश्न चुनें। यह प्रश्न तभी दिखेगा जब पैरेंट का उत्तर दिया गया हो AND उसका उत्तर नीचे दिए गए मान से मेल खाता हो। सहेजते समय चक्र अवरुद्ध होते हैं।';
$string['dep_value']                      = '… पैरेंट का उत्तर मेल खाता हो';
$string['dep_value_help']                 = 'हाँ/नहीं पैरेंट के लिए, <code>yes</code> या <code>no</code> दर्ज करें। मल्टीचॉइस पैरेंट के लिए, विकल्पों में से एक दर्ज करें (केस-संवेदी)। रेटिंग / NPS / न्यूमेरिक पैरेंट के लिए, संख्यात्मक मान दर्ज करें। किसी भी गैर-खाली उत्तर पर ट्रिगर करने के लिए खाली छोड़ दें।';
$string['dep_invalid_parent']             = 'चुना गया पैरेंट प्रश्न मौजूद नहीं है।';
$string['dep_self_reference']             = 'कोई प्रश्न स्वयं पर निर्भर नहीं हो सकता।';
$string['dep_parent_other_evaluation']    = 'पैरेंट प्रश्न इस मूल्यांकन का हिस्सा नहीं है। एक सहोदर चुनें।';
$string['dep_cycle']                      = 'यह निर्भरता एक चक्र बनाएगी (पैरेंट अंततः इसी प्रश्न पर निर्भर हो जाता है)।';

// P1 #38 (2026-05-20) — show-non-respondents admin page.
$string['non_respondents_title']             = '{$a} — लंबित / पूर्ण';
$string['non_respondents_heading']           = '"{$a}" का उत्तर किसने दिया है?';
$string['non_respondents_subtitle']          = 'नियुक्त सीखने वाले और उनकी प्रतिक्रिया स्थिति। ऑटो-नियुक्तियाँ W1-5 ट्रिगर क्यू से आती हैं जब सीखने वाला योग्य कोर्स / प्रोग्राम / क्लासरूम पूरा करता है; मैन्युअल नियुक्तियाँ एडमिन UI के माध्यम से जोड़ी जा सकती हैं।';
$string['back_to_evaluations']               = 'मूल्यांकनों पर वापस जाएँ';
$string['non_respondents_tab_pending']       = 'लंबित';
$string['non_respondents_tab_responded']     = 'उत्तर दिया';
$string['non_respondents_col_name']          = 'नाम';
$string['non_respondents_col_email']         = 'ईमेल';
$string['non_respondents_col_trigger']       = 'नियुक्त के द्वारा';
$string['non_respondents_col_assigned']      = 'नियुक्ति';
$string['non_respondents_col_due']           = 'देय तिथि';
$string['non_respondents_col_responded']     = 'उत्तर दिया गया';
$string['non_respondents_trigger_manual']    = 'मैन्युअल';
$string['non_respondents_trigger_course']    = 'कोर्स';
$string['non_respondents_trigger_program']   = 'प्रोग्राम';
$string['non_respondents_trigger_classroom'] = 'क्लासरूम';
$string['non_respondents_empty_pending_heading']   = 'सभी ने उत्तर दे दिया है।';
$string['non_respondents_empty_pending_body']      = 'या तो हर नियुक्त सीखने वाले ने यह मूल्यांकन भर दिया है, या अभी तक कोई नियुक्ति दर्ज नहीं हुई है।';
$string['non_respondents_empty_responded_heading'] = 'अभी तक कोई उत्तर नहीं।';
$string['non_respondents_empty_responded_body']    = 'जब नियुक्त सीखने वाले अपनी प्रतिक्रियाएँ सबमिट करेंगे, वे यहाँ दिखाई देंगे।';

// P1 #39 (2026-05-20) — bulk-assign by audience.
$string['filterstoolong']             = 'फ़िल्टर payload बहुत लंबा है।';
$string['bulk_assign_modal_title']    = 'लक्षित दर्शकों द्वारा बल्क-नियुक्त करें';
$string['bulk_assign_form_intro']     = 'यूज़र्स के समूह को लक्षित करने के लिए एक या अधिक फ़िल्टर मानदंड चुनें। फ़िल्टर बदलने पर नीचे पूर्वावलोकन अपडेट होता है। "मिलते यूज़र्स को नियुक्त करें" पर क्लिक करें — पहले से नियुक्त सीखने वाले चुपचाप डिडुप होते हैं।';
$string['bulk_assign_button']         = 'मिलते यूज़र्स को नियुक्त करें';
$string['bulk_assign_pick_at_least_one'] = 'कम से कम एक फ़िल्टर मानदंड चुनें।';
$string['bulk_assign_result']         = '{$a->assigned} नई नियुक्ति; {$a->matched} यूज़र मिले ({$a->existing} पहले से नियुक्त)।';
$string['bulk_assign_capped']         = 'दर्शक आकार {$a} की सीमा पर पहुँच गया। अधिक यूज़र्स नियुक्त करने के लिए अपना फ़िल्टर परिष्कृत करें।';

// P1 #40 (2026-05-20) — modal form labels.
$string['audience_any']           = 'कोई भी';
$string['audience_any_cohort']    = 'कोई भी कोहोर्ट';
$string['audience_users_matched'] = 'यूज़र इस दर्शकों से मेल खाते हैं';
$string['audience_designation']   = 'पद';
$string['audience_region']        = 'क्षेत्र';
$string['audience_location']      = 'स्थान';
$string['audience_employmenttype'] = 'रोज़गार प्रकार';
$string['audience_cohort']        = 'कोहोर्ट';

// P1 #41 (2026-05-20) — template library.
$string['template_name_required']        = 'टेम्पलेट नाम आवश्यक है।';
$string['template_payload_corrupt']      = 'इस टेम्पलेट का payload डिकोड नहीं हो सका। पंक्ति Sentientia LMS के बाहर संपादित की गई हो सकती है। हटाएँ और पुनः सहेजें।';
$string['template_saved']                = 'टेम्पलेट सहेज लिया गया।';
$string['template_deleted']              = 'टेम्पलेट हटा दिया गया।';
$string['template_save_modal_title']     = 'टेम्पलेट के रूप में सहेजें';
$string['template_picker_modal_title']   = 'टेम्पलेट से बनाएँ';
$string['template_name_label']           = 'टेम्पलेट का नाम';
$string['template_desc_label']           = 'संक्षिप्त विवरण (वैकल्पिक)';
$string['template_ispublic']             = 'इस टेम्पलेट को अन्य टेनेंट्स के लिए उपलब्ध कराएँ';
$string['template_ispublic_help']        = 'जब चेक किया जाता है, अन्य टेनेंट्स के एडमिन मूल्यांकन बनाते समय इस टेम्पलेट को चुन सकते हैं। HQ-क्यूरेटेड फॉर्म (POSH, AML, anti-bribery) के लिए उपयोगी।';

// P1 #42 (2026-05-20) — auto-expire overdue assignments cron.
$string['task_expire_assignments'] = 'अतिदेय मूल्यांकन नियुक्तियाँ स्वत: समाप्त करें';
