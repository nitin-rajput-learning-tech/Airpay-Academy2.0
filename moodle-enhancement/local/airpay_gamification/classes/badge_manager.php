<?php
/**
 * Badge Manager — checks criteria and awards badges.
 *
 * @package    local_airpay_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_gamification;

defined('MOODLE_INTERNAL') || die();

class badge_manager {

    /**
     * Check all badges for a user and award any newly earned ones.
     */
    public static function check_badges(int $userid): array {
        global $DB;

        $badges = $DB->get_records('local_airpay_badges', [], 'sort_order ASC');
        $earned = [];

        foreach ($badges as $badge) {
            // Skip if already earned.
            if ($DB->record_exists('local_airpay_user_badges', ['userid' => $userid, 'badgeid' => $badge->id])) {
                continue;
            }

            // Check criteria.
            if (self::meets_criteria($userid, $badge->criteria_type, $badge->criteria_value)) {
                $DB->insert_record('local_airpay_user_badges', (object)[
                    'userid'     => $userid,
                    'badgeid'    => $badge->id,
                    'timeearned' => time(),
                ]);
                $earned[] = $badge;
            }
        }

        return $earned;
    }

    /**
     * Check if a user meets badge criteria.
     */
    private static function meets_criteria(int $userid, string $type, int $value): bool {
        global $DB;

        switch ($type) {
            case 'courses_completed':
                $count = $DB->count_records_select('course_completions',
                    'userid = :uid AND timecompleted IS NOT NULL',
                    ['uid' => $userid]);
                return $count >= $value;

            case 'quizzes_perfect':
                $count = $DB->count_records('local_airpay_points_log', [
                    'userid' => $userid,
                    'action' => 'quiz_perfect',
                ]);
                return $count >= $value;

            case 'streak_days':
                $streak = $DB->get_record('local_airpay_streaks', ['userid' => $userid]);
                return $streak && $streak->longest_streak >= $value;

            case 'points_total':
                $total = points_manager::get_total($userid);
                return $total >= $value;

            case 'first_course':
                return $DB->record_exists_select('course_completions',
                    'userid = :uid AND timecompleted IS NOT NULL',
                    ['uid' => $userid]);

            case 'compliance_complete':
                // All courses with enddate (mandatory) are completed.
                $mandatory = $DB->count_records_select('course',
                    'enddate > 0 AND visible = 1 AND id > 1');
                if ($mandatory == 0) {
                    return false;
                }
                $completed = $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT cc.course)
                       FROM {course_completions} cc
                       JOIN {course} c ON c.id = cc.course
                      WHERE cc.userid = :uid AND cc.timecompleted IS NOT NULL
                        AND c.enddate > 0",
                    ['uid' => $userid]);
                return $completed >= $mandatory;

            case 'leaderboard_top10':
                // Check if user is in top 10 by total points.
                $rank = $DB->count_records_sql(
                    "SELECT COUNT(*) FROM {local_airpay_streaks}
                     WHERE total_points > (SELECT total_points FROM {local_airpay_streaks} WHERE userid = :uid)",
                    ['uid' => $userid]);
                return $rank < 10;

            default:
                return false;
        }
    }

    /**
     * Get all badges earned by a user.
     */
    public static function get_user_badges(int $userid): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT b.id, b.name, b.description, b.icon, b.criteria_type, b.criteria_value,
                    ub.timeearned
               FROM {local_airpay_user_badges} ub
               JOIN {local_airpay_badges} b ON b.id = ub.badgeid
              WHERE ub.userid = :uid
           ORDER BY ub.timeearned DESC",
            ['uid' => $userid]
        ));
    }

    /**
     * Get all available badges with earned status for a user.
     */
    public static function get_all_badges_for_user(int $userid): array {
        global $DB;
        $all = $DB->get_records('local_airpay_badges', [], 'sort_order ASC');
        $earned_ids = $DB->get_fieldset_select('local_airpay_user_badges',
            'badgeid', 'userid = :uid', ['uid' => $userid]);

        $result = [];
        foreach ($all as $badge) {
            $badge->earned = in_array($badge->id, $earned_ids);
            if ($badge->earned) {
                $ub = $DB->get_record('local_airpay_user_badges',
                    ['userid' => $userid, 'badgeid' => $badge->id]);
                $badge->timeearned = $ub->timeearned ?? 0;
            }
            $result[] = $badge;
        }
        return $result;
    }
}
