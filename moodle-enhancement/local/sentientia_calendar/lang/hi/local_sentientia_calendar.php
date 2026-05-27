<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi strings for local_sentientia_calendar.
 *
 * 100% parity with en — every key in en/local_sentientia_calendar.php
 * appears here. Maintain this property when adding new strings (CLAUDE.md
 * Hindi-parity rule).
 *
 * @package local_sentientia_calendar
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — कैलेंडर सिंक';

// Navigation.
$string['nav_label'] = 'कैलेंडर सदस्यता';

// User-facing subscription page.
$string['page_title']     = 'कैलेंडर सदस्यता';
$string['page_heading']   = 'अपने लर्निंग कैलेंडर की सदस्यता लें';
$string['page_intro']     = 'अपनी Sentientia LMS कोर्स डेडलाइन, क्लासरूम सेशन और परीक्षा तिथियाँ Outlook, Google Calendar या Apple Calendar में जोड़ें। नीचे दिया गया लिंक आपके लिए व्यक्तिगत है — इसे निजी रखें।';
$string['events_heading'] = 'क्या-क्या शामिल है';
$string['events_courses']   = 'हर कोर्स जिसमें आप एनरोल हैं, उसकी कोर्स पूरा करने की डेडलाइन';
$string['events_classroom'] = 'क्लासरूम (इंस्ट्रक्टर-लेड ट्रेनिंग) सेशन की शुरुआत और समाप्ति समय';
$string['events_exams']     = 'अगले 90 दिनों में परीक्षा बंद होने की तिथियाँ';

// Subscription URL widget.
$string['copy_label']        = 'सदस्यता URL कॉपी करें';
$string['copied_label']      = 'कॉपी हो गया!';
$string['security_note']     = 'इस URL को पासवर्ड की तरह संभालें — जिस किसी के पास यह होगा वह आपका लर्निंग कैलेंडर देख सकता है। यदि आप कभी गलती से कहीं पेस्ट कर दें तो नीचे "पुनः जनरेट करें" का उपयोग करें।';

// Regenerate.
$string['regenerate_label']   = 'URL पुनः जनरेट करें';
$string['regenerate_help']    = 'मौजूदा URL अमान्य कर देता है और नया जारी करता है। आपको हर उस कैलेंडर क्लाइंट में सदस्यता अपडेट करनी होगी जहाँ आपने पहले से जोड़ा है।';
$string['regenerate_success'] = 'सदस्यता URL पुनः जनरेट कर दिया गया। पुराना लिंक अब काम नहीं करेगा।';

// How-to.
$string['how_to_heading'] = 'सदस्यता कैसे लें';
$string['how_to_outlook'] = 'Outlook वेब पर: Calendar ▶ Add calendar ▶ Subscribe from web ▶ URL पेस्ट करें ▶ नाम दें "Sentientia" ▶ Import। डेस्कटॉप Outlook: File ▶ Account Settings ▶ Internet Calendars ▶ New ▶ URL पेस्ट करें।';
$string['how_to_google']  = 'Google Calendar: Other calendars ▶ + ▶ From URL ▶ URL पेस्ट करें ▶ Add calendar।';
$string['how_to_apple']   = 'Apple Calendar (macOS): File ▶ New Calendar Subscription ▶ URL पेस्ट करें ▶ Subscribe। iOS: Settings ▶ Calendar ▶ Accounts ▶ Add Account ▶ Other ▶ Add Subscribed Calendar।';

