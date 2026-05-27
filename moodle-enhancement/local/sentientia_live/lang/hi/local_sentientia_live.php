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
$string['trainer_sessions_table_caption'] = 'आपके लाइव सेशन की सूची — स्थिति, join कोड, स्लाइड संख्या, ऑडियंस आकार, बनाने की तारीख और उपलब्ध क्रियाएं।';

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
$string['slide_editor_pending_title']  = 'स्लाइड संपादक — जल्द आ रहा है';
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
$string['live_runner_pending_title']  = 'लाइव रनर — रीयल-टाइम प्रोजेक्टर जल्द आ रहा है';
$string['live_runner_pending_body']   = 'वर्तमान placeholder सेशन जानकारी और basic state दिखाता है। आगामी अपडेट में रीयल-टाइम प्रोजेक्टर व्यू जोड़ा जाएगा (auto-updating ऑडियंस गिनती, लाइव प्रतिक्रिया चार्ट, आगे/पीछे बटन, full-screen मोड)।';
$string['action_end_session']         = 'इस सेशन को समाप्त करें';
$string['response_count_label']       = 'प्राप्त प्रतिक्रियाएं';

// ── Phase E.4 — result panel strings ───────────────────────────────
$string['live_results_heading']        = 'लाइव परिणाम';
$string['live_results_total_suffix']   = 'प्रतिक्रियाएं';
$string['live_results_empty']          = 'अभी तक कोई प्रतिक्रिया नहीं — कोड साझा करें और अपने ऑडियंस की प्रतीक्षा करें।';
$string['live_results_correct_label']  = 'सही';
$string['live_results_avg_label']      = 'औसत';
$string['live_results_responses_label']= 'प्रतिक्रियाएं';
$string['live_results_scale_label']    = 'स्केल';
$string['live_results_rank_label']     = 'रैंक';
$string['live_results_item_label']     = 'आइटम';
$string['live_results_avg_pos_label']  = 'औसत स्थिति';

// ── Phase E.6 — Quiz सारांश + leaderboard ─────────────────────────
$string['quiz_summary_label']            = 'क्विज़ परिणाम:';
$string['quiz_summary_of']               = 'में से';
$string['quiz_summary_got_it_right']     = 'ने सही उत्तर दिया';
$string['quiz_summary_correct_was']      = 'सही उत्तर:';
$string['quiz_leaderboard_label']        = 'लीडरबोर्ड';
$string['quiz_leaderboard_rank_col']     = 'रैंक';
$string['quiz_leaderboard_name_col']     = 'नाम';
$string['quiz_leaderboard_time_col']     = 'समय';
$string['quiz_leaderboard_seconds_suffix']= 'से';
$string['quiz_leaderboard_empty']        = 'अभी तक कोई सही उत्तर नहीं।';

// ── Phase E.7 — Session analytics CSV export ──────────────────────
$string['action_export_csv']             = 'निर्यात';
$string['action_export_csv_title']       = 'इस सेशन की सभी प्रतिक्रियाओं को CSV के रूप में डाउनलोड करें';
$string['export_session_label']          = 'सेशन निर्यात';
$string['export_format_unsupported']     = 'असमर्थित निर्यात प्रारूप: {$a}';
$string['export_open_failed']            = 'निर्यात के लिए आउटपुट स्ट्रीम नहीं खोली जा सकी।';

// ── Phase E.1.j — slide editor strings ─────────────────────────────

$string['invalidslide']                = 'स्लाइड मौजूद नहीं है।';
$string['back_to_session']             = 'सेशन पर वापस';

// Type picker (add_slide.php step 1).
$string['add_slide_pagetitle']          = 'स्लाइड जोड़ें';
$string['add_slide_pick_type_heading']  = 'प्रश्न प्रकार चुनें';
$string['add_slide_pick_type_intro']    = 'चुनें कि आपका ऑडियंस कैसे प्रतिक्रिया देगा। आप बाद में किसी भी प्रकार की और स्लाइड जोड़ सकते हैं।';
$string['no_slide_types_enabled']       = 'इस सर्वर पर कोई प्रश्न प्रकार सक्षम नहीं है। अपने एडमिन से Switchboard के माध्यम से कम से कम एक सक्षम करने के लिए कहें।';
$string['use_this_type']                = 'इस प्रकार का उपयोग करें';

