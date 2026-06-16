<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #12 (2026-05-16) — Hindi (hi) translations for local_sentientia_learningpath.
//
// Scope: learner-facing labels + admin form labels that frequently appear
// in mixed Hindi/English screenshots. The bulk-enrol modal (admin-only)
// is translated because Airpay's L&D team uses it daily.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे लर्निंग पाथ';

// Capabilities.
$string['sentientia_learningpath:manage'] = 'लर्निंग पाथ प्रबंधित करें';
$string['sentientia_learningpath:view']   = 'लर्निंग पाथ देखें';
$string['sentientia_learningpath:enrol']  = 'यूज़र्स को पाथ में नामांकित करें';
$string['sentientia_learningpath:create'] = 'लर्निंग पाथ बनाएँ';
$string['sentientia_learningpath:update'] = 'लर्निंग पाथ अपडेट करें';
$string['sentientia_learningpath:delete'] = 'लर्निंग पाथ हटाएँ';

// Form section headings.
$string['heading_basic']   = 'पाथ विवरण';
$string['heading_org']     = 'संगठन';
$string['heading_window']  = 'नामांकन अवधि (वैकल्पिक)';
$string['heading_status']  = 'स्थिति';

// Field labels.
$string['name']         = 'पाथ का नाम';
$string['description']  = 'विवरण';
$string['organisation'] = 'संगठन (टेनेंट)';
$string['status']       = 'स्थिति';
$string['startdate']    = 'प्रारंभ तिथि';
$string['enddate']      = 'समाप्ति तिथि';
$string['startdate_help'] = 'वैकल्पिक। इस तिथि से पाथ में नामांकन उपलब्ध होगा। तत्काल नामांकन के लिए खाली छोड़ें।';
$string['enddate_help']   = 'वैकल्पिक। इस तिथि के बाद नए नामांकन बंद हो जाएँगे। मौजूदा नामांकित शिक्षार्थी प्रभावित नहीं होते।';
$string['enddate_before_start'] = 'समाप्ति तिथि प्रारंभ तिथि के बाद या उसी दिन होनी चाहिए।';

// Status values.
$string['status_active']   = 'सक्रिय';
$string['status_archived'] = 'संग्रहीत';

// Action labels.
$string['add_courses']  = 'कोर्स जोड़ें';
$string['enrol_users']  = 'यूज़र्स नामांकित करें';
$string['pathcreated']  = 'पाथ बना दिया गया।';
$string['pathupdated']  = 'पाथ अपडेट कर दिया गया।';

// P1 #11 (2026-05-16) — bulk-enrol-by-audience modal.
$string['audience_modal_title']       = 'लक्षित दर्शकों द्वारा बल्क नामांकन';
$string['audience_form_intro']        = 'यूज़र्स के समूह को लक्षित करने के लिए एक या अधिक फ़िल्टर मानदंड चुनें। फ़िल्टर बदलते ही नीचे पूर्वावलोकन अपडेट होगा।';
$string['audience_any']               = 'कोई भी';
$string['audience_any_cohort']        = 'कोई भी कोहोर्ट';
$string['audience_users_matched']     = 'यूज़र्स मिलते हैं';
$string['audience_pick_at_least_one'] = 'कम से कम एक फ़िल्टर चुनें (सभी यूज़र्स को नामांकित करने के लिए सामान्य Enrol Users फ़ॉर्म का उपयोग करें)।';
$string['audience_enrol_button']      = 'मिलने वाले यूज़र्स नामांकित करें';
$string['audience_enrol_result']      = '%d नए नामांकन; %d यूज़र दर्शकों में मिले।';
$string['designation']                = 'पद';
$string['region']                     = 'क्षेत्र';
$string['location']                   = 'स्थान';
$string['employmenttype']             = 'रोज़गार प्रकार';
$string['cohort']                     = 'कोहोर्ट';

// Privacy metadata.
$string['privacy:metadata'] = 'एयरपे लर्निंग पाथ प्लगइन उपयोगकर्ता के पाथ-स्तर नामांकन और प्रगति को संग्रहीत करता है।';

// P1 #46 (2026-05-20) — Hindi top-up: 30 strings covering CRUD,
// confirms, view tabs, errors, privacy metadata.
$string['addpath']        = 'लर्निंग पाथ जोड़ें';
$string['editpath']       = 'लर्निंग पाथ संपादित करें';
$string['deletepath']     = 'लर्निंग पाथ हटाएँ';
$string['archivepath']    = 'पाथ संग्रहीत करें';
$string['activatepath']   = 'पाथ सक्रिय करें';

$string['organisation_help'] = 'चुनें कि यह लर्निंग पाथ किस टेनेंट से संबंधित है। सभी टेनेंट के लिए उपलब्ध रखने हेतु "कोई विशिष्ट संगठन नहीं" चुनें।';

$string['missingrequiredfields'] = 'आवश्यक फ़ील्ड गुम हैं।';
$string['confirmdelete']    = 'क्या आप वाकई "{$a}" हटाना चाहते हैं? यह पाथ, इसके सभी कोर्स असाइनमेंट और शिक्षार्थी नामांकन स्थायी रूप से हटा दिए जाएँगे। इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmarchive']   = 'क्या आप वाकई "{$a}" संग्रहीत करना चाहते हैं? यह नए शिक्षार्थियों के लिए उपलब्ध नहीं रहेगा।';
$string['confirmactivate']  = '"{$a}" को सक्रिय बनाएँ? शिक्षार्थी पुनः नामांकित हो सकेंगे।';

$string['pathdeleted']         = 'लर्निंग पाथ हटा दिया गया।';
$string['pathstatuschanged']   = 'स्थिति अपडेट की गई।';

