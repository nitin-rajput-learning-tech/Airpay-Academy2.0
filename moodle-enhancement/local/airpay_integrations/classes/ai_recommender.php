<?php
/**
 * AI Course Recommendation Engine.
 * Generates personalised course recommendations based on:
 * 1. Completion history (what categories the user has completed)
 * 2. Skill gaps (skills not yet covered)
 * 3. Peer patterns (what colleagues in same department completed)
 *
 * Only active when ai_enable = 1 AND ai_recommendations_enable = 1.
 *
 * @package    local_airpay_integrations
 * @copyright  2026 Airpay Payment Services
 */

namespace local_airpay_integrations;

defined('MOODLE_INTERNAL') || die();

class ai_recommender {

    /**
     * Check if AI recommendations are enabled.
     */
    public static function is_enabled(): bool {
        return !empty(get_config('local_airpay_integrations', 'ai_enable'))
            && !empty(get_config('local_airpay_integrations', 'ai_recommendations_enable'));
    }

    /**
     * Inspect whether the BizLMS-only profile fields that two of the four
     * recommendation strategies depend on are present in the schema.
     *
     * Strategies that need BizLMS fields:
     *   recommend_by_skills  — needs {course}.open_skill
     *   recommend_by_peers   — needs {user}.open_departmentid
     *
     * If the fields are missing, those strategies silently return [] (the
     * try/catch in this class) — recommendations degrade to category-based
     * + popular-only. The admin notice surfaced in settings.php is what
     * makes that degradation visible to the operator.
     *
     * @return array{course_open_skill:bool, user_open_departmentid:bool, all_present:bool}
     */
    public static function bizlms_fields_status(): array {
        global $DB;
        $manager = $DB->get_manager();

        $hasskill = $manager->field_exists('course',
            new \xmldb_field('open_skill', XMLDB_TYPE_INTEGER, '10'));
        $hasdept  = $manager->field_exists('user',
            new \xmldb_field('open_departmentid', XMLDB_TYPE_INTEGER, '10'));

        return [
            'course_open_skill'      => $hasskill,
            'user_open_departmentid' => $hasdept,
            'all_present'            => $hasskill && $hasdept,
        ];
    }