// Per-type display name + short description.
$string['slide_type_multichoice']       = 'बहुविकल्पीय';
$string['slide_type_multichoice_desc']  = 'ऑडियंस आपके दिए गए विकल्पों में से एक चुनता है। परिणाम बार चार्ट के रूप में दिखाई देते हैं।';
$string['slide_type_quiz']              = 'क्विज़';
$string['slide_type_quiz_desc']         = 'Multiple choice की तरह, लेकिन सही उत्तर के साथ। ऑडियंस को तुरंत सही / गलत दिखाई देता है और एक लाइव लीडरबोर्ड भी।';
$string['slide_type_rating']            = 'रेटिंग scale';
$string['slide_type_rating_desc']       = '1-5 (या 0-10 NPS) scale। परिणाम औसत + वितरण हिस्टोग्राम के रूप में दिखाए जाते हैं।';
$string['slide_type_ranking']           = 'रैंकिंग';
$string['slide_type_ranking_desc']      = 'ऑडियंस आइटम की सूची को अपने पसंदीदा क्रम में drag करता है। परिणाम aggregate रैंकिंग दिखाते हैं।';
$string['slide_type_wordcloud']         = 'वर्ड क्लाउड';
$string['slide_type_wordcloud_desc']    = 'ऑडियंस एक शब्द भेजता है। सामान्य उत्तर क्लाउड में बड़े होते जाते हैं।';
$string['slide_type_openended']         = 'खुली प्रतिक्रिया';
$string['slide_type_openended_desc']    = 'फ़्री-टेक्स्ट प्रतिक्रिया। ऑडियंस के भेजते ही उत्तर स्क्रीन पर scroll होते हैं।';

// Add-slide form (step 2) + edit-slide.
$string['add_slide_form_pagetitle']     = 'स्लाइड जोड़ें';
$string['add_slide_form_heading']       = 'स्लाइड जोड़ें: {$a}';
$string['edit_slide_pagetitle']         = 'स्लाइड संपादित करें';
$string['edit_slide_heading']           = 'स्लाइड संपादित करें: {$a}';
$string['slide_added_notice']           = 'स्लाइड जोड़ी गई।';
$string['slide_updated_notice']         = 'स्लाइड अपडेट हुई।';
$string['slide_deleted_notice']         = 'स्लाइड हट गई।';

// Slide form labels.
$string['slide_title_label']            = 'प्रश्न पाठ';
$string['slide_title_required']         = 'प्रश्न पाठ आवश्यक है।';
$string['slide_type_label']             = 'प्रकार';
$string['slide_form_add_submit']        = 'स्लाइड जोड़ें';
$string['slide_form_update_submit']     = 'परिवर्तन सहेजें';

// Multiple choice + quiz options repeat.
$string['mc_option']                    = 'विकल्प';
$string['mc_add_more']                  = 'और विकल्प जोड़ें';
$string['quiz_option']                  = 'विकल्प';
$string['quiz_add_more']                = 'और विकल्प जोड़ें';
$string['quiz_correct_index_label']     = 'सही विकल्प संख्या';
$string['quiz_correct_index']           = 'सही विकल्प संख्या';
$string['quiz_correct_index_required']  = 'निर्दिष्ट करें कि कौन सा विकल्प (1, 2, ...) सही उत्तर है।';
$string['quiz_correct_index_help']      = 'सही विकल्प की 1-based स्थिति। तो यदि सही उत्तर आपके द्वारा टाइप किया गया दूसरा विकल्प है, तो 2 दर्ज करें। Server-side validated; out-of-range मान अस्वीकृत होते हैं।';

// Rating scale.
$string['rating_scale_min_label']       = 'Scale न्यूनतम';
$string['rating_scale_max_label']       = 'Scale अधिकतम';
$string['rating_scale_labels_label']    = 'Scale लेबल (वैकल्पिक, | से अलग किया गया)';
$string['rating_scale_labels']          = 'Scale लेबल';
$string['rating_scale_labels_help']     = 'Pipe-separated labels जो scale के प्रत्येक step पर क्रम में दिखाए जाएंगे। उदाहरण: "पूरी तरह असहमत|असहमत|तटस्थ|सहमत|पूरी तरह सहमत"। केवल संख्याएं दिखाने के लिए खाली छोड़ें।';

