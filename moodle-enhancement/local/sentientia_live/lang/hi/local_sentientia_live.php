<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia LMS — लाइव एंगेजमेंट';

// ── Phase E.0 — scaffold strings (no UI yet) ─────────────────────────

// Privacy metadata.
$string['privacy:metadata:sessions'] = 'एक लाइव सेशन — प्रति Mentimeter-style पोल/क्विज़ इवेंट जो ट्रेनर चलाता है उसकी एक पंक्ति। मालिक (ट्रेनर userid), सेशन कोड, शुरू/समाप्ति टाइमस्टैम्प, और tenant/customer संदर्भ रिकॉर्ड करता है।';
$string['privacy:metadata:sessions:ownerid']    = 'सेशन बनाने और चलाने वाला ट्रेनर।';
$string['privacy:metadata:sessions:code']       = 'संख्यात्मक join कोड (6 अंक) जो ऑडियंस सदस्य join करने के लिए इस्तेमाल करते हैं।';
$string['privacy:metadata:sessions:tenantid']   = 'जिस BizLMS tenant से सेशन संबंधित है।';
$string['privacy:metadata:sessions:customerid'] = 'Sentientia customer scope।';
$string['privacy:metadata:sessions:timecreated']  = 'जब सेशन बनाया गया।';
$string['privacy:metadata:sessions:timestarted']  = 'जब सेशन लाइव हुआ (या 0 यदि कभी शुरू नहीं हुआ)।';
$string['privacy:metadata:sessions:timeended']    = 'जब सेशन समाप्त हुआ (या 0 यदि अभी भी चल रहा है)।';

$string['privacy:metadata:slides'] = 'लाइव सेशन के भीतर स्लाइड (प्रश्न)। प्रति स्लाइड एक पंक्ति।';
$string['privacy:metadata:slides:title']       = 'ऑडियंस को दिखाया गया स्लाइड शीर्षक।';
$string['privacy:metadata:slides:type']        = 'स्लाइड प्रकार — multichoice / wordcloud / openended / rating / quiz / ranking।';

$string['privacy:metadata:responses'] = 'स्लाइड के लिए व्यक्तिगत ऑडियंस प्रतिक्रियाएँ। यदि userid null है तो अनाम।';
$string['privacy:metadata:responses:userid']      = 'प्रतिक्रिया देने वाले user ID — अनाम सेशन के लिए nullable।';
$string['privacy:metadata:responses:value_text']  = 'फ़्री-टेक्स्ट प्रतिक्रिया (wordcloud / openended स्लाइड के लिए)।';
$string['privacy:metadata:responses:value_int']   = 'संख्यात्मक प्रतिक्रिया (multichoice / rating / quiz स्लाइड के लिए)।';
$string['privacy:metadata:responses:timecreated'] = 'जब प्रतिक्रिया भेजी गई।';

$string['privacy:metadata:participants'] = 'लाइव सेशन में ऑडियंस प्रतिभागी। उपस्थिति (last_seen) और प्रदर्शन नाम ट्रैक करता है।';
$string['privacy:metadata:participants:userid']        = 'प्रतिभागी user ID — अनाम joins के लिए nullable।';
$string['privacy:metadata:participants:display_name']  = 'ऑडियंस सूची / लीडरबोर्ड में दिखाया गया प्रदर्शन नाम।';
$string['privacy:metadata:participants:timejoined']    = 'जब user ने सेशन join किया।';
$string['privacy:metadata:participants:timelastseen']  = 'इस प्रतिभागी से अंतिम SSE heartbeat।';

$string['privacy:metadata:events'] = 'आंतरिक इवेंट जर्नल — स्लाइड परिवर्तन, प्रतिक्रिया गिनती, सेशन जीवनचक्र। SSE stream endpoint द्वारा polled। सेशन समाप्त होने के 24 घंटे बाद purge।';
$string['privacy:metadata:events:payload']       = 'इवेंट का वर्णन करने वाला JSON payload (slide_id, response_count, आदि)।';
$string['privacy:metadata:events:timecreated']   = 'जब इवेंट जनरेट हुआ।';

