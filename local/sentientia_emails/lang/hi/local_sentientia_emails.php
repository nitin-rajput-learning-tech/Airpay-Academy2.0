<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'एयरपे ईमेल टेम्पलेट';
$string['emailpreview'] = 'ईमेल टेम्पलेट प्रीव्यू';
$string['emailpreview_desc'] = 'डिप्लॉयमेंट से पहले सभी ब्रांडेड ईमेल टेम्पलेट देखें।';
$string['selecttemplate'] = 'टेम्पलेट चुनें';
$string['selecttenant'] = 'टेनेंट चुनें';
$string['viewsource'] = 'HTML सोर्स';
$string['viewplaintext'] = 'प्लेन टेक्स्ट';
$string['viewvisual'] = 'विज़ुअल प्रीव्यू';
$string['category_compliance'] = 'अनुपालन';
$string['category_notifications'] = 'सूचनाएं';
$string['category_enrollment'] = 'नामांकन';
$string['category_account'] = 'अकाउंट';
$string['category_privacy'] = 'गोपनीयता';
$string['tenant_airpay'] = 'एयरपे';
$string['tenant_public'] = 'पब्लिक';
$string['tenant_zeea'] = 'ZEEA';
$string['no_template_selected'] = 'प्रीव्यू के लिए साइडबार से कोई टेम्पलेट चुनें।';
$string['manage'] = 'नोटिफ़िकेशन मैनेजमेंट';
$string['manage_desc'] = 'ईमेल टेम्पलेट, नोटिफ़िकेशन रूल्स और डिलीवरी लॉग मैनेज करें।';
$string['tab_dashboard'] = 'डैशबोर्ड';
$string['tab_templates'] = 'टेम्पलेट्स';
$string['tab_rules'] = 'रूल्स';
$string['tab_logs'] = 'लॉग्स';
$string['tab_settings'] = 'सेटिंग्स';
$string['save_success'] = 'टेम्पलेट सेव हो गया।';
$string['revert_success'] = 'डिफ़ॉल्ट टेम्पलेट पर वापस गया।';
$string['rule_enabled'] = 'रूल चालू हो गया।';
$string['rule_disabled'] = 'रूल बंद हो गया।';
$string['noemailever_warning'] = 'इस सर्वर पर ईमेल भेजना बंद है। सभी डिलीवरी suppressed के तौर पर लॉग होंगी।';
$string['task_process_rules'] = 'नोटिफ़िकेशन रूल्स प्रोसेस करें';
$string['notification_alert'] = 'नोटिफ़िकेशन अलर्ट';

// Sprint B (2026-05-13) — course-completion email + ramping reminders.
// Keep {$a} placeholders single-quoted; PHP must NOT interpolate at load.
$string['sprintb_rule_completed_name'] = 'कोर्स पूरा: बधाई + सर्टिफिकेट';
$string['sprintb_rule_incomplete_name'] = 'कोर्स अधूरा: चरणबद्ध रिमाइंडर (1-3-7-14-21)';
$string['sprintb_email_subject_default'] = '{$a} पूरा करने पर बधाई';
$string['sprintb_reminder_subject_default'] = 'रिमाइंडर: अपना कोर्स {$a} जारी रखें';
$string['sprintb_certificate_display_name'] = 'Airpay-certificate-{$a}.pdf';
$string['email_to_user_failed'] = 'Sentientia LMS email_to_user() ने false लौटाया। मेल सर्वर कॉन्फ़िग और प्राप्तकर्ता पता जाँचें।';

// P1 #49 (2026-05-20) — Hindi top-up: 25 strings covering privacy metadata,
// ramping reminder + certificate settings, and cadence JSON errors.

// Privacy metadata (email log).
$string['privacy:metadata:emaillog']             = 'ईमेल भेजने का लॉग — प्रति यूज़र को कतारबद्ध/भेजे गए ईमेल पर एक पंक्ति।';
$string['privacy:metadata:emaillog:userid']      = 'प्राप्तकर्ता यूज़र ID।';
$string['privacy:metadata:emaillog:subject']     = 'विषय पंक्ति।';
$string['privacy:metadata:emaillog:recipient']   = 'प्राप्तकर्ता ईमेल पता।';
$string['privacy:metadata:emaillog:status']      = 'भेजने की स्थिति (queued/sent/bounced/failed)।';
$string['privacy:metadata:emaillog:timecreated'] = 'भेजने का टाइमस्टैम्प।';

