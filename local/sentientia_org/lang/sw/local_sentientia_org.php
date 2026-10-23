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
 * Swahili strings for local_sentientia_org.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_org
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Injini ya Mashirika ya Sentientia';
$string['sentientia_org:manage'] = 'Simamia mashirika';
$string['sentientia_org:manage_multiorganizations'] = 'Simamia mashirika mengi';
$string['sentientia_org:manage_ownorganization'] = 'Simamia shirika lako';
$string['sentientia_org:manage_owndepartments'] = 'Simamia idara zako';
$string['sentientia_org:view'] = 'Tazama mashirika';
$string['settings_heading'] = 'Mipangilio ya Shirika ya Airpay';
$string['settings_heading_desc'] = 'Sanidi muundo wa ngazi za shirika na usimamizi wa tenant.';
$string['public_tenant_id'] = 'ID ya tenant ya umma';
$string['public_tenant_id_desc'] = 'ID ya Costcenter kwa tenant ya umma (inayoonekana kwa wageni). Chaguo-msingi: kugundua kiotomatiki.';
$string['invalidtenant'] = 'ID ya tenant si sahihi';
$string['orgnotfound'] = 'Shirika halijapatikana';
$string['migrationcomplete'] = 'Uhamishaji wa data kutoka local_costcenter umekamilika kwa mafanikio.';
$string['migrationskipped'] = 'Uhamishaji umerukwa — jedwali la local_sentientia_org tayari lina data.';
$string['sourcetablemissing'] = 'Jedwali chanzo local_costcenter halipo. Hakuna data ya kuhamisha.';
$string['addorg'] = 'Ongeza Shirika';
$string['editorg'] = 'Hariri Shirika';
$string['deleteorg'] = 'Futa Shirika';
$string['hideorg'] = 'Ficha Shirika';
$string['showorg'] = 'Onyesha Shirika';
$string['heading_basic'] = 'Utambulisho';
$string['heading_hierarchy'] = 'Muundo wa ngazi';
$string['heading_branding'] = 'Chapa (si lazima)';
$string['heading_visibility'] = 'Mwonekano';
$string['org_fullname'] = 'Jina kamili';
$string['org_shortname'] = 'Jina fupi';
$string['shortname_help'] = 'Kitambulisho kifupi cha kipekee kwa shirika hili. Kinatumika kuchuja kwa path na kwenye slug za URL. k.m. "AirPay_Acquiring".';
$string['description'] = 'Maelezo';
$string['parent_org'] = 'Shirika kuu';
$string['parent_org_help'] = 'Chagua mahali shirika hili linakaa kwenye muundo wa ngazi. Chagua "Tenant ya ngazi ya juu" ili kuunda tenant mpya. Muundo wa ngazi hukokotolewa kiotomatiki — kina na path hurithiwa kutoka kwa shirika kuu.';
$string['top_level_tenant'] = '— Tenant ya ngazi ya juu —';
$string['brand_color'] = 'Rangi ya chapa (hex)';
$string['button_color'] = 'Rangi ya kitufe (hex)';
$string['hover_color'] = 'Rangi ya hover (hex)';
$string['theme_scheme'] = 'Mpangilio wa theme';
$string['branding_help'] = 'Mabadiliko ya kila tenant kwa theme ya airpayux. Acha wazi ili kutumia chaguo-msingi za tovuti. Muundo wa hex k.m. #0066A7.';
$string['visible'] = 'Mwonekano';
$string['visible_yes'] = 'Hai (inaonekana kwa watumiaji)';
$string['visible_no'] = 'Imefichwa (admin pekee)';
$string['sortorder'] = 'Mpangilio';
$string['missingrequiredfields'] = 'Tafadhali jaza sehemu zote zinazohitajika.';
$string['name_required'] = 'Jina la shirika linahitajika.';
$string['invalid_color'] = 'Tumia rangi sahihi ya hex, k.m. #0066A7.';
$string['invalidparent'] = 'Shirika kuu ulilochagua halipo.';
$string['error_parent_outofscope'] = 'Chagua shirika kuu lililo ndani ya shirika lako. Ni msimamizi wa jukwaa (wa tenant zote) pekee anayeweza kuunda tenant mpya ya ngazi ya juu.';
$string['cannotdeletetenant'] = 'Tenant za ngazi ya juu haziwezi kufutwa. Zifiche badala yake ili data ya zamani ibaki salama.';
$string['orghaschildren'] = 'Haiwezi kufutwa: shirika hili bado lina mashirika madogo. Yafute au yahamishe kwanza.';
$string['orghasusers'] = 'Haiwezi kufutwa: shirika hili bado lina watumiaji waliopangiwa. Wapangie mahali pengine kwanza.';
$string['confirmdelete'] = 'Futa "{$a}"? Hii itaondoa shirika kabisa. Kitendo hiki kinazuiwa ikiwa lina mashirika madogo au watumiaji waliopangiwa.';
$string['confirmhide'] = 'Ficha "{$a}"? Watumiaji waliopo wanabaki walipopangiwa, lakini shirika halitaonekana kwenye orodha za kuchagua na vichujio.';
$string['confirmshow'] = 'Onyesha "{$a}"? Litaonekana tena kwenye orodha za kuchagua na vichujio.';
$string['org_created'] = 'Shirika limeundwa.';
$string['org_updated'] = 'Shirika limesasishwa.';
$string['orgdeleted'] = 'Shirika limefutwa.';
$string['orgvisibilitychanged'] = 'Mwonekano wa shirika umesasishwa.';
$string['privacy:metadata'] = 'Plugin ya Airpay sentientia_org huhifadhi rejea moja tu ya kibinafsi kwenye majedwali yake: msimamizi aliyebadilisha mwisho wigo wa tenant wa cohort. Majedwali ya mashirika hayana data ya kibinafsi; hali nyingine ya mtumiaji iko kwenye majedwali makuu ya Sentientia LMS, ambayo hutolewa (export) na provider zake husika.';
$string['privacy:metadata:cohort_scope'] = 'Wigo wa tenant wa kila cohort: shirika na idara ambazo cohort hiyo ni sehemu yake, na nani aliyeubadilisha mwisho.';
$string['privacy:metadata:cohort_scope:cohortid'] = 'Cohort ambayo wigo huu ni wake.';
$string['privacy:metadata:cohort_scope:usermodified'] = 'id ya mtumiaji aliyebadilisha wigo mwisho. Mtumiaji akifutwa, thamani hii huwekwa 0 na safu inabaki.';
$string['privacy:metadata:cohort_scope:timemodified'] = 'Wakati wigo ulipobadilishwa mwisho.';
$string['privacy:subcontext:cohort_scope'] = 'Wigo za tenant za cohort ulizobadilisha mwisho';
$string['org_logo'] = 'Nembo ya shirika';
$string['cascade_l1'] = 'Shirika';
$string['cascade_l2'] = 'Idara';
$string['cascade_l3'] = 'Idara Ndogo';
$string['cascade_l4'] = 'Ngazi ya 4';
$string['cascade_l5'] = 'Ngazi ya 5';
$string['cascade_all_l1'] = 'Mashirika Yote';
$string['cascade_all_l2'] = 'Idara Zote';
$string['cascade_all_l3'] = 'Idara Ndogo Zote';
$string['cascade_all_l4'] = 'Vitengo Vyote vya Ngazi ya 4';
$string['cascade_all_l5'] = 'Vitengo Vyote vya Ngazi ya 5';
