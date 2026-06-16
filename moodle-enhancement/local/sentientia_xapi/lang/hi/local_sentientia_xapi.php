<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Hindi language strings — local_sentientia_xapi.
 * 100% parity with lang/en/local_sentientia_xapi.php (required by CLAUDE.md §6).
 *
 * @package    local_sentientia_xapi
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin identity.
$string['pluginname'] = 'Sentientia xAPI / LRS';

// ─── Admin settings ──────────────────────────────────────────────────────────
$string['settings_pagetitle']          = 'Sentientia xAPI / LRS सेटिंग्स';
$string['setting_lrs_token']           = 'LRS Bearer टोकन';
$string['setting_lrs_token_desc']      = 'बाहरी xAPI क्लाइंट (जैसे SCORM सामग्री, cmi5 AU) को प्रमाणित करने के लिए गोपनीय टोकन। न्यूनतम 32 वर्ण। गोपनीय रखें — किसी भी संदिग्ध एक्सपोज़र के बाद बदलें।';
$string['setting_lrs_basic_user']      = 'LRS बेसिक-ऑथ उपयोगकर्ता नाम';
$string['setting_lrs_basic_user_desc'] = 'Bearer टोकन के अतिरिक्त /lrs/statements एंडपॉइंट पर स्वीकृत वैकल्पिक HTTP Basic प्रमाणीकरण उपयोगकर्ता नाम। अक्षम करने के लिए खाली छोड़ें।';
$string['setting_lrs_basic_pass']      = 'LRS बेसिक-ऑथ पासवर्ड';
$string['setting_lrs_basic_pass_desc'] = 'HTTP Basic प्रमाणीकरण पासवर्ड (ऊपर दिए गए उपयोगकर्ता नाम के साथ)। अक्षम करने के लिए खाली छोड़ें।';
$string['setting_retention_days']      = 'स्टेटमेंट प्रतिधारण (दिन)';
$string['setting_retention_days_desc'] = 'इस अवधि से पुराने xAPI स्टेटमेंट रात्रिकालीन सफाई कार्य द्वारा हटा दिए जाते हैं। हमेशा के लिए रखने हेतु 0 सेट करें। डिफ़ॉल्ट: 730 (2 वर्ष)।';
$string['setting_emit_login']          = 'लॉगिन पर स्टेटमेंट भेजें';
$string['setting_emit_login_desc']     = 'चालू होने पर, उपयोगकर्ता लॉगिन करने पर "experienced" क्रिया स्टेटमेंट भेजा जाता है। अधिक ट्रैफ़िक वाली साइटें इसे बंद कर सकती हैं।';

// ─── Capability strings ───────────────────────────────────────────────────────
$string['xapi:viewstatements']   = 'LRS व्यूअर में xAPI स्टेटमेंट देखें';
$string['xapi:deletestatements'] = 'LRS से xAPI स्टेटमेंट हटाएं';
$string['xapi:managelrs']        = 'LRS सेटिंग्स और क्रेडेंशियल प्रबंधित करें';

// ─── LRS endpoint messages ────────────────────────────────────────────────────
$string['error_lrs_disabled']       = 'xAPI LRS वर्तमान में अक्षम है। अपने व्यवस्थापक से संपर्क करें।';
$string['error_lrs_auth']           = 'प्रमाणीकरण विफल। वैध Bearer टोकन या Basic क्रेडेंशियल प्रदान करें।';
$string['error_lrs_invalid_json']   = 'अनुरोध का मुख्य भाग वैध JSON नहीं है।';
$string['error_lrs_invalid_stmt']   = 'स्टेटमेंट सत्यापन विफल: {$a}';
$string['error_lrs_tenant']         = 'टेनेंट पहचान विफल। सुनिश्चित करें कि actor account homePage एक पंजीकृत टेनेंट से मेल खाता है।';
$string['error_lrs_method']         = 'इस एंडपॉइंट पर HTTP विधि समर्थित नहीं है।';
$string['error_lrs_not_found']      = 'स्टेटमेंट नहीं मिला।';

// ─── Statement model / verbs ──────────────────────────────────────────────────
$string['verb_completed']   = 'पूर्ण किया';
$string['verb_experienced'] = 'अनुभव किया';
$string['verb_passed']      = 'उत्तीर्ण';
$string['verb_failed']      = 'असफल';
$string['verb_attempted']   = 'प्रयास किया';
$string['verb_answered']    = 'उत्तर दिया';
$string['verb_launched']    = 'लॉन्च किया';
$string['verb_initialized'] = 'आरंभ किया';
$string['verb_terminated']  = 'समाप्त किया';
$string['verb_suspended']   = 'निलंबित किया';
$string['verb_resumed']     = 'पुनः आरंभ किया';
$string['verb_satisfied']   = 'संतुष्ट किया';

