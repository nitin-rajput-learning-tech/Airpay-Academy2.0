<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * P1 batch (2026-05-16) — tenant-scoped supervisor autocomplete WS.
 *
 * Replaces `core_user/form_user_selector` on the edit-user form, which is
 * NOT tenant-aware: a Public-tenant admin could otherwise pick an
 * Airpay-tenant manager and silently break the org chart.
 *
 * Scope rules:
 *   - siteadmin               → can pick any non-deleted, non-suspended user
 *   - other callers           → restricted to their own open_path tenant root
 *   - manager-of-edited-user  → if the edited user already has an open_path,
 *                               we ALSO restrict to that user's tenant tree
 *                               (the supervisor must live in the same tenant
 *                               as the subordinate, never higher).
 *
 * Returns up to 50 results, ranked alpha by lastname/firstname. Caller must
 * provide at least 2 characters in `query` to avoid loading everyone.
 *
 * @package local_sentientia_users
 */
class search_supervisors extends external_api {

    public const MAX_RESULTS = 50;
    public const MIN_QUERY_LEN = 2;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query'           => new external_value(PARAM_TEXT,
                'Search substring (matched against firstname, lastname, email, employee code)'),
            'subject_userid'  => new external_value(PARAM_INT,
                'User being edited. 0 = creating a new user (caller tenant only). '
                . 'If supplied, scope is intersected with this user\'s tenant.',
                VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(string $query, int $subject_userid = 0): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('query', 'subject_userid'));
        $query          = trim($params['query']);
        $subject_userid = (int) $params['subject_userid'];

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_users:view', $context);

        if (strlen($query) < self::MIN_QUERY_LEN) {
            return ['total' => 0, 'rows' => []];
        }

        // ── Build the tenant-scope WHERE fragment ────────────────────────
        [$scope_sql, $scope_args] = self::build_scope_filter($USER, $subject_userid);

        // ── Search term (LIKE across 4 columns) ──────────────────────────
        $term = '%' . $DB->sql_like_escape($query) . '%';
        $search_sql = '('
            . $DB->sql_like('u.firstname',                              ':s1', false) . ' OR '
            . $DB->sql_like('u.lastname',                               ':s2', false) . ' OR '
            . $DB->sql_like('u.email',                                  ':s3', false) . ' OR '
            . $DB->sql_like("COALESCE(u.open_employeeid, '')",          ':s4', false)
        . ')';

        $params = array_merge($scope_args, [
            's1' => $term, 's2' => $term, 's3' => $term, 's4' => $term,
        ]);

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email,
                       u.open_employeeid, u.open_designation
                  FROM {user} u
                 WHERE u.deleted = 0
                   AND u.suspended = 0
                   AND u.id > 2
                   $scope_sql
                   AND $search_sql
              ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";

        $records = $DB->get_records_sql($sql, $params, 0, self::MAX_RESULTS);

        $rows = [];
        foreach ($records as $r) {
            $label = trim($r->firstname . ' ' . $r->lastname);
            if (!empty($r->open_designation)) {
                $label .= ' — ' . $r->open_designation;
            }
            $rows[] = [
                'id'    => (int) $r->id,
                'label' => $label,
                'email' => (string) $r->email,
                'empid' => (string) ($r->open_employeeid ?? ''),
            ];
        }

        return ['total' => count($rows), 'rows' => $rows];
    }

    /**
     * Build the tenant-scope WHERE fragment.
     *
     * @return array{0:string, 1:array}  [sql_fragment, named_params]
     */
    private static function build_scope_filter(\stdClass $caller,
                                                  int $subject_userid): array {
        global $DB;

        // siteadmin: no extra filter.
        if (is_siteadmin($caller)) {
            // BUT if subject_userid is set, still scope to subject's tenant.
            if ($subject_userid > 0) {
                $subject_path = (string) $DB->get_field('user', 'open_path',
                    ['id' => $subject_userid]);
                if ($subject_path !== '') {
                    $top = self::tenant_root_from_path($subject_path);
                    if ($top > 0) {
                        return [
                            "AND (u.open_path = :subjpathexact OR u.open_path LIKE :subjpathprefix)",
                            ['subjpathexact' => '/' . $top,
                             'subjpathprefix' => '/' . $top . '/%'],
                        ];
                    }
                }
            }
            return ['', []];
        }

        // Non-siteadmin: always scoped to caller's tenant.
        $caller_top = self::tenant_root_from_path(
            (string) ($caller->open_path ?? ''));
        if ($caller_top === 0) {
            // Caller has no tenant — return zero results.
            return ['AND 1=0', []];
        }
        return [
            "AND (u.open_path = :callerpathexact OR u.open_path LIKE :callerpathprefix)",
            [
                'callerpathexact'  => '/' . $caller_top,
                'callerpathprefix' => '/' . $caller_top . '/%',
            ],
        ];
    }

    /**
     * Parse the top-level numeric segment out of an open_path string.
     * '/1/3/5' → 1.   '/77' → 77.   ''/null → 0.
     */
    private static function tenant_root_from_path(string $path): int {
        $parts = explode('/', trim($path, '/'));
        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, 'Number of results returned'),
            'rows'  => new external_multiple_structure(
                new external_single_structure([
                    'id'    => new external_value(PARAM_INT,  'User ID'),
                    'label' => new external_value(PARAM_TEXT, 'Display label (name + designation)'),
                    'email' => new external_value(PARAM_TEXT, 'Email'),
                    'empid' => new external_value(PARAM_TEXT, 'Employee code'),
                ])
            ),
        ]);
    }
}
