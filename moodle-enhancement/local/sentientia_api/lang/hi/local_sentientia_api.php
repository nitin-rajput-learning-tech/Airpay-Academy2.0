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

// ── आउटबाउंड वेबहुक (ADR-030 वेव A) ──────────────────────────────────
$string['sentientia_api:webhooks_manage'] = 'आउटबाउंड वेबहुक सदस्यताएँ और डिलीवरी लॉग प्रबंधित करें';
$string['webhooks_title']         = 'आउटबाउंड वेबहुक';
$string['webhooks_intro']         = 'ऐसे https एंडपॉइंट पंजीकृत करें जो सीखने की घटनाओं पर हस्ताक्षरित JSON POST प्राप्त करें। हर सदस्यता का अपना HMAC-SHA256 सीक्रेट होता है (हेडर X-Sentientia-Signature: t=&lt;unix&gt;,v1=&lt;"t.body" का hmac&gt;; 5 मिनट से पुराना होने पर अस्वीकार करें)। डिलीवरी एक्सपोनेंशियल बैकऑफ़ के साथ पुनः प्रयास करती है और 5 प्रयासों के बाद डेड-लेटर हो जाती है।';
$string['webhooks_subscriptions'] = 'सदस्यताएँ';
$string['webhooks_deliveries']    = 'हाल की डिलीवरी';
$string['webhooks_none']          = 'अभी कोई सदस्यता नहीं है।';
$string['webhooks_nodeliveries']  = 'अभी कोई डिलीवरी नहीं है।';
$string['webhook_name']           = 'नाम';
$string['webhook_url']            = 'एंडपॉइंट URL (https)';
$string['webhook_events']         = 'घटनाएँ';
$string['webhook_event']          = 'घटना';
$string['webhook_tenant']         = 'टेनेंट रूट आईडी';
$string['webhook_tenant_help']    = 'इस सदस्यता को एक टेनेंट रूट आईडी तक सीमित करें, या ग्राहक के प्रत्येक टेनेंट की घटनाएँ प्राप्त करने के लिए 0 रखें।';
$string['webhook_enabled']        = 'सक्षम';
$string['webhook_lastsuccess']    = 'अंतिम सफलता';
$string['webhook_lastfailure']    = 'अंतिम विफलता';
$string['webhook_status']         = 'स्थिति';
$string['webhook_attempts']       = 'प्रयास';
$string['webhook_nextattempt']    = 'अगला प्रयास';
$string['webhook_httpstatus']     = 'HTTP';
$string['webhook_lasterror']      = 'अंतिम त्रुटि';
$string['webhook_all_tenants']    = 'सभी टेनेंट';
$string['webhook_never']          = 'कभी नहीं';
$string['webhook_add']            = 'सदस्यता जोड़ें';
$string['webhook_created']        = 'सदस्यता बनाई गई।';
$string['webhook_deleted']        = 'सदस्यता और उसका डिलीवरी इतिहास हटा दिया गया।';
$string['webhook_toggled']        = 'सदस्यता अद्यतन की गई।';
$string['webhook_retried']        = 'डिलीवरी तत्काल पुनः प्रयास के लिए पुनः कतारबद्ध की गई।';
$string['webhook_secret_shown']   = '"{$a}" के लिए हस्ताक्षर सीक्रेट (केवल एक बार दिखाया गया - इसे अभी प्राप्तकर्ता सिस्टम में संग्रहीत करें):';
$string['webhook_action_enable']  = 'सक्षम करें';
$string['webhook_action_disable'] = 'अक्षम करें';
$string['webhook_action_delete']  = 'हटाएँ';
$string['webhook_action_rotate']  = 'सीक्रेट बदलें';
$string['webhook_action_retry']   = 'पुनः प्रयास';
$string['webhook_confirm_delete'] = 'यह सदस्यता और इसका संपूर्ण डिलीवरी इतिहास हटाएँ?';
$string['webhook_counts']         = 'डिलीवरी - कतार में: {$a->queued}, भेजी गईं: {$a->sent}, विफल (पुनः प्रयास): {$a->failed}, डेड: {$a->dead}';
$string['webhook_flag_off_notice'] = 'आउटबाउंड-वेबहुक फ़ीचर फ़्लैग वैश्विक स्कोप में बंद है। सदस्यताएँ संग्रहीत हैं, लेकिन जब तक संबंधित ग्राहक/टेनेंट के लिए sentientia.api.enabled और sentientia.api.webhooks.enabled चालू नहीं किए जाते, कुछ भी कतारबद्ध या भेजा नहीं जाता।';
$string['webhook_name_required']  = 'सदस्यता का नाम आवश्यक है।';
$string['webhook_events_required'] = 'कम से कम एक घटना चुनें।';
$string['webhook_url_invalid']    = 'एंडपॉइंट एक पूर्ण https:// URL होना चाहिए।';
$string['webhook_url_blocked']    = 'वह एंडपॉइंट होस्ट साइट की आउटबाउंड-अनुरोध सुरक्षा नीति द्वारा अवरुद्ध है (निजी या आंतरिक पता)।';
$string['event_course_completed']   = 'कोर्स पूर्ण हुआ';
$string['event_enrolment_created']  = 'नामांकन बनाया गया';
$string['event_certificate_issued'] = 'प्रमाणपत्र जारी किया गया';
$string['task_webhook_drain']     = 'सेंटिएंशिया एपीआई आउटबाउंड वेबहुक डिलीवरी';
$string['privacy:metadata:whdel']             = 'कतारबद्ध और वितरित आउटबाउंड वेबहुक घटनाएँ।';
$string['privacy:metadata:whdel:userid']      = 'वह उपयोगकर्ता जिससे घटना संबंधित है।';
$string['privacy:metadata:whdel:eventkey']    = 'सीखने की घटना का प्रकार।';
$string['privacy:metadata:whdel:status']      = 'घटना की डिलीवरी स्थिति।';
$string['privacy:metadata:whdel:timecreated'] = 'घटना कब कतारबद्ध की गई।';
$string['privacy:metadata:webhook_endpoint']  = 'घटना मेटाडेटा (उपयोगकर्ता आईडी, कोर्स आईडी, टाइमस्टैम्प - कोई नाम या ईमेल नहीं) ग्राहक-पंजीकृत https एंडपॉइंट पर POST किया जाता है।';

