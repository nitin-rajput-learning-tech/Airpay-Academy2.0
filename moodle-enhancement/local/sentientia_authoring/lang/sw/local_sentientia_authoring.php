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
 * Swahili strings for local_sentientia_authoring.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_authoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia GenAI Studio ya Uandishi';
$string['sentientia_authoring:generate'] = 'Tengeneza rasimu za kozi kwenye Studio ya Uandishi';
$string['sentientia_authoring:review'] = 'Kagua, hariri na kamilisha rasimu zilizotengenezwa';
$string['sentientia_authoring:managetemplates'] = 'Unda na hariri violezo vya muundo wa mafunzo';
$string['sentientia_authoring:manage_all'] = 'Simamia rasimu na violezo vyote vya wamiliki wote na tenant wote';
$string['nav_studio'] = 'Studio ya Uandishi';
$string['nav_templates'] = 'Violezo vya muundo';
$string['mode_disabled_badge'] = 'Studio ya Uandishi imezimwa — washa feature flag ya sentientia.authoring.enabled.';
$string['mode_mock_badge'] = 'HALI YA MAJARIBIO — utengenezaji unarudisha maudhui ya mfano yasiyobadilika. Hakuna ombi linalotumwa kwa Anthropic na hakuna gharama yoyote.';
$string['mode_no_apikey_badge'] = 'Flag ya Live API imewashwa lakini hakuna API key ya Anthropic iliyowekwa — utengenezaji utaendeshwa katika hali ya majaribio hadi key iwekwe.';
$string['mode_live_badge'] = 'HALI YA LIVE — utengenezaji utatuma ombi kwa Anthropic. Kila uendeshaji unatozwa kwa kila tokeni.';
$string['index_page_title'] = 'Studio ya Uandishi';
$string['index_page_heading'] = 'Studio ya Uandishi — rasimu zangu za kozi';
$string['index_empty'] = 'Bado huna rasimu yoyote ya kozi. Fungua studio ili utengeneze moduli yako ya kwanza ya microlearning.';
$string['index_col_title'] = 'Kichwa';
$string['index_col_status'] = 'Hali';
$string['index_col_cards'] = 'Kadi';
$string['index_col_questions'] = 'Maswali';
$string['index_col_created'] = 'Imeundwa';
$string['index_col_actions'] = 'Vitendo';
$string['index_review_link'] = 'Kagua';
$string['studio_page_title'] = 'Studio ya Uandishi — tengeneza';
$string['studio_page_heading'] = 'Tengeneza moduli ya microlearning';
$string['studio_intro'] = 'Badilisha prompt, hati uliyobandika, au maandishi yaliyotolewa kwenye PDF kuwa moduli kamili ya microlearning — kadi shirikishi pamoja na tathmini yenye aina mchanganyiko za maswali. Kila rasimu hupitia ukaguzi wa binadamu kabla haijachapishwa.';
$string['studio_form_title'] = 'Kichwa cha moduli';
$string['studio_form_template'] = 'Kiolezo cha muundo wa mafunzo';
$string['studio_form_template_help'] = 'Si lazima. Chagua kiolezo kupanga muundo, au acha kama huru.';
$string['studio_form_template_none'] = 'Huru (bila kiolezo)';
$string['studio_form_sourcetype'] = 'Aina ya chanzo';
$string['studio_form_language'] = 'Lugha ya matokeo';
$string['studio_form_language_help'] = 'Lugha nyingine zaidi ya Kiingereza na Kihindi hutengenezwa kwa Kiingereza kisha hutafsiriwa kupitia plugin ya tafsiri inapopatikana.';
$string['studio_form_source'] = 'Maudhui ya chanzo';
$string['studio_form_source_help'] = 'Bandika prompt yako, maandishi ya hati, au dondoo la PDF hapa (hadi maneno {$a}). Usibandike PII ya wafanyakazi.';
$string['studio_form_numcards'] = 'Idadi ya kadi';
$string['studio_form_numq'] = 'Idadi ya maswali';
$string['studio_form_mastery'] = 'Alama ya umahiri (%)';
$string['studio_form_model'] = 'Modeli';
$string['studio_confirm_label'] = 'Ninathibitisha kuwa nataka kuendesha utengenezaji huu. (Katika hali ya live hii inatuma ombi kwa Anthropic na inatozwa kwa kila tokeni.)';
$string['studio_confirm_help'] = 'Lango hili la [CONFIRM] linahitajika kwa kila utengenezaji, hata ule wa hali ya majaribio, ili mtiririko wa kazi uwe sawa katika hali zote mbili.';
$string['studio_submit'] = 'Tengeneza rasimu';
$string['sourcetype_prompt'] = 'Prompt';
$string['sourcetype_doc'] = 'Maandishi ya hati';
$string['sourcetype_pdf'] = 'Dondoo la PDF';
$string['language_en'] = 'Kiingereza';
$string['language_hi'] = 'Kihindi (हिन्दी)';
$string['template_builtin_suffix'] = '(cha mfumo)';
$string['tokens_used_today'] = 'Tokeni zilizotumika leo: {$a->used} kati ya {$a->cap}.';
$string['source_word_count'] = 'Maneno {$a}';
$string['review_page_title'] = 'Studio ya Uandishi — ukaguzi';
$string['review_page_heading'] = 'Kagua rasimu ya kozi';
$string['review_meta'] = 'Hali: {$a->status} · Aina: {$a->mode} · Kadi {$a->cards} · Maswali {$a->questions} · Umahiri: {$a->mastery}%';
$string['review_gate_notice'] = 'Lango la ukaguzi wa binadamu: idhinisha au hariri angalau kadi moja kabla ya kukamilisha. Hakuna kitu kilichotengenezwa hapa kinachochapishwa chenyewe.';
$string['review_failed'] = 'Utengenezaji umeshindwa: {$a}';
$string['review_back_to_studio'] = 'Rudi kwenye studio';
$string['review_cards_heading'] = 'Kadi shirikishi';
$string['review_questions_heading'] = 'Maswali ya tathmini';
$string['review_flip_back'] = 'Upande wa nyuma:';
$string['review_narration'] = 'Masimulizi:';
$string['review_correct_mark'] = 'sahihi';
$string['review_feedback_correct'] = 'Mrejesho (sahihi):';
$string['review_feedback_incorrect'] = 'Mrejesho (si sahihi):';
$string['review_note_placeholder'] = 'Dokezo la mkaguzi (si lazima)';
$string['review_btn_approved'] = 'Idhinisha';
$string['review_btn_edited'] = 'Weka alama: imehaririwa';
$string['review_btn_rejected'] = 'Kataa';
$string['review_finalise'] = 'Kamilisha ukaguzi';
$string['review_finalised_approved'] = 'Ukaguzi umekamilika — rasimu imeidhinishwa. Sasa inaweza kuchapishwa au kuwekewa sauti.';
$string['review_finalised_rejected'] = 'Ukaguzi umekamilika — rasimu imekataliwa (hakuna kadi iliyoidhinishwa).';
$string['review_voiceover_link'] = 'Weka voiceover kwenye kadi hii';
$string['item_status_generated'] = 'Inasubiri ukaguzi';
$string['item_status_approved'] = 'Imeidhinishwa';
$string['item_status_edited'] = 'Imehaririwa';
$string['item_status_rejected'] = 'Imekataliwa';
$string['templates_page_title'] = 'Studio ya Uandishi — violezo vya muundo';
$string['templates_page_heading'] = 'Violezo vya muundo wa mafunzo';
$string['templates_new_button'] = 'Kiolezo kipya';
$string['templates_new_heading'] = 'Kiolezo kipya';
$string['templates_edit_heading'] = 'Hariri kiolezo';
$string['templates_empty'] = 'Bado hakuna violezo. Unda kimoja ili kuamua jinsi studio inavyopanga moduli.';
$string['templates_col_name'] = 'Jina';
$string['templates_col_description'] = 'Maelezo';
$string['templates_col_actions'] = 'Vitendo';
$string['templates_form_name'] = 'Jina la kiolezo';
$string['templates_form_description'] = 'Maelezo mafupi';
$string['templates_form_body'] = 'Maudhui ya kiolezo';
$string['templates_form_body_help'] = 'Eleza muundo na mtindo ambao mfumo wa utengenezaji unapaswa kufuata (mpangilio wa kadi, mchanganyiko wa maswali, mtindo wa lugha).';
$string['templates_archive'] = 'Weka kumbukumbu';
$string['templates_saved'] = 'Kiolezo kimehifadhiwa.';
$string['templates_created'] = 'Kiolezo kimeundwa.';
$string['templates_archived'] = 'Kiolezo kimewekwa kwenye kumbukumbu.';
$string['voiceover_page_title'] = 'Studio ya Uandishi — voiceover';
$string['voiceover_page_heading'] = 'Tengeneza voiceover ya TTS';
$string['voiceover_mode_mock'] = 'HALI YA MAJARIBIO — sauti ya mfano isiyobadilika inatengenezwa. Hakuna ombi linalotumwa kwa ElevenLabs na hakuna gharama yoyote.';
$string['voiceover_mode_live'] = 'HALI YA LIVE — hii itatuma ombi kwa ElevenLabs. Inatozwa kwa kila herufi.';
$string['voiceover_cost_estimate'] = 'Urefu wa masimulizi: herufi {$a->chars}. Makadirio ya gharama ya live: ${$a->cost}.';
$string['voiceover_confirm_label'] = 'Ninathibitisha kuwa nataka kutengeneza voiceover hii. (Katika hali ya live hii inatuma ombi kwa ElevenLabs na inatozwa kwa kila herufi.)';
$string['voiceover_confirm_help'] = 'Lango hili la [CONFIRM] linahitajika kwa kila voiceover, hata zile za hali ya majaribio.';
$string['voiceover_submit'] = 'Tengeneza voiceover';
$string['voiceover_no_narration'] = 'Kadi hii haina hati ya masimulizi ya kuwekewa sauti.';
$string['voiceover_done_mock'] = 'Voiceover ya majaribio imerekodiwa — hakuna ombi lililotumwa kwa ElevenLabs.';
$string['voiceover_done_live'] = 'Voiceover imetengenezwa.';
$string['voiceover_failed'] = 'Voiceover imeshindwa: {$a}';
$string['localize_native'] = 'Imetengenezwa moja kwa moja katika lugha lengwa.';
$string['localize_translate'] = 'Imetengenezwa kwa Kiingereza, kisha ikatafsiriwa kupitia plugin ya tafsiri.';
$string['localize_degraded'] = 'Plugin ya tafsiri haipatikani — matokeo yanabaki kwa Kiingereza.';
$string['settings_heading_ai'] = 'Utengenezaji wa AI (Anthropic)';
$string['settings_heading_ai_desc'] = 'Credentials za utengenezaji wa kozi wa live. Hazitumiki isipokuwa feature flag ya live_api imewashwa na lango la [CONFIRM] limepitishwa. Flag za msingi zikiwa zimezimwa, studio inaendeshwa katika hali ya majaribio na hizi hazisomwi kamwe.';
$string['setting_anthropic_api_key'] = 'API key ya Anthropic';
$string['setting_anthropic_api_key_desc'] = 'Inahifadhiwa kwa usalama. Haiandikwi kwenye log kamwe. Inasomwa tu wakati wa utengenezaji wa live uliopitia lango la [CONFIRM].';
$string['setting_default_model'] = 'Modeli ya msingi';
$string['setting_default_model_desc'] = 'Kitambulisho cha modeli ya Anthropic kinachotumika kwa utengenezaji (k.m. claude-sonnet-4-6).';
$string['settings_heading_tts'] = 'Voiceover ya TTS (ElevenLabs)';
$string['settings_heading_tts_desc'] = 'Credentials za TTS ya live. Hazitumiki isipokuwa flag za tts na live_api zote mbili zimewashwa na lango la [CONFIRM] limepitishwa.';
$string['setting_elevenlabs_api_key'] = 'API key ya ElevenLabs';
$string['setting_elevenlabs_api_key_desc'] = 'Inahifadhiwa kwa usalama. Haiandikwi kwenye log kamwe. Inasomwa tu wakati wa voiceover ya live iliyopitia lango la [CONFIRM].';
$string['setting_elevenlabs_voice_id'] = 'ID ya sauti ya ElevenLabs';
$string['setting_elevenlabs_voice_id_desc'] = 'Sauti inayotumika kwa TTS ya live. Haitumiki katika hali ya majaribio.';
$string['settings_heading_limits'] = 'Vikomo';
$string['settings_heading_limits_desc'] = 'Vikomo vya juu kwa kila ombi na kwa kila siku vinavyodhibiti gharama ya utengenezaji na ukubwa wa moduli.';
$string['setting_max_cards'] = 'Idadi ya juu ya kadi kwa moduli';
$string['setting_max_cards_desc'] = 'Kikomo cha juu cha idadi ya kadi ambazo utengenezaji mmoja unaweza kuomba.';
$string['setting_max_questions'] = 'Idadi ya juu ya maswali kwa moduli';
$string['setting_max_questions_desc'] = 'Kikomo cha juu cha idadi ya maswali ambayo utengenezaji mmoja unaweza kuomba.';
$string['setting_max_source_words'] = 'Idadi ya juu ya maneno ya chanzo';
$string['setting_max_source_words_desc'] = 'Kataa maudhui ya chanzo yenye maneno mengi kuliko idadi hii.';
$string['setting_daily_token_cap'] = 'Kikomo cha tokeni kwa siku (kwa kila mtumiaji)';
$string['setting_daily_token_cap_desc'] = 'Zuia utengenezaji zaidi pale mtumiaji anapokuwa ametumia idadi hii ya tokeni leo.';
$string['setting_default_mastery_score'] = 'Alama ya msingi ya umahiri (%)';
$string['setting_default_mastery_score_desc'] = 'Kiwango cha msingi cha kufaulu kwa tathmini iliyotengenezwa (CLAUDE.md §8 msingi ni 70).';
$string['err_feature_off'] = 'Studio ya Uandishi haijawashwa.';
$string['err_tts_off'] = 'Voiceover ya TTS haijawashwa.';
$string['err_source_empty'] = 'Maudhui ya chanzo hayawezi kuwa tupu.';
$string['err_source_too_long'] = 'Maudhui ya chanzo yamezidi kikomo cha maneno.';
$string['err_source_contains_pii'] = 'Maudhui ya chanzo yanaonekana kuwa na PII (Aadhaar au PAN). Iondoe kabla ya kutengeneza.';
$string['err_invalid_cards'] = 'Idadi ya kadi lazima iwe kati ya {$a->min} na {$a->max}.';
$string['err_invalid_questions'] = 'Idadi ya maswali lazima iwe kati ya {$a->min} na {$a->max}.';
$string['err_invalid_mastery'] = 'Alama ya umahiri lazima iwe kati ya 0 na 100.';
$string['err_confirm_required'] = 'Lazima uweke tiki kwenye kisanduku cha uthibitisho kabla ya kuendelea.';
$string['err_token_cap_reached'] = 'Kikomo cha tokeni cha siku kimefikiwa ({$a->used} kati ya {$a->cap}). Jaribu tena kesho.';
$string['err_api_failed'] = 'Ombi la utengenezaji limeshindwa: {$a}';
$string['err_template_not_found'] = 'Kiolezo hakijapatikana au huna ruhusa ya kukifikia.';
$string['err_template_builtin'] = 'Violezo vya mfumo haviwezi kuwekwa kwenye kumbukumbu wala kufutwa.';
$string['err_template_readonly'] = 'Unaweza kutumia kiolezo hiki lakini huwezi kukibadilisha. Violezo vya mfumo vinashirikiwa na kila tenant, kwa hiyo ni msimamizi wa jukwaa pekee anayeweza kuvihariri.';
$string['err_draft_not_found'] = 'Rasimu haijapatikana au huna ruhusa ya kuifikia.';
$string['err_card_not_found'] = 'Kadi haijapatikana kwenye rasimu hii.';
$string['err_publish_not_approved'] = 'Rasimu lazima ikaguliwe na kuidhinishwa kabla ya kuchapishwa.';
$string['privacy:path:drafts'] = 'Rasimu za kozi';
$string['privacy:path:templates'] = 'Violezo vya muundo';
$string['privacy:metadata:template'] = 'Violezo vya muundo wa mafunzo vilivyoundwa na mtumiaji.';
$string['privacy:metadata:template:ownerid'] = 'Mtumiaji aliyeandika kiolezo.';
$string['privacy:metadata:template:name'] = 'Jina la kiolezo.';
$string['privacy:metadata:template:body'] = 'Maudhui ya kiolezo.';
$string['privacy:metadata:template:timecreated'] = 'Wakati kiolezo kilipoundwa.';
$string['privacy:metadata:template:timemodified'] = 'Wakati kiolezo kilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:draft'] = 'Rasimu za utengenezaji wa kozi zilizoundwa na mtumiaji.';
$string['privacy:metadata:draft:ownerid'] = 'Mtumiaji aliyeunda rasimu.';
$string['privacy:metadata:draft:title'] = 'Kichwa cha rasimu.';
$string['privacy:metadata:draft:sourcetext'] = 'Maudhui ya chanzo yaliyowasilishwa kwa utengenezaji.';
$string['privacy:metadata:draft:model'] = 'Kitambulisho cha modeli ya AI kilichotumika.';
$string['privacy:metadata:draft:tokens'] = 'Idadi ya tokeni iliyorekodiwa kwa ufuatiliaji wa gharama.';
$string['privacy:metadata:draft:reviewed_by'] = 'Mtumiaji aliyekagua rasimu.';
$string['privacy:metadata:draft:reviewed_at'] = 'Wakati ukaguzi ulipokamilika.';
$string['privacy:metadata:draft:timecreated'] = 'Wakati rasimu ilipoundwa.';
$string['privacy:metadata:draft:timemodified'] = 'Wakati rasimu ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:card'] = 'Kadi shirikishi zilizotengenezwa kwa ajili ya rasimu.';
$string['privacy:metadata:card:draftid'] = 'Rasimu mama.';
$string['privacy:metadata:card:body'] = 'Maudhui ya kadi.';
$string['privacy:metadata:card:reviewer_note'] = 'Dokezo la mkaguzi kuhusu kadi.';
$string['privacy:metadata:card:timecreated'] = 'Wakati kadi ilipoundwa.';
$string['privacy:metadata:card:timemodified'] = 'Wakati kadi ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:question'] = 'Maswali ya tathmini yaliyotengenezwa kwa ajili ya rasimu.';
$string['privacy:metadata:question:draftid'] = 'Rasimu mama.';
$string['privacy:metadata:question:qtext'] = 'Kiini cha swali.';
$string['privacy:metadata:question:qoptions'] = 'Machaguo au jozi za swali.';
$string['privacy:metadata:question:reviewer_note'] = 'Dokezo la mkaguzi kuhusu swali.';
$string['privacy:metadata:question:timecreated'] = 'Wakati swali lilipoundwa.';
$string['privacy:metadata:question:timemodified'] = 'Wakati swali lilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:anthropic'] = 'Maudhui ya chanzo yanaweza kutumwa kwa Anthropic kwa ajili ya utengenezaji wa kozi, lakini tu pale feature flag ya live_api imewashwa (kwa msingi imezimwa).';
$string['privacy:metadata:anthropic:sourcetext'] = 'Maudhui ya chanzo yaliyotumwa kwa utengenezaji.';
$string['privacy:metadata:anthropic:model'] = 'Modeli iliyolengwa na ombi.';
$string['privacy:metadata:elevenlabs'] = 'Maandishi ya masimulizi yanaweza kutumwa kwa ElevenLabs kwa ajili ya voiceover, lakini tu pale feature flag ya live_api imewashwa (kwa msingi imezimwa).';
$string['privacy:metadata:elevenlabs:narration'] = 'Maandishi ya masimulizi yaliyotumwa kwa ajili ya kutengeneza sauti.';
$string['publish_to_course'] = 'Chapisha kama kozi';
$string['publish_selectcategory'] = 'Kategoria ya kozi';
$string['publish_disabled'] = 'Uchapishaji wa kozi umezimwa (flag sentientia.authoring.publish.enabled imezimwa) — itawashwa baada ya uthibitishaji wa ninja/staging.';
$string['publish_success'] = 'Kozi "{$a->shortname}" imeundwa (imefichwa) ikiwa na kadi {$a->cardcount} za maudhui na maswali {$a->questioncount} ya quiz. Ikague, kisha uifanye ionekane.';
$string['publish_failed'] = 'Uchapishaji umeshindwa: {$a}';
$string['publish_course_summary'] = 'Imetengenezwa na studio ya uandishi ya Sentientia kutoka rasimu #{$a->draftid} (modeli: {$a->model}) na kukaguliwa na binadamu kabla ya kuchapishwa.';
$string['publish_book_name'] = 'Maudhui ya kozi';
$string['publish_card_untitled'] = 'Kadi {$a}';
$string['publish_card_keypoint'] = 'Hoja kuu:';
$string['publish_card_transcript'] = 'Nakala ya masimulizi';
$string['publish_quiz_name'] = 'Jaribio la umahiri ({$a}% kufaulu)';
$string['publish_quiz_intro'] = 'Jibu maswali yaliyo hapa chini. Unahitaji {$a}% ili kufaulu kozi hii.';
$string['err_publish_nocards'] = 'Hakuna kadi zilizoidhinishwa au kuhaririwa za kuchapisha.';
$string['err_publish_nobank'] = 'Imeshindikana kupata benki ya maswali ya kozi.';
$string['err_publish_importcount'] = 'Idadi ya maswali yaliyoingizwa hailingani: yaliyotarajiwa {$a->expected}, yaliyoingizwa {$a->actual}. Hakuna kilichochapishwa.';
$string['err_publish_badoptions'] = 'Swali {$a} lina machaguo ya majibu chini ya mawili.';
$string['err_publish_badanswer'] = 'Swali {$a} lina namba ya jibu sahihi iliyo nje ya machaguo yake.';
$string['err_publish_tempfile'] = 'Imeshindikana kuandika faili la kazi la uingizaji.';
$string['err_publish_import'] = 'Uingizaji wa maswali umeshindwa. Hakuna kilichochapishwa.';
$string['err_publish_notenant'] = 'Rasimu hii haijawekwa chini ya tenant yoyote, kwa hiyo haiwezi kuchapishwa: kozi ingeonekana kwenye katalogi ya kila tenant. Mwombe msimamizi wa jukwaa aichapishe.';
