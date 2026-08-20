<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — PWA';

// Privacy.
$string['privacy:metadata'] = 'The Sentientia LMS PWA plugin stores web-push subscription endpoints per-user. When push notifications are not enabled, no personal data is stored.';

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
$string['settings_vapid_public_safe_note'] = 'This is the PUBLIC half of the keypair — it is distributed to every browser that subscribes and is safe to share. The PRIVATE half is envelope-encrypted at rest and never displayed in admin UI.';

$string['settings_store_body_label']      = 'Store push title + body in the delivery log';
$string['settings_store_body_desc']       = 'Default OFF — push titles and the first 200 chars of body are SHA-256 hashed before insert, so the log can be safely retained for the default 90 days without GDPR exposure. Turn ON for forensics-mode deployments that need to correlate exact push content with downstream incidents. Existing rows are NOT rewritten.';

$string['settings_vapid_subject_label']   = 'VAPID subject';
$string['settings_vapid_subject_desc']    = 'The contact identifier sent in the JWT to push providers (Google FCM, Mozilla autopush). Must be a mailto: or https: URL. Push vendors use this to contact you about abuse complaints.';

$string['settings_push_defaults_heading'] = 'Push notification defaults';
$string['settings_push_defaults_desc']    = 'Server-side defaults applied when delivering each push. Individual messages can override these.';

$string['settings_default_ttl_label']     = 'Default TTL (seconds)';
$string['settings_default_ttl_desc']      = 'How long the push provider should keep trying to deliver if the device is offline. Default 86400 (24h). Set to 0 to drop the message if undeliverable on first attempt.';

$string['settings_max_payload_label']     = 'Max payload size (bytes)';
$string['settings_max_payload_desc']      = 'Largest payload allowed per push. Web Push spec mandates ≤ 4096 bytes after encryption. Default 3500 leaves headroom for the encryption overhead. Larger payloads will be silently truncated.';

// ── Phase B.3.c — push delivery log strings ──────────────────────────

$string['settings_log_retention_label']   = 'Log retention (days)';
$string['settings_log_retention_desc']    = 'How long to keep rows in <code>mdl_local_sentientia_push_log</code>. A daily cron at 02:00 purges older rows. Set to 0 for unlimited retention (not recommended on chatty deployments — the table grows fast).';

$string['settings_push_log_link']         = 'View push delivery log';
$string['settings_push_log_link_desc']    = 'Operational log of every push attempt. Filterable by result, user, and time window.';

$string['task_push_log_retention']        = 'PWA push log retention (daily purge)';

// Admin viewer page.
$string['push_log_page_title']            = 'PWA push delivery log';
$string['push_log_page_heading']          = 'PWA push delivery log';
$string['push_log_stats_24h']             = 'Last 24 hours';
$string['push_log_stats_line']            = '<strong>{$a->total_24h}</strong> attempts — <span class="text-success"><strong>{$a->sent_24h}</strong> sent</span>, <span class="text-warning">{$a->gone_24h} gone</span>, <span class="text-danger">{$a->failed_24h} failed</span>. <strong>{$a->unique_users_24h}</strong> unique users.';
$string['push_log_filter_apply']          = 'Apply';
$string['push_log_filter_result']         = 'Result';
$string['push_log_filter_since']          = 'Since';
$string['push_log_filter_userid']         = 'User ID';
$string['push_log_filter_any']            = 'Any';
$string['push_log_filter_sent']           = 'Sent';
$string['push_log_filter_failed']         = 'Failed';
$string['push_log_filter_gone']           = 'Gone (sub deleted)';
$string['push_log_filter_truncated']      = 'Truncated';
$string['push_log_since_1h']              = 'Last 1 hour';
$string['push_log_since_24h']             = 'Last 24 hours';
$string['push_log_since_7d']              = 'Last 7 days';
$string['push_log_since_30d']             = 'Last 30 days';
$string['push_log_since_all']             = 'All time';
$string['push_log_no_results']            = 'No push deliveries match the filter.';
$string['push_log_total_count']           = '{$a} matching deliveries';
$string['push_log_col_when']              = 'When';
$string['push_log_col_user']              = 'User';
$string['push_log_col_host']              = 'Push host';
$string['push_log_col_title']             = 'Title';
$string['push_log_col_result']            = 'Result';
$string['push_log_col_http']              = 'HTTP';
$string['push_log_col_error']             = 'Error detail';

