<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_evaluation;

defined('MOODLE_INTERNAL') || die();

/**
 * P1 #39 (2026-05-20) — bulk-assign an evaluation by target audience.
 *
 * Parallel-port of `\local_sentientia_classroom\classroom_audience_enroller`
 * (P1 #13), `\local_sentientia_programs\program_audience_enroller` (P1 #9),
 * and `\local_sentientia_learningpath\path_audience_enroller` (P1 #8).
 * Same filter shape, same tenant-scope rules, same MAX_AUDIENCE_SIZE
 * cap, same cohort filter. Delegates to
 * `evaluation_manager::ensure_assignment()` (P1 #37) which is
 * idempotent — already-assigned learners are silently skipped.
 *
 * Filters supported (all optional; ANDed together; empty = no constraint; but assign_by_filter() needs at least one
 * that says something, {@see self::has_constraint()}: an empty rule no longer means "everyone in my tenant"):
 *   designation, region, location, employmenttype, grade, hrmsrole
 *     → exact match on user.open_* column
 *   org_path
 *     → prefix match (path = org_path OR LIKE org_path/%)
 *   cohortid
 *     → EXISTS (SELECT 1 FROM cohort_members ...)
 *
 * Closes the bulk-assign half of audit item #21 from
 * parity-audit-2026-05-15/sentientia_evaluation.md. Pairs with P1 #37's
 * back-end and P1 #38's show-non-respondents view to give admins the
 * full "assign N, see who responded" workflow.
 *
 * @package local_sentientia_evaluation
 */
class evaluation_audience_assigner {

    public const MAX_AUDIENCE_SIZE = 2000;

    /** Filter keys that match one user column exactly, and the column each one matches. */
    private const EXACT_FILTERS = [
        'designation'    => 'u.open_designation',
        'region'         => 'u.open_region',
        'location'       => 'u.open_location',
        'employmenttype' => 'u.open_employmenttype',
        'grade'          => 'u.open_grade',
        'hrmsrole'       => 'u.open_hrmsrole',
    ];

    /**
     * Does a filter map narrow the audience at all?
     *
     * An empty map used to mean "everyone in my tenant" (up to MAX_AUDIENCE_SIZE users), which the bulk-assign form
     * already refuses ("pick at least one filter criterion") but the web service and assign_by_filter() did not
     * (EV-36). A value counts only when it says something: a blank or whitespace-only string, a cohort id that is
     * not positive, an org path that is only slashes, or anything that is not a plain value is no constraint.
     * An org path that names a tenant root IS one, however many users that is: the caller chose the whole node.
     *
     * @param array $filters The filter map, as resolve_audience() reads it.
     * @return bool
     */
    public static function has_constraint(array $filters): bool {
        $text = static fn($value): string => is_scalar($value) ? trim((string) $value) : '';
        foreach (array_keys(self::EXACT_FILTERS) as $key) {
            if ($text($filters[$key] ?? '') !== '') {
                return true;
            }
        }
        if (trim($text($filters['org_path'] ?? ''), '/') !== '') {
            return true;
        }
        $cohortid = $filters['cohortid'] ?? 0;
        return is_scalar($cohortid) && (int) $cohortid > 0;
    }

    /**
     * Resolve a filter map → matching user ids. Tenant-scoped unless
     * the caller is siteadmin.
     */
    public static function resolve_audience(array $filters, int $caller_userid): array {
        global $DB;

        $where  = ['u.deleted = 0', 'u.suspended = 0', 'u.id > 2'];
        $params = [];

        // ADR-031: the caller's scope - '' only for a cross-tenant caller,
        // '/N' for a tenant, null (nothing at all) for a caller who is
        // neither. A non-admin with no open_path used to get NO filter here,
        // i.e. every active user of every tenant.
        $caller = $DB->get_record('user', ['id' => $caller_userid], 'id, open_path');
        $scope = $caller ? \local_sentientia_platform\tenant::scope_path($caller) : null;
        if ($scope === null) {
            return [];
        }
        if ($scope !== '') {
            [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_descendant_filter(
                $scope, 'u', 'open_path', 'tn');
            $where[] = $tnsql;
            $params = array_merge($params, $tnargs);
        }

        foreach (self::EXACT_FILTERS as $key => $col) {
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

    /**
     * Resolve filters + create assignment rows for every matched user.
     * Returns counts. `assigned_by_userid` is the caller — recorded on
     * each row for the audit trail.
     *
     * An empty audience rule is refused (EV-36): with no filter at all, or only blank ones, this used to assign the
     * whole tenant (up to MAX_AUDIENCE_SIZE people). The form already asked for at least one criterion; the web
     * service did not. The evaluation is looked up first, so a missing one is still reported as missing; nothing is
     * written before the refusal.
     *
     * @throws \moodle_exception bulk_assign_pick_at_least_one when no filter narrows the audience
     */
    public static function assign_by_filter(int $evaluationid,
                                              array $filters,
                                              int $caller_userid,
                                              ?int $due_at = null): array {
        global $DB;
        $DB->get_record('local_sentientia_evaluation', ['id' => $evaluationid],
            'id, status', MUST_EXIST);

        if (!self::has_constraint($filters)) {
            throw new \moodle_exception('bulk_assign_pick_at_least_one', 'local_sentientia_evaluation');
        }

        $userids = self::resolve_audience($filters, $caller_userid);
        $count = count($userids);
        if ($count === 0) {
            return [
                'matched'  => 0,
                'assigned' => 0,
                'capped'   => false,
            ];
        }

        // ensure_assignment is idempotent (UNIQUE index dedupes); we
        // count how many were NEW vs ALREADY-ASSIGNED so the admin
        // sees an accurate result.
        $new_count = 0;
        foreach ($userids as $uid) {
            $before = $DB->record_exists('local_sentientia_evaluation_assign', [
                'evaluationid'  => $evaluationid,
                'userid'        => $uid,
                'trigger_event' => 'manual',
                'source_id'     => 0,
            ]);
            evaluation_manager::ensure_assignment(
                $evaluationid, $uid, 'manual', 0,
                $caller_userid, $due_at);
            if (!$before) {
                $new_count++;
            }
        }

        return [
            'matched'  => $count,
            'assigned' => $new_count,
            'capped'   => $count >= self::MAX_AUDIENCE_SIZE,
        ];
    }
}
