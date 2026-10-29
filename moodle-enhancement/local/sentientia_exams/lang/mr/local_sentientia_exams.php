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
 * Marathi strings for local_sentientia_exams.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_exams
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia ऑनलाइन परीक्षा';
$string['sentientia_exams:manage'] = 'ऑनलाइन परीक्षा मॅनेज करा';
$string['sentientia_exams:view'] = 'ऑनलाइन परीक्षा पाहा';
$string['sentientia_exams:enrol'] = 'युजर्सना परीक्षांमध्ये एनरोल करा (पॅरेंट क्विझ कोर्सद्वारे)';
$string['addexam'] = 'परीक्षा रजिस्टर करा';
$string['editexam'] = 'परीक्षा एडिट करा';
$string['deleteexam'] = 'परीक्षेचे रजिस्ट्रेशन रद्द करा';
$string['activateexam'] = 'परीक्षा ॲक्टिव्हेट करा';
$string['deactivateexam'] = 'परीक्षा डीॲक्टिव्हेट करा';
$string['heading_basic'] = 'क्विझ निवड';
$string['heading_settings'] = 'परीक्षा सेटिंग्ज';
$string['heading_org'] = 'संस्था';
$string['quiz'] = 'मूळ क्विझ';
$string['quiz_help'] = 'एंटरप्राइज परीक्षा म्हणून रजिस्टर करण्यासाठी आधीपासून असलेली Sentientia LMS क्विझ ॲक्टिव्हिटी निवडा. फक्त अजून रजिस्टर न झालेल्या क्विझ दिसतात. आधी तिच्या कोर्समध्ये क्विझ तयार करा (ॲक्टिव्हिटी जोडा > क्विझ), मग इथे रजिस्टर करा.';
$string['exam_name'] = 'परीक्षेचे डिस्प्ले नाव';
$string['exam_name_help'] = 'रिपोर्ट्स आणि डॅशबोर्डमध्ये दिसणारे सोपे, समजण्याजोगे नाव. हे मूळ क्विझच्या नावापेक्षा वेगळे असू शकते.';
$string['duration'] = 'वेळ मर्यादा (सेकंद)';
$string['duration_help'] = 'परीक्षेच्या लेव्हलवर क्विझची वेळ मर्यादा बदला. क्विझची डीफॉल्ट वेळ वापरण्यासाठी रिकामे ठेवा. 1800 = 30 मिनिटे, 3600 = 1 तास.';
$string['passinggrade'] = 'पासिंग ग्रेड (%)';
$string['organisation'] = 'संस्था (टेनंट)';
$string['exam_active'] = 'परीक्षा ॲक्टिव्ह आहे';
$string['exam_category'] = 'कॅटेगरी';
$string['exam_category_help'] = 'शोधायला सोपे जावे म्हणून या परीक्षेला टॅग करा. कोर्सेससाठी असलेलीच कॅटेगरी रचना इथे वापरली जाते (साइट ॲडमिनिस्ट्रेशन ▶ कोर्सेस ▶ कोर्स कॅटेगरीज मॅनेज करा इथे सेट केलेली), त्यामुळे ॲडमिन परीक्षांना त्यांची तयारी करून देणाऱ्या ट्रेनिंगच्या शेजारीच ग्रुप करू शकतात. नेहमीचे ग्रुप: कम्प्लायन्स, सेल्स, लीडरशिप, ऑनबोर्डिंग. परीक्षा एकदाच होणारी असेल तर <em>कॅटेगरी नाही</em> असेच ठेवा.';
$string['uncategorised'] = '— कॅटेगरी नाही —';
$string['missingrequiredfields'] = 'कृपया एक क्विझ निवडा आणि डिस्प्ले नाव द्या.';
$string['invalidquiz'] = 'निवडलेली क्विझ आता अस्तित्वात नाही.';
$string['invalidcategory'] = 'निवडलेली कॅटेगरी आता अस्तित्वात नाही.';
$string['quizalreadyregistered'] = 'ही क्विझ आधीच परीक्षा म्हणून रजिस्टर आहे. त्याऐवजी आधीची परीक्षा एडिट करा.';
$string['duration_invalid'] = 'कालावधी 0 किंवा सेकंदांची पॉझिटिव्ह संख्या असावी.';
$string['passinggrade_invalid'] = 'पासिंग ग्रेड 0 ते 100 च्या दरम्यान असावा.';
$string['confirmdelete'] = 'नक्की परीक्षा "{$a}" चे रजिस्ट्रेशन रद्द करायचे का? मूळ Sentientia LMS क्विझ डिलीट होणार नाही — ती तिच्या कोर्समध्येच राहील. फक्त एंटरप्राइज परीक्षेचा मेटाडेटा (टेनंट, डिस्प्ले नाव, स्टेटस) काढून टाकला जातो.';
$string['confirmactivate'] = 'नक्की "{$a}" ॲक्टिव्हेट करायची का? लर्नर्सना ती उपलब्ध परीक्षा म्हणून दिसेल.';
$string['confirmdeactivate'] = 'नक्की "{$a}" डीॲक्टिव्हेट करायची का? लर्नर्सना ती दिसणार नाही, पण डेटा जपून ठेवला जाईल.';
$string['examcreated'] = 'परीक्षा रजिस्टर झाली.';
$string['examupdated'] = 'परीक्षा अपडेट झाली.';
$string['examdeleted'] = 'परीक्षेचे रजिस्ट्रेशन रद्द झाले (मूळ क्विझ तशीच ठेवली आहे).';
$string['examstatuschanged'] = 'परीक्षेचा स्टेटस अपडेट झाला.';
$string['noexams_subtitle'] = 'टेनंट स्कोपिंग, कस्टम पासिंग ग्रेड आणि डॅशबोर्ड रिपोर्टिंग जोडण्यासाठी आधीपासून असलेल्या Sentientia LMS क्विझ एंटरप्राइज परीक्षा म्हणून रजिस्टर करा.';
$string['task_exam_reminder'] = 'परीक्षा डेडलाइन रिमाइंडर';
$string['messageprovider:exam_reminder'] = 'परीक्षा डेडलाइन रिमाइंडर';
$string['reminder_settings_heading'] = 'परीक्षा डेडलाइन रिमाइंडर (क्रॉन)';
$string['reminder_settings_intro'] = 'परीक्षेची डेडलाइन जवळ आलेल्या लर्नर्सना आठवण करून देणारा रोजचा शेड्यूल्ड टास्क. डेडलाइनचा सोर्स: <code>quiz.timeclose</code> (संबंधित क्विझवर सेट केलेला कॅलेंडर टाइमस्टॅम्प). हा टास्क डीफॉल्टनुसार बंद असतो; खालील <code>reminder_enabled</code> मधून तो सुरू करा, मग <em>साइट ॲडमिनिस्ट्रेशन ▶ सर्व्हर ▶ शेड्यूल्ड टास्क</em> इथे टास्क सुरू करा. डीफॉल्ट शेड्यूल: दररोज 09:15.';
$string['reminder_enabled'] = 'परीक्षा रिमाइंडर सुरू करा';
$string['reminder_enabled_help'] = 'OFF (डीफॉल्ट) असताना टास्क काहीच करत नाही. ON असताना तो दररोज चालतो, परीक्षेच्या timeclose जवळ आलेल्या लर्नर्सना नोटिफिकेशन पाठवतो, आणि <code>local_sentientia_exams_remind_sent</code> द्वारे डुप्लिकेट टाळतो.';
$string['reminder_days_before'] = 'रिमाइंडर बकेट्स (timeclose च्या किती दिवस आधी)';
$string['reminder_days_before_help'] = 'डेडलाइनच्या किती दिवस आधी याच्या मर्यादांची कॉमा लावून वेगळी केलेली यादी. <code>7,3,1</code> म्हणजे लर्नर्सना 7 दिवस आधी, पुन्हा 3 दिवस आधी, आणि शेवटी 1 दिवस आधी आठवण दिली जाते. बकेट वाटप एकाच दिशेने होते — 5 दिवस बाकी असलेला लर्नर "7" बकेटमध्ये एकदाच येतो.';
$string['reminder_max_per_run'] = 'प्रत्येक रनमध्ये जास्तीत जास्त नोटिफिकेशन्स';
$string['reminder_max_per_run_help'] = 'चुकीच्या कॉन्फिगर केलेल्या नियमामुळे एकाच क्रॉन रनमध्ये सगळ्या लर्नर्सना मेलचा भडिमार होऊ नये म्हणून सुरक्षा मर्यादा. डीफॉल्ट 500. बाकी राहिलेले लर्नर्स पुढच्या रनमध्ये जातात; युनिक इंडेक्स डुप्लिकेट टाळतो.';
$string['reminder_status'] = 'शेवटचा रन';
$string['reminder_last_run_value'] = 'शेवटचा यशस्वी रन: <strong>{$a->time}</strong>. त्या रनमध्ये पाठवलेली नोटिफिकेशन्स: <strong>{$a->count}</strong>.';
$string['reminder_last_run_never'] = 'परीक्षा डेडलाइन रिमाइंडर क्रॉन अजून एकदाही चाललेला नाही. टास्क सुरू करा आणि सर्व्हरच्या वेळेनुसार 09:15 नंतर पुन्हा पाहा, किंवा तो मॅन्युअली चालवण्यासाठी कमांड लाइनवरून <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_exams\\\\task\\\\exam_reminder</code> चालवा.';
$string['reminder_subject'] = 'रिमाइंडर: परीक्षा "{$a->examname}" साठी {$a->days_remaining} दिवस बाकी';
$string['reminder_small'] = '"{$a->examname}" साठी {$a->days_remaining} दिवस बाकी';
$string['reminder_push_title'] = 'परीक्षेसाठी {$a->days_remaining} दिवस बाकी';
$string['reminder_push_body'] = '"{$a->examname}" — {$a->deadline} पर्यंत पूर्ण करा. परीक्षा देण्यासाठी टॅप करा.';
$string['reminder_body_plain'] = 'नमस्कार,

