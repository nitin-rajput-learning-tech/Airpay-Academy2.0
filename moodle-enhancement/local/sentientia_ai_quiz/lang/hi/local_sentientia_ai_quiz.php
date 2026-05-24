<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi strings — Sentientia LMS AI Quiz (Phase G.1 scaffold).
 *
 * Parity contract: every key in lang/en/local_sentientia_ai_quiz.php
 * MUST exist in this file. The parity test in
 * tests/feature_flags_test.php verifies this.
 *
 * @package local_sentientia_ai_quiz
 */

defined('MOODLE_INTERNAL') || die();

// ── Plugin identity ───────────────────────────────────────────────
$string['pluginname']      = 'Sentientia LMS — एआई क्विज़ (हिन्दी / प्रति-ग्राहक प्रॉम्प्ट)';
$string['plugin_tagline']  = 'प्रति-ग्राहक Anthropic प्रॉम्प्ट टेम्पलेट के साथ हिन्दी क्विज़ निर्माण।';

// ── Capability strings ────────────────────────────────────────────
$string['sentientia_ai_quiz:generate'] = 'एआई क्विज़ ड्राफ़्ट उत्पन्न करें (फेज़ G.1)';

// ── Exception / error messages ────────────────────────────────────
$string['confirm_required']     = 'प्रति-कॉल [CONFIRM] गेट आवश्यक है। फेज़ G.1 स्कैफ़ोल्ड हर generate_quiz() कॉल को तब तक रोकता है जब तक लाइव-वायरिंग चिप की समीक्षा नहीं हो जाती।';
$string['error_feature_off']    = 'एआई क्विज़ (G.1) अक्षम है। कृपया व्यवस्थापक से Switchboard में sentientia_ai_quiz_enabled सक्षम करने को कहें।';
$string['error_no_capability']  = 'आपको एआई क्विज़ ड्राफ़्ट बनाने की अनुमति नहीं है।';
$string['error_invalid_lang']   = 'असमर्थित भाषा कोड: {$a}. समर्थित मान: en, hi.';
$string['error_empty_source']   = 'स्रोत सामग्री आवश्यक है।';
$string['error_source_too_long'] = 'स्रोत सामग्री कॉन्फ़िगर की गई शब्द सीमा से अधिक है।';
$string['error_cost_cap']       = 'आपके ग्राहक ने आज {$a->cap} USD बजट में से {$a->used} USD का उपयोग किया है।';

// ── Settings ──────────────────────────────────────────────────────
$string['settings_heading_prompt']      = 'प्रॉम्प्ट टेम्पलेट';
$string['settings_heading_prompt_desc'] = 'G.1 हिन्दी क्विज़ जनरेटर के लिए डिफ़ॉल्ट Anthropic सिस्टम प्रॉम्प्ट। प्रति-ग्राहक ओवरराइड रनटाइम पर local_airpay_core ग्राहक-कॉन्फ़िग हुक के माध्यम से लागू होते हैं।';
$string['setting_prompt_template']      = 'प्रॉम्प्ट टेम्पलेट';
$string['setting_prompt_template_desc'] = 'Anthropic सिस्टम प्रॉम्प्ट। प्लेसहोल्डर {source} और {lang} का उपयोग करें। ग्राहक-विशिष्ट ओवरराइड इस डिफ़ॉल्ट पर वरीयता रखते हैं।';
$string['setting_max_tokens']           = 'प्रति अनुरोध अधिकतम आउटपुट टोकन';
$string['setting_max_tokens_desc']      = 'किसी भी एक generate अनुरोध के लिए Anthropic को भेजे जाने वाले output_tokens की ऊपरी सीमा। डिफ़ॉल्ट 4000। लागत कम करने के लिए घटाएँ; लंबी क्विज़ के लिए बढ़ाएँ।';
$string['settings_heading_limits']      = 'लागत सीमाएँ';
$string['settings_heading_limits_desc'] = 'सॉफ़्ट कैप जो पार होने पर आगे के generate कॉल को रोकते हैं। प्रति ग्राहक प्रति दिन ऑडिट किया जाता है।';
$string['setting_daily_cost_cap']       = 'प्रति ग्राहक दैनिक लागत सीमा (USD)';
$string['setting_daily_cost_cap_desc']  = 'प्रति ग्राहक प्रति कैलेंडर दिन कुल Anthropic ख़र्च पर अमेरिकी डॉलर में सॉफ़्ट कैप। डिफ़ॉल्ट 100। एक बार पार होने पर, generate_quiz() आधी रात तक आगे की कॉल अस्वीकार करता है।';

