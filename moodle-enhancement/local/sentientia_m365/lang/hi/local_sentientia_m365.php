<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — Microsoft 365';

// ── Phase C.1 — स्कैफ़ोल्ड स्ट्रिंग्स (अभी कोई अंतिम-उपयोगकर्ता UI नहीं) ─────

// क्षमता विवरण।
$string['sentientia_m365:use']   = 'अपने Microsoft 365 खाते को कनेक्ट करें और Sentientia LMS के माध्यम से अपना M365 डेटा पढ़ें';
$string['sentientia_m365:admin'] = 'Sentientia Microsoft 365 एकीकरण का प्रशासन करें (tenant कॉन्फ़िगरेशन, किसी भी उपयोगकर्ता का अधिकार रद्द करना)';

// त्रुटियाँ / सुरक्षा अवरोध।
$string['confirm_required']      = 'इस Microsoft 365 कॉल को चलाने से पहले एक स्पष्ट प्रशासकीय पुष्टि की आवश्यकता है। यह सुविधा स्कैफ़ोल्ड मोड में है।';
$string['feature_off']           = 'Sentientia Microsoft 365 एकीकरण प्रशासक द्वारा अक्षम है (sentientia_m365_enabled बंद है)।';
$string['error_not_configured']  = 'Microsoft 365 एकीकरण कॉन्फ़िगर नहीं है। कनेक्ट करने से पहले Azure tenant ID, क्लाइंट ID और रीडायरेक्ट URI सेट करें।';
$string['error_empty_token']     = 'खाली access या refresh token संग्रहीत नहीं किया जा सकता।';
$string['error_token_decrypt']   = 'संग्रहीत token डिक्रिप्ट नहीं हो सका। अपना Microsoft 365 खाता डिस्कनेक्ट करके पुनः कनेक्ट करें।';
$string['error_invalid_state']   = 'OAuth state पैरामीटर मेल नहीं खाता। कृपया कनेक्शन पुनः आरंभ करें।';
$string['error_missing_code']    = 'Microsoft 365 ने प्राधिकरण कोड नहीं लौटाया। कनेक्शन रद्द कर दिया गया है।';
$string['error_scope_required']  = 'अनुरोधित ऑपरेशन के लिए {$a} स्कोप की आवश्यकता है, जो प्रदान नहीं किया गया था। पुनः कनेक्ट करें और अतिरिक्त स्कोप प्रदान करें।';

// सेटिंग्स पेज।
$string['settings_heading_azure']       = 'Azure AD ऐप्लिकेशन';
$string['settings_heading_azure_desc']  = 'Microsoft Entra ID के अंतर्गत Azure पोर्टल में एक ऐप्लिकेशन पंजीकृत करें। रीडायरेक्ट URI को नीचे सेट किए गए मान से बिल्कुल मेल खाना चाहिए।';
$string['setting_azure_tenant_id']      = 'Azure tenant ID (किरायेदार आईडी)';
$string['setting_azure_tenant_id_desc'] = 'Microsoft Entra (Azure AD) tenant का GUID जिसका एकीकरण उपयोग करता है। "common" मान का उपयोग केवल तब करें जब आप चाहते हैं कि कोई भी Microsoft खाता (कार्य, स्कूल या व्यक्तिगत) कनेक्ट कर सके।';
$string['setting_azure_client_id']      = 'Azure ऐप्लिकेशन (क्लाइंट) ID';
$string['setting_azure_client_id_desc'] = 'आपके Azure tenant में Sentientia LMS का प्रतिनिधित्व करने वाले ऐप्लिकेशन पंजीकरण का GUID।';
$string['setting_redirect_uri']         = 'OAuth रीडायरेक्ट URI';
$string['setting_redirect_uri_desc']    = 'Sentientia LMS कॉलबैक एंडपॉइंट का पूरा URL (उदाहरण: https://your.sentientia.example/local/sentientia_m365/callback.php)। इसे Azure ऐप्लिकेशन पंजीकरण में रीडायरेक्ट URI के रूप में जोड़ा जाना चाहिए।';
$string['setting_allowed_scopes']       = 'अनुमत OAuth स्कोप';
$string['setting_allowed_scopes_desc']  = 'कनेक्ट करते समय उपयोगकर्ता द्वारा अनुरोधित किए जा सकने वाले OAuth स्कोप। डिफ़ॉल्ट सेट (openid, profile, offline_access, User.Read) हमेशा प्रदान किया जाता है; यहाँ चयनित अतिरिक्त स्कोप वैकल्पिक होते हैं।';

