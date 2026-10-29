<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Marathi strings for local_sentientia_api.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_api
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia पब्लिक API';
$string['sentientia_api:read'] = 'Sentientia पब्लिक API वाचा (कोर्सेस, एनरोलमेंट्स, कम्प्लीशन्स, स्किल्स)';
$string['sentientia_api:write'] = 'Sentientia पब्लिक API द्वारे राइट ऑपरेशन्स करा';
$string['sentientia_api:manage'] = 'Sentientia API आणि LTI रजिस्ट्रेशन्स मॅनेज करा';
$string['sentientia_api:lti'] = 'LTI 1.3 लाँच एंडपॉइंट म्हणून काम करा';
$string['api_disabled'] = 'Sentientia पब्लिक API सध्या बंद आहे.';
$string['api_write_disabled'] = 'Sentientia पब्लिक API वरील राइट ऑपरेशन्स सध्या बंद आहेत.';
$string['api_enabled_notice'] = 'Sentientia पब्लिक API (v1) चालू आहे.';
$string['ratelimited'] = 'रेट लिमिट ओलांडली. प्रत्येक विंडोसाठी {$a} रिक्वेस्ट्सची मर्यादा आहे. थोड्या वेळाने पुन्हा प्रयत्न करा.';
$string['error_notenant'] = 'तुमचे अकाउंट कोणत्याही योग्य टेनंटशी जोडलेले नाही; API ॲक्सेस नाकारला आहे.';
$string['error_notauthenticated'] = 'Sentientia पब्लिक API कॉल करण्यासाठी ऑथेंटिकेशन आवश्यक आहे.';
$string['error_no_manual_enrol'] = 'या कोर्सवर मॅन्युअल एनरोलमेंट उपलब्ध नाही.';
$string['error_role_not_assignable'] = 'तुम्ही या कोर्समध्ये हा रोल देऊ शकत नाही.';
$string['rest_base'] = 'REST बेस URL';
$string['v1_endpoints'] = 'v1 एंडपॉइंट्स';
$string['lti_status'] = 'LTI 1.3 स्टेटस';
$string['lti_enabled'] = 'LTI 1.3 चालू आहे.';
$string['lti_disabled'] = 'LTI 1.3 बंद आहे.';
$string['lti_launch_title'] = 'LTI 1.3 लाँच';
$string['lti_launch_verified'] = 'LTI लाँच व्हेरिफाय झाले.';
$string['lti_message_type'] = 'मेसेज प्रकार';
$string['lti_invalid_token'] = 'LTI टोकन चुकीच्या फॉरमॅटमध्ये आहे.';
$string['lti_bad_alg'] = 'LTI टोकन साइनिंग अल्गोरिदम सपोर्टेड नाही (RS256 आवश्यक).';
$string['lti_no_key'] = 'या LTI रजिस्ट्रेशनसाठी पडताळणीची कोणतीही की उपलब्ध नाही.';
$string['lti_bad_signature'] = 'LTI टोकन सिग्नेचर व्हेरिफाय करता आले नाही.';
$string['lti_bad_iss'] = 'LTI टोकनचा इश्युअर रजिस्ट्रेशनशी जुळत नाही.';
$string['lti_bad_aud'] = 'LTI टोकनचा ऑडियन्स या टूलशी जुळत नाही.';
$string['lti_expired'] = 'LTI टोकनची मुदत संपली आहे.';
$string['lti_bad_iat'] = 'LTI टोकन जारी केल्याची वेळ चुकीची आहे.';
$string['lti_bad_nonce'] = 'LTI टोकनचा नॉन्स चुकीचा आहे, गहाळ आहे किंवा आधीच वापरला गेला आहे.';
$string['lti_no_registration'] = 'या लाँचसाठी जुळणारे LTI रजिस्ट्रेशन सापडले नाही.';
$string['lti_no_authurl'] = 'या LTI रजिस्ट्रेशनमध्ये ऑथेंटिकेशन लॉगिन URL कॉन्फिगर केलेली नाही.';
$string['setting_ratelimit_heading'] = 'रेट लिमिटिंग';
$string['setting_ratelimit_desc'] = 'पब्लिक API साठी प्रति-युजर फिक्स्ड-विंडो रेट लिमिटिंग.';
$string['setting_rate_limit'] = 'प्रति विंडो रिक्वेस्ट्स';
$string['setting_rate_limit_desc'] = 'एका विंडोमध्ये एक युजर जास्तीत जास्त किती API रिक्वेस्ट्स करू शकतो.';
$string['setting_rate_window'] = 'विंडोची लांबी (सेकंद)';
$string['setting_rate_window_desc'] = 'रेट-लिमिट विंडोची लांबी, सेकंदांमध्ये.';
$string['setting_log_retention'] = 'रिक्वेस्ट-लॉग ठेवण्याचा कालावधी (दिवस)';
$string['setting_log_retention_desc'] = 'क्लीनअप टास्क API रिक्वेस्ट-लॉगच्या ओळी काढून टाकण्यापूर्वी त्या किती दिवस ठेवायच्या.';
$string['task_cleanup'] = 'Sentientia API क्लीनअप (रेट काउंटर्स, LTI नॉन्सेस, रिक्वेस्ट लॉग)';
$string['privacy:metadata:log'] = 'पब्लिक-API रिक्वेस्ट्सचा अपेंड-ओन्ली लॉग.';
$string['privacy:metadata:log:userid'] = 'रिक्वेस्ट करणारा युजर.';
$string['privacy:metadata:log:endpoint'] = 'कॉल केलेला API एंडपॉइंट.';
$string['privacy:metadata:log:status'] = 'रिक्वेस्टचा लॉजिकल स्टेटस.';
$string['privacy:metadata:log:timecreated'] = 'रिक्वेस्ट केव्हा केली गेली.';
$string['privacy:metadata:rate'] = 'प्रति-युजर रेट-लिमिट काउंटर्स.';
$string['privacy:metadata:rate:userid'] = 'हा काउंटर ज्या युजरचा आहे तो युजर.';
$string['privacy:metadata:rate:hits'] = 'सध्याच्या विंडोमधील रिक्वेस्ट्सची संख्या.';
$string['privacy:metadata:rate:windowstart'] = 'सध्याची रेट-लिमिट विंडो सुरू झाल्याची वेळ.';
$string['sentientia_api:webhooks_manage'] = 'आउटबाउंड वेबहुक सबस्क्रिप्शन्स आणि डिलिव्हरी लॉग मॅनेज करा';
$string['webhooks_title'] = 'आउटबाउंड वेबहुक्स';
$string['webhooks_intro'] = 'लर्निंग इव्हेंट्स घडल्यावर साइन केलेले JSON POST मिळवणारे https एंडपॉइंट्स रजिस्टर करा. प्रत्येक सबस्क्रिप्शनचे स्वतःचे HMAC-SHA256 सीक्रेट असते (हेडर X-Sentientia-Signature: t=&lt;unix&gt;,v1=&lt;hmac of "t.body"&gt;; 5 मिनिटांपेक्षा जुने असल्यास नाकारा). डिलिव्हरीज एक्स्पोनेन्शियल बॅकऑफसह पुन्हा प्रयत्न करतात आणि 5 प्रयत्नांनंतर डेड-लेटर केल्या जातात.';
$string['webhooks_subscriptions'] = 'सबस्क्रिप्शन्स';
$string['webhooks_deliveries'] = 'अलीकडील डिलिव्हरीज';
$string['webhooks_none'] = 'अजून कोणतेही सबस्क्रिप्शन नाही.';
$string['webhooks_nodeliveries'] = 'अजून कोणतीही डिलिव्हरी नाही.';
$string['webhook_name'] = 'नाव';
$string['webhook_url'] = 'एंडपॉइंट URL (https)';
$string['webhook_events'] = 'इव्हेंट्स';
$string['webhook_event'] = 'इव्हेंट';
$string['webhook_tenant'] = 'टेनंट रूट ID';
$string['webhook_tenant_help'] = 'हे सबस्क्रिप्शन एका टेनंट रूट ID पुरते मर्यादित करा, किंवा कस्टमरच्या प्रत्येक टेनंटकडून इव्हेंट्स मिळवण्यासाठी 0 टाका.';
$string['webhook_enabled'] = 'चालू';
$string['webhook_lastsuccess'] = 'शेवटचे यश';
$string['webhook_lastfailure'] = 'शेवटचे अपयश';
$string['webhook_status'] = 'स्टेटस';
$string['webhook_attempts'] = 'प्रयत्न';
$string['webhook_nextattempt'] = 'पुढचा प्रयत्न';
$string['webhook_httpstatus'] = 'HTTP';
$string['webhook_lasterror'] = 'शेवटची एरर';
$string['webhook_all_tenants'] = 'सर्व टेनंट्स';
$string['webhook_never'] = 'कधीच नाही';
$string['webhook_add'] = 'सबस्क्रिप्शन जोडा';
$string['webhook_created'] = 'सबस्क्रिप्शन तयार झाले.';
$string['webhook_deleted'] = 'सबस्क्रिप्शन आणि त्याची डिलिव्हरी हिस्ट्री डिलीट केली.';
$string['webhook_toggled'] = 'सबस्क्रिप्शन अपडेट झाले.';
$string['webhook_retried'] = 'डिलिव्हरी लगेच पुन्हा प्रयत्नासाठी परत क्यूमध्ये टाकली.';
$string['webhook_secret_shown'] = '"{$a}" साठी साइनिंग सीक्रेट (फक्त एकदाच दाखवले जाते - आत्ताच रिसीव्हिंग सिस्टममध्ये सेव्ह करा):';
$string['webhook_action_enable'] = 'सुरू करा';
$string['webhook_action_disable'] = 'बंद करा';
$string['webhook_action_delete'] = 'डिलीट करा';
$string['webhook_action_rotate'] = 'सीक्रेट बदला';
$string['webhook_action_retry'] = 'पुन्हा प्रयत्न करा';
$string['webhook_confirm_delete'] = 'नक्की हे सबस्क्रिप्शन आणि त्याची संपूर्ण डिलिव्हरी हिस्ट्री डिलीट करायची का?';
$string['webhook_counts'] = 'डिलिव्हरीज - क्यूमध्ये: {$a->queued}, पाठवल्या: {$a->sent}, अयशस्वी (पुन्हा प्रयत्न सुरू): {$a->failed}, डेड: {$a->dead}';
$string['webhook_flag_off_notice'] = 'आउटबाउंड-वेबहुक्स फीचर फ्लॅग ग्लोबल स्कोपमध्ये OFF आहे. सबस्क्रिप्शन्स सेव्ह होतात, पण संबंधित कस्टमर/टेनंटसाठी sentientia.api.enabled आणि sentientia.api.webhooks.enabled चालू होईपर्यंत काहीही क्यूमध्ये जात नाही किंवा पाठवले जात नाही.';
$string['webhook_name_required'] = 'सबस्क्रिप्शनचे नाव आवश्यक आहे.';
$string['webhook_events_required'] = 'किमान एक इव्हेंट निवडा.';
$string['webhook_url_invalid'] = 'एंडपॉइंट पूर्ण https:// URL असणे आवश्यक आहे.';
$string['webhook_url_blocked'] = 'साइटच्या आउटबाउंड-रिक्वेस्ट सिक्युरिटी पॉलिसीने तो एंडपॉइंट होस्ट ब्लॉक केला आहे (प्रायव्हेट किंवा इंटर्नल ॲड्रेस).';
$string['event_course_completed'] = 'कोर्स पूर्ण झाला';
$string['event_enrolment_created'] = 'एनरोलमेंट तयार झाले';
$string['event_certificate_issued'] = 'सर्टिफिकेट दिले';
$string['task_webhook_drain'] = 'Sentientia API आउटबाउंड वेबहुक डिलिव्हरी';
$string['privacy:metadata:whdel'] = 'क्यूमधील आणि डिलिव्हर झालेले आउटबाउंड वेबहुक इव्हेंट्स.';
$string['privacy:metadata:whdel:userid'] = 'इव्हेंट ज्या युजरशी संबंधित आहे तो युजर.';
$string['privacy:metadata:whdel:eventkey'] = 'लर्निंग इव्हेंटचा प्रकार.';
$string['privacy:metadata:whdel:status'] = 'इव्हेंटचा डिलिव्हरी स्टेटस.';
$string['privacy:metadata:whdel:timecreated'] = 'इव्हेंट क्यूमध्ये केव्हा टाकला गेला.';
$string['privacy:metadata:webhook_endpoint'] = 'इव्हेंट मेटाडेटा (युजर ID, कोर्स ID, टाइमस्टॅम्प्स - नावे किंवा ईमेल नाहीत) कस्टमरने रजिस्टर केलेल्या https एंडपॉइंट्सवर POST केला जातो.';
$string['sentientia_api:scim_manage'] = 'SCIM 2.0 प्रोव्हिजनिंग क्लायंट्स मॅनेज करा';
$string['scim_title'] = 'SCIM 2.0 प्रोव्हिजनिंग क्लायंट्स';
$string['scim_intro'] = 'प्रत्येक आयडेंटिटी प्रोव्हायडरसाठी (Entra ID, Okta) एक क्लायंट रजिस्टर करा. प्रत्येक क्लायंटला एक बेअरर टोकन मिळतो (फक्त एकदाच दाखवला जातो) आणि तो एका टेनंटशी बांधलेला असतो: तो तयार करत असलेले युजर्स त्या टेनंटमध्ये जातात, आणि तो फक्त त्या टेनंटमधील युजर्स पाहू, अपडेट किंवा डीॲक्टिव्हेट करू शकतो. डीॲक्टिव्हेशन (SCIM DELETE किंवा active=false) अकाउंट सस्पेंड करते आणि त्याची सेशन्स बंद करते; लर्निंग हिस्ट्री तशीच राहते.';
$string['scim_endpoint_url'] = 'आयडेंटिटी प्रोव्हायडरसाठी टेनंट URL';
$string['scim_clients'] = 'क्लायंट्स';
$string['scim_none'] = 'अजून कोणतेही SCIM क्लायंट्स नाहीत.';
$string['scim_client_name'] = 'नाव';
$string['scim_client_tenant'] = 'टेनंट रूट ID';
$string['scim_client_tenant_help'] = 'या क्लायंटने प्रोव्हिजन केलेले युजर्स या टेनंट रूटमध्ये ठेवले जातात आणि क्लायंट त्यापुरताच मर्यादित असतो. 0 = साइट-लेव्हल क्लायंट (सर्व टेनंट्स) - फक्त प्लॅटफॉर्म ऑपरेटरसाठी वापरा.';
$string['scim_client_auth'] = 'तयार केलेल्या युजर्ससाठी ऑथेंटिकेशन पद्धत';
$string['scim_client_auth_help'] = 'हा क्लायंट तयार करत असलेल्या अकाउंट्सना दिला जाणारा Moodle ऑथेंटिकेशन प्लगइन - सहसा तोच आयडेंटिटी प्रोव्हायडर देत असलेली सिंगल-साइन-ऑन पद्धत (oauth2 / oidc / saml2). त्यानंतर युजर्स IdP द्वारे साइन इन करतात; कोणताही पासवर्ड सेट केला जात नाही.';
$string['scim_client_ratelimit'] = 'प्रति विंडो रिक्वेस्ट्स';
$string['scim_client_ratelimit_help'] = 'एका रेट-लिमिट विंडोसाठी प्रति-क्लायंट रिक्वेस्ट मर्यादा (विंडोची लांबी संपूर्ण प्लगइनसाठीचे सेटिंग आहे). 0 टाकल्यास संपूर्ण प्लगइनची मर्यादा वापरली जाते.';
$string['scim_client_enabled'] = 'चालू';
$string['scim_client_lastseen'] = 'शेवटचे दिसले';
$string['scim_mappings'] = 'मॅप केलेले युजर्स';
$string['scim_client_add'] = 'क्लायंट जोडा';
$string['scim_client_created'] = 'क्लायंट तयार झाला.';
$string['scim_client_deleted'] = 'क्लायंट आणि त्याचे externalId मॅपिंग्स डिलीट केले (युजर अकाउंट्सना हात लावलेला नाही).';
$string['scim_client_toggled'] = 'क्लायंट अपडेट झाला.';
$string['scim_client_token_shown'] = '"{$a}" साठी बेअरर टोकन (फक्त एकदाच दाखवला जातो - आत्ताच आयडेंटिटी प्रोव्हायडरमध्ये पेस्ट करा):';
$string['scim_action_enable'] = 'सुरू करा';
$string['scim_action_disable'] = 'बंद करा';
$string['scim_action_delete'] = 'डिलीट करा';
$string['scim_action_rotate'] = 'टोकन बदला';
$string['scim_confirm_delete'] = 'नक्की हा क्लायंट डिलीट करायचा का? त्याचा टोकन लगेच काम करणे थांबवेल आणि त्याचे externalId मॅपिंग्स काढून टाकले जातील. युजर अकाउंट्सवर काहीही परिणाम होत नाही.';
$string['scim_client_name_required'] = 'क्लायंटचे नाव आवश्यक आहे.';
$string['scim_client_auth_invalid'] = 'SCIM ने तयार केलेल्या युजर्ससाठी ती ऑथेंटिकेशन पद्धत वापरण्याची परवानगी नाही.';
$string['scim_flag_off_notice'] = 'SCIM फीचर फ्लॅग ग्लोबल स्कोपमध्ये OFF आहे. संबंधित कस्टमर/टेनंटसाठी sentientia.api.enabled आणि sentientia.api.scim.enabled चालू होईपर्यंत एंडपॉइंट 503 उत्तर देतो.';
$string['scim_unauthorized'] = 'योग्य बेअरर टोकन आवश्यक आहे.';
$string['scim_disabled'] = 'या क्लायंटसाठी SCIM प्रोव्हिजनिंग चालू नाही.';
$string['scim_notfound'] = 'रिसोर्स सापडला नाही.';
$string['scim_conflict_username'] = 'हा userName असलेला युजर आधीपासून अस्तित्वात आहे.';
$string['scim_conflict_email'] = 'हा ईमेल ॲड्रेस असलेला युजर आधीपासून अस्तित्वात आहे.';
$string['scim_bad_json'] = 'रिक्वेस्ट बॉडी JSON ऑब्जेक्ट असणे आवश्यक आहे.';
$string['scim_internal'] = 'प्रोव्हिजनिंग रिक्वेस्ट पूर्ण करता आली नाही.';
$string['privacy:metadata:scimmap'] = 'Sentientia युजर्स आणि बाहेरील आयडेंटिटी प्रोव्हायडरने दिलेला आयडेंटिफायर यांमधील लिंक्स.';
$string['privacy:metadata:scimmap:userid'] = 'Sentientia युजर.';
$string['privacy:metadata:scimmap:externalid'] = 'आयडेंटिटी प्रोव्हायडरने त्या युजरला दिलेला आयडेंटिफायर.';
$string['privacy:metadata:scimmap:timecreated'] = 'लिंक केव्हा तयार झाली.';
$string['scim_events'] = 'प्रोव्हिजनिंग इव्हेंट्स (अटेस्टेशन)';
$string['scim_events_intro'] = 'आयडेंटिटी प्रोव्हायडरने SCIM द्वारे तयार, पुन्हा ॲक्टिव्हेट, डीॲक्टिव्हेट, अपडेट किंवा मूव्ह केलेले प्रत्येक अकाउंट, वेळ आणि क्लायंटसह - ऑडिटर्स मागतात तो डिप्रोव्हिजनिंगचा पुरावा.';
$string['scim_events_none'] = 'अजून कोणतेही प्रोव्हिजनिंग इव्हेंट्स नाहीत.';
$string['scim_event_time'] = 'केव्हा (UTC)';
$string['scim_event_action'] = 'ॲक्शन';
$string['scim_event_client'] = 'क्लायंट';
$string['scim_event_user'] = 'युजर';
$string['scim_event_external'] = 'externalId';
$string['scim_event_detail'] = 'तपशील';
$string['scim_export_csv'] = 'अटेस्टेशन CSV डाउनलोड करा';
$string['scim_action_created'] = 'तयार केले';
$string['scim_action_reactivated'] = 'पुन्हा ॲक्टिव्हेट केले';
$string['scim_action_deactivated'] = 'डीॲक्टिव्हेट केले';
$string['scim_action_updated'] = 'अपडेट केले';
$string['scim_action_moved'] = 'ग्रुप बदलला';
$string['scim_groups_readonly'] = 'ग्रुप्स संस्थेच्या ट्रीचे प्रतिबिंब आहेत आणि SCIM द्वारे ते तयार, रिनेम किंवा डिलीट करता येत नाहीत; हायरार्की संस्था ॲडमिनिस्ट्रेशनमध्ये मॅनेज करा. सदस्यत्वातील बदल स्वीकारले जातात.';
$string['scim_groups_unavailable'] = 'ग्रुप सदस्यत्वासाठी संस्था-ट्री युजर कॉलम्स आवश्यक आहेत, जे या साइटवर नाहीत.';
$string['scim_member_outside_scope'] = 'एक किंवा अधिक सदस्य या क्लायंटच्या टेनंटमध्ये नाहीत.';
$string['privacy:metadata:scimevt'] = 'आयडेंटिटी प्रोव्हायडर्सनी SCIM द्वारे केलेल्या अकाउंट बदलांचा अटेस्टेशन लॉग.';
$string['privacy:metadata:scimevt:userid'] = 'प्रभावित युजर.';
$string['privacy:metadata:scimevt:action'] = 'आयडेंटिटी प्रोव्हायडरने काय केले (तयार केले, पुन्हा ॲक्टिव्हेट केले, डीॲक्टिव्हेट केले, अपडेट केले, मूव्ह केले).';
$string['privacy:metadata:scimevt:externalid'] = 'त्या वेळी असलेला आयडेंटिटी प्रोव्हायडरचा आयडेंटिफायर.';
$string['privacy:metadata:scimevt:timecreated'] = 'ते केव्हा घडले.';
