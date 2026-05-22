<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//
// P1 #58 (2026-05-20) — Hindi (hi) translations for local_airpay_challenge.
// Scope: gamification challenges — capabilities, filters, status, attempt
// status, types, table columns, form labels, buttons, tabs, overview
// metrics, leaderboard, notifications, errors, empty states, privacy
// metadata for 3 tables (challenges, attempts, leaderboard).

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे गेमिफ़िकेशन चैलेंज';

// Capabilities.
$string['airpay_challenge:view']        = 'चैलेंज और लीडरबोर्ड देखें';
$string['airpay_challenge:participate'] = 'चैलेंज में शामिल हों और छोड़ें';
$string['airpay_challenge:manage']      = 'चैलेंज बनाएँ, संपादित और हटाएँ';
$string['airpay_challenge:viewall']     = 'सभी टेनेंट के लीडरबोर्ड देखें';

// Page titles.
$string['heading_index']       = 'चैलेंज';
$string['heading_view']        = 'चैलेंज: {$a}';
$string['heading_leaderboard'] = 'लीडरबोर्ड';

// Navigation.
$string['nav_my_challenges'] = 'मेरे चैलेंज';
$string['nav_browse']        = 'ब्राउज़';
$string['nav_leaderboard']   = 'लीडरबोर्ड';
$string['nav_admin']         = 'चैलेंज प्रबंधित करें';

// Index/admin filters.
$string['filter_status']             = 'स्थिति';
$string['filter_status_all']         = 'सभी';
$string['filter_status_draft']       = 'मसौदा';
$string['filter_status_active']      = 'सक्रिय';
$string['filter_status_archived']    = 'संग्रहीत';
$string['filter_search']             = 'खोज';
$string['filter_search_placeholder'] = 'चैलेंज नाम';
$string['filter_my']                 = 'मेरी भागीदारी';
$string['filter_my_all']             = 'सभी चैलेंज';
$string['filter_my_joined']          = 'शामिल हुए';
$string['filter_my_completed']       = 'पूर्ण';
$string['filter_my_available']       = 'शामिल होने के लिए उपलब्ध';

// Status labels.
$string['status_draft']    = 'मसौदा';
$string['status_active']   = 'सक्रिय';
$string['status_archived'] = 'संग्रहीत';

// Attempt status labels.
$string['attempt_enrolled']    = 'नामांकित';
$string['attempt_in_progress'] = 'प्रगति पर';
$string['attempt_completed']   = 'पूर्ण';
$string['attempt_failed']      = 'विफल';
$string['attempt_expired']     = 'समाप्त';

// Type labels.
$string['type_course_completion'] = 'कोर्स पूर्णता';
$string['type_streak']            = 'लॉगिन स्ट्रीक';
$string['type_quiz_score']        = 'क्विज़ स्कोर';
$string['type_custom']            = 'कस्टम';

// Index/table columns.
$string['col_name']         = 'नाम';
$string['col_type']         = 'प्रकार';
$string['col_target']       = 'लक्ष्य';
$string['col_points']       = 'अंक';
$string['col_status']       = 'स्थिति';
$string['col_participants'] = 'प्रतिभागी';
$string['col_dates']        = 'तिथियाँ';
$string['col_actions']      = 'क्रियाएँ';
$string['col_progress']     = 'मेरी प्रगति';

// Form labels.
$string['form_name']             = 'चैलेंज का नाम';
$string['form_shortname']        = 'संक्षिप्त नाम (slug)';
$string['form_shortname_help']   = 'आंतरिक पहचानकर्ता। URL में इस्तेमाल होता है। केवल अक्षर, अंक, हाइफ़न।';
$string['form_description']      = 'विवरण';
$string['form_type']             = 'चैलेंज प्रकार';
$string['form_targetcount']      = 'लक्ष्य संख्या';
$string['form_targetcount_help'] = 'इस चैलेंज को जीतने के लिए कितने पात्र कोर्स पूर्णताएँ चाहिए।';
$string['form_courseids']        = 'पात्र कोर्स';
$string['form_courseids_help']   = 'किसी भी कोर्स को गिनने के लिए खाली छोड़ें। अन्यथा, केवल इन कोर्सेज़ की पूर्णताएँ गिनी जाती हैं।';
$string['form_pointsreward']     = 'अंक पुरस्कार';
$string['form_status']           = 'स्थिति';
$string['form_startdate']        = 'शुरू होता है';
$string['form_enddate']          = 'समाप्त होता है';

// Buttons.
$string['btn_create']      = 'नया चैलेंज';
$string['btn_edit']        = 'संपादित करें';
$string['btn_delete']      = 'हटाएँ';
$string['btn_view']        = 'देखें';
$string['btn_join']        = 'चैलेंज में शामिल हों';
$string['btn_leave']       = 'चैलेंज छोड़ें';
$string['btn_publish']     = 'प्रकाशित करें (सक्रिय)';
$string['btn_archive']     = 'संग्रहीत करें';
$string['btn_leaderboard'] = 'लीडरबोर्ड';

// Tabs.
$string['tab_overview']     = 'सिंहावलोकन';
$string['tab_participants'] = 'प्रतिभागी';
$string['tab_leaderboard']  = 'लीडरबोर्ड';

// Overview metrics.
$string['ov_participants']    = 'प्रतिभागी';
$string['ov_completed']       = 'पूर्ण';
$string['ov_completion_pct']  = 'पूर्णता दर';
$string['ov_avg_progress']    = 'औसत प्रगति';
$string['ov_my_progress']     = 'मेरी प्रगति';
$string['ov_my_status']       = 'मेरी स्थिति';
$string['ov_my_points']       = 'मेरे अंक';
$string['ov_top_participant'] = 'शीर्ष प्रतिभागी';

