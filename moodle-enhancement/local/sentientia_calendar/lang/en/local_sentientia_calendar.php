<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * English strings for local_sentientia_calendar.
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Calendar Sync';

// Navigation.
$string['nav_label'] = 'Calendar subscription';

// User-facing subscription page.
$string['page_title']     = 'Calendar subscription';
$string['page_heading']   = 'Subscribe to your learning calendar';
$string['page_intro']     = 'Add your Sentientia LMS course deadlines, classroom sessions, and exam dates to Outlook, Google Calendar, or Apple Calendar. The link below is personal to you — keep it private.';
$string['events_heading'] = 'What\'s included';
$string['events_courses']   = 'Course completion deadlines for every course you\'re enrolled in';
$string['events_classroom'] = 'Classroom (instructor-led training) session start and end times';
$string['events_exams']     = 'Exam close-dates for the next 90 days';

// Subscription URL widget.
$string['copy_label']        = 'Copy subscription URL';
$string['copied_label']      = 'Copied!';
$string['security_note']     = 'Treat this URL like a password — anyone who has it can read your learning calendar. Use "Regenerate" below if you ever paste it somewhere by accident.';

// Regenerate.
$string['regenerate_label']   = 'Regenerate URL';
$string['regenerate_help']    = 'Invalidates the current URL and issues a fresh one. You\'ll need to update the subscription in every calendar client you\'ve already added.';
$string['regenerate_success'] = 'Subscription URL regenerated. The old link no longer works.';

// How-to.
$string['how_to_heading'] = 'How to subscribe';
$string['how_to_outlook'] = 'Outlook on the web: Calendar ▶ Add calendar ▶ Subscribe from web ▶ paste the URL ▶ name it "Sentientia" ▶ Import. Desktop Outlook: File ▶ Account Settings ▶ Internet Calendars ▶ New ▶ paste the URL.';
$string['how_to_google']  = 'Google Calendar: Other calendars ▶ + ▶ From URL ▶ paste the URL ▶ Add calendar.';
$string['how_to_apple']   = 'Apple Calendar (macOS): File ▶ New Calendar Subscription ▶ paste the URL ▶ Subscribe. iOS: Settings ▶ Calendar ▶ Accounts ▶ Add Account ▶ Other ▶ Add Subscribed Calendar.';

// Errors.
$string['error_flag_off']                 = 'Calendar sync is not currently enabled for your account. Contact your administrator.';
$string['error_token_collision']          = 'Could not generate a unique calendar token after multiple attempts. Please try again.';
$string['error_oauth_clientid_missing']   = 'OAuth client ID is not configured for this provider. Ask an administrator to register the app and add the client ID under Site administration → Plugins → Local plugins → Sentientia Calendar Sync.';
$string['error_oauth_state_invalid']      = 'OAuth state mismatch — the request did not match a pending authorisation. Please start the connect flow again.';
$string['error_oauth_code_missing']       = 'OAuth callback did not include an authorisation code. The provider may have rejected the consent. Please try again.';
$string['error_oauth_no_refresh_token']   = 'No stored refresh token for this provider. Reconnect to the provider to mint a new one.';
$string['oauth_not_live']                 = 'OAuth Phase 2 is currently scaffolding only. Live token exchange will be enabled in a future release once per-customer rollout is confirmed.';

// Scheduled tasks.
$string['task_purge_old_tokens'] = 'Sentientia Calendar — purge revoked tokens';

// Capabilities.
$string['sentientia_calendar:subscribe']  = 'Manage own calendar subscription URL';
$string['sentientia_calendar:manage_all'] = 'Manage any user\'s calendar subscription tokens';

