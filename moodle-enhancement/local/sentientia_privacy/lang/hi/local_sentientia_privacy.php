<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे गोपनीयता (DPDP)';
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
