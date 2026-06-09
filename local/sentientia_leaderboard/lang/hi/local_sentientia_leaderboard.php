<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'सेंटिएंटिया LMS — रीयल-टाइम लीडरबोर्ड';

// ── Capabilities ──────────────────────────────────────────────
$string['sentientia_leaderboard:view']         = 'मेरे टेनेंट के अंदर लीडरबोर्ड देखें';
$string['sentientia_leaderboard:manageboard']  = 'मेरे टेनेंट के अंदर लीडरबोर्ड बनाएँ, संपादित करें और हटाएँ';
$string['sentientia_leaderboard:promoteboard'] = 'लीडरबोर्ड को ग्राहक-व्यापी दृश्यता पर पदोन्नत करें';
$string['sentientia_leaderboard:viewall']      = 'सभी टेनेंट के लीडरबोर्ड देखें (HR विश्लेषण)';

// ── Board types ────────────────────────────────────────────────
$string['type_quiz']       = 'क्विज़ शीर्ष स्कोरर';
$string['type_completion'] = 'सबसे तेज़ पूर्णता';
$string['type_skill']      = 'अर्जित कौशल अंक';

$string['type_quiz_desc']       = 'एक क्विज़ पर सर्वश्रेष्ठ स्कोर के आधार पर शिक्षार्थियों की रैंकिंग। बराबरी का निर्णय लिए गए समय पर होता है।';
$string['type_completion_desc'] = 'किसी पाठ्यक्रम को पूरा करने में लगे समय के आधार पर रैंकिंग। कम समय = बेहतर रैंक।';
$string['type_skill_desc']      = 'दिनांक सीमा में अर्जित कौशल अंकों के आधार पर शिक्षार्थियों की रैंकिंग।';

// ── Scopes ─────────────────────────────────────────────────────
$string['scope_course']   = 'पाठ्यक्रम';
$string['scope_tenant']   = 'टेनेंट';
$string['scope_customer'] = 'ग्राहक-व्यापी';

// ── Statuses ───────────────────────────────────────────────────
$string['status_active']   = 'सक्रिय';
$string['status_disabled'] = 'अक्षम';
$string['status_archived'] = 'संग्रहीत';

// ── Columns ────────────────────────────────────────────────────
$string['col_rank']     = 'रैंक';
$string['col_user']     = 'शिक्षार्थी';
$string['col_points']   = 'अंक';
$string['col_score']    = 'स्कोर';
$string['col_time']     = 'लिया गया समय';
$string['col_progress'] = 'प्रगति';
$string['col_skills']   = 'कौशल स्तर बढ़ाए';

// ── Headings + labels ──────────────────────────────────────────
$string['heading_index']         = 'लीडरबोर्ड';
$string['heading_create']        = 'लीडरबोर्ड बनाएँ';
$string['heading_edit']          = 'लीडरबोर्ड संपादित करें';
$string['heading_view']          = 'लीडरबोर्ड';
$string['label_name']            = 'बोर्ड का नाम';
$string['label_type']            = 'बोर्ड का प्रकार';
$string['label_scope']           = 'दायरा';
$string['label_course']          = 'पाठ्यक्रम';
$string['label_quiz']            = 'क्विज़';
$string['label_skills']          = 'कौशल (अल्पविराम से अलग किए गए ID; सभी के लिए खाली छोड़ें)';
$string['label_window_start']    = 'स्कोरिंग विंडो प्रारंभ';
$string['label_window_end']      = 'स्कोरिंग विंडो समाप्त';
$string['label_recompute']       = 'पुनर्गणना अंतराल (सेकंड)';
$string['label_top_n']           = 'शीर्ष N दिखाएँ';
$string['label_show_my_rank']    = 'दर्शक को उनकी अपनी रैंक दिखाएँ';
$string['label_optout']          = 'मुझे सार्वजनिक लीडरबोर्ड से छिपाएँ';
$string['label_optout_desc']     = 'चेक किए जाने पर, आपका नाम हर सार्वजनिक लीडरबोर्ड से छिपा दिया जाता है। आप अंक अर्जित करना जारी रखते हैं, लेकिन अन्य शिक्षार्थी आपकी रैंक नहीं देख सकते।';

// ── Actions ────────────────────────────────────────────────────
$string['action_create']     = 'बोर्ड बनाएँ';
$string['action_save']       = 'परिवर्तन सहेजें';
$string['action_delete']     = 'हटाएँ';
$string['action_view']       = 'देखें';
$string['action_recompute']  = 'अभी पुनर्गणना करें';

// ── Misc + UI ─────────────────────────────────────────────────
$string['anonymous']         = 'अनाम शिक्षार्थी';
$string['you']               = 'आप';
$string['your_rank']         = 'आपकी रैंक: {$a}';
$string['no_rank_optout']    = 'आप सार्वजनिक लीडरबोर्ड से ऑप्ट-आउट हैं।';
$string['no_rank_no_entry']  = 'आपकी अभी इस बोर्ड पर कोई रैंकिंग नहीं है।';
$string['no_entries']        = 'अभी तक कोई रैंकिंग नहीं — अगली पुनर्गणना के बाद वापस जाँचें।';
$string['last_recomputed_at'] = 'अंतिम बार अद्यतन: {$a}';
$string['live_indicator']    = 'लाइव — रीयल-टाइम में अपडेट हो रहा है';
$string['polling_fallback']  = 'हर 30 सेकंड में अपडेट';
$string['feature_disabled']  = 'लीडरबोर्ड सक्षम नहीं हैं। व्यवस्थापक से sentientia.leaderboards.enabled को चालू करने के लिए कहें।';
$string['type_disabled']     = 'यह बोर्ड प्रकार सक्षम नहीं है।';

