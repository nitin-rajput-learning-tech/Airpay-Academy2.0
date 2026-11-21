<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #55 (2026-05-20) — Hindi (hi) translations for local_sentientia_proctoring.
// Scope: proctored-exam workflow — consent, identity verification,
// live monitoring, behavioural events, review queue, status, settings,
// notifications, errors, privacy metadata (incl. AWS Rekognition / S3).

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया प्रॉक्टरिंग';

// Navigation.
$string['adminhome']   = 'प्रॉक्टरिंग एडमिन';
$string['reviewqueue'] = 'समीक्षा कतार';
$string['attempts']    = 'प्रॉक्टर्ड प्रयास';
$string['mysessions']  = 'मेरे प्रॉक्टर्ड सत्र';
$string['settings_h']  = 'प्रॉक्टरिंग सेटिंग्स';

// Capabilities.
$string['sentientia_proctoring:attempt']      = 'प्रॉक्टर्ड क्विज़ का प्रयास करें';
$string['sentientia_proctoring:viewattempts'] = 'प्रॉक्टरिंग प्रयास विवरण देखें';
$string['sentientia_proctoring:review']       = 'फ़्लैग किए गए प्रॉक्टरिंग सत्रों की समीक्षा करें';
$string['sentientia_proctoring:manage']       = 'प्रॉक्टरिंग सेटिंग्स + समीक्षक असाइनमेंट प्रबंधित करें';
$string['sentientia_proctoring:bypass']       = 'प्रॉक्टरिंग बायपास करें (आपातकालीन, ऑडिट-लॉग्ड)';

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
$string['privacy:metadata:local_sentientia_proctor_sessions']   = 'प्रॉक्टर्ड परीक्षा सत्र';
$string['privacy:metadata:local_sentientia_proctor_identity']   = 'पहचान सत्यापन स्कोर (मैच के बाद फ़ोटो हटा दिए जाते हैं)';
$string['privacy:metadata:local_sentientia_proctor_events']     = 'प्रति-प्रयास व्यवहारिक घटनाएँ';
$string['privacy:metadata:local_sentientia_proctor_recordings'] = 'वेबकैम/स्क्रीन रिकॉर्डिंग के लिए S3 कुंजियाँ';
$string['privacy:metadata:local_sentientia_proctor_reviews']    = 'मानव समीक्षक नोट और निर्णय';
$string['privacy:metadata:aws_rekognition']                 = 'AWS Rekognition (पहचान चेहरा मिलान)';
$string['privacy:metadata:aws_rekognition:photo']           = 'ID फ़ोटो और सेल्फ़ी (मैच के बाद हटा दिए जाते हैं, केवल स्कोर बरकरार)';
$string['privacy:metadata:aws_s3']                          = 'AWS S3 (रिकॉर्डिंग संग्रहण)';
$string['privacy:metadata:aws_s3:video']                    = 'परीक्षा प्रयास के वीडियो चंक';
$string['privacy:metadata:logstore']                        ='फ़्लैग किया गया सत्र और सबमिट की गई समीक्षा मानक लॉग में दर्ज होते हैं: सत्र, क्विज़, उम्मीदवार, समीक्षक और निर्णय।';

// Decision A5 (2026-11-12): प्लेटफ़ॉर्म के ऑडिट लॉग में सूचीबद्ध दो ऑडिट इवेंट।
$string['eventsessionflagged']  = 'प्रॉक्टर्ड सत्र समीक्षा के लिए फ़्लैग किया गया';
$string['eventreviewsubmitted'] = 'प्रॉक्टरिंग समीक्षा सबमिट की गई';

