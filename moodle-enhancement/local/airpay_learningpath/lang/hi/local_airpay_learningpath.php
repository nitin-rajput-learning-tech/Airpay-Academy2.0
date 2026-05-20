<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #12 (2026-05-16) — Hindi (hi) translations for local_airpay_learningpath.
//
// Scope: learner-facing labels + admin form labels that frequently appear
// in mixed Hindi/English screenshots. The bulk-enrol modal (admin-only)
// is translated because Airpay's L&D team uses it daily.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे लर्निंग पाथ';

// Capabilities.
$string['airpay_learningpath:manage'] = 'लर्निंग पाथ प्रबंधित करें';
$string['airpay_learningpath:view']   = 'लर्निंग पाथ देखें';
$string['airpay_learningpath:enrol']  = 'यूज़र्स को पाथ में नामांकित करें';
$string['airpay_learningpath:create'] = 'लर्निंग पाथ बनाएँ';
$string['airpay_learningpath:update'] = 'लर्निंग पाथ अपडेट करें';
$string['airpay_learningpath:delete'] = 'लर्निंग पाथ हटाएँ';

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
