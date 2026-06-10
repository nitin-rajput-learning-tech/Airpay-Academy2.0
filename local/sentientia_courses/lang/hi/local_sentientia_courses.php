<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #35 (2026-05-20) — Hindi (hi) translations for local_sentientia_courses.
// Covers all base CRUD + Sprint C/D sharing strings + P1 #21 (completion
// deadline) + P1 #28/#29 (reminder + overdue cron).

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे कोर्स इंजन';

// Capabilities.
$string['sentientia_courses:manage']     = 'कोर्स प्रबंधित करें';
$string['sentientia_courses:enrol']      = 'यूज़र्स को कोर्स में नामांकित करें';
$string['sentientia_courses:view']       = 'कोर्स प्रबंधन देखें';
$string['sentientia_courses:create']     = 'कोर्स बनाएँ';
$string['sentientia_courses:update']     = 'कोर्स संपादित करें';
$string['sentientia_courses:delete']     = 'कोर्स हटाएँ';
$string['sentientia_courses:visibility'] = 'कोर्स दिखाएँ या छिपाएँ';

// CRUD form strings.
$string['addcourse']    = 'कोर्स जोड़ें';
$string['editcourse']   = 'कोर्स संपादित करें';
$string['deletecourse'] = 'कोर्स हटाएँ';
$string['hidecourse']   = 'कोर्स छिपाएँ';
$string['showcourse']   = 'कोर्स दिखाएँ';

// Form section headings.
$string['heading_basic']    = 'मूल जानकारी';
$string['heading_category'] = 'श्रेणी और संगठन';
$string['heading_summary']  = 'विवरण';
$string['heading_format']   = 'प्रारूप और दृश्यता';

// Form field labels.
$string['fullname']        = 'कोर्स का पूरा नाम';
$string['shortname']       = 'कोर्स का संक्षिप्त नाम';
$string['shortname_help']  = 'विशिष्ट संक्षिप्त पहचानकर्ता — URL और रिपोर्ट में उपयोग किया जाता है।';
$string['idnumber']        = 'कोर्स ID संख्या';
$string['category']        = 'कोर्स श्रेणी';
$string['organisation']    = 'संगठन (टेनेंट)';
$string['summary']         = 'कोर्स विवरण';
$string['courseformat']    = 'कोर्स प्रारूप';
$string['format_topics']   = 'विषय प्रारूप';
$string['format_weeks']    = 'साप्ताहिक प्रारूप';
$string['format_single']   = 'एकल गतिविधि';
$string['format_social']   = 'सामाजिक प्रारूप';
$string['numsections']     = 'अनुभागों की संख्या';
$string['visibility']      = 'सीखने वालों को दृश्य';
$string['startdate']       = 'प्रारंभ तिथि';
$string['enddate']         = 'समाप्ति तिथि';

// P1 #21 — completion deadline.
$string['coursecompletiondays']         = 'समापन समय-सीमा (नामांकन से दिन)';
$string['coursecompletiondays_help']    = 'नामांकन से कितने दिनों में सीखने वालों को यह कोर्स पूरा करना होगा। <code>course_manager::get_completion_deadline()</code> द्वारा पढ़ा जाता है और रिमाइंडर वर्कफ़्लो द्वारा सीखने वालों को नज करने का समय तय करने के लिए उपयोग किया जाता है। कोई समय-सीमा न होने के लिए <code>0</code> सेट करें। उदाहरण: मासिक कंप्लायंस मॉड्यूल के लिए <code>30</code>, अर्ध-वार्षिक रिफ्रेशर के लिए <code>180</code>, वार्षिक पुन: प्रमाणन के लिए <code>365</code>।';
$string['coursecompletiondays_invalid'] = 'समापन समय-सीमा 0 या धनात्मक संख्या होनी चाहिए।';

// Error messages.
$string['missingrequiredfields']    = 'कृपया सभी आवश्यक फ़ील्ड भरें।';
$string['shortnametaken']           = 'यह संक्षिप्त नाम पहले से उपयोग में है। कृपया दूसरा चुनें।';
$string['enddatebeforestart']       = 'समाप्ति तिथि प्रारंभ तिथि के बाद होनी चाहिए।';
$string['cannotdeletesitecourse']   = 'साइट कोर्स को हटाया नहीं जा सकता।';
$string['confirmdelete']            = 'क्या आप वाकई "{$a}" हटाना चाहते हैं? यह कोर्स और उसके सभी नामांकन, गतिविधियाँ और ग्रेड को स्थायी रूप से हटा देगा। इसे पूर्ववत नहीं किया जा सकता।';
$string['confirmhide']              = 'क्या आप वाकई "{$a}" छिपाना चाहते हैं? सीखने वाले अब यह कोर्स नहीं देखेंगे।';
$string['confirmshow']              = 'क्या आप वाकई "{$a}" को सीखने वालों के लिए दृश्य बनाना चाहते हैं?';

