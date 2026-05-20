<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #36 (2026-05-20) — Hindi (hi) translations for local_airpay_exams.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे ऑनलाइन परीक्षाएँ';
$string['airpay_exams:manage'] = 'ऑनलाइन परीक्षाएँ प्रबंधित करें';
$string['airpay_exams:view']   = 'ऑनलाइन परीक्षाएँ देखें';
$string['airpay_exams:enrol']  = 'यूज़र्स को परीक्षाओं में नामांकित करें (पैरेंट क्विज़ कोर्स के माध्यम से)';

// CRUD strings.
$string['addexam']          = 'परीक्षा पंजीकृत करें';
$string['editexam']         = 'परीक्षा संपादित करें';
$string['deleteexam']       = 'परीक्षा अपंजीकृत करें';
$string['activateexam']     = 'परीक्षा सक्रिय करें';
$string['deactivateexam']   = 'परीक्षा निष्क्रिय करें';

// Form sections.
$string['heading_basic']    = 'क्विज़ चयन';
$string['heading_settings'] = 'परीक्षा सेटिंग्स';
$string['heading_org']      = 'संगठन';

// Form labels.
$string['quiz']           = 'अंतर्निहित क्विज़';
$string['quiz_help']      = 'इसे एंटरप्राइज़ परीक्षा के रूप में पंजीकृत करने के लिए एक मौजूदा Sentientia LMS क्विज़ गतिविधि चुनें। केवल वही क्विज़ दिखाए जाते हैं जो पहले पंजीकृत नहीं हैं। पहले क्विज़ को उसके कोर्स में बनाएँ (गतिविधि जोड़ें > क्विज़), फिर इसे यहाँ पंजीकृत करें।';
$string['exam_name']      = 'परीक्षा प्रदर्शन नाम';
$string['exam_name_help'] = 'मानव-अनुकूल नाम जो रिपोर्ट और डैशबोर्ड में दिखाया जाता है। अंतर्निहित क्विज़ के नाम से भिन्न हो सकता है।';
$string['duration']       = 'समय सीमा (सेकंड)';
$string['duration_help']  = 'परीक्षा स्तर पर क्विज़ समय सीमा को ओवरराइड करें। क्विज़ डिफ़ॉल्ट का उपयोग करने के लिए खाली छोड़ें। 1800 = 30 मिनट, 3600 = 1 घंटा।';
$string['passinggrade']   = 'उत्तीर्ण ग्रेड (%)';
$string['organisation']   = 'संगठन (टेनेंट)';
$string['exam_active']    = 'परीक्षा सक्रिय है';

// P1 #23 — exam categories.
$string['exam_category']      = 'श्रेणी';
$string['exam_category_help'] = 'खोज के लिए इस परीक्षा को टैग करें। कोर्सेज़ के समान श्रेणी टैक्सोनॉमी का पुन: उपयोग करता है (साइट प्रशासन ▶ कोर्सेज़ ▶ कोर्स श्रेणियाँ प्रबंधित करें पर सेट करें) ताकि एडमिन प्रशिक्षण के साथ परीक्षाओं को समूहित कर सकें। सामान्य समूह: कंप्लायंस, सेल्स, लीडरशिप, ऑनबोर्डिंग। यदि परीक्षा एक बार की है तो <em>अवर्गीकृत</em> छोड़ दें।';
$string['uncategorised']      = '— अवर्गीकृत —';

// Errors.
$string['missingrequiredfields']  = 'कृपया एक क्विज़ चुनें और प्रदर्शन नाम दें।';
$string['invalidquiz']            = 'चुनी गई क्विज़ अब मौजूद नहीं है।';
$string['invalidcategory']        = 'चुनी गई श्रेणी अब मौजूद नहीं है।';
$string['quizalreadyregistered']  = 'यह क्विज़ पहले से ही परीक्षा के रूप में पंजीकृत है। मौजूदा परीक्षा को संपादित करें।';
$string['duration_invalid']       = 'अवधि 0 या धनात्मक संख्या होनी चाहिए।';
$string['passinggrade_invalid']   = 'उत्तीर्ण ग्रेड 0 और 100 के बीच होना चाहिए।';
$string['confirmdelete']          = 'परीक्षा "{$a}" अपंजीकृत करें? अंतर्निहित Sentientia LMS क्विज़ को हटाया नहीं जाएगा — यह अपने कोर्स में बना रहेगा। केवल एंटरप्राइज़ परीक्षा मेटाडेटा (टेनेंट, प्रदर्शन नाम, स्थिति) हटाया जाएगा।';
$string['confirmactivate']        = '"{$a}" सक्रिय करें? सीखने वाले इसे उपलब्ध परीक्षा के रूप में देखेंगे।';
$string['confirmdeactivate']      = '"{$a}" निष्क्रिय करें? सीखने वाले इसे नहीं देखेंगे लेकिन डेटा बना रहेगा।';

