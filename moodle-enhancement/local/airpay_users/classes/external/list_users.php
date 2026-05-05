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
        $client_filters = json_decode($params['filters'], true) ?: [];
        $orgid  = (int)  ($client_filters['orgid']  ?? 0);
        $status = (string) ($client_filters['status'] ?? 'active');

        // ── WHERE assembly ──
        $where = ['u.deleted = 0', 'u.id > 2'];
        $sqlparams = [];

        if ($orgid > 0) {
            $org = $DB->get_record('local_airpay_org', ['id' => $orgid], 'path');
            if ($org && !empty($org->path)) {
                $where[] = 'u.open_path LIKE :orgpath';
                $sqlparams['orgpath'] = $org->path . '%';
            }
        } else if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = $parts[0] ?? '';
            if (!empty($top)) {
                $where[] = 'u.open_path LIKE :userorg';
                $sqlparams['userorg'] = '/' . $top . '%';
            }
        }

        if ($status === 'active') {
            $where[] = 'u.suspended = 0';
        } else if ($status === 'suspended') {
            $where[] = 'u.suspended = 1';
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
