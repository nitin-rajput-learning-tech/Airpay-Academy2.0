<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay Email Templates';
$string['emailpreview'] = 'Email Template Preview';
$string['emailpreview_desc'] = 'Preview all branded email templates before deployment.';
$string['selecttemplate'] = 'Select a template';
$string['selecttenant'] = 'Select tenant';
$string['viewsource'] = 'HTML Source';
$string['viewplaintext'] = 'Plain Text';
$string['viewvisual'] = 'Visual Preview';
$string['category_compliance'] = 'Compliance';
$string['category_notifications'] = 'Notifications';
$string['category_enrollment'] = 'Enrollment';
$string['category_account'] = 'Account';
$string['category_privacy'] = 'Privacy';
$string['tenant_airpay'] = 'Airpay';
$string['tenant_public'] = 'Public';
$string['tenant_zeea'] = 'ZEEA';
$string['no_template_selected'] = 'Select a template from the sidebar to preview.';
$string['manage'] = 'Notification Management';
$string['manage_desc'] = 'Manage email templates, notification rules, and delivery logs.';
$string['tab_dashboard'] = 'Dashboard';
$string['tab_templates'] = 'Templates';
$string['tab_rules'] = 'Rules';
$string['tab_logs'] = 'Logs';
$string['tab_settings'] = 'Settings';
$string['save_success'] = 'Template saved successfully.';
$string['revert_success'] = 'Reverted to default template.';
$string['rule_enabled'] = 'Rule enabled.';
$string['rule_disabled'] = 'Rule disabled.';
$string['noemailever_warning'] = 'Email sending is disabled on this server ($CFG->noemailever = true). All deliveries will be logged as suppressed.';
$string['task_process_rules'] = 'Process notification rules';
$string['notification_alert'] = 'Notification alert';

// Privacy strings (Phase Z.1).
$string['privacy:metadata:emaillog'] = 'Email send log — one row per email queued/sent to a user.';
$string['privacy:metadata:emaillog:userid'] = 'Recipient user ID.';
$string['privacy:metadata:emaillog:subject'] = 'Subject line.';
$string['privacy:metadata:emaillog:recipient'] = 'Recipient email address.';
$string['privacy:metadata:emaillog:status'] = 'Send status (queued/sent/bounced/failed).';
$string['privacy:metadata:emaillog:timecreated'] = 'Send timestamp.';
$string['privacy:metadata:emailprefs'] = 'Per-user email preferences.';
$string['privacy:metadata:emailprefs:userid'] = 'User the preferences belong to.';
$string['privacy:metadata:emailprefs:timemodified'] = 'Last update timestamp.';

// Sprint B (2026-05-13) — course-completion email + ramping reminders.
// Note: Moodle lang strings with {$a} placeholders MUST be in
// single quotes — PHP would otherwise try to interpolate $a at
// load time, which is the warning the upgrade flagged on first
// deploy.
$string['sprintb_rule_completed_name'] = 'Course completed: congratulations + certificate';
$string['sprintb_rule_incomplete_name'] = 'Course incomplete: ramping reminders (1-3-7-14-21)';
$string['sprintb_email_subject_default'] = 'Congratulations on completing {$a}';
$string['sprintb_reminder_subject_default'] = 'Reminder: continue your course {$a}';
$string['sprintb_certificate_display_name'] = 'Airpay-certificate-{$a}.pdf';
$string['email_to_user_failed'] = 'Moodle email_to_user() returned false. Check mail server config + recipient address.';

