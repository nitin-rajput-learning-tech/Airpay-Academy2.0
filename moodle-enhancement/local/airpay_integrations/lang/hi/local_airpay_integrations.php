<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #53 (2026-05-20) — Hindi (hi) translations for local_airpay_integrations.
// Scope: settings hub covering AI features, SENTIENTIA pipeline,
// Microsoft 365 SSO, Teams notifications, HRMS sync, gamification.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे इंटीग्रेशंस हब';

// Settings page.
$string['settings_heading'] = 'एयरपे इंटीग्रेशंस कॉन्फ़िगरेशन';
$string['settings_desc']    = 'एयरपे अकैडमी के लिए बाहरी इंटीग्रेशंस कॉन्फ़िगर करें। प्रत्येक फ़ीचर डिफ़ॉल्ट रूप से अक्षम है — व्यक्तिगत रूप से सक्षम और कॉन्फ़िगर करें।';

// AI features.
$string['ai_heading']                = 'AI फ़ीचर (Phase 9)';
$string['ai_enable']                 = 'AI फ़ीचर सक्षम करें';
$string['ai_enable_desc']            = 'सभी AI फ़ीचर के लिए मास्टर टॉगल। साइट एडमिन → AI में AI प्रदाता कॉन्फ़िगर होना चाहिए।';
$string['ai_recommendations_enable'] = 'AI कोर्स अनुशंसाएँ सक्षम करें';
$string['ai_recommendations_desc']   = 'पूर्णता इतिहास और स्किल गैप के आधार पर शिक्षार्थी डैशबोर्ड पर वैयक्तिकृत कोर्स अनुशंसाएँ दिखाएँ।';
$string['ai_quiz_enable']            = 'AI क्विज़ जनरेशन सक्षम करें';
$string['ai_quiz_desc']              = 'L&D एडमिन को AI का उपयोग करके कोर्स सामग्री से क्विज़ प्रश्न उत्पन्न करने की अनुमति दें।';

// SENTIENTIA.
$string['sentientia_heading']   = 'SENTIENTIA सामग्री पाइपलाइन (Phase 9)';
$string['sentientia_enable']    = 'SENTIENTIA पाइपलाइन सक्षम करें';
$string['sentientia_desc']      = 'SOP → SCORM ऑटोमेशन पाइपलाइन। वॉइस जनरेशन के लिए ElevenLabs API कुंजी आवश्यक।';
$string['elevenlabs_apikey']    = 'ElevenLabs API कुंजी';
$string['elevenlabs_apikey_desc'] = 'वॉइस जनरेशन (Agent 4) के लिए elevenlabs.io से API कुंजी।';
$string['elevenlabs_voiceid']     = 'ElevenLabs वॉइस ID';
$string['elevenlabs_voiceid_desc'] = 'नैरेशन जनरेशन के लिए उपयोग करने वाली वॉइस ID।';

// Microsoft 365.
$string['m365_heading']           = 'Microsoft 365 इंटीग्रेशन (Phase 10)';
$string['m365_enable']            = 'Microsoft 365 SSO सक्षम करें';
$string['m365_desc']              = 'Azure AD सिंगल साइन-ऑन। OIDC प्लगइन कॉन्फ़िगर और Azure ऐप पंजीकरण आवश्यक।';
$string['m365_tenant_id']         = 'Azure टेनेंट ID';
$string['m365_tenant_id_desc']    = 'Azure पोर्टल → ऐप पंजीकरण → डायरेक्टरी (टेनेंट) ID से।';
$string['m365_client_id']         = 'Azure क्लाइंट ID';
$string['m365_client_id_desc']    = 'Azure ऐप पंजीकरण से एप्लिकेशन (क्लाइंट) ID।';
$string['m365_client_secret']     = 'Azure क्लाइंट सीक्रेट';
$string['m365_client_secret_desc'] = 'क्लाइंट सीक्रेट मान (हर 24 महीने में घूमता है)।';

// Teams.
$string['teams_heading']         = 'Microsoft Teams सूचनाएँ (Phase 10)';
$string['teams_enable']          = 'Teams सूचनाएँ सक्षम करें';
$string['teams_desc']            = 'वेबहुक के माध्यम से Teams चैनलों पर लर्निंग इवेंट्स (नामांकन, डेडलाइन, पूर्णता) भेजें।';
$string['teams_webhook_url']     = 'Teams वेबहुक URL';
$string['teams_webhook_url_desc'] = 'लक्षित Teams चैनल के लिए इनकमिंग वेबहुक URL।';
$string['teams_events']           = 'सूचित करने वाले इवेंट्स';
$string['teams_events_desc']      = 'कौन से इवेंट्स Teams सूचनाएँ ट्रिगर करते हैं।';

// HRMS.
$string['hrms_heading']          = 'HRMS सिंक (Phase 10)';
$string['hrms_enable']           = 'HRMS सिंक सक्षम करें';
$string['hrms_desc']             = 'REST API के माध्यम से Keka या अन्य HRMS से रियल-टाइम कर्मचारी सिंक।';
$string['hrms_api_url']          = 'HRMS API एंडपॉइंट';
$string['hrms_api_url_desc']     = 'HRMS API का बेस URL (जैसे, https://api.keka.com/v1/)।';
$string['hrms_api_key']          = 'HRMS API कुंजी';
$string['hrms_api_key_desc']     = 'HRMS API के लिए प्रमाणीकरण कुंजी।';
$string['hrms_sync_interval']    = 'सिंक अंतराल (घंटे)';
$string['hrms_sync_interval_desc'] = 'कर्मचारी अपडेट कितनी बार खींचना है। डिफ़ॉल्ट: 4 घंटे।';

// Gamification.
$string['gamification_heading']                = 'गेमिफ़िकेशन (Phase 11)';
$string['gamification_enable']                 = 'गेमिफ़िकेशन सक्षम करें';
$string['gamification_desc']                   = 'XP अंक, लीडरबोर्ड और लर्निंग स्ट्रीक। block_xp प्लगइन इंस्टॉल होना चाहिए।';
$string['gamification_xp_per_completion']      = 'प्रति कोर्स पूर्णता XP';
$string['gamification_xp_per_completion_desc'] = 'जब शिक्षार्थी कोर्स पूर्ण करता है तो दिए जाने वाले XP।';
$string['gamification_leaderboard_enable']     = 'विभाग लीडरबोर्ड सक्षम करें';
$string['gamification_leaderboard_desc']       = 'costcenter/विभाग द्वारा फ़िल्टर्ड लीडरबोर्ड दिखाएँ।';

// Privacy.
$string['privacy:metadata'] = 'एयरपे इंटीग्रेशंस प्लगइन प्लगइन-स्वामित्व वाली तालिकाओं में व्यक्तिगत डेटा संग्रहीत नहीं करता है; यूज़र स्थिति संबंधित प्रदाताओं द्वारा निर्यात की गई कोर प्लेटफ़ॉर्म तालिकाओं पर रहती है।';
