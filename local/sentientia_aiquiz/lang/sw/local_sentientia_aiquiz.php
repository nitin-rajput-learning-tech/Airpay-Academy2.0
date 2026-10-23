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
 * Swahili strings for local_sentientia_aiquiz.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_aiquiz
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Uundaji wa Quiz kwa AI';
$string['sentientia_aiquiz:generate'] = 'Tengeneza maswali ya quiz yaliyoandaliwa na AI';
$string['sentientia_aiquiz:review'] = 'Kagua maswali ya quiz yaliyoandaliwa na AI';
$string['sentientia_aiquiz:manage_all'] = 'Simamia rasimu za quiz za AI za wamiliki wote';
$string['privacy:metadata'] = 'Plugin ya Sentientia LMS AI Quiz huhifadhi rasimu za quiz na maandishi chanzo yaliyotumika kuzitengeneza. Flag ya live API ikiwa ON, maandishi chanzo hutumwa kwa Anthropic ili yachakatwe.';
$string['privacy:metadata:draft'] = 'Rasimu za quiz za AI zilizoundwa na watumiaji.';
$string['privacy:metadata:draft:ownerid'] = 'Mtumiaji aliyeunda rasimu.';
$string['privacy:metadata:draft:sourcetext'] = 'Maandishi chanzo, kama yalivyo, ambayo mtumiaji aliyatoa kwa ajili ya uundaji.';
$string['privacy:metadata:draft:title'] = 'Kichwa cha rasimu kilichotolewa na mtumiaji.';
$string['privacy:metadata:draft:tokens'] = 'Matumizi ya tokeni za Anthropic API kwa ajili ya hesabu ya gharama.';
$string['privacy:metadata:draft:reviewed_by'] = 'Mtumiaji aliyekagua rasimu.';
$string['privacy:metadata:draft:reviewed_at'] = 'Wakati rasimu ilipokaguliwa.';
$string['privacy:metadata:draft:timecreated'] = 'Wakati rasimu ilipoundwa.';
$string['privacy:metadata:draft:timemodified'] = 'Wakati rasimu ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:question'] = 'Maswali binafsi yaliyotengenezwa na AI yaliyo ndani ya rasimu.';
$string['privacy:metadata:question:draftid'] = 'Rasimu kuu ambayo swali hili ni sehemu yake.';
$string['privacy:metadata:question:qtext'] = 'Maandishi ya kiini cha swali.';
$string['privacy:metadata:question:qoptions'] = 'Machaguo ya maswali ya chaguo-nyingi yaliyosimbwa kwa JSON.';
$string['privacy:metadata:question:reviewer_note'] = 'Maoni ya mkaguzi kuhusu swali.';
$string['privacy:metadata:question:timecreated'] = 'Wakati swali lilipotengenezwa.';
$string['privacy:metadata:question:timemodified'] = 'Wakati swali lilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — flag ya live API ikiwashwa, maandishi chanzo hutumwa kwa Anthropic ili kutengeneza quiz. Kila mwito wa API unahitaji uthibitisho kwanza.';
$string['privacy:metadata:anthropic:sourcetext'] = 'Maandishi chanzo yaliyotolewa na mkufunzi.';
$string['privacy:metadata:anthropic:model'] = 'Kitambulisho cha modeli ya Anthropic kilichotumika.';
$string['nav_generate'] = 'Tengeneza quiz ya AI';
$string['nav_review'] = 'Kagua rasimu za quiz za AI';
$string['generate_page_title'] = 'Tengeneza quiz ya AI';
$string['generate_page_heading'] = 'Tengeneza rasimu ya quiz kutoka maudhui ya kozi';
$string['generate_intro'] = 'Bandika maudhui chanzo (nakala ya maandishi ya SCORM, maandishi ya simulizi, sehemu ya SOP) hapa chini. Sentientia LMS huyatuma kwa Anthropic Claude ili kutengeneza rasimu ya quiz. Kila rasimu lazima ikaguliwe na mtu kabla swali lolote halijatumwa kwenye quiz ya kozi.';
$string['generate_form_title'] = 'Kichwa cha rasimu';
$string['generate_form_title_help'] = 'Lebo fupi ili uweze kuipata rasimu hii baadaye kwenye foleni ya ukaguzi.';
$string['generate_form_course'] = 'Kozi';
$string['generate_form_course_help'] = 'Kozi ambayo quiz itakayotengenezwa itakuwa sehemu yake. Chagua \\"Tovuti nzima / bado haijapangiwa\\" ili kuahirisha uchaguzi huu hadi wakati wa ukaguzi.';
$string['generate_form_course_none'] = 'Tovuti nzima / bado haijapangiwa';
$string['generate_form_source'] = 'Maudhui chanzo';
$string['generate_form_source_help'] = 'Hadi maneno {$a}. Ondoa PII yoyote (majina ya wafanyakazi, vitambulisho, data za wateja) kabla ya kubandika.';
$string['generate_form_num'] = 'Idadi ya maswali unayotaka';
$string['generate_form_num_help'] = 'Kati ya 1 na {$a}. Claude anaweza kurudisha maswali machache kuliko hayo ikiwa chanzo ni kifupi mno.';
$string['generate_form_model'] = 'Modeli';
$string['generate_form_model_help'] = 'Kitambulisho cha modeli ya Anthropic. Chaguo-msingi: claude-sonnet-4-6.';
$string['generate_confirm_label'] = 'Ninathibitisha kuwa nataka kutuma maudhui haya kwa Anthropic';
$string['generate_confirm_help'] = 'Huu ni uthibitisho unaohitajika kwa kila mwito. Flag ya live API ikiwa ON, utumaji huu utagharimu pesa halisi kulingana na urefu wa chanzo. Ondoa tiki ili kusitisha.';
$string['generate_submit'] = 'Tengeneza rasimu ya quiz';
$string['generate_cancel'] = 'Ghairi';
$string['generate_form_language'] = 'Lugha ya quiz';
$string['generate_form_language_help'] = 'Huchagua system prompt inayotumwa kwa Claude. Kiingereza hutumia prompt ya msingi ya v1. Kihindi hutumia prompt ya v2-hindi na humwomba Claude arudishe maswali kwa hati ya Devanagari.';
$string['generate_form_language_en'] = 'Kiingereza (v1)';
$string['generate_form_language_hi'] = 'हिन्दी / Kihindi (v2-hindi)';
$string['generate_prompt_preview_summary'] = 'Hakiki system prompt ambayo Claude ataiona (toleo la prompt: {$a->version}, mteja: {$a->customer})';
$string['generate_prompt_preview_help'] = 'Hii ndiyo system prompt kamili itakayotumwa kwa Anthropic ukibofya Tengeneza huku flag ya live API ikiwa ON. Mock mode hutengeneza maswali ya majaribio na haiiti API. Ikiwa kiolezo cha mteja mahususi kimesanidiwa hapa chini, kinachukua nafasi ya maudhui ya prompt ya msingi kama kilivyo.';
$string['generate_prompt_preview_custom_badge'] = 'Kiolezo maalum cha mteja kinatumika';
$string['mode_mock_badge'] = 'MOCK MODE — hakuna mwito wa live API (weka sentientia.aiquiz.live_api = ON kwa uundaji halisi)';
$string['mode_live_badge'] = 'LIVE API — mwito wa Anthropic unatozwa kwenye akaunti yako';
$string['mode_disabled_badge'] = 'Kipengele kimezimwa — weka sentientia.aiquiz.enabled = ON kwenye Switchboard';
$string['mode_no_apikey_badge'] = 'Hakuna API key iliyowekwa — weka local_sentientia_aiquiz | api_key kwenye Usimamizi wa tovuti';
$string['err_feature_off'] = 'Kipengele cha AI Quiz kimezimwa. Mwombe msimamizi awashe sentientia.aiquiz.enabled.';
$string['err_no_capability'] = 'Huna ruhusa ya kutengeneza rasimu za quiz za AI.';
$string['err_source_empty'] = 'Maudhui chanzo yanahitajika.';
$string['err_source_too_long'] = 'Maudhui chanzo yamezidi kikomo cha maneno kilichowekwa. Yapunguze kabla ya kuwasilisha.';
$string['err_source_contains_pii'] = 'Maudhui chanzo yanaonekana kuwa na PII (namba za Aadhaar au PAN). Zifiche kisha uwasilishe tena.';
$string['err_invalid_count'] = 'Idadi ya maswali lazima iwe kati ya {$a->min} na {$a->max}.';
$string['err_token_cap_reached'] = 'Akaunti yako imetumia tokeni {$a->used} leo (kikomo laini cha kila siku ni {$a->cap}). Jaribu tena kesho au mwombe admin aongeze kikomo.';
$string['err_confirm_required'] = 'Lazima uweke tiki kwenye kisanduku cha uthibitisho ili kuita API.';
$string['err_api_key_not_set'] = 'Hakuna API key ya Anthropic iliyowekwa. Haiwezekani kufanya mwito wa live.';
$string['err_api_failed'] = 'Mwito wa Anthropic API umeshindwa: {$a}';
$string['err_parser_zero'] = 'Claude amejibu lakini hakuna maswali yanayotumika yaliyoweza kusomwa. Jaribu chanzo kirefu zaidi au kilicho wazi zaidi.';
$string['review_page_title'] = 'Kagua rasimu ya quiz ya AI';
$string['review_page_heading'] = 'Kagua rasimu #{$a}';
$string['review_intro'] = 'Kagua kila swali lililotengenezwa hapa chini. Idhinisha, hariri, au kataa — maswali yaliyoidhinishwa au kuhaririwa pekee ndiyo yanaweza kutumwa kwenye quiz ya kozi.';
$string['review_no_draft'] = 'Rasimu haikupatikana au huna ruhusa ya kuiona.';
$string['review_meta_owner'] = 'Mmiliki';
$string['review_meta_course'] = 'Kozi';
$string['review_meta_model'] = 'Modeli';
$string['review_meta_prompt'] = 'Toleo la prompt';
$string['review_meta_tokens'] = 'Tokeni zilizotumika';
$string['review_meta_generated_at'] = 'Ilitengenezwa';
$string['review_meta_mode'] = 'Hali ya uundaji';
$string['review_status'] = 'Hali';
$string['review_status_pending'] = 'Inasubiri kutengenezwa';
$string['review_status_generated'] = 'Inasubiri ukaguzi';
$string['review_status_approved'] = 'Imeidhinishwa (tayari kutumwa)';
$string['review_status_pushed'] = 'Imetumwa kwenye quiz #{$a}';
$string['review_status_rejected'] = 'Imekataliwa';
$string['review_status_failed'] = 'Uundaji umeshindwa';
$string['review_question_label'] = 'Swali {$a}';
$string['review_question_answer'] = 'Jibu sahihi';
$string['review_question_explanation'] = 'Maelezo';
$string['review_action_approve'] = 'Idhinisha';
$string['review_action_edit'] = 'Hariri';
$string['review_action_reject'] = 'Kataa';
$string['review_action_save_edit'] = 'Hifadhi mabadiliko';
$string['review_action_cancel_edit'] = 'Ghairi uhariri';
$string['review_q_status_generated'] = 'Bado halijakaguliwa';
$string['review_q_status_approved'] = 'Limeidhinishwa';
$string['review_q_status_edited'] = 'Limehaririwa';
$string['review_q_status_rejected'] = 'Limekataliwa';
$string['review_finalise'] = 'Kamilisha ukaguzi';
$string['review_finalise_help'] = 'Huweka alama kuwa rasimu hii imekaguliwa kikamilifu. Hali inakuwa \\"imeidhinishwa\\" ikiwa angalau swali moja limeidhinishwa au kuhaririwa, vinginevyo \\"imekataliwa\\".';
$string['review_push_to_quiz'] = 'Tuma maswali yaliyoidhinishwa kwenye quiz ya kozi';
$string['review_push_disabled'] = 'Kutuma kwenye mod_quiz kunategemea sentientia.aiquiz.auto_push (chaguo-msingi OFF).';
$string['review_push_success'] = 'Maswali yaliyoidhinishwa yametumwa kwenye quiz #{$a->quizid} (maswali {$a->count}).';
$string['review_no_questions'] = 'Hakuna maswali yaliyotengenezwa kwa rasimu hii.';
$string['review_empty_state'] = 'Bado huna rasimu zozote za quiz za AI. Tumia ukurasa wa Tengeneza kuunda moja.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Taarifa za uthibitisho za Anthropic Claude API. Key hutumika tu wakati sentientia.aiquiz.enabled na sentientia.aiquiz.live_api zote mbili ziko ON.';
$string['setting_api_key'] = 'API key ya Anthropic';
$string['setting_api_key_desc'] = 'Bandika API key kutoka console.anthropic.com. Huhifadhiwa ikiwa imesimbwa kwa njia fiche na configpasswordunmask ya Moodle. KAMWE usiweke thamani hii kwenye source control.';
$string['setting_default_model'] = 'Modeli chaguo-msingi';
$string['setting_default_model_desc'] = 'Modeli ya Anthropic inayotumika wakati mtengenezaji hajabainisha moja. Inapendekezwa: claude-sonnet-4-6.';
$string['settings_heading_limits'] = 'Vikomo na viwango';
$string['settings_heading_limits_desc'] = 'Vikomo kwa kila mtumiaji na kwa kila ombi ili gharama zitabirike.';
$string['setting_max_questions'] = 'Idadi ya juu ya maswali kwa kila ombi';
$string['setting_max_questions_desc'] = 'Kikomo cha juu cha maswali kwa kila uundaji. Chaguo-msingi ni 10.';
$string['setting_daily_token_cap'] = 'Kikomo cha tokeni kwa siku kwa kila mtumiaji';
$string['setting_daily_token_cap_desc'] = 'Kikomo laini cha tokeni (input + output) ambazo mtumiaji mmoja anaweza kutumia kwa siku. Kikizidi, generate.php hurudisha hitilafu hadi saa sita usiku.';
$string['setting_max_source_words'] = 'Idadi ya juu ya maneno chanzo kwa kila rasimu';
$string['setting_max_source_words_desc'] = 'Maandishi chanzo hukataliwa yakizidi idadi hii ya maneno. Chaguo-msingi ni 4000 — takriban kurasa 8.';
$string['settings_heading_customer_prompts'] = 'Violezo vya prompt kwa kila mteja';
$string['settings_heading_customer_prompts_desc'] = 'Hiari: badilisha system prompt inayotumwa kwa Claude, kwa kila mteja wa Sentientia LMS. Ikiwekwa, maudhui ya kisanduku hiki HUCHUKUA NAFASI ya maudhui ya prompt ya msingi ya v1 (Kiingereza) au v2-hindi kama yalivyo. Acha wazi ili kutumia prompt ya msingi iliyo kwenye code. Kifungashio cha ujumbe wa mtumiaji (alama za begin/end + agizo la "maswali N haswa") daima hufuata chaguo la lugha kwenye fomu ya kutengeneza.';
$string['setting_customer_1_prompt_template'] = 'Airpay (mteja 1) — kiolezo maalum cha prompt';
$string['setting_customer_1_prompt_template_desc'] = 'Bandika system prompt mahususi kwa Airpay. Acha wazi ili kutumia prompt ya msingi ya v1/v2-hindi. Huhifadhiwa chini ya local_sentientia_platform/customer_1_aiquiz_prompt_template — husomwa na \\local_sentientia_platform\\customer::get_customer_config(). Rasimu zinazotengenezwa wakati kiolezo maalum kinatumika hurekodiwa na prompt_version kama "custom:v1" au "custom:v2-hindi".';
$string['source_word_count'] = 'Idadi ya maneno: {$a}';
$string['tokens_used_today'] = 'Tokeni zilizotumika leo: {$a->used} / {$a->cap}';
$string['back_to_drafts'] = 'Rudi kwenye orodha ya rasimu';
$string['drafts_list_title'] = 'Rasimu zako za quiz za AI';
$string['push_selectcourse'] = 'Kozi lengwa';
$string['push_default_quizname'] = 'Quiz iliyotengenezwa na AI (rasimu {$a})';
$string['push_quiz_intro'] = 'Imetengenezwa kutoka rasimu ya quiz ya AI #{$a->draftid} (modeli: {$a->model}) na kukaguliwa na mtu kabla ya kuchapishwa. Imeundwa ikiwa imefichwa — kagua mahali ilipo na mipangilio, kisha ifanye ionekane.';
$string['push_success_real'] = 'Quiz "{$a->quizname}" imeundwa (id {$a->quizid}) ikiwa na swali/maswali {$a->count}. Imefichwa — ikague ndani ya kozi, kisha ifanye ionekane.';
$string['push_err_nocourse'] = 'Chagua kozi lengwa kabla ya kutuma — rasimu hii ilitengenezwa bila muktadha wa kozi.';
$string['push_err_notapproved'] = 'Rasimu iliyokamilishwa (iliyoidhinishwa) pekee ndiyo inaweza kutumwa. Kamilisha ukaguzi kwanza.';
$string['push_err_noquestions'] = 'Hakuna maswali yaliyoidhinishwa au kuhaririwa ya kutuma.';
$string['push_err_nobank'] = 'Imeshindwa kupata benki ya maswali ya kozi.';
$string['push_err_importcount'] = 'Idadi ya maswali yaliyoingizwa hailingani: yalitarajiwa {$a->expected}, yameingizwa {$a->actual}. Hakuna kilichochapishwa.';
$string['push_err_badoptions'] = 'Swali {$a} lina machaguo ya majibu chini ya mawili.';
$string['push_err_badanswer'] = 'Swali {$a} lina namba ya jibu sahihi iliyo nje ya machaguo yake.';
$string['push_err_tempfile'] = 'Imeshindwa kuandika faili ya kazi ya uingizaji.';
$string['push_err_import'] = 'Uingizaji wa maswali umeshindwa. Hakuna kilichochapishwa.';
$string['push_err_failed'] = 'Kutuma kumeshindwa: {$a}';
$string['review_notfound'] = 'Rasimu haikupatikana, au huna ruhusa ya kuifikia.';
