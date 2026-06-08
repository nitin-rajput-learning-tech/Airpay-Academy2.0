<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_org\external;

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
        $params = self::validate_parameters(self::execute_parameters(), ['orgid' => $orgid]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_org:manage', $context);

        // Look up first so an invalid id gets "orgnotfound" not "outoftenant".
        $existing = \local_sentientia_org\org_manager::get($params['orgid']);
        if (!$existing) {
            throw new \moodle_exception('orgnotfound', 'local_sentientia_org');
        }

        // Tenant guard via the airpay_core helper. Site admins pass
        // through; tenant-bound managers can only flip visibility on
        // orgs inside their own top-level tree. Replaces the bespoke
        // inline pattern that had a silent-pass bug on empty open_path.
        \local_airpay_core\tenant::require_path_access((string) $existing->path);

        $newstate = \local_sentientia_org\org_manager::toggle_visibility($params['orgid']);
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
