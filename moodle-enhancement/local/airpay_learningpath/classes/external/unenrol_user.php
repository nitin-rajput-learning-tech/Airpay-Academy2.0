<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: unenrol a single user from a learning path.
 *
 * Does NOT touch the user's underlying course enrolments or progress —
 * only removes the path-level association. Their existing course completions
 * survive and will count if they're re-enrolled later.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unenrol_user extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid' => new external_value(PARAM_INT, 'Learning path ID'),
            'userid' => new external_value(PARAM_INT, 'User ID to unenrol'),
        ]);
    }

    public static function execute(int $pathid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['pathid' => $pathid, 'userid' => $userid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:enrol', $context);

        $removed = \local_airpay_learningpath\path_manager::unenrol_user(
            $params['pathid'], $params['userid']);

        return ['pathid' => $params['pathid'], 'userid' => $params['userid'], 'removed' => $removed];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'  => new external_value(PARAM_INT,  'Learning path ID'),
            'userid'  => new external_value(PARAM_INT,  'User ID'),
            'removed' => new external_value(PARAM_BOOL, 'True if unenrolled; false if user was not enrolled'),
        ]);
    }
}
