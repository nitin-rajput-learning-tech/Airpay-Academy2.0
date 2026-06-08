<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Web service: bulk-assign courses to a learning path.
 *
 * Idempotent — courses already on the path are silently skipped. Newly added
 * courses go to the end of the existing sort order.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'    => new external_value(PARAM_INT, 'Learning path ID'),
            'courseids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'Course IDs to assign'
            ),
        ]);
    }

    public static function execute(int $pathid, array $courseids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['pathid' => $pathid, 'courseids' => $courseids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_learningpath:update', $context);

        // Bound the input — refuse > 100 courses in a single call to prevent
        // memory blow-up + UI footguns. The UI's "Add Courses" modal won't
        // ever submit this many, but bound it server-side anyway.
        if (count($params['courseids']) > 100) {
            throw new \moodle_exception('toomanycourses', 'local_sentientia_learningpath');
        }

        $count = \local_sentientia_learningpath\path_manager::assign_courses(
            $params['pathid'], $params['courseids']);

        return ['pathid' => $params['pathid'], 'inserted' => $count];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'   => new external_value(PARAM_INT,  'Learning path ID'),
            'inserted' => new external_value(PARAM_INT,  'Count of courses actually assigned (excludes skips)'),
        ]);
    }
}
