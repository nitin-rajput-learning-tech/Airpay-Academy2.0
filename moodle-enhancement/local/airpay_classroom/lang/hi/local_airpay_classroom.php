<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #15 (2026-05-16) — Hindi (hi) translations for local_airpay_classroom.
// Scope: form labels, status pills, bulk-enrol modal, session UI.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे क्लासरूम';

// Capabilities.
$string['airpay_classroom:view']       = 'क्लासरूम देखें';
$string['airpay_classroom:create']     = 'क्लासरूम बनाएँ';
$string['airpay_classroom:update']     = 'क्लासरूम अपडेट करें';
$string['airpay_classroom:delete']     = 'क्लासरूम हटाएँ';
$string['airpay_classroom:enrol']      = 'यूज़र्स को क्लासरूम में नामांकित करें';
$string['airpay_classroom:attendance'] = 'उपस्थिति प्रबंधित करें';

// Form section headings.
$string['heading_basic']      = 'बुनियादी जानकारी';
$string['heading_logistics']  = 'लॉजिस्टिक्स';
$string['heading_org']        = 'संगठन';
$string['heading_window']     = 'नामांकन अवधि (वैकल्पिक)';
$string['heading_status']     = 'स्थिति';

// Field labels.
$string['name']               = 'क्लासरूम का नाम';
$string['organisation']       = 'संगठन (टेनेंट)';
$string['status']             = 'स्थिति';
$string['startdate']          = 'प्रारंभ तिथि';
$string['enddate']            = 'समाप्ति तिथि';
$string['startdate_help']     = 'वैकल्पिक। इस तिथि से क्लासरूम में नामांकन उपलब्ध होगा।';
$string['enddate_help']       = 'वैकल्पिक। इस तिथि के बाद नए नामांकन बंद हो जाएँगे।';
$string['enddate_before_start'] = 'समाप्ति तिथि प्रारंभ तिथि के बाद या उसी दिन होनी चाहिए।';

// Status values.
$string['status_active']    = 'सक्रिय';
$string['status_completed'] = 'पूर्ण';
$string['status_cancelled'] = 'रद्द';

// Sessions tab.
$string['add_session']           = 'सत्र जोड़ें';
$string['edit_session']          = 'सत्र संपादित करें';
$string['delete_session']        = 'सत्र हटाएँ';
$string['session_title']         = 'सत्र शीर्षक';
$string['session_starttime']     = 'प्रारंभ समय';
$string['session_endtime']       = 'समाप्ति समय';
$string['session_location']      = 'सत्र स्थान';
$string['session_meeting_url']   = 'लाइव मीटिंग URL';
$string['session_recording_url'] = 'रिकॉर्डिंग URL';

// Users tab.
$string['enrol_users']        = 'यूज़र्स नामांकित करें';

// P1 #13 (2026-05-16) — bulk-enrol-by-audience modal.
$string['audience_modal_title']       = 'लक्षित दर्शकों द्वारा बल्क नामांकन';
$string['audience_form_intro']        = 'यूज़र्स के समूह को लक्षित करने के लिए एक या अधिक फ़िल्टर मानदंड चुनें। फ़िल्टर बदलते ही नीचे पूर्वावलोकन अपडेट होगा।';
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
$string['privacy:metadata'] = 'एयरपे क्लासरूम प्लगइन क्लासरूम नामांकन, सत्र, और उपस्थिति डेटा संग्रहीत करता है।';

// P1 #44 (2026-05-20) — Hindi top-up. 74 strings translated covering
// CRUD, sessions, attendance, view tabs, privacy metadata.
$string['addclassroom']     = 'क्लासरूम जोड़ें';
$string['editclassroom']    = 'क्लासरूम संपादित करें';
$string['deleteclassroom']  = 'क्लासरूम हटाएँ';
$string['cancelclassroom']  = 'क्लासरूम रद्द करें';
$string['completeclassroom'] = 'क्लासरूम पूरा करें';
$string['reopenclassroom']  = 'क्लासरूम पुनः खोलें';
$string['airpay_classroom:manage'] = 'क्लासरूम और सत्र प्रबंधित करें';

$string['description']      = 'विवरण';
$string['capacity']         = 'क्षमता';
$string['trainer']          = 'ट्रेनर';

$string['missingrequiredfields']  = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['capacityinvalid']        = 'क्षमता एक धनात्मक संख्या होनी चाहिए।';
$string['endbeforestart']         = 'समाप्ति तिथि प्रारंभ तिथि के बाद होनी चाहिए।';
$string['invalidstatus']          = 'अमान्य स्थिति।';
$string['invalidsessiontime']     = 'अमान्य सत्र समय।';
$string['invalidattendancestatus'] = 'अमान्य उपस्थिति स्थिति।';
$string['toomanymarks']           = 'एक अनुरोध में बहुत अधिक उपस्थिति अंक।';
$string['confirmdelete']          = 'क्या आप वाकई "{$a}" हटाना चाहते हैं? इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmcancel']          = 'क्या आप वाकई "{$a}" रद्द करना चाहते हैं?';
$string['confirmcomplete']        = 'क्या आप वाकई "{$a}" को पूर्ण के रूप में चिह्नित करना चाहते हैं?';
$string['confirmreopen']          = '"{$a}" पुनः खोलें? यह फिर से नामांकन स्वीकार करेगा।';
$string['confirm_delete_session'] = 'सत्र "{$a}" हटाएँ? उपस्थिति रिकॉर्ड भी हटा दिए जाएँगे।';
$string['confirm_unenrol_user']   = '{$a} को इस क्लासरूम से हटाएँ?';