// B1 "Fix the foundation" (ADR-038 D2, 2026-11-20).
// प्रॉक्टर किए गए खुले प्रयास के हर पेज पर मॉनिटर द्वारा दिखाया जाने वाला बैनर।
$string['monitor_notice'] = 'यह प्रयास प्रॉक्टर किया जा रहा है: किसी दूसरे टैब या विंडो पर जाना, फ़ुलस्क्रीन छोड़ना और टेक्स्ट पेस्ट करना दर्ज किया जाता है।';
$string['error_event_type'] = 'यह इवेंट ब्राउज़र से रिपोर्ट नहीं किया जा सकता।';
// B1 review (2026-11-20): sentientia.proctoring.recording.enabled बंद होने पर upload_chunk मना कर देता है।
$string['error_recording_disabled'] = 'आपके संगठन के लिए रिकॉर्डिंग चालू नहीं है।';
// सर्वर और मॉनिटर द्वारा दर्ज इवेंट, जैसे समीक्षक उन्हें देखते हैं।
$string['event_page_shown']         = 'क्विज़ पेज खोला गया';
$string['event_window_focus']       = 'विंडो पर फ़ोकस वापस आया';
$string['event_consent_given']      = 'शिक्षार्थी ने निगरानी के लिए सहमति दी';
$string['event_identity_confirmed'] = 'शिक्षार्थी ने पुष्टि की कि वह प्रयास स्वयं कर रहा है';
$string['event_attempt_linked']     = 'क्विज़ प्रयास से जोड़ा गया';
$string['event_attempt_submitted']  = 'प्रयास सबमिट किया गया';
$string['event_attempt_abandoned']  = 'प्रयास छोड़ दिया गया';
$string['event_attempt_overdue']    = 'प्रयास का समय समाप्त';
// शेड्यूल्ड टास्क।
$string['task_purge_old_recordings'] = 'प्रॉक्टरिंग: अवधारण तिथि बीत चुकी रिकॉर्डिंग हटाएँ';
// सेटिंग्स शीर्षक।
$string['settings_h_store']      = 'रिकॉर्डिंग संग्रहण (AWS S3)';
$string['settings_h_store_desc'] = 'अवधारण तिथि बीत चुकी रिकॉर्डिंग हटाने वाला दैनिक टास्क इनका उपयोग करता है। पहचान फ़ोटो अब किसी प्रदाता को नहीं भेजे जाते (निर्णय D10: ID से चेहरे का स्वचालित मिलान नहीं)।';
$string['settings_h_rec']        = 'रिकॉर्डिंग';
$string['settings_h_rev']        = 'समीक्षा कतार';
// सूचनाएँ (प्राप्तकर्ता की भाषा में भेजी जाती हैं)।
$string['msg_flagged_subject'] = 'प्रॉक्टर्ड सत्र फ़्लैग किया गया: {$a->decision} (जोखिम {$a->score})';
$string['msg_flagged_body'] = 'एक प्रॉक्टर्ड क्विज़ सत्र समीक्षा के लिए फ़्लैग किया गया है।

सत्र ID: {$a->sessionid}
यूज़र ID: {$a->userid}
क्विज़ ID: {$a->quizid}
जोखिम स्कोर: {$a->score}/100
स्वचालित निर्णय: {$a->decision}

सत्र देखने और निर्णय दर्ज करने के लिए प्रॉक्टरिंग समीक्षा कतार खोलें।';
$string['msg_reviewed_subject'] = 'आपके प्रॉक्टर्ड क्विज़ प्रयास की समीक्षा हो गई';
$string['msg_reviewed_body'] = 'एक समीक्षक ने आपके प्रॉक्टर्ड क्विज़ प्रयास को देख लिया है।

निर्णय: {$a->decision}

प्रॉक्टर्ड क्विज़ पूरी करने के लिए धन्यवाद।';
$string['msg_reviewed_body_fail'] = 'एक समीक्षक ने आपके प्रॉक्टर्ड क्विज़ प्रयास को देख लिया है।

निर्णय: {$a->decision}

समीक्षक को ऐसी गतिविधि मिली जो इस क्विज़ के नियमों का उल्लंघन करती है, इसलिए इस प्रयास का परिणाम मान्य नहीं भी हो सकता है। यदि आपको लगता है कि यह गलती है, तो कृपया अपनी L&D टीम से संपर्क करें।';
$string['msg_identityfailed_subject'] = 'पहचान सत्यापन विफल';
$string['msg_identityfailed_body'] = 'इस प्रॉक्टर्ड क्विज़ के लिए आपका पहचान सत्यापन पास नहीं हुआ।

मैच स्कोर: {$a->score}%

