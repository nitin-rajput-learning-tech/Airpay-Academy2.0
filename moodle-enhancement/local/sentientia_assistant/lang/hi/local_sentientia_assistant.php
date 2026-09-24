<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे AI शिक्षा सहायक';
$string['privacy:metadata:chat_log'] = 'शिक्षार्थी और AI सहायक के बीच बातचीत का लॉग।';
$string['privacy:metadata:chat_log:userid'] = 'वह उपयोगकर्ता जिसने संदेश भेजा या प्राप्त किया।';
$string['privacy:metadata:chat_log:role'] = 'संदेश उपयोगकर्ता का था या सहायक का।';
$string['privacy:metadata:chat_log:message'] = 'संदेश का पाठ।';
$string['privacy:metadata:chat_log:model'] = 'वह AI मॉडल जिसने उत्तर तैयार किया।';
$string['privacy:metadata:chat_log:tokens_in'] = 'संदेश द्वारा उपयोग किए गए इनपुट टोकन।';
$string['privacy:metadata:chat_log:tokens_out'] = 'संदेश के लिए उत्पन्न आउटपुट टोकन।';
$string['privacy:metadata:chat_log:timecreated'] = 'संदेश कब दर्ज किया गया।';
$string['privacy:metadata:agent_audit'] = 'उपयोगकर्ता की ओर से एजेंटिक कोपायलट द्वारा प्रस्तावित प्रत्येक क्रिया और प्राधिकरण परिणाम का ऑडिट।';
$string['privacy:metadata:agent_audit:userid'] = 'वह उपयोगकर्ता जिसकी शिक्षा प्रस्तावित क्रिया से प्रभावित होगी।';
$string['privacy:metadata:agent_audit:costcenterid'] = 'वह टेनेंट जिसके लिए क्रिया सीमित थी।';
$string['privacy:metadata:agent_audit:tool'] = 'वह टूल जिसे सहायक ने चलाने का प्रस्ताव दिया।';
$string['privacy:metadata:agent_audit:args_json'] = 'क्रिया के लिए प्रस्तावित तर्क।';
$string['privacy:metadata:agent_audit:proposed_by'] = 'क्रिया लाइव मॉडल द्वारा प्रस्तावित थी या मॉक द्वारा।';
$string['privacy:metadata:agent_audit:outcome'] = 'प्राधिकरण और निष्पादन का परिणाम।';
$string['privacy:metadata:agent_audit:detail'] = 'परिणाम का मानव-पठनीय विवरण।';
$string['privacy:metadata:agent_audit:idempotency_key'] = 'एक ही क्रिया के दोहरे निष्पादन को रोकने के लिए उपयोग किया गया हैश।';
$string['privacy:metadata:agent_audit:timecreated'] = 'क्रिया कब दर्ज की गई।';
$string['privacy:metadata:anthropic'] = 'लाइव AI सक्षम होने पर, उत्तर तैयार करने के लिए चैट संदेश Anthropic Claude को भेजे जाते हैं।';
$string['privacy:metadata:anthropic:message'] = 'उत्तर के लिए भेजा गया चैट संदेश पाठ।';
$string['privacy:metadata:anthropic:model'] = 'अनुरोधित Claude मॉडल।';
$string['privacy:export:chat'] = 'AI सहायक बातचीत';
$string['privacy:export:audit'] = 'AI कोपायलट क्रिया इतिहास';
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

// P1.3 — एजेंटिक कोपायलट (2026-06-16)।
// क्षमताएँ (capabilities)।
$string['sentientia_assistant:useagent']  = 'एजेंटिक लर्निंग कोपायलट का उपयोग करें';
$string['sentientia_assistant:enrol']     = 'कोपायलट को आपको किसी कोर्स में स्वयं-नामांकित करने दें';
$string['sentientia_assistant:bookilt']   = 'कोपायलट को आपको ILT सत्र में बुक करने दें';
$string['sentientia_assistant:recommend'] = 'कोपायलट को अंतराल भरने वाली सामग्री सुझाने दें';
$string['sentientia_assistant:manageall'] = 'टेनेंट भर में एजेंटिक कोपायलट ऑडिट लॉग देखें';

// पेज + पैनल।
$string['agent_title']             = 'लर्निंग कोपायलट';
$string['agent_intro']             = 'मुझसे कहें कि मैं आपको किसी कोर्स में नामांकित करूँ, ILT सत्र बुक करूँ, या आपके कौशल अंतराल भरने हेतु सामग्री सुझाऊँ। मैं एक कार्रवाई प्रस्तावित करूँगा और आप उसकी पुष्टि करेंगे।';
$string['agent_input_label']       = 'लर्निंग कोपायलट को संदेश भेजें';
$string['agent_input_placeholder'] = 'जैसे: मेरे कौशल बेहतर करने हेतु कोर्स सुझाएँ';
$string['agent_disabled_notice']   = 'आपके खाते के लिए एजेंटिक कोपायलट अभी सक्षम नहीं है। कृपया बाद में देखें।';
$string['agent_mode_mock']         = 'मॉक मोड';
$string['agent_mode_live']         = 'लाइव मोड';
$string['agent_confirm_btn']       = 'पुष्टि करें';
$string['agent_cancel_btn']        = 'रद्द करें';

// एजेंट लूप / गार्ड परिणाम (उपयोगकर्ता को दिखने वाले)।
$string['agent_help']              = 'मैं आपको कोर्स में नामांकन, ILT सत्र बुकिंग, या सामग्री सुझाव में मदद कर सकता हूँ। आप क्या करना चाहेंगे?';
$string['agent_unavailable']       = 'कोपायलट अस्थायी रूप से अनुपलब्ध है। कृपया कुछ क्षण बाद पुनः प्रयास करें।';
$string['agent_denied_invalid']    = 'मैं उस अनुरोध पर कार्रवाई नहीं कर सका — विवरण सही नहीं निकले।';
$string['agent_denied_capability'] = 'आपके पास वह कार्रवाई करने की अनुमति नहीं है।';
$string['agent_denied_tenant']     = 'वह कार्रवाई आपके संगठन के बाहर है और नहीं की जा सकती।';
$string['agent_noop']              = 'आप पहले से ही इसके लिए तैयार हैं — कुछ करने की आवश्यकता नहीं।';
$string['agent_failed']            = 'वह कार्रवाई करते समय कुछ गड़बड़ हो गई। कृपया बाद में पुनः प्रयास करें।';

// टूल।
$string['tool_enrol_course']    = 'आपको किसी कोर्स में नामांकित करें';
$string['tool_enrol_done']      = 'हो गया — अब आप {$a} में नामांकित हैं।';
$string['tool_book_ilt']        = 'आपको ILT सत्र में बुक करें';
$string['tool_book_done']       = 'हो गया — आप ILT सत्र में बुक हो गए हैं।';
$string['tool_book_full']       = 'वह ILT सत्र भरा हुआ है, इसलिए मैं आपको बुक नहीं कर सका।';
$string['tool_recommend']       = 'अंतराल भरने वाली सामग्री सुझाएँ';
$string['tool_recommend_intro'] = 'यहाँ कुछ कोर्स हैं जो आपके कौशल अंतराल भरने में मदद कर सकते हैं:';
$string['tool_recommend_none']  = 'अभी सुझाने हेतु नए कोर्स नहीं मिले — आप सभी प्रासंगिक कोर्स में नामांकित हैं।';
