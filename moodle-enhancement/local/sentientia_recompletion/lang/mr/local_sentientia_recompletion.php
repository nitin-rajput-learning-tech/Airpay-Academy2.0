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
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia रीकम्प्लीशन';
$string['rules'] = 'रीकम्प्लीशन नियम';
$string['history'] = 'रीसेट हिस्ट्री';
$string['bulkreset'] = 'बल्क रीसेट';
$string['sentientia_recompletion:view'] = 'रीकम्प्लीशन नियम आणि हिस्ट्री पाहा';
$string['sentientia_recompletion:manage'] = 'रीकम्प्लीशन नियम मॅनेज करा';
$string['sentientia_recompletion:reset'] = 'युजर्सचे कम्प्लीशन मॅन्युअली रीसेट करा';
$string['enabled'] = 'चालू';
$string['disabled'] = 'बंद';
$string['running'] = 'रन होत आहे';
$string['settings_pre_notify_days'] = 'आगाऊ नोटिफिकेशनचा कालावधी (दिवस)';
$string['settings_pre_notify_days_desc'] = 'युजर्सच्या कम्प्लायन्सची मुदत संपण्याच्या इतके दिवस आधी त्यांना कळवा. डीफॉल्ट 30.';
$string['settings_max_batch'] = 'प्रत्येक क्रॉन रनमध्ये जास्तीत जास्त किती युजर्स रीसेट करायचे';
$string['settings_max_batch_desc'] = 'चुकीच्या पद्धतीने कॉन्फिगर केलेल्या नियमाने एकाच क्रॉन रनमध्ये हजारो युजर्स रीसेट करू नयेत यासाठी सुरक्षा मर्यादा. डीफॉल्ट 500.';
$string['settings_dry_run_default'] = 'ड्राय-रन मोड (डीफॉल्ट OFF)';
$string['settings_dry_run_default_desc'] = 'ON असताना, रोजचा क्रॉन काय रीसेट झाले असते ते लॉग करतो, पण प्रत्यक्षात काहीही रीसेट करत नाही. नवीन नियम टेस्ट करण्यासाठी उपयुक्त.';
$string['rule_name'] = 'नियमाचे नाव';
$string['rule_courseid'] = 'कोर्स (कम्प्लीशन चालू असलेल्या सर्व कोर्सेससाठी 0 ठेवा)';
$string['rule_period_days'] = 'रीसेट कालावधी (दिवस)';
$string['rule_period_days_help'] = 'दर N दिवसांनी कम्प्लीशन रीसेट करा. 365 = वार्षिक, 90 = त्रैमासिक. बंद करण्यासाठी 0 ठेवा.';
$string['rule_trigger'] = 'ट्रिगर';
$string['rule_trigger_completion'] = 'पूर्ण केल्यानंतर N दिवसांनी';
$string['rule_trigger_enrolment'] = 'एनरोल झाल्यानंतर N दिवसांनी';
$string['rule_trigger_fixed'] = 'ठरलेल्या कॅलेंडर तारखेला';
$string['rule_fixed_date'] = 'ठरलेली तारीख (ट्रिगर = ठरलेली तारीख असल्यास)';
$string['rule_reset_grades'] = 'ग्रेडही रीसेट करायचे?';
$string['rule_reset_attempts'] = 'क्विझ प्रयत्नही रीसेट करायचे?';
$string['rule_enabled'] = 'चालू';
$string['messageprovider:recompletion_due_soon'] = 'रीकम्प्लीशनची शेवटची तारीख जवळ आली';
$string['messageprovider:recompletion_reset'] = 'रीकम्प्लीशन रीसेट (पूर्ण झाले)';
$string['msg_reset_subject'] = 'रीकम्प्लीशन: \'{$a->course}\' रीसेट झाला आहे';
$string['msg_reset_body'] = 'तुम्ही \'{$a->course}\' आधी {$a->previous} रोजी पूर्ण केला होता. {$a->days} दिवसांच्या रीकम्प्लीशन नियमानुसार, कम्प्लायन्स कायम ठेवण्यासाठी तुम्हाला तो पुन्हा पूर्ण करावा लागेल.';
$string['msg_due_subject'] = 'रीकम्प्लीशनसाठी {$a->days} दिवस बाकी: \'{$a->course}\'';
$string['msg_due_body'] = 'लक्षात घ्या — \'{$a->course}\' च्या तुमच्या कम्प्लीशनची मुदत {$a->days} दिवसांत संपेल. कम्प्लायन्स कायम ठेवण्यासाठी त्याआधी तो पुन्हा पूर्ण करण्याचे नियोजन करा.';
$string['event_completion_reset'] = 'कोर्स कम्प्लीशन रीसेट';
$string['nrules'] = '{$a} नियम';
$string['rules_empty'] = 'अजून एकही रीकम्प्लीशन नियम कॉन्फिगर केलेला नाही.';
$string['history_empty'] = 'अजून एकही रीसेट झालेला नाही.';
$string['no_courses_resetable'] = 'कम्प्लीशन ट्रॅकिंग चालू असलेला एकही कोर्स नाही — रीकम्प्लीशनसाठी कोर्स कम्प्लीशन कॉन्फिगर केलेले असावे लागते.';
$string['history_title'] = 'रीकम्प्लीशन हिस्ट्री';
$string['history_back'] = 'नियमांकडे परत जा';
$string['history_prev'] = 'मागील';
$string['history_next'] = 'पुढील';
$string['history_filtered'] = 'फक्त एक कोर्स किंवा एक लर्नर दाखवत आहे.';
$string['history_clear_filter'] = 'सर्व रीसेट दाखवा';
$string['hcol_when'] = 'कधी';
$string['hcol_user'] = 'युजर';
$string['hcol_course'] = 'कोर्स';
$string['hcol_reason'] = 'कारण';
$string['hcol_previous'] = 'मागील कम्प्लीशन';
$string['hcol_grades'] = 'ग्रेड रीसेट?';
$string['hcol_attempts'] = 'प्रयत्न रीसेट?';
$string['badge_dryrun'] = 'ड्राय-रन';
$string['badge_legacy'] = 'लेगसी';
$string['badge_self'] = 'स्वतः';
$string['badge_deleted_user'] = 'डिलीट केलेला युजर';
$string['badge_inferred'] = 'अंदाजे';
$string['badge_inferred_title'] = 'या रीसेटची लॉग नोंद सापडली नाही, म्हणून त्याची वेळ त्याने संपवलेल्या कम्प्लीशनवरून काढली आहे.';
$string['link_evidence'] = 'पुरावा';
$string['badge_legacy_rule'] = 'BizLMS मधून इम्पोर्ट केलेले';
$string['legacy_settings'] = 'या कोर्सची BizLMS सेटिंग्ज';
$string['legacy_unfinished_parity'] = 'हा नियम BizLMS मधून आला आहे. Sentientia इंजिन अजून त्याची सर्व सेटिंग्ज तशीच लागू करत नाही: SCORM ट्रॅकिंग नेहमी साफ केले जाते, कालावधी पूर्ण दिवसांत मोजला जातो, आणि अतिरिक्त प्रयत्न, असाइनमेंट, LTI, प्रश्नावली आणि कस्टम ईमेल हे पर्याय फक्त संदर्भासाठी ठेवले आहेत. त्यामुळे तो अजून सुरू करता येत नाही. लर्नर्सना आत्ताच रीसेट करायचे असल्यास, तुमचा स्वतःचा नियम तयार करा: तो जसा कॉन्फिगर केला तसाच चालतो.';
$string['legacy_enable_blocked'] = 'हा नियम BizLMS मधून आला आहे आणि अजून सुरू करता येत नाही: Sentientia इंजिन त्याची सर्व सेटिंग्ज तशीच लागू करत नाही (नियमावरील सूचना पाहा). लर्नर्सना रीसेट करण्यासाठी तुमचा स्वतःचा नियम तयार करा; तो जसा कॉन्फिगर केला तसाच चालतो.';
$string['legacy_dead_scorm'] = 'या कोर्समध्ये फक्त जुने SCORM सेटिंगचे नाव (deletescormdata) होते, जे BizLMS प्लगइनने कधीच वाचले नाही: SCORM साठी त्याने काहीच केले नाही.';
$string['legacy_days'] = '{$a} दिवस';
$string['legacy_choice_0'] = 'काहीही नाही';
$string['legacy_choice_1'] = 'डिलीट करा';
$string['legacy_choice_2'] = 'एक जादा प्रयत्न द्या';
$string['legacy_enable'] = 'रीकम्प्लीशन चालू होते';
$string['legacy_recompletionduration'] = 'कालावधी';
$string['legacy_deletegradedata'] = 'ग्रेड डिलीट करा';
$string['legacy_archivecompletiondata'] = 'कम्प्लीशन डेटा आर्काइव्ह करा';
$string['legacy_quiz'] = 'क्विझ प्रयत्न';
$string['legacy_archivequiz'] = 'क्विझ प्रयत्न आर्काइव्ह करा';
$string['legacy_scorm'] = 'SCORM';
$string['legacy_archivescorm'] = 'SCORM डेटा आर्काइव्ह करा';
$string['legacy_assign'] = 'असाइनमेंट';
$string['legacy_lti'] = 'LTI ग्रेड';
$string['legacy_archivelti'] = 'LTI ग्रेड आर्काइव्ह करा';
$string['legacy_questionnaire'] = 'प्रश्नावली प्रतिसाद';
$string['legacy_archivequestionnaire'] = 'प्रश्नावली प्रतिसाद आर्काइव्ह करा';
$string['legacy_pulse'] = 'Pulse नोटिफिकेशन्स';
$string['legacy_recompletionemailenable'] = 'रीसेट झाल्यावर लर्नरला ईमेल पाठवा';
$string['evidence_title'] = 'रीसेट पुरावा';
$string['evidence_back'] = 'हिस्ट्रीकडे परत जा';
$string['evidence_learner'] = 'लर्नर';
$string['evidence_course'] = 'कोर्स';
$string['evidence_reset_at'] = 'रीसेटची वेळ';
$string['evidence_reason'] = 'कारण';
$string['evidence_reset_by'] = 'रीसेट करणारे';
$string['evidence_previous'] = 'रीसेटपूर्वी पूर्ण केले';
$string['evidence_grades_reset'] = 'ग्रेड रीसेट केले';
$string['evidence_attempts_reset'] = 'क्विझ प्रयत्न रीसेट केले';
$string['evidence_inferred'] = 'अंदाजे';
$string['evidence_inferred_note'] = 'या रीसेटची वेळ अंदाजे आहे. रीसेटची लॉग नोंद सापडली नाही, म्हणून ही वेळ म्हणजे (कम्प्लीशन + नियमाचा कालावधी) आणि पुढच्या सायकलचा पहिला पुरावा यांपैकी जे आधी असेल ते.';
$string['evidence_unattached_note'] = 'हा पुरावा कोणत्याही रीसेटशी जोडता आला नाही: रीसेटची लॉग नोंद पर्ज झाली आहे आणि त्यावरून वेळ काढण्यासाठी कोणतेही आर्काइव्ह केलेले कम्प्लीशन उरलेले नाही.';
$string['evidence_none'] = 'या रीसेटसाठी कोणताही पुरावा आर्काइव्ह केलेला नाही.';
$string['evidence_more'] = 'आणखी {$a} ओळी दाखवलेल्या नाहीत.';
$string['evidence_redacted'] = '(लपवलेले)';
$string['evidence_course_gone'] = '(डिलीट केलेला कोर्स)';
$string['evidence_scheduled'] = 'शेड्यूल्ड टास्क';
$string['evidence_self'] = 'लर्नर';
$string['evidence_admin'] = 'एक ॲडमिन';
$string['evidence_admin_erased'] = 'एक ॲडमिन (मिटवलेला)';
$string['evidence_not_recorded'] = 'नोंद नाही';
$string['evidence_view_off'] = 'रीसेट पुरावा व्ह्यू चालू नाही.';
$string['evidence_not_found'] = 'असा कोणताही रीसेट नाही, किंवा तो दुसऱ्या टेनंटचा आहे.';
$string['evidence_type_course_completion'] = 'कोर्स कम्प्लीशन';
$string['evidence_type_criteria_completion'] = 'कम्प्लीशनचे निकष';
$string['evidence_type_activity_completion'] = 'ॲक्टिव्हिटी कम्प्लीशन्स';
$string['evidence_type_quiz_attempt'] = 'क्विझ प्रयत्न';
$string['evidence_type_quiz_grade'] = 'क्विझ ग्रेड';
$string['evidence_type_scorm_track'] = 'SCORM ट्रॅकिंग';
$string['evidence_type_lti_grade'] = 'LTI ग्रेड';
$string['evidence_type_questionnaire_response'] = 'प्रश्नावली प्रतिसाद';
$string['evidence_type_questionnaire_answer'] = 'प्रश्नावली उत्तरे';
$string['evidence_type_gradebook_grade'] = 'ग्रेड';
$string['col_state'] = 'स्टेटस';
$string['col_enrolled'] = 'एनरोल झाले';
$string['col_started'] = 'सुरू झाले';
$string['col_completed'] = 'पूर्ण झाले';
$string['col_criteria'] = 'निकष';
$string['col_grade'] = 'ग्रेड';
$string['col_when'] = 'कधी';
$string['col_activity'] = 'ॲक्टिव्हिटी';
$string['col_quiz'] = 'क्विझ';
$string['col_attempt'] = 'प्रयत्न';
$string['col_marks'] = 'गुण';
$string['col_scorm'] = 'SCORM';
$string['col_element'] = 'एलिमेंट';
$string['col_value'] = 'व्हॅल्यू';
$string['col_tool'] = 'LTI टूल';
$string['col_questionnaire'] = 'प्रश्नावली';
$string['col_question'] = 'प्रश्न';
$string['col_answer'] = 'उत्तर';
$string['col_item'] = 'ग्रेड आयटम';
$string['privacy:metadata:local_sentientia_recompletion_rules'] = 'रीकम्प्लीशन नियमांच्या व्याख्या';
$string['privacy:metadata:local_sentientia_recompletion_history'] = 'प्रत्येक युजरचा रीसेट ऑडिट लॉग';
$string['privacy:metadata:local_sentientia_recompletion_history:userid'] = 'ज्या युजरचे कम्प्लीशन रीसेट झाले तो युजर';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'रीसेट झालेला कोर्स';
$string['privacy:metadata:local_sentientia_recompletion_history:reason'] = 'रीसेट का झाला';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'दुसऱ्याचे कम्प्लीशन रीसेट करणारा ॲडमिन (शेड्यूल्ड टास्कने केल्यास रिकामे)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'रीसेट होण्यापूर्वी युजरने कोर्स शेवटचा कधी पूर्ण केला';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'रीसेट कधी झाला';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'रीसेट Sentientia ने केला की BizLMS प्लगइनमधून इम्पोर्ट केला';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'रीसेटने डिलीट केलेला पुरावा: त्याने संपवलेल्या सायकलचे कम्प्लीशन, निकष, ॲक्टिव्हिटी कम्प्लीशन्स, क्विझ प्रयत्न आणि ग्रेड, SCORM ट्रॅकिंग, LTI ग्रेड आणि प्रश्नावली उत्तरे';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'ज्या युजरचा हा पुरावा आहे तो युजर';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'पुरावा ज्या कोर्सचा आहे तो कोर्स';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'ही ओळ कोणत्या प्रकारचा पुरावा आहे';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'पुरावा ज्या ॲक्टिव्हिटीचा आहे ती ॲक्टिव्हिटी';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'पुरावा ज्याचा आहे ते क्विझ, SCORM पॅकेज, LTI टूल, प्रश्नावली, निकष किंवा ग्रेड आयटम';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'SCORM एलिमेंट किंवा प्रश्नावलीतील प्रश्न';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'कम्प्लीशनचा स्टेटस, प्रयत्नाचा स्टेटस किंवा उत्तर';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'ग्रेड, गुण किंवा स्कोअर';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'आर्काइव्ह केलेली गोष्ट कधी घडली';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'आर्काइव्ह केलेली ओळ जशी होती तशीच; यात ॲक्टिव्हिटी कम्प्लीशन ओव्हरराइड करणारा ॲडमिन, ग्रेड शेवटचा बदलणारा शिक्षक आणि त्यांनी लिहिलेला कोणताही फीडबॅक, लर्नरने प्रश्नावलीत टाइप केलेला मजकूर, आणि SCORM ट्रॅकिंगमधील मुक्त मजकूर यांचा समावेश आहे';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'पुरावा कधी आर्काइव्ह केला';
$string['privacy:export:resets_performed'] = 'मी केलेले रीसेट';
$string['privacy:export:evidence'] = 'रीसेटने आर्काइव्ह केलेला पुरावा';
