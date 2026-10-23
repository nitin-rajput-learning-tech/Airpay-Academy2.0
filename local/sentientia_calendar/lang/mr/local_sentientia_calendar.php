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
 * Marathi strings for local_sentientia_calendar.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * awaiting review by a native speaker.
 *
 * @package    local_sentientia_calendar
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — कॅलेंडर सिंक';
$string['nav_label'] = 'कॅलेंडर सबस्क्रिप्शन';
$string['page_title'] = 'कॅलेंडर सबस्क्रिप्शन';
$string['page_heading'] = 'तुमच्या लर्निंग कॅलेंडरला सबस्क्राइब करा';
$string['page_intro'] = 'तुमच्या Sentientia LMS कोर्सच्या डेडलाइन, क्लासरूम सेशन आणि परीक्षांच्या तारखा Outlook, Google Calendar किंवा Apple Calendar मध्ये जोडा. खालची लिंक फक्त तुमच्यासाठी आहे — ती खाजगी ठेवा.';
$string['events_heading'] = 'यात काय काय आहे';
$string['events_courses'] = 'तुम्ही एनरोल असलेल्या प्रत्येक कोर्सच्या कम्प्लिशन डेडलाइन';
$string['events_classroom'] = 'क्लासरूम (इन्स्ट्रक्टर-लेड ट्रेनिंग) सेशन सुरू होण्याच्या आणि संपण्याच्या वेळा';
$string['events_exams'] = 'पुढील 90 दिवसांतील परीक्षा बंद होण्याच्या तारखा';
$string['copy_label'] = 'सबस्क्रिप्शन URL कॉपी करा';
$string['copied_label'] = 'कॉपी झाले!';
$string['security_note'] = 'हा URL पासवर्डसारखा जपा — ज्याच्याकडे तो असेल तो तुमचे लर्निंग कॅलेंडर पाहू शकतो. चुकून कुठे पेस्ट झाला तर खालील "रिजनरेट" वापरा.';
$string['regenerate_label'] = 'URL रिजनरेट करा';
$string['regenerate_help'] = 'सध्याचा URL रद्द करून नवीन URL देतो. तुम्ही आधीच जोडलेल्या प्रत्येक कॅलेंडर क्लायंटमध्ये सबस्क्रिप्शन अपडेट करावे लागेल.';
$string['regenerate_success'] = 'सबस्क्रिप्शन URL रिजनरेट झाला. जुनी लिंक आता चालणार नाही.';
$string['how_to_heading'] = 'सबस्क्राइब कसे करायचे';
$string['how_to_outlook'] = 'वेबवरील Outlook: Calendar ▶ Add calendar ▶ Subscribe from web ▶ URL पेस्ट करा ▶ त्याला "Sentientia" नाव द्या ▶ Import. डेस्कटॉप Outlook: File ▶ Account Settings ▶ Internet Calendars ▶ New ▶ URL पेस्ट करा.';
$string['how_to_google'] = 'Google Calendar: Other calendars ▶ + ▶ From URL ▶ URL पेस्ट करा ▶ Add calendar.';
$string['how_to_apple'] = 'Apple Calendar (macOS): File ▶ New Calendar Subscription ▶ URL पेस्ट करा ▶ Subscribe. iOS: Settings ▶ Calendar ▶ Accounts ▶ Add Account ▶ Other ▶ Add Subscribed Calendar.';
$string['oauth_heading'] = 'टू-वे कॅलेंडर सिंक (बीटा)';
$string['oauth_intro'] = 'तुमचे Outlook किंवा Google कॅलेंडर कनेक्ट करा — Sentientia LMS च्या डेडलाइन थेट त्यात जातील आणि तुमचे उत्तर (ॲक्सेप्ट / डिक्लाइन) आपोआप Sentientia कडे परत येतील.';
$string['oauth_provider_m365'] = 'Microsoft 365 (Outlook)';
$string['oauth_provider_desc_m365'] = 'तुमच्या Outlook कॅलेंडरमधील इव्हेंट वाचणे + लिहिणे. आम्ही फक्त LMS ने स्वतः जोडलेल्या इव्हेंटनाच हात लावतो.';
$string['oauth_provider_google'] = 'Google Calendar';
$string['oauth_provider_desc_google'] = 'तुमच्या Google कॅलेंडरमध्ये LMS तयार करत असलेले इव्हेंट वाचणे + लिहिणे. तुमचे वैयक्तिक इव्हेंट आम्हाला कधीच दिसत नाहीत.';
$string['oauth_connect_m365'] = 'Outlook कनेक्ट करा';
$string['oauth_connect_google'] = 'Google Calendar कनेक्ट करा';
$string['oauth_reconnect'] = 'पुन्हा कनेक्ट करा';
$string['oauth_disconnect'] = 'डिस्कनेक्ट करा';
$string['oauth_status_connected'] = 'कनेक्टेड ({$a->date} ला मुदत संपेल)';
$string['oauth_status_expired'] = 'मुदत संपली — कृपया पुन्हा कनेक्ट करा';
$string['oauth_status_disconnected'] = 'कनेक्ट केलेले नाही';
$string['connect_success_m365'] = 'Microsoft 365 कॅलेंडर कनेक्ट झाले — तुमच्या Sentientia डेडलाइन लवकरच सिंक होऊ लागतील.';
$string['connect_success_google'] = 'Google Calendar कनेक्ट झाले — तुमच्या Sentientia डेडलाइन लवकरच सिंक होऊ लागतील.';
$string['disconnect_success_m365'] = 'Microsoft 365 कॅलेंडर डिस्कनेक्ट झाले. आधीच पाठवलेले इव्हेंट तुमच्या कॅलेंडरमध्ये तसेच राहतील.';
$string['disconnect_success_google'] = 'Google Calendar डिस्कनेक्ट झाले. आधीच पाठवलेले इव्हेंट तुमच्या कॅलेंडरमध्ये तसेच राहतील.';
$string['connect_error'] = 'कॅलेंडर प्रोव्हायडरने कनेक्शन नाकारले ({$a->code}). {$a->description}';
$string['error_flag_off'] = 'तुमच्या अकाउंटसाठी सध्या कॅलेंडर सिंक सुरू केलेले नाही. तुमच्या ॲडमिनशी संपर्क साधा.';
$string['error_token_collision'] = 'अनेकदा प्रयत्न करूनही युनिक कॅलेंडर टोकन तयार करता आले नाही. कृपया पुन्हा प्रयत्न करा.';
$string['error_oauth_clientid_missing'] = 'या प्रोव्हायडरसाठी OAuth क्लायंट ID कॉन्फिगर केलेला नाही. ॲडमिनला ॲप रजिस्टर करून Site administration → Plugins → Local plugins → Sentientia Calendar Sync इथे क्लायंट ID जोडायला सांगा.';
$string['error_oauth_state_invalid'] = 'OAuth state जुळत नाही — ही रिक्वेस्ट कोणत्याही प्रलंबित ऑथरायझेशनशी जुळली नाही. कृपया कनेक्ट प्रक्रिया पुन्हा सुरू करा.';
$string['error_oauth_code_missing'] = 'OAuth कॉलबॅकमध्ये ऑथरायझेशन कोड आला नाही. प्रोव्हायडरने संमती नाकारली असू शकते. कृपया पुन्हा प्रयत्न करा.';
$string['error_oauth_no_refresh_token'] = 'या प्रोव्हायडरसाठी कोणतेही रिफ्रेश टोकन सेव्ह केलेले नाही. नवीन टोकन मिळवण्यासाठी प्रोव्हायडरशी पुन्हा कनेक्ट करा.';
$string['error_oauth_token_response'] = 'कॅलेंडर प्रोव्हायडरकडून अनपेक्षित रिस्पॉन्स आला ({$a}). कृपया पुन्हा प्रयत्न करा किंवा तुमच्या ॲडमिनशी संपर्क साधा.';
$string['error_oauth_invalid_grant'] = 'तुमच्या कॅलेंडर प्रोव्हायडरने आमचा ॲक्सेस काढून घेतला आहे. नवीन संमती देण्यासाठी कृपया पुन्हा कनेक्ट करा.';
$string['error_oauth_http_failure'] = 'कॅलेंडर प्रोव्हायडरशी संपर्क होऊ शकला नाही ({$a}). नेटवर्क किंवा प्रोव्हायडर डाउन असू शकतो — कृपया काही मिनिटांनी पुन्हा प्रयत्न करा.';
$string['error_oauth_unknown_provider'] = 'अज्ञात OAuth प्रोव्हायडर: {$a}.';
$string['oauth_not_live'] = 'OAuth फेज 2 सध्या फक्त स्कॅफोल्डिंग आहे. प्रत्येक कस्टमरसाठी रोलआउट निश्चित झाल्यावर पुढच्या रिलीजमध्ये लाइव्ह टोकन एक्सचेंज सुरू होईल.';
$string['task_purge_old_tokens'] = 'Sentientia Calendar — रद्द केलेले टोकन काढून टाका';
$string['sentientia_calendar:subscribe'] = 'स्वतःचा कॅलेंडर सबस्क्रिप्शन URL मॅनेज करा';
$string['sentientia_calendar:manage_all'] = 'कोणत्याही युजरचे कॅलेंडर सबस्क्रिप्शन टोकन मॅनेज करा';
$string['settings_pagetitle'] = 'Sentientia कॅलेंडर सिंक';
$string['settings_section_oauth'] = 'OAuth — Microsoft 365 आणि Google Calendar';
$string['settings_section_oauth_desc'] = 'दोन्ही बाजूंच्या सिंकसाठी क्लायंट ID आणि सीक्रेट. रिकामे ठेवल्यास युजरच्या पेजवरील संबंधित "कनेक्ट…" बटण बंद होते. हे भाग दिसण्यासाठी <code>sentientia.calendar_sync.oauth.enabled</code> हा फीचर फ्लॅगदेखील ON असणे आवश्यक आहे.';
$string['setting_microsoft_client_id'] = 'Microsoft Azure क्लायंट ID';
$string['setting_microsoft_client_id_desc'] = 'Azure AD ॲप रजिस्ट्रेशनमधील Application (client) ID. "Outlook कनेक्ट करा" बटण लपवण्यासाठी रिकामे ठेवा. खाली दाखवलेल्या रिडायरेक्ट URI सोबत हे वापरा — Azure मध्ये तो "Authentication → Web → Redirect URIs" खाली अगदी जसाच्या तसा असला पाहिजे.';
$string['setting_microsoft_client_secret'] = 'Microsoft Azure क्लायंट सीक्रेट';
$string['setting_microsoft_client_secret_desc'] = 'Azure ॲप रजिस्ट्रेशनमधील क्लायंट सीक्रेटचे VALUE (ID नाही). हे सीक्रेट म्हणूनच हाताळले जाते — कधीही लॉग होत नाही, ब्राउझरला परत दाखवले जात नाही.';
$string['setting_google_client_id'] = 'Google OAuth क्लायंट ID';
$string['setting_google_client_id_desc'] = 'Google Cloud Console मधील OAuth 2.0 क्लायंट ID. "Google Calendar कनेक्ट करा" बटण लपवण्यासाठी रिकामे ठेवा. खाली दाखवलेल्या रिडायरेक्ट URI सोबत हे वापरा — Google मध्ये तो "Authorised redirect URIs" खाली अगदी जसाच्या तसा असला पाहिजे.';
$string['setting_google_client_secret'] = 'Google OAuth क्लायंट सीक्रेट';
$string['setting_google_client_secret_desc'] = 'Google Cloud Console मधील OAuth 2.0 क्लायंटचे क्लायंट सीक्रेट. हे सीक्रेट म्हणूनच हाताळले जाते — कधीही लॉग होत नाही, ब्राउझरला परत दाखवले जात नाही.';
$string['setting_redirect_uri'] = 'OAuth रिडायरेक्ट URI';
$string['setting_redirect_uri_desc'] = 'OAuth प्रक्रिया यशस्वी होण्यासाठी Microsoft Azure आणि Google Cloud Console या दोन्हींमध्ये हाच URL अधिकृत रिडायरेक्ट URI म्हणून नोंदवलेला असला पाहिजे. फक्त वाचण्यासाठी — <code>$CFG-&gt;wwwroot</code> वरून घेतलेला.';
$string['setting_scaffolding_notice'] = 'OAuth फेज 2.1 मध्ये लाइव्ह टोकन एक्सचेंज मास्टर फीचर फ्लॅग <code>sentientia.calendar_sync.oauth.enabled</code> (डिफॉल्ट OFF) च्या मागे दिले आहे. खालील क्रेडेन्शियल भरल्यावर इंटिग्रेशन तयार होते; एखाद्या कस्टमरसाठी फ्लॅग ON केल्यावर ते ॲक्टिव्हेट होते. टेस्ट मॉक एंडपॉइंट वापरतात — CI मधून कोणतेही लाइव्ह OAuth कॉल चालत नाहीत.';
$string['privacy:metadata'] = 'Sentientia LMS कॅलेंडर सिंक प्रत्येक युजरसाठी एक सीक्रेट सबस्क्रिप्शन टोकन साठवते. कॅलेंडर क्लायंट हे टोकन वापरून युजरचे वैयक्तिक फीड मिळवतात. फेज 2 OAuth सुरू असल्यास, ते युजरसाठी एन्क्रिप्ट केलेले Microsoft 365 आणि/किंवा Google Calendar OAuth टोकनदेखील साठवते. कोणताही कोर्स कंटेंट किंवा थर्ड-पार्टी डेटा साठवला जात नाही — फक्त क्रेडेन्शियल आणि ऑडिट मेटाडेटा (शेवटच्या वापराची वेळ, IP, संख्या).';
$string['privacy:metadata:token'] = 'प्रत्येक युजरला दिलेले वैयक्तिक कॅलेंडर सबस्क्रिप्शन टोकन.';
$string['privacy:metadata:token:userid'] = 'हे टोकन ज्या युजरचे आहे तो युजर.';
$string['privacy:metadata:token:token'] = '64-अक्षरी रँडम टोकन (प्रत्यक्षात एक क्रेडेन्शियल).';
$string['privacy:metadata:token:last_used_at'] = 'कॅलेंडर फीड मिळवण्यासाठी टोकन शेवटचे कधी वापरले गेले.';
$string['privacy:metadata:token:last_used_ip'] = 'शेवटच्या यशस्वी फेचचा IP ॲड्रेस (गैरवापराच्या तपासासाठी).';
$string['privacy:metadata:token:use_count'] = 'एकूण यशस्वी फेचची संख्या.';
$string['privacy:metadata:token:timecreated'] = 'टोकन पहिल्यांदा कधी दिले गेले.';
$string['privacy:metadata:token:timemodified'] = 'टोकन शेवटचे कधी बदलले गेले (रिजनरेट किंवा रद्द).';
$string['privacy:metadata:oauth'] = 'Microsoft 365 किंवा Google Calendar साठी OAuth ॲक्सेस + रिफ्रेश टोकन — Moodle च्या Sodium-आधारित एन्क्रिप्शन हेल्परद्वारे साठवताना एन्क्रिप्ट केलेले. प्रत्येक (युजर, प्रोव्हायडर) साठी एक रो.';
$string['privacy:metadata:oauth:userid'] = 'ज्या युजरचे कॅलेंडर वाचण्याची आणि लिहिण्याची परवानगी OAuth टोकन LMS ला देतात तो युजर.';
$string['privacy:metadata:oauth:customerid'] = 'युजर ज्या Sentientia LMS कस्टमरचा आहे तो कस्टमर.';
$string['privacy:metadata:oauth:provider'] = 'टोकन कोणत्या प्रोव्हायडरचे आहेत — m365 (Microsoft Graph) किंवा google (Google Calendar API).';
$string['privacy:metadata:oauth:access_token_enc'] = 'एन्क्रिप्ट केलेले कमी कालावधीचे ॲक्सेस टोकन (साधारणपणे 1 तास वैध).';
$string['privacy:metadata:oauth:refresh_token_enc'] = 'युजरला पुन्हा न विचारता नवीन ॲक्सेस टोकन मिळवण्यासाठी वापरले जाणारे एन्क्रिप्ट केलेले दीर्घ कालावधीचे रिफ्रेश टोकन.';
$string['privacy:metadata:oauth:expires'] = 'ॲक्सेस टोकनची मुदत संपण्याचा Unix टाइमस्टॅम्प.';
$string['privacy:metadata:oauth:scopes'] = 'संमती देताना प्रोव्हायडरने दिलेले OAuth स्कोप.';
$string['privacy:metadata:oauth:timecreated'] = 'OAuth टोकन पहिल्यांदा कधी दिले गेले (पहिली संमती).';
$string['privacy:metadata:oauth:timemodified'] = 'OAuth टोकन शेवटचे कधी रिफ्रेश झाले किंवा पुन्हा संमती दिली गेली.';
$string['privacy:metadata:microsoft_graph'] = 'OAuth फीचर फ्लॅग सुरू असल्यास आणि युजरने संमती दिली असल्यास, Sentientia LMS युजरच्या वतीने Microsoft Graph द्वारे कॅलेंडर इव्हेंट वाचते आणि लिहिते. युजर ऑप्ट-इन करेपर्यंत कोणताही डेटा पाठवला जात नाही.';
$string['privacy:metadata:microsoft_graph:userid'] = 'ज्या युजरचे कॅलेंडर इव्हेंट वाचले किंवा लिहिले जातात तो युजर.';
$string['privacy:metadata:google_calendar'] = 'OAuth फीचर फ्लॅग सुरू असल्यास आणि युजरने संमती दिली असल्यास, Sentientia LMS युजरच्या वतीने Google Calendar API द्वारे कॅलेंडर इव्हेंट वाचते आणि लिहिते. युजर ऑप्ट-इन करेपर्यंत कोणताही डेटा पाठवला जात नाही.';
$string['privacy:metadata:google_calendar:userid'] = 'ज्या युजरचे कॅलेंडर इव्हेंट वाचले किंवा लिहिले जातात तो युजर.';