// Ranking.
$string['ranking_item']                 = 'आइटम';
$string['ranking_add_more']             = 'और आइटम जोड़ें';

// Word cloud.
$string['wc_max_word_length_label']     = 'अधिकतम शब्द लंबाई';
$string['wc_max_word_length']           = 'अधिकतम शब्द लंबाई';
$string['wc_max_word_length_help']      = 'इस अक्षर संख्या से लंबे submissions truncate हो जाते हैं। क्लाउड को पढ़ने योग्य बनाए रखने में मदद करता है। Range 3-100।';
$string['wc_dedupe_label']              = 'ऑडियंस submissions को de-duplicate करें';
$string['wc_dedupe_desc']               = 'चेक करने पर, प्रत्येक ऑडियंस सदस्य केवल एक शब्द भेज सकता है। अनचेक करने पर, वे कई शब्द भेज सकते हैं।';

// Open ended.
$string['openended_max_chars_label']    = 'प्रति प्रतिक्रिया अधिकतम अक्षर';
$string['openended_max_chars']          = 'प्रति प्रतिक्रिया अधिकतम अक्षर';
$string['openended_max_chars_help']     = 'प्रतिक्रिया लंबाई पर हार्ड कैप। डिफ़ॉल्ट 280 (Twitter-style)। Range 10-2000।';

// Slide row actions on edit.php.
$string['action_add_slide']             = 'स्लाइड जोड़ें';
$string['action_move_up']               = 'ऊपर ले जाएं';
$string['action_move_down']             = 'नीचे ले जाएं';
$string['action_delete_slide']          = 'हटाएं';
$string['action_show_now']              = 'अभी दिखाएं';
$string['badge_current_slide']          = 'वर्तमान';

// Delete-slide confirmation.
$string['delete_slide_pagetitle']       = 'स्लाइड हटाएं';
$string['delete_slide_heading']         = 'स्लाइड हटाएं?';
$string['delete_slide_confirm_html']    = '{$a->type} स्लाइड <strong>"{$a->title}"</strong> हटाएं? इस स्लाइड के किसी भी ऑडियंस प्रतिक्रियाएं हट जाएंगी।';

// Set-current notices.
$string['slide_made_current_notice']    = 'अब ऑडियंस को यह स्लाइड दिखाई जा रही है।';
$string['slide_make_current_failed']    = 'इस स्लाइड को वर्तमान के रूप में सेट नहीं कर सका। सुनिश्चित करें कि सेशन लाइव है।';

// ── Phase E.2 — audience UI strings ────────────────────────────────

// Join page.
$string['audience_join_pagetitle']      = 'लाइव सेशन में join करें';
$string['audience_join_heading']        = 'लाइव सेशन में join करें';
$string['audience_join_intro']          = 'अपने प्रस्तुतकर्ता द्वारा साझा किया गया 6-अंक कोड दर्ज करें।';
$string['audience_invalid_code']        = 'उस कोड के साथ कोई लाइव सेशन नहीं। अपने प्रस्तुतकर्ता से अंक पुनः जांचें।';
$string['audience_code_label']          = 'सेशन कोड';
$string['audience_lookup_code']         = 'सेशन ढूंढें';
$string['audience_session_found']       = 'मिला: <strong>{$a}</strong>';
$string['audience_displayname_label']   = 'आपका प्रदर्शन नाम';
$string['audience_displayname_placeholder'] = 'हम आपको कैसे दिखाएं?';
$string['audience_join_button']         = 'सेशन में join करें';
$string['audience_cannot_join']         = 'इस सेशन में join करने की अनुमति आपके पास नहीं है।';
$string['audience_anonymous_not_allowed']= 'यह सेशन अनाम joins स्वीकार नहीं करता। कृपया पहले sign in करें।';

// Play page guards.
$string['audience_must_join_first']     = 'कृपया पहले सेशन कोड दर्ज करके join करें।';
$string['audience_token_invalid']       = 'आपका join token अमान्य या समाप्त है। सेशन में पुनः join करें।';

