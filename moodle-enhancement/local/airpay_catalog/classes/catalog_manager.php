<?php
/**
 * Catalog Manager — queries courses with filters, sort, tenant scoping, recommendations.
 *
 * @package    local_airpay_catalog
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_catalog;

defined('MOODLE_INTERNAL') || die();

class catalog_manager {

    /**
     * Get courses for the catalog with filters and pagination.
     *
     * @param int    $userid      Current user ID
     * @param string $search      Search query
     * @param array  $filters     Filter criteria (category, type, level, status)
     * @param string $sort        Sort field (newest, popular, name, rating)
     * @param int    $page        Page number (0-based)
     * @param int    $perpage     Results per page
     * @return array {courses: [], total: int, page: int, perpage: int}
     */
    public static function get_courses(int $userid, string $search = '', array $filters = [],
                                        string $sort = 'newest', int $page = 0, int $perpage = 12): array {
        global $DB, $USER;

        // Build WHERE conditions.
        $conditions = ['c.visible = 1', 'c.id > 1'];
        $params = [];

        // Tenant scoping — only show courses in user's org.
        $userpath = $USER->open_path ?? '';
        if (!empty($userpath)) {
            $parts = explode('/', trim($userpath, '/'));
            $orgpath = '/' . ($parts[0] ?? '');
            $conditions[] = "c.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        // Search filter.
        if (!empty($search)) {
            $search_escaped = $DB->sql_like_escape($search);
            $conditions[] = '(' . $DB->sql_like('c.fullname', ':search1', false) . ' OR ' .
                            $DB->sql_like('c.shortname', ':search2', false) . ' OR ' .
                            $DB->sql_like('c.summary', ':search3', false) . ')';
            $params['search1'] = '%' . $search_escaped . '%';
            $params['search2'] = '%' . $search_escaped . '%';
            $params['search3'] = '%' . $search_escaped . '%';
        }

        // Category filter.
        if (!empty($filters['category'])) {
            $conditions[] = 'c.category = :catid';
            $params['catid'] = (int)$filters['category'];
        }

        // Difficulty level filter (from open_level).
        if (!empty($filters['level'])) {
            $conditions[] = 'c.open_level = :level';
            $params['level'] = (int)$filters['level'];
        }

        // Enrollment status filter.
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'enrolled') {
                $conditions[] = "EXISTS (SELECT 1 FROM {user_enrolments} ue2
                                 JOIN {enrol} e2 ON e2.id = ue2.enrolid
                                 WHERE e2.courseid = c.id AND ue2.userid = :statusuid)";
                $params['statusuid'] = $userid;
            } else if ($filters['status'] === 'not_enrolled') {
                $conditions[] = "NOT EXISTS (SELECT 1 FROM {user_enrolments} ue2
                                 JOIN {enrol} e2 ON e2.id = ue2.enrolid
                                 WHERE e2.courseid = c.id AND ue2.userid = :statusuid)";
                $params['statusuid'] = $userid;
            }
        }

        $where = implode(' AND ', $conditions);

        // Sort.
        $orderby = match($sort) {
            'popular' => 'enrolled_count DESC',
            'name'    => 'c.fullname ASC',
            'rating'  => 'c.fullname ASC', // TODO: add actual rating sort when rating table is available
            default   => 'c.timecreated DESC',
        };

        // Count total.
        $total = $DB->count_records_sql(
            "SELECT COUNT(c.id) FROM {course} c WHERE $where", $params);

        // Fetch courses with enrollment count.
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname,
                    (SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = c.id) as enrolled_count
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE $where
           ORDER BY $orderby",
            $params, $page * $perpage, $perpage);

        // Format for template.
        $formatted = [];
        foreach ($courses as $course) {
            $formatted[] = self::format_course($course, $userid);
        }

        return [
            'courses'  => $formatted,
            'total'    => $total,
            'page'     => $page,
            'perpage'  => $perpage,
            'pages'    => ceil($total / $perpage),
            'has_more' => (($page + 1) * $perpage) < $total,
        ];
    }

    /**
     * Get trending courses (highest enrollment in last 30 days).
     */
    public static function get_trending(int $userid, int $limit = 6): array {
        global $DB, $USER;

        $orgfilter = '';
        $params = ['since' => time() - (30 * 86400)];

        $userpath = $USER->open_path ?? '';
        if (!empty($userpath)) {
            $parts = explode('/', trim($userpath, '/'));
            $orgfilter = "AND c.open_path LIKE :orgpath";
            $params['orgpath'] = '/' . ($parts[0] ?? '') . '%';
        }

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname,
                    COUNT(ue.id) as recent_enrolments
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.timestart > :since
              WHERE c.visible = 1 AND c.id > 1 $orgfilter
           GROUP BY c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype, cc.name
           ORDER BY recent_enrolments DESC",
            $params, 0, $limit);

        return array_map(fn($c) => self::format_course($c, $userid), array_values($courses));
    }

    /**
     * Get new courses (created in last 30 days).
     */
    public static function get_new(int $userid, int $limit = 6): array {
        global $DB, $USER;

        $orgfilter = '';
        $params = ['since' => time() - (30 * 86400)];

        $userpath = $USER->open_path ?? '';
        if (!empty($userpath)) {
            $parts = explode('/', trim($userpath, '/'));
            $orgfilter = "AND c.open_path LIKE :orgpath";
            $params['orgpath'] = '/' . ($parts[0] ?? '') . '%';
        }

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname,
                    0 as enrolled_count
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.visible = 1 AND c.id > 1 AND c.timecreated > :since $orgfilter
           ORDER BY c.timecreated DESC",
            $params, 0, $limit);

        return array_map(fn($c) => self::format_course($c, $userid), array_values($courses));
    }

    /**
     * Get courses in progress (user enrolled but not completed).
     */
    public static function get_in_progress(int $userid, int $limit = 6): array {
        global $DB;

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname, 0 as enrolled_count
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :uid
          LEFT JOIN {course_completions} ccomp ON ccomp.course = c.id AND ccomp.userid = :uid2
              WHERE c.visible = 1 AND c.id > 1
                AND (ccomp.timecompleted IS NULL)
           ORDER BY ue.timestart DESC",
            ['uid' => $userid, 'uid2' => $userid], 0, $limit);

        $formatted = [];
        foreach ($courses as $course) {
            $f = self::format_course($course, $userid);
            $progress = \core_completion\progress::get_course_progress_percentage(
                get_course($course->id), $userid);
            $f['progress'] = $progress !== null ? round($progress) : 0;
            $f['has_progress'] = ($f['progress'] > 0);
            $formatted[] = $f;
        }
        return $formatted;
    }

    /**
     * Get category list with course counts.
     */
    public static function get_categories(): array {
        global $DB, $USER;

        // Scope categories to the user's tenant org if logged in.
        $orgfilter = '';
        $params = [];
        $tenantpath = \local_airpay_org\tenant_manager::get_tenant_path();
        if (!empty($tenantpath)) {
            $orgfilter = " AND c.open_path LIKE :orgpath";
            $params['orgpath'] = $tenantpath . '%';
        }

        return array_values($DB->get_records_sql(
            "SELECT cc.id, cc.name,
                    COUNT(c.id) as course_count
               FROM {course_categories} cc
               JOIN {course} c ON c.category = cc.id AND c.visible = 1 AND c.id > 1
                    $orgfilter
           GROUP BY cc.id, cc.name
             HAVING COUNT(c.id) > 0
           ORDER BY COUNT(c.id) DESC", $params));
    }

    /**
     * Format a course record for Mustache template.
     */
    private static function format_course(\stdClass $course, int $userid): array {
        global $DB, $CFG;

        // Check enrollment status.
        $enrolled = $DB->record_exists_sql(
            "SELECT 1 FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid
             WHERE e.courseid = :cid AND ue.userid = :uid",
            ['cid' => $course->id, 'uid' => $userid]);

        // Check completion.
        $completed = $DB->record_exists_select('course_completions',
            'course = :cid AND userid = :uid AND timecompleted IS NOT NULL',
            ['cid' => $course->id, 'uid' => $userid]);

        // Difficulty level.
        $levels = [1 => 'Beginner', 2 => 'Intermediate', 3 => 'Advanced'];
        $level = $levels[$course->open_level ?? 0] ?? '';

        // Course type.
        $types = [0 => 'E-Learning', 1 => 'E-Learning', 2 => 'Classroom', 3 => 'Exam'];
        $type = $types[$course->open_coursetype ?? 0] ?? 'E-Learning';

        // Time ago for "new" badge.
        $daysold = (time() - $course->timecreated) / 86400;
        $is_new = ($daysold <= 30);

        return [
            'id'            => $course->id,
            'fullname'      => format_string($course->fullname),
            'shortname'     => format_string($course->shortname),
            'summary'       => shorten_text(strip_tags(format_text($course->summary)), 120),
            'categoryname'  => format_string($course->categoryname ?? ''),
            'categoryid'    => $course->category,
            'enrolled_count' => $course->enrolled_count ?? 0,
            'level'         => $level,
            'has_level'     => !empty($level),
            'type'          => $type,
            'is_enrolled'   => $enrolled,
            'is_completed'  => $completed,
            'is_new'        => $is_new,
            'viewurl'       => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'detailurl'     => (new \moodle_url('/local/search/coursedetails.php', ['id' => $course->id]))->out(false),
            'imageurl'      => (new \moodle_url('/theme/image.php/airpayux/local_courses/' .
                               $CFG->themerev . '/courseimg'))->out(false),
        ];
    }
}
