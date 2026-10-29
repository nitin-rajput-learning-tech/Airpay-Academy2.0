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
 * Marathi strings for local_sentientia_courses.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia कोर्स इंजिन';
$string['sentientia_courses:manage'] = 'कोर्सेस मॅनेज करा';
$string['sentientia_courses:enrol'] = 'युजर्सना कोर्सेसमध्ये एनरोल करा';
$string['sentientia_courses:view'] = 'कोर्स मॅनेजमेंट पाहा';
$string['sentientia_courses:create'] = 'कोर्सेस तयार करा';
$string['sentientia_courses:update'] = 'कोर्सेस एडिट करा';
$string['sentientia_courses:delete'] = 'कोर्सेस डिलीट करा';
$string['sentientia_courses:visibility'] = 'कोर्सेस दाखवा किंवा लपवा';
$string['addcourse'] = 'कोर्स जोडा';
$string['editcourse'] = 'कोर्स एडिट करा';
$string['deletecourse'] = 'कोर्स डिलीट करा';
$string['hidecourse'] = 'कोर्स लपवा';
$string['showcourse'] = 'कोर्स दाखवा';
$string['heading_basic'] = 'मूलभूत माहिती';
$string['heading_category'] = 'कॅटेगरी आणि संस्था';
$string['heading_summary'] = 'वर्णन';
$string['heading_format'] = 'फॉरमॅट आणि दृश्यता';
$string['fullname'] = 'कोर्सचे पूर्ण नाव';
$string['shortname'] = 'कोर्सचे लहान नाव';
$string['shortname_help'] = 'युनिक लहान ओळख — URL आणि रिपोर्ट्समध्ये वापरली जाते.';
$string['idnumber'] = 'कोर्स ID नंबर';
$string['category'] = 'कोर्स कॅटेगरी';
$string['organisation'] = 'संस्था (टेनंट)';
$string['summary'] = 'कोर्सचे वर्णन';
$string['courseformat'] = 'कोर्स फॉरमॅट';
$string['format_topics'] = 'टॉपिक्स फॉरमॅट';
$string['format_weeks'] = 'साप्ताहिक फॉरमॅट';
$string['format_single'] = 'एकच ॲक्टिव्हिटी';
$string['format_social'] = 'सोशल फॉरमॅट';
$string['numsections'] = 'सेक्शन्सची संख्या';
$string['visibility'] = 'लर्नर्सना दिसेल';
$string['startdate'] = 'सुरुवातीची तारीख';
$string['enddate'] = 'संपण्याची तारीख';
$string['coursecompletiondays'] = 'पूर्ण करण्याची डेडलाइन (एनरोलमेंटपासून दिवस)';
$string['coursecompletiondays_help'] = 'एनरोलमेंटपासून किती दिवसांत लर्नर्सनी हा कोर्स पूर्ण करायचा आहे ते. हे <code>course_manager::get_completion_deadline()</code> वाचते, आणि लर्नर्सना कधी आठवण करून द्यायची हे ठरवण्यासाठी रिमाइंडर वर्कफ्लो हे वापरतो. डेडलाइन नको असल्यास <code>0</code> ठेवा. उदाहरणे: मासिक कम्प्लायन्स मॉड्यूल्ससाठी <code>30</code>, सहामाही रिफ्रेशर्ससाठी <code>180</code>, वार्षिक रिसर्टिफिकेशनसाठी <code>365</code>.';
$string['coursecompletiondays_invalid'] = 'पूर्ण करण्याची डेडलाइन 0 किंवा त्यापेक्षा जास्त दिवसांची असावी.';
$string['missingrequiredfields'] = 'कृपया सर्व आवश्यक फील्ड्स भरा.';
$string['shortnametaken'] = 'हे लहान नाव आधीच वापरात आहे. कृपया दुसरे निवडा.';
$string['enddatebeforestart'] = 'संपण्याची तारीख सुरुवातीच्या तारखेनंतर असावी.';
$string['cannotdeletesitecourse'] = 'साइट कोर्स डिलीट करता येत नाही.';
$string['confirmdelete'] = 'तुम्हाला नक्की "{$a}" डिलीट करायचा आहे का? यामुळे कोर्स आणि त्यातील सर्व एनरोलमेंट्स, ॲक्टिव्हिटीज आणि ग्रेड्स कायमचे काढून टाकले जातील. हे परत आणता येणार नाही.';
$string['confirmhide'] = 'तुम्हाला नक्की "{$a}" लपवायचा आहे का? लर्नर्सना हा कोर्स यापुढे दिसणार नाही.';
$string['confirmshow'] = 'तुम्हाला नक्की "{$a}" लर्नर्सना दिसेल असा करायचा आहे का?';
$string['coursecreated'] = 'कोर्स तयार झाला.';
$string['courseupdated'] = 'कोर्स अपडेट झाला.';
$string['coursedeleted'] = 'कोर्स डिलीट झाला.';
$string['coursehidden'] = 'कोर्स लपवला.';
$string['courseshown'] = 'कोर्स दिसत आहे.';
$string['sentientia_courses:share_to_tenant'] = 'कोर्स इतर टेनंट्सना शेअर करा';
$string['event_course_share_created'] = 'Airpay: कोर्स टेनंटला शेअर केला';
$string['event_course_share_withdrawn'] = 'Airpay: टेनंटकडून कोर्स शेअर मागे घेतला';
$string['share_saved'] = 'शेअर सेटिंग्ज सेव्ह झाल्या. संबंधित टेनंट्ससाठी काही सेकंदांत कॅटलॉग रिफ्रेश होईल.';
$string['invalidparameter'] = 'एक किंवा अधिक पॅरामीटर्स चुकीचे आहेत.';
$string['sentientia_courses:request_course'] = 'माझ्या टेनंटला कोर्स शेअर करण्याची विनंती करा';
$string['sentientia_courses:approve_request'] = 'इतर टेनंट्सच्या शेअर विनंत्या मंजूर करा / नाकारा';
$string['event_course_share_requested'] = 'Airpay: कोर्स शेअरची विनंती केली';
$string['event_course_share_request_approved'] = 'Airpay: कोर्स शेअर विनंती मंजूर झाली';
$string['event_course_share_request_rejected'] = 'Airpay: कोर्स शेअर विनंती नाकारली';
$string['request_filed'] = 'तुमची विनंती सबमिट झाली आहे. Airpay ॲडमिन लवकरच तिचा रिव्ह्यू करतील.';
$string['request_approved'] = 'विनंती मंजूर झाली. कोर्स आता विनंती करणाऱ्या टेनंटच्या कॅटलॉगमध्ये आहे.';
$string['request_rejected'] = 'विनंती नाकारली. विनंती करणाऱ्यांना त्यांच्या आउटबॉक्समध्ये निर्णय दिसेल.';
$string['invalidtenant'] = 'तुमचा टेनंट ओळखता आला नाही — तुमच्या युजर अकाउंटला संस्थेचा पाथ नाही.';
$string['error_orgoutoftenant'] = 'तुमच्याच टेनंटमधील संस्था निवडा.';
$string['error_crosstenantonly'] = 'फक्त क्रॉस-टेनंट ॲडमिनच टेनंट्समध्ये कोर्सेस शेअर करू शकतात किंवा शेअर विनंत्यांवर निर्णय घेऊ शकतात.';
$string['invaliduser'] = 'विनंती करणारे युजर अकाउंट आता ॲक्टिव्ह नाही.';
$string['invalidcourse'] = 'असा कोणताही कोर्स नाही.';
$string['cannotrequestowncourse'] = 'तुमच्या टेनंटच्या मालकीच्या कोर्ससाठी तुम्ही विनंती करू शकत नाही.';
$string['task_course_reminder'] = 'कोर्स डेडलाइन रिमाइंडर';
$string['messageprovider:course_reminder'] = 'कोर्स डेडलाइन रिमाइंडर';
$string['reminder_settings_heading'] = 'कोर्स डेडलाइन रिमाइंडर्स (क्रॉन)';
$string['reminder_settings_intro'] = 'ज्या लर्नर्सची कोर्स डेडलाइन जवळ येत आहे त्यांना आठवण करून देणारा रोजचा शेड्यूल्ड टास्क. डेडलाइन <code>enrolment.timestart + course.open_coursecompletiondays × 86400</code> वरून मोजली जाते — एडिट-कोर्स फॉर्मवर दिसणारे फील्ड हेच. डीफॉल्टनुसार हा टास्क बंद असतो; तुम्ही ऑप्ट-इन केल्यावर <em>Site administration ▶ Server ▶ Scheduled tasks</em> मधून तो सुरू करा. डीफॉल्ट शेड्यूल: दररोज 09:00.';
$string['reminder_enabled'] = 'डेडलाइन रिमाइंडर्स सुरू करा';
$string['reminder_enabled_help'] = 'OFF (डीफॉल्ट) असताना शेड्यूल्ड टास्क काहीच करत नाही. ON असताना टास्क दररोज चालतो, डेडलाइन जवळ आलेल्या लर्नर्सना नोटिफिकेशन्स पाठवतो, आणि डुप्लिकेट टाळण्यासाठी प्रत्येक पाठवणी <code>local_sentientia_courses_remind_sent</code> मध्ये नोंदवतो.';
$string['reminder_days_before'] = 'रिमाइंडर बकेट्स (डेडलाइनच्या आधीचे दिवस)';
$string['reminder_days_before_help'] = 'डेडलाइनच्या किती दिवस आधी आठवण करायची याच्या मर्यादांची कॉमाने वेगळी केलेली यादी. <code>7,3,1</code> म्हणजे लर्नर्सना डेडलाइनच्या 7 दिवस आधी, पुन्हा 3 दिवस आधी, आणि शेवटची आठवण 1 दिवस आधी. रिकामी यादी = रिमाइंडर्स नाहीत. बकेट असाइनमेंट मोनोटोनिक आहे — 5 दिवस बाकी असलेला लर्नर "7" बकेटमध्ये फक्त एकदाच येतो.';
$string['reminder_max_per_run'] = 'प्रत्येक रनमध्ये जास्तीत जास्त नोटिफिकेशन्स';
$string['reminder_max_per_run_help'] = 'चुकीच्या पद्धतीने कॉन्फिगर केलेल्या नियमामुळे एकाच क्रॉन टिकमध्ये 50 000 युजर्सना ईमेल्सचा भडिमार होऊ नये म्हणून सुरक्षा मर्यादा. डीफॉल्ट 500. उरलेले लर्नर्स पुढच्या क्रॉन रनमध्ये जातात; युनिक इंडेक्स डुप्लिकेट टाळतो त्यामुळे काहीही हरवत नाही.';
$string['reminder_status'] = 'शेवटचा रन';
$string['reminder_last_run_value'] = 'शेवटचा यशस्वी रन: <strong>{$a->time}</strong>. त्या रनमध्ये पाठवलेली नोटिफिकेशन्स: <strong>{$a->count}</strong>. (संपूर्ण ऑडिट <code>SELECT * FROM mdl_local_sentientia_courses_remind_sent ORDER BY timesent DESC</code> द्वारे उपलब्ध.)';
$string['reminder_last_run_never'] = 'डेडलाइन-रिमाइंडर क्रॉन अजून एकदाही रन झालेला नाही. टास्क सुरू करा आणि उद्या सर्व्हर वेळेनुसार 09:00 नंतर पुन्हा तपासा, किंवा मॅन्युअली चालवण्यासाठी कमांड लाइनवरून <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_reminder</code> चालवा.';
$string['reminder_subject'] = 'रिमाइंडर: "{$a->fullname}" पूर्ण करायला {$a->days_remaining} दिवस बाकी';
$string['reminder_small'] = '"{$a->fullname}" साठी {$a->days_remaining} दिवस बाकी';
$string['reminder_body_plain'] = 'नमस्कार,

