<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List users with server-side search, sort, and pagination.
 * Powers the shared datatable module (theme_airpayux/datatable).
 *
 * Contract:
 * - args: {search, sort, sortdir, page, perpage, filters (JSON)}
 * - returns: {total, rows[], page, perpage}
 */
class list_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, 'Search term', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'lastname'),
            'sortdir' => new external_value(PARAM_ALPHA, 'asc|desc', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT, 'Page number (0-indexed)', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Records per page', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, 'JSON-encoded filter map', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'lastname', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_users:view', $context);

        $can_edit   = has_capability('local/airpay_users:edit', $context);
        $can_delete = has_capability('local/airpay_users:delete', $context);

        // ── Sort whitelist ──
        $allowed_sort = ['firstname', 'lastname', 'email', 'open_employeeid',
                         'open_designation', 'lastaccess', 'suspended'];
        $sort = in_array($params['sort'], $allowed_sort, true) ? $params['sort'] : 'lastname';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        // Compose stable secondary sort to keep paging deterministic.
        $orderby = "u.{$sort} {$sortdir}, u.id ASC";

        // ── Filters from client (whitelisted) ──
        // M2 fix: bound size + decode depth so a malicious nested JSON
        // can't spike memory.
        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_airpay_users');
        }
        $client_filters = json_decode($params['filters'], true, 5);
        if (!is_array($client_filters) || json_last_error() !== JSON_ERROR_NONE) {
            $client_filters = [];
        }
        // 2026-05-15 BizLMS parity — multi-level hierarchy filter.
        // Five levels: org_l1 (organisation root tenant) through org_l5
        // (level-5 unit). Whichever is the DEEPEST non-zero value wins —
        // we scope by that node's full path so descendants are included.
        // The single legacy `orgid` parameter is still accepted as a
        // synonym for org_l1 to keep existing API callers working.
        $org_levels = [];
        for ($lvl = 1; $lvl <= 5; $lvl++) {
            $org_levels[$lvl] = (int) ($client_filters['org_l' . $lvl] ?? 0);
        }
        if (!array_filter($org_levels) && !empty($client_filters['orgid'])) {
            // Back-compat: callers using the old single-level filter.
            $org_levels[1] = (int) $client_filters['orgid'];
        }
        // Find the deepest non-zero level — that's the filter target.
        $deepest_orgid = 0;
        for ($lvl = 5; $lvl >= 1; $lvl--) {
            if ($org_levels[$lvl] > 0) { $deepest_orgid = $org_levels[$lvl]; break; }
        }
        $status         = (string) ($client_filters['status']         ?? 'active');
        $email_contains = (string) ($client_filters['email_contains'] ?? '');
        $empid_contains = (string) ($client_filters['empid_contains'] ?? '');

        // P1 batch (2026-05-16) — chip filters for HR attributes. Each is a
        // single-value exact match; pass empty string to skip.
        $designation    = (string) ($client_filters['designation']    ?? '');
        $location       = (string) ($client_filters['location']       ?? '');
        $hrmsrole       = (string) ($client_filters['hrmsrole']       ?? '');
        $employmenttype = (string) ($client_filters['employmenttype'] ?? '');
        $region         = (string) ($client_filters['region']         ?? '');
        $grade          = (string) ($client_filters['grade']          ?? '');

        // P1 batch — multi-value email/empid (CSV string, splits on comma,
        // becomes an `OR LIKE` chain so admins can paste "show me these 30
        // emails" lists).
        $email_list = (string) ($client_filters['email_list'] ?? '');
        $empid_list = (string) ($client_filters['empid_list'] ?? '');

        // ── WHERE assembly ──
        $where = ['u.deleted = 0', 'u.id > 2'];
        $sqlparams = [];

        // Tenant scoping. Two forks:
        //  - client passed any org level → use the DEEPEST one's path
        //  - else fall back to caller's own top-level tenant
        // LIKE pattern is /<id>/% (slash-bounded + escaped) so '/1' never
        // matches '/10' or '/177' (C2 hardening from earlier audit).
        if ($deepest_orgid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $deepest_orgid], 'path');
            if ($org && !empty($org->path)) {
                if (!is_siteadmin()) {
                    $caller_parts = explode('/', trim($USER->open_path ?? '', '/'));
                    $caller_top = isset($caller_parts[0]) && ctype_digit($caller_parts[0])
                        ? '/' . (int) $caller_parts[0] : '';
                    $is_inside = ($org->path === $caller_top)
                        || (strpos($org->path, $caller_top . '/') === 0);
                    if (empty($caller_top) || !$is_inside) {
                        throw new \moodle_exception('outoftenant', 'local_airpay_users');
                    }
                }
                // Match the org's path itself OR any descendant. The OR is
                // necessary because users assigned at the tenant-root (e.g.
                // open_path = '/1' exactly) would otherwise be excluded.
                $where[] = '(u.open_path = :orgexact OR u.open_path LIKE :orgprefix)';
                $sqlparams['orgexact']  = rtrim($org->path, '/');
                $sqlparams['orgprefix'] =
                    $DB->sql_like_escape(rtrim($org->path, '/') . '/') . '%';
            }
        } else if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            if ($top > 0) {
                $where[] = '(u.open_path = :userorgexact OR u.open_path LIKE :userorgprefix)';
                $sqlparams['userorgexact']  = '/' . $top;
                $sqlparams['userorgprefix'] =
                    $DB->sql_like_escape('/' . $top . '/') . '%';
            }
        }

        if ($status === 'active') {
            $where[] = 'u.suspended = 0';
        } else if ($status === 'suspended') {
            $where[] = 'u.suspended = 1';
        }

        // Email-contains filter (BizLMS parity: was a separate field in
        // the legacy users_filters_form, alongside the generic search).
        if ($email_contains !== '') {
            $where[] = $DB->sql_like('u.email', ':emailterm', false);
            $sqlparams['emailterm'] = '%' . $DB->sql_like_escape($email_contains) . '%';
        }
        // Employee-ID-contains filter (BizLMS parity).
        if ($empid_contains !== '') {
            $where[] = $DB->sql_like("COALESCE(u.open_employeeid, '')",
                ':empidterm', false);
            $sqlparams['empidterm'] = '%' . $DB->sql_like_escape($empid_contains) . '%';
        }

        // P1 batch (2026-05-16) — HR-attribute chip filters. The 6 fields
        // below are EXACT-match (came from a distinct-values dropdown so
        // no need for LIKE). Skip empties.
        $chip_map = [
            'designation'    => ['col' => 'u.open_designation',    'val' => $designation],
            'location'       => ['col' => 'u.open_location',       'val' => $location],
            'hrmsrole'       => ['col' => 'u.open_hrmsrole',       'val' => $hrmsrole],
            'employmenttype' => ['col' => 'u.open_employmenttype', 'val' => $employmenttype],
            'region'         => ['col' => 'u.open_region',         'val' => $region],
            'grade'          => ['col' => 'u.open_grade',          'val' => $grade],
        ];
        foreach ($chip_map as $key => $cfg) {
            if ($cfg['val'] !== '') {
                $param_key = 'chip_' . $key;
                $where[] = $cfg['col'] . ' = :' . $param_key;
                $sqlparams[$param_key] = $cfg['val'];
            }
        }

        // P1 batch — multi-value email_list / empid_list.
        // Admin pastes a comma- or newline-separated list; we split, trim,
        // dedupe, and IN-clause it. Cap at 200 values to keep the query
        // sane (Moodle DML limits IN clauses anyway).
        if ($email_list !== '') {
            $emails = self::normalise_csv_list($email_list, 200);
            if (!empty($emails)) {
                [$insql, $inargs] = $DB->get_in_or_equal($emails,
                    SQL_PARAMS_NAMED, 'emaillist', true, '');
                $where[] = 'LOWER(u.email) ' . $insql;
                $sqlparams = array_merge($sqlparams, $inargs);
            }
        }
        if ($empid_list !== '') {
            $empids = self::normalise_csv_list($empid_list, 200);
            if (!empty($empids)) {
                [$insql, $inargs] = $DB->get_in_or_equal($empids,
                    SQL_PARAMS_NAMED, 'empidlist', true, '');
                $where[] = "COALESCE(u.open_employeeid, '') " . $insql;
                $sqlparams = array_merge($sqlparams, $inargs);
            }
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' .
                $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname',  ':s2', false) . ' OR ' .
                $DB->sql_like('u.email',     ':s3', false) . ' OR ' .
                $DB->sql_like("COALESCE(u.open_employeeid, '')", ':s4', false) .
            ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
            $sqlparams['s3'] = $term;
            $sqlparams['s4'] = $term;
        }

        $wheresql = implode(' AND ', $where);

        // ── Counts + page ──
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {user} u WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $sql = "SELECT u.id, u.firstname, u.lastname, u.email, u.suspended, u.lastaccess,
                           u.open_employeeid, u.open_designation, u.open_path, u.department
                      FROM {user} u
                     WHERE $wheresql
                  ORDER BY $orderby";
            $records = $DB->get_records_sql($sql, $sqlparams,
                $params['page'] * $params['perpage'], $params['perpage']);
        }

        // ── Org name lookup ──
        $orgs = $DB->get_records('local_airpay_org', ['depth' => 1], '', 'id, path, fullname');
        $orgmap = [];
        foreach ($orgs as $o) {
            $orgmap[$o->path] = format_string($o->fullname);
        }

        // ── Build rows ──
        $rows = [];
        foreach ($records as $u) {
            $parts = explode('/', trim($u->open_path ?? '', '/'));
            $top = $parts[0] ?? '';
            $orgname = $orgmap['/' . $top] ?? '—';

            $statuslabel = $u->suspended ? 'Suspended' : 'Active';
            $statuscss = $u->suspended ? 'badge-danger' : 'badge-success';

            $profileurl = (new \moodle_url('/local/airpay_users/profile.php', ['id' => $u->id]))->out(false);
            $fullname = trim(($u->firstname ?? '') . ' ' . ($u->lastname ?? ''));
            $fullname_html = '<a href="' . s($profileurl) . '" class="text-reset fw-semibold text-decoration-none">'
                           . s($fullname) . '</a>';

            // Per-row action buttons (rendered by datatable's actionsHtml or row.actions).
            $actions = [];
            if ($can_edit) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-user" data-userid="' . (int) $u->id . '" '
                    . 'data-name="' . s($fullname) . '" title="Edit"><i class="fa fa-pencil"></i></a>';
                $verb = $u->suspended ? 'activate' : 'suspend';
                $icon = $u->suspended ? 'fa-undo text-success' : 'fa-ban text-warning';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="' . $verb . '-user" data-userid="' . (int) $u->id . '" '
                    . 'data-name="' . s($fullname) . '" title="' . ucfirst($verb) . '"><i class="fa ' . $icon . '"></i></a>';
            }
            if ($can_delete) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-user" data-userid="' . (int) $u->id . '" '
                    . 'data-name="' . s($fullname) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'          => (int) $u->id,
                'fullname'    => $fullname_html,
                'employeeid'  => $u->open_employeeid ?? '—',
                'email'       => $u->email,
                'orgname'     => $orgname,
                'designation' => $u->open_designation ?? '—',
                'lastaccess'  => $u->lastaccess ? userdate($u->lastaccess, '%d %b %Y') : 'Never',
                'statuslabel' => $statuslabel,
                'statuscss'   => $statuscss,
                'actions'     => implode(' ', $actions),
            ];
        }

        return [
            'total'   => $total,
            'rows'    => $rows,
            'page'    => $params['page'],
            'perpage' => $params['perpage'],
        ];
    }

    /**
     * P1 batch (2026-05-16) — split a CSV/newline-separated list into a
     * sanitised array. Lower-cases each entry, trims whitespace, deduplicates,
     * and caps the result at $max_items to bound the resulting IN clause.
     *
     * @param string $raw  Comma- or newline-separated input
     * @param int    $max_items
     * @return string[]  Cleaned values; possibly empty.
     */
    private static function normalise_csv_list(string $raw, int $max_items = 200): array {
        $values = preg_split('/[,\n\r;]+/', $raw) ?: [];
        $cleaned = [];
        foreach ($values as $v) {
            $v = strtolower(trim($v));
            if ($v !== '') {
                $cleaned[$v] = true;  // dedup via array-key
            }
        }
        return array_slice(array_keys($cleaned), 0, $max_items);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matching rows'),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'User ID'),
                    'fullname'    => new external_value(PARAM_RAW, 'Full name (HTML — linked profile)'),
                    'employeeid'  => new external_value(PARAM_TEXT, 'Employee ID'),
                    'email'       => new external_value(PARAM_TEXT, 'Email'),
                    'orgname'     => new external_value(PARAM_TEXT, 'Tenant name'),
                    'designation' => new external_value(PARAM_TEXT, 'Designation'),
                    'lastaccess'  => new external_value(PARAM_TEXT, 'Last access (formatted)'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Active|Suspended'),
                    'statuscss'   => new external_value(PARAM_TEXT, 'Bootstrap badge class'),
                    'actions'     => new external_value(PARAM_RAW, 'Per-row HTML actions'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Records per page'),
        ]);
    }
}
