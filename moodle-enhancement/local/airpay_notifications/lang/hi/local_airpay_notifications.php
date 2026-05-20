<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे स्मार्ट सूचनाएँ';
$string['taskprocessrules'] = 'एयरपे सूचना नियम प्रक्रिया';
$string['messageprovider:smart_alert'] = 'एयरपे लर्निंग अलर्ट';
$string['notifications'] = 'सूचनाएँ';
$string['markallread'] = 'सभी पढ़ा हुआ चिन्हित करें';
$string['nonotifications'] = 'आप अपडेट हैं!';
$string['urgent'] = 'ज़रूरी';
$string['learning'] = 'शिक्षा';
$string['achievement'] = 'उपलब्धि';
$string['viewall'] = 'सभी सूचनाएँ देखें';
$string['preferences'] = 'सूचना प्राथमिकताएँ';

// P1 #48 (2026-05-20) — Hindi top-up: 53 strings covering capabilities,
// CRUD form, errors, success, and privacy metadata.
$string['privacy:metadata'] = 'सूचना प्लगइन सूचना प्राथमिकताएँ और डिलीवरी लॉग संग्रहीत करता है।';

// Capabilities.
$string['airpay_notifications:view']   = 'सूचना नियम देखें';
$string['airpay_notifications:manage'] = 'सूचना नियम प्रबंधित करें';

// CRUD actions.
$string['addrule']     = 'सूचना नियम बनाएँ';
$string['editrule']    = 'सूचना नियम संपादित करें';
$string['deleterule']  = 'नियम हटाएँ';
$string['enablerule']  = 'नियम सक्षम करें';
$string['disablerule'] = 'नियम अक्षम करें';

// Form section headings.
$string['heading_basic']    = 'नियम पहचान';
$string['heading_trigger']  = 'ट्रिगर शर्तें';
$string['heading_delivery'] = 'डिलीवरी';

// Form labels.
$string['rule_name']         = 'नियम का नाम';
$string['rule_type']         = 'नियम प्रकार';
$string['rule_type_help']    = 'कौन सी घटना इस सूचना को ट्रिगर करती है। जब आप यूज़र्स को अलर्ट करना चाहते हैं उसी घटना प्रकार का चयन करें।';
$string['trigger_days']      = 'ट्रिगर विंडो (दिन)';
$string['trigger_days_help'] = 'घटना से कितने दिन पहले/बाद भेजना है। उदाहरण: डेडलाइन से 3 दिन पहले = नियम प्रकार "Deadline approaching" के साथ 3 दर्ज करें।';
$string['audience']          = 'दर्शक';
$string['channel']           = 'डिलीवरी चैनल';
$string['template']          = 'संदेश टेम्पलेट';
$string['template_help']     = 'सूचना संदेश। आप {{firstname}}, {{coursename}}, {{days}}, {{deadline}} जैसे प्लेसहोल्डर इस्तेमाल कर सकते हैं।';
$string['enabled']           = 'नियम सक्रिय है';

// Errors.
$string['missingrequiredfields'] = 'कृपया नाम और नियम प्रकार भरें।';
$string['invalidruletype']       = 'अमान्य नियम प्रकार।';
$string['invalidchannel']        = 'अमान्य डिलीवरी चैनल।';
$string['invalidaudience']       = 'अमान्य दर्शक।';
$string['trigger_days_invalid']  = 'ट्रिगर विंडो 0 या अधिक दिन होनी चाहिए।';
$string['confirmdelete']         = 'नियम "{$a}" हटाएँ? मौजूदा सूचना लॉग ऑडिट के लिए सुरक्षित रहेंगे, लेकिन यह नियम नई घटनाओं पर ट्रिगर नहीं होगा।';
$string['confirmdisable']        = 'नियम "{$a}" अक्षम करें? यह तुरंत ट्रिगर होना बंद कर देगा लेकिन बाद में पुनः सक्षम किया जा सकता है।';
$string['confirmenable']         = 'नियम "{$a}" सक्षम करें? यह अगले cron रन पर मिलने वाली घटनाओं को संसाधित करना शुरू कर देगा।';

// Success messages.
$string['rulecreated']        = 'सूचना नियम बनाया गया।';
$string['ruleupdated']        = 'सूचना नियम अपडेट किया गया।';
$string['ruledeleted']        = 'नियम हटा दिया गया।';
$string['rulestatuschanged']  = 'नियम की स्थिति अपडेट की गई।';

// Privacy metadata (delivery log).
$string['privacy:metadata:log']              = 'भेजी गई सूचना लॉग — प्रति डिलीवर्ड (या प्रयासित) सूचना एक पंक्ति।';
$string['privacy:metadata:log:ruleid']       = 'सूचना को ट्रिगर करने वाले नियम की ID।';
$string['privacy:metadata:log:userid']       = 'प्राप्तकर्ता यूज़र ID।';
$string['privacy:metadata:log:courseid']     = 'वैकल्पिक कोर्स संदर्भ (साइट-व्यापी सूचनाओं के लिए NULL)।';
$string['privacy:metadata:log:channel']      = 'डिलीवरी चैनल (inapp/email/push/whatsapp)।';
$string['privacy:metadata:log:subject']      = 'सूचना की विषय पंक्ति।';
$string['privacy:metadata:log:message']      = 'सूचना का बॉडी टेक्स्ट।';
$string['privacy:metadata:log:status']       = 'डिलीवरी स्थिति (sending/sent/read/failed)।';
$string['privacy:metadata:log:timecreated']  = 'भेजने का टाइमस्टैम्प।';
$string['privacy:metadata:log:timeread']     = 'पढ़ने का टाइमस्टैम्प (न पढ़ा गया हो तो NULL)।';

// Privacy metadata (preferences).
$string['privacy:metadata:prefs']                     = 'यूज़र-विशिष्ट सूचना प्राथमिकताएँ (चैनल टॉगल, क्वायट आवर्स, नियम-प्रकार ऑप्ट-आउट)।';
$string['privacy:metadata:prefs:userid']              = 'जिस यूज़र की प्राथमिकताएँ हैं।';
$string['privacy:metadata:prefs:channel_inapp']       = 'क्या यूज़र इन-ऐप संदेश स्वीकार करता है।';
$string['privacy:metadata:prefs:channel_email']       = 'क्या यूज़र ईमेल संदेश स्वीकार करता है।';
$string['privacy:metadata:prefs:channel_push']        = 'क्या यूज़र पुश सूचनाएँ स्वीकार करता है।';
$string['privacy:metadata:prefs:digest_frequency']    = 'डाइजेस्ट कितनी बार भेजा जाता है (none/daily/weekly)।';
$string['privacy:metadata:prefs:disabled_rule_types'] = 'यूज़र द्वारा साइलेंस किए गए नियम प्रकारों की कॉमा-सेपरेटेड सूची।';
$string['privacy:metadata:prefs:quiet_hours_start']   = 'क्वायट आवर्स विंडो का प्रारंभ घंटा।';
$string['privacy:metadata:prefs:quiet_hours_end']     = 'क्वायट आवर्स विंडो का समाप्ति घंटा।';
$string['privacy:metadata:prefs:timemodified']        = 'अंतिम अपडेट टाइमस्टैम्प।';
