<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #51 (2026-05-20) — Hindi (hi) translations for local_sentientia_lifecycle.
// 2026-08-07 — joiner auto-enrolment strings added (ADR-029).

defined('MOODLE_INTERNAL') || die();

$string['pluginname']       = 'एयरपे एम्प्लॉयी लाइफ़साइकल';

// Joiner auto-enrolment (2026-08-07, ADR-029).
$string['autoenrol_heading']      = 'जॉइनर ऑटो-नामांकन';
$string['autoenrol_heading_desc'] = 'जब sentientia.lifecycle.autoenrol.enabled फ़ीचर फ़्लैग चालू होता है (डिफ़ॉल्ट: बंद), तो नए यूज़र अनिवार्य कोर्सों में स्वतः नामांकित होते हैं। कोई कोर्स तब अनिवार्य माना जाता है जब उस पर नीचे कॉन्फ़िगर किया गया टैग लगा हो; कोर्स जॉइनर के टेनेंट से उनके org पथ के आधार पर मिलाए जाते हैं।';
$string['mandatory_tag']          = 'अनिवार्य-कोर्स टैग';
$string['mandatory_tag_desc']     = 'वह कोर्स टैग जो किसी कोर्स को नए जॉइनर्स के लिए अनिवार्य चिह्नित करता है। डिफ़ॉल्ट: "mandatory"।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे लाइफ़साइकल प्लगइन प्लगइन-स्वामित्व वाली तालिकाओं में व्यक्तिगत डेटा संग्रहीत नहीं करता है।';
