<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use MoodleQuickForm;

/**
 * Airpay proctoring quiz access rule.
 *
 * Adds a "Require proctoring" toggle to the quiz edit form. When
 * enabled:
 *   - Pre-attempt: a "Start proctored attempt" button replaces the
 *     normal attempt button. It opens the consent + identity flow.
 *   - During attempt: proctor.js runs in-page, capturing events.
 *   - Post-attempt: server-side finalize runs AI analysis.
 *
 * Mirrors the pattern of quizaccess_seb (the bundled SEB integration).
 */
class rule extends access_rule_base {

    /**
     * Add the "Require proctoring" checkbox to the quiz settings form.
     */
    public static function add_settings_form_fields(\mod_quiz_mod_form $quizform, MoodleQuickForm $mform): void {
        $mform->addElement('selectyesno', 'sentientia_proctoring_enabled',
            get_string('enable', 'quizaccess_sentientia_proctoring'));
        $mform->addHelpButton('sentientia_proctoring_enabled', 'enable', 'quizaccess_sentientia_proctoring');
        $mform->setDefault('sentientia_proctoring_enabled', 0);
    }

    /**
     * Persist the form field.
     *
     * N7 fix (Phase 8.2 re-audit): the previous implementation stored
     * the per-quiz flag in `mdl_config_plugins` via `set_config()` keyed
     * by quizid. That works for small N but at 1000+ proctored quizzes
     * the config-plugins table becomes a smell — config rows aren't
     * indexed by name, the lookup is a sequential scan, and any future
     * `purge_config` administrative action would wipe the proctoring
     * configuration. Moved to a proper relational table
     * `mdl_quizaccess_sentientia_proctor` keyed by quizid with an extension
     * column for per-quiz overrides of `min_match_score` and
     * `retention_days_override`.
     */
    public static function save_settings($quiz): void {
        global $DB;
        $enabled = (int) ($quiz->sentientia_proctoring_enabled ?? 0);
        $now = time();
        $existing = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => $quiz->id]);
        if ($existing) {
            $existing->enabled      = $enabled;
            $existing->timemodified = $now;
            $DB->update_record('quizaccess_sentientia_proctor', $existing);
        } else {
            $DB->insert_record('quizaccess_sentientia_proctor', (object) [
                'quizid'       => (int) $quiz->id,
                'enabled'      => $enabled,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        }
    }

    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_sentientia_proctor', ['quizid' => (int) $quiz->id]);
    }

    /**
     * Create the rule instance for this quiz attempt (or return null
     * if proctoring is disabled for this quiz).
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits): ?access_rule_base {
        global $DB;
        $quiz = $quizobj->get_quiz();
        $row = $DB->get_record('quizaccess_sentientia_proctor',
            ['quizid' => $quiz->id], 'enabled');
        if (!$row || empty($row->enabled)) return null;
        return new self($quizobj, $timenow);
    }

    /**
     * Show a description on the quiz info page.
     */
    public function description(): string {
        return get_string('enable_help', 'quizaccess_sentientia_proctoring');
    }

    /**
     * Called when the candidate clicks "Re-attempt quiz" — we hijack
     * to require the consent + identity flow first.
     *
     * Returns false if attempt allowed, error message otherwise.
     */
    public function prevent_new_attempt($numprevattempts, $lastattempt) {
        global $USER, $DB;

        // Has the user completed a proctoring session for this quiz that's
        // currently in 'recording' state? If yes, let them in.
        $session = $DB->get_record_sql(
            "SELECT * FROM {local_sentientia_proctor_sessions}
              WHERE userid = :u AND quizid = :q
                AND status IN ('recording', 'verifying')
              ORDER BY id DESC LIMIT 1",
            ['u' => $USER->id, 'q' => $this->quiz->id]);

        if ($session && $session->status === 'recording') {
            return false;  // proctoring active, allow attempt
        }

        // Otherwise require completing the flow.
        return get_string('consent_required', 'quizaccess_sentientia_proctoring');
    }

    /**
     * Inject the consent.js loader on the quiz info page so the user
     * can complete consent + identity before they click "Attempt quiz".
     */
    public function setup_attempt_page($page) {
        $page->requires->js_call_amd('local_sentientia_proctoring/consent', 'open',
            [$this->quiz->id]);
    }

    /**
     * Whether this quiz is configured as proctored. Public so the
     * Moodle quiz settings form can check it.
     */
    public static function is_quiz_proctored(int $quizid): bool {
        global $DB;
        $row = $DB->get_record('quizaccess_sentientia_proctor',
            ['quizid' => $quizid], 'enabled');
        return $row && !empty($row->enabled);
    }
}
