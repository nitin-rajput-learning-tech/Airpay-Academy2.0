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
 * Delete an org node. Refuses on tenants, descendants present, or users assigned.
 */
class delete_org extends external_api {

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

        // Fetch BEFORE the tenant check so we can return a clean
        // "orgnotfound" rather than "outoftenant" when the id is bad.
        $existing = \local_sentientia_org\org_manager::get($params['orgid']);
        if (!$existing) {
            throw new \moodle_exception('orgnotfound', 'local_sentientia_org');
        }

        // Tenant guard. Site admins pass through; tenant-bound managers
        // can only act on orgs inside their own top-level tree (one of
        // the H3 findings from Phase 8.1). The bespoke inline check
        // this replaces had a subtle bug: a viewer with an EMPTY
        // open_path silently passed the cap check, because the inline
        // logic short-circuited on `empty($caller_top)` AFTER computing
        // it. The helper throws on empty viewer root, closing the bug.
        \local_sentientia_platform\tenant::require_path_access((string) $existing->path);

        $success = \local_sentientia_org\org_manager::delete($params['orgid']);
        return ['orgid' => $params['orgid'], 'success' => $success];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'orgid'   => new external_value(PARAM_INT, 'Org ID'),
            'success' => new external_value(PARAM_BOOL, 'Success'),
        ]);
    }
}
