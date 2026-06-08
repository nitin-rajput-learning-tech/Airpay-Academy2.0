<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Server-side list classrooms for the shared theme_airpayux/datatable.
 */
class list_classrooms extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT,     'Search term', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'name'),
            'sortdir' => new external_value(PARAM_ALPHA,    'asc|desc',    VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      'Page',        VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per page',    VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      'JSON filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'name', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_classroom:view', $context);

        $can_manage = has_capability('local/sentientia_classroom:manage', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_classroom');
        }
        $client_filters = json_decode($params['filters'], true, 5);
        if (!is_array($client_filters) || json_last_error() !== JSON_ERROR_NONE) {
            $client_filters = [];
        }

        $allowed = ['name', 'location', 'capacity', 'status', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';
        $orderby = "c.{$sort} {$sortdir}, c.id ASC";

        $where = ['1=1'];
        $sqlparams = [];

        // W1-1 BizLMS parity: 5-level org cascade overrides default
        // tenant scope. If the user picked a specific org (any level
        // 1..5), filter to that subtree. Otherwise apply the default
        // path_filter (caller's own tenant tree).
        [$cascadesql, $cascadeargs] =
            \local_sentientia_org\org_manager::cascade_where_sql($client_filters, 'c');
        if ($cascadesql !== '') {
            $where[] = $cascadesql;
            $sqlparams = array_merge($sqlparams, $cascadeargs);
        } else {
            [$tnsql, $tnargs] = \local_airpay_core\tenant::path_filter('c');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
        }

        // Status filter.
        $status_filter = (string) ($client_filters['status'] ?? 'all');
        if ($status_filter !== 'all' && ctype_digit($status_filter)) {
            $where[] = 'c.status = :sfilter';
            $sqlparams['sfilter'] = (int) $status_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('c.name', ':s1', false) . ' OR ' .
                $DB->sql_like('c.location', ':s2', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_classroom} c WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT c.* FROM {local_sentientia_classroom} c
                  WHERE $wheresql
               ORDER BY $orderby",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $c) {
            $statusmap = [0 => 'Cancelled', 1 => 'Active', 2 => 'Completed'];
            $cssmap = [0 => 'badge-secondary', 1 => 'badge-success', 2 => 'badge-info'];
            $statuslabel = $statusmap[(int) $c->status] ?? 'Unknown';
            $statuscss = $cssmap[(int) $c->status] ?? 'badge-secondary';

            $viewurl = (new \moodle_url('/local/sentientia_classroom/view.php', ['id' => $c->id]))->out(false);
            $name_html = '<a href="' . s($viewurl) . '" class="text-reset fw-semibold text-decoration-none">'
                . s($c->name) . '</a>';

            $actions = [];
            if ($can_manage) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-classroom" data-classroomid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-classroom" data-classroomid="' . (int) $c->id . '" '
                    . 'data-name="' . s($c->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'          => (int) $c->id,
                'name'        => $name_html,
                'location'    => s($c->location ?? '—'),
                'capacity'    => (int) $c->capacity,
                'created'     => $c->timecreated ? userdate($c->timecreated, '%d %b %Y') : '—',
                'statuslabel' => $statuslabel,
                'statuscss'   => $statuscss,
                'actions'     => implode(' ', $actions),
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total matches'),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, 'ID'),
                    'name'        => new external_value(PARAM_RAW, 'Name (HTML)'),
                    'location'    => new external_value(PARAM_TEXT, 'Location'),
                    'capacity'    => new external_value(PARAM_INT, 'Capacity'),
                    'created'     => new external_value(PARAM_TEXT, 'Created date'),
                    'statuslabel' => new external_value(PARAM_TEXT, 'Status label'),
                    'statuscss'   => new external_value(PARAM_TEXT, 'Badge class'),
                    'actions'     => new external_value(PARAM_RAW, 'Per-row HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
