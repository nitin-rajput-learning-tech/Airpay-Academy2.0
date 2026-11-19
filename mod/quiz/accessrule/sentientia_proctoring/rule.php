<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace quizaccess_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_proctoring\quiz_integration;
use local_sentientia_proctoring\secure_mode;
use mod_quiz\form\preflight_check_form;
use mod_quiz\local\access_rule_base;
use mod_quiz\quiz_settings;
use MoodleQuickForm;

/**
 * Sentientia proctoring quiz access rule.
 *
 * B1 "Fix the foundation" (ADR-038 D2, post-demo plan B1, 2026-11-20). Before B1 this rule refused every attempt
 * (prevent_new_attempt) unless a session was already `recording`, and the consent flow that opened one was loaded
 * only on the attempt page the learner could never reach: a proctored quiz locked every learner out. Now the rule
 * uses the stock quiz pre-flight hooks, as quizaccess_password and quizaccess_seb do:
 *
 *  - make(): only for a quiz with "Require proctoring" = Yes, while sentientia.proctoring.enabled is ON for the
 *    learner's customer and tenant, and never for a teacher's preview. With the flag OFF the quiz is stock: no
 *    setting on the form, no pre-start check, nothing on the attempt pages.
 *  - is_preflight_check_required() / add_preflight_check_form_fields() / validate_preflight_check(): the notice, the
 *    consent and the identity confirmation are fields of the quiz's own pre-start check, shown when the learner
 *    presses Start (or continues an attempt that has no active session). The Start button is never hidden.
 *  - notify_preflight_check_passed(): stores consent and identity on the session (quiz_integration).
 *  - setup_attempt_page(): loads the monitor on EVERY page of the open attempt (attempt and summary pages), with the
 *    session the server found for the attempt.
 *  - The session is closed on the server when the attempt is submitted or abandoned (local_sentientia_proctoring
 *    observer), not by the browser.
 *
 * B2 "Secure assessment mode" (ADR-038 D1, D3 and D6, post-demo plan B2, session 7, 2026-11-20), behind
 * sentientia.assessment.secure_mode.enabled (default OFF):
 *
 *  - Settings: "Security level" (Off / Standard / Secure / Proctored), "Assessment purpose" (sets the lowest level,
 *    plan decision D8; only a holder of local/sentientia_proctoring:manage may go lower) and, for hiring, "Auto-submit
 *    after this many violations" (default 3, 0 = never; plan decision D11). They replace "Require proctoring" on the
 *    form while the flag is ON; Proctored keeps that setting in step. A quiz saved with "Require proctoring" = Yes
 *    and no level counts as Proctored (ADR-038 D1).
 *  - make() picks one of four modes for the current user:
 *      proctoring  the B1 rule, unchanged (secure mode OFF, quiz with "Require proctoring" = Yes, B1 flag ON);
 *      secure      the quiz has a level and secure mode is ON: the rules notice (and, at Proctored, the B1 consent
 *                  and identity boxes) in the pre-start check, and local_sentientia_proctoring/secure_mode on the
 *                  attempt and summary pages;
 *      blocked     fail closed (ADR-038 D1): a quiz saved as Secure or Proctored while secure mode is OFF for the
 *                  learner, or a Proctored quiz while proctoring is OFF, does not start; prevent_access() says why. It
 *                  never runs silently at a lower level. A Standard quiz with secure mode OFF is simply stock;
 *      reviewer    a teacher (preview user) with secure mode ON: no check and nothing on their previews, but the
 *                  integrity report on a learner's attempt review page.
 *  - Secure is, until the Safe Exam Browser preset (B3, session 9) lands, the same browser protection as Standard;
 *    the settings help says so and points to the Safe Exam Browser section for a lock-down today.
 *  - Proctored is B1 proctoring plus this protection; webcam recording arrives with B4.
 *
 * B2 review (2026-11-20):
 *  - An open attempt keeps the policy its learner accepted at the pre-start check (the level and limit stored on its
 *    session) until it ends, whatever the flags or the quiz settings say now. Switching a flag OFF mid-attempt used to
 *    block the attempt and summary pages, so an attempt in progress could neither continue nor be submitted. The fail
 *    closed block now applies to new attempts only; an open attempt with no accepted policy (one started before the
 *    quiz had a level) finishes as the stock quiz it started as.
 *  - Web-service calls (webservice/rest or soap: the mobile app, or a direct call with a mobile token) never load the
 *    attempt pages, so nothing would protect or monitor the attempt. A Secure or Proctored quiz (and a B1 proctored
 *    one) is therefore "desktop required" there, for new and open attempts alike (ADR-038, plan decision D29); a
 *    Standard quiz runs as the stock quiz in the app (ADR-040: non-proctored quizzes are allowed). The quiz pages' own
 *    calls go through lib/ajax/service.php, which is not a web-service server, and are unaffected.
 *
 * The rule's own table keeps per-quiz settings only (null privacy provider); everything about a learner is stored and
 * described by local_sentientia_proctoring.
 */
