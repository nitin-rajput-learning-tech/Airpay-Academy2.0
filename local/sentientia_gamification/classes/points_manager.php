<?php
/**
 * Points Manager — handles all point operations.
 *
 * @package    local_sentientia_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_gamification;

defined('MOODLE_INTERNAL') || die();

class points_manager {

    /** Point values for each action. */
    const POINTS = [
        'course_completed'      => 100,
        'quiz_passed'           => 50,
        'quiz_perfect'          => 100,
        'daily_login'           => 10,
        'streak_7'              => 50,
        'streak_30'             => 200,
        'first_course'          => 150,
        'learning_path'         => 500,
    ];

    /**
     * Award points to a user.
     *
     * @param int    $userid     User ID
     * @param string $action     Action type (key from POINTS constant)
     * @param int    $courseid   Course ID (optional)
     * @param string $description Human-readable description
     * @return int The points awarded
     */
    public static function award(int $userid, string $action, ?int $courseid = null, string $description = ''): int {
        global $DB;

        $points = self::POINTS[$action] ?? 0;
        if ($points <= 0) {
            return 0;
        }

        // Prevent duplicate awards for the same action + course combo (within 1 hour).
        $recent = $DB->record_exists_select('local_sentientia_points_log',
            'userid = :uid AND action = :act AND courseid = :cid AND timecreated > :since',
            [
                'uid'   => $userid,
                'act'   => $action,
                'cid'   => $courseid ?? 0,
                'since' => time() - 3600,
            ]);
        if ($recent && !in_array($action, ['daily_login', 'streak_7', 'streak_30'])) {
            return 0; // Already awarded recently for this action+course.
        }

        // Insert points log.
        $record = new \stdClass();
        $record->userid      = $userid;
        $record->action       = $action;
        $record->points       = $points;
        $record->courseid     = $courseid;
        $record->description  = $description ?: self::get_action_label($action);
        $record->timecreated  = time();
        $DB->insert_record('local_sentientia_points_log', $record);

        // Update cached total in streaks table.
        self::update_total_points($userid);

        // Check for badge unlocks after awarding points.
        badge_manager::check_badges($userid);

        return $points;
    }

    /**
     * Get total points for a user.
     */
    public static function get_total(int $userid): int {
        global $DB;
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(points), 0) FROM {local_sentientia_points_log} WHERE userid = :uid",
            ['uid' => $userid]
        );
    }

    /**
     * Get points earned today.
     */
    public static function get_today(int $userid): int {
        global $DB;
        $today = strtotime('today');
        return (int)$DB->get_field_sql(
            "SELECT COALESCE(SUM(points), 0) FROM {local_sentientia_points_log}
             WHERE userid = :uid AND timecreated >= :today",
            ['uid' => $userid, 'today' => $today]
        );
    }

    /**
     * Get user's level based on total points.
     * Level 1: 0-499, Level 2: 500-1499, Level 3: 1500-3999, Level 4: 4000-9999, Level 5: 10000+
     */
    public static function get_level(int $totalpoints): array {
        $levels = [
            ['level' => 1, 'name' => 'Beginner',    'min' => 0,     'max' => 499,   'color' => '#6b7280'],
            ['level' => 2, 'name' => 'Learner',     'min' => 500,   'max' => 1499,  'color' => '#0066A7'],
            ['level' => 3, 'name' => 'Achiever',    'min' => 1500,  'max' => 3999,  'color' => '#1985DD'],
            ['level' => 4, 'name' => 'Expert',      'min' => 4000,  'max' => 9999,  'color' => '#7c3aed'],
            ['level' => 5, 'name' => 'Master',      'min' => 10000, 'max' => 999999,'color' => '#d97706'],
        ];

        foreach ($levels as $l) {
            if ($totalpoints >= $l['min'] && $totalpoints <= $l['max']) {
                $progress = ($totalpoints - $l['min']) / max(1, $l['max'] - $l['min']) * 100;
                return array_merge($l, [
                    'points'   => $totalpoints,
                    'progress' => round($progress),
                    'next'     => $l['max'] + 1 - $totalpoints,
                ]);
            }
        }
        return $levels[4]; // Default to highest.
    }

    /**
     * Get recent points history for a user.
     */
    public static function get_history(int $userid, int $limit = 10): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT id, action, points, courseid, description, timecreated
               FROM {local_sentientia_points_log}
              WHERE userid = :uid
           ORDER BY timecreated DESC",
            ['uid' => $userid], 0, $limit
        ));
    }

    /**
     * Update cached total points in streaks table.
     */
    private static function update_total_points(int $userid): void {
        global $DB;
        $total = self::get_total($userid);
        $streak = $DB->get_record('local_sentientia_streaks', ['userid' => $userid]);
        if ($streak) {
            $streak->total_points = $total;
            $streak->timemodified = time();
            $DB->update_record('local_sentientia_streaks', $streak);
        } else {
            $DB->insert_record('local_sentientia_streaks', (object)[
                'userid'          => $userid,
                'current_streak'  => 0,
                'longest_streak'  => 0,
                'last_login_date' => '',
                'total_points'    => $total,
                'timemodified'    => time(),
            ]);
        }
    }

    /**
     * Human-readable label for an action.
     */
    private static function get_action_label(string $action): string {
        $labels = [
            'course_completed' => 'Course completed',
            'quiz_passed'      => 'Quiz passed',
            'quiz_perfect'     => 'Perfect quiz score',
            'daily_login'      => 'Daily login',
            'streak_7'         => '7-day streak bonus',
            'streak_30'        => '30-day streak bonus',
            'first_course'     => 'First course completed',
            'learning_path'    => 'Learning path completed',
        ];
        return $labels[$action] ?? $action;
    }
}
