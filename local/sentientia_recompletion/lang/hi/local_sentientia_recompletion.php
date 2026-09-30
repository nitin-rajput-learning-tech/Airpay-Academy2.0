<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #53 (2026-05-20) — Hindi (hi) translations for local_sentientia_recompletion.
// Scope: recompletion rules, history, capabilities, settings, rule form,
// message providers, event labels, UI, and privacy metadata.
// ADR-032 (2026-09-30): history page, imported rules, evidence view, messages.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया रीकम्प्लीशन';

// Navigation.
$string['rules']     = 'रीकम्प्लीशन नियम';
$string['history']   = 'रीसेट इतिहास';
$string['bulkreset'] = 'थोक रीसेट';

// Capabilities.
$string['sentientia_recompletion:view']   = 'रीकम्प्लीशन नियम और इतिहास देखें';
$string['sentientia_recompletion:manage'] = 'रीकम्प्लीशन नियम प्रबंधित करें';
$string['sentientia_recompletion:reset']  = 'मैन्युअल रूप से यूज़र पूर्णता रीसेट करें';

// Rule status.
$string['enabled']  = 'सक्षम';
$string['disabled'] = 'अक्षम';
$string['running']  = 'चल रहा है';

// Settings.
$string['settings_pre_notify_days']      = 'पूर्व-सूचना विंडो (दिन)';
$string['settings_pre_notify_days_desc'] = 'अनुपालन समाप्त होने से इतने दिन पहले यूज़र्स को सूचित करें। डिफ़ॉल्ट 30।';
$string['settings_max_batch']            = 'प्रति cron रन अधिकतम यूज़र रीसेट';
$string['settings_max_batch_desc']       = 'सुरक्षा कैप ताकि एक गलत-कॉन्फ़िगर नियम एक cron पास में हज़ारों यूज़र्स को रीसेट न कर सके। डिफ़ॉल्ट 500।';
$string['settings_dry_run_default']      = 'ड्राई-रन मोड (डिफ़ॉल्ट OFF)';
$string['settings_dry_run_default_desc'] = 'ON होने पर, दैनिक cron लॉग करता है कि क्या रीसेट किया जाएगा लेकिन वास्तव में कुछ भी रीसेट नहीं करता। नए नियमों के परीक्षण के लिए उपयोगी।';

// Rule form.
$string['rule_name']               = 'नियम का नाम';
$string['rule_courseid']           = 'कोर्स (पूर्णता-सक्षम सभी कोर्स के लिए 0 छोड़ें)';
$string['rule_period_days']        = 'रीसेट अवधि (दिन)';
$string['rule_period_days_help']   = 'प्रत्येक N दिनों में पूर्णता रीसेट करें। 365 = वार्षिक, 90 = त्रैमासिक। अक्षम करने के लिए 0 सेट करें।';
$string['rule_trigger']            = 'ट्रिगर';
$string['rule_trigger_completion'] = 'पूर्णता के N दिन बाद';
$string['rule_trigger_enrolment']  = 'नामांकन के N दिन बाद';
$string['rule_trigger_fixed']      = 'एक निश्चित कैलेंडर तिथि पर';
$string['rule_fixed_date']         = 'निश्चित तिथि (यदि ट्रिगर = निश्चित)';
$string['rule_reset_grades']       = 'क्या ग्रेड भी रीसेट करें?';
$string['rule_reset_attempts']     = 'क्या क्विज़ प्रयास भी रीसेट करें?';
$string['rule_enabled']            = 'सक्षम';

// Message providers.
$string['messageprovider:recompletion_due_soon'] = 'रीकम्प्लीशन जल्द ही देय';
$string['messageprovider:recompletion_reset']    = 'रीकम्प्लीशन रीसेट (पूर्ण हुआ)';
$string['msg_reset_subject'] = 'रीकम्प्लीशन: \'{$a->course}\' रीसेट कर दिया गया है';
$string['msg_reset_body']    = '\'{$a->course}\' की आपकी पिछली पूर्णता {$a->previous} को हुई थी। {$a->days}-दिन के रीकम्प्लीशन नियम के अनुसार, अनुपालन बनाए रखने के लिए आपको इसे दोबारा पूरा करना होगा।';
$string['msg_due_subject']   = 'रीकम्प्लीशन {$a->days} दिनों में देय: \'{$a->course}\'';
$string['msg_due_body']      = 'ध्यान दें — \'{$a->course}\' की आपकी पूर्णता {$a->days} दिन में समाप्त हो जाएगी। अनुपालन बनाए रखने के लिए उससे पहले इसे दोबारा पूरा करने की योजना बनाएँ।';

