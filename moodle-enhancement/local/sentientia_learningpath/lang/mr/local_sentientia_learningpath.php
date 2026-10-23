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
 * Marathi strings for local_sentientia_learningpath.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia लर्निंग पाथ';
$string['sentientia_learningpath:manage'] = 'लर्निंग पाथ मॅनेज करा';
$string['sentientia_learningpath:view'] = 'लर्निंग पाथ पाहा';
$string['sentientia_learningpath:enrol'] = 'युजर्सना लर्निंग पाथमध्ये एनरोल करा';
$string['sentientia_learningpath:create'] = 'लर्निंग पाथ तयार करा';
$string['sentientia_learningpath:update'] = 'लर्निंग पाथ एडिट करा';
$string['sentientia_learningpath:delete'] = 'लर्निंग पाथ डिलीट करा';
$string['addpath'] = 'लर्निंग पाथ जोडा';
$string['editpath'] = 'लर्निंग पाथ एडिट करा';
$string['deletepath'] = 'लर्निंग पाथ डिलीट करा';
$string['archivepath'] = 'पाथ आर्काइव्ह करा';
$string['activatepath'] = 'पाथ ॲक्टिव्हेट करा';
$string['heading_basic'] = 'पाथचे तपशील';
$string['heading_org'] = 'संस्था';
$string['heading_window'] = 'एनरोलमेंट कालावधी (ऐच्छिक)';
$string['heading_status'] = 'स्टेटस';
$string['startdate'] = 'सुरुवातीची तारीख';
$string['startdate_help'] = 'ऐच्छिक. या तारखेपासून पाथमध्ये एनरोल करता येईल. लगेच एनरोलमेंट सुरू करण्यासाठी रिकामे ठेवा.';
$string['enddate'] = 'शेवटची तारीख';
$string['enddate_help'] = 'ऐच्छिक. या तारखेनंतर पाथ नवीन एनरोलमेंट स्वीकारणार नाही. आधीपासून एनरोल असलेल्या लर्नर्सवर काहीही परिणाम होणार नाही.';
$string['enddate_before_start'] = 'शेवटची तारीख सुरुवातीच्या तारखेला किंवा त्यानंतरची असली पाहिजे.';
$string['audience_modal_title'] = 'टार्गेट ऑडियन्सनुसार बल्क एनरोल';
$string['audience_form_intro'] = 'युजर्सच्या ग्रुपला टार्गेट करण्यासाठी एक किंवा अधिक फिल्टर निवडा. तुम्ही फिल्टर बदलताच खालील प्रिव्ह्यू अपडेट होतो. कन्फर्म करण्यासाठी "जुळणाऱ्या युजर्सना एनरोल करा" वर क्लिक करा.';
$string['audience_any'] = 'कोणतेही';
$string['audience_any_cohort'] = 'कोणताही कोहॉर्ट';
$string['audience_users_matched'] = 'युजर्स जुळतात';
$string['audience_pick_at_least_one'] = 'किमान एक फिल्टर निवडा (सर्व युजर्सना एनरोल करण्यासाठी नेहमीचा एनरोल युजर्स फॉर्म वापरा).';
$string['audience_enrol_button'] = 'जुळणाऱ्या युजर्सना एनरोल करा';
$string['audience_enrol_result'] = '%d नवीन एनरोलमेंट; %d युजर्स ऑडियन्सशी जुळले.';
$string['designation'] = 'पद';
$string['region'] = 'प्रदेश';
$string['location'] = 'ठिकाण';
$string['employmenttype'] = 'नोकरीचा प्रकार';
$string['cohort'] = 'कोहॉर्ट';
$string['name'] = 'पाथचे नाव';
$string['description'] = 'वर्णन';
$string['organisation'] = 'संस्था (टेनंट)';
$string['organisation_help'] = 'हा लर्निंग पाथ कोणत्या टेनंटचा आहे ते निवडा. सर्व टेनंट्ससाठी उपलब्ध करण्यासाठी "कोणतीही विशिष्ट संस्था नाही" असेच ठेवा.';
$string['status'] = 'स्टेटस';
$string['status_active'] = 'ॲक्टिव्ह';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['missingrequiredfields'] = 'आवश्यक फील्ड्स भरलेली नाहीत.';
$string['confirmdelete'] = 'तुम्हाला नक्की "{$a}" डिलीट करायचा आहे का? यामुळे पाथ, त्याचे सर्व कोर्स असाइनमेंट आणि लर्नर एनरोलमेंट कायमचे काढून टाकले जातील. हे परत आणता येणार नाही.';
$string['confirmarchive'] = 'तुम्हाला नक्की "{$a}" आर्काइव्ह करायचा आहे का? तो नवीन लर्नर्ससाठी उपलब्ध राहणार नाही.';
$string['confirmactivate'] = '"{$a}" ॲक्टिव्ह करायचा? लर्नर्स पुन्हा एनरोल करू शकतील.';
$string['pathcreated'] = 'लर्निंग पाथ तयार झाला.';
$string['pathupdated'] = 'लर्निंग पाथ अपडेट झाला.';
$string['pathdeleted'] = 'लर्निंग पाथ डिलीट झाला.';
$string['pathstatuschanged'] = 'स्टेटस अपडेट झाला.';
$string['toomanycourses'] = 'एका रिक्वेस्टमध्ये खूप जास्त कोर्सेस आहेत. प्रत्येक कॉलसाठी मर्यादा 100 आहे.';
$string['toomanyusers'] = 'एका रिक्वेस्टमध्ये खूप जास्त युजर्स आहेत. प्रत्येक कॉलसाठी मर्यादा 500 आहे.';
$string['filterstoolong'] = 'फिल्टर पेलोड खूप मोठा आहे.';
$string['view_path_title'] = 'लर्निंग पाथ: {$a}';
$string['tab_overview'] = 'ओव्हरव्ह्यू';
$string['tab_courses'] = 'कोर्सेस';
$string['tab_users'] = 'युजर्स';
$string['add_courses'] = 'कोर्सेस जोडा';
$string['enrol_users'] = 'युजर्सना एनरोल करा';
$string['back_to_paths'] = 'लर्निंग पाथकडे परत जा';
$string['confirm_unassign_course'] = '"{$a}" या लर्निंग पाथमधून काढायचा? युजर्सचे कोर्स कम्प्लिशन तसेच राहतील.';
$string['confirm_unenrol_user'] = '{$a} यांना या लर्निंग पाथमधून अनएनरोल करायचे?';
$string['unenrol_courses_remain'] = 'लर्नरला पाथमधून काढले आहे, पण BizLMS इम्पोर्टने त्यांना या प्लॅनमधून या कोर्सेसमध्येही एनरोल केले होते, आणि ते एनरोलमेंट काढलेले नाहीत: {$a}. लर्नरचा ॲक्सेस काढायचा असल्यास, प्रत्येक कोर्सच्या पार्टिसिपंट्स पेजवरून त्यांना काढा.';
$string['no_courses_assigned'] = 'अजून कोणतेही कोर्सेस असाइन केलेले नाहीत. सुरुवात करण्यासाठी "कोर्सेस जोडा" वर क्लिक करा.';
$string['no_users_enrolled'] = 'अजून कोणतेही युजर्स एनरोल केलेले नाहीत. लर्नर्स जोडण्यासाठी "युजर्सना एनरोल करा" वर क्लिक करा.';
$string['task_adaptive_sweep'] = 'ॲडॅप्टिव्ह जर्नी वेग स्वीप';
$string['adaptive_mode'] = 'ॲडॅप्टिव्ह मोड';
$string['adaptive_mode_help'] = 'हे सुरू केल्यावर, क्विझ स्कोअर, कम्प्लिशनचा वेग आणि स्किल्स गॅप सिग्नल्सच्या आधारे हा पाथ आपोआप ब्रँच होतो, वेग वाढवतो किंवा रेमिडिएशन देतो.';
$string['adaptive_mode_off'] = 'स्टॅटिक (क्रमाने)';
$string['adaptive_mode_on'] = 'ॲडॅप्टिव्ह (परफॉर्मन्सनुसार बदलणारा)';
$string['score_threshold_low'] = 'रेमिडिएशन थ्रेशोल्ड (%)';
$string['score_threshold_low_help'] = 'या टक्केवारीइतके किंवा त्यापेक्षा कमी क्विझ स्कोअर रेमिडिएशन ब्रँच ट्रिगर करतात. सिस्टम डीफॉल्ट (50%) वापरण्यासाठी रिकामे ठेवा.';
$string['score_threshold_high'] = 'ॲक्सिलरेशन थ्रेशोल्ड (%)';
$string['score_threshold_high_help'] = 'या टक्केवारीइतके किंवा त्यापेक्षा जास्त क्विझ स्कोअर, जास्त कम्प्लिशन वेगासोबत, ॲक्सिलरेशन ब्रँच ट्रिगर करतात. सिस्टम डीफॉल्ट (80%) वापरण्यासाठी रिकामे ठेवा.';
$string['pivot_remediate'] = 'रेमिडिएट केले';
$string['pivot_accelerate'] = 'ॲक्सिलरेट केले';
$string['pivot_branch'] = 'ब्रँच केले';
$string['pivot_no_action'] = 'कोणतीही ॲक्शन नाही';
$string['trigger_quiz_score'] = 'क्विझ स्कोअर';
$string['trigger_velocity'] = 'कम्प्लिशनचा वेग';
$string['trigger_skills_gap'] = 'स्किल्स गॅप';
$string['trigger_combined'] = 'एकत्रित सिग्नल्स';
$string['adaptive_log_empty'] = 'या पाथसाठी अजून कोणतेही ॲडॅप्टिव्ह निर्णय नोंदवलेले नाहीत.';
$string['adaptive_log_title'] = 'ॲडॅप्टिव्ह निर्णय लॉग';
$string['adaptive_feature_flag_off'] = 'ॲडॅप्टिव्ह लर्निंग जर्नी फीचर सध्या बंद आहे. ते सुरू करण्यासाठी Switchboard मध्ये "sentientia.learningpath.adaptive.enabled" फ्लॅग चालू करा.';
$string['privacy:metadata:lp_adaptive_log'] = 'लर्निंग पाथसाठी ॲडॅप्टिव्ह जर्नी निर्णय लॉग.';
$string['privacy:metadata:lp_adaptive_log:userid'] = 'ज्या युजरचा पाथ बदलला गेला तो युजर.';
$string['privacy:metadata:lp_adaptive_log:pathid'] = 'ज्या लर्निंग पाथवर निर्णय घेतला गेला तो पाथ.';
$string['privacy:metadata:lp_adaptive_log:pivot_type'] = 'लागू केलेल्या पिव्हटचा प्रकार.';
$string['privacy:metadata:lp_adaptive_log:quiz_score'] = 'पिव्हट ट्रिगर करणारा क्विझ स्कोअर (लागू असल्यास).';
$string['privacy:metadata:lp_adaptive_log:timecreated'] = 'ॲडॅप्टिव्ह निर्णय नोंदवला गेला ती वेळ.';
$string['privacy:metadata'] = 'Airpay लर्निंग पाथ प्लगइन युजर्सचे पाथ-स्तरावरील एनरोलमेंट, प्रोग्रेस आणि ॲडॅप्टिव्ह जर्नीचे निर्णय साठवते.';
$string['privacy:metadata:lp'] = 'प्रत्येक लर्निंग पाथसाठी युजर असाइनमेंट.';
$string['privacy:metadata:lp:pathid'] = 'लर्निंग पाथ ID.';
$string['privacy:metadata:lp:userid'] = 'असाइन केलेल्या युजरचा ID.';
$string['privacy:metadata:lp:status'] = 'असाइनमेंट स्टेटस.';
$string['privacy:metadata:lp:timecreated'] = 'असाइनमेंटचा टाइमस्टॅम्प.';
$string['privacy:metadata:lp:timecompleted'] = 'असाइनमेंट पूर्ण झाल्याचा टाइमस्टॅम्प.';
$string['privacy:metadata:lp:timemodified'] = 'शेवटच्या अपडेटचा टाइमस्टॅम्प.';
$string['mypaths_title'] = 'माझे लर्निंग पाथ';
$string['mypaths_summary'] = 'तुमच्या लर्निंग पाथचा सारांश';
$string['mypaths_stat_total'] = 'सर्व पाथ';
$string['mypaths_empty'] = 'तुम्ही अजून कोणत्याही लर्निंग पाथमध्ये एनरोल केलेले नाही.';
$string['mypaths_progress'] = 'प्रोग्रेस';
$string['mypaths_enrolled_on'] = 'एनरोल केल्याची तारीख';
$string['mypaths_completed_on'] = 'पूर्ण झाल्याची तारीख';
$string['mypaths_opens_on'] = 'सुरू होण्याची तारीख';
$string['mypaths_closes_on'] = 'बंद होण्याची तारीख';
$string['mypaths_courses'] = 'कोर्सेस';
$string['mypaths_course_done'] = 'पूर्ण';
$string['mypaths_optional'] = 'ऐच्छिक';
$string['lpstate_enrolled'] = 'सुरू केलेले नाही';
$string['lpstate_inprogress'] = 'सुरू आहे';
$string['lpstate_completed'] = 'पूर्ण';
$string['learner_paths_off'] = 'लर्निंग पाथचे पेज अजून उपलब्ध नाही.';
$string['imported_history_protected'] = 'ही आधीच्या सिस्टममधून आणलेली लर्निंग हिस्टरी आहे आणि ती संरक्षित आहे. ती डिलीट करण्याऐवजी किंवा तिचे लर्नर्स काढण्याऐवजी लर्निंग पाथ आर्काइव्ह करा.';
$string['privacy:metadata:lp:enrolledby'] = 'लर्नरला पाथमध्ये कोणी एनरोल केले (सेल्फ-एनरोलमेंटमध्ये लर्नर स्वतः).';
$string['privacy:metadata:lp_path'] = 'लर्निंग पाथ. ज्यांनी पाथ तयार केला किंवा शेवटचा एडिट केला, फक्त त्या व्यक्तींची माहिती वैयक्तिक डेटा आहे.';
$string['privacy:metadata:lp_path:usercreated'] = 'पाथ तयार करणारा युजर.';
$string['privacy:metadata:lp_path:usermodified'] = 'पाथ शेवटचा एडिट करणारा युजर.';
$string['privacy:metadata:lp_course'] = 'लर्निंग पाथवर ठेवलेले कोर्सेस. ज्यांनी ओळ जोडली किंवा बदलली, फक्त त्या व्यक्तींची माहिती वैयक्तिक डेटा आहे.';
$string['privacy:metadata:lp_course:usercreated'] = 'कोर्स पाथवर ठेवणारा युजर.';
$string['privacy:metadata:lp_course:usermodified'] = 'ओळ शेवटची बदलणारा युजर.';
$string['privacy:metadata:lp_course_status'] = 'आधीच्या सिस्टममधून आणलेल्या, लर्निंग पाथवरील लर्नरच्या प्रत्येक कोर्सच्या स्टेटसच्या ओळी.';
$string['privacy:metadata:lp_course_status:pathid'] = 'लर्निंग पाथ ID.';
$string['privacy:metadata:lp_course_status:courseid'] = 'कोर्स ID.';
$string['privacy:metadata:lp_course_status:userid'] = 'लर्नर.';
$string['privacy:metadata:lp_course_status:status'] = 'आधीच्या सिस्टमने जसे साठवले तसेच ठेवलेले स्टेटस मूल्य.';
$string['privacy:metadata:lp_course_status:percentage'] = 'आधीच्या सिस्टमने जसे साठवले तसेच ठेवलेले टक्केवारी मूल्य.';
$string['privacy:metadata:lp_course_status:startdate'] = 'लर्नरने पाथवरील कोर्स सुरू केल्याची वेळ.';
$string['privacy:metadata:lp_course_status:completiondate'] = 'लर्नरने पाथवरील कोर्स पूर्ण केल्याची वेळ.';
$string['privacy:metadata:lp_course_status:usercreated'] = 'ओळ तयार करणारा युजर.';
$string['privacy:metadata:lp_course_status:usermodified'] = 'ओळ शेवटची बदलणारा युजर.';
$string['privacy:metadata:lp_course_status:timecreated'] = 'ओळ तयार केल्याची वेळ.';
$string['privacy:metadata:lp_course_status:timemodified'] = 'ओळ शेवटची बदलल्याची वेळ.';
