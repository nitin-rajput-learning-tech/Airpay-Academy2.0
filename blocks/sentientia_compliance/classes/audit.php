<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace block_sentientia_compliance;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\tenant;

/**
 * Who sees how much of the compliance block, and the data behind it.
 *
 * ADR-031 (2026-09-25). Until this date the block handed its org-wide matrix
 * (every mandatory course, deadlines, enrolled / completed / overdue counts)
 * for EVERY tenant to anyone holding core moodle/site:viewreports - tenant
 * admins, trainers - and to any ordinary user with a direct report. Its CSV
 * export streamed every tenant's employees (employee id, name, email, status)
 * to any holder of local/sentientia_courses:manage, i.e. every tenant admin.
 *
 * Now, mirroring local_sentientia_compliance_report\viewer_scope:
 *
 *   cross-tenant   site admin or :crosstenant holder      every tenant
 *   tenant         moodle/site:viewreports, or the         their own tenant
 *                  BizLMS local/courses:manage where
 *                  that capability is declared
 *   team           a line manager (someone reports to     their reporting tree
 *                  them), admitted by that alone           inside their tenant
 *   none           anyone else, and any non-cross-tenant   their own status only
 *                  viewer whose tenant does not resolve
 *
 * @package    block_sentientia_compliance
 */
final class audit {

    /**
     * A viewer's scope, or null for the learner view (own status only).
     *
     * @param \stdClass $user a user record carrying id and open_path
     * @return array{path: string, userids: int[]|null}|null
     *         path ''  only for a cross-tenant viewer (no restriction);
     *         userids  null = the whole path, or exactly these users (team)
     */
    public static function viewer_scope(\stdClass $user): ?array {
        $userid = (int) ($user->id ?? 0);
        if ($userid <= 0 || isguestuser($userid)) {
            return null;
        }
        if (tenant::is_cross_tenant($userid)) {
            return ['path' => '', 'userids' => null];
        }
        // Fail closed: an unresolved tenant never becomes the site-wide matrix.
        $root = tenant::root_for_user($user);
        if ($root <= 0) {
            return null;
        }
        $path = '/' . $root;

        $sys = \context_system::instance();
        // local/courses:manage is declared only where BizLMS is installed; on
        // a 5.2 stack has_capability() on it raises a debugging notice.
        $tenantlevel = (get_capability_info('local/courses:manage')
                && has_capability('local/courses:manage', $sys, $userid))
            || has_capability('moodle/site:viewreports', $sys, $userid);
        if ($tenantlevel) {
            return ['path' => $path, 'userids' => null];
        }

        $team = self::reporting_tree($userid, $path);
        return $team ? ['path' => $path, 'userids' => $team] : null;
    }

    /**
     * Everyone who reports to $userid, bounded to the tenant $path.
     *
     * Uses the compliance report's tree walk when that plugin is installed,
     * otherwise the active direct reports - the block's old admission rule,
     * now bounded to the manager's tenant.
     *
     * @param int $userid
     * @param string $path tenant root, e.g. '/1' (never '')
     * @return int[]
     */
    public static function reporting_tree(int $userid, string $path): array {
        global $DB;
        if ($userid <= 0 || trim($path, '/') === '') {
            return [];
        }
        if (class_exists('\local_sentientia_compliance_report\compliance_engine')
                && method_exists('\local_sentientia_compliance_report\compliance_engine', 'get_reporting_tree')) {
            return array_map('intval',
                \local_sentientia_compliance_report\compliance_engine::get_reporting_tree($userid, $path));
        }
        $dbman = $DB->get_manager();
        if (!$dbman->field_exists(new \xmldb_table('user'), new \xmldb_field('open_supervisorid'))) {
            return [];
        }
        [$tsql, $targs] = tenant::path_descendant_filter($path, '', 'open_path', 'cmprt');
        return array_map('intval', $DB->get_fieldset_select('user', 'id',
            "open_supervisorid = :sup AND deleted = 0 AND suspended = 0 AND {$tsql}",
            ['sup' => $userid] + $targs));
    }

    /**
     * WHERE fragment on alias u restricting to the scope's people.
     *
     * @param string $path '' = no path restriction (cross-tenant only)
     * @param int[]|null $userids
     * @return array{0: string, 1: array}
     */
    private static function population_sql(string $path, ?array $userids): array {
        global $DB;
        [$sql, $params] = tenant::path_descendant_filter($path, 'u', 'open_path', 'cmpt');
        if ($userids !== null) {
            if (!$userids) {
                return ['1=0', []];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'cmpu');
            $sql .= " AND u.id {$insql}";
            $params += $inparams;
        }
        return [$sql, $params];
    }

