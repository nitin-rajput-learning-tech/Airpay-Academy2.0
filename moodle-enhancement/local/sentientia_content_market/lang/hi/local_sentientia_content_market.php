<?php
/**
 * Hindi (हिन्दी) language strings for local_sentientia_content_market.
 * 100% parity with lang/en — every string key must be present.
 *
 * @package    local_sentientia_content_market
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ─── Plugin identity ─────────────────────────────────────────────
$string['pluginname']      = 'कंटेंट मार्केटप्लेस';
$string['privacy:metadata'] = 'कंटेंट मार्केटप्लेस प्लगइन व्यक्तिगत डेटा संग्रहीत नहीं करता। यह केवल तृतीय-पक्ष कोर्स कैटलॉग मेटाडेटा को इंडेक्स करता है।';

// ─── Capabilities ────────────────────────────────────────────────
$string['sentientia_content_market:view']            = 'कंटेंट मार्केटप्लेस कैटलॉग ब्राउज़ करें';
$string['sentientia_content_market:syncproviders']   = 'प्रोवाइडर कैटलॉग सिंक ट्रिगर करें';
$string['sentientia_content_market:manageproviders'] = 'प्रोवाइडर कॉन्फ़िगरेशन और क्रेडेंशियल प्रबंधित करें';
$string['sentientia_content_market:mapskills']       = 'कैटलॉग आइटम को स्किल्स टैक्सोनॉमी से मैन्युअली मैप करें';

// ─── Browse page UI ──────────────────────────────────────────────
$string['browse_heading']         = 'कंटेंट मार्केटप्लेस';
$string['browse_desc']            = 'Go1, Udemy Business, Coursera और Skillsoft के क्यूरेटेड कोर्स खोजें — सर्चेबल और आपकी स्किल्स टैक्सोनॉमी से मैप किए हुए।';
$string['search_placeholder']     = 'कोर्स, विषय, स्किल्स खोजें...';
$string['search_button']          = 'खोजें';
$string['filter_provider']        = 'प्रोवाइडर';
$string['filter_all_providers']   = 'सभी प्रोवाइडर';
$string['filter_content_type']    = 'प्रकार';
$string['filter_all_types']       = 'सभी प्रकार';
$string['filter_level']           = 'स्तर';
$string['filter_all_levels']      = 'सभी स्तर';
$string['filter_skill']           = 'स्किल';
$string['results_count']          = '{$a} परिणाम';
$string['no_results']             = 'आपके फ़िल्टर से मेल खाने वाला कोई कोर्स नहीं मिला।';
$string['no_results_hint']        = 'अपनी खोज विस्तृत करने या फ़िल्टर हटाने का प्रयास करें।';
$string['view_on_provider']       = '{$a} पर खोलें';
$string['duration_mins']          = '{$a} मिनट';
$string['duration_hours']         = '{$a} घंटे';
$string['level_beginner']         = 'शुरुआती';
$string['level_intermediate']     = 'मध्यवर्ती';
$string['level_advanced']         = 'उन्नत';
$string['content_type_video']     = 'वीडियो';
$string['content_type_course']    = 'कोर्स';
$string['content_type_microlearning'] = 'माइक्रो-लर्निंग';
$string['content_type_podcast']   = 'पॉडकास्ट';
$string['content_type_article']   = 'लेख';
$string['free_label']             = 'शामिल';
$string['price_label']            = 'USD {$a}';
$string['page_prev']              = 'पिछला';
$string['page_next']              = 'अगला';
$string['page_indicator']         = 'पृष्ठ {$a->current} / {$a->total}';
$string['sync_now']               = 'अभी सिंक करें';
$string['manage_providers']       = 'प्रोवाइडर प्रबंधित करें';

// ─── Errors ──────────────────────────────────────────────────────
$string['featureunavailable']     = 'कंटेंट मार्केटप्लेस अभी आपके संगठन के लिए सक्षम नहीं है। अपने L&D व्यवस्थापक से संपर्क करें।';
$string['error_invalidtenant']    = 'अमान्य टेनेंट। आपके पास इस संसाधन तक पहुंच नहीं है।';

// ─── Settings page ───────────────────────────────────────────────
$string['settings_general_heading'] = 'कंटेंट मार्केटप्लेस';
$string['settings_general_desc']    = 'तृतीय-पक्ष कंटेंट प्रोवाइडर क्रेडेंशियल कॉन्फ़िगर करें। प्रत्येक प्रोवाइडर को फ़ीचर फ़्लैग स्विचबोर्ड के माध्यम से अलग से सक्षम करें। सभी क्रेडेंशियल एन्क्रिप्टेड संग्रहीत होते हैं।';

$string['settings_go1_heading']     = 'Go1';
$string['settings_go1_desc']        = 'Go1 कंटेंट लाइब्रेरी — 80,000+ कोर्स। Go1 पार्टनर डैशबोर्ड से API कुंजी प्राप्त करें।';
$string['settings_go1_api_key']     = 'Go1 API कुंजी';
$string['settings_go1_api_key_desc'] = 'Go1 REST API v3 के लिए बेयरर टोकन।';

$string['settings_udemy_heading']      = 'Udemy Business';
$string['settings_udemy_desc']         = 'Udemy Business सब्सक्रिप्शन कैटलॉग। सक्रिय Udemy Business खाता आवश्यक है।';
$string['settings_udemy_account_id']   = 'Udemy Business खाता ID';
$string['settings_udemy_account_id_desc'] = 'आपका Udemy Business संगठन खाता पहचानकर्ता।';
$string['settings_udemy_api_key']      = 'Udemy API कुंजी';
$string['settings_udemy_api_key_desc'] = 'Udemy Business → सेटिंग्स → API क्रेडेंशियल से API कुंजी।';

$string['settings_coursera_heading']       = 'Coursera for Business';
$string['settings_coursera_desc']          = 'Coursera for Business सब्सक्रिप्शन कैटलॉग।';
$string['settings_coursera_client_id']     = 'Coursera OAuth क्लाइंट ID';
$string['settings_coursera_client_id_desc'] = 'Coursera for Business → पार्टनर टूल्स → OAuth एप्लिकेशन से क्लाइंट ID।';
$string['settings_coursera_client_secret']  = 'Coursera OAuth क्लाइंट सीक्रेट';
$string['settings_coursera_client_secret_desc'] = 'ऊपर दिए गए क्लाइंट ID के साथ जोड़ा गया क्लाइंट सीक्रेट।';

$string['settings_skillsoft_heading']        = 'Skillsoft Percipio';
$string['settings_skillsoft_desc']           = 'Skillsoft Percipio कंटेंट लाइब्रेरी — अनुपालन, नेतृत्व और तकनीकी कोर्स।';
$string['settings_skillsoft_subdomain']      = 'Percipio सबडोमेन';
$string['settings_skillsoft_subdomain_desc'] = 'आपके संगठन का सबडोमेन (जैसे airpay.percipio.com के लिए "airpay")।';
$string['settings_skillsoft_org_id']         = 'Percipio संगठन ID';
$string['settings_skillsoft_org_id_desc']    = 'Percipio Admin → सेटिंग्स → API से संगठन GUID।';
$string['settings_skillsoft_api_key']        = 'Percipio API टोकन';
$string['settings_skillsoft_api_key_desc']   = 'Percipio Admin → सेटिंग्स → API से बेयरर टोकन।';

// ─── Scheduled task ──────────────────────────────────────────────
$string['task_sync_providers']    = 'तृतीय-पक्ष कंटेंट प्रोवाइडर कैटलॉग सिंक करें';
