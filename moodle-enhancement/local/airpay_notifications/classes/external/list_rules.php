<?php
namespace local_airpay_notifications\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class list_rules extends external_api {

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
        require_capability('local/airpay_notifications:manage', $context);

        if (strlen($params['filters']) > 4096) throw new \moodle_exception('filterstoolong', 'local_airpay_notifications');
        $f = json_decode($params['filters'], true, 5);
        if (!is_array($f) || json_last_error() !== JSON_ERROR_NONE) $f = [];

        $allowed = ['name', 'rule_type', 'channel', 'enabled', 'timemodified'];
        $sort = in_array($params['sort'], $allowed, true) ? $params['sort'] : 'name';
        $sortdir = strtolower($params['sortdir']) === 'desc' ? 'DESC' : 'ASC';

        $where = ['1=1'];
        $sqlparams = [];

        $enabled_filter = (string) ($f['enabled'] ?? 'all');
        if ($enabled_filter === '1' || $enabled_filter === '0') {
            $where[] = 'r.enabled = :ef';
            $sqlparams['ef'] = (int) $enabled_filter;
        }

        if (!empty($params['search'])) {
            $term = '%' . $DB->sql_like_escape($params['search']) . '%';
            $where[] = $DB->sql_like('r.name', ':s1', false);
            $sqlparams['s1'] = $term;
        }

        $wheresql = implode(' AND ', $where);
        $total = (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {local_airpay_notif_rules} r WHERE $wheresql", $sqlparams);

        $records = [];
        if ($total > 0) {
            $records = $DB->get_records_sql(
                "SELECT r.* FROM {local_airpay_notif_rules} r
                  WHERE $wheresql
               ORDER BY r.$sort $sortdir, r.id ASC",
                $sqlparams, $params['page'] * $params['perpage'], $params['perpage']);
        }

        $rows = [];
        foreach ($records as $r) {
            $rows[] = [
                'id'         => (int) $r->id,
                'name'       => format_string($r->name),
                'rule_type'  => format_string($r->rule_type),
                'channel'    => format_string($r->channel),
                'audience'   => format_string($r->audience ?? '—'),
                'trigger'    => (int) $r->trigger_days . ' days',
                'modified'   => $r->timemodified ? userdate($r->timemodified, '%d %b %Y') : '—',
                'statuslabel' => $r->enabled ? 'Enabled' : 'Disabled',
                'statuscss'  => $r->enabled ? 'badge-success' : 'badge-secondary',
                'actions'    => '<a href="#" class="btn btn-sm btn-link p-1" data-action="edit-rule" data-ruleid="' . (int)$r->id . '" data-name="' . s($r->name) . '" title="Edit"><i class="fa fa-pencil"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="toggle-rule" data-ruleid="' . (int)$r->id . '" data-name="' . s($r->name) . '" data-enabled="' . (int) $r->enabled . '" title="Toggle"><i class="fa fa-power-off"></i></a>'
                    . ' <a href="#" class="btn btn-sm btn-link p-1" data-action="delete-rule" data-ruleid="' . (int)$r->id . '" data-name="' . s($r->name) . '" title="Delete"><i class="fa fa-trash text-danger"></i></a>',
            ];
        }

        return ['total' => $total, 'rows' => $rows, 'page' => $params['page'], 'perpage' => $params['perpage']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, ''),
            'rows'    => new \core_external\external_multiple_structure(
                new external_single_structure([
                    'id'         => new external_value(PARAM_INT, ''),
                    'name'       => new external_value(PARAM_TEXT, ''),
                    'rule_type'  => new external_value(PARAM_TEXT, ''),
                    'channel'    => new external_value(PARAM_TEXT, ''),
                    'audience'   => new external_value(PARAM_TEXT, ''),
                    'trigger'    => new external_value(PARAM_TEXT, ''),
                    'modified'   => new external_value(PARAM_TEXT, ''),
                    'statuslabel' => new external_value(PARAM_TEXT, ''),
                    'statuscss'  => new external_value(PARAM_TEXT, ''),
                    'actions'    => new external_value(PARAM_RAW,  ''),
                ])
            ),
            'page'    => new external_value(PARAM_INT, ''),
            'perpage' => new external_value(PARAM_INT, ''),
        ]);
    }
}
