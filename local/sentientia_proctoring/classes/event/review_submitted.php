<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring\event;

defined('MOODLE_INTERNAL') || die();

/**
 * A reviewer submitted a decision on a proctored session.
 *
 * Decision A5 (2026-11-12): \local_sentientia_platform\audit_log::SENSITIVE_EVENTS has named this class since the
 * audit dashboard was built, but nothing created it, so no exam-integrity verdict ever reached the audit trail. It is
 * triggered once per review, by session_manager::submit_review(), after the review row is written and the session is
 * stored as reviewed: never for an unknown decision (refused before anything is written), nor when the web service
 * refuses the reviewer (capability, tenant). The standard logstore records it.
 *
 * The object is the session (local_sentientia_proctor_sessions), whose status the review changes to reviewed; the
 * review row it wrote is in `other`. The context is the quiz's module context, or the system context when the quiz no
 * longer exists (session_manager::event_context()). relateduserid is the candidate; userid is the reviewer.
 *
 * Payload contract, `other` (ids and codes only: the reviewer's free-text note is NOT carried):
 *   reviewid  int     The review row in local_sentientia_proctor_reviews.
 *   quizid    int     The quiz instance (local_sentientia_proctor_sessions.quizid).
 *   decision  string  clean, warn or fail.
 *
 * @package    local_sentientia_proctoring
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_submitted extends \core\event\base {

    /** @var string[] The values `other.decision` may take (session_manager::submit_review() accepts no other). */
    public const DECISIONS = ['clean', 'warn', 'fail'];

    /**
     * Build the event for a review that submit_review() has just stored.
     *
     * @param \stdClass $session The session row, as submit_review() left it (id, userid, quizid, human_decision).
     * @param int $reviewid The review row.
     * @param int $reviewerid The reviewer who submitted it.
     * @return self
     */
    public static function create_from_review(\stdClass $session, int $reviewid, int $reviewerid): self {
        $quizid = (int) ($session->quizid ?? 0);
        return self::create([
            'context'       => \local_sentientia_proctoring\session_manager::event_context($quizid),
            'objectid'      => (int) $session->id,
            'userid'        => $reviewerid,
            'relateduserid' => (int) $session->userid,
            'other'         => [
                'reviewid' => $reviewid,
                'quizid'   => $quizid,
                'decision' => (string) $session->human_decision,
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
        return get_string('eventreviewsubmitted', 'local_sentientia_proctoring');
    }

    /**
     * Plain-English description, ids only.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' reviewed the proctored session with id '{$this->objectid}' of the"
            . " user with id '{$this->relateduserid}' on the quiz with id '" . (int) ($this->other['quizid'] ?? 0)
            . "' and decided '" . ($this->other['decision'] ?? '') . "' (review row "
            . (int) ($this->other['reviewid'] ?? 0) . ').';
    }

    /**
     * The attempt detail page of the session, where the review is shown.
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
     * The review row is not part of a course backup; the quiz is restored with the course, as mod_quiz's own events
     * map it. decision is a code, not an id.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'reviewid' => ['db' => 'local_sentientia_proctor_reviews', 'restore' => \core\event\base::NOT_MAPPED],
            'quizid'   => ['db' => 'quiz', 'restore' => 'quiz'],
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
        foreach (['reviewid', 'quizid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("review_submitted: the '$key' value must be set in other.");
            }
        }
        if (!isset($this->other['decision']) || !in_array($this->other['decision'], self::DECISIONS, true)) {
            throw new \coding_exception("review_submitted: 'decision' in other must be clean, warn or fail.");
        }
    }
}
