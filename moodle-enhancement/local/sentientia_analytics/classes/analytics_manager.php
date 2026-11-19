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

    /** The time ranges get_range_dates() knows; anything else is read as 30d. */
    public const RANGES = ['7d', '30d', '90d', 'ytd'];

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
        // Only the counts are cached: the labels are strings in the VIEWER's
        // language, and this is an application cache shared by every viewer.
        // (Until 2026-11-18 the labels were English literals.)
        $cache = \cache::make('local_sentientia_analytics', 'kpis');
        $cachekey = 'kpis_v2_' . md5($range . '|' . ($orgpath ?: 'all'));
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return self::kpi_cards($cached);
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

        // New enrolments. Owner decision (2026-10-07, "readers that count enrolments"): a learner enrolled through an
        // imported BizLMS method AND its converted manual twin held two rows for one course, so counting rows counted that
        // learner twice. The unit is the learner-course pair, counted once, and only ACTIVE enrolments on ENABLED instances.
        $pairkey = $DB->sql_concat('e.courseid', "'-'", 'ue.userid');
        $enrol_current = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT $pairkey) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.status = 0 AND ue.timestart >= :start AND ue.timestart < :end $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $enrol_previous = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT $pairkey) FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid AND e.status = 0
               JOIN {user} u ON u.id = ue.userid
              WHERE ue.status = 0 AND ue.timestart >= :start AND ue.timestart < :end $orgfilter",
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

        // Certificates issued. tool_certificate is a third-party plugin, not
        // core: on a site without it (a vanilla Moodle, CI's phpu_ site, a new
        // customer) the table does not exist and the query threw, failing the
        // whole KPI call. Count 0 there, as team_manager and rule_engine do.
        $hascerts = $DB->get_manager()->table_exists('tool_certificate_issues');
        $cert_current = !$hascerts ? 0 : $DB->count_records_sql(
            "SELECT COUNT(ci.id) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid
              WHERE ci.timecreated >= :start AND ci.timecreated < :end $orgfilter",
            array_merge($params, ['start' => $current_start, 'end' => $current_end]));

        $cert_previous = !$hascerts ? 0 : $DB->count_records_sql(
            "SELECT COUNT(ci.id) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid
              WHERE ci.timecreated >= :start AND ci.timecreated < :end $orgfilter",
            array_merge($params, ['start' => $previous_start, 'end' => $previous_end]));

        // [label string id, current, previous, icon, variant] - see kpi_cards().
        $counts = [
            ['kpi_active_users',   $active_current, $active_previous, 'users',        'primary'],
            ['kpi_new_enrolments', $enrol_current,  $enrol_previous,  'user-plus',    'accent'],
            ['kpi_completions',    $comp_current,   $comp_previous,   'check-circle', 'success'],
            ['kpi_certificates',   $cert_current,   $cert_previous,   'certificate',  'warning'],
        ];
        $cache->set($cachekey, $counts);
        return self::kpi_cards($counts);
    }

    /**
     * KPI cards from the cached counts, labelled in the viewer's language.
     *
     * Phase B0 iter X — KPI shape includes both the legacy `trend`
     * object (kept for any consumer that still reads .is_up/.is_down)
     * AND the canonical stat_card partial fields:
     *   - color   : semantic variant ('primary' instead of hex)
     *   - icon    : FA name WITHOUT the 'fa-' prefix
     *   - trend   : already-formatted string for the partial's trend slot
     *   - trenddir: "up" / "down" / "flat" — drives the arrow + colour
     *   - trend_obj: legacy trend object — kept to avoid breaking tests
     *                and consumers that read trend.is_up etc.
     *
     * @param array $counts rows of [label string id, current, previous, icon, variant]
     * @return array
     */
    private static function kpi_cards(array $counts): array {
        $result = [];
        foreach ($counts as [$labelid, $cur, $prev, $icon, $variant]) {
            $trendobj = self::trend($cur, $prev);
            $dir = $trendobj['direction'] ?? 'flat';
            $result[] = [
                'label'     => get_string($labelid, 'local_sentientia_analytics'),
                'value'     => $cur,
                'previous'  => $prev,
                'icon'      => $icon,
                'color'     => $variant,
                'trend'     => get_string('kpi_vs_previous', 'local_sentientia_analytics', $trendobj['label']),
                'trenddir'  => ($dir === 'up' || $dir === 'down') ? $dir : 'flat',
                'trend_obj' => $trendobj,  // legacy — preserves .is_up / .is_down
            ];
        }
        return $result;
    }

    /**
     * Get engagement funnel: Enrolled → Started → Completed → Certified
     * (certified = holds a certificate AND completed at least one course).
     */
    public static function get_funnel(string $orgpath = ''): array {
        global $DB;

        // Counts only in the shared cache; stage names are added per viewer
        // (funnel_stages()), in the viewer's language.
        $cache = \cache::make('local_sentientia_analytics', 'funnel');
        // v3 (11-18, V12): Certified needs a completion too; v2 rows are stale.
        $cachekey = 'funnel_v3_' . md5($orgpath ?: 'all');
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return self::funnel_stages($cached);
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

        // tool_certificate may not be installed (see get_kpis()): no certificates then.
        // Certified = holds a certificate AND completed at least one course.
        // Imported (BizLMS) certificates carry courseid 0, so they cannot be
        // matched to the completion of their own course; counting every
        // certificate holder made the Certified bar taller than Completed
        // (11-18 surface walk, V12). The funnel is a funnel again.
        $certified = !$DB->get_manager()->table_exists('tool_certificate_issues') ? 0 : $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ci.userid) FROM {tool_certificate_issues} ci
               JOIN {user} u ON u.id = ci.userid
              WHERE u.deleted = 0 $orgfilter
                AND EXISTS (SELECT 1 FROM {course_completions} cc
                             WHERE cc.userid = ci.userid AND cc.timecompleted IS NOT NULL)", $params);

        $max = max($enrolled, 1);

        $result = [
            ['stage' => 'enrolled',  'count' => $enrolled,  'pct' => 100,                             'width' => 100],
            ['stage' => 'started',   'count' => $started,   'pct' => round($started / $max * 100),    'width' => round($started / $max * 100)],
            ['stage' => 'completed', 'count' => $completed, 'pct' => round($completed / $max * 100),  'width' => round($completed / $max * 100)],
            ['stage' => 'certified', 'count' => $certified, 'pct' => round($certified / $max * 100),  'width' => round($certified / $max * 100)],
        ];
        $cache->set($cachekey, $result);
        return self::funnel_stages($result);
    }

    /**
     * Name each cached funnel stage in the viewer's language. 'stage' was an
     * English literal until 2026-11-18; 'stagekey' keeps the stable id.
     *
     * @param array $stages rows with 'stage' = enrolled|started|completed|certified
     * @return array
     */
    private static function funnel_stages(array $stages): array {
        foreach ($stages as $i => $row) {
            $stages[$i]['stagekey'] = $row['stage'];
            $stages[$i]['stage'] = get_string('funnel_' . $row['stage'], 'local_sentientia_analytics');
        }
        return $stages;
    }

    /**
     * Whether the compliance report plugin, which owns the mandatory-course
     * list and the compliance snapshot, is installed. Without it there is no
     * compliance figure to show.
     */
    public static function compliance_source_available(): bool {
        global $DB;
        return class_exists('\local_sentientia_compliance_report\compliance_engine')
            && $DB->get_manager()->table_exists('local_compliance_snapshot');
    }

    /**
     * Get compliance heat map — RAG status by department.
     *
     * Read from the compliance report's Department Scorecard
     * (compliance_engine::get_department_scorecard()), so the two pages show
     * the same rate for the same department: completed mandatory-course
     * assignments over all assignments, from the compliance snapshot, with
     * the scorecard's RAG bands (green >= 90, amber >= 70). Departments with
     * no assignments are left out, as departments with no users were before.
     *
     * Until 2026-11-18 this counted every visible course with an end date as
     * mandatory, so the denominator was users x all dated courses and nearly
     * every department read 0% in red while the scorecard read 60-91% and the
     * dashboard 72% (11-18 surface walk, vp_compliance1 item 1, vp_admin1 B4).
     *
     * 'department' and 'parentname' are format_string()'d (HTML): render them
     * with triple braces; 'department_plain' is for CSV.
     *
     * @param string $orgpath tenant scope, '' = tenant 1 (as the scorecard)
     * @return array[]
     */
    public static function get_compliance_heatmap(string $orgpath = ''): array {
        // Cache: the scorecard reads the whole snapshot of a tenant and changes
        // slowly enough that a 5-minute TTL is fine for executive dashboards.
        $cache = \cache::make('local_sentientia_analytics', 'compliance_heatmap');
        $cachekey = 'heatmap_v2_' . md5($orgpath ?: 'all');
        $cached = $cache->get($cachekey);
        if ($cached !== false) {
            return $cached;
        }

        $heatmap = [];
        if (self::compliance_source_available()) {
            $scorecard = \local_sentientia_compliance_report\compliance_engine::get_department_scorecard($orgpath);
            foreach ($scorecard as $row) {
                if (!empty($row['is_empty'])) {
                    continue;
                }
                $heatmap[] = [
                    'department'       => $row['department'],
                    'department_plain' => $row['department_plain'],
                    'parentname'       => $row['parentname'],
                    'parentname_plain' => $row['parentname_plain'],
                    'has_parent'       => $row['has_parent'],
                    'path'             => $row['path'],
                    'users'            => $row['users'],
                    'total'            => $row['total'],
                    'completed'        => $row['completed'],
                    'rate'             => $row['rate'],
                    'rag'              => $row['rag'],
                    'is_green'         => $row['is_green'],
                    'is_amber'         => $row['is_amber'],
                    'is_red'           => $row['is_red'],
                ];
            }
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
    public static function get_course_learners(int $courseid, int $limit = 50,
                                               string $orgpath = ''): array {
        global $DB;

        // Tenant clamp. Callers pass the viewer's permitted org path; only a
        // holder of local/sentientia_analytics:viewallorgs gets '' here.
        // Without this the drill-down listed every enrolled learner's name and
        // email across all three tenants.
        $orgfilter = '';
        $orgparams = [];
        if ($orgpath !== '') {
            [$orgfilter, $orgparams] = \local_sentientia_platform\tenant::path_descendant_filter(
                $orgpath, 'u', 'open_path', 'clorg');
            $orgfilter = 'AND ' . $orgfilter;
        }

        // ORDER BY: MySQL and MariaDB sort NULLs last under DESC, which is the
        // order we want -- learners who have completed first, most recent
        // first, then everyone still in progress. This used to read
        // `DESC NULLS LAST`, which is PostgreSQL/Oracle syntax that both of
        // our database targets reject, so the whole page 500'd.
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
                    {$orgfilter}
           ORDER BY cc.timecompleted DESC, ue.timecreated DESC",
            ['cid' => $courseid, 'cid2' => $courseid] + $orgparams, 0, $limit));
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
