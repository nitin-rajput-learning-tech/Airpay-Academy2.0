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
 * Swahili strings for local_sentientia_exams.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Mitihani ya Mtandaoni ya Sentientia';
$string['sentientia_exams:manage'] = 'Simamia mitihani ya mtandaoni';
$string['sentientia_exams:view'] = 'Tazama mitihani ya mtandaoni';
$string['sentientia_exams:enrol'] = 'Andikisha watumiaji kwenye mitihani (kupitia kozi kuu ya jaribio)';
$string['addexam'] = 'Sajili Mtihani';
$string['editexam'] = 'Hariri Mtihani';
$string['deleteexam'] = 'Ondoa Usajili wa Mtihani';
$string['activateexam'] = 'Washa Mtihani';
$string['deactivateexam'] = 'Zima Mtihani';
$string['heading_basic'] = 'Uchaguzi wa Jaribio';
$string['heading_settings'] = 'Mipangilio ya Mtihani';
$string['heading_org'] = 'Shirika';
$string['quiz'] = 'Jaribio la msingi';
$string['quiz_help'] = 'Chagua shughuli ya jaribio iliyopo ya Sentientia LMS ili kuisajili kama mtihani wa kampuni. Yanaonyeshwa majaribio ambayo bado hayajasajiliwa tu. Kwanza tengeneza jaribio kwenye kozi yake (Ongeza shughuli > Jaribio), kisha lisajili hapa.';
$string['exam_name'] = 'Jina la mtihani litakaloonyeshwa';
$string['exam_name_help'] = 'Jina rahisi kueleweka linaloonyeshwa kwenye ripoti na dashibodi. Linaweza kutofautiana na jina la jaribio la msingi.';
$string['duration'] = 'Kikomo cha muda (sekunde)';
$string['duration_help'] = 'Badilisha kikomo cha muda wa jaribio kwa ngazi ya mtihani. Acha wazi ili kutumia muda chaguomsingi wa jaribio. 1800 = dakika 30, 3600 = saa 1.';
$string['passinggrade'] = 'Alama ya kufaulu (%)';
$string['organisation'] = 'Shirika (tenant)';
$string['exam_active'] = 'Mtihani unatumika';
$string['exam_category'] = 'Kategoria';
$string['exam_category_help'] = 'Weka lebo kwenye mtihani huu ili upatikane kwa urahisi. Unatumia mpangilio ule ule wa kategoria kama kozi (unaowekwa kwenye Usimamizi wa tovuti ▶ Kozi ▶ Simamia kategoria za kozi) ili admin waweze kuweka mitihani pamoja na mafunzo yanayowaandaa wanafunzi kwa mitihani hiyo. Makundi ya kawaida: Uzingatiaji wa kanuni, Mauzo, Uongozi, Onboarding. Acha kama <em>Haina kategoria</em> ikiwa mtihani ni wa mara moja tu.';
$string['uncategorised'] = '— Haina kategoria —';
$string['missingrequiredfields'] = 'Tafadhali chagua jaribio na uweke jina la kuonyeshwa.';
$string['invalidquiz'] = 'Jaribio lililochaguliwa halipo tena.';
$string['invalidcategory'] = 'Kategoria iliyochaguliwa haipo tena.';
$string['quizalreadyregistered'] = 'Jaribio hili tayari limesajiliwa kama mtihani. Badala yake, hariri mtihani uliopo.';
$string['duration_invalid'] = 'Muda lazima uwe 0 au idadi chanya ya sekunde.';
$string['passinggrade_invalid'] = 'Alama ya kufaulu lazima iwe kati ya 0 na 100.';
$string['confirmdelete'] = 'Ondoa usajili wa mtihani "{$a}"? Jaribio la msingi la Sentientia LMS HALITAFUTWA — litabaki kwenye kozi yake. Ni taarifa za mtihani wa kampuni pekee (tenant, jina la kuonyeshwa, hali) zitakazoondolewa.';
$string['confirmactivate'] = 'Washa "{$a}"? Wanafunzi watauona kama mtihani unaopatikana.';
$string['confirmdeactivate'] = 'Zima "{$a}"? Wanafunzi hawatauona, lakini data itahifadhiwa.';
$string['examcreated'] = 'Mtihani umesajiliwa.';
$string['examupdated'] = 'Mtihani umesasishwa.';
$string['examdeleted'] = 'Usajili wa mtihani umeondolewa (jaribio la msingi limehifadhiwa).';
$string['examstatuschanged'] = 'Hali ya mtihani imesasishwa.';
$string['noexams_subtitle'] = 'Sajili majaribio yaliyopo ya Sentientia LMS kama mitihani ya kampuni ili kuongeza mipaka ya tenant, alama maalum za kufaulu, na ripoti kwenye dashibodi.';
$string['task_exam_reminder'] = 'Kikumbusho cha tarehe ya mwisho ya mtihani';
$string['messageprovider:exam_reminder'] = 'Kikumbusho cha tarehe ya mwisho ya mtihani';
$string['reminder_settings_heading'] = 'Vikumbusho vya tarehe ya mwisho ya mtihani (cron)';
$string['reminder_settings_intro'] = 'Kazi iliyoratibiwa ya kila siku inayowakumbusha wanafunzi ambao tarehe ya mwisho ya mtihani wao inakaribia. Chanzo cha tarehe ya mwisho: <code>quiz.timeclose</code> (muda wa kalenda uliowekwa kwenye jaribio husika). Kazi hii imezimwa kwa chaguomsingi; iwashe kupitia <code>reminder_enabled</code> hapa chini, kisha washa kazi kwenye <em>Usimamizi wa tovuti ▶ Seva ▶ Kazi zilizoratibiwa</em>. Ratiba chaguomsingi: 09:15 kila siku.';
$string['reminder_enabled'] = 'Washa vikumbusho vya mitihani';
$string['reminder_enabled_help'] = 'Ikiwa IMEZIMWA (chaguomsingi) kazi haifanyi chochote. Ikiwa IMEWASHWA inaendeshwa kila siku, inatuma arifa kwa wanafunzi wanaokaribia timeclose ya mtihani, na inazuia arifa zisijirudie kupitia <code>local_sentientia_exams_remind_sent</code>.';
$string['reminder_days_before'] = 'Makundi ya vikumbusho (siku kabla ya timeclose)';
$string['reminder_days_before_help'] = 'Orodha ya viwango vya siku kabla ya tarehe ya mwisho, vilivyotenganishwa kwa koma. <code>7,3,1</code> inawakumbusha wanafunzi siku 7 kabla, tena siku 3 kabla, na mwisho siku 1 kabla. Upangaji wa makundi huenda upande mmoja tu — mwanafunzi aliyebakiwa na siku 5 anaingia kundi la "7" mara moja tu.';
$string['reminder_max_per_run'] = 'Idadi ya juu ya arifa kwa kila mzunguko';
$string['reminder_max_per_run_help'] = 'Kikomo cha usalama ili kanuni iliyowekwa vibaya isiweze kumjaza kila mwanafunzi barua pepe katika mzunguko mmoja wa cron. Chaguomsingi 500. Wanafunzi ambao hawajashughulikiwa wanahamia mzunguko unaofuata; faharasa ya kipekee inazuia arifa zisijirudie.';
$string['reminder_status'] = 'Mzunguko wa mwisho';
$string['reminder_last_run_value'] = 'Mzunguko wa mwisho uliofanikiwa: <strong>{$a->time}</strong>. Arifa zilizotumwa katika mzunguko huo: <strong>{$a->count}</strong>.';
$string['reminder_last_run_never'] = 'Cron ya kikumbusho cha tarehe ya mwisho ya mtihani haijawahi kuendeshwa. Washa kazi hiyo kisha angalia tena baada ya saa 09:15 kwa muda wa seva, au endesha <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_exams\\\\task\\\\exam_reminder</code> kutoka kwenye command line ili kuianzisha mwenyewe.';
$string['reminder_subject'] = 'Kikumbusho: mtihani "{$a->examname}" unaisha baada ya siku {$a->days_remaining}';
$string['reminder_small'] = '"{$a->examname}" unaisha baada ya siku {$a->days_remaining}';
$string['reminder_push_title'] = 'Mtihani unaisha baada ya siku {$a->days_remaining}';
$string['reminder_push_body'] = '"{$a->examname}" — maliza kabla ya {$a->deadline}. Gusa ili kufanya mtihani.';
$string['reminder_body_plain'] = 'Habari,

