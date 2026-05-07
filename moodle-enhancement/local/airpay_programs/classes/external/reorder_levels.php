<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_programs\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Reorder levels in a program. Caller passes ordered IDs.
 *
 * Cap: 200 levels per call (defensive — programs typically have 3-6 levels).
 */
class reorder_levels extends external_api {

    private const MAX_LEVELS = 200;

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'programid' => new external_value(PARAM_INT, 'Program ID'),
            'levelids'  => new external_multiple_structure(
                new external_value(PARAM_INT, 'Level ID'),
                'Levels in desired order'
            ),
        ]);
    }

    public static function execute(int $programid, array $levelids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['programid' => $programid, 'levelids' => $levelids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_programs:update', $context);

        if (count($params['levelids']) > self::MAX_LEVELS) {
            throw new \moodle_exception('toomanylevels', 'local_airpay_programs');
        }

        $count = \local_airpay_programs\program_manager::reorder_levels(
            $params['programid'], $params['levelids']);

        return [
            'programid' => $params['programid'],
            'reordered' => $count,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'programid' => new external_value(PARAM_INT, ''),
            'reordered' => new external_value(PARAM_INT, ''),
        ]);
    }
}
