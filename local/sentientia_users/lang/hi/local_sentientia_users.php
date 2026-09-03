<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #12 (2026-05-16) — Hindi (hi) translations for the user-facing
// strings in local_sentientia_users.
//
// Scope: signup flow, welcome email, profile labels — the strings a
// real Hindi-speaking learner would actually see. Admin-only strings
// (settings.php, HRMS import field map) are left untranslated as
// they're operated by English-fluent IT staff.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे यूज़र इंजन';
$string['manageusers_title'] = 'यूज़र प्रबंधन';

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
$string['signup_have_account']        = 'क्या आपके पास पहले से अकाउंट है?';
$string['signup_generic_error']       = 'हम आपका रजिस्ट्रेशन प्रोसेस नहीं कर सके। कृपया फिर से कोशिश करें।';
$string['signup_validation_failed']   = 'साइन-अप सत्यापन विफल: {$a}';

// H1 fix (UAT-SECURITY-POSTURE-2026-09-03) — existing-account notice.
$string['signup_existing_account_subject'] = 'किसी ने एयरपे अकैडमी पर आपकी ईमेल से साइन अप करने की कोशिश की';
$string['signup_existing_account_body']    = 'हमें एयरपे अकैडमी पर इस ईमेल पते से एक नया अकाउंट बनाने का अनुरोध मिला है। आपका यहाँ पहले से एक अकाउंट मौजूद है — अगर यह आपने किया था, तो आप {$a->loginurl} पर लॉग-इन कर सकते हैं, या पासवर्ड भूल जाने पर {$a->forgotpasswordurl} पर उसे रीसेट कर सकते हैं। अगर आपने यह अनुरोध नहीं किया, तो आप इस ईमेल को अनदेखा कर सकते हैं।';

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

// P1 #47 (2026-05-20) — Hindi top-up: 128 strings covering capabilities,
// profile labels, CRUD forms, errors, success messages, HRMS bulk import
// (admin UI + history + cron + sync settings), and welcome email tokens.
// Brings sentientia_users to full Hindi parity.

// Capabilities.
$string['sentientia_users:edit']             = 'यूज़र संपादित करें';
$string['sentientia_users:view']             = 'यूज़र प्रोफ़ाइल देखें';
$string['sentientia_users:bulkstatuschange'] = 'थोक स्थिति परिवर्तन';

// Profile labels.
$string['employee']     = 'कर्मचारी';
$string['na']           = 'लागू नहीं';
$string['all']          = 'सभी';
$string['profile']      = 'प्रोफ़ाइल';
$string['editprofile']  = 'प्रोफ़ाइल संपादित करें';
$string['reportingto']  = 'रिपोर्टिंग';

// Settings page.
$string['settings_heading']        = 'एयरपे यूज़र सेटिंग्स';
$string['organization_shortname']  = 'पंजीकरण के लिए संगठन शॉर्टनेम';
$string['activeregistration']      = 'यूज़र पंजीकरण सक्षम करें';
$string['activeregistration_help'] = 'सक्षम होने पर, यूज़र्स /local/sentientia_users/signup.php पर साइन अप कर सकते हैं। नए अकाउंट डिफ़ॉल्ट रूप से Public टेनेंट में जाते हैं (नीचे कॉन्फ़िगर करने योग्य)।';
$string['signup_settings_heading'] = 'सेल्फ़-रजिस्ट्रेशन (Public टेनेंट)';
$string['signup_settings_intro']   = 'नीचे Public-टेनेंट साइन-अप प्रवाह को कॉन्फ़िगर करें। प्राइवेसी + टर्म्स पेज सेट होने पर एडमिन-आपूर्ति HTML रेंडर करते हैं; अन्यथा इनबिल्ट GDPR-अनुपालक डिफ़ॉल्ट दिखाते हैं।';
$string['signup_tenant_path']      = 'नए साइन-अप के लिए टेनेंट open_path';
$string['signup_tenant_path_help'] = 'नए स्वयं-पंजीकृत यूज़र्स को असाइन किया गया open_path। डिफ़ॉल्ट /77 (Public टेनेंट)। इसे Airpay टेनेंट (id=1) की ओर इंगित न करें — वह टेनेंट केवल HRMS-प्रबंधित है।';
$string['custom_privacy_policy_html']      = 'गोपनीयता नीति HTML (ओवरराइड)';
$string['custom_privacy_policy_html_help'] = 'इनबिल्ट GDPR-अनुपालक डिफ़ॉल्ट इस्तेमाल करने के लिए खाली छोड़ें। कंपनी-स्वीकृत क़ानूनी पाठ से ओवरराइड करने के लिए इसे सेट करें।';
$string['custom_tos_html']                 = 'उपयोग की शर्तें HTML (ओवरराइड)';
$string['custom_tos_html_help']            = 'इनबिल्ट डिफ़ॉल्ट इस्तेमाल करने के लिए खाली छोड़ें। कंपनी-स्वीकृत क़ानूनी पाठ से ओवरराइड करने के लिए इसे सेट करें।';

