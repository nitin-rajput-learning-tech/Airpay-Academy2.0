<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\event;

defined('MOODLE_INTERNAL') || die();

/**
 * A proctored session was flagged for human review.
 *
 * Decision A5 (2026-11-12): \local_sentientia_platform\audit_log::SENSITIVE_EVENTS has named this class since the
 * audit dashboard was built, but nothing created it. A session becomes flagged in two places, and each triggers it
 * once, after the flag is stored:
 *   - auto:   session_manager::finalize(), when the risk analyzer's verdict is not clean;
 *   - manual: external\flag_session, when a reviewer flags a session that was not flagged already.
 * A clean finish, a finalize() replay and a re-flag of a flagged session trigger nothing. The standard logstore
 * records it.
 *
 * The object is the session (local_sentientia_proctor_sessions). The context is the quiz's module context, or the
 * system context when the quiz no longer exists (session_manager::event_context()). relateduserid is the candidate;
 * userid is whoever ran the request (the candidate for auto, the reviewer for manual).
 *
 * Payload contract, `other` (ids and codes only: no risk score, no names):
 *   quizid  int     The quiz instance (local_sentientia_proctor_sessions.quizid).
 *   source  string  auto (the analyzer) or manual (a reviewer).
 *
 * @package    local_sentientia_proctoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_flagged extends \core\event\base {

    /** @var string[] The values `other.source` may take. */
    public const SOURCES = ['auto', 'manual'];

    /**
     * Build the event for a session that has just been stored as flagged.
     *
     * @param \stdClass $session The session row (id, userid, quizid).
     * @param string $source auto or manual
     * @return self
     */
    public static function create_from_session(\stdClass $session, string $source): self {
        $quizid = (int) ($session->quizid ?? 0);
        return self::create([
            'context'       => \local_sentientia_proctoring\session_manager::event_context($quizid),
            'objectid'      => (int) $session->id,
            'relateduserid' => (int) $session->userid,
            'other'         => [
                'quizid' => $quizid,
                'source' => $source,
            ],
        ]);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_sentientia_proctor_sessions';
    }

    /**
     * The event's name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventsessionflagged', 'local_sentientia_proctoring');
    }

    /**
     * Plain-English description, ids only.
     *
     * @return string
     */
    public function get_description(): string {
        $quizid = (int) ($this->other['quizid'] ?? 0);
        if (($this->other['source'] ?? '') === 'manual') {
            return "The user with id '{$this->userid}' flagged the proctored session with id '{$this->objectid}'"
                . " of the user with id '{$this->relateduserid}' on the quiz with id '$quizid' for review.";
        }
        return "The risk analyzer flagged the proctored session with id '{$this->objectid}' of the user with id"
            . " '{$this->relateduserid}' on the quiz with id '$quizid' for review, when the user with id"
            . " '{$this->userid}' finished it.";
    }

    /**
     * The attempt detail page of the session.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/sentientia_proctoring/attempt.php', ['id' => $this->objectid]);
    }

    /**
     * Proctoring sessions are not part of a course backup, so the object cannot be mapped on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_sentientia_proctor_sessions', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * The quiz is restored with the course, as mod_quiz's own events map it. source is a code, not an id.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'quizid' => ['db' => 'quiz', 'restore' => 'quiz'],
        ];
    }

    /**
     * Refuse an event that lacks the other values the audit trail needs.
     *
     * @return void
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['quizid'])) {
            throw new \coding_exception("session_flagged: the 'quizid' value must be set in other.");
        }
        if (!isset($this->other['source']) || !in_array($this->other['source'], self::SOURCES, true)) {
            throw new \coding_exception("session_flagged: 'source' in other must be auto or manual.");
        }
    }
}
