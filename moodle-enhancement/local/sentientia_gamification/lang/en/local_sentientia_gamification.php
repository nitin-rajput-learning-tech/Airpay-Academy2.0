<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sentientia Gamification';
$string['privacy:metadata'] = 'The gamification plugin stores points and badge data linked to user IDs.';

// Points.
$string['points'] = 'Points';
$string['totalpoints'] = 'Total Points';
$string['pointstoday'] = 'Points Today';
$string['pointshistory'] = 'Points History';
$string['level'] = 'Level';

// Badges.
$string['badges'] = 'Badges';
$string['badgeearned'] = 'Badge earned!';
$string['badge_first_step'] = 'First Step';
$string['badge_first_step_desc'] = 'Complete your first course';
$string['badge_quick_learner'] = 'Quick Learner';
$string['badge_quick_learner_desc'] = 'Complete 5 courses';
$string['badge_knowledge_seeker'] = 'Knowledge Seeker';
$string['badge_knowledge_seeker_desc'] = 'Complete 10 courses';
$string['badge_compliance_champion'] = 'Compliance Champion';
$string['badge_compliance_champion_desc'] = 'Complete all mandatory compliance courses';
$string['badge_streak_master'] = 'Streak Master';
$string['badge_streak_master_desc'] = 'Maintain a 30-day login streak';
$string['badge_quiz_ace'] = 'Quiz Ace';
$string['badge_quiz_ace_desc'] = 'Score 100% on 5 quizzes';
$string['badge_team_player'] = 'Team Player';
$string['badge_team_player_desc'] = 'Reach the top 10 leaderboard';

// Streaks.
$string['streak'] = 'Streak';
$string['currentstreak'] = 'Current Streak';
$string['longeststreak'] = 'Longest Streak';
$string['streakdays'] = '{$a} days';
$string['keepgoing'] = 'Keep going!';

// Leaderboard.
$string['leaderboard'] = 'Leaderboard';
$string['globalleaderboard'] = 'Global Leaderboard';
$string['departmentleaderboard'] = 'Your Department';
$string['yourrank'] = 'Your Rank: #{$a}';
$string['noentries'] = 'No leaderboard entries yet. Start learning to earn points!';

// Levels.
$string['level_beginner'] = 'Beginner';
$string['level_learner'] = 'Learner';
$string['level_achiever'] = 'Achiever';
$string['level_expert'] = 'Expert';
$string['level_master'] = 'Master';
$string['pointstonext'] = '{$a} points to next level';

// Privacy provider (2026-08-04) — real metadata + export + delete.
$string['privacy:metadata:points_log']              = 'Log of points awarded to a user per action';
$string['privacy:metadata:points_log:userid']       = 'The user the points were awarded to';
$string['privacy:metadata:points_log:action']       = 'The action that earned the points';
$string['privacy:metadata:points_log:points']       = 'How many points were awarded';
$string['privacy:metadata:points_log:courseid']     = 'The course the action happened in, if any';
$string['privacy:metadata:points_log:description']  = 'A short description of the award';
$string['privacy:metadata:points_log:timecreated']  = 'When the points were awarded';
$string['privacy:metadata:user_badges']             = 'Badges a user has earned';
$string['privacy:metadata:user_badges:userid']      = 'The user who earned the badge';
$string['privacy:metadata:user_badges:badgeid']     = 'The badge that was earned';
$string['privacy:metadata:user_badges:timeearned']  = 'When the badge was earned';
$string['privacy:metadata:streaks']                 = 'The user\'s login-streak counters';
$string['privacy:metadata:streaks:userid']          = 'The user the streak belongs to';
$string['privacy:metadata:streaks:current_streak']  = 'The current consecutive-day login streak';
$string['privacy:metadata:streaks:longest_streak']  = 'The longest streak the user has achieved';
$string['privacy:metadata:streaks:last_login_date'] = 'The date of the user\'s last counted login';
$string['privacy:metadata:streaks:total_points']    = 'The user\'s lifetime points total';
