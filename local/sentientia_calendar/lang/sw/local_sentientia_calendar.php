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
 * Swahili strings for local_sentientia_calendar.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_calendar
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Usawazishaji wa Kalenda';
$string['nav_label'] = 'Usajili wa kalenda';
$string['page_title'] = 'Usajili wa kalenda';
$string['page_heading'] = 'Jisajili kwenye kalenda yako ya mafunzo';
$string['page_intro'] = 'Ongeza tarehe za mwisho za kozi zako za Sentientia LMS, vipindi vya darasani, na tarehe za mitihani kwenye Outlook, Google Calendar, au Apple Calendar. Kiungo kilicho hapa chini ni chako binafsi — kiweke siri.';
$string['events_heading'] = 'Kilichojumuishwa';
$string['events_courses'] = 'Tarehe za mwisho za kukamilisha kila kozi uliyosajiliwa';
$string['events_classroom'] = 'Muda wa kuanza na kumaliza vipindi vya darasani (mafunzo yanayoongozwa na mkufunzi)';
$string['events_exams'] = 'Tarehe za kufungwa kwa mitihani kwa siku 90 zijazo';
$string['copy_label'] = 'Nakili URL ya usajili';
$string['copied_label'] = 'Imenakiliwa!';
$string['security_note'] = 'Ichukulie URL hii kama nenosiri — yeyote aliye nayo anaweza kusoma kalenda yako ya mafunzo. Tumia "Tengeneza URL upya" hapa chini ikiwa utaibandika mahali fulani kwa bahati mbaya.';
$string['regenerate_label'] = 'Tengeneza URL upya';
$string['regenerate_help'] = 'Inabatilisha URL ya sasa na kutoa mpya. Utahitaji kusasisha usajili katika kila app ya kalenda ambayo umeshaiongeza.';
$string['regenerate_success'] = 'URL ya usajili imetengenezwa upya. Kiungo cha zamani hakifanyi kazi tena.';
$string['how_to_heading'] = 'Jinsi ya kujisajili';
$string['how_to_outlook'] = 'Outlook kwenye wavuti: Calendar ▶ Add calendar ▶ Subscribe from web ▶ bandika URL ▶ iite "Sentientia" ▶ Import. Outlook ya kompyuta: File ▶ Account Settings ▶ Internet Calendars ▶ New ▶ bandika URL.';
$string['how_to_google'] = 'Google Calendar: Other calendars ▶ + ▶ From URL ▶ bandika URL ▶ Add calendar.';
$string['how_to_apple'] = 'Apple Calendar (macOS): File ▶ New Calendar Subscription ▶ bandika URL ▶ Subscribe. iOS: Settings ▶ Calendar ▶ Accounts ▶ Add Account ▶ Other ▶ Add Subscribed Calendar.';
$string['oauth_heading'] = 'Usawazishaji wa kalenda wa pande mbili (Beta)';
$string['oauth_intro'] = 'Unganisha kalenda yako ya Outlook au Google ili tarehe za mwisho za Sentientia LMS ziingie humo moja kwa moja, na majibu yako (kubali / kataa) yarudi Sentientia kiotomatiki.';
$string['oauth_provider_m365'] = 'Microsoft 365 (Outlook)';
$string['oauth_provider_desc_m365'] = 'Kusoma + kuandika matukio kwenye kalenda yako ya Outlook. Tunagusa tu matukio ambayo LMS yenyewe imeongeza.';
$string['oauth_provider_google'] = 'Google Calendar';
$string['oauth_provider_desc_google'] = 'Kusoma + kuandika matukio ambayo LMS inaunda kwenye kalenda yako ya Google. Hatuoni kamwe matukio yako binafsi.';
$string['oauth_connect_m365'] = 'Unganisha Outlook';
$string['oauth_connect_google'] = 'Unganisha Google Calendar';
$string['oauth_reconnect'] = 'Unganisha upya';
$string['oauth_disconnect'] = 'Tenganisha';
$string['oauth_status_connected'] = 'Imeunganishwa (muda unaisha {$a->date})';
$string['oauth_status_expired'] = 'Muda umeisha — tafadhali unganisha upya';
$string['oauth_status_disconnected'] = 'Haijaunganishwa';
$string['connect_success_m365'] = 'Kalenda ya Microsoft 365 imeunganishwa — tarehe zako za mwisho za Sentientia zitaanza kusawazishwa hivi punde.';
$string['connect_success_google'] = 'Google Calendar imeunganishwa — tarehe zako za mwisho za Sentientia zitaanza kusawazishwa hivi punde.';
$string['disconnect_success_m365'] = 'Kalenda ya Microsoft 365 imetenganishwa. Matukio ambayo tayari yametumwa yatabaki kwenye kalenda yako.';
$string['disconnect_success_google'] = 'Google Calendar imetenganishwa. Matukio ambayo tayari yametumwa yatabaki kwenye kalenda yako.';
$string['connect_error'] = 'Mtoa huduma wa kalenda amekataa muunganisho ({$a->code}). {$a->description}';
$string['error_flag_off'] = 'Usawazishaji wa kalenda haujawashwa kwa akaunti yako kwa sasa. Wasiliana na msimamizi wako.';
$string['error_token_collision'] = 'Imeshindikana kutengeneza tokeni ya kipekee ya kalenda baada ya majaribio kadhaa. Tafadhali jaribu tena.';
$string['error_oauth_clientid_missing'] = 'Client ID ya OAuth haijawekwa kwa mtoa huduma huyu. Mwombe msimamizi asajili app na aongeze client ID chini ya Site administration → Plugins → Local plugins → Sentientia Calendar Sync.';
$string['error_oauth_state_invalid'] = 'OAuth state hailingani — ombi halikulingana na idhini yoyote inayosubiri. Tafadhali anza upya mchakato wa kuunganisha.';
$string['error_oauth_code_missing'] = 'Callback ya OAuth haikuwa na msimbo wa idhini. Huenda mtoa huduma amekataa ridhaa. Tafadhali jaribu tena.';
$string['error_oauth_no_refresh_token'] = 'Hakuna tokeni ya kuhuisha (refresh token) iliyohifadhiwa kwa mtoa huduma huyu. Unganisha upya na mtoa huduma ili kupata mpya.';
$string['error_oauth_token_response'] = 'Mtoa huduma wa kalenda ametoa jibu lisilotarajiwa ({$a}). Tafadhali jaribu tena au wasiliana na msimamizi wako.';
$string['error_oauth_invalid_grant'] = 'Mtoa huduma wako wa kalenda ameondoa ruhusa yetu. Tafadhali unganisha upya ili kutoa ridhaa mpya.';
$string['error_oauth_http_failure'] = 'Imeshindikana kumfikia mtoa huduma wa kalenda ({$a}). Huenda mtandao au mtoa huduma hapatikani — tafadhali jaribu tena baada ya dakika chache.';
$string['error_oauth_unknown_provider'] = 'Mtoa huduma wa OAuth asiyejulikana: {$a}.';
$string['oauth_not_live'] = 'OAuth Awamu ya 2 kwa sasa ni msingi wa awali (scaffolding) tu. Ubadilishanaji halisi wa tokeni utawezeshwa katika toleo lijalo baada ya usambazaji kwa kila mteja kuthibitishwa.';
$string['task_purge_old_tokens'] = 'Sentientia Calendar — futa tokeni zilizobatilishwa';
$string['sentientia_calendar:subscribe'] = 'Simamia URL yako ya usajili wa kalenda';
$string['sentientia_calendar:manage_all'] = 'Simamia tokeni za usajili wa kalenda za mtumiaji yeyote';
$string['settings_pagetitle'] = 'Usawazishaji wa Kalenda wa Sentientia';
$string['settings_section_oauth'] = 'OAuth — Microsoft 365 na Google Calendar';
$string['settings_section_oauth_desc'] = 'Client ID na siri za mtiririko wa usawazishaji wa pande mbili. Thamani tupu huzima kitufe husika cha "Unganisha…" kwenye ukurasa wa watumiaji. Feature flag <code>sentientia.calendar_sync.oauth.enabled</code> lazima pia iwe ON ili sehemu hizi zionekane.';
$string['setting_microsoft_client_id'] = 'Client ID ya Microsoft Azure';
$string['setting_microsoft_client_id_desc'] = 'Application (client) ID kutoka kwenye usajili wa app wa Azure AD. Acha wazi ili kuficha kitufe cha "Unganisha Outlook". Itumie pamoja na redirect URI inayoonyeshwa hapa chini — Azure lazima iiorodheshe neno kwa neno chini ya "Authentication → Web → Redirect URIs".';
$string['setting_microsoft_client_secret'] = 'Client secret ya Microsoft Azure';
$string['setting_microsoft_client_secret_desc'] = 'THAMANI ya client secret (si ID) kutoka kwenye usajili wa app wa Azure. Inashughulikiwa kama siri — haiandikwi kwenye kumbukumbu (log) kamwe, wala hairudishwi kwenye kivinjari.';
$string['setting_google_client_id'] = 'Client ID ya Google OAuth';
$string['setting_google_client_id_desc'] = 'Client ID ya OAuth 2.0 kutoka Google Cloud Console. Acha wazi ili kuficha kitufe cha "Unganisha Google Calendar". Itumie pamoja na redirect URI inayoonyeshwa hapa chini — Google lazima iiorodheshe neno kwa neno chini ya "Authorised redirect URIs".';
$string['setting_google_client_secret'] = 'Client secret ya Google OAuth';
$string['setting_google_client_secret_desc'] = 'Client secret kutoka kwa OAuth 2.0 client ya Google Cloud Console. Inashughulikiwa kama siri — haiandikwi kwenye kumbukumbu (log) kamwe, wala hairudishwi kwenye kivinjari.';
$string['setting_redirect_uri'] = 'Redirect URI ya OAuth';
$string['setting_redirect_uri_desc'] = 'Microsoft Azure na Google Cloud Console zote mbili lazima ziorodheshe URL hii kamili kama redirect URI iliyoidhinishwa ili mchakato wa OAuth ufanikiwe. Ni ya kusoma tu — inatokana na <code>$CFG-&gt;wwwroot</code>.';
$string['setting_scaffolding_notice'] = 'OAuth Awamu ya 2.1 inaleta ubadilishanaji halisi wa tokeni nyuma ya master feature flag <code>sentientia.calendar_sync.oauth.enabled</code> (chaguo-msingi OFF). Kujaza vitambulisho hapa chini kunaandaa muunganisho; kuwasha flag kuwa ON kwa mteja kunauwezesha. Majaribio yanatumia mock endpoints — hakuna maombi halisi ya OAuth yanayoendeshwa kutoka CI.';
$string['privacy:metadata'] = 'Usawazishaji wa Kalenda wa Sentientia LMS huhifadhi tokeni moja ya siri ya usajili kwa kila mtumiaji. App za kalenda hupata mlisho binafsi wa mtumiaji kwa kutumia tokeni hii. Awamu ya 2 ya OAuth ikiwashwa, pia huhifadhi tokeni za OAuth za Microsoft 365 na/au Google Calendar zilizosimbwa kwa ajili ya mtumiaji. Hakuna maudhui ya kozi wala data ya wahusika wengine inayohifadhiwa — ni vitambulisho na metadata ya ukaguzi pekee (wakati tokeni ilipotumika mara ya mwisho, IP, idadi).';
$string['privacy:metadata:token'] = 'Tokeni binafsi ya usajili wa kalenda iliyotolewa kwa kila mtumiaji.';
$string['privacy:metadata:token:userid'] = 'Mtumiaji anayemiliki tokeni hii.';
$string['privacy:metadata:token:token'] = 'Tokeni nasibu ya herufi 64 (kiutendaji ni kitambulisho cha kuingia).';
$string['privacy:metadata:token:last_used_at'] = 'Wakati tokeni ilipotumika mara ya mwisho kupata mlisho wa kalenda.';
$string['privacy:metadata:token:last_used_ip'] = 'Anwani ya IP ya upakuaji wa mwisho uliofanikiwa (kwa uchunguzi wa matumizi mabaya).';
$string['privacy:metadata:token:use_count'] = 'Jumla ya idadi ya upakuaji uliofanikiwa.';
$string['privacy:metadata:token:timecreated'] = 'Wakati tokeni ilipotolewa kwa mara ya kwanza.';
$string['privacy:metadata:token:timemodified'] = 'Wakati tokeni ilipobadilishwa mara ya mwisho (kutengenezwa upya au kubatilishwa).';
$string['privacy:metadata:oauth'] = 'Access token + refresh token za OAuth kwa Microsoft 365 au Google Calendar — zimesimbwa zikiwa zimehifadhiwa kupitia kisaidizi cha usimbaji cha Moodle kinachotumia Sodium. Safu moja kwa kila (mtumiaji, mtoa huduma).';
$string['privacy:metadata:oauth:userid'] = 'Mtumiaji ambaye tokeni za OAuth zinairuhusu LMS kusoma na kuandika kwenye kalenda yake.';
$string['privacy:metadata:oauth:customerid'] = 'Mteja wa Sentientia LMS ambaye mtumiaji yuko chini yake.';
$string['privacy:metadata:oauth:provider'] = 'Mtoa huduma anayemiliki tokeni — m365 (Microsoft Graph) au google (Google Calendar API).';
$string['privacy:metadata:oauth:access_token_enc'] = 'Access token iliyosimbwa ya muda mfupi (kwa kawaida hudumu saa 1).';
$string['privacy:metadata:oauth:refresh_token_enc'] = 'Refresh token iliyosimbwa ya muda mrefu inayotumika kupata access token mpya bila kumuuliza mtumiaji.';
$string['privacy:metadata:oauth:expires'] = 'Muhuri wa muda wa Unix ambapo muda wa access token unaisha.';
$string['privacy:metadata:oauth:scopes'] = 'Scopes za OAuth ambazo mtoa huduma alitoa wakati wa ridhaa.';
$string['privacy:metadata:oauth:timecreated'] = 'Wakati tokeni za OAuth zilipotolewa kwa mara ya kwanza (ridhaa ya awali).';
$string['privacy:metadata:oauth:timemodified'] = 'Wakati tokeni za OAuth zilipohuishwa au ridhaa ilipotolewa upya mara ya mwisho.';
$string['privacy:metadata:microsoft_graph'] = 'Feature flag ya OAuth ikiwa imewashwa na mtumiaji ametoa ridhaa, Sentientia LMS husoma na kuandika matukio ya kalenda kupitia Microsoft Graph kwa niaba ya mtumiaji. Hakuna data inayotumwa hadi mtumiaji akubali kujiunga.';
$string['privacy:metadata:microsoft_graph:userid'] = 'Mtumiaji ambaye matukio yake ya kalenda yanasomwa au kuandikwa.';
$string['privacy:metadata:google_calendar'] = 'Feature flag ya OAuth ikiwa imewashwa na mtumiaji ametoa ridhaa, Sentientia LMS husoma na kuandika matukio ya kalenda kupitia Google Calendar API kwa niaba ya mtumiaji. Hakuna data inayotumwa hadi mtumiaji akubali kujiunga.';
$string['privacy:metadata:google_calendar:userid'] = 'Mtumiaji ambaye matukio yake ya kalenda yanasomwa au kuandikwa.';
