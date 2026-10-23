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
 * Marathi strings for local_sentientia_evaluation.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_evaluation
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia मूल्यमापन';
$string['sentientia_evaluation:manage'] = 'मूल्यमापन फॉर्म मॅनेज करा';
$string['sentientia_evaluation:respond'] = 'मूल्यमापन फॉर्मना प्रतिसाद द्या';
$string['task_process_triggers'] = 'क्यूमधील मूल्यमापन ट्रिगर प्रोसेस करा';
$string['messageprovider:evaluation_invite'] = 'मूल्यमापनाचे निमंत्रण';
$string['invaliditemid'] = 'ट्रिगर इव्हेंटसाठी अवैध आयटम ID';
$string['invalidratearea'] = 'अवैध रेटिंग एरिया';
$string['messageprovider:evaluation_response'] = 'मूल्यमापन प्रतिसाद — ॲडमिन नोटिफिकेशन';
$string['addevaluation'] = 'मूल्यमापन फॉर्म तयार करा';
$string['editevaluation'] = 'मूल्यमापन फॉर्म एडिट करा';
$string['deleteevaluation'] = 'मूल्यमापन डिलीट करा';
$string['publishevaluation'] = 'मूल्यमापन पब्लिश करा';
$string['archiveevaluation'] = 'मूल्यमापन आर्काइव्ह करा';
$string['draftevaluation'] = 'ड्राफ्टमध्ये हलवा';
$string['heading_basic'] = 'फॉर्मची ओळख';
$string['heading_kirkpatrick'] = 'मूल्यमापन फ्रेमवर्क';
$string['heading_trigger'] = 'केव्हा पाठवायचे';
$string['heading_privacy'] = 'गोपनीयता';
$string['heading_window'] = 'उपलब्धतेचा कालावधी (ऐच्छिक)';
$string['eval_name'] = 'फॉर्मचे नाव';
$string['description'] = 'वर्णन';
$string['kirkpatrick_level'] = 'कर्कपॅट्रिक स्तर';
$string['kirkpatrick_level_help'] = 'डोनाल्ड कर्कपॅट्रिक यांचे 4-स्तरीय ट्रेनिंग मूल्यमापन मॉडेल. स्तर 1 (प्रतिक्रिया) लगेच पाठवला जातो; कामावर प्रत्यक्ष वापर मोजण्यासाठी स्तर 3-4 (वर्तन/परिणाम) ला साधारणपणे 30-90 दिवसांचा विलंब लागतो.';
$string['trigger_event'] = 'ट्रिगर इव्हेंट';
$string['days_after'] = 'ट्रिगरनंतरचे दिवस';
$string['days_after_help'] = 'ट्रिगर इव्हेंटनंतर मूल्यमापन पाठवण्यापूर्वी किती दिवस थांबायचे. 0 = लगेच पाठवा. नेहमीचे पॅटर्न: 0 (स्तर 1), 7 (स्तर 2), 30-60 (स्तर 3), 90-180 (स्तर 4).';
$string['organisation'] = 'संस्था (टेनंट)';
$string['anonymous'] = 'प्रतिसाद निनावी पद्धतीने गोळा करा';
$string['anonymous_help'] = 'टिक केल्यावर, प्रतिसाद कोणत्याही विशिष्ट युझरशी जोडले जात नाहीत. POSH किंवा कल्चर सर्व्हेसारख्या संवेदनशील विषयांमध्ये समाजाला पटेल असे उत्तर देण्याची प्रवृत्ती कमी होते, पण प्रतिसाद देणाऱ्या व्यक्तींशी फॉलो-अप करता येत नाही. एकदा कोणीही प्रतिसाद दिला की निनावीपणा बंद करता येत नाही.';
$string['status'] = 'स्टेटस';
$string['status_draft'] = 'ड्राफ्ट';
$string['status_active'] = 'सक्रिय';
$string['status_archived'] = 'आर्काइव्ह केलेले';
$string['missingrequiredfields'] = 'कृपया सर्व आवश्यक फील्ड भरा.';
$string['invalidkirkpatricklevel'] = 'अवैध कर्कपॅट्रिक स्तर (1-4 असणे आवश्यक).';
$string['invalidtrigger'] = 'अवैध ट्रिगर इव्हेंट.';
$string['invalidstatus'] = 'अवैध स्टेटस व्हॅल्यू.';
$string['days_after_invalid'] = 'नंतरचे दिवस 0 किंवा त्याहून जास्त असले पाहिजेत.';
$string['confirmdelete'] = 'मूल्यमापन "{$a}" डिलीट करायचे? यामुळे फॉर्म, त्यातील सर्व प्रश्न आणि सबमिट केलेले सर्व प्रतिसाद कायमचे काढून टाकले जातील. हे परत आणता येणार नाही.';
$string['confirmpublish'] = '"{$a}" पब्लिश करायचे? ट्रिगर इव्हेंटनुसार ते प्रतिसाद गोळा करायला सुरुवात करेल.';
$string['confirmarchive'] = '"{$a}" आर्काइव्ह करायचे? नवीन प्रतिसाद गोळा करणे थांबेल, पण जुना डेटा जपून ठेवला जाईल.';
$string['confirmdraft'] = '"{$a}" परत ड्राफ्टमध्ये हलवायचे? यामुळे प्रतिसाद गोळा करणे तात्पुरते थांबेल.';
$string['evaluationcreated'] = 'मूल्यमापन फॉर्म तयार झाला.';
$string['evaluationupdated'] = 'मूल्यमापन फॉर्म अपडेट झाला.';
$string['evaluationdeleted'] = 'मूल्यमापन डिलीट झाले.';
$string['evaluationstatuschanged'] = 'मूल्यमापनाचे स्टेटस अपडेट झाले.';
$string['managequestions'] = 'प्रश्न मॅनेज करा';
$string['addquestion'] = 'प्रश्न जोडा';
$string['editquestion'] = 'प्रश्न एडिट करा';
$string['deletequestion'] = 'प्रश्न डिलीट करा';
$string['question_type'] = 'प्रश्नाचा प्रकार';
$string['question_type_help'] = 'शिकणारे कसे उत्तर देतील ते निवडा. L1/L2 मूल्यमापनासाठी रेटिंग सर्वोत्तम; शिफारस करण्याची तयारी मोजण्यासाठी NPS; ठरावीक पर्यायांपैकी एक निवडायला लावणाऱ्या परिस्थितींसाठी मल्टिपल चॉइस; गुणात्मक फीडबॅकसाठी टेक्स्ट.';
$string['question_text'] = 'प्रश्नाचा मजकूर';
$string['question_options'] = 'उत्तराचे पर्याय (प्रत्येक ओळीत एक)';
$string['question_options_help'] = 'फक्त मल्टिपल चॉइस प्रश्नांसाठी. प्रत्येक पर्याय नवीन ओळीवर लिहा. किमान 2 पर्याय आवश्यक.';
$string['question_required'] = 'आवश्यक (शिकणाऱ्याने उत्तर द्यायलाच हवे)';
$string['invalidquestiontype'] = 'अवैध प्रश्न प्रकार.';
$string['invalidevaluation'] = 'मूल्यमापन फॉर्म सापडला नाही.';
$string['invalidquestion'] = 'प्रश्न सापडला नाही.';
$string['multichoice_needs_options'] = 'मल्टिपल चॉइस प्रश्नांना किमान 2 पर्याय लागतात.';
$string['confirmdeletequestion'] = 'हा प्रश्न डिलीट करायचा? त्याची सर्व उत्तरेही मागील सबमिशनमधून काढून टाकली जातील.';
$string['questioncreated'] = 'प्रश्न जोडला.';
$string['questionupdated'] = 'प्रश्न अपडेट झाला.';
$string['questiondeleted'] = 'प्रश्न डिलीट झाला.';
$string['orderupdated'] = 'प्रश्नांचा क्रम सेव्ह झाला.';
$string['analysis_title'] = 'मूल्यमापन विश्लेषण';
$string['analysis_subtitle'] = 'सर्व मूल्यमापनांचे कर्कपॅट्रिक एकत्रित विश्लेषण';
$string['filter_from'] = 'सबमिट केल्याची तारीख (पासून)';
$string['filter_to'] = 'सबमिट केल्याची तारीख (पर्यंत)';
$string['filter_courseid'] = 'कोर्स ID';
$string['filter_programid'] = 'प्रोग्राम ID';
$string['filter_classroomid'] = 'क्लासरूम ID';
$string['filter_apply'] = 'फिल्टर लागू करा';
$string['filter_clear'] = 'क्लिअर';
$string['filter_subset_hint'] = 'खालील आकडेवारी फिल्टर केलेल्या भागाची आहे';
$string['export_csv'] = 'CSV एक्स्पोर्ट करा';
$string['no_responses_filtered'] = 'सध्याच्या फिल्टरशी कोणतेही प्रतिसाद जुळत नाहीत';
$string['no_responses_filtered_help'] = 'वरील तारखेची रेंज किंवा कॉन्टेक्स्ट फिल्टर बदला, किंवा शिकणारे मूल्यमापन सबमिट करेपर्यंत थांबा.';
$string['avg_rating'] = 'सरासरी रेटिंग';
$string['nps_score'] = 'NPS स्कोअर';
$string['stat_evaluations'] = 'मूल्यमापने';
$string['stat_responses'] = 'प्रतिसाद';
$string['viewresponses'] = 'प्रतिसाद पाहा';
$string['evaluationnotactive'] = 'हे मूल्यमापन सध्या प्रतिसाद स्वीकारत नाही.';
$string['alreadyresponded'] = 'तुम्ही हे मूल्यमापन आधीच सबमिट केले आहे.';
$string['evaluationhasnoquestions'] = 'या मूल्यमापनात अजून कोणतेही प्रश्न नाहीत.';
$string['timeopen'] = 'सुरू होण्याची तारीख';
$string['timeopen_help'] = 'ऐच्छिक. शिकणारे प्रतिसाद सबमिट करू शकतील अशी सर्वात पहिली तारीख. मूल्यमापन लगेच सुरू करण्यासाठी चेकबॉक्स टिक करू नका. "कंप्लायन्स सर्व्हे 1 जून ते 30 जून चालेल" अशा वर्कफ्लोसाठी याचा वापर करा.';
$string['timeclose'] = 'बंद होण्याची तारीख';
$string['timeclose_help'] = 'ऐच्छिक. मूल्यमापन नवीन प्रतिसाद स्वीकारणे बंद करेल ती तारीख (आधीचे प्रतिसाद जपून ठेवले जातात). मूल्यमापन कायम सुरू ठेवण्यासाठी टिक करू नका. सुरू होण्याच्या तारखेला किंवा त्यानंतर असली पाहिजे.';
$string['multiple_submit'] = 'एकाच युझरला अनेक प्रतिसाद सबमिट करू द्या';
$string['multiple_submit_help'] = 'एकच शिकणारा वेळोवेळी पुन्हा सबमिट करतो अशा पल्स-प्रकारच्या सर्व्हेसाठी (साप्ताहिक एंगेजमेंट चेक, मासिक कंप्लायन्स टिक, इ.) टिक करा. टिक न केल्यास (डीफॉल्ट), प्रत्येक युझरला फक्त एकच सबमिशन मिळते. या सेटिंगची पर्वा न करता निनावी मूल्यमापनात पुन्हा सबमिट करता येतेच.';
$string['eval_window_inverted'] = 'बंद होण्याची तारीख सुरू होण्याच्या तारखेला किंवा त्यानंतर असली पाहिजे.';
$string['evaluationnotyetopen'] = 'हे मूल्यमापन {$a} रोजी सुरू होईल — कृपया तेव्हा पुन्हा या.';
$string['evaluationclosed'] = 'हे मूल्यमापन {$a} रोजी बंद झाले.';
$string['eval_notyetopen_heading'] = 'अजून सुरू झालेले नाही';
$string['eval_notyetopen_body'] = 'हे मूल्यमापन {$a} रोजी सुरू होईल. तुमचा प्रतिसाद सबमिट करण्यासाठी कृपया तेव्हा पुन्हा या.';
$string['eval_closed_heading'] = 'मूल्यमापन बंद झाले';
$string['eval_closed_body'] = 'हे मूल्यमापन {$a} रोजी बंद झाले. नवीन प्रतिसाद आता स्वीकारले जात नाहीत.';
$string['eval_pulse_hint'] = 'हे पुन्हा-पुन्हा होणारे (पल्स) मूल्यमापन आहे — तुम्ही प्रत्येक वेळी आल्यावर नवीन प्रतिसाद सबमिट करू शकता.';
$string['numeric_min'] = 'किमान परवानगी असलेली व्हॅल्यू';
$string['numeric_min_help'] = 'ऐच्छिक. खालची मर्यादा नको असल्यास रिकामे ठेवा. शिकणाऱ्याला हे इनपुटवरील <code>min</code> ॲट्रिब्यूट म्हणून दिसते — यापेक्षा कमी व्हॅल्यू सबमिट करताना नाकारल्या जातात. उदाहरणे: "नॉन-निगेटिव्ह पूर्णांकांसाठी" <code>0</code>, वयासाठी <code>18</code>.';
$string['numeric_max'] = 'कमाल परवानगी असलेली व्हॅल्यू';
$string['numeric_max_help'] = 'ऐच्छिक. वरची मर्यादा नको असल्यास रिकामे ठेवा. दोन्ही सेट केल्यास, ही किमान व्हॅल्यूपेक्षा जास्त किंवा तिच्याइतकी असली पाहिजे. उदाहरणे: टक्केवारीसाठी <code>100</code>, वयासाठी <code>120</code>.';
$string['numeric_must_be_integer'] = 'पूर्ण संख्या असली पाहिजे (उदा. 0, 5, 100).';
$string['numeric_min_max_invalid'] = 'कमाल व्हॅल्यू किमान व्हॅल्यूपेक्षा जास्त किंवा तिच्याइतकी असली पाहिजे.';
$string['invalid_numeric'] = 'यासाठी उत्तर पूर्ण संख्या असली पाहिजे: {$a}';
$string['invalid_numeric_below_min'] = '"{$a->q}" चे उत्तर किमान {$a->min} असले पाहिजे.';
$string['invalid_numeric_above_max'] = '"{$a->q}" चे उत्तर जास्तीत जास्त {$a->max} असले पाहिजे.';
$string['invalid_multichoice_multi'] = 'निवडलेले एक किंवा अधिक पर्याय यासाठी वैध नाहीत: {$a}';
$string['multichoice_multi_hint'] = 'लागू होणारे सर्व पर्याय टिक करा.';
$string['heading_dependency'] = 'अटीनुसार दाखवणे (ऐच्छिक)';
$string['dep_none'] = '— नेहमी दिसेल —';
$string['dep_parent'] = 'हा प्रश्न फक्त तेव्हाच दाखवा जेव्हा…';
$string['dep_parent_help'] = 'एक पॅरेंट प्रश्न निवडा. पॅरेंट प्रश्नाचे उत्तर दिले असेल आणि ते उत्तर खालील व्हॅल्यूशी जुळत असेल, तरच हा प्रश्न रिस्पॉन्ड पेजवर दिसेल. सेव्ह करतानाच सायकल ब्लॉक केल्या जातात. बिनशर्त प्रश्नांसाठी <em>नेहमी दिसेल</em> असेच ठेवा.';
$string['dep_value'] = '… पॅरेंट प्रश्नाचे उत्तर याच्याशी जुळते';
$string['dep_value_help'] = 'होय/नाही पॅरेंटसाठी <code>yes</code> किंवा <code>no</code> लिहा. मल्टिपल चॉइस पॅरेंटसाठी, पर्यायांच्या लेबलपैकी नेमके एक लिहा (केस-सेन्सिटिव्ह). रेटिंग / NPS / संख्या पॅरेंटसाठी संख्यात्मक व्हॅल्यू लिहा. <em>कोणतेही रिकामे नसलेले उत्तर आल्यास हा प्रश्न दिसेल</em> असा अर्थ हवा असल्यास रिकामे ठेवा — "युझरने Q3 चे कोणतेही उत्तर दिले तर Q4 विचारा" अशा वेळी उपयोगी.';
$string['dep_invalid_parent'] = 'निवडलेला पॅरेंट प्रश्न अस्तित्वात नाही.';
$string['dep_self_reference'] = 'प्रश्न स्वतःवरच अवलंबून असू शकत नाही.';
$string['dep_parent_other_evaluation'] = 'पॅरेंट प्रश्न या मूल्यमापनाचा भाग नाही. याच मूल्यमापनातील प्रश्न निवडा.';
$string['dep_cycle'] = 'या अवलंबनामुळे सायकल तयार होईल (पॅरेंट शेवटी पुन्हा याच प्रश्नावर अवलंबून राहतो).';
$string['non_respondents_title'] = '{$a} — बाकी / पूर्ण';
$string['non_respondents_heading'] = '"{$a}" ला कोणी प्रतिसाद दिला आहे?';
$string['non_respondents_subtitle'] = 'असाइन केलेले शिकणारे आणि त्यांचे प्रतिसाद स्टेटस. शिकणारा पात्र कोर्स / प्रोग्राम / क्लासरूम पूर्ण करतो तेव्हा ऑटो-असाइनमेंट W1-5 ट्रिगर क्यूमधून येतात; मॅन्युअल असाइनमेंट ॲडमिन UI मधून जोडता येतील (पुढे येणार).';
$string['back_to_evaluations'] = 'मूल्यमापनांकडे परत';
$string['non_respondents_tab_pending'] = 'बाकी';
$string['non_respondents_tab_responded'] = 'प्रतिसाद दिलेले';
$string['non_respondents_col_name'] = 'नाव';
$string['non_respondents_col_email'] = 'ईमेल';
$string['non_respondents_col_trigger'] = 'कशाद्वारे असाइन';
$string['non_respondents_col_assigned'] = 'असाइन केले';
$string['non_respondents_col_due'] = 'शेवटची तारीख';
$string['non_respondents_col_responded'] = 'प्रतिसाद दिला';
$string['non_respondents_trigger_manual'] = 'मॅन्युअल';
$string['non_respondents_trigger_course'] = 'कोर्स';
$string['non_respondents_trigger_program'] = 'प्रोग्राम';
$string['non_respondents_trigger_classroom'] = 'क्लासरूम';
$string['non_respondents_empty_pending_heading'] = 'सर्वांनी प्रतिसाद दिला आहे.';
$string['non_respondents_empty_pending_body'] = 'एकतर प्रत्येक असाइन केलेल्या शिकणाऱ्याने हे मूल्यमापन भरले आहे, किंवा अजून कोणतीही असाइनमेंट नोंदवलेली नाही. ट्रिगर-आधारित मूल्यमापने शिकणारे पात्र ॲक्टिव्हिटी पूर्ण करतात तसे त्यांना ऑटो-असाइन होतात.';
$string['non_respondents_empty_responded_heading'] = 'अजून कोणतेही प्रतिसाद नाहीत.';
$string['non_respondents_empty_responded_body'] = 'असाइन केलेल्या शिकणाऱ्यांनी प्रतिसाद सबमिट केल्यावर, ते इथे दिसतील.';
$string['non_respondents_anonymous_heading'] = 'या मूल्यमापनाचे प्रतिसाद पूर्णपणे किंवा अंशतः निनावी आहेत.';
$string['non_respondents_anonymous_body'] = 'कोणी आणि केव्हा प्रतिसाद दिला हे दाखवलेले नाही: प्रतिसादाच्या वेळेवरून एखाद्या व्यक्तीचे उत्तर ओळखता येऊ शकते. या टॅबवरील संख्या तरीही अचूक आहे, आणि बाकी टॅबमध्ये अजून कोणी प्रतिसाद दिलेला नाही ते दिसते.';
$string['error_anonymity_locked'] = 'आपले प्रतिसाद निनावी राहतील या विश्वासाने लोकांनी या मूल्यमापनाला आधीच उत्तरे दिली आहेत, त्यामुळे निनावीपणा बंद करता येणार नाही. नावासह प्रतिसाद गोळा करण्यासाठी, नवीन मूल्यमापन तयार करा.';
$string['error_question_anonymity_delete_locked'] = 'हा प्रश्न निनावी असताना लोकांनी या मूल्यमापनाला आधीच उत्तरे दिली आहेत, त्यामुळे तो डिलीट करता येणार नाही.';
$string['error_question_anonymity_locked'] = 'हा प्रश्न निनावी असताना लोकांनी या मूल्यमापनाला आधीच उत्तरे दिली आहेत, त्यामुळे तो नावासह करता येणार नाही. नावासह विचारण्यासाठी, नवीन प्रश्न जोडा.';
$string['filterstoolong'] = 'फिल्टर पेलोड खूप मोठा आहे.';
$string['bulk_assign_modal_title'] = 'ऑडियन्सनुसार बल्क-असाइन';
$string['bulk_assign_form_intro'] = 'युझरच्या एका गटाला टार्गेट करण्यासाठी एक किंवा अधिक फिल्टर निकष निवडा. तुम्ही फिल्टर बदलता तसा खालील प्रिव्ह्यू अपडेट होतो. निश्चित करण्यासाठी "जुळणाऱ्या युझरना असाइन करा" वर क्लिक करा — आधीच असाइन केलेले शिकणारे आपोआप वगळले जातात (डुप्लिकेट होत नाहीत).';
$string['bulk_assign_button'] = 'जुळणाऱ्या युझरना असाइन करा';
$string['bulk_assign_pick_at_least_one'] = 'किमान एक फिल्टर निकष निवडा.';
$string['bulk_assign_result'] = '{$a->assigned} नवीन असाइनमेंट; {$a->matched} युझर ऑडियन्सशी जुळले ({$a->existing} आधीच असाइन केलेले).';
$string['bulk_assign_capped'] = 'ऑडियन्सची संख्या {$a} या मर्यादेपर्यंत पोहोचली. अधिक युझरना असाइन करण्यासाठी फिल्टर अधिक नेमका करा (किंवा अधिक कडक निकषांसह बल्क-असाइन दोनदा चालवा).';
$string['audience_any'] = 'कोणतेही';
$string['audience_any_cohort'] = 'कोणताही कोहॉर्ट';
$string['audience_users_matched'] = 'युझर या ऑडियन्सशी जुळतात';
$string['audience_designation'] = 'पद';
$string['audience_region'] = 'प्रदेश';
$string['audience_location'] = 'ठिकाण';
$string['audience_employmenttype'] = 'नोकरीचा प्रकार';
$string['audience_cohort'] = 'कोहॉर्ट';
$string['template_name_required'] = 'टेम्पलेटचे नाव आवश्यक आहे.';
$string['template_payload_corrupt'] = 'या टेम्पलेटचा पेलोड डिकोड करता आला नाही. ही रो Sentientia LMS च्या बाहेर एडिट केली गेली असू शकते. डिलीट करून पुन्हा सेव्ह करा.';
$string['template_saved'] = 'टेम्पलेट सेव्ह झाले.';
$string['template_deleted'] = 'टेम्पलेट डिलीट झाले.';
$string['template_save_modal_title'] = 'टेम्पलेट म्हणून सेव्ह करा';
$string['template_picker_modal_title'] = 'टेम्पलेटमधून तयार करा';
$string['template_name_label'] = 'टेम्पलेटचे नाव';
$string['template_desc_label'] = 'थोडक्यात वर्णन (ऐच्छिक)';
$string['template_ispublic'] = 'हे टेम्पलेट इतर टेनंटनाही उपलब्ध करा';
$string['template_ispublic_help'] = 'टिक केल्यावर, इतर टेनंटमधील ॲडमिन मूल्यमापन तयार करताना हे टेम्पलेट निवडू शकतात. HQ ने तयार केलेल्या फॉर्मसाठी (POSH, AML, लाचविरोधी) उपयोगी. टेम्पलेट फक्त तुमच्या टेनंटपुरते ठेवण्यासाठी टिक करू नका.';
$string['task_expire_assignments'] = 'मुदत संपलेल्या मूल्यमापन असाइनमेंट आपोआप एक्सपायर करा';
$string['heading_notifications'] = 'नोटिफिकेशन';
$string['notify_admin_on_response'] = 'प्रत्येक प्रतिसादावर साइट ॲडमिनना ईमेल करा';
$string['notify_admin_on_response_help'] = 'टिक केल्यावर, प्रत्येक यशस्वी सबमिशनवर सर्व साइट ॲडमिनना Sentientia LMS नोटिफिकेशन जाते. ते आपल्या नोटिफिकेशन प्रेफरन्समध्ये प्रत्येक चॅनेलसाठी (ईमेल / पॉपअप / मोबाइल पुश) ऑप्ट-आउट करू शकतात. कमी संख्येच्या स्ट्रॅटेजिक सर्व्हेसाठी (सी-सूट पल्स, घटनेनंतरचे डीब्रीफ) उपयोगी. जास्त संख्येच्या L1 रिॲक्शन फॉर्मसाठी हे बंदच ठेवा — नाहीतर ॲडमिन मेलमध्ये बुडून जातील.';
$string['eval_response_subject'] = 'नवीन मूल्यमापन प्रतिसाद: {$a}';
$string['eval_response_small'] = 'नवीन प्रतिसाद: {$a}';
$string['eval_response_body_plain'] = 'मूल्यमापन "{$a->evalname}" साठी नवीन प्रतिसाद सबमिट झाला आहे.

