<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_org\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Toggle org visibility (active <-> hidden).
 */
class toggle_visibility extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orgid' => new external_value(PARAM_INT, 'Org ID'),
        ]);
    }

    public static function execute(int $orgid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['orgid' => $orgid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_org:manage', $context);

        // H3 fix: tenant scope check on the target org.
        if (!is_siteadmin()) {
            $existing = \local_airpay_org\org_manager::get($params['orgid']);
            if (!$existing) {
                throw new \moodle_exception('orgnotfound', 'local_airpay_org');
            }
            $caller_parts = explode('/', trim($USER->open_path ?? '', '/'));
            $caller_top = isset($caller_parts[0]) && ctype_digit($caller_parts[0])
                ? '/' . (int) $caller_parts[0] : '';
            $is_inside = ($existing->path === $caller_top)
                || (strpos((string) $existing->path, $caller_top . '/') === 0);
            if (empty($caller_top) || !$is_inside) {
                throw new \moodle_exception('outoftenant', 'local_airpay_org');
            }
        }

        $newstate = \local_airpay_org\org_manager::toggle_visibility($params['orgid']);
        return [
            'orgid'   => $params['orgid'],
            'visible' => $newstate,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'orgid'   => new external_value(PARAM_INT, 'Org ID'),
            'visible' => new external_value(PARAM_BOOL, 'New visibility state'),
        ]);
    }
}