ही आठवण आहे की परीक्षा "{$a->examname}" (कोर्स: {$a->coursename}) {$a->deadline} रोजी बंद होईल — आतापासून {$a->days_remaining} दिवसांनी.

परीक्षा इथे द्या: {$a->exam_url}

— Airpay Academy';
$string['reminder_body_html'] = '<p>नमस्कार,</p><p>ही आठवण आहे की परीक्षा <strong>{$a->examname}</strong> (कोर्स: {$a->coursename}) <strong>{$a->deadline}</strong> रोजी बंद होईल — आतापासून {$a->days_remaining} दिवसांनी.</p><p><a href="{$a->exam_url}">परीक्षा द्या</a></p><p style="color:#777;">— Airpay Academy</p>';
$string['task_exam_overdue'] = 'परीक्षा ओव्हरड्यू — मॅनेजर एस्कलेशन';
$string['messageprovider:exam_overdue_supervisor'] = 'परीक्षा ओव्हरड्यू — सुपरवायझर एस्कलेशन';
$string['overdue_settings_heading'] = 'ओव्हरड्यू मॅनेजर एस्कलेशन (क्रॉन)';
$string['overdue_settings_intro'] = 'परीक्षा रिमाइंडरचा सोबती टास्क. लर्नरने परीक्षेचा <code>quiz.timeclose</code> चुकवला की हा टास्क त्यांच्या थेट सुपरवायझरला (<code>user.open_supervisorid</code>) कळवतो. सुपरवायझर नसलेले लर्नर्स वगळले जातात. डेडलाइननंतरच्या नोंदी मार्क करण्यासाठी <code>local_sentientia_exams_remind_sent</code> हीच टेबल निगेटिव्ह <code>days_before_deadline</code> व्हॅल्यूजसह पुन्हा वापरतो. डीफॉल्ट शेड्यूल: दररोज 09:45.';
$string['overdue_enabled'] = 'सुपरवायझर एस्कलेशन सुरू करा';
$string['overdue_enabled_help'] = 'OFF (डीफॉल्ट) असताना टास्क काहीच करत नाही. ON असताना तो दररोज चालतो, ओव्हरड्यू लर्नर्सना त्यांच्या सुपरवायझरकडे एस्कलेट करतो, आणि रिमाइंडर टास्क वापरत असलेल्या त्याच टेबलवरून डुप्लिकेट टाळतो.';
$string['overdue_days_after'] = 'एस्कलेशन बकेट्स (डेडलाइननंतर किती दिवस)';
$string['overdue_days_after_help'] = 'डेडलाइननंतर किती दिवस याच्या मर्यादांची कॉमा लावून वेगळी केलेली यादी. <code>1,7,14</code> म्हणजे quiz.timeclose नंतर 1 / 7 / 14 दिवसांनी एस्कलेट करतो, मग थांबतो.';
$string['overdue_max_per_run'] = 'प्रत्येक रनमध्ये जास्तीत जास्त एस्कलेशन्स';
$string['overdue_max_per_run_help'] = 'चुकीच्या कॉन्फिगर केलेल्या नियमामुळे एकाच क्रॉन रनमध्ये सगळ्या मॅनेजर्सना मेलचा भडिमार होऊ नये म्हणून सुरक्षा मर्यादा. डीफॉल्ट 500.';
$string['overdue_status'] = 'शेवटचा ओव्हरड्यू रन';
$string['overdue_last_run_value'] = 'शेवटचा यशस्वी रन: <strong>{$a->time}</strong>. पाठवलेली एस्कलेशन्स: <strong>{$a->count}</strong>.';
$string['overdue_last_run_never'] = 'परीक्षा सुपरवायझर-एस्कलेशन क्रॉन अजून एकदाही चाललेला नाही. तो सुरू करा आणि पुन्हा पाहा, किंवा मॅन्युअली <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_exams\\\\task\\\\exam_overdue</code> चालवा.';
$string['overdue_subject'] = '{$a->learner_name} यांची परीक्षा "{$a->exam_name}" {$a->days_past} दिवस ओव्हरड्यू आहे';
$string['overdue_small'] = '{$a->learner_name} यांची परीक्षा {$a->exam_name} ओव्हरड्यू';
$string['overdue_push_title'] = '{$a->learner_name} — परीक्षा {$a->days_past} दिवस ओव्हरड्यू';
$string['overdue_push_body'] = 'परीक्षा "{$a->exam_name}" ची डेडलाइन ({$a->deadline}) चुकली. फॉलो-अप करण्यासाठी टॅप करा.';
$string['overdue_body_plain'] = 'नमस्कार,