Hiki ni kikumbusho kwamba mtihani "{$a->examname}" (kozi: {$a->coursename}) unafungwa {$a->deadline} — siku {$a->days_remaining} kuanzia sasa.

Fanya mtihani hapa: {$a->exam_url}

— Airpay Academy';
$string['reminder_body_html'] = '<p>Habari,</p><p>Hiki ni kikumbusho kwamba mtihani <strong>{$a->examname}</strong> (kozi: {$a->coursename}) unafungwa <strong>{$a->deadline}</strong> — siku {$a->days_remaining} kuanzia sasa.</p><p><a href="{$a->exam_url}">Fanya mtihani</a></p><p style="color:#777;">— Airpay Academy</p>';
$string['task_exam_overdue'] = 'Mtihani uliochelewa — kuripoti kwa meneja';
$string['messageprovider:exam_overdue_supervisor'] = 'Mtihani uliochelewa — kuripoti kwa msimamizi';
$string['overdue_settings_heading'] = 'Kuripoti kwa meneja kuhusu waliochelewa (cron)';
$string['overdue_settings_intro'] = 'Kazi inayoendana na kikumbusho cha mtihani. Mwanafunzi anapopitwa na <code>quiz.timeclose</code> ya mtihani, kazi hii inamjulisha msimamizi wake wa moja kwa moja (<code>user.open_supervisorid</code>). Wanafunzi wasio na msimamizi wanarukwa. Inatumia tena jedwali la <code>local_sentientia_exams_remind_sent</code> lenye thamani HASI za <code>days_before_deadline</code> kuweka alama kwenye safu za baada ya tarehe ya mwisho. Ratiba chaguomsingi: 09:45 kila siku.';
$string['overdue_enabled'] = 'Washa kuripoti kwa msimamizi';
$string['overdue_enabled_help'] = 'Ikiwa IMEZIMWA (chaguomsingi) kazi haifanyi chochote. Ikiwa IMEWASHWA inaendeshwa kila siku, inawaripoti wanafunzi waliochelewa kwa msimamizi wao, na inazuia ripoti zisijirudie kwa kutumia jedwali lile lile linalotumiwa na kazi ya kikumbusho.';
$string['overdue_days_after'] = 'Makundi ya kuripoti (siku baada ya tarehe ya mwisho)';
$string['overdue_days_after_help'] = 'Orodha ya viwango vya siku baada ya tarehe ya mwisho, vilivyotenganishwa kwa koma. <code>1,7,14</code> inaripoti siku 1 / 7 / 14 baada ya quiz.timeclose, kisha inasimama.';
$string['overdue_max_per_run'] = 'Idadi ya juu ya ripoti kwa kila mzunguko';
$string['overdue_max_per_run_help'] = 'Kikomo cha usalama ili kanuni iliyowekwa vibaya isiweze kumjaza kila meneja barua pepe katika mzunguko mmoja wa cron. Chaguomsingi 500.';
$string['overdue_status'] = 'Mzunguko wa mwisho wa waliochelewa';
$string['overdue_last_run_value'] = 'Mzunguko wa mwisho uliofanikiwa: <strong>{$a->time}</strong>. Ripoti zilizotumwa: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never'] = 'Cron ya kuripoti kwa msimamizi kuhusu mitihani haijawahi kuendeshwa. Iwashe kisha angalia tena, au endesha <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_exams\\\\task\\\\exam_overdue</code> mwenyewe.';
$string['overdue_subject'] = '{$a->learner_name} amechelewa siku {$a->days_past} kwenye mtihani "{$a->exam_name}"';
$string['overdue_small'] = '{$a->learner_name} amechelewa kwenye {$a->exam_name}';
$string['overdue_push_title'] = '{$a->learner_name} — mtihani umechelewa siku {$a->days_past}';
$string['overdue_push_body'] = 'Amepitwa na tarehe ya mwisho ya mtihani "{$a->exam_name}" ({$a->deadline}). Gusa ili kufuatilia.';
$string['overdue_body_plain'] = 'Habari,

