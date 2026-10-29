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
 * Marathi strings for local_sentientia_roles.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_roles
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia रोल मॅनेजमेंट';
$string['sentientia_roles:view'] = 'रोल-मॅनेजमेंट UI पाहा';
$string['sentientia_roles:manage'] = 'रोल्सवरील कॅपेबिलिटी परवानग्या एडिट करा';
$string['sentientia_roles:assign'] = 'युजर्सना रोल्स असाइन करा / काढा';
$string['sentientia_roles:audit'] = 'रोल-मॅनेजमेंट ऑडिट लॉग पाहा';
$string['sentientia_roles:export'] = 'रोल + कॅपेबिलिटी डेटा CSV मध्ये एक्सपोर्ट करा';
$string['heading_index'] = 'रोल मॅनेजमेंट';
$string['heading_view'] = 'रोल: {$a}';
$string['heading_audit'] = 'ऑडिट लॉग';
$string['filter_archetype'] = 'आर्किटाइप';
$string['filter_archetype_all'] = 'सर्व आर्किटाइप';
$string['filter_search'] = 'शोधा';
$string['filter_search_placeholder'] = 'रोलचे नाव किंवा शॉर्टनेम';
$string['btn_audit_log'] = 'ऑडिट लॉग';
$string['btn_export_csv'] = 'CSV एक्सपोर्ट करा';
$string['btn_view_role'] = 'पाहा';
$string['btn_edit_caps'] = 'कॅपेबिलिटीज एडिट करा';
$string['col_name'] = 'नाव';
$string['col_shortname'] = 'शॉर्टनेम';
$string['col_archetype'] = 'आर्किटाइप';
$string['col_caps'] = 'कॅपेबिलिटीज';
$string['col_assignments'] = 'असाइनमेंट्स';
$string['col_sortorder'] = 'क्रम';
$string['col_actions'] = 'ॲक्शन्स';
$string['tab_overview'] = 'आढावा';
$string['tab_capabilities'] = 'कॅपेबिलिटीज';
$string['tab_assignments'] = 'असाइनमेंट्स';
$string['tab_audit'] = 'ऑडिट';
$string['ov_id'] = 'रोल ID';
$string['ov_shortname'] = 'शॉर्टनेम';
$string['ov_archetype'] = 'आर्किटाइप';
$string['ov_archetype_custom'] = '(कस्टम — आर्किटाइप नाही)';
$string['ov_description'] = 'वर्णन';
$string['ov_caps_total'] = 'एकूण कॅपेबिलिटीज';
$string['ov_caps_allow'] = 'परवानगी';
$string['ov_caps_prevent'] = 'प्रतिबंध';
$string['ov_caps_prohibit'] = 'पूर्ण मनाई';
$string['ov_assignments'] = 'युजर असाइनमेंट्स';
$string['ov_audit_entries'] = 'ऑडिट नोंदी (हा रोल)';
$string['cap_filter_search'] = 'कॅपेबिलिटीज फिल्टर करा';
$string['cap_filter_perm'] = 'परवानगी';
$string['cap_filter_perm_all'] = 'सर्व';
$string['cap_perm_inherit'] = 'इनहेरिट (सेट केलेले नाही)';
$string['cap_perm_allow'] = 'परवानगी';
$string['cap_perm_prevent'] = 'प्रतिबंध';
$string['cap_perm_prohibit'] = 'पूर्ण मनाई';
$string['cap_col_name'] = 'कॅपेबिलिटी';
$string['cap_col_component'] = 'कंपोनंट';
$string['cap_col_risks'] = 'रिस्क';
$string['cap_col_perm'] = 'परवानगी';
$string['cap_col_actions'] = 'ॲक्शन्स';
$string['cap_no_results'] = 'सध्याच्या फिल्टरशी कोणतीही कॅपेबिलिटी जुळत नाही.';
$string['form_edit_cap'] = 'कॅपेबिलिटी एडिट करा';
$string['form_capability'] = 'कॅपेबिलिटी';
$string['form_permission'] = 'परवानगी';
$string['form_reason'] = 'बदलाचे कारण';
$string['form_reason_help'] = 'ऐच्छिक. कम्प्लायन्स रिव्ह्यूसाठी ऑडिट लॉगमध्ये सेव्ह केले जाते.';
$string['form_save'] = 'बदल सेव्ह करा';
$string['form_cancel'] = 'रद्द करा';
$string['audit_col_when'] = 'केव्हा';
$string['audit_col_who'] = 'कोणी';
$string['audit_col_role'] = 'रोल';
$string['audit_col_action'] = 'ॲक्शन';
$string['audit_col_cap'] = 'कॅपेबिलिटी';
$string['audit_col_change'] = 'बदल';
$string['audit_col_reason'] = 'कारण';
$string['audit_no_entries'] = 'अजून एकही ऑडिट नोंद नाही.';
$string['audit_filter_role'] = 'रोलनुसार फिल्टर करा';
$string['audit_filter_role_all'] = 'सर्व रोल्स';
$string['audit_filter_action_all'] = 'सर्व ॲक्शन्स';
$string['audit_filter_action'] = 'ॲक्शननुसार फिल्टर करा';
$string['audit_action_capability_set'] = 'कॅपेबिलिटी बदलली';
$string['audit_action_capability_unset'] = 'कॅपेबिलिटी रीसेट केली';
$string['audit_action_role_assigned'] = 'रोल असाइन केला';
$string['audit_action_role_unassigned'] = 'रोल काढला';
$string['audit_action_role_created'] = 'रोल तयार केला';
$string['audit_action_role_deleted'] = 'रोल डिलीट केला';
$string['err_role_not_found'] = 'रोल सापडला नाही.';
$string['err_user_not_found'] = 'युजर सापडला नाही.';
$string['err_capability_not_found'] = 'कॅपेबिलिटी "{$a}" या Sentientia LMS इन्स्टॉलमध्ये रजिस्टर केलेली नाही.';
$string['err_invalid_permission'] = 'परवानगी यापैकी एक असावी: inherit, allow, prevent, prohibit.';
$string['err_cannot_modify_admin'] = 'साइट ॲडमिन रोलवरील कॅपेबिलिटीज बदलता येत नाहीत.';
$string['err_filterstoolong'] = 'फिल्टर ब्लॉब मर्यादेपेक्षा मोठा आहे.';
$string['err_definitions_crosstenant'] = 'रोल डेफिनिशन्स सर्व टेनंट्समध्ये शेअर केलेल्या असतात, त्यामुळे फक्त प्लॅटफॉर्म (क्रॉस-टेनंट) ॲडमिनच त्या बदलू शकतो.';
$string['err_role_not_assignable'] = 'तुम्ही फक्त असा रोल असाइन करू शकता जो तुमच्याकडे स्वतः आहे आणि जो असाइन करण्याची तुम्हाला परवानगी आहे, आणि तोही फक्त तुमच्याच संस्थेतील दुसऱ्या व्यक्तीला.';
$string['err_assignment_not_found'] = 'त्या युजरकडे साइट लेव्हलवर हा रोल नाही, त्यामुळे काढण्यासारखे काहीही नाही.';
$string['assignment_scope_system'] = 'संपूर्ण प्लॅटफॉर्म';
$string['privacy:metadata:auditlog'] = 'रोल-मॅनेजमेंट UI मधून केलेले रोल आणि कॅपेबिलिटी बदल, तसेच BizLMS मधून इम्पोर्ट केलेल्या रोल असाइनमेंट्स यांचा ॲपेंड-ओन्ली ऑडिट लॉग.';
$string['privacy:metadata:auditlog:roleid'] = 'बदलला जात असलेला रोल.';
$string['privacy:metadata:auditlog:capability'] = 'ज्या कॅपेबिलिटीची परवानगी बदलली ती कॅपेबिलिटी.';
$string['privacy:metadata:auditlog:oldpermission'] = 'बदलापूर्वीची कॅपेबिलिटी परवानगी.';
$string['privacy:metadata:auditlog:newpermission'] = 'बदलानंतरची कॅपेबिलिटी परवानगी.';
$string['privacy:metadata:auditlog:changedby'] = 'बदल करणाऱ्या ॲडमिनचा युजर ID.';
$string['privacy:metadata:auditlog:targetuserid'] = 'ज्याला रोल असाइन केला जात आहे किंवा ज्याच्याकडून रोल काढला जात आहे त्या युजरचा युजर ID (रोल-असाइनमेंट इव्हेंट्ससाठी).';
$string['privacy:metadata:auditlog:reason'] = 'ॲडमिनने दिलेल्या कारणाचा ऐच्छिक मजकूर.';
$string['privacy:metadata:auditlog:open_path'] = 'बदलाच्या वेळी ॲडमिनचा Airpay टेनंट पाथ.';
$string['privacy:metadata:auditlog:timecreated'] = 'बदल केव्हा झाला.';
$string['cap_updated_success'] = 'कॅपेबिलिटी "{$a}" अपडेट झाली.';
