<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

class list_mine extends external_api {

    public static function execute_parameters(): external_function_parameters {
        // Bug fix 2026-05-22 (Goal A audit Bug #6 final root-cause):
        // theme_sentientia/datatable.js POSTs {search, sort, sortdir, page, perpage,
        // filters} to every endpoint it consumes. Moodle's external_api validator
        // is strict-by-default and rejects unknown keys with
        // invalid_parameter_exception ("Unexpected keys (search) detected"),
        // which causes the spinner to hang on Loading... forever. Adding
        // `search` with VALUE_DEFAULT '' aligns our schema with the shared
        // client's contract — sibling endpoint list_all.php already has it.
        return new external_function_parameters([
            'search'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
            'sort'    => new external_value(PARAM_ALPHAEXT, '', VALUE_DEFAULT, 'timecreated'),
            'sortdir' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'desc'),
            'page'    => new external_value(PARAM_INT, '', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, '', VALUE_DEFAULT, 25),
            'filters' => new external_value(PARAM_RAW, '', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', string $sort = 'timecreated',
                                    string $sortdir = 'desc', int $page = 0,
                                    int $perpage = 25, string $filters = '{}'): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/sentientia_request:request', $ctx);

        $allowed = ['timecreated', 'status', 'timedecided'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'timecreated';
        $sortdir = strtolower($params['sortdir']) === 'asc' ? 'ASC' : 'DESC';

        $where = 'r.userid = :uid';
        $args  = ['uid' => (int) $USER->id];

        $client = json_decode($params['filters'] ?: '{}', true) ?: [];
        if (!empty($client['status'])) {
            $where .= ' AND r.status = :st';
            $args['st'] = $client['status'];
        }

        // Free-text search over course name + reason + decision note.
        // sql_like_escape() escapes the user's wildcards so '%' / '_' typed
        // by the learner search as literals, not LIKE operators.
        if (trim($params['search']) !== '') {
            $term = '%' . $DB->sql_like_escape(trim($params['search'])) . '%';
            $where .= ' AND ('
                . $DB->sql_like('c.fullname',     ':s1', false) . ' OR '
                . $DB->sql_like('r.reason',       ':s2', false) . ' OR '
                . $DB->sql_like('r.decision_note', ':s3', false)
                . ')';
            $args['s1'] = $term;
            $args['s2'] = $term;
            $args['s3'] = $term;
        }

        // The count query needs the same JOIN now because the WHERE may
        // reference c.fullname for search. Cheap on a per-user dataset
        // (typical learner has <50 requests).
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_sentientia_request} r
        LEFT JOIN {course} c ON c.id = r.courseid
             WHERE $where", $args);

        $rows = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT r.*, c.fullname AS course_name
                   FROM {local_sentientia_request} r
              LEFT JOIN {course} c ON c.id = r.courseid
                  WHERE $where
               ORDER BY r.$sort $sortdir, r.id DESC",
                $args,
                $params['page'] * $params['perpage'], $params['perpage']);
            foreach ($records as $r) {
                $rows[] = self::shape($r);
            }
        }
        return ['total' => $total, 'rows' => $rows,
                'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total' => new external_value(PARAM_INT, ''),
            'rows'  => new external_multiple_structure(
                self::row_structure()),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }

