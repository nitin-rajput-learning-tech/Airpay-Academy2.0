<?php
namespace local_airpay_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_programs extends external_api {

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
        require_capability('local/airpay_programs:view', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_airpay_programs');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'status', 'visible', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        // W1-1 BizLMS parity: 5-level org cascade overrides default
        // tenant scope.
        [$cascadesql, $cascadeargs] =
            \local_airpay_org\org_manager::cascade_where_sql($f, 'p');
        if ($cascadesql !== '') {
            $where[] = $cascadesql;
            $sqlparams = array_merge($sqlparams, $cascadeargs);
        } else {
            [$tnsql, $tnargs] = \local_airpay_core\tenant::path_filter('p');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
        }

        $status_filter = (string) ($f['status'] ?? 'all');
        if ($status_filter !== 'all' && ctype_digit($status_filter)) {
            $where[] = 'p.status = :sfilter';
            $sqlparams['sfilter'] = (int) $status_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('p.name', ':s1', false) . ' OR ' .
                $DB->sql_like('p.description', ':s2', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_programs} p WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT p.*,
                        (SELECT COUNT(*) FROM {local_airpay_programs_levels} l WHERE l.programid = p.id) AS level_count,
                        (SELECT COUNT(*) FROM {local_airpay_programs_users} pu WHERE pu.programid = p.id) AS user_count
                   FROM {local_airpay_programs} p
                  WHERE $wheresql
               ORDER BY p.$sort $sortdir, p.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        // Status: 0=draft, 1=active, 2=archived (matches install.xml).
        $statusmap = [0 => 'Draft', 1 => 'Active', 2 => 'Archived'];
        $cssmap = [0 => 'badge-secondary', 1 => 'badge-success', 2 => 'badge-info'];

        $rows = [];
        foreach ($records as $p) {
            $viewurl = (new \moodle_url('/local/airpay_programs/view.php',
                ['id' => (int) $p->id]))->out(false);
            $name_html = '<a href="' . s($viewurl) . '" class="text-reset fw-semibold text-decoration-none">'
                . format_string($p->name) . '</a>';

            $rows[] = [
                'id'          => (int) $p->id,
                'name'        => $name_html,
                'levels'      => (int) ($p->level_count ?? 0),
                'enrolled'    => (int) ($p->user_count ?? 0),
                'created'     => $p->timecreated ? userdate($p->timecreated, '%d %b %Y') : '—',
                'statuslabel' => $statusmap[(int) $p->status] ?? 'Unknown',
                'statuscss'   => $cssmap[(int) $p->status] ?? 'badge-secondary',
                'actions'     => '<a href="#" class="btn btn-sm btn-link p-1" data-action="edit-program" data-programid="' . (int)$p->id . '" data-name="' . s($p->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-program" data-programid="' . (int)$p->id . '" data-name="' . s($p->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>',
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT, ''),
                    'name'        => new external_value(PARAM_RAW, ''),
                    'levels'      => new external_value(PARAM_INT, ''),
                    'enrolled'    => new external_value(PARAM_INT, ''),
                    'created'     => new external_value(PARAM_TEXT, ''),
                    'statuslabel' => new external_value(PARAM_TEXT, ''),
                    'statuscss'   => new external_value(PARAM_TEXT, ''),
                    'actions'     => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
