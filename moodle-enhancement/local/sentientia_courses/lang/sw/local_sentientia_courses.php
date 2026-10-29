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
 * Swahili strings for local_sentientia_courses.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Injini ya Kozi ya Sentientia';
$string['sentientia_courses:manage'] = 'Simamia kozi';
$string['sentientia_courses:enrol'] = 'Sajili watumiaji kwenye kozi';
$string['sentientia_courses:view'] = 'Tazama usimamizi wa kozi';
$string['sentientia_courses:create'] = 'Unda kozi';
$string['sentientia_courses:update'] = 'Hariri kozi';
$string['sentientia_courses:delete'] = 'Futa kozi';
$string['sentientia_courses:visibility'] = 'Onyesha au ficha kozi';
$string['addcourse'] = 'Ongeza Kozi';
$string['editcourse'] = 'Hariri Kozi';
$string['deletecourse'] = 'Futa Kozi';
$string['hidecourse'] = 'Ficha Kozi';
$string['showcourse'] = 'Onyesha Kozi';
$string['heading_basic'] = 'Taarifa za Msingi';
$string['heading_category'] = 'Kategoria na Shirika';
$string['heading_summary'] = 'Maelezo';
$string['heading_format'] = 'Muundo na Uonekanaji';
$string['fullname'] = 'Jina kamili la kozi';
$string['shortname'] = 'Jina fupi la kozi';
$string['shortname_help'] = 'Kitambulisho kifupi cha kipekee — kinatumika kwenye URL na ripoti.';
$string['idnumber'] = 'Namba ya ID ya kozi';
$string['category'] = 'Kategoria ya kozi';
$string['organisation'] = 'Shirika (tenant)';
$string['summary'] = 'Maelezo ya kozi';
$string['courseformat'] = 'Muundo wa kozi';
$string['format_topics'] = 'Muundo wa mada';
$string['format_weeks'] = 'Muundo wa kila wiki';
$string['format_single'] = 'Shughuli moja';
$string['format_social'] = 'Muundo wa kijamii';
$string['numsections'] = 'Idadi ya sehemu';
$string['visibility'] = 'Inaonekana kwa wanafunzi';
$string['startdate'] = 'Tarehe ya kuanza';
$string['enddate'] = 'Tarehe ya kumaliza';
$string['coursecompletiondays'] = 'Tarehe ya mwisho ya kukamilisha (siku tangu usajili)';
$string['coursecompletiondays_help'] = 'Idadi ya siku tangu usajili ambazo ndani yake wanafunzi lazima wakamilishe kozi hii. Inasomwa na <code>course_manager::get_completion_deadline()</code> na hutumiwa na mtiririko wa vikumbusho kuamua lini kuwakumbusha wanafunzi. Weka <code>0</code> kama hakuna tarehe ya mwisho. Mifano: <code>30</code> kwa moduli za uzingatiaji za kila mwezi, <code>180</code> kwa mafunzo ya kujikumbusha ya kila nusu mwaka, <code>365</code> kwa kuthibitishwa upya kila mwaka.';
$string['coursecompletiondays_invalid'] = 'Tarehe ya mwisho ya kukamilisha lazima iwe 0 au idadi chanya ya siku.';
$string['missingrequiredfields'] = 'Tafadhali jaza sehemu zote zinazohitajika.';
$string['shortnametaken'] = 'Jina hili fupi tayari linatumika. Tafadhali chagua jingine.';
$string['enddatebeforestart'] = 'Tarehe ya kumaliza lazima iwe baada ya tarehe ya kuanza.';
$string['cannotdeletesitecourse'] = 'Kozi ya tovuti haiwezi kufutwa.';
$string['confirmdelete'] = 'Una uhakika unataka kufuta "{$a}"? Hii itaondoa kabisa kozi pamoja na usajili wote, shughuli na alama zake. Hili haliwezi kutenduliwa.';
$string['confirmhide'] = 'Una uhakika unataka kuficha "{$a}"? Wanafunzi hawataiona tena kozi hii.';
$string['confirmshow'] = 'Una uhakika unataka kufanya "{$a}" ionekane kwa wanafunzi?';
$string['coursecreated'] = 'Kozi imeundwa.';
$string['courseupdated'] = 'Kozi imesasishwa.';
$string['coursedeleted'] = 'Kozi imefutwa.';
$string['coursehidden'] = 'Kozi imefichwa.';
$string['courseshown'] = 'Kozi inaonekana.';
$string['sentientia_courses:share_to_tenant'] = 'Shiriki kozi na tenant nyingine';
$string['event_course_share_created'] = 'Airpay: kozi imeshirikishwa kwa tenant';
$string['event_course_share_withdrawn'] = 'Airpay: ushirikishaji wa kozi umeondolewa kutoka kwa tenant';
$string['share_saved'] = 'Mipangilio ya kushiriki imehifadhiwa. Katalogi itasasishwa kwa tenant husika ndani ya sekunde chache.';
$string['invalidparameter'] = 'Kigezo kimoja au zaidi si sahihi.';
$string['sentientia_courses:request_course'] = 'Omba kozi ishirikishwe kwa tenant yangu';
$string['sentientia_courses:approve_request'] = 'Idhinisha / kataa maombi ya kushiriki kutoka tenant nyingine';
$string['event_course_share_requested'] = 'Airpay: ombi la kushiriki kozi limetumwa';
$string['event_course_share_request_approved'] = 'Airpay: ombi la kushiriki kozi limeidhinishwa';
$string['event_course_share_request_rejected'] = 'Airpay: ombi la kushiriki kozi limekataliwa';
$string['request_filed'] = 'Ombi lako limewasilishwa. Msimamizi wa Airpay atalikagua hivi karibuni.';
$string['request_approved'] = 'Ombi limeidhinishwa. Kozi sasa iko kwenye katalogi ya tenant iliyoomba.';
$string['request_rejected'] = 'Ombi limekataliwa. Mwombaji ataona uamuzi kwenye outbox yake.';
$string['invalidtenant'] = 'Hatukuweza kutambua tenant yako — akaunti yako ya mtumiaji haina njia ya shirika.';
$string['error_orgoutoftenant'] = 'Chagua shirika lililo ndani ya tenant yako.';
$string['error_crosstenantonly'] = 'Ni msimamizi wa tenant zote (cross-tenant) pekee anayeweza kushiriki kozi kati ya tenant au kuamua maombi ya kushiriki.';
$string['invaliduser'] = 'Akaunti ya mtumiaji aliyeomba haitumiki tena.';
$string['invalidcourse'] = 'Hakuna kozi kama hiyo.';
$string['cannotrequestowncourse'] = 'Huwezi kuomba kozi ambayo tenant yako tayari inaimiliki.';
$string['task_course_reminder'] = 'Kikumbusho cha tarehe ya mwisho ya kozi';
$string['messageprovider:course_reminder'] = 'Kikumbusho cha tarehe ya mwisho ya kozi';
$string['reminder_settings_heading'] = 'Vikumbusho vya tarehe ya mwisho ya kozi (cron)';
$string['reminder_settings_intro'] = 'Kazi iliyoratibiwa ya kila siku inayowakumbusha wanafunzi ambao tarehe ya mwisho ya kozi inakaribia. Tarehe ya mwisho huhesabiwa kutoka <code>enrolment.timestart + course.open_coursecompletiondays × 86400</code> — sehemu inayoonekana kwenye fomu ya kuhariri kozi. Kazi hii imezimwa kwa chaguo-msingi; ukishaamua kuitumia, iwashe kutoka <em>Site administration ▶ Server ▶ Scheduled tasks</em>. Ratiba ya chaguo-msingi: 09:00 kila siku.';
$string['reminder_enabled'] = 'Washa vikumbusho vya tarehe ya mwisho';
$string['reminder_enabled_help'] = 'Ikiwa IMEZIMWA (chaguo-msingi) kazi iliyoratibiwa haifanyi chochote. Ikiwa IMEWASHWA kazi inaendeshwa kila siku, inatuma arifa kwa wanafunzi wanaokaribia tarehe yao ya mwisho, na inarekodi kila utumaji kwenye <code>local_sentientia_courses_remind_sent</code> ili kuzuia marudio.';
$string['reminder_days_before'] = 'Viwango vya vikumbusho (siku kabla ya tarehe ya mwisho)';
$string['reminder_days_before_help'] = 'Orodha iliyotenganishwa kwa koma ya viwango vya siku kabla ya tarehe ya mwisho. <code>7,3,1</code> huwakumbusha wanafunzi siku 7 kabla, tena siku 3 kabla, na kikumbusho cha mwisho siku 1 kabla ya tarehe ya mwisho. Orodha tupu = hakuna vikumbusho. Viwango hupangwa upande mmoja tu — mwanafunzi aliyebakiza siku 5 huingia kiwango cha "7" mara moja tu.';
$string['reminder_max_per_run'] = 'Idadi ya juu ya arifa kwa kila mzunguko';
$string['reminder_max_per_run_help'] = 'Kikomo cha usalama ili kanuni iliyowekwa vibaya isiweze kujaza barua pepe kwa watumiaji 50 000 katika mzunguko mmoja wa cron. Chaguo-msingi 500. Wanafunzi ambao hawajashughulikiwa wanahamishiwa mzunguko unaofuata wa cron; faharasa ya kipekee inazuia marudio kwa hivyo hakuna kinachopotea.';
$string['reminder_status'] = 'Mzunguko wa mwisho';
$string['reminder_last_run_value'] = 'Mzunguko wa mwisho uliofanikiwa: <strong>{$a->time}</strong>. Arifa zilizotumwa katika mzunguko huo: <strong>{$a->count}</strong>. (Ukaguzi wa muda wote unapatikana kupitia <code>SELECT * FROM mdl_local_sentientia_courses_remind_sent ORDER BY timesent DESC</code>.)';
$string['reminder_last_run_never'] = 'Cron ya vikumbusho vya tarehe ya mwisho haijawahi kuendeshwa. Washa kazi hii na uangalie tena kesho baada ya saa 09:00 kwa saa ya seva, au endesha <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_reminder</code> kutoka kwenye command line ili kuiendesha mwenyewe.';
$string['reminder_subject'] = 'Kikumbusho: "{$a->fullname}" inatakiwa kukamilika ndani ya siku {$a->days_remaining}';
$string['reminder_small'] = '"{$a->fullname}" inatakiwa ndani ya siku {$a->days_remaining}';
$string['reminder_body_plain'] = 'Habari,

