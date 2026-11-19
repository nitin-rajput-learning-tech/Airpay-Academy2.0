<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Proctoring notifications.
 *
 * B1 (ADR-038 D2, 2026-11-20): the texts moved from English literals into the language pack (en + hi) and are
 * rendered in the RECIPIENT's language, not the sender's: the flag notice is sent from the attempt observer, which
 * runs as the learner or, for an attempt that timed out, in cron as nobody.
 *
 * @package local_sentientia_proctoring
 */
class notifier {

    public static function session_flagged(\stdClass $session, int $reviewerid): void {
        if (!$reviewerid) {
            return;
        }
        $a = (object) [
            'sessionid' => (int) $session->id,
            'userid'    => (int) $session->userid,
            'quizid'    => (int) $session->quizid,
            'score'     => number_format((float) $session->risk_score, 1),
            'decision'  => (string) $session->auto_decision,
        ];
        self::send($reviewerid, 'session_flagged', 'msg_flagged_subject', 'msg_flagged_body', $a);
    }

    public static function session_reviewed(\stdClass $session): void {
        $decision = (string) $session->human_decision;
        $a = (object) ['decision' => $decision];
        self::send((int) $session->userid, 'session_reviewed', 'msg_reviewed_subject',
            $decision === 'fail' ? 'msg_reviewed_body_fail' : 'msg_reviewed_body', $a);
    }

    public static function identity_failed(\stdClass $session, array $result): void {
        $a = (object) ['score' => number_format((float) ($result['score'] ?? 0), 1)];
        self::send((int) $session->userid, 'identity_failed', 'msg_identityfailed_subject',
            'msg_identityfailed_body', $a);
    }

    /**
     * Send one notification, its texts in the recipient's language.
     *
     * @param int $userid recipient
     * @param string $event message provider name (db/messages.php)
     * @param string $subjectid language string id of the subject
     * @param string $bodyid language string id of the plain-text body
     * @param \stdClass $a placeholders
     * @return void
     */
    private static function send(int $userid, string $event, string $subjectid, string $bodyid, \stdClass $a): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*');
        if (!$user) {
            return;
        }
        $lang = !empty($user->lang) ? $user->lang : null;
        $strings = get_string_manager();
        $subject = $strings->get_string($subjectid, 'local_sentientia_proctoring', $a, $lang);
        $body    = $strings->get_string($bodyid, 'local_sentientia_proctoring', $a, $lang);

        $message = new \core\message\message();
        $message->component         = 'local_sentientia_proctoring';
        $message->name              = $event;
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $user;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = nl2br(s($body));
        $message->smallmessage      = $subject;
        $message->notification      = 1;
        message_send($message);
    }
}
