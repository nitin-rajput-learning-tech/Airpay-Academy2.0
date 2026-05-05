<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_reports\external;

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
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['reportid' => $reportid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_reports:manage', $context);

        // H3 fix: tenant scope check on the target report. A non-siteadmin
        // manager can only delete reports inside their own top-level tree.
        if (!is_siteadmin()) {
            $existing = \local_airpay_reports\report_manager::get($params['reportid']);
            if (!$existing) {
                throw new \moodle_exception('invalidreport', 'local_airpay_reports');
            }
            $caller_parts = explode('/', trim($USER->open_path ?? '', '/'));
            $caller_top = isset($caller_parts[0]) && ctype_digit($caller_parts[0])
                ? '/' . (int) $caller_parts[0] : '';
            // Reports without an open_path (= "all organisations") are
            // siteadmin-only; managers cannot delete them.
            if (empty($existing->open_path)) {
                throw new \moodle_exception('outoftenant', 'local_airpay_reports');
            }
            $is_inside = ($existing->open_path === $caller_top)
                || (strpos((string) $existing->open_path, $caller_top . '/') === 0);
            if (empty($caller_top) || !$is_inside) {
                throw new \moodle_exception('outoftenant', 'local_airpay_reports');
            }
        }

        $success = \local_airpay_reports\report_manager::delete($params['reportid']);
        return ['reportid' => $params['reportid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reportid' => new external_value(PARAM_INT, 'Report ID'),
            'success'  => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
