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
 * Save (upsert) the current user's push subscription — WS endpoint.
 *
 * @package local_sentientia_pwa
 */
class save_subscription extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'endpoint' => new external_value(PARAM_URL, 'PushSubscription.endpoint URL'),
            'p256dh'   => new external_value(PARAM_ALPHANUMEXT, 'PushSubscription.getKey(p256dh) as base64url'),
            'auth'     => new external_value(PARAM_ALPHANUMEXT, 'PushSubscription.getKey(auth) as base64url'),
            'user_agent' => new external_value(PARAM_TEXT, 'Browser user-agent (informational)', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $endpoint, string $p256dh, string $auth,
                                    string $user_agent = ''): array {
        global $USER;
        self::validate_parameters(self::execute_parameters(), [
            'endpoint' => $endpoint, 'p256dh' => $p256dh,
            'auth' => $auth, 'user_agent' => $user_agent,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_pwa:subscribe', $context);

        $id = \local_sentientia_pwa\subscription_manager::save(
            (int) $USER->id,
            $endpoint,
            $p256dh,
            $auth,
            $user_agent
        );

        return [
            'id'      => $id,
            'success' => true,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'      => new external_value(PARAM_INT, 'Subscription row ID'),
            'success' => new external_value(PARAM_BOOL, 'Always true on success — exceptions thrown on failure'),
        ]);
    }
}
