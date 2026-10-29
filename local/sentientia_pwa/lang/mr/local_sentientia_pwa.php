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
 * Marathi strings for local_sentientia_pwa.
 *
 * Machine-drafted on 2026-10-23 (research item 4) following TRANSLATION-REVIEW-INSTRUCTIONS.md;
 * reviewed 2026-10-25 to 2026-10-29 by AI bilingual reviewers (glossary pass, then back-translation QA);
 * not yet checked by a human native speaker.
 *
 * @package    local_sentientia_pwa
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — PWA';
$string['privacy:metadata'] = 'Sentientia LMS PWA प्लगइन प्रत्येक युजरसाठी वेब-पुश सबस्क्रिप्शन एंडपॉइंट्स सेव्ह करतो. पुश नोटिफिकेशन्स चालू नसतील तेव्हा कोणताही वैयक्तिक डेटा सेव्ह केला जात नाही.';
$string['nav_label'] = 'ब्राउझर नोटिफिकेशन्स';
$string['preferences_page_title'] = 'ब्राउझर नोटिफिकेशन्स';
$string['preferences_page_heading'] = 'ब्राउझर नोटिफिकेशन्स';
$string['preferences_page_intro'] = 'Sentientia LMS टॅबमध्ये उघडे न ठेवता रिमाइंडर्स, कोर्स अपडेट्स आणि असाइनमेंट अलर्ट्स मिळवण्यासाठी या ब्राउझरवर पुश नोटिफिकेशन्स चालू करा.';
$string['preferences_install_heading'] = 'Sentientia LMS ॲप म्हणून इन्स्टॉल करा';
$string['preferences_install_intro'] = 'मोबाइलवर सर्वोत्तम अनुभवासाठी, तुमच्या ब्राउझर मेनूमधून Sentientia LMS तुमच्या होम स्क्रीनवर इन्स्टॉल करा. इन्स्टॉल झाल्यावर, पुश नोटिफिकेशन्स इतर कोणत्याही ॲपसारखीच दिसतात — ब्राउझर बंद असतानाही.';
$string['subscribe_section_title'] = 'पुश नोटिफिकेशन्स';
$string['subscribe_intro'] = 'या ब्राउझरमध्ये डेडलाइन रिमाइंडर्स, कोर्स अपडेट्स आणि असाइनमेंट अलर्ट्स मिळवा. तुम्ही ते कधीही बंद करू शकता.';
$string['subscribe_enable'] = 'ब्राउझर नोटिफिकेशन्स चालू करा';
$string['subscribe_disable'] = 'ब्राउझर नोटिफिकेशन्स बंद करा';
$string['subscribe_unsupported'] = 'तुमचा ब्राउझर पुश नोटिफिकेशन्सना सपोर्ट करत नाही';
$string['subscribe_denied'] = 'तुमच्या ब्राउझरने नोटिफिकेशन्स ब्लॉक केली आहेत';
$string['subscribe_privacy_note'] = 'पुश मेसेजेस तुमच्या ब्राउझर व्हेंडरमार्फत (Google, Mozilla किंवा Apple) पाठवले जातात. आम्ही मेसेजमधील कंटेंट व्हेंडरसोबत कधीही शेअर करत नाही — फक्त एक एन्क्रिप्टेड ब्लॉब जातो, जो तुमचा ब्राउझर लोकली डिक्रिप्ट करतो.';
$string['vapid_not_setup_title'] = 'पुश नोटिफिकेशन्स अजून कॉन्फिगर केलेली नाहीत';
$string['vapid_not_setup_body'] = 'या सर्व्हरवर अजून VAPID कीपेअर जनरेट केलेली नाही. तुमच्या ॲडमिनला "php local/sentientia_pwa/cli/generate_vapid_keys.php" एकदा रन करायला सांगा.';
$string['push_flag_off_notice'] = 'पुश डिलिव्हरी सध्या तुमच्या ॲडमिनने बंद केली आहे. तुम्ही ती Switchboard मधून पुन्हा चालू करू शकता.';
$string['pwa_disabled_redirect'] = 'PWA फीचर सध्या बंद आहे. तुमच्या ॲडमिनशी संपर्क साधा.';
$string['vapid_already_exists'] = 'VAPID कीपेअर आधीच अस्तित्वात आहे. ओव्हरराइट करण्यासाठी --regenerate पास करा (यामुळे सध्याची सबस्क्रिप्शन्स निकामी होतात).';
$string['vapid_openssl_required'] = 'VAPID कीज जनरेट करण्यासाठी PHP openssl एक्स्टेंशन आवश्यक आहे.';
$string['vapid_generation_failed'] = 'VAPID की जनरेशन अयशस्वी झाले: {$a}';
$string['missingrequiredfields'] = 'एक आवश्यक फील्ड भरलेले नाही.';
$string['settings_vapid_heading'] = 'VAPID कीपेअर स्टेटस';
$string['settings_vapid_ready'] = 'VAPID कीपेअर ॲक्टिव्ह आहे. Switchboard मध्ये पुश डिलिव्हरी चालू करता येईल.';
$string['settings_vapid_not_setup'] = 'कोणतीही VAPID कीपेअर जनरेट केलेली नाही. तुम्ही keygen CLI रन करेपर्यंत पुश नोटिफिकेशन्स काम करणार नाहीत.';
$string['settings_vapid_cli_instruction'] = 'keygen CLI सर्व्हरवर एकदा रन करा (सहसा वेब होस्टवर, apache युजर म्हणून):';
$string['settings_vapid_public_label'] = 'पब्लिक की (base64url)';
$string['settings_vapid_generated_label'] = 'जनरेट केल्याची वेळ';
$string['settings_active_subs_label'] = 'ॲक्टिव्ह सबस्क्रिप्शन्स';
$string['settings_vapid_public_safe_note'] = 'हा कीपेअरचा पब्लिक भाग आहे — सबस्क्राइब करणाऱ्या प्रत्येक ब्राउझरला तो दिला जातो आणि तो शेअर करणे सुरक्षित आहे. प्रायव्हेट भाग स्टोरेजमध्ये एन्व्हलप-एन्क्रिप्टेड असतो आणि ॲडमिन UI मध्ये कधीही दाखवला जात नाही.';
$string['settings_store_body_label'] = 'पुश शीर्षक + बॉडी डिलिव्हरी लॉगमध्ये सेव्ह करा';
$string['settings_store_body_desc'] = 'डीफॉल्ट OFF — इन्सर्ट करण्यापूर्वी पुश शीर्षके आणि बॉडीचे पहिले 200 कॅरेक्टर्स SHA-256 ने हॅश केले जातात, त्यामुळे GDPR जोखमीशिवाय लॉग डीफॉल्ट 90 दिवस सुरक्षितपणे ठेवता येतो. नंतरच्या घटनांशी नेमका पुश कंटेंट जुळवून पाहायचा असेल अशा फॉरेन्सिक्स-मोड डिप्लॉयमेंट्ससाठी ON करा. सध्याच्या नोंदी पुन्हा लिहिल्या जात नाहीत.';
$string['settings_vapid_subject_label'] = 'VAPID सब्जेक्ट';
$string['settings_vapid_subject_desc'] = 'पुश प्रोव्हायडर्सना (Google FCM, Mozilla autopush) JWT मध्ये पाठवला जाणारा कॉन्टॅक्ट आयडेंटिफायर. तो mailto: किंवा https: URL असला पाहिजे. गैरवापराच्या तक्रारींबाबत तुमच्याशी संपर्क साधण्यासाठी पुश व्हेंडर्स याचा वापर करतात.';
$string['settings_push_defaults_heading'] = 'पुश नोटिफिकेशन डीफॉल्ट्स';
$string['settings_push_defaults_desc'] = 'प्रत्येक पुश डिलिव्हर करताना लागू होणारे सर्व्हर-साइड डीफॉल्ट्स. वैयक्तिक मेसेजेस हे ओव्हरराइड करू शकतात.';
$string['settings_default_ttl_label'] = 'डीफॉल्ट TTL (सेकंद)';
$string['settings_default_ttl_desc'] = 'डिव्हाइस ऑफलाइन असल्यास पुश प्रोव्हायडरने किती वेळ डिलिव्हरीचा प्रयत्न करत राहावे. डीफॉल्ट 86400 (24 तास). पहिल्या प्रयत्नात डिलिव्हर न झाल्यास मेसेज ड्रॉप करण्यासाठी 0 सेट करा.';
$string['settings_max_payload_label'] = 'कमाल पेलोड साइज (बाइट्स)';
$string['settings_max_payload_desc'] = 'प्रत्येक पुशसाठी परवानगी असलेला सर्वात मोठा पेलोड. Web Push स्पेकनुसार एन्क्रिप्शननंतर ≤ 4096 बाइट्स असणे बंधनकारक आहे. डीफॉल्ट 3500 एन्क्रिप्शन ओव्हरहेडसाठी जागा ठेवतो. यापेक्षा मोठे पेलोड्स न सांगता ट्रंकेट केले जातील.';
$string['settings_log_retention_label'] = 'लॉग रिटेन्शन (दिवस)';
$string['settings_log_retention_desc'] = '<code>mdl_local_sentientia_push_log</code> मधील नोंदी किती दिवस ठेवायच्या. रोज 02:00 वाजता एक क्रॉन जुन्या नोंदी पर्ज करतो. अमर्यादित रिटेन्शनसाठी 0 सेट करा (जास्त पुश पाठवणाऱ्या डिप्लॉयमेंट्सवर याची शिफारस केलेली नाही — टेबल झपाट्याने वाढते).';
$string['settings_push_log_link'] = 'पुश डिलिव्हरी लॉग पाहा';
$string['settings_push_log_link_desc'] = 'प्रत्येक पुश प्रयत्नाचा ऑपरेशनल लॉग. निकाल, युजर आणि वेळेनुसार फिल्टर करता येतो.';
$string['task_push_log_retention'] = 'PWA पुश लॉग रिटेन्शन (रोजचा पर्ज)';
$string['push_log_page_title'] = 'PWA पुश डिलिव्हरी लॉग';
$string['push_log_page_heading'] = 'PWA पुश डिलिव्हरी लॉग';
$string['push_log_stats_24h'] = 'मागील 24 तास';
$string['push_log_stats_line'] = '<strong>{$a->total_24h}</strong> प्रयत्न — <span class="text-success"><strong>{$a->sent_24h}</strong> पाठवले</span>, <span class="text-warning">{$a->gone_24h} गॉन</span>, <span class="text-danger">{$a->failed_24h} अयशस्वी</span>. <strong>{$a->unique_users_24h}</strong> युनिक युजर्स.';
$string['push_log_filter_apply'] = 'लागू करा';
$string['push_log_filter_result'] = 'निकाल';
$string['push_log_filter_since'] = 'कधीपासून';
$string['push_log_filter_userid'] = 'युजर ID';
$string['push_log_filter_any'] = 'कोणतेही';
$string['push_log_filter_sent'] = 'पाठवले';
$string['push_log_filter_failed'] = 'अयशस्वी';
$string['push_log_filter_gone'] = 'गॉन (सबस्क्रिप्शन डिलीट झाले)';
$string['push_log_filter_truncated'] = 'ट्रंकेट केलेले';
$string['push_log_since_1h'] = 'मागील 1 तास';
$string['push_log_since_24h'] = 'मागील 24 तास';
$string['push_log_since_7d'] = 'मागील 7 दिवस';
$string['push_log_since_30d'] = 'मागील 30 दिवस';
$string['push_log_since_all'] = 'सर्व काळ';
$string['push_log_no_results'] = 'फिल्टरशी जुळणारी कोणतीही पुश डिलिव्हरी नाही.';
$string['push_log_total_count'] = '{$a} जुळणाऱ्या डिलिव्हरीज';
$string['push_log_col_when'] = 'कधी';
$string['push_log_col_user'] = 'युजर';
$string['push_log_col_host'] = 'पुश होस्ट';
$string['push_log_col_title'] = 'शीर्षक';
$string['push_log_col_result'] = 'निकाल';
$string['push_log_col_http'] = 'HTTP';
$string['push_log_col_error'] = 'एरर तपशील';
$string['ios_hint_title'] = 'पुश नोटिफिकेशन्स चालू करण्यासाठी Sentientia LMS इन्स्टॉल करा';
$string['ios_hint_body'] = 'iOS Safari वर, ही साइट तुमच्या होम स्क्रीनवर जोडल्यावरच पुश नोटिफिकेशन्स काम करतात:';
$string['ios_hint_step1'] = 'स्क्रीनच्या खालच्या बाजूला असलेले शेअर बटण टॅप करा.';
$string['ios_hint_step2'] = 'खाली स्क्रोल करा आणि "Add to Home Screen" निवडा.';
$string['ios_hint_step3'] = 'तुमच्या होम स्क्रीनवरून Sentientia LMS उघडा आणि पुन्हा नोटिफिकेशन्स चालू करून पाहा.';
$string['ios_hint_dismiss'] = 'बंद करा';
$string['invalid_subscription_endpoint'] = 'सबस्क्रिप्शन एंडपॉइंट URL ओळखल्या जाणाऱ्या पुश सर्व्हिसची नाही.';
$string['invalid_subscription_key_p256dh'] = 'सबस्क्रिप्शन की (p256dh) चुकीच्या फॉरमॅटमध्ये आहे.';
$string['invalid_subscription_key_auth'] = 'सबस्क्रिप्शन auth सीक्रेट चुकीच्या फॉरमॅटमध्ये आहे.';
$string['vapid_master_key_missing'] = 'VAPID प्रायव्हेट की डिस्कवर एन्क्रिप्टेड आहे, पण मास्टर की कॉन्फिगर केलेली नाही. SENTIENTIA_VAPID_MASTER_KEY env var किंवा $CFG->sentientia_vapid_master_key सेट करा.';
$string['vapid_pem_decrypt_failed'] = 'सेव्ह केलेली VAPID प्रायव्हेट की डिक्रिप्ट करता आली नाही. एकतर मास्टर की रोटेट झाली आहे किंवा एन्क्रिप्टेड ब्लॉबमध्ये छेडछाड झाली आहे.';
$string['install_cta_title'] = 'Sentientia LMS इन्स्टॉल करा';
$string['install_cta_body'] = 'एका टॅपमध्ये ॲक्सेस, पुश नोटिफिकेशन्स आणि ऑफलाइन सपोर्टसाठी ॲप तुमच्या होम स्क्रीनवर जोडा.';
$string['install_cta_install_btn'] = 'इन्स्टॉल करा';
$string['install_cta_dismiss'] = 'आता नको';
$string['install_cta_dismiss_aria'] = 'इन्स्टॉल प्रॉम्प्ट बंद करा';
$string['install_cta_aria_label'] = 'Sentientia LMS ॲप इन्स्टॉल करा';
$string['install_cta_gotit'] = 'समजले';
$string['privacy:metadata:push_subs'] = 'युजरच्या ब्राउझर्सनी रजिस्टर केलेली वेब-पुश सबस्क्रिप्शन्स';
$string['privacy:metadata:push_subs:userid'] = 'हे सबस्क्रिप्शन ज्या युजरचे आहे तो युजर';
$string['privacy:metadata:push_subs:endpoint'] = 'युजरच्या ब्राउझरसाठी पुश-सर्व्हिस एंडपॉइंट URL';
$string['privacy:metadata:push_subs:p256dh'] = 'या सबस्क्रिप्शनसाठी ब्राउझरने जनरेट केलेली पब्लिक एन्क्रिप्शन की';
$string['privacy:metadata:push_subs:auth_secret'] = 'या सबस्क्रिप्शनसाठी ब्राउझरने जनरेट केलेले ऑथेंटिकेशन सीक्रेट';
$string['privacy:metadata:push_subs:user_agent'] = 'सबस्क्रिप्शन रजिस्टर करणाऱ्या ब्राउझरचा युजर एजंट';
$string['privacy:metadata:push_subs:last_seen'] = 'सबस्क्रिप्शनने शेवटचे चेक-इन केल्याची वेळ';
$string['privacy:metadata:push_subs:timecreated'] = 'सबस्क्रिप्शन रजिस्टर झाल्याची वेळ';
$string['privacy:metadata:push_log'] = 'युजरला पाठवलेल्या पुश नोटिफिकेशन्सचा लॉग';
$string['privacy:metadata:push_log:userid'] = 'ज्या युजरला नोटिफिकेशन पाठवले तो युजर';
$string['privacy:metadata:push_log:endpoint_host'] = 'ज्या पुश-सर्व्हिस होस्टमार्फत नोटिफिकेशन डिलिव्हर झाले तो होस्ट';
$string['privacy:metadata:push_log:title'] = 'नोटिफिकेशनचे शीर्षक';
$string['privacy:metadata:push_log:body_truncated'] = 'नोटिफिकेशन बॉडीचा छोटा केलेला अंश';
$string['privacy:metadata:push_log:url'] = 'नोटिफिकेशनमध्ये दिलेली लिंक';
$string['privacy:metadata:push_log:result'] = 'डिलिव्हरी यशस्वी झाली की अयशस्वी';
$string['privacy:metadata:push_log:sent_at'] = 'नोटिफिकेशन पाठवल्याची वेळ';