तुमचा कोर्स "{$a->fullname}" {$a->deadline} पर्यंत ({$a->days_remaining} दिवसांत) पूर्ण करायचा आहे, याची ही आठवण.

इथून सुरू ठेवा: {$a->course_url}

— Airpay Academy';
$string['reminder_body_html'] = '<p>नमस्कार,</p><p>तुमचा कोर्स <strong>{$a->fullname}</strong> <strong>{$a->deadline}</strong> पर्यंत ({$a->days_remaining} दिवसांत) पूर्ण करायचा आहे, याची ही आठवण.</p><p><a href="{$a->course_url}">कोर्स सुरू ठेवा</a></p><p style="color:#777;">— Airpay Academy</p>';
$string['reminder_push_title'] = 'कोर्ससाठी {$a->days_remaining} दिवस बाकी';
$string['reminder_push_body'] = '"{$a->fullname}" — {$a->deadline} पर्यंत पूर्ण करा. सुरू ठेवण्यासाठी टॅप करा.';
$string['task_course_overdue'] = 'कोर्स ओव्हरड्यू — मॅनेजरकडे एस्कलेशन';
$string['messageprovider:course_overdue_supervisor'] = 'कोर्स ओव्हरड्यू — सुपरवायझरकडे एस्कलेशन';
$string['overdue_settings_heading'] = 'ओव्हरड्यू मॅनेजर एस्कलेशन (क्रॉन)';
$string['overdue_settings_intro'] = 'डेडलाइन रिमाइंडरचा जोडीदार टास्क. लर्नरने कोर्सची डेडलाइन चुकवली की हा टास्क त्यांच्या थेट सुपरवायझरला (<code>user.open_supervisorid</code>) कळवतो. ज्या लर्नर्सचा सुपरवायझर नोंदलेला नाही त्यांना वगळले जाते — एस्कलेट करायला कोणीच नाही. डीफॉल्टनुसार हा टास्क बंद असतो; <code>overdue_enabled</code> द्वारे ऑप्ट-इन करा आणि <em>Site administration ▶ Server ▶ Scheduled tasks</em> मधून शेड्यूल्ड टास्क सुरू करा. डीफॉल्ट शेड्यूल: दररोज 09:30.';
$string['overdue_enabled'] = 'सुपरवायझर एस्कलेशन सुरू करा';
$string['overdue_enabled_help'] = 'OFF (डीफॉल्ट) असताना टास्क काहीच करत नाही. ON असताना टास्क दररोज चालतो, ओव्हरड्यू लर्नर्सना त्यांच्या सुपरवायझरकडे एस्कलेट करतो, आणि रिमाइंडर टास्क वापरत असलेल्या त्याच <code>local_sentientia_courses_remind_sent</code> टेबलवरून डुप्लिकेट टाळतो (नेगेटिव्ह <code>days_before_deadline</code> व्हॅल्यूज डेडलाइननंतरच्या नोंदी दर्शवतात).';
$string['overdue_days_after'] = 'एस्कलेशन बकेट्स (डेडलाइननंतरचे दिवस)';
$string['overdue_days_after_help'] = 'डेडलाइननंतर किती दिवसांनी एस्कलेट करायचे याच्या मर्यादांची कॉमाने वेगळी केलेली यादी. <code>1,7,14</code> म्हणजे लर्नरने डेडलाइन चुकवल्यानंतर 1 दिवसाने, पुन्हा 7 दिवसांनी, आणि शेवटचे 14 दिवसांनी सुपरवायझरकडे एस्कलेशन. सर्वात मोठ्या बकेटनंतर क्रॉन आठवण करणे थांबवतो.';
$string['overdue_max_per_run'] = 'प्रत्येक रनमध्ये जास्तीत जास्त एस्कलेशन्स';
$string['overdue_max_per_run_help'] = 'चुकीच्या पद्धतीने कॉन्फिगर केलेल्या नियमामुळे एकाच क्रॉन टिकमध्ये प्रत्येक मॅनेजरला ईमेल्सचा भडिमार होऊ नये म्हणून सुरक्षा मर्यादा. डीफॉल्ट 500. उरलेले लर्नर्स पुढच्या क्रॉन रनमध्ये जातात; युनिक इंडेक्स डुप्लिकेट टाळतो.';
$string['overdue_status'] = 'शेवटचा ओव्हरड्यू रन';
$string['overdue_last_run_value'] = 'शेवटचा यशस्वी रन: <strong>{$a->time}</strong>. पाठवलेली एस्कलेशन्स: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never'] = 'सुपरवायझर-एस्कलेशन क्रॉन अजून एकदाही रन झालेला नाही. टास्क सुरू करा आणि उद्या सर्व्हर वेळेनुसार 09:30 नंतर पुन्हा तपासा, किंवा मॅन्युअली चालवण्यासाठी कमांड लाइनवरून <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_overdue</code> चालवा.';
$string['overdue_subject'] = '{$a->learner_name} यांचा "{$a->course_name}" कोर्स {$a->days_past} दिवस ओव्हरड्यू आहे';
$string['overdue_small'] = '{$a->learner_name} यांचा {$a->course_name} कोर्स ओव्हरड्यू';
$string['overdue_push_title'] = '{$a->learner_name} — {$a->days_past} दिवस ओव्हरड्यू';
$string['overdue_push_body'] = '"{$a->course_name}" ची डेडलाइन चुकली ({$a->deadline}). फॉलो-अप करण्यासाठी टॅप करा.';
$string['overdue_body_plain'] = 'नमस्कार,

