<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Per-message detailed tracking — Phase 4 B.8.
 *
 * @package local_airpay_notifications
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_notifications/log_detail.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Notification detail');
$PAGE->set_heading('Notification detail');
require_capability('local/airpay_notifications:viewlogs', $ctx);

$log = $DB->get_record('local_airpay_notif_log', ['id' => $id], '*', MUST_EXIST);
$rule = $log->ruleid
    ? $DB->get_record('local_airpay_notif_rules', ['id' => $log->ruleid])
    : null;
$user = $log->userid
    ? $DB->get_record('user', ['id' => $log->userid], 'firstname,lastname,email')
    : null;
$course = $log->courseid > 0
    ? $DB->get_record('course', ['id' => $log->courseid], 'fullname,shortname')
    : null;

// Recent timeline for this user + rule (last 30 days).
$timeline = [];
if ($log->userid && $log->ruleid) {
    $since = time() - (30 * 86400);
    $events = $DB->get_records_sql(
        "SELECT * FROM {local_airpay_notif_log}
          WHERE userid = :uid AND ruleid = :rid AND timecreated > :since
          ORDER BY timecreated ASC",
        ['uid' => $log->userid, 'rid' => $log->ruleid, 'since' => $since]);
    foreach ($events as $e) {
        $timeline[] = [
            'time'       => userdate($e->timecreated, '%d %b %Y %H:%M'),
            'subject'    => format_string($e->subject ?? ''),
            'status'     => $e->status,
            'is_current' => $e->id == $log->id,
            'detail_url' => (new moodle_url('/local/airpay_notifications/log_detail.php',
                ['id' => $e->id]))->out(false),
            'read_at'    => $e->timeread ? userdate($e->timeread, '%H:%M') : '',
        ];
    }
}

$data = [
    'id'          => (int) $log->id,
    'subject'     => format_string($log->subject ?? ''),
    'message'     => format_text($log->message ?? '', FORMAT_HTML),
    'has_message' => !empty(trim($log->message ?? '')),
    'channel'     => $log->channel,
    'status'      => $log->status,
    'status_css'  => match ($log->status) {
        'sent'    => 'badge bg-success',
        'sending' => 'badge bg-info',
        'failed'  => 'badge bg-danger',
        default   => 'badge bg-secondary',
    },
    'sent_at'     => userdate($log->timecreated, '%d %b %Y %H:%M:%S'),
    'read_at'     => $log->timeread ? userdate($log->timeread, '%d %b %Y %H:%M:%S') : '(unread)',
    'is_read'     => !empty($log->timeread),

    'has_user'    => !empty($user),
    'user_name'   => $user ? trim($user->firstname . ' ' . $user->lastname) : '',
    'user_email'  => $user->email ?? '',

    'has_rule'    => !empty($rule),
    'rule_name'   => $rule ? format_string($rule->name) : '',
    'rule_type'   => $rule->rule_type ?? '',

    'has_course'  => !empty($course),
    'course_name' => $course ? format_string($course->fullname) : '',

    'timeline'    => $timeline,
    'has_timeline' => count($timeline) > 1,

    'back_url'    => (new moodle_url('/local/airpay_notifications/logs.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/log_detail', $data);
echo $OUTPUT->footer();
