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
 * Marathi strings for local_sentientia_reports.
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

$string['pluginname'] = 'Sentientia रिपोर्ट्स';
$string['sentientia_reports:view'] = 'सेव्ह केलेले रिपोर्ट्स पाहा';
$string['sentientia_reports:manage'] = 'सेव्ह केलेले रिपोर्ट्स मॅनेज करा';
$string['sentientia_reports:export'] = 'सेव्ह केलेले रिपोर्ट्स CSV मध्ये एक्सपोर्ट करा';
$string['addreport'] = 'रिपोर्ट तयार करा';
$string['editreport'] = 'रिपोर्ट एडिट करा';
$string['deletereport'] = 'रिपोर्ट डिलीट करा';
$string['archivereport'] = 'रिपोर्ट आर्काइव्ह करा';
$string['activatereport'] = 'रिपोर्ट ॲक्टिव्हेट करा';
$string['heading_basic'] = 'रिपोर्टची ओळख';
$string['heading_type'] = 'रिपोर्टचा प्रकार';
$string['heading_scope'] = 'संस्थेचा स्कोप';
$string['report_name'] = 'रिपोर्टचे नाव';
$string['description'] = 'वर्णन';
$string['report_type'] = 'रिपोर्टचा प्रकार';
$string['report_type_help'] = 'प्रत्येक बिल्ट-इन प्रकार वेगळी क्वेरी चालवतो. कोर्स कम्प्लीशन प्रत्येक युजरची कोर्सनिहाय प्रगती दाखवतो. कम्प्लायन्स आढावा अनिवार्य ट्रेनिंगचे दर दाखवतो. युजर ॲक्टिव्हिटी लॉगिन एंगेजमेंट ट्रॅक करतो. एनरोलमेंट ट्रेंड दरमहा किती एनरोलमेंट झाले ते दाखवतो.';
$string['organisation'] = 'संस्था (टेनंट स्कोप)';
$string['organisation_help'] = 'रिपोर्ट तुमच्या हायरार्कीमधील एखाद्या विशिष्ट संस्थेपुरता मर्यादित करा. सर्व टेनंट्स समाविष्ट करण्यासाठी "सर्व संस्था" हेच निवडलेले ठेवा.';
$string['status'] = 'स्टेटस';
$string['status_active'] = 'ॲक्टिव्ह';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['name_required'] = 'रिपोर्टचे नाव आवश्यक आहे.';
$string['invalid_report_type'] = 'रिपोर्टचा प्रकार चुकीचा आहे.';
$string['invalidreport'] = 'रिपोर्ट सापडला नाही.';
$string['invalidreporttype'] = 'रिपोर्टचा प्रकार चुकीचा आहे.';
$string['missingrequiredfields'] = 'कृपया सर्व आवश्यक फील्ड्स भरा.';
$string['confirmdelete'] = 'रिपोर्ट "{$a}" डिलीट करायचा? यामुळे सेव्ह केलेली डेफिनिशन कायमची काढली जाईल. आधी तयार झालेल्या CSV एक्सपोर्ट्सवर काही परिणाम होणार नाही. हे पूर्ववत करता येणार नाही.';
$string['confirmarchive'] = '"{$a}" आर्काइव्ह करायचा? तो ॲक्टिव्ह लिस्टमधून लपवला जाईल, पण ऑडिटसाठी ठेवला जाईल. कधीही पुन्हा ॲक्टिव्हेट करता येईल.';
$string['confirmactivate'] = '"{$a}" ॲक्टिव्हेट करायचा? तो मुख्य रिपोर्ट्स लिस्टमध्ये दिसेल आणि गरजेनुसार चालवता येईल.';
$string['report_created'] = 'रिपोर्ट तयार झाला.';
$string['report_updated'] = 'रिपोर्ट अपडेट झाला.';
$string['reportdeleted'] = 'रिपोर्ट डिलीट झाला.';
$string['reportstatuschanged'] = 'रिपोर्टचा स्टेटस अपडेट झाला.';
$string['privacy:metadata'] = 'Airpay रिपोर्ट्स प्लगइन सेव्ह केलेल्या रिपोर्ट डेफिनिशन्स साठवते, पण युजर डेटा थेट एक्सपोर्ट करत नाही. तयार केलेले रिपोर्ट्स सध्याच्या कोर Sentientia LMS टेबल्समधून युजर ॲक्टिव्हिटी एकत्र करू शकतात.';
$string['privacy:metadata:reports'] = 'सेव्ह केलेल्या रिपोर्ट डेफिनिशन्स; प्रत्येक रिपोर्ट कोणत्या युजरने तयार केला याची नोंद ठेवते';
$string['privacy:metadata:reports:created_by'] = 'रिपोर्ट तयार करणारा युजर';
$string['privacy:metadata:reports:name'] = 'रिपोर्टचे नाव';
$string['privacy:metadata:reports:report_type'] = 'रिपोर्टचा प्रकार';
$string['privacy:metadata:reports:timecreated'] = 'रिपोर्ट कधी तयार केला';
$string['report_type_training_transcript'] = 'ट्रेनिंग ट्रान्सक्रिप्ट — आधीचे ट्रेनिंग रेकॉर्ड्स (इम्पोर्ट केलेले)';
$string['report_col_name'] = 'नाव';
$string['report_col_empid'] = 'कर्मचारी ID';
$string['report_col_training'] = 'ट्रेनिंग';
$string['report_col_type'] = 'प्रकार';
$string['report_col_completed'] = 'पूर्ण';
$string['report_col_status'] = 'स्टेटस';
$string['report_col_statusraw'] = 'लोड केल्याप्रमाणे स्टेटस';
$string['report_col_score'] = 'स्कोअर';
$string['report_col_hours'] = 'तास';
$string['report_col_logindays'] = 'लॉगिन दिवस (इम्पोर्ट केलेले)';
$string['report_status_completed'] = 'पूर्ण';
$string['report_status_inprogress'] = 'सुरू आहे';
$string['report_status_failed'] = 'नापास';
$string['report_status_notstarted'] = 'सुरू केले नाही';
$string['report_status_cancelled'] = 'रद्द';
$string['report_status_unknown'] = 'अज्ञात';
$string['report_sum_records'] = 'एकूण रेकॉर्ड्स';
$string['report_sum_completed'] = 'पूर्ण';
$string['report_sum_hours'] = 'एकूण तास';
