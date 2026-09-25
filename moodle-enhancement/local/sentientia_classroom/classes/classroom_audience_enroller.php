<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #13 (2026-05-16) — bulk enrol a classroom by target audience.
 *
 * Sibling of `\local_sentientia_learningpath\path_audience_enroller` and
 * `\local_sentientia_programs\program_audience_enroller`. Same filter shape,
 * same tenant-scope rules, same MAX_AUDIENCE_SIZE cap, same cohort
 * filter. Delegates the actual enrolment to
 * `session_manager::enrol_users()` (which writes to
 * `local_sentientia_classroom_users` and is idempotent).
 *
 * Filters supported (all optional; ANDed together; empty = no constraint):
 *   designation, region, location, employmenttype, grade, hrmsrole
 *     → exact match on user.open_* column
 *   org_path
 *     → prefix match (path = org_path OR LIKE org_path/%)
 *   cohortid
 *     → EXISTS (SELECT 1 FROM cohort_members ...) — intersects with above
 *
 * @package local_sentientia_classroom
 */
class classroom_audience_enroller {

    public const MAX_AUDIENCE_SIZE = 2000;

    /**
     * Resolve filter map → matching user ids, inside the caller's tenant.
     *
     * ADR-031: cross-tenant callers (site admin, :crosstenant) are unscoped;
     * a scoped caller gets their own tenant; a caller whose tenant does not
     * resolve gets NOBODY. Until 2026-09-25 that last case skipped the
     * tenant clause and matched every tenant's users (up to the cap).
     */
    public static function resolve_audience(array $filters, int $caller_userid): array {
        global $DB;

        $where  = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        $scope = self::caller_scope($caller_userid);
        if ($scope === null) {
            return [];
        }
        if ($scope !== '') {
            [$tsql, $targs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $scope, 'u', 'open_path', 'tn');
            $where[] = $tsql;
            $params = array_merge($params, $targs);
        }

        $allowed_exact = [
            'designation'    => 'u.open_designation',
            'region'         => 'u.open_region',
            'location'       => 'u.open_location',
            'employmenttype' => 'u.open_employmenttype',
            'grade'          => 'u.open_grade',
            'hrmsrole'       => 'u.open_hrmsrole',
        ];
        foreach ($allowed_exact as $key => $col) {
            $val = (string) ($filters[$key] ?? '');
            if ($val !== '') {
                $param_key = 'flt_' . $key;
                $where[] = $col . ' = :' . $param_key;
                $params[$param_key] = $val;
            }
        }

        $org_path = (string) ($filters['org_path'] ?? '');
        if ($org_path !== '') {
            $org_path = '/' . trim($org_path, '/');
            $where[] = '(u.open_path = :opathexact OR u.open_path LIKE :opathprefix)';
            $params['opathexact']  = $org_path;
            $params['opathprefix'] = $DB->sql_like_escape($org_path . '/') . '%';
        }

        // P1 #10 (2026-05-16) — cohort membership filter. Mirrors siblings.
        $cohortid = (int) ($filters['cohortid'] ?? 0);
        if ($cohortid > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM {cohort_members} cm '
                     . '  WHERE cm.userid = u.id AND cm.cohortid = :flt_cohortid)';
            $params['flt_cohortid'] = $cohortid;
        }

        $wheresql = implode(' AND ', $where);
        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE $wheresql ORDER BY u.id ASC",
            $params, 0, self::MAX_AUDIENCE_SIZE);

        return array_map(fn($r) => (int) $r->id, array_values($rows));
    }

    /** Preview audience: ['count' => int, 'sample' => [...], 'capped_at' => int]. */
    public static function preview(array $filters, int $caller_userid,
                                     int $sample_size = 10): array {
        global $DB;
        $ids = self::resolve_audience($filters, $caller_userid);
        $sample = [];
        if (!empty($ids)) {
            $sample_ids = array_slice($ids, 0, $sample_size);
            [$insql, $inparams] = $DB->get_in_or_equal($sample_ids,
                SQL_PARAMS_NAMED, 'sid');
            $rows = $DB->get_records_sql(
                "SELECT id, firstname, lastname, email
                   FROM {user} WHERE id $insql
                  ORDER BY lastname ASC, firstname ASC",
                $inparams);
            foreach ($rows as $r) {
                $sample[] = [
                    'id'       => (int) $r->id,
                    'fullname' => trim($r->firstname . ' ' . $r->lastname),
                    'email'    => $r->email,
                ];
            }
        }
        return [
            'count'     => count($ids),
            'sample'    => $sample,
            'capped_at' => self::MAX_AUDIENCE_SIZE,
        ];
    }

    /** Resolve + enrol. Returns ['matched','enrolled','capped']. */
    public static function enrol_by_filter(int $classroomid, array $filters,
                                              int $caller_userid): array {
        // ADR-031: the target classroom must be in the caller's tenant.
        session_manager::require_classroom_access($classroomid, $caller_userid);

        $userids = self::resolve_audience($filters, $caller_userid);
        $count = count($userids);
        if ($count === 0) {
            return ['matched' => 0, 'enrolled' => 0, 'capped' => false];
        }

        // session_manager::enrol_users() inserts the classroom-user row;
        // it's idempotent — already-enrolled users are silently skipped.
        $enrolled = session_manager::enrol_users($classroomid, $userids);

        return [
            'matched'  => $count,
            'enrolled' => $enrolled,
            'capped'   => $count >= self::MAX_AUDIENCE_SIZE,
        ];
    }

    /**
     * The caller's tenant scope (ADR-031, tenant::scope_path()): '' for a
     * cross-tenant caller, '/N' for a scoped one, null = no tenant (nothing).
     * Replaces caller_tenant_root(), which returned 0 both for a site admin
     * and for a caller with no tenant - and 0 meant "no constraint".
     */
    private static function caller_scope(int $caller_userid): ?string {
        global $DB;
        $caller = $DB->get_record('user', ['id' => $caller_userid], 'id, open_path');
        if (!$caller) {
            return null;
        }
        return \local_sentientia_platform\tenant::scope_path($caller);
    }
}