class rule extends access_rule_base {

    /** Form and settings field name, also the column alias of get_settings_sql(). */
    private const FIELD = 'sentientia_proctoring_enabled';

    /** B2: the level the quiz runs at (its own, or Proctored for a B1 proctored quiz), as the form shows it. */
    private const FIELD_LEVEL = 'sentientia_proctoring_level';

    /** B2: the level stored on the quiz ('' when none). */
    private const FIELD_SETLEVEL = 'sentientia_proctoring_setlevel';

    /** B2: the assessment purpose. */
    private const FIELD_PURPOSE = 'sentientia_proctoring_purpose';

    /** B2: the hiring auto-submit limit. */
    private const FIELD_LIMIT = 'sentientia_proctoring_violationlimit';

    /** B2 pre-start check: the rules acknowledgement (Standard and Secure). */
    private const FIELD_ACK = 'sentientia_secure_ack';

    /** The modes make() chooses between (B2). */
    private const MODE_PROCTORING = 'proctoring';
    /** The modes make() chooses between (B2). */
    private const MODE_SECURE = 'secure';
    /** The modes make() chooses between (B2). */
    private const MODE_BLOCKED = 'blocked';
    /** The modes make() chooses between (B2). */
    private const MODE_REVIEWER = 'reviewer';

    /** Strings component. */
    private const C = 'quizaccess_sentientia_proctoring';

    /** B2 review: levels a web-service client may not take (desktop required, ADR-038 and plan decision D29). */
    private const DESKTOP_ONLY_LEVELS = [secure_mode::LEVEL_SECURE, secure_mode::LEVEL_PROCTORED];

    /** @var bool|null tests only: treat the request as a web-service call (true) or not (false); null = detect */
    private static $wsrequestfortest = null;

    /** @var string one of the MODE_ constants */
    private $mode = self::MODE_PROCTORING;

    /** @var string secure mode: the level */
    private $level = '';

    /** @var int secure mode: violations at which the attempt is submitted, 0 = never */
    private $limit = 0;

    /** @var string blocked mode: the string that says why */
    private $blockedreason = '';

    /**
     * Add the quiz settings: B2's level, purpose and limit while secure mode is ON for the editor; else B1's "Require
     * proctoring" while proctoring is ON for the editor; else nothing.
     */
    public static function add_settings_form_fields(\mod_quiz_mod_form $quizform, MoodleQuickForm $mform): void {
        if (secure_mode::enabled()) {
            self::add_secure_settings($mform);
            return;
        }
        if (!quiz_integration::enabled()) {
            return;
        }
        $mform->addElement('selectyesno', self::FIELD,
            get_string('enable', self::C));
        $mform->addHelpButton(self::FIELD, 'enable', self::C);
        $mform->setDefault(self::FIELD, 0);
    }

