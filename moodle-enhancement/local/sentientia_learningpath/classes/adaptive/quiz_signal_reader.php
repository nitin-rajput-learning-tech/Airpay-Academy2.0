<?php
namespace local_sentientia_learningpath\adaptive;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads mod_quiz attempt data to produce per-course score signals.
 *
 * P0.2 (2026-06-16) — Adaptive Learning Journeys.
 *
 * The engine needs to know: "did this learner do well or poorly on the
 * quiz in course X?" This class queries the quiz attempt tables
 * (mdl_quiz_attempts) to get the best or most-recent finalised grade
 * for a user in any quiz inside a given course.
 *
 * Score is normalised to 0–100. When a course has multiple quizzes, the
 * LOWEST passing grade is used (conservative: remediate if any quiz was
 * failed). This mirrors Moodle's course-completion grade condition logic.
 *
 * @package local_sentientia_learningpath
 */
class quiz_signal_reader {

    /**
     * Get the best quiz score a user achieved across all quizzes in a
     * course, as a percentage (0–100).
     *
     * Returns null when:
     *   - No quizzes in the course
     *   - No finalised attempts
     *   - mdl_quiz or mdl_quiz_attempts tables don't exist (Moodle version guard)
     *
     * @param int $userid
     * @param int $courseid
     * @return float|null  Percentage 0–100 or null
     */
    public static function best_score(int $userid, int $courseid): ?float {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('quiz') || !$dbman->table_exists('quiz_attempts')) {
            return null;
        }

        // Get the best finalised attempt grade for any quiz in this course.
        // grade in mdl_quiz_attempts is on the quiz's grademax scale.
        // We also need the quiz's sumgrades (max possible) to normalise.
        $record = $DB->get_record_sql(
            "SELECT qa.id, qa.sumgrades, q.sumgrades AS maxgrade
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid    = :uid
                AND q.course     = :cid
                AND qa.state     = :state
                AND q.sumgrades  > 0
           ORDER BY (qa.sumgrades / q.sumgrades) DESC
              LIMIT 1",
            [
                'uid'   => $userid,
                'cid'   => $courseid,
                'state' => 'finished',
            ]
        );

        if (!$record) {
            return null;
        }

        $maxgrade = (float) $record->maxgrade;
        if ($maxgrade <= 0) {
            return null;
        }

        return round(((float) $record->sumgrades / $maxgrade) * 100, 2);
    }

    /**
     * Get the lowest quiz score across all quizzes in a course
     * (conservative — used to detect learners who need remediation).
     *
     * Returns null when there are no finalised attempts.
     *
     * @param int $userid
     * @param int $courseid
     * @return float|null
     */
    public static function worst_score(int $userid, int $courseid): ?float {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('quiz') || !$dbman->table_exists('quiz_attempts')) {
            return null;
        }

        $record = $DB->get_record_sql(
            "SELECT qa.id, qa.sumgrades, q.sumgrades AS maxgrade
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid    = :uid
                AND q.course     = :cid
                AND qa.state     = :state
                AND q.sumgrades  > 0
           ORDER BY (qa.sumgrades / q.sumgrades) ASC
              LIMIT 1",
            [
                'uid'   => $userid,
                'cid'   => $courseid,
                'state' => 'finished',
            ]
        );

        if (!$record) {
            return null;
        }

        $maxgrade = (float) $record->maxgrade;
        if ($maxgrade <= 0) {
            return null;
        }

        return round(((float) $record->sumgrades / $maxgrade) * 100, 2);
    }

    /**
     * Determine the trigger score to use for a given course.
     *
     * For pivot decisions we use:
     *   - worst_score if present (conservative — catch failures)
     *   - best_score as fallback (when there's only one quiz)
     *   - null if no data
     *
     * @param int $userid
     * @param int $courseid
     * @return float|null
     */
    public static function trigger_score(int $userid, int $courseid): ?float {
        $worst = self::worst_score($userid, $courseid);
        if ($worst !== null) {
            return $worst;
        }
        return self::best_score($userid, $courseid);
    }
}
