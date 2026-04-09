<?php
/**
 * Leaderboard — queries and renders leaderboard data.
 *
 * @package    local_airpay_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_gamification;

defined('MOODLE_INTERNAL') || die();

class leaderboard {

    /**
     * Get global leaderboard (top N users by total points).
     */
    public static function get_global(int $limit = 10): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT s.userid, s.total_points, s.current_streak, s.longest_streak,
                    u.firstname, u.lastname, u.open_path
               FROM {local_airpay_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE u.deleted = 0 AND u.suspended = 0 AND s.total_points > 0
           ORDER BY s.total_points DESC",
            [], 0, $limit
        ));
    }

    /**
     * Get department leaderboard (same costcenter path prefix).
     */
    public static function get_department(int $userid, int $limit = 10): array {
        global $DB, $USER;

        // Get user's org path prefix (top-level org).
        $user = $DB->get_record('user', ['id' => $userid], 'open_path');
        if (!$user || empty($user->open_path)) {
            return self::get_global($limit);
        }

        // Extract top-level org path (e.g., /1 from /1/2/3).
        $parts = explode('/', trim($user->open_path, '/'));
        $orgpath = '/' . ($parts[0] ?? '');

        return array_values($DB->get_records_sql(
            "SELECT s.userid, s.total_points, s.current_streak, s.longest_streak,
                    u.firstname, u.lastname, u.open_path
               FROM {local_airpay_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE u.deleted = 0 AND u.suspended = 0 AND s.total_points > 0
                AND u.open_path LIKE :pathprefix
           ORDER BY s.total_points DESC",
            ['pathprefix' => $orgpath . '%'], 0, $limit
        ));
    }

    /**
     * Get user's rank in global leaderboard.
     */
    public static function get_rank(int $userid): int {
        global $DB;
        $userpoints = $DB->get_field('local_airpay_streaks', 'total_points', ['userid' => $userid]);
        if (!$userpoints) {
            return 0;
        }
        $rank = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_streaks} WHERE total_points > :pts",
            ['pts' => $userpoints]
        );
        return $rank + 1;
    }

    /**
     * Get leaderboard data formatted for Mustache template.
     */
    public static function get_template_data(int $userid, string $scope = 'department', int $limit = 5): array {
        $entries = ($scope === 'global')
            ? self::get_global($limit)
            : self::get_department($userid, $limit);

        $data = [];
        $rank = 1;
        foreach ($entries as $entry) {
            $level = points_manager::get_level($entry->total_points);
            $data[] = [
                'rank'          => $rank,
                'firstname'     => format_string($entry->firstname),
                'lastname'      => format_string($entry->lastname),
                'initials'      => strtoupper(substr($entry->firstname, 0, 1) . substr($entry->lastname, 0, 1)),
                'points'        => number_format($entry->total_points),
                'streak'        => $entry->current_streak,
                'level_name'    => $level['name'],
                'level_color'   => $level['color'],
                'is_current_user' => ($entry->userid == $userid),
                'is_top3'       => ($rank <= 3),
            ];
            $rank++;
        }

        return [
            'entries'    => $data,
            'has_entries' => !empty($data),
            'user_rank'  => self::get_rank($userid),
            'scope'      => $scope,
        ];
    }
}
