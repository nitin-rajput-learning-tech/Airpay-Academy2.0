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
 * Swahili strings for local_sentientia_leaderboard.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_leaderboard
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Mbao za Vinara za Wakati Halisi';
$string['sentientia_leaderboard:view'] = 'Tazama mbao za vinara ndani ya tenant yangu';
$string['sentientia_leaderboard:manageboard'] = 'Unda, hariri na futa mbao za vinara ndani ya tenant yangu';
$string['sentientia_leaderboard:promoteboard'] = 'Pandisha ubao wa vinara uonekane kote kwa mteja';
$string['sentientia_leaderboard:viewall'] = 'Tazama mbao za vinara za tenant zote (uchambuzi wa HR)';
$string['type_quiz'] = 'Vinara wa alama za quiz';
$string['type_completion'] = 'Waliokamilisha haraka zaidi';
$string['type_skill'] = 'Pointi za ujuzi zilizopatikana';
$string['type_quiz_desc'] = 'Panga wanafunzi kwa alama zao bora kwenye quiz moja. Wakilingana, muda uliotumika ndio huamua.';
$string['type_completion_desc'] = 'Panga wanafunzi kulingana na jinsi walivyokamilisha kozi haraka. Muda mfupi = bora zaidi.';
$string['type_skill_desc'] = 'Panga wanafunzi kwa pointi za ujuzi walizopata katika muda wa tarehe uliowekwa.';
$string['scope_course'] = 'Kozi';
$string['scope_tenant'] = 'Tenant';
$string['scope_customer'] = 'Kote kwa mteja';
$string['status_active'] = 'Inatumika';
$string['status_disabled'] = 'Imezimwa';
$string['status_archived'] = 'Kwenye kumbukumbu';
$string['col_rank'] = 'Nafasi';
$string['col_user'] = 'Mwanafunzi';
$string['col_points'] = 'Pointi';
$string['col_score'] = 'Alama';
$string['col_time'] = 'Muda uliotumika';
$string['col_progress'] = 'Maendeleo';
$string['col_skills'] = 'Ujuzi uliopanda kiwango';
$string['heading_index'] = 'Mbao za vinara';
$string['heading_create'] = 'Unda ubao wa vinara';
$string['heading_edit'] = 'Hariri ubao wa vinara';
$string['heading_view'] = 'Ubao wa vinara';
$string['label_name'] = 'Jina la ubao';
$string['label_type'] = 'Aina ya ubao';
$string['label_scope'] = 'Wigo';
$string['label_course'] = 'Kozi';
$string['label_quiz'] = 'Quiz';
$string['label_skills'] = 'Ujuzi (ID zilizotenganishwa kwa koma; acha wazi kwa zote)';
$string['label_window_start'] = 'Mwanzo wa muda wa kuhesabu alama';
$string['label_window_end'] = 'Mwisho wa muda wa kuhesabu alama';
$string['label_recompute'] = 'Muda kati ya kukokotoa upya (sekunde)';
$string['label_top_n'] = 'Onyesha N bora';
$string['label_show_my_rank'] = 'Mwonyeshe mtazamaji nafasi yake mwenyewe';
$string['label_optout'] = 'Nifiche kwenye mbao za vinara za umma';
$string['label_optout_desc'] = 'Ukiweka tiki hapa, jina lako litafichwa kwenye kila ubao wa vinara wa umma. Bado utapata pointi, lakini wanafunzi wengine hawataona nafasi yako.';
$string['action_create'] = 'Unda ubao';
$string['action_save'] = 'Hifadhi mabadiliko';
$string['action_delete'] = 'Futa';
$string['action_view'] = 'Tazama';
$string['action_recompute'] = 'Kokotoa upya sasa';
$string['anonymous'] = 'Mwanafunzi asiyejulikana';
$string['you'] = 'Wewe';
$string['your_rank'] = 'Nafasi yako: {$a}';
$string['no_rank_optout'] = 'Umejiondoa kwenye mbao za vinara za umma.';
$string['no_rank_no_entry'] = 'Bado huna nafasi kwenye ubao huu.';
$string['no_entries'] = 'Bado hakuna nafasi — angalia tena baada ya ukokotoaji upya ujao.';
$string['last_recomputed_at'] = 'Ilisasishwa mwisho: {$a}';
$string['live_indicator'] = 'Mubashara — inasasishwa papo hapo';
$string['polling_fallback'] = 'Inasasishwa kila sekunde 30';
$string['feature_disabled'] = 'Mbao za vinara hazijawashwa. Mwombe msimamizi awashe sentientia.leaderboards.enabled.';
$string['type_disabled'] = 'Aina hii ya ubao haijawashwa.';
$string['block_title'] = 'Ubao wa vinara';
$string['block_choose'] = 'Chagua ubao wa vinara';
$string['block_none'] = 'Hakuna mbao za vinara zinazopatikana kwenye tenant yako.';
$string['error_invalidtype'] = 'Aina ya ubao si sahihi. Lazima iwe mojawapo ya: quiz, completion, skill.';
$string['error_invalidscope'] = 'Wigo si sahihi. Lazima uwe mojawapo ya: course, tenant, customer.';
$string['error_invalidwindow'] = 'Mwisho wa muda wa kuhesabu alama lazima uwe baada ya mwanzo wake.';
$string['error_invalidrecompute'] = 'Muda kati ya kukokotoa upya lazima uwe angalau sekunde 30.';
$string['error_typenotenabled'] = 'Aina hii ya ubao haijawashwa na msimamizi.';
$string['error_quiznotscoped'] = 'Mbao za quiz lazima zihusishwe na quiz maalum (quizid > 0).';
$string['error_completionnotscoped'] = 'Mbao za ukamilishaji lazima zihusishwe na kozi maalum (courseid > 0).';
$string['error_noboard'] = 'Ubao wa vinara haukupatikana.';
$string['error_outoftenant'] = 'Huwezi kutazama ubao wa vinara wa tenant nyingine.';
$string['error_cantpromote'] = 'Huna ruhusa ya kufanya ubao uonekane kote kwa mteja.';
$string['error_invalidpayload'] = 'Data ya payload si sahihi kwa kumbukumbu ya matukio.';
$string['invalid_event_type'] = 'Aina ya tukio isiyojulikana: {$a}';
$string['task_recompute_due_boards'] = 'Kokotoa upya mbao za vinara zilizofikia muda (Sentientia)';
$string['task_purge_old_events'] = 'Futa matukio ya zamani ya SSE ya mbao za vinara (Sentientia)';
$string['event_rankings_updated'] = 'Nafasi za ubao wa vinara zimesasishwa';
$string['messageprovider:rank_change'] = 'Mabadiliko ya nafasi kwenye ubao wa vinara';
$string['msg_top10_subject'] = 'Umeingia kwenye {$a->new_rank} bora katika {$a->boardname}!';
$string['msg_top10_body'] = 'Kazi nzuri — umeingia kwenye 10 bora katika "{$a->boardname}" ukiwa nafasi ya #{$a->new_rank}. Endelea hivyo ili upande juu zaidi.';
$string['msg_moveup_subject'] = 'Umepanda nafasi {$a->delta} kwenye {$a->boardname}';
$string['msg_moveup_body'] = 'Umepanda kutoka nafasi #{$a->old_rank} hadi #{$a->new_rank} kwenye "{$a->boardname}" — umeruka nafasi {$a->delta}. Hongera!';
$string['msg_movedown_subject'] = 'Umeshuka nafasi {$a->delta} kwenye {$a->boardname}';
$string['msg_movedown_body'] = 'Nafasi yako kwenye "{$a->boardname}" imeshuka kutoka #{$a->old_rank} hadi #{$a->new_rank} (chini kwa nafasi {$a->delta}). Unataka kuirudisha? Fungua ubao na uendelee kujifunza.';
$string['preference_optout'] = 'Nifiche kwenye mbao za vinara za umma';
$string['privacy:metadata:lb_entries'] = 'Nafasi za ubao wa vinara za mtumiaji zilizohifadhiwa kwenye cache';
$string['privacy:metadata:lb_entries:userid'] = 'Mtumiaji anayepangiwa nafasi';
$string['privacy:metadata:lb_entries:boardid'] = 'Ubao wa vinara ambao nafasi hii ni yake';
$string['privacy:metadata:lb_entries:points'] = 'Pointi zilizopatikana kwenye ubao';
$string['privacy:metadata:lb_entries:userrank'] = 'Nafasi ya mtumiaji';
$string['privacy:metadata:lb_entries:last_recomputed'] = 'Wakati safu hii ilipokokotolewa mara ya mwisho';
$string['privacy:metadata:lb_optouts'] = 'Kujiondoa kwa kila mtumiaji ili asionyeshwe hadharani';
$string['privacy:metadata:lb_optouts:userid'] = 'Mtumiaji aliyejiondoa';
$string['privacy:metadata:lb_optouts:customerid'] = 'Wigo wa mteja wa kujiondoa huku';
$string['privacy:metadata:lb_optouts:timeoptedout'] = 'Wakati mtumiaji alipojiondoa';
$string['privacy:metadata:lb_boards'] = 'Mbao za vinara, pamoja na aliyeunda kila ubao.';
$string['privacy:metadata:lb_boards:ownerid'] = 'ID ya mtumiaji anayemiliki ubao wa vinara.';
$string['privacy:metadata:lb_boards:name'] = 'Jina la ubao wa vinara.';
$string['privacy:metadata:lb_notify_log'] = 'Rekodi ya arifa za mabadiliko ya nafasi zilizotumwa kwako.';
$string['privacy:metadata:lb_notify_log:userid'] = 'ID ya mtumiaji aliyearifiwa.';
$string['privacy:metadata:lb_notify_log:boardid'] = 'Ubao wa vinara ambao arifa ilihusu.';
$string['privacy:metadata:lb_notify_log:last_sent'] = 'Wakati arifa ya mwisho ilipotumwa.';
$string['privacy:metadata:lb_notify_log:last_old_rank'] = 'Nafasi yako kabla ya mabadiliko.';
$string['privacy:metadata:lb_notify_log:last_new_rank'] = 'Nafasi yako baada ya mabadiliko.';
$string['privacy:metadata:lb_notify_log:last_reason'] = 'Sababu ya arifa kutumwa.';