कृपया अपना चेहरा बीच में और अच्छी रोशनी में रखकर फिर से प्रयास करें। यदि यह बार-बार विफल हो, तो अपनी L&D टीम से संपर्क करें।';
// गोपनीयता फ़ील्ड।
$string['privacy:metadata:sessions:userid']           = 'वह शिक्षार्थी जिसका प्रयास प्रॉक्टर किया गया';
$string['privacy:metadata:sessions:quizid']           = 'प्रॉक्टर की गई क्विज़';
$string['privacy:metadata:sessions:attemptid']        = 'वह क्विज़ प्रयास जिससे सत्र जुड़ा है';
$string['privacy:metadata:sessions:status']           = 'सत्र अपने जीवन-चक्र में कहाँ है';
$string['privacy:metadata:sessions:consent_given_at'] = 'शिक्षार्थी ने क्विज़ की प्रारंभ-पूर्व जाँच में निगरानी के लिए कब सहमति दी';
$string['privacy:metadata:sessions:risk_score']       = 'दर्ज इवेंट से निकाला गया स्कोर';
$string['privacy:metadata:sessions:auto_decision']    = 'उस स्कोर से स्वचालित निर्णय';
$string['privacy:metadata:sessions:human_decision']   = 'समीक्षक का निर्णय';
$string['privacy:metadata:sessions:timestarted']      = 'निगरानी कब शुरू हुई';
$string['privacy:metadata:sessions:timefinished']     = 'सर्वर ने सत्र कब बंद किया';
$string['privacy:metadata:events:event_type']         = 'क्या हुआ (जैसे टैब बदलना, पेस्ट, सहमति देना, प्रयास सबमिट होना)';
$string['privacy:metadata:events:severity']           = 'स्कोर में इवेंट कितना गिना जाता है';
$string['privacy:metadata:events:payload_json']       = 'इवेंट का विवरण, जैसे क्विज़ पेज नंबर और, सिक्योर मोड में, वह नेटवर्क (IP) पता जिससे हर क्विज़ पेज माँगा गया';
$string['privacy:metadata:events:timecreated']        = 'सर्वर ने इवेंट कब दर्ज किया';

