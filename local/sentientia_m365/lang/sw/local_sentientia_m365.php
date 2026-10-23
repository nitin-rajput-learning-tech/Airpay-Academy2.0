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
 * Swahili strings for local_sentientia_m365.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_m365
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Microsoft 365';
$string['sentientia_m365:use'] = 'Unganisha akaunti ya Microsoft 365 na usome data zako za M365 kupitia Sentientia LMS';
$string['sentientia_m365:admin'] = 'Simamia muunganisho wa Sentientia na Microsoft 365 (mipangilio ya tenant, kubatilisha mtumiaji yeyote)';
$string['confirm_required'] = 'Ombi hili la Microsoft 365 linahitaji uthibitisho wa wazi wa msimamizi kabla ya kuendeshwa. Kipengele hiki kiko katika hali ya scaffold.';
$string['feature_off'] = 'Muunganisho wa Sentientia na Microsoft 365 umezimwa na msimamizi (sentientia_m365_enabled iko OFF).';
$string['error_not_configured'] = 'Muunganisho wa Microsoft 365 haujasanidiwa. Weka tenant ID ya Azure, client ID na redirect URI kabla ya kuunganisha.';
$string['error_empty_token'] = 'Haiwezekani kuhifadhi access token au refresh token tupu.';
$string['error_token_decrypt'] = 'Token iliyohifadhiwa haikuweza kusimbuliwa. Tenganisha kisha unganisha tena akaunti yako ya Microsoft 365.';
$string['error_invalid_state'] = 'Kigezo cha state cha OAuth hakilingani. Tafadhali anza kuunganisha upya.';
$string['error_missing_code'] = 'Microsoft 365 haikurudisha msimbo wa idhini. Muunganisho umesitishwa.';
$string['error_scope_required'] = 'Kitendo ulichoomba kinahitaji scope ya {$a}, ambayo haikutolewa. Unganisha tena na utoe scope hiyo ya ziada.';
$string['settings_heading_azure'] = 'Programu ya Azure AD';
$string['settings_heading_azure_desc'] = 'Sajili programu kwenye Azure Portal chini ya Microsoft Entra ID. Redirect URI lazima ilingane kabisa na thamani utakayoweka hapa chini.';
$string['setting_azure_tenant_id'] = 'Tenant ID ya Azure';
$string['setting_azure_tenant_id_desc'] = 'GUID ya tenant ya Microsoft Entra (Azure AD) inayotumiwa na muunganisho huu. Tumia thamani "common" tu ikiwa unataka akaunti yoyote ya Microsoft (ya kazini, ya shule au binafsi) iweze kuunganishwa.';
$string['setting_azure_client_id'] = 'Client ID ya programu ya Azure';
$string['setting_azure_client_id_desc'] = 'GUID ya usajili wa programu inayowakilisha Sentientia LMS kwenye tenant yako ya Azure.';
$string['setting_redirect_uri'] = 'Redirect URI ya OAuth';
$string['setting_redirect_uri_desc'] = 'URL kamili ya endpoint ya callback ya Sentientia LMS (k.m. https://your.sentientia.example/local/sentientia_m365/callback.php). Lazima iongezwe kwenye usajili wa programu ya Azure kama redirect URI.';
$string['setting_allowed_scopes'] = 'Scope za OAuth zinazoruhusiwa';
$string['setting_allowed_scopes_desc'] = 'Scope za OAuth ambazo mtumiaji anaweza kuomba anapounganisha. Seti ya msingi (openid, profile, offline_access, User.Read) hutolewa kila mara; scope za ziada utakazochagua hapa zinakuwa za hiari.';
$string['scope_sites_read_all'] = 'SharePoint — soma tovuti zote ambazo mtumiaji anaweza kuona (Sites.Read.All)';
$string['scope_files_read_all'] = 'SharePoint / OneDrive — soma faili zote ambazo mtumiaji anaweza kuona (Files.Read.All)';
$string['scope_calendars_read'] = 'Outlook — soma kalenda za mtumiaji aliyeingia (Calendars.Read)';
$string['scope_calendars_readwrite'] = 'Outlook — soma + andika kalenda za mtumiaji aliyeingia (Calendars.ReadWrite)';
$string['scope_team_member_read_all'] = 'Teams — soma uanachama wa timu wa mtumiaji aliyeingia (TeamMember.Read.All)';
$string['scope_mail_read'] = 'Outlook — soma sanduku la barua pepe la mtumiaji aliyeingia (Mail.Read)';
$string['privacy:metadata:tokens'] = 'Token za OAuth za Microsoft 365 zilizounganishwa na mtumiaji wa Moodle. Token husimbwa kwa ufunguo wa Sodium wa seva kabla ya kufika kwenye hifadhidata, lakini maandishi yaliyosimbwa bado yanachukuliwa kuwa data binafsi kwa sababu yakisimbuliwa yanatoa ufikiaji wa akaunti ya Microsoft ya mtumiaji.';
$string['privacy:metadata:tokens:userid'] = 'Mtumiaji wa Moodle anayemiliki token hii.';
$string['privacy:metadata:tokens:customerid'] = 'Wigo wa mteja wa Sentientia ambao muunganisho ulifanywa chini yake.';
$string['privacy:metadata:tokens:access_token_enc'] = 'Access token ya Microsoft ya muda mfupi iliyosimbwa. Haitolewi kamwe kama maandishi ya wazi.';
$string['privacy:metadata:tokens:refresh_token_enc'] = 'Refresh token ya Microsoft ya muda mrefu iliyosimbwa. Haitolewi kamwe kama maandishi ya wazi.';
$string['privacy:metadata:tokens:expires'] = 'Unix timestamp ya wakati access token inapoisha muda wake.';
$string['privacy:metadata:tokens:scopes'] = 'Scope za OAuth ambazo mtumiaji alizikubali.';
$string['privacy:metadata:tokens:timecreated'] = 'Wakati muunganisho ulipoanzishwa mara ya kwanza.';
$string['privacy:metadata:tokens:timemodified'] = 'Wakati token zilipohuishwa au kubadilishwa mara ya mwisho.';
$string['privacy:metadata:microsoft_graph'] = 'Microsoft Graph hupokea access token ya mtumiaji kila wakati kipengele cha Sentientia LMS kinaposoma data za M365 kwa niaba ya mtumiaji. Taarifa ya utambulisho wa mtumiaji hutoka nje; majibu kutoka Microsoft Graph (sehemu za profile, matukio ya kalenda, metadata ya SharePoint) hurudi kwenye LMS.';
$string['privacy:metadata:microsoft_graph:userid'] = 'Mtumiaji wa Moodle ambaye ombi linafanywa kwa niaba yake (hutumika kutafuta token iliyosimbwa).';
$string['privacy:metadata:microsoft_graph:scopes'] = 'Scope za OAuth zilizotolewa kwa access token — zinaamua ni endpoint zipi za Graph zinaweza kuitwa.';
$string['admin_index_title'] = 'Microsoft 365';
$string['admin_index_intro'] = 'Sanidi na fuatilia muunganisho wa Sentientia LMS na tenant ya Microsoft 365. Awamu C.1 inaleta scaffold ya OAuth; maombi halisi ya Graph yanakuja katika Awamu C.2.';
$string['admin_index_flag_off_notice'] = 'Flag ya kipengele cha Microsoft 365 (sentientia_m365_enabled) kwa sasa iko OFF. Scaffold ya OAuth ipo lakini hakuna endpoint ya Graph inayoweza kufikiwa.';
$string['admin_index_phase_c1_notice'] = 'Awamu C.1 inakuja katika hali ya confirm-required. Kila ombi kwenda graph_client hutupa moodle_exception("confirm_required") — kwa makusudi. Awamu C.2 itabadilisha stubs kwa maombi halisi ya Graph yenye lango la [CONFIRM] kwa kila ombi.';
$string['admin_index_readiness'] = 'Utayari wa MSAL client';
$string['admin_index_ready_yes'] = 'msal_client inaripoti READY — tenant + client + redirect URI ya Azure zimesanidiwa na zinasomeka.';
$string['admin_index_ready_no'] = 'msal_client inaripoti NOT READY — angalau moja kati ya tenant ID ya Azure, client ID au redirect URI haijawekwa au haisomeki. Nenda kwenye Mipangilio ukamilishe usanidi.';
$string['admin_index_roadmap'] = 'Ramani ya mpango';
$string['admin_index_quicknav'] = 'Viungo vya haraka';
$string['admin_index_link_settings'] = 'Mipangilio ya Azure & OAuth';
$string['admin_index_link_settings_desc'] = 'Tenant ID ya Azure, client ID ya programu, redirect URI na uchaguzi wa scope za hiari.';
$string['admin_index_link_privacy'] = 'Faragha & haki za mhusika wa data';
$string['admin_index_link_privacy_desc'] = 'Moodle Usimamizi wa Tovuti → Faragha na Sera. Token za M365 zimejumuishwa kwenye michakato ya kawaida ya kuhamisha na kufuta data.';
$string['admin_index_footnote'] = 'Ukurasa huu unasoma thamani za usanidi na kuhesabu safu za token zilizosimbwa. Hauiti Microsoft Graph na hausimbui token yoyote wakati wa kuendesha.';
$string['stats_configured'] = 'Imesanidiwa';
$string['stats_unconfigured'] = 'Haijawekwa';
$string['stats_tenant_id'] = 'Tenant ID ya Azure';
$string['stats_client_id'] = 'Client ID ya Azure';
$string['stats_flag_label'] = 'Flag ya kipengele';
$string['stats_flag_on'] = 'Imewashwa';
$string['stats_flag_off'] = 'Imezimwa';
$string['stats_connected_users'] = 'Watumiaji waliounganishwa';
$string['roadmap_col_phase'] = 'Awamu';
$string['roadmap_col_what'] = 'Kinacholetwa';
$string['roadmap_col_status'] = 'Hali';
$string['roadmap_done'] = 'Imekamilika';
$string['roadmap_planned'] = 'Imepangwa';
$string['roadmap_c1'] = 'C.1 — Scaffold ya OAuth, stubs za Graph, hifadhi ya token zilizosimbwa, privacy provider.';
$string['roadmap_c2'] = 'C.2 — Kubadilisha stubs za confirm_required kwa maombi halisi ya Graph yenye [CONFIRM] kwa kila ombi. get_me, list_sites, msingi wa kalenda.';
$string['roadmap_c3'] = 'C.3 — Kuingiza nyaraka za SharePoint kwenye pipeline ya SENTIENTIA ya kuchanganua SOP.';
$string['roadmap_c4'] = 'C.4 — Mkutano wa Outlook → sync ya tukio la darasa la LMS.';
$string['roadmap_c5'] = 'C.5 — Kuingiza mahudhurio ya Teams → rekodi ya ukamilishaji.';
$string['roadmap_c6'] = 'C.6 — Prompt + scope maalum kwa kila mteja + UI ya ridhaa kwa Kihindi.';
