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
     * Derive the viewer's top-level tenant root from $USER->open_path.
     *
     * Returns 0 for site admins (so every tenant-scoped query gets the
     * "no filter" 1=1 from sharing_manager::build_catalog_filter_sql).
     * Returns the integer tenant root for normal tenant-bound users.
     *
     * Sprint C addition — exposed as a single helper so all four
     * catalog query methods compute the tenant the same way.
     *
     * @return int Tenant root (0 = unscoped / site admin)
     */
    private static function viewer_tenant_root(): int {
        global $USER;
        if (function_exists('is_siteadmin') && is_siteadmin()) {
            return 0;
        }
        $path = $USER->open_path ?? '';
        if ($path === '') {
            return 0;
        }
        $parts = explode('/', trim($path, '/'));
        $first = $parts[0] ?? '';
        return ctype_digit($first) ? (int) $first : 0;
    }

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

        // Sprint C: tenant scoping now also UNIONs in shared courses.
        // The viewer sees:
        //   (a) courses inside their tenant tree (the "owned" path), AND
        //   (b) courses an Airpay admin has explicitly shared to their
        //       tenant via local_airpay_courses_tenant_share.
        // Site admins (viewer_tenant=0) get a 1=1 pass-through.
        $viewer_tenant = self::viewer_tenant_root();
        [$tenant_sql, $tenant_params] =
            \local_airpay_courses\sharing_manager::build_catalog_filter_sql(
                'c', $viewer_tenant);
        $conditions[] = $tenant_sql;
        $params = array_merge($params, $tenant_params);

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

        $cache = \cache::make('local_airpay_catalog', 'trending');
        // Sprint C: cache key must include tenant root so a Public learner
        // gets a different cached list from an Airpay learner (the share
        // table membership can differ per tenant).
        $viewer_tenant = self::viewer_tenant_root();
        $cachekey = "tr_{$userid}_{$limit}_t{$viewer_tenant}";
        $cached = $cache->get($cachekey);
        if ($cached !== false) { return $cached; }

        $params = ['since' => time() - (30 * 86400)];
        [$tenant_sql, $tenant_params] =
            \local_airpay_courses\sharing_manager::build_catalog_filter_sql('c', $viewer_tenant);
        $params = array_merge($params, $tenant_params);

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname,
                    COUNT(ue.id) as recent_enrolments
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.timestart > :since
              WHERE c.visible = 1 AND c.id > 1 AND $tenant_sql
           GROUP BY c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype, cc.name
           ORDER BY recent_enrolments DESC",
            $params, 0, $limit);

        $result = array_map(fn($c) => self::format_course($c, $userid), array_values($courses));
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get new courses (created in last 30 days).
     */
    public static function get_new(int $userid, int $limit = 6): array {
        global $DB, $USER;

        $cache = \cache::make('local_airpay_catalog', 'new_courses');
        // Sprint C: cache key tenant-suffixed (share state varies per tenant).
        $viewer_tenant = self::viewer_tenant_root();
        $cachekey = "new_{$userid}_{$limit}_t{$viewer_tenant}";
        $cached = $cache->get($cachekey);
        if ($cached !== false) { return $cached; }

        $params = ['since' => time() - (30 * 86400)];
        [$tenant_sql, $tenant_params] =
            \local_airpay_courses\sharing_manager::build_catalog_filter_sql('c', $viewer_tenant);
        $params = array_merge($params, $tenant_params);

        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.summary, c.category, c.timecreated,
                    c.open_path, c.open_level, c.open_skill, c.open_coursetype,
                    cc.name as categoryname,
                    0 as enrolled_count
               FROM {course} c
               JOIN {course_categories} cc ON cc.id = c.category
              WHERE c.visible = 1 AND c.id > 1 AND c.timecreated > :since AND $tenant_sql
           ORDER BY c.timecreated DESC",
            $params, 0, $limit);

        $result = array_map(fn($c) => self::format_course($c, $userid), array_values($courses));
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get courses in progress (user enrolled but not completed).
     */
    public static function get_in_progress(int $userid, int $limit = 6): array {
        global $DB;

        // Cache: heaviest catalog method (4.2s cold). Per-user key, 5 min TTL.
        $cache = \cache::make('local_airpay_catalog', 'in_progress');
        $cachekey = "ip_{$userid}_{$limit}";
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

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
        $cache->set($cachekey, $formatted);
        return $formatted;
    }

    /**
     * Get category list with course counts.
     */
    public static function get_categories(): array {
        global $DB, $USER;

        // Sprint C: tenant scoping now includes shared courses (i.e. a
        // Public learner's category list includes any Airpay categories
        // whose courses have been shared to Public). Cache key suffixed
        // by viewer_tenant so per-tenant caches stay distinct.
        $viewer_tenant = self::viewer_tenant_root();
        $cache = \cache::make('local_airpay_catalog', 'categories');
        $cachekey = 'cat_t' . $viewer_tenant;
        $cached = $cache->get($cachekey);
        if ($cached !== false) { return $cached; }

        [$tenant_sql, $tenant_params] =
            \local_airpay_courses\sharing_manager::build_catalog_filter_sql('c', $viewer_tenant);

        $result = array_values($DB->get_records_sql(
            "SELECT cc.id, cc.name,
                    COUNT(c.id) as course_count
               FROM {course_categories} cc
               JOIN {course} c ON c.category = cc.id AND c.visible = 1 AND c.id > 1
                    AND $tenant_sql
           GROUP BY cc.id, cc.name
             HAVING COUNT(c.id) > 0
           ORDER BY COUNT(c.id) DESC", $tenant_params));
        $cache->set($cachekey, $result);
        return $result;
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

        // Sprint C: provenance — is this course visible to the viewer
        // because it was SHARED from another tenant (vs. owned)?
        // The badge "Provided by Airpay Academy" appears on cards where
        // the answer is "borrowed".
        $viewer_tenant = self::viewer_tenant_root();
        $is_borrowed = false;
        $provider_tenant_name = '';
        if ($viewer_tenant > 0) {
            // Owned by viewer iff course's open_path is in viewer's tree.
            $course_path = $course->open_path ?? '';
            $exact_path  = '/' . $viewer_tenant;
            $is_owned = ($course_path === $exact_path)
                || (strpos($course_path, $exact_path . '/') === 0);
            if (!$is_owned) {
                // Must be present via an active share row.
                if (\local_airpay_courses\sharing_manager::is_course_shared_to(
                        (int) $course->id, $viewer_tenant)) {
                    $is_borrowed = true;
                    // Resolve provider tenant from course's open_path
                    // first segment.
                    $parts = explode('/', trim($course_path, '/'));
                    $provider_root = isset($parts[0]) && ctype_digit($parts[0])
                        ? (int) $parts[0] : 0;
                    $known = \local_airpay_courses\sharing_manager::known_tenants();
                    foreach ($known as $t) {
                        if ((int) $t->id === $provider_root) {
                            $provider_tenant_name = $t->name;
                            break;
                        }
                    }
                }
            }
        }

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
            // Sprint C provenance flags — for the "Provided by X" badge.
            'is_borrowed'           => $is_borrowed,
            'provider_tenant_name'  => $provider_tenant_name,
            // detailurl was pointing at /local/search/coursedetails.php (BizLMS-era
            // page; defunct since the BizLMS plugin removal). Use Moodle's
            // standard course view, same as viewurl.
            'viewurl'       => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'detailurl'     => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            // imageurl was pointing at theme/airpayux/pix_plugins/local_courses/courseimg
            // which never existed (404). Use Moodle core's default course icon as fallback.
            'imageurl'      => (new \moodle_url('/theme/image.php/airpayux/core/' .
                               $CFG->themerev . '/i/course'))->out(false),
        ];
    }
}
