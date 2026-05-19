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
