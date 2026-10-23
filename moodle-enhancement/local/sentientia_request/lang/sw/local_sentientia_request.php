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
 * Swahili strings for local_sentientia_request.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Maombi ya Kozi ya Sentientia';
$string['myrequests'] = 'Maombi yangu ya kozi';
$string['pendingapprovals'] = 'Idhini zinazosubiri';
$string['allrequests'] = 'Maombi yote';
$string['sentientia_request:request'] = 'Kuomba kujiandikisha kwenye kozi zenye vikwazo';
$string['sentientia_request:approve'] = 'Kuidhinisha / kukataa maombi ya kozi';
$string['sentientia_request:viewall'] = 'Kuona maombi yote ndani ya tenant';
$string['sentientia_request:overrideroute'] = 'Kubatilisha mpangilio wa uidhinishaji';
$string['requestcourse'] = 'Omba ufikiaji';
$string['requestcourse_long'] = 'Omba kujiandikisha';
$string['requestpending_short'] = 'Ombi linasubiri';
$string['approve'] = 'Idhinisha';
$string['reject'] = 'Kataa';
$string['cancel_request'] = 'Ghairi ombi';
$string['reason'] = 'Sababu';
$string['reason_help'] = 'Mweleze mwidhinishaji kwa nini unahitaji kozi hii (angalau herufi 200).';
$string['decision_note'] = 'Maelezo ya uamuzi';
$string['decision_note_help'] = 'Yanaonekana kwa mwombaji. Yanahitajika unapokataa ombi.';
$string['status_pending'] = 'Linasubiri';
$string['status_approved'] = 'Limeidhinishwa';
$string['status_rejected'] = 'Limekataliwa';
$string['status_cancelled'] = 'Limeghairiwa';
$string['status_expired'] = 'Muda umeisha lenyewe';
$string['sla_due_in'] = 'Linatakiwa ndani ya {$a}';
$string['sla_overdue'] = 'Muda umepita';
$string['sla_decided'] = 'Limeamuliwa';
$string['sla_48h'] = 'SLA ya saa 48';
$string['sla_escalated'] = 'Limepandishwa ngazi';
$string['route_manager'] = 'Idhini ya meneja';
$string['route_courseowner'] = 'Idhini ya mmiliki wa kozi';
$string['route_admin'] = 'Idhini ya admin wa tovuti';
$string['messageprovider:request_submitted'] = 'Ombi limewasilishwa (mwombaji)';
$string['messageprovider:request_pending'] = 'Ombi linahitaji idhini yako (mwidhinishaji)';
$string['messageprovider:request_decided'] = 'Uamuzi wa ombi (mwombaji)';
$string['messageprovider:request_escalated'] = 'Ombi limepandishwa ngazi baada ya SLA kupita';
$string['error_reasonshort'] = 'Sababu lazima iwe na angalau herufi 20.';
$string['error_alreadyenrolled'] = 'Tayari umejiandikisha kwenye kozi hii au njia hii ya kujifunza.';
$string['error_alreadyrequested'] = 'Tayari una ombi linalosubiri kwa kipengele hiki.';
$string['error_courseunavailable'] = 'Kozi hii haipatikani kwa maombi.';
$string['error_invalidstate'] = 'Hali ya ombi si sahihi kwa kitendo hiki.';
$string['error_outoftenant'] = 'Kitendo hiki hakiruhusiwi kati ya tenant tofauti.';
$string['error_path_inactive'] = 'Njia hii ya kujifunza haiko hai na haiwezi kuombwa.';
$string['settings_sla_hours'] = 'Saa za SLA kabla ya kupandishwa ngazi';
$string['settings_sla_hours_desc'] = 'Muda ambao mwidhinishaji anao kabla ombi halijapandishwa ngazi lenyewe.';
$string['settings_default_approver'] = 'Mwidhinishaji chaguo-msingi pasipo meneja';
$string['settings_default_approver_desc'] = 'ID ya mtumiaji anayepokea maombi pale mwombaji hana meneja aliyepangiwa wala mmiliki wa kozi.';
$string['settings_auto_expire_days'] = 'Muda uishe wenyewe baada ya siku N';
$string['settings_auto_expire_days_desc'] = 'Maombi yanayosubiri kwa idadi hii ya siku yanaisha muda yenyewe. Weka 0 kuzima.';
$string['no_requests'] = 'Bado hakuna maombi.';
$string['no_pending'] = 'Hakuna idhini zinazosubiri — umemaliza kila kitu!';
$string['request_table_col_course'] = 'Kozi';
$string['request_table_col_requester'] = 'Mwombaji';
$string['request_table_col_status'] = 'Hali';
$string['request_table_col_requested'] = 'Tarehe ya ombi';
$string['request_table_col_decided'] = 'Tarehe ya uamuzi';
$string['request_table_col_approver'] = 'Mwidhinishaji';
$string['request_table_col_actions'] = 'Vitendo';
$string['privacy:metadata:local_sentientia_request'] = 'Maombi ya kujiandikisha kwenye kozi';
$string['privacy:metadata:local_sentientia_request:userid'] = 'Mtumiaji aliyetuma ombi';
$string['privacy:metadata:local_sentientia_request:courseid'] = 'Kozi iliyoombwa';
$string['privacy:metadata:local_sentientia_request:reason'] = 'Sababu iliyoandikwa na mwombaji';
$string['privacy:metadata:local_sentientia_request:decision_note'] = 'Maelezo ya uamuzi ya mwidhinishaji';
$string['privacy:metadata:local_sentientia_request:approver_userid'] = 'Mtumiaji aliyeamua ombi';
$string['privacy:metadata:local_sentientia_request:status'] = 'Linasubiri / limeidhinishwa / limekataliwa / n.k.';
$string['privacy:metadata:local_sentientia_request:timecreated'] = 'Muda ombi lilipotumwa';
$string['col_item'] = 'Kipengele';
$string['item_deleted_course'] = '(kozi iliyofutwa)';
$string['item_deleted'] = '(kipengele kilichofutwa)';
$string['item_certification'] = 'Cheti (mfumo wa awali, id {$a})';
$string['reason_imported'] = 'Imeletwa kutoka mfumo wa awali - hakuna sababu iliyorekodiwa.';
$string['sla_none'] = 'Hakuna tarehe ya mwisho';
$string['route_legacy'] = 'Imeletwa kutoka mfumo wa awali';
$string['privacy:metadata:local_sentientia_request:item_type'] = 'Aina ya kipengele kilichoombwa: kozi, njia ya kujifunza, darasa, programu au cheti';
$string['privacy:metadata:local_sentientia_request:itemid'] = 'id ya kipengele kilichoombwa';
$string['privacy:metadata:local_sentientia_request:decided_by_userid'] = 'Mtumiaji aliyeamua ombi hasa';
$string['event_request_submitted'] = 'Ombi la ufikiaji limewasilishwa';
$string['event_request_approved'] = 'Ombi la ufikiaji limeidhinishwa';
$string['event_request_rejected'] = 'Ombi la ufikiaji limekataliwa';