// Leaderboard.
$string['lb_col_rank']                   = 'रैंक';
$string['lb_col_user']                   = 'यूज़र';
$string['lb_col_points']                 = 'अंक';
$string['lb_col_completed']              = 'पूर्ण';
$string['lb_no_entries']                 = 'अभी तक कोई लीडरबोर्ड प्रविष्टि नहीं। अंक कमाना शुरू करने के लिए एक चैलेंज में शामिल हों।';
$string['lb_filter_challenge']           = 'चैलेंज';
$string['lb_filter_challenge_aggregate'] = 'सभी चैलेंज (समेकित)';
$string['lb_filter_tenant']              = 'टेनेंट';
$string['lb_filter_tenant_mine']         = 'केवल मेरा टेनेंट';
$string['lb_filter_tenant_all']          = 'सभी टेनेंट (क्रॉस-टेनेंट)';

// Notifications.
$string['challenge_created']   = 'चैलेंज "{$a}" बनाया गया।';
$string['challenge_updated']   = 'चैलेंज "{$a}" अपडेट किया गया।';
$string['challenge_deleted']   = 'चैलेंज "{$a}" हटाया गया।';
$string['joined_challenge']    = 'आप इस चैलेंज में शामिल हो गए।';
$string['left_challenge']      = 'आपने यह चैलेंज छोड़ दिया।';
$string['challenge_completed'] = 'आपने यह चैलेंज पूरा कर लिया!';

// Errors.
$string['err_challenge_not_found']  = 'चैलेंज नहीं मिला।';
$string['err_challenge_not_active'] = 'यह चैलेंज वर्तमान में सक्रिय नहीं है।';
$string['err_already_joined']       = 'आप पहले से इस चैलेंज में शामिल हैं।';
$string['err_not_joined']           = 'आप इस चैलेंज में शामिल नहीं हैं।';
$string['err_already_completed']    = 'आप पहले से यह चैलेंज पूरा कर चुके हैं।';
$string['err_invalid_type']         = 'अमान्य चैलेंज प्रकार।';
$string['err_invalid_status']       = 'अमान्य स्थिति।';
$string['err_targetcount_min']      = 'लक्ष्य संख्या कम से कम 1 होनी चाहिए।';
$string['err_pointsreward_min']     = 'अंक पुरस्कार 0 या उससे अधिक होना चाहिए।';
$string['err_filterstoolong']       = 'फ़िल्टर ब्लॉब सीमा से अधिक है।';
$string['err_shortname_taken']      = 'संक्षिप्त नाम "{$a}" पहले से उपयोग में है।';
$string['err_outside_cohort']       = 'यह चैलेंज एक कोहोर्ट तक सीमित है जिसमें आप शामिल नहीं हैं।';

// Empty states.
$string['empty_no_challenges'] = 'अभी तक कोई चैलेंज नहीं। एक बनाने के लिए "नया चैलेंज" पर क्लिक करें।';
$string['empty_no_attempts']   = 'अभी तक कोई प्रतिभागी नहीं।';

// Misc.
$string['target_x_completions'] = '{$a} कोर्स पूर्णताएँ';
$string['rank_position']        = '#{$a}';
$string['points_x']             = '{$a} अंक';
$string['attempts_x_completed'] = '{$a} पूर्ण';

// Scheduled task.
$string['task_recompute_leaderboard'] = 'एयरपे चैलेंज लीडरबोर्ड पुनः गणना';

// Privacy provider strings — challenges table.
$string['privacy:metadata:challenges']             = 'एडमिन यूज़र्स द्वारा बनाई गई चैलेंज परिभाषाएँ (गेमिफ़िकेशन)।';
$string['privacy:metadata:challenges:createdby']   = 'चैलेंज बनाने वाले एडमिन की यूज़र ID।';
$string['privacy:metadata:challenges:name']        = 'चैलेंज का प्रदर्शन नाम।';
$string['privacy:metadata:challenges:open_path']   = 'चैलेंज-निर्माण समय में निर्माता का एयरपे टेनेंट पथ।';
$string['privacy:metadata:challenges:timecreated'] = 'चैलेंज कब बनाया गया।';

// Privacy provider strings — attempts table.
$string['privacy:metadata:attempts']               = 'चैलेंज पर प्रति-यूज़र नामांकन + प्रगति।';
$string['privacy:metadata:attempts:challengeid']   = 'जिस चैलेंज में यूज़र शामिल हुआ।';
$string['privacy:metadata:attempts:userid']        = 'प्रतिभागी यूज़र ID।';
$string['privacy:metadata:attempts:status']        = 'वर्तमान स्थिति (enrolled, in_progress, completed, failed, expired)।';
$string['privacy:metadata:attempts:progress']      = 'लक्ष्य की ओर पूर्ण की गई पात्र कार्रवाइयों की संख्या।';
$string['privacy:metadata:attempts:pointsawarded'] = 'पूर्ण होने पर दिए गए अंक।';
$string['privacy:metadata:attempts:timecreated']   = 'यूज़र चैलेंज में कब शामिल हुआ।';

// Privacy provider strings — leaderboard table.
$string['privacy:metadata:leaderboard']             = 'पूर्व-गणित लीडरबोर्ड रैंकिंग।';
$string['privacy:metadata:leaderboard:challengeid'] = 'जिस चैलेंज की रैंकिंग है (0 = समेकित)।';
$string['privacy:metadata:leaderboard:userid']      = 'रैंक किया गया यूज़र।';
$string['privacy:metadata:leaderboard:points']      = 'रैंकिंग चलाने वाला अंक स्कोर।';
$string['privacy:metadata:leaderboard:userrank']    = '1-आधारित रैंक स्थिति।';
