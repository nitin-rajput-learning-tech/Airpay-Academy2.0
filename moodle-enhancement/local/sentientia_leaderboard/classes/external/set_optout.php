<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_leaderboard\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

require_once($CFG->libdir . '/externallib.php');

/**
 * WS: set the caller's leaderboard opt-out preference.
 *
 * Privacy-mandated reversible toggle. No capability check beyond
 * loginrequired — a user can always change their own opt-out.
 *
 * @package local_sentientia_leaderboard
 */
class set_optout extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'optout' => new external_value(PARAM_BOOL,
                'true = hide me; false = show me', VALUE_REQUIRED),
        ]);
    }

    public static function execute(bool $optout): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            ['optout' => $optout]);

        // Context check — even though this is per-user, validate that the
        // caller is in a valid web service context.
        self::validate_context(\context_user::instance($USER->id));

        \local_sentientia_leaderboard\optout_manager::set_preference_value(
            (int) $USER->id, (bool) $params['optout']);

        return [
            'optout' => \local_sentientia_leaderboard\optout_manager::is_opted_out(
                (int) $USER->id) ? 1 : 0,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'optout' => new external_value(PARAM_INT,
                'Final opt-out state (0|1) after applying the change'),
        ]);
    }
}