    /**
     * Get course recommendations for a user.
     *
     * @param int $userid User ID
     * @param int $limit Max recommendations
     * @return array of course objects with 'score' and 'reason'
     */
    public static function get_recommendations(int $userid, int $limit = 5): array {
        global $DB;

        if (!self::is_enabled()) {
            return [];
        }

        // Get user's enrolled course IDs.
        $enrolledcourses = enrol_get_all_users_courses($userid, true);
        $enrolledids = array_keys($enrolledcourses);

        if (empty($enrolledids)) {
            // New user — recommend popular courses.
            return self::get_popular_courses($limit);
        }

        $recommendations = [];

        // Strategy 1: Category-based (courses in same categories the user is already in).
        $categoryrecs = self::recommend_by_category($userid, $enrolledids, $limit);
        foreach ($categoryrecs as $rec) {
            $rec->reason = 'Based on your learning categories';
            $recommendations[$rec->id] = $rec;
        }

        // Strategy 2: Skill-gap (courses covering skills user hasn't learned yet).
        $skillrecs = self::recommend_by_skills($userid, $enrolledids, $limit);
        foreach ($skillrecs as $rec) {
            if (!isset($recommendations[$rec->id])) {
                $rec->reason = 'Covers skills you haven\'t explored';
                $recommendations[$rec->id] = $rec;
            }
        }

        // Strategy 3: Peer-based (what colleagues in same department completed).
        $peerrecs = self::recommend_by_peers($userid, $enrolledids, $limit);
        foreach ($peerrecs as $rec) {
            if (!isset($recommendations[$rec->id])) {
                $rec->reason = 'Popular with your colleagues';
                $recommendations[$rec->id] = $rec;
            }
        }

        // Sort by score (higher = better recommendation).
        usort($recommendations, function($a, $b) {
            return ($b->score ?? 0) - ($a->score ?? 0);
        });

        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Recommend courses from the same categories as user's enrolled courses.
     */
    private static function recommend_by_category(int $userid, array $enrolledids, int $limit): array {
        global $DB;

        if (empty($enrolledids)) return [];

        $categories = $DB->get_fieldset_sql(
            "SELECT DISTINCT category FROM {course} WHERE id IN (" .
            implode(',', array_map('intval', $enrolledids)) . ")"
        );

        if (empty($categories)) return [];

        [$catsql, $catparams] = $DB->get_in_or_equal($categories, SQL_PARAMS_NAMED, 'cat');
        [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
        $params = array_merge($catparams, $exparams);

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.summary, c.category, c.timecreated
               FROM {course} c
              WHERE c.category $catsql AND c.id $exsql
                AND c.visible = 1 AND c.id > 1
           ORDER BY c.timecreated DESC",
            $params, 0, $limit);

        foreach ($courses as $c) {
            $c->score = 70; // category match = 70 points
        }

        return $courses;
    }

    /**
     * Recommend courses covering skills the user hasn't learned.
     * Uses BizLMS skill repository if available.
     */
    private static function recommend_by_skills(int $userid, array $enrolledids, int $limit): array {
        global $DB;

        // Check if skill tables exist (BizLMS).
        try {
            $userskills = $DB->get_fieldset_sql(
                "SELECT DISTINCT c.open_skill FROM {course} c
                  WHERE c.id IN (" . implode(',', array_map('intval', $enrolledids)) . ")
                    AND c.open_skill > 0"
            );

            if (empty($userskills)) return [];

            [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
            [$skillsql, $skillparams] = $DB->get_in_or_equal($userskills, SQL_PARAMS_NAMED, 'sk', false);
            $params = array_merge($exparams, $skillparams);

            $courses = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.summary, c.category
                   FROM {course} c
                  WHERE c.id $exsql AND c.open_skill $skillsql
                    AND c.open_skill > 0 AND c.visible = 1 AND c.id > 1
               ORDER BY c.timecreated DESC",
                $params, 0, $limit);

            foreach ($courses as $c) {
                $c->score = 80; // skill gap = 80 points
            }

            return $courses;
        } catch (\Exception $e) {
            return []; // Skill tables may not exist.
        }
    }

    /**
     * Recommend courses popular with colleagues in the same department.
     */
    private static function recommend_by_peers(int $userid, array $enrolledids, int $limit): array {
        global $DB;

        // Get user's department (BizLMS open_departmentid).
        try {
            $userdept = $DB->get_field('user', 'open_departmentid', ['id' => $userid]);
            if (empty($userdept)) return [];

            // Find courses that colleagues completed but this user hasn't.
            [$exsql, $exparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED, 'ex', false);
            $params = array_merge(['dept' => $userdept, 'uid' => $userid], $exparams);

            $courses = $DB->get_records_sql(
                "SELECT c.id, c.fullname, c.summary, c.category, COUNT(cc.id) as peercount
                   FROM {course} c
                   JOIN {course_completions} cc ON cc.course = c.id AND cc.timecompleted IS NOT NULL
                   JOIN {user} u ON u.id = cc.userid AND u.open_departmentid = :dept AND u.id != :uid
                  WHERE c.id $exsql AND c.visible = 1 AND c.id > 1
               GROUP BY c.id, c.fullname, c.summary, c.category
               ORDER BY peercount DESC",
                $params, 0, $limit);

            foreach ($courses as $c) {
                $c->score = 60 + min(20, (int)$c->peercount * 5); // peer count bonus
            }

            return $courses;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Fallback: recommend most popular courses overall.
     */
    private static function get_popular_courses(int $limit): array {
        global $DB;

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.summary, c.category, COUNT(ue.id) as enrolcount
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
              WHERE c.visible = 1 AND c.id > 1
           GROUP BY c.id, c.fullname, c.summary, c.category
           ORDER BY enrolcount DESC",
            [], 0, $limit);

        foreach ($courses as $c) {
            $c->score = 50;
            $c->reason = 'Popular on the platform';
        }

        return $courses;
    }
}
