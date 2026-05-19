<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #8 (2026-05-16) — bulk enrol a learning path by target audience.
 *
 * Closes audit item #6 from parity-audit-2026-05-15/airpay_learningpath.md.
 * Before: admins assigning "all Branch Managers in West region" to a path
 * had to scroll through 2000 users in a multi-select. After: pass a
 * filter map and the engine resolves the userset + enrols all matching
 * users via `path_manager::enrol_users()`.
 *
 * Filters supported (all optional; ANDed together; empty = no constraint):
 *   designation     exact match on user.open_designation
 *   region          exact match on user.open_region
 *   location        exact match on user.open_location
 *   employmenttype  exact match on user.open_employmenttype
 *   grade           exact match on user.open_grade
 *   hrmsrole        exact match on user.open_hrmsrole
 *   org_path        path prefix — user.open_path LIKE "$org_path/%" OR = $org_path
 *
 * Tenant scope is layered on top: a non-siteadmin caller cannot enrol
 * users outside their own tenant tree, regardless of filter.
 *
 * @package local_airpay_learningpath
 */
class path_audience_enroller {

    /** Cap on resolved audience size — protects against runaway enrol jobs. */
    public const MAX_AUDIENCE_SIZE = 2000;

    /**
     * Resolve filter map → matching user ids. Caller-tenant-scoped if the
     * caller isn't siteadmin.
     *
     * @param array $filters
     *   designation|region|location|employmenttype|grade|hrmsrole|org_path
     * @param int   $caller_userid
     * @return int[]  Matching user ids (capped at MAX_AUDIENCE_SIZE)
     */
    public static function resolve_audience(array $filters, int $caller_userid): array {
        global $DB;

        $where  = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        // Tenant scope from caller.
        $caller_top = self::caller_tenant_root($caller_userid);
        if ($caller_top > 0) {
            $where[] = '(u.open_path = :tnexact OR u.open_path LIKE :tnprefix)';
            $params['tnexact']  = '/' . $caller_top;
            $params['tnprefix'] = $DB->sql_like_escape('/' . $caller_top . '/') . '%';
        }

        // Per-filter exact matches. Keys are hard-allow-listed to prevent
        // SQL injection via the filter map keys.
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

        // Org path prefix.
        $org_path = (string) ($filters['org_path'] ?? '');
        if ($org_path !== '') {
            $org_path = '/' . trim($org_path, '/');
            $where[] = '(u.open_path = :opathexact OR u.open_path LIKE :opathprefix)';
            $params['opathexact']  = $org_path;
            $params['opathprefix'] = $DB->sql_like_escape($org_path . '/') . '%';
        }

        $wheresql = implode(' AND ', $where);
        $rows = $DB->get_records_sql(
            "SELECT u.id FROM {user} u WHERE $wheresql ORDER BY u.id ASC",
            $params, 0, self::MAX_AUDIENCE_SIZE);

        return array_map(fn($r) => (int) $r->id, array_values($rows));
    }

    /**
     * Preview a target audience without enrolling. Returns
     * `['count' => int, 'sample' => [{id, fullname, email}, ...]]`
     * for the admin to sanity-check before clicking "enrol".
     *
     * @param array $filters
     * @param int   $caller_userid
     * @param int   $sample_size
     * @return array
     */
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

    /**
     * Resolve filters + enrol all matching users.
     *
     * @param int   $pathid
     * @param array $filters
     * @param int   $caller_userid
     * @return array  ['matched' => int, 'enrolled' => int, 'capped' => bool]
     * @throws \moodle_exception
     */
    public static function enrol_by_filter(int $pathid, array $filters,
                                              int $caller_userid): array {
        global $DB;
        $DB->get_record('local_airpay_learningpath', ['id' => $pathid],
            'id, status', MUST_EXIST);

        $userids = self::resolve_audience($filters, $caller_userid);
        $count = count($userids);
        if ($count === 0) {
            return ['matched' => 0, 'enrolled' => 0, 'capped' => false];
        }

        // path_manager::enrol_users() handles the path-user insert + course
        // enrolment via manual enrol (W1-2 chain). It's idempotent —
        // already-enrolled users are silently skipped, so the returned
        // `enrolled` count is the number of NEW enrolments only.
        $enrolled = path_manager::enrol_users($pathid, $userids);

        return [
            'matched'  => $count,
            'enrolled' => $enrolled,
            'capped'   => $count >= self::MAX_AUDIENCE_SIZE,
        ];
    }

    /**
     * Caller's tenant root id (= top-level org). 0 for siteadmin (no constraint).
     */
    private static function caller_tenant_root(int $caller_userid): int {
        if (is_siteadmin($caller_userid)) {
            return 0;
        }
        global $DB;
        $path = (string) ($DB->get_field('user', 'open_path', ['id' => $caller_userid]) ?? '');
        $parts = explode('/', trim($path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }
}
