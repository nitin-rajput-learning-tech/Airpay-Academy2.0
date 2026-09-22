<?php
/**
 * Leaderboard — queries and renders leaderboard data.
 *
 * @package    local_sentientia_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_gamification;

defined('MOODLE_INTERNAL') || die();

class leaderboard {

    /**
     * Get global leaderboard (top N users by total points).
     */
    public static function get_global(int $limit = 10, string $orgpath = ''): array {
        global $DB, $USER;

        // Scope to user's tenant unless explicit orgpath given.
        $orgfilter = '';
        $params = [];
        if (empty($orgpath)) {
            $orgpath = \local_sentientia_org\tenant_manager::get_tenant_path();
        }
        if (!empty($orgpath)) {
            // '/1' . '%' also matched '/177', putting another tenant's learners on
            // this tenant's leaderboard. Exact-or-descendant instead.
            [$orgsql, $params] = \local_sentientia_platform\tenant::path_descendant_filter(
                $orgpath, 'u', 'open_path', 'lborg');
            $orgfilter = 'AND ' . $orgsql;
        }

        return array_values($DB->get_records_sql(
            "SELECT s.userid, s.total_points, s.current_streak, s.longest_streak,
                    u.firstname, u.lastname, u.open_path
               FROM {local_sentientia_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE u.deleted = 0 AND u.suspended = 0 AND s.total_points > 0
                    $orgfilter
           ORDER BY s.total_points DESC",
            $params, 0, $limit
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
        // '/1' . '%' also matched '/177': neighbour ranking spanned tenants.
        [$nboursql, $nbourargs] = \local_sentientia_platform\tenant::path_descendant_filter(
            $orgpath, 'u', 'open_path', 'nbour');

        return array_values($DB->get_records_sql(
            "SELECT s.userid, s.total_points, s.current_streak, s.longest_streak,
                    u.firstname, u.lastname, u.open_path
               FROM {local_sentientia_streaks} s
               JOIN {user} u ON u.id = s.userid
              WHERE u.deleted = 0 AND u.suspended = 0 AND s.total_points > 0
                AND {$nboursql}
           ORDER BY s.total_points DESC",
            $nbourargs, 0, $limit
        ));
    }

    /**
     * Get user's rank in global leaderboard.
     */
    public static function get_rank(int $userid): int {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], 'open_path');
        $userpoints = $DB->get_field('local_sentientia_streaks', 'total_points', ['userid' => $userid]);
        if (!$userpoints) {
            return 0;
        }

        // Scope rank to user's own tenant so they're ranked within their org.
        $orgfilter = '';
        $params = ['pts' => $userpoints];
        if ($user && !empty($user->open_path)) {
            $parts = explode('/', $user->open_path);
            $org = $parts[1] ?? '';
            if (!empty($org)) {
                // '/1' . '%' also matched '/177': the rank denominator counted another tenant's users.
                [$ranksql, $params] = \local_sentientia_platform\tenant::path_descendant_filter(
                    '/' . $org, '', 'open_path', 'rankorg');
                $orgfilter = "AND s.userid IN (SELECT id FROM {user} "
                    . "WHERE {$ranksql} AND deleted = 0)";
            }
        }

        $rank = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_streaks} s
             WHERE s.total_points > :pts $orgfilter",
            $params
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