// B2 "Secure assessment mode" (ADR-038 D3 और D6, 2026-11-20)।
// प्रयास के पेजों पर (local_sentientia_proctoring/secure_mode), शिक्षार्थी की भाषा में।
$string['secure_banner'] = 'सिक्योर मोड: कॉपी, पेस्ट और प्रिंट रोके गए हैं, और फ़ुलस्क्रीन या यह पेज छोड़ना दर्ज होता है।';
$string['secure_banner_count'] = 'दर्ज उल्लंघन: {$a}।';
$string['secure_banner_limit'] = 'दर्ज उल्लंघन: {$a->limit} में से {$a->count}। {$a->limit} पर आपका प्रयास अपने आप सबमिट हो जाता है।';
$string['secure_gate_title'] = 'फ़ुलस्क्रीन आवश्यक';
$string['secure_gate_body'] = 'प्रश्न केवल फ़ुलस्क्रीन में दिखाए जाते हैं। जारी रखने के लिए बटन चुनें।';
$string['secure_gate_returned'] = 'आपने फ़ुलस्क्रीन छोड़ दी, इसलिए प्रश्न छिपा दिए गए हैं। यह दर्ज किया गया।';
$string['secure_gate_denied'] = 'आपका ब्राउज़र फ़ुलस्क्रीन में नहीं गया। फिर से प्रयास करें। यदि यह विफल होता रहे, तो किसी अद्यतन डेस्कटॉप ब्राउज़र का उपयोग करें या अपनी L&D टीम से संपर्क करें।';
$string['secure_gate_button'] = 'फ़ुलस्क्रीन में जारी रखें';
$string['secure_warning_flag'] = 'उल्लंघन {$a} दर्ज: आपने फ़ुलस्क्रीन या यह पेज छोड़ा। आपका प्रयास एक समीक्षक के लिए चिह्नित किया जाएगा।';
$string['secure_warning_limit'] = '{$a->limit} में से उल्लंघन {$a->count} दर्ज: आपने फ़ुलस्क्रीन या यह पेज छोड़ा। {$a->limit} पर आपका प्रयास अपने आप सबमिट हो जाता है।';
$string['secure_autosubmitted_title'] = 'आपका प्रयास सबमिट हो गया';
$string['secure_autosubmitted_body'] = 'आपका प्रयास {$a} उल्लंघनों तक पहुँच गया, इसलिए उस क्षण तक सहेजे गए उत्तरों के साथ इसे अपने आप सबमिट कर दिया गया। एक समीक्षक इसे देखेगा।';
$string['secure_continue'] = 'जारी रखें';
$string['secure_blocked'] = 'इस मूल्यांकन के दौरान इसकी अनुमति नहीं है।';
$string['secure_watermark'] = '{$a->name} · प्रयास {$a->attempt}';
// सिक्योर मोड के इवेंट, जैसे समीक्षक उन्हें देखते हैं।
$string['event_page_served']             = 'सर्वर ने क्विज़ पेज भेजा';
$string['event_heartbeat_gap']           = 'पेज ने रिपोर्ट करना बंद कर दिया';
$string['event_monitor_missing']         = 'पेज जिन्होंने कभी रिपोर्ट नहीं किया';
$string['event_rate_limited']            = 'बहुत अधिक रिपोर्ट: कुछ संग्रहीत नहीं हुईं';
$string['event_integrity_autosubmitted'] = 'प्रयास अपने आप सबमिट हुआ (उल्लंघन सीमा)';
$string['event_secure_rules_accepted']   = 'शिक्षार्थी ने सिक्योर मोड के नियम स्वीकार किए';
$string['event_fullscreen_enter']        = 'फ़ुलस्क्रीन में गया';
$string['event_fullscreen_unavailable']  = 'ब्राउज़र में फ़ुलस्क्रीन नहीं है (प्रश्न छिपाए नहीं गए)';
$string['event_fullscreen_denied']       = 'ब्राउज़र ने फ़ुलस्क्रीन से इनकार किया';
$string['event_file_dialog']             = 'फ़ाइल चुनने वाला खुला (गिना नहीं गया)';
$string['event_copy_blocked']            = 'कॉपी रोकी गई';
$string['event_cut_blocked']             = 'कट रोका गया';
$string['event_paste_blocked']           = 'पेस्ट रोका गया';
$string['event_contextmenu_blocked']     = 'राइट-क्लिक रोका गया';
$string['event_print_blocked']           = 'प्रिंट रोका गया';
$string['event_shortcut_blocked']        = 'कीबोर्ड शॉर्टकट रोका गया';
$string['event_drop_blocked']            = 'ड्रैग और ड्रॉप रोका गया';
$string['event_screenshot_key']          = 'Print Screen कुंजी दबाई गई (रोकी नहीं जा सकती)';
$string['event_extended_display']        = 'एक दूसरा डिस्प्ले जुड़ा है (जैसा ब्राउज़र बताता है)';
$string['event_ip_changed']              = 'दो पेजों के बीच नेटवर्क पता बदल गया';
// क्विज़ प्रयास समीक्षा पेज पर इंटीग्रिटी रिपोर्ट (शिक्षक)।
$string['report_title'] = 'इंटीग्रिटी रिपोर्ट';
$string['report_level'] = 'सुरक्षा स्तर';
$string['report_policy'] = 'उल्लंघन होने पर';
$string['report_policy_warn'] = 'चेतावनी और समीक्षा के लिए चिह्न';
$string['report_policy_autosubmit'] = '{$a} उल्लंघनों पर अपने आप सबमिट';
$string['report_violations'] = 'गिने गए उल्लंघन';
$string['report_violations_of'] = '{$a->limit} में से {$a->count}';
$string['report_autosubmitted'] = 'अपने आप सबमिट हुआ';
$string['report_status'] = 'समीक्षा स्थिति';
$string['report_verdict_auto'] = 'स्वचालित निर्णय';
$string['report_verdict_human'] = 'समीक्षक का निर्णय';
$string['report_gaps'] = 'हार्टबीट अंतराल';
$string['report_unmonitored'] = 'पेज जिन्होंने कभी रिपोर्ट नहीं किया';
$string['report_counts'] = 'इवेंट';
$string['report_timeline'] = 'समयरेखा (सर्वर समय)';
$string['report_timeline_truncated'] = '{$a->total} में से सबसे नए {$a->shown} इवेंट दिखाए जा रहे हैं।';
$string['report_col_time'] = 'समय';
$string['report_col_event'] = 'इवेंट';
$string['report_col_page'] = 'पेज';
$string['report_col_note'] = 'विवरण';
$string['report_counted'] = 'उल्लंघन के रूप में गिना गया';
$string['report_counted_grace_exceeded'] = 'उल्लंघन के रूप में गिना गया: पेज खुलने के {$a} सेकंड के भीतर विंडो से फ़ोकस हटा और उन्हीं सेकंड में वापस नहीं आया';
$string['report_notcounted_duplicate'] = 'नहीं गिना गया: पिछले उल्लंघन के {$a} सेकंड के भीतर';
$string['report_notcounted_grace'] = 'नहीं गिना गया: पेज खुलने के {$a} सेकंड के भीतर';
$string['report_note_gap'] = '{$a} सेकंड तक कोई रिपोर्ट नहीं';
$string['report_note_missing'] = '{$a->served} पेज भेजे गए, {$a->shown} ने रिपोर्ट किया';
$string['report_note_autosubmitted'] = '{$a->limit} में से {$a->violations} उल्लंघनों पर';
$string['report_note_ip'] = '{$a->from} से {$a->to} तक';
$string['report_summarypage'] = 'सारांश';
$string['report_none'] = 'कोई इवेंट दर्ज नहीं।';
$string['report_open'] = 'प्रॉक्टरिंग पेजों में खोलें';
$string['report_limits'] = 'ये रिकॉर्ड शिक्षार्थी के ब्राउज़र से आते हैं; हर समय हमारे सर्वर का है। फ़ुलस्क्रीन या पेज छोड़ना गिना जाता है; रोके गए कॉपी, पेस्ट और प्रिंट प्रयास सूचीबद्ध होते हैं लेकिन गिने नहीं जाते। पेज दूसरे उपकरण, दूसरे व्यक्ति या कंप्यूटर के अपने टूल से लिए गए स्क्रीनशॉट को नहीं देख सकता, और बदला हुआ ब्राउज़र इसे रोक सकता है: तब हार्टबीट का अंतराल, या ऐसा पेज जिसने कभी रिपोर्ट नहीं किया, ही निशान होता है। निर्णय समीक्षक करता है; स्वचालित निर्णय केवल समीक्षा कतार का क्रम तय करता है।';
// गोपनीयता फ़ील्ड (B2)।
$string['privacy:metadata:sessions:securitylevel']     = 'जब शिक्षार्थी ने क्विज़ के नियम स्वीकार किए, तब उसका सुरक्षा स्तर';
$string['privacy:metadata:sessions:violationlimit']    = 'उल्लंघनों की वह संख्या जिस पर प्रयास अपने आप सबमिट होता है (भर्ती मूल्यांकन)';
$string['privacy:metadata:sessions:violations']        = 'सर्वर ने कितने उल्लंघन गिने';
$string['privacy:metadata:sessions:lastviolation']     = 'पिछला उल्लंघन कब गिना गया';
$string['privacy:metadata:sessions:lastheartbeat']     = 'प्रयास के पेज ने आख़िरी बार सर्वर को कब रिपोर्ट किया';
$string['privacy:metadata:sessions:timeautosubmitted'] = 'सीमा तक पहुँचने के कारण सर्वर ने प्रयास कब सबमिट किया';