// CRUD form actions.
$string['adduser']      = 'यूज़र जोड़ें';
$string['edituser']     = 'यूज़र संपादित करें';
$string['deleteuser']   = 'यूज़र हटाएँ';
$string['suspenduser']  = 'यूज़र निलंबित करें';
$string['activateuser'] = 'यूज़र सक्रिय करें';

// Form section headings.
$string['heading_account']      = 'अकाउंट';
$string['heading_personal']     = 'व्यक्तिगत विवरण';
$string['heading_organisation'] = 'संगठन';
$string['heading_password']     = 'पासवर्ड';

// Form field labels.
$string['username']         = 'यूज़रनेम';
$string['username_help']    = 'लोअरकेस, कोई स्पेस नहीं। लॉग-इन के लिए इस्तेमाल होता है।';
$string['email']            = 'ईमेल';
$string['firstname']        = 'पहला नाम';
$string['lastname']         = 'अंतिम नाम';
$string['employeeid']       = 'कर्मचारी ID';
$string['designation']      = 'पद';
$string['organisation']     = 'संगठन';
$string['department']       = 'विभाग';
$string['location']         = 'स्थान';
$string['phone']            = 'फ़ोन';
$string['authmethod']       = 'प्रमाणीकरण विधि';
$string['password']         = 'पासवर्ड';
$string['newpassword']      = 'नया पासवर्ड';
$string['newpassword_help'] = 'वर्तमान पासवर्ड बनाए रखने के लिए खाली छोड़ें।';
$string['emailwelcome']     = 'लॉग-इन विवरण के साथ स्वागत ईमेल भेजें';

// Error messages.
$string['missingrequiredfields']  = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['usernametaken']          = 'यह यूज़रनेम पहले से लिया जा चुका है। कृपया दूसरा चुनें।';
$string['emailtaken']             = 'यह ईमेल पहले से पंजीकृत है। कृपया दूसरा ईमेल इस्तेमाल करें।';
$string['cannotdeleteself']       = 'आप अपना अकाउंट नहीं हटा सकते।';
$string['cannotdeletesystemuser'] = 'सिस्टम यूज़र हटाए नहीं जा सकते।';
$string['confirmdelete']          = 'क्या आप वाकई {$a} को हटाना चाहते हैं? इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmsuspend']         = 'क्या आप वाकई {$a} को निलंबित करना चाहते हैं? वे लॉग-इन नहीं कर पाएँगे।';
$string['confirmactivate']        = 'क्या आप वाकई {$a} को पुनः सक्रिय करना चाहते हैं?';

// Success messages.
$string['usercreated']   = 'यूज़र सफलतापूर्वक बना दिया गया।';
$string['userupdated']   = 'यूज़र सफलतापूर्वक अपडेट किया गया।';
$string['userdeleted']   = 'यूज़र हटा दिया गया।';
$string['usersuspended'] = 'यूज़र निलंबित किया गया।';
$string['useractivated'] = 'यूज़र सक्रिय किया गया।';

// HRMS bulk import — pages + buttons.
$string['hrms_pagetitle']          = 'HRMS थोक आयात (24-कॉलम CSV)';
$string['hrms_pageheading']        = 'HRMS थोक आयात';
$string['hrms_breadcrumb']         = 'HRMS आयात';
$string['hrms_csvfile']            = 'HRMS CSV फ़ाइल';
$string['hrms_runimport']          = 'आयात चलाएँ';
$string['hrms_empty_csv']          = 'अपलोड की गई CSV खाली है। कृपया हेडर पंक्ति और एक या अधिक डेटा पंक्तियों के साथ फ़ाइल अपलोड करें।';
$string['hrms_import_done']        = 'HRMS आयात पूर्ण। नीचे परिणाम देखें।';
$string['hrms_view_history']       = 'आयात इतिहास देखें';
$string['hrms_history_title']      = 'HRMS आयात इतिहास';
$string['hrms_history_breadcrumb'] = 'HRMS इतिहास';
$string['hrms_new_import']         = 'नया HRMS आयात';
$string['hrms_no_runs']            = 'इस टेनेंट के लिए अभी तक कोई HRMS आयात नहीं चलाया गया है।';
$string['hrms_back_to_history']    = 'इतिहास पर वापस जाएँ';
$string['hrms_run_detail_title']   = 'HRMS रन #{$a}';
$string['hrms_run_detail_heading'] = 'HRMS रन #{$a} — विवरण';
$string['hrms_no_errors']          = 'यह रन बिना किसी पंक्ति-स्तरीय त्रुटि या चेतावनी के पूर्ण हुआ।';
$string['hrms_error_log']          = 'पंक्ति-स्तरीय त्रुटियाँ और चेतावनियाँ';