तुमच्या टीममधील {$a->learner_name} यांनी "{$a->course_name}" कोर्सची डेडलाइन चुकवली आहे.

डेडलाइन: {$a->deadline} ({$a->days_past} दिवसांपूर्वी).

कोर्स पाहा:    {$a->course_url}
लर्नर पाहा:   {$a->learner_profile_url}

त्यांनी लवकरात लवकर कोर्स पूर्ण करावा यासाठी कृपया त्यांच्याशी फॉलो-अप करा.

— Airpay Academy';
$string['overdue_body_html'] = '<p>नमस्कार,</p><p>तुमच्या टीममधील <strong>{$a->learner_name}</strong> यांनी <strong>{$a->course_name}</strong> कोर्सची डेडलाइन चुकवली आहे.</p><p>डेडलाइन: <strong>{$a->deadline}</strong> ({$a->days_past} दिवसांपूर्वी).</p><ul><li><a href="{$a->course_url}">कोर्स पाहा</a></li><li><a href="{$a->learner_profile_url}">लर्नरचे प्रोफाइल पाहा</a></li></ul><p>त्यांनी लवकरात लवकर कोर्स पूर्ण करावा यासाठी कृपया त्यांच्याशी फॉलो-अप करा.</p><p style="color:#777;">— Airpay Academy</p>';
$string['browse_title'] = '{$a} कॅटलॉग ब्राउझ करा';
$string['browse_intro'] = '{$a->customer} च्या मालकीचे कोर्सेस ब्राउझ करा. तुमच्या टेनंटच्या ({$a->tenant}) कॅटलॉगमध्ये जोडायचा असलेल्या कोणत्याही कोर्ससाठी ॲक्सेसची विनंती करा. {$a->customer} ॲडमिनने मंजुरी दिल्यावर, कोर्स तुमच्या लर्नर्सच्या कॅटलॉगमध्ये दिसेल — कम्प्लीशन्स तुमच्या टेनंटपुरतेच वेगळे राहतात.';
$string['browse_col_course'] = 'कोर्स';
$string['browse_col_category'] = 'कॅटेगरी';
$string['browse_col_status'] = 'स्टेटस';
$string['browse_col_action'] = 'ॲक्शन';
$string['browse_request_access'] = 'ॲक्सेसची विनंती करा';
$string['browse_empty'] = '{$a} लायब्ररीमध्ये सध्या ब्राउझ करण्यासाठी कोणतेही पब्लिश केलेले कोर्सेस नाहीत.';
$string['browse_isolation_note'] = 'तुमच्या टेनंटच्या लर्नर्सना उधार घेतलेले कोर्सेस <strong>"{$a->customer} द्वारे उपलब्ध"</strong> बॅजसह दिसतील. कम्प्लीशन डेटा तुमच्या टेनंटमध्येच राहतो — {$a->customer} ला फक्त त्यांचेच कम्प्लीशन्स दिसतात, आणि तुमच्या रिपोर्ट्समध्ये फक्त {$a->tenant} लर्नर्स दिसतात.';
$string['browse_state_none'] = 'विनंती केलेली नाही';
$string['browse_state_pending'] = 'मंजुरी प्रलंबित';
$string['browse_state_in_catalog'] = 'तुमच्या कॅटलॉगमध्ये';
$string['browse_state_rejected'] = 'नाकारले';
$string['browse_tenant_fallback'] = 'टेनंट {$a}';
$string['privacy:metadata:courses_requests'] = 'कोर्स दुसऱ्या टेनंटसोबत शेअर करण्याची विनंती, ती कोणी केली आणि त्यावर कोणी निर्णय घेतला.';
$string['privacy:metadata:courses_requests:requester_userid'] = 'कोर्ससाठी विनंती करणाऱ्या युजरचा ID.';
$string['privacy:metadata:courses_requests:courseid'] = 'ही नोंद ज्या कोर्सशी संबंधित आहे त्याचा ID.';
$string['privacy:metadata:courses_requests:requesting_tenant'] = 'ज्या टेनंटकडून विनंती आली तो टेनंट.';
$string['privacy:metadata:courses_requests:status'] = 'विनंतीचा स्टेटस: प्रलंबित, मंजूर किंवा नाकारले.';
$string['privacy:metadata:courses_requests:decided_by'] = 'विनंतीवर निर्णय घेणाऱ्या युजरचा ID.';
$string['privacy:metadata:courses_requests:decision_reason'] = 'निर्णयासाठी नोंदवलेले कारण.';
$string['privacy:metadata:courses_requests:timecreated'] = 'नोंद कधी तयार झाली.';
$string['privacy:metadata:courses_requests:timedecided'] = 'विनंतीवर निर्णय कधी घेतला.';
$string['privacy:metadata:courses_remind_sent'] = 'एखाद्या कर्मचाऱ्याला कोर्स डेडलाइन रिमाइंडर पाठवला गेला याची नोंद, जेणेकरून तो दोनदा पाठवला जाणार नाही.';
$string['privacy:metadata:courses_remind_sent:userid'] = 'ही नोंद ज्या युजरबद्दल आहे त्याचा ID.';
$string['privacy:metadata:courses_remind_sent:courseid'] = 'ही नोंद ज्या कोर्सशी संबंधित आहे त्याचा ID.';
$string['privacy:metadata:courses_remind_sent:days_before_deadline'] = 'डेडलाइनच्या किती दिवस आधी रिमाइंडर पाठवला गेला.';
$string['privacy:metadata:courses_remind_sent:deadline_ts'] = 'रिमाइंडर ज्या डेडलाइनबद्दल होता ती डेडलाइन.';
$string['privacy:metadata:courses_remind_sent:timesent'] = 'रिमाइंडर कधी पाठवला गेला.';
$string['privacy:metadata:course_type'] = 'कोर्स टाइप, म्हणजे कोर्सची ओळख असलेले लेबल (उदा. ई-लर्निंग किंवा क्लासरूम), तो कोणी तयार केला आणि शेवटचा कोणी बदलला यासह.';
$string['privacy:metadata:course_type:usercreated'] = 'कोर्स टाइप तयार करणाऱ्या युजरचा ID.';
$string['privacy:metadata:course_type:usermodified'] = 'कोर्स टाइप शेवटचा बदलणाऱ्या युजरचा ID.';
$string['privacy:metadata:course_type:timecreated'] = 'कोर्स टाइप कधी तयार झाला.';
$string['privacy:metadata:course_type:timemodified'] = 'कोर्स टाइप शेवटचा कधी बदलला.';
$string['privacy:metadata:course_category'] = 'कस्टम कोर्स कॅटेगरी, ती कोणी तयार केली आणि शेवटची कोणी बदलली यासह.';
$string['privacy:metadata:course_category:usercreated'] = 'कॅटेगरी तयार करणाऱ्या युजरचा ID.';
$string['privacy:metadata:course_category:usermodified'] = 'कॅटेगरी शेवटची बदलणाऱ्या युजरचा ID.';
$string['privacy:metadata:course_category:timecreated'] = 'कॅटेगरी कधी तयार झाली.';
$string['privacy:metadata:course_category:timemodified'] = 'कॅटेगरी शेवटची कधी बदलली.';
