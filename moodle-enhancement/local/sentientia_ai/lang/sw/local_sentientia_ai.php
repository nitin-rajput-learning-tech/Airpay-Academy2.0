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
 * Swahili strings for local_sentientia_ai.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_ai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Lango la AI la Sentientia';
$string['sentientia_ai:viewledger'] = 'Tazama leja ya matumizi ya AI';
$string['sentientia_ai:manage'] = 'Simamia vidhibiti vya uendeshaji vya lango la AI';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Ufunguo MMOJA mkuu unaotumiwa na kila kipengele cha Sentientia AI. Funguo za zamani za kila plugin zinaendelea kufanya kazi kama akiba wakati wa uhamishaji; hamishia vyote kwenye ufunguo huu kisha ufute funguo za zamani.';
$string['setting_api_key'] = 'Ufunguo wa API';
$string['setting_api_key_desc'] = 'Ufunguo wa Anthropic API. Kamwe hauwekwi kwenye commit, hauandikwi kwenye kumbukumbu, wala haujumuishwi kwenye ujumbe wa hitilafu. Maombi halisi pia yanahitaji feature flag zote mbili za lango la AI ziwe ON na quota iwe na nafasi iliyobaki.';
$string['setting_default_model'] = 'Modeli chaguo-msingi';
$string['setting_default_model_desc'] = 'Modeli inayotumika pale kipengele kinachoita kisipobainisha modeli (k.m. claude-sonnet-4-6).';
$string['settings_heading_quotas'] = 'Quota za matumizi (fail-closed)';
$string['settings_heading_quotas_desc'] = 'Vikomo madhubuti vinavyotekelezwa KABLA ya kila ombi halisi, kwa kutumia leja ya matumizi. Thamani ya sifuri au tupu inamaanisha HAKUNA maombi halisi (kamwe si bila kikomo) — bajeti ya AI iliyoidhinishwa ni kikomo madhubuti kwa uamuzi rasmi (memo 2026-08-04, Addendum A). Muda unahesabiwa kwa siku/miezi ya kalenda kwa saa za seva. Gharama ni MAKADIRIO kutoka kwenye ramani ya bei kwa ajili ya bajeti, si ankara.';
$string['setting_daily_tokens_global'] = 'Kikomo cha tokeni kwa siku (jumla)';
$string['setting_daily_tokens_global_desc'] = 'Tokeni halisi (input + output) zinazoruhusiwa kwa kila siku ya kalenda kwa wateja wote na vipengele vyote. 0 = maombi halisi yamezuiwa.';
$string['setting_daily_tokens_customer'] = 'Kikomo cha tokeni kwa siku (kwa kila mteja)';
$string['setting_daily_tokens_customer_desc'] = 'Tokeni halisi zinazoruhusiwa kwa kila siku ya kalenda kwa mteja yeyote mmoja. 0 = maombi halisi yamezuiwa.';
$string['setting_monthly_cost_cap'] = 'Kikomo cha gharama kwa mwezi (USD, makadirio)';
$string['setting_monthly_cost_cap_desc'] = 'Kikomo cha matumizi yanayokadiriwa kwa kila mwezi wa kalenda. Makadirio ya leja ya mwezi huu hadi sasa yakifikia kiwango hiki, maombi halisi zaidi yanakataliwa. 0 = maombi halisi yamezuiwa.';
$string['ledger_title'] = 'Leja ya matumizi ya AI';
$string['ledger_intro'] = 'Kila ombi kwa lango la AI — la majaribio (mock), halisi (live), lililoshindikana au lililokataliwa — ni safu moja. Quota zinatekelezwa kwa kutumia namba hizi kabla ya kila ombi halisi.';
$string['ledger_today'] = 'Leo (tokeni halisi)';
$string['ledger_month'] = 'Mwezi huu hadi sasa (USD, makadirio)';
$string['ledger_bycomponent'] = 'Siku 30 zilizopita kwa kipengele';
$string['ledger_recent'] = 'Maombi ya hivi karibuni';
$string['ledger_col_time'] = 'Wakati';
$string['ledger_col_component'] = 'Kipengele';
$string['ledger_col_purpose'] = 'Madhumuni';
$string['ledger_col_user'] = 'Mtumiaji';
$string['ledger_col_model'] = 'Modeli';
$string['ledger_col_tokens'] = 'Tokeni (ndani/nje)';
$string['ledger_col_cost'] = 'Gharama (makadirio)';
$string['ledger_col_mode'] = 'Hali';
$string['ledger_col_calls'] = 'Maombi';
$string['ledger_empty'] = 'Bado hakuna maombi ya lango la AI yaliyorekodiwa.';
$string['privacy:exportpath'] = 'Matumizi ya lango la AI';
$string['privacy:metadata:ledger'] = 'Leja ya matumizi ya lango la AI inarekodi safu moja kwa kila ombi la AI ambalo mtumiaji anaanzisha. Maandishi ya prompt na jibu hayahifadhiwi kamwe — ni hesabu ya matumizi tu.';
$string['privacy:metadata:ledger:userid'] = 'Mtumiaji ambaye kitendo chake kilianzisha ombi la AI.';
$string['privacy:metadata:ledger:component'] = 'Plugin iliyotuma ombi.';
$string['privacy:metadata:ledger:purpose'] = 'Madhumuni yaliyotajwa ya ombi (k.m. quiz_generation).';
$string['privacy:metadata:ledger:model'] = 'Modeli ya AI iliyotumika.';
$string['privacy:metadata:ledger:prompttokens'] = 'Tokeni za input zilizoripotiwa na API.';
$string['privacy:metadata:ledger:completiontokens'] = 'Tokeni za output zilizoripotiwa na API.';
$string['privacy:metadata:ledger:estcost'] = 'Makadirio ya gharama ya ombi kwa USD.';
$string['privacy:metadata:ledger:mode'] = 'Kama ombi lilikuwa la majaribio (mock), halisi (live), lililoshindikana au lililokataliwa.';
$string['privacy:metadata:ledger:timecreated'] = 'Wakati ombi lilipotumwa.';
$string['privacy:metadata:anthropic'] = 'Hali halisi ikiwashwa, maandishi ya prompt hutumwa kwa Anthropic API ili kutengeneza jibu. Taarifa binafsi (PII) za wafanyakazi hazipaswi kujumuishwa kwenye prompt (hili linasimamiwa na sheria za input za vipengele vinavyoita).';
$string['privacy:metadata:anthropic:prompttext'] = 'Maandishi ya prompt ya ombi la AI.';
