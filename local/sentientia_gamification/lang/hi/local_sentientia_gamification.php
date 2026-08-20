<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'एयरपे गेमिफिकेशन';
$string['points'] = 'अंक';
$string['totalpoints'] = 'कुल अंक';
$string['pointstoday'] = 'आज के अंक';
$string['pointshistory'] = 'अंक इतिहास';
$string['level'] = 'स्तर';
$string['badges'] = 'बैज';
$string['badgeearned'] = 'बैज अर्जित!';
$string['streak'] = 'स्ट्रीक';
$string['currentstreak'] = 'वर्तमान स्ट्रीक';
$string['longeststreak'] = 'सबसे लंबी स्ट्रीक';
$string['streakdays'] = '{$a} दिन';
$string['keepgoing'] = 'जारी रखें!';
$string['leaderboard'] = 'लीडरबोर्ड';
$string['globalleaderboard'] = 'वैश्विक लीडरबोर्ड';
$string['departmentleaderboard'] = 'आपका विभाग';
$string['yourrank'] = 'आपकी रैंक: #{$a}';
$string['noentries'] = 'अभी तक कोई प्रविष्टि नहीं। अंक कमाने के लिए सीखना शुरू करें!';
$string['level_beginner'] = 'शुरुआती';
$string['level_learner'] = 'शिक्षार्थी';
$string['level_achiever'] = 'उपलब्धिकर्ता';
$string['level_expert'] = 'विशेषज्ञ';
$string['level_master'] = 'मास्टर';
$string['pointstonext'] = 'अगले स्तर तक {$a} अंक';

$string['badge_first_step'] = 'पहला कदम';
$string['badge_first_step_desc'] = 'अपना पहला कोर्स पूरा करें';
$string['badge_quick_learner'] = 'तेज़ शिक्षार्थी';
$string['badge_quick_learner_desc'] = '5 कोर्स पूरे करें';
$string['badge_knowledge_seeker'] = 'ज्ञान साधक';
$string['badge_knowledge_seeker_desc'] = '10 कोर्स पूरे करें';
$string['badge_compliance_champion'] = 'अनुपालन चैंपियन';
$string['badge_compliance_champion_desc'] = 'सभी अनिवार्य अनुपालन कोर्स पूरे करें';
$string['badge_streak_master'] = 'स्ट्रीक मास्टर';
$string['badge_streak_master_desc'] = '30-दिन की लॉगिन स्ट्रीक बनाए रखें';
$string['badge_quiz_ace'] = 'क्विज़ एस';
$string['badge_quiz_ace_desc'] = '5 क्विज़ में 100% स्कोर करें';
$string['badge_team_player'] = 'टीम प्लेयर';
$string['badge_team_player_desc'] = 'टॉप 10 लीडरबोर्ड में पहुँचें';

// P1 #50 (2026-05-20) — Hindi top-up: 1 string (privacy).
$string['privacy:metadata'] = 'गेमिफ़िकेशन प्लगइन यूज़र ID से लिंक्ड अंक और बैज डेटा संग्रहीत करता है।';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:points_log']              = 'प्रति कार्रवाई उपयोगकर्ता को दिए गए अंकों का लॉग';
$string['privacy:metadata:points_log:userid']       = 'जिस उपयोगकर्ता को अंक दिए गए';
$string['privacy:metadata:points_log:action']       = 'जिस कार्रवाई से अंक अर्जित हुए';
$string['privacy:metadata:points_log:points']       = 'कितने अंक दिए गए';
$string['privacy:metadata:points_log:courseid']     = 'जिस कोर्स में कार्रवाई हुई, यदि कोई हो';
$string['privacy:metadata:points_log:description']  = 'पुरस्कार का संक्षिप्त विवरण';
$string['privacy:metadata:points_log:timecreated']  = 'अंक कब दिए गए';
$string['privacy:metadata:user_badges']             = 'उपयोगकर्ता द्वारा अर्जित बैज';
$string['privacy:metadata:user_badges:userid']      = 'जिस उपयोगकर्ता ने बैज अर्जित किया';
$string['privacy:metadata:user_badges:badgeid']     = 'जो बैज अर्जित किया गया';
$string['privacy:metadata:user_badges:timeearned']  = 'बैज कब अर्जित किया गया';
$string['privacy:metadata:streaks']                 = 'उपयोगकर्ता के लॉगिन-स्ट्रीक काउंटर';
$string['privacy:metadata:streaks:userid']          = 'जिस उपयोगकर्ता की स्ट्रीक है';
$string['privacy:metadata:streaks:current_streak']  = 'वर्तमान लगातार-दिन लॉगिन स्ट्रीक';
$string['privacy:metadata:streaks:longest_streak']  = 'उपयोगकर्ता की अब तक की सबसे लंबी स्ट्रीक';
$string['privacy:metadata:streaks:last_login_date'] = 'उपयोगकर्ता के अंतिम गिने गए लॉगिन की तारीख';
$string['privacy:metadata:streaks:total_points']    = 'उपयोगकर्ता का कुल आजीवन अंक';
