<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi strings for local_sentientia_ai (the AI gateway).
 *
 * @package local_sentientia_ai
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia AI गेटवे';

// Capabilities.
$string['sentientia_ai:viewledger'] = 'AI खर्च लेजर देखें';
$string['sentientia_ai:manage'] = 'AI गेटवे रनटाइम नियंत्रण मैनेज करें';

// Settings.
$string['settings_heading_api'] = 'Anthropic API';
$string['settings_heading_api_desc'] = 'एक ही केंद्रीय key जिसका उपयोग हर Sentientia AI फ़ीचर करता है। माइग्रेशन के दौरान पुरानी per-plugin keys fallback के रूप में काम करती रहती हैं; इस key पर समेकित करें और पुरानी keys हटा दें।';
$string['setting_api_key'] = 'API key';
$string['setting_api_key_desc'] = 'Anthropic API key। कभी commit नहीं होती, कभी log नहीं होती, error संदेशों में कभी शामिल नहीं होती। Live कॉल के लिए दोनों gateway feature flags ON और quota में गुंजाइश भी ज़रूरी है।';
$string['setting_default_model'] = 'डिफ़ॉल्ट मॉडल';
$string['setting_default_model_desc'] = 'जब कॉल करने वाला फ़ीचर कोई मॉडल निर्दिष्ट नहीं करता तब उपयोग होने वाला मॉडल (जैसे claude-sonnet-4-6)।';
$string['settings_heading_quotas'] = 'Fail-closed खर्च quotas';
$string['settings_heading_quotas_desc'] = 'हर live कॉल से पहले खर्च लेजर से लागू होने वाली सख़्त सीमाएँ। शून्य या खाली मान का अर्थ है कोई live कॉल नहीं (कभी अनलिमिटेड नहीं) — स्वीकृत AI बजट निर्णय के अनुसार एक सख़्त सीमा है (memo 2026-08-04, Addendum A)। विंडो सर्वर-स्थानीय कैलेंडर दिन/माह हैं। लागतें बजट हेतु pricing-map अनुमान हैं, invoice नहीं।';
$string['setting_daily_tokens_global'] = 'दैनिक टोकन सीमा (वैश्विक)';
$string['setting_daily_tokens_global_desc'] = 'सभी ग्राहकों और फ़ीचर्स में प्रति कैलेंडर दिन अनुमत live टोकन (इनपुट + आउटपुट)। 0 = live कॉल अवरुद्ध।';
$string['setting_daily_tokens_customer'] = 'दैनिक टोकन सीमा (प्रति ग्राहक)';
$string['setting_daily_tokens_customer_desc'] = 'किसी एक ग्राहक के लिए प्रति कैलेंडर दिन अनुमत live टोकन। 0 = live कॉल अवरुद्ध।';
$string['setting_monthly_cost_cap'] = 'मासिक लागत सीमा (USD, अनुमानित)';
$string['setting_monthly_cost_cap_desc'] = 'प्रति कैलेंडर माह अनुमानित खर्च की सीमा। जब लेजर का month-to-date अनुमान इस तक पहुँचता है, आगे की live कॉलें denied होती हैं। 0 = live कॉल अवरुद्ध।';

// Ledger admin page.
$string['ledger_title'] = 'AI खर्च लेजर';
$string['ledger_intro'] = 'हर gateway कॉल — mock, live, failed या denied — एक पंक्ति है। हर live कॉल से पहले quotas इन्हीं आँकड़ों से लागू होते हैं।';
$string['ledger_today'] = 'आज (live टोकन)';
$string['ledger_month'] = 'माह-दर-तिथि (अनुमानित USD)';
$string['ledger_bycomponent'] = 'पिछले 30 दिन, फ़ीचर के अनुसार';
$string['ledger_recent'] = 'सबसे हालिया कॉलें';
$string['ledger_col_time'] = 'समय';
$string['ledger_col_component'] = 'फ़ीचर';
$string['ledger_col_purpose'] = 'उद्देश्य';
$string['ledger_col_user'] = 'यूज़र';
$string['ledger_col_model'] = 'मॉडल';
$string['ledger_col_tokens'] = 'टोकन (in/out)';
$string['ledger_col_cost'] = 'अनु. लागत';
$string['ledger_col_mode'] = 'मोड';
$string['ledger_col_calls'] = 'कॉलें';
$string['ledger_empty'] = 'अभी तक कोई gateway कॉल दर्ज नहीं हुई।';

// Privacy API.
$string['privacy:exportpath'] = 'AI गेटवे उपयोग';
$string['privacy:metadata:ledger'] = 'AI गेटवे खर्च लेजर यूज़र द्वारा ट्रिगर की गई हर AI कॉल की एक पंक्ति दर्ज करता है। Prompt और response का टेक्स्ट कभी संग्रहीत नहीं होता — केवल उपयोग का हिसाब।';
$string['privacy:metadata:ledger:userid'] = 'वह यूज़र जिसकी कार्रवाई से AI कॉल ट्रिगर हुई।';
$string['privacy:metadata:ledger:component'] = 'कॉल करने वाला plugin।';
$string['privacy:metadata:ledger:purpose'] = 'कॉल का घोषित उद्देश्य (जैसे quiz_generation)।';
$string['privacy:metadata:ledger:model'] = 'उपयोग किया गया AI मॉडल।';
$string['privacy:metadata:ledger:prompttokens'] = 'API द्वारा रिपोर्ट किए गए इनपुट टोकन।';
$string['privacy:metadata:ledger:completiontokens'] = 'API द्वारा रिपोर्ट किए गए आउटपुट टोकन।';
$string['privacy:metadata:ledger:estcost'] = 'कॉल की अनुमानित लागत (USD)।';
$string['privacy:metadata:ledger:mode'] = 'कॉल mock, live, failed या denied थी।';
$string['privacy:metadata:ledger:timecreated'] = 'कॉल कब की गई।';
$string['privacy:metadata:anthropic'] = 'Live मोड सक्षम होने पर response बनाने के लिए prompt टेक्स्ट Anthropic API को भेजा जाता है। Prompt में कर्मचारी PII शामिल नहीं होनी चाहिए (कॉल करने वाले फ़ीचर्स के इनपुट नियमों द्वारा लागू)।';
$string['privacy:metadata:anthropic:prompttext'] = 'AI अनुरोध का prompt टेक्स्ट।';
