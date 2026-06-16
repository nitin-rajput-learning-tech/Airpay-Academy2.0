<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
defined('MOODLE_INTERNAL') || die();

// Hindi pack — 100% parity with lang/en/local_sentientia_talent.php.

$string['pluginname'] = 'सेंटिएंटिया प्रतिभा गतिशीलता';

// Capabilities.
$string['sentientia_talent:viewopportunities'] = 'आंतरिक अवसर बोर्ड देखें';
$string['sentientia_talent:registerinterest'] = 'किसी आंतरिक अवसर में रुचि दर्ज करें';
$string['sentientia_talent:viewcareerpath'] = 'अपनी भूमिका के लिए करियर पथ देखें';
$string['sentientia_talent:viewsuccession'] = 'उत्तराधिकार योजनाएँ देखें (एचआर / प्रबंधक)';
$string['sentientia_talent:managesuccession'] = 'उत्तराधिकार नामांकन प्रबंधित करें (एचआर / प्रबंधक)';
$string['sentientia_talent:managecareerpaths'] = 'करियर पथ परिभाषित और संपादित करें';
$string['sentientia_talent:manageopportunities'] = 'आंतरिक अवसर पोस्ट और प्रबंधित करें';
$string['sentientia_talent:audit'] = 'प्रतिभा ऑडिट लॉग देखें';

// Navigation.
$string['nav_console'] = 'प्रतिभा गतिशीलता';
$string['nav_opportunities'] = 'आंतरिक अवसर';

// Page headings.
$string['heading_console'] = 'प्रतिभा गतिशीलता कंसोल';
$string['heading_opportunities'] = 'आंतरिक अवसर';
$string['heading_paths'] = 'करियर पथ';
$string['heading_succession'] = 'उत्तराधिकार योजनाएँ';
$string['heading_mypath'] = 'आपका करियर पथ';

// KPI cards.
$string['kpi_paths'] = 'करियर पथ';
$string['kpi_successions'] = 'उत्तराधिकार नामांकन';
$string['kpi_opportunities'] = 'खुले अवसर';

// Table columns.
$string['col_path'] = 'पथ';
$string['col_from'] = 'मौजूदा भूमिका';
$string['col_to'] = 'लक्षित भूमिका';
$string['col_status'] = 'स्थिति';
$string['col_role'] = 'भूमिका';
$string['col_candidate'] = 'उम्मीदवार';
$string['col_incumbent'] = 'वर्तमान धारक';
$string['col_readiness'] = 'तत्परता';
$string['col_match'] = 'कौशल मिलान';
$string['col_title'] = 'शीर्षक';

// Status / readiness labels.
$string['status_active'] = 'सक्रिय';
$string['status_archived'] = 'संग्रहित';
$string['readiness_ready_now'] = 'अभी तैयार';
$string['readiness_ready_1y'] = '1 वर्ष में तैयार';
$string['readiness_ready_2y'] = '2 वर्ष में तैयार';
$string['readiness_developing'] = 'विकासशील';

// Opportunity board.
$string['mypath_intro'] = 'आपकी वर्तमान भूमिका ({$a}) के आधार पर, ये प्रगति आपके लिए उपलब्ध हैं:';
$string['match_label'] = 'मिलान';
$string['match_help'] = 'आपके वर्तमान कौशल इस भूमिका के लिए आवश्यक कौशल से कितने मेल खाते हैं।';
$string['interest_message'] = 'एक संक्षिप्त टिप्पणी जोड़ें (वैकल्पिक)';
$string['btn_register'] = 'रुचि दर्ज करें';
$string['btn_withdraw'] = 'रुचि वापस लें';
$string['interest_registered_badge'] = 'रुचि दर्ज की गई';
$string['interest_saved'] = 'आपकी रुचि अपडेट कर दी गई है।';

// Empty states.
$string['empty_paths'] = 'आपके टेनेंट के लिए अभी तक कोई करियर पथ परिभाषित नहीं किया गया है।';
$string['empty_succession'] = 'अभी तक कोई उत्तराधिकार नामांकन दर्ज नहीं किया गया है।';
$string['empty_opportunities'] = 'अभी कोई खुला आंतरिक अवसर उपलब्ध नहीं है।';

// Skills-source indicator.
$string['skillsource_active'] = 'कौशल मिलान इससे संचालित है: {$a}';
$string['skillsource_skillsai'] = 'एआई कौशल वर्गीकरण (सेंटिएंटिया SkillsAI)';
$string['skillsource_manual'] = 'मैनुअल कौशल मैट्रिक्स (सेंटिएंटिया कौशल)';