प्रतिसाद देणारे: {$a->responder}

सर्व प्रतिसाद पाहा: {$a->url}';
$string['eval_response_body_html'] = '<p>मूल्यमापन <strong>{$a->evalname}</strong> साठी नवीन प्रतिसाद सबमिट झाला आहे.</p><p>प्रतिसाद देणारे: {$a->responder}</p><p><a href="{$a->url}">या मूल्यमापनाचे सर्व प्रतिसाद पाहा</a></p>';
$string['eval_response_responder_anonymous'] = '(निनावी)';
$string['eval_response_responder_unknown'] = '(अज्ञात — युझर अकाउंट काढून टाकले आहे)';
$string['answer_required'] = 'आवश्यक उत्तर दिलेले नाही: {$a}';
$string['invalid_rating'] = 'रेटिंग 1-5 असले पाहिजे: {$a}';
$string['invalid_nps'] = 'NPS स्कोअर 0-10 असला पाहिजे: {$a}';
$string['invalid_yesno'] = 'उत्तर होय किंवा नाही असले पाहिजे: {$a}';
$string['invalid_multichoice'] = 'निवडलेला पर्याय यासाठी वैध नाही: {$a}';
$string['please_answer_required'] = 'सबमिट करण्यापूर्वी कृपया सर्व आवश्यक प्रश्नांची उत्तरे द्या.';
$string['response_submitted'] = 'धन्यवाद — तुमचा प्रतिसाद नोंदवला गेला आहे.';
$string['privacy:metadata:responses'] = 'युझरनी सबमिट केलेले मूल्यमापन प्रतिसाद (प्रत्येक सबमिशनसाठी एक रो).';
$string['privacy:metadata:responses:evaluationid'] = 'ज्या मूल्यमापन फॉर्मला प्रतिसाद दिला जात आहे त्याचा ID.';
$string['privacy:metadata:responses:userid'] = 'प्रतिसाद सबमिट करणाऱ्या युझरचा ID (निनावी असल्यास 0).';
$string['privacy:metadata:responses:response_data'] = 'JSON-एन्कोड केलेली उत्तरे (questionid → उत्तर).';
$string['privacy:metadata:responses:timesubmitted'] = 'सबमिशनची वेळ.';
$string['privacy:metadata:triggers'] = 'तुमच्यासाठी एखादे मूल्यमापन क्यूमध्ये ठेवले होते याची नोंद.';
$string['privacy:metadata:triggers:userid'] = 'ज्या युझरसाठी मूल्यमापन क्यूमध्ये ठेवले होते त्याचा ID.';
$string['privacy:metadata:triggers:evaluationid'] = 'कोणते मूल्यमापन क्यूमध्ये ठेवले होते.';
$string['privacy:metadata:triggers:trigger_event'] = 'ते क्यूमध्ये ठेवण्याचे कारण काय होते.';
$string['privacy:metadata:triggers:status'] = 'ते अजून फायर झाले आहे का.';
$string['privacy:metadata:triggers:timefired'] = 'ते केव्हा फायर झाले.';
$string['privacy:metadata:assign'] = 'तुम्हाला असाइन केलेले मूल्यमापन, आणि ते कोणी असाइन केले.';
$string['privacy:metadata:assign:userid'] = 'ज्या युझरला मूल्यमापन असाइन केले होते त्याचा ID.';
$string['privacy:metadata:assign:evaluationid'] = 'कोणते मूल्यमापन असाइन केले होते.';
$string['privacy:metadata:assign:trigger_event'] = 'असाइनमेंटचे कारण काय होते.';
$string['privacy:metadata:assign:status'] = 'तुम्ही प्रतिसाद दिला आहे का.';
$string['privacy:metadata:assign:assigned_by_userid'] = 'असाइन करणाऱ्या युझरचा ID.';
$string['privacy:metadata:assign:due_at'] = 'प्रतिसाद केव्हापर्यंत द्यायचा आहे.';
$string['privacy:metadata:assign:responded_at'] = 'तुम्ही केव्हा प्रतिसाद दिला.';
$string['privacy:metadata:responses:subject_userid'] = 'सुपरवायझर फॉर्म: प्रतिसाद ज्या टीम मेंबरबद्दल आहे त्याचा ID.';
$string['error_imported_form_read_only'] = 'हे मूल्यमापन आधीच्या सिस्टममधून आणले आहे आणि रीड-ओन्ली आहे: काय विचारले आणि काय उत्तर दिले याची ही नोंद आहे. तेच प्रश्न पुन्हा विचारण्यासाठी, हे टेम्पलेट म्हणून एक्स्पोर्ट करा आणि त्यातून नवीन मूल्यमापन तयार करा.';
$string['error_imported_template_read_only'] = 'हे टेम्पलेट आधीच्या सिस्टममधून आणले आहे आणि डिलीट करता येणार नाही. ते पुन्हा वापरण्यासाठी, त्यातून नवीन मूल्यमापन तयार करा.';
$string['my_evaluations_title'] = 'माझी मूल्यमापने';
$string['my_evaluations_intro'] = 'तुम्हाला पूर्ण करायला सांगितलेली किंवा तुम्ही पूर्ण केलेली मूल्यमापने, आधीच्या सिस्टममधून आणलेल्यांसह.';
$string['my_evaluations_col_evaluation'] = 'मूल्यमापन';
$string['my_evaluations_col_status'] = 'स्टेटस';
$string['my_evaluations_col_date'] = 'तारीख';
$string['my_evaluations_date_submitted'] = 'सबमिट केले';
$string['my_evaluations_date_due'] = 'शेवटची तारीख';
$string['my_evaluations_date_assigned'] = 'असाइन केले';
$string['my_evaluations_status_responded'] = 'प्रतिसाद दिला';
$string['my_evaluations_status_assigned'] = 'तुमची वाट पाहत आहे';
$string['my_evaluations_status_expired'] = 'प्रतिसादाशिवाय बंद झाले';
$string['my_evaluations_imported'] = 'आधीच्या सिस्टममधून';
$string['my_evaluations_anonymous_note'] = 'निनावी मूल्यमापन: त्याच्या निकालांमध्ये तुमच्या उत्तरांसोबत तुमचे नाव दिसत नाही.';
$string['my_evaluations_none'] = 'तुमच्या नावावर कोणत्याही मूल्यमापनाची नोंद नाही.';
$string['my_evaluations_unavailable'] = 'तुमचा मूल्यमापन इतिहास अजून उपलब्ध नाही.';
$string['responses_col_subject'] = 'कोणाबद्दल';
$string['responses_numeric_average'] = 'सरासरी';
$string['responses_numeric_lowest_highest'] = 'सर्वात कमी {$a->lowest}, सर्वात जास्त {$a->highest}';
$string['responses_numeric_range'] = 'परवानगी असलेली रेंज {$a->min} ते {$a->max}';
$string['responses_multi_summary'] = '{$a->respondents} प्रतिसादकर्त्यांकडून {$a->picks} निवडी (सरासरी प्रत्येकी {$a->avg})';
$string['responses_multi_share_note'] = 'एक व्यक्ती अनेक पर्याय टिक करू शकते, त्यामुळे टक्केवारींची बेरीज 100% पेक्षा जास्त होऊ शकते.';
$string['responses_question_no_answers'] = 'या प्रश्नाला अजून कोणतीही उत्तरे नाहीत.';
$string['response_detail_numeric_avg'] = 'सर्व प्रतिसादांची सरासरी: {$a}';
$string['imported_badge'] = 'इम्पोर्ट केलेले';
$string['imported_readonly_notice'] = 'आधीच्या सिस्टममधून आणलेले. हे मूल्यमापन रीड-ओन्ली आहे: काय विचारले आणि काय उत्तर दिले याची ही नोंद आहे. तेच प्रश्न पुन्हा विचारण्यासाठी, हे टेम्पलेट म्हणून एक्स्पोर्ट करा आणि त्यातून नवीन मूल्यमापन तयार करा.';
$string['questiontype_multichoice_multi_short'] = 'मल्टिपल चॉइस (अनेक)';
$string['questiontype_numeric_short'] = 'संख्या';
$string['questions_numeric_atleast'] = 'परवानगी असलेली रेंज: किमान {$a}';
$string['questions_numeric_atmost'] = 'परवानगी असलेली रेंज: जास्तीत जास्त {$a}';
$string['invalidresponse'] = 'प्रतिसाद सापडला नाही.';
$string['responses_subject_deleted'] = '(डिलीट केलेला युझर)';
$string['responses_numeric_ignored'] = 'संख्या नसलेली सेव्ह केलेली उत्तरे या आकडेवारीतून वगळली आहेत: {$a}.';
$string['responses_individual_link'] = 'वैयक्तिक प्रतिसाद';
$string['response_drilldown_unavailable'] = 'वैयक्तिक प्रतिसाद अजून उपलब्ध नाहीत.';
$string['responses_individual_protected_note'] = 'या मूल्यमापनाचे प्रतिसाद पूर्णपणे किंवा अंशतः निनावी असल्यामुळे वैयक्तिक प्रतिसाद उपलब्ध नाहीत. खालील एकूण आकडे तरीही दाखवले जातात.';
$string['response_list_protected'] = 'या मूल्यमापनाचे प्रतिसाद पूर्णपणे किंवा अंशतः निनावी आहेत, त्यामुळे वैयक्तिक प्रतिसाद उपलब्ध नाहीत: फक्त एकूण आकडे दाखवले जातात. एखादा प्रतिसाद, तो आलेला दिवस आणि तो ज्या कोर्स, प्रोग्राम किंवा क्लासरूममधून आला त्यासह पाहिला, तर तो देणाऱ्या व्यक्तीशी जुळवता येऊ शकतो.';
$string['response_list_back_to_totals'] = 'एकूण आकड्यांकडे परत';