// Success.
$string['examcreated']        = 'परीक्षा पंजीकृत।';
$string['examupdated']        = 'परीक्षा अपडेट की गई।';
$string['examdeleted']        = 'परीक्षा अपंजीकृत (अंतर्निहित क्विज़ सुरक्षित)।';
$string['examstatuschanged']  = 'परीक्षा स्थिति अपडेट हुई।';

// Index page.
$string['noexams_subtitle'] = 'टेनेंट स्कोपिंग, कस्टम उत्तीर्ण ग्रेड और डैशबोर्ड रिपोर्टिंग जोड़ने के लिए मौजूदा Sentientia LMS क्विज़ को एंटरप्राइज़ परीक्षाओं के रूप में पंजीकृत करें।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे airpay_exams प्लगइन अपनी टेबल्स में व्यक्तिगत डेटा संग्रहीत नहीं करता।';

// P1 #33 — deadline-reminder cron.
$string['task_exam_reminder']             = 'परीक्षा समय-सीमा रिमाइंडर';
$string['messageprovider:exam_reminder']  = 'परीक्षा समय-सीमा रिमाइंडर';

$string['reminder_settings_heading']      = 'परीक्षा समय-सीमा रिमाइंडर (क्रॉन)';
$string['reminder_settings_intro']        = 'दैनिक शेड्यूल्ड टास्क जो सीखने वालों को नज करता है जिनकी परीक्षा समय-सीमा निकट है। समय-सीमा स्रोत: <code>quiz.timeclose</code>। टास्क डिफ़ॉल्ट रूप से अक्षम। डिफ़ॉल्ट शेड्यूल: 09:15 दैनिक।';
$string['reminder_enabled']               = 'परीक्षा रिमाइंडर सक्षम करें';
$string['reminder_enabled_help']          = 'बंद होने पर (डिफ़ॉल्ट) टास्क निष्क्रिय। चालू होने पर दैनिक चलता है, परीक्षा timeclose के निकट सीखने वालों को सूचनाएँ भेजता है, और <code>local_airpay_exams_remind_sent</code> के माध्यम से डी-डुप करता है।';
$string['reminder_days_before']           = 'रिमाइंडर बकेट (समय-सीमा से पहले दिन)';
$string['reminder_days_before_help']      = 'समय-सीमा से पहले दिनों की कॉमा-अलग सूची। <code>7,3,1</code> सीखने वालों को 7, 3 और 1 दिन पहले नज करता है। बकेट असाइनमेंट मोनोटोनिक।';
$string['reminder_max_per_run']           = 'प्रति रन अधिकतम सूचनाएँ';
$string['reminder_max_per_run_help']      = 'सुरक्षा सीमा। डिफ़ॉल्ट 500। बिना उपचार वाले अगले रन में रोल ओवर; यूनिक इंडेक्स डी-डुप करता है।';
$string['reminder_status']                = 'अंतिम रन';
$string['reminder_last_run_value']        = 'अंतिम सफल रन: <strong>{$a->time}</strong>। उस रन में भेजी गई सूचनाएँ: <strong>{$a->count}</strong>।';
$string['reminder_last_run_never']        = 'परीक्षा-समय-सीमा-रिमाइंडर क्रॉन कभी नहीं चला। टास्क सक्षम करें या <code>php admin/cli/scheduled_task.php --execute=\\\\local_airpay_exams\\\\task\\\\exam_reminder</code> चलाएँ।';

$string['reminder_subject']      = 'रिमाइंडर: परीक्षा "{$a->examname}" {$a->days_remaining} दिन में देय है';
$string['reminder_small']        = '"{$a->examname}" {$a->days_remaining} दिन में देय';
$string['reminder_body_plain']   = 'नमस्ते,

यह एक रिमाइंडर है कि परीक्षा "{$a->examname}" (कोर्स: {$a->coursename}) {$a->deadline} को बंद हो रही है — अब से {$a->days_remaining} दिन।

परीक्षा यहाँ दें: {$a->exam_url}

