<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi (hi) strings for local_sentientia_api — 100% parity with en.
 *
 * @package local_sentientia_api
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंशिया सार्वजनिक एपीआई';

// Capabilities.
$string['sentientia_api:read']   = 'सेंटिएंशिया सार्वजनिक एपीआई पढ़ें (कोर्स, नामांकन, समापन, कौशल)';
$string['sentientia_api:write']  = 'सेंटिएंशिया सार्वजनिक एपीआई के माध्यम से लेखन कार्य करें';
$string['sentientia_api:manage'] = 'सेंटिएंशिया एपीआई और LTI पंजीकरण प्रबंधित करें';
$string['sentientia_api:lti']    = 'LTI 1.3 लॉन्च एंडपॉइंट के रूप में कार्य करें';

// API state / errors.
$string['api_disabled']       = 'सेंटिएंशिया सार्वजनिक एपीआई वर्तमान में अक्षम है।';
$string['api_write_disabled'] = 'सेंटिएंशिया सार्वजनिक एपीआई पर लेखन कार्य वर्तमान में अक्षम हैं।';
$string['api_enabled_notice'] = 'सेंटिएंशिया सार्वजनिक एपीआई (v1) सक्षम है।';
$string['ratelimited']        = 'दर सीमा पार हो गई। बजट प्रति विंडो {$a} अनुरोध है। कृपया शीघ्र ही पुनः प्रयास करें।';
$string['error_notenant']     = 'आपका खाता किसी मान्य टेनेंट से संबद्ध नहीं है; एपीआई पहुँच अस्वीकृत।';
$string['error_notauthenticated'] = 'सेंटिएंशिया सार्वजनिक एपीआई को कॉल करने के लिए प्रमाणीकरण आवश्यक है।';
$string['error_no_manual_enrol'] = 'इस कोर्स पर मैन्युअल नामांकन उपलब्ध नहीं है।';

// Landing page.
$string['rest_base']    = 'REST बेस URL';
$string['v1_endpoints'] = 'v1 एंडपॉइंट';
$string['lti_status']   = 'LTI 1.3 स्थिति';
$string['lti_enabled']  = 'LTI 1.3 सक्षम है।';
$string['lti_disabled'] = 'LTI 1.3 अक्षम है।';

// LTI launch.
$string['lti_launch_title']    = 'LTI 1.3 लॉन्च';
$string['lti_launch_verified'] = 'LTI लॉन्च सफलतापूर्वक सत्यापित हुआ।';
$string['lti_message_type']    = 'संदेश प्रकार';

// LTI errors.
$string['lti_invalid_token']   = 'LTI टोकन विकृत है।';
$string['lti_bad_alg']         = 'असमर्थित LTI टोकन हस्ताक्षर एल्गोरिथम (RS256 आवश्यक)।';
$string['lti_no_key']          = 'इस LTI पंजीकरण के लिए कोई सत्यापन कुंजी उपलब्ध नहीं है।';
$string['lti_bad_signature']   = 'LTI टोकन हस्ताक्षर सत्यापित नहीं किया जा सका।';
$string['lti_bad_iss']         = 'LTI टोकन जारीकर्ता पंजीकरण से मेल नहीं खाता।';
$string['lti_bad_aud']         = 'LTI टोकन ऑडियंस इस टूल से मेल नहीं खाता।';
$string['lti_expired']         = 'LTI टोकन की समय-सीमा समाप्त हो गई है।';
$string['lti_bad_iat']         = 'LTI टोकन का जारी-करने-का-समय अमान्य है।';
$string['lti_bad_nonce']       = 'LTI टोकन nonce अमान्य, अनुपस्थित, या पहले से उपयोग किया गया है।';
$string['lti_no_registration'] = 'इस लॉन्च के लिए कोई मिलान खाता LTI पंजीकरण नहीं मिला।';
$string['lti_no_authurl']      = 'LTI पंजीकरण में कोई प्रमाणीकरण लॉगिन URL कॉन्फ़िगर नहीं है।';

// Settings.
$string['setting_ratelimit_heading'] = 'दर सीमन';
$string['setting_ratelimit_desc']    = 'सार्वजनिक एपीआई के लिए प्रति-उपयोगकर्ता निश्चित-विंडो दर सीमन।';
$string['setting_rate_limit']        = 'प्रति विंडो अनुरोध';
$string['setting_rate_limit_desc']   = 'एक उपयोगकर्ता एक विंडो के भीतर अधिकतम कितने एपीआई अनुरोध कर सकता है।';
$string['setting_rate_window']       = 'विंडो अवधि (सेकंड)';
$string['setting_rate_window_desc']  = 'दर-सीमा विंडो की अवधि सेकंड में।';
$string['setting_log_retention']     = 'अनुरोध-लॉग प्रतिधारण (दिन)';
$string['setting_log_retention_desc'] = 'सफाई कार्य द्वारा एपीआई अनुरोध-लॉग पंक्तियों को हटाने से पहले उन्हें रखने के दिनों की संख्या।';

// Scheduled task.
$string['task_cleanup'] = 'सेंटिएंशिया एपीआई सफाई (दर काउंटर, LTI nonce, अनुरोध लॉग)';

// Privacy.
$string['privacy:metadata:log']              = 'सार्वजनिक-एपीआई अनुरोधों का केवल-जोड़ने योग्य लॉग।';
$string['privacy:metadata:log:userid']       = 'अनुरोध करने वाला उपयोगकर्ता।';
$string['privacy:metadata:log:endpoint']     = 'आह्वान किया गया एपीआई एंडपॉइंट।';
$string['privacy:metadata:log:status']       = 'अनुरोध की तार्किक स्थिति।';
$string['privacy:metadata:log:timecreated']  = 'अनुरोध कब किया गया।';
$string['privacy:metadata:rate']             = 'प्रति-उपयोगकर्ता दर-सीमा काउंटर।';
$string['privacy:metadata:rate:userid']      = 'काउंटर जिस उपयोगकर्ता का है।';
$string['privacy:metadata:rate:hits']        = 'वर्तमान विंडो में अनुरोधों की संख्या।';
$string['privacy:metadata:rate:windowstart'] = 'वर्तमान दर-सीमा विंडो का प्रारंभ समय।';