// Capability descriptions.
$string['sentientia_live:create']  = 'ट्रेनर के तौर पर नया लाइव सेशन बनाएं';
$string['sentientia_live:run']     = 'अपने बनाए लाइव सेशन को चलाएं (शुरू/आगे बढ़ाएं/समाप्त करें)';
$string['sentientia_live:join']    = 'कोड से मौजूदा लाइव सेशन में join करें';
$string['sentientia_live:respond'] = 'लाइव स्लाइड पर प्रतिक्रिया भेजें';
$string['sentientia_live:manage_all'] = 'एडमिन: सभी tenants के लाइव सेशन देखें और मैनेज करें';

// Errors.
$string['errorfeatureoff'] = 'Sentientia LMS लाइव एंगेजमेंट अभी बंद है। अपने एडमिन से live.enabled फ़ीचर फ़्लैग चालू करने के लिए कहें।';
$string['invalidsession']            = 'लाइव सेशन मौजूद नहीं है।';
$string['invalidslidetype']          = 'अमान्य स्लाइड प्रकार: {$a}';
$string['invalidtitle']              = 'शीर्षक आवश्यक है और 200 अक्षरों से कम होना चाहिए।';
$string['displayname_required']      = 'सेशन में join करने के लिए प्रदर्शन नाम आवश्यक है।';
$string['code_generation_failed']    = 'अनूठा join कोड allocate नहीं कर सका। कुछ देर में पुनः प्रयास करें।';
$string['invalid_event_type']        = 'अज्ञात इवेंट प्रकार: {$a}';
$string['mc_options_count']          = 'Multiple-choice / quiz स्लाइड को 2-20 विकल्प चाहिए (आपने {$a} प्रदान किए)।';
$string['mc_option_type']            = 'प्रत्येक विकल्प string होना चाहिए।';
$string['mc_option_length']          = 'प्रत्येक विकल्प 1-200 अक्षरों का होना चाहिए।';
$string['quiz_correct_out_of_range'] = 'सही-उत्तर index विकल्पों की सूची के बाहर है।';
$string['rating_scale_invalid']      = 'रेटिंग scale में 0 ≤ min < max ≤ 10 होना चाहिए।';
$string['ranking_items_count']       = 'Ranking स्लाइड को 2-20 आइटम चाहिए (आपने {$a} प्रदान किए)।';
$string['ranking_item_type']         = 'प्रत्येक ranking आइटम string होना चाहिए।';
$string['ranking_item_length']       = 'प्रत्येक ranking आइटम 1-200 अक्षरों का होना चाहिए।';

// ── Phase E.1.f — trainer dashboard strings ──
$string['trainer_dashboard_pagetitle']  = 'लाइव सेशन — ट्रेनर डैशबोर्ड';
$string['trainer_dashboard_heading']    = 'आपके लाइव सेशन';
$string['trainer_dashboard_subhead']    = 'अपने ऑडियंस के साथ रीयल-टाइम पोल, क्विज़ और वर्ड क्लाउड बनाएं, मैनेज करें और चलाएं।';
$string['trainer_create_button']        = 'नया सेशन बनाएं';
$string['trainer_no_sessions_heading']  = 'अभी कोई लाइव सेशन नहीं';
$string['trainer_no_sessions_body']     = 'अपने ऑडियंस से रीयल-टाइम फ़ीडबैक लेने के लिए अपना पहला सेशन बनाएं।';
$string['state_draft']                  = 'ड्राफ्ट';
$string['state_live']                   = 'लाइव';
$string['state_ended']                  = 'समाप्त';
$string['live_label']                   = 'लाइव';
$string['col_title']                    = 'शीर्षक';
$string['col_state']                    = 'स्थिति';
$string['col_code']                     = 'Join कोड';
$string['col_slides']                   = 'स्लाइड';
$string['col_audience']                 = 'ऑडियंस';
$string['col_created']                  = 'बनाया गया';
$string['col_actions']                  = 'क्रियाएं';
$string['action_edit']                  = 'संपादित करें';
$string['action_run']                   = 'चलाएं';
$string['action_end']                   = 'समाप्त करें';
$string['action_view']                  = 'देखें';
$string['confirm_end_session']          = 'इस लाइव सेशन को समाप्त करें? ऑडियंस disconnect हो जाएगा और परिणाम फ़्रीज़ हो जाएंगे।';
$string['confirm_delete_session']       = 'इस सेशन को स्थायी रूप से हटाएं? स्लाइड, ऑडियंस रिकॉर्ड और प्रतिक्रियाएं — सभी हट जाएंगे।';
$string['dashboard_session_count']      = 'कुल {$a} सेशन।';

