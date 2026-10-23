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
 * Swahili strings for local_sentientia_translate.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_translate
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Tafsiri ya Maudhui kwa AI';
$string['sentientia_translate:translate'] = 'Tafsiri maudhui kwa kutumia Anthropic Claude';
$string['sentientia_translate:manage_brands'] = 'Simamia majina mbadala ya chapa kwa kila mteja';
$string['sentientia_translate:manage_all'] = 'Simamia historia ya tafsiri ya wamiliki wote';
$string['privacy:metadata'] = 'Plugin ya Tafsiri ya AI ya Sentientia LMS huhifadhi maandishi asilia na tafsiri zake. Flag ya live API ikiwa ON, maandishi asilia hutumwa kwa Anthropic ili yachakatwe.';
$string['privacy:metadata:tr'] = 'Tafsiri za maudhui zilizoundwa na watumiaji.';
$string['privacy:metadata:tr:ownerid'] = 'Mtumiaji aliyeunda tafsiri.';
$string['privacy:metadata:tr:sourcetext'] = 'Maandishi asilia yaliyotolewa ili yatafsiriwe, kama yalivyo.';
$string['privacy:metadata:tr:translatedtext'] = 'Matokeo yaliyotafsiriwa.';
$string['privacy:metadata:tr:targetlang'] = 'Msimbo wa lugha lengwa.';
$string['privacy:metadata:tr:title'] = 'Kichwa cha tafsiri kilichotolewa na mtumiaji.';
$string['privacy:metadata:tr:tokens'] = 'Matumizi ya tokeni za Anthropic API kwa ajili ya uhasibu wa gharama.';
$string['privacy:metadata:tr:timecreated'] = 'Wakati tafsiri ilipoundwa.';
$string['privacy:metadata:tr:timemodified'] = 'Wakati tafsiri ilipobadilishwa mara ya mwisho.';
$string['privacy:metadata:anthropic'] = 'Anthropic Claude API — flag ya live API ikiwashwa, maandishi asilia hutumwa kwa Anthropic ili yatafsiriwe. Kila mwito wa API unahitaji uthibitisho kwanza.';
$string['privacy:metadata:anthropic:sourcetext'] = 'Maandishi asilia ya kutafsiri.';
$string['privacy:metadata:anthropic:targetlang'] = 'Msimbo wa lugha lengwa ulioombwa.';
$string['privacy:metadata:anthropic:model'] = 'Kitambulisho cha modeli ya Anthropic kilichotumika.';
$string['nav_translate'] = 'Tafsiri maudhui';
$string['lang_hi'] = 'Kihindi (हिन्दी)';
$string['lang_mr'] = 'Kimarathi (मराठी)';
$string['lang_kn'] = 'Kikannada (ಕನ್ನಡ)';
$string['lang_sw'] = 'Kiswahili (Kiswahili)';
$string['translate_page_title'] = 'Tafsiri maudhui';
$string['translate_page_heading'] = 'Tafsiri maudhui ya kozi kwa AI';
$string['translate_intro'] = 'Bandika maudhui asilia ya Kiingereza, chagua lugha lengwa, na Anthropic Claude itatoa tafsiri kwa maandishi ya lugha hiyo. Majina ya chapa yanahifadhiwa kulingana na majina mbadala ya mteja wako. Kagua tofauti (diff) kando kwa kando kabla ya kuhifadhi.';
$string['form_title'] = 'Kichwa cha tafsiri';
$string['form_targetlang'] = 'Lugha lengwa';
$string['form_source'] = 'Maudhui asilia (Kiingereza)';
$string['form_source_help'] = 'Hadi maneno {$a}. Ondoa PII yoyote (majina ya wafanyakazi, ID, data za wateja) kabla ya kubandika.';
$string['form_model'] = 'Modeli';
$string['form_confirm_label'] = 'Nathibitisha kwamba nataka kuita Anthropic kwa maudhui haya';
$string['form_confirm_help'] = 'Hii ni hatua ya uthibitisho kwa kila mwito. Flag ya live API ikiwa ON, kutuma fomu hii kutagharimu pesa halisi kulingana na urefu wa maandishi asilia. Ondoa tiki ili kusitisha.';
$string['form_submit'] = 'Tafsiri';
$string['form_cancel'] = 'Ghairi';
$string['diff_heading'] = 'Kagua tafsiri';
$string['diff_meta'] = 'Lugha lengwa: {$a->lang} | Mabadiliko ya chapa yaliyotumika: {$a->brands} | Mfumo: {$a->mode}';
$string['diff_source'] = 'Asilia (Kiingereza)';
$string['diff_translation'] = 'Tafsiri';
$string['action_save'] = 'Hifadhi tafsiri';
$string['action_discard'] = 'Tupa';
$string['back_to_translate'] = 'Rudi kwenye tafsiri';
$string['saved_notice'] = 'Tafsiri imehifadhiwa.';
$string['discarded_notice'] = 'Tafsiri imetupwa.';
$string['status_label'] = 'Hali';
$string['status_pending'] = 'Inasubiri';
$string['status_translated'] = 'Imetafsiriwa (inasubiri kukaguliwa)';
$string['status_saved'] = 'Imehifadhiwa';
$string['status_failed'] = 'Imeshindwa';
$string['status_discarded'] = 'Imetupwa';
$string['mode_mock_badge'] = 'MFUMO WA MAJARIBIO — hakuna mwito wa live API (weka sentientia.translate.live_api = ON kwa tafsiri halisi)';
$string['mode_live_badge'] = 'LIVE API — mwito wa Anthropic unatozwa kwenye akaunti yako';
$string['mode_disabled_badge'] = 'Kipengele kimezimwa — weka sentientia.translate.enabled = ON kwenye Switchboard';
$string['mode_no_apikey_badge'] = 'Hakuna ufunguo wa API uliowekwa — weka local_sentientia_translate | api_key kwenye Usimamizi wa tovuti';
$string['err_feature_off'] = 'Kipengele cha Tafsiri ya AI kimezimwa. Mwombe msimamizi awashe sentientia.translate.enabled.';
$string['err_no_capability'] = 'Huna ruhusa ya kutafsiri maudhui.';
$string['err_source_empty'] = 'Maudhui asilia yanahitajika.';
$string['err_source_too_long'] = 'Maudhui asilia yamezidi kikomo cha maneno kilichowekwa. Yapunguze kabla ya kutuma.';
$string['err_source_contains_pii'] = 'Maudhui asilia yanaonekana kuwa na PII (namba za Aadhaar au PAN). Yafiche kisha utume tena.';
$string['err_unsupported_lang'] = 'Lugha lengwa haitumiki. Chagua Kihindi, Kimarathi, Kikannada au Kiswahili.';
$string['err_confirm_required'] = 'Lazima uweke tiki kwenye kisanduku cha uthibitisho ili kuita API.';
$string['err_api_key_not_set'] = 'Hakuna ufunguo wa Anthropic API uliowekwa. Haiwezekani kufanya mwito wa live.';
$string['err_api_failed'] = 'Mwito wa Anthropic API umeshindwa: {$a}';
$string['err_cost_cap_reached'] = 'Akaunti ya mteja wako imetumia tokeni {$a->used} leo (kikomo laini cha kila siku ni {$a->cap}). Jaribu tena kesho au mwombe admin aongeze kikomo.';
$string['err_row_not_found'] = 'Tafsiri haijapatikana au huna ruhusa ya kuiona.';
$string['brands_page_title'] = 'Majina mbadala ya chapa';
$string['brands_page_heading'] = 'Majina mbadala ya chapa';
$string['brands_intro'] = 'Weka jinsi majina ya chapa yanavyoonekana katika kila lugha lengwa. Chapa ikiwa na jina mbadala kwa lugha fulani, kila inapotokea kwenye tafsiri itabadilishwa kwa muundo wa maandishi ya lugha lengwa. Chapa zisizo na jina mbadala huhifadhiwa kama zilivyo.';
$string['brands_protected_label'] = 'Huhifadhiwa kila mara (kama zilivyo, isipokuwa zikiwa na jina mbadala)';
$string['brands_empty'] = 'Bado hakuna majina mbadala ya chapa yaliyowekwa. Ongeza moja hapa chini.';
$string['brands_add_heading'] = 'Ongeza jina mbadala la chapa';
$string['brand_source'] = 'Chapa (Kiingereza)';
$string['brand_lang'] = 'Lugha';
$string['brand_target'] = 'Muundo katika maandishi lengwa';
$string['brand_add'] = 'Ongeza jina mbadala';
$string['brand_delete'] = 'Futa';
$string['brand_saved'] = 'Jina mbadala la chapa limehifadhiwa.';
$string['brand_invalid'] = 'Jina mbadala la chapa si sahihi. Chapa asilia, muundo lengwa na lugha inayotumika vyote vinahitajika.';
$string['brand_deleted'] = 'Jina mbadala la chapa limefutwa.';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Vitambulisho vya kuingia kwa Anthropic Claude API. Ufunguo huu hutumika tu pale sentientia.translate.enabled na sentientia.translate.live_api zote zikiwa ON.';
$string['setting_api_key'] = 'Ufunguo wa Anthropic API';
$string['setting_api_key_desc'] = 'Bandika ufunguo wa API kutoka console.anthropic.com. Huhifadhiwa ukiwa umesimbwa. KAMWE usiweke thamani hii kwenye source control.';
$string['setting_default_model'] = 'Modeli chaguomsingi';
$string['setting_default_model_desc'] = 'Modeli ya Anthropic inayotumika mtafsiri asipochagua nyingine. Inapendekezwa: claude-sonnet-4-6.';
$string['setting_max_output_tokens'] = 'Idadi ya juu ya tokeni za matokeo';
$string['setting_max_output_tokens_desc'] = 'Kikomo kikali cha Anthropic max_tokens kwa kila mwito. Chaguomsingi ni 8192 (tafsiri zinaweza kuwa ndefu).';
$string['settings_heading_limits'] = 'Vikomo na viwango';
$string['settings_heading_limits_desc'] = 'Vikomo kwa kila ombi na kwa kila mteja ili gharama zitabirike.';
$string['setting_max_source_words'] = 'Idadi ya juu ya maneno asilia kwa kila tafsiri';
$string['setting_max_source_words_desc'] = 'Maandishi asilia hukataliwa yakizidi idadi hii ya maneno. Chaguomsingi ni 4000 — takriban kurasa 8.';
$string['setting_daily_cost_cap'] = 'Kikomo cha tokeni kwa siku kwa kila mteja';
$string['setting_daily_cost_cap_desc'] = 'Kikomo laini cha tokeni (zinazoingia + zinazotoka) zinazotumika kwa tafsiri kwa kila mteja kwa siku. Kikishazidi, translate.php hurudisha hitilafu hadi saa sita usiku.';
$string['settings_heading_prompt'] = 'Mipangilio ya prompt';
$string['settings_heading_prompt_desc'] = 'Matoleo ya prompt yanahifadhiwa kwenye msimbo (prompt_builder::VERSION). Sehemu hii ya maandishi huru ni kwa taarifa tu.';
$string['setting_prompt_template_note'] = 'Dokezo la kiolezo cha prompt';
$string['setting_prompt_template_note_desc'] = 'Madokezo huru kuhusu kiolezo cha sasa cha prompt — huonyeshwa kwenye UI ya admin pekee. HAIBADILISHI prompt_builder.';
$string['source_word_count'] = 'Idadi ya maneno: {$a}';
$string['tokens_used_today'] = 'Tokeni zilizotumika leo (kwa mteja mzima): {$a->used} / {$a->cap}';
$string['admin_index_title'] = 'Tafsiri ya AI';
$string['admin_index_intro'] = 'Simamia tafsiri za maudhui zinazofanywa na AI. Tuma kazi mpya za tafsiri, kagua tofauti zinazosubiri, na fanya ukaguzi wa shughuli za awali.';
$string['admin_index_flag_off_notice'] = 'Flag ya kipengele cha tafsiri ya AI (sentientia.translate.enabled) kwa sasa iko OFF. Tafsiri bado zinaweza kukaguliwa lakini hakuna miito mipya ya Anthropic itakayofanyika.';
$string['admin_index_queue'] = 'Tafsiri za hivi karibuni';
$string['admin_index_empty'] = 'Hakuna tafsiri zinazolingana na vichujio vya sasa.';
$string['admin_index_truncated'] = 'Inaonyesha safu 25 za hivi karibuni. Boresha vichujio ili kupunguza zaidi.';
$string['admin_index_quicknav'] = 'Urambazaji wa haraka';
$string['admin_index_link_translate'] = 'Tafsiri mpya';
$string['admin_index_link_translate_desc'] = 'Bandika maudhui asilia ya Kiingereza na uendeshe kazi ya tafsiri kwenda Kihindi, Kimarathi, Kikannada au Kiswahili.';
$string['admin_index_link_brands'] = 'Ramani ya majina mbadala ya chapa';
$string['admin_index_link_brands_desc'] = 'Simamia majina mbadala ya chapa kwa kila mteja (k.m. "Airpay" kuhifadhiwa kama lilivyo au kuandikwa kwa maandishi ya lugha lengwa).';
$string['admin_index_link_settings'] = 'Mipangilio ya tafsiri';
$string['admin_index_link_settings_desc'] = 'Ufunguo wa Anthropic API, modeli chaguomsingi, kikomo cha tokeni kwa siku kwa kila mteja na vikomo vya maneno asilia.';
$string['stats_total'] = 'Jumla ya tafsiri';
$string['stats_pending'] = 'Zinasubiri / zinasubiri ukaguzi';
$string['stats_saved'] = 'Zimehifadhiwa (zimekubaliwa)';
$string['stats_failed'] = 'Zimeshindwa';
$string['filter_status'] = 'Hali:';
$string['filter_lang'] = 'Lugha lengwa:';
$string['filter_all'] = 'Zote';
$string['filter_apply'] = 'Tumia';
$string['filter_reset'] = 'Weka upya';
$string['col_title'] = 'Kichwa';
$string['col_lang'] = 'Lengwa';
$string['col_status'] = 'Hali';
$string['col_tokens'] = 'Tokeni (ndani + nje)';
$string['col_created'] = 'Imeundwa';
$string['col_actions'] = 'Vitendo';
$string['action_review'] = 'Kagua tofauti';
$string['action_open'] = 'Fungua';