$string['toomanycourses']   = 'एक अनुरोध में बहुत अधिक कोर्स। प्रति कॉल सीमा 100 है।';
$string['toomanyusers']     = 'एक अनुरोध में बहुत अधिक उपयोगकर्ता। प्रति कॉल सीमा 500 है।';
$string['filterstoolong']   = 'फ़िल्टर पेलोड बहुत लंबा है।';

$string['view_path_title']  = 'लर्निंग पाथ: {$a}';
$string['tab_overview']     = 'सिंहावलोकन';
$string['tab_courses']      = 'कोर्स';
$string['tab_users']        = 'उपयोगकर्ता';
$string['back_to_paths']    = 'लर्निंग पाथ पर वापस जाएँ';

$string['confirm_unassign_course'] = '"{$a}" को इस लर्निंग पाथ से हटाएँ? उपयोगकर्ता अपनी कोर्स पूर्णता बनाए रखेंगे।';
$string['confirm_unenrol_user']    = '{$a} को इस लर्निंग पाथ से हटाएँ?';

$string['no_courses_assigned']  = 'अभी तक कोई कोर्स असाइन नहीं किया गया। प्रारंभ करने के लिए "कोर्स जोड़ें" पर क्लिक करें।';
$string['no_users_enrolled']    = 'अभी तक कोई उपयोगकर्ता नामांकित नहीं है। शिक्षार्थियों को जोड़ने के लिए "यूज़र्स नामांकित करें" पर क्लिक करें।';

$string['privacy:metadata:lp']              = 'प्रति लर्निंग पाथ उपयोगकर्ता असाइनमेंट।';
$string['privacy:metadata:lp:pathid']       = 'लर्निंग पाथ ID।';
$string['privacy:metadata:lp:userid']       = 'असाइन किए गए उपयोगकर्ता की ID।';
$string['privacy:metadata:lp:status']       = 'असाइनमेंट स्थिति।';
$string['privacy:metadata:lp:timecreated']  = 'असाइनमेंट टाइमस्टैम्प।';
$string['privacy:metadata:lp:timemodified'] = 'अंतिम अपडेट टाइमस्टैम्प।';

// P0.2 (2026-06-16) — अनुकूली लर्निंग जर्नी स्ट्रिंग्स।
$string['task_adaptive_sweep']          = 'अनुकूली जर्नी वेलोसिटी स्वीप';
$string['adaptive_mode']                = 'अनुकूली मोड';
$string['adaptive_mode_help']           = 'जब सक्षम हो, यह पाथ क्विज़ स्कोर, पूर्णता गति और कौशल-अंतराल संकेतों के आधार पर स्वचालित रूप से शाखाओं में बँटता है, तेज़ करता है या उपचारात्मक कदम उठाता है।';
$string['adaptive_mode_off']            = 'स्थैतिक (क्रमिक)';
$string['adaptive_mode_on']             = 'अनुकूली (प्रदर्शन-आधारित)';
$string['score_threshold_low']          = 'उपचारात्मक सीमा (%)';
$string['score_threshold_low_help']     = 'इस प्रतिशत पर या उससे कम क्विज़ स्कोर उपचारात्मक शाखा को सक्रिय करते हैं। सिस्टम डिफ़ॉल्ट (50%) उपयोग करने के लिए खाली छोड़ें।';
$string['score_threshold_high']         = 'त्वरण सीमा (%)';
$string['score_threshold_high_help']    = 'इस प्रतिशत पर या उससे अधिक क्विज़ स्कोर, उच्च पूर्णता गति के साथ, त्वरण शाखा को सक्रिय करते हैं। सिस्टम डिफ़ॉल्ट (80%) उपयोग करने के लिए खाली छोड़ें।';
$string['pivot_remediate']              = 'उपचारात्मक';
$string['pivot_accelerate']             = 'त्वरित';
$string['pivot_branch']                 = 'शाखित';
$string['pivot_no_action']              = 'कोई कार्रवाई नहीं';
$string['trigger_quiz_score']           = 'क्विज़ स्कोर';
$string['trigger_velocity']             = 'पूर्णता गति';
$string['trigger_skills_gap']           = 'कौशल अंतराल';
$string['trigger_combined']             = 'संयुक्त संकेत';
$string['adaptive_log_empty']           = 'इस पाथ के लिए अभी तक कोई अनुकूली निर्णय दर्ज नहीं किया गया।';
$string['adaptive_log_title']           = 'अनुकूली निर्णय लॉग';
$string['adaptive_feature_flag_off']    = 'अनुकूली लर्निंग जर्नी सुविधा वर्तमान में अक्षम है। इसे सक्रिय करने के लिए Switchboard में "sentientia.learningpath.adaptive.enabled" फ़्लैग सक्षम करें।';
$string['privacy:metadata:lp_adaptive_log']              = 'लर्निंग पाथ के लिए अनुकूली जर्नी निर्णय लॉग।';
$string['privacy:metadata:lp_adaptive_log:userid']       = 'वह उपयोगकर्ता जिसका पाथ परिवर्तित किया गया।';
$string['privacy:metadata:lp_adaptive_log:pathid']       = 'वह लर्निंग पाथ जिस पर निर्णय लिया गया।';
$string['privacy:metadata:lp_adaptive_log:pivot_type']   = 'लागू किए गए परिवर्तन का प्रकार।';
$string['privacy:metadata:lp_adaptive_log:quiz_score']   = 'परिवर्तन को ट्रिगर करने वाला क्विज़ स्कोर (यदि लागू हो)।';
$string['privacy:metadata:lp_adaptive_log:timecreated']  = 'अनुकूली निर्णय कब दर्ज किया गया।';
