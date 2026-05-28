<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #51 (2026-05-20) — Hindi (hi) translations for local_airpay_core.
// Scope: tenant error strings, scheduled task names, cache definitions,
// Switchboard (feature flags), Style Guide, and feature flag categories.

defined('MOODLE_INTERNAL') || die();

$string['pluginname']            = 'एयरपे कोर (साझा संरचना)';
$string['error_outoftenant']     = 'आपके पास इस टेनेंट तक पहुँच नहीं है।';
$string['error_invalidtenant']   = 'अमान्य टेनेंट पहचानकर्ता।';

// Scheduled task names.
$string['task_publish_cron_health'] = 'एयरपे कोर: cron-health सारांश प्रकाशित करें';

// Cache definition descriptions (shown in /admin/cache_settings.php).
$string['cachedef_cron_health_banner']     = 'cron-health साइट-नोटिफ़िकेशन बैनर के लिए डीडुप कुंजी';
$string['cachedef_feature_flags_registry'] = 'प्रत्येक प्लगइन के घोषित फ़ीचर फ़्लैग की समेकित रजिस्ट्री। 60s TTL।';

// Phase A0 (2026-05-14) — Switchboard / feature flags.
$string['switchboard_pagetitle']  = 'Switchboard — फ़ीचर फ़्लैग';
$string['switchboard_no_changes'] = 'लागू करने के लिए कोई बदलाव नहीं।';
$string['switchboard_applied']    = '{$a} फ़्लैग बदलाव लागू किए गए। नए मान 60 सेकंड के भीतर प्रभावी होंगे (कैश TTL)।';

// Phase A0.5 (2026-05-14) — Style Guide.
$string['styleguide_pagetitle'] = 'एयरपे स्टाइल गाइड';

$string['unknownflagkey'] = 'अज्ञात फ़ीचर फ़्लैग कुंजी: "{$a}"। सेट करने से पहले कुंजी को किसी प्लगइन की db/feature_flags.php रजिस्ट्री फ़ाइल में घोषित किया जाना चाहिए।';

// Flag-gated feature disabled message.
$string['featuredisabled'] = 'फ़ीचर "{$a}" वर्तमान में आपके साइट एडमिनिस्ट्रेटर द्वारा अक्षम है। उनसे Switchboard के माध्यम से इसे पुनः सक्षम करने के लिए कहें।';

// Flag-category display labels (shown as section headers on the Switchboard).
$string['flag_category_ai']         = 'AI & ऑटोमेशन';
$string['flag_category_engagement'] = 'एंगेजमेंट & संचार';
$string['flag_category_commerce']   = 'कॉमर्स & मार्केटप्लेस';
$string['flag_category_identity']   = 'पहचान & एक्सेस';
$string['flag_category_learning']   = 'लर्निंग डिलीवरी';
$string['flag_category_search']     = 'सर्च';
$string['flag_category_obs']        = 'ऑब्ज़र्वेबिलिटी';
$string['flag_category_ux']         = 'यूज़र अनुभव';
$string['flag_category_sentientia'] = 'Sentientia प्लेटफ़ॉर्म';

// Session 2 / ADR-002 (2026-05-20) — customer-level feature flag scope.
$string['customer_default_label']     = 'सभी कस्टमर (वैश्विक डिफ़ॉल्ट)';
$string['error_invalidcustomer']      = 'अमान्य कस्टमर पहचानकर्ता: {$a}।';
$string['gateflag_no_customer_scope'] = 'कस्टमर-स्तरीय स्कोप गेट फ़्लैग का स्वयं कोई कस्टमर स्कोप नहीं है। इसे केवल वैश्विक या लीगेसी-टेनेंट स्कोप के माध्यम से सेट करें।';
$string['customer_layer_disabled']    = '"{$a}" के लिए कस्टमर-स्कोप ओवरराइड सेट नहीं किया जा सकता — कस्टमर-स्तरीय स्कोप लेयर वर्तमान में अक्षम है। पहले वैश्विक स्कोप पर sentientia.customer_level_flags.enabled सक्षम करें।';

// Switchboard scope banner copy.
$string['scope_global']                 = 'वैश्विक डिफ़ॉल्ट';
$string['scope_banner_global']          = 'आप <strong>वैश्विक डिफ़ॉल्ट</strong> संपादित कर रहे हैं — यह हर कस्टमर और हर टेनेंट पर लागू होता है जब तक ओवरराइड न किया जाए।';
$string['scope_banner_legacy_tenant']   = 'आप <strong>{$a}</strong> टेनेंट (लीगेसी स्कोप — सभी कस्टमर पर लागू) संपादित कर रहे हैं। यहाँ टॉगल केवल {$a} के लिए वैश्विक डिफ़ॉल्ट को ओवरराइड करते हैं।';
$string['scope_banner_customer']        = 'आप <strong>{$a}</strong> कस्टमर स्कोप संपादित कर रहे हैं। यहाँ टॉगल इस कस्टमर के सभी टेनेंट पर लागू होते हैं जब तक टेनेंट स्तर पर ओवरराइड न किया जाए।';
$string['scope_banner_customer_tenant'] = 'आप <strong>{$a->customer}</strong> कस्टमर / <strong>{$a->tenant}</strong> टेनेंट जोड़ी संपादित कर रहे हैं। यहाँ टॉगल केवल इस विशिष्ट टेनेंट के लिए कस्टमर-व्यापी मान को ओवरराइड करते हैं।';

