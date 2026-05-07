<?php
namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_paths extends external_api {

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
        require_capability('local/airpay_learningpath:view', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_airpay_learningpath');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'status', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        if (!is_siteadmin()) {
            $parts = explode('/', trim($USER->open_path ?? '', '/'));
            $top = isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
            if ($top > 0) {
                $where[] = '(lp.open_path = :lporgexact OR lp.open_path LIKE :lporgprefix)';
                $sqlparams['lporgexact']  = '/' . $top;
                $sqlparams['lporgprefix'] = $DB->sql_like_escape('/' . $top . '/') . '%';
            }
        }

        $status_filter = (string) ($f['status'] ?? 'all');
        if ($status_filter !== 'all' && ctype_digit($status_filter)) {
            $where[] = 'lp.status = :sfilter';
            $sqlparams['sfilter'] = (int) $status_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = $DB->sql_like('lp.name', ':s1', false);
            $sqlparams['s1'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_learningpath} lp WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT lp.*,
                        (SELECT COUNT(*) FROM {local_airpay_learningpath_courses} c WHERE c.pathid = lp.id) AS course_count,
                        (SELECT COUNT(*) FROM {local_airpay_learningpath_users} u WHERE u.pathid = lp.id) AS user_count
                   FROM {local_airpay_learningpath} lp
                  WHERE $wheresql
               ORDER BY lp.$sort $sortdir, lp.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $statusmap = [0 => 'Cancelled', 1 => 'Active', 2 => 'Completed'];
        $cssmap = [0 => 'badge-secondary', 1 => 'badge-success', 2 => 'badge-info'];

        $rows = [];
        foreach ($records as $lp) {
            $rows[] = [
                'id'          => (int) $lp->id,
                'name'        => format_string($lp->name),
                'courses'     => (int) ($lp->course_count ?? 0),
                'enrolled'    => (int) ($lp->user_count ?? 0),
                'created'     => $lp->timecreated ? userdate($lp->timecreated, '%d %b %Y') : '—',
                'statuslabel' => $statusmap[(int) $lp->status] ?? 'Unknown',
                'statuscss'   => $cssmap[(int) $lp->status] ?? 'badge-secondary',
                'actions'     => '<a href="' . (new \moodle_url('/local/airpay_learningpath/view.php', ['id' => $lp->id]))->out(false) . '" class="btn btn-sm btn-link p-1" title="View path detail"><i class="fa fa-eye"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="edit-path" data-pathid="' . (int)$lp->id . '" data-name="' . s($lp->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-path" data-pathid="' . (int)$lp->id . '" data-name="' . s($lp->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>',
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
                    'name'        => new external_value(PARAM_TEXT, ''),
                    'courses'     => new external_value(PARAM_INT, ''),
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
