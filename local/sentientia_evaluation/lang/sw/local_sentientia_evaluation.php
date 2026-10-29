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
 * Swahili strings for local_sentientia_evaluation.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Tathmini za Sentientia';
$string['sentientia_evaluation:manage'] = 'Simamia fomu za tathmini';
$string['sentientia_evaluation:respond'] = 'Jibu fomu za tathmini';
$string['task_process_triggers'] = 'Shughulikia vichocheo vya tathmini vilivyo kwenye foleni';
$string['messageprovider:evaluation_invite'] = 'Mwaliko wa tathmini';
$string['invaliditemid'] = 'ID ya kipengele si sahihi kwa tukio la kichocheo';
$string['invalidratearea'] = 'Eneo la ukadiriaji si sahihi';
$string['messageprovider:evaluation_response'] = 'Jibu la tathmini — arifa kwa msimamizi';
$string['addevaluation'] = 'Unda Fomu ya Tathmini';
$string['editevaluation'] = 'Hariri Fomu ya Tathmini';
$string['deleteevaluation'] = 'Futa Tathmini';
$string['publishevaluation'] = 'Chapisha Tathmini';
$string['archiveevaluation'] = 'Weka Tathmini Kwenye Kumbukumbu';
$string['draftevaluation'] = 'Rudisha Kwenye Rasimu';
$string['heading_basic'] = 'Utambulisho wa Fomu';
$string['heading_kirkpatrick'] = 'Mfumo wa Tathmini';
$string['heading_trigger'] = 'Wakati wa Kutuma';
$string['heading_privacy'] = 'Faragha';
$string['heading_window'] = 'Muda wa upatikanaji (si lazima)';
$string['eval_name'] = 'Jina la fomu';
$string['description'] = 'Maelezo';
$string['kirkpatrick_level'] = 'Kiwango cha Kirkpatrick';
$string['kirkpatrick_level_help'] = 'Modeli ya Donald Kirkpatrick ya tathmini ya mafunzo yenye viwango 4. Kiwango 1 (mwitikio) hutumwa mara moja; Kiwango 3-4 (tabia/matokeo) kwa kawaida huhitaji kusubiri siku 30-90 ili kupima jinsi mafunzo yanavyotumika kazini.';
$string['trigger_event'] = 'Tukio la kichocheo';
$string['days_after'] = 'Siku baada ya kichocheo';
$string['days_after_help'] = 'Idadi ya siku za kusubiri baada ya tukio la kichocheo kabla ya kutuma tathmini. 0 = tuma mara moja. Mipangilio ya kawaida: 0 (Kiwango 1), 7 (Kiwango 2), 30-60 (Kiwango 3), 90-180 (Kiwango 4).';
$string['organisation'] = 'Shirika (tenant)';
$string['anonymous'] = 'Kusanya majibu bila majina';
$string['anonymous_help'] = 'Ikiwekewa tiki, majibu hayaunganishwi na mtumiaji yeyote mahususi. Hupunguza tabia ya watu kutoa majibu yanayopendeza jamii kwenye mada nyeti kama POSH au madodoso ya utamaduni, lakini huzuia kumfuatilia mjibuji mmoja mmoja. Mtu yeyote akishajibu, hali ya kutokuwa na majina haiwezi kuzimwa.';
$string['status'] = 'Hali';
$string['status_draft'] = 'Rasimu';
$string['status_active'] = 'Inatumika';
$string['status_archived'] = 'Kwenye kumbukumbu';
$string['missingrequiredfields'] = 'Tafadhali jaza sehemu zote za lazima.';
$string['invalidkirkpatricklevel'] = 'Kiwango cha Kirkpatrick si sahihi (lazima kiwe 1-4).';
$string['invalidtrigger'] = 'Tukio la kichocheo si sahihi.';
$string['invalidstatus'] = 'Thamani ya hali si sahihi.';
$string['days_after_invalid'] = 'Siku baada ya kichocheo lazima ziwe 0 au zaidi.';
$string['confirmdelete'] = 'Futa tathmini "{$a}"? Hii itaondoa kabisa fomu, maswali yake yote, na majibu yote yaliyowasilishwa. Hatua hii haiwezi kutenduliwa.';
$string['confirmpublish'] = 'Chapisha "{$a}"? Itaanza kukusanya majibu kulingana na tukio la kichocheo.';
$string['confirmarchive'] = 'Weka "{$a}" kwenye kumbukumbu? Itaacha kukusanya majibu mapya lakini data ya zamani itahifadhiwa.';
$string['confirmdraft'] = 'Rudisha "{$a}" kwenye rasimu? Itasimamisha ukusanyaji wa majibu kwa muda.';
$string['evaluationcreated'] = 'Fomu ya tathmini imeundwa.';
$string['evaluationupdated'] = 'Fomu ya tathmini imesasishwa.';
$string['evaluationdeleted'] = 'Tathmini imefutwa.';
$string['evaluationstatuschanged'] = 'Hali ya tathmini imesasishwa.';
$string['managequestions'] = 'Simamia Maswali';
$string['addquestion'] = 'Ongeza Swali';
$string['editquestion'] = 'Hariri Swali';
$string['deletequestion'] = 'Futa Swali';
$string['question_type'] = 'Aina ya swali';
$string['question_type_help'] = 'Chagua jinsi wanafunzi watakavyojibu. Ukadiriaji ni bora kwa tathmini za L1/L2; NPS kwa kupima utayari wa kupendekeza; chaguo-nyingi kwa hali za kulazimika kuchagua jibu moja; maandishi kwa mrejesho wa kina.';
$string['question_text'] = 'Maandishi ya swali';
$string['question_options'] = 'Machaguo ya majibu (moja kwa kila mstari)';
$string['question_options_help'] = 'Kwa maswali ya chaguo-nyingi pekee. Andika kila chaguo kwenye mstari mpya. Angalau machaguo 2 yanahitajika.';
$string['question_required'] = 'Lazima (mwanafunzi lazima ajibu)';
$string['invalidquestiontype'] = 'Aina ya swali si sahihi.';
$string['invalidevaluation'] = 'Fomu ya tathmini haikupatikana.';
$string['invalidquestion'] = 'Swali halikupatikana.';
$string['multichoice_needs_options'] = 'Maswali ya chaguo-nyingi yanahitaji angalau machaguo 2.';
$string['confirmdeletequestion'] = 'Futa swali hili? Majibu yoyote ya swali hili pia yataondolewa kwenye mawasilisho ya awali.';
$string['questioncreated'] = 'Swali limeongezwa.';
$string['questionupdated'] = 'Swali limesasishwa.';
$string['questiondeleted'] = 'Swali limefutwa.';
$string['orderupdated'] = 'Mpangilio wa maswali umehifadhiwa.';
$string['analysis_title'] = 'Uchambuzi wa Tathmini';
$string['analysis_subtitle'] = 'Muhtasari wa Kirkpatrick wa tathmini zote';
$string['filter_from'] = 'Imewasilishwa kuanzia';
$string['filter_to'] = 'Imewasilishwa hadi';
$string['filter_courseid'] = 'ID ya kozi';
$string['filter_programid'] = 'ID ya programu';
$string['filter_classroomid'] = 'ID ya darasa';
$string['filter_apply'] = 'Tumia vichujio';
$string['filter_clear'] = 'Safisha';
$string['filter_subset_hint'] = 'Takwimu zilizo hapa chini zinaonyesha sehemu iliyochujwa tu';
$string['export_csv'] = 'Hamisha CSV';
$string['no_responses_filtered'] = 'Hakuna majibu yanayolingana na kichujio cha sasa';
$string['no_responses_filtered_help'] = 'Badilisha muda wa tarehe au vichujio vya muktadha hapo juu, au subiri wanafunzi wawasilishe tathmini.';
$string['avg_rating'] = 'Wastani wa ukadiriaji';
$string['nps_score'] = 'Alama ya NPS';
$string['stat_evaluations'] = 'Tathmini';
$string['stat_responses'] = 'Majibu';
$string['viewresponses'] = 'Tazama Majibu';
$string['evaluationnotactive'] = 'Tathmini hii haipokei majibu kwa sasa.';
$string['alreadyresponded'] = 'Umeshawasilisha tathmini hii.';
$string['evaluationhasnoquestions'] = 'Tathmini hii bado haina maswali.';
$string['timeopen'] = 'Inafunguliwa kuanzia';
$string['timeopen_help'] = 'Si lazima. Tarehe ya mapema zaidi ambayo wanafunzi wanaweza kuwasilisha jibu. Usiweke tiki kwenye kisanduku ili kufungua tathmini mara moja. Tumia hii kwa mtiririko kama "dodoso la uzingatiaji linaendelea kuanzia 1 Juni hadi 30 Juni".';
$string['timeclose'] = 'Inafungwa tarehe';
$string['timeclose_help'] = 'Si lazima. Tarehe ambayo tathmini inaacha kupokea majibu mapya (majibu yaliyopo yanahifadhiwa). Usiweke tiki ili tathmini ibaki wazi bila kikomo. Lazima iwe siku ya kufunguliwa au baada yake.';
$string['multiple_submit'] = 'Ruhusu mtumiaji mmoja kuwasilisha majibu mara nyingi';
$string['multiple_submit_help'] = 'Weka tiki kuruhusu madodoso ya aina ya pulse ambapo mwanafunzi yuleyule huwasilisha tena kadri muda unavyokwenda (ukaguzi wa ushiriki wa kila wiki, tiki ya uzingatiaji ya kila mwezi, n.k.). Usipoweka tiki (chaguo-msingi), kila mtumiaji anapata uwasilishaji mmoja tu. Tathmini zisizo na majina huruhusu kuwasilisha tena daima bila kujali mpangilio huu.';
$string['eval_window_inverted'] = 'Tarehe ya kufunga lazima iwe siku ya kufungua au baada yake.';
$string['evaluationnotyetopen'] = 'Tathmini hii inafunguliwa tarehe {$a} — tafadhali rudi wakati huo.';
$string['evaluationclosed'] = 'Tathmini hii ilifungwa tarehe {$a}.';
$string['eval_notyetopen_heading'] = 'Bado haijafunguliwa';
$string['eval_notyetopen_body'] = 'Tathmini hii inafunguliwa tarehe {$a}. Tafadhali rudi wakati huo kuwasilisha jibu lako.';
$string['eval_closed_heading'] = 'Tathmini imefungwa';
$string['eval_closed_body'] = 'Tathmini hii ilifungwa tarehe {$a}. Majibu mapya hayapokelewi tena.';
$string['eval_pulse_hint'] = 'Hii ni tathmini ya kujirudia (pulse) — unaweza kuwasilisha jibu jipya kila unapotembelea.';
$string['numeric_min'] = 'Thamani ya chini inayoruhusiwa';
$string['numeric_min_help'] = 'Si lazima. Acha wazi kama hutaki kikomo cha chini. Mwanafunzi huona hii kama sifa ya <code>min</code> kwenye kisanduku cha kuingiza — thamani zilizo chini yake zinakataliwa wakati wa kuwasilisha. Mifano: <code>0</code> kwa "namba kamili zisizo hasi", <code>18</code> kwa umri.';
$string['numeric_max'] = 'Thamani ya juu inayoruhusiwa';
$string['numeric_max_help'] = 'Si lazima. Acha wazi kama hutaki kikomo cha juu. Lazima iwe kubwa kuliko au sawa na thamani ya chini kama zote zimewekwa. Mifano: <code>100</code> kwa asilimia, <code>120</code> kwa umri.';
$string['numeric_must_be_integer'] = 'Lazima iwe namba kamili (mf. 0, 5, 100).';
$string['numeric_min_max_invalid'] = 'Thamani ya juu lazima iwe kubwa kuliko au sawa na thamani ya chini.';
$string['invalid_numeric'] = 'Jibu lazima liwe namba kamili kwa: {$a}';
$string['invalid_numeric_below_min'] = 'Jibu la "{$a->q}" lazima liwe angalau {$a->min}.';
$string['invalid_numeric_above_max'] = 'Jibu la "{$a->q}" lazima lisizidi {$a->max}.';
$string['invalid_multichoice_multi'] = 'Chaguo moja au zaidi kati ya uliyochagua si sahihi kwa: {$a}';
$string['multichoice_multi_hint'] = 'Weka tiki kwenye machaguo yote yanayohusika.';
$string['heading_dependency'] = 'Kuonyesha kwa masharti (si lazima)';
$string['dep_none'] = '— Huonyeshwa daima —';
$string['dep_parent'] = 'Onyesha swali hili tu ikiwa…';
$string['dep_parent_help'] = 'Chagua swali kuu. Swali hili litaonekana kwenye ukurasa wa kujibu tu pale swali kuu limejibiwa NA jibu lake linalingana na thamani iliyo hapa chini. Mizunguko inazuiwa wakati wa kuhifadhi. Acha kama <em>Huonyeshwa daima</em> kwa maswali yasiyo na masharti.';
$string['dep_value'] = '… jibu la swali kuu linalingana na';
$string['dep_value_help'] = 'Kwa maswali makuu ya Ndiyo/Hapana, andika <code>yes</code> au <code>no</code>. Kwa maswali makuu ya chaguo-nyingi, andika lebo moja ya chaguo kama ilivyo hasa (herufi kubwa na ndogo zinazingatiwa). Kwa maswali makuu ya ukadiriaji / NPS / namba, andika thamani ya namba. Acha wazi kumaanisha <em>jibu lolote lisilo tupu linaonyesha swali hili</em> — inafaa kwa "mtumiaji akijibu Q3 kwa namna yoyote, uliza Q4".';
$string['dep_invalid_parent'] = 'Swali kuu ulilochagua halipo.';
$string['dep_self_reference'] = 'Swali haliwezi kutegemea swali lenyewe.';
$string['dep_parent_other_evaluation'] = 'Swali kuu si sehemu ya tathmini hii. Chagua swali la tathmini hii hii.';
$string['dep_cycle'] = 'Utegemezi huu ungeunda mzunguko (swali kuu hatimaye linategemea tena swali hili).';
$string['non_respondents_title'] = '{$a} — hawajajibu / wamekamilisha';
$string['non_respondents_heading'] = 'Nani amejibu "{$a}"?';
$string['non_respondents_subtitle'] = 'Wanafunzi waliopangiwa na hali ya majibu yao. Upangaji wa kiotomatiki unatoka kwenye foleni ya vichocheo W1-5 mwanafunzi anapokamilisha kozi / programu / darasa linalostahili; upangaji wa mkono unaweza kuongezwa kupitia UI ya msimamizi (baadaye).';
$string['back_to_evaluations'] = 'Rudi kwenye tathmini';
$string['non_respondents_tab_pending'] = 'Hawajajibu';
$string['non_respondents_tab_responded'] = 'Wamejibu';
$string['non_respondents_col_name'] = 'Jina';
$string['non_respondents_col_email'] = 'Barua pepe';
$string['non_respondents_col_trigger'] = 'Amepangiwa kupitia';
$string['non_respondents_col_assigned'] = 'Amepangiwa';
$string['non_respondents_col_due'] = 'Tarehe ya mwisho';
$string['non_respondents_col_responded'] = 'Amejibu';
$string['non_respondents_trigger_manual'] = 'Kwa mkono';
$string['non_respondents_trigger_course'] = 'Kozi';
$string['non_respondents_trigger_program'] = 'Programu';
$string['non_respondents_trigger_classroom'] = 'Darasa';
$string['non_respondents_empty_pending_heading'] = 'Kila mtu amejibu.';
$string['non_respondents_empty_pending_body'] = 'Ama kila mwanafunzi aliyepangiwa amejaza tathmini hii, au bado hakuna upangaji uliorekodiwa. Tathmini zinazotegemea kichocheo huwapangia wanafunzi kiotomatiki wanapokamilisha shughuli inayostahili.';
$string['non_respondents_empty_responded_heading'] = 'Bado hakuna majibu.';
$string['non_respondents_empty_responded_body'] = 'Wanafunzi waliopangiwa wakishawasilisha majibu yao, yataonekana hapa.';
$string['non_respondents_anonymous_heading'] = 'Majibu ya tathmini hii hayana majina, kwa ujumla au kwa sehemu.';
$string['non_respondents_anonymous_body'] = 'Nani amejibu, na lini, haionyeshwi: muda wa jibu unaweza kuhusishwa na jibu la mtu mmoja mmoja. Idadi kwenye kichupo hiki bado ni sahihi, na kichupo cha Hawajajibu kinaonyesha ambao bado hawajajibu.';
$string['error_anonymity_locked'] = 'Watu wameshajibu tathmini hii wakiamini kwamba majibu yao hayana majina, kwa hiyo hali ya kutokuwa na majina haiwezi kuzimwa. Ili kukusanya majibu yenye majina, unda tathmini mpya.';
$string['error_question_anonymity_delete_locked'] = 'Watu wameshajibu tathmini hii wakati swali hili halikuwa na majina, kwa hiyo haliwezi kufutwa.';
$string['error_question_anonymity_locked'] = 'Watu wameshajibu tathmini hii wakati swali hili halikuwa na majina, kwa hiyo haliwezi kubadilishwa kuwa na majina. Ili kuliuliza pamoja na majina, ongeza swali jipya.';
$string['filterstoolong'] = 'Data ya kichujio ni ndefu mno.';
$string['bulk_assign_modal_title'] = 'Pangia kwa wingi kulingana na walengwa';
$string['bulk_assign_form_intro'] = 'Chagua kigezo kimoja au zaidi cha kuchuja ili kulenga kikundi cha watumiaji. Onyesho la awali hapa chini hubadilika unapobadilisha vichujio. Bofya "Pangia watumiaji wanaolingana" ili kuthibitisha — wanafunzi waliokwisha pangiwa hurukwa bila kurudiwa.';
$string['bulk_assign_button'] = 'Pangia watumiaji wanaolingana';
$string['bulk_assign_pick_at_least_one'] = 'Chagua angalau kigezo kimoja cha kuchuja.';
$string['bulk_assign_result'] = 'Upangaji mpya {$a->assigned}; watumiaji {$a->matched} walilingana na walengwa ({$a->existing} walikuwa wameshapangiwa).';
$string['bulk_assign_capped'] = 'Idadi ya walengwa imefikia kikomo cha {$a}. Boresha kichujio chako ili kupangia watumiaji zaidi (au endesha upangaji wa wingi mara mbili kwa vigezo finyu zaidi).';
$string['audience_any'] = 'Yoyote';
$string['audience_any_cohort'] = 'Kundi lolote';
$string['audience_users_matched'] = 'watumiaji wanalingana na walengwa hawa';
$string['audience_designation'] = 'Cheo';
$string['audience_region'] = 'Kanda';
$string['audience_location'] = 'Mahali';
$string['audience_employmenttype'] = 'Aina ya ajira';
$string['audience_cohort'] = 'Kundi';
$string['template_name_required'] = 'Jina la kiolezo linahitajika.';
$string['template_payload_corrupt'] = 'Payload ya kiolezo hiki haikuweza kusomwa. Huenda safu hii ilihaririwa nje ya Sentientia LMS. Ifute kisha uihifadhi upya.';
$string['template_saved'] = 'Kiolezo kimehifadhiwa.';
$string['template_deleted'] = 'Kiolezo kimefutwa.';
$string['template_save_modal_title'] = 'Hifadhi kama kiolezo';
$string['template_picker_modal_title'] = 'Unda kutoka kwenye kiolezo';
$string['template_name_label'] = 'Jina la kiolezo';
$string['template_desc_label'] = 'Maelezo mafupi (si lazima)';
$string['template_ispublic'] = 'Fanya kiolezo hiki kipatikane kwa tenant nyingine';
$string['template_ispublic_help'] = 'Ikiwekewa tiki, wasimamizi wa tenant NYINGINE wanaweza kuchagua kiolezo hiki wanapounda tathmini. Inafaa kwa fomu zilizoandaliwa na HQ (POSH, AML, kupinga rushwa). Usiweke tiki ili kiolezo kibaki ndani ya tenant yako tu.';
$string['task_expire_assignments'] = 'Funga kiotomatiki upangaji wa tathmini uliochelewa';
$string['heading_notifications'] = 'Arifa';
$string['notify_admin_on_response'] = 'Tuma barua pepe kwa wasimamizi wa tovuti kwa kila jibu';
$string['notify_admin_on_response_help'] = 'Ikiwekewa tiki, kila uwasilishaji uliofanikiwa hutuma arifa ya Sentientia LMS kwa wasimamizi wote wa tovuti. Wanaweza kujiondoa kwa kila njia (barua pepe / popup / arifa za push za simu) kwenye mapendeleo yao ya arifa. Inafaa kwa madodoso ya kimkakati yenye majibu machache (pulse ya C-suite, tathmini baada ya tukio). Iache IMEZIMWA kwa fomu za mwitikio za L1 zenye majibu mengi — vinginevyo wasimamizi watazama kwenye barua pepe.';
$string['eval_response_subject'] = 'Jibu jipya la tathmini: {$a}';
$string['eval_response_small'] = 'Jibu jipya: {$a}';
$string['eval_response_body_plain'] = 'Jibu jipya limewasilishwa kwa tathmini "{$a->evalname}".

