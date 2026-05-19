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
