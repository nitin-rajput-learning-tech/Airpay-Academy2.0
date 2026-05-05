<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_users\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_multiple_structure;

/**
 * Bulk action on a set of user IDs.
 *
 * Supported actions: suspend, activate.
 * Wraps each operation in a transaction so a single bad ID rolls the
 * whole batch — caller can retry with the failing ID excluded.
 */
class bulk_action extends external_api {

    private const ACTIONS = ['suspend', 'activate'];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'action'  => new external_value(PARAM_ALPHA, 'suspend|activate'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'Array of user IDs to act on'
            ),
        ]);
    }

    public static function execute(string $action, array $userids): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(),
            ['action' => $action, 'userids' => $userids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_users:edit', $context);

        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \moodle_exception('invalidaction', 'local_airpay_users');
        }

        if (empty($params['userids'])) {
            return ['action' => $params['action'], 'count' => 0, 'skipped' => 0];
        }

        // Hard guard — never let bulk action touch the current user, guest, or admin id=2.
        $protected = [(int) $USER->id, 1, 2];
        $clean_ids = array_values(array_diff(array_map('intval', $params['userids']), $protected));

        if (empty($clean_ids)) {
            return [
                'action' => $params['action'],
                'count'  => 0,
                'skipped' => count($params['userids']),
            ];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($clean_ids, SQL_PARAMS_NAMED, 'uid');

        $transaction = $DB->start_delegated_transaction();
        try {
            $suspended = ($params['action'] === 'suspend') ? 1 : 0;
            // Only update rows that actually need to change.
            $changed = $DB->execute(
                "UPDATE {user}
                    SET suspended = :s, timemodified = :tm
                  WHERE id $insql
                    AND deleted = 0
                    AND suspended != :s2",
                array_merge($inparams, [
                    's'  => $suspended,
                    's2' => $suspended,
                    'tm' => time(),
                ]));
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // Recount actually changed rows.
        $now_state = $DB->count_records_select('user',
            "id $insql AND suspended = :s",
            array_merge($inparams, ['s' => $suspended]));

        return [
            'action'  => $params['action'],
            'count'   => (int) $now_state,
            'skipped' => count($params['userids']) - count($clean_ids),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'action'  => new external_value(PARAM_ALPHA, 'Action performed'),
            'count'   => new external_value(PARAM_INT, 'Users now in the target state'),
            'skipped' => new external_value(PARAM_INT, 'Users skipped (self/guest/admin protected)'),
        ]);
    }
}
