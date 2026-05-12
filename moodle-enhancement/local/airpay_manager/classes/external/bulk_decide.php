<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_manager\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Bulk approve / reject multiple requests at once.
 *
 * Per-request tenant + manager checks are enforced (a malicious payload
 * with random requestids can't decide on requests outside the manager's
 * scope).
 *
 * Phase 4 B.10 (2026-05-11).
 */
class bulk_decide extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'requestids'       => new external_multiple_structure(
                new external_value(PARAM_INT, ''), 'Request IDs to decide'),
            'decision'         => new external_value(PARAM_ALPHAEXT, 'approved|rejected'),
            'decision_reason'  => new external_value(PARAM_TEXT, '', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(array $requestids, string $decision,
                                    string $decision_reason = ''): array {
        global $USER, $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('requestids', 'decision', 'decision_reason'));

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_manager:approve', $context);
        require_sesskey();

        if (!in_array($params['decision'], ['approved', 'rejected'], true)) {
            throw new \moodle_exception('invalidparameter');
        }

        $approved = 0;
        $rejected = 0;
        $skipped = 0;
        $errors = [];

        foreach ($params['requestids'] as $reqid) {
            // Per-request tenant gate.
            $row = $DB->get_record('local_airpay_mgr_requests',
                ['id' => (int) $reqid]);
            if (!$row) {
                $skipped++; $errors[] = "$reqid: not found";
                continue;
            }
            if (!is_siteadmin() && (int) $row->managerid !== (int) $USER->id) {
                $skipped++;
                $errors[] = "$reqid: not your assigned request";
                continue;
            }
            if ($row->status !== 'pending') {
                $skipped++;
                $errors[] = "$reqid: status={$row->status}";
                continue;
            }

            try {
                \local_airpay_manager\approval_manager::decide_request(
                    (int) $reqid, (string) $params['decision'],
                    (string) $params['decision_reason'], (int) $USER->id);
                if ($params['decision'] === 'approved') $approved++;
                else $rejected++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "$reqid: " . $e->getMessage();
            }
        }

        return [
            'approved' => $approved,
            'rejected' => $rejected,
            'skipped'  => $skipped,
            'errors'   => array_slice($errors, 0, 50),  // cap for response size
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'approved' => new external_value(PARAM_INT, ''),
            'rejected' => new external_value(PARAM_INT, ''),
            'skipped'  => new external_value(PARAM_INT, ''),
            'errors'   => new external_multiple_structure(
                new external_value(PARAM_TEXT, '')),
        ]);
    }
}
