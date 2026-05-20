<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #15 (2026-05-16) — Hindi (hi) translations for local_airpay_programs.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे सर्टिफिकेशन प्रोग्राम';

// Capabilities.
$string['airpay_programs:view']   = 'प्रोग्राम देखें';
$string['airpay_programs:create'] = 'प्रोग्राम बनाएँ';
$string['airpay_programs:update'] = 'प्रोग्राम अपडेट करें';
$string['airpay_programs:delete'] = 'प्रोग्राम हटाएँ';
$string['airpay_programs:enrol']  = 'यूज़र्स को प्रोग्राम में नामांकित करें';

// Form section headings.
$string['heading_basic']      = 'प्रोग्राम विवरण';
$string['heading_org']        = 'संगठन';
$string['heading_completion'] = 'समापन नियम';
$string['heading_window']     = 'नामांकन अवधि (वैकल्पिक)';
$string['heading_status']     = 'स्थिति';

// Field labels.
$string['name']         = 'प्रोग्राम का नाम';
$string['description']  = 'विवरण';
$string['organisation'] = 'संगठन (टेनेंट)';
$string['status']       = 'स्थिति';
$string['startdate']    = 'प्रारंभ तिथि';
$string['enddate']      = 'समाप्ति तिथि';
$string['startdate_help'] = 'वैकल्पिक। इस तिथि से प्रोग्राम में नामांकन उपलब्ध होगा।';
$string['enddate_help']   = 'वैकल्पिक। इस तिथि के बाद नए नामांकन बंद हो जाएँगे।';
$string['enddate_before_start'] = 'समाप्ति तिथि प्रारंभ तिथि के बाद या उसी दिन होनी चाहिए।';

// Completion rules.
$string['completion_rule']      = 'समापन नियम';
$string['completion_all_levels'] = 'सभी स्तर पूरा करें';
$string['completion_any_level'] = 'कोई भी स्तर पूरा करें';

// Status values.
$string['status_draft']     = 'मसौदा';
$string['status_active']    = 'सक्रिय';
$string['status_archived']  = 'संग्रहीत';

// Users tab.
$string['enrol_users']  = 'यूज़र्स नामांकित करें';

// P1 #14 (2026-05-16) — bulk-enrol-by-audience modal.
$string['audience_modal_title']       = 'लक्षित दर्शकों द्वारा बल्क नामांकन';
$string['audience_form_intro']        = 'यूज़र्स के समूह को लक्षित करने के लिए एक या अधिक फ़िल्टर मानदंड चुनें।';
$string['audience_any']               = 'कोई भी';
$string['audience_any_cohort']        = 'कोई भी कोहोर्ट';
$string['audience_users_matched']     = 'यूज़र्स मिलते हैं';
$string['audience_pick_at_least_one'] = 'कम से कम एक फ़िल्टर चुनें।';
$string['audience_enrol_button']      = 'मिलने वाले यूज़र्स नामांकित करें';
$string['audience_enrol_result']      = '%d नए नामांकन; %d यूज़र दर्शकों में मिले।';
$string['designation']                = 'पद';
$string['region']                     = 'क्षेत्र';
$string['location']                   = 'स्थान';
$string['employmenttype']             = 'रोज़गार प्रकार';
$string['cohort']                     = 'कोहोर्ट';

// Privacy metadata.
$string['privacy:metadata'] = 'एयरपे प्रोग्राम प्लगइन प्रोग्राम-स्तर नामांकन और स्तर-वार प्रगति संग्रहीत करता है।';

// P1 #45 (2026-05-20) — Hindi top-up: 65 strings covering CRUD,
// levels, courses, enrolment, view tabs, privacy metadata.
$string['addprogram']            = 'प्रोग्राम जोड़ें';
$string['editprogram']           = 'प्रोग्राम संपादित करें';
$string['deleteprogram']         = 'प्रोग्राम हटाएँ';
$string['publishprogram']        = 'प्रोग्राम प्रकाशित करें';
$string['archiveprogram']        = 'प्रोग्राम संग्रहीत करें';
$string['draftprogram']          = 'ड्राफ्ट में ले जाएँ';
$string['airpay_programs:manage'] = 'प्रोग्राम प्रबंधित करें';

