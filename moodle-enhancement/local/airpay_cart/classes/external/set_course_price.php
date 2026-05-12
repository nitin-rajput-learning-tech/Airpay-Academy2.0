<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_cart\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Set a course's purchase price by adding/updating an enrol_fee instance.
 *
 * We reuse Moodle's enrol_fee mechanism rather than a new pricing table:
 * - Existing data flow (admin → course → enrolment methods → Payment)
 * - Free integration with Moodle Payment subsystem
 * - One source of truth: price + currency on enrol instance
 */
class set_course_price extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, ''),
            'price'    => new external_value(PARAM_FLOAT, '0 to remove price (make free)'),
            'currency' => new external_value(PARAM_ALPHA, '', VALUE_DEFAULT, 'INR'),
        ]);
    }

    public static function execute(int $courseid, float $price, string $currency = 'INR'): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('courseid', 'price', 'currency'));

        $course = $DB->get_record('course', ['id' => $params['courseid']],
            '*', MUST_EXIST);

        // ── B9 fix: capability must be checked at COURSE context ────────
        // Was previously checked at CONTEXT_SYSTEM, which meant any user
        // holding :manageprices anywhere could re-price any course in
        // any tenant. Checking at the course's own context ties the
        // cap-grant to the course's category hierarchy → tenant.
        $coursectx = \context_course::instance($course->id);
        self::validate_context($coursectx);
        require_capability('local/airpay_cart:manageprices', $coursectx);

        $existing = $DB->get_record('enrol',
            ['courseid' => $course->id, 'enrol' => 'fee', 'status' => 0]);

        if ($params['price'] <= 0) {
            // Remove pricing — disable the fee instance.
            if ($existing) {
                $existing->status = 1;  // disabled
                $existing->timemodified = time();
                $DB->update_record('enrol', $existing);
            }
            return ['success' => true, 'price' => 0.0, 'currency' => $params['currency']];
        }

        $enrolplugin = enrol_get_plugin('fee');
        if (!$enrolplugin) {
            throw new \moodle_exception('error_invalidstate', 'local_airpay_cart',
                '', 'enrol_fee plugin not installed');
        }

        if ($existing) {
            $existing->status   = 0;
            $existing->cost     = $params['price'];
            $existing->currency = $params['currency'];
            $existing->timemodified = time();
            $DB->update_record('enrol', $existing);
        } else {
            $enrolplugin->add_instance($course, [
                'name'     => 'Cart purchase',
                'status'   => 0,
                'cost'     => $params['price'],
                'currency' => $params['currency'],
            ]);
        }

        return ['success' => true, 'price' => (float) $params['price'],
                'currency' => $params['currency']];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'  => new external_value(PARAM_BOOL, ''),
            'price'    => new external_value(PARAM_FLOAT, ''),
            'currency' => new external_value(PARAM_ALPHA, ''),
        ]);
    }
}