// OAuth bi-directional sync — Phase 2.1 UI strings.
$string['oauth_heading']             = 'द्विदिशीय कैलेंडर सिंक (बीटा)';
$string['oauth_intro']                = 'अपना Outlook या Google कैलेंडर कनेक्ट करें ताकि Sentientia LMS डेडलाइन सीधे उसमें डाली जाएँ, और आपकी प्रतिक्रियाएँ (स्वीकार / अस्वीकार) स्वतः Sentientia में वापस आ जाएँ।';
$string['oauth_provider_m365']        = 'Microsoft 365 (Outlook)';
$string['oauth_provider_desc_m365']   = 'आपके Outlook कैलेंडर में इवेंट पढ़ें और लिखें। हम केवल उन्हीं इवेंट को छूते हैं जिन्हें LMS स्वयं जोड़ता है।';
$string['oauth_provider_google']      = 'Google Calendar';
$string['oauth_provider_desc_google'] = 'LMS द्वारा आपके Google कैलेंडर में बनाए गए इवेंट पढ़ें और लिखें। हम आपके निजी इवेंट कभी नहीं देखते।';
$string['oauth_connect_m365']         = 'Outlook कनेक्ट करें';
$string['oauth_connect_google']       = 'Google Calendar कनेक्ट करें';
$string['oauth_reconnect']            = 'पुनः कनेक्ट करें';
$string['oauth_disconnect']           = 'डिस्कनेक्ट करें';
$string['oauth_status_connected']     = 'कनेक्टेड ({$a->date} को समाप्त)';
$string['oauth_status_expired']       = 'समाप्त — कृपया पुनः कनेक्ट करें';
$string['oauth_status_disconnected']  = 'कनेक्टेड नहीं';
$string['connect_success_m365']       = 'Microsoft 365 कैलेंडर कनेक्ट हो गया — आपकी Sentientia डेडलाइन शीघ्र ही सिंक होने लगेंगी।';
$string['connect_success_google']     = 'Google Calendar कनेक्ट हो गया — आपकी Sentientia डेडलाइन शीघ्र ही सिंक होने लगेंगी।';
$string['disconnect_success_m365']    = 'Microsoft 365 कैलेंडर डिस्कनेक्ट हो गया। पहले से पुश किए गए इवेंट आपके कैलेंडर में बने रहेंगे।';
$string['disconnect_success_google']  = 'Google Calendar डिस्कनेक्ट हो गया। पहले से पुश किए गए इवेंट आपके कैलेंडर में बने रहेंगे।';
$string['connect_error']              = 'कैलेंडर प्रोवाइडर ने कनेक्शन अस्वीकार कर दिया ({$a->code})। {$a->description}';

// Errors.
$string['error_flag_off']                 = 'आपके खाते के लिए कैलेंडर सिंक वर्तमान में सक्षम नहीं है। अपने एडमिनिस्ट्रेटर से संपर्क करें।';
$string['error_token_collision']          = 'कई प्रयासों के बाद भी एक अद्वितीय कैलेंडर टोकन जनरेट नहीं हो सका। कृपया पुनः प्रयास करें।';
$string['error_oauth_clientid_missing']   = 'इस प्रोवाइडर के लिए OAuth क्लाइंट ID कॉन्फ़िगर नहीं है। एडमिनिस्ट्रेटर से ऐप रजिस्टर करने और साइट प्रशासन → प्लगिन्स → लोकल प्लगिन्स → Sentientia कैलेंडर सिंक में क्लाइंट ID जोड़ने का अनुरोध करें।';
$string['error_oauth_state_invalid']      = 'OAuth स्टेट मेल नहीं खाया — अनुरोध किसी लंबित प्राधिकरण से मेल नहीं खाता। कृपया कनेक्ट प्रवाह फिर से शुरू करें।';
$string['error_oauth_code_missing']       = 'OAuth कॉलबैक में प्राधिकरण कोड नहीं था। प्रोवाइडर ने सहमति अस्वीकार कर दी होगी। कृपया पुनः प्रयास करें।';
$string['error_oauth_no_refresh_token']   = 'इस प्रोवाइडर के लिए कोई संग्रहीत रिफ्रेश टोकन नहीं है। नया जारी करने के लिए प्रोवाइडर से पुनः कनेक्ट करें।';
$string['error_oauth_token_response']     = 'कैलेंडर प्रोवाइडर ने अप्रत्याशित प्रतिक्रिया लौटाई ({$a})। कृपया पुनः प्रयास करें या अपने एडमिनिस्ट्रेटर से संपर्क करें।';
$string['error_oauth_invalid_grant']      = 'आपके कैलेंडर प्रोवाइडर ने हमारी पहुँच रद्द कर दी। ताज़ी सहमति देने के लिए कृपया पुनः कनेक्ट करें।';
$string['error_oauth_http_failure']       = 'कैलेंडर प्रोवाइडर तक नहीं पहुँच सके ({$a})। नेटवर्क या प्रोवाइडर डाउन हो सकता है — कृपया कुछ मिनटों में पुनः प्रयास करें।';
$string['error_oauth_unknown_provider']   = 'अज्ञात OAuth प्रोवाइडर: {$a}।';
$string['oauth_not_live']                 = 'OAuth Phase 2 वर्तमान में केवल स्कैफ़ोल्डिंग है। प्रति-ग्राहक रोलआउट की पुष्टि के बाद भविष्य की रिलीज़ में लाइव टोकन एक्सचेंज सक्षम किया जाएगा।';

// Scheduled tasks.
$string['task_purge_old_tokens'] = 'Sentientia कैलेंडर — रद्द किए गए टोकन हटाएँ';

