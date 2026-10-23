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
 * Swahili strings for local_sentientia_learningpath.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Njia za Kujifunza';
$string['sentientia_learningpath:manage'] = 'Simamia njia za kujifunza';
$string['sentientia_learningpath:view'] = 'Tazama njia za kujifunza';
$string['sentientia_learningpath:enrol'] = 'Sajili watumiaji kwenye njia za kujifunza';
$string['sentientia_learningpath:create'] = 'Unda njia za kujifunza';
$string['sentientia_learningpath:update'] = 'Hariri njia za kujifunza';
$string['sentientia_learningpath:delete'] = 'Futa njia za kujifunza';
$string['addpath'] = 'Ongeza Njia ya Kujifunza';
$string['editpath'] = 'Hariri Njia ya Kujifunza';
$string['deletepath'] = 'Futa Njia ya Kujifunza';
$string['archivepath'] = 'Weka Njia Kumbukumbuni';
$string['activatepath'] = 'Washa Njia';
$string['heading_basic'] = 'Maelezo ya Njia';
$string['heading_org'] = 'Shirika';
$string['heading_window'] = 'Kipindi cha usajili (si lazima)';
$string['heading_status'] = 'Hali';
$string['startdate'] = 'Tarehe ya kuanza';
$string['startdate_help'] = 'Si lazima. Njia inaanza kupokea usajili kuanzia tarehe hii. Acha wazi ili kuruhusu usajili mara moja.';
$string['enddate'] = 'Tarehe ya mwisho';
$string['enddate_help'] = 'Si lazima. Njia inaacha kupokea usajili mpya baada ya tarehe hii. Wanafunzi ambao tayari wamesajiliwa hawaathiriki.';
$string['enddate_before_start'] = 'Tarehe ya mwisho lazima iwe siku ya kuanza au baada yake.';
$string['audience_modal_title'] = 'Sajili kwa wingi kulingana na hadhira lengwa';
$string['audience_form_intro'] = 'Chagua kigezo kimoja au zaidi cha kichujio ili kulenga kundi la watumiaji. Muhtasari ulio hapa chini unabadilika unapobadilisha vichujio. Bofya "Sajili watumiaji wanaolingana" ili kuthibitisha.';
$string['audience_any'] = 'Yoyote';
$string['audience_any_cohort'] = 'Kundi lolote';
$string['audience_users_matched'] = 'watumiaji wanalingana';
$string['audience_pick_at_least_one'] = 'Chagua angalau kigezo kimoja cha kichujio (tumia fomu ya kawaida ya Sajili Watumiaji ili kusajili watumiaji wote).';
$string['audience_enrol_button'] = 'Sajili watumiaji wanaolingana';
$string['audience_enrol_result'] = 'Usajili mpya %d; watumiaji %d walilingana na hadhira.';
$string['designation'] = 'Cheo';
$string['region'] = 'Kanda';
$string['location'] = 'Mahali';
$string['employmenttype'] = 'Aina ya ajira';
$string['cohort'] = 'Kundi';
$string['name'] = 'Jina la njia';
$string['description'] = 'Maelezo';
$string['organisation'] = 'Shirika (tenant)';
$string['organisation_help'] = 'Chagua tenant ambayo njia hii ya kujifunza ni yake. Acha "Hakuna shirika maalum" ili ipatikane kwa tenant zote.';
$string['status'] = 'Hali';
$string['status_active'] = 'Inatumika';
$string['status_archived'] = 'Imewekwa kumbukumbuni';
$string['missingrequiredfields'] = 'Sehemu za lazima hazijajazwa.';
$string['confirmdelete'] = 'Una uhakika unataka kufuta "{$a}"? Hii itaondoa kabisa njia hii, kozi zote zilizopangwa ndani yake, na usajili wa wanafunzi. Hatua hii haiwezi kutenduliwa.';
$string['confirmarchive'] = 'Una uhakika unataka kuweka "{$a}" kumbukumbuni? Haitapatikana tena kwa wanafunzi wapya.';
$string['confirmactivate'] = 'Washa "{$a}"? Wanafunzi wataweza kujisajili tena.';
$string['pathcreated'] = 'Njia ya kujifunza imeundwa.';
$string['pathupdated'] = 'Njia ya kujifunza imesasishwa.';
$string['pathdeleted'] = 'Njia ya kujifunza imefutwa.';
$string['pathstatuschanged'] = 'Hali imesasishwa.';
$string['toomanycourses'] = 'Kozi ni nyingi mno katika ombi moja. Kikomo ni 100 kwa kila mwito.';
$string['toomanyusers'] = 'Watumiaji ni wengi mno katika ombi moja. Kikomo ni 500 kwa kila mwito.';
$string['filterstoolong'] = 'Data ya vichujio ni ndefu mno.';
$string['view_path_title'] = 'Njia ya kujifunza: {$a}';
$string['tab_overview'] = 'Muhtasari';
$string['tab_courses'] = 'Kozi';
$string['tab_users'] = 'Watumiaji';
$string['add_courses'] = 'Ongeza Kozi';
$string['enrol_users'] = 'Sajili Watumiaji';
$string['back_to_paths'] = 'Rudi kwenye njia za kujifunza';
$string['confirm_unassign_course'] = 'Ondoa "{$a}" kwenye njia hii ya kujifunza? Watumiaji wataendelea kuwa na ukamilishaji wa kozi zao.';
$string['confirm_unenrol_user'] = 'Ondoa usajili wa {$a} kwenye njia hii ya kujifunza?';
$string['unenrol_courses_remain'] = 'Mwanafunzi ameondolewa kwenye njia, lakini uingizaji wa BizLMS pia ulimsajili kwenye kozi hizi kutoka mpango huu, na usajili huo haujaondolewa: {$a}. Mwondoe kwenye ukurasa wa washiriki wa kila kozi ikiwa mwanafunzi hapaswi kuwa na ufikiaji tena.';
$string['no_courses_assigned'] = 'Bado hakuna kozi zilizopangwa. Bofya "Ongeza Kozi" ili kuanza.';
$string['no_users_enrolled'] = 'Bado hakuna watumiaji waliosajiliwa. Bofya "Sajili Watumiaji" ili kuongeza wanafunzi.';
$string['task_adaptive_sweep'] = 'Ukaguzi wa kasi wa safari zinazojirekebisha';
$string['adaptive_mode'] = 'Modi ya kujirekebisha';
$string['adaptive_mode_help'] = 'Ikiwashwa, njia hii hujigawa, huongeza kasi, au hutoa mafunzo ya marudio kulingana na alama za quiz, kasi ya ukamilishaji, na viashiria vya mapengo ya ujuzi.';
$string['adaptive_mode_off'] = 'Tuli (kwa mpangilio)';
$string['adaptive_mode_on'] = 'Inayojirekebisha (kulingana na utendaji)';
$string['score_threshold_low'] = 'Kiwango cha marudio (%)';
$string['score_threshold_low_help'] = 'Alama za quiz zilizo sawa au chini ya asilimia hii huanzisha tawi la marudio. Acha wazi ili kutumia chaguo-msingi la mfumo (50%).';
$string['score_threshold_high'] = 'Kiwango cha kuongeza kasi (%)';
$string['score_threshold_high_help'] = 'Alama za quiz zilizo sawa au juu ya asilimia hii, pamoja na kasi kubwa ya ukamilishaji, huanzisha tawi la kuongeza kasi. Acha wazi ili kutumia chaguo-msingi la mfumo (80%).';
$string['pivot_remediate'] = 'Imepewa marudio';
$string['pivot_accelerate'] = 'Imeongezwa kasi';
$string['pivot_branch'] = 'Imehamishiwa tawi';
$string['pivot_no_action'] = 'Hakuna hatua';
$string['trigger_quiz_score'] = 'Alama ya quiz';
$string['trigger_velocity'] = 'Kasi ya ukamilishaji';
$string['trigger_skills_gap'] = 'Pengo la ujuzi';
$string['trigger_combined'] = 'Viashiria vya pamoja';
$string['adaptive_log_empty'] = 'Bado hakuna maamuzi ya kujirekebisha yaliyorekodiwa kwa njia hii.';
$string['adaptive_log_title'] = 'Kumbukumbu ya maamuzi ya kujirekebisha';
$string['adaptive_feature_flag_off'] = 'Kipengele cha safari za kujifunza zinazojirekebisha kimezimwa kwa sasa. Washa flag "sentientia.learningpath.adaptive.enabled" kwenye Switchboard ili kukiwezesha.';
$string['privacy:metadata:lp_adaptive_log'] = 'Kumbukumbu ya maamuzi ya safari zinazojirekebisha kwa njia za kujifunza.';
$string['privacy:metadata:lp_adaptive_log:userid'] = 'Mtumiaji ambaye njia yake ilibadilishwa.';
$string['privacy:metadata:lp_adaptive_log:pathid'] = 'Njia ya kujifunza ambayo uamuzi ulifanywa.';
$string['privacy:metadata:lp_adaptive_log:pivot_type'] = 'Aina ya mabadiliko yaliyotumika.';
$string['privacy:metadata:lp_adaptive_log:quiz_score'] = 'Alama ya quiz iliyosababisha mabadiliko (ikiwa inahusika).';
$string['privacy:metadata:lp_adaptive_log:timecreated'] = 'Wakati uamuzi wa kujirekebisha ulipohifadhiwa.';
$string['privacy:metadata'] = 'Plugin ya Njia za Kujifunza ya Airpay huhifadhi usajili wa watumiaji kwenye njia, maendeleo, na maamuzi ya safari zinazojirekebisha.';
$string['privacy:metadata:lp'] = 'Ugawaji wa watumiaji kwa kila njia ya kujifunza.';
$string['privacy:metadata:lp:pathid'] = 'ID ya njia ya kujifunza.';
$string['privacy:metadata:lp:userid'] = 'ID ya mtumiaji aliyepangiwa.';
$string['privacy:metadata:lp:status'] = 'Hali ya ugawaji.';
$string['privacy:metadata:lp:timecreated'] = 'Muda wa ugawaji.';
$string['privacy:metadata:lp:timecompleted'] = 'Muda wa kukamilika kwa ugawaji.';
$string['privacy:metadata:lp:timemodified'] = 'Muda wa sasisho la mwisho.';
$string['mypaths_title'] = 'Njia zangu za kujifunza';
$string['mypaths_summary'] = 'Muhtasari wa njia zako za kujifunza';
$string['mypaths_stat_total'] = 'Njia zote';
$string['mypaths_empty'] = 'Bado hujasajiliwa kwenye njia yoyote ya kujifunza.';
$string['mypaths_progress'] = 'Maendeleo';
$string['mypaths_enrolled_on'] = 'Tarehe ya usajili';
$string['mypaths_completed_on'] = 'Tarehe ya kukamilisha';
$string['mypaths_opens_on'] = 'Inafunguliwa tarehe';
$string['mypaths_closes_on'] = 'Inafungwa tarehe';
$string['mypaths_courses'] = 'Kozi';
$string['mypaths_course_done'] = 'Imekamilika';
$string['mypaths_optional'] = 'Si lazima';
$string['lpstate_enrolled'] = 'Haijaanza';
$string['lpstate_inprogress'] = 'Inaendelea';
$string['lpstate_completed'] = 'Imekamilika';
$string['learner_paths_off'] = 'Ukurasa wa njia za kujifunza bado haupatikani.';
$string['imported_history_protected'] = 'Hii ni historia ya kujifunza iliyoletwa kutoka mfumo uliopita na imelindwa. Weka njia hii ya kujifunza kumbukumbuni badala ya kuifuta au kuondoa wanafunzi wake.';
$string['privacy:metadata:lp:enrolledby'] = 'Aliyemsajili mwanafunzi kwenye njia (mwanafunzi mwenyewe ikiwa alijisajili).';
$string['privacy:metadata:lp_path'] = 'Njia za kujifunza. Ni watu walioiunda au walioihariri njia mara ya mwisho tu ndio data binafsi.';
$string['privacy:metadata:lp_path:usercreated'] = 'Mtumiaji aliyeunda njia.';
$string['privacy:metadata:lp_path:usermodified'] = 'Mtumiaji aliyehariri njia mara ya mwisho.';
$string['privacy:metadata:lp_course'] = 'Kozi zilizowekwa kwenye njia ya kujifunza. Ni watu walioongeza au kubadilisha safu tu ndio data binafsi.';
$string['privacy:metadata:lp_course:usercreated'] = 'Mtumiaji aliyeweka kozi kwenye njia.';
$string['privacy:metadata:lp_course:usermodified'] = 'Mtumiaji aliyebadilisha safu mara ya mwisho.';
$string['privacy:metadata:lp_course_status'] = 'Safu za hali ya kila kozi ya mwanafunzi kwenye njia ya kujifunza, zilizoletwa kutoka mfumo uliopita.';
$string['privacy:metadata:lp_course_status:pathid'] = 'ID ya njia ya kujifunza.';
$string['privacy:metadata:lp_course_status:courseid'] = 'ID ya kozi.';
$string['privacy:metadata:lp_course_status:userid'] = 'Mwanafunzi.';
$string['privacy:metadata:lp_course_status:status'] = 'Thamani ya hali kama ilivyohifadhiwa na mfumo uliopita.';
$string['privacy:metadata:lp_course_status:percentage'] = 'Thamani ya asilimia kama ilivyohifadhiwa na mfumo uliopita.';
$string['privacy:metadata:lp_course_status:startdate'] = 'Wakati mwanafunzi alipoanza kozi kwenye njia.';
$string['privacy:metadata:lp_course_status:completiondate'] = 'Wakati mwanafunzi alipokamilisha kozi kwenye njia.';
$string['privacy:metadata:lp_course_status:usercreated'] = 'Mtumiaji aliyeunda safu.';
$string['privacy:metadata:lp_course_status:usermodified'] = 'Mtumiaji aliyebadilisha safu mara ya mwisho.';
$string['privacy:metadata:lp_course_status:timecreated'] = 'Wakati safu ilipoundwa.';
$string['privacy:metadata:lp_course_status:timemodified'] = 'Wakati safu ilipobadilishwa mara ya mwisho.';
