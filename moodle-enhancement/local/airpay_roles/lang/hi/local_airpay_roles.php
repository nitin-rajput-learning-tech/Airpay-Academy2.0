<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #56 (2026-05-20) — Hindi (hi) translations for local_airpay_roles.
// Scope: role-management UI — capabilities, filters, table columns,
// view tabs, capability edit, audit log, errors, privacy metadata.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे रोल प्रबंधन';

// Capabilities.
$string['airpay_roles:view']   = 'रोल-प्रबंधन UI देखें';
$string['airpay_roles:manage'] = 'रोल पर क्षमता अनुमतियाँ संपादित करें';
$string['airpay_roles:assign'] = 'यूज़र्स को रोल असाइन / अनअसाइन करें';
$string['airpay_roles:audit']  = 'रोल-प्रबंधन ऑडिट लॉग देखें';
$string['airpay_roles:export'] = 'रोल + क्षमता डेटा CSV में निर्यात करें';

// Page titles.
$string['heading_index'] = 'रोल प्रबंधन';
$string['heading_view']  = 'रोल: {$a}';
$string['heading_audit'] = 'ऑडिट लॉग';

// Index page UI.
$string['filter_archetype']          = 'आर्कीटाइप';
$string['filter_archetype_all']      = 'सभी आर्कीटाइप';
$string['filter_search']             = 'खोज';
$string['filter_search_placeholder'] = 'रोल का नाम या संक्षिप्त नाम';
$string['btn_audit_log']             = 'ऑडिट लॉग';
$string['btn_export_csv']            = 'CSV निर्यात करें';
$string['btn_view_role']             = 'देखें';
$string['btn_edit_caps']             = 'क्षमताएँ संपादित करें';

// Index table columns.
$string['col_name']        = 'नाम';
$string['col_shortname']   = 'संक्षिप्त नाम';
$string['col_archetype']   = 'आर्कीटाइप';
$string['col_caps']        = 'क्षमताएँ';
$string['col_assignments'] = 'असाइनमेंट';
$string['col_sortorder']   = 'सॉर्ट क्रम';
$string['col_actions']     = 'क्रियाएँ';

// View page tabs.
$string['tab_overview']     = 'सिंहावलोकन';
$string['tab_capabilities'] = 'क्षमताएँ';
$string['tab_assignments']  = 'असाइनमेंट';
$string['tab_audit']        = 'ऑडिट';

// Overview tab.
$string['ov_id']               = 'रोल ID';
$string['ov_shortname']        = 'संक्षिप्त नाम';
$string['ov_archetype']        = 'आर्कीटाइप';
$string['ov_archetype_custom'] = '(कस्टम — कोई आर्कीटाइप नहीं)';
$string['ov_description']      = 'विवरण';
$string['ov_caps_total']       = 'कुल क्षमताएँ';
$string['ov_caps_allow']       = 'अनुमति';
$string['ov_caps_prevent']     = 'रोकें';
$string['ov_caps_prohibit']    = 'निषेध';
$string['ov_assignments']      = 'यूज़र असाइनमेंट';
$string['ov_audit_entries']    = 'ऑडिट प्रविष्टियाँ (इस रोल)';

// Capabilities tab.
$string['cap_filter_search']   = 'क्षमताएँ फ़िल्टर करें';
$string['cap_filter_perm']     = 'अनुमति';
$string['cap_filter_perm_all'] = 'सभी';
$string['cap_perm_inherit']    = 'वंशागत (सेट नहीं)';
$string['cap_perm_allow']      = 'अनुमति';
$string['cap_perm_prevent']    = 'रोकें';
$string['cap_perm_prohibit']   = 'निषेध';
$string['cap_col_name']        = 'क्षमता';
$string['cap_col_component']   = 'घटक';
$string['cap_col_risks']       = 'जोखिम';
$string['cap_col_perm']        = 'अनुमति';
$string['cap_col_actions']     = 'क्रियाएँ';
$string['cap_no_results']      = 'वर्तमान फ़िल्टर से कोई क्षमता मेल नहीं खाती।';