// Success messages.
$string['coursecreated'] = 'कोर्स सफलतापूर्वक बनाया गया।';
$string['courseupdated'] = 'कोर्स सफलतापूर्वक अपडेट किया गया।';
$string['coursedeleted'] = 'कोर्स हटा दिया गया।';
$string['coursehidden']  = 'कोर्स छिपा दिया गया।';
$string['courseshown']   = 'कोर्स दृश्य कर दिया गया।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे sentientia_courses प्लगइन अपनी टेबल्स में व्यक्तिगत डेटा संग्रहीत नहीं करता; उपयोगकर्ता स्थिति कोर Sentientia LMS टेबल्स में रहती है जो अपने-अपने प्रदाताओं द्वारा एक्सपोर्ट की जाती हैं।';

// Sprint C — cross-tenant sharing.
$string['sentientia_courses:share_to_tenant'] = 'अन्य टेनेंट्स के साथ कोर्स साझा करें';
$string['event_course_share_created']     = 'एयरपे: कोर्स टेनेंट के साथ साझा किया गया';
$string['event_course_share_withdrawn']   = 'एयरपे: कोर्स साझा करना टेनेंट से वापस लिया गया';
$string['share_saved']                    = 'शेयर सेटिंग्स सहेज ली गईं। प्रभावित टेनेंट्स के लिए कैटलॉग कुछ सेकंडों में रिफ़्रेश होगा।';
$string['invalidparameter']               = 'एक या अधिक पैरामीटर अमान्य हैं।';

// Sprint D — pull/request workflow.
$string['sentientia_courses:request_course']       = 'अनुरोध करें कि कोई कोर्स मेरे टेनेंट के साथ साझा किया जाए';
$string['sentientia_courses:approve_request']      = 'अन्य टेनेंट्स के शेयर अनुरोधों को मंज़ूर/अस्वीकार करें';
$string['event_course_share_requested']        = 'एयरपे: कोर्स-शेयर का अनुरोध किया गया';
$string['event_course_share_request_approved'] = 'एयरपे: कोर्स-शेयर अनुरोध स्वीकृत';
$string['event_course_share_request_rejected'] = 'एयरपे: कोर्स-शेयर अनुरोध अस्वीकृत';
$string['request_filed']                       = 'आपका अनुरोध दर्ज कर लिया गया है। एयरपे व्यवस्थापक शीघ्र ही इसकी समीक्षा करेंगे।';
$string['request_approved']                    = 'अनुरोध स्वीकृत। कोर्स अब अनुरोधकर्ता टेनेंट के कैटलॉग में है।';
$string['request_rejected']                    = 'अनुरोध अस्वीकृत। अनुरोधकर्ता अपने आउटबॉक्स में निर्णय देखेगा।';
$string['invalidtenant']                       = 'आपका टेनेंट निर्धारित नहीं किया जा सका — आपके उपयोगकर्ता खाते में कोई संगठन पथ नहीं है।';
$string['invaliduser']                         = 'अनुरोधकर्ता उपयोगकर्ता खाता अब सक्रिय नहीं है।';
$string['invalidcourse']                       = 'ऐसा कोई कोर्स नहीं।';
$string['cannotrequestowncourse']              = 'आप ऐसे कोर्स का अनुरोध नहीं कर सकते जिसका स्वामित्व आपके टेनेंट के पास पहले से है।';

// P1 #28 — learner deadline-reminder cron.
$string['task_course_reminder']             = 'कोर्स समय-सीमा रिमाइंडर';
$string['messageprovider:course_reminder']  = 'कोर्स समय-सीमा रिमाइंडर';

