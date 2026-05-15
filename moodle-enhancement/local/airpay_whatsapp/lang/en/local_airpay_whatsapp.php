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