    /**
     * Per-course compliance figures for a scope.
     *
     * For the unscoped (cross-tenant) scope the queries are exactly the
     * block's original ones. For a tenant or team scope, a course appears only
     * when someone in the scope is enrolled in it (so no other tenant's course
     * names show), and both counts are over the scope's people only.
     *
     * @param string $path '' only for a cross-tenant viewer
     * @param int[]|null $userids
     * @return array<int, array{id:int, shortname:string, fullname:string, enddate:int,
     *                          enrolled:int, completed:int}>
     */
    public static function course_stats(string $path, ?array $userids): array {
        global $DB;

        // Defence in depth: '' means "every tenant" and is for cross-tenant
        // viewers only (viewer_scope never hands it to anyone else).
        if ($path === '' && !tenant::is_cross_tenant()) {
            return [];
        }
        $scoped = ($path !== '' || $userids !== null);

        if (!$scoped) {
            $courses = $DB->get_records_select('course',
                'enddate > 0 AND visible = 1 AND id > 1',
                [], 'fullname ASC', 'id,shortname,fullname,enddate');
        } else {
            [$usql, $uparams] = self::population_sql($path, $userids);
            $courses = $DB->get_records_sql(
                "SELECT c.id, c.shortname, c.fullname, c.enddate
                   FROM {course} c
                  WHERE c.enddate > 0 AND c.visible = 1 AND c.id > 1
                    AND EXISTS (SELECT 1
                                  FROM {user_enrolments} ue
                                  JOIN {enrol} e ON e.id = ue.enrolid
                                  JOIN {user} u ON u.id = ue.userid
                                 WHERE e.courseid = c.id AND {$usql})
               ORDER BY c.fullname ASC", $uparams);
        }

        $out = [];
        foreach ($courses as $course) {
            if (!$scoped) {
                $enrolled = (int) $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ue.userid)
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                      WHERE e.courseid = :cid",
                    ['cid' => $course->id]);
                $completed = (int) $DB->count_records_sql(
                    "SELECT COUNT(cc.id)
                       FROM {course_completions} cc
                      WHERE cc.course = :cid AND cc.timecompleted IS NOT NULL",
                    ['cid' => $course->id]);
            } else {
                $enrolled = (int) $DB->count_records_sql(
                    "SELECT COUNT(DISTINCT ue.userid)
                       FROM {user_enrolments} ue
                       JOIN {enrol} e ON e.id = ue.enrolid
                       JOIN {user} u ON u.id = ue.userid
                      WHERE e.courseid = :cid AND {$usql}",
                    ['cid' => $course->id] + $uparams);
                // Completions of the scope's ENROLLED people only.
                $completed = (int) $DB->count_records_sql(
                    "SELECT COUNT(cc.id)
                       FROM {course_completions} cc
                       JOIN {user} u ON u.id = cc.userid
                      WHERE cc.course = :cid AND cc.timecompleted IS NOT NULL AND {$usql}
                        AND EXISTS (SELECT 1
                                      FROM {user_enrolments} ue2
                                      JOIN {enrol} e2 ON e2.id = ue2.enrolid
                                     WHERE e2.courseid = cc.course AND ue2.userid = cc.userid)",
                    ['cid' => $course->id] + $uparams);
            }
            $out[] = [
                'id'        => (int) $course->id,
                'shortname' => (string) $course->shortname,
                'fullname'  => (string) $course->fullname,
                'enddate'   => (int) $course->enddate,
                'enrolled'  => $enrolled,
                'completed' => $completed,
            ];
        }
        return $out;
    }

    /**
     * The CSV export's people: the caller's tenant, or everyone for a
     * cross-tenant caller.
     *
     * @return string '' (cross-tenant) or '/N'
     * @throws \moodle_exception error_outoftenant for a caller with no tenant
     */
    public static function export_scope_path(): string {
        $path = tenant::scope_path();
        if ($path === null) {
            throw new \moodle_exception('error_outoftenant', 'local_sentientia_platform');
        }
        return $path;
    }

    /**
     * The users the audit export lists, for a scope path.
     *
     * Rows are emitted only for enrolled user x mandatory-course pairs, so
     * scoping the people is what keeps other tenants' personal data out -
     * including for a course shared across tenants.
     *
     * @param string $path '' only for a cross-tenant caller
     * @return \stdClass[] keyed by id
     */
    public static function export_users(string $path): array {
        global $DB;
        if ($path === '' && !tenant::is_cross_tenant()) {
            return [];
        }
        [$tsql, $targs] = tenant::path_descendant_filter($path, '', 'open_path', 'cmpx');
        $fields = 'id,firstname,lastname,email';
        $cols = $DB->get_columns('user');
        foreach (['open_employeeid', 'open_departmentid'] as $col) {
            if (isset($cols[$col])) {
                $fields .= ',' . $col;
            }
        }
        return $DB->get_records_select('user',
            "deleted = 0 AND suspended = 0 AND id > 1 AND {$tsql}", $targs,
            'lastname ASC', $fields);
    }
}
