<?php
/**
 * Analytics Manager — interactive dashboard data with drill-down, time ranges, and funnels.
 *
 * @package    local_sentientia_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sentientia_analytics;

defined('MOODLE_INTERNAL') || die();

class analytics_manager {

    /**
     * Get KPI widgets data with time-range comparison.
     *
     * @param string $range  Time range: 7d, 30d, 90d, ytd, custom
     * @param string $orgpath Tenant/org filter (e.g., '/1' for Airpay)
     * @return array KPI data with current, previous, trend
     */
    public static function get_kpis(string $range = '30d', string $orgpath = ''): array {
        global $DB;

        // Cache the 8-count KPI block; same range+org returns same result for 5 min.
        $cache = \cache::make('local_sentientia_analytics', 'kpis');
        $cachekey = 'kpis_' . md5($range . '|' . ($orgpath ?: 'all'));
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        [$current_start, $current_end, $previous_start, $previous_end] = self::get_range_dates($range);

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            // Match exact tenant root OR any descendant with /-boundary
            // (`'/1' . '%'` would match /10, /100, /177 — cross-tenant leak).
            $orgfilter = "AND (u.open_path = :orgexact OR u.open_path LIKE :orgprefix)";
            $params['orgexact']  = $orgpath;
            $params['orgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }

        // Active users (logged in during period).
        $active_current = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
              WHERE u.lastaccess >= :start AND u.lastaccess < :end
                AND u.deleted = 0 AND u.suspended = 0 $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $active_previous = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id) FROM {user} u
              WHERE u.lastaccess >= :start AND u.lastaccess < :end
                AND u.deleted = 0 AND u.suspended = 0 $orgfilter",
            array_merge($params, ['start' => $previous_start, 'end' => $previous_end]));

        // New enrolments.
        $enrol_current = $DB->count_records_sql(
            "SELECT COUNT(ue.id) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.timestart >= :start AND ue.timestart < :end $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $enrol_previous = $DB->count_records_sql(
            "SELECT COUNT(ue.id) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.timestart >= :start AND ue.timestart < :end $orgfilter",
            array_merge($params, ['start' => $previous_start, 'end' => $previous_end]));

        // Completions.
        $comp_current = $DB->count_records_sql(
            "SELECT COUNT(cc.id) FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid
              WHERE cc.timecompleted >= :start AND cc.timecompleted < :end
                AND cc.timecompleted IS NOT NULL $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $comp_previous = $DB->count_records_sql(
            "SELECT COUNT(cc.id) FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid
              WHERE cc.timecompleted >= :start AND cc.timecompleted < :end
                AND cc.timecompleted IS NOT NULL $orgfilter",
            array_merge($params, ['start' => $previous_start, 'end' => $previous_end]));

        // Certificates issued.
        $cert_current = $DB->count_records_sql(
            "SELECT COUNT(ci.id) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid
              WHERE ci.timecreated >= :start AND ci.timecreated < :end $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $cert_previous = $DB->count_records_sql(
            "SELECT COUNT(ci.id) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid
              WHERE ci.timecreated >= :start AND ci.timecreated < :end $orgfilter",
            array_merge($params, ['start' => $previous_start, 'end' => $previous_end]));

        // Phase B0 iter X — KPI shape now includes both the legacy `trend`
        // object (kept for any consumer that still reads .is_up/.is_down)
        // AND the canonical stat_card partial fields:
        //   - color   : semantic variant ('primary' instead of hex)
        //   - icon    : FA name WITHOUT the 'fa-' prefix
        //   - trend   : already-formatted string for the partial's trend slot
        //   - trenddir: "up" / "down" / "flat" — drives the arrow + colour
        //   - trend_obj: legacy trend object — kept to avoid breaking tests
        //                and consumers that read trend.is_up etc.
        $result = [];
        $kpis = [
            ['Active Users',    $active_current, $active_previous, 'users',        'primary'],
            ['New Enrolments',  $enrol_current,  $enrol_previous,  'user-plus',    'accent'],
            ['Completions',     $comp_current,   $comp_previous,   'check-circle', 'success'],
            ['Certificates',    $cert_current,   $cert_previous,   'certificate',  'warning'],
        ];
        foreach ($kpis as [$label, $cur, $prev, $icon, $variant]) {
            $trendobj = self::trend($cur, $prev);
            $dir = $trendobj['direction'] ?? 'flat';
            $result[] = [
                'label'     => $label,
                'value'     => $cur,
                'previous'  => $prev,
                'icon'      => $icon,
                'color'     => $variant,
                'trend'     => $trendobj['label'] . ' vs previous',
                'trenddir'  => ($dir === 'up' || $dir === 'down') ? $dir : 'flat',
                'trend_obj' => $trendobj,  // legacy — preserves .is_up / .is_down
            ];
        }
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get engagement funnel: Enrolled → Started → 50% → Completed → Certified.
     */
    public static function get_funnel(string $orgpath = ''): array {
        global $DB;

        $cache = \cache::make('local_sentientia_analytics', 'funnel');
        $cachekey = 'funnel_' . md5($orgpath ?: 'all');
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND (u.open_path = :orgexact OR u.open_path LIKE :orgprefix)";
            $params['orgexact']  = $orgpath;
            $params['orgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }

        $enrolled = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue
               JOIN {user} u ON u.id = ue.userid WHERE u.deleted = 0 $orgfilter", $params);

        $started = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT l.userid) FROM {logstore_standard_log} l
               JOIN {user} u ON u.id = l.userid
              WHERE l.target = 'course' AND l.action = 'viewed' AND u.deleted = 0 $orgfilter", $params);

        $completed = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT cc.userid) FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid
              WHERE cc.timecompleted IS NOT NULL AND u.deleted = 0 $orgfilter", $params);

        $certified = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ci.userid) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid WHERE u.deleted = 0 $orgfilter", $params);

        $max = max($enrolled, 1);

        $result = [
            ['stage' => 'Enrolled',  'count' => $enrolled,  'pct' => 100,                             'width' => 100],
            ['stage' => 'Started',   'count' => $started,   'pct' => round($started / $max * 100),    'width' => round($started / $max * 100)],
            ['stage' => 'Completed', 'count' => $completed, 'pct' => round($completed / $max * 100),  'width' => round($completed / $max * 100)],
            ['stage' => 'Certified', 'count' => $certified, 'pct' => round($certified / $max * 100),  'width' => round($certified / $max * 100)],
        ];
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Get compliance heat map — RAG status by department.
     */
    public static function get_compliance_heatmap(string $orgpath = ''): array {
        global $DB;

        // Cache: compliance_heatmap is heavy (was N+1 over departments) and changes
        // slowly enough that a 5-minute TTL is fine for executive dashboards.
        $cache = \cache::make('local_sentientia_analytics', 'compliance_heatmap');
        $cachekey = 'heatmap_' . md5($orgpath ?: 'all');
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        // Get departments (depth 3) under the requested top org.
        $parts = explode('/', trim($orgpath ?: '/1', '/'));
        $toporg = '/' . ($parts[0] ?? '1');

        $departments = $DB->get_records_sql(
            "SELECT cc.id, cc.fullname, cc.path
               FROM {local_sentientia_org} cc
              WHERE cc.path LIKE :pathprefix AND cc.depth = 3
           ORDER BY cc.fullname",
            ['pathprefix' => $toporg . '/%']);

        if (empty($departments)) {
            $cache->set($cachekey, []);
            return [];
        }

        // ── ONE QUERY: count users grouped by exact open_path (no per-dept N+1) ──
        // We then roll up in PHP into per-department totals using path prefixes.
        $user_path_counts = $DB->get_records_sql(
            "SELECT open_path AS p, COUNT(*) AS cnt
               FROM {user}
              WHERE deleted = 0 AND suspended = 0
                AND open_path IS NOT NULL AND open_path <> ''
                AND open_path LIKE :prefix
           GROUP BY open_path",
            ['prefix' => $toporg . '/%']);

        // ── ONE QUERY: completion counts grouped by exact user open_path ──
        $completion_path_counts = $DB->get_records_sql(
            "SELECT u.open_path AS p, COUNT(DISTINCT cc.id) AS cnt
               FROM {course_completions} cc
               JOIN {user} u ON u.id = cc.userid
               JOIN {course} c ON c.id = cc.course
              WHERE u.deleted = 0 AND u.open_path LIKE :prefix
                AND c.enddate > 0 AND cc.timecompleted IS NOT NULL
           GROUP BY u.open_path",
            ['prefix' => $toporg . '/%']);

        // ── ONE QUERY: hoisted out of the loop. Same value for every dept. ──
        $mandatory_courses = $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course}
              WHERE enddate > 0 AND visible = 1 AND id > 1 AND open_path LIKE :mpath",
            ['mpath' => $toporg . '/%']);

        if ($mandatory_courses == 0) {
            $cache->set($cachekey, []);
            return [];
        }

        // Per-department rollup: a user at /1/2/3/4 contributes to dept /1/2/3.
        // Match each user_path to the dept whose path is a prefix of the user_path.
        $dept_paths = [];
        foreach ($departments as $dept) {
            $dept_paths[$dept->path] = ['users' => 0, 'completed' => 0];
        }
        foreach ($user_path_counts as $row) {
            foreach ($dept_paths as $dpath => &$tot) {
                if ($row->p === $dpath || str_starts_with($row->p, $dpath . '/')) {
                    $tot['users'] += (int) $row->cnt;
                }
            }
            unset($tot);
        }
        foreach ($completion_path_counts as $row) {
            foreach ($dept_paths as $dpath => &$tot) {
                if ($row->p === $dpath || str_starts_with($row->p, $dpath . '/')) {
                    $tot['completed'] += (int) $row->cnt;
                }
            }
            unset($tot);
        }

        $heatmap = [];
        foreach ($departments as $dept) {
            $total_users = $dept_paths[$dept->path]['users'] ?? 0;
            if ($total_users == 0) {
                continue;
            }
            $completed_mandatory = $dept_paths[$dept->path]['completed'] ?? 0;
            $expected = $total_users * $mandatory_courses;
            $rate = $expected > 0 ? (int) round(($completed_mandatory / $expected) * 100) : 0;
            $rag = ($rate >= 80) ? 'green' : (($rate >= 50) ? 'amber' : 'red');

            $heatmap[] = [
                'department' => format_string($dept->fullname),
                'path'       => $dept->path,
                'users'      => $total_users,
                'rate'       => $rate,
                'rag'        => $rag,
                'is_green'   => ($rag === 'green'),
                'is_amber'   => ($rag === 'amber'),
                'is_red'     => ($rag === 'red'),
            ];
        }

        $cache->set($cachekey, $heatmap);
        return $heatmap;
    }

    /**
     * Get top/bottom performing courses.
     */
    public static function get_course_effectiveness(int $limit = 10, string $orgpath = ''): array {
        global $DB, $USER;

        // Scope to user's tenant.
        if (empty($orgpath) && !empty($USER->open_path)) {
            $parts = explode('/', $USER->open_path);
            $org = $parts[1] ?? '';
            if (!empty($org)) {
                $orgpath = '/' . $org;
            }
        }

        // Cache: complex JOIN+GROUP BY across {course}+{enrol}+{user_enrolments}+
        // {course_completions} for 411 courses + 3K users. Expensive on every render.
        $cache = \cache::make('local_sentientia_analytics', 'course_effectiveness');
        $cachekey = 'eff_' . md5($limit . '|' . ($orgpath ?: 'all'));
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND (c.open_path = :orgexact OR c.open_path LIKE :orgprefix)";
            $params['orgexact']  = $orgpath;
            $params['orgprefix'] = $DB->sql_like_escape($orgpath) . '/%';
        }

        $result = array_values($DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname,
                    COUNT(DISTINCT ue.userid) as enrolled,
                    COUNT(DISTINCT cc.userid) as completed,
                    CASE WHEN COUNT(DISTINCT ue.userid) > 0
                         THEN ROUND(COUNT(DISTINCT cc.userid) * 100.0 / COUNT(DISTINCT ue.userid))
                         ELSE 0 END as completion_rate
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id
               JOIN {user_enrolments} ue ON ue.enrolid = e.id
          LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = ue.userid
                    AND cc.timecompleted IS NOT NULL
              WHERE c.visible = 1 AND c.id > 1 $orgfilter
           GROUP BY c.id, c.fullname, c.shortname
             HAVING COUNT(DISTINCT ue.userid) >= 5
           ORDER BY completion_rate DESC",
            $params, 0, $limit));
        $cache->set($cachekey, $result);
        return $result;
    }

    /**
     * Calculate trend percentage and direction.
     */
    private static function trend(?int $current = 0, ?int $previous = 0): array {
        $current = (int) $current;
        $previous = (int) $previous;
        if ($previous == 0) {
            return ['pct' => $current > 0 ? 100 : 0, 'direction' => 'up', 'label' => $current > 0 ? '+100%' : '0%'];
        }
        $pct = round((($current - $previous) / $previous) * 100);
        $direction = $pct >= 0 ? 'up' : 'down';
        $label = ($pct >= 0 ? '+' : '') . $pct . '%';
        return ['pct' => abs($pct), 'direction' => $direction, 'label' => $label, 'is_up' => ($pct >= 0), 'is_down' => ($pct < 0)];
    }

    /**
     * DRILL-DOWN: Get users within a department with their learning stats.
     */
    public static function get_department_users(string $deptpath, int $limit = 50): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.open_path, u.lastlogin,
                    COUNT(DISTINCT ue.enrolid) as enrolled_courses,
                    COUNT(DISTINCT cc.id) as completed_courses,
                    CASE WHEN COUNT(DISTINCT ue.enrolid) > 0
                         THEN ROUND(COUNT(DISTINCT cc.id) * 100.0 / COUNT(DISTINCT ue.enrolid))
                         ELSE 0 END as completion_rate
               FROM {user} u
          LEFT JOIN {user_enrolments} ue ON ue.userid = u.id
          LEFT JOIN {enrol} e ON e.id = ue.enrolid
          LEFT JOIN {course_completions} cc ON cc.userid = u.id AND cc.course = e.courseid
                    AND cc.timecompleted IS NOT NULL
              WHERE u.deleted = 0 AND u.suspended = 0
                AND (u.open_path = :dpathexact OR u.open_path LIKE :dpathprefix)
           GROUP BY u.id, u.firstname, u.lastname, u.email, u.open_path, u.lastlogin
           ORDER BY completion_rate ASC",
            [
                'dpathexact'  => $deptpath,
                'dpathprefix' => $DB->sql_like_escape($deptpath) . '/%',
            ], 0, $limit));
    }

    /**
     * DRILL-DOWN: Get learners enrolled in a specific course with their status.
     */
    public static function get_course_learners(int $courseid, int $limit = 50): array {
        global $DB;

        return array_values($DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.open_path,
                    ue.timecreated as enrolled_date,
                    cc.timecompleted as completed_date,
                    CASE WHEN cc.timecompleted IS NOT NULL THEN 'completed'
                         WHEN ue.id IS NOT NULL THEN 'enrolled'
                         ELSE 'not_enrolled' END as status
               FROM {user} u
               JOIN {user_enrolments} ue ON ue.userid = u.id
               JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :cid
          LEFT JOIN {course_completions} cc ON cc.course = :cid2 AND cc.userid = u.id
                    AND cc.timecompleted IS NOT NULL
              WHERE u.deleted = 0 AND u.suspended = 0
           ORDER BY cc.timecompleted DESC NULLS LAST, ue.timecreated DESC",
            ['cid' => $courseid, 'cid2' => $courseid], 0, $limit));
    }

    /**
     * EXPORT: Get all analytics data as structured array for CSV/PDF export.
     */
    public static function get_export_data(string $range = '30d', string $orgpath = ''): array {
        $kpis = self::get_kpis($range, $orgpath);
        $funnel = self::get_funnel($orgpath);
        $heatmap = self::get_compliance_heatmap($orgpath);
        $courses = self::get_course_effectiveness(20, $orgpath);

        return [
            'generated'  => userdate(time(), '%d %b %Y %I:%M %p'),
            'range'      => $range,
            'kpis'       => $kpis,
            'funnel'     => $funnel,
            'heatmap'    => $heatmap,
            'courses'    => $courses,
        ];
    }

    /**
     * Get date ranges for period comparison.
     */
    private static function get_range_dates(string $range): array {
        $now = time();
        switch ($range) {
            case '7d':
                return [$now - (7 * 86400), $now, $now - (14 * 86400), $now - (7 * 86400)];
            case '90d':
                return [$now - (90 * 86400), $now, $now - (180 * 86400), $now - (90 * 86400)];
            case 'ytd':
                $yearstart = strtotime(date('Y') . '-01-01');
                $prevyearstart = strtotime((date('Y') - 1) . '-01-01');
                return [$yearstart, $now, $prevyearstart, $yearstart];
            default: // 30d
                return [$now - (30 * 86400), $now, $now - (60 * 86400), $now - (30 * 86400)];
        }
    }
}
