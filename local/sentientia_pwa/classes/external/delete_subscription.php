<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Delete the current user's push subscription — WS endpoint.
 *
 * @package local_sentientia_pwa
 */
class delete_subscription extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'endpoint' => new external_value(PARAM_URL, 'PushSubscription.endpoint URL to delete'),
        ]);
    }

    public static function execute(string $endpoint): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), ['endpoint' => $endpoint]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_pwa:subscribe', $context);

        $deleted = \local_sentientia_pwa\subscription_manager::delete(
            (int) $USER->id, $endpoint);

        return [
            'deleted' => $deleted,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'deleted' => new external_value(PARAM_BOOL, 'True if a matching subscription was found and deleted'),
        ]);
    }
}
