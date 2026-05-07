<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * Web service: reorder the courses in a learning path.
 *
 * Receives an ordered array of course IDs; each course's sortorder is set to
 * its index in the array. Course IDs not on the path are ignored. Courses on
 * the path but not in the array keep their existing sortorder.
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reorder_courses extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'             => new external_value(PARAM_INT, 'Learning path ID'),
            'ordered_course_ids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Course ID'),
                'Course IDs in their new desired order'
            ),
        ]);
    }

    public static function execute(int $pathid, array $ordered_course_ids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['pathid' => $pathid, 'ordered_course_ids' => $ordered_course_ids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:update', $context);

        if (count($params['ordered_course_ids']) > 200) {
            throw new \moodle_exception('toomanycourses', 'local_airpay_learningpath');
        }

        $updated = \local_airpay_learningpath\path_manager::reorder_courses(
            $params['pathid'], $params['ordered_course_ids']);

        return ['pathid' => $params['pathid'], 'updated' => $updated];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'  => new external_value(PARAM_INT, 'Learning path ID'),
            'updated' => new external_value(PARAM_INT, 'Count of courses whose sortorder actually changed'),
        ]);
    }
}