// ─── Validation messages ──────────────────────────────────────────────────────
$string['validate_actor_required']           = 'Actor आवश्यक है।';
$string['validate_actor_missing_objecttype'] = 'Actor का objectType Agent या Group होना चाहिए।';
$string['validate_actor_missing_ifi']        = 'Actor में ठीक एक IFI होना चाहिए (mbox, mbox_sha1sum, openid, या account)।';
$string['validate_actor_mbox_format']        = 'Actor mbox एक mailto: URI होना चाहिए।';
$string['validate_actor_account_missing']    = 'Actor account में homePage और name होना चाहिए।';
$string['validate_verb_required']            = 'Verb आवश्यक है।';
$string['validate_verb_id_required']         = 'Verb में एक id (IRI) होना चाहिए।';
$string['validate_verb_id_iri']              = 'Verb id एक वैध IRI होना चाहिए।';
$string['validate_object_required']          = 'Object आवश्यक है।';
$string['validate_object_id_required']       = 'Object में एक id (IRI) होना चाहिए।';
$string['validate_object_id_iri']            = 'Object id एक वैध IRI होना चाहिए।';
$string['validate_result_score_range']       = 'Result score scaled -1.0 और 1.0 के बीच होना चाहिए।';
$string['validate_result_score_raw_max']     = 'Result score raw, max से अधिक नहीं होना चाहिए।';
$string['validate_context_registration_uuid'] = 'Context registration एक वैध UUID होना चाहिए।';
$string['validate_timestamp_format']         = 'Timestamp एक वैध ISO 8601 दिनांक-समय स्ट्रिंग होनी चाहिए।';
$string['validate_id_uuid']                  = 'Statement id एक वैध UUID होना चाहिए।';

// ─── cmi5 strings ─────────────────────────────────────────────────────────────
$string['cmi5_session']              = 'cmi5 सत्र';
$string['cmi5_session_initialized']  = 'सत्र आरंभ हुआ';
$string['cmi5_session_terminated']   = 'सत्र समाप्त हुआ';
$string['cmi5_au_passed']            = 'Assignable Unit उत्तीर्ण';
$string['cmi5_au_failed']            = 'Assignable Unit असफल';
$string['cmi5_au_completed']         = 'Assignable Unit पूर्ण';

// ─── Admin UI ─────────────────────────────────────────────────────────────────
$string['lrs_viewer_title']      = 'xAPI स्टेटमेंट व्यूअर';
$string['lrs_viewer_heading']    = 'LRS — हालिया स्टेटमेंट';
$string['lrs_col_timestamp']     = 'समय';
$string['lrs_col_actor']         = 'Actor';
$string['lrs_col_verb']          = 'Verb';
$string['lrs_col_object']        = 'Object';
$string['lrs_col_score']         = 'स्कोर';
$string['lrs_col_success']       = 'सफलता';
$string['lrs_col_tenant']        = 'टेनेंट';
$string['lrs_no_statements']     = 'अभी तक कोई स्टेटमेंट दर्ज नहीं।';
$string['lrs_endpoint_label']    = 'LRS एंडपॉइंट URL';
$string['lrs_endpoint_desc']     = 'अपनी xAPI / cmi5 सामग्री या LRS क्लाइंट में LRS एंडपॉइंट के रूप में इस URL का उपयोग करें।';

// ─── Privacy ──────────────────────────────────────────────────────────────────
$string['privacy:metadata:local_sentientia_xapi_statements']          = 'इस उपयोगकर्ता द्वारा या उसके लिए भेजे गए xAPI स्टेटमेंट।';
$string['privacy:metadata:local_sentientia_xapi_statements:actorid']  = 'xAPI actor से जुड़ा Moodle उपयोगकर्ता id।';
$string['privacy:metadata:local_sentientia_xapi_statements:actor']    = 'JSON actor ऑब्जेक्ट (ईमेल या account पहचानकर्ता हो सकता है)।';
$string['privacy:metadata:local_sentientia_xapi_statements:verb']     = 'xAPI verb IRI।';
$string['privacy:metadata:local_sentientia_xapi_statements:object']   = 'JSON ऑब्जेक्ट (गतिविधि, एजेंट आदि)।';
$string['privacy:metadata:local_sentientia_xapi_statements:result']   = 'JSON परिणाम (स्कोर, सफलता, पूर्णता)।';
$string['privacy:metadata:local_sentientia_xapi_statements:context']  = 'JSON संदर्भ।';
$string['privacy:metadata:local_sentientia_xapi_statements:stored']   = 'LRS में स्टेटमेंट संग्रहीत होने का समय।';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions']              = 'cmi5 सत्र ट्रैकिंग रिकॉर्ड।';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:userid']       = 'Moodle उपयोगकर्ता id।';
$string['privacy:metadata:local_sentientia_xapi_cmi5_sessions:registration'] = 'cmi5 पंजीकरण UUID।';

// ─── Scheduled tasks ─────────────────────────────────────────────────────────
$string['task_purge_old_statements'] = 'पुराने xAPI स्टेटमेंट हटाएं';
