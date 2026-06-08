<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\external;

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
        require_capability('local/sentientia_users:edit', $context);

        if (!in_array($params['action'], self::ACTIONS, true)) {
            throw new \moodle_exception('invalidaction', 'local_sentientia_users');
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

        // C1 fix: tenant scope. A non-siteadmin can only act on users that
        // sit beneath their own top-level tenant in open_path. Without this,
        // an Airpay manager could bulk-suspend Public users by ID-guessing.
        //
        // For bulk operations we use tenant::path_filter() (SQL-level scoping)
        // rather than require_path_access() (single-resource). path_filter
        // returns '1=1' for site admins and the slash-bounded
        // '(open_path = :exact OR open_path LIKE :prefix)' clause for
        // tenant-bound users.
        //
        // We keep the explicit "invalid tenant" throw for non-admins with
        // no tenant root — path_filter would return '1=0' (silently skip
        // everything) but the bulk-action UX expects a hard error so the
        // caller knows their request was denied.
        if (!is_siteadmin()) {
            if (\local_sentientia_platform\tenant::root_for_current_user() <= 0) {
                throw new \moodle_exception('invalidtenant', 'local_sentientia_users');
            }
        }

        [$tnsql, $tnargs] = \local_sentientia_platform\tenant::path_filter('', 'open_path');
        [$inscope_sql, $inscope_params] = $DB->get_in_or_equal(
            $clean_ids, SQL_PARAMS_NAMED, 'cuid');
        $in_scope = $DB->get_fieldset_sql(
            "SELECT id FROM {user}
              WHERE id $inscope_sql AND deleted = 0 AND ($tnsql)",
            array_merge($inscope_params, $tnargs));
        $clean_ids = array_values(array_intersect(
            array_map('intval', $clean_ids),
            array_map('intval', $in_scope)));
        if (empty($clean_ids)) {
            return [
                'action'  => $params['action'],
                'count'   => 0,
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

        // M1 fix: do not echo back per-id success counts (would be a
        // user-enumeration oracle). Return only the size of the
        // request-set vs. how many were filtered out by tenant/protection
        // rules. The audit log is the source of truth for who actually
        // changed.
        $count_attempted = count($clean_ids);
        $skipped = count($params['userids']) - $count_attempted;

        return [
            'action'  => $params['action'],
            'count'   => $count_attempted,
            'skipped' => $skipped,
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