// ── SCIM 2.0 प्रोविज़निंग (ADR-030 वेव B) ──────────────────────────────
$string['sentientia_api:scim_manage'] = 'SCIM 2.0 प्रोविज़निंग क्लाइंट प्रबंधित करें';
$string['scim_title']              = 'SCIM 2.0 प्रोविज़निंग क्लाइंट';
$string['scim_intro']              = 'प्रति पहचान प्रदाता (Entra ID, Okta) एक क्लाइंट पंजीकृत करें। हर क्लाइंट को एक बियरर टोकन (एक बार दिखाया जाता है) मिलता है और वह एक टेनेंट से बंधा होता है: उसके द्वारा बनाए गए उपयोगकर्ता उसी टेनेंट में जाते हैं, और वह केवल उसके भीतर के उपयोगकर्ताओं को देख, अद्यतन या निष्क्रिय कर सकता है। निष्क्रियकरण (SCIM DELETE या active=false) खाते को निलंबित करता है और उसके सत्र समाप्त करता है; सीखने का इतिहास बना रहता है।';
$string['scim_endpoint_url']       = 'पहचान प्रदाता के लिए टेनेंट URL';
$string['scim_clients']            = 'क्लाइंट';
$string['scim_none']               = 'अभी कोई SCIM क्लाइंट नहीं है।';
$string['scim_client_name']        = 'नाम';
$string['scim_client_tenant']      = 'टेनेंट रूट आईडी';
$string['scim_client_tenant_help'] = 'इस क्लाइंट द्वारा प्रोविज़न किए गए उपयोगकर्ता इस टेनेंट रूट में रखे जाते हैं और क्लाइंट उसी तक सीमित रहता है। 0 = साइट-स्तरीय क्लाइंट (सभी टेनेंट) - केवल प्लेटफ़ॉर्म ऑपरेटर के लिए उपयोग करें।';
$string['scim_client_auth']        = 'बनाए गए उपयोगकर्ताओं के लिए प्रमाणीकरण विधि';
$string['scim_client_auth_help']   = 'इस क्लाइंट द्वारा बनाए गए खातों को दी जाने वाली Moodle प्रमाणीकरण प्लगइन - सामान्यतः वही सिंगल-साइन-ऑन विधि जो यह पहचान प्रदाता देता है (oauth2 / oidc / saml2)। उपयोगकर्ता फिर IdP के माध्यम से साइन इन करते हैं; कोई पासवर्ड सेट नहीं होता।';
$string['scim_client_ratelimit']   = 'प्रति विंडो अनुरोध';
$string['scim_client_ratelimit_help'] = 'एक दर-सीमा विंडो के लिए प्रति-क्लाइंट अनुरोध बजट (विंडो अवधि प्लगइन-व्यापी सेटिंग है)। 0 प्लगइन-व्यापी बजट का उपयोग करता है।';
$string['scim_client_enabled']     = 'सक्षम';
$string['scim_client_lastseen']    = 'अंतिम बार देखा गया';
$string['scim_mappings']           = 'मैप किए गए उपयोगकर्ता';
$string['scim_client_add']         = 'क्लाइंट जोड़ें';
$string['scim_client_created']     = 'क्लाइंट बनाया गया।';
$string['scim_client_deleted']     = 'क्लाइंट और उसकी externalId मैपिंग हटा दी गईं (उपयोगकर्ता खाते अप्रभावित हैं)।';
$string['scim_client_toggled']     = 'क्लाइंट अद्यतन किया गया।';
$string['scim_client_token_shown'] = '"{$a}" के लिए बियरर टोकन (केवल एक बार दिखाया गया - इसे अभी पहचान प्रदाता में पेस्ट करें):';
$string['scim_action_enable']      = 'सक्षम करें';
$string['scim_action_disable']     = 'अक्षम करें';
$string['scim_action_delete']      = 'हटाएँ';
$string['scim_action_rotate']      = 'टोकन बदलें';
$string['scim_confirm_delete']     = 'यह क्लाइंट हटाएँ? इसका टोकन तत्काल काम करना बंद कर देगा और इसकी externalId मैपिंग हट जाएँगी। उपयोगकर्ता खाते प्रभावित नहीं होंगे।';
$string['scim_client_name_required'] = 'क्लाइंट का नाम आवश्यक है।';
$string['scim_client_auth_invalid']  = 'SCIM-निर्मित उपयोगकर्ताओं के लिए वह प्रमाणीकरण विधि अनुमत नहीं है।';
$string['scim_flag_off_notice']    = 'SCIM फ़ीचर फ़्लैग वैश्विक स्कोप में बंद है। जब तक संबंधित ग्राहक/टेनेंट के लिए sentientia.api.enabled और sentientia.api.scim.enabled चालू नहीं किए जाते, एंडपॉइंट 503 उत्तर देता है।';
$string['scim_unauthorized']       = 'एक मान्य बियरर टोकन आवश्यक है।';
$string['scim_disabled']           = 'इस क्लाइंट के लिए SCIM प्रोविज़निंग सक्षम नहीं है।';
$string['scim_notfound']           = 'संसाधन नहीं मिला।';
$string['scim_conflict_username']  = 'इस userName वाला उपयोगकर्ता पहले से मौजूद है।';
$string['scim_conflict_email']     = 'इस ईमेल पते वाला उपयोगकर्ता पहले से मौजूद है।';
$string['scim_bad_json']           = 'अनुरोध बॉडी एक JSON ऑब्जेक्ट होनी चाहिए।';
$string['scim_internal']           = 'प्रोविज़निंग अनुरोध पूरा नहीं किया जा सका।';
$string['privacy:metadata:scimmap']             = 'सेंटिएंशिया उपयोगकर्ताओं और बाहरी पहचान प्रदाता द्वारा दिए गए पहचानकर्ता के बीच लिंक।';
$string['privacy:metadata:scimmap:userid']      = 'सेंटिएंशिया उपयोगकर्ता।';
$string['privacy:metadata:scimmap:externalid']  = 'उपयोगकर्ता के लिए पहचान प्रदाता का पहचानकर्ता।';
$string['privacy:metadata:scimmap:timecreated'] = 'लिंक कब बनाया गया।';