    /**
     * B2 settings: Security level, Assessment purpose and, for hiring, the auto-submit limit.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    private static function add_secure_settings(MoodleQuickForm $mform): void {
        $levels = ['' => get_string('securitylevel_off', self::C)];
        foreach (array_keys(secure_mode::LEVELS) as $level) {
            $levels[$level] = get_string('securitylevel_' . $level, self::C);
        }
        $mform->addElement('select', self::FIELD_LEVEL, get_string('securitylevel', self::C), $levels);
        $mform->addHelpButton(self::FIELD_LEVEL, 'securitylevel', self::C);
        $mform->setDefault(self::FIELD_LEVEL, '');

        $purposes = ['' => get_string('purpose_none', self::C)];
        foreach (array_keys(secure_mode::PURPOSES) as $purpose) {
            $purposes[$purpose] = get_string('purpose_' . $purpose, self::C);
        }
        $mform->addElement('select', self::FIELD_PURPOSE, get_string('purpose', self::C), $purposes);
        $mform->addHelpButton(self::FIELD_PURPOSE, 'purpose', self::C);
        $mform->setDefault(self::FIELD_PURPOSE, '');

        $mform->addElement('text', self::FIELD_LIMIT, get_string('violationlimit', self::C), ['size' => 3]);
        $mform->setType(self::FIELD_LIMIT, PARAM_INT);
        $mform->setDefault(self::FIELD_LIMIT, secure_mode::DEFAULT_VIOLATION_LIMIT);
        $mform->addHelpButton(self::FIELD_LIMIT, 'violationlimit', self::C);
        $mform->hideIf(self::FIELD_LIMIT, self::FIELD_PURPOSE, 'neq', secure_mode::PURPOSE_HIRING);
    }

    /**
     * B2: the level may not be lower than the purpose needs (plan decision D8) unless the editor holds
     * local/sentientia_proctoring:manage; Proctored needs proctoring ON for the editor; the limit is 0 to 20.
     *
     * @param array $errors
     * @param array $data
     * @param array $files
     * @param \mod_quiz_mod_form $quizform
     * @return array
     */
    public static function validate_settings_form_fields(array $errors, array $data, $files,
            \mod_quiz_mod_form $quizform): array {
        if (!array_key_exists(self::FIELD_LEVEL, $data)) {
            return $errors;
        }
        $level = (string) ($data[self::FIELD_LEVEL] ?? '');
        $purpose = (string) ($data[self::FIELD_PURPOSE] ?? '');
        $min = secure_mode::min_level($purpose);
        if ($min !== '' && secure_mode::rank($level) < secure_mode::rank($min)
                && !has_capability('local/sentientia_proctoring:manage', \context_system::instance())) {
            $errors[self::FIELD_LEVEL] = get_string('err_level_below_purpose', self::C,
                get_string('securitylevel_' . $min, self::C));
        } else if ($level === secure_mode::LEVEL_PROCTORED && !quiz_integration::enabled()) {
            $errors[self::FIELD_LEVEL] = get_string('err_proctored_needs_flag', self::C);
        }
        if ($purpose === secure_mode::PURPOSE_HIRING) {
            $raw = trim((string) ($data[self::FIELD_LIMIT] ?? ''));
            if ($raw === '' || !preg_match('/^\d+$/', $raw) || (int) $raw > secure_mode::MAX_VIOLATION_LIMIT) {
                $errors[self::FIELD_LIMIT] = get_string('err_violationlimit', self::C, secure_mode::MAX_VIOLATION_LIMIT);
            }
        }
        return $errors;
    }

