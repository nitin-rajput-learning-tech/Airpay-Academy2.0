<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kannada strings for local_sentientia_m365.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_m365
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Microsoft 365';
$string['sentientia_m365:use'] = 'Microsoft 365 ಖಾತೆ ಕನೆಕ್ಟ್ ಮಾಡಿ ಮತ್ತು Sentientia LMS ಮೂಲಕ ನಿಮ್ಮ ಸ್ವಂತ M365 ಡೇಟಾ ಓದಿ';
$string['sentientia_m365:admin'] = 'Sentientia Microsoft 365 ಇಂಟಿಗ್ರೇಶನ್ ನಿರ್ವಹಿಸಿ (ಟೆನೆಂಟ್ ಕಾನ್ಫಿಗ್, ಯಾವುದೇ ಯೂಸರ್‌ನ ಆಕ್ಸೆಸ್ ರದ್ದು ಮಾಡಿ)';
$string['confirm_required'] = 'ಈ Microsoft 365 ಕಾಲ್ ರನ್ ಆಗುವ ಮೊದಲು ಅಡ್ಮಿನ್‌ನ ಸ್ಪಷ್ಟ ದೃಢೀಕರಣ ಬೇಕು. ಈ ಫೀಚರ್ ಸ್ಕ್ಯಾಫೋಲ್ಡ್ ಮೋಡ್‌ನಲ್ಲಿದೆ.';
$string['feature_off'] = 'Sentientia Microsoft 365 ಇಂಟಿಗ್ರೇಶನ್ ಅನ್ನು ಅಡ್ಮಿನ್ ಆಫ್ ಮಾಡಿದ್ದಾರೆ (sentientia_m365_enabled ಆಫ್ ಆಗಿದೆ).';
$string['error_not_configured'] = 'Microsoft 365 ಇಂಟಿಗ್ರೇಶನ್ ಕಾನ್ಫಿಗರ್ ಆಗಿಲ್ಲ. ಕನೆಕ್ಟ್ ಮಾಡುವ ಮೊದಲು Azure ಟೆನೆಂಟ್ ID, ಕ್ಲೈಂಟ್ ID ಮತ್ತು ರೀಡೈರೆಕ್ಟ್ URI ಸೆಟ್ ಮಾಡಿ.';
$string['error_empty_token'] = 'ಖಾಲಿ ಆಕ್ಸೆಸ್ ಅಥವಾ ರಿಫ್ರೆಶ್ ಟೋಕನ್ ಅನ್ನು ಸ್ಟೋರ್ ಮಾಡಲು ಆಗುವುದಿಲ್ಲ.';
$string['error_token_decrypt'] = 'ಸ್ಟೋರ್ ಮಾಡಿದ ಟೋಕನ್ ಅನ್ನು ಡೀಕ್ರಿಪ್ಟ್ ಮಾಡಲು ಆಗಲಿಲ್ಲ. ನಿಮ್ಮ Microsoft 365 ಖಾತೆಯನ್ನು ಡಿಸ್‌ಕನೆಕ್ಟ್ ಮಾಡಿ ಮತ್ತೆ ಕನೆಕ್ಟ್ ಮಾಡಿ.';
$string['error_invalid_state'] = 'OAuth state ಪ್ಯಾರಾಮೀಟರ್ ಹೊಂದಿಕೆಯಾಗುತ್ತಿಲ್ಲ. ಕನೆಕ್ಷನ್ ಅನ್ನು ಮತ್ತೆ ಶುರು ಮಾಡಿ.';
$string['error_missing_code'] = 'Microsoft 365 ಆಥರೈಸೇಶನ್ ಕೋಡ್ ಕಳುಹಿಸಲಿಲ್ಲ. ಕನೆಕ್ಷನ್ ರದ್ದಾಗಿದೆ.';
$string['error_scope_required'] = 'ಕೇಳಿದ ಈ ಕಾರ್ಯಕ್ಕೆ {$a} ಸ್ಕೋಪ್ ಬೇಕು, ಆದರೆ ಅದನ್ನು ನೀಡಿಲ್ಲ. ಮತ್ತೆ ಕನೆಕ್ಟ್ ಮಾಡಿ ಹೆಚ್ಚುವರಿ ಸ್ಕೋಪ್ ನೀಡಿ.';
$string['settings_heading_azure'] = 'Azure AD ಅಪ್ಲಿಕೇಶನ್';
$string['settings_heading_azure_desc'] = 'Azure Portal ನಲ್ಲಿ Microsoft Entra ID ಅಡಿಯಲ್ಲಿ ಒಂದು ಅಪ್ಲಿಕೇಶನ್ ರಿಜಿಸ್ಟರ್ ಮಾಡಿ. ರೀಡೈರೆಕ್ಟ್ URI ಕೆಳಗೆ ನೀವು ಸೆಟ್ ಮಾಡುವ ವ್ಯಾಲ್ಯೂಗೆ ನಿಖರವಾಗಿ ಹೊಂದಿಕೆಯಾಗಬೇಕು.';
$string['setting_azure_tenant_id'] = 'Azure ಟೆನೆಂಟ್ ID';
$string['setting_azure_tenant_id_desc'] = 'ಈ ಇಂಟಿಗ್ರೇಶನ್ ಬಳಸುವ Microsoft Entra (Azure AD) ಟೆನೆಂಟ್‌ನ GUID. ಯಾವುದೇ Microsoft ಖಾತೆ (ಆಫೀಸ್, ಶಾಲೆ ಅಥವಾ ವೈಯಕ್ತಿಕ) ಕನೆಕ್ಟ್ ಆಗಲು ಬಿಡಬೇಕಾದರೆ ಮಾತ್ರ "common" ವ್ಯಾಲ್ಯೂ ಬಳಸಿ.';
$string['setting_azure_client_id'] = 'Azure ಅಪ್ಲಿಕೇಶನ್ (ಕ್ಲೈಂಟ್) ID';
$string['setting_azure_client_id_desc'] = 'ನಿಮ್ಮ Azure ಟೆನೆಂಟ್‌ನಲ್ಲಿ Sentientia LMS ಅನ್ನು ಪ್ರತಿನಿಧಿಸುವ ಅಪ್ಲಿಕೇಶನ್ ರಿಜಿಸ್ಟ್ರೇಶನ್‌ನ GUID.';
$string['setting_redirect_uri'] = 'OAuth ರೀಡೈರೆಕ್ಟ್ URI';
$string['setting_redirect_uri_desc'] = 'Sentientia LMS ಕಾಲ್‌ಬ್ಯಾಕ್ ಎಂಡ್‌ಪಾಯಿಂಟ್‌ನ ಪೂರ್ಣ URL (ಉದಾ. https://your.sentientia.example/local/sentientia_m365/callback.php). ಇದನ್ನು Azure ಅಪ್ಲಿಕೇಶನ್ ರಿಜಿಸ್ಟ್ರೇಶನ್‌ಗೆ ರೀಡೈರೆಕ್ಟ್ URI ಆಗಿ ಸೇರಿಸಬೇಕು.';
$string['setting_allowed_scopes'] = 'ಅನುಮತಿಸಿದ OAuth ಸ್ಕೋಪ್‌ಗಳು';
$string['setting_allowed_scopes_desc'] = 'ಕನೆಕ್ಟ್ ಮಾಡುವಾಗ ಯೂಸರ್ ಕೇಳಬಹುದಾದ OAuth ಸ್ಕೋಪ್‌ಗಳು. ಡೀಫಾಲ್ಟ್ ಸೆಟ್ (openid, profile, offline_access, User.Read) ಯಾವಾಗಲೂ ನೀಡಲಾಗುತ್ತದೆ; ಇಲ್ಲಿ ಆಯ್ಕೆ ಮಾಡುವ ಹೆಚ್ಚುವರಿ ಸ್ಕೋಪ್‌ಗಳು ಐಚ್ಛಿಕವಾಗುತ್ತವೆ.';
$string['scope_sites_read_all'] = 'SharePoint — ಯೂಸರ್ ನೋಡಬಹುದಾದ ಎಲ್ಲಾ ಸೈಟ್‌ಗಳನ್ನು ಓದಿ (Sites.Read.All)';
$string['scope_files_read_all'] = 'SharePoint / OneDrive — ಯೂಸರ್ ನೋಡಬಹುದಾದ ಎಲ್ಲಾ ಫೈಲ್‌ಗಳನ್ನು ಓದಿ (Files.Read.All)';
$string['scope_calendars_read'] = 'Outlook — ಲಾಗಿನ್ ಆಗಿರುವ ಯೂಸರ್‌ನ ಕ್ಯಾಲೆಂಡರ್‌ಗಳನ್ನು ಓದಿ (Calendars.Read)';
$string['scope_calendars_readwrite'] = 'Outlook — ಲಾಗಿನ್ ಆಗಿರುವ ಯೂಸರ್‌ನ ಕ್ಯಾಲೆಂಡರ್‌ಗಳನ್ನು ಓದಿ + ಬರೆಯಿರಿ (Calendars.ReadWrite)';
$string['scope_team_member_read_all'] = 'Teams — ಲಾಗಿನ್ ಆಗಿರುವ ಯೂಸರ್‌ನ ಟೀಮ್ ಮೆಂಬರ್‌ಶಿಪ್‌ಗಳನ್ನು ಓದಿ (TeamMember.Read.All)';
$string['scope_mail_read'] = 'Outlook — ಲಾಗಿನ್ ಆಗಿರುವ ಯೂಸರ್‌ನ ಮೇಲ್‌ಬಾಕ್ಸ್ ಓದಿ (Mail.Read)';
$string['privacy:metadata:tokens'] = 'Moodle ಯೂಸರ್‌ಗೆ ಲಿಂಕ್ ಆಗಿರುವ Microsoft 365 OAuth ಟೋಕನ್‌ಗಳು. ಟೋಕನ್‌ಗಳು ಡೇಟಾಬೇಸ್ ತಲುಪುವ ಮೊದಲೇ ಸರ್ವರ್‌ನ Sodium ಕೀ ಮೂಲಕ ಎನ್‌ಕ್ರಿಪ್ಟ್ ಆಗುತ್ತವೆ, ಆದರೂ ಸೈಫರ್‌ಟೆಕ್ಸ್ಟ್ ಅನ್ನು ವೈಯಕ್ತಿಕ ಡೇಟಾ ಎಂದೇ ಪರಿಗಣಿಸಲಾಗುತ್ತದೆ, ಏಕೆಂದರೆ ಡೀಕ್ರಿಪ್ಟ್ ಮಾಡಿದಾಗ ಅದು ಯೂಸರ್‌ನ Microsoft ಖಾತೆಗೆ ಆಕ್ಸೆಸ್ ಕೊಡುತ್ತದೆ.';
$string['privacy:metadata:tokens:userid'] = 'ಟೋಕನ್ ಸೇರಿರುವ Moodle ಯೂಸರ್.';
$string['privacy:metadata:tokens:customerid'] = 'ಕನೆಕ್ಷನ್ ಮಾಡಿದ Sentientia ಕಸ್ಟಮರ್ ಸ್ಕೋಪ್.';
$string['privacy:metadata:tokens:access_token_enc'] = 'ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ, ಅಲ್ಪಾವಧಿಯ Microsoft ಆಕ್ಸೆಸ್ ಟೋಕನ್. ಎಂದಿಗೂ ಪ್ಲೇನ್‌ಟೆಕ್ಸ್ಟ್‌ನಲ್ಲಿ ಎಕ್ಸ್‌ಪೋರ್ಟ್ ಆಗುವುದಿಲ್ಲ.';
$string['privacy:metadata:tokens:refresh_token_enc'] = 'ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ, ದೀರ್ಘಾವಧಿಯ Microsoft ರಿಫ್ರೆಶ್ ಟೋಕನ್. ಎಂದಿಗೂ ಪ್ಲೇನ್‌ಟೆಕ್ಸ್ಟ್‌ನಲ್ಲಿ ಎಕ್ಸ್‌ಪೋರ್ಟ್ ಆಗುವುದಿಲ್ಲ.';
$string['privacy:metadata:tokens:expires'] = 'ಆಕ್ಸೆಸ್ ಟೋಕನ್‌ನ ಅವಧಿ ಮುಗಿಯುವ Unix ಟೈಮ್‌ಸ್ಟ್ಯಾಂಪ್.';
$string['privacy:metadata:tokens:scopes'] = 'ಯೂಸರ್ ಒಪ್ಪಿಗೆ ನೀಡಿದ OAuth ಸ್ಕೋಪ್‌ಗಳು.';
$string['privacy:metadata:tokens:timecreated'] = 'ಕನೆಕ್ಷನ್ ಮೊದಲ ಬಾರಿ ಮಾಡಿದ ಸಮಯ.';
$string['privacy:metadata:tokens:timemodified'] = 'ಟೋಕನ್‌ಗಳನ್ನು ಕೊನೆಯ ಬಾರಿ ರಿಫ್ರೆಶ್ ಮಾಡಿದ ಅಥವಾ ಬದಲಾಯಿಸಿದ ಸಮಯ.';
$string['privacy:metadata:microsoft_graph'] = 'Sentientia LMS ನ ಯಾವುದೇ ಫೀಚರ್ ಯೂಸರ್ ಪರವಾಗಿ M365 ಡೇಟಾ ಓದಿದಾಗಲೆಲ್ಲಾ Microsoft Graph ಗೆ ಯೂಸರ್‌ನ ಆಕ್ಸೆಸ್ ಟೋಕನ್ ಹೋಗುತ್ತದೆ. ಯೂಸರ್‌ನ ಐಡೆಂಟಿಟಿ ಕ್ಲೇಮ್ ಹೊರಗೆ ಹೋಗುತ್ತದೆ; Microsoft Graph ನಿಂದ ಬರುವ ರೆಸ್ಪಾನ್ಸ್‌ಗಳು (ಪ್ರೊಫೈಲ್ ಫೀಲ್ಡ್‌ಗಳು, ಕ್ಯಾಲೆಂಡರ್ ಈವೆಂಟ್‌ಗಳು, SharePoint ಮೆಟಾಡೇಟಾ) LMS ಗೆ ಹಿಂತಿರುಗುತ್ತವೆ.';
$string['privacy:metadata:microsoft_graph:userid'] = 'ಯಾರ ಪರವಾಗಿ ಕಾಲ್ ಮಾಡಲಾಗುತ್ತದೆಯೋ ಆ Moodle ಯೂಸರ್ (ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ ಟೋಕನ್ ಹುಡುಕಲು ಬಳಸಲಾಗುತ್ತದೆ).';
$string['privacy:metadata:microsoft_graph:scopes'] = 'ಆಕ್ಸೆಸ್ ಟೋಕನ್‌ಗೆ ನೀಡಿದ OAuth ಸ್ಕೋಪ್‌ಗಳು — ಯಾವ Graph ಎಂಡ್‌ಪಾಯಿಂಟ್‌ಗಳನ್ನು ಕಾಲ್ ಮಾಡಬಹುದು ಎಂಬುದನ್ನು ಇವು ನಿರ್ಧರಿಸುತ್ತವೆ.';
$string['admin_index_title'] = 'Microsoft 365';
$string['admin_index_intro'] = 'Microsoft 365 ಟೆನೆಂಟ್‌ಗೆ Sentientia LMS ಕನೆಕ್ಷನ್ ಅನ್ನು ಕಾನ್ಫಿಗರ್ ಮಾಡಿ ಮತ್ತು ಮಾನಿಟರ್ ಮಾಡಿ. ಫೇಸ್ C.1 ನಲ್ಲಿ OAuth ಸ್ಕ್ಯಾಫೋಲ್ಡ್ ಬರುತ್ತದೆ; ಲೈವ್ Graph ಕಾಲ್‌ಗಳು ಫೇಸ್ C.2 ನಲ್ಲಿ ಬರುತ್ತವೆ.';
$string['admin_index_flag_off_notice'] = 'Microsoft 365 ಫೀಚರ್ ಫ್ಲ್ಯಾಗ್ (sentientia_m365_enabled) ಈಗ ಆಫ್ ಆಗಿದೆ. OAuth ಸ್ಕ್ಯಾಫೋಲ್ಡ್ ಇದೆ, ಆದರೆ ಯಾವುದೇ Graph ಎಂಡ್‌ಪಾಯಿಂಟ್ ತಲುಪಲು ಆಗುವುದಿಲ್ಲ.';
$string['admin_index_phase_c1_notice'] = 'ಫೇಸ್ C.1 confirm-required ಮೋಡ್‌ನಲ್ಲಿ ಬರುತ್ತದೆ. graph_client ಗೆ ಮಾಡುವ ಪ್ರತಿ ಕಾಲ್ moodle_exception("confirm_required") ಎಸೆಯುತ್ತದೆ — ಇದು ಉದ್ದೇಶಪೂರ್ವಕ. ಫೇಸ್ C.2 ನಲ್ಲಿ ಸ್ಟಬ್‌ಗಳ ಬದಲು, ಪ್ರತಿ ಕಾಲ್‌ಗೂ [CONFIRM] ಗೇಟ್ ಇರುವ ನಿಜವಾದ Graph ಕಾಲ್‌ಗಳು ಬರುತ್ತವೆ.';
$string['admin_index_readiness'] = 'MSAL ಕ್ಲೈಂಟ್ ಸಿದ್ಧತೆ';
$string['admin_index_ready_yes'] = 'msal_client READY ಎಂದು ತೋರಿಸುತ್ತಿದೆ — Azure ಟೆನೆಂಟ್ + ಕ್ಲೈಂಟ್ + ರೀಡೈರೆಕ್ಟ್ URI ಕಾನ್ಫಿಗರ್ ಆಗಿವೆ ಮತ್ತು ಸರಿಯಾಗಿ ಓದಬಹುದು.';
$string['admin_index_ready_no'] = 'msal_client NOT READY ಎಂದು ತೋರಿಸುತ್ತಿದೆ — Azure ಟೆನೆಂಟ್ ID, ಕ್ಲೈಂಟ್ ID ಅಥವಾ ರೀಡೈರೆಕ್ಟ್ URI ಯಲ್ಲಿ ಕನಿಷ್ಠ ಒಂದು ಸೆಟ್ ಆಗಿಲ್ಲ ಅಥವಾ ಸರಿಯಾಗಿ ಓದಲು ಆಗುತ್ತಿಲ್ಲ. ಕಾನ್ಫಿಗರೇಶನ್ ಪೂರ್ಣಗೊಳಿಸಲು ಸೆಟ್ಟಿಂಗ್‌ಗಳಿಗೆ ಹೋಗಿ.';
$string['admin_index_roadmap'] = 'ರೋಡ್‌ಮ್ಯಾಪ್';
$string['admin_index_quicknav'] = 'ಕ್ವಿಕ್ ನ್ಯಾವಿಗೇಶನ್';
$string['admin_index_link_settings'] = 'Azure & OAuth ಸೆಟ್ಟಿಂಗ್‌ಗಳು';
$string['admin_index_link_settings_desc'] = 'Azure ಟೆನೆಂಟ್ ID, ಅಪ್ಲಿಕೇಶನ್ ಕ್ಲೈಂಟ್ ID, ರೀಡೈರೆಕ್ಟ್ URI ಮತ್ತು ಐಚ್ಛಿಕ ಸ್ಕೋಪ್‌ಗಳ ಮಲ್ಟಿಸೆಲೆಕ್ಟ್.';
$string['admin_index_link_privacy'] = 'ಗೌಪ್ಯತೆ & ಡೇಟಾ ಸಬ್ಜೆಕ್ಟ್ ಹಕ್ಕುಗಳು';
$string['admin_index_link_privacy_desc'] = 'Moodle ಸೈಟ್ ಅಡ್ಮಿನಿಸ್ಟ್ರೇಶನ್ → ಗೌಪ್ಯತೆ & ಪಾಲಿಸಿಗಳು. M365 ಟೋಕನ್‌ಗಳು ಸ್ಟ್ಯಾಂಡರ್ಡ್ ಡೇಟಾ ಎಕ್ಸ್‌ಪೋರ್ಟ್ ಮತ್ತು ಡೇಟಾ ಅಳಿಸುವಿಕೆ ಫ್ಲೋಗಳಲ್ಲಿ ಸೇರಿವೆ.';
$string['admin_index_footnote'] = 'ಈ ಪುಟ ಕಾನ್ಫಿಗರೇಶನ್ ವ್ಯಾಲ್ಯೂಗಳನ್ನು ಓದುತ್ತದೆ ಮತ್ತು ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ ಟೋಕನ್ ಸಾಲುಗಳನ್ನು ಎಣಿಸುತ್ತದೆ. ಇದು Microsoft Graph ಅನ್ನು ಕಾಲ್ ಮಾಡುವುದಿಲ್ಲ ಮತ್ತು ರನ್‌ಟೈಮ್‌ನಲ್ಲಿ ಯಾವುದೇ ಟೋಕನ್ ಅನ್ನು ಡೀಕ್ರಿಪ್ಟ್ ಮಾಡುವುದಿಲ್ಲ.';
$string['stats_configured'] = 'ಕಾನ್ಫಿಗರ್ ಆಗಿದೆ';
$string['stats_unconfigured'] = 'ಸೆಟ್ ಆಗಿಲ್ಲ';
$string['stats_tenant_id'] = 'Azure ಟೆನೆಂಟ್ ID';
$string['stats_client_id'] = 'Azure ಕ್ಲೈಂಟ್ ID';
$string['stats_flag_label'] = 'ಫೀಚರ್ ಫ್ಲ್ಯಾಗ್';
$string['stats_flag_on'] = 'ಆನ್ ಆಗಿದೆ';
$string['stats_flag_off'] = 'ಆಫ್ ಆಗಿದೆ';
$string['stats_connected_users'] = 'ಕನೆಕ್ಟ್ ಆದ ಯೂಸರ್‌ಗಳು';
$string['roadmap_col_phase'] = 'ಫೇಸ್';
$string['roadmap_col_what'] = 'ಏನು ಬರುತ್ತದೆ';
$string['roadmap_col_status'] = 'ಸ್ಟೇಟಸ್';
$string['roadmap_done'] = 'ಮುಗಿದಿದೆ';
$string['roadmap_planned'] = 'ಯೋಜನೆಯಲ್ಲಿದೆ';
$string['roadmap_c1'] = 'C.1 — OAuth ಸ್ಕ್ಯಾಫೋಲ್ಡ್, Graph ಸ್ಟಬ್‌ಗಳು, ಎನ್‌ಕ್ರಿಪ್ಟ್ ಮಾಡಿದ ಟೋಕನ್ ಸ್ಟೋರೇಜ್, ಗೌಪ್ಯತೆ ಪ್ರೊವೈಡರ್.';
$string['roadmap_c2'] = 'C.2 — confirm_required ಸ್ಟಬ್‌ಗಳ ಬದಲು ಪ್ರತಿ ಕಾಲ್‌ಗೂ [CONFIRM] ಇರುವ ನಿಜವಾದ Graph ಕಾಲ್‌ಗಳು. get_me, list_sites, ಕ್ಯಾಲೆಂಡರ್ ಬೇಸ್‌ಲೈನ್.';
$string['roadmap_c3'] = 'C.3 — SharePoint ಡಾಕ್ಯುಮೆಂಟ್‌ಗಳನ್ನು SENTIENTIA SOP ಪಾರ್ಸರ್ ಪೈಪ್‌ಲೈನ್‌ಗೆ ಸೇರಿಸುವುದು.';
$string['roadmap_c4'] = 'C.4 — Outlook ಮೀಟಿಂಗ್ → LMS ಕ್ಲಾಸ್‌ರೂಮ್ ಈವೆಂಟ್ ಸಿಂಕ್.';
$string['roadmap_c5'] = 'C.5 — Teams ಹಾಜರಾತಿ ಇಂಪೋರ್ಟ್ → ಪೂರ್ಣಗೊಳಿಸುವಿಕೆಯ ದಾಖಲೆ.';
$string['roadmap_c6'] = 'C.6 — ಪ್ರತಿ ಕಸ್ಟಮರ್‌ಗೆ ಪ್ರಾಂಪ್ಟ್ + ಸ್ಕೋಪ್ ಓವರ್‌ರೈಡ್‌ಗಳು + ಹಿಂದಿ ಒಪ್ಪಿಗೆ UI.';