// Settings — Phase 2 OAuth.
$string['settings_pagetitle']               = 'Sentientia Calendar Sync';
$string['settings_section_oauth']           = 'OAuth — Microsoft 365 & Google Calendar (Phase 2)';
$string['settings_section_oauth_desc']      = 'Client IDs and secrets for the bi-directional sync flow. Empty values disable the corresponding "Connect…" button on the user-facing page. The feature flag <code>sentientia.calendar_sync.oauth.enabled</code> must also be ON for the surfaces to render.';
$string['setting_microsoft_client_id']      = 'Microsoft Azure client ID';
$string['setting_microsoft_client_id_desc'] = 'Application (client) ID from the Azure AD app registration. Leave empty to hide the "Connect Outlook" button. Pair this with the redirect URI shown below — Azure must list it verbatim under "Authentication → Web → Redirect URIs".';
$string['setting_microsoft_client_secret']  = 'Microsoft Azure client secret';
$string['setting_microsoft_client_secret_desc'] = 'Client secret VALUE (not the ID) from the Azure app registration. Treated as a secret — never logged, never echoed back to the browser.';
$string['setting_google_client_id']         = 'Google OAuth client ID';
$string['setting_google_client_id_desc']    = 'OAuth 2.0 client ID from the Google Cloud Console. Leave empty to hide the "Connect Google Calendar" button. Pair this with the redirect URI shown below — Google must list it verbatim under "Authorised redirect URIs".';
$string['setting_google_client_secret']     = 'Google OAuth client secret';
$string['setting_google_client_secret_desc'] = 'Client secret from the Google Cloud Console OAuth 2.0 client. Treated as a secret — never logged, never echoed back to the browser.';
$string['setting_redirect_uri']             = 'OAuth redirect URI';
$string['setting_redirect_uri_desc']        = 'Both Microsoft Azure and Google Cloud Console must list this exact URL as an authorised redirect URI before the OAuth dance will succeed. Read-only — derived from <code>$CFG-&gt;wwwroot</code>.';
$string['setting_scaffolding_notice']       = 'Phase 2 ships SCAFFOLDING only. Live token exchange does NOT run yet — the callback handler intentionally throws <code>oauth_not_live</code> so a careless rollout cannot accidentally start hitting the providers. Phase 2.1 will wire up the live HTTP exchange behind the same feature flag.';

// Privacy.
$string['privacy:metadata'] = 'Sentientia LMS Calendar Sync stores one secret subscription token per user. Calendar clients fetch the user\'s personal feed using this token. When Phase 2 OAuth is enabled, it additionally stores encrypted Microsoft 365 and/or Google Calendar OAuth tokens for the user. No course content or third-party data is stored — only the credentials and audit metadata (last-used time, IP, count).';
$string['privacy:metadata:token']                = 'The personal calendar subscription token issued to each user.';
$string['privacy:metadata:token:userid']         = 'The user the token belongs to.';
$string['privacy:metadata:token:token']          = 'The 64-character random token (functionally a credential).';
$string['privacy:metadata:token:last_used_at']   = 'When the token was last used to fetch the calendar feed.';
$string['privacy:metadata:token:last_used_ip']   = 'IP address of the last successful fetch (for abuse forensics).';
$string['privacy:metadata:token:use_count']      = 'Total successful fetch count.';
$string['privacy:metadata:token:timecreated']    = 'When the token was first issued.';
$string['privacy:metadata:token:timemodified']   = 'When the token was last modified (regenerated or revoked).';
$string['privacy:metadata:oauth']                    = 'OAuth access + refresh tokens for Microsoft 365 or Google Calendar — encrypted at rest via Moodle\'s Sodium-backed encryption helper. One row per (user, provider).';
$string['privacy:metadata:oauth:userid']             = 'The user whose calendar the OAuth tokens authorise the LMS to read and write.';
$string['privacy:metadata:oauth:customerid']         = 'The Sentientia LMS customer the user belongs to.';
$string['privacy:metadata:oauth:provider']           = 'Which provider the tokens belong to — m365 (Microsoft Graph) or google (Google Calendar API).';
$string['privacy:metadata:oauth:access_token_enc']   = 'Encrypted short-lived access token (typically 1 hour validity).';
$string['privacy:metadata:oauth:refresh_token_enc']  = 'Encrypted long-lived refresh token used to mint new access tokens without prompting the user.';
$string['privacy:metadata:oauth:expires']            = 'Unix timestamp at which the access token expires.';
$string['privacy:metadata:oauth:scopes']             = 'The OAuth scopes the provider granted at consent time.';
$string['privacy:metadata:oauth:timecreated']        = 'When the OAuth tokens were first issued (initial consent).';
$string['privacy:metadata:oauth:timemodified']       = 'When the OAuth tokens were last refreshed or re-consented.';
$string['privacy:metadata:microsoft_graph']          = 'When the OAuth feature flag is enabled and the user has consented, Sentientia LMS reads and writes calendar events via Microsoft Graph on the user\'s behalf. No data is sent until the user opts in.';
$string['privacy:metadata:microsoft_graph:userid']   = 'The user whose calendar events are read or written.';
$string['privacy:metadata:google_calendar']          = 'When the OAuth feature flag is enabled and the user has consented, Sentientia LMS reads and writes calendar events via the Google Calendar API on the user\'s behalf. No data is sent until the user opts in.';
$string['privacy:metadata:google_calendar:userid']   = 'The user whose calendar events are read or written.';
