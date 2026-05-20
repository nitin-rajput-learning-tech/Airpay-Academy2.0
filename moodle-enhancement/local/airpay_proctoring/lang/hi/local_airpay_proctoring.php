<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #55 (2026-05-20) — Hindi (hi) translations for local_airpay_proctoring.
// Scope: proctored-exam workflow — consent, identity verification,
// live monitoring, behavioural events, review queue, status, settings,
// notifications, errors, privacy metadata (incl. AWS Rekognition / S3).

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे प्रॉक्टरिंग';

// Navigation.
$string['adminhome']   = 'प्रॉक्टरिंग एडमिन';
$string['reviewqueue'] = 'समीक्षा कतार';
$string['attempts']    = 'प्रॉक्टर्ड प्रयास';
$string['mysessions']  = 'मेरे प्रॉक्टर्ड सत्र';
$string['settings_h']  = 'प्रॉक्टरिंग सेटिंग्स';

// Capabilities.
$string['airpay_proctoring:attempt']      = 'प्रॉक्टर्ड क्विज़ का प्रयास करें';
$string['airpay_proctoring:viewattempts'] = 'प्रॉक्टरिंग प्रयास विवरण देखें';
$string['airpay_proctoring:review']       = 'फ़्लैग किए गए प्रॉक्टरिंग सत्रों की समीक्षा करें';
$string['airpay_proctoring:manage']       = 'प्रॉक्टरिंग सेटिंग्स + समीक्षक असाइनमेंट प्रबंधित करें';
$string['airpay_proctoring:bypass']       = 'प्रॉक्टरिंग बायपास करें (आपातकालीन, ऑडिट-लॉग्ड)';

// Consent flow.
$string['consent_title']     = 'रिकॉर्ड की गई परीक्षा — सहमति आवश्यक';
$string['consent_intro']     = 'यह परीक्षा प्रॉक्टर की गई है। प्रारंभ करने से पहले, कृपया समीक्षा करें और सहमति दें।';
$string['consent_l1']        = '<strong>पहचान सत्यापन</strong> — आपसे सरकारी ID की एक तस्वीर और एक सेल्फ़ी अपलोड करने के लिए कहा जाएगा। मैच स्कोर की गणना की जाती है और केवल स्कोर बरकरार रखा जाता है।';
$string['consent_l2']        = '<strong>वेबकैम और स्क्रीन मॉनिटरिंग</strong> — परीक्षा की अवधि के लिए आपका कैमरा और स्क्रीन रिकॉर्ड किया जाता है। ऑडियो केवल शोर-फ़्लैगिंग के लिए कैप्चर किया जाता है।';
$string['consent_l3']        = '<strong>स्वचालित समीक्षा</strong> — रिकॉर्डिंग को संदिग्ध व्यवहार (कई चेहरे, फ़्रेम से बाहर निकलना, लंबे समय तक पृष्ठभूमि शोर) के लिए AI द्वारा स्कैन किया जाता है। फ़्लैग किए गए सत्रों की समीक्षा मानव प्रॉक्टर द्वारा की जाती है।';
$string['consent_retention'] = 'रिकॉर्डिंग {$a} दिनों तक बरकरार रखी जाती हैं फिर हटा दी जाती हैं। पहचान फ़ोटो मैच के तुरंत बाद हटा दी जाती हैं।';
$string['consent_accept']    = 'मैंने इन रिकॉर्डिंग शर्तों को पढ़ा है और सहमत हूँ';
$string['consent_decline']   = 'रद्द करें और बाहर निकलें';
$string['consent_proceed']   = 'परीक्षा पर जाएँ';

// Identity step.
$string['identity_title']        = 'चरण 1 — पहचान सत्यापन';
$string['identity_id_label']     = 'सरकारी ID (स्पष्ट तस्वीर, सभी 4 कोने दिखाई दे रहे हों)';
$string['identity_selfie_label'] = 'सेल्फ़ी (चेहरा केंद्र में, अच्छी रोशनी में)';
$string['identity_submit']       = 'पहचान सत्यापित करें';
$string['identity_processing']   = 'सत्यापित किया जा रहा है — इसमें 10-30 सेकंड लगते हैं...';
$string['identity_passed']       = 'पहचान सत्यापित (मैच स्कोर: {$a})';
$string['identity_failed']       = 'पहचान सत्यापन विफल। कृपया पुनः प्रयास करें या सहायता से संपर्क करें।';
$string['identity_lowmatch']     = 'मैच स्कोर बहुत कम ({$a})। कृपया बेहतर रोशनी में सेल्फ़ी पुनः लें।';

// Monitoring step.
$string['monitor_title']    = 'चरण 2 — लाइव मॉनिटरिंग सक्रिय';
$string['monitor_camera']   = 'कैमरा ऑन';
$string['monitor_mic']      = 'माइक्रोफ़ोन ऑन';
$string['monitor_screen']   = 'स्क्रीन रिकॉर्डिंग ऑन';
$string['monitor_lockwarn'] = 'इस ब्राउज़र टैब को न छोड़ें। टैब स्विच लॉग किए जाते हैं।';

// Events.
$string['event_face_lost']       = 'चेहरा फ़्रेम से बाहर';
$string['event_multiple_faces']  = 'कई चेहरे पाए गए';
$string['event_tab_switch']      = 'टैब स्विच किया गया';
$string['event_window_blur']     = 'विंडो ने फ़ोकस खोया';
$string['event_mic_noise']       = 'पृष्ठभूमि शोर स्पाइक';
$string['event_clipboard_paste'] = 'पेस्ट का पता चला';
$string['event_fullscreen_exit'] = 'फ़ुलस्क्रीन से बाहर निकला';
$string['event_session_start']   = 'सत्र शुरू हुआ';
$string['event_session_end']     = 'सत्र समाप्त हुआ';

