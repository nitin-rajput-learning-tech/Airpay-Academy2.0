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
 * Swahili strings for local_sentientia_integrations.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Kitovu cha Miunganisho cha Sentientia';
$string['settings_heading'] = 'Mipangilio ya Miunganisho ya Airpay';
$string['settings_desc'] = 'Sanidi miunganisho ya nje kwa ajili ya Airpay Academy. Kila kipengele kimezimwa kwa chaguo-msingi — kiwashe na ukisanidi kimoja kimoja.';
$string['ai_heading'] = 'Vipengele vya AI';
$string['ai_enable'] = 'Washa vipengele vya AI';
$string['ai_enable_desc'] = 'Swichi kuu ya vipengele vyote vya AI. Inahitaji mtoa huduma wa AI awe amesanidiwa kwenye Usimamizi wa tovuti → AI.';
$string['ai_recommendations_enable'] = 'Washa mapendekezo ya kozi ya AI';
$string['ai_recommendations_desc'] = 'Onyesha mapendekezo ya kozi yanayomfaa mwanafunzi kwenye dashibodi yake, kulingana na historia ya kukamilisha kozi na mapengo ya ujuzi.';
$string['ai_quiz_enable'] = 'Washa utengenezaji wa quiz kwa AI';
$string['ai_quiz_desc'] = 'Ruhusu wasimamizi wa L&D kutengeneza maswali ya quiz kutoka kwenye maudhui ya kozi kwa kutumia AI.';
$string['sentientia_heading'] = 'Pipeline ya Maudhui ya SENTIENTIA';
$string['sentientia_enable'] = 'Washa pipeline ya SENTIENTIA';
$string['sentientia_desc'] = 'Pipeline ya kiotomatiki ya SOP → SCORM. Inahitaji ufunguo wa API wa ElevenLabs kwa ajili ya kutengeneza sauti.';
$string['elevenlabs_apikey'] = 'Ufunguo wa API wa ElevenLabs';
$string['elevenlabs_apikey_desc'] = 'Ufunguo wa API kutoka elevenlabs.io kwa ajili ya kutengeneza sauti (Agent 4).';
$string['elevenlabs_voiceid'] = 'Voice ID ya ElevenLabs';
$string['elevenlabs_voiceid_desc'] = 'Voice ID ya kutumia kutengeneza sauti ya usimulizi.';
$string['m365_heading'] = 'Muunganisho wa Microsoft 365';
$string['m365_enable'] = 'Washa SSO ya Microsoft 365';
$string['m365_desc'] = 'Single Sign-On ya Azure AD. Inahitaji plugin ya OIDC iwe imesanidiwa na usajili wa app kwenye Azure.';
$string['m365_tenant_id'] = 'Tenant ID ya Azure';
$string['m365_tenant_id_desc'] = 'Kutoka Azure Portal → App registrations → Directory (tenant) ID.';
$string['m365_client_id'] = 'Client ID ya Azure';
$string['m365_client_id_desc'] = 'Application (client) ID kutoka kwenye usajili wa app wa Azure.';
$string['m365_client_secret'] = 'Client Secret ya Azure';
$string['m365_client_secret_desc'] = 'Thamani ya client secret (hubadilishwa kila miezi 24).';
$string['teams_heading'] = 'Arifa za Microsoft Teams';
$string['teams_enable'] = 'Washa arifa za Teams';
$string['teams_desc'] = 'Tuma matukio ya kujifunza (usajili, tarehe ya mwisho, ukamilishaji) kwenye chaneli za Teams kupitia webhook.';
$string['teams_webhook_url'] = 'URL ya webhook ya Teams';
$string['teams_webhook_url_desc'] = 'URL ya incoming webhook ya chaneli ya Teams inayolengwa.';
$string['teams_events'] = 'Matukio ya kuarifu';
$string['teams_events_desc'] = 'Matukio yapi yanaanzisha arifa za Teams.';
$string['hrms_heading'] = 'Usawazishaji wa HRMS';
$string['hrms_enable'] = 'Washa usawazishaji wa HRMS';
$string['hrms_desc'] = 'Usawazishaji wa wafanyakazi wa papo hapo kutoka Keka au HRMS nyingine kupitia REST API.';
$string['hrms_api_url'] = 'Endpoint ya API ya HRMS';
$string['hrms_api_url_desc'] = 'URL ya msingi ya API ya HRMS (k.m., https://api.keka.com/v1/).';
$string['hrms_api_key'] = 'Ufunguo wa API wa HRMS';
$string['hrms_api_key_desc'] = 'Ufunguo wa uthibitishaji kwa API ya HRMS.';
$string['hrms_sync_interval'] = 'Muda kati ya usawazishaji (saa)';
$string['hrms_sync_interval_desc'] = 'Mara ngapi kuvuta masasisho ya wafanyakazi. Chaguo-msingi: saa 4.';
$string['keka_heading'] = 'Muunganisho wa KeKa HRMS';
$string['keka_heading_desc'] = 'Uendeshaji wa kiotomatiki wa wafanyakazi wanaojiunga, wanaohama na wanaoondoka kupitia KeKa REST API + webhooks. Endpoint ya webhook na kazi ya ulinganishaji pia zinadhibitiwa na feature flags za sentientia.hrms.* (zimezimwa kwa chaguo-msingi) na swichi ya "Washa usawazishaji wa HRMS" iliyo hapo juu.';
$string['keka_base_url'] = 'URL ya msingi ya API ya KeKa';
$string['keka_base_url_desc'] = 'k.m., https://airpay.keka.com';
$string['keka_api_key'] = 'Ufunguo wa API wa KeKa';
$string['keka_api_key_desc'] = 'Tengeneza kutoka KeKa Admin > Integrations.';
$string['keka_client_id'] = 'OAuth Client ID ya KeKa';
$string['keka_client_id_desc'] = 'Mbadala wa ufunguo wa API.';
$string['keka_client_secret'] = 'OAuth Client Secret ya KeKa';
$string['keka_client_secret_desc'] = 'Inatumika pamoja na OAuth client ID wakati ufunguo wa API haujawekwa.';
$string['webhook_secret'] = 'Siri ya webhook';
$string['webhook_secret_desc'] = 'Weka thamani hiyo hiyo kwenye mipangilio ya webhook ya KeKa. KeKa lazima iitume kwenye HTTP header ya X-Webhook-Secret — query parameter ya ?secret= haikubaliki (ingevuja kwenye access logs). Endpoint: /local/sentientia_integrations/webhook.php';
$string['keka_default_orgpath'] = 'Njia ya shirika (org path) chaguo-msingi kwa watumiaji wapya';
$string['keka_default_orgpath_desc'] = 'Njia ya shirika (k.m. /1) ambayo watumiaji walioundwa kupitia webhook huwekwa chini yake pale idara yao ya KeKa isipoweza kuhusishwa na shirika. Njia hii hukaguliwa dhidi ya mti wa mashirika; thamani zisizo sahihi hurudi kwenye /1.';
$string['task_keka_reconcile'] = 'Kuvuta data ya ulinganishaji kutoka KeKa HRMS';
$string['gamification_heading'] = 'Gamification';
$string['gamification_enable'] = 'Washa gamification';
$string['gamification_desc'] = 'Pointi za XP, mbao za vinara na mfululizo wa siku za kujifunza. Inahitaji plugin ya block_xp iwe imesakinishwa.';
$string['gamification_xp_per_completion'] = 'XP kwa kila kozi iliyokamilika';
$string['gamification_xp_per_completion_desc'] = 'XP anazopewa mwanafunzi anapokamilisha kozi.';
$string['gamification_leaderboard_enable'] = 'Washa mbao za vinara za idara';
$string['gamification_leaderboard_desc'] = 'Onyesha mbao za vinara zilizochujwa kwa costcenter/idara.';
$string['privacy:metadata'] = 'Plugin ya Airpay sentientia_integrations haihifadhi data binafsi kwenye majedwali yake yenyewe; hali ya mtumiaji iko kwenye majedwali ya msingi ya Sentientia LMS, na data hiyo huhamishwa na watoa huduma (providers) wa majedwali hayo.';
