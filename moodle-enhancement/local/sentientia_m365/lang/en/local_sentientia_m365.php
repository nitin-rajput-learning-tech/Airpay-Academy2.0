<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Microsoft 365';

// ── Phase C.1 — scaffold strings (no end-user UI yet) ────────────────

// Capability descriptions.
$string['sentientia_m365:use']   = 'Connect a Microsoft 365 account and read your own M365 data through Sentientia LMS';
$string['sentientia_m365:admin'] = 'Administer the Sentientia Microsoft 365 integration (tenant config, revoke any user)';

// Errors / guards.
$string['confirm_required']      = 'This Microsoft 365 call requires an explicit administrator confirmation before it can run. The feature is in scaffold mode.';
$string['feature_off']           = 'The Sentientia Microsoft 365 integration is disabled by the administrator (sentientia_m365_enabled is OFF).';
$string['error_not_configured']  = 'Microsoft 365 integration is not configured. Set the Azure tenant ID, client ID, and redirect URI before connecting.';
$string['error_empty_token']     = 'Cannot store an empty access or refresh token.';
$string['error_token_decrypt']   = 'The stored token could not be decrypted. Disconnect and reconnect your Microsoft 365 account.';
$string['error_invalid_state']   = 'The OAuth state parameter does not match. Please start the connection again.';
$string['error_missing_code']    = 'Microsoft 365 did not return an authorization code. The connection has been cancelled.';
$string['error_scope_required']  = 'The requested operation needs the {$a} scope, which was not granted. Reconnect and grant the additional scope.';

// Settings page.
$string['settings_heading_azure']       = 'Azure AD application';
$string['settings_heading_azure_desc']  = 'Register an application in Azure Portal under Microsoft Entra ID. The redirect URI must match the value you set below exactly.';
$string['setting_azure_tenant_id']      = 'Azure tenant ID';
$string['setting_azure_tenant_id_desc'] = 'GUID of the Microsoft Entra (Azure AD) tenant the integration uses. Use the value "common" only if you want any Microsoft account (work, school, or personal) to be able to connect.';
$string['setting_azure_client_id']      = 'Azure application (client) ID';
$string['setting_azure_client_id_desc'] = 'GUID of the application registration that represents Sentientia LMS in your Azure tenant.';
$string['setting_redirect_uri']         = 'OAuth redirect URI';
$string['setting_redirect_uri_desc']    = 'Full URL of the Sentientia LMS callback endpoint (e.g. https://your.sentientia.example/local/sentientia_m365/callback.php). This must be added to the Azure application registration as a redirect URI.';
$string['setting_allowed_scopes']       = 'Allowed OAuth scopes';
$string['setting_allowed_scopes_desc']  = 'OAuth scopes a user may request when connecting. The default set (openid, profile, offline_access, User.Read) is always granted; additional scopes selected here become optional.';

// Allowed-scope option labels (for the multiselect).
$string['scope_sites_read_all']    = 'SharePoint — read all sites the user can see (Sites.Read.All)';
$string['scope_files_read_all']    = 'SharePoint / OneDrive — read all files the user can see (Files.Read.All)';
$string['scope_calendars_read']    = 'Outlook — read the signed-in user\'s calendars (Calendars.Read)';
$string['scope_calendars_readwrite']  = 'Outlook — read + write the signed-in user\'s calendars (Calendars.ReadWrite)';
$string['scope_team_member_read_all'] = 'Teams — read the signed-in user\'s team memberships (TeamMember.Read.All)';
$string['scope_mail_read']         = 'Outlook — read the signed-in user\'s mailbox (Mail.Read)';

// ── Privacy metadata ────────────────────────────────────────────────
$string['privacy:metadata:tokens'] = 'Microsoft 365 OAuth tokens linked to a Moodle user. Tokens are encrypted with the server\'s Sodium key before they touch the database, but the ciphertext is still considered personal data because it grants access to the user\'s Microsoft account when decrypted.';
$string['privacy:metadata:tokens:userid']            = 'Moodle user the token belongs to.';
$string['privacy:metadata:tokens:customerid']        = 'Sentientia customer scope under which the connection was made.';
$string['privacy:metadata:tokens:access_token_enc']  = 'Encrypted short-lived Microsoft access token. Never exported in plaintext.';
$string['privacy:metadata:tokens:refresh_token_enc'] = 'Encrypted long-lived Microsoft refresh token. Never exported in plaintext.';
$string['privacy:metadata:tokens:expires']           = 'Unix timestamp when the access token expires.';
$string['privacy:metadata:tokens:scopes']            = 'OAuth scopes the user consented to.';
$string['privacy:metadata:tokens:timecreated']       = 'When the connection was first established.';
$string['privacy:metadata:tokens:timemodified']      = 'When the tokens were most recently refreshed or replaced.';

$string['privacy:metadata:microsoft_graph']        = 'Microsoft Graph receives the user\'s access token whenever a Sentientia LMS feature reads M365 data on the user\'s behalf. The user\'s identity claim flows outward; responses from Microsoft Graph (profile fields, calendar events, SharePoint metadata) flow back to the LMS.';
$string['privacy:metadata:microsoft_graph:userid'] = 'The Moodle user on whose behalf the call is made (used to look up the encrypted token).';
$string['privacy:metadata:microsoft_graph:scopes'] = 'OAuth scopes the access token was granted — defines which Graph endpoints can be called.';
