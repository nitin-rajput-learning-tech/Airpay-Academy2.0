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
 * Swahili strings for local_sentientia_talent.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_talent
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Uhamaji wa Vipaji wa Sentientia';
$string['sentientia_talent:viewopportunities'] = 'Tazama ubao wa nafasi za ndani';
$string['sentientia_talent:registerinterest'] = 'Onyesha nia kwenye nafasi ya ndani';
$string['sentientia_talent:viewcareerpath'] = 'Tazama njia za kikazi za cheo chako';
$string['sentientia_talent:viewsuccession'] = 'Tazama mipango ya urithi wa vyeo (HR / mameneja)';
$string['sentientia_talent:managesuccession'] = 'Simamia uteuzi wa warithi (HR / mameneja)';
$string['sentientia_talent:managecareerpaths'] = 'Unda na hariri njia za kikazi';
$string['sentientia_talent:manageopportunities'] = 'Tangaza na simamia nafasi za ndani';
$string['sentientia_talent:audit'] = 'Tazama kumbukumbu za ukaguzi wa vipaji';
$string['nav_console'] = 'Uhamaji wa vipaji';
$string['nav_opportunities'] = 'Nafasi za ndani';
$string['heading_console'] = 'Dashibodi ya uhamaji wa vipaji';
$string['heading_opportunities'] = 'Nafasi za ndani';
$string['heading_paths'] = 'Njia za kikazi';
$string['heading_succession'] = 'Mipango ya urithi wa vyeo';
$string['heading_mypath'] = 'Njia yako ya kikazi';
$string['kpi_paths'] = 'Njia za kikazi';
$string['kpi_successions'] = 'Uteuzi wa warithi';
$string['kpi_opportunities'] = 'Nafasi zilizo wazi';
$string['col_path'] = 'Njia';
$string['col_from'] = 'Cheo cha kuanzia';
$string['col_to'] = 'Cheo cha kufikia';
$string['col_status'] = 'Hali';
$string['col_role'] = 'Cheo';
$string['col_candidate'] = 'Mgombea';
$string['col_incumbent'] = 'Mshikiliaji wa sasa';
$string['col_readiness'] = 'Utayari';
$string['col_match'] = 'Ulinganifu wa ujuzi';
$string['col_title'] = 'Kichwa';
$string['status_active'] = 'Inatumika';
$string['status_archived'] = 'Kwenye kumbukumbu';
$string['readiness_ready_now'] = 'Yuko tayari sasa';
$string['readiness_ready_1y'] = 'Atakuwa tayari ndani ya mwaka 1';
$string['readiness_ready_2y'] = 'Atakuwa tayari ndani ya miaka 2';
$string['readiness_developing'] = 'Anaendelezwa';
$string['mypath_intro'] = 'Kwa kuzingatia cheo chako cha sasa ({$a}), hatua hizi za kupanda ziko wazi kwako:';
$string['match_label'] = 'ulinganifu';
$string['match_help'] = 'Jinsi ujuzi wako wa sasa unavyolingana na ujuzi unaohitajika kwa cheo hiki.';
$string['interest_message'] = 'Ongeza maelezo mafupi (si lazima)';
$string['btn_register'] = 'Onyesha nia';
$string['btn_withdraw'] = 'Ondoa nia';
$string['interest_registered_badge'] = 'Nia imewasilishwa';
$string['interest_saved'] = 'Nia yako imesasishwa.';
$string['empty_paths'] = 'Bado hakuna njia za kikazi zilizowekwa kwa tenant yako.';
$string['empty_succession'] = 'Bado hakuna uteuzi wa warithi uliorekodiwa.';
$string['empty_opportunities'] = 'Hakuna nafasi za ndani zilizo wazi kwa sasa.';
$string['skillsource_active'] = 'Ulinganishaji wa ujuzi unaendeshwa na: {$a}';
$string['skillsource_skillsai'] = 'Taksonomia ya ujuzi ya AI (Sentientia SkillsAI)';
$string['skillsource_manual'] = 'Jedwali la ujuzi la kujazwa kwa mkono (Sentientia Skills)';
$string['settings_pagetitle'] = 'Uhamaji wa Vipaji wa Sentientia';
$string['settings_section_general'] = 'Uhamaji wa vipaji';
$string['settings_section_general_desc'] = 'Kifurushi cha uhamaji wa vipaji kinadhibitiwa na Switchboard ya feature flag za jukwaa. Ukurasa huu unatoa muhtasari wa hali ya sasa.';
$string['setting_skillsource'] = 'Taksonomia ya ujuzi inayotumika';
$string['setting_skillsource_desc'] = 'Ulinganishaji wa ujuzi kwa nafasi na wagombea warithi kwa sasa unatumia: {$a}. Plugin ya AI ya ujuzi ikiwa imesakinishwa na kuwashwa, inatumika kiotomatiki; vinginevyo jedwali la ujuzi la kujazwa kwa mkono linatumika.';
$string['setting_switchboard'] = 'Feature flags';
$string['setting_switchboard_desc'] = 'Washa au zima kifurushi cha uhamaji wa vipaji kwa kila tenant kwenye Switchboard ya jukwaa.';
$string['setting_switchboard_btn'] = 'Fungua Switchboard';
$string['error_featuredisabled'] = 'Kipengele cha uhamaji wa vipaji hakijawashwa kwa shirika lako.';
$string['error_missingfields'] = 'Tafadhali jaza sehemu zote zinazohitajika.';
$string['error_invalidreadiness'] = 'Thamani ya utayari si sahihi.';
$string['error_invalidstatus'] = 'Hali ya nafasi si sahihi.';
$string['error_duplicatenomination'] = 'Mtu huyu tayari ameteuliwa kuwa mrithi wa cheo hiki.';
$string['error_opportunityclosed'] = 'Nafasi hii haipokei tena nia mpya.';
$string['privacy:metadata:succ'] = 'Uteuzi wa warithi unaounganisha wagombea na vyeo muhimu.';
$string['privacy:metadata:succ:designation'] = 'Cheo kinachopangiwa urithi.';
$string['privacy:metadata:succ:candidateid'] = 'Mtumiaji aliyeteuliwa kuwa mrithi.';
$string['privacy:metadata:succ:incumbentid'] = 'Mtumiaji anayeshikilia cheo hicho kwa sasa.';
$string['privacy:metadata:succ:readiness'] = 'Kiwango cha utayari wa mgombea kuchukua cheo hicho.';
$string['privacy:metadata:succ:notes'] = 'Maelezo ya HR kuhusu uteuzi.';
$string['privacy:metadata:succ:timecreated'] = 'Wakati uteuzi ulipoundwa.';
$string['privacy:metadata:int'] = 'Nia zilizoonyeshwa kwenye nafasi za ndani.';
$string['privacy:metadata:int:opportunityid'] = 'Nafasi ambayo nia inahusiana nayo.';
$string['privacy:metadata:int:userid'] = 'Mtumiaji aliyeonyesha nia.';
$string['privacy:metadata:int:message'] = 'Maelezo kutoka kwa mwombaji (si lazima).';
$string['privacy:metadata:int:matchpct'] = 'Asilimia ya ulinganifu wa ujuzi wakati nia ilipoonyeshwa.';
$string['privacy:metadata:int:timecreated'] = 'Wakati nia ilipowasilishwa.';
$string['privacy:metadata:opp'] = 'Matangazo ya nafasi za ndani.';
$string['privacy:metadata:opp:title'] = 'Kichwa cha nafasi.';
$string['privacy:metadata:opp:postedby'] = 'Mtumiaji aliyetangaza nafasi.';
$string['privacy:metadata:opp:timecreated'] = 'Wakati nafasi ilipotangazwa.';
$string['privacy:metadata:audit'] = 'Kumbukumbu za ukaguzi wa vitendo nyeti vya HR kuhusu vipaji.';
$string['privacy:metadata:audit:action'] = 'Kitendo kilichofanywa.';
$string['privacy:metadata:audit:targetuserid'] = 'Mhusika wa kitendo, ikiwa yupo.';
$string['privacy:metadata:audit:changedby'] = 'Mtumiaji aliyefanya kitendo.';
$string['privacy:metadata:audit:timecreated'] = 'Wakati kitendo kilipotokea.';
$string['privacy:metadata:path'] = 'Ufafanuzi wa njia za kikazi. Hubaki hata data ya mtu ikifutwa; mtu aliyehariri mwisho huondolewa humo.';
$string['privacy:metadata:path:name'] = 'Jina la njia ya kikazi.';
$string['privacy:metadata:path:usermodified'] = 'Mtumiaji aliyehariri njia ya kikazi mwisho. Huwekwa 0 mtumiaji huyo anapofutwa.';
$string['privacy:metadata:path:timemodified'] = 'Wakati njia ya kikazi ilipohaririwa mwisho.';
$string['privacy:metadata:succ:usermodified'] = 'Mtumiaji aliyehariri uteuzi mwisho. Huwekwa 0 mtumiaji huyo anapofutwa.';
$string['privacy:metadata:opp:usermodified'] = 'Mtumiaji aliyehariri nafasi mwisho. Huwekwa 0 mtumiaji huyo anapofutwa.';