// Review queue.
$string['review_pending']        = 'समीक्षा लंबित';
$string['review_in_progress']    = 'समीक्षा में';
$string['review_completed']      = 'समीक्षित';
$string['review_decision']       = 'निर्णय';
$string['review_decision_clean'] = 'साफ़ — कोई समस्या नहीं';
$string['review_decision_warn']  = 'चेतावनी — मामूली फ़्लैग';
$string['review_decision_fail']  = 'धोखाधड़ी का पता चला — प्रयास विफल';
$string['review_note']           = 'समीक्षक नोट';
$string['review_assign']         = 'समीक्षक असाइन करें';

// Status.
$string['status_new']        = 'शुरू नहीं हुआ';
$string['status_consenting'] = 'सहमति की प्रतीक्षा';
$string['status_verifying']  = 'पहचान सत्यापित की जा रही है';
$string['status_recording']  = 'रिकॉर्डिंग';
$string['status_finished']   = 'समाप्त';
$string['status_flagged']    = 'समीक्षा के लिए फ़्लैग किया गया';
$string['status_reviewed']   = 'समीक्षित';

// Settings.
$string['settings_provider']                  = 'पहचान सत्यापन प्रदाता';
$string['settings_provider_desc']             = 'aws = AWS Rekognition (प्रोडक्शन), mock = स्थानीय मॉक (परीक्षण/dev)। प्रति-परिवेश सेट करें।';
$string['settings_aws_region']                = 'AWS क्षेत्र';
$string['settings_aws_key']                   = 'AWS एक्सेस कुंजी ID';
$string['settings_aws_secret']                = 'AWS सीक्रेट एक्सेस कुंजी';
$string['settings_aws_s3_bucket']             = 'रिकॉर्डिंग के लिए S3 बकेट';
$string['settings_match_threshold']           = 'पहचान मैच थ्रेशोल्ड (%)';
$string['settings_match_threshold_desc']      = 'पहचान चरण पास करने के लिए न्यूनतम फ़ेस-मैच स्कोर। डिफ़ॉल्ट 85।';
$string['settings_retention_days']            = 'रिकॉर्डिंग अवधारण (दिन)';
$string['settings_retention_days_desc']       = 'हटाने से पहले रिकॉर्डिंग कितने दिनों तक रखी जाती हैं। डिफ़ॉल्ट 90।';
$string['settings_recording_chunk_secs']      = 'रिकॉर्डिंग चंक आकार (सेकंड)';
$string['settings_recording_chunk_secs_desc'] = 'वीडियो चंक कितनी बार अपलोड होते हैं। डिफ़ॉल्ट 30 सेकंड।';
$string['settings_default_reviewer']          = 'डिफ़ॉल्ट समीक्षक यूज़र ID';
$string['settings_default_reviewer_desc']     = 'जिस यूज़र को फ़्लैग किए गए सत्र मिलते हैं यदि कोई विशिष्ट समीक्षक असाइन नहीं है।';

// Notifications.
$string['messageprovider:session_flagged']  = 'प्रॉक्टर्ड सत्र समीक्षा के लिए फ़्लैग किया गया';
$string['messageprovider:session_reviewed'] = 'आपके प्रॉक्टर्ड सत्र की समीक्षा हुई';
$string['messageprovider:identity_failed']  = 'पहचान सत्यापन विफल';

// Errors.
$string['error_consent_required']   = 'प्रॉक्टर्ड परीक्षा शुरू करने से पहले सहमति आवश्यक है।';
$string['error_identity_required']  = 'शुरू करने से पहले पहचान सत्यापन पूर्ण होना चाहिए।';
$string['error_no_provider']        = 'कोई पहचान प्रदाता कॉन्फ़िगर नहीं है।';
$string['error_session_not_found']  = 'प्रॉक्टरिंग सत्र नहीं मिला।';
$string['error_session_state']      = 'इस कार्रवाई के लिए अमान्य सत्र स्थिति।';
$string['error_review_not_allowed'] = 'आप इस सत्र की समीक्षा के लिए अधिकृत नहीं हैं।';

// Privacy.
$string['privacy:metadata:local_airpay_proctor_sessions']   = 'प्रॉक्टर्ड परीक्षा सत्र';
$string['privacy:metadata:local_airpay_proctor_identity']   = 'पहचान सत्यापन स्कोर (मैच के बाद फ़ोटो हटा दिए जाते हैं)';
$string['privacy:metadata:local_airpay_proctor_events']     = 'प्रति-प्रयास व्यवहारिक घटनाएँ';
$string['privacy:metadata:local_airpay_proctor_recordings'] = 'वेबकैम/स्क्रीन रिकॉर्डिंग के लिए S3 कुंजियाँ';
$string['privacy:metadata:local_airpay_proctor_reviews']    = 'मानव समीक्षक नोट और निर्णय';
$string['privacy:metadata:aws_rekognition']                 = 'AWS Rekognition (पहचान चेहरा मिलान)';
$string['privacy:metadata:aws_rekognition:photo']           = 'ID फ़ोटो और सेल्फ़ी (मैच के बाद हटा दिए जाते हैं, केवल स्कोर बरकरार)';
$string['privacy:metadata:aws_s3']                          = 'AWS S3 (रिकॉर्डिंग संग्रहण)';
$string['privacy:metadata:aws_s3:video']                    = 'परीक्षा प्रयास के वीडियो चंक';
