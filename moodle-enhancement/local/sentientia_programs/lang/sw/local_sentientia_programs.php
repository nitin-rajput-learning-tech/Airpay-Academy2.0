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
 * Swahili strings for local_sentientia_programs.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Programu za Vyeti za Sentientia';
$string['sentientia_programs:view'] = 'Kuona programu za vyeti';
$string['sentientia_programs:manage'] = 'Kusimamia programu za vyeti';
$string['sentientia_programs:enrol'] = 'Kusajili kwenye programu';
$string['sentientia_programs:create'] = 'Kuunda programu';
$string['sentientia_programs:update'] = 'Kuhariri programu';
$string['sentientia_programs:delete'] = 'Kufuta programu';
$string['addprogram'] = 'Ongeza Programu ya Vyeti';
$string['editprogram'] = 'Hariri Programu';
$string['deleteprogram'] = 'Futa Programu';
$string['publishprogram'] = 'Chapisha Programu';
$string['archiveprogram'] = 'Weka Programu Kwenye Kumbukumbu';
$string['draftprogram'] = 'Rudisha Kwenye Rasimu';
$string['heading_basic'] = 'Maelezo ya Programu';
$string['heading_org'] = 'Shirika';
$string['heading_completion'] = 'Kanuni za Kukamilisha';
$string['heading_window'] = 'Kipindi cha usajili (si lazima)';
$string['heading_status'] = 'Hali';
$string['startdate'] = 'Tarehe ya kuanza';
$string['startdate_help'] = 'Si lazima. Programu itaanza kupokea usajili kuanzia tarehe hii. Iache wazi ili kuruhusu usajili mara moja.';
$string['enddate'] = 'Tarehe ya mwisho';
$string['enddate_help'] = 'Si lazima. Programu itafungwa kwa usajili mpya baada ya tarehe hii. Wanafunzi ambao tayari wamesajiliwa hawataathirika.';
$string['enddate_before_start'] = 'Tarehe ya mwisho lazima iwe siku ya kuanza au baada yake.';
$string['audience_modal_title'] = 'Sajili kwa wingi kulingana na walengwa';
$string['audience_form_intro'] = 'Chagua kigezo kimoja au zaidi cha kichujio ili kulenga kundi la watumiaji. Onyesho la awali hapa chini hubadilika unapobadilisha vichujio. Bofya "Sajili watumiaji wanaolingana" ili kuthibitisha.';
$string['audience_any'] = 'Yoyote';
$string['audience_any_cohort'] = 'Kundi lolote';
$string['audience_users_matched'] = 'watumiaji wamelingana';
$string['audience_pick_at_least_one'] = 'Chagua angalau kigezo kimoja cha kichujio (tumia fomu ya kawaida ya Sajili Watumiaji ili kusajili watumiaji wote).';
$string['audience_enrol_button'] = 'Sajili watumiaji wanaolingana';
$string['audience_enrol_result'] = 'Usajili mpya %d; watumiaji %d walilingana na walengwa.';
$string['designation'] = 'Cheo';
$string['region'] = 'Kanda';
$string['location'] = 'Mahali';
$string['employmenttype'] = 'Aina ya ajira';
$string['cohort'] = 'Kundi';
$string['name'] = 'Jina la programu';
$string['description'] = 'Maelezo';
$string['organisation'] = 'Shirika (tenant)';
$string['completion_rule'] = 'Jinsi wanafunzi wanavyokamilisha programu hii';
$string['completion_all_levels'] = 'Kamilisha ngazi ZOTE (cheti cha mfuatano)';
$string['completion_any_level'] = 'Kamilisha ngazi YOYOTE (cheti sambamba)';
$string['status'] = 'Hali';
$string['status_draft'] = 'Rasimu';
$string['status_active'] = 'Inatumika';
$string['status_archived'] = 'Kwenye kumbukumbu';
$string['missingrequiredfields'] = 'Tafadhali jaza sehemu zote zinazohitajika.';
$string['invalidstatus'] = 'Thamani ya hali si sahihi.';
$string['confirmdelete'] = 'Una uhakika unataka kufuta "{$a}"? Hii itaondoa kabisa programu, ngazi zake zote, kozi zilizopangwa na usajili wa wanafunzi. Hatua hii haiwezi kutenduliwa.';
$string['confirmpublish'] = 'Chapisha "{$a}" ili wanafunzi waweze kuipata?';
$string['confirmarchive'] = 'Weka "{$a}" kwenye kumbukumbu? Haitapokea tena usajili mpya.';
$string['confirmdraft'] = 'Rudisha "{$a}" kwenye rasimu? Itafichwa kwa wanafunzi.';
$string['programcreated'] = 'Programu ya vyeti imeundwa.';
$string['programupdated'] = 'Programu imesasishwa.';
$string['programdeleted'] = 'Programu imefutwa.';
$string['programstatuschanged'] = 'Hali ya programu imesasishwa.';
$string['view_program_title'] = 'Programu: {$a}';
$string['back_to_programs'] = 'Rudi kwenye programu';
$string['tab_overview'] = 'Muhtasari';
$string['tab_levels'] = 'Ngazi';
$string['tab_users'] = 'Watumiaji';
$string['no_description'] = 'Hakuna maelezo yaliyowekwa.';
$string['updated'] = 'Imesasishwa';
$string['levels_count_label'] = 'Ngazi {$a}';
$string['enrolled_count_label'] = '{$a} wamesajiliwa';
$string['add_level'] = 'Ongeza Ngazi';
$string['edit_level'] = 'Hariri Ngazi';
$string['delete_level'] = 'Futa Ngazi';
$string['level_name'] = 'Jina la ngazi';
$string['level_description'] = 'Maelezo';
$string['level_completion'] = 'Ukamilishaji';
$string['level_required'] = 'Inahitajika ili kukamilisha programu';
$string['level_optional'] = 'Ngazi ya hiari (inaweza kurukwa)';
$string['level_position'] = 'Nafasi';
$string['level_courses'] = 'Kozi';
$string['no_levels'] = 'Programu hii bado haina ngazi. Ongeza angalau ngazi moja ili kuanza.';
$string['levelcreated'] = 'Ngazi imeongezwa.';
$string['levelupdated'] = 'Ngazi imesasishwa.';
$string['leveldeleted'] = 'Ngazi imefutwa.';
$string['confirm_delete_level'] = 'Futa ngazi "{$a}"? Hii pia itaondoa kozi zilizopangwa kwake. Hatua hii haiwezi kutenduliwa.';
$string['toomanylevels'] = 'Ngazi nyingi mno katika ombi moja (kikomo ni 200).';
$string['manage_level_courses'] = 'Simamia kozi za: {$a}';
$string['back_to_program'] = 'Rudi kwenye programu';
$string['add_courses'] = 'Ongeza Kozi';
$string['no_courses_assigned'] = 'Bado hakuna kozi zilizopangwa kwenye ngazi hii.';
$string['confirm_unassign_course'] = 'Ondoa "{$a}" kwenye ngazi hii?';
$string['courseassigned'] = 'Kozi zimepangwa kwenye ngazi.';
$string['courseunassigned'] = 'Kozi imeondolewa kwenye ngazi.';
$string['courses_assigned_count'] = 'Kozi {$a} zimepangwa.';
$string['toomanycourses'] = 'Kozi nyingi mno katika ombi moja (kikomo ni 100).';
$string['enrol_users'] = 'Sajili Watumiaji';
$string['unenrol_user'] = 'Ondoa kwenye programu';
$string['no_users_enrolled'] = 'Bado hakuna wanafunzi waliosajiliwa kwenye programu hii.';
$string['confirm_unenrol_user'] = 'Ondoa {$a} kwenye programu hii? Maendeleo yao ya ngazi pia yatafutwa.';
$string['userunenrolled'] = 'Mtumiaji ameondolewa kwenye programu.';
$string['users_enrolled_success'] = 'Watumiaji {$a} wamesajiliwa.';
$string['toomanyusers'] = 'Watumiaji wengi mno katika ombi moja (kikomo ni 500).';
$string['privacy:metadata'] = 'Plugin ya Programu za Vyeti za Airpay huhifadhi usajili wa watumiaji kwa kila programu na maendeleo ya sasa ya ngazi ya kila mwanafunzi.';
$string['privacy:metadata:enrol'] = 'Rekodi za usajili wa watumiaji kwa kila programu.';
$string['privacy:metadata:enrol:programid'] = 'ID ya programu.';
$string['privacy:metadata:enrol:userid'] = 'ID ya mtumiaji aliyesajiliwa.';
$string['privacy:metadata:enrol:currentlevelid'] = 'Ngazi ambayo mtumiaji yupo sasa (NULL = hajaanza).';
$string['privacy:metadata:enrol:status'] = 'Hali ya usajili (new/in_progress/completed).';
$string['privacy:metadata:enrol:timecreated'] = 'Muhuri wa muda wa usajili.';
$string['privacy:metadata:enrol:timecompleted'] = 'Muhuri wa muda wa kukamilisha (NULL kama haijakamilika).';
$string['event_program_completed'] = 'Programu imekamilika';
$string['myprograms'] = 'Programu zangu';
$string['myprograms_none'] = 'Bado hujasajiliwa kwenye programu yoyote ya vyeti.';
$string['completed_on'] = 'Ilikamilika tarehe';
$string['completed_on_date'] = 'Ilikamilika tarehe {$a}';
$string['enrolled_on_date'] = 'Alisajiliwa tarehe {$a}';
$string['status_completed'] = 'Imekamilika';
$string['status_inprogress'] = 'Inaendelea';
$string['status_enrolled'] = 'Amesajiliwa';
$string['levels_progress'] = 'Ngazi {$a->done} kati ya {$a->total}';
$string['overall_progress'] = 'Maendeleo ya jumla ya programu';
$string['level_locked'] = 'Imefungwa';
$string['level_locked_hint'] = 'Kamilisha ngazi ya lazima iliyotangulia ili kuifungua.';
$string['badge_required'] = 'lazima';
$string['badge_optional'] = 'hiari';
$string['courses_progress'] = 'Kozi za lazima {$a->done}/{$a->total}';
$string['courses_progress_any'] = 'Kozi yoyote moja kati ya {$a->total} za lazima ({$a->done} zimekamilika)';
$string['error_learner_page_off'] = 'Ukurasa huu haupatikani.';
$string['error_history_protected'] = 'Rekodi hii ililetwa kutoka BizLMS na ni sehemu ya historia ya vyeti, hivyo haiwezi kufutwa. Badala yake weka programu kwenye kumbukumbu.';
$string['privacy:metadata:enrol:enrolledby'] = 'Mtumiaji aliyemsajili mwanafunzi (0 = haijulikani). Huondolewa mtumiaji huyo anapofutwa.';
$string['privacy:metadata:enrol:timemodified'] = 'Wakati rekodi ya usajili ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:lvlcomp'] = 'Ukamilishaji wa ngazi uliohifadhiwa wa mwanafunzi kwenye programu ya vyeti (historia iliyoletwa kutoka BizLMS).';
$string['privacy:metadata:lvlcomp:programid'] = 'ID ya programu.';
$string['privacy:metadata:lvlcomp:levelid'] = 'ID ya ngazi.';
$string['privacy:metadata:lvlcomp:userid'] = 'ID ya mtumiaji wa mwanafunzi.';
$string['privacy:metadata:lvlcomp:status'] = 'Hali ya ukamilishaji (1 = imekamilika).';
$string['privacy:metadata:lvlcomp:timecompleted'] = 'Wakati ngazi ilipokamilika (tupu kama chanzo hakikuirekodi).';
$string['privacy:metadata:lvlcomp:completedcourseids'] = 'ID za kozi ambazo BizLMS ilirekodi kwa ukamilishaji huu (kwa ukaguzi tu).';
$string['privacy:metadata:lvlcomp:source'] = 'Mahali safu hii ilipotoka (bizlms kwa safu zilizoletwa).';
$string['privacy:metadata:lvlcomp:timecreated'] = 'Wakati rekodi ilipoundwa.';
$string['privacy:metadata:lvlcomp:timemodified'] = 'Wakati rekodi ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:trainers'] = 'Wakufunzi waliopangwa kwenye programu ya vyeti (historia iliyoletwa kutoka BizLMS).';
$string['privacy:metadata:trainers:programid'] = 'ID ya programu.';
$string['privacy:metadata:trainers:userid'] = 'ID ya mtumiaji wa mkufunzi.';
$string['privacy:metadata:trainers:feedbackid'] = 'ID ya fomu ya tathmini iliyotumika kwa mrejesho kuhusu mkufunzi.';
$string['privacy:metadata:trainers:feedback_score'] = 'Alama ya mrejesho ambayo BizLMS ilirekodi kwa mkufunzi.';
$string['privacy:metadata:trainers:assignedby'] = 'Mtumiaji aliyempanga mkufunzi (0 = haijulikani). Huondolewa mtumiaji huyo anapofutwa.';
$string['privacy:metadata:trainers:timecreated'] = 'Wakati upangaji ulipoundwa.';
$string['privacy:metadata:trainers:timemodified'] = 'Wakati upangaji ulipobadilishwa mara ya mwisho.';
$string['privacy:metadata:trainerfb'] = 'Mrejesho uliotolewa kuhusu mkufunzi wa programu (historia iliyoletwa kutoka BizLMS).';
$string['privacy:metadata:trainerfb:programtrainerid'] = 'Upangaji wa mkufunzi ambao mrejesho unauhusu.';
$string['privacy:metadata:trainerfb:programid'] = 'ID ya programu.';
$string['privacy:metadata:trainerfb:trainerid'] = 'Mkufunzi ambaye mrejesho unamhusu (ID ya mtumiaji).';
$string['privacy:metadata:trainerfb:userid'] = 'Mwanafunzi aliyetoa mrejesho (tupu ikiwa haijulikani au amefutwa).';
$string['privacy:metadata:trainerfb:score'] = 'Alama ya mrejesho.';
$string['privacy:metadata:trainerfb:timecreated'] = 'Wakati mrejesho uliporekodiwa.';
$string['privacy:metadata:trainerfb:timemodified'] = 'Wakati mrejesho ulipobadilishwa mara ya mwisho.';
