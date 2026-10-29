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
 * Swahili strings for local_sentientia_reports.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_reports
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Ripoti za Sentientia';
$string['sentientia_reports:view'] = 'Tazama ripoti zilizohifadhiwa';
$string['sentientia_reports:manage'] = 'Simamia ripoti zilizohifadhiwa';
$string['sentientia_reports:export'] = 'Hamisha ripoti zilizohifadhiwa kama CSV';
$string['addreport'] = 'Unda Ripoti';
$string['editreport'] = 'Hariri Ripoti';
$string['deletereport'] = 'Futa Ripoti';
$string['archivereport'] = 'Weka Ripoti kwenye Kumbukumbu';
$string['activatereport'] = 'Wezesha Ripoti';
$string['heading_basic'] = 'Utambulisho wa Ripoti';
$string['heading_type'] = 'Aina ya Ripoti';
$string['heading_scope'] = 'Wigo wa Shirika';
$string['report_name'] = 'Jina la ripoti';
$string['description'] = 'Maelezo';
$string['report_type'] = 'Aina ya ripoti';
$string['report_type_help'] = 'Kila aina iliyojengwa ndani huendesha hoja (query) tofauti. Ukamilishaji wa Kozi huorodhesha maendeleo ya kila mtumiaji kwa kila kozi. Muhtasari wa Uzingatiaji huonyesha viwango vya mafunzo ya lazima. Shughuli za Mtumiaji hufuatilia jinsi watumiaji wanavyoingia kwenye mfumo. Mwenendo wa Usajili huonyesha idadi ya usajili kwa kila mwezi.';
$string['organisation'] = 'Shirika (wigo wa tenant)';
$string['organisation_help'] = 'Wekea ripoti kikomo kwa shirika mahususi katika muundo wako. Acha kwenye "Mashirika yote" ili kujumuisha kila tenant.';
$string['status'] = 'Hali';
$string['status_active'] = 'Inatumika';
$string['status_archived'] = 'Kwenye kumbukumbu';
$string['name_required'] = 'Jina la ripoti linahitajika.';
$string['invalid_report_type'] = 'Aina ya ripoti si sahihi.';
$string['invalidreport'] = 'Ripoti haijapatikana.';
$string['invalidreporttype'] = 'Aina ya ripoti si sahihi.';
$string['missingrequiredfields'] = 'Tafadhali jaza sehemu zote zinazohitajika.';
$string['confirmdelete'] = 'Futa ripoti "{$a}"? Hii itaondoa kabisa muundo wa ripoti uliohifadhiwa. Faili za CSV zilizokwisha kutengenezwa haziathiriwi. Hatua hii haiwezi kutenduliwa.';
$string['confirmarchive'] = 'Weka "{$a}" kwenye kumbukumbu? Itafichwa kwenye orodha ya zinazotumika lakini itahifadhiwa kwa ajili ya ukaguzi. Unaweza kuiwezesha tena wakati wowote.';
$string['confirmactivate'] = 'Wezesha "{$a}"? Itaonekana kwenye orodha kuu ya ripoti na itaendeshwa unapohitaji.';
$string['report_created'] = 'Ripoti imeundwa.';
$string['report_updated'] = 'Ripoti imesasishwa.';
$string['reportdeleted'] = 'Ripoti imefutwa.';
$string['reportstatuschanged'] = 'Hali ya ripoti imesasishwa.';
$string['privacy:metadata'] = 'Plugin ya Ripoti za Airpay huhifadhi miundo ya ripoti zilizohifadhiwa, lakini haihamishi data za watumiaji moja kwa moja. Ripoti zinazotengenezwa zinaweza kujumlisha shughuli za watumiaji kutoka kwenye majedwali ya msingi yaliyopo ya Sentientia LMS.';
$string['privacy:metadata:reports'] = 'Miundo ya ripoti iliyohifadhiwa; hurekodi mtumiaji aliyeandaa kila ripoti';
$string['privacy:metadata:reports:created_by'] = 'Mtumiaji aliyeunda ripoti';
$string['privacy:metadata:reports:name'] = 'Jina la ripoti';
$string['privacy:metadata:reports:report_type'] = 'Aina ya ripoti';
$string['privacy:metadata:reports:timecreated'] = 'Wakati ripoti ilipoundwa';
$string['report_type_training_transcript'] = 'Rekodi ya Mafunzo — rekodi za mafunzo ya awali (zilizoingizwa)';
$string['report_col_name'] = 'Jina';
$string['report_col_empid'] = 'Namba ya Mfanyakazi';
$string['report_col_training'] = 'Mafunzo';
$string['report_col_type'] = 'Aina';
$string['report_col_completed'] = 'Imekamilika';
$string['report_col_status'] = 'Hali';
$string['report_col_statusraw'] = 'Hali kama ilivyoingizwa';
$string['report_col_score'] = 'Alama';
$string['report_col_hours'] = 'Saa';
$string['report_col_logindays'] = 'Siku za kuingia (zilizoingizwa)';
$string['report_status_completed'] = 'Imekamilika';
$string['report_status_inprogress'] = 'Inaendelea';
$string['report_status_failed'] = 'Hakufaulu';
$string['report_status_notstarted'] = 'Haijaanza';
$string['report_status_cancelled'] = 'Imeghairiwa';
$string['report_status_unknown'] = 'Haijulikani';
$string['report_sum_records'] = 'Jumla ya Rekodi';
$string['report_sum_completed'] = 'Zilizokamilika';
$string['report_sum_hours'] = 'Jumla ya saa';