// Edit capability modal.
$string['form_edit_cap']    = 'क्षमता संपादित करें';
$string['form_capability']  = 'क्षमता';
$string['form_permission']  = 'अनुमति';
$string['form_reason']      = 'परिवर्तन का कारण';
$string['form_reason_help'] = 'वैकल्पिक। अनुपालन समीक्षा के लिए ऑडिट लॉग में संग्रहीत।';
$string['form_save']        = 'परिवर्तन सहेजें';
$string['form_cancel']      = 'रद्द करें';

// Audit log columns.
$string['audit_col_when']          = 'कब';
$string['audit_col_who']           = 'कौन';
$string['audit_col_role']          = 'रोल';
$string['audit_col_action']        = 'क्रिया';
$string['audit_col_cap']           = 'क्षमता';
$string['audit_col_change']        = 'परिवर्तन';
$string['audit_col_reason']        = 'कारण';
$string['audit_no_entries']        = 'अभी तक कोई ऑडिट प्रविष्टि नहीं।';
$string['audit_filter_role']       = 'रोल द्वारा फ़िल्टर';
$string['audit_filter_role_all']   = 'सभी रोल';
$string['audit_filter_action_all'] = 'सभी क्रियाएँ';
$string['audit_filter_action']     = 'क्रिया द्वारा फ़िल्टर';
$string['audit_action_capability_set']   = 'क्षमता बदली गई';
$string['audit_action_capability_unset'] = 'क्षमता रीसेट की गई';
$string['audit_action_role_assigned']    = 'रोल असाइन किया गया';
$string['audit_action_role_unassigned']  = 'रोल अनअसाइन किया गया';
$string['audit_action_role_created']     = 'रोल बनाया गया';
$string['audit_action_role_deleted']     = 'रोल हटाया गया';

// Errors.
$string['err_role_not_found']       = 'रोल नहीं मिला।';
$string['err_user_not_found']       = 'यूज़र नहीं मिला।';
$string['err_capability_not_found'] = 'क्षमता "{$a}" इस Sentientia LMS में पंजीकृत नहीं है।';
$string['err_invalid_permission']   = 'अनुमति इनमें से एक होनी चाहिए: inherit, allow, prevent, prohibit।';
$string['err_cannot_modify_admin']  = 'साइट एडमिनिस्ट्रेटर रोल पर क्षमताएँ संशोधित नहीं कर सकते।';
$string['err_filterstoolong']       = 'फ़िल्टर ब्लॉब सीमा से अधिक है।';

// Privacy provider strings.
$string['privacy:metadata:auditlog']               = 'एयरपे रोल-प्रबंधन UI के माध्यम से किए गए रोल और क्षमता परिवर्तनों का केवल-जोड़ें ऑडिट लॉग।';
$string['privacy:metadata:auditlog:roleid']        = 'जिस रोल को संशोधित किया जा रहा है।';
$string['privacy:metadata:auditlog:capability']    = 'जिस क्षमता की अनुमति बदली गई।';
$string['privacy:metadata:auditlog:oldpermission'] = 'परिवर्तन से पहले क्षमता अनुमति।';
$string['privacy:metadata:auditlog:newpermission'] = 'परिवर्तन के बाद क्षमता अनुमति।';
$string['privacy:metadata:auditlog:changedby']     = 'परिवर्तन करने वाले एडमिन की यूज़र ID।';
$string['privacy:metadata:auditlog:targetuserid']  = 'असाइन या अनअसाइन किए जा रहे यूज़र की ID (रोल-असाइनमेंट इवेंट के लिए)।';
$string['privacy:metadata:auditlog:reason']        = 'वैकल्पिक एडमिन तर्क पाठ।';
$string['privacy:metadata:auditlog:open_path']     = 'परिवर्तन के समय एडमिन का एयरपे टेनेंट पथ।';
$string['privacy:metadata:auditlog:timecreated']   = 'परिवर्तन कब हुआ।';

// Notifications.
$string['cap_updated_success'] = 'क्षमता "{$a}" अपडेट की गई।';
