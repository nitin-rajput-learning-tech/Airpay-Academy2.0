<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — लाइव एंगेजमेंट';

// ── Phase E.0 — scaffold strings (no UI yet) ─────────────────────────

// Privacy metadata.
$string['privacy:metadata:sessions'] = 'एक लाइव सेशन — प्रति Mentimeter-style पोल/क्विज़ इवेंट जो ट्रेनर चलाता है उसकी एक पंक्ति। मालिक (ट्रेनर userid), सेशन कोड, शुरू/समाप्ति टाइमस्टैम्प, और tenant/customer संदर्भ रिकॉर्ड करता है।';
$string['privacy:metadata:sessions:ownerid']    = 'सेशन बनाने और चलाने वाला ट्रेनर।';
$string['privacy:metadata:sessions:code']       = 'संख्यात्मक join कोड (6 अंक) जो ऑडियंस सदस्य join करने के लिए इस्तेमाल करते हैं।';
$string['privacy:metadata:sessions:tenantid']   = 'जिस BizLMS tenant से सेशन संबंधित है।';
$string['privacy:metadata:sessions:customerid'] = 'Sentientia customer scope।';
$string['privacy:metadata:sessions:timecreated']  = 'जब सेशन बनाया गया।';
$string['privacy:metadata:sessions:timestarted']  = 'जब सेशन लाइव हुआ (या 0 यदि कभी शुरू नहीं हुआ)।';
$string['privacy:metadata:sessions:timeended']    = 'जब सेशन समाप्त हुआ (या 0 यदि अभी भी चल रहा है)।';

$string['privacy:metadata:slides'] = 'लाइव सेशन के भीतर स्लाइड (प्रश्न)। प्रति स्लाइड एक पंक्ति।';
$string['privacy:metadata:slides:title']       = 'ऑडियंस को दिखाया गया स्लाइड शीर्षक।';
$string['privacy:metadata:slides:type']        = 'स्लाइड प्रकार — multichoice / wordcloud / openended / rating / quiz / ranking।';

$string['privacy:metadata:responses'] = 'स्लाइड के लिए व्यक्तिगत ऑडियंस प्रतिक्रियाएँ। यदि userid null है तो अनाम।';
$string['privacy:metadata:responses:userid']      = 'प्रतिक्रिया देने वाले user ID — अनाम सेशन के लिए nullable।';
$string['privacy:metadata:responses:value_text']  = 'फ़्री-टेक्स्ट प्रतिक्रिया (wordcloud / openended स्लाइड के लिए)।';
$string['privacy:metadata:responses:value_int']   = 'संख्यात्मक प्रतिक्रिया (multichoice / rating / quiz स्लाइड के लिए)।';
$string['privacy:metadata:responses:timecreated'] = 'जब प्रतिक्रिया भेजी गई।';

$string['privacy:metadata:participants'] = 'लाइव सेशन में ऑडियंस प्रतिभागी। उपस्थिति (last_seen) और प्रदर्शन नाम ट्रैक करता है।';
$string['privacy:metadata:participants:userid']        = 'प्रतिभागी user ID — अनाम joins के लिए nullable।';
$string['privacy:metadata:participants:display_name']  = 'ऑडियंस सूची / लीडरबोर्ड में दिखाया गया प्रदर्शन नाम।';
$string['privacy:metadata:participants:timejoined']    = 'जब user ने सेशन join किया।';
$string['privacy:metadata:participants:timelastseen']  = 'इस प्रतिभागी से अंतिम SSE heartbeat।';

$string['privacy:metadata:events'] = 'आंतरिक इवेंट जर्नल — स्लाइड परिवर्तन, प्रतिक्रिया गिनती, सेशन जीवनचक्र। SSE stream endpoint द्वारा polled। सेशन समाप्त होने के 24 घंटे बाद purge।';
$string['privacy:metadata:events:payload']       = 'इवेंट का वर्णन करने वाला JSON payload (slide_id, response_count, आदि)।';
$string['privacy:metadata:events:timecreated']   = 'जब इवेंट जनरेट हुआ।';

// Capability descriptions.
$string['sentientia_live:create']  = 'ट्रेनर के तौर पर नया लाइव सेशन बनाएं';
$string['sentientia_live:run']     = 'अपने बनाए लाइव सेशन को चलाएं (शुरू/आगे बढ़ाएं/समाप्त करें)';
$string['sentientia_live:join']    = 'कोड से मौजूदा लाइव सेशन में join करें';
$string['sentientia_live:respond'] = 'लाइव स्लाइड पर प्रतिक्रिया भेजें';
$string['sentientia_live:manage_all'] = 'एडमिन: सभी tenants के लाइव सेशन देखें और मैनेज करें';

// Errors.
$string['errorfeatureoff'] = 'Sentientia LMS लाइव एंगेजमेंट अभी बंद है। अपने एडमिन से live.enabled फ़ीचर फ़्लैग चालू करने के लिए कहें।';
