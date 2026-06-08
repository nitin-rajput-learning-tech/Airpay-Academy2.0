<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_notifications\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Render a rule's message body as it would appear for one user — without
 * sending. For admin preview / smoke-test in the admin UI.
 */
class preview_rule extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'ruleid'  => new external_value(PARAM_INT, 'Rule ID'),
            'userid'  => new external_value(PARAM_INT, 'Target user ID',
                VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $ruleid, int $userid = 0): array {
        global $DB, $USER;
        $params = self::validate_parameters(self::execute_parameters(),
            compact('ruleid', 'userid'));
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('local/sentientia_notifications:manage', $context);

        $rule = $DB->get_record('local_sentientia_notif_rules',
            ['id' => $params['ruleid']], '*', MUST_EXIST);

        // Default the preview user to the admin themselves.
        $targetid = (int) ($params['userid'] ?: $USER->id);
        $user = $DB->get_record('user',
            ['id' => $targetid, 'deleted' => 0], 'id, firstname, lastname, email');
        if (!$user) {
            throw new \moodle_exception('invaliduser');
        }

        // Pick a sample course for placeholder rendering.
        $course = $DB->get_record_sql(
            "SELECT id, fullname FROM {course} WHERE id <> :siteid AND visible = 1
               ORDER BY id DESC LIMIT 1",
            ['siteid' => SITEID]);
        $course_id = $course ? (int) $course->id : 0;
        $course_name = $course ? format_string($course->fullname) : '(no course)';

        $subject = 'Preview: ' . format_string($rule->name);
        $message = self::render_template((string) ($rule->template ?? ''),
            (string) ($rule->name ?? 'Notification'), (object) [
            'firstname' => $user->firstname,
            'lastname'  => $user->lastname,
            'fullname'  => $user->firstname . ' ' . $user->lastname,
            'course_name' => $course_name,
            'course_url'  => $course_id
                ? (new \moodle_url('/course/view.php', ['id' => $course_id]))->out(false) : '',
            'rule_type' => $rule->rule_type,
            'channel'   => $rule->channel,
        ]);

        return [
            'subject' => $subject,
            'message' => $message,
            'channel' => (string) $rule->channel,
            'rule_type' => (string) $rule->rule_type,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'subject'   => new external_value(PARAM_TEXT, 'Subject line preview'),
            'message'   => new external_value(PARAM_RAW, 'Message body preview (HTML)'),
            'channel'   => new external_value(PARAM_TEXT, 'Channel'),
            'rule_type' => new external_value(PARAM_TEXT, 'Rule type'),
        ]);
    }

    /** Render a {{placeholder}} template with the given context. */
    private static function render_template(string $template,
                                            string $fallback_subject,
                                            \stdClass $ctx): string {
        $body = trim($template) === ''
            ? "<p>Hi {{firstname}},</p>"
              . "<p>This is a preview of the <strong>$fallback_subject</strong> notification, "
              . "rendered against your account. Substitution: course = "
              . "{{course_name}}.</p>"
            : $template;
        // Simple {{var}} replacement — no logic, no loops.
        return preg_replace_callback('/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/',
            function ($m) use ($ctx) {
                $key = $m[1];
                return isset($ctx->$key) ? s((string) $ctx->$key) : '';
            }, $body);
    }
}
