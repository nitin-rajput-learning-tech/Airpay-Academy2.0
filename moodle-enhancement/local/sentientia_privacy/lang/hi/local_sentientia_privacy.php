<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया गोपनीयता (DPDP)';
$string['myprivacy'] = 'मेरी गोपनीयता और डेटा';
$string['downloadrequested'] = 'आपका डेटा एक्सपोर्ट तैयार किया जा रहा है। तैयार होने पर आपको सूचित किया जाएगा।';
$string['deleterequested'] = 'आपका खाता हटाने का अनुरोध सबमिट किया गया है। एक व्यवस्थापक 3-5 कार्य दिवसों में इसकी समीक्षा करेगा।';
$string['downloaddata'] = 'मेरा डेटा डाउनलोड करें';
$string['deleteaccount'] = 'मेरा खाता हटाएं';
$string['requesthistory'] = 'अनुरोध इतिहास';
$string['dpdpnotice'] = 'DPDP अधिनियम 2023 सूचना';

// P1 #50 (2026-05-20) — Hindi top-up: 2 strings (message provider + privacy).
$string['privacy:metadata']                = 'गोपनीयता अनुरोध रिकॉर्ड और सहमति लॉग संग्रहीत करता है।';
$string['messageprovider:privacy_request'] = 'DPDP गोपनीयता अनुरोध सूचनाएँ';

// Privacy provider (2026-08-04) — real metadata + export provider.
$string['privacy:metadata:privacy_requests']               = 'उपयोगकर्ता द्वारा या उनके लिए दर्ज डेटा विषय अधिकार (DSR) अनुरोध';
$string['privacy:metadata:privacy_requests:userid']        = 'जिस उपयोगकर्ता के बारे में अनुरोध है';
$string['privacy:metadata:privacy_requests:request_type']  = 'अनुरोध का प्रकार (एक्सपोर्ट या हटाना)';
$string['privacy:metadata:privacy_requests:status']        = 'अनुरोध की प्रोसेसिंग स्थिति';
$string['privacy:metadata:privacy_requests:reason']        = 'अनुरोध के लिए उपयोगकर्ता द्वारा दिया गया कारण';
$string['privacy:metadata:privacy_requests:admin_notes']   = 'अनुरोध पर व्यवस्थापक द्वारा दर्ज टिप्पणियाँ';
$string['privacy:metadata:privacy_requests:processed_by']  = 'अनुरोध को प्रोसेस करने वाला व्यवस्थापक';
$string['privacy:metadata:privacy_requests:download_url']  = 'डेटा एक्सपोर्ट का (समाप्त होने वाला) डाउनलोड लिंक';
$string['privacy:metadata:privacy_requests:timecreated']   = 'अनुरोध कब दर्ज किया गया';
$string['privacy:metadata:privacy_requests:timeprocessed'] = 'अनुरोध कब प्रोसेस किया गया';
$string['privacy:metadata:consent_log']                    = 'सहमति देने और वापस लेने का लॉग, सहमति के प्रमाण के रूप में रखा गया';
$string['privacy:metadata:consent_log:userid']             = 'जिस उपयोगकर्ता ने सहमति दी या वापस ली';
$string['privacy:metadata:consent_log:consent_type']       = 'सहमति किस विषय को कवर करती है';
$string['privacy:metadata:consent_log:consented']          = 'सहमति दी गई (1) या वापस ली गई (0)';
$string['privacy:metadata:consent_log:ip_address']         = 'सहमति घटना का IP पता';
$string['privacy:metadata:consent_log:user_agent']         = 'सहमति घटना का ब्राउज़र यूज़र एजेंट';
$string['privacy:metadata:consent_log:timecreated']        = 'सहमति घटना कब हुई';

// Right-to-erasure outcomes on the admin panel (2026-09-24).
$string['incomplete']         = 'अपूर्ण';
$string['needsfollowup']      = 'मैन्युअल अनुवर्ती कार्रवाई आवश्यक';
$string['erasurecompleted']   = 'खाते को गुमनाम कर दिया गया और इस उपयोगकर्ता का सारा सेंटिएंटिया डेटा मिटा दिया गया।';
$string['erasurepartial']     = 'खाते को गुमनाम कर दिया गया, लेकिन कुछ डेटा मिटाया नहीं जा सका। अनुरोध को अपूर्ण चिह्नित किया गया है; उसके नोट में शेष डेटा की सूची है।';
$string['requestnotpending']  = 'इस अनुरोध पर पहले ही कार्रवाई हो चुकी है।';
$string['erasurenotapplicable'] = 'यह खाता हटाने का अनुरोध नहीं है, या इसका उपयोगकर्ता अब मौजूद नहीं है। कुछ भी नहीं बदला गया।';

// ADR-035 erasure supplement (2026-11-13).
$string['privacy:metadata:supplement:logstore']         = 'डेटा अनुरोध पर कार्रवाई होने पर यह प्लगइन मानक लॉग में उपयोगकर्ता की अपनी प्रविष्टियाँ भी खोजता, निर्यात करता और हटाता है। जब कोई लॉग संदर्भ एक से अधिक बार आता है, तो कोर इन्हें छोड़ सकता है। जिन प्रविष्टियों में उपयोगकर्ता केवल संबंधित या वास्तविक उपयोगकर्ता के रूप में दर्ज है, वे कोर की तरह बनी रहती हैं।';
$string['privacy:metadata:supplement:completion']       = 'डेटा अनुरोध पर कार्रवाई होने पर यह प्लगइन उपयोगकर्ता के पाठ्यक्रम पूर्णता रिकॉर्ड भी खोजता, निर्यात करता और हटाता है, जिनमें बिना पूर्णता मानदंड वाले पाठ्यक्रमों के रिकॉर्ड भी शामिल हैं, जिन्हें कोर नहीं खोजता।';
$string['privacy:metadata:supplement:stats']            = 'डेटा अनुरोध पर कार्रवाई होने पर यह प्लगइन उपयोगकर्ता के प्रति-पाठ्यक्रम गतिविधि आँकड़े भी खोजता, निर्यात करता और हटाता है। जब कोई पाठ्यक्रम एक से अधिक बार आता है, तो कोर इन्हें छोड़ सकता है।';
$string['privacy:metadata:supplement:stats:userid']     = 'वह उपयोगकर्ता जिसके बारे में आँकड़े हैं';
$string['privacy:metadata:supplement:stats:courseid']   = 'वह पाठ्यक्रम जिसके लिए आँकड़े हैं';
$string['privacy:metadata:supplement:stats:statsreads'] = 'अवधि में पढ़ने की कार्रवाइयों की संख्या';
$string['privacy:metadata:supplement:stats:statswrites'] = 'अवधि में लिखने की कार्रवाइयों की संख्या';
$string['privacy:path:coursecompletion']                = 'पाठ्यक्रम पूर्णता रिकॉर्ड';
