<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_api\external\v1;

defined('MOODLE_INTERNAL') || die();

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_sentientia_platform\tenant;

/**
 * v1: GET /courses/{id} — one course, tenant-scoped.
 *
 * @package local_sentientia_api
 */
class get_course extends base {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * @param int $id
     * @return array
     */
    public static function execute(int $id): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), compact('id'));

        self::open_v1('local_sentientia_api_v1_get_course', 'local/sentientia_api:read');

        $course = $DB->get_record('course', ['id' => $params['id']],
            'id, fullname, shortname, summary, startdate, enddate, visible, open_path', MUST_EXIST);

        // Tenant scope: the course must live in the caller's tenant tree.
        tenant::require_path_access((string) ($course->open_path ?? ''));

        return [
            'id'        => (int) $course->id,
            'fullname'  => format_string($course->fullname),
            'shortname' => format_string($course->shortname),
            'summary'   => format_text($course->summary ?? '', FORMAT_HTML, ['context' => \context_course::instance($course->id)]),
            'startdate' => (int) $course->startdate,
            'enddate'   => (int) $course->enddate,
            'visible'   => (bool) $course->visible,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'        => new external_value(PARAM_INT,  'Course id'),
            'fullname'  => new external_value(PARAM_TEXT, 'Course full name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course short name'),
            'summary'   => new external_value(PARAM_RAW,  'Course summary HTML'),
            'startdate' => new external_value(PARAM_INT,  'Start date (epoch)'),
            'enddate'   => new external_value(PARAM_INT,  'End date (epoch, 0 if none)'),
            'visible'   => new external_value(PARAM_BOOL, 'Whether the course is visible'),
        ]);
    }
}
