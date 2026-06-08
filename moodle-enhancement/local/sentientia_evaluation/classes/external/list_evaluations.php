<?php
namespace local_sentientia_evaluation\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_evaluations extends external_api {

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
        require_capability('local/sentientia_evaluation:manage', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_sentientia_evaluation');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'kirkpatrick_level', 'status', 'timemodified'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        // W1-1 BizLMS parity: 5-level org cascade overrides default
        // tenant scope.
        [$cascadesql, $cascadeargs] =
            \local_airpay_org\org_manager::cascade_where_sql($f, 'e');
        if ($cascadesql !== '') {
            $where[] = $cascadesql;
            $sqlparams = array_merge($sqlparams, $cascadeargs);
        } else {
            // Phase 9.6: back-ported from inline open_path pattern to the
            // shared `\local_airpay_core\tenant::path_filter()` helper.
            [$tnsql, $tnargs] = \local_airpay_core\tenant::path_filter('e');
            $where[] = $tnsql;
            $sqlparams = array_merge($sqlparams, $tnargs);
        }

        $status_filter = (string) ($f['status'] ?? 'all');
        if ($status_filter !== 'all' && ctype_digit($status_filter)) {
            $where[] = 'e.status = :sfilter';
            $sqlparams['sfilter'] = (int) $status_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('e.name', ':s1', false) . ' OR ' .
                $DB->sql_like('e.description', ':s2', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_evaluation} e WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT e.*,
                        (SELECT COUNT(*) FROM {local_sentientia_evaluation_questions} q WHERE q.evaluationid = e.id) AS qcount,
                        (SELECT COUNT(*) FROM {local_sentientia_evaluation_responses} r WHERE r.evaluationid = e.id) AS rcount
                   FROM {local_sentientia_evaluation} e
                  WHERE $wheresql
               ORDER BY e.$sort $sortdir, e.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $kp_short = [1 => 'L1 Reaction', 2 => 'L2 Learning', 3 => 'L3 Behaviour', 4 => 'L4 Results'];
        $statusmap = [0 => 'Draft', 1 => 'Active', 2 => 'Archived'];
        $cssmap = [0 => 'badge-secondary', 1 => 'badge-success', 2 => 'badge-warning'];

        $rows = [];
        foreach ($records as $e) {
            $status = (int) $e->status;
            $rows[] = [
                'id'           => (int) $e->id,
                'name'         => s($e->name),
                'kirkpatrick'  => $kp_short[(int) $e->kirkpatrick_level] ?? '—',
                'qcount'       => (int) $e->qcount,
                'rcount'       => (int) $e->rcount,
                'modified'     => $e->timemodified ? userdate($e->timemodified, '%d %b %Y') : '—',
                'statuslabel'  => $statusmap[$status] ?? 'Unknown',
                'statuscss'    => $cssmap[$status] ?? 'badge-secondary',
                'actions'      => '<a href="questions.php?id=' . (int)$e->id . '" class="btn btn-sm btn-link p-1" title="Questions"><i class="fa fa-list-ol"></i></a>'
                    . ' <a href="responses.php?id=' . (int)$e->id . '" class="btn btn-sm btn-link p-1" title="Responses"><i class="fa fa-bar-chart"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="edit-evaluation" data-evaluationid="' . (int)$e->id . '" data-name="' . s($e->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-evaluation" data-evaluationid="' . (int)$e->id . '" data-name="' . s($e->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>',
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, ''),
                    'name'         => new external_value(PARAM_TEXT, ''),
                    'kirkpatrick'  => new external_value(PARAM_TEXT, ''),
                    'qcount'       => new external_value(PARAM_INT, ''),
                    'rcount'       => new external_value(PARAM_INT, ''),
                    'modified'     => new external_value(PARAM_TEXT, ''),
                    'statuslabel'  => new external_value(PARAM_TEXT, ''),
                    'statuscss'    => new external_value(PARAM_TEXT, ''),
                    'actions'      => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