    /**
     * Persist the form fields.
     *
     * N7 fix (Phase 8.2 re-audit): one row per quiz in quizaccess_sentientia_proctor, not config_plugins.
     *
     * B1 (2026-11-20): when the field is not on the form (the flag is OFF for the editor, or the quiz is saved by code
     * that does not know the setting) the stored row is left as it is. Until B1 a missing field was saved as 0, which
     * would silently switch proctoring off on every save made while the setting is hidden.
     *
     * B2 (2026-11-20): with the level on the form, the level, purpose and limit are stored and "Require proctoring"
     * follows the level (Yes exactly when Proctored). With only "Require proctoring" on the form (secure mode OFF for
     * the editor), the level follows it: No clears a Proctored level, Yes raises a lower level to Proctored.
     */
    public static function save_settings($quiz): void {
        global $DB;
        $hassecure = property_exists($quiz, self::FIELD_LEVEL);
        if (!$hassecure && !property_exists($quiz, self::FIELD)) {
            return;
        }
        $now = time();
        $existing = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => $quiz->id]);
        $row = $existing ?: (object) ['quizid' => (int) $quiz->id, 'timecreated' => $now];

        if ($hassecure) {
            $level = (string) $quiz->{self::FIELD_LEVEL};
            $purpose = (string) ($quiz->{self::FIELD_PURPOSE} ?? '');
            $row->securitylevel = secure_mode::is_level($level) ? $level : null;
            $row->purpose = secure_mode::min_level($purpose) !== '' ? $purpose : null;
            if (property_exists($quiz, self::FIELD_LIMIT) && $quiz->{self::FIELD_LIMIT} !== '') {
                $row->violationlimit = max(0, min(secure_mode::MAX_VIOLATION_LIMIT, (int) $quiz->{self::FIELD_LIMIT}));
            }
            $row->enabled = (int) ($row->securitylevel === secure_mode::LEVEL_PROCTORED);
        } else {
            $row->enabled = (int) !empty($quiz->{self::FIELD});
            $stored = $row->securitylevel ?? null;
            if (!$row->enabled && $stored === secure_mode::LEVEL_PROCTORED) {
                $row->securitylevel = null;
            } else if ($row->enabled && secure_mode::is_level($stored) && $stored !== secure_mode::LEVEL_PROCTORED) {
                $row->securitylevel = secure_mode::LEVEL_PROCTORED;
            }
        }
        $row->timemodified = $now;
        if ($existing) {
            $DB->update_record('quizaccess_sentientia_proctor', $row);
        } else {
            $DB->insert_record('quizaccess_sentientia_proctor', $row);
        }
    }

    public static function delete_settings($quiz): void {
        global $DB;
        $DB->delete_records('quizaccess_sentientia_proctor', ['quizid' => (int) $quiz->id]);
    }

    /**
     * Load the settings with the quiz, in core's single settings query (B1, 2026-11-20; B2 adds the level, the
     * stored level, the purpose and the limit).
     *
     * Before B1 the quiz form never received the stored value, so it always showed "No" and saving it switched
     * proctoring off. With this the form shows the stored values and make() reads them without a query of its own.
     *
     * @param int $quizid
     * @return array [fields, joins, params]
     */
    public static function get_settings_sql($quizid): array {
        $level = "CASE WHEN sentientia_proctor.securitylevel IS NOT NULL AND sentientia_proctor.securitylevel <> ''"
            . " THEN sentientia_proctor.securitylevel"
            . " WHEN sentientia_proctor.enabled = 1 THEN '" . secure_mode::LEVEL_PROCTORED . "'"
            . " ELSE '' END";
        return [
            'COALESCE(sentientia_proctor.enabled, 0) AS ' . self::FIELD
                . ', ' . $level . ' AS ' . self::FIELD_LEVEL
                . ", COALESCE(sentientia_proctor.securitylevel, '') AS " . self::FIELD_SETLEVEL
                . ", COALESCE(sentientia_proctor.purpose, '') AS " . self::FIELD_PURPOSE
                . ', COALESCE(sentientia_proctor.violationlimit, ' . secure_mode::DEFAULT_VIOLATION_LIMIT . ') AS '
                . self::FIELD_LIMIT,
            'LEFT JOIN {quizaccess_sentientia_proctor} sentientia_proctor ON sentientia_proctor.quizid = quiz.id',
            [],
        ];
    }

    /**
     * The rule for this quiz and the current user, or null when it does not apply (see the class comment for the
     * four modes).
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits): ?access_rule_base {
        $settings = self::settings_of($quizobj->get_quiz());
        $secure = secure_mode::enabled();

        if ($quizobj->is_preview_user()) {
            // Previews by teachers are never proctored or protected (B1). With secure mode ON, a teacher reading a
            // learner's attempt of a quiz with a level gets the integrity report on the review page.
            if ($secure && $settings['level'] !== '') {
                return self::create($quizobj, (int) $timenow, self::MODE_REVIEWER);
            }
            return null;
        }

        $rule = self::make_for_learner($quizobj, (int) $timenow, $settings, $secure);
        if ($rule !== null && self::is_web_service_request()) {
            return $rule->for_web_service();
        }
        return $rule;
    }

    /**
     * The rule for a learner (not a preview user), as a quiz page needs it.
     *
     * B2 review (2026-11-20): an open attempt that accepted a secure-mode policy runs with that policy, before the
     * flags are read; and a fail-closed quiz blocks only a learner with no open attempt (see the class comment).
     *
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param array $settings from settings_of()
     * @param bool $secure secure mode is ON for the learner
     * @return self|null
     */
    private static function make_for_learner(quiz_settings $quizobj, int $timenow, array $settings,
            bool $secure): ?self {
        global $USER;
        if ($settings['level'] === '') {
            return null;  // No level and not proctored: a stock quiz, whatever the flags say.
        }

        $quizid = (int) $quizobj->get_quizid();
        $open = quiz_integration::open_attempt_of((int) $USER->id, $quizid);
        if ($open !== null) {
            $session = quiz_integration::session_for_attempt((int) $USER->id, $quizid, (int) $open->id);
            if ($session && $session->status === quiz_integration::ACTIVE
                    && secure_mode::is_level($session->securitylevel ?? null)) {
                // The rules the learner accepted, until the attempt ends (secure_report reads the same session).
                return self::create($quizobj, $timenow, self::MODE_SECURE, (string) $session->securitylevel,
                    max(0, (int) ($session->violationlimit ?? 0)));
            }
        }

        if ($secure) {
            if ($settings['level'] === secure_mode::LEVEL_PROCTORED && !quiz_integration::enabled()) {
                // Fail closed for new attempts. An open attempt with no accepted policy started before the quiz had a
                // level: it finishes as the stock quiz it started as instead of being trapped.
                return $open ? null : self::create($quizobj, $timenow, self::MODE_BLOCKED, '', 0, 'blocked_proctoring_off');
            }
            // B3 (Safe Exam Browser preset, session 9) adds its check for Secure and Proctored here.
            return self::create($quizobj, $timenow, self::MODE_SECURE, $settings['level'], $settings['limit']);
        }

        // Secure mode OFF for this learner.
        if (in_array($settings['setlevel'], [secure_mode::LEVEL_SECURE, secure_mode::LEVEL_PROCTORED], true)) {
            return $open ? null : self::create($quizobj, $timenow, self::MODE_BLOCKED, '', 0, 'blocked_securemode_off');
        }
        if (!$settings['enabled'] || !quiz_integration::enabled()) {
            return null;
        }
        return self::create($quizobj, $timenow, self::MODE_PROCTORING);
    }

    /**
     * B2 review: this rule as it applies to a web-service call (the mobile app, or a direct call with a token). Those
     * calls never load the attempt pages, so a quiz this rule protects or monitors cannot be taken through them: a
     * Secure or Proctored quiz, or a B1 proctored one, says "desktop required" (prevent_access(), so new and open
     * attempts alike); a Standard quiz runs as the stock quiz (ADR-040: non-proctored quizzes are allowed in the app,
     * plan decision D29). A quiz already blocked keeps its own reason.
     *
     * @return self|null
     */
    private function for_web_service(): ?self {
        if ($this->mode === self::MODE_BLOCKED) {
            return $this;
        }
        if ($this->mode === self::MODE_PROCTORING || in_array($this->level, self::DESKTOP_ONLY_LEVELS, true)) {
            $this->mode = self::MODE_BLOCKED;
            $this->level = '';
            $this->limit = 0;
            $this->blockedreason = 'blocked_desktop_required';
            return $this;
        }
        return null;
    }

    /**
     * Is this request a web-service call? Only the web-service servers (webservice/rest, webservice/soap) define
     * WS_SERVER as true; lib/ajax/service.php, which the quiz pages' own calls (secure_report, integrity_report) use,
     * does not.
     *
     * @return bool
     */
    private static function is_web_service_request(): bool {
        if (self::$wsrequestfortest !== null) {
            return self::$wsrequestfortest;
        }
        return defined('WS_SERVER') && WS_SERVER;
    }

    /**
     * Tests only: treat the requests that follow as web-service calls (true) or page requests (false); null detects
     * again. A PHPUnit run cannot define WS_SERVER for one test (lib/setup.php defines it false for the whole run).
     *
     * @param bool|null $value
     * @return void
     */
    public static function set_web_service_request_for_testing(?bool $value): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('set_web_service_request_for_testing() is for PHPUnit tests only');
        }
        self::$wsrequestfortest = $value;
    }

    /**
     * A rule in the given mode.
     *
     * @param quiz_settings $quizobj
     * @param int $timenow
     * @param string $mode
     * @param string $level
     * @param int $limit
     * @param string $blockedreason
     * @return self
     */
    private static function create(quiz_settings $quizobj, int $timenow, string $mode, string $level = '',
            int $limit = 0, string $blockedreason = ''): self {
        $rule = new self($quizobj, $timenow);
        $rule->mode = $mode;
        $rule->level = $level;
        $rule->limit = $limit;
        $rule->blockedreason = $blockedreason;
        return $rule;
    }

    /**
     * The quiz's stored settings: from the quiz object when core loaded them with it (get_settings_sql()), else from
     * the table.
     *
     * @param \stdClass $quiz
     * @return array enabled (bool), setlevel (stored level or ''), level (the level it runs at or ''), limit (the
     *               auto-submit limit: the quiz's limit for hiring, else 0)
     */
    private static function settings_of(\stdClass $quiz): array {
        global $DB;
        if (property_exists($quiz, self::FIELD_SETLEVEL)) {
            $enabled = !empty($quiz->{self::FIELD});
            $setlevel = (string) $quiz->{self::FIELD_SETLEVEL};
            $purpose = (string) ($quiz->{self::FIELD_PURPOSE} ?? '');
            $limit = $quiz->{self::FIELD_LIMIT} ?? null;
        } else {
            $row = $DB->get_record('quizaccess_sentientia_proctor', ['quizid' => (int) $quiz->id]);
            $enabled = $row && !empty($row->enabled);
            $setlevel = $row ? (string) ($row->securitylevel ?? '') : '';
            $purpose = $row ? (string) ($row->purpose ?? '') : '';
            $limit = $row ? ($row->violationlimit ?? null) : null;
        }
        $setlevel = secure_mode::is_level($setlevel) ? $setlevel : '';
        return [
            'enabled'  => $enabled,
            'setlevel' => $setlevel,
            'level'    => secure_mode::effective_level($setlevel, $enabled),
            'limit'    => secure_mode::autosubmit_limit($purpose, $limit),
        ];
    }

    /**
     * Fail closed (B2): a Secure or Proctored quiz that cannot run at its level does not start.
     *
     * @return string|false
     */
    public function prevent_access() {
        if ($this->mode === self::MODE_BLOCKED) {
            return get_string($this->blockedreason, self::C);
        }
        return false;
    }

    /**
     * Shown on the quiz's front page.
     */
    public function description(): string {
        if ($this->mode === self::MODE_PROCTORING) {
            return get_string('description', self::C);
        }
        if ($this->mode !== self::MODE_SECURE) {
            return '';
        }
        $text = get_string('description_secure', self::C, get_string('securitylevel_' . $this->level, self::C));
        if ($this->limit > 0) {
            $text .= ' ' . get_string('description_autosubmit', self::C, $this->limit);
        }
        if ($this->level === secure_mode::LEVEL_PROCTORED) {
            $text .= ' ' . get_string('description', self::C);
        }
        return $text;
    }

    /**
     * The pre-start check is needed until the learner has agreed and confirmed their identity for this attempt (or,
     * with no attempt yet, within the last hour for the attempt about to start). B2: in secure mode, until they have
     * accepted the secure-mode rules (and, at Proctored, agreed and confirmed their identity).
     *
     * @param int|null $attemptid
     * @return bool
     */
    public function is_preflight_check_required($attemptid) {
        global $USER;
        if ($this->mode !== self::MODE_PROCTORING && $this->mode !== self::MODE_SECURE) {
            return false;
        }
        return !quiz_integration::preflight_done((int) $USER->id, (int) $this->quiz->id,
            empty($attemptid) ? null : (int) $attemptid, $this->mode === self::MODE_SECURE);
    }

    /**
     * The notice, the consent and the identity confirmation (B1), or the secure-mode rules and their acknowledgement
     * (B2), in the quiz's pre-start form.
     *
     * @param preflight_check_form $quizform
     * @param MoodleQuickForm $mform
     * @param int|null $attemptid
     * @return void
     */
    public function add_preflight_check_form_fields(preflight_check_form $quizform,
            MoodleQuickForm $mform, $attemptid) {
        if ($this->mode === self::MODE_SECURE) {
            $this->add_secure_preflight_fields($mform);
            return;
        }
        if ($this->mode !== self::MODE_PROCTORING) {
            return;
        }
        $mform->addElement('header', 'sentientia_proctoring_header',
            get_string('preflight_header', self::C));
        $mform->addElement('static', 'sentientia_proctoring_notice', '',
            get_string('preflight_notice', self::C));
        $this->add_consent_fields($mform);
    }

    /**
     * B1's consent and identity boxes. The identity text is rendered as HTML: the learner's own name goes in escaped.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    private function add_consent_fields(MoodleQuickForm $mform): void {
        global $USER;
        $mform->addElement('advcheckbox', 'sentientia_proctoring_consent', '',
            get_string('preflight_consent', self::C));
        $mform->addElement('advcheckbox', 'sentientia_proctoring_identity', '',
            get_string('preflight_identity', self::C, s(fullname($USER))));
    }

    /**
     * B2: the rules notice of the quiz's level and policy, and the acknowledgement (or, at Proctored, B1's consent and
     * identity boxes).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    private function add_secure_preflight_fields(MoodleQuickForm $mform): void {
        $mform->addElement('header', 'sentientia_secure_header',
            get_string('secure_header', self::C, get_string('securitylevel_' . $this->level, self::C)));
        $notice = get_string('secure_notice_rules', self::C)
            . ($this->limit > 0 ? get_string('secure_notice_autosubmit', self::C, $this->limit)
                : get_string('secure_notice_warn', self::C))
            . ($this->level === secure_mode::LEVEL_PROCTORED ? get_string('secure_notice_proctored', self::C) : '')
            . get_string('secure_notice_limits', self::C)
            . get_string('secure_notice_cancel', self::C);
        $mform->addElement('static', 'sentientia_secure_notice', '', $notice);
        if ($this->level === secure_mode::LEVEL_PROCTORED) {
            $this->add_consent_fields($mform);
        } else {
            $mform->addElement('advcheckbox', self::FIELD_ACK, '', get_string('secure_ack', self::C));
        }
    }

    /**
     * Both boxes must be ticked (B1, and B2 at Proctored); B2 at Standard and Secure: the acknowledgement.
     *
     * @param array $data
     * @param array $files
     * @param array $errors
     * @param int|null $attemptid
     * @return array
     */
    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        if ($this->mode === self::MODE_SECURE && $this->level !== secure_mode::LEVEL_PROCTORED) {
            if (empty($data[self::FIELD_ACK])) {
                $errors[self::FIELD_ACK] = get_string('secure_ack_required', self::C);
            }
            return $errors;
        }
        if ($this->mode !== self::MODE_PROCTORING && $this->mode !== self::MODE_SECURE) {
            return $errors;
        }
        if (empty($data['sentientia_proctoring_consent'])) {
            $errors['sentientia_proctoring_consent'] =
                get_string('preflight_consent_required', self::C);
        }
        if (empty($data['sentientia_proctoring_identity'])) {
            $errors['sentientia_proctoring_identity'] =
                get_string('preflight_identity_required', self::C);
        }
        return $errors;
    }

    /**
     * Store the consent and identity confirmation (B1) or the accepted secure-mode policy (B2). Core calls this for
     * every rule once the whole pre-start form passed, so it acts only when this rule's own check was part of it.
     *
     * @param int|null $attemptid null for a new attempt (its session then waits for attempt_started)
     * @return void
     */
    public function notify_preflight_check_passed($attemptid) {
        global $USER;
        if (!$this->is_preflight_check_required($attemptid)) {
            return;
        }
        $policy = $this->mode === self::MODE_SECURE ? ['level' => $this->level, 'limit' => $this->limit] : null;
        quiz_integration::pass_preflight((int) $USER->id, (int) $this->quiz->id,
            empty($attemptid) ? null : (int) $attemptid, $policy);
    }

    /**
     * Set up the attempt pages core calls this on (startattempt.php, attempt.php, summary.php and review.php):
     *  - review.php (B2): the integrity report for a teacher reading a learner's attempt;
     *  - attempt.php and summary.php of the learner's open attempt: B1's monitor (proctoring mode) or B2's secure
     *    mode module (secure mode), with the session the server found for the attempt. startattempt.php has no open
     *    attempt in its URL, so nothing loads there.
     *
     * @param \moodle_page $page
     * @return void
     */
    public function setup_attempt_page($page) {
        if (!$page->has_set_url()) {
            return;
        }
        if (self::is_script($page, '/mod/quiz/review.php')) {
            $this->setup_review_page($page);
            return;
        }
        if ($this->mode === self::MODE_SECURE) {
            $this->setup_secure_page($page);
        } else if ($this->mode === self::MODE_PROCTORING) {
            $this->setup_proctoring_page($page);
        }
    }

    /**
     * B1: load the monitor on every page of the learner's open attempt.
     *
     * @param \moodle_page $page
     * @return void
     */
    private function setup_proctoring_page($page): void {
        global $USER;
        $attemptid = (int) $page->url->get_param('attempt');
        $attempt = quiz_integration::open_attempt($attemptid);
        if (!$attempt || (int) $attempt->quiz !== (int) $this->quiz->id || (int) $attempt->userid !== (int) $USER->id) {
            return;
        }
        $session = quiz_integration::session_for_attempt((int) $USER->id, (int) $this->quiz->id, $attemptid);
        if (!$session || $session->status !== quiz_integration::ACTIVE) {
            return;  // No consent for this attempt yet: core sends the learner to the pre-start check.
        }
        $page->requires->js_call_amd('local_sentientia_proctoring/proctor', 'start', [[
            'sessionid' => (int) $session->id,
            'attemptid' => $attemptid,
            'page'      => max(0, (int) $page->url->get_param('page')),
            'notice'    => get_string('monitor_notice', 'local_sentientia_proctoring'),
        ]]);
    }

    /**
     * B2: record the page on the session and load the secure-mode module on the learner's open attempt. The
     * fullscreen gate is on attempt.php only: the summary page shows no question.
     *
     * @param \moodle_page $page
     * @return void
     */
    private function setup_secure_page($page): void {
        global $USER;
        $attemptid = (int) $page->url->get_param('attempt');
        $attempt = quiz_integration::open_attempt($attemptid);
        if (!$attempt || (int) $attempt->quiz !== (int) $this->quiz->id || (int) $attempt->userid !== (int) $USER->id) {
            return;
        }
        $session = quiz_integration::session_for_attempt((int) $USER->id, (int) $this->quiz->id, $attemptid);
        if (!$session || $session->status !== quiz_integration::ACTIVE
                || !secure_mode::is_level($session->securitylevel ?? null)) {
            return;  // The rules were not accepted for this attempt yet: core sends the learner to the pre-start check.
        }
        $isattempt = self::is_script($page, '/mod/quiz/attempt.php');
        $pageno = $isattempt ? max(0, (int) $page->url->get_param('page')) : secure_mode::SUMMARY_PAGE;
        secure_mode::page_served($session, $pageno);

        // From the first paint (styles.css): printing gives a blank page and, on attempt pages, the questions are
        // hidden until the module puts the page in fullscreen. A page whose script never runs (blocked, or a modified
        // browser) therefore never shows the questions: the gate fails closed. The module lifts it in a browser that
        // has no fullscreen at all (a phone), and records that.
        $page->add_body_class('sentientia-secure-active');
        if ($isattempt) {
            $page->add_body_class('sentientia-secure-gated');
        }

        $page->requires->strings_for_js([
            'secure_banner', 'secure_banner_count', 'secure_banner_limit', 'secure_gate_title', 'secure_gate_body',
            'secure_gate_returned', 'secure_gate_denied', 'secure_gate_button', 'secure_warning_flag',
            'secure_warning_limit', 'secure_autosubmitted_title', 'secure_autosubmitted_body', 'secure_continue',
            'secure_blocked',
        ], 'local_sentientia_proctoring');
        $page->requires->js_call_amd('local_sentientia_proctoring/secure_mode', 'start', [[
            'attemptid'  => $attemptid,
            'page'       => $pageno,
            'gate'       => $isattempt,
            'watermark'  => get_string('secure_watermark', 'local_sentientia_proctoring',
                (object) ['name' => fullname($USER), 'attempt' => $attemptid]),
            'violations' => (int) ($session->violations ?? 0),
            'limit'      => (int) ($session->violationlimit ?? 0),
            'heartbeat'  => secure_mode::HEARTBEAT_SECONDS,
            'grace'      => secure_mode::GRACE_SECONDS,  // B2 review: the page reports a lost focus that outlasts it.
            'viewurl'    => (new \moodle_url('/mod/quiz/view.php', ['id' => (int) $this->quizobj->get_cmid()]))->out(false),
        ]]);
    }

    /**
     * B2: the integrity report, for a teacher reading another user's attempt of this quiz that ran in secure mode.
     *
     * @param \moodle_page $page
     * @return void
     */
    private function setup_review_page($page): void {
        global $DB, $USER;
        if (!secure_mode::enabled()) {
            return;
        }
        $attemptid = (int) $page->url->get_param('attempt');
        if ($attemptid <= 0) {
            return;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], 'id, quiz, userid');
        if (!$attempt || (int) $attempt->quiz !== (int) $this->quiz->id || (int) $attempt->userid === (int) $USER->id) {
            return;
        }
        if (!has_capability('mod/quiz:viewreports', $this->quizobj->get_context())) {
            return;
        }
        if (!$DB->record_exists_select('local_sentientia_proctor_sessions',
                'attemptid = :a AND securitylevel IS NOT NULL', ['a' => $attemptid])) {
            return;
        }
        $page->requires->js_call_amd('local_sentientia_proctoring/integrity_report', 'init', [['attemptid' => $attemptid]]);
    }

    /**
     * Is the page this core script?
     *
     * @param \moodle_page $page
     * @param string $script e.g. /mod/quiz/review.php
     * @return bool
     */
    private static function is_script($page, string $script): bool {
        return $page->url->compare(new \moodle_url($script), URL_MATCH_BASE);
    }

    /**
     * Whether this quiz is configured as proctored (its stored setting, whatever the flag says).
     */
    public static function is_quiz_proctored(int $quizid): bool {
        global $DB;
        $row = $DB->get_record('quizaccess_sentientia_proctor',
            ['quizid' => $quizid], 'enabled');
        return $row && !empty($row->enabled);
    }
}

// B1 (2026-11-20): the name core looks for. mod_quiz\access_manager::get_rule_classes() calls
// core_component::get_plugin_list_with_class('quizaccess', '', 'rule.php'), which includes this file and then accepts
// only a class named quizaccess_sentientia_proctoring in the global namespace (as quizaccess_seb and
// quizaccess_password are). The class above was declared only as quizaccess_sentientia_proctoring\rule, so core never
// found it: the rule never ran, its setting never reached the quiz form, and the lockout its docblock described could
// not happen either. The alias makes core find it; the namespaced name stays for the tests and any other caller.
if (!class_exists('quizaccess_sentientia_proctoring', false)) {
    \class_alias(rule::class, 'quizaccess_sentientia_proctoring');
}