// Capabilities.
$string['sentientia_calendar:subscribe']  = 'अपना कैलेंडर सदस्यता URL प्रबंधित करें';
$string['sentientia_calendar:manage_all'] = 'किसी भी यूज़र के कैलेंडर सदस्यता टोकन प्रबंधित करें';

// Settings — Phase 2 OAuth.
$string['settings_pagetitle']               = 'Sentientia कैलेंडर सिंक';
$string['settings_section_oauth']           = 'OAuth — Microsoft 365 और Google Calendar';
$string['settings_section_oauth_desc']      = 'द्विदिशीय सिंक प्रवाह के लिए क्लाइंट ID और सीक्रेट। खाली मान संबंधित "कनेक्ट…" बटन को यूज़र पेज पर छिपा देते हैं। सरफेस रेंडर होने के लिए फ़ीचर फ़्लैग <code>sentientia.calendar_sync.oauth.enabled</code> भी ON होना चाहिए।';
$string['setting_microsoft_client_id']      = 'Microsoft Azure क्लाइंट ID';
$string['setting_microsoft_client_id_desc'] = 'Azure AD ऐप रजिस्ट्रेशन से Application (client) ID। "Outlook कनेक्ट करें" बटन छिपाने के लिए खाली छोड़ें। नीचे दिखाए गए redirect URI के साथ जोड़ें — Azure को इसे "Authentication → Web → Redirect URIs" के तहत सटीक रूप से सूचीबद्ध करना होगा।';
$string['setting_microsoft_client_secret']  = 'Microsoft Azure क्लाइंट सीक्रेट';
$string['setting_microsoft_client_secret_desc'] = 'Azure ऐप रजिस्ट्रेशन से क्लाइंट सीक्रेट VALUE (ID नहीं)। सीक्रेट के रूप में संभाला जाता है — कभी लॉग नहीं होता, कभी ब्राउज़र पर वापस नहीं भेजा जाता।';
$string['setting_google_client_id']         = 'Google OAuth क्लाइंट ID';
$string['setting_google_client_id_desc']    = 'Google Cloud Console से OAuth 2.0 क्लाइंट ID। "Google Calendar कनेक्ट करें" बटन छिपाने के लिए खाली छोड़ें। नीचे दिखाए गए redirect URI के साथ जोड़ें — Google को इसे "Authorised redirect URIs" के तहत सटीक रूप से सूचीबद्ध करना होगा।';
$string['setting_google_client_secret']     = 'Google OAuth क्लाइंट सीक्रेट';
$string['setting_google_client_secret_desc'] = 'Google Cloud Console OAuth 2.0 क्लाइंट से क्लाइंट सीक्रेट। सीक्रेट के रूप में संभाला जाता है — कभी लॉग नहीं होता, कभी ब्राउज़र पर वापस नहीं भेजा जाता।';
$string['setting_redirect_uri']             = 'OAuth redirect URI';
$string['setting_redirect_uri_desc']        = 'OAuth प्रवाह सफल होने से पहले Microsoft Azure और Google Cloud Console दोनों को इस सटीक URL को एक अधिकृत redirect URI के रूप में सूचीबद्ध करना होगा। केवल-पढ़ने योग्य — <code>$CFG-&gt;wwwroot</code> से व्युत्पन्न।';
$string['setting_scaffolding_notice']       = 'OAuth Phase 2.1 मास्टर फ़ीचर फ़्लैग <code>sentientia.calendar_sync.oauth.enabled</code> (डिफ़ॉल्ट OFF) के पीछे लाइव टोकन एक्सचेंज शिप करता है। नीचे क्रेडेंशियल भरने से इंटीग्रेशन तैयार होता है; फ़्लैग को किसी ग्राहक के लिए ON करने पर सक्रिय हो जाता है। टेस्ट मॉक एंडपॉइंट का उपयोग करते हैं — CI से कोई लाइव OAuth कॉल नहीं चलती।';