// Privacy metadata: fields of the identity, recordings and reviews tables (2026-11-21).
$string['privacy:metadata:identity:userid']        = 'वह शिक्षार्थी जिसकी पहचान जाँची गई';
$string['privacy:metadata:identity:sessionid']     = 'वह प्रॉक्टर्ड सत्र जिससे यह जाँच जुड़ी है';
$string['privacy:metadata:identity:provider']      = 'वह सेवा जिसने फ़ोटो की तुलना की';
$string['privacy:metadata:identity:match_score']   = 'सेल्फ़ी ID फ़ोटो से कितनी मिलती थी';
$string['privacy:metadata:identity:passed']        = 'पहचान जाँच पास हुई या नहीं';
$string['privacy:metadata:identity:error_code']    = 'जाँच पूरी न हो पाने पर त्रुटि कोड';
$string['privacy:metadata:identity:error_msg']     = 'जाँच पूरी न हो पाने पर त्रुटि संदेश';
$string['privacy:metadata:identity:timecreated']   = 'जाँच कब की गई';
$string['privacy:metadata:recordings:sessionid']   = 'वह प्रॉक्टर्ड सत्र जिससे रिकॉर्डिंग जुड़ी है';
$string['privacy:metadata:recordings:kind']        = 'रिकॉर्डिंग वेबकैम की है या स्क्रीन की';
$string['privacy:metadata:recordings:s3_key']      = 'रिकॉर्डिंग कहाँ संग्रहीत है';
$string['privacy:metadata:recordings:duration_ms'] = 'रिकॉर्डिंग कितनी लंबी है';
$string['privacy:metadata:recordings:retain_until'] = 'रिकॉर्डिंग कब हटाई जानी है';
$string['privacy:metadata:recordings:deleted_at']  = 'रिकॉर्डिंग कब हटाई गई';
$string['privacy:metadata:recordings:timecreated'] = 'रिकॉर्डिंग कब संग्रहीत की गई';
$string['privacy:metadata:reviews:sessionid']      = 'वह प्रॉक्टर्ड सत्र जिसकी समीक्षा हुई';
$string['privacy:metadata:reviews:reviewer_userid'] = 'वह समीक्षक जिसने निर्णय लिया';
$string['privacy:metadata:reviews:decision']       = 'समीक्षक का निर्णय';
$string['privacy:metadata:reviews:note']           = 'समीक्षक का नोट';
$string['privacy:metadata:reviews:timecreated']    = 'समीक्षा कब सबमिट की गई';
