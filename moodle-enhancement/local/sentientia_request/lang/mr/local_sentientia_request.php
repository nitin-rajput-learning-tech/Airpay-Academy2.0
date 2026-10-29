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
 * Marathi strings for local_sentientia_request.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia कोर्स विनंत्या';
$string['myrequests'] = 'माझ्या कोर्स विनंत्या';
$string['pendingapprovals'] = 'प्रलंबित मंजुरी';
$string['allrequests'] = 'सर्व विनंत्या';
$string['sentientia_request:request'] = 'प्रतिबंधित कोर्सेसमध्ये एनरोलमेंटसाठी विनंती करा';
$string['sentientia_request:approve'] = 'कोर्स विनंत्या मंजूर करा / नाकारा';
$string['sentientia_request:viewall'] = 'संपूर्ण टेनंटमधील सर्व विनंत्या पाहा';
$string['sentientia_request:overrideroute'] = 'मंजुरीचे रूटिंग ओव्हरराइड करा';
$string['requestcourse'] = 'ॲक्सेसची विनंती करा';
$string['requestcourse_long'] = 'एनरोलमेंटची विनंती करा';
$string['requestpending_short'] = 'विनंती प्रलंबित';
$string['approve'] = 'मंजूर करा';
$string['reject'] = 'नाकारा';
$string['cancel_request'] = 'विनंती रद्द करा';
$string['reason'] = 'कारण';
$string['reason_help'] = 'तुम्हाला हा कोर्स का हवा आहे ते मंजुरी देणाऱ्यांना सांगा (किमान 20 अक्षरे).';
$string['decision_note'] = 'निर्णयाची टीप';
$string['decision_note_help'] = 'विनंती करणाऱ्यांना दिसेल. नाकारताना आवश्यक.';
$string['status_pending'] = 'प्रलंबित';
$string['status_approved'] = 'मंजूर';
$string['status_rejected'] = 'नाकारले';
$string['status_cancelled'] = 'रद्द';
$string['status_expired'] = 'आपोआप मुदत संपली';
$string['sla_due_in'] = '{$a} बाकी';
$string['sla_overdue'] = 'ओव्हरड्यू';
$string['sla_decided'] = 'निर्णय झाला';
$string['sla_48h'] = '48 तास SLA';
$string['sla_escalated'] = 'एस्कलेट केले';
$string['route_manager'] = 'मॅनेजरची मंजुरी';
$string['route_courseowner'] = 'कोर्स मालकाची मंजुरी';
$string['route_admin'] = 'साइट ॲडमिनची मंजुरी';
$string['messageprovider:request_submitted'] = 'विनंती सबमिट केली (विनंती करणारे)';
$string['messageprovider:request_pending'] = 'विनंतीला तुमची मंजुरी हवी आहे (मंजुरी देणारे)';
$string['messageprovider:request_decided'] = 'विनंतीवरील निर्णय (विनंती करणारे)';
$string['messageprovider:request_escalated'] = 'SLA ओलांडल्याने विनंती एस्कलेट झाली';
$string['error_reasonshort'] = 'कारण किमान 20 अक्षरांचे असावे.';
$string['error_alreadyenrolled'] = 'तुम्ही या कोर्समध्ये किंवा लर्निंग पाथमध्ये आधीच एनरोल आहात.';
$string['error_alreadyrequested'] = 'या आयटमसाठी तुमची विनंती आधीच प्रलंबित आहे.';
$string['error_courseunavailable'] = 'हा कोर्स विनंतीसाठी उपलब्ध नाही.';
$string['error_invalidstate'] = 'या ॲक्शनसाठी विनंतीचा स्टेटस चुकीचा आहे.';
$string['error_outoftenant'] = 'ही ॲक्शन वेगवेगळ्या टेनंट्समध्ये करण्याची परवानगी नाही.';
$string['error_path_inactive'] = 'हा लर्निंग पाथ ॲक्टिव्ह नाही, त्यामुळे त्याची विनंती करता येणार नाही.';
$string['settings_sla_hours'] = 'एस्कलेशनपूर्वीचे SLA तास';
$string['settings_sla_hours_desc'] = 'विनंती आपोआप एस्कलेट होण्यापूर्वी मंजुरी देणाऱ्यांकडे किती वेळ असेल.';
$string['settings_default_approver'] = 'मॅनेजर नसल्यास डीफॉल्ट मंजुरी देणारे';
$string['settings_default_approver_desc'] = 'विनंती करणाऱ्यांना मॅनेजर नेमलेला नसेल आणि कोर्स मालकही नसेल, तेव्हा विनंत्या मिळणाऱ्या युजरचा ID.';
$string['settings_auto_expire_days'] = 'N दिवसांनंतर आपोआप मुदत संपेल';
$string['settings_auto_expire_days_desc'] = 'इतके दिवस प्रलंबित असलेल्या विनंत्यांची मुदत आपोआप संपते. बंद करण्यासाठी 0 सेट करा.';
$string['no_requests'] = 'अजून एकही विनंती नाही.';
$string['no_pending'] = 'कोणतीही मंजुरी प्रलंबित नाही — सगळं काम पूर्ण!';
$string['request_table_col_course'] = 'कोर्स';
$string['request_table_col_requester'] = 'विनंती करणारे';
$string['request_table_col_status'] = 'स्टेटस';
$string['request_table_col_requested'] = 'विनंतीची तारीख';
$string['request_table_col_decided'] = 'निर्णयाची तारीख';
$string['request_table_col_approver'] = 'मंजुरी देणारे';
$string['request_table_col_actions'] = 'ॲक्शन्स';
$string['privacy:metadata:local_sentientia_request'] = 'कोर्स एनरोलमेंट विनंत्या';
$string['privacy:metadata:local_sentientia_request:userid'] = 'विनंती करणारा युजर';
$string['privacy:metadata:local_sentientia_request:courseid'] = 'विनंती केलेला कोर्स';
$string['privacy:metadata:local_sentientia_request:reason'] = 'विनंती करणाऱ्यांनी लिहिलेले कारण';
$string['privacy:metadata:local_sentientia_request:decision_note'] = 'मंजुरी देणाऱ्यांची निर्णयाची टीप';
$string['privacy:metadata:local_sentientia_request:approver_userid'] = 'विनंतीवर निर्णय घेणारा युजर';
$string['privacy:metadata:local_sentientia_request:status'] = 'प्रलंबित / मंजूर / नाकारले / इत्यादी';
$string['privacy:metadata:local_sentientia_request:timecreated'] = 'विनंती केल्याची वेळ';
$string['col_item'] = 'आयटम';
$string['item_deleted_course'] = '(डिलीट केलेला कोर्स)';
$string['item_deleted'] = '(डिलीट केलेला आयटम)';
$string['item_certification'] = 'सर्टिफिकेशन (मागील सिस्टम, id {$a})';
$string['reason_imported'] = 'मागील सिस्टममधून इम्पोर्ट केले - कोणतेही कारण नोंदवलेले नाही.';
$string['sla_none'] = 'डेडलाइन नाही';
$string['route_legacy'] = 'मागील सिस्टममधून इम्पोर्ट केले';
$string['privacy:metadata:local_sentientia_request:item_type'] = 'विनंती केलेल्या आयटमचा प्रकार: कोर्स, लर्निंग पाथ, क्लासरूम, प्रोग्राम किंवा सर्टिफिकेशन';
$string['privacy:metadata:local_sentientia_request:itemid'] = 'विनंती केलेल्या आयटमचा id';
$string['privacy:metadata:local_sentientia_request:decided_by_userid'] = 'विनंतीवर प्रत्यक्ष निर्णय घेणारा युजर';
$string['event_request_submitted'] = 'ॲक्सेस विनंती सबमिट केली';
$string['event_request_approved'] = 'ॲक्सेस विनंती मंजूर केली';
$string['event_request_rejected'] = 'ॲक्सेस विनंती नाकारली';
