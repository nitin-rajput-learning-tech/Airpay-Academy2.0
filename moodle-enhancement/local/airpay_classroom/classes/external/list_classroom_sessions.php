<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_classroom\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List sessions for a classroom (powers the Sessions tab datatable).
 *
 * Honours the shared datatable contract: takes search/sort/sortdir/page/perpage
 * + an `extra_args` blob containing the classroomid (passed via the
 * `data-extra-args` attribute on the datatable root, see Phase 0B).
 *
 * @package    local_airpay_classroom
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_classroom_sessions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'classroomid' => new external_value(PARAM_INT,      'Classroom ID'),
            'search'      => new external_value(PARAM_TEXT,     'Search term', VALUE_DEFAULT, ''),
            'sort'        => new external_value(PARAM_ALPHAEXT, 'Sort column', VALUE_DEFAULT, 'sessiondate'),
            'sortdir'     => new external_value(PARAM_ALPHA,    'asc|desc',    VALUE_DEFAULT, 'asc'),
            'page'        => new external_value(PARAM_INT,      'Page',        VALUE_DEFAULT, 0),
            'perpage'     => new external_value(PARAM_INT,      'Per page',    VALUE_DEFAULT, 25),
            'filters'     => new external_value(PARAM_RAW,      'JSON filters', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(int $classroomid, string $search = '', string $sort = 'sessiondate',
                                    string $sortdir = 'asc', int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(),
            compact('classroomid', 'search', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_classroom:view', $context);

        $can_update = has_capability('local/airpay_classroom:update', $context)
            || has_capability('local/airpay_classroom:manage', $context);
        $can_attend = has_capability('local/airpay_classroom:attendance', $context);

        $DB->get_record('local_airpay_classroom', ['id' => $params['classroomid']],
            'id', MUST_EXIST);

        $allowed = ['title', 'sessiondate', 'starttime', 'endtime', 'location', 'timecreated'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'sessiondate';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['s.classroomid = :cid'];
        $sqlparams = ['cid' => $params['classroomid']];

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = '(' . $DB->sql_like('s.title', ':s1', false) . ' OR ' .
                $DB->sql_like('s.location', ':s2', false) . ' OR ' .
                $DB->sql_like('s.notes', ':s3', false) . ')';
            $sqlparams['s1'] = $sqlparams['s2'] = $sqlparams['s3'] = $term;
        }
        $wheresql = implode(' AND ', $where);

        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_classroom_sessions} s WHERE $wheresql",
            $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT s.* FROM {local_airpay_classroom_sessions} s
                  WHERE $wheresql
               ORDER BY s.{$sort} {$sortdir}, s.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $s) {
            // Build attendance link.
            $atturl = (new \moodle_url('/local/airpay_classroom/attendance.php',
                ['sessionid' => (int) $s->id]))->out(false);

            $title = $s->title ?? '';
            if (empty($title)) {
                $title = 'Session on ' . userdate((int) $s->sessiondate, '%d %b %Y');
            }
            $title_html = '<a href="' . s($atturl) . '" class="text-reset fw-semibold text-decoration-none">'
                . s($title) . '</a>';

            $actions = [];
            // W1-7 (2026-05-15) — virtual meeting + recording URLs.
            // Prepend join/replay icons so attendees can launch the session
            // straight from the list. Both fields are optional; only emit
            // an icon if the URL is set.
            if (!empty($s->meeting_url)) {
                $actions[] = '<a href="' . s($s->meeting_url) . '" target="_blank"'
                    . ' rel="noopener noreferrer" class="btn btn-sm btn-link text-primary p-1"'
                    . ' title="Join live session"><i class="fa fa-video-camera"></i></a>';
            }
            if (!empty($s->recording_url)) {
                $actions[] = '<a href="' . s($s->recording_url) . '" target="_blank"'
                    . ' rel="noopener noreferrer" class="btn btn-sm btn-link text-info p-1"'
                    . ' title="Watch recording"><i class="fa fa-play-circle-o"></i></a>';
            }
            if ($can_attend) {
                $actions[] = '<a href="' . s($atturl) . '" class="btn btn-sm btn-link p-1" '
                    . 'title="Mark attendance"><i class="fa fa-check-square-o"></i></a>';
            }
            // Phase H.1 (2026-05-08) — calendar invite (.ics download).
            $icsurl = (new \moodle_url('/local/airpay_classroom/ics.php',
                ['sessionid' => (int) $s->id]))->out(false);
            $actions[] = '<a href="' . s($icsurl) . '" class="btn btn-sm btn-link p-1" '
                . 'title="Add to calendar"><i class="fa fa-calendar-plus-o"></i></a>';
            if ($can_update) {
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="edit-session" data-sessionid="' . (int) $s->id . '" '
                    . 'title="Edit"><i class="fa fa-pencil"></i></a>';
                $actions[] = '<a href="#" class="btn btn-sm btn-link text-muted p-1" '
                    . 'data-action="delete-session" data-sessionid="' . (int) $s->id . '" '
                    . 'data-title="' . s($title) . '" '
                    . 'title="Delete"><i class="fa fa-trash text-danger"></i></a>';
            }

            $rows[] = [
                'id'             => (int) $s->id,
                'title'          => $title_html,
                'sessiondate'    => userdate((int) $s->sessiondate, '%a, %d %b %Y'),
                'time_range'     => userdate((int) $s->starttime, '%H:%M') . ' – ' . userdate((int) $s->endtime, '%H:%M'),
                'duration_min'   => max(0, (int) round(((int) $s->endtime - (int) $s->starttime) / 60)),
                'location'       => s($s->location ?? '—'),
                'actions'        => implode(' ', $actions),
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
            'total'   => new external_value(PARAM_INT, 'Total matches'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'id'             => new external_value(PARAM_INT, 'Session ID'),
                    'title'          => new external_value(PARAM_RAW, 'Title (HTML)'),
                    'sessiondate'    => new external_value(PARAM_TEXT, 'Date'),
                    'time_range'     => new external_value(PARAM_TEXT, 'Time range'),
                    'duration_min'   => new external_value(PARAM_INT, 'Duration (min)'),
                    'location'       => new external_value(PARAM_TEXT, 'Location'),
                    'actions'        => new external_value(PARAM_RAW, 'Per-row HTML'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
