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
 * Swahili strings for local_sentientia_xapi.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia xAPI / LRS';
$string['settings_pagetitle'] = 'Mipangilio ya Sentientia xAPI / LRS';
$string['setting_lrs_token'] = 'Tokeni ya Bearer ya LRS';
$string['setting_lrs_token_desc'] = 'Tokeni ya siri ya kuthibitisha programu-mteja (clients) za nje za xAPI (k.m. maudhui ya SCORM, cmi5 AU). Angalau herufi 32. Iweke siri — ibadilishe mara tu unaposhuku imevuja.';
$string['setting_lrs_basic_user'] = 'Jina la mtumiaji la basic-auth la LRS';
$string['setting_lrs_basic_user_desc'] = 'Jina la mtumiaji (si lazima) la uthibitishaji wa HTTP Basic linalokubaliwa na endpoint ya /lrs/statements pamoja na tokeni ya Bearer. Acha wazi ili kuzima basic-auth.';
$string['setting_lrs_basic_pass'] = 'Nenosiri la basic-auth la LRS';
$string['setting_lrs_basic_pass_desc'] = 'Nenosiri la uthibitishaji wa HTTP Basic (litumie pamoja na jina la mtumiaji hapo juu). Acha wazi ili kuzima basic-auth.';
$string['setting_retention_days'] = 'Muda wa kuhifadhi taarifa (siku)';
$string['setting_retention_days_desc'] = 'Taarifa za xAPI zenye umri wa zaidi ya siku hizi hufutwa na kazi ya usafishaji ya kila usiku. Weka 0 ili kuzihifadhi milele. Chaguo-msingi: 730 (miaka 2).';
$string['setting_emit_login'] = 'Tuma taarifa mtumiaji anapoingia';
$string['setting_emit_login_desc'] = 'Ikiwashwa, taarifa yenye kitenzi "experienced" hutumwa kila mtumiaji anapoingia. Tovuti zenye matumizi makubwa zinaweza kuzima hii.';
$string['setting_lrs_rate_limit'] = 'Kikomo cha maombi cha LRS (maombi kwa kila dirisha)';
$string['setting_lrs_rate_limit_desc'] = 'Idadi ya juu ya maombi ya /lrs/statements ambayo programu-mteja moja iliyothibitishwa (vitambulisho vya programu-mteja vilivyosajiliwa, au tokeni/vitambulisho vya basic vya akiba vya tovuti nzima) inaweza kutuma kwa kila dirisha. Chaguo-msingi: 600.';
$string['setting_lrs_rate_window'] = 'Dirisha la kikomo cha maombi la LRS (sekunde)';
$string['setting_lrs_rate_window_desc'] = 'Urefu wa dirisha maalum la kikomo cha maombi, kwa sekunde. Chaguo-msingi: 60 (kwa hiyo chaguo-msingi ni maombi 600 kwa kila sekunde 60 kwa kila programu-mteja).';
$string['xapi:viewstatements'] = 'Tazama taarifa za xAPI kwenye kitazamaji cha LRS';
$string['xapi:deletestatements'] = 'Futa taarifa za xAPI kutoka LRS';
$string['xapi:managelrs'] = 'Simamia mipangilio na vitambulisho vya LRS';
$string['error_lrs_disabled'] = 'xAPI LRS imezimwa kwa sasa. Wasiliana na msimamizi wako.';
$string['error_lrs_auth'] = 'Uthibitishaji umeshindikana. Weka tokeni sahihi ya Bearer au vitambulisho sahihi vya Basic.';
$string['error_lrs_invalid_json'] = 'Kiini cha ombi si JSON halali.';
$string['error_lrs_invalid_stmt'] = 'Uthibitishaji wa taarifa umeshindikana: {$a}';
$string['error_lrs_tenant'] = 'Imeshindikana kutambua tenant. Hakikisha homePage ya akaunti ya mhusika inalingana na tenant iliyosajiliwa.';
$string['error_lrs_method'] = 'Njia (method) hii ya HTTP haikubaliwi kwenye endpoint hii.';
$string['error_lrs_not_found'] = 'Taarifa haikupatikana.';
$string['error_lrs_ratelimited'] = 'Kikomo cha maombi kimepitwa: programu-mteja hii inaruhusiwa maombi {$a} kwa kila dirisha. Jaribu tena baada ya muda wa Retry-After kupita.';
$string['verb_completed'] = 'amekamilisha';
$string['verb_experienced'] = 'amepitia';
$string['verb_passed'] = 'amefaulu';
$string['verb_failed'] = 'hakufaulu';
$string['verb_attempted'] = 'amejaribu';
$string['verb_answered'] = 'amejibu';
$string['verb_launched'] = 'amezindua';
$string['verb_initialized'] = 'ameanzisha';
$string['verb_terminated'] = 'amehitimisha';
$string['verb_suspended'] = 'amesitisha';
$string['verb_resumed'] = 'ameendelea';
$string['verb_satisfied'] = 'ametimiza';
$string['validate_actor_required'] = 'Mhusika anahitajika.';
$string['validate_actor_missing_objecttype'] = 'Mhusika lazima awe na objectType Agent au Group.';
$string['validate_actor_missing_ifi'] = 'Mhusika lazima awe na IFI moja tu (mbox, mbox_sha1sum, openid, au account).';
$string['validate_actor_mbox_format'] = 'mbox ya mhusika lazima iwe URI ya mailto:.';
$string['validate_actor_account_missing'] = 'Akaunti ya mhusika lazima iwe na homePage na name.';
$string['validate_verb_required'] = 'Kitenzi kinahitajika.';
$string['validate_verb_id_required'] = 'Kitenzi lazima kiwe na id (IRI).';
$string['validate_verb_id_iri'] = 'id ya kitenzi lazima iwe IRI halali.';
$string['validate_object_required'] = 'Kitu kinahitajika.';
$string['validate_object_id_required'] = 'Kitu lazima kiwe na id (IRI).';
$string['validate_object_id_iri'] = 'id ya kitu lazima iwe IRI halali.';
$string['validate_result_score_range'] = 'Thamani ya scaled ya alama ya matokeo lazima iwe kati ya -1.0 na 1.0.';
$string['validate_result_score_raw_max'] = 'Thamani ya raw ya alama ya matokeo haipaswi kuzidi max.';
$string['validate_context_registration_uuid'] = 'registration ya muktadha lazima iwe UUID halali.';
$string['validate_timestamp_format'] = 'Muhuri wa muda lazima uwe tarehe-saa halali ya ISO 8601.';
$string['validate_id_uuid'] = 'id ya taarifa lazima iwe UUID halali.';
$string['cmi5_session'] = 'Kipindi cha cmi5';
$string['cmi5_session_initialized'] = 'Kipindi kimeanzishwa';
$string['cmi5_session_terminated'] = 'Kipindi kimehitimishwa';
$string['cmi5_au_passed'] = 'Assignable Unit imefaulu';
$string['cmi5_au_failed'] = 'Assignable Unit haikufaulu';
$string['cmi5_au_completed'] = 'Assignable Unit imekamilika';
$string['lrs_viewer_title'] = 'Kitazamaji cha taarifa za xAPI';
$string['lrs_viewer_heading'] = 'LRS — Taarifa za hivi karibuni';
$string['lrs_col_timestamp'] = 'Muda';
$string['lrs_col_actor'] = 'Mhusika';
$string['lrs_col_verb'] = 'Kitenzi';
$string['lrs_col_object'] = 'Kitu';
$string['lrs_col_score'] = 'Alama';
$string['lrs_col_success'] = 'Mafanikio';
$string['lrs_col_tenant'] = 'Tenant';
$string['lrs_no_statements'] = 'Bado hakuna taarifa zilizorekodiwa.';
$string['lrs_endpoint_label'] = 'URL ya endpoint ya LRS';
$string['lrs_endpoint_desc'] = 'Tumia URL hii kama endpoint ya LRS kwenye maudhui yako ya xAPI / cmi5 au kwenye programu-mteja ya LRS.';
$string['privacy:metadata:local_sentientia_xapi_statements'] = 'Taarifa za xAPI zilizotolewa na mtumiaji huyu au kwa ajili yake.';
$string['privacy:metadata:local_sentientia_xapi_statements:actorid'] = 'id ya mtumiaji wa Moodle iliyounganishwa na mhusika wa xAPI.';
$string['privacy:metadata:local_sentientia_xapi_statements:actor'] = 'Kitu cha JSON cha mhusika (kinaweza kuwa na barua pepe au kitambulisho cha akaunti).';
$string['privacy:metadata:local_sentientia_xapi_statements:verb'] = 'IRI ya kitenzi cha xAPI.';
$string['privacy:metadata:local_sentientia_xapi_statements:object'] = 'Kitu cha JSON (shughuli, wakala, n.k.).';
$string['privacy:metadata:local_sentientia_xapi_statements:result'] = 'Matokeo ya JSON (alama, mafanikio, ukamilishaji).';
$string['privacy:metadata:local_sentientia_xapi_statements:context'] = 'Muktadha wa JSON.';
$string['privacy:metadata:local_sentientia_xapi_statements:timestored'] = 'Muhuri wa muda ambapo taarifa ilihifadhiwa kwenye LRS.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions'] = 'Rekodi za ufuatiliaji wa vipindi vya cmi5.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid'] = 'id ya mtumiaji wa Moodle.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration'] = 'UUID ya usajili wa cmi5.';
$string['task_purge_old_statements'] = 'Futa taarifa za zamani za xAPI';
