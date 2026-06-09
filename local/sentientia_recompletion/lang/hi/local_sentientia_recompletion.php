<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #53 (2026-05-20) — Hindi (hi) translations for local_sentientia_recompletion.
// Scope: recompletion rules, history, capabilities, settings, rule form,
// message providers, event labels, UI, and privacy metadata.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे रीकम्प्लीशन';

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

// Event class names.
$string['event_completion_reset'] = 'कोर्स पूर्णता रीसेट';

// UI.
$string['nrules']               = '{$a} नियम';
$string['rules_empty']          = 'अभी तक कोई रीकम्प्लीशन नियम कॉन्फ़िगर नहीं किया गया।';
$string['history_empty']        = 'अभी तक कोई रीसेट नहीं किया गया।';
$string['no_courses_resetable'] = 'पूर्णता ट्रैकिंग सक्षम वाला कोई कोर्स नहीं — रीकम्प्लीशन के लिए कोर्स पूर्णता कॉन्फ़िगर होनी चाहिए।';

// Privacy.
$string['privacy:metadata:local_sentientia_recompletion_rules']            = 'रीकम्प्लीशन नियम परिभाषाएँ';
$string['privacy:metadata:local_sentientia_recompletion_history']          = 'प्रति-यूज़र रीसेट ऑडिट लॉग';
$string['privacy:metadata:local_sentientia_recompletion_history:userid']   = 'जिस यूज़र की पूर्णता रीसेट की गई';
$string['privacy:metadata:local_sentientia_recompletion_history:courseid'] = 'जो कोर्स रीसेट किया गया';
$string['privacy:metadata:local_sentientia_recompletion_history:reason']   = 'रीसेट क्यों ट्रिगर हुआ';