// Event class names.
$string['event_completion_reset'] = 'कोर्स पूर्णता रीसेट';

// UI.
$string['nrules']               = '{$a} नियम';
$string['rules_empty']          = 'अभी तक कोई रीकम्प्लीशन नियम कॉन्फ़िगर नहीं किया गया।';
$string['history_empty']        = 'अभी तक कोई रीसेट नहीं किया गया।';
$string['no_courses_resetable'] = 'पूर्णता ट्रैकिंग सक्षम वाला कोई कोर्स नहीं — रीकम्प्लीशन के लिए कोर्स पूर्णता कॉन्फ़िगर होनी चाहिए।';

// Reset history page (ADR-032).
$string['history_title']        = 'रीकम्प्लीशन इतिहास';
$string['history_back']         = 'नियमों पर वापस जाएँ';
$string['history_prev']         = 'पिछला';
$string['history_next']         = 'अगला';
$string['history_filtered']     = 'केवल एक कोर्स या शिक्षार्थी दिखाया जा रहा है।';
$string['history_clear_filter'] = 'सभी रीसेट दिखाएँ';
$string['hcol_when']            = 'कब';
$string['hcol_user']            = 'यूज़र';
$string['hcol_course']          = 'कोर्स';
$string['hcol_reason']          = 'कारण';
$string['hcol_previous']        = 'पिछली पूर्णता';
$string['hcol_grades']          = 'ग्रेड रीसेट?';
$string['hcol_attempts']        = 'प्रयास रीसेट?';
$string['badge_dryrun']         = 'ड्राई-रन';
$string['badge_legacy']         = 'पुराना';
$string['badge_self']           = 'स्वयं';
$string['badge_inferred']       = 'अनुमानित';
$string['badge_inferred_title'] = 'इस रीसेट की लॉग पंक्ति नहीं मिली, इसलिए इसका समय उस पूर्णता से निकाला गया है जिसे इसने समाप्त किया।';
$string['link_evidence']        = 'साक्ष्य';

// Imported rules (ADR-032).
$string['badge_legacy_rule']    = 'BizLMS से आयात किया गया';
$string['legacy_settings']      = 'इस कोर्स की BizLMS सेटिंग्स';
$string['legacy_dead_scorm']    = 'इस कोर्स में केवल SCORM का पुराना सेटिंग नाम (deletescormdata) था, जिसे BizLMS प्लगइन ने कभी नहीं पढ़ा: SCORM के लिए यह कुछ नहीं करता था।';
$string['legacy_days']          = '{$a} दिन';
$string['legacy_choice_0']      = 'कुछ नहीं';
$string['legacy_choice_1']      = 'हटाएँ';
$string['legacy_choice_2']      = 'एक अतिरिक्त प्रयास की अनुमति दें';
$string['legacy_enable']                  = 'रीकम्प्लीशन चालू था';
$string['legacy_recompletionduration']    = 'अवधि';
$string['legacy_deletegradedata']         = 'ग्रेड हटाएँ';
$string['legacy_archivecompletiondata']   = 'पूर्णता डेटा संग्रहित करें';
$string['legacy_quiz']                    = 'क्विज़ प्रयास';
$string['legacy_archivequiz']             = 'क्विज़ प्रयास संग्रहित करें';
$string['legacy_scorm']                   = 'SCORM';
$string['legacy_archivescorm']            = 'SCORM डेटा संग्रहित करें';
$string['legacy_assign']                  = 'असाइनमेंट';
$string['legacy_lti']                     = 'LTI ग्रेड';
$string['legacy_archivelti']              = 'LTI ग्रेड संग्रहित करें';
$string['legacy_questionnaire']           = 'प्रश्नावली उत्तर';
$string['legacy_archivequestionnaire']    = 'प्रश्नावली उत्तर संग्रहित करें';
$string['legacy_pulse']                   = 'पल्स सूचनाएँ';
$string['legacy_recompletionemailenable'] = 'रीसेट पर शिक्षार्थी को ई-मेल भेजें';

