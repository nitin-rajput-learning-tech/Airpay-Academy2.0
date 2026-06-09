<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_pwa\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * List current user's push subscriptions (safe — keys redacted).
 *
 * @package local_sentientia_pwa
 */
class list_my_subscriptions extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        global $USER;
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_pwa:subscribe', $context);

        return [
            'subscriptions' => \local_sentientia_pwa\subscription_manager::for_user_safe(
                (int) $USER->id),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'subscriptions' => new external_multiple_structure(
                new external_single_structure([
                    'id'            => new external_value(PARAM_INT,  'Subscription row ID'),
                    'user_agent'    => new external_value(PARAM_TEXT, 'Browser UA at subscribe time', VALUE_OPTIONAL),
                    'endpoint_host' => new external_value(PARAM_TEXT, 'Push service host (e.g. fcm.googleapis.com) — full endpoint redacted'),
                    'last_seen'     => new external_value(PARAM_INT,  'Last successful delivery timestamp (0 = never)'),
                    'fail_count'    => new external_value(PARAM_INT,  'Consecutive delivery failures'),
                    'timecreated'   => new external_value(PARAM_INT,  'Subscribe timestamp'),
                ])
            ),
        ]);
    }
}