तुमच्या टीममधील {$a->learner_name} यांनी परीक्षा "{$a->exam_name}" (कोर्स: {$a->coursename}) ची डेडलाइन चुकवली आहे.

डेडलाइन: {$a->deadline} ({$a->days_past} दिवसांपूर्वी).

परीक्षा पाहा:    {$a->exam_url}
लर्नर पाहा: {$a->learner_profile_url}

कृपया त्यांच्याशी फॉलो-अप करा.

— Airpay Academy';
$string['overdue_body_html'] = '<p>नमस्कार,</p><p>तुमच्या टीममधील <strong>{$a->learner_name}</strong> यांनी परीक्षा <strong>{$a->exam_name}</strong> (कोर्स: {$a->coursename}) ची डेडलाइन चुकवली आहे.</p><p>डेडलाइन: <strong>{$a->deadline}</strong> ({$a->days_past} दिवसांपूर्वी).</p><ul><li><a href="{$a->exam_url}">परीक्षा पाहा</a></li><li><a href="{$a->learner_profile_url}">लर्नरचे प्रोफाइल पाहा</a></li></ul><p>कृपया त्यांच्याशी फॉलो-अप करा.</p><p style="color:#777;">— Airpay Academy</p>';
$string['privacy:metadata:exams_remind_sent'] = 'एखाद्या कर्मचाऱ्याला परीक्षा डेडलाइन रिमाइंडर पाठवला गेला याची नोंद, जेणेकरून तो दोनदा पाठवला जाणार नाही.';
$string['privacy:metadata:exams_remind_sent:userid'] = 'ही नोंद ज्या युजरबद्दल आहे त्याचा ID.';
$string['privacy:metadata:exams_remind_sent:examid'] = 'रिमाइंडर ज्या परीक्षेबद्दल होता तिचा ID.';
$string['privacy:metadata:exams_remind_sent:days_before_deadline'] = 'रिमाइंडर डेडलाइनच्या किती दिवस आधी पाठवला गेला.';
$string['privacy:metadata:exams_remind_sent:deadline_ts'] = 'रिमाइंडर ज्या डेडलाइनबद्दल होता ती डेडलाइन.';
$string['privacy:metadata:exams_remind_sent:timesent'] = 'रिमाइंडर कधी पाठवला गेला.';
