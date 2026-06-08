<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

require_once($CFG->libdir . '/externallib.php');

/**
 * WS: get the top-N entries for a board, opt-outs filtered, tenant-scoped.
 *
 * @package local_sentientia_leaderboard
 */
class get_board extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'boardid' => new external_value(PARAM_INT, 'Board ID', VALUE_REQUIRED),
            'topn'    => new external_value(PARAM_INT, 'Top N (1-200)',
                VALUE_DEFAULT, 10),
        ]);
    }

    public static function execute(int $boardid, int $topn = 10): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            ['boardid' => $boardid, 'topn' => $topn]);
        $boardid = (int) $params['boardid'];
        $topn    = max(1, min(200, (int) $params['topn']));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_leaderboard:view', $context);

        // Master flag gate. UI should never reach this with the flag off,
        // but a malicious client could call the WS directly.
        if (class_exists('\\local_sentientia_platform\\feature_flags')) {
            if (!\local_sentientia_platform\feature_flags::is_enabled(
                    'sentientia.leaderboards.enabled')) {
                throw new \moodle_exception('feature_disabled',
                    'local_sentientia_leaderboard');
            }
        }

        $board = \local_sentientia_leaderboard\board_manager::get($boardid);
        if (!$board) {
            throw new \moodle_exception('error_noboard',
                'local_sentientia_leaderboard');
        }

        $can_view_all = has_capability(
            'local/sentientia_leaderboard:viewall', $context);

        // Tenant gate — site admin + :viewall pass; everyone else must be
        // in the board's tenant OR the board must be customer-wide.
        if (!$can_view_all && (int) $board->tenantid > 0) {
            $viewer_root = \local_sentientia_platform\tenant::root_for_current_user();
            if ($viewer_root !== (int) $board->tenantid) {
                throw new \moodle_exception('error_outoftenant',
                    'local_sentientia_leaderboard');
            }
        }

        $result = \local_sentientia_leaderboard\ranking_engine::read_top(
            $boardid, $topn, $can_view_all);

        $my_rank = \local_sentientia_leaderboard\ranking_engine::read_my_rank(
            $boardid, (int) $USER->id);

        return [
            'boardid'         => $boardid,
            'name'            => (string) $board->name,
            'type'            => (string) $board->type,
            'last_recomputed' => (int) $board->last_recomputed,
            'rows'            => $result['rows'],
            'total'           => $result['total'],
            'optout_total'    => $result['optout_total'],
            'my_rank'         => $my_rank ? (int) $my_rank['rank'] : 0,
            'my_points'       => $my_rank ? (int) $my_rank['points'] : 0,
            'my_optout'       => \local_sentientia_leaderboard\optout_manager::is_opted_out((int) $USER->id) ? 1 : 0,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'boardid'         => new external_value(PARAM_INT, 'Board ID'),
            'name'            => new external_value(PARAM_TEXT, 'Board name'),
            'type'            => new external_value(PARAM_ALPHA, 'Board type'),
            'last_recomputed' => new external_value(PARAM_INT, 'Unix ts'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'rank'      => new external_value(PARAM_INT, 'Rank (1-based)'),
                    'userid'    => new external_value(PARAM_INT, 'User ID'),
                    'fullname'  => new external_value(PARAM_TEXT, 'Display name'),
                    'points'    => new external_value(PARAM_INT, 'Primary metric'),
                    'secondary' => new external_value(PARAM_INT, 'Secondary metric'),
                ])
            ),
            'total'        => new external_value(PARAM_INT, 'Total ranked rows visible'),
            'optout_total' => new external_value(PARAM_INT, 'Hidden by opt-out'),
            'my_rank'      => new external_value(PARAM_INT, 'Caller rank, 0 if none'),
            'my_points'    => new external_value(PARAM_INT, 'Caller points'),
            'my_optout'    => new external_value(PARAM_INT, 'Caller opted out (0|1)'),
        ]);
    }
}
