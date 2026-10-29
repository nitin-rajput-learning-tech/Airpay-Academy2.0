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
 * Swahili strings for local_sentientia_recommendations.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_recommendations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Mapendekezo ya kozi ya AI';
$string['sentientia_recommendations:view'] = 'Tazama mapendekezo ya kozi ya AI kwenye dashibodi';
$string['sentientia_recommendations:generate'] = 'Tengeneza mapendekezo ya kozi ya AI kwa mwanafunzi';
$string['sentientia_recommendations:manage_all'] = 'Simamia historia ya mapendekezo ya AI ya wanafunzi wote';
$string['privacy:metadata'] = 'Plugin ya Mapendekezo ya AI ya Sentientia LMS huhifadhi mapendekezo ya kozi yaliyobinafsishwa pamoja na sababu fupi kwa kila moja. Flag ya API halisi (live) ikiwa ON, wasifu wa mwanafunzi usio na utambulisho (jukumu, tenant, ID za kozi alizokamilisha, lebo za ujuzi) hutumwa kwa Anthropic kwa ajili ya uchakataji.';
$string['privacy:metadata:rec'] = 'Mapendekezo ya kozi yaliyotengenezwa na AI kwa kila mwanafunzi.';
$string['privacy:metadata:rec:userid'] = 'Mwanafunzi ambaye pendekezo ni lake.';
$string['privacy:metadata:rec:courseid'] = 'Kozi iliyopendekezwa.';
$string['privacy:metadata:rec:score'] = 'Alama ya uhakika ambayo Claude ameipa pendekezo hili.';
$string['privacy:metadata:rec:reasoning'] = 'Maelezo mafupi ya kwa nini kozi hii ni hatua inayofaa inayofuata.';
$string['privacy:metadata:rec:tokens'] = 'Matumizi ya tokeni za Anthropic API kwa ajili ya kuhesabu gharama.';
$string['privacy:metadata:rec:status'] = 'Hatua ya pendekezo katika mzunguko wake: linatumika, limeondolewa, amesajiliwa, muda umeisha.';
$string['privacy:metadata:rec:generated_at'] = 'Wakati pendekezo lilipotengenezwa.';
$string['privacy:metadata:rec:timecreated'] = 'Wakati pendekezo lilipoundwa.';
$string['privacy:metadata:rec:timemodified'] = 'Wakati pendekezo lilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — flag ya API halisi (live) ikiwa imewashwa, wasifu wa mwanafunzi usio na utambulisho hutumwa kwa Anthropic ili kutengeneza mapendekezo. Kila ombi la API linahitaji hatua ya uthibitisho kwanza.';
$string['privacy:metadata:anthropic:profile_role'] = 'Lebo ya jukumu la mwanafunzi (k.m. mwanafunzi, meneja).';
$string['privacy:metadata:anthropic:profile_completed'] = 'ID za kozi ambazo mwanafunzi amekamilisha.';
$string['privacy:metadata:anthropic:profile_skills'] = 'Lebo za ujuzi zilizopo kwa sasa kwenye rekodi ya mwanafunzi.';
$string['privacy:metadata:anthropic:model'] = 'Kitambulisho cha modeli ya Anthropic kilichotumika.';
$string['nav_generate'] = 'Tengeneza mapendekezo ya AI';
$string['generate_page_title'] = 'Tengeneza mapendekezo ya kozi ya AI';
$string['generate_page_heading'] = 'Tengeneza mapendekezo ya kozi yaliyobinafsishwa';
$string['generate_intro'] = 'Chagua mwanafunzi na uombe fungu la mapendekezo ya kozi yaliyobinafsishwa kutoka kwa Anthropic Claude. Kila pendekezo linakuja na sababu fupi ambayo mwanafunzi anaweza kuisoma kwenye dashibodi yake.';
$string['generate_form_targetuser'] = 'Mwanafunzi lengwa (ID ya mtumiaji)';
$string['generate_form_targetuser_help'] = 'ID ya mtumiaji ya mwanafunzi ambaye fungu hili litatengenezwa kwa ajili yake. Kwa chaguo-msingi ni ID yako mwenyewe.';
$string['generate_form_num'] = 'Idadi ya mapendekezo';
$string['generate_form_num_help'] = 'Kati ya 1 na {$a}. Claude anaweza kurudisha mapendekezo machache kuliko hayo ikiwa katalogi ya kozi zinazoweza kupendekezwa ni ndogo mno.';
$string['generate_form_model'] = 'Modeli';
$string['generate_confirm_label'] = 'Ninathibitisha kuwa ninataka kutuma ombi kwa Anthropic nikitumia wasifu huu wa mwanafunzi';
$string['generate_confirm_help'] = 'Hii ndiyo hatua ya uthibitisho kwa kila ombi. Flag ya API halisi (live) ikiwa ON, ombi hili litagharimu pesa halisi kulingana na ukubwa wa wasifu + katalogi. Ondoa tiki ili kusitisha.';
$string['generate_submit'] = 'Tengeneza mapendekezo';
$string['generate_cancel'] = 'Ghairi';
$string['generate_success'] = 'Fungu la mapendekezo limetengenezwa. ID ya fungu: {$a->batchid}. Mapendekezo {$a->count} yametengenezwa katika hali ya {$a->mode}. Tokeni: {$a->tokens_in} ndani / {$a->tokens_out} nje.';
$string['mode_mock_badge'] = 'HALI YA MAJARIBIO — hakuna ombi la API halisi (weka sentientia.recommendations.live_api = ON ili kutengeneza kwa kweli)';
$string['mode_live_badge'] = 'API HALISI — ombi kwa Anthropic linatozwa kwenye akaunti yako';
$string['mode_disabled_badge'] = 'Kipengele kimezimwa — weka sentientia.recommendations.enabled = ON kwenye Switchboard';
$string['mode_no_apikey_badge'] = 'Hakuna ufunguo wa API uliowekwa — weka local_sentientia_recommendations | api_key kwenye Usimamizi wa tovuti';
$string['err_feature_off'] = 'Kipengele cha Mapendekezo ya AI kimezimwa. Mwombe msimamizi awashe sentientia.recommendations.enabled.';
$string['err_no_capability'] = 'Huna ruhusa ya kutengeneza mapendekezo ya AI.';
$string['err_invalid_count'] = 'Idadi ya mapendekezo lazima iwe kati ya {$a->min} na {$a->max}.';
$string['err_confirm_required'] = 'Lazima uweke tiki kwenye kisanduku cha uthibitisho ili kuita API.';
$string['err_api_key_not_set'] = 'Hakuna ufunguo wa Anthropic API uliowekwa. Haiwezekani kutuma ombi halisi.';
$string['err_api_failed'] = 'Ombi kwa Anthropic API limeshindikana: {$a}';
$string['err_parser_zero'] = 'Claude amejibu lakini hakuna mapendekezo yanayotumika yaliyoweza kuchakatwa.';
$string['err_cost_cap_reached'] = 'Akaunti yako ya mteja imetumia tokeni {$a->used} leo (kikomo laini cha kila siku ni {$a->cap}). Jaribu tena kesho au mwombe msimamizi aongeze kikomo.';
$string['err_user_not_found'] = 'Mwanafunzi lengwa hajapatikana au amefutwa.';
$string['err_candidates_empty'] = 'Hakuna kozi zinazoweza kupendekezwa kwa mwanafunzi huyu.';
$string['err_profile_invalid'] = 'Wasifu wa mwanafunzi si sahihi.';
$string['err_profile_contains_pii'] = 'Wasifu wa mwanafunzi una mifumo ya PII (Aadhaar au PAN). Ondoa taarifa hizo kisha uwasilishe tena.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Vitambulisho vya Anthropic Claude API. Ufunguo hutumika tu pale sentientia.recommendations.enabled na sentientia.recommendations.live_api zote zikiwa ON.';
$string['setting_api_key'] = 'Ufunguo wa Anthropic API';
$string['setting_api_key_desc'] = 'Bandika ufunguo wa API kutoka console.anthropic.com. Huhifadhiwa ukiwa umesimbwa. KAMWE usiweke thamani hii kwenye source control.';
$string['setting_default_model'] = 'Modeli chaguo-msingi';
$string['setting_default_model_desc'] = 'Modeli ya Anthropic inayotumika pale generator isipobainisha modeli. Inayopendekezwa: claude-sonnet-4-6.';
$string['setting_max_output_tokens'] = 'Idadi ya juu ya tokeni za output';
$string['setting_max_output_tokens_desc'] = 'Kikomo madhubuti cha max_tokens za Anthropic kwa kila ombi. Chaguo-msingi ni 2048.';
$string['settings_heading_limits'] = 'Vikomo na quota';
$string['settings_heading_limits_desc'] = 'Vikomo kwa kila fungu na kwa kila mteja ili gharama zitabirike.';
$string['setting_max_recommendations'] = 'Idadi ya juu ya mapendekezo kwa kila fungu';
$string['setting_max_recommendations_desc'] = 'Kikomo cha juu cha mapendekezo kwa kila utengenezaji. Chaguo-msingi ni 5.';
$string['setting_max_history_items'] = 'Idadi ya juu ya vipengele vya historia ya ukamilishaji';
$string['setting_max_history_items_desc'] = 'Idadi ya kozi alizokamilisha mwanafunzi hivi karibuni zitakazowekwa kwenye prompt. Chaguo-msingi ni 50.';
$string['setting_daily_cost_cap'] = 'Kikomo cha tokeni kwa siku kwa kila mteja';
$string['setting_daily_cost_cap_desc'] = 'Kikomo laini cha tokeni (input + output) zinazotumiwa na utengenezaji wa mapendekezo kwa kila mteja kwa siku. Kikizidiwa, generate.php hurudisha hitilafu hadi saa sita usiku.';
$string['settings_heading_prompt'] = 'Usanidi wa prompt';
$string['settings_heading_prompt_desc'] = 'Matoleo ya prompt yanatunzwa ndani ya code (prompt_builder::VERSION). Sehemu hii ya maandishi huru ni kwa taarifa tu.';
$string['setting_prompt_template_note'] = 'Dokezo la kiolezo cha prompt';
$string['setting_prompt_template_note_desc'] = 'Madokezo huru kuhusu kiolezo cha sasa cha prompt — huonyeshwa kwenye kiolesura cha msimamizi pekee. HAYABATILISHI prompt_builder.';
$string['block_title'] = 'Mapendekezo kwako';
$string['block_empty'] = 'Bado huna mapendekezo. Angalia tena baadaye.';
$string['block_disabled'] = 'Mapendekezo ya AI yamezimwa kwa sasa.';
$string['block_view_all'] = 'Tazama yote';
$string['block_dismiss'] = 'Sivutiwi';
$string['block_why'] = 'Kwa nini hili?';
$string['block_score'] = 'Ulinganifu';
$string['tokens_used_today'] = 'Tokeni zilizotumika leo (kwa mteja mzima): {$a->used} / {$a->cap}';
$string['recommendation_card'] = 'Pendekezo #{$a}';
