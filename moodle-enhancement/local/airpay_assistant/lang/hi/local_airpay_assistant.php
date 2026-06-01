<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे AI शिक्षा सहायक';
$string['apikey'] = 'Anthropic API कुंजी';
$string['apikey_desc'] = 'console.anthropic.com से आपकी Claude API कुंजी। AI सहायक के लिए आवश्यक।';
$string['ratelimit'] = 'प्रति उपयोगकर्ता दैनिक प्रश्न सीमा';
$string['ratelimit_desc'] = 'प्रति दिन AI प्रश्नों की अधिकतम संख्या। डिफ़ॉल्ट: 20.';
$string['assistant'] = 'शिक्षा सहायक';
$string['askme'] = 'मुझसे कुछ भी पूछें...';
$string['poweredby'] = 'AI द्वारा संचालित';
$string['ratelimited'] = 'दैनिक सीमा पूरी। कल वापस आएं!';
$string['notconfigured'] = 'AI सहायक कॉन्फ़िगर नहीं है। अपने व्यवस्थापक से संपर्क करें।';
$string['queriesremaining'] = 'आज {$a} प्रश्न शेष';

// Phase B0 (2026-05-14) — a11y लेबल चैट बबल के लिए।
$string['toggle_assistant']  = 'AI शिक्षा सहायक खोलें';
$string['close_assistant']   = 'AI शिक्षा सहायक बंद करें';
$string['minimize_assistant'] = 'सहायक पैनल छोटा करें';
$string['send_message']      = 'संदेश भेजें';
$string['type_question']     = 'अपना प्रश्न लिखें';
$string['quick_questions']   = 'त्वरित प्रश्न';

// P1 #50 (2026-05-20) — Hindi top-up: 3 strings (settings + privacy).
$string['enabled']          = 'AI सहायक सक्षम करें';
$string['enabled_desc']     = 'सभी पेज पर AI चैटबॉट बबल दिखाएँ। साइट-व्यापी रूप से चैटबॉट छिपाने के लिए अनचेक करें।';
$string['privacy:metadata'] = 'AI सहायक यूज़र ID से लिंक्ड चैट लॉग संग्रहीत करता है।';

// Role-aware quick-action chips (2026-06-01).
$string['qa_learn']       = 'आगे क्या सीखें?';
$string['qa_learn_q']     = 'मुझे आगे क्या सीखना चाहिए?';
$string['qa_deadlines']   = 'मेरी समय-सीमाएँ';
$string['qa_deadlines_q'] = 'मेरी समय-सीमाएँ क्या हैं?';
$string['qa_quiz']        = 'मुझसे प्रश्न पूछें';
$string['qa_quiz_q']      = 'मेरे कोर्स पर मुझसे प्रश्न पूछें';
$string['qa_team']        = 'टीम की स्थिति';
$string['qa_team_q']      = 'मेरी टीम कैसा प्रदर्शन कर रही है?';
$string['qa_certs']       = 'मेरे प्रमाणपत्र';
$string['qa_certs_q']     = 'मेरे प्रमाणपत्र दिखाएँ';
