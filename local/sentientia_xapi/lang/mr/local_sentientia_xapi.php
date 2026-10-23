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
 * Marathi strings for local_sentientia_xapi.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia xAPI / LRS';
$string['settings_pagetitle'] = 'Sentientia xAPI / LRS सेटिंग्ज';
$string['setting_lrs_token'] = 'LRS Bearer टोकन';
$string['setting_lrs_token_desc'] = 'बाह्य xAPI क्लायंट्सना (उदा. SCORM कंटेंट, cmi5 AU) ऑथेंटिकेट करण्यासाठी सिक्रेट टोकन. किमान 32 अक्षरे. गोपनीय ठेवा — लीक झाल्याचा संशय आल्यास लगेच बदला.';
$string['setting_lrs_basic_user'] = 'LRS basic-auth युजरनेम';
$string['setting_lrs_basic_user_desc'] = 'Bearer टोकनसोबतच /lrs/statements एंडपॉइंट स्वीकारत असलेले पर्यायी HTTP Basic ऑथेंटिकेशन युजरनेम. basic-auth बंद करण्यासाठी रिकामे ठेवा.';
$string['setting_lrs_basic_pass'] = 'LRS basic-auth पासवर्ड';
$string['setting_lrs_basic_pass_desc'] = 'HTTP Basic ऑथेंटिकेशन पासवर्ड (वरील युजरनेमसोबत वापरा). basic-auth बंद करण्यासाठी रिकामे ठेवा.';
$string['setting_retention_days'] = 'स्टेटमेंट ठेवण्याचा कालावधी (दिवस)';
$string['setting_retention_days_desc'] = 'यापेक्षा जास्त दिवस जुनी xAPI स्टेटमेंट्स रात्रीच्या क्लीनअप टास्कद्वारे काढून टाकली जातात. कायमची ठेवण्यासाठी 0 टाका. डिफॉल्ट: 730 (2 वर्षे).';
$string['setting_emit_login'] = 'लॉगिनवर स्टेटमेंट पाठवा';
$string['setting_emit_login_desc'] = 'ऑन असताना, युजर प्रत्येक वेळी लॉगिन करतो तेव्हा "experienced" व्हर्ब असलेले स्टेटमेंट पाठवले जाते. जास्त ट्रॅफिक असलेल्या साइट्स हे बंद करू शकतात.';
$string['setting_lrs_rate_limit'] = 'LRS रेट लिमिट (प्रति विंडो रिक्वेस्ट्स)';
$string['setting_lrs_rate_limit_desc'] = 'एक ऑथेंटिकेटेड क्लायंट (रजिस्टर्ड क्लायंट क्रेडेन्शियल, किंवा साइट-वाइड फॉलबॅक टोकन/basic क्रेडेन्शियल) प्रत्येक विंडोमध्ये जास्तीत जास्त किती /lrs/statements रिक्वेस्ट्स करू शकतो ती संख्या. डिफॉल्ट: 600.';
$string['setting_lrs_rate_window'] = 'LRS रेट लिमिट विंडो (सेकंद)';
$string['setting_lrs_rate_window_desc'] = 'ठराविक रेट-लिमिट विंडोचा कालावधी, सेकंदांमध्ये. डिफॉल्ट: 60 (म्हणजे डिफॉल्टनुसार प्रत्येक क्लायंटसाठी 60 सेकंदांत 600 रिक्वेस्ट्स).';
$string['xapi:viewstatements'] = 'LRS व्ह्यूअरमध्ये xAPI स्टेटमेंट्स पाहा';
$string['xapi:deletestatements'] = 'LRS मधून xAPI स्टेटमेंट्स डिलीट करा';
$string['xapi:managelrs'] = 'LRS सेटिंग्ज आणि क्रेडेन्शियल्स मॅनेज करा';
$string['error_lrs_disabled'] = 'xAPI LRS सध्या बंद आहे. तुमच्या ॲडमिनशी संपर्क साधा.';
$string['error_lrs_auth'] = 'ऑथेंटिकेशन अयशस्वी झाले. वैध Bearer टोकन किंवा Basic क्रेडेन्शियल्स द्या.';
$string['error_lrs_invalid_json'] = 'रिक्वेस्ट बॉडी वैध JSON नाही.';
$string['error_lrs_invalid_stmt'] = 'स्टेटमेंट व्हॅलिडेशन अयशस्वी: {$a}';
$string['error_lrs_tenant'] = 'टेनंट ओळखता आला नाही. ॲक्टर अकाउंटचे homePage एखाद्या रजिस्टर्ड टेनंटशी जुळते याची खात्री करा.';
$string['error_lrs_method'] = 'या एंडपॉइंटवर ही HTTP मेथड सपोर्टेड नाही.';
$string['error_lrs_not_found'] = 'स्टेटमेंट सापडले नाही.';
$string['error_lrs_ratelimited'] = 'रेट लिमिट ओलांडली: या क्लायंटसाठी प्रति विंडो {$a} रिक्वेस्ट्सची मर्यादा आहे. Retry-After कालावधीनंतर पुन्हा प्रयत्न करा.';
$string['verb_completed'] = 'पूर्ण केले';
$string['verb_experienced'] = 'अनुभवले';
$string['verb_passed'] = 'पास झाले';
$string['verb_failed'] = 'नापास झाले';
$string['verb_attempted'] = 'प्रयत्न केला';
$string['verb_answered'] = 'उत्तर दिले';
$string['verb_launched'] = 'लाँच केले';
$string['verb_initialized'] = 'सुरू केले';
$string['verb_terminated'] = 'संपवले';
$string['verb_suspended'] = 'थांबवले';
$string['verb_resumed'] = 'पुन्हा सुरू केले';
$string['verb_satisfied'] = 'पूर्तता केली';
$string['validate_actor_required'] = 'ॲक्टर आवश्यक आहे.';
$string['validate_actor_missing_objecttype'] = 'ॲक्टरचा objectType Agent किंवा Group असणे आवश्यक आहे.';
$string['validate_actor_missing_ifi'] = 'ॲक्टरकडे नेमका एकच IFI (mbox, mbox_sha1sum, openid, किंवा account) असणे आवश्यक आहे.';
$string['validate_actor_mbox_format'] = 'ॲक्टर mbox हा mailto: URI असला पाहिजे.';
$string['validate_actor_account_missing'] = 'ॲक्टर account मध्ये homePage आणि name असणे आवश्यक आहे.';
$string['validate_verb_required'] = 'व्हर्ब आवश्यक आहे.';
$string['validate_verb_id_required'] = 'व्हर्बला id (IRI) असणे आवश्यक आहे.';
$string['validate_verb_id_iri'] = 'व्हर्ब id वैध IRI असला पाहिजे.';
$string['validate_object_required'] = 'ऑब्जेक्ट आवश्यक आहे.';
$string['validate_object_id_required'] = 'ऑब्जेक्टला id (IRI) असणे आवश्यक आहे.';
$string['validate_object_id_iri'] = 'ऑब्जेक्ट id वैध IRI असला पाहिजे.';
$string['validate_result_score_range'] = 'रिझल्ट स्कोअरचे scaled मूल्य -1.0 आणि 1.0 च्या दरम्यान असले पाहिजे.';
$string['validate_result_score_raw_max'] = 'रिझल्ट स्कोअरचे raw मूल्य max पेक्षा जास्त नसावे.';
$string['validate_context_registration_uuid'] = 'कॉन्टेक्स्ट registration वैध UUID असला पाहिजे.';
$string['validate_timestamp_format'] = 'टाइमस्टॅम्प वैध ISO 8601 तारीख-वेळ स्ट्रिंग असली पाहिजे.';
$string['validate_id_uuid'] = 'स्टेटमेंट id वैध UUID असला पाहिजे.';
$string['cmi5_session'] = 'cmi5 सेशन';
$string['cmi5_session_initialized'] = 'सेशन सुरू झाले';
$string['cmi5_session_terminated'] = 'सेशन संपले';
$string['cmi5_au_passed'] = 'असाइनेबल युनिट पास झाले';
$string['cmi5_au_failed'] = 'असाइनेबल युनिट नापास झाले';
$string['cmi5_au_completed'] = 'असाइनेबल युनिट पूर्ण झाले';
$string['lrs_viewer_title'] = 'xAPI स्टेटमेंट व्ह्यूअर';
$string['lrs_viewer_heading'] = 'LRS — अलीकडील स्टेटमेंट्स';
$string['lrs_col_timestamp'] = 'टाइमस्टॅम्प';
$string['lrs_col_actor'] = 'ॲक्टर';
$string['lrs_col_verb'] = 'व्हर्ब';
$string['lrs_col_object'] = 'ऑब्जेक्ट';
$string['lrs_col_score'] = 'स्कोअर';
$string['lrs_col_success'] = 'यश';
$string['lrs_col_tenant'] = 'टेनंट';
$string['lrs_no_statements'] = 'अजून कोणतेही स्टेटमेंट रेकॉर्ड झालेले नाही.';
$string['lrs_endpoint_label'] = 'LRS एंडपॉइंट URL';
$string['lrs_endpoint_desc'] = 'तुमच्या xAPI / cmi5 कंटेंटमध्ये किंवा LRS क्लायंटमध्ये हा URL LRS एंडपॉइंट म्हणून वापरा.';
$string['privacy:metadata:local_sentientia_xapi_statements'] = 'या युजरने किंवा या युजरसाठी पाठवलेली xAPI स्टेटमेंट्स.';
$string['privacy:metadata:local_sentientia_xapi_statements:actorid'] = 'xAPI ॲक्टरशी लिंक असलेला Moodle युजर id.';
$string['privacy:metadata:local_sentientia_xapi_statements:actor'] = 'JSON ॲक्टर ऑब्जेक्ट (यात ईमेल किंवा अकाउंट आयडेंटिफायर असू शकतो).';
$string['privacy:metadata:local_sentientia_xapi_statements:verb'] = 'xAPI व्हर्ब IRI.';
$string['privacy:metadata:local_sentientia_xapi_statements:object'] = 'JSON ऑब्जेक्ट (ॲक्टिव्हिटी, एजंट, इ.).';
$string['privacy:metadata:local_sentientia_xapi_statements:result'] = 'JSON रिझल्ट (स्कोअर, यश, पूर्णता).';
$string['privacy:metadata:local_sentientia_xapi_statements:context'] = 'JSON कॉन्टेक्स्ट.';
$string['privacy:metadata:local_sentientia_xapi_statements:timestored'] = 'LRS मध्ये स्टेटमेंट सेव्ह झाल्याचा टाइमस्टॅम्प.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions'] = 'cmi5 सेशन ट्रॅकिंग रेकॉर्ड्स.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid'] = 'Moodle युजर id.';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration'] = 'cmi5 रजिस्ट्रेशन UUID.';
$string['task_purge_old_statements'] = 'जुनी xAPI स्टेटमेंट्स काढून टाका';