// History table column headers.
$string['hrms_col_id']       = 'रन #';
$string['hrms_col_filename'] = 'फ़ाइल';
$string['hrms_col_time']     = 'समय';
$string['hrms_col_user']     = 'रन करने वाला';
$string['hrms_col_source']   = 'स्रोत';
$string['hrms_col_status']   = 'स्थिति';
$string['hrms_col_total']    = 'कुल पंक्तियाँ';
$string['hrms_col_inserted'] = 'जोड़ी गईं';
$string['hrms_col_updated']  = 'अपडेट की गईं';
$string['hrms_col_errors']   = 'त्रुटियाँ';
$string['hrms_col_warnings'] = 'चेतावनियाँ';

// Run-detail table column headers.
$string['hrms_col_line']     = 'पंक्ति #';
$string['hrms_col_severity'] = 'गंभीरता';
$string['hrms_col_email']    = 'ईमेल';
$string['hrms_col_empcode']  = 'कर्मचारी कोड';
$string['hrms_col_username'] = 'यूज़रनेम';
$string['hrms_col_name']     = 'नाम';
$string['hrms_col_message']  = 'संदेश';
$string['hrms_col_missing']  = 'अनिवार्य गुम';

// CSV parse errors.
$string['error_csv_header_missing'] = 'आवश्यक हेडर कॉलम गुम: {$a}';

// Welcome email — extended strings.
$string['welcome_settings_intro']         = 'जब create-user फ़ॉर्म पर "Email welcome" चेकबॉक्स सक्षम होता है, तो यूज़र को यह संदेश प्राप्त होता है। बॉडी में निम्न में से कोई भी टोकन शामिल हो सकता है (केस-इनसेंसिटिव, भेजते समय बदले जाते हैं): <code>[employee_name]</code>, <code>[employee_email]</code>, <code>[employee_username]</code>, <code>[employee_password]</code>, <code>[employee_organization]</code>। नीचे टेनेंट-विशिष्ट ओवरराइड डिफ़ॉल्ट से प्राथमिकता लेते हैं।';
$string['welcome_email_subject_help']     = 'जब कोई टेनेंट-विशिष्ट विषय सेट नहीं होता है तो इसका इस्तेमाल होता है। टोकन भेजने से पहले प्रतिस्थापित किए जाते हैं।';
$string['welcome_email_body_help']        = 'सादा-पाठ बॉडी। ऊपर सूचीबद्ध टोकन भेजने से पहले प्रतिस्थापित किए जाते हैं। सादा पाठ + ऑटो-फ़ॉर्मेटेड HTML के रूप में भेजा जाता है (कोई स्क्रिप्ट टैग नहीं)।';
$string['welcome_email_subject_tenant']   = '{$a} टेनेंट — विषय ओवरराइड';
$string['welcome_email_body_tenant']      = '{$a} टेनेंट — बॉडी ओवरराइड';
$string['welcome_email_body_tenant_help'] = 'डिफ़ॉल्ट इस्तेमाल करने के लिए खाली छोड़ें। अन्यथा {$a} टेनेंट ट्री के यूज़र्स के लिए यह डिफ़ॉल्ट को ओवरराइड करता है।';

