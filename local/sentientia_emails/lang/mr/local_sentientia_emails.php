<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'एअरपे ईमेल टेम्पलेट';
$string['emailpreview'] = 'ईमेल टेम्पलेट प्रीव्यू';
$string['emailpreview_desc'] = 'डिप्लॉयमेंट आधी सर्व ब्रँडेड ईमेल टेम्पलेट पहा.';
$string['selecttemplate'] = 'टेम्पलेट निवडा';
$string['selecttenant'] = 'टेनंट निवडा';
$string['viewsource'] = 'HTML सोर्स';
$string['viewplaintext'] = 'प्लेन टेक्स्ट';
$string['viewvisual'] = 'विज़ुअल प्रीव्यू';
$string['category_compliance'] = 'अनुपालन';
$string['category_notifications'] = 'सूचना';
$string['category_enrollment'] = 'नोंदणी';
$string['category_account'] = 'अकाउंट';
$string['category_privacy'] = 'गोपनीयता';
$string['tenant_airpay'] = 'एअरपे';
$string['tenant_public'] = 'पब्लिक';
$string['tenant_zeea'] = 'ZEEA';
$string['no_template_selected'] = 'प्रीव्यूसाठी साइडबारमधून टेम्पलेट निवडा.';
$string['manage'] = 'सूचना व्यवस्थापन';
$string['manage_desc'] = 'ईमेल टेम्पलेट, सूचना नियम आणि डिलिव्हरी लॉग व्यवस्थापित करा.';
$string['tab_dashboard'] = 'डॅशबोर्ड';
$string['tab_templates'] = 'टेम्पलेट्स';
$string['tab_rules'] = 'नियम';
$string['tab_logs'] = 'लॉग्स';
$string['tab_settings'] = 'सेटिंग्ज';
$string['save_success'] = 'टेम्पलेट सेव्ह झाले.';
$string['revert_success'] = 'डीफॉल्ट टेम्पलेटवर परत गेले.';
$string['rule_enabled'] = 'नियम चालू झाला.';
$string['rule_disabled'] = 'नियम बंद झाला.';
$string['noemailever_warning'] = 'या सर्व्हरवर ईमेल पाठवणे बंद आहे. सर्व डिलिव्हरी suppressed म्हणून लॉग होतील.';
$string['task_process_rules'] = 'सूचना नियम प्रक्रिया करा';
$string['notification_alert'] = 'सूचना अलर्ट';

// Sprint B (2026-05-13) — course-completion email + ramping reminders.
// Keep {$a} placeholders single-quoted; PHP must NOT interpolate at load.
$string['sprintb_rule_completed_name'] = 'कोर्स पूर्ण: अभिनंदन + प्रमाणपत्र';
$string['sprintb_rule_incomplete_name'] = 'कोर्स अपूर्ण: टप्पेवार स्मरणपत्रे (1-3-7-14-21)';
$string['sprintb_email_subject_default'] = '{$a} पूर्ण केल्याबद्दल अभिनंदन';
$string['sprintb_reminder_subject_default'] = 'स्मरणपत्र: तुमचा कोर्स {$a} सुरू ठेवा';
$string['sprintb_certificate_display_name'] = 'Airpay-certificate-{$a}.pdf';
$string['email_to_user_failed'] = 'Moodle email_to_user() false परतले. मेल सर्व्हर कॉन्फिग आणि प्राप्तकर्ता पत्ता तपासा.';