Hiki ni kikumbusho kwamba kozi yako "{$a->fullname}" inatakiwa kukamilika kufikia {$a->deadline} (siku {$a->days_remaining} kuanzia sasa).

Endelea nayo hapa: {$a->course_url}

— Airpay Academy';
$string['reminder_body_html'] = '<p>Habari,</p><p>Hiki ni kikumbusho kwamba kozi yako <strong>{$a->fullname}</strong> inatakiwa kukamilika kufikia <strong>{$a->deadline}</strong> (siku {$a->days_remaining} kuanzia sasa).</p><p><a href="{$a->course_url}">Endelea na kozi</a></p><p style="color:#777;">— Airpay Academy</p>';
$string['reminder_push_title'] = 'Kozi inatakiwa ndani ya siku {$a->days_remaining}';
$string['reminder_push_body'] = '"{$a->fullname}" — maliza kufikia {$a->deadline}. Gusa ili kuendelea.';
$string['task_course_overdue'] = 'Kozi iliyochelewa — kupandisha ngazi kwa meneja';
$string['messageprovider:course_overdue_supervisor'] = 'Kozi iliyochelewa — kupandisha ngazi kwa meneja';
$string['overdue_settings_heading'] = 'Kupandisha ngazi kwa meneja kwa kozi zilizochelewa (cron)';
$string['overdue_settings_intro'] = 'Kazi pacha ya kikumbusho cha tarehe ya mwisho. Mwanafunzi anapokosa tarehe ya mwisho ya kozi, kazi hii humjulisha meneja anayemripotia moja kwa moja (<code>user.open_supervisorid</code>). Wanafunzi wasio na meneja kwenye kumbukumbu wanarukwa — hakuna wa kupandishiwa ngazi. Kazi hii imezimwa kwa chaguo-msingi; iwashe kupitia <code>overdue_enabled</code> na uwashe kazi iliyoratibiwa kutoka <em>Site administration ▶ Server ▶ Scheduled tasks</em>. Ratiba ya chaguo-msingi: 09:30 kila siku.';
$string['overdue_enabled'] = 'Washa kupandisha ngazi kwa meneja';
$string['overdue_enabled_help'] = 'Ikiwa IMEZIMWA (chaguo-msingi), kazi haifanyi chochote. Ikiwa IMEWASHWA, kazi inaendeshwa kila siku, hupeleka taarifa za wanafunzi waliochelewa kwa meneja wao, na inazuia marudio kwa kutumia jedwali lile lile la <code>local_sentientia_courses_remind_sent</code> linalotumiwa na kazi ya vikumbusho (thamani hasi za <code>days_before_deadline</code> zinaonyesha safu za baada ya tarehe ya mwisho).';
$string['overdue_days_after'] = 'Viwango vya kupandisha ngazi (siku baada ya tarehe ya mwisho)';
$string['overdue_days_after_help'] = 'Orodha iliyotenganishwa kwa koma ya viwango vya siku baada ya tarehe ya mwisho. <code>1,7,14</code> humjulisha meneja siku 1 baada ya mwanafunzi kukosa, tena siku 7, na taarifa ya mwisho siku 14. Baada ya kiwango kikubwa zaidi, cron huacha kukumbusha.';
$string['overdue_max_per_run'] = 'Idadi ya juu ya taarifa za kupandisha ngazi kwa kila mzunguko';
$string['overdue_max_per_run_help'] = 'Kikomo cha usalama ili kanuni iliyowekwa vibaya isiweze kujaza barua pepe kwa kila meneja katika mzunguko mmoja wa cron. Chaguo-msingi 500. Wanafunzi ambao hawajashughulikiwa wanahamishiwa mzunguko unaofuata wa cron; faharasa ya kipekee inazuia marudio.';
$string['overdue_status'] = 'Mzunguko wa mwisho wa waliochelewa';
$string['overdue_last_run_value'] = 'Mzunguko wa mwisho uliofanikiwa: <strong>{$a->time}</strong>. Taarifa za kupandisha ngazi zilizotumwa: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never'] = 'Cron ya kupandisha ngazi kwa meneja haijawahi kuendeshwa. Washa kazi hii na uangalie tena kesho baada ya saa 09:30 kwa saa ya seva, au endesha <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_overdue</code> kutoka kwenye command line ili kuiendesha mwenyewe.';
$string['overdue_subject'] = '{$a->learner_name} amechelewa siku {$a->days_past} kwenye "{$a->course_name}"';
$string['overdue_small'] = '{$a->learner_name} amechelewa kwenye {$a->course_name}';
$string['overdue_push_title'] = '{$a->learner_name} — amechelewa siku {$a->days_past}';
$string['overdue_push_body'] = 'Amekosa tarehe ya mwisho ya "{$a->course_name}" ({$a->deadline}). Gusa ili kufuatilia.';
$string['overdue_body_plain'] = 'Habari,