// ── Phase B.3.d — iOS install hint banner ─────────────────────────────

$string['ios_hint_title']   = 'Install Sentientia LMS to enable push notifications';
$string['ios_hint_body']    = 'On iOS Safari, push notifications only work when this site is added to your home screen:';
$string['ios_hint_step1']   = 'Tap the Share button at the bottom of the screen.';
$string['ios_hint_step2']   = 'Scroll down and choose "Add to Home Screen".';
$string['ios_hint_step3']   = 'Open Sentientia LMS from your home screen and try Enable notifications again.';
$string['ios_hint_dismiss'] = 'Dismiss';

// ── Audit 2026-05-21 — subscription validation errors ─────────────
$string['invalid_subscription_endpoint']  = 'Subscription endpoint URL is not from a recognised push service.';
$string['invalid_subscription_key_p256dh']= 'Subscription key (p256dh) is malformed.';
$string['invalid_subscription_key_auth']  = 'Subscription auth secret is malformed.';
$string['vapid_master_key_missing']       = 'The VAPID private key is encrypted on disk but the master key is not configured. Set SENTIENTIA_VAPID_MASTER_KEY env var or $CFG->sentientia_vapid_master_key.';
$string['vapid_pem_decrypt_failed']       = 'Failed to decrypt the stored VAPID private key. Either the master key has rotated or the encrypted blob is tampered.';

// ── Phase D.1.b — Install CTA strings ──────────────────────────────
$string['install_cta_title']        = 'Install Sentientia LMS';
$string['install_cta_body']         = 'Add the app to your home screen for one-tap access, push notifications, and offline support.';
$string['install_cta_install_btn']  = 'Install';
$string['install_cta_dismiss']      = 'Not now';
$string['install_cta_dismiss_aria'] = 'Dismiss the install prompt';
$string['install_cta_aria_label']   = 'Install Sentientia LMS app';
$string['install_cta_gotit']        = 'Got it';

// ── Privacy provider 2026-08-04 — real metadata + export + delete ──
$string['privacy:metadata:push_subs']                = 'Web-push subscriptions registered by the user\'s browsers';
$string['privacy:metadata:push_subs:userid']         = 'The user the subscription belongs to';
$string['privacy:metadata:push_subs:endpoint']       = 'The push-service endpoint URL for the user\'s browser';
$string['privacy:metadata:push_subs:p256dh']         = 'The browser-generated public encryption key for this subscription';
$string['privacy:metadata:push_subs:auth_secret']    = 'The browser-generated authentication secret for this subscription';
$string['privacy:metadata:push_subs:user_agent']     = 'The browser user agent that registered the subscription';
$string['privacy:metadata:push_subs:last_seen']      = 'When the subscription last checked in';
$string['privacy:metadata:push_subs:timecreated']    = 'When the subscription was registered';
$string['privacy:metadata:push_log']                 = 'Log of push notifications sent to the user';
$string['privacy:metadata:push_log:userid']          = 'The user the notification was sent to';
$string['privacy:metadata:push_log:endpoint_host']   = 'The push-service host the notification was delivered through';
$string['privacy:metadata:push_log:title']           = 'The notification title';
$string['privacy:metadata:push_log:body_truncated']  = 'A truncated excerpt of the notification body';
$string['privacy:metadata:push_log:url']             = 'The link the notification pointed to';
$string['privacy:metadata:push_log:result']          = 'Whether delivery succeeded or failed';
$string['privacy:metadata:push_log:sent_at']         = 'When the notification was sent';
