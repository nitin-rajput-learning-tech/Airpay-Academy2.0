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
 * Marathi strings for local_sentientia_integrations.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_integrations
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia इंटिग्रेशन्स हब';
$string['settings_heading'] = 'Airpay इंटिग्रेशन्स कॉन्फिगरेशन';
$string['settings_desc'] = 'Airpay Academy साठी बाहेरची इंटिग्रेशन्स कॉन्फिगर करा. प्रत्येक फीचर डीफॉल्टनुसार बंद असते — प्रत्येक फीचर स्वतंत्रपणे सुरू करा आणि कॉन्फिगर करा.';
$string['ai_heading'] = 'AI फीचर्स';
$string['ai_enable'] = 'AI फीचर्स सुरू करा';
$string['ai_enable_desc'] = 'सर्व AI फीचर्ससाठी मास्टर टॉगल. साइट ॲडमिन → AI मध्ये AI प्रोव्हायडर कॉन्फिगर केलेला असणे आवश्यक आहे.';
$string['ai_recommendations_enable'] = 'AI कोर्स शिफारसी सुरू करा';
$string['ai_recommendations_desc'] = 'कम्प्लीशन हिस्ट्री आणि स्किल गॅप्सच्या आधारे लर्नर डॅशबोर्डवर वैयक्तिक कोर्स शिफारसी दाखवा.';
$string['ai_quiz_enable'] = 'AI क्विझ जनरेशन सुरू करा';
$string['ai_quiz_desc'] = 'L&D ॲडमिन्सना AI वापरून कोर्स कंटेंटमधून क्विझ प्रश्न तयार करू द्या.';
$string['sentientia_heading'] = 'SENTIENTIA कंटेंट पाइपलाइन';
$string['sentientia_enable'] = 'SENTIENTIA पाइपलाइन सुरू करा';
$string['sentientia_desc'] = 'SOP → SCORM ऑटोमेशन पाइपलाइन. व्हॉइस जनरेशनसाठी ElevenLabs API की आवश्यक आहे.';
$string['elevenlabs_apikey'] = 'ElevenLabs API की';
$string['elevenlabs_apikey_desc'] = 'व्हॉइस जनरेशनसाठी elevenlabs.io वरून मिळालेली API की (एजंट 4).';
$string['elevenlabs_voiceid'] = 'ElevenLabs व्हॉइस ID';
$string['elevenlabs_voiceid_desc'] = 'नरेशन तयार करण्यासाठी वापरायचा व्हॉइस ID.';
$string['m365_heading'] = 'Microsoft 365 इंटिग्रेशन';
$string['m365_enable'] = 'Microsoft 365 SSO सुरू करा';
$string['m365_desc'] = 'Azure AD सिंगल साइन-ऑन. OIDC प्लगइन कॉन्फिगर केलेले असणे आणि Azure ॲप रजिस्ट्रेशन असणे आवश्यक आहे.';
$string['m365_tenant_id'] = 'Azure टेनंट ID';
$string['m365_tenant_id_desc'] = 'Azure Portal → App registrations → Directory (tenant) ID मधून घ्या.';
$string['m365_client_id'] = 'Azure क्लायंट ID';
$string['m365_client_id_desc'] = 'Azure ॲप रजिस्ट्रेशनमधील Application (client) ID.';
$string['m365_client_secret'] = 'Azure क्लायंट सीक्रेट';
$string['m365_client_secret_desc'] = 'क्लायंट सीक्रेट व्हॅल्यू (दर 24 महिन्यांनी बदलते).';
$string['teams_heading'] = 'Microsoft Teams नोटिफिकेशन्स';
$string['teams_enable'] = 'Teams नोटिफिकेशन्स सुरू करा';
$string['teams_desc'] = 'लर्निंग इव्हेंट्स (एनरोलमेंट, डेडलाइन, कम्प्लीशन) वेबहुकद्वारे Teams चॅनेल्सना पाठवा.';
$string['teams_webhook_url'] = 'Teams वेबहुक URL';
$string['teams_webhook_url_desc'] = 'टार्गेट Teams चॅनेलचा इनकमिंग वेबहुक URL.';
$string['teams_events'] = 'कळवायचे इव्हेंट्स';
$string['teams_events_desc'] = 'कोणते इव्हेंट्स Teams नोटिफिकेशन्स ट्रिगर करतात.';
$string['hrms_heading'] = 'HRMS सिंक';
$string['hrms_enable'] = 'HRMS सिंक सुरू करा';
$string['hrms_desc'] = 'REST API द्वारे Keka किंवा इतर HRMS मधून कर्मचाऱ्यांचा रिअल-टाइम सिंक.';
$string['hrms_api_url'] = 'HRMS API एंडपॉइंट';
$string['hrms_api_url_desc'] = 'HRMS API चा बेस URL (उदा., https://api.keka.com/v1/).';
$string['hrms_api_key'] = 'HRMS API की';
$string['hrms_api_key_desc'] = 'HRMS API साठी ऑथेंटिकेशन की.';
$string['hrms_sync_interval'] = 'सिंक इंटरव्हल (तास)';
$string['hrms_sync_interval_desc'] = 'कर्मचाऱ्यांचे अपडेट्स किती अंतराने पुल करायचे. डीफॉल्ट: 4 तास.';
$string['keka_heading'] = 'KeKa HRMS इंटिग्रेशन';
$string['keka_heading_desc'] = 'KeKa REST API + वेबहुक्सद्वारे जॉइनर-मूव्हर-लीव्हर ऑटोमेशन. वेबहुक एंडपॉइंट आणि रिकन्सिलिएशन टास्क यांच्यावर sentientia.hrms.* फीचर फ्लॅग्स (डीफॉल्ट बंद) आणि वरील "HRMS सिंक सुरू करा" टॉगल यांचेही नियंत्रण आहे.';
$string['keka_base_url'] = 'KeKa API बेस URL';
$string['keka_base_url_desc'] = 'उदा., https://airpay.keka.com';
$string['keka_api_key'] = 'KeKa API की';
$string['keka_api_key_desc'] = 'KeKa Admin > Integrations मधून जनरेट करा.';
$string['keka_client_id'] = 'KeKa OAuth क्लायंट ID';
$string['keka_client_id_desc'] = 'API की ला पर्याय.';
$string['keka_client_secret'] = 'KeKa OAuth क्लायंट सीक्रेट';
$string['keka_client_secret_desc'] = 'API की सेट नसताना OAuth क्लायंट ID सोबत वापरले जाते.';
$string['webhook_secret'] = 'वेबहुक सीक्रेट';
$string['webhook_secret_desc'] = 'KeKa वेबहुक कॉन्फिगरेशनमध्ये हीच व्हॅल्यू सेट करा. KeKa ने ती X-Webhook-Secret HTTP हेडरमध्ये पाठवली पाहिजे — ?secret= क्वेरी पॅरामीटर स्वीकारला जात नाही (तो ॲक्सेस लॉग्समध्ये लीक होईल). एंडपॉइंट: /local/sentientia_integrations/webhook.php';
$string['keka_default_orgpath'] = 'नवीन युजर्ससाठी डीफॉल्ट ऑर्ग पाथ';
$string['keka_default_orgpath_desc'] = 'वेबहुकद्वारे तयार झालेल्या युजर्सचा KeKa विभाग कोणत्याही संस्थेशी मॅप होत नसेल, तर त्यांना ज्या ऑर्ग पाथखाली ठेवले जाते तो पाथ (उदा. /1). हा पाथ ऑर्ग ट्रीनुसार व्हॅलिडेट केला जातो; चुकीच्या व्हॅल्यूजसाठी /1 वापरला जातो.';
$string['task_keka_reconcile'] = 'KeKa HRMS रिकन्सिलिएशन पुल';
$string['gamification_heading'] = 'गेमिफिकेशन';
$string['gamification_enable'] = 'गेमिफिकेशन सुरू करा';
$string['gamification_desc'] = 'XP पॉइंट्स, लीडरबोर्ड्स आणि लर्निंग स्ट्रीक्स. block_xp प्लगइन इन्स्टॉल असणे आवश्यक आहे.';
$string['gamification_xp_per_completion'] = 'प्रति कोर्स कम्प्लीशन XP';
$string['gamification_xp_per_completion_desc'] = 'लर्नरने कोर्स पूर्ण केल्यावर मिळणारे XP.';
$string['gamification_leaderboard_enable'] = 'विभागनिहाय लीडरबोर्ड्स सुरू करा';
$string['gamification_leaderboard_desc'] = 'कॉस्टसेंटर/विभागानुसार फिल्टर केलेले लीडरबोर्ड्स दाखवा.';
$string['privacy:metadata'] = 'Airpay sentientia_integrations प्लगइन स्वतःच्या टेबल्समध्ये वैयक्तिक डेटा सेव्ह करत नाही; इतर युजर डेटा मुख्य Sentientia LMS टेबल्समध्ये असतो, जो त्यांच्या संबंधित प्रोव्हायडर्सद्वारे एक्सपोर्ट केला जातो.';
