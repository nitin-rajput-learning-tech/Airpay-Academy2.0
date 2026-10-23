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
 * Marathi strings for local_sentientia_recompletion.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia पुनर्पूर्णता';
$string['rules'] = 'पुनर्पूर्णता नियम';
$string['history'] = 'रीसेट इतिहास';
$string['bulkreset'] = 'बल्क रीसेट';
$string['sentientia_recompletion:view'] = 'पुनर्पूर्णता नियम आणि इतिहास पाहा';
$string['sentientia_recompletion:manage'] = 'पुनर्पूर्णता नियम व्यवस्थापित करा';
$string['sentientia_recompletion:reset'] = 'युजरच्या पूर्णता मॅन्युअली रीसेट करा';
$string['enabled'] = 'सक्षम';
$string['disabled'] = 'अक्षम';
$string['running'] = 'चालू आहे';
$string['settings_pre_notify_days'] = 'आधी सूचना देण्याचा कालावधी (दिवस)';
$string['settings_pre_notify_days_desc'] = 'युजरचे कम्प्लायन्स संपण्याच्या इतके दिवस आधीच त्यांना सूचना द्या. डीफॉल्ट 30.';
$string['settings_max_batch'] = 'प्रत्येक क्रॉन रनमध्ये रीसेट करायचे कमाल युजर';
$string['settings_max_batch_desc'] = 'चुकीच्या पद्धतीने कॉन्फिगर केलेल्या नियमाने एकाच क्रॉन रनमध्ये हजारो युजर रीसेट करू नयेत यासाठी सुरक्षा मर्यादा. डीफॉल्ट 500.';
$string['settings_dry_run_default'] = 'ड्राय-रन मोड (डीफॉल्ट बंद)';
$string['settings_dry_run_default_desc'] = 'चालू असताना, दैनिक क्रॉन काय रीसेट झाले असते ते लॉग करतो, पण प्रत्यक्षात काहीही रीसेट करत नाही. नवीन नियम टेस्ट करण्यासाठी उपयुक्त.';
$string['rule_name'] = 'नियमाचे नाव';
$string['rule_courseid'] = 'कोर्स (पूर्णता सुरू असलेल्या सर्व कोर्ससाठी 0 ठेवा)';
$string['rule_period_days'] = 'रीसेट कालावधी (दिवस)';
$string['rule_period_days_help'] = 'दर N दिवसांनी पूर्णता रीसेट करा. 365 = वार्षिक, 90 = त्रैमासिक. बंद करण्यासाठी 0 ठेवा.';
$string['rule_trigger'] = 'ट्रिगर';
$string['rule_trigger_completion'] = 'पूर्ण केल्यानंतर N दिवसांनी';
$string['rule_trigger_enrolment'] = 'एनरोल झाल्यानंतर N दिवसांनी';
$string['rule_trigger_fixed'] = 'ठरलेल्या कॅलेंडर तारखेला';
$string['rule_fixed_date'] = 'ठरलेली तारीख (ट्रिगर = ठरलेली तारीख असल्यास)';
$string['rule_reset_grades'] = 'ग्रेडही रीसेट करायचे?';
$string['rule_reset_attempts'] = 'क्विझ प्रयत्नही रीसेट करायचे?';
$string['rule_enabled'] = 'सक्षम';
$string['messageprovider:recompletion_due_soon'] = 'पुनर्पूर्णता लवकरच देय';
$string['messageprovider:recompletion_reset'] = 'पुनर्पूर्णता रीसेट (पूर्ण झाले)';
$string['msg_reset_subject'] = 'पुनर्पूर्णता: \'{$a->course}\' रीसेट झाला आहे';
$string['msg_reset_body'] = 'तुम्ही \'{$a->course}\' आधी {$a->previous} रोजी पूर्ण केला होता. {$a->days}-दिवसांच्या पुनर्पूर्णता नियमानुसार, कम्प्लायन्स कायम ठेवण्यासाठी तुम्हाला तो पुन्हा पूर्ण करावा लागेल.';
$string['msg_due_subject'] = '{$a->days} दिवसांत पुनर्पूर्णता देय: \'{$a->course}\'';
$string['msg_due_body'] = 'लक्षात घ्या — \'{$a->course}\' ची तुमची पूर्णता {$a->days} दिवसांत संपेल. कम्प्लायन्स कायम ठेवण्यासाठी त्याआधी तो पुन्हा पूर्ण करण्याचे नियोजन करा.';
$string['event_completion_reset'] = 'कोर्स पूर्णता रीसेट';
$string['nrules'] = '{$a} नियम';
$string['rules_empty'] = 'अजून कोणतेही पुनर्पूर्णता नियम कॉन्फिगर केलेले नाहीत.';
$string['history_empty'] = 'अजून एकही रीसेट झालेला नाही.';
$string['no_courses_resetable'] = 'पूर्णता ट्रॅकिंग सुरू असलेला एकही कोर्स नाही — पुनर्पूर्णतेसाठी कोर्स पूर्णता कॉन्फिगर केलेली असावी लागते.';
$string['history_title'] = 'पुनर्पूर्णता इतिहास';
$string['history_back'] = 'नियमांकडे परत';
$string['history_prev'] = 'मागील';
$string['history_next'] = 'पुढील';
$string['history_filtered'] = 'फक्त एक कोर्स किंवा एक लर्नर दाखवत आहे.';
$string['history_clear_filter'] = 'सर्व रीसेट दाखवा';
$string['hcol_when'] = 'केव्हा';
$string['hcol_user'] = 'युजर';
$string['hcol_course'] = 'कोर्स';
$string['hcol_reason'] = 'कारण';
$string['hcol_previous'] = 'मागील पूर्णता';
$string['hcol_grades'] = 'ग्रेड रीसेट?';
$string['hcol_attempts'] = 'प्रयत्न रीसेट?';
$string['badge_dryrun'] = 'ड्राय-रन';
$string['badge_legacy'] = 'जुने';
$string['badge_self'] = 'स्वतः';
$string['badge_deleted_user'] = 'डिलीट केलेला युजर';
$string['badge_inferred'] = 'अंदाजे';
$string['badge_inferred_title'] = 'या रीसेटची लॉग नोंद सापडली नाही, म्हणून त्याची वेळ त्याने संपवलेल्या पूर्णतेवरून काढली आहे.';
$string['link_evidence'] = 'पुरावा';
$string['badge_legacy_rule'] = 'BizLMS मधून इम्पोर्ट केलेले';
$string['legacy_settings'] = 'या कोर्सची BizLMS सेटिंग्ज';
$string['legacy_unfinished_parity'] = 'हा नियम BizLMS मधून आला आहे. Sentientia इंजिन अजून त्याची सर्व सेटिंग्ज तशीच लागू करत नाही: SCORM ट्रॅकिंग नेहमी साफ केले जाते, कालावधी पूर्ण दिवसांत मोजला जातो, आणि अतिरिक्त प्रयत्न, असाइनमेंट, LTI, प्रश्नावली आणि कस्टम ई-मेल हे पर्याय फक्त संदर्भासाठी ठेवले आहेत. त्यामुळे तो अजून सक्षम करता येत नाही. लर्नरना आत्ताच रीसेट करायचे असल्यास, तुमचा स्वतःचा नियम तयार करा: तो जसा कॉन्फिगर केला तसाच चालतो.';
$string['legacy_enable_blocked'] = 'हा नियम BizLMS मधून आला आहे आणि अजून सक्षम करता येत नाही: Sentientia इंजिन त्याची सर्व सेटिंग्ज तशीच लागू करत नाही (नियमावरील सूचना पाहा). लर्नरना रीसेट करण्यासाठी तुमचा स्वतःचा नियम तयार करा; तो जसा कॉन्फिगर केला तसाच चालतो.';
$string['legacy_dead_scorm'] = 'या कोर्समध्ये फक्त जुने SCORM सेटिंगचे नाव (deletescormdata) होते, जे BizLMS प्लगइनने कधीच वाचले नाही: SCORM साठी त्याने काहीच केले नाही.';
$string['legacy_days'] = '{$a} दिवस';
$string['legacy_choice_0'] = 'काहीही नाही';
$string['legacy_choice_1'] = 'डिलीट करा';
$string['legacy_choice_2'] = 'एक जादा प्रयत्न द्या';
$string['legacy_enable'] = 'पुनर्पूर्णता सुरू होती';
$string['legacy_recompletionduration'] = 'कालावधी';
$string['legacy_deletegradedata'] = 'ग्रेड डिलीट करा';
$string['legacy_archivecompletiondata'] = 'पूर्णता डेटा आर्काइव्ह करा';
$string['legacy_quiz'] = 'क्विझ प्रयत्न';
$string['legacy_archivequiz'] = 'क्विझ प्रयत्न आर्काइव्ह करा';
$string['legacy_scorm'] = 'SCORM';
$string['legacy_archivescorm'] = 'SCORM डेटा आर्काइव्ह करा';
$string['legacy_assign'] = 'असाइनमेंट';
$string['legacy_lti'] = 'LTI ग्रेड';
$string['legacy_archivelti'] = 'LTI ग्रेड आर्काइव्ह करा';
$string['legacy_questionnaire'] = 'प्रश्नावली प्रतिसाद';
$string['legacy_archivequestionnaire'] = 'प्रश्नावली प्रतिसाद आर्काइव्ह करा';
$string['legacy_pulse'] = 'Pulse नोटिफिकेशन';
$string['legacy_recompletionemailenable'] = 'रीसेट झाल्यावर लर्नरला ई-मेल पाठवा';
$string['evidence_title'] = 'रीसेट पुरावा';
$string['evidence_back'] = 'इतिहासाकडे परत';
$string['evidence_learner'] = 'लर्नर';
$string['evidence_course'] = 'कोर्स';
$string['evidence_reset_at'] = 'रीसेटची वेळ';
$string['evidence_reason'] = 'कारण';
$string['evidence_reset_by'] = 'रीसेट करणारे';
$string['evidence_previous'] = 'रीसेटपूर्वी पूर्ण केले';
$string['evidence_grades_reset'] = 'ग्रेड रीसेट केले';
$string['evidence_attempts_reset'] = 'क्विझ प्रयत्न रीसेट केले';
$string['evidence_inferred'] = 'अंदाजे';
$string['evidence_inferred_note'] = 'या रीसेटची वेळ अंदाजे आहे. रीसेटची लॉग नोंद सापडली नाही, म्हणून ही वेळ म्हणजे पूर्णता अधिक नियमाचा कालावधी आणि पुढच्या सायकलचा पहिला पुरावा यांपैकी जे आधी असेल ते.';
$string['evidence_unattached_note'] = 'हा पुरावा कोणत्याही रीसेटशी जोडता आला नाही: रीसेटची लॉग नोंद पर्ज झाली आहे आणि त्यावरून वेळ काढण्यासाठी कोणतीही आर्काइव्ह केलेली पूर्णता उरलेली नाही.';
$string['evidence_none'] = 'या रीसेटसाठी कोणताही पुरावा आर्काइव्ह केलेला नाही.';
$string['evidence_more'] = 'आणखी {$a} ओळी दाखवलेल्या नाहीत.';
$string['evidence_redacted'] = '(लपवलेले)';
$string['evidence_course_gone'] = '(डिलीट केलेला कोर्स)';
$string['evidence_scheduled'] = 'शेड्यूल्ड टास्क';
$string['evidence_self'] = 'लर्नर';
$string['evidence_admin'] = 'एक ॲडमिन';
$string['evidence_admin_erased'] = 'एक ॲडमिन (मिटवलेले)';
$string['evidence_not_recorded'] = 'नोंद नाही';
$string['evidence_view_off'] = 'रीसेट पुरावा व्ह्यू सुरू केलेला नाही.';
$string['evidence_not_found'] = 'असा कोणताही रीसेट नाही, किंवा तो दुसऱ्या टेनंटचा आहे.';
$string['evidence_type_course_completion'] = 'कोर्स पूर्णता';
$string['evidence_type_criteria_completion'] = 'पूर्णतेचे निकष';
$string['evidence_type_activity_completion'] = 'ॲक्टिव्हिटी पूर्णता';
$string['evidence_type_quiz_attempt'] = 'क्विझ प्रयत्न';
$string['evidence_type_quiz_grade'] = 'क्विझ ग्रेड';
$string['evidence_type_scorm_track'] = 'SCORM ट्रॅकिंग';
$string['evidence_type_lti_grade'] = 'LTI ग्रेड';
$string['evidence_type_questionnaire_response'] = 'प्रश्नावली प्रतिसाद';
$string['evidence_type_questionnaire_answer'] = 'प्रश्नावली उत्तरे';
$string['evidence_type_gradebook_grade'] = 'ग्रेड';
$string['col_state'] = 'स्थिती';
$string['col_enrolled'] = 'एनरोल केले';
$string['col_started'] = 'सुरू केले';
$string['col_completed'] = 'पूर्ण केले';
$string['col_criteria'] = 'निकष';
$string['col_grade'] = 'ग्रेड';
$string['col_when'] = 'केव्हा';
$string['col_activity'] = 'ॲक्टिव्हिटी';
$string['col_quiz'] = 'क्विझ';
$string['col_attempt'] = 'प्रयत्न';
$string['col_marks'] = 'गुण';
$string['col_scorm'] = 'SCORM';
$string['col_element'] = 'एलिमेंट';
$string['col_value'] = 'मूल्य';
$string['col_tool'] = 'LTI टूल';
$string['col_questionnaire'] = 'प्रश्नावली';
$string['col_question'] = 'प्रश्न';
$string['col_answer'] = 'उत्तर';
$string['col_item'] = 'ग्रेड आयटम';
$string['privacy:metadata:local_sentientia_recompletion_rules'] = 'पुनर्पूर्णता नियमांच्या व्याख्या';
$string['privacy:metadata:local_sentientia_recompletion_history'] = 'प्रत्येक युजरचा रीसेट ऑडिट लॉग';
$string['privacy:metadata:local_sentientia_recompletion_history:userid'] = 'ज्या युजरची पूर्णता रीसेट झाली तो युजर';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'रीसेट झालेला कोर्स';
$string['privacy:metadata:local_sentientia_recompletion_history:reason'] = 'रीसेट का झाला';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'दुसऱ्याची पूर्णता रीसेट करणारा ॲडमिन (शेड्यूल्ड टास्कने केल्यास रिकामे)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'रीसेट होण्यापूर्वी युजरने कोर्स शेवटचा कधी पूर्ण केला';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'रीसेट कधी झाला';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'रीसेट Sentientia ने केला की BizLMS प्लगइनमधून इम्पोर्ट केला';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'रीसेटने डिलीट केलेला पुरावा: त्याने संपवलेल्या सायकलची पूर्णता, निकष, ॲक्टिव्हिटी पूर्णता, क्विझ प्रयत्न आणि ग्रेड, SCORM ट्रॅकिंग, LTI ग्रेड आणि प्रश्नावली उत्तरे';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'ज्या युजरचा हा पुरावा आहे तो युजर';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'पुरावा ज्या कोर्सचा आहे तो कोर्स';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'ही ओळ कोणत्या प्रकारचा पुरावा आहे';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'पुरावा ज्या ॲक्टिव्हिटीचा आहे ती ॲक्टिव्हिटी';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'पुरावा ज्याचा आहे ते क्विझ, SCORM पॅकेज, LTI टूल, प्रश्नावली, निकष किंवा ग्रेड आयटम';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'SCORM एलिमेंट किंवा प्रश्नावलीतील प्रश्न';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'पूर्णतेची स्थिती, प्रयत्नाची स्थिती किंवा उत्तर';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'ग्रेड, गुण किंवा स्कोअर';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'आर्काइव्ह केलेली गोष्ट कधी घडली';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'आर्काइव्ह केलेली ओळ जशी होती तशीच; यात ॲक्टिव्हिटी पूर्णता ओव्हरराइड करणारा ॲडमिन, ग्रेड शेवटचा बदलणारा शिक्षक आणि त्यांनी लिहिलेला कोणताही फीडबॅक, लर्नरने प्रश्नावलीत टाइप केलेला मजकूर, आणि SCORM ट्रॅकिंगमधील मुक्त मजकूर यांचा समावेश आहे';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'पुरावा कधी आर्काइव्ह केला';
$string['privacy:export:resets_performed'] = 'मी केलेले रीसेट';
$string['privacy:export:evidence'] = 'रीसेटने आर्काइव्ह केलेला पुरावा';
