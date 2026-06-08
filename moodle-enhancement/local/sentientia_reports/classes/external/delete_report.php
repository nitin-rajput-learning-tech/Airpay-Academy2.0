<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_reports\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Delete a saved report definition.
 */
class delete_report extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reportid' => new external_value(PARAM_INT, 'Report ID'),
        ]);
    }

    public static function execute(int $reportid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['reportid' => $reportid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_reports:manage', $context);

        // Look up BEFORE the tenant guard so an invalid id surfaces as
        // "invalidreport" rather than "outoftenant".
        $existing = \local_sentientia_reports\report_manager::get($params['reportid']);
        if (!$existing) {
            throw new \moodle_exception('invalidreport', 'local_sentientia_reports');
        }

        // Reports-specific rule: a report with empty open_path is an
        // "all-organisations" report — those are site-admin-only.
        // The shared tenant helper would tolerate empty as a legacy
        // unscoped row, so we explicitly reject it here BEFORE the
        // helper short-circuits.
        if (empty($existing->open_path) && !is_siteadmin()) {
            throw new \moodle_exception('outoftenant', 'local_sentientia_reports');
        }

        // Standard tenant guard for scoped reports. Site admins pass
        // through; tenant-bound managers can only delete reports inside
        // their own top-level tree.
        \local_sentientia_platform\tenant::require_path_access((string) $existing->open_path);

        $success = \local_sentientia_reports\report_manager::delete($params['reportid']);
        return ['reportid' => $params['reportid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reportid' => new external_value(PARAM_INT, 'Report ID'),
            'success'  => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