// ── Phase E.1.g — create-session strings ──
$string['create_session_pagetitle']   = 'लाइव सेशन बनाएं';
$string['create_session_heading']     = 'नया लाइव सेशन';
$string['create_session_intro']       = 'अपने सेशन को शीर्षक दें और ऑडियंस सेटिंग्स समायोजित करें। आप अगला स्लाइड जोड़ सकेंगे।';
$string['session_created_notice']     = 'सेशन बन गया। नीचे अपनी पहली स्लाइड जोड़ें।';

// ── Phase E.1.g — session_form labels + help ──
$string['form_title_label']           = 'सेशन शीर्षक';
$string['form_title_required']        = 'कृपया सेशन शीर्षक प्रदान करें।';
$string['form_title_too_long']        = 'सेशन शीर्षक 200 अक्षरों से कम होना चाहिए।';
$string['form_title']                 = 'सेशन शीर्षक';
$string['form_title_help']            = 'इस सेशन के लिए एक छोटा नाम — ट्रेनर डैशबोर्ड और ऑडियंस-सामना स्क्रीन पर दिखाया गया। उदाहरण: "Q3 KYC ब्रिफ़ शॉटहैंडल", "September 2026 All-hands"।';

$string['form_settings_heading']      = 'ऑडियंस सेटिंग्स';

$string['form_allow_anonymous_label'] = 'अनाम ऑडियंस की अनुमति दें';
$string['form_allow_anonymous_desc']  = 'चेक करने पर, ऑडियंस सदस्य प्रदर्शन नाम दर्ज करके बिना लॉगिन के join कर सकते हैं।';
$string['form_allow_anonymous']       = 'अनाम ऑडियंस की अनुमति दें';
$string['form_allow_anonymous_help']  = 'एंटरप्राइज़ डिप्लॉयमेंट के लिए डिफ़ॉल्ट OFF — अधिकांश संगठन लर्नर ID के साथ correlated प्रतिक्रियाएं चाहते हैं। केवल तब चेक करें जब आप एक वर्कशॉप चला रहे हों जहाँ प्रतिभागी अनाम रखे जाने की बात है।';

$string['form_show_results_label']    = 'ऑडियंस को परिणाम दिखाएं';
$string['form_show_results_desc']     = 'चेक करने पर, ऑडियंस को प्रतिक्रिया देने के बाद रीयल-टाइम में चल रही गिनती (बार चार्ट / वर्ड क्लाउड / लीडरबोर्ड) दिखाई देगी।';

$string['form_allow_late_join_label'] = 'देर से Join की अनुमति दें';
$string['form_allow_late_join_desc']  = 'चेक करने पर, ऑडियंस सदस्य पहले से उत्तर दिए गए स्लाइड के बाद भी सेशन में join कर सकते हैं। उन्हें वर्तमान स्लाइड दिखाई देगी; पिछले स्लाइड skip हो जाएंगे।';

$string['form_max_concurrent_label']  = 'अधिकतम सहवर्ती ऑडियंस';
$string['form_max_concurrent']        = 'अधिकतम सहवर्ती ऑडियंस';
$string['form_max_concurrent_help']   = 'एक साथ कितने ऑडियंस सदस्य connect हो सकते हैं इस पर हार्ड कैप। डिफ़ॉल्ट 500 — सर्वर संसाधनों की रक्षा के लिए चुना गया। 500 से अधिक प्रतिभागियों वाले सेशन को इन्फ्रास्ट्रक्चर समीक्षा की आवश्यकता है (ADR-004 देखें)।';
$string['form_max_concurrent_range']  = 'अधिकतम सहवर्ती ऑडियंस 1 और 500 के बीच होना चाहिए।';

$string['form_create_submit']         = 'सेशन बनाएं';

