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
 * Swahili strings for local_sentientia_skillsai.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_skillsai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Akili ya Ujuzi (AI)';
$string['sentientia_skillsai:extract'] = 'Bainisha ujuzi kwa AI';
$string['sentientia_skillsai:review'] = 'Kagua na uidhinishe ujuzi uliobainishwa';
$string['sentientia_skillsai:manage_taxonomy'] = 'Simamia taksonomia ya ujuzi na uhusiano wake na athari za biashara';
$string['sentientia_skillsai:viewgaps'] = 'Tazama mipasho ya mapengo ya ujuzi';
$string['sentientia_skillsai:manage_all'] = 'Simamia kazi zote za ubainishaji za wamiliki wote na tenant wote';
$string['nav_extract'] = 'Bainisha ujuzi';
$string['nav_taxonomy'] = 'Taksonomia ya ujuzi';
$string['nav_gaps'] = 'Mapengo ya ujuzi';
$string['queue_page_title'] = 'Foleni ya ubainishaji wa ujuzi';
$string['queue_page_heading'] = 'Foleni ya ubainishaji wa ujuzi';
$string['queue_empty'] = 'Bado hakuna kazi za ubainishaji. Bofya "Bainisha ujuzi" ili kuanza.';
$string['col_title'] = 'Kichwa';
$string['col_sourcekind'] = 'Chanzo';
$string['col_status'] = 'Hali';
$string['col_extracted'] = 'Ujuzi';
$string['col_actions'] = 'Vitendo';
$string['action_review'] = 'Kagua';
$string['sourcekind_scorm'] = 'Nakala ya SCORM';
$string['sourcekind_narration'] = 'Maandishi ya simulizi';
$string['sourcekind_sop'] = 'Sehemu ya SOP';
$string['sourcekind_manual'] = 'Maandishi yaliyobandikwa';
$string['jobstatus_pending'] = 'Inasubiri';
$string['jobstatus_extracted'] = 'Inasubiri ukaguzi';
$string['jobstatus_reviewed'] = 'Imekaguliwa';
$string['jobstatus_failed'] = 'Imeshindwa';
$string['extract_page_title'] = 'Bainisha ujuzi kwa AI';
$string['extract_page_heading'] = 'Bainisha ujuzi kwa AI';
$string['extract_intro'] = 'Bandika nakala ya kozi/SCORM, sehemu ya SOP au maandishi ya simulizi. Claude anapendekeza ujuzi unaofundishwa humo; kila pendekezo lazima likaguliwe na mtu kabla ya kuingia kwenye taksonomia.';
$string['extract_form_title'] = 'Kichwa cha ubainishaji';
$string['extract_form_course'] = 'Kozi husika (si lazima)';
$string['extract_form_course_none'] = 'Haijaunganishwa na kozi';
$string['extract_form_sourcekind'] = 'Aina ya chanzo';
$string['extract_form_language'] = 'Lugha';
$string['extract_form_language_en'] = 'Kiingereza';
$string['extract_form_language_hi'] = 'Kihindi (हिन्दी)';
$string['extract_form_source'] = 'Maudhui ya chanzo';
$string['extract_form_source_help'] = 'Hadi maneno {$a}. Usibandike taarifa zinazomtambulisha mtu (majina ya wafanyakazi, ID, mishahara, data za wateja).';
$string['extract_prompt_preview_summary'] = 'Hakiki prompt atakayoiona Claude (toleo: {$a->version})';
$string['extract_prompt_preview_help'] = 'Hii ndiyo system prompt kamili inayoongoza modeli. Ipitie kabla ya kuthibitisha.';
$string['extract_prompt_preview_custom_badge'] = 'Kiolezo cha prompt maalum kwa mteja kinatumika';
$string['extract_confirm_label'] = 'Ninathibitisha kuwa chanzo hiki hakina data binafsi na ninaidhinisha ubainishaji kwa AI.';
$string['extract_confirm_help'] = 'Ombi la API la moja kwa moja linagharimu pesa kwa kila token. Hali ya mock (chaguo-msingi) ni bure na haihitaji uthibitisho ili iwe salama, lakini uthibitisho unahitajika kila wakati.';
$string['extract_submit'] = 'Bainisha ujuzi';
$string['extract_cancel'] = 'Ghairi';
$string['mode_disabled_badge'] = 'Akili ya Ujuzi imezimwa (flag ya kipengele OFF).';
$string['mode_mock_badge'] = 'Hali ya mock — hakuna ombi litakalotumwa kwa Anthropic (flag ya API ya moja kwa moja OFF). Gharama sifuri.';
$string['mode_no_apikey_badge'] = 'Flag ya API ya moja kwa moja iko ON lakini hakuna ufunguo wa API uliowekwa — maombi yatashindwa hadi ufunguo uwekwe.';
$string['mode_live_badge'] = 'Hali ya moja kwa moja — ubainishaji utatuma POST kwa Anthropic na utaleta gharama ya token.';
$string['tokens_used_today'] = 'Token zilizotumika leo: {$a->used} kati ya {$a->cap}.';
$string['source_word_count'] = 'Maneno {$a}';
$string['review_page_title'] = 'Kagua ujuzi uliobainishwa';
$string['review_page_heading'] = 'Kagua ujuzi uliobainishwa';
$string['review_intro'] = 'Idhinisha, hariri au kataa kila ujuzi uliopendekezwa. Kuidhinisha au kuhariri kunauingiza kwenye taksonomia rasmi ya tenant. Hakuna kinachokuwa rasmi bila uamuzi wako.';
$string['review_saved'] = 'Uamuzi wa ujuzi umehifadhiwa.';
$string['review_finalised'] = 'Ukaguzi umekamilishwa.';
$string['review_finalise_submit'] = 'Kamilisha ukaguzi';
$string['review_job_failed'] = 'Ubainishaji huu umeshindwa: {$a}';
$string['back_to_queue'] = 'Rudi kwenye foleni';
$string['cand_name'] = 'Jina la ujuzi';
$string['cand_description'] = 'Maelezo';
$string['cand_category'] = 'Kundi';
$string['cand_level'] = 'Kiwango cha kufundishwa';
$string['cand_note'] = 'Dokezo la mkaguzi';
$string['cand_confidence'] = 'Uhakika {$a}';
$string['cand_promoted'] = 'Iko kwenye taksonomia';
$string['candstatus_proposed'] = 'Imependekezwa';
$string['candstatus_approved'] = 'Imeidhinishwa';
$string['candstatus_edited'] = 'Imehaririwa';
$string['candstatus_rejected'] = 'Imekataliwa';
$string['verdict_approve'] = 'Idhinisha';
$string['verdict_edit'] = 'Hifadhi mabadiliko';
$string['verdict_reject'] = 'Kataa';
$string['nav_taxonomy_short'] = 'Taksonomia';
$string['taxonomy_page_title'] = 'Taksonomia ya ujuzi';
$string['taxonomy_page_heading'] = 'Taksonomia ya ujuzi na athari kwa biashara';
$string['taxonomy_intro'] = 'Ujuzi rasmi ulioidhinishwa na watu kwa tenant huyu. Unganisha kila ujuzi na vipimo vya biashara unavyoathiri ili mapengo ya ujuzi yapangwe kwa kipaumbele cha biashara.';
$string['taxonomy_empty'] = 'Bado hakuna ujuzi ulioidhinishwa. Idhinisha ujuzi uliobainishwa ili kujenga taksonomia.';
$string['impact_flag_off'] = 'Uhusiano wa athari kwa biashara umezimwa (flag ya kipengele OFF). Taksonomia ni ya kusoma tu hadi kipengele hiki kiwashwe.';
$string['impact_metric'] = 'Kipimo cha biashara';
$string['impact_detail'] = 'Jinsi unavyoathiri kipimo';
$string['impact_weight'] = 'Kipaumbele';
$string['impact_add'] = 'Ongeza';
$string['impact_added'] = 'Uhusiano wa athari kwa biashara umeongezwa.';
$string['impact_weight_badge'] = 'Kipaumbele {$a}';
$string['gaps_page_title'] = 'Mapengo ya ujuzi';
$string['gaps_page_heading'] = 'Mapengo ya ujuzi';
$string['gaps_summary_heading'] = 'Muhtasari wa mapengo ya ujuzi wa tenant';
$string['gaps_summary_intro'] = 'Ujuzi ambao wanafunzi wako chini ya kiwango kinachohitajika na nafasi zao za kazi, umepangwa kwanza kwa kipaumbele cha biashara kisha kwa idadi ya watu walioathirika.';
$string['gaps_summary_none'] = 'Hakuna mapengo ya ujuzi yaliyo wazi. Jenga upya mpasho wa mwanafunzi ili kujaza mwonekano huu.';
$string['gaps_user_heading'] = 'Mapengo ya ujuzi ya {$a}';
$string['gaps_user_none'] = 'Mwanafunzi huyu hana mapengo ya ujuzi yaliyo wazi — kila hitaji la nafasi yake limetimizwa.';
$string['gaps_rebuild_user'] = 'Jenga upya mpasho wa mapengo wa mwanafunzi huyu';
$string['gaps_rebuilt'] = 'Mpasho wa mapengo umejengwa upya: mapengo {$a} yaliyo wazi.';
$string['gaps_back_summary'] = 'Rudi kwenye muhtasari';
$string['col_skill'] = 'Ujuzi';
$string['col_required'] = 'Kinachohitajika';
$string['col_held'] = 'Kilichopo';
$string['col_gap'] = 'Pengo';
$string['col_impact'] = 'Athari';
$string['col_affected'] = 'Wanafunzi walioathirika';
$string['col_maxgap'] = 'Pengo kubwa zaidi';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Weka ufunguo wa Anthropic API + modeli. Ufunguo unatumika tu wakati flag ya API ya moja kwa moja iko ON; katika hali ya mock ufunguo hauhitajiki.';
$string['setting_api_key'] = 'Ufunguo wa API';
$string['setting_api_key_desc'] = 'Ufunguo wako wa Anthropic API. Huhifadhiwa ukiwa umefichwa. Hauwekwi kamwe kwenye source control. Acha wazi ili kubaki kwenye hali ya mock.';
$string['setting_default_model'] = 'Modeli chaguo-msingi';
$string['setting_default_model_desc'] = 'Kitambulisho cha modeli ya Anthropic kinachotumika kwa ubainishaji (k.m. claude-sonnet-4-6).';
$string['settings_heading_limits'] = 'Mipaka';
$string['settings_heading_limits_desc'] = 'Vizuizi vya kila ombi na kila siku vinavyozuia gharama kupanda bila udhibiti.';
$string['setting_max_skills'] = 'Idadi ya juu ya ujuzi kwa kila ubainishaji';
$string['setting_max_skills_desc'] = 'Idadi kubwa zaidi ya mapendekezo ya ujuzi ambayo ubainishaji mmoja utaomba.';
$string['setting_daily_token_cap'] = 'Kikomo cha token kwa siku (kwa kila mtumiaji)';
$string['setting_daily_token_cap_desc'] = 'Mwandishi akishatumia token hizi leo, ubainishaji zaidi unazuiwa hadi kesho.';
$string['setting_max_source_words'] = 'Idadi ya juu ya maneno ya chanzo';
$string['setting_max_source_words_desc'] = 'Maandishi marefu zaidi ya chanzo yanakataliwa wakati wa kubandika.';
$string['settings_heading_customer_prompts'] = 'Kiolezo cha prompt kwa kila mteja';
$string['settings_heading_customer_prompts_desc'] = 'Mbadala wa hiari wa kiini cha system prompt, unaotumika kwa mteja huyu. Acha wazi ili kutumia prompt ya msingi iliyojengwa ndani.';
$string['setting_customer_1_prompt_template'] = 'Kiolezo cha prompt ya ubainishaji ya Airpay';
$string['setting_customer_1_prompt_template_desc'] = 'Kikiwekwa, maandishi haya yanachukua nafasi ya system prompt iliyojengwa ndani kwa ubainishaji wa Airpay. Kifungashio cha ujumbe wa mtumiaji bado kinafuata lugha iliyochaguliwa.';
$string['task_rebuild_gap_feed'] = 'Jenga upya mipasho ya mapengo ya ujuzi';
$string['err_feature_off'] = 'Akili ya Ujuzi haijawashwa kwenye tovuti hii.';
$string['err_gap_feature_off'] = 'Injini ya mapengo ya ujuzi haijawashwa kwenye tovuti hii.';
$string['err_source_empty'] = 'Maudhui ya chanzo hayawezi kuwa tupu.';
$string['err_source_too_long'] = 'Maudhui ya chanzo yamezidi kikomo cha juu cha maneno.';
$string['err_source_contains_pii'] = 'Maudhui ya chanzo yanaonekana kuwa na taarifa zinazomtambulisha mtu (k.m. namba ya Aadhaar au PAN). Ziondoe kabla ya kubainisha ujuzi.';
$string['err_confirm_required'] = 'Lazima uweke tiki kwenye kisanduku cha uthibitisho kabla ya kubainisha.';
$string['err_token_cap_reached'] = 'Kikomo cha token cha siku kimefikiwa ({$a->used} kati ya {$a->cap}). Jaribu tena kesho.';
$string['err_api_failed'] = 'Ombi la ubainishaji limeshindwa: {$a}';
$string['err_job_not_found'] = 'Kazi hiyo ya ubainishaji haipo au huna ruhusa ya kuifikia.';
$string['err_candidate_not_found'] = 'Pendekezo hilo la ujuzi si sehemu ya kazi hii ya ubainishaji.';
$string['err_candidate_not_approved'] = 'Ni mapendekezo yaliyoidhinishwa au kuhaririwa pekee yanayoweza kuingizwa kwenye taksonomia.';
$string['privacy:metadata:job'] = 'Kazi za ubainishaji wa ujuzi zilizoombwa na kukaguliwa na watumiaji.';
$string['privacy:metadata:job:ownerid'] = 'Mtumiaji aliyeomba ubainishaji.';
$string['privacy:metadata:job:reviewed_by'] = 'Mtumiaji aliyekagua ubainishaji.';
$string['privacy:metadata:job:title'] = 'Kichwa cha kazi ya ubainishaji.';
$string['privacy:metadata:job:status'] = 'Hali ya kazi ya ubainishaji.';
$string['privacy:metadata:job:timecreated'] = 'Wakati ubainishaji ulipoundwa.';
$string['privacy:metadata:gap'] = 'Safu za mpasho wa mapengo ya ujuzi kwa kila mtumiaji.';
$string['privacy:metadata:gap:userid'] = 'Mtumiaji mwenye pengo hili la ujuzi.';
$string['privacy:metadata:gap:skillid'] = 'Ujuzi wenye pengo.';
$string['privacy:metadata:gap:required_level'] = 'Kiwango kinachohitajika na nafasi ya kazi.';
$string['privacy:metadata:gap:held_level'] = 'Kiwango alicho nacho mtumiaji kwa sasa.';
$string['privacy:metadata:gap:timecreated'] = 'Wakati safu ya pengo ilipokokotolewa.';
$string['privacy:metadata:anthropic'] = 'Maandishi ya chanzo ya mafunzo yanayotumwa kwa Anthropic Claude API kwa ajili ya kubainisha ujuzi (tu wakati flag ya API ya moja kwa moja iko ON).';
$string['privacy:metadata:anthropic:sourcetext'] = 'Maandishi ya nyenzo za mafunzo yaliyowasilishwa kwa ubainishaji (hukaguliwa kama yana data binafsi wakati wa kubandika).';
$string['privacy:metadata:anthropic:model'] = 'Modeli ya Anthropic iliyotumika.';
$string['privacy:export:jobs'] = 'Kazi za ubainishaji';
$string['privacy:export:gaps'] = 'Mapengo ya ujuzi';
$string['privacy:metadata:skai_taxonomy'] = 'Taksonomia ya ujuzi inayoshirikiwa, ikiwemo nani aliidhinisha kila kipengele.';
$string['privacy:metadata:skai_taxonomy:approved_by'] = 'ID ya mtumiaji aliyeidhinisha ujuzi.';
$string['privacy:metadata:skai_taxonomy:name'] = 'Jina la ujuzi.';
$string['privacy:metadata:skai_impact'] = 'Vipimo vya athari kwa biashara vilivyounganishwa na ujuzi, ikiwemo mwandishi wake.';
$string['privacy:metadata:skai_impact:createdby'] = 'ID ya mtumiaji aliyeunda kipimo.';
$string['privacy:metadata:skai_impact:metric_name'] = 'Jina la kipimo.';