// अनुमत-स्कोप विकल्प लेबल (multiselect के लिए)।
$string['scope_sites_read_all']    = 'SharePoint — उपयोगकर्ता द्वारा देखने योग्य सभी साइट्स पढ़ें (Sites.Read.All)';
$string['scope_files_read_all']    = 'SharePoint / OneDrive — उपयोगकर्ता द्वारा देखने योग्य सभी फ़ाइलें पढ़ें (Files.Read.All)';
$string['scope_calendars_read']    = 'Outlook — साइन-इन उपयोगकर्ता के कैलेंडर पढ़ें (Calendars.Read)';
$string['scope_calendars_readwrite']  = 'Outlook — साइन-इन उपयोगकर्ता के कैलेंडर पढ़ें + लिखें (Calendars.ReadWrite)';
$string['scope_team_member_read_all'] = 'Teams — साइन-इन उपयोगकर्ता की टीम सदस्यताएँ पढ़ें (TeamMember.Read.All)';
$string['scope_mail_read']         = 'Outlook — साइन-इन उपयोगकर्ता का मेलबॉक्स पढ़ें (Mail.Read)';

// ── गोपनीयता मेटाडेटा ─────────────────────────────────────────────────
$string['privacy:metadata:tokens'] = 'Moodle उपयोगकर्ता से जुड़े Microsoft 365 OAuth tokens। डेटाबेस तक पहुँचने से पहले tokens को सर्वर की Sodium कुंजी से एन्क्रिप्ट किया जाता है, लेकिन ciphertext को भी व्यक्तिगत डेटा माना जाता है क्योंकि डिक्रिप्ट होने पर यह उपयोगकर्ता के Microsoft खाते तक पहुँच प्रदान करता है।';
$string['privacy:metadata:tokens:userid']            = 'जिस Moodle उपयोगकर्ता का यह token है।';
$string['privacy:metadata:tokens:customerid']        = 'Sentientia customer scope जिसके तहत कनेक्शन बनाया गया था।';
$string['privacy:metadata:tokens:access_token_enc']  = 'एन्क्रिप्टेड अल्पकालिक Microsoft access token। प्लेनटेक्स्ट में कभी निर्यात नहीं किया जाता।';
$string['privacy:metadata:tokens:refresh_token_enc'] = 'एन्क्रिप्टेड दीर्घकालिक Microsoft refresh token। प्लेनटेक्स्ट में कभी निर्यात नहीं किया जाता।';
$string['privacy:metadata:tokens:expires']           = 'Unix timestamp जब access token की समय सीमा समाप्त होती है।';
$string['privacy:metadata:tokens:scopes']            = 'OAuth स्कोप जिनके लिए उपयोगकर्ता ने सहमति दी।';
$string['privacy:metadata:tokens:timecreated']       = 'जब कनेक्शन पहली बार स्थापित किया गया था।';
$string['privacy:metadata:tokens:timemodified']      = 'जब tokens को सबसे हाल ही में रीफ़्रेश या प्रतिस्थापित किया गया था।';

$string['privacy:metadata:microsoft_graph']        = 'Microsoft Graph हर बार उपयोगकर्ता का access token प्राप्त करता है जब कोई Sentientia LMS सुविधा उपयोगकर्ता की ओर से M365 डेटा पढ़ती है। उपयोगकर्ता का पहचान दावा बाहर की ओर प्रवाहित होता है; Microsoft Graph से प्रतिक्रियाएँ (प्रोफ़ाइल फ़ील्ड्स, कैलेंडर इवेंट्स, SharePoint मेटाडेटा) LMS में वापस प्रवाहित होती हैं।';
$string['privacy:metadata:microsoft_graph:userid'] = 'जिस Moodle उपयोगकर्ता की ओर से कॉल किया जा रहा है (एन्क्रिप्टेड token देखने के लिए उपयोग किया जाता है)।';
$string['privacy:metadata:microsoft_graph:scopes'] = 'OAuth स्कोप जिनके लिए access token को अनुमति दी गई थी — परिभाषित करते हैं कि कौन से Graph एंडपॉइंट कॉल किए जा सकते हैं।';