$string['classroomcreated']       = 'क्लासरूम बनाया गया।';
$string['classroomupdated']       = 'क्लासरूम अपडेट किया गया।';
$string['classroomdeleted']       = 'क्लासरूम हटा दिया गया।';
$string['classroomstatuschanged'] = 'क्लासरूम की स्थिति बदली गई।';
$string['session_created']        = 'सत्र बनाया गया।';
$string['session_updated']        = 'सत्र अपडेट किया गया।';
$string['sessiondeleted']         = 'सत्र हटा दिया गया।';
$string['userunenrolled']         = 'उपयोगकर्ता हटा दिया गया।';
$string['attendancemarked']       = 'उपस्थिति दर्ज की गई।';

$string['view_classroom_title']   = 'क्लासरूम: {$a}';
$string['back_to_classrooms']     = 'क्लासरूम पर वापस जाएँ';
$string['tab_overview']           = 'सिंहावलोकन';
$string['tab_sessions']           = 'सत्र';
$string['tab_users']              = 'उपयोगकर्ता';
$string['tab_attendance']         = 'उपस्थिति';
$string['no_description']         = 'कोई विवरण सेट नहीं है।';
$string['no_sessions']            = 'अभी तक कोई सत्र निर्धारित नहीं किया गया है।';
$string['no_users_enrolled']      = 'अभी तक कोई उपयोगकर्ता नामांकित नहीं है।';
$string['no_attendance_yet']      = 'इस सत्र के लिए अभी तक कोई उपस्थिति दर्ज नहीं की गई है।';
$string['unenrol_user']           = 'उपयोगकर्ता हटाएँ';
$string['updated']                = 'अपडेट किया गया';
$string['users_enrolled_count']   = '{$a} उपयोगकर्ता नामांकित';
$string['users_enrolled_success'] = '{$a->count} उपयोगकर्ता नामांकित किए गए।';

$string['session_date']            = 'सत्र की तिथि';
$string['session_time']            = 'समय';
$string['session_duration']        = 'अवधि';
$string['session_minutes']         = 'मिनट';
$string['session_trainer']         = 'सत्र ट्रेनर';
$string['session_notes']           = 'सत्र नोट्स';
$string['session_virtual_header']  = 'वर्चुअल मीटिंग लिंक (वैकल्पिक)';
$string['session_meeting_url_help'] = 'वैकल्पिक। Zoom / Teams / Meet लिंक — सीखने वाले इसे सत्र के दिन देखेंगे।';
$string['session_recording_url_help'] = 'वैकल्पिक। सत्र के बाद रिकॉर्डिंग URL जोड़ें।';

$string['attendance_for_session']     = 'सत्र के लिए उपस्थिति: {$a}';
$string['attendance_summary']         = 'उपस्थिति सारांश';
$string['attendance_status_present']  = 'उपस्थित';
$string['attendance_status_absent']   = 'अनुपस्थित';
$string['attendance_status_late']     = 'देर से';
$string['attendance_status_excused']  = 'क्षमा प्राप्त';
$string['mark_all_present']           = 'सभी को उपस्थित चिह्नित करें';
$string['save_attendance']            = 'उपस्थिति सहेजें';

$string['event_classroom_completed'] = 'एयरपे: क्लासरूम पूर्ण किया गया';

$string['privacy:metadata:roster']             = 'क्लासरूम नामांकन रोस्टर।';
$string['privacy:metadata:roster:classroomid'] = 'जिस क्लासरूम में उपयोगकर्ता नामांकित है उसकी ID।';
$string['privacy:metadata:roster:userid']      = 'नामांकित उपयोगकर्ता की ID।';
$string['privacy:metadata:roster:timecreated'] = 'नामांकन की तिथि।';
$string['privacy:metadata:attendance']          = 'प्रति सत्र उपस्थिति रिकॉर्ड।';
$string['privacy:metadata:attendance:sessionid'] = 'सत्र ID।';
$string['privacy:metadata:attendance:userid']    = 'उपयोगकर्ता ID।';
$string['privacy:metadata:attendance:status']    = 'उपस्थिति स्थिति।';
$string['privacy:metadata:attendance:markedat']  = 'जब उपस्थिति दर्ज की गई।';
$string['privacy:metadata:attendance:markedby']  = 'उपस्थिति दर्ज करने वाले उपयोगकर्ता की ID।';
