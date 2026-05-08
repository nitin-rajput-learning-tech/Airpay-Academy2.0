<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_notifications\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_airpay_notifications\prefs_manager;

class save_prefs extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'channel_inapp'   => new external_value(PARAM_BOOL, 'Allow in-app messages'),
            'channel_email'   => new external_value(PARAM_BOOL, 'Allow email messages'),
            'channel_push'    => new external_value(PARAM_BOOL, 'Allow push messages'),
            'digest_frequency' => new external_value(PARAM_ALPHA, 'none|daily|weekly'),
            'disabled_rule_types' => new external_multiple_structure(
                new external_value(PARAM_ALPHANUMEXT, 'rule_type'),
                'Disabled rule types', VALUE_DEFAULT, []),
            'quiet_hours_start' => new external_value(PARAM_INT, 'Quiet start hour 0-23 (-1 = none)',
                VALUE_DEFAULT, -1),
            'quiet_hours_end'   => new external_value(PARAM_INT, 'Quiet end hour 0-23 (-1 = none)',
                VALUE_DEFAULT, -1),
        ]);
    }

    public static function execute(bool $channel_inapp, bool $channel_email,
                                    bool $channel_push, string $digest_frequency,
                                    array $disabled_rule_types = [],
                                    int $quiet_hours_start = -1,
                                    int $quiet_hours_end = -1): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('channel_inapp', 'channel_email', 'channel_push',
                'digest_frequency', 'disabled_rule_types',
                'quiet_hours_start', 'quiet_hours_end'));
        $context = \context_user::instance($USER->id);
        self::validate_context($context);
        require_sesskey();
        // Any logged-in user can edit their own prefs — no extra cap needed.

        $start = $params['quiet_hours_start'] >= 0 ? (int) $params['quiet_hours_start'] : null;
        $end   = $params['quiet_hours_end']   >= 0 ? (int) $params['quiet_hours_end']   : null;
        // If only one of the pair is set, treat as no quiet hours.
        if ($start === null xor $end === null) {
            $start = $end = null;
        }
        $id = prefs_manager::save_for_user(
            (int) $USER->id,
            (bool) $params['channel_inapp'],
            (bool) $params['channel_email'],
            (bool) $params['channel_push'],
            (string) $params['digest_frequency'],
            (array)  $params['disabled_rule_types'],
            $start, $end);
        return ['id' => $id];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Saved prefs row ID'),
        ]);
    }
}