$string['reminder_settings_heading']        = 'कोर्स समय-सीमा रिमाइंडर (क्रॉन)';
$string['reminder_settings_intro']          = 'दैनिक शेड्यूल्ड टास्क जो सीखने वालों को नज करता है जिनकी कोर्स समय-सीमा निकट आ रही है। समय-सीमा <code>enrolment.timestart + course.open_coursecompletiondays × 86400</code> से गणना की जाती है — एडिट-कोर्स फॉर्म पर सामने आया फ़ील्ड। टास्क डिफ़ॉल्ट रूप से अक्षम है; ऑप्ट-इन करने के बाद, इसे <em>साइट प्रशासन ▶ सर्वर ▶ शेड्यूल्ड टास्क</em> से सक्षम करें। डिफ़ॉल्ट शेड्यूल: 09:00 दैनिक।';
$string['reminder_enabled']                 = 'समय-सीमा रिमाइंडर सक्षम करें';
$string['reminder_enabled_help']            = 'जब बंद हो (डिफ़ॉल्ट), शेड्यूल्ड टास्क निष्क्रिय रहता है। चालू होने पर, टास्क दैनिक चलता है, समय-सीमा के निकट सीखने वालों को सूचनाएँ भेजता है, और प्रत्येक सेंड को डी-डुप के लिए <code>local_sentientia_courses_remind_sent</code> में रिकॉर्ड करता है।';
$string['reminder_days_before']             = 'रिमाइंडर बकेट (समय-सीमा से पहले दिन)';
$string['reminder_days_before_help']        = 'समय-सीमा से पहले दिनों की कॉमा-अलग सूची। <code>7,3,1</code> सीखने वालों को 7 दिन पहले, फिर 3 दिन पर, और समय-सीमा से 1 दिन पहले अंतिम पिंग भेजता है। खाली सूची = कोई रिमाइंडर नहीं। बकेट असाइनमेंट मोनोटोनिक — 5 दिन दूर का सीखने वाला "7" बकेट में केवल एक बार आता है।';
$string['reminder_max_per_run']             = 'प्रति रन अधिकतम सूचनाएँ';
$string['reminder_max_per_run_help']        = 'सुरक्षा सीमा ताकि गलत-कॉन्फ़िगर किया गया नियम एक क्रॉन टिक में 50,000 यूज़र्स को मेल-बम न कर सके। डिफ़ॉल्ट 500। बिना उपचार वाले सीखने वाले अगले क्रॉन रन में रोल ओवर हो जाते हैं; यूनिक इंडेक्स डी-डुप करता है इसलिए कुछ भी खोता नहीं।';
$string['reminder_status']                  = 'अंतिम रन';
$string['reminder_last_run_value']          = 'अंतिम सफल रन: <strong>{$a->time}</strong>। उस रन में भेजी गई सूचनाएँ: <strong>{$a->count}</strong>।';
$string['reminder_last_run_never']          = 'समय-सीमा-रिमाइंडर क्रॉन कभी नहीं चला। टास्क सक्षम करें और कल 09:00 सर्वर समय के बाद जाँचें, या इसे मैन्युअल रूप से चलाने के लिए कमांड लाइन से <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_reminder</code> चलाएँ।';

$string['reminder_subject']      = 'रिमाइंडर: "{$a->fullname}" {$a->days_remaining} दिन में देय है';
$string['reminder_small']        = '"{$a->fullname}" {$a->days_remaining} दिन में देय';
// Phase B.3.a — push notification (छोटा, mobile-friendly).
$string['reminder_push_title']   = 'कोर्स {$a->days_remaining} दिन में देय';
$string['reminder_push_body']    = '"{$a->fullname}" — {$a->deadline} तक पूरा करें। जारी रखने के लिए टैप करें।';
$string['reminder_body_plain']   = 'नमस्ते,

यह एक रिमाइंडर है कि आपके कोर्स "{$a->fullname}" को {$a->deadline} तक पूरा किया जाना है (अब से {$a->days_remaining} दिन)।

इसे यहाँ से उठाएँ: {$a->course_url}

— {$a->sitename}';
$string['reminder_body_html']    = '<p>नमस्ते,</p><p>यह एक रिमाइंडर है कि आपका कोर्स <strong>{$a->fullname}</strong> को <strong>{$a->deadline}</strong> तक पूरा किया जाना है (अब से {$a->days_remaining} दिन)।</p><p><a href="{$a->course_url}">कोर्स जारी रखें</a></p><p style="color:#777;">— {$a->sitename}</p>';

// P1 #29 — overdue manager-escalation cron.
$string['task_course_overdue']                       = 'कोर्स अतिदेय — प्रबंधक एस्केलेशन';
$string['messageprovider:course_overdue_supervisor'] = 'कोर्स अतिदेय — पर्यवेक्षक एस्केलेशन';

