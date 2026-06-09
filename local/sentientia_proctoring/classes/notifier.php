<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_proctoring;

defined('MOODLE_INTERNAL') || die();

class notifier {

    public static function session_flagged(\stdClass $session, int $reviewerid): void {
        if (!$reviewerid) return;
        $score = number_format((float) $session->risk_score, 1);
        $decision = $session->auto_decision;
        self::send($reviewerid, 'session_flagged',
            "Proctored session flagged — auto-decision: $decision (risk $score)",
            "A proctored quiz session has been flagged for human review.\n\n"
            . "Session ID: {$session->id}\nUser ID: {$session->userid}\n"
            . "Quiz ID: {$session->quizid}\nRisk score: $score/100\n"
            . "Auto-decision: $decision\n\n"
            . "Open the review queue to view the recording and make a final decision.");
    }

    public static function session_reviewed(\stdClass $session): void {
        $decision = $session->human_decision;
        self::send((int) $session->userid, 'session_reviewed',
            "Your proctored session was reviewed",
            "Your proctored quiz session has been reviewed.\n\n"
            . "Decision: " . ucfirst($decision) . "\n\n"
            . ($decision === 'fail'
                ? "Unfortunately, suspicious activity was detected. "
                  . "You may not have the result of this attempt count. "
                  . "Contact L&D admin if you believe this is in error."
                : "Thank you for completing the proctored exam."));
    }

    public static function identity_failed(\stdClass $session, array $result): void {
        $score = number_format((float) ($result['score'] ?? 0), 1);
        self::send((int) $session->userid, 'identity_failed',
            'Identity verification failed',
            "Your identity verification did not pass for this proctored exam.\n\n"
            . "Match score: $score%\n\n"
            . "Please retake the selfie in better lighting, with your face "
            . "centred and clearly visible. If this continues, contact support.");
    }

    private static function send(int $userid, string $event, string $subject, string $body): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user) return;
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
