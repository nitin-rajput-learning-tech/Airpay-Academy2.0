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
 * Swahili strings for local_sentientia_recompletion.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Kukamilisha Upya';
$string['rules'] = 'Kanuni za kukamilisha upya';
$string['history'] = 'Historia ya kuweka upya';
$string['bulkreset'] = 'Weka upya kwa wingi';
$string['sentientia_recompletion:view'] = 'Kuona kanuni na historia ya kukamilisha upya';
$string['sentientia_recompletion:manage'] = 'Kusimamia kanuni za kukamilisha upya';
$string['sentientia_recompletion:reset'] = 'Kuweka upya ukamilishaji wa watumiaji kwa mkono';
$string['enabled'] = 'Imewashwa';
$string['disabled'] = 'Imezimwa';
$string['running'] = 'Inaendelea';
$string['settings_pre_notify_days'] = 'Muda wa taarifa ya mapema (siku)';
$string['settings_pre_notify_days_desc'] = 'Wajulishe watumiaji idadi hii ya siku KABLA muda wa utiifu (compliance) wao haujaisha. Chaguo-msingi ni 30.';
$string['settings_max_batch'] = 'Idadi ya juu ya watumiaji wa kuwekwa upya kwa kila mzunguko wa cron';
$string['settings_max_batch_desc'] = 'Kikomo cha usalama ili kanuni iliyowekwa vibaya isiweze kuweka upya maelfu ya watumiaji katika mzunguko mmoja wa cron. Chaguo-msingi ni 500.';
$string['settings_dry_run_default'] = 'Hali ya dry-run (chaguo-msingi IMEZIMWA)';
$string['settings_dry_run_default_desc'] = 'Ikiwashwa, cron ya kila siku hurekodi kile AMBACHO kingewekwa upya lakini haiweki upya chochote kwa kweli. Inasaidia kujaribu kanuni mpya.';
$string['rule_name'] = 'Jina la kanuni';
$string['rule_courseid'] = 'Kozi (acha 0 kwa kozi zote zenye ukamilishaji umewashwa)';
$string['rule_period_days'] = 'Kipindi cha kuweka upya (siku)';
$string['rule_period_days_help'] = 'Weka upya ukamilishaji kila baada ya siku N. 365 = kila mwaka, 90 = kila robo mwaka. Weka 0 kuzima.';
$string['rule_trigger'] = 'Kichocheo';
$string['rule_trigger_completion'] = 'Siku N baada ya kukamilisha';
$string['rule_trigger_enrolment'] = 'Siku N baada ya kusajiliwa';
$string['rule_trigger_fixed'] = 'Katika tarehe maalum ya kalenda';
$string['rule_fixed_date'] = 'Tarehe maalum (ikiwa kichocheo = tarehe maalum)';
$string['rule_reset_grades'] = 'Weka upya alama pia?';
$string['rule_reset_attempts'] = 'Weka upya majaribio ya quiz pia?';
$string['rule_enabled'] = 'Imewashwa';
$string['messageprovider:recompletion_due_soon'] = 'Kukamilisha upya kunakaribia';
$string['messageprovider:recompletion_reset'] = 'Ukamilishaji umewekwa upya (ulikuwa umekamilika)';
$string['msg_reset_subject'] = 'Kukamilisha upya: \'{$a->course}\' imewekwa upya';
$string['msg_reset_body'] = 'Ulikamilisha \'{$a->course}\' mara ya mwisho tarehe {$a->previous}. Kwa mujibu wa kanuni ya kukamilisha upya ya siku {$a->days}, utahitaji kuikamilisha tena ili kudumisha utiifu.';
$string['msg_due_subject'] = 'Kukamilisha upya kunahitajika ndani ya siku {$a->days}: \'{$a->course}\'';
$string['msg_due_body'] = 'Taarifa — ukamilishaji wako wa \'{$a->course}\' utaisha muda ndani ya siku {$a->days}. Panga kuirudia kabla ya hapo ili kudumisha utiifu.';
$string['event_completion_reset'] = 'Ukamilishaji wa kozi umewekwa upya';
$string['nrules'] = 'Kanuni {$a}';
$string['rules_empty'] = 'Bado hakuna kanuni za kukamilisha upya zilizowekwa.';
$string['history_empty'] = 'Bado hakuna uwekaji upya uliofanyika.';
$string['no_courses_resetable'] = 'Hakuna kozi zenye ufuatiliaji wa ukamilishaji umewashwa — kukamilisha upya kunahitaji ukamilishaji wa kozi uwe umewekwa.';
$string['history_title'] = 'Historia ya kukamilisha upya';
$string['history_back'] = 'Rudi kwenye kanuni';
$string['history_prev'] = 'Nyuma';
$string['history_next'] = 'Mbele';
$string['history_filtered'] = 'Inaonyesha kozi moja au mwanafunzi mmoja tu.';
$string['history_clear_filter'] = 'Onyesha uwekaji upya wote';
$string['hcol_when'] = 'Lini';
$string['hcol_user'] = 'Mtumiaji';
$string['hcol_course'] = 'Kozi';
$string['hcol_reason'] = 'Sababu';
$string['hcol_previous'] = 'Ukamilishaji uliopita';
$string['hcol_grades'] = 'Alama zimewekwa upya?';
$string['hcol_attempts'] = 'Majaribio yamewekwa upya?';
$string['badge_dryrun'] = 'dry-run';
$string['badge_legacy'] = 'Ya zamani';
$string['badge_self'] = 'binafsi';
$string['badge_deleted_user'] = 'Mtumiaji aliyefutwa';
$string['badge_inferred'] = 'makadirio';
$string['badge_inferred_title'] = 'Rekodi ya log ya uwekaji upya huu haikupatikana, kwa hiyo muda wake umekadiriwa kutokana na ukamilishaji ulioumaliza.';
$string['link_evidence'] = 'Ushahidi';
$string['badge_legacy_rule'] = 'Imeingizwa kutoka BizLMS';
$string['legacy_settings'] = 'Mipangilio ya BizLMS ya kozi hii';
$string['legacy_unfinished_parity'] = 'Kanuni hii imetoka BizLMS. Injini ya Sentientia bado haitekelezi mipangilio yake yote: ufuatiliaji wa SCORM hufutwa kila mara, kipindi huhesabiwa kwa siku kamili, na chaguo za jaribio la ziada, zoezi, LTI, dodoso na barua pepe maalum zimehifadhiwa kwa kumbukumbu tu. Kwa sababu hiyo bado haiwezi kuwashwa. Ili kuweka upya wanafunzi sasa, unda kanuni yako mwenyewe: itafanya kazi kama ilivyowekwa.';
$string['legacy_enable_blocked'] = 'Kanuni hii imetoka BizLMS na bado haiwezi kuwashwa: injini ya Sentientia haitekelezi mipangilio yake yote (angalia taarifa kwenye kanuni). Unda kanuni yako mwenyewe ili kuweka upya wanafunzi; itafanya kazi kama ilivyowekwa.';
$string['legacy_dead_scorm'] = 'Kozi hii ilikuwa na jina la zamani la mpangilio wa SCORM pekee (deletescormdata), ambalo plugin ya BizLMS haikuwahi kulisoma: halikufanya chochote kwa SCORM.';
$string['legacy_days'] = 'siku {$a}';
$string['legacy_choice_0'] = 'hakuna';
$string['legacy_choice_1'] = 'futa';
$string['legacy_choice_2'] = 'ruhusu jaribio moja la ziada';
$string['legacy_enable'] = 'Kukamilisha upya kulikuwa kumewashwa';
$string['legacy_recompletionduration'] = 'Muda';
$string['legacy_deletegradedata'] = 'Futa alama';
$string['legacy_archivecompletiondata'] = 'Hifadhi kumbukumbu za data ya ukamilishaji';
$string['legacy_quiz'] = 'Majaribio ya quiz';
$string['legacy_archivequiz'] = 'Hifadhi kumbukumbu za majaribio ya quiz';
$string['legacy_scorm'] = 'SCORM';
$string['legacy_archivescorm'] = 'Hifadhi kumbukumbu za data ya SCORM';
$string['legacy_assign'] = 'Mazoezi';
$string['legacy_lti'] = 'Alama za LTI';
$string['legacy_archivelti'] = 'Hifadhi kumbukumbu za alama za LTI';
$string['legacy_questionnaire'] = 'Majibu ya dodoso';
$string['legacy_archivequestionnaire'] = 'Hifadhi kumbukumbu za majibu ya dodoso';
$string['legacy_pulse'] = 'Arifa za Pulse';
$string['legacy_recompletionemailenable'] = 'Tuma barua pepe kwa mwanafunzi wakati wa kuweka upya';
$string['evidence_title'] = 'Ushahidi wa kuweka upya';
$string['evidence_back'] = 'Rudi kwenye historia';
$string['evidence_learner'] = 'Mwanafunzi';
$string['evidence_course'] = 'Kozi';
$string['evidence_reset_at'] = 'Imewekwa upya saa';
$string['evidence_reason'] = 'Sababu';
$string['evidence_reset_by'] = 'Imewekwa upya na';
$string['evidence_previous'] = 'Ilikamilishwa kabla ya kuwekwa upya';
$string['evidence_grades_reset'] = 'Alama zimewekwa upya';
$string['evidence_attempts_reset'] = 'Majaribio ya quiz yamewekwa upya';
$string['evidence_inferred'] = 'makadirio';
$string['evidence_inferred_note'] = 'Muda wa uwekaji upya huu ni makadirio. Rekodi ya log ya uwekaji upya haikupatikana, kwa hiyo muda ni ule wa mapema zaidi kati ya ukamilishaji ukiongezwa kipindi cha kanuni na ushahidi wa kwanza wa mzunguko unaofuata.';
$string['evidence_unattached_note'] = 'Ushahidi huu haukuweza kuunganishwa na uwekaji upya wowote: rekodi ya log ya uwekaji upya ilifutwa na hakukubaki ukamilishaji uliohifadhiwa wa kuukadiria.';
$string['evidence_none'] = 'Hakuna ushahidi uliohifadhiwa kwa uwekaji upya huu.';
$string['evidence_more'] = 'Safu {$a} zaidi hazijaonyeshwa.';
$string['evidence_redacted'] = '(imefichwa)';
$string['evidence_course_gone'] = '(kozi iliyofutwa)';
$string['evidence_scheduled'] = 'kazi iliyoratibiwa';
$string['evidence_self'] = 'mwanafunzi';
$string['evidence_admin'] = 'msimamizi';
$string['evidence_admin_erased'] = 'msimamizi (amefutwa)';
$string['evidence_not_recorded'] = 'haijarekodiwa';
$string['evidence_view_off'] = 'Mwonekano wa ushahidi wa kuweka upya haujawashwa.';
$string['evidence_not_found'] = 'Hakuna uwekaji upya kama huo, au ni wa tenant mwingine.';
$string['evidence_type_course_completion'] = 'Ukamilishaji wa kozi';
$string['evidence_type_criteria_completion'] = 'Vigezo vya ukamilishaji';
$string['evidence_type_activity_completion'] = 'Ukamilishaji wa shughuli';
$string['evidence_type_quiz_attempt'] = 'Majaribio ya quiz';
$string['evidence_type_quiz_grade'] = 'Alama za quiz';
$string['evidence_type_scorm_track'] = 'Ufuatiliaji wa SCORM';
$string['evidence_type_lti_grade'] = 'Alama za LTI';
$string['evidence_type_questionnaire_response'] = 'Majibu ya dodoso';
$string['evidence_type_questionnaire_answer'] = 'Majibu ya maswali ya dodoso';
$string['evidence_type_gradebook_grade'] = 'Alama';
$string['col_state'] = 'Hali';
$string['col_enrolled'] = 'Amesajiliwa';
$string['col_started'] = 'Ameanza';
$string['col_completed'] = 'Amekamilisha';
$string['col_criteria'] = 'Kigezo';
$string['col_grade'] = 'Alama';
$string['col_when'] = 'Lini';
$string['col_activity'] = 'Shughuli';
$string['col_quiz'] = 'Quiz';
$string['col_attempt'] = 'Jaribio';
$string['col_marks'] = 'Maksi';
$string['col_scorm'] = 'SCORM';
$string['col_element'] = 'Kipengele';
$string['col_value'] = 'Thamani';
$string['col_tool'] = 'Zana ya LTI';
$string['col_questionnaire'] = 'Dodoso';
$string['col_question'] = 'Swali';
$string['col_answer'] = 'Jibu';
$string['col_item'] = 'Kipengele cha alama';
$string['privacy:metadata:local_sentientia_recompletion_rules'] = 'Maelezo ya kanuni za kukamilisha upya';
$string['privacy:metadata:local_sentientia_recompletion_history'] = 'Kumbukumbu ya ukaguzi ya uwekaji upya kwa kila mtumiaji';
$string['privacy:metadata:local_sentientia_recompletion_history:userid'] = 'Mtumiaji ambaye ukamilishaji wake uliwekwa upya';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'Kozi iliyowekwa upya';
$string['privacy:metadata:local_sentientia_recompletion_history:reason'] = 'Sababu ya uwekaji upya kufanyika';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'Msimamizi aliyeweka upya ukamilishaji wa mtu mwingine (tupu ikiwa kazi iliyoratibiwa ndiyo iliyofanya)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'Mara ya mwisho mtumiaji alipokamilisha kozi kabla haijawekwa upya';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'Wakati uwekaji upya ulipofanyika';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'Kama uwekaji upya ulifanywa na Sentientia au uliingizwa kutoka plugin ya BizLMS';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'Ushahidi uliofutwa na uwekaji upya: ukamilishaji, vigezo, ukamilishaji wa shughuli, majaribio ya quiz na alama, ufuatiliaji wa SCORM, alama za LTI na majibu ya dodoso ya mzunguko ulioumaliza';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'Mtumiaji ambaye ushahidi ni wake';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'Kozi ambayo ushahidi ni wake';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'Aina ya ushahidi wa safu hii';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'Shughuli ambayo ushahidi ni wake';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'Quiz, kifurushi cha SCORM, zana ya LTI, dodoso, kigezo au kipengele cha alama ambacho ushahidi ni wake';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'Kipengele cha SCORM au swali la dodoso';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'Hali ya ukamilishaji, hali ya jaribio au jibu';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'Alama, maksi au matokeo';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'Wakati kitu kilichohifadhiwa kilipotokea';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'Safu iliyohifadhiwa kama ilivyokuwa hasa, ikijumuisha msimamizi aliyebadilisha ukamilishaji wa shughuli, mwalimu aliyebadilisha alama mara ya mwisho na maoni yoyote aliyoandika, kile mwanafunzi alichoandika kwenye dodoso, na maandishi huru ya ufuatiliaji wa SCORM';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'Wakati ushahidi ulipohifadhiwa';
$string['privacy:export:resets_performed'] = 'Uwekaji upya niliofanya';
$string['privacy:export:evidence'] = 'Ushahidi uliohifadhiwa na uwekaji upya';
