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
 * Marathi strings for local_sentientia_core.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Core';
$string['privacy:metadata'] = 'Sentientia Core प्लगइन ऑर्ग-युनिट सदस्यत्वाच्या रो (युजर, युनिट, रोल, थेट मॅनेजर) local_sentientia_org_member मध्ये आणि इम्पोर्ट केलेला BizLMS ॲडमिन लॉग local_sentientia_admin_log मध्ये सेव्ह करतो (कोर्स कोणी तयार, अपडेट किंवा डिलीट केला आणि कधी, सोबत त्या व्यक्तीचे पहिले नाव असलेले वर्णन). खुद्द टेनंट रजिस्ट्रीमध्ये (कस्टमर + टेनंट कॉन्फिगरेशन: नावे, रूट id, स्टेटस) कोणताही वैयक्तिक डेटा नसतो.';
$string['settings_tenant_identity'] = 'टेनंट ओळख';
$string['setting_legacy_openpath'] = 'BizLMS open_path वरून टेनंट ठरवा (लेगसी)';
$string['setting_legacy_openpath_desc'] = 'हे चालू असताना (डीफॉल्ट), Sentientia युजरचा टेनंट लेगसी BizLMS <code>open_path</code> प्रोफाइल फील्डवरून ठरवते — सध्याच्या प्रॉडक्शनसारखेच वर्तन. Sentientia टेनंट रजिस्ट्री तयार झाल्यावर, हे OFF करणे फक्त ADR-018 Wave 3+ साठी राखीव आहे; तोपर्यंत सर्व्हिस तरीही सुरक्षितपणे <code>open_path</code> वरच परत जाते. प्रॉडक्शनमध्ये ON ठेवा.';
$string['settings_org'] = 'ऑर्ग हायरार्की';
$string['setting_org_legacy'] = 'BizLMS वरून मॅनेजर / ऑर्ग ठरवा (लेगसी)';
$string['setting_org_legacy_desc'] = 'हे चालू असताना (डीफॉल्ट), Sentientia युजरचा मॅनेजर लेगसी BizLMS <code>open_supervisorid</code> फील्डवरून ठरवते — सध्याच्या प्रॉडक्शनसारखेच वर्तन. Sentientia ऑर्ग मॉडेल तयार झाल्यावर, हे OFF करणे फक्त ADR-020 Wave 3.2+ साठी राखीव आहे; तोपर्यंत सर्व्हिस तरीही सुरक्षितपणे <code>open_supervisorid</code> वरच परत जाते. प्रॉडक्शनमध्ये ON ठेवा.';
$string['setting_org_dualwrite'] = 'लेगसी ऑर्ग ग्राफ Sentientia ऑर्ग मॉडेलमध्ये मिरर करा (ड्युअल-राइट)';
$string['setting_org_dualwrite_desc'] = 'हे चालू असताना, एक शेड्यूल्ड टास्क ठराविक अंतराने लेगसी BizLMS ऑर्ग ग्राफ (<code>open_path</code> कॉस्ट-सेंटर ट्री + <code>open_supervisorid</code> मॅनेजर लिंक) Sentientia ऑर्ग मॉडेलच्या टेबलमध्ये मिरर करतो. लेगसी ग्राफच मुख्य सोर्स राहतो — हे फक्त पुढे होणाऱ्या कटओव्हरआधी नवीन टेबल अपडेटेड ठेवते. डीफॉल्ट OFF (टास्क काहीच करत नाही): पॅरिटी चेक किंवा रिहर्सलसाठी मॉडेल भरायचे असेल तेव्हाच ON करा, आणि फ्लिप करण्याचा विचार करण्याआधी <code>cli/parity_check_org.php</code> रन करा. यामुळे मॅनेजर ठरवण्याची पद्धत बदलत नाही — ती अजूनही वरच्या “BizLMS वरून मॅनेजर / ऑर्ग ठरवा (लेगसी)” फ्लॅगनुसारच चालते.';
$string['task_reconcile_org'] = 'लेगसी ग्राफवरून Sentientia ऑर्ग मॉडेल रिकन्साइल करा';
$string['error_outoftenant'] = 'तुम्हाला या रिसोर्सचा ॲक्सेस नाही — हा दुसऱ्या टेनंटचा आहे.';
$string['sentientia_core:managetenants'] = 'Sentientia टेनंट रजिस्ट्री मॅनेज करा';
$string['settings_tenant_registry'] = 'टेनंट रजिस्ट्री';
$string['setting_legacy_registry'] = 'हार्डकोड केलेल्या अलाउ-लिस्टवरून टेनंट व्हॅलिडेट करा (लेगसी)';
$string['setting_legacy_registry_desc'] = 'हे चालू असताना (डीफॉल्ट), Sentientia टेनंट रूट लेगसी हार्डकोड केलेल्या अलाउ-लिस्टनुसार (<code>[1, 77, 177]</code>) व्हॅलिडेट करते — सध्याच्या प्रॉडक्शनसारखेच वर्तन. हे OFF केल्यास त्याऐवजी Sentientia टेनंट रजिस्ट्री (खालील <em>टेनंट रजिस्ट्री मॅनेज करा</em> पेज) वाचली जाते. रजिस्ट्री सीड करून <code>cli/parity_check_tenants.php</code> ने 100% पॅरिटीची खात्री केल्यानंतरच OFF करा (आधी क्लोन DB वर रिहर्सल करा). कटओव्हरपर्यंत प्रॉडक्शनमध्ये ON ठेवा.';
$string['error_invalidtenant'] = 'अज्ञात टेनंट — हा id टेनंट रजिस्ट्रीमध्ये नाही.';
$string['managetenants'] = 'टेनंट रजिस्ट्री मॅनेज करा';
$string['registry_flag_legacy_on'] = 'टेनंट रजिस्ट्री सध्या निष्क्रिय आहे — टेनंट व्हॅलिडेशन सध्या लेगसी हार्डकोड केलेली अलाउ-लिस्ट वापरते. इथे मॅनेज केलेल्या रो फक्त "हार्डकोड केलेल्या अलाउ-लिस्टवरून टेनंट व्हॅलिडेट करा (लेगसी)" सेटिंग OFF केल्यानंतरच लागू होतात.';
$string['registry_flag_legacy_off'] = 'टेनंट रजिस्ट्री आता लाइव्ह आहे — टेनंट व्हॅलिडेशन आता याच रो वाचते. इथे एखादा टेनंट काढला किंवा सस्पेंड केला, तर टेनंट-स्कोप्ड रिसोर्स कोण ॲक्सेस करू शकतो यावर लगेच परिणाम होतो.';
$string['customers'] = 'कस्टमर्स';
$string['tenants'] = 'टेनंट्स';
$string['tenantcount'] = 'टेनंट्स';
$string['customer_missing'] = '(अज्ञात कस्टमर)';
$string['nocustomers'] = 'अजून एकही कस्टमर नाही. खाली एक जोडा, किंवा cli/seed_tenants.php रन करा.';
$string['notenants'] = 'अजून एकही टेनंट रजिस्टर झालेला नाही. खाली एक जोडा, किंवा cli/seed_tenants.php रन करा.';
$string['actions'] = 'ॲक्शन्स';
$string['suspend'] = 'सस्पेंड करा';
$string['activate'] = 'ॲक्टिव्हेट करा';
$string['tenant_statuschanged'] = 'टेनंट स्टेटस अपडेट झाला.';
$string['customer_saved'] = 'कस्टमर सेव्ह झाला.';
$string['tenant_saved'] = 'टेनंट सेव्ह झाला.';
$string['addcustomer'] = 'कस्टमर जोडा';
$string['addtenant'] = 'टेनंट जोडा';
$string['addcustomer_first'] = 'टेनंट रजिस्टर करण्याआधी कस्टमर जोडा — प्रत्येक टेनंट कोणत्यातरी एका कस्टमरचा असणे आवश्यक आहे.';
$string['status_active'] = 'ॲक्टिव्ह';
$string['status_suspended'] = 'सस्पेंड केलेले';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['field_customername'] = 'कस्टमरचे नाव';
$string['field_shortname'] = 'लहान नाव';
$string['field_shortname_help'] = 'कस्टमरसाठी एक छोटे, युनिक, मशीनला सोपे हँडल (अक्षरे, अंक, _ आणि -). उदाहरण: <code>airpay</code>. फक्त अंतर्गत वापरासाठी; लर्नर्सना दिसत नाही.';
$string['field_status'] = 'स्टेटस';
$string['field_rootid'] = 'टेनंट रूट id';
$string['field_rootid_help'] = 'टेनंट रूट id. सध्या हा BizLMS कॉस्ट-सेंटर रूट आहे (1 = Airpay, 77 = Public, 177 = ZEEA). हा धन पूर्णांक असला पाहिजे आणि संपूर्ण रजिस्ट्रीमध्ये युनिक असला पाहिजे.';
$string['field_customer'] = 'मालक कस्टमर';
$string['field_tenantname'] = 'टेनंटचे नाव';
$string['field_idnumber'] = 'एक्सटर्नल id (ऐच्छिक)';
$string['field_idnumber_help'] = 'एक ऐच्छिक एक्सटर्नल की (उदाहरणार्थ HRMS आयडेंटिफायर), जी अपस्ट्रीम सिंकसोबत हा टेनंट राउंड-ट्रिप करण्यासाठी वापरली जाते. लागू नसेल तर रिकामे ठेवा.';
$string['err_shortname_taken'] = 'हे लहान नाव आधीच दुसऱ्या कस्टमरने वापरले आहे.';
$string['err_rootid_positive'] = 'टेनंट रूट id धन पूर्णांक असला पाहिजे.';
$string['err_rootid_taken'] = 'हा टेनंट रूट id आधीच रजिस्टर आहे.';
$string['privacy:metadata:org_member'] = 'युजरचे ऑर्ग-युनिट सदस्यत्व: युनिट, रोल आणि थेट मॅनेजर';
$string['privacy:metadata:org_member:userid'] = 'सदस्य';
$string['privacy:metadata:org_member:unitid'] = 'युजर ज्या ऑर्ग युनिटमध्ये आहे ते युनिट';
$string['privacy:metadata:org_member:role'] = 'युनिटमधील युजरचा रोल';
$string['privacy:metadata:org_member:managerid'] = 'युजरचा थेट मॅनेजर';
$string['privacy:metadata:org_member:timecreated'] = 'सदस्यत्व कधी नोंदवले गेले';
$string['sentientia_core:viewadminlog'] = 'इम्पोर्ट केलेला ॲडमिन लॉग पाहा';
$string['adminlog'] = 'इम्पोर्ट केलेला ॲडमिन लॉग';
$string['adminlog_intro'] = 'BizLMS ॲडमिन लॉग (कोर्स तयार, अपडेट किंवा डिलीट केला) आणि बल्क कोर्स अपलोडच्या एरर, कटओव्हरच्या वेळी इम्पोर्ट केलेले. ही फक्त वाचण्यासाठीची हिस्ट्री आहे: इथे काहीही एडिट किंवा डिलीट करता येत नाही. वर्णनांमध्ये कृती करणाऱ्या व्यक्तीचे पहिले नाव असते, म्हणून हे पेज वैयक्तिक डेटा म्हणून हाताळा.';
$string['adminlog_flagoff'] = 'हा रिपोर्ट बंद आहे. तो sentientia.legacy_logs.report.enabled या फीचर फ्लॅगने सुरू होतो, जो पेजचा रिव्ह्यू होईपर्यंत OFF आहे.';
$string['adminlog_noentries'] = 'कोणतीही एंट्री जुळत नाही.';
$string['adminlog_notenant'] = 'तुमचे अकाउंट कोणत्याही टेनंटशी जोडलेले नाही, त्यामुळे दाखवण्यासारखे काही नाही.';
$string['adminlog_total'] = '{$a} एंट्री';
$string['adminlog_col_when'] = 'कधी';
$string['adminlog_col_who'] = 'कोण';
$string['adminlog_col_source'] = 'सोर्स';
$string['adminlog_col_event'] = 'इव्हेंट';
$string['adminlog_col_module'] = 'मॉड्यूल';
$string['adminlog_col_item'] = 'आयटम';
$string['adminlog_col_description'] = 'वर्णन';
$string['adminlog_source_local_logs'] = 'कोर्स ॲडमिन लॉग';
$string['adminlog_source_local_courseerrors'] = 'बल्क कोर्स अपलोड एरर';
$string['adminlog_filter_any'] = 'कोणतेही';
$string['adminlog_filter_apply'] = 'फिल्टर';
$string['adminlog_actor_deleted'] = '{$a} (डिलीट केलेला युजर)';
$string['adminlog_actor_unknown'] = 'अज्ञात किंवा मिटवलेला';
$string['privacy:metadata:admin_log'] = 'इम्पोर्ट केलेला BizLMS ॲडमिन लॉग आणि बल्क कोर्स अपलोड एरर: कोणी, कशावर आणि कधी कृती केली. एखाद्या व्यक्तीचा डेटा मिटवला तरी हे हिस्ट्री म्हणून ठेवले जाते; त्या व्यक्तीला रोमधून काढले जाते.';
$string['privacy:metadata:admin_log:source'] = 'ही रो कोणत्या BizLMS लॉगमधून आली';
$string['privacy:metadata:admin_log:event'] = 'काय घडले (इन्सर्ट, अपडेट, डिलीट, किंवा अपलोड एरर)';
$string['privacy:metadata:admin_log:module'] = 'एंट्री कोणत्या प्रकारच्या आयटमबद्दल आहे';
$string['privacy:metadata:admin_log:description'] = 'कृती करणाऱ्या व्यक्तीचे पहिले नाव असलेला फ्री टेक्स्ट; व्यक्तीचा डेटा मिटवल्यावर तो साफ केला जातो';
$string['privacy:metadata:admin_log:itemref'] = 'एंट्री ज्या आयटमबद्दल आहे त्याचा id, सहसा कोर्स';
$string['privacy:metadata:admin_log:userid'] = 'ज्या व्यक्तीने कृती केली, किंवा अपलोड रन केला';
$string['privacy:metadata:admin_log:usermodified'] = 'BizLMS मध्ये एंट्री शेवटची बदलणारी व्यक्ती';
$string['privacy:metadata:admin_log:actor_path'] = 'एंट्री इम्पोर्ट झाली तेव्हा कृती करणाऱ्या व्यक्तीचा संस्थेचा पाथ';
$string['privacy:metadata:admin_log:timecreated'] = 'BizLMS मध्ये एंट्री कधी केली गेली';
$string['privacy:metadata:admin_log:timemodified'] = 'BizLMS मध्ये एंट्री शेवटची कधी बदलली गेली';
$string['privacy:metadata:course_creator'] = 'कोर्सवरील BizLMS कोर्स-क्रिएटर कॉलम. कोर्स तयार करणाऱ्याचा डेटा मिटवला तरी कोर्स ठेवला जातो; क्रिएटर 0 वर सेट केला जातो.';
$string['privacy:metadata:course_creator:open_coursecreator'] = 'BizLMS ने नोंदवल्याप्रमाणे, कोर्स तयार करणारा युजर.';