    public static function row_structure(): external_single_structure {
        // Bug fix 2026-05-22 (Goal A audit Bug #6 polish): index.php declares
        // columns status_badge (format=badge), actions (format=html) that the
        // old return shape didn't supply — they rendered as blank cells.
        // status_badge + status_badge_class drive the colored badge; actions
        // is the per-row Cancel button (rendered only for own pending rows).
        return new external_single_structure([
            'id'                 => new external_value(PARAM_INT, ''),
            'course_name'        => new external_value(PARAM_TEXT, ''),
            'courseid'           => new external_value(PARAM_INT, ''),
            'status'             => new external_value(PARAM_ALPHANUMEXT, ''),
            'status_badge'       => new external_value(PARAM_TEXT, ''),
            'status_badge_class' => new external_value(PARAM_ALPHANUMEXT, ''),
            'route'              => new external_value(PARAM_ALPHANUMEXT, ''),
            'reason'             => new external_value(PARAM_TEXT, ''),
            'decision_note'      => new external_value(PARAM_TEXT, ''),
            'placed_on'          => new external_value(PARAM_TEXT, ''),
            'decided_on'         => new external_value(PARAM_TEXT, ''),
            'due_on'             => new external_value(PARAM_TEXT, ''),
            'is_overdue'         => new external_value(PARAM_BOOL, ''),
            'actions'            => new external_value(PARAM_RAW, ''),
        ]);
    }

    /**
     * Map a raw DB row to the shape the My Requests datatable expects.
     *
     * Status-to-badge map (Bootstrap utility classes — these are what the
     * shared datatable renders inside `<span class="badge {{class}}">`):
     *   pending   → bg-warning text-dark    (amber, demands attention)
     *   approved  → bg-success              (green, settled positively)
     *   rejected  → bg-danger               (red, settled negatively)
     *   cancelled → bg-secondary            (grey, withdrawn by learner)
     *   expired   → bg-secondary            (grey, auto-aged-out)
     *   (other)   → bg-secondary
     *
     * Actions only render a Cancel button when the row is still pending —
     * once approved/rejected, the request is immutable to the learner.
     */
    public static function shape(\stdClass $r): array {
        $now = time();
        $status = (string) ($r->status ?? '');
        $isoverdue = $status === 'pending' && !empty($r->timedue) && $r->timedue < $now;

        // Status badge label + class.
        $badgemap = [
            'pending'   => ['label' => get_string('status_pending',   'local_sentientia_request'),
                            'class' => 'bg-warning text-dark'],
            'approved'  => ['label' => get_string('status_approved',  'local_sentientia_request'),
                            'class' => 'bg-success'],
            'rejected'  => ['label' => get_string('status_rejected',  'local_sentientia_request'),
                            'class' => 'bg-danger'],
            'cancelled' => ['label' => get_string('status_cancelled', 'local_sentientia_request'),
                            'class' => 'bg-secondary'],
            'expired'   => ['label' => get_string('status_expired',   'local_sentientia_request'),
                            'class' => 'bg-secondary'],
        ];
        $badge = $badgemap[$status]
            ?? ['label' => ucfirst($status ?: 'unknown'), 'class' => 'bg-secondary'];

        // Per-row actions. Only own-pending rows can be cancelled.
        $actions = '';
        if ($status === 'pending') {
            $cancellabel = s(get_string('cancel'));
            $actions = '<button type="button" class="btn btn-outline-danger btn-sm" '
                . 'data-action="cancel-request" data-requestid="' . (int) $r->id . '">'
                . '<i class="fa fa-times" aria-hidden="true"></i> '
                . $cancellabel
                . '</button>';
        }

        return [
            'id'                 => (int) $r->id,
            'course_name'        => format_string($r->course_name ?? '(deleted course)'),
            'courseid'           => (int) ($r->courseid ?? 0),
            'status'             => $status,
            'status_badge'       => $badge['label'],
            'status_badge_class' => $badge['class'],
            'route'              => (string) ($r->route ?? ''),
            'reason'             => (string) ($r->reason ?? ''),
            'decision_note'      => (string) ($r->decision_note ?? ''),
            'placed_on'          => !empty($r->timecreated) ? userdate($r->timecreated, '%d %b %Y %H:%M') : '',
            'decided_on'         => !empty($r->timedecided) ? userdate($r->timedecided, '%d %b %Y %H:%M') : '',
            'due_on'             => !empty($r->timedue)     ? userdate($r->timedue,     '%d %b %Y %H:%M') : '',
            'is_overdue'         => $isoverdue,
            'actions'            => $actions,
        ];
    }
}
