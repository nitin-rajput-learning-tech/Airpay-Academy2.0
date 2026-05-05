<?php
namespace local_airpay_skills\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_skills extends external_api {

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
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_skills:manage', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_airpay_skills');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'sort_order', 'max_level', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        $categoryid = (int) ($f['categoryid'] ?? 0);
        if ($categoryid > 0) {
            $where[] = 's.categoryid = :catid';
            $sqlparams['catid'] = $categoryid;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('s.name', ':s1', false) . ' OR ' .
                $DB->sql_like('s.description', ':s2', false) . ')';
            $sqlparams['s1'] = $term;
            $sqlparams['s2'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_skills} s WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.*, c.name AS catname
                   FROM {local_airpay_skills} s
              LEFT JOIN {local_airpay_skill_cats} c ON c.id = s.categoryid
                  WHERE $wheresql
               ORDER BY s.$sort $sortdir, s.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $s) {
            $rows[] = [
                'id'        => (int) $s->id,
                'name'      => format_string($s->name),
                'category'  => $s->catname ? format_string($s->catname) : '—',
                'max_level' => (int) $s->max_level,
                'sort'      => (int) $s->sort_order,
                'created'   => $s->timecreated ? userdate($s->timecreated, '%d %b %Y') : '—',
                'actions'   => '<a href="#" class="btn btn-sm btn-link p-1" data-action="edit-skill" data-skillid="' . (int)$s->id . '" data-name="' . s($s->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-skill" data-skillid="' . (int)$s->id . '" data-name="' . s($s->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>',
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
                    'category'  => new external_value(PARAM_TEXT, ''),
                    'max_level' => new external_value(PARAM_INT, ''),
                    'sort'      => new external_value(PARAM_INT, ''),
                    'created'   => new external_value(PARAM_TEXT, ''),
                    'actions'   => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
