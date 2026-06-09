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
 * Toggle report active/archived status.
 */
class toggle_status extends external_api {

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

        // Look up first for a clean "invalidreport" error if the id is bad.
        $existing = \local_sentientia_reports\report_manager::get($params['reportid']);
        if (!$existing) {
            throw new \moodle_exception('invalidreport', 'local_sentientia_reports');
        }

        // Empty open_path = "all-organisations" report → site-admin-only.
        // Reject explicitly before the shared helper (which would tolerate
        // empty as legacy unscoped).
        if (empty($existing->open_path) && !is_siteadmin()) {
            throw new \moodle_exception('outoftenant', 'local_sentientia_reports');
        }

        // Tenant guard for scoped reports.
        \local_sentientia_platform\tenant::require_path_access((string) $existing->open_path);

        $newstate = \local_sentientia_reports\report_manager::toggle_status($params['reportid']);
        return [
            'reportid'  => $params['reportid'],
            'is_active' => $newstate,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reportid'  => new external_value(PARAM_INT, 'Report ID'),
            'is_active' => new external_value(PARAM_BOOL, 'New active state'),
        ]);
    }
}
