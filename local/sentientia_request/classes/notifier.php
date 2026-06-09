<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request;

defined('MOODLE_INTERNAL') || die();

class notifier {

    public static function request_submitted(\stdClass $req): void {
        $course = self::course_name($req->courseid);
        self::send($req->userid, 'request_submitted',
            "Request submitted: $course",
            "Your request to enrol in '$course' has been submitted.\n\n"
            . "You'll be notified when it's approved or rejected (SLA: "
            . get_config('local_sentientia_request', 'sla_hours') . "h).");
    }

    public static function request_pending(\stdClass $req): void {
        if (!$req->approver_userid) return;
        $course = self::course_name($req->courseid);
        $requester = self::user_name($req->userid);
        self::send($req->approver_userid, 'request_pending',
            "Course request needs your approval — $course",
            "$requester has requested enrolment in '$course'.\n\n"
            . "Reason: " . $req->reason . "\n\n"
            . "Decide: approve or reject this request from your"
            . " 'Pending approvals' page.");
    }

    public static function request_decided(\stdClass $req): void {
        $course = self::course_name($req->courseid);
        $body = $req->status === 'approved'
            ? "Your request to enrol in '$course' was approved. You're now enrolled."
            : "Your request to enrol in '$course' was rejected.\n\nReason: "
              . ($req->decision_note ?: '(no note)');
        self::send($req->userid, 'request_decided',
            "Request decision: " . ucfirst($req->status) . " — $course",
            $body);
    }

    public static function request_escalated(\stdClass $req): void {
        if (!$req->approver_userid) return;
        $course = self::course_name($req->courseid);
        self::send($req->approver_userid, 'request_escalated',
            "Course request escalated to you — $course",
            "A request for '$course' has been pending past its SLA"
            . " and has been escalated to you for decision.\n\n"
            . "Reason from requester: " . $req->reason);
    }

    private static function course_name(int $courseid): string {
        global $DB;
        $row = $DB->get_record('course', ['id' => $courseid], 'fullname');
        return $row ? format_string($row->fullname) : "course #$courseid";
    }

    private static function user_name(int $userid): string {
        global $DB;
        $row = $DB->get_record('user', ['id' => $userid],
            'firstname, lastname, email');
        return $row ? trim($row->firstname . ' ' . $row->lastname) . " ($row->email)"
                    : "user #$userid";
    }

    private static function send(int $userid, string $event, string $subject, string $body): void {
        global $DB;
        $user = $DB->get_record('user', ['id' => $userid], '*');
        if (!$user) return;
        $message = new \core\message\message();
        $message->component         = 'local_sentientia_request';
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