// ── Language labels ───────────────────────────────────────────────
$string['lang_en'] = 'अंग्रेज़ी';
$string['lang_hi'] = 'हिन्दी';

// ── Privacy provider strings ──────────────────────────────────────
$string['privacy:metadata']               = 'Sentientia LMS एआई क्विज़ (G.1) एक प्रति-कॉल ऑडिट लॉग संग्रहीत करता है ताकि ग्राहक Anthropic ख़र्च का विश्लेषण कर सकें और ऐतिहासिक generation अनुरोधों को पुनः उत्पन्न कर सकें। यह स्कैफ़ोल्ड स्रोत टेक्स्ट को कभी संग्रहीत नहीं करता; केवल (प्रॉम्प्ट टेम्पलेट || स्रोत) का SHA-256 हैश संग्रहीत होता है।';
$string['privacy:metadata:log']           = 'एआई क्विज़ generation ऑडिट लॉग। प्रत्येक generate_quiz() कॉल पर एक पंक्ति।';
$string['privacy:metadata:log:userid']    = 'वह उपयोगकर्ता जिसने generate_quiz() कॉल किया।';
$string['privacy:metadata:log:courseid']  = 'वह कोर्स जिससे ड्राफ़्ट जुड़ा था (0 = साइट-स्तरीय)।';
$string['privacy:metadata:log:prompt_hash'] = 'प्रॉम्प्ट टेम्पलेट और स्रोत टेक्स्ट का SHA-256 हेक्स डाइजेस्ट। डुप्लिकेट हटाने और ऑडिट के लिए, स्रोत सामग्री को पुनर्निर्मित करने के लिए नहीं।';
$string['privacy:metadata:log:model']     = 'कॉल के लिए उपयोग किया गया Anthropic मॉडल पहचानकर्ता।';
$string['privacy:metadata:log:tokens']    = 'लागत ट्रैकिंग के लिए रिपोर्ट किए गए Anthropic इनपुट + आउटपुट टोकन।';
$string['privacy:metadata:log:success']   = 'क्या कॉल सफल रही (1) या विफल (0)।';
$string['privacy:metadata:log:error']     = 'success = 0 होने पर संक्षिप्त विफलता विवरण (कभी API कुंजी या स्रोत टेक्स्ट शामिल नहीं)।';
$string['privacy:metadata:log:timecreated'] = 'कॉल का प्रयास किए जाने का यूनिक्स टाइमस्टैम्प।';

// ── External subsystem (Anthropic) ────────────────────────────────
$string['privacy:metadata:anthropic']            = 'जब लाइव-वायरिंग चिप सक्षम होगी, स्रोत टेक्स्ट क्विज़ generation के लिए Anthropic Claude को भेजा जाएगा। फेज़ G.1 स्कैफ़ोल्ड अभी कोई भी लाइव कॉल नहीं करता।';
$string['privacy:metadata:anthropic:sourcetext'] = 'Anthropic को भेजा गया प्रशिक्षक द्वारा प्रदान किया गया स्रोत टेक्स्ट।';
$string['privacy:metadata:anthropic:lang']       = 'लक्षित भाषा कोड (en या hi) जो Claude को बताता है कि किस भाषा में प्रश्न तैयार करने हैं।';