// Privacy metadata (email prefs).
$string['privacy:metadata:emailprefs']              = 'प्रति-यूज़र ईमेल प्राथमिकताएँ।';
$string['privacy:metadata:emailprefs:userid']       = 'जिस यूज़र की प्राथमिकताएँ हैं।';
$string['privacy:metadata:emailprefs:timemodified'] = 'अंतिम अपडेट टाइमस्टैम्प।';

// Day-2 (2026-05-14) — settings panel: ramping reminder defaults.
$string['setting_ramping_heading']        = 'चरणबद्ध रिमाइंडर डिफ़ॉल्ट';
$string['setting_ramping_desc']           = 'ये मान किसी भी नए <code>course_incomplete</code> नियम को सीड करते हैं। मौजूदा नियम नहीं बदलते — प्रत्येक नियम को व्यक्तिगत रूप से संपादित करके ओवरराइड करें।';
$string['setting_default_cadence']        = 'डिफ़ॉल्ट कैडेंस (दिन ऑफ़सेट का JSON ऐरे)';
$string['setting_default_cadence_help']   = 'दिन ऑफ़सेट का JSON ऐरे, जैसे <code>[1,3,7,14,21]</code>। प्रत्येक मान नामांकन से दिन; रिमाइंडर केवल उन्हीं दिनों पर ट्रिगर होता है। अधिकतम 10 प्रविष्टियाँ; मान धनात्मक पूर्णांक होने चाहिए।';
$string['setting_default_cap']            = 'प्रति (यूज़र × कोर्स) डिफ़ॉल्ट अधिकतम रिमाइंडर';
$string['setting_default_cap_help']       = 'एक कोर्स के लिए एक शिक्षार्थी को कितने रिमाइंडर मिलते हैं उस पर हार्ड कैप। <code>0</code> = असीमित (अनुशंसित नहीं)। डिफ़ॉल्ट <code>5</code> सीडेड [1,3,7,14,21] कैडेंस से मेल खाता है।';
$string['setting_default_auto_stop']      = 'कोर्स पूर्णता पर रिमाइंडर ऑटो-स्टॉप करें';
$string['setting_default_auto_stop_help'] = 'चेक करने पर, रिमाइंडर क्वेरी उन यूज़र्स को छोड़ देती है जिन्होंने पहले ही कोर्स पूर्ण कर लिया है। केवल तब अनचेक करें जब आप पूर्ण किए कोर्सेज पर पुनर्भ्रमण के लिए यूज़र्स को आगे पुश करना चाहते हों।';

// Day-2 — certificate-email defaults.
$string['setting_cert_heading']     = 'सर्टिफिकेट-ईमेल डिफ़ॉल्ट';
$string['setting_cert_desc']        = 'कोर्स-पूर्णता ईमेल के लिए नियंत्रण जो <code>\\core\\event\\course_completed</code> ऑब्ज़र्वर से ट्रिगर होता है।';
$string['setting_attach_cert']      = 'सर्टिफिकेट PDF संलग्न करें';
$string['setting_attach_cert_help'] = 'चेक करने पर, पूर्णता ईमेल यूज़र की <code>tool_certificate</code> PDF को अटैचमेंट के रूप में ले जाता है। सर्टिफिकेट प्लगइन के गड़बड़ करने पर किसी घटना के दौरान वैश्विक रूप से अनचेक करें — ईमेल फिर भी भेजा जाता है, बस अटैचमेंट के बिना।';

// Day-2 — cadence JSON validation errors.
$string['cadence_error_not_array'] = 'कैडेंस JSON ऐरे होना चाहिए, जैसे [1,3,7,14,21]। कुछ और मिला।';
$string['cadence_error_empty']     = 'कैडेंस खाली है — फ़ील्ड साफ़ करके डिफ़ॉल्ट इस्तेमाल करें।';
$string['cadence_error_too_long']  = 'कैडेंस में बहुत अधिक प्रविष्टियाँ हैं — अधिकतम {$a}। इससे अधिक स्पैमी है और शिक्षार्थी प्रेषक को म्यूट कर देंगे।';
$string['cadence_error_bad_value'] = 'कैडेंस में ख़राब मान है: {$a}। प्रत्येक प्रविष्टि एक धनात्मक पूर्णांक (1, 2, 3, …) होनी चाहिए।';
