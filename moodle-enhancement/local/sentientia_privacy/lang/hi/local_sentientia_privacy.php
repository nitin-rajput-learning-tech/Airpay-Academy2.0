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

// Erasure completeness (1.1.0, 2026-11-13): supplementary erasure provider.
$string['privacy:metadata:erasurecompleteness'] = 'जब erasure-completeness फ़ीचर फ़्लैग चालू होते हैं, तो इस प्लगइन की संदर्भ सूची में वे संदर्भ भी शामिल होते हैं जिनका डेटा अन्य घटकों का है: वे संदर्भ जिन्हें Moodle कोर की डेटा-अनुरोध स्वीकृति छोड़ देती है (एक कोर दोष), और कोर्स समापन रिकॉर्ड वाले वे कोर्स संदर्भ जिन्हें कोर कभी नहीं खोजता। ये अन्य संदर्भों की तरह ही स्वीकृति और डेटा रजिस्ट्री जाँच से गुज़रते हैं, और इन्हें उसी घटक के माध्यम से मिटाया या एक्सपोर्ट किया जाता है जिसका यह डेटा है। यह प्लगइन इनके बारे में कुछ भी संग्रहीत नहीं करता।';
$string['eventerasuresupplemented'] = 'पूरक डेटा मिटाना पूरा हुआ';
$string['erasuresupplementfailed_delete'] = 'पूरक डेटा मिटाना (ADR-035) इनका डेटा नहीं मिटा सका: {$a}, उन संदर्भों में जिन्हें मानक स्वीकृति ने छोड़ दिया था या उन कोर्स समापन रिकॉर्ड में जिन्हें कोर कभी नहीं खोजता। शेष घटकों को प्रोसेस कर दिया गया और डेटा मिटाना फिर भी पूरा हुआ (डेटा अनुरोध अब भी "हटाया गया" चिह्नित है; समाप्त उपयोगकर्ता अब भी हटाया जाता है), इसलिए इसे दोबारा नहीं चलाया जा सकता: इस डेटा विषय को ADR-035 अवशेष स्वीप के लिए दर्ज करें।';
$string['erasuresupplementfailed_export'] = 'पूरक एक्सपोर्ट (ADR-035) इनका डेटा एक्सपोर्ट नहीं कर सका: {$a}, उन संदर्भों से जिन्हें मानक स्वीकृति ने छोड़ दिया था या उन कोर्स समापन रिकॉर्ड से जिन्हें कोर कभी नहीं खोजता। एक्सपोर्ट फ़ाइल में यह डेटा नहीं है। कारण ठीक होने के बाद एक नया एक्सपोर्ट अनुरोध बनाएँ।';
