<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_challenge\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_challenge\challenge_engine;
use local_sentientia_challenge\leaderboard_manager;

class get_leaderboard extends external_api {

    public static function execute_parameters(): external_function_parameters {
        // Goal A audit Bug #10 (2026-05-22): align with the shared
        // theme_airpayux/datatable client contract. `search` is currently
        // accepted but not yet acted on for leaderboard (leaderboard rows
        // are bounded by perpage and search would skip the rank ordering
        // anyway — pending UX decision on what "search a leaderboard"
        // should mean).
        return new external_function_parameters([
            'search'      => new external_value(PARAM_TEXT, 'Reserved — see WS comment', VALUE_DEFAULT, ''),
            'challengeid' => new external_value(PARAM_INT, '0 = aggregate', VALUE_DEFAULT, 0),
            'tenantmode'  => new external_value(PARAM_ALPHA, 'mine|all', VALUE_DEFAULT, 'mine'),
            'sort'        => new external_value(PARAM_ALPHAEXT, 'Sort', VALUE_DEFAULT, 'points'),
            'sortdir'     => new external_value(PARAM_ALPHA, 'asc|desc', VALUE_DEFAULT, 'desc'),
            'page'        => new external_value(PARAM_INT, 'Page', VALUE_DEFAULT, 0),
            'perpage'     => new external_value(PARAM_INT, 'Per page', VALUE_DEFAULT, 25),
            'filters'     => new external_value(PARAM_RAW, 'Reserved', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute(string $search = '', int $challengeid = 0,
                                    string $tenantmode = 'mine',
                                    string $sort = 'points', string $sortdir = 'desc',
                                    int $page = 0, int $perpage = 25,
                                    string $filters = '{}'): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('search', 'challengeid', 'tenantmode', 'sort', 'sortdir', 'page', 'perpage', 'filters'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_challenge:view', $context);

        if (strlen($params['filters']) > 4096) {
            throw new \moodle_exception('err_filterstoolong', 'local_sentientia_challenge');
        }

        // Tenant scoping. 'all' requires :viewall.
        $tenant = 0;
        if ($params['tenantmode'] === 'mine'
                || !has_capability('local/sentientia_challenge:viewall', $context)) {
            $tenant = challenge_engine::tenant_from_path($USER->open_path ?? '');
        }

        $result = leaderboard_manager::get_top(
            (int) $params['challengeid'], $tenant,
            (int) $params['page'], (int) $params['perpage']);

        // Add a per-row "is me" flag for highlight.
        foreach ($result['rows'] as &$row) {
            $row['ismine'] = (int) $row['userid'] === (int) $USER->id;
        }
        unset($row);

        return $result;
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'total'   => new external_value(PARAM_INT, 'Total'),
            'rows'    => new external_multiple_structure(
                new external_single_structure([
                    'rank'              => new external_value(PARAM_INT,  'Rank'),
                    'userid'            => new external_value(PARAM_INT,  'User ID'),
                    'fullname'          => new external_value(PARAM_TEXT, 'Full name'),
                    'points'            => new external_value(PARAM_INT,  'Points'),
                    'attemptscompleted' => new external_value(PARAM_INT,  'Attempts completed'),
                    'ismine'            => new external_value(PARAM_BOOL, 'Caller'),
                ])
            ),
            'page'    => new external_value(PARAM_INT, 'Page'),
            'perpage' => new external_value(PARAM_INT, 'Per page'),
        ]);
    }
}
