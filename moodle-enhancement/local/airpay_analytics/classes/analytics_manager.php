<?php
/**
 * Analytics Manager — interactive dashboard data with drill-down, time ranges, and funnels.
 *
 * @package    local_airpay_analytics
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_airpay_analytics;

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

        [$current_start, $current_end, $previous_start, $previous_end] = self::get_range_dates($range);

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND u.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
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

        return [
            ['label' => 'Active Users',    'value' => $active_current, 'previous' => $active_previous, 'trend' => self::trend($active_current, $active_previous), 'icon' => 'fa-users',      'color' => '#0066A7'],
            ['label' => 'New Enrolments',   'value' => $enrol_current,  'previous' => $enrol_previous,  'trend' => self::trend($enrol_current, $enrol_previous),   'icon' => 'fa-user-plus',  'color' => '#0f7a73'],
            ['label' => 'Completions',      'value' => $comp_current,   'previous' => $comp_previous,   'trend' => self::trend($comp_current, $comp_previous),     'icon' => 'fa-check-circle','color' => '#16a34a'],
            ['label' => 'Certificates',     'value' => $cert_current,   'previous' => $cert_previous,   'trend' => self::trend($cert_current, $cert_previous),     'icon' => 'fa-certificate','color' => '#d97706'],
        ];
    }

    /**
     * Get engagement funnel: Enrolled → Started → 50% → Completed → Certified.
     */
    public static function get_funnel(string $orgpath = ''): array {
        global $DB;

        $orgfilter = '';
        $params = [];
        if (!empty($orgpath)) {
            $orgfilter = "AND u.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
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

        return [
            ['stage' => 'Enrolled',  'count' => $enrolled,  'pct' => 100,                             'width' => 100],
            ['stage' => 'Started',   'count' => $started,   'pct' => round($started / $max * 100),    'width' => round($started / $max * 100)],
            ['stage' => 'Completed', 'count' => $completed, 'pct' => round($completed / $max * 100),  'width' => round($completed / $max * 100)],
            ['stage' => 'Certified', 'count' => $certified, 'pct' => round($certified / $max * 100),  'width' => round($certified / $max * 100)],
        ];
    }

    /**
     * Get compliance heat map — RAG status by department.
     */
    public static function get_compliance_heatmap(string $orgpath = ''): array {
        global $DB;

        $now = time();
        $orgfilter = '';
        $params = ['now' => $now];
        if (!empty($orgpath)) {
            $orgfilter = "AND u.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        // Get departments (level 3 costcenters under the org).
        $parts = explode('/', trim($orgpath ?: '/1', '/'));
        $toporg = '/' . ($parts[0] ?? '1');

        $departments = $DB->get_records_sql(
            "SELECT cc.id, cc.fullname, cc.path
               FROM {local_costcenter} cc
              WHERE cc.path LIKE :pathprefix AND cc.depth = 3
           ORDER BY cc.fullname",
            ['pathprefix' => $toporg . '/%']);

        $heatmap = [];
        foreach ($departments as $dept) {
            // Count users in this department.
            $total_users = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {user} WHERE open_path LIKE :path AND deleted = 0 AND suspended = 0",
                ['path' => $dept->path . '%']);

            if ($total_users == 0) continue;

            // Count mandatory courses scoped to the same top-level org.
            $mandatory_courses = $DB->count_records_sql(
                "SELECT COUNT(*) FROM {course}
                 WHERE enddate > 0 AND visible = 1 AND id > 1 AND open_path LIKE :mpath",
                ['mpath' => $toporg . '%']);
            if ($mandatory_courses == 0) continue;

            $completed_mandatory = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT cc.id) FROM {course_completions} cc
                   JOIN {user} u ON u.id = cc.userid
                   JOIN {course} c ON c.id = cc.course
                  WHERE u.open_path LIKE :path AND u.deleted = 0
                    AND c.enddate > 0 AND cc.timecompleted IS NOT NULL",
                ['path' => $dept->path . '%']);

            $expected = $total_users * $mandatory_courses;
            $rate = $expected > 0 ? round(($completed_mandatory / $expected) * 100) : 0;
            $rag = ($rate >= 80) ? 'green' : (($rate >= 50) ? 'amber' : 'red');

            $heatmap[] = [
                'department' => format_string($dept->fullname),
                'users'      => $total_users,
                'rate'       => $rate,
                'rag'        => $rag,
                'is_green'   => ($rag === 'green'),
                'is_amber'   => ($rag === 'amber'),
                'is_red'     => ($rag === 'red'),
            ];
        }

        return $heatmap;
    }

    /**
     * Get top/bottom performing courses.
     */
    public static function get_course_effectiveness(int $limit = 10, string $orgpath = ''): array {
        global $DB, $USER;

        // Scope to user's tenant.
        $orgfilter = '';
        $params = [];
        if (empty($orgpath) && !empty($USER->open_path)) {
            $parts = explode('/', $USER->open_path);
            $org = $parts[1] ?? '';
            if (!empty($org)) {
                $orgpath = '/' . $org;
            }
        }
        if (!empty($orgpath)) {
            $orgfilter = "AND c.open_path LIKE :orgpath";
            $params['orgpath'] = $orgpath . '%';
        }

        return array_values($DB->get_records_sql(
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
    }

    /**
     * Calculate trend percentage and direction.
     */
    private static function trend(int $current, int $previous): array {
        if ($previous == 0) {
            return ['pct' => $current > 0 ? 100 : 0, 'direction' => 'up', 'label' => $current > 0 ? '+100%' : '0%'];
        }
        $pct = round((($current - $previous) / $previous) * 100);
        $direction = $pct >= 0 ? 'up' : 'down';
        $label = ($pct >= 0 ? '+' : '') . $pct . '%';
        return ['pct' => abs($pct), 'direction' => $direction, 'label' => $label, 'is_up' => ($pct >= 0), 'is_down' => ($pct < 0)];
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
