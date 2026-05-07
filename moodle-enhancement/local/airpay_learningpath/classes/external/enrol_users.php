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
 * Web service: bulk-enrol users in a learning path.
 *
 * Idempotent — users already enrolled are silently skipped. New enrolments
 * start at status=ENROL_NEW; cron promotes to INPROGRESS / COMPLETED based
 * on course-completion progress.
 *
 * Requires `:enrol` capability (separate from `:update` so we can grant
 * trainer-only enrolment without giving them edit-the-path permission).
 *
 * @package    local_airpay_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_users extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'pathid'  => new external_value(PARAM_INT, 'Learning path ID'),
            'userids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'User ID'),
                'User IDs to enrol'
            ),
        ]);
    }

    public static function execute(int $pathid, array $userids): array {
        $params = self::validate_parameters(self::execute_parameters(),
            ['pathid' => $pathid, 'userids' => $userids]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_learningpath:enrol', $context);

        // Bound the input. 500 users is the largest reasonable single bulk
        // enrolment we'd expect (e.g. a whole tenant). Above that the admin
        // should split into batches OR use the bulk-CSV upload (a separate
        // feature, not built yet).
        if (count($params['userids']) > 500) {
            throw new \moodle_exception('toomanyusers', 'local_airpay_learningpath');
        }

        $count = \local_airpay_learningpath\path_manager::enrol_users(
            $params['pathid'], $params['userids']);

        return ['pathid' => $params['pathid'], 'enrolled' => $count];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'pathid'   => new external_value(PARAM_INT, 'Learning path ID'),
            'enrolled' => new external_value(PARAM_INT, 'Count of users actually enrolled (excludes skips)'),
        ]);
    }
}
