<?php
namespace local_airpay_reports\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_reports extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT,     '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'name'),
            'sortdir' => new external_value(PARAM_ALPHA,    '', VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'name', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_reports:view', $context);

        $can_manage = has_capability('local/airpay_reports:manage', $context);
        $can_export = has_capability('local/airpay_reports:export', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_airpay_reports');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'report_type', 'status', 'lastrun', 'runcount', 'timemodified'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        // W1-1 BizLMS parity: 5-level org cascade overrides default
        // tenant scope.
        [$cascadesql, $cascadeargs] =
            \local_airpay_org\org_manager::cascade_where_sql($f, 'r');
        if ($cascadesql !== '') {
            $where[] = $cascadesql;
            $sqlparams = array_merge($sqlparams, $cascadeargs);
        } else {
            // Phase 9.6: back-ported to shared tenant helper. Reports
            // without open_path remain siteadmin-only (helper returns 1=1
            // for siteadmin, which matches; tenant users get the path
            // filter without the IS-NULL clause so unscoped reports
            // are not visible to them — same contract as before).
            [$tnsql, $tnargs] = \local_airpay_core\tenant::path_filter('r');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
        }

        $status_filter = (string) ($f['status'] ?? 'all');
        if ($status_filter !== 'all' && ctype_digit($status_filter)) {
            $where[] = 'r.status = :sfilter';
            $sqlparams['sfilter'] = (int) $status_filter;
        }

        $type_filter = (string) ($f['report_type'] ?? '');
        if (!empty($type_filter)) {
            $where[] = 'r.report_type = :tfilter';
            $sqlparams['tfilter'] = $type_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('r.name', ':s1', false) . ' OR ' .
                $DB->sql_like('r.description', ':s2', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_reports} r WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT r.* FROM {local_airpay_reports} r
                  WHERE $wheresql
               ORDER BY r.$sort $sortdir, r.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $type_short = \local_airpay_reports\report_manager::REPORT_TYPE_SHORT;

        $rows = [];
        foreach ($records as $r) {
            $statuslabel = $r->status == 1 ? 'Active' : 'Archived';
            $statuscss = $r->status == 1 ? 'badge-success' : 'badge-warning';

            $type_label = $type_short[$r->report_type] ?? ucfirst(str_replace('_', ' ', $r->report_type));

            $actions = '<a href="run.php?id=' . (int)$r->id . '" class="btn btn-sm btn-outline-primary me-1">'
                . '<i class="fa fa-play fa-fw"></i> Run</a>';
            if ($can_manage) {
                $actions .= ' <a href="#" class="btn btn-sm btn-link p-1" data-action="edit-report" data-reportid="' . (int)$r->id . '" data-name="' . s($r->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>';
                $actions .= ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-report" data-reportid="' . (int)$r->id . '" data-name="' . s($r->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }
            if ($can_export) {
                $actions .= ' <a href="export.php?id=' . (int)$r->id . '" class="btn btn-sm btn-link p-1" title="Export CSV"><i class="fa fa-download"></i></a>';
            }

            $rows[] = [
                'id'        => (int) $r->id,
                'name'      => format_string($r->name),
                'type'      => $type_label,
                'lastrun'   => $r->lastrun ? userdate($r->lastrun, '%d %b %Y, %H:%M') : '—',
                'runcount'  => (int) $r->runcount,
                'statuslabel' => $statuslabel,
                'statuscss' => $statuscss,
                'actions'   => $actions,
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'        => new external_value(PARAM_INT, ''),
                    'name'      => new external_value(PARAM_TEXT, ''),
                    'type'      => new external_value(PARAM_TEXT, ''),
                    'lastrun'   => new external_value(PARAM_TEXT, ''),
                    'runcount'  => new external_value(PARAM_INT, ''),
                    'statuslabel' => new external_value(PARAM_TEXT, ''),
                    'statuscss' => new external_value(PARAM_TEXT, ''),
                    'actions'   => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
