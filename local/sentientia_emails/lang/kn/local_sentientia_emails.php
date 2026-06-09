<?php
defined('MOODLE_INTERNAL') || die();
$string['pluginname'] = 'ಏರ್‌ಪೇ ಇಮೇಲ್ ಟೆಂಪ್ಲೇಟ್‌ಗಳು';
$string['emailpreview'] = 'ಇಮೇಲ್ ಟೆಂಪ್ಲೇಟ್ ಪೂರ್ವವೀಕ್ಷಣೆ';
$string['emailpreview_desc'] = 'ಡಿಪ್ಲಾಯ್ ಮಾಡುವ ಮೊದಲು ಎಲ್ಲಾ ಬ್ರ್ಯಾಂಡೆಡ್ ಇಮೇಲ್ ಟೆಂಪ್ಲೇಟ್‌ಗಳನ್ನು ನೋಡಿ.';
$string['selecttemplate'] = 'ಟೆಂಪ್ಲೇಟ್ ಆಯ್ಕೆ ಮಾಡಿ';
$string['selecttenant'] = 'ಟೆನೆಂಟ್ ಆಯ್ಕೆ ಮಾಡಿ';
$string['viewsource'] = 'HTML ಮೂಲ';
$string['viewplaintext'] = 'ಸರಳ ಪಠ್ಯ';
$string['viewvisual'] = 'ದೃಶ್ಯ ಪೂರ್ವವೀಕ್ಷಣೆ';
$string['category_compliance'] = 'ಅನುಸರಣೆ';
$string['category_notifications'] = 'ಅಧಿಸೂಚನೆಗಳು';
$string['category_enrollment'] = 'ನೋಂದಣಿ';
$string['category_account'] = 'ಖಾತೆ';
$string['category_privacy'] = 'ಗೌಪ್ಯತೆ';
$string['tenant_airpay'] = 'ಏರ್‌ಪೇ';
$string['tenant_public'] = 'ಸಾರ್ವಜನಿಕ';
$string['tenant_zeea'] = 'ZEEA';
$string['no_template_selected'] = 'ಪೂರ್ವವೀಕ್ಷಿಸಲು ಸೈಡ್‌ಬಾರ್‌ನಿಂದ ಟೆಂಪ್ಲೇಟ್ ಆಯ್ಕೆ ಮಾಡಿ.';
$string['manage'] = 'ಅಧಿಸೂಚನೆ ನಿರ್ವಹಣೆ';
$string['manage_desc'] = 'ಇಮೇಲ್ ಟೆಂಪ್ಲೇಟ್‌ಗಳು, ಅಧಿಸೂಚನೆ ನಿಯಮಗಳು ಮತ್ತು ವಿತರಣಾ ಲಾಗ್‌ಗಳನ್ನು ನಿರ್ವಹಿಸಿ.';
$string['tab_dashboard'] = 'ಡ್ಯಾಶ್‌ಬೋರ್ಡ್';
$string['tab_templates'] = 'ಟೆಂಪ್ಲೇಟ್‌ಗಳು';
$string['tab_rules'] = 'ನಿಯಮಗಳು';
$string['tab_logs'] = 'ಲಾಗ್‌ಗಳು';
$string['tab_settings'] = 'ಸೆಟ್ಟಿಂಗ್‌ಗಳು';
$string['save_success'] = 'ಟೆಂಪ್ಲೇಟ್ ಉಳಿಸಲಾಗಿದೆ.';
$string['revert_success'] = 'ಡೀಫಾಲ್ಟ್ ಟೆಂಪ್ಲೇಟ್‌ಗೆ ಮರಳಿದೆ.';
$string['rule_enabled'] = 'ನಿಯಮ ಸಕ್ರಿಯಗೊಂಡಿದೆ.';
$string['rule_disabled'] = 'ನಿಯಮ ನಿಷ್ಕ್ರಿಯಗೊಂಡಿದೆ.';
$string['noemailever_warning'] = 'ಈ ಸರ್ವರ್‌ನಲ್ಲಿ ಇಮೇಲ್ ಕಳುಹಿಸುವುದನ್ನು ನಿಷ್ಕ್ರಿಯಗೊಳಿಸಲಾಗಿದೆ. ಎಲ್ಲಾ ವಿತರಣೆಗಳನ್ನು ನಿಗ್ರಹಿಸಿದ ಎಂದು ಲಾಗ್ ಮಾಡಲಾಗುತ್ತದೆ.';
$string['task_process_rules'] = 'ಅಧಿಸೂಚನೆ ನಿಯಮಗಳನ್ನು ಪ್ರಕ್ರಿಯೆಗೊಳಿಸಿ';
$string['notification_alert'] = 'ಅಧಿಸೂಚನೆ ಎಚ್ಚರಿಕೆ';

// Sprint B (2026-05-13) — course-completion email + ramping reminders.
// Keep {$a} placeholders single-quoted; PHP must NOT interpolate at load.
$string['sprintb_rule_completed_name'] = 'ಕೋರ್ಸ್ ಪೂರ್ಣ: ಅಭಿನಂದನೆ + ಪ್ರಮಾಣಪತ್ರ';
$string['sprintb_rule_incomplete_name'] = 'ಕೋರ್ಸ್ ಅಪೂರ್ಣ: ಹೆಜ್ಜೆ ಜ್ಞಾಪನೆಗಳು (1-3-7-14-21)';
$string['sprintb_email_subject_default'] = '{$a} ಪೂರ್ಣಗೊಳಿಸಿದ್ದಕ್ಕಾಗಿ ಅಭಿನಂದನೆಗಳು';
$string['sprintb_reminder_subject_default'] = 'ಜ್ಞಾಪನೆ: ನಿಮ್ಮ ಕೋರ್ಸ್ {$a} ಮುಂದುವರಿಸಿ';
$string['sprintb_certificate_display_name'] = 'Airpay-certificate-{$a}.pdf';
$string['email_to_user_failed'] = 'Moodle email_to_user() false ಎಂದು ಮರಳಿಸಿತು. ಮೇಲ್ ಸರ್ವರ್ ಕಾನ್ಫಿಗ್ ಮತ್ತು ಸ್ವೀಕರಿಸುವವರ ವಿಳಾಸವನ್ನು ಪರಿಶೀಲಿಸಿ.';