Mwanatimu wako {$a->learner_name} amepitwa na tarehe ya mwisho ya mtihani "{$a->exam_name}" (kozi: {$a->coursename}).

Tarehe ya mwisho: {$a->deadline} (siku {$a->days_past} zilizopita).

Tazama mtihani:    {$a->exam_url}
Tazama mwanafunzi: {$a->learner_profile_url}

Tafadhali fuatilia naye.

— Airpay Academy';
$string['overdue_body_html'] = '<p>Habari,</p><p>Mwanatimu wako <strong>{$a->learner_name}</strong> amepitwa na tarehe ya mwisho ya mtihani <strong>{$a->exam_name}</strong> (kozi: {$a->coursename}).</p><p>Tarehe ya mwisho: <strong>{$a->deadline}</strong> (siku {$a->days_past} zilizopita).</p><ul><li><a href="{$a->exam_url}">Tazama mtihani</a></li><li><a href="{$a->learner_profile_url}">Tazama wasifu wa mwanafunzi</a></li></ul><p>Tafadhali fuatilia naye.</p><p style="color:#777;">— Airpay Academy</p>';
$string['privacy:metadata:exams_remind_sent'] = 'Rekodi inayoonyesha kuwa kikumbusho cha tarehe ya mwisho ya mtihani kimetumwa kwa mfanyakazi, ili kisitumwe mara mbili.';
$string['privacy:metadata:exams_remind_sent:userid'] = 'ID ya mtumiaji ambaye rekodi hii inamhusu.';
$string['privacy:metadata:exams_remind_sent:examid'] = 'ID ya mtihani ambao kikumbusho kilihusu.';
$string['privacy:metadata:exams_remind_sent:days_before_deadline'] = 'Siku ngapi kabla ya tarehe ya mwisho kikumbusho kilitumwa.';
$string['privacy:metadata:exams_remind_sent:deadline_ts'] = 'Tarehe ya mwisho ambayo kikumbusho kilihusu.';
$string['privacy:metadata:exams_remind_sent:timesent'] = 'Wakati kikumbusho kilipotumwa.';