// Play page states.
$string['audience_waiting_heading']     = 'अगले प्रश्न की प्रतीक्षा कर रहे हैं…';
$string['audience_waiting_body']        = 'आपके प्रस्तुतकर्ता ने अभी पहला प्रश्न नहीं चुना है। प्रतीक्षा करें — यह पृष्ठ स्वचालित रूप से refresh होता है।';
$string['audience_waiting_next']        = 'थोड़ा रुकें — अगला प्रश्न स्वचालित रूप से दिखाई देगा।';
$string['audience_current_slide_gone']  = 'आपके प्रस्तुतकर्ता द्वारा चुनी गई स्लाइड अब उपलब्ध नहीं है।';
$string['audience_session_ended_heading'] = 'सेशन समाप्त';
$string['audience_session_ended_body']    = 'भाग लेने के लिए धन्यवाद। आपकी प्रतिक्रियाएं रिकॉर्ड हो गई हैं।';
$string['audience_response_saved']      = 'प्रतिक्रिया प्राप्त हुई — धन्यवाद!';
$string['audience_already_responded']   = 'आप पहले ही इस स्लाइड पर प्रतिक्रिया दे चुके हैं।';
$string['audience_submit_response']     = 'प्रतिक्रिया भेजें';
$string['audience_slide_progress']      = 'प्रश्न {$a->pos} में से {$a->total}';

// Response-side placeholders.
$string['wc_response_placeholder']      = 'एक शब्द टाइप करें…';
$string['openended_response_placeholder']= 'आपका उत्तर…';
$string['ranking_response_intro']       = 'प्रत्येक आइटम को 1 (आपकी शीर्ष पसंद) से नीचे की ओर number करें। प्रत्येक संख्या अनूठी होनी चाहिए — कोई ties नहीं।';

// ── Phase E.x — Accessibility (P0 #8 — aria-live regions) ─────────
// aria-live क्षेत्रों के अंदर surface होने वाले strings — screen reader
// इन्हें तब announce करते हैं जब underlying region update हो।
$string['a11y_results_region_label']     = 'लाइव परिणाम';
$string['a11y_results_bar_chart_label']  = 'लाइव परिणाम bar chart';
$string['a11y_response_recorded']        = 'प्रतिक्रिया रिकॉर्ड हो गई';
$string['a11y_session_ended_announce']   = 'सेशन समाप्त। आपकी प्रतिक्रियाएं रिकॉर्ड हो गई हैं।';
$string['a11y_audience_count_region']    = 'लाइव दर्शक संख्या';
$string['a11y_response_count_region']    = 'लाइव प्रतिक्रिया संख्या';
$string['a11y_current_question_region']  = 'वर्तमान प्रश्न';
$string['a11y_waiting_for_question']     = 'अगले प्रश्न की प्रतीक्षा';
$string['a11y_already_responded']        = 'आप पहले ही इस प्रश्न पर प्रतिक्रिया दे चुके हैं';

// ── Phase E.4-E.9 scaffold — question-type registry strings ───────
// प्रति registered abstract_question_type subclass एक नाम + एक विवरण।
// question_type_registry::get_all() द्वारा get_display_name() /
// get_description() के माध्यम से surface — slide-type picker और
// "available question types" admin listing में उपयोग।
$string['qtype_multichoice_name']        = 'बहुविकल्पीय';
$string['qtype_multichoice_desc']        = 'ऑडियंस आपके दिए गए विकल्पों में से एक चुनता है। परिणाम प्रति विकल्प एक bar के साथ horizontal bar chart के रूप में प्रदर्शित होते हैं — प्रतिशत और live गिनती सहित।';
$string['qtype_wordcloud_name']          = 'वर्ड क्लाउड';
$string['qtype_wordcloud_desc']          = 'प्रत्येक ऑडियंस सदस्य एक छोटा शब्द भेजता है। आवृत्ति बढ़ने के साथ-साथ सामान्य उत्तर tag cloud में बड़े दिखाई देते हैं।';
$string['qtype_openended_name']          = 'खुली प्रतिक्रिया';
$string['qtype_openended_desc']          = 'ऑडियंस configurable अधिकतम अक्षर सीमा तक free-form text भेजता है। उत्तर पहुँचते ही projector पर scroll होते हैं।';
$string['qtype_rating_name']             = 'रेटिंग scale';
$string['qtype_rating_desc']             = '1-5 Likert या 0-10 NPS। परिणाम histogram के रूप में और औसत + प्रतिक्रिया-गिनती सारांश सहित प्रदर्शित होते हैं।';
$string['qtype_quiz_name']               = 'क्विज़';
$string['qtype_quiz_desc']               = 'Multiple choice की तरह, लेकिन एक निर्धारित सही उत्तर के साथ। ऑडियंस को तुरंत सही/गलत दिखाई देता है; trainer के projector पर शुद्धता और गति के आधार पर live leaderboard दिखाई देता है।';
$string['qtype_ranking_name']             = 'रैंकिंग';
$string['qtype_ranking_desc']             = 'ऑडियंस आइटम की सूची को अपने पसंदीदा क्रम में drag करता है। परिणाम प्रत्येक आइटम की aggregate औसत स्थिति दिखाते हैं — निम्न = अधिक पसंदीदा।';