// HRMS sync (cron).
$string['task_hrms_sync']             = 'HRMS सिंक (CSV पुल और आयात)';
$string['hrms_sync_settings_heading'] = 'HRMS सिंक (cron)';
$string['hrms_sync_settings_intro']   = 'एक स्वचालित दैनिक आयात कॉन्फ़िगर करें जो URL या फ़ाइलसिस्टम पथ से 24-कॉलम HRMS CSV खींचता है और मैनुअल अपलोड पेज द्वारा इस्तेमाल होने वाले समान आयातक के माध्यम से चलाता है। यह कार्य डिफ़ॉल्ट रूप से अक्षम है — स्रोत मोड <em>URL</em> या <em>Filesystem</em> सेट करें, फिर Site administration ▶ Server ▶ Scheduled tasks से कार्य सक्षम करें। डिफ़ॉल्ट शेड्यूल: दैनिक 02:30।';
$string['hrms_sync_mode']             = 'स्रोत मोड';
$string['hrms_sync_mode_help']        = '<strong>अक्षम</strong> — कार्य निष्क्रिय है। <strong>URL</strong> — HTTP GET से प्राप्त करें (वैकल्पिक Authorization हेडर के साथ)। <strong>Filesystem</strong> — सर्वर-लोकल पूर्ण पथ से पढ़ें।';
$string['hrms_sync_mode_disabled']    = 'अक्षम (निष्क्रिय)';
$string['hrms_sync_mode_url']         = 'URL (HTTP GET)';
$string['hrms_sync_mode_filesystem']  = 'Filesystem (सर्वर-लोकल पथ)';
$string['hrms_sync_url']              = 'CSV स्रोत URL';
$string['hrms_sync_url_help']         = 'जब स्रोत मोड <em>URL</em> हो तब इस्तेमाल होता है। एंडपॉइंट को 24-कॉलम HRMS CSV बॉडी और HTTP 200 के साथ जवाब देना चाहिए। कनेक्शन टाइमआउट: 15 सेकंड। रीड टाइमआउट: 5 मिनट।';
$string['hrms_sync_auth_header']      = 'Authorization हेडर (वैकल्पिक)';
$string['hrms_sync_auth_header_help'] = 'पूरी हेडर लाइन पास करें, जैसे <code>Authorization: Bearer eyJhbGci...</code> या <code>X-Api-Key: ...</code>। अप्रमाणीकृत एंडपॉइंट के लिए खाली छोड़ें। Sentientia LMS कॉन्फ़िग टेबल में अनएन्क्रिप्टेड संग्रहीत — लीक के संदेह पर टोकन घुमाएँ।';
$string['hrms_sync_path']             = 'CSV फ़ाइलसिस्टम पथ';
$string['hrms_sync_path_help']        = 'जब स्रोत मोड <em>Filesystem</em> हो तब इस्तेमाल होता है। पूर्ण सर्वर-लोकल पथ होना चाहिए (Unix: <code>/var/airpay/exports/hrms.csv</code> या Windows: <code>C:\airpay\hrms.csv</code>)। Sentientia LMS वेब-सर्वर यूज़र को रीड परमिशन होना चाहिए। फ़ाइल हर रन पर फिर से पढ़ी जाती है।';
$string['hrms_sync_user_id']          = 'रनर यूज़र ID';
$string['hrms_sync_user_id_help']     = 'न्यूमेरिक यूज़र ID जिसके अंतर्गत आयात चलता है। डिफ़ॉल्ट <code>2</code> (स्टॉक Sentientia LMS पर साइट एडमिन)। ऐसा यूज़र चुनें जिसके पास <code>local/sentientia_users:edit</code> क्षमता हो और CSV की हर पंक्ति को कवर करने वाला टेनेंट स्कोप हो — अन्यथा पंक्तियाँ क्रॉस-टेनेंट के रूप में अस्वीकार होंगी।';
$string['hrms_sync_status']           = 'सिंक स्थिति';
$string['hrms_sync_last_run_value']   = 'अंतिम सफल रन: <strong>{$a->time}</strong> (रन #{$a->runid})। विवरण <a href="../local/sentientia_users/hrms_history.php">HRMS इतिहास पेज</a> पर देखें।';
$string['hrms_sync_last_run_never']   = 'Cron ने अभी तक कोई HRMS सिंक सफलतापूर्वक पूर्ण नहीं किया है। कार्य सक्षम करें और सर्वर समय 02:30 के बाद देखें, या इसे मैन्युअली ट्रिगर करने के लिए कमांड लाइन से <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_users\\\\task\\\\hrms_sync</code> चलाएँ।';

// Scheduled-task error strings.
$string['hrms_sync_invalid_mode']      = 'अज्ञात स्रोत मोड: {$a}';
$string['hrms_sync_url_empty']         = 'स्रोत मोड URL है लेकिन कोई URL कॉन्फ़िगर नहीं है।';
$string['hrms_sync_url_http_error']    = 'HRMS URL प्राप्ति विफल: {$a}';
$string['hrms_sync_path_empty']        = 'स्रोत मोड फ़ाइलसिस्टम है लेकिन कोई पथ कॉन्फ़िगर नहीं है।';
$string['hrms_sync_path_not_absolute'] = 'HRMS फ़ाइलसिस्टम पथ पूर्ण होना चाहिए। मिला: {$a}';
$string['hrms_sync_path_not_readable'] = 'HRMS फ़ाइलसिस्टम पथ वेब सर्वर द्वारा पठनीय नहीं है: {$a}';
$string['hrms_sync_path_read_failed']  = 'HRMS फ़ाइलसिस्टम पथ पढ़ा नहीं जा सका: {$a}';