// ── Block ──────────────────────────────────────────────────────
$string['block_title']       = 'लीडरबोर्ड';
$string['block_choose']      = 'एक लीडरबोर्ड चुनें';
$string['block_none']        = 'आपके टेनेंट में कोई लीडरबोर्ड उपलब्ध नहीं है।';

// ── Errors ─────────────────────────────────────────────────────
$string['error_invalidtype']     = 'अमान्य बोर्ड प्रकार। यह quiz, completion या skill होना चाहिए।';
$string['error_invalidscope']    = 'अमान्य दायरा। यह course, tenant या customer होना चाहिए।';
$string['error_invalidwindow']   = 'स्कोरिंग विंडो की समाप्ति, प्रारंभ के बाद होनी चाहिए।';
$string['error_invalidrecompute'] = 'पुनर्गणना अंतराल कम से कम 30 सेकंड होना चाहिए।';
$string['error_typenotenabled']  = 'यह बोर्ड प्रकार व्यवस्थापक द्वारा सक्षम नहीं किया गया है।';
$string['error_quiznotscoped']   = 'क्विज़ बोर्ड को एक विशिष्ट क्विज़ (quizid > 0) के दायरे में होना चाहिए।';
$string['error_completionnotscoped'] = 'पूर्णता बोर्ड को एक विशिष्ट पाठ्यक्रम (courseid > 0) के दायरे में होना चाहिए।';
$string['error_noboard']         = 'लीडरबोर्ड नहीं मिला।';
$string['error_outoftenant']     = 'आप दूसरे टेनेंट का लीडरबोर्ड नहीं देख सकते।';
$string['error_cantpromote']     = 'आपके पास बोर्ड को ग्राहक-व्यापी बनाने की अनुमति नहीं है।';
$string['error_invalidpayload']  = 'इवेंट जर्नल के लिए अमान्य पेलोड डेटा।';
$string['invalid_event_type']    = 'अज्ञात इवेंट प्रकार: {$a}';

// ── Tasks ──────────────────────────────────────────────────────
$string['task_recompute_due_boards'] = 'देय लीडरबोर्ड की पुनर्गणना करें (सेंटिएंटिया)';
$string['task_purge_old_events']     = 'पुराने लीडरबोर्ड SSE इवेंट हटाएँ (सेंटिएंटिया)';

// ── Phase L.1: events + messages ───────────────────────────────
$string['event_rankings_updated']     = 'लीडरबोर्ड रैंकिंग अपडेट हुई';
$string['messageprovider:rank_change'] = 'लीडरबोर्ड रैंक परिवर्तन';

// Top-10 entry — celebration.
$string['msg_top10_subject'] = 'आपने {$a->boardname} पर शीर्ष {$a->new_rank} में जगह बनाई!';
$string['msg_top10_body']    = 'शाबाश — आपने अभी "{$a->boardname}" के शीर्ष 10 में रैंक #{$a->new_rank} पर प्रवेश किया है। और ऊँचाई तक पहुँचने के लिए जारी रखें।';

// Moved up.
$string['msg_moveup_subject'] = 'आप {$a->boardname} पर {$a->delta} स्थान ऊपर बढ़े';
$string['msg_moveup_body']    = 'आप "{$a->boardname}" पर रैंक #{$a->old_rank} से #{$a->new_rank} तक चढ़े — {$a->delta} स्थानों की छलांग। बढ़िया प्रदर्शन।';

// Moved down.
$string['msg_movedown_subject'] = 'आप {$a->boardname} पर {$a->delta} स्थान नीचे गिरे';
$string['msg_movedown_body']    = '"{$a->boardname}" पर आपकी रैंक #{$a->old_rank} से #{$a->new_rank} तक फिसल गई ({$a->delta} स्थान नीचे)। वापस ऊपर आना चाहते हैं? बोर्ड खोलें और सीखना जारी रखें।';

// ── User preference (opt-out) ─────────────────────────────────
$string['preference_optout'] = 'मुझे सार्वजनिक लीडरबोर्ड से छिपाएँ';

// ── Privacy ────────────────────────────────────────────────────
$string['privacy:metadata:lb_entries']                = 'किसी उपयोगकर्ता के लिए कैश की गई लीडरबोर्ड रैंकिंग';
$string['privacy:metadata:lb_entries:userid']         = 'जिस उपयोगकर्ता की रैंकिंग है';
$string['privacy:metadata:lb_entries:boardid']        = 'जिस लीडरबोर्ड से रैंकिंग संबंधित है';
$string['privacy:metadata:lb_entries:points']         = 'बोर्ड में अर्जित अंक';
$string['privacy:metadata:lb_entries:userrank']       = 'उपयोगकर्ता की रैंक';
$string['privacy:metadata:lb_entries:last_recomputed'] = 'यह पंक्ति अंतिम बार कब गणना की गई';

$string['privacy:metadata:lb_optouts']                = 'सार्वजनिक रूप से सूचीबद्ध होने से प्रति-उपयोगकर्ता ऑप्ट-आउट';
$string['privacy:metadata:lb_optouts:userid']         = 'जिस उपयोगकर्ता ने ऑप्ट-आउट किया';
$string['privacy:metadata:lb_optouts:customerid']     = 'ऑप्ट-आउट का ग्राहक दायरा';
$string['privacy:metadata:lb_optouts:timeoptedout']   = 'उपयोगकर्ता ने कब ऑप्ट-आउट किया';
