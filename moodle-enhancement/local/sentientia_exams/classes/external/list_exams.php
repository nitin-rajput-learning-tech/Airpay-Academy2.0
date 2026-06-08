<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_exams\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_exams extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT,     'Search', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, 'Sort',   VALUE_DEFAULT, 'name'),
            'sortdir' => new external_value(PARAM_ALPHA,    'Dir',    VALUE_DEFAULT, 'asc'),
            'page'    => new external_value(PARAM_INT,      'Page',   VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT,      'Per',    VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW,      'JSON',   VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'name', string $sortdir = 'asc',
                                    int $page = 0, int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_exams:view', $context);

        $can_manage = has_capability('local/sentientia_exams:manage', $context);
        $can_enrol  = has_capability('local/sentientia_exams:enrol', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('filterstoolong', 'local_sentientia_exams');
        }
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'duration', 'passinggrade', 'status', 'timecreated'];
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
            $where[] = $DB->sql_like('e.name', ':s1', false);
            $sqlparams['s1'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_exams} e WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            // G-06: also fetch the parent course of the wrapping quiz —
            // needed to deep-link Enrol Users to /enrol/users.php?id=<courseid>.
            $records = $DB->get_records_sql(
                "SELECT e.*, q.attempts AS attempts_allowed, q.timelimit, q.course AS quiz_courseid
                   FROM {local_sentientia_exams} e
              LEFT JOIN {quiz} q ON q.id = e.quizid
                  WHERE $wheresql
               ORDER BY e.$sort $sortdir, e.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $e) {
            $statuslabel = $e->status == 1 ? 'Active' : 'Inactive';
            $statuscss = $e->status == 1 ? 'badge-success' : 'badge-secondary';

            $actions = [];
            // Phase 3 B.3 (2026-05-11) — standalone detail page.
            $viewurl = (new \moodle_url('/local/sentientia_exams/view.php',
                ['id' => (int) $e->id]))->out(false);
            $actions[] = '<a href="' . s($viewurl) . '" '
                . 'class="btn btn-sm btn-link p-1" '
                . 'title="View detail"><i class="fa fa-eye"></i></a>';

            // Phase 3 B.3 (2026-05-11) — switched from deep-link to airpay
            // core /enrol/users.php to airpay-native enrolledusers page,
            // giving full datatable + modal enrol/unenrol + completion %.
            if ($can_enrol && !empty($e->quiz_courseid)) {
                $enrolurl = (new \moodle_url('/local/sentientia_courses/enrolledusers.php',
                    ['id' => (int) $e->quiz_courseid]))->out(false);
                $actions[] = '<a href="' . s($enrolurl) . '" '
                    . 'class="btn btn-sm btn-link text-muted p-1" '
                    . 'title="Manage enrolment"><i class="fa fa-user-plus"></i></a>';
            }
            if ($can_manage) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-exam" data-examid="' . (int) $e->id . '" '
                    . 'data-name="' . s($e->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-exam" data-examid="' . (int) $e->id . '" '
                    . 'data-name="' . s($e->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'           => (int) $e->id,
                'name'         => s($e->name),
                'duration'     => $e->duration ? round($e->duration / 60) . ' min' : '—',
                'passinggrade' => $e->passinggrade !== null ? number_format((float) $e->passinggrade, 1) . '%' : '—',
                'attempts'     => (int) ($e->attempts_allowed ?? 0),
                'created'      => $e->timecreated ? userdate($e->timecreated, '%d %b %Y') : '—',
                'statuslabel'  => $statuslabel,
                'statuscss'    => $statuscss,
                'actions'      => implode(' ', $actions),
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total'),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'           => new external_value(PARAM_INT, 'ID'),
                    'name'         => new external_value(PARAM_TEXT, 'Name'),
                    'duration'     => new external_value(PARAM_TEXT, 'Duration'),
                    'passinggrade' => new external_value(PARAM_TEXT, 'Pass grade'),
                    'attempts'     => new external_value(PARAM_INT, 'Attempts allowed'),
                    'created'      => new external_value(PARAM_TEXT, 'Created'),
                    'statuslabel'  => new external_value(PARAM_TEXT, 'Status'),
                    'statuscss'    => new external_value(PARAM_TEXT, 'Status badge'),
                    'actions'      => new external_value(PARAM_RAW,  'HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
