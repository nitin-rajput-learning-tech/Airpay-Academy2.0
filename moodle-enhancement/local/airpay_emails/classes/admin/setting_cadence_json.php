<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_emails\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom admin setting that validates a JSON array of positive
 * day-offsets (the ramping cadence used by `course_incomplete`).
 *
 * Without validation, the settings page accepts any text and silent
 * fallbacks in process_rules pick the baseline cadence — the admin
 * never finds out their config wasn't applied. With this class,
 * Moodle's standard admin error-display shows a clear message at
 * save time.
 *
 * Validation rules (matching the runtime defensive guards in
 * `process_rules::process_course_incomplete()`):
 *
 *   - must be valid JSON
 *   - must decode to a non-empty array
 *   - every element must be a positive integer
 *   - max 10 elements (anything more is spam regardless)
 *
 * Day-2 (2026-05-14).
 *
 * @package local_airpay_emails
 */
class setting_cadence_json extends \admin_setting_configtext {

    public const MAX_ENTRIES = 10;

    /**
     * @param string $data Raw user input from the settings form
     * @return string '' on success, error message on failure
     */
    public function validate($data) {
        // First let the parent run its PARAM_TEXT check (which is a
        // no-op for ordinary text but keeps the contract intact).
        $err = parent::validate($data);
        if ($err !== '' && $err !== true) {
            return $err;
        }

        // Empty is treated as "use the baked-in default" — accept it.
        if (trim($data) === '') {
            return '';
        }

        $decoded = json_decode($data, true);
        if (!is_array($decoded)) {
            return get_string('cadence_error_not_array',
                'local_airpay_emails');
        }
        if (empty($decoded)) {
            return get_string('cadence_error_empty',
                'local_airpay_emails');
        }
        if (count($decoded) > self::MAX_ENTRIES) {
            return get_string('cadence_error_too_long',
                'local_airpay_emails', self::MAX_ENTRIES);
        }
        foreach ($decoded as $v) {
            // Strict int check. JSON's "1" (string-quoted) shouldn't
            // pass — that's the admin typing the wrong format.
            if (!is_int($v) || $v <= 0) {
                return get_string('cadence_error_bad_value',
                    'local_airpay_emails', var_export($v, true));
            }
        }

        return '';
    }
}
