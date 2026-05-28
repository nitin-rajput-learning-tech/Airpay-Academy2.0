<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Airpay WhatsApp & SMS';

// Page chrome
$string['preferences_pagetitle']   = 'Communication preferences';
$string['preferences_nav']         = 'Communication preferences';
$string['preferences_heading']     = 'How would you like Airpay Academy to reach you?';
$string['preferences_intro']       = 'Choose the channels Airpay Academy can use to send you course nudges, deadline reminders, and certificate alerts. Email is always on — it\'s the fallback that catches anything the other channels can\'t deliver.';

// Channel labels
$string['channel_email']           = 'Email';
$string['channel_whatsapp']        = 'WhatsApp';
$string['channel_sms']             = 'SMS';
$string['channel_email_desc']      = 'Always-on. Your work email is the baseline reachable channel.';
$string['channel_whatsapp_desc']   = 'Fastest open rate. Templates are pre-approved under DLT for India.';
$string['channel_sms_desc']        = '95% open rate within an hour. Works without internet, perfect for field staff.';

// Mobile number capture
$string['mobile_label']            = 'Mobile number';
$string['mobile_hint']             = 'Required for WhatsApp and SMS. Include country code (e.g. +91 for India).';
$string['mobile_invalid']          = 'Please enter a valid mobile number with country code (e.g. +919876543210).';

// Primary preference
$string['prefer_label']            = 'Primary channel';
$string['prefer_hint']             = 'When more than one channel is available, this one tries first. The system falls back to email if delivery fails.';

// DLT consent
$string['dlt_consent_heading']     = 'Consent (required for WhatsApp/SMS in India)';
$string['dlt_consent_body']        = 'By opting in, I agree to receive transactional and service messages from Airpay Academy on the channels selected above, in accordance with the Telecom Commercial Communications Customer Preference Regulations 2018 (TCCCPR) and the Digital Personal Data Protection Act 2023 (DPDP). I understand I can withdraw consent at any time by editing this page.';
$string['dlt_consent_required']    = 'You must accept the consent statement to enable WhatsApp or SMS delivery.';
$string['dlt_consent_logged_at']   = 'Consent recorded: {$a}';

// Disabled-by-tenant messaging (when feature flag is off)
$string['channel_disabled_tenant'] = 'This channel is currently disabled for your organisation. Contact your administrator if you\'d like it enabled.';

// Action buttons + status
$string['save_preferences']        = 'Save preferences';
$string['preferences_saved']       = 'Communication preferences updated.';
$string['preferences_unchanged']   = 'No changes to save.';

// Settings page (Phase A1 iter 3+)
$string['settings_pagetitle']         = 'Airpay WhatsApp & SMS — settings';
$string['settings_heading_live_mode'] = 'Provider credentials';
$string['settings_heading_live_mode_desc'] = 'These keys enable live WhatsApp/SMS sending. Until both keys are set AND the Switchboard flags engagement.whatsapp.enabled / engagement.sms.enabled are turned on, the plugin runs in mock mode — messages are logged but never sent to a real provider.';
$string['settings_karix_api_key']     = 'Karix WhatsApp API key';
$string['settings_karix_api_key_desc'] = 'Karix Business API token (https://www.karix.com). Required to flip WhatsApp from mock to live mode.';
$string['settings_msg91_api_key']     = 'MSG91 SMS API key';
$string['settings_msg91_api_key_desc'] = 'MSG91 authkey (https://msg91.com). Required to flip SMS from mock to live mode.';
$string['settings_dlt_pe_id']         = 'DLT Principal Entity ID';
$string['settings_dlt_pe_id_desc']    = 'Your organisation\'s DLT-registered Principal Entity ID (issued by the Indian DLT portal). Required for any operator-bound SMS.';

// Template manager (Phase A1 iter 2)
$string['templates_pagetitle']        = 'DLT template manager';
$string['templates_heading']          = 'DLT-approved message templates';
$string['templates_intro']            = 'Templates must be DLT-registered with the operator before the cadence engine will send them. Statuses move pending → submitted → approved/rejected. Only `approved` templates are usable.';
$string['template_status_updated']    = 'Template status updated.';
$string['show_body']                  = 'Show body';
$string['th_template']                = 'Template key';
$string['th_channel']                 = 'Channel';
$string['th_status']                  = 'Status';
$string['th_dlt_id']                  = 'DLT ID';
$string['th_body']                    = 'Body';
$string['th_actions']                 = 'Actions';
$string['btn_submit']                 = 'Submit to DLT';
$string['btn_approve']                = 'Mark approved';
$string['btn_reject']                 = 'Reject';
$string['btn_redraft']                = 'Redraft';
$string['approved_ready']             = 'Ready to send';

