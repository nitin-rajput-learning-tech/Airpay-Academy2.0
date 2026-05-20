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