// Evidence view (ADR-032).
$string['evidence_title']          = 'रीसेट साक्ष्य';
$string['evidence_back']           = 'इतिहास पर वापस जाएँ';
$string['evidence_learner']        = 'शिक्षार्थी';
$string['evidence_course']         = 'कोर्स';
$string['evidence_reset_at']       = 'रीसेट का समय';
$string['evidence_reason']         = 'कारण';
$string['evidence_reset_by']       = 'रीसेट किसने किया';
$string['evidence_previous']       = 'रीसेट से पहले पूर्ण हुआ';
$string['evidence_grades_reset']   = 'ग्रेड रीसेट';
$string['evidence_attempts_reset'] = 'क्विज़ प्रयास रीसेट';
$string['evidence_inferred']       = 'अनुमानित';
$string['evidence_inferred_note']  = 'इस रीसेट का समय एक अनुमान है। रीसेट की लॉग पंक्ति नहीं मिली, इसलिए समय पूर्णता में नियम की अवधि जोड़ने और अगले चक्र के पहले साक्ष्य में से जो पहले हो, वह है।';
$string['evidence_unattached_note'] = 'इस साक्ष्य को किसी रीसेट से नहीं जोड़ा जा सका: रीसेट की लॉग पंक्ति हटा दी गई थी और उससे समय निकालने के लिए कोई संग्रहित पूर्णता नहीं बची थी।';
$string['evidence_none']           = 'इस रीसेट के लिए कोई साक्ष्य संग्रहित नहीं किया गया था।';
$string['evidence_more']           = '{$a} और पंक्तियाँ नहीं दिखाई गई हैं।';
$string['evidence_redacted']       = '(हटाया गया)';
$string['evidence_course_gone']    = '(हटाया गया कोर्स)';
$string['evidence_scheduled']      = 'निर्धारित कार्य';
$string['evidence_self']           = 'शिक्षार्थी';
$string['evidence_admin']          = 'एक एडमिन';
$string['evidence_view_off']       = 'रीसेट साक्ष्य दृश्य चालू नहीं है।';
$string['evidence_not_found']      = 'ऐसा कोई रीसेट नहीं है, या वह किसी दूसरे टेनेंट का है।';
$string['evidence_type_course_completion']      = 'कोर्स पूर्णता';
$string['evidence_type_criteria_completion']    = 'पूर्णता मानदंड';
$string['evidence_type_activity_completion']    = 'गतिविधि पूर्णताएँ';
$string['evidence_type_quiz_attempt']           = 'क्विज़ प्रयास';
$string['evidence_type_quiz_grade']             = 'क्विज़ ग्रेड';
$string['evidence_type_scorm_track']            = 'SCORM ट्रैकिंग';
$string['evidence_type_lti_grade']              = 'LTI ग्रेड';
$string['evidence_type_questionnaire_response'] = 'प्रश्नावली प्रतिक्रियाएँ';
$string['evidence_type_questionnaire_answer']   = 'प्रश्नावली उत्तर';
$string['evidence_type_gradebook_grade']        = 'ग्रेड';
$string['col_state']         = 'स्थिति';
$string['col_enrolled']      = 'नामांकित';
$string['col_started']       = 'शुरू किया';
$string['col_completed']     = 'पूर्ण किया';
$string['col_criteria']      = 'मानदंड';
$string['col_grade']         = 'ग्रेड';
$string['col_when']          = 'कब';
$string['col_activity']      = 'गतिविधि';
$string['col_quiz']          = 'क्विज़';
$string['col_attempt']       = 'प्रयास';
$string['col_marks']         = 'अंक';
$string['col_scorm']         = 'SCORM';
$string['col_element']       = 'तत्व';
$string['col_value']         = 'मान';
$string['col_tool']          = 'LTI टूल';
$string['col_questionnaire'] = 'प्रश्नावली';
$string['col_question']      = 'प्रश्न';
$string['col_answer']        = 'उत्तर';
$string['col_item']          = 'ग्रेड आइटम';