// ── Phase E.1.i — edit / end / delete handler strings ──
$string['edit_session_pagetitle']     = 'लाइव सेशन संपादित करें';
$string['cannot_edit_session']        = 'इस सेशन को संपादित करने की अनुमति आपके पास नहीं है।';
$string['cannot_edit_live_session']   = 'लाइव सेशन को संपादित नहीं किया जा सकता। बदलाव करने के लिए पहले इसे समाप्त करें।';
$string['cannot_run_session']         = 'इस सेशन को चलाने की अनुमति आपके पास नहीं है।';
$string['cannot_delete_session']      = 'इस सेशन को हटाने की अनुमति आपके पास नहीं है।';
$string['session_updated_notice']     = 'सेशन अपडेट हुआ।';
$string['session_ended_notice']       = 'सेशन समाप्त। ऑडियंस disconnect; परिणाम फ़्रीज़।';
$string['session_not_live_error']     = 'इस सेशन को समाप्त नहीं किया जा सकता — यह वर्तमान में लाइव नहीं है।';
$string['session_deleted_notice']     = 'सेशन स्थायी रूप से हट गया।';
$string['delete_session_pagetitle']   = 'लाइव सेशन हटाएं';
$string['delete_session_heading']     = 'लाइव सेशन हटाएं?';
$string['delete_session_confirm_html'] = 'आप <strong>{$a->title}</strong> को स्थायी रूप से हटाने वाले हैं। यह <strong>{$a->slide_count}</strong> स्लाइड और <strong>{$a->participant_count}</strong> ऑडियंस रिकॉर्ड को उनकी सभी प्रतिक्रियाओं सहित हटा देगा।<br><br>इसे पूर्ववत नहीं किया जा सकता।';
$string['state_label']                = 'स्थिति';
$string['code_label']                 = 'Join कोड';
$string['action_start_session']       = 'सेशन शुरू करें';
$string['add_slide_to_start']         = 'सेशन शुरू करने से पहले कम से कम एक स्लाइड जोड़ें।';
$string['slides_heading']             = 'स्लाइड';
$string['no_slides_yet']              = 'अभी तक कोई स्लाइड नहीं जोड़ी गई।';
$string['slide_editor_pending_title']  = 'स्लाइड संपादक — Phase E.1.j में आ रहा है';
$string['slide_editor_pending_body']   = 'स्लाइड जोड़ना और संपादन अगला बनाया जा रहा है। तब तक, सेशन फ़्रेम अपनी जगह पर है: आप इसे rename कर सकते हैं, ऑडियंस सेटिंग्स समायोजित कर सकते हैं, और (एक बार स्लाइड मौजूद हो जाए) सेशन शुरू कर सकते हैं।';
$string['settings_heading_inline']    = 'ऑडियंस सेटिंग्स';

// ── Phase E.1.i — start/run page strings ──
$string['session_started_notice']     = 'सेशन अब लाइव है। ऑडियंस नीचे दिए गए कोड का उपयोग करके join कर सकते हैं।';
$string['session_not_startable_error']= 'सेशन शुरू नहीं कर सका — हो सकता है यह draft state में न हो।';
$string['session_not_live_for_run']   = 'यह सेशन लाइव नहीं है। पहले इसे शुरू करें।';
$string['run_session_pagetitle']      = 'लाइव सेशन चलाएं';
$string['audience_join_at']           = 'ऑडियंस यहाँ join करें';
$string['audience_join_url_hint']     = 'अपने ऑडियंस को {$a} पर भेजें और वे ऊपर दिया गया कोड दर्ज करें।';
$string['audience_count_label']       = 'ऑडियंस';
$string['audience_online']            = 'अभी ऑनलाइन';
$string['total_slides_label']         = 'डेक में {$a} स्लाइड';
$string['current_slide_heading']      = 'वर्तमान स्लाइड';
$string['slide_position_of']          = 'स्लाइड {$a->pos} में से {$a->total}';
$string['no_current_slide']           = 'अभी तक कोई स्लाइड चयनित नहीं। पहले कौन सी स्लाइड दिखानी है यह चुनने के लिए स्लाइड संपादक का उपयोग करें।';
$string['live_runner_pending_title']  = 'लाइव रनर — Phase E.3 में रीयल-टाइम प्रोजेक्टर आ रहा है';
$string['live_runner_pending_body']   = 'वर्तमान placeholder सेशन जानकारी और basic state दिखाता है। Phase E.3 SSE-संचालित प्रोजेक्टर व्यू जोड़ता है (auto-updating ऑडियंस गिनती, लाइव प्रतिक्रिया चार्ट, आगे/पीछे बटन, full-screen मोड)।';
$string['action_end_session']         = 'इस सेशन को समाप्त करें';
