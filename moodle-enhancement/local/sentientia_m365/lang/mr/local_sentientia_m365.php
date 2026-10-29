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
 * Marathi strings for local_sentientia_m365.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_m365
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Microsoft 365';
$string['sentientia_m365:use'] = 'Microsoft 365 अकाउंट कनेक्ट करा आणि Sentientia LMS द्वारे तुमचा स्वतःचा M365 डेटा वाचा';
$string['sentientia_m365:admin'] = 'Sentientia Microsoft 365 इंटिग्रेशन मॅनेज करा (टेनंट कॉन्फिग, कोणत्याही युजरचा ॲक्सेस रद्द करा)';
$string['confirm_required'] = 'हा Microsoft 365 कॉल चालण्यापूर्वी ॲडमिनने तो स्पष्टपणे कन्फर्म करणे आवश्यक आहे. हे फीचर स्कॅफोल्ड मोडमध्ये आहे.';
$string['feature_off'] = 'Sentientia Microsoft 365 इंटिग्रेशन ॲडमिनने बंद केले आहे (sentientia_m365_enabled OFF आहे).';
$string['error_not_configured'] = 'Microsoft 365 इंटिग्रेशन कॉन्फिगर केलेले नाही. कनेक्ट करण्यापूर्वी Azure टेनंट ID, क्लायंट ID आणि रीडायरेक्ट URI सेट करा.';
$string['error_empty_token'] = 'रिकामा ॲक्सेस किंवा रिफ्रेश टोकन सेव्ह करता येत नाही.';
$string['error_token_decrypt'] = 'सेव्ह केलेला टोकन डिक्रिप्ट करता आला नाही. तुमचे Microsoft 365 अकाउंट डिस्कनेक्ट करून पुन्हा कनेक्ट करा.';
$string['error_invalid_state'] = 'OAuth state पॅरामीटर जुळत नाही. कृपया कनेक्शन पुन्हा सुरू करा.';
$string['error_missing_code'] = 'Microsoft 365 ने ऑथरायझेशन कोड पाठवला नाही. कनेक्शन रद्द केले आहे.';
$string['error_scope_required'] = 'या ऑपरेशनसाठी {$a} स्कोप लागतो, पण तो दिलेला नाही. पुन्हा कनेक्ट करा आणि अतिरिक्त स्कोप द्या.';
$string['settings_heading_azure'] = 'Azure AD ॲप्लिकेशन';
$string['settings_heading_azure_desc'] = 'Azure Portal मध्ये Microsoft Entra ID अंतर्गत एक ॲप्लिकेशन रजिस्टर करा. रीडायरेक्ट URI खाली तुम्ही सेट केलेल्या व्हॅल्यूशी तंतोतंत जुळली पाहिजे.';
$string['setting_azure_tenant_id'] = 'Azure टेनंट ID';
$string['setting_azure_tenant_id_desc'] = 'हे इंटिग्रेशन वापरत असलेल्या Microsoft Entra (Azure AD) टेनंटचा GUID. कोणतेही Microsoft अकाउंट (ऑफिस, शाळा किंवा वैयक्तिक) कनेक्ट करता यावे असे हवे असेल तरच "common" ही व्हॅल्यू वापरा.';
$string['setting_azure_client_id'] = 'Azure ॲप्लिकेशन (क्लायंट) ID';
$string['setting_azure_client_id_desc'] = 'तुमच्या Azure टेनंटमध्ये Sentientia LMS चे प्रतिनिधित्व करणाऱ्या ॲप्लिकेशन रजिस्ट्रेशनचा GUID.';
$string['setting_redirect_uri'] = 'OAuth रीडायरेक्ट URI';
$string['setting_redirect_uri_desc'] = 'Sentientia LMS कॉलबॅक एंडपॉइंटची पूर्ण URL (उदा. https://your.sentientia.example/local/sentientia_m365/callback.php). ही Azure ॲप्लिकेशन रजिस्ट्रेशनमध्ये रीडायरेक्ट URI म्हणून जोडलेली असली पाहिजे.';
$string['setting_allowed_scopes'] = 'परवानगी असलेले OAuth स्कोप';
$string['setting_allowed_scopes_desc'] = 'कनेक्ट करताना युजर मागू शकतो असे OAuth स्कोप. डीफॉल्ट सेट (openid, profile, offline_access, User.Read) नेहमी दिला जातो; इथे निवडलेले अतिरिक्त स्कोप ऐच्छिक होतात.';
$string['scope_sites_read_all'] = 'SharePoint — युजरला दिसणाऱ्या सर्व साइट्स वाचा (Sites.Read.All)';
$string['scope_files_read_all'] = 'SharePoint / OneDrive — युजरला दिसणाऱ्या सर्व फाइल्स वाचा (Files.Read.All)';
$string['scope_calendars_read'] = 'Outlook — साइन-इन केलेल्या युजरची कॅलेंडर्स वाचा (Calendars.Read)';
$string['scope_calendars_readwrite'] = 'Outlook — साइन-इन केलेल्या युजरची कॅलेंडर्स वाचा + लिहा (Calendars.ReadWrite)';
$string['scope_team_member_read_all'] = 'Teams — साइन-इन केलेल्या युजरचे टीम सदस्यत्व वाचा (TeamMember.Read.All)';
$string['scope_mail_read'] = 'Outlook — साइन-इन केलेल्या युजरचा मेलबॉक्स वाचा (Mail.Read)';
$string['privacy:metadata:tokens'] = 'Moodle युजरशी लिंक असलेले Microsoft 365 OAuth टोकन. डेटाबेसमध्ये जाण्यापूर्वीच टोकन सर्व्हरच्या Sodium की ने एन्क्रिप्ट केले जातात, तरीही सायफरटेक्स्ट वैयक्तिक डेटाच मानला जातो, कारण डिक्रिप्ट केल्यावर तो युजरच्या Microsoft अकाउंटचा ॲक्सेस देतो.';
$string['privacy:metadata:tokens:userid'] = 'हा टोकन ज्या Moodle युजरचा आहे तो युजर.';
$string['privacy:metadata:tokens:customerid'] = 'ज्या Sentientia कस्टमर स्कोपमध्ये कनेक्शन केले गेले तो स्कोप.';
$string['privacy:metadata:tokens:access_token_enc'] = 'एन्क्रिप्ट केलेला, कमी काळ चालणारा Microsoft ॲक्सेस टोकन. कधीही प्लेनटेक्स्टमध्ये एक्सपोर्ट होत नाही.';
$string['privacy:metadata:tokens:refresh_token_enc'] = 'एन्क्रिप्ट केलेला, दीर्घ काळ चालणारा Microsoft रिफ्रेश टोकन. कधीही प्लेनटेक्स्टमध्ये एक्सपोर्ट होत नाही.';
$string['privacy:metadata:tokens:expires'] = 'ॲक्सेस टोकनची मुदत संपण्याचा Unix टाइमस्टॅम्प.';
$string['privacy:metadata:tokens:scopes'] = 'युजरने संमती दिलेले OAuth स्कोप.';
$string['privacy:metadata:tokens:timecreated'] = 'कनेक्शन पहिल्यांदा केव्हा झाले.';
$string['privacy:metadata:tokens:timemodified'] = 'टोकन शेवटचे केव्हा रिफ्रेश केले किंवा बदलले गेले.';
$string['privacy:metadata:microsoft_graph'] = 'Sentientia LMS चे एखादे फीचर युजरच्या वतीने M365 डेटा वाचते तेव्हा प्रत्येक वेळी Microsoft Graph ला युजरचा ॲक्सेस टोकन मिळतो. युजरचा आयडेंटिटी क्लेम बाहेर जातो; Microsoft Graph कडून आलेले रिस्पॉन्स (प्रोफाइल फील्ड्स, कॅलेंडर इव्हेंट्स, SharePoint मेटाडेटा) LMS कडे परत येतात.';
$string['privacy:metadata:microsoft_graph:userid'] = 'ज्या Moodle युजरच्या वतीने कॉल केला जातो तो युजर (एन्क्रिप्ट केलेला टोकन शोधण्यासाठी वापरला जातो).';
$string['privacy:metadata:microsoft_graph:scopes'] = 'ॲक्सेस टोकनला दिलेले OAuth स्कोप — कोणते Graph एंडपॉइंट कॉल करता येतील हे यावरून ठरते.';
$string['admin_index_title'] = 'Microsoft 365';
$string['admin_index_intro'] = 'Microsoft 365 टेनंटशी Sentientia LMS चे कनेक्शन कॉन्फिगर आणि मॉनिटर करा. फेज C.1 मध्ये OAuth स्कॅफोल्ड येतो; लाइव्ह Graph कॉल्स फेज C.2 मध्ये येतील.';
$string['admin_index_flag_off_notice'] = 'Microsoft 365 फीचर फ्लॅग (sentientia_m365_enabled) सध्या OFF आहे. OAuth स्कॅफोल्ड तयार आहे, पण कोणताही Graph एंडपॉइंट वापरता येत नाही.';
$string['admin_index_phase_c1_notice'] = 'फेज C.1 confirm-required मोडमध्ये येतो. graph_client ला केलेला प्रत्येक कॉल moodle_exception("confirm_required") थ्रो करतो — हे मुद्दामच आहे. फेज C.2 मध्ये स्टब्सऐवजी, प्रत्येक कॉलसाठी [CONFIRM] गेट असलेले खरे Graph कॉल्स येतील.';
$string['admin_index_readiness'] = 'MSAL क्लायंट तयारी';
$string['admin_index_ready_yes'] = 'msal_client READY दाखवत आहे — Azure टेनंट + क्लायंट + रीडायरेक्ट URI कॉन्फिगर आहेत आणि नीट वाचता येतात.';
$string['admin_index_ready_no'] = 'msal_client NOT READY दाखवत आहे — Azure टेनंट ID, क्लायंट ID किंवा रीडायरेक्ट URI पैकी किमान एक सेट केलेले नाही किंवा नीट वाचता येत नाही. कॉन्फिगरेशन पूर्ण करण्यासाठी सेटिंग्जमध्ये जा.';
$string['admin_index_roadmap'] = 'रोडमॅप';
$string['admin_index_quicknav'] = 'क्विक नॅव्हिगेशन';
$string['admin_index_link_settings'] = 'Azure & OAuth सेटिंग्ज';
$string['admin_index_link_settings_desc'] = 'Azure टेनंट ID, ॲप्लिकेशन क्लायंट ID, रीडायरेक्ट URI आणि ऐच्छिक स्कोपचा मल्टीसिलेक्ट.';
$string['admin_index_link_privacy'] = 'गोपनीयता आणि डेटा सब्जेक्टचे हक्क';
$string['admin_index_link_privacy_desc'] = 'Moodle साइट ॲडमिनिस्ट्रेशन → गोपनीयता आणि धोरणे. M365 टोकन स्टँडर्ड डेटा एक्सपोर्ट आणि डेटा मिटवण्याच्या प्रक्रियांमध्ये समाविष्ट आहेत.';
$string['admin_index_footnote'] = 'हे पेज कॉन्फिगरेशन व्हॅल्यूज वाचते आणि एन्क्रिप्ट केलेल्या टोकनच्या रो मोजते. हे Microsoft Graph ला कॉल करत नाही आणि रनटाइममध्ये कोणताही टोकन डिक्रिप्ट करत नाही.';
$string['stats_configured'] = 'कॉन्फिगर केले';
$string['stats_unconfigured'] = 'सेट केलेले नाही';
$string['stats_tenant_id'] = 'Azure टेनंट ID';
$string['stats_client_id'] = 'Azure क्लायंट ID';
$string['stats_flag_label'] = 'फीचर फ्लॅग';
$string['stats_flag_on'] = 'चालू';
$string['stats_flag_off'] = 'बंद';
$string['stats_connected_users'] = 'कनेक्ट झालेले युजर्स';
$string['roadmap_col_phase'] = 'फेज';
$string['roadmap_col_what'] = 'काय येते';
$string['roadmap_col_status'] = 'स्टेटस';
$string['roadmap_done'] = 'पूर्ण';
$string['roadmap_planned'] = 'नियोजित';
$string['roadmap_c1'] = 'C.1 — OAuth स्कॅफोल्ड, Graph स्टब्स, एन्क्रिप्टेड टोकन स्टोरेज, गोपनीयता प्रोव्हायडर.';
$string['roadmap_c2'] = 'C.2 — confirm_required स्टब्सऐवजी प्रत्येक कॉलसाठी [CONFIRM] असलेले खरे Graph कॉल्स. get_me, list_sites, कॅलेंडर बेसलाइन.';
$string['roadmap_c3'] = 'C.3 — SharePoint डॉक्युमेंट्स SENTIENTIA SOP पार्सर पाइपलाइनमध्ये आणणे.';
$string['roadmap_c4'] = 'C.4 — Outlook मीटिंग → LMS क्लासरूम इव्हेंट सिंक.';
$string['roadmap_c5'] = 'C.5 — Teams हजेरी इम्पोर्ट → कम्प्लीशन रेकॉर्ड.';
$string['roadmap_c6'] = 'C.6 — प्रत्येक कस्टमरसाठी प्रॉम्प्ट + स्कोप ओव्हरराइड्स + हिंदी संमती UI.';