// Privacy.
$string['privacy:metadata:local_sentientia_recompletion_rules']            = 'रीकम्प्लीशन नियम परिभाषाएँ';
$string['privacy:metadata:local_sentientia_recompletion_history']          = 'प्रति-यूज़र रीसेट ऑडिट लॉग';
$string['privacy:metadata:local_sentientia_recompletion_history:userid']   = 'जिस यूज़र की पूर्णता रीसेट की गई';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'जो कोर्स रीसेट किया गया';
$string['privacy:metadata:local_sentientia_recompletion_history:reason']   = 'रीसेट क्यों ट्रिगर हुआ';
$string['privacy:metadata:local_sentientia_recompletion_history:reset_by_userid'] = 'वह एडमिन जिसने किसी और की पूर्णता रीसेट की (शेड्यूल्ड टास्क द्वारा किए गए रीसेट के लिए खाली)';
$string['privacy:metadata:local_sentientia_recompletion_history:previous_timecompleted'] = 'रीसेट से पहले यूज़र ने कोर्स आख़िरी बार कब पूरा किया था';
$string['privacy:metadata:local_sentientia_recompletion_history:timecreated'] = 'रीसेट कब हुआ';
$string['privacy:metadata:local_sentientia_recompletion_history:source'] = 'रीसेट सेंटिएंटिया ने किया था या BizLMS प्लगइन से आयात किया गया';
$string['privacy:metadata:local_sentientia_recompletion_archive'] = 'रीसेट ने जो साक्ष्य हटाया: उसके द्वारा समाप्त किए गए चक्र की पूर्णता, मानदंड, गतिविधि पूर्णताएँ, क्विज़ प्रयास और ग्रेड, SCORM ट्रैकिंग, LTI ग्रेड और प्रश्नावली उत्तर';
$string['privacy:metadata:local_sentientia_recompletion_archive:userid'] = 'जिस यूज़र का यह साक्ष्य है';
$string['privacy:metadata:local_sentientia_recompletion_archive:courseid'] = 'जिस कोर्स का साक्ष्य है';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemtype'] = 'पंक्ति किस प्रकार का साक्ष्य है';
$string['privacy:metadata:local_sentientia_recompletion_archive:cmid'] = 'जिस गतिविधि का साक्ष्य है';
$string['privacy:metadata:local_sentientia_recompletion_archive:instanceid'] = 'जिस क्विज़, SCORM पैकेज, LTI टूल, प्रश्नावली, मानदंड या ग्रेड आइटम का साक्ष्य है';
$string['privacy:metadata:local_sentientia_recompletion_archive:itemkey'] = 'SCORM तत्व या प्रश्नावली का प्रश्न';
$string['privacy:metadata:local_sentientia_recompletion_archive:state'] = 'पूर्णता की स्थिति, प्रयास की स्थिति या उत्तर';
$string['privacy:metadata:local_sentientia_recompletion_archive:grade'] = 'ग्रेड, अंक या स्कोर';
$string['privacy:metadata:local_sentientia_recompletion_archive:timeevent'] = 'संग्रहित घटना कब हुई';
$string['privacy:metadata:local_sentientia_recompletion_archive:payload'] = 'संग्रहित पंक्ति जैसी थी ठीक वैसी, जिसमें गतिविधि पूर्णता को ओवरराइड करने वाला एडमिन और शिक्षार्थी द्वारा प्रश्नावली में लिखा गया पाठ शामिल है';
$string['privacy:metadata:local_sentientia_recompletion_archive:timecreated'] = 'साक्ष्य कब संग्रहित किया गया';
$string['privacy:export:resets_performed'] = 'मेरे द्वारा किए गए रीसेट';
$string['privacy:export:evidence'] = 'रीसेट द्वारा संग्रहित साक्ष्य';
