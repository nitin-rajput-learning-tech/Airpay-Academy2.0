<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #12 (2026-05-16) — Hindi (hi) translations for the user-facing
// strings in local_airpay_users.
//
// Scope: signup flow, welcome email, profile labels — the strings a
// real Hindi-speaking learner would actually see. Admin-only strings
// (settings.php, HRMS import field map) are left untranslated as
// they're operated by English-fluent IT staff.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे यूज़र इंजन';

// W1-8 (2026-05-16) — Public-tenant self-registration.
$string['signup_pagetitle']           = 'अपना एयरपे अकैडमी अकाउंट बनाएँ';
$string['signup_heading']             = 'अकाउंट बनाएँ';
$string['signup_intro']               = 'एयरपे अकैडमी के सार्वजनिक कोर्स, सर्टिफिकेशन और लर्निंग पाथ का उपयोग करने के लिए मुफ्त में साइन अप करें।';
$string['signup_password']            = 'पासवर्ड';
$string['signup_password_help']       = 'एक मज़बूत पासवर्ड चुनें — कम से कम 8 अक्षर, अक्षरों, अंकों और चिह्नों का मिश्रण।';
$string['signup_password_confirm']    = 'पासवर्ड पुष्टि करें';
$string['signup_tos_label']           = 'मैंने <a href="{$a->tos_url}" target="_blank">उपयोग की शर्तें</a> और <a href="{$a->privacy_url}" target="_blank">गोपनीयता नीति</a> पढ़ी हैं और मैं इनसे सहमत हूँ।';
$string['signup_must_accept_tos']     = 'आगे बढ़ने से पहले आपको उपयोग की शर्तें और गोपनीयता नीति पढ़कर स्वीकार करनी होगी।';
$string['signup_submit']              = 'अकाउंट बनाएँ';
$string['signup_disabled']            = 'सेल्फ़-रजिस्ट्रेशन वर्तमान में बंद है। अकाउंट के लिए कृपया अपने एडमिनिस्ट्रेटर से संपर्क करें।';
$string['signup_disabled_notice']     = 'सेल्फ़-रजिस्ट्रेशन वर्तमान में साइट एडमिन द्वारा बंद किया गया है। यदि आपको लगता है कि आप रजिस्टर कर सकते हैं, तो कृपया <a href="mailto:academy@airpay.co.in">academy@airpay.co.in</a> पर ईमेल करें।';
$string['signup_success_check_email'] = 'अकाउंट बन गया है। हमने आपके ईमेल पर एक पुष्टिकरण लिंक भेजा है — अकाउंट सक्रिय करने के लिए कृपया उस पर क्लिक करें।';
$string['signup_success_help']        = 'अगर कुछ मिनटों में ईमेल नहीं दिखता है, तो अपना स्पैम फ़ोल्डर देखें या मदद के लिए academy@airpay.co.in पर संपर्क करें।';
$string['signup_back_to_login']       = 'लॉग-इन पर वापस जाएँ';
$string['signup_generic_error']       = 'हम आपका रजिस्ट्रेशन प्रोसेस नहीं कर सके। कृपया फिर से कोशिश करें।';
$string['signup_validation_failed']   = 'साइन-अप सत्यापन विफल: {$a}';

// Privacy + ToS pages.
$string['privacy_pagetitle'] = 'गोपनीयता नीति — एयरपे अकैडमी';
$string['privacy_heading']   = 'गोपनीयता नीति';
$string['tos_pagetitle']     = 'उपयोग की शर्तें — एयरपे अकैडमी';
$string['tos_heading']       = 'उपयोग की शर्तें';

// P1 #5 (2026-05-16) — DOB + DOJ.
$string['open_dateofbirth']      = 'जन्म तिथि';
$string['open_dateofbirth_help'] = 'वैकल्पिक। रिपोर्ट्स और जन्मदिन-आधारित अभियानों में इस्तेमाल होता है। पहले से सेट DOB हटाने के लिए चेकबॉक्स को अनचेक करें।';
$string['open_joindate']         = 'ज्वाइनिंग की तिथि';
$string['open_joindate_help']    = 'वैकल्पिक। कर्मचारी ने संगठन में कब ज्वाइन किया। टेन्योर-आधारित रिपोर्ट्स और HRMS मिलान में इस्तेमाल होता है।';

// P1 #7 (2026-05-16) — welcome email.
$string['messageprovider:welcome_email'] = 'नए यूज़र्स के लिए स्वागत ईमेल';
$string['welcome_settings_heading']   = 'स्वागत ईमेल (यूज़र बनाते समय भेजा जाता है)';
$string['welcome_email_subject']      = 'डिफ़ॉल्ट स्वागत ईमेल विषय';
$string['welcome_email_body']         = 'डिफ़ॉल्ट स्वागत ईमेल का मुख्य भाग';

// P1 chip filters + supervisor.
$string['supervisor']            = 'रिपोर्टिंग मैनेजर';
$string['supervisor_help']       = 'सुपरवाइज़र का नाम, ईमेल या एम्प्लॉयी कोड टाइप करें। केवल आपके टेनेंट के यूज़र दिखते हैं।';
$string['supervisor_wrong_tenant'] = 'सुपरवाइज़र ({$a->supervisor_tenant} टेनेंट) इस यूज़र ({$a->subordinate_tenant} टेनेंट) के समान टेनेंट में नहीं है। उसी टेनेंट से सुपरवाइज़र चुनें।';

// Common labels.
$string['back_to_users'] = 'यूज़र सूची पर वापस जाएँ';
$string['manage_users']  = 'यूज़र प्रबंधित करें';

// Privacy metadata declaration (Moodle compliance).
$string['privacy:metadata'] = 'एयरपे यूज़र्स प्लगइन open_* फ़ील्ड्स के माध्यम से कोर {user} तालिका का विस्तार करता है। ये core_user द्वारा निर्यात किए जाते हैं; कोई एयरपे-स्वामित्व वाली तालिकाएँ अतिरिक्त व्यक्तिगत डेटा संग्रहीत नहीं करतीं।';
