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
 * Marathi strings for local_sentientia_manager.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_manager
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia मॅनेजर डॅशबोर्ड';
$string['myteam'] = 'माझी टीम';
$string['teamlearning'] = 'टीम लर्निंग डॅशबोर्ड';
$string['emptyteam_title'] = 'तुम्हाला अजून कोणतेही टीम मेंबर असाइन केलेले नाहीत';
$string['emptyteam_message'] = 'डायरेक्ट रिपोर्ट्स यूजर प्रोफाइलवरील सुपरवायझर फील्डद्वारे असाइन केले जातात.';
$string['privacy:metadata'] = 'मॅनेजर डॅशबोर्ड प्लगिन कम्प्लायन्स / ऑडिटसाठी मॅनेजर्सनी केलेल्या एनरोलमेंट रिक्वेस्ट्स + कोर्स अलोकेशन्सची नोंद ठेवतो.';
$string['sentientia_manager:view'] = 'टीम डॅशबोर्ड + रिक्वेस्ट्स + अलोकेशन्स पहा';
$string['sentientia_manager:approve'] = 'एनरोलमेंट रिक्वेस्ट्स अप्रूव्ह / रिजेक्ट करा';
$string['sentientia_manager:allocate'] = 'डायरेक्ट रिपोर्ट्सना कोर्स असाइन करा';
$string['messageprovider:request_decided'] = 'तुमच्या एनरोलमेंट रिक्वेस्टचा निकाल';
$string['messageprovider:allocation_assigned'] = 'तुमच्या मॅनेजरने असाइन केलेला कोर्स';
$string['duplicaterequest'] = 'या कोर्ससाठी तुमची एक रिक्वेस्ट आधीच पेंडिंग आहे.';
$string['alreadydecided'] = 'या रिक्वेस्टवर आधीच निर्णय झाला आहे.';
$string['notdirectreport'] = 'निवडलेला यूजर तुमचा डायरेक्ट रिपोर्ट नाही.';
$string['duplicateallocation'] = 'या यूजरकडे त्या कोर्सचे अलोकेशन आधीच आहे.';
$string['manualenrolnotavailable'] = 'या कोर्समध्ये मॅन्युअल एनरोलमेंट एनेबल केलेले नाही. कोर्सच्या एनरोलमेंट मेथड्समध्ये ते कॉन्फिगर करा.';
$string['filterstoolong'] = 'फिल्टर डेटा 4 KB मर्यादेपेक्षा जास्त आहे.';
$string['error_cannotviewmember'] = 'माफ करा, सध्या तुम्हाला या टीम मेंबरला पाहण्याची परमिशन नाही. तुम्ही फक्त तुमचे डायरेक्ट रिपोर्ट्स आणि तुमच्या रिपोर्टिंग लाइनमध्ये तुम्हाला रिपोर्ट करणारे लोकच पाहू शकता.';
$string['privacy:metadata:requests'] = 'मॅनेजरच्या अप्रूव्हलची वाट पाहणाऱ्या लर्नर्सच्या एनरोलमेंट रिक्वेस्ट्स.';
$string['privacy:metadata:requests:userid'] = 'रिक्वेस्ट करणारा लर्नर.';
$string['privacy:metadata:requests:courseid'] = 'ज्या कोर्ससाठी रिक्वेस्ट केली आहे तो कोर्स.';
$string['privacy:metadata:requests:managerid'] = 'निर्णय घेण्यासाठी असाइन केलेला मॅनेजर.';
$string['privacy:metadata:requests:status'] = 'प्रलंबित | मंजूर | नामंजूर | रद्द.';
$string['privacy:metadata:requests:reason'] = 'लर्नरला हा कोर्स का हवा आहे (फ्री टेक्स्ट).';
$string['privacy:metadata:requests:decision_reason'] = 'अप्रूव्ह/रिजेक्ट करताना मॅनेजरची नोट.';
$string['privacy:metadata:requests:decided_by'] = 'अप्रूव्ह/रिजेक्ट क्लिक करणाऱ्या यूजरचा ID.';
$string['privacy:metadata:requests:decided_at'] = 'निर्णय कधी घेतला गेला.';
$string['privacy:metadata:requests:timecreated'] = 'रिक्वेस्ट कधी सबमिट केली गेली.';
$string['privacy:metadata:allocations'] = 'डायरेक्ट रिपोर्ट्सना मॅनेजरने केलेली कोर्स अलोकेशन्स.';
$string['privacy:metadata:allocations:managerid'] = 'अलोकेशन तयार करणारा मॅनेजर.';
$string['privacy:metadata:allocations:userid'] = 'ज्या लर्नरला कोर्स असाइन केला आहे तो लर्नर.';
$string['privacy:metadata:allocations:courseid'] = 'अलोकेट केलेला कोर्स.';
$string['privacy:metadata:allocations:due_date'] = 'ऐच्छिक डेडलाइन.';
$string['privacy:metadata:allocations:status'] = 'असाइन केले | सुरू आहे | पूर्ण | मुदत संपली | रद्द.';
$string['privacy:metadata:allocations:note'] = 'अलोकेशनसोबत जोडलेली मॅनेजरची नोट.';
$string['privacy:metadata:allocations:timecreated'] = 'अलोकेशन कधी तयार केले गेले.';
