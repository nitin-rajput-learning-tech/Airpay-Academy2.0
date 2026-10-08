<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Web service: unenrol a single user from a learning path.
 *
 * Does NOT touch the user's underlying course enrolments or progress —
 * only removes the path-level association. Their existing course completions
 * survive and will count if they're re-enrolled later. For an enrolment the
 * BizLMS import converted, the result lists the course enrolments that stay
 * (`remaining`, and `notice` as ready-made HTML), so an admin who wants the
 * learner to lose access removes them knowingly (owner decision LRN-10).
 *
 * @package    local_sentientia_learningpath
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
        require_capability('local/sentientia_learningpath:enrol', $context);
        // ADR-031: the capability says WHAT; the path must also be in the caller's tenant.
        // The user being removed must be in it too - or already on this
        // (in-tenant) roster: removing a legacy out-of-tenant or pathless
        // learner from your own path reaches into no other tenant.
        \local_sentientia_learningpath\path_manager::require_path_tenant($params['pathid']);
        \local_sentientia_learningpath\path_manager::require_unenrol_target(
            $params['pathid'], $params['userid']);

        // LRN-10 (2026-10-07): the course enrolments the BizLMS import converted from this plan are NOT removed with
        // the path row (BizLMS removed them). The admin is told which stay, so they can remove them knowingly. Read
        // before the delete; empty for an enrolment the import did not make.
        $remaining = \local_sentientia_learningpath\path_manager::plan_course_enrolments(
            $params['pathid'], $params['userid']);

        $removed = \local_sentientia_learningpath\path_manager::unenrol_user(
            $params['pathid'], $params['userid']);

        return [
            'pathid' => $params['pathid'],
            'userid' => $params['userid'],
            'removed' => $removed,
            'remaining' => $removed ? $remaining : [],
            'notice' => $removed ? \local_sentientia_learningpath\path_manager::unenrol_notice($remaining) : '',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'  => new external_value(PARAM_INT,  'Learning path ID'),
            'userid'  => new external_value(PARAM_INT,  'User ID'),
            'removed' => new external_value(PARAM_BOOL, 'True if unenrolled; false if user was not enrolled'),
            'remaining' => new external_multiple_structure(
                new external_single_structure([
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'name'     => new external_value(PARAM_TEXT, 'Course name'),
                    'url'      => new external_value(PARAM_URL, 'The course participants page'),
                ]),
                'Course enrolments the BizLMS import made from this plan that the learner keeps; empty for a native enrolment'),
            'notice' => new external_value(PARAM_RAW,
                'HTML telling the admin which course enrolments stay (empty when none)'),
        ]);
    }
}
