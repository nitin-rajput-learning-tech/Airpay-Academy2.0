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
$string['email_to_user_failed'] = 'Moodle email_to_user() ने false लौटाया। मेल सर्वर कॉन्फ़िग और प्राप्तकर्ता पता जाँचें।';