— एयरपे एकेडमी';
$string['reminder_body_html']    = '<p>नमस्ते,</p><p>यह एक रिमाइंडर है कि परीक्षा <strong>{$a->examname}</strong> (कोर्स: {$a->coursename}) <strong>{$a->deadline}</strong> को बंद हो रही है — अब से {$a->days_remaining} दिन।</p><p><a href="{$a->exam_url}">परीक्षा दें</a></p><p style="color:#777;">— एयरपे एकेडमी</p>';

// P1 #34 — overdue manager-escalation cron.
$string['task_exam_overdue']                       = 'परीक्षा अतिदेय — प्रबंधक एस्केलेशन';
$string['messageprovider:exam_overdue_supervisor'] = 'परीक्षा अतिदेय — पर्यवेक्षक एस्केलेशन';

$string['overdue_settings_heading']  = 'अतिदेय प्रबंधक एस्केलेशन (क्रॉन)';
$string['overdue_settings_intro']    = 'परीक्षा रिमाइंडर का सहोदर। जब कोई सीखने वाला परीक्षा का <code>quiz.timeclose</code> चूकता है, यह टास्क उनके सीधे पर्यवेक्षक (<code>user.open_supervisorid</code>) को सूचित करता है। बिना पर्यवेक्षक वाले सीखने वालों को छोड़ दिया जाता है। डिफ़ॉल्ट शेड्यूल: 09:45 दैनिक।';
$string['overdue_enabled']           = 'पर्यवेक्षक एस्केलेशन सक्षम करें';
$string['overdue_enabled_help']      = 'बंद होने पर (डिफ़ॉल्ट) टास्क निष्क्रिय। चालू होने पर अतिदेय सीखने वालों को उनके पर्यवेक्षक को एस्केलेट करता है।';
$string['overdue_days_after']        = 'एस्केलेशन बकेट (समय-सीमा के बाद दिन)';
$string['overdue_days_after_help']   = 'समय-सीमा के बाद के दिनों की कॉमा-अलग सूची। <code>1,7,14</code> quiz.timeclose के 1 / 7 / 14 दिन बाद एस्केलेट करता है, फिर रुक जाता है।';
$string['overdue_max_per_run']       = 'प्रति रन अधिकतम एस्केलेशन';
$string['overdue_max_per_run_help']  = 'सुरक्षा सीमा। डिफ़ॉल्ट 500।';
$string['overdue_status']            = 'अंतिम अतिदेय रन';
$string['overdue_last_run_value']    = 'अंतिम सफल रन: <strong>{$a->time}</strong>। भेजे गए एस्केलेशन: <strong>{$a->count}</strong>।';
$string['overdue_last_run_never']    = 'परीक्षा पर्यवेक्षक-एस्केलेशन क्रॉन कभी नहीं चला। टास्क सक्षम करें या <code>php admin/cli/scheduled_task.php --execute=\\\\local_airpay_exams\\\\task\\\\exam_overdue</code> चलाएँ।';

$string['overdue_subject']      = '{$a->learner_name} परीक्षा "{$a->exam_name}" पर {$a->days_past} दिन अतिदेय हैं';
$string['overdue_small']        = '{$a->learner_name} {$a->exam_name} पर अतिदेय';
$string['overdue_body_plain']   = 'नमस्ते,

आपके टीम सदस्य {$a->learner_name} ने परीक्षा "{$a->exam_name}" (कोर्स: {$a->coursename}) की समय-सीमा चूक दी है।

समय-सीमा: {$a->deadline} ({$a->days_past} दिन पहले)।

परीक्षा देखें:    {$a->exam_url}
सीखने वाला देखें: {$a->learner_profile_url}

कृपया उनसे फॉलो-अप करें।

— एयरपे एकेडमी';
$string['overdue_body_html']    = '<p>नमस्ते,</p><p>आपके टीम सदस्य <strong>{$a->learner_name}</strong> ने परीक्षा <strong>{$a->exam_name}</strong> (कोर्स: {$a->coursename}) की समय-सीमा चूक दी है।</p><p>समय-सीमा: <strong>{$a->deadline}</strong> ({$a->days_past} दिन पहले)।</p><ul><li><a href="{$a->exam_url}">परीक्षा देखें</a></li><li><a href="{$a->learner_profile_url}">सीखने वाले की प्रोफ़ाइल देखें</a></li></ul><p>कृपया उनसे फॉलो-अप करें।</p><p style="color:#777;">— एयरपे एकेडमी</p>';