// Settings page.
$string['settings_pagetitle'] = 'सेंटिएंटिया प्रतिभा गतिशीलता';
$string['settings_section_general'] = 'प्रतिभा गतिशीलता';
$string['settings_section_general_desc'] = 'प्रतिभा गतिशीलता सुइट प्लेटफ़ॉर्म फीचर-फ्लैग स्विचबोर्ड द्वारा नियंत्रित है। यह पृष्ठ वर्तमान स्थिति का सारांश देता है।';
$string['setting_skillsource'] = 'सक्रिय कौशल वर्गीकरण';
$string['setting_skillsource_desc'] = 'अवसरों और उत्तराधिकार उम्मीदवारों के लिए कौशल मिलान वर्तमान में इसका उपयोग करता है: {$a}. जब एआई कौशल प्लगइन इंस्टॉल और सक्षम होता है तो यह स्वचालित रूप से उपयोग किया जाता है; अन्यथा मैनुअल कौशल मैट्रिक्स का उपयोग किया जाता है।';
$string['setting_switchboard'] = 'फीचर फ्लैग';
$string['setting_switchboard_desc'] = 'प्लेटफ़ॉर्म स्विचबोर्ड में प्रति टेनेंट प्रतिभा गतिशीलता सुइट सक्षम या अक्षम करें।';
$string['setting_switchboard_btn'] = 'स्विचबोर्ड खोलें';

// Errors.
$string['error_featuredisabled'] = 'आपके संगठन के लिए प्रतिभा गतिशीलता सुविधा सक्षम नहीं है।';
$string['error_missingfields'] = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['error_invalidreadiness'] = 'अमान्य तत्परता मान।';
$string['error_invalidstatus'] = 'अमान्य अवसर स्थिति।';
$string['error_duplicatenomination'] = 'इस व्यक्ति को पहले ही इस भूमिका के लिए उत्तराधिकारी के रूप में नामांकित किया जा चुका है।';
$string['error_opportunityclosed'] = 'यह अवसर अब रुचि के लिए खुला नहीं है।';

// Privacy metadata.
$string['privacy:metadata:succ'] = 'उम्मीदवारों को प्रमुख भूमिकाओं से जोड़ने वाले उत्तराधिकार नामांकन।';
$string['privacy:metadata:succ:designation'] = 'उत्तराधिकार-योजना वाली भूमिका।';
$string['privacy:metadata:succ:candidateid'] = 'उत्तराधिकारी के रूप में नामांकित उपयोगकर्ता।';
$string['privacy:metadata:succ:incumbentid'] = 'भूमिका को वर्तमान में धारण करने वाला उपयोगकर्ता।';
$string['privacy:metadata:succ:readiness'] = 'उम्मीदवार भूमिका लेने के लिए कितना तैयार है।';
$string['privacy:metadata:succ:notes'] = 'नामांकन के बारे में एचआर टिप्पणियाँ।';
$string['privacy:metadata:succ:timecreated'] = 'नामांकन कब बनाया गया था।';
$string['privacy:metadata:int'] = 'आंतरिक अवसरों में रुचि की अभिव्यक्तियाँ।';
$string['privacy:metadata:int:opportunityid'] = 'वह अवसर जिससे रुचि संबंधित है।';
$string['privacy:metadata:int:userid'] = 'वह उपयोगकर्ता जिसने रुचि व्यक्त की।';
$string['privacy:metadata:int:message'] = 'आवेदक की एक वैकल्पिक टिप्पणी।';
$string['privacy:metadata:int:matchpct'] = 'रुचि के समय कौशल-मिलान प्रतिशत।';
$string['privacy:metadata:int:timecreated'] = 'रुचि कब दर्ज की गई थी।';
$string['privacy:metadata:opp'] = 'आंतरिक अवसर पोस्टिंग।';
$string['privacy:metadata:opp:title'] = 'अवसर का शीर्षक।';
$string['privacy:metadata:opp:postedby'] = 'वह उपयोगकर्ता जिसने अवसर पोस्ट किया।';
$string['privacy:metadata:opp:timecreated'] = 'अवसर कब पोस्ट किया गया था।';
$string['privacy:metadata:audit'] = 'एचआर-संवेदनशील प्रतिभा कार्रवाइयों का ऑडिट लॉग।';
$string['privacy:metadata:audit:action'] = 'की गई कार्रवाई।';
$string['privacy:metadata:audit:targetuserid'] = 'कार्रवाई का विषय, यदि कोई हो।';
$string['privacy:metadata:audit:changedby'] = 'वह उपयोगकर्ता जिसने कार्रवाई की।';
$string['privacy:metadata:audit:timecreated'] = 'कार्रवाई कब हुई।';