Mwanatimu wako {$a->learner_name} amekosa tarehe ya mwisho ya kozi "{$a->course_name}".

Tarehe ya mwisho: {$a->deadline} (siku {$a->days_past} zilizopita).

Tazama kozi:    {$a->course_url}
Tazama mwanafunzi:   {$a->learner_profile_url}

Tafadhali fuatilia naye ili aweze kukamilisha kozi haraka iwezekanavyo.

— Airpay Academy';
$string['overdue_body_html'] = '<p>Habari,</p><p>Mwanatimu wako <strong>{$a->learner_name}</strong> amekosa tarehe ya mwisho ya kozi <strong>{$a->course_name}</strong>.</p><p>Tarehe ya mwisho: <strong>{$a->deadline}</strong> (siku {$a->days_past} zilizopita).</p><ul><li><a href="{$a->course_url}">Tazama kozi</a></li><li><a href="{$a->learner_profile_url}">Tazama wasifu wa mwanafunzi</a></li></ul><p>Tafadhali fuatilia naye ili aweze kukamilisha kozi haraka iwezekanavyo.</p><p style="color:#777;">— Airpay Academy</p>';
$string['browse_title'] = 'Vinjari katalogi ya {$a}';
$string['browse_intro'] = 'Vinjari kozi zinazomilikiwa na {$a->customer}. Omba ufikiaji wa kozi yoyote ungependa kuiongeza kwenye katalogi ya tenant yako ({$a->tenant}). Msimamizi wa {$a->customer} akishaidhinisha, kozi itaonekana kwenye katalogi ya wanafunzi wako — ukamilishaji hubaki ndani ya tenant yako pekee.';
$string['browse_col_course'] = 'Kozi';
$string['browse_col_category'] = 'Kategoria';
$string['browse_col_status'] = 'Hali';
$string['browse_col_action'] = 'Kitendo';
$string['browse_request_access'] = 'Omba ufikiaji';
$string['browse_empty'] = 'Maktaba ya {$a} haina kozi zilizochapishwa za kuvinjari kwa sasa.';
$string['browse_isolation_note'] = 'Wanafunzi wa tenant yako wataona kozi zilizoazimwa zikiwa na beji ya <strong>"Imetolewa na {$a->customer}"</strong>. Data ya ukamilishaji inabaki ndani ya tenant yako — {$a->customer} inaona ukamilishaji wake tu, na ripoti zako zinaonyesha wanafunzi wa {$a->tenant} pekee.';
$string['browse_state_none'] = 'Haijaombwa';
$string['browse_state_pending'] = 'Inasubiri idhini';
$string['browse_state_in_catalog'] = 'Iko kwenye katalogi yako';
$string['browse_state_rejected'] = 'Imekataliwa';
$string['browse_tenant_fallback'] = 'Tenant {$a}';
$string['privacy:metadata:courses_requests'] = 'Ombi la kushiriki kozi na tenant nyingine, nani aliliomba na nani aliliamua.';
$string['privacy:metadata:courses_requests:requester_userid'] = 'ID ya mtumiaji aliyeomba kozi.';
$string['privacy:metadata:courses_requests:courseid'] = 'ID ya kozi ambayo rekodi inaihusu.';
$string['privacy:metadata:courses_requests:requesting_tenant'] = 'Tenant ambayo ombi lilitoka kwake.';
$string['privacy:metadata:courses_requests:status'] = 'Hali ya ombi: inasubiri, imeidhinishwa au imekataliwa.';
$string['privacy:metadata:courses_requests:decided_by'] = 'ID ya mtumiaji aliyeamua ombi.';
$string['privacy:metadata:courses_requests:decision_reason'] = 'Sababu iliyorekodiwa kwa uamuzi.';
$string['privacy:metadata:courses_requests:timecreated'] = 'Wakati rekodi ilipoundwa.';
$string['privacy:metadata:courses_requests:timedecided'] = 'Wakati ombi lilipoamuliwa.';
$string['privacy:metadata:courses_remind_sent'] = 'Rekodi kwamba kikumbusho cha tarehe ya mwisho ya kozi kilitumwa kwa mfanyakazi, ili kisitumwe mara mbili.';
$string['privacy:metadata:courses_remind_sent:userid'] = 'ID ya mtumiaji ambaye rekodi hii inamhusu.';
$string['privacy:metadata:courses_remind_sent:courseid'] = 'ID ya kozi ambayo rekodi inaihusu.';
$string['privacy:metadata:courses_remind_sent:days_before_deadline'] = 'Siku ngapi kabla ya tarehe ya mwisho kikumbusho kilitumwa.';
$string['privacy:metadata:courses_remind_sent:deadline_ts'] = 'Tarehe ya mwisho ambayo kikumbusho kiliihusu.';
$string['privacy:metadata:courses_remind_sent:timesent'] = 'Wakati kikumbusho kilipotumwa.';
$string['privacy:metadata:course_type'] = 'Aina ya kozi, yaani lebo inayotambulisha kozi (kwa mfano E-learning au Darasani), pamoja na nani aliiunda na nani aliibadilisha mwisho.';
$string['privacy:metadata:course_type:usercreated'] = 'ID ya mtumiaji aliyeunda aina ya kozi.';
$string['privacy:metadata:course_type:usermodified'] = 'ID ya mtumiaji aliyebadilisha aina ya kozi mara ya mwisho.';
$string['privacy:metadata:course_type:timecreated'] = 'Wakati aina ya kozi ilipoundwa.';
$string['privacy:metadata:course_type:timemodified'] = 'Wakati aina ya kozi ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:course_category'] = 'Kategoria maalum ya kozi, pamoja na nani aliiunda na nani aliibadilisha mwisho.';
$string['privacy:metadata:course_category:usercreated'] = 'ID ya mtumiaji aliyeunda kategoria.';
$string['privacy:metadata:course_category:usermodified'] = 'ID ya mtumiaji aliyebadilisha kategoria mara ya mwisho.';
$string['privacy:metadata:course_category:timecreated'] = 'Wakati kategoria ilipoundwa.';
$string['privacy:metadata:course_category:timemodified'] = 'Wakati kategoria ilipobadilishwa mara ya mwisho.';