$string['overdue_settings_heading']        = 'अतिदेय प्रबंधक एस्केलेशन (क्रॉन)';
$string['overdue_settings_intro']          = 'समय-सीमा रिमाइंडर का सहोदर। जब कोई सीखने वाला कोर्स की समय-सीमा चूकता है, यह टास्क उनके सीधे पर्यवेक्षक (<code>user.open_supervisorid</code>) को सूचित करता है। बिना पर्यवेक्षक वाले सीखने वालों को छोड़ दिया जाता है। टास्क डिफ़ॉल्ट रूप से अक्षम है। डिफ़ॉल्ट शेड्यूल: 09:30 दैनिक।';
$string['overdue_enabled']                 = 'पर्यवेक्षक एस्केलेशन सक्षम करें';
$string['overdue_enabled_help']            = 'बंद होने पर (डिफ़ॉल्ट), टास्क निष्क्रिय। चालू होने पर, अतिदेय सीखने वालों को उनके पर्यवेक्षक को एस्केलेट करता है और उसी <code>local_sentientia_courses_remind_sent</code> टेबल के विरुद्ध डी-डुप करता है (नकारात्मक <code>days_before_deadline</code> मान पोस्ट-समय-सीमा पंक्तियों को चिह्नित करते हैं)।';
$string['overdue_days_after']              = 'एस्केलेशन बकेट (समय-सीमा के बाद दिन)';
$string['overdue_days_after_help']         = 'समय-सीमा के बाद के दिनों की कॉमा-अलग सूची। <code>1,7,14</code> सीखने वाले के चूकने के 1 दिन बाद, फिर 7 दिन पर, और अंत में 14 दिनों पर पर्यवेक्षक को एस्केलेट करता है। सबसे चौड़े बकेट के बाद, क्रॉन परेशान करना बंद कर देता है।';
$string['overdue_max_per_run']             = 'प्रति रन अधिकतम एस्केलेशन';
$string['overdue_max_per_run_help']        = 'सुरक्षा सीमा। डिफ़ॉल्ट 500। बिना उपचार वाले अगले रन में रोल ओवर; यूनिक इंडेक्स डी-डुप करता है।';
$string['overdue_status']                  = 'अंतिम अतिदेय रन';
$string['overdue_last_run_value']          = 'अंतिम सफल रन: <strong>{$a->time}</strong>। भेजे गए एस्केलेशन: <strong>{$a->count}</strong>।';
$string['overdue_last_run_never']          = 'पर्यवेक्षक-एस्केलेशन क्रॉन कभी नहीं चला। टास्क सक्षम करें या <code>php admin/cli/scheduled_task.php --execute=\\\\local_sentientia_courses\\\\task\\\\course_overdue</code> चलाएँ।';

$string['overdue_subject']      = '{$a->learner_name} "{$a->course_name}" पर {$a->days_past} दिन अतिदेय हैं';
$string['overdue_small']        = '{$a->learner_name} {$a->course_name} पर अतिदेय';
// Phase B.3.b — supervisor के लिए push notification (mobile-friendly).
$string['overdue_push_title']   = '{$a->learner_name} — {$a->days_past} दिन अतिदेय';
$string['overdue_push_body']    = '"{$a->course_name}" की डेडलाइन चूकी ({$a->deadline})। फॉलो-अप के लिए टैप करें।';
$string['overdue_body_plain']   = 'नमस्ते,

आपके टीम सदस्य {$a->learner_name} ने कोर्स "{$a->course_name}" की समय-सीमा चूक दी है।

समय-सीमा: {$a->deadline} ({$a->days_past} दिन पहले)।

कोर्स देखें:     {$a->course_url}
सीखने वाला देखें: {$a->learner_profile_url}

कृपया उनसे फॉलो-अप करें ताकि वे जल्द से जल्द कोर्स पूरा कर सकें।

— {$a->sitename}';
$string['overdue_body_html']    = '<p>नमस्ते,</p><p>आपके टीम सदस्य <strong>{$a->learner_name}</strong> ने कोर्स <strong>{$a->course_name}</strong> की समय-सीमा चूक दी है।</p><p>समय-सीमा: <strong>{$a->deadline}</strong> ({$a->days_past} दिन पहले)।</p><ul><li><a href="{$a->course_url}">कोर्स देखें</a></li><li><a href="{$a->learner_profile_url}">सीखने वाले की प्रोफ़ाइल देखें</a></li></ul><p>कृपया उनसे फॉलो-अप करें ताकि वे जल्द से जल्द कोर्स पूरा कर सकें।</p><p style="color:#777;">— {$a->sitename}</p>';
