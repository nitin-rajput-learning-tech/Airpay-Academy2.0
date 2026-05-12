<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace quizaccess_airpay_proctoring;

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
        $mform->addElement('selectyesno', 'airpay_proctoring_enabled',
            get_string('enable', 'quizaccess_airpay_proctoring'));
        $mform->addHelpButton('airpay_proctoring_enabled', 'enable', 'quizaccess_airpay_proctoring');
        $mform->setDefault('airpay_proctoring_enabled', 0);
    }

    /**
     * Persist the form field. Stored as quiz config via the access rule pattern.
     */
    public static function save_settings($quiz): void {
        global $DB;
        $enabled = (int) ($quiz->airpay_proctoring_enabled ?? 0);
        // Storage approach: a row in quiz_overrides-like table, OR a quiz
        // custom field. For simplicity here, we set a config flag on the
        // quiz instance via a separate table — but to avoid creating one,
        // we just keep it in plugin config keyed by quizid.
        set_config('quiz_' . $quiz->id . '_enabled', $enabled, 'quizaccess_airpay_proctoring');
    }

    public static function delete_settings($quiz): void {
        unset_config('quiz_' . $quiz->id . '_enabled', 'quizaccess_airpay_proctoring');
    }

    /**
     * Create the rule instance for this quiz attempt (or return null
     * if proctoring is disabled for this quiz).
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits): ?access_rule_base {
        $quiz = $quizobj->get_quiz();
        $enabled = (int) get_config('quizaccess_airpay_proctoring',
            'quiz_' . $quiz->id . '_enabled');
        if (!$enabled) return null;
        return new self($quizobj, $timenow);
    }

    /**
     * Show a description on the quiz info page.
     */
    public function description(): string {
        return get_string('enable_help', 'quizaccess_airpay_proctoring');
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
            "SELECT * FROM {local_airpay_proctor_sessions}
              WHERE userid = :u AND quizid = :q
                AND status IN ('recording', 'verifying')
              ORDER BY id DESC LIMIT 1",
            ['u' => $USER->id, 'q' => $this->quiz->id]);

        if ($session && $session->status === 'recording') {
            return false;  // proctoring active, allow attempt
        }

        // Otherwise require completing the flow.
        return get_string('consent_required', 'quizaccess_airpay_proctoring');
    }

    /**
     * Inject the consent.js loader on the quiz info page so the user
     * can complete consent + identity before they click "Attempt quiz".
     */
    public function setup_attempt_page($page) {
        $page->requires->js_call_amd('local_airpay_proctoring/consent', 'open',
            [$this->quiz->id]);
    }

    /**
     * Whether this quiz is configured as proctored. Public so the
     * Moodle quiz settings form can check it.
     */
    public static function is_quiz_proctored(int $quizid): bool {
        return (bool) (int) get_config('quizaccess_airpay_proctoring',
            'quiz_' . $quizid . '_enabled');
    }
}
