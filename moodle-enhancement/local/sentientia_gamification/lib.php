<?php
/**
 * Lib functions for airpay gamification.
 *
 * @package    local_sentientia_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Seed default badges on first install.
 * Called from install.php or manually.
 */
function local_sentientia_gamification_seed_badges() {
    global $DB;

    // Only seed if no badges exist.
    if ($DB->count_records('local_sentientia_badges') > 0) {
        return;
    }

    $badges = [
        ['name' => 'First Step',           'description' => 'Complete your first course',                'icon' => 'fa-bullseye',       'criteria_type' => 'first_course',        'criteria_value' => 1,  'sort_order' => 1],
        ['name' => 'Quick Learner',        'description' => 'Complete 5 courses',                        'icon' => 'fa-bolt',           'criteria_type' => 'courses_completed',   'criteria_value' => 5,  'sort_order' => 2],
        ['name' => 'Knowledge Seeker',     'description' => 'Complete 10 courses',                       'icon' => 'fa-book',           'criteria_type' => 'courses_completed',   'criteria_value' => 10, 'sort_order' => 3],
        ['name' => 'Compliance Champion',  'description' => 'Complete all mandatory compliance courses',  'icon' => 'fa-shield',         'criteria_type' => 'compliance_complete', 'criteria_value' => 1,  'sort_order' => 4],
        ['name' => 'Streak Master',        'description' => 'Maintain a 30-day login streak',            'icon' => 'fa-fire',           'criteria_type' => 'streak_days',         'criteria_value' => 30, 'sort_order' => 5],
        ['name' => 'Quiz Ace',             'description' => 'Score 100% on 5 quizzes',                   'icon' => 'fa-trophy',         'criteria_type' => 'quizzes_perfect',     'criteria_value' => 5,  'sort_order' => 6],
        ['name' => 'Team Player',          'description' => 'Reach the top 10 leaderboard',              'icon' => 'fa-users',          'criteria_type' => 'leaderboard_top10',   'criteria_value' => 1,  'sort_order' => 7],
        ['name' => 'Century Club',         'description' => 'Earn 1,000 total points',                   'icon' => 'fa-star',           'criteria_type' => 'points_total',        'criteria_value' => 1000, 'sort_order' => 8],
        ['name' => 'Scholar',              'description' => 'Complete 25 courses',                        'icon' => 'fa-graduation-cap', 'criteria_type' => 'courses_completed',   'criteria_value' => 25, 'sort_order' => 9],
        ['name' => 'Elite Learner',        'description' => 'Earn 10,000 total points',                   'icon' => 'fa-diamond',        'criteria_type' => 'points_total',        'criteria_value' => 10000, 'sort_order' => 10],
    ];

    $now = time();
    foreach ($badges as $badge) {
        $badge['timecreated'] = $now;
        $DB->insert_record('local_sentientia_badges', (object)$badge);
    }
}

/**
 * Get gamification summary for a user (used by dashboard).
 */
function local_sentientia_gamification_get_summary(int $userid): array {
    $total = \local_sentientia_gamification\points_manager::get_total($userid);
    $today = \local_sentientia_gamification\points_manager::get_today($userid);
    $level = \local_sentientia_gamification\points_manager::get_level($total);
    $badges = \local_sentientia_gamification\badge_manager::get_user_badges($userid);
    $rank = \local_sentientia_gamification\leaderboard::get_rank($userid);

    global $DB;
    $streak = $DB->get_record('local_sentientia_streaks', ['userid' => $userid]);

    return [
        'total_points'   => number_format($total),
        'today_points'   => $today,
        'level_name'     => $level['name'],
        'level_number'   => $level['level'],
        'level_color'    => $level['color'],
        'level_progress' => $level['progress'],
        'points_to_next' => number_format($level['next']),
        'current_streak' => $streak->current_streak ?? 0,
        'longest_streak' => $streak->longest_streak ?? 0,
        'badge_count'    => count($badges),
        'badges'         => $badges,
        'rank'           => $rank,
        'has_badges'     => !empty($badges),
        'has_streak'     => ($streak->current_streak ?? 0) > 0,
    ];
}
