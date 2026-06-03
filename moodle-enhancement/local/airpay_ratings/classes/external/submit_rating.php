<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_ratings\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * W1-3 (2026-05-15) — submit a star rating for an item.
 *
 * Wraps `\local_airpay_ratings\rating_manager::submit_rating()` with:
 *   - parameter validation
 *   - context check (system)
 *   - capability check (`local/airpay_ratings:rate`)
 *   - ratearea whitelist (prevents users from rating arbitrary table rows)
 *
 * Returns the new average + count so the client can refresh the display
 * without a follow-up roundtrip.
 *
 * @package    local_airpay_ratings
 */
class submit_rating extends external_api {

    /**
     * Whitelist of accepted `ratearea` strings.
     *
     * Pinned per Airpay plugin to prevent injection of arbitrary table names
     * (the column is just a string — there's nothing in the schema stopping
     * a malicious caller from setting it to `mdl_user` and gaming the ratings
     * for unrelated rows).
     */
    private const ALLOWED_RATEAREAS = [
        'local_airpay_courses',
        'local_airpay_classroom',
        'local_airpay_programs',
        'local_airpay_learningpath',
        'local_airpay_exams',
        'local_airpay_evaluation',
        // BizLMS-era values still in old `local_rating` rows during transition.
        'local_courses',
        'local_classroom',
        'local_program',
        'local_learningplan',
    ];

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid'   => new external_value(PARAM_INT,
                'ID of the item being rated (course ID, classroom ID, etc.)'),
            'ratearea' => new external_value(PARAM_ALPHANUMEXT,
                'Rating area — identifies which Airpay plugin the item belongs to'),
            'rating'   => new external_value(PARAM_INT,
                'Star rating, 1-5'),
        ]);
    }

    public static function execute(int $itemid, string $ratearea, int $rating): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'itemid' => $itemid,
            'ratearea' => $ratearea,
            'rating' => $rating,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_ratings:rate', $context);

        // Range check (PARAM_INT enforces int but not 1..5).
        if ($params['rating'] < 1 || $params['rating'] > 5) {
            throw new \moodle_exception('invalidrating', 'local_airpay_ratings');
        }

        // Whitelist the ratearea — the column is unconstrained at the schema
        // level so we must enforce the membership here.
        if (!in_array($params['ratearea'], self::ALLOWED_RATEAREAS, true)) {
            throw new \moodle_exception('invalidratearea', 'local_airpay_ratings');
        }

        \local_airpay_ratings\rating_manager::submit_rating(
            $params['itemid'],
            $params['ratearea'],
            (int) $USER->id,
            $params['rating']
        );

        // Refresh average for client display — saves a second roundtrip.
        $avg = \local_airpay_ratings\rating_manager::get_average(
            $params['itemid'], $params['ratearea']);

        return [
            'success'    => true,
            'average'    => (float) $avg->average,
            'count'      => (int)   $avg->count,
            'my_rating'  => $params['rating'],
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'   => new external_value(PARAM_BOOL,  'Whether the rating was saved'),
            'average'   => new external_value(PARAM_FLOAT, 'New average rating'),
            'count'     => new external_value(PARAM_INT,   'Total number of ratings'),
            'my_rating' => new external_value(PARAM_INT,   'The rating the current user just submitted'),
        ]);
    }
}
