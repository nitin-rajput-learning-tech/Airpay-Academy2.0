<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: remove a single course from a learning path.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unassign_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'   => new external_value(PARAM_INT, 'Learning path ID'),
            'courseid' => new external_value(PARAM_INT, 'Course ID to remove'),
        ]);
    }

    public static function execute(int $pathid, int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['pathid' => $pathid, 'courseid' => $courseid]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_learningpath:update', $context);

        $removed = \local_sentientia_learningpath\path_manager::unassign_course(
            $params['pathid'], $params['courseid']);

        return ['pathid' => $params['pathid'], 'courseid' => $params['courseid'], 'removed' => $removed];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'   => new external_value(PARAM_INT,  'Learning path ID'),
            'courseid' => new external_value(PARAM_INT,  'Course ID'),
            'removed'  => new external_value(PARAM_BOOL, 'True if removed; false if it was not on the path'),
        ]);
    }
}