$string['missingrequiredfields'] = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['invalidstatus']         = 'अमान्य स्थिति।';
$string['toomanycourses']        = 'एक अनुरोध में बहुत अधिक कोर्स।';
$string['toomanylevels']         = 'एक अनुरोध में बहुत अधिक स्तर।';
$string['toomanyusers']          = 'एक अनुरोध में बहुत अधिक उपयोगकर्ता।';
$string['confirmdelete']         = 'क्या आप वाकई प्रोग्राम "{$a}" हटाना चाहते हैं?';
$string['confirmpublish']        = '"{$a}" प्रकाशित करें?';
$string['confirmarchive']        = '"{$a}" संग्रहीत करें?';
$string['confirmdraft']          = '"{$a}" को वापस ड्राफ्ट में ले जाएँ?';
$string['confirm_delete_level']   = 'स्तर "{$a}" हटाएँ?';
$string['confirm_unassign_course'] = 'इस प्रोग्राम से कोर्स हटाएँ?';
$string['confirm_unenrol_user']   = '{$a} को इस प्रोग्राम से हटाएँ?';

$string['programcreated']         = 'प्रोग्राम बनाया गया।';
$string['programupdated']         = 'प्रोग्राम अपडेट किया गया।';
$string['programdeleted']         = 'प्रोग्राम हटा दिया गया।';
$string['programstatuschanged']   = 'प्रोग्राम स्थिति बदली गई।';
$string['levelcreated']           = 'स्तर बनाया गया।';
$string['levelupdated']           = 'स्तर अपडेट किया गया।';
$string['leveldeleted']           = 'स्तर हटा दिया गया।';
$string['courseassigned']         = 'कोर्स असाइन किया गया।';
$string['courseunassigned']       = 'कोर्स हटा दिया गया।';
$string['userunenrolled']         = 'उपयोगकर्ता हटा दिया गया।';
$string['users_enrolled_success'] = '{$a->count} उपयोगकर्ता नामांकित किए गए।';

$string['view_program_title']     = 'प्रोग्राम: {$a}';
$string['back_to_program']        = 'प्रोग्राम पर वापस जाएँ';
$string['back_to_programs']       = 'सभी प्रोग्रामों पर वापस जाएँ';
$string['tab_overview']           = 'सिंहावलोकन';
$string['tab_levels']             = 'स्तर';
$string['tab_users']              = 'उपयोगकर्ता';
$string['no_description']         = 'कोई विवरण सेट नहीं है।';
$string['no_levels']              = 'अभी तक कोई स्तर नहीं बनाया गया।';
$string['no_courses_assigned']    = 'इस प्रोग्राम में अभी तक कोई कोर्स असाइन नहीं किया गया।';
$string['no_users_enrolled']      = 'अभी तक कोई उपयोगकर्ता नामांकित नहीं है।';
$string['add_courses']            = 'कोर्स जोड़ें';
$string['unenrol_user']           = 'उपयोगकर्ता हटाएँ';
$string['updated']                = 'अपडेट किया गया';
$string['courses_assigned_count'] = '{$a} कोर्स असाइन किए गए';
$string['enrolled_count_label']   = 'नामांकित: {$a}';
$string['levels_count_label']     = 'स्तर: {$a}';

$string['add_level']              = 'स्तर जोड़ें';
$string['edit_level']             = 'स्तर संपादित करें';
$string['delete_level']           = 'स्तर हटाएँ';
$string['manage_level_courses']   = 'स्तर के कोर्स प्रबंधित करें';
$string['level_name']             = 'स्तर का नाम';
$string['level_description']      = 'स्तर का विवरण';
$string['level_position']         = 'क्रम स्थिति';
$string['level_required']         = 'आवश्यक';
$string['level_optional']         = 'वैकल्पिक';
$string['level_completion']       = 'समापन नियम';
$string['level_courses']          = 'स्तर के कोर्स';

$string['event_program_completed'] = 'एयरपे: प्रोग्राम पूर्ण किया गया';

$string['privacy:metadata:enrol']               = 'प्रति उपयोगकर्ता प्रोग्राम नामांकन।';
$string['privacy:metadata:enrol:programid']     = 'प्रोग्राम ID।';
$string['privacy:metadata:enrol:userid']        = 'नामांकित उपयोगकर्ता ID।';
$string['privacy:metadata:enrol:currentlevelid'] = 'वर्तमान स्तर ID।';
$string['privacy:metadata:enrol:status']        = 'नामांकन स्थिति।';
$string['privacy:metadata:enrol:timecreated']   = 'नामांकन की तिथि।';
$string['privacy:metadata:enrol:timecompleted'] = 'पूर्णता की तिथि।';
