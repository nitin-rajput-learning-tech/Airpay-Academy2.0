<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Team manager — aggregate learning data for a manager's direct reports.
 *
 * Replaces the old per-row query loop in index.php. One query each for:
 * enrolments, completions, overdue, streaks. Then merge in PHP.
 */
class team_manager {

    /**
     * Get direct reports for a manager.
     * @return object[] Indexed by user id.
     */
    public static function get_team(int $managerid): array {
        global $DB;
        // Resolve the direct reports through the org seam (ADR-020): org_legacy ON
        // reads open_supervisorid exactly as before; OFF reads the Sentientia org
        // model — so a cutover switches the team dashboard automatically. The rich
        // record load (+ deleted/suspended re-filter, + the stable name ordering)
        // is preserved below.
        $reportids = \local_sentientia_core\org::direct_reports($managerid);
        if (empty($reportids)) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($reportids, SQL_PARAMS_NAMED, 'uid');
        return $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.email, u.lastlogin,
                    u.lastaccess, u.open_path, u.open_designation,
                    u.department, u.open_employeeid
               FROM {user} u
              WHERE u.id {$insql} AND u.deleted = 0 AND u.suspended = 0
           ORDER BY u.lastname ASC, u.firstname ASC",
            $inparams
        );
    }

    /**
     * Build summary rows for the manager dashboard table.
     * Batches 4 aggregate queries instead of N×3 per-row queries.
     *
     * @param object[] $team  Array of user records (from get_team()).
     * @return array<int, array>  Map from userid → row data.
     */
    public static function summarize_team(array $team): array {
        global $DB;
        if (empty($team)) {
            return [];
        }

        $userids = array_keys($team);
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        // 1) Enrolments per user.
        $enrolled_map = [];
        $rows = $DB->get_records_sql(
            "SELECT ue.userid, COUNT(DISTINCT e.courseid) AS cnt
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid $insql
           GROUP BY ue.userid",
            $inparams);
        foreach ($rows as $r) { $enrolled_map[(int) $r->userid] = (int) $r->cnt; }

        // 2) Completions per user.
        $completed_map = [];
        $rows = $DB->get_records_sql(
            "SELECT userid, COUNT(*) AS cnt
               FROM {course_completions}
              WHERE timecompleted IS NOT NULL AND userid $insql
           GROUP BY userid",
            $inparams);
        foreach ($rows as $r) { $completed_map[(int) $r->userid] = (int) $r->cnt; }

        // 3) Overdue compliance items (if the table exists).
        $overdue_map = [];
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_airpay_compliance_snapshot')) {
            $rows = $DB->get_records_sql(
                "SELECT userid, COUNT(*) AS cnt
                   FROM {local_airpay_compliance_snapshot}
                  WHERE status = 'overdue' AND userid $insql
               GROUP BY userid",
                $inparams);
            foreach ($rows as $r) { $overdue_map[(int) $r->userid] = (int) $r->cnt; }
        }

        // 4) Streak/points (if table exists).
        $streak_map = [];
        if ($dbman->table_exists('local_airpay_streaks')) {
            $rows = $DB->get_records_sql(
                "SELECT userid, current_streak, total_points
                   FROM {local_airpay_streaks}
                  WHERE userid $insql",
                $inparams);
            foreach ($rows as $r) {
                $streak_map[(int) $r->userid] = [
                    'streak' => (int) ($r->current_streak ?? 0),
                    'points' => (int) ($r->total_points ?? 0),
                ];
            }
        }

        // Build result rows.
        $result = [];
        foreach ($team as $m) {
            $enrolled  = $enrolled_map[$m->id] ?? 0;
            $completed = $completed_map[$m->id] ?? 0;
            $overdue   = $overdue_map[$m->id] ?? 0;
            $streak    = $streak_map[$m->id]['streak'] ?? 0;
            $points    = $streak_map[$m->id]['points'] ?? 0;
            // Cap at 100% — completions can exceed current enrolments when a
            // course was un-enrolled but the completion record persists.
            $rate      = $enrolled > 0 ? min(100, round(($completed / $enrolled) * 100)) : 0;
            $lastlogin_ts = (int) ($m->lastlogin ?: 0);
            $inactive_days = $lastlogin_ts ? round((time() - $lastlogin_ts) / 86400) : 999;

            $result[(int) $m->id] = [
                'id'           => (int) $m->id,
                'firstname'    => format_string($m->firstname),
                'lastname'     => format_string($m->lastname),
                'fullname'     => format_string(trim(($m->firstname ?? '') . ' ' . ($m->lastname ?? ''))),
                'email'        => $m->email,
                'employeeid'   => $m->open_employeeid ?? '',
                'designation'  => format_string($m->open_designation ?? ''),
                'department'   => format_string($m->department ?? ''),
                'enrolled'     => $enrolled,
                'completed'    => $completed,
                'rate'         => $rate,
                'rate_class'   => $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger'),
                'overdue'      => $overdue,
                'has_overdue'  => ($overdue > 0),
                'streak'       => $streak,
                'points'       => number_format($points),
                'lastlogin'    => $lastlogin_ts ? userdate($lastlogin_ts, '%d %b %Y') : 'Never',
                'inactive_days' => $inactive_days,
                'is_inactive'  => ($inactive_days > 14),
            ];
        }
        return $result;
    }

    /**
     * Detailed learning report for a single team member.
     * Used by member.php drill-down.
     *
     * @return array{
     *   user: object, courses: array, certificates: array,
     *   enrolments_total: int, completions_total: int,
     *   in_progress: int, not_started: int
     * }
     */
    public static function get_member_detail(int $userid): array {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', MUST_EXIST);

        // All enrolled courses with completion status.
        $courses = $DB->get_records_sql(
            "SELECT c.id, c.fullname, c.shortname, c.idnumber,
                    cc.timestarted, cc.timecompleted,
                    cat.name AS catname,
                    (SELECT COUNT(*) FROM {course_modules_completion} cmc
                       JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                      WHERE cm.course = c.id AND cmc.userid = u.id AND cmc.completionstate > 0) AS modules_done,
                    (SELECT COUNT(*) FROM {course_modules} cm
                      WHERE cm.course = c.id AND cm.completion > 0) AS modules_total
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {course} c ON c.id = e.courseid
               JOIN {user} u ON u.id = ue.userid
          LEFT JOIN {course_completions} cc ON cc.course = c.id AND cc.userid = u.id
          LEFT JOIN {course_categories} cat ON cat.id = c.category
              WHERE ue.userid = :uid AND c.id > 1
           ORDER BY (cc.timecompleted IS NULL) DESC, cc.timecompleted DESC, c.fullname ASC",
            ['uid' => $userid]);

        $course_rows = [];
        $completed = 0;
        $in_progress = 0;
        $not_started = 0;

        foreach ($courses as $c) {
            $is_complete = !empty($c->timecompleted);
            $modules_done = (int) ($c->modules_done ?? 0);
            $modules_total = (int) ($c->modules_total ?? 0);
            $progress_pct = $modules_total > 0 ? round(($modules_done / $modules_total) * 100) : ($is_complete ? 100 : 0);

            if ($is_complete) {
                $status = 'completed';
                $status_label = 'Completed';
                $status_css = 'badge-success';
                $completed++;
            } else if ($modules_done > 0 || !empty($c->timestarted)) {
                $status = 'in_progress';
                $status_label = 'In Progress';
                $status_css = 'badge-warning';
                $in_progress++;
            } else {
                $status = 'not_started';
                $status_label = 'Not Started';
                $status_css = 'badge-secondary';
                $not_started++;
            }

            $course_rows[] = [
                'id'            => (int) $c->id,
                'fullname'      => format_string($c->fullname),
                'shortname'     => format_string($c->shortname ?? ''),
                'category'      => format_string($c->catname ?? '—'),
                'started'       => $c->timestarted ? userdate($c->timestarted, '%d %b %Y') : '—',
                'completed'     => $c->timecompleted ? userdate($c->timecompleted, '%d %b %Y') : '—',
                'progress_pct'  => $progress_pct,
                'progress_text' => "{$modules_done}/{$modules_total}",
                'status'        => $status,
                'status_label'  => $status_label,
                'status_css'    => $status_css,
                'status_completed'   => ($status === 'completed'),
                'status_in_progress' => ($status === 'in_progress'),
                'status_not_started' => ($status === 'not_started'),
                'course_url'    => (new \moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
            ];
        }

        // Certificates issued via tool_certificate.
        $certificates = [];
        if ($DB->get_manager()->table_exists('tool_certificate_issues')) {
            $cert_records = $DB->get_records_sql(
                "SELECT i.id, i.code, i.timecreated, t.name
                   FROM {tool_certificate_issues} i
                   JOIN {tool_certificate_templates} t ON t.id = i.templateid
                  WHERE i.userid = :uid
               ORDER BY i.timecreated DESC", ['uid' => $userid]);
            foreach ($cert_records as $cr) {
                $certificates[] = [
                    'name' => format_string($cr->name),
                    'code' => $cr->code,
                    'date' => userdate($cr->timecreated, '%d %b %Y'),
                    'verify_url' => (new \moodle_url('/admin/tool/certificate/index.php', ['code' => $cr->code]))->out(false),
                ];
            }
        }

        return [
            'user'             => $user,
            'courses'          => $course_rows,
            'certificates'     => $certificates,
            'enrolments_total' => count($course_rows),
            'completions_total' => $completed,
            'in_progress'      => $in_progress,
            'not_started'      => $not_started,
        ];
    }

    /**
     * Can this user access the manager surface (team dashboard,
     * approval queue, performance reports)? Bug fix 2026-05-22
     * (Goal A audit): require_capability('local/airpay_manager:view')
     * was rejecting users who had direct reports but had not been
     * assigned the Moodle `manager` role. On the production data
     * (110 users with direct reports, only ~10 with the manager
     * archetype), this left ~100 supervisors unable to access their
     * own team's data.
     *
     * Allow rule (ordered cheapest-first):
     *   1. Site admins always.
     *   2. Anyone with local/airpay_manager:view capability.
     *   3. Anyone listed as `open_supervisorid` on at least one
     *      active, non-deleted user (= has direct reports).
     */
    public static function can_manage(int $viewerid = 0): bool {
        global $USER;
        if (empty($viewerid)) {
            $viewerid = (int) ($USER->id ?? 0);
        }
        if (empty($viewerid)) return false;
        if (is_siteadmin($viewerid)) return true;
        if (has_capability('local/airpay_manager:view',
                \context_system::instance(), $viewerid)) {
            return true;
        }
        // Has direct reports? Routed through the org seam (ADR-020): org_legacy ON
        // = the open_supervisorid reverse lookup as before; OFF = the org model.
        return \local_sentientia_core\org::is_manager($viewerid);
    }

    /**
     * require_login + can_manage in one call — drop-in replacement
     * for `require_capability('local/airpay_manager:view', ...)`.
     * Throws the same `required_capability_exception` shape so callers
     * upstream don't need to special-case it.
     */
    public static function require_manage(): void {
        global $USER;
        if (!self::can_manage()) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'local/airpay_manager:view',
                'nopermissions',
                ''
            );
        }
    }

    /**
     * Verify the requesting user can view the target user's data.
     * Allows: admins, the target user themselves, the target's supervisor,
     * and the supervisor's supervisor (skip-level managers).
     */
    public static function can_view_member(int $viewerid, int $targetid): bool {
        global $DB;
        if ($viewerid === $targetid) return true;
        if (is_siteadmin($viewerid)) return true;
        if (has_capability('local/airpay_users:view', \context_system::instance(), $viewerid)) return true;

        // Walk up the supervisor chain from target via the org seam (ADR-020):
        // org_legacy ON resolves each manager from open_supervisorid as before;
        // OFF resolves from the org model — so the chain switches at cutover.
        $current = $targetid;
        $visited = [];
        for ($i = 0; $i < 5; $i++) { // depth limit
            $u = $DB->get_record('user', ['id' => $current], '*');
            $sup = $u ? \local_sentientia_core\org::manager_id_of($u) : 0;
            if (empty($sup) || isset($visited[$sup])) break;
            if ((int) $sup === $viewerid) return true;
            $visited[$sup] = true;
            $current = (int) $sup;
        }
        return false;
    }
}
