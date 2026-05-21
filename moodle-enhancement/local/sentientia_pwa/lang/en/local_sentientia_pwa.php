<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — PWA';

// Privacy.
$string['privacy:metadata'] = 'The Sentientia LMS PWA plugin stores web-push subscription endpoints per-user (Phase B.2+). Phase B.1 (service worker only) stores no personal data.';

// ── Phase B.2.b — subscribe UI strings ────────────────────────────────

// Navigation.
$string['nav_label'] = 'Browser notifications';

// User preferences page.
$string['preferences_page_title']    = 'Browser notifications';
$string['preferences_page_heading']  = 'Browser notifications';
$string['preferences_page_intro']    = 'Enable push notifications on this browser to receive reminders, course updates and assignment alerts without keeping Sentientia LMS open in a tab.';
$string['preferences_install_heading'] = 'Install Sentientia LMS as an app';
$string['preferences_install_intro']   = 'For the best mobile experience, install Sentientia LMS to your home screen from your browser menu. Once installed, push notifications appear like any other app — even when the browser is closed.';

// Subscribe widget.
$string['subscribe_section_title']  = 'Push notifications';
$string['subscribe_intro']          = 'Receive deadline reminders, course updates and assignment alerts in this browser. You can turn them off at any time.';
$string['subscribe_enable']         = 'Enable browser notifications';
$string['subscribe_disable']        = 'Disable browser notifications';
$string['subscribe_unsupported']    = 'Your browser does not support push notifications';
$string['subscribe_denied']         = 'Notifications blocked by your browser';
$string['subscribe_privacy_note']   = 'Push messages are routed through your browser vendor (Google, Mozilla or Apple). We never share the message content with the vendor — only an encrypted blob your browser decrypts locally.';

// VAPID not-set-up notices (admin hasn''t run the keygen CLI yet).
$string['vapid_not_setup_title']    = 'Push notifications not yet configured';
$string['vapid_not_setup_body']     = 'A VAPID keypair has not been generated on this server. Ask your administrator to run "php local/sentientia_pwa/cli/generate_vapid_keys.php" once.';
$string['push_flag_off_notice']     = 'Push delivery is currently disabled by your administrator. You can re-enable it from the Switchboard.';
$string['pwa_disabled_redirect']    = 'The PWA feature is currently disabled. Contact your administrator.';

// Error strings (raised by vapid_key_manager).
$string['vapid_already_exists']     = 'A VAPID keypair already exists. Pass --regenerate to overwrite (invalidates existing subscriptions).';
$string['vapid_openssl_required']   = 'The PHP openssl extension is required to generate VAPID keys.';
$string['vapid_generation_failed']  = 'VAPID key generation failed: {$a}';

// Misc.
$string['missingrequiredfields']    = 'A required field is missing.';

// ── Phase B.2.b — admin settings strings ──────────────────────────────

$string['settings_vapid_heading']         = 'VAPID keypair status';
$string['settings_vapid_ready']           = 'A VAPID keypair is active. Push delivery can be enabled in the Switchboard.';
$string['settings_vapid_not_setup']       = 'No VAPID keypair has been generated. Push notifications will not work until you run the keygen CLI.';
$string['settings_vapid_cli_instruction'] = 'Run the keygen CLI once on the server (typically the web host, as the apache user):';
$string['settings_vapid_public_label']    = 'Public key (base64url)';
$string['settings_vapid_generated_label'] = 'Generated at';
$string['settings_active_subs_label']     = 'Active subscriptions';

$string['settings_vapid_subject_label']   = 'VAPID subject';
$string['settings_vapid_subject_desc']    = 'The contact identifier sent in the JWT to push providers (Google FCM, Mozilla autopush). Must be a mailto: or https: URL. Push vendors use this to contact you about abuse complaints.';

$string['settings_push_defaults_heading'] = 'Push notification defaults';
$string['settings_push_defaults_desc']    = 'Server-side defaults applied when delivering each push. Individual messages can override these.';

$string['settings_default_ttl_label']     = 'Default TTL (seconds)';
$string['settings_default_ttl_desc']      = 'How long the push provider should keep trying to deliver if the device is offline. Default 86400 (24h). Set to 0 to drop the message if undeliverable on first attempt.';

$string['settings_max_payload_label']     = 'Max payload size (bytes)';
$string['settings_max_payload_desc']      = 'Largest payload allowed per push. Web Push spec mandates ≤ 4096 bytes after encryption. Default 3500 leaves headroom for the encryption overhead. Larger payloads will be silently truncated.';
