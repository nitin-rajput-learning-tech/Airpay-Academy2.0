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
$string['email_to_user_failed'] = 'Sentientia LMS email_to_user() returned false. Check mail server config + recipient address.';

// Day-2 (2026-05-14) — settings panel for ramping reminder + cert defaults.
$string['setting_ramping_heading'] = 'Ramping reminder defaults';
$string['setting_ramping_desc']    = 'These values seed any new <code>course_incomplete</code> rule. Existing rules are not changed — edit each rule individually to override.';

$string['setting_default_cadence']      = 'Default cadence (JSON array of day offsets)';
$string['setting_default_cadence_help'] = 'JSON array of day offsets, e.g. <code>[1,3,7,14,21]</code>. Each value is days since enrolment; a reminder fires only on those day offsets. Max 10 entries; values must be positive integers.';

$string['setting_default_cap']      = 'Default max reminders per (user × course)';
$string['setting_default_cap_help'] = 'Hard cap on how many reminders one learner gets for one course. <code>0</code> = unlimited (not recommended). Default <code>5</code> matches the seeded [1,3,7,14,21] cadence.';

$string['setting_default_auto_stop']      = 'Auto-stop reminders on course completion';
$string['setting_default_auto_stop_help'] = 'When ticked, the reminder query excludes users who already completed the course. Untick only if you want to keep nudging users to revisit completed courses.';

$string['setting_cert_heading']        = 'Certificate-email defaults';
$string['setting_cert_desc']           = 'Controls for the course-completion email that fires from the <code>\\core\\event\\course_completed</code> observer.';

$string['setting_attach_cert']         = 'Attach the certificate PDF';
$string['setting_attach_cert_help']    = 'When ticked, the completion email carries the user\'s <code>tool_certificate</code> PDF as an attachment. Untick globally during an incident if the certificate plugin is misbehaving — the email still sends, just without the attachment.';

// Day-2 — validation errors for the cadence JSON setting. Surfaced
// at save time by \local_airpay_emails\admin\setting_cadence_json::validate().
$string['cadence_error_not_array']  = 'Cadence must be a JSON array, e.g. [1,3,7,14,21]. Got something else.';
$string['cadence_error_empty']      = 'Cadence is empty — use the default by clearing the field.';
$string['cadence_error_too_long']   = 'Cadence has too many entries — max is {$a}. More than that is spammy and learners will mute the sender.';
$string['cadence_error_bad_value']  = 'Cadence contains a bad value: {$a}. Every entry must be a positive integer (1, 2, 3, …).';

