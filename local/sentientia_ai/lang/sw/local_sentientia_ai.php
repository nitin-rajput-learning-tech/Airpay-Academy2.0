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
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_ai
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Gateway ya Sentientia AI';
$string['sentientia_ai:viewledger'] = 'Angalia leja ya matumizi ya AI';
$string['sentientia_ai:manage'] = 'Simamia vidhibiti vya uendeshaji vya gateway ya AI';
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'Ufunguo MMOJA mkuu unaotumiwa na kila kipengele cha Sentientia AI. Funguo za zamani za kila plugin zinaendelea kufanya kazi kama akiba wakati wa uhamisho; hamishia kila kitu kwenye ufunguo huu na ufute zile za zamani.';
$string['setting_api_key'] = 'Ufunguo wa API';
$string['setting_api_key_desc'] = 'Ufunguo wa Anthropic API. Kamwe hauwekwi kwenye commit, hauandikwi kwenye log, wala haujumuishwi kwenye ujumbe wa hitilafu. Maombi ya live pia yanahitaji flag zote mbili za gateway ziwe ON na nafasi iliyobaki kwenye quota.';
$string['setting_default_model'] = 'Modeli chaguo-msingi';
$string['setting_default_model_desc'] = 'Modeli inayotumika pale kipengele kinachoita kisipobainisha modeli (k.m. claude-sonnet-4-6).';
$string['settings_heading_quotas'] = 'Quota za matumizi (fail-closed)';
$string['settings_heading_quotas_desc'] = 'Vikomo madhubuti vinavyotekelezwa KABLA ya kila ombi la live, kwa kutumia leja ya matumizi. Thamani ya sifuri au tupu inamaanisha HAKUNA maombi ya live (kamwe si bila kikomo) — bajeti ya AI iliyoidhinishwa ni kikomo madhubuti kwa uamuzi (memo 2026-08-04, Addendum A). Vipindi ni siku/miezi ya kalenda kwa saa za seva. Gharama ni MAKADIRIO kutoka kwenye ramani ya bei kwa ajili ya bajeti, si ankara.';
$string['setting_daily_tokens_global'] = 'Kikomo cha token kwa siku (jumla)';
$string['setting_daily_tokens_global_desc'] = 'Token za live (input + output) zinazoruhusiwa kwa kila siku ya kalenda kwa wateja wote na vipengele vyote. 0 = maombi ya live yamezuiwa.';
$string['setting_daily_tokens_customer'] = 'Kikomo cha token kwa siku (kwa kila mteja)';
$string['setting_daily_tokens_customer_desc'] = 'Token za live zinazoruhusiwa kwa kila siku ya kalenda kwa mteja mmoja yeyote. 0 = maombi ya live yamezuiwa.';
$string['setting_monthly_cost_cap'] = 'Kikomo cha gharama kwa mwezi (USD, makadirio)';
$string['setting_monthly_cost_cap_desc'] = 'Kikomo cha matumizi yanayokadiriwa kwa kila mwezi wa kalenda. Makadirio ya leja ya mwezi huu hadi sasa yakifikia kiwango hiki, maombi zaidi ya live yanakataliwa. 0 = maombi ya live yamezuiwa.';
$string['ledger_title'] = 'Leja ya matumizi ya AI';
$string['ledger_intro'] = 'Kila ombi la gateway — mock, live, lililoshindwa au lililokataliwa — ni safu moja. Quota zinatekelezwa kwa kutumia namba hizi kabla ya kila ombi la live.';
$string['ledger_today'] = 'Leo (token za live)';
$string['ledger_month'] = 'Mwezi huu hadi sasa (USD, makadirio)';
$string['ledger_bycomponent'] = 'Siku 30 zilizopita kwa kipengele';
$string['ledger_recent'] = 'Maombi ya hivi karibuni';
$string['ledger_col_time'] = 'Muda';
$string['ledger_col_component'] = 'Kipengele';
$string['ledger_col_purpose'] = 'Madhumuni';
$string['ledger_col_user'] = 'Mtumiaji';
$string['ledger_col_model'] = 'Modeli';
$string['ledger_col_tokens'] = 'Token (ndani/nje)';
$string['ledger_col_cost'] = 'Makadirio ya gharama';
$string['ledger_col_mode'] = 'Hali';
$string['ledger_col_calls'] = 'Maombi';
$string['ledger_empty'] = 'Bado hakuna maombi ya gateway yaliyorekodiwa.';
$string['privacy:exportpath'] = 'Matumizi ya gateway ya AI';
$string['privacy:metadata:ledger'] = 'Leja ya matumizi ya gateway ya AI inarekodi safu moja kwa kila ombi la AI ambalo mtumiaji ameanzisha. Maandishi ya prompt na jibu hayahifadhiwi kamwe — ni hesabu ya matumizi tu.';
$string['privacy:metadata:ledger:userid'] = 'Mtumiaji ambaye kitendo chake kilianzisha ombi la AI.';
$string['privacy:metadata:ledger:component'] = 'Plugin iliyofanya ombi.';
$string['privacy:metadata:ledger:purpose'] = 'Madhumuni yaliyotajwa ya ombi (k.m. quiz_generation).';
$string['privacy:metadata:ledger:model'] = 'Modeli ya AI iliyotumika.';
$string['privacy:metadata:ledger:prompttokens'] = 'Token za input zilizoripotiwa na API.';
$string['privacy:metadata:ledger:completiontokens'] = 'Token za output zilizoripotiwa na API.';
$string['privacy:metadata:ledger:estcost'] = 'Makadirio ya gharama ya ombi kwa USD.';
$string['privacy:metadata:ledger:mode'] = 'Kama ombi lilikuwa mock, live, lililoshindwa au lililokataliwa.';
$string['privacy:metadata:ledger:timecreated'] = 'Wakati ombi lilipofanywa.';
$string['privacy:metadata:anthropic'] = 'Hali ya live ikiwashwa, maandishi ya prompt hutumwa kwa Anthropic API ili kutengeneza jibu. Taarifa binafsi (PII) za wafanyakazi hazipaswi kujumuishwa kwenye prompt (hili linasimamiwa na sheria za input za vipengele vinavyoita).';
$string['privacy:metadata:anthropic:prompttext'] = 'Maandishi ya prompt ya ombi la AI.';
