<?php
/**
 * Event observer — awards points when learning events occur.
 *
 * @package    local_airpay_gamification
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_gamification;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Course completed — award 100 points (+ 150 bonus for first course).
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB;

        $userid   = $event->relateduserid;
        $courseid = $event->courseid;
        $course   = $DB->get_record('course', ['id' => $courseid], 'id, fullname');

        // Award course completion points.
        $desc = $course ? format_string($course->fullname) : 'Course #' . $courseid;
        points_manager::award($userid, 'course_completed', $courseid, 'Completed: ' . $desc);

        // Check if this is the user's first ever course completion.
        $completioncount = $DB->count_records('course_completions', [
            'userid' => $userid,
        ]);
        if ($completioncount <= 1) {
            points_manager::award($userid, 'first_course', $courseid, 'First course completed!');
        }
    }

    /**
     * Quiz submitted — award 50 points for pass, 100 for perfect.
     */
    public static function quiz_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        $attemptid = $event->objectid;
        $userid    = $event->userid;

        // Get attempt and quiz details.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
        if (!$attempt) {
            return;
        }

        $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
        if (!$quiz) {
            return;
        }

        // Calculate percentage.
        $grade    = $attempt->sumgrades ?? 0;
        $maxgrade = $quiz->sumgrades ?? 0;
        if ($maxgrade <= 0) {
            return;
        }

        $percentage = ($grade / $maxgrade) * 100;

        // Award points based on score.
        if ($percentage >= 100) {
            points_manager::award($userid, 'quiz_perfect', $quiz->course,
                'Perfect score on: ' . format_string($quiz->name));
        } else if ($percentage >= 70) {
            points_manager::award($userid, 'quiz_passed', $quiz->course,
                'Passed: ' . format_string($quiz->name) . ' (' . round($percentage) . '%)');
        }
        // Below 70% = no points (motivates retrying).
    }

    /**
     * User logged in — award 10 points for daily login + streak management.
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB;

        $userid = $event->userid;

        // Skip admin and guest.
        if (is_siteadmin($userid) || isguestuser($userid)) {
            return;
        }

        $today = date('Y-m-d');

        // Get or create streak record.
        $streak = $DB->get_record('local_airpay_streaks', ['userid' => $userid]);
        if (!$streak) {
            $streak = (object)[
                'userid'          => $userid,
                'current_streak'  => 0,
                'longest_streak'  => 0,
                'last_login_date' => '',
                'total_points'    => 0,
                'timemodified'    => time(),
            ];
            $streak->id = $DB->insert_record('local_airpay_streaks', $streak);
        }

        // Already logged in today? No duplicate points.
        if ($streak->last_login_date === $today) {
            return;
        }

        // Check if yesterday was the last login (streak continues).
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        if ($streak->last_login_date === $yesterday) {
            // Streak continues.
            $streak->current_streak++;
        } else {
            // Streak broken — reset to 1.
            $streak->current_streak = 1;
        }

        // Update longest streak.
        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_login_date = $today;
        $streak->timemodified    = time();
        $DB->update_record('local_airpay_streaks', $streak);

        // Award daily login points.
        points_manager::award($userid, 'daily_login', null, 'Day ' . $streak->current_streak . ' streak');

        // Award streak bonuses.
        if ($streak->current_streak == 7) {
            points_manager::award($userid, 'streak_7', null, '7-day learning streak!');
        }
        if ($streak->current_streak == 30) {
            points_manager::award($userid, 'streak_30', null, '30-day learning streak!');
        }
    }
}
