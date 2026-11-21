<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_emails\admin;

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
 * @package local_sentientia_emails
 */
class setting_cadence_json extends \admin_setting_configtext {

    /** D-12 (2026-11-20): the rule editor checks a rule's own cadence with the same rules (rule_manager::cadence_error()). */
    public const MAX_ENTRIES = \local_sentientia_emails\rule_manager::CADENCE_MAX_ENTRIES;

    /**
     * @param string $data Raw user input from the settings form
     * @return true|string true on success (parent contract), error message on failure
     */
    public function validate($data) {
        // First let the parent run its PARAM_TEXT check (which is a
        // no-op for ordinary text but keeps the contract intact).
        $err = parent::validate($data);
        if ($err !== '' && $err !== true) {
            return $err;
        }

        // Empty is treated as "use the baked-in default" — accept it.
        // NOTE: the parent contract is `true` on success (not ''):
        // admin_setting_configtext::write_setting() only calls
        // config_write() when validate() === true, so returning '' made
        // every save — including the install/upgrade default — silently
        // vanish ("New setting: …default_cadence_days_json" on every
        // upgrade run, admin edits never persisted). Fixed 2026-09-22.
        // The checks themselves live in rule_manager::cadence_error() since D-12 (2026-11-20), so the rule
        // editor's per-rule cadence follows the same rules: valid JSON, a non-empty array of at most
        // MAX_ENTRIES positive integers (a string-quoted "1" is refused). Empty = use the default.
        $error = \local_sentientia_emails\rule_manager::cadence_error((string) $data);
        return $error === null ? true : $error;
    }
}