Mjibuji: {$a->responder}

Tazama majibu yote: {$a->url}';
$string['eval_response_body_html'] = '<p>Jibu jipya limewasilishwa kwa tathmini <strong>{$a->evalname}</strong>.</p><p>Mjibuji: {$a->responder}</p><p><a href="{$a->url}">Tazama majibu yote ya tathmini hii</a></p>';
$string['eval_response_responder_anonymous'] = '(bila jina)';
$string['eval_response_responder_unknown'] = '(haijulikani — akaunti ya mtumiaji imeondolewa)';
$string['answer_required'] = 'Jibu la lazima halipo: {$a}';
$string['invalid_rating'] = 'Ukadiriaji lazima uwe 1-5: {$a}';
$string['invalid_nps'] = 'Alama ya NPS lazima iwe 0-10: {$a}';
$string['invalid_yesno'] = 'Jibu lazima liwe Ndiyo au Hapana: {$a}';
$string['invalid_multichoice'] = 'Chaguo ulilochagua si sahihi kwa: {$a}';
$string['please_answer_required'] = 'Tafadhali jibu maswali yote ya lazima kabla ya kuwasilisha.';
$string['response_submitted'] = 'Asante — jibu lako limerekodiwa.';
$string['privacy:metadata:responses'] = 'Majibu ya tathmini yaliyowasilishwa na watumiaji (safu moja kwa kila uwasilishaji).';
$string['privacy:metadata:responses:evaluationid'] = 'ID ya fomu ya tathmini inayojibiwa.';
$string['privacy:metadata:responses:userid'] = 'ID ya mtumiaji aliyewasilisha jibu (0 kama bila jina).';
$string['privacy:metadata:responses:response_data'] = 'Majibu yaliyosimbwa kwa JSON (questionid → jibu).';
$string['privacy:metadata:responses:timesubmitted'] = 'Muda wa kuwasilisha.';
$string['privacy:metadata:triggers'] = 'Rekodi kwamba tathmini iliwekwa kwenye foleni kwa ajili yako.';
$string['privacy:metadata:triggers:userid'] = 'ID ya mtumiaji ambaye tathmini iliwekwa kwenye foleni kwa ajili yake.';
$string['privacy:metadata:triggers:evaluationid'] = 'Tathmini ipi iliwekwa kwenye foleni.';
$string['privacy:metadata:triggers:trigger_event'] = 'Kilichosababisha iwekwe kwenye foleni.';
$string['privacy:metadata:triggers:status'] = 'Kama tayari imeanzishwa.';
$string['privacy:metadata:triggers:timefired'] = 'Lini ilianzishwa.';
$string['privacy:metadata:assign'] = 'Tathmini uliyopangiwa, na nani aliyekupangia.';
$string['privacy:metadata:assign:userid'] = 'ID ya mtumiaji aliyepangiwa tathmini.';
$string['privacy:metadata:assign:evaluationid'] = 'Tathmini ipi ilipangwa.';
$string['privacy:metadata:assign:trigger_event'] = 'Kilichosababisha upangaji.';
$string['privacy:metadata:assign:status'] = 'Kama umejibu.';
$string['privacy:metadata:assign:assigned_by_userid'] = 'ID ya mtumiaji aliyefanya upangaji.';
$string['privacy:metadata:assign:due_at'] = 'Lini jibu linatakiwa.';
$string['privacy:metadata:assign:responded_at'] = 'Lini ulijibu.';
$string['privacy:metadata:responses:subject_userid'] = 'Fomu za meneja: ID ya mwanatimu ambaye jibu linamhusu.';
$string['error_imported_form_read_only'] = 'Tathmini hii imeletwa kutoka kwenye mfumo wa awali na ni ya kusoma tu: ni kumbukumbu ya kilichoulizwa na kujibiwa. Ili kuuliza maswali hayo hayo tena, ihamishe kama kiolezo kisha unda tathmini mpya kutoka kwake.';
$string['error_imported_template_read_only'] = 'Kiolezo hiki kimeletwa kutoka kwenye mfumo wa awali na hakiwezi kufutwa. Ili kukitumia tena, unda tathmini mpya kutoka kwake.';
$string['my_evaluations_title'] = 'Tathmini zangu';
$string['my_evaluations_intro'] = 'Tathmini ulizoombwa kukamilisha au ulizokamilisha, zikiwemo zilizoletwa kutoka kwenye mfumo wa awali.';
$string['my_evaluations_col_evaluation'] = 'Tathmini';
$string['my_evaluations_col_status'] = 'Hali';
$string['my_evaluations_col_date'] = 'Tarehe';
$string['my_evaluations_date_submitted'] = 'Imewasilishwa';
$string['my_evaluations_date_due'] = 'Tarehe ya mwisho';
$string['my_evaluations_date_assigned'] = 'Imepangwa';
$string['my_evaluations_status_responded'] = 'Umejibu';
$string['my_evaluations_status_assigned'] = 'Inakusubiri';
$string['my_evaluations_status_expired'] = 'Imefungwa bila jibu';
$string['my_evaluations_imported'] = 'Kutoka kwenye mfumo wa awali';
$string['my_evaluations_anonymous_note'] = 'Tathmini bila majina: jina lako halionyeshwi pamoja na majibu yako kwenye matokeo yake.';
$string['my_evaluations_none'] = 'Huna tathmini zozote kwenye kumbukumbu.';
$string['my_evaluations_unavailable'] = 'Historia yako ya tathmini bado haipatikani.';
$string['responses_col_subject'] = 'Anayehusika';
$string['responses_numeric_average'] = 'wastani';
$string['responses_numeric_lowest_highest'] = 'Chini kabisa {$a->lowest}, juu kabisa {$a->highest}';
$string['responses_numeric_range'] = 'Kiwango kinachoruhusiwa {$a->min} hadi {$a->max}';
$string['responses_multi_summary'] = 'Machaguo {$a->picks} kutoka kwa wajibuji {$a->respondents} (wastani wa {$a->avg} kwa kila mjibuji)';
$string['responses_multi_share_note'] = 'Mtu mmoja anaweza kuweka tiki kwenye machaguo kadhaa, kwa hiyo jumla ya asilimia inaweza kuzidi 100%.';
$string['responses_question_no_answers'] = 'Bado hakuna majibu kwa swali hili.';
$string['response_detail_numeric_avg'] = 'Wastani wa majibu yote: {$a}';
$string['imported_badge'] = 'Imeingizwa';
$string['imported_readonly_notice'] = 'Imeletwa kutoka kwenye mfumo wa awali. Tathmini hii ni ya kusoma tu: ni kumbukumbu ya kilichoulizwa na kujibiwa. Ili kuuliza maswali hayo hayo tena, ihamishe kama kiolezo kisha unda tathmini mpya kutoka kwake.';
$string['questiontype_multichoice_multi_short'] = 'Chaguo-nyingi (kadhaa)';
$string['questiontype_numeric_short'] = 'Namba';
$string['questions_numeric_atleast'] = 'Kiwango kinachoruhusiwa: angalau {$a}';
$string['questions_numeric_atmost'] = 'Kiwango kinachoruhusiwa: si zaidi ya {$a}';
$string['invalidresponse'] = 'Jibu halikupatikana.';
$string['responses_subject_deleted'] = '(mtumiaji aliyefutwa)';
$string['responses_numeric_ignored'] = 'Majibu yaliyohifadhiwa ambayo si namba yameachwa nje ya takwimu hizi: {$a}.';
$string['responses_individual_link'] = 'Majibu ya mtu mmoja mmoja';
$string['response_drilldown_unavailable'] = 'Majibu ya mtu mmoja mmoja bado hayapatikani.';
$string['responses_individual_protected_note'] = 'Majibu ya mtu mmoja mmoja hayapatikani kwa tathmini hii kwa sababu majibu yake hayana majina, kwa ujumla au kwa sehemu. Jumla zilizo hapa chini bado zinaonyeshwa.';
$string['response_list_protected'] = 'Majibu ya tathmini hii hayana majina, kwa ujumla au kwa sehemu, kwa hiyo majibu ya mtu mmoja mmoja hayapatikani: jumla pekee ndizo zinaonyeshwa. Jibu moja, pamoja na siku lilipoingia na kozi, programu au darasa lilikotoka, linaweza kuhusishwa na mtu aliyelitoa.';
$string['response_list_back_to_totals'] = 'Rudi kwenye jumla';