// P0 बोरो #10 (Moodle 5.2, 2026-05-23) — रिपोर्ट पंक्तियों, प्रतिभागी सूची
// और ग्रेडबुक में यूज़र-स्थिति बैज। "यह 0% पर क्यों है? — अरे, ये निकल गया"
// वाला कन्फ्यूज़न ख़त्म करता है।
$string['userstatus_suspended']        = 'निलंबित';
$string['userstatus_deleted']          = 'हटाया गया';
$string['userstatus_badge_aria']       = 'खाते की स्थिति: {$a}';
$string['privacy:metadata:userstatus'] = 'यूज़र-स्थिति हेल्पर केवल यूज़र निलंबन फ्लैग पढ़ता है, संग्रहित नहीं करता।';

// P0 बोरो #11 (Moodle 5.2, 2026-05-23) — बैकअप फ़ाइलनाम टेम्पलेट।
// साइट एडमिन → प्लगइन्स → लोकल प्लगइन्स → Airpay Core।
$string['settings_pagetitle']                    = 'Airpay Core';
$string['setting_backup_filename_template']      = 'डिफ़ॉल्ट बैकअप फ़ाइलनाम टेम्पलेट';
$string['setting_backup_filename_template_desc'] = 'SENTIENTIA पाइपलाइन (और भविष्य के Sentientia LMS एक्सपोर्ट जॉब्स) बैकअप फ़ाइलनाम बनाते समय यह टेम्पलेट इस्तेमाल करता है। नीचे दिए प्लेसहोल्डर टोकन का उपयोग करें — रन-टाइम पर वे रिप्लेस हो जाएंगे। टेम्पलेट में न होने वाले टोकन को नज़रअंदाज़ किया जाएगा। {extension} खुद-ब-खुद जुड़ जाता है।';
$string['setting_backup_filename_tokens']        = 'उपलब्ध टोकन:';

// ADR-017 Phase 2 / C1.2 (2026-05-28) — पॉलीमॉर्फिक user_type labels.
$string['usertype_employee_label']          = 'कर्मचारी';
$string['usertype_consumer_label']          = 'शिक्षार्थी';
$string['usertype_partner_employee_label']  = 'पार्टनर स्टाफ';
$string['usertype_operator_label']          = 'ऑपरेटर';
$string['profile_field_department']         = 'विभाग';
$string['profile_field_job_title']          = 'पदनाम';
$string['profile_field_employee_id']        = 'कर्मचारी आईडी';
$string['profile_field_manager_name']       = 'प्रबंधक';
$string['profile_field_hire_date']          = 'सम्मिलित हुए';
$string['profile_field_interests']          = 'आपके विषय';
$string['profile_field_weekly_goal_hours']  = 'साप्ताहिक सीखने का लक्ष्य (घंटे)';
$string['profile_field_referral_source']    = 'हमारे बारे में कैसे जाना';
$string['profile_field_courses_enrolled']   = 'नामांकित पाठ्यक्रम';
$string['profile_field_consent_marketing']  = 'मार्केटिंग ईमेल';
$string['profile_field_consent_leaderboard'] = 'लीडरबोर्ड पर दिखाई दें';
$string['profile_field_customer_name']      = 'संगठन';
$string['profile_field_partner_employee_id'] = 'कर्मचारी आईडी';
$string['profile_field_partner_department'] = 'विभाग';
$string['profile_field_partner_job_title']  = 'भूमिका';
$string['profile_field_partner_manager']    = 'प्रबंधक';
$string['profile_field_operator_role']      = 'ऑपरेटर भूमिका';
$string['profile_field_contact_phone']      = 'संपर्क';
$string['profile_field_oncall_for']         = 'के लिए ऑन-कॉल';
$string['onboarding_step_welcome']          = 'स्वागत है';
$string['onboarding_step_interests']        = 'अपने विषय चुनें';
$string['onboarding_step_weekly_goal']      = 'साप्ताहिक लक्ष्य निर्धारित करें';
$string['onboarding_step_manager_intro']    = 'अपने प्रबंधक से मिलें';
$string['onboarding_step_compliance_walkthrough'] = 'अनिवार्य प्रशिक्षण';
$string['onboarding_step_consent_capture']  = 'गोपनीयता विकल्प';
$string['onboarding_step_finish']           = 'सब तैयार है';