// Analytics dashboard (Phase A1 iter 5)
$string['analytics_pagetitle']        = 'Channel analytics';
$string['analytics_heading']          = 'WhatsApp / SMS / Email channel mix';

// Privacy
$string['privacy:metadata:local_airpay_user_channel_prefs']
    = 'Per-user opt-in preferences for WhatsApp / SMS / email channels, including mobile number and DLT consent timestamp.';
$string['privacy:metadata:local_airpay_user_channel_prefs:userid']
    = 'The user this preference belongs to.';
$string['privacy:metadata:local_airpay_user_channel_prefs:mobile_number']
    = 'Mobile number with country code, used to deliver WhatsApp and SMS messages.';
$string['privacy:metadata:local_airpay_user_channel_prefs:whatsapp_optin']
    = 'Whether the user has opted in to receive WhatsApp messages.';
$string['privacy:metadata:local_airpay_user_channel_prefs:sms_optin']
    = 'Whether the user has opted in to receive SMS messages.';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_at']
    = 'Timestamp when the user gave DLT consent for transactional messaging.';
$string['privacy:metadata:local_airpay_user_channel_prefs:dlt_consent_text']
    = 'A snapshot of the consent language presented to the user when they opted in.';

// ── Stream F / Wave E2 P4 (2026-05-25) — content notifications ──
$string['content_notifications_heading'] = 'Content notifications (WhatsApp)';
$string['content_notifications_intro']   = 'Master switch for the four content-event WhatsApp triggers: new course published in your catalogue, course due in under 48 hours, certificate ready, and learning-path milestone reached. Default off — admin must opt in via the Switchboard. All triggers also require the master WhatsApp engagement flag to be on.';
$string['content_flag_new_course']           = 'New course published in your catalogue';
$string['content_flag_course_due_soon']      = 'Course due in under 48 hours';
$string['content_flag_certificate_ready']    = 'Certificate ready';
$string['content_flag_path_milestone']       = 'Learning-path milestone reached (25 / 50 / 75 / 100%)';
$string['content_template_new_course']       = 'Hi {firstname}, a new course is now available in your catalogue: {course_name}. Start here: {course_url}';
$string['content_template_course_due_soon']  = 'Hi {firstname}, {course_name} is due in {deadline}. Complete it now: {course_url}';
$string['content_template_certificate_ready'] = 'Congratulations {firstname}! Your certificate for {course_name} is ready: {certificate_url}';
$string['content_template_path_milestone']   = 'Hi {firstname}, you have reached {milestone_label} of {path_name}. Keep going: {path_url}';
$string['content_throttle_note']             = 'Duplicate sends of the same trigger to the same learner within {$a} hours are suppressed to protect against burst notifications.';

// C14/F-082 stabilization (2026-05-28) - unified admin landing
$string['admin_index_title'] = 'WhatsApp control panel';
$string['admin_index_intro'] = 'Unified landing for WhatsApp channel administration. Use the quick links below to manage DLT templates, review analytics, or adjust channel settings.';
$string['stats_sent_week'] = 'Sent (last 7 days)';
$string['stats_active_templates'] = 'Active DLT templates';
$string['stats_failures_24h'] = 'Failures (24h)';
$string['stats_flag_on'] = 'Channel ON';
$string['stats_flag_off'] = 'Channel OFF';
$string['stats_flag_label'] = 'Feature flag';
$string['admin_index_quicknav'] = 'Quick navigation';
$string['admin_index_link_templates'] = 'DLT template manager';
$string['admin_index_link_templates_desc'] = 'Add, edit, and approve DLT-compliant message templates.';
$string['admin_index_link_analytics'] = 'Channel analytics';
$string['admin_index_link_analytics_desc'] = 'Send-volume trends, failure rate, and per-template performance.';
$string['admin_index_link_settings'] = 'Channel settings';
$string['admin_index_link_settings_desc'] = 'Provider API keys, sandbox vs live mode, opt-out keywords.';
