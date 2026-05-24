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

// Errors.
$string['error_flag_off']        = 'आपके खाते के लिए कैलेंडर सिंक वर्तमान में सक्षम नहीं है। अपने एडमिनिस्ट्रेटर से संपर्क करें।';
$string['error_token_collision'] = 'कई प्रयासों के बाद भी एक अद्वितीय कैलेंडर टोकन जनरेट नहीं हो सका। कृपया पुनः प्रयास करें।';

// Scheduled tasks.
$string['task_purge_old_tokens'] = 'Sentientia कैलेंडर — रद्द किए गए टोकन हटाएँ';

// Capabilities.
$string['sentientia_calendar:subscribe']  = 'अपना कैलेंडर सदस्यता URL प्रबंधित करें';
$string['sentientia_calendar:manage_all'] = 'किसी भी यूज़र के कैलेंडर सदस्यता टोकन प्रबंधित करें';

// Privacy.
$string['privacy:metadata'] = 'Sentientia LMS कैलेंडर सिंक प्रति-यूज़र एक गुप्त सदस्यता टोकन संग्रहीत करता है। कैलेंडर क्लाइंट इस टोकन से यूज़र की व्यक्तिगत फीड फेच करते हैं। कोई कोर्स कंटेंट या थर्ड-पार्टी डेटा संग्रहीत नहीं होता — केवल टोकन और ऑडिट मेटाडेटा (अंतिम उपयोग समय, IP, गणना)।';
$string['privacy:metadata:token']                = 'प्रत्येक यूज़र को जारी किया गया व्यक्तिगत कैलेंडर सदस्यता टोकन।';
$string['privacy:metadata:token:userid']         = 'टोकन किस यूज़र का है।';
$string['privacy:metadata:token:token']          = '64-वर्ण का रैंडम टोकन (कार्यात्मक रूप से क्रेडेंशियल)।';
$string['privacy:metadata:token:last_used_at']   = 'टोकन का अंतिम बार कैलेंडर फीड फेच में उपयोग कब हुआ।';
$string['privacy:metadata:token:last_used_ip']   = 'अंतिम सफल फेच का IP पता (दुरुपयोग फॉरेंसिक्स के लिए)।';
$string['privacy:metadata:token:use_count']      = 'कुल सफल फेच गणना।';
$string['privacy:metadata:token:timecreated']    = 'टोकन पहली बार कब जारी हुआ।';
$string['privacy:metadata:token:timemodified']   = 'टोकन अंतिम बार कब संशोधित (पुनः जनरेट या रद्द) हुआ।';