// Privacy.
$string['privacy:metadata'] = 'Sentientia LMS कैलेंडर सिंक प्रति-यूज़र एक गुप्त सदस्यता टोकन संग्रहीत करता है। कैलेंडर क्लाइंट इस टोकन से यूज़र की व्यक्तिगत फीड फेच करते हैं। जब Phase 2 OAuth सक्षम होता है, तो यह यूज़र के लिए एन्क्रिप्टेड Microsoft 365 और/या Google Calendar OAuth टोकन भी संग्रहीत करता है। कोई कोर्स कंटेंट या थर्ड-पार्टी डेटा संग्रहीत नहीं होता — केवल क्रेडेंशियल और ऑडिट मेटाडेटा (अंतिम उपयोग समय, IP, गणना)।';
$string['privacy:metadata:token']                = 'प्रत्येक यूज़र को जारी किया गया व्यक्तिगत कैलेंडर सदस्यता टोकन।';
$string['privacy:metadata:token:userid']         = 'टोकन किस यूज़र का है।';
$string['privacy:metadata:token:token']          = '64-वर्ण का रैंडम टोकन (कार्यात्मक रूप से क्रेडेंशियल)।';
$string['privacy:metadata:token:last_used_at']   = 'टोकन का अंतिम बार कैलेंडर फीड फेच में उपयोग कब हुआ।';
$string['privacy:metadata:token:last_used_ip']   = 'अंतिम सफल फेच का IP पता (दुरुपयोग फॉरेंसिक्स के लिए)।';
$string['privacy:metadata:token:use_count']      = 'कुल सफल फेच गणना।';
$string['privacy:metadata:token:timecreated']    = 'टोकन पहली बार कब जारी हुआ।';
$string['privacy:metadata:token:timemodified']   = 'टोकन अंतिम बार कब संशोधित (पुनः जनरेट या रद्द) हुआ।';
$string['privacy:metadata:oauth']                    = 'Microsoft 365 या Google Calendar के लिए OAuth एक्सेस + रिफ्रेश टोकन — Moodle के Sodium-समर्थित एन्क्रिप्शन हेल्पर के माध्यम से रेस्ट पर एन्क्रिप्टेड। प्रति (यूज़र, प्रोवाइडर) एक पंक्ति।';
$string['privacy:metadata:oauth:userid']             = 'वह यूज़र जिसके कैलेंडर को OAuth टोकन LMS को पढ़ने और लिखने का प्राधिकरण देते हैं।';
$string['privacy:metadata:oauth:customerid']         = 'वह Sentientia LMS ग्राहक जिससे यूज़र संबंधित है।';
$string['privacy:metadata:oauth:provider']           = 'टोकन किस प्रोवाइडर के हैं — m365 (Microsoft Graph) या google (Google Calendar API)।';
$string['privacy:metadata:oauth:access_token_enc']   = 'एन्क्रिप्टेड शॉर्ट-लिव्ड एक्सेस टोकन (आमतौर पर 1 घंटे की वैधता)।';
$string['privacy:metadata:oauth:refresh_token_enc']  = 'यूज़र को प्रॉम्प्ट किए बिना नए एक्सेस टोकन जारी करने के लिए उपयोग किया जाने वाला एन्क्रिप्टेड लॉन्ग-लिव्ड रिफ्रेश टोकन।';
$string['privacy:metadata:oauth:expires']            = 'वह यूनिक्स टाइमस्टैम्प जिस पर एक्सेस टोकन समाप्त होता है।';
$string['privacy:metadata:oauth:scopes']             = 'वे OAuth स्कोप जो प्रोवाइडर ने सहमति के समय प्रदान किए।';
$string['privacy:metadata:oauth:timecreated']        = 'OAuth टोकन पहली बार कब जारी हुए (प्रारंभिक सहमति)।';
$string['privacy:metadata:oauth:timemodified']       = 'OAuth टोकन अंतिम बार कब रिफ्रेश या पुनः सहमति प्राप्त हुए।';
$string['privacy:metadata:microsoft_graph']          = 'जब OAuth फ़ीचर फ़्लैग सक्षम हो और यूज़र ने सहमति दी हो, Sentientia LMS यूज़र की ओर से Microsoft Graph के माध्यम से कैलेंडर इवेंट पढ़ता और लिखता है। जब तक यूज़र ऑप्ट-इन नहीं करता तब तक कोई डेटा नहीं भेजा जाता।';
$string['privacy:metadata:microsoft_graph:userid']   = 'वह यूज़र जिसके कैलेंडर इवेंट पढ़े या लिखे जाते हैं।';
$string['privacy:metadata:google_calendar']          = 'जब OAuth फ़ीचर फ़्लैग सक्षम हो और यूज़र ने सहमति दी हो, Sentientia LMS यूज़र की ओर से Google Calendar API के माध्यम से कैलेंडर इवेंट पढ़ता और लिखता है। जब तक यूज़र ऑप्ट-इन नहीं करता तब तक कोई डेटा नहीं भेजा जाता।';
$string['privacy:metadata:google_calendar:userid']   = 'वह यूज़र जिसके कैलेंडर इवेंट पढ़े या लिखे जाते हैं।';
