<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_airpay_notifications\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Send a one-off notification using a rule's channel / template, addressed
 * to either the admin themselves or one named user. Used by admins to
 * verify rule wording end-to-end (rendered → message bus → reaches inbox).
 */
class test_send extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid' => new external_value(PARAM_INT, 'Rule ID'),
            'userid' => new external_value(PARAM_INT, 'Target user ID (0 = self)',
                VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $ruleid, int $userid = 0): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('ruleid', 'userid'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/airpay_notifications:manage', $context);
        require_sesskey();

        $rule = $DB->get_record('local_airpay_notif_rules',
            ['id' => $params['ruleid']], '*', MUST_EXIST);

        $targetid = (int) ($params['userid'] ?: $USER->id);
        $target = $DB->get_record('user',
            ['id' => $targetid, 'deleted' => 0], 'id, firstname, lastname, email');
        if (!$target) {
            throw new \moodle_exception('invaliduser');
        }

        // Build a preview message.
        $preview = preview_rule::execute((int) $rule->id, $targetid);
        $subject = '[TEST] ' . $preview['subject'];
        $html = $preview['message'];

        $eventdata = new \core\message\message();
        $eventdata->component         = 'local_airpay_notifications';
        $eventdata->name              = 'smart_alert';
        $eventdata->userfrom          = \core_user::get_noreply_user();
        $eventdata->userto            = $target->id;
        $eventdata->subject           = $subject;
        $eventdata->fullmessage       = html_to_text($html);
        $eventdata->fullmessageformat = FORMAT_HTML;
        $eventdata->fullmessagehtml   = $html;
        $eventdata->smallmessage      = $subject;
        $eventdata->notification      = 1;

        $sent_id = (int) message_send($eventdata);

        // Log the test send for audit.
        $DB->insert_record('local_airpay_notif_log', (object) [
            'ruleid'      => (int) $rule->id,
            'userid'      => (int) $target->id,
            'courseid'    => 0,
            'channel'     => (string) $rule->channel,
            'subject'     => $subject,
            'message'     => $html,
            'status'      => 'sent',
            'timecreated' => time(),
        ]);

        return [
            'ok'      => $sent_id > 0,
            'message_id' => $sent_id,
            'sent_to' => $target->firstname . ' ' . $target->lastname
                . ' <' . $target->email . '>',
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'ok'         => new external_value(PARAM_BOOL, 'Send succeeded'),
            'message_id' => new external_value(PARAM_INT,  'Moodle message ID'),
            'sent_to'    => new external_value(PARAM_TEXT, 'Recipient summary'),
        ]);
    }
}