// response_recorder errors.
$string['response_slide_mismatch']      = 'वह स्लाइड इस सेशन का हिस्सा नहीं है।';
$string['response_int_required']        = 'संख्यात्मक प्रतिक्रिया आवश्यक है।';
$string['response_text_required']       = 'पाठ प्रतिक्रिया आवश्यक है।';
$string['response_text_too_long']       = 'प्रतिक्रिया बहुत लंबी है। अधिकतम {$a} अक्षर।';
$string['response_out_of_range']        = 'प्रतिक्रिया मान अनुमत range से बाहर है: {$a}';
$string['response_ranking_bad_json']    = 'Ranking प्रतिक्रिया item indices के JSON array के रूप में होनी चाहिए।';
$string['response_ranking_incomplete']  = 'भेजने से पहले कृपया प्रत्येक आइटम को rank करें।';
$string['invalidparticipant']           = 'प्रतिभागी record नहीं मिला।';
$string['participant_session_mismatch'] = 'प्रतिभागी इस सेशन का नहीं है।';

// ── Phase E.4 — multiple_choice question type ─────────────────────
// Class-layer constraints (validate_config) और audience-render
// surfaces। 2-6 cap केवल class layer में लगता है;
// slide_manager::validate_settings backwards compat के लिए अब भी
// 20 तक options स्वीकार करता है।
$string['mc_options_must_be_array']     = 'विकल्प सूची के रूप में देने होंगे।';
$string['mc_options_count_2_6']         = 'बहुविकल्पीय स्लाइड के लिए 2 से 6 विकल्प चाहिए (आपने {$a} दिए)।';
$string['mc_option_index_required']     = 'चयनित विकल्प आवश्यक है।';
$string['mc_render_style']              = 'प्रदर्शन शैली';
$string['mc_render_style_invalid']      = 'Render style "radio" या "buttons" होना चाहिए।';
$string['mc_render_style_label']        = 'प्रदर्शन शैली';
$string['mc_render_style_radio']        = 'Radio बटन';
$string['mc_render_style_buttons']      = 'Tap-target बटन';
$string['mc_render_style_help']         = 'Radio बटन घने विकल्प सूचियों के लिए उपयुक्त हैं; tap-target बटन मोबाइल पर तब आसान हैं जब प्रत्येक विकल्प छोटा हो।';
$string['mc_correct']                   = 'सही उत्तर';
$string['mc_correct_label']             = 'सही उत्तर (वैकल्पिक)';
$string['mc_correct_help']              = 'वैकल्पिक। वह विकल्प संख्या (1, 2, …) दर्ज करें जो सही उत्तर है, या बिना सही उत्तर वाले पोल के लिए खाली छोड़ें। ट्रेनर परिणाम दृश्य सही विकल्प को चिह्नित करता है; ऑडियंस इसे तब तक नहीं देखती जब तक आप प्रकट नहीं करते।';
$string['a11y_mc_tally_updated']        = 'मत गिनती अद्यतन हुई';
$string['a11y_mc_correct_revealed']     = 'सही उत्तर प्रकट किया गया';
