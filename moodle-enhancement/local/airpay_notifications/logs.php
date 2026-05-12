<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Notification log + email status filter UI (Phase 4 B.8).
 *
 * @package local_airpay_notifications
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_notifications/logs.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Notification logs');
$PAGE->set_heading('Notification logs');
require_capability('local/airpay_notifications:viewlogs', $ctx);

// Filters from URL.
$status  = optional_param('status',  '', PARAM_ALPHANUMEXT);
$channel = optional_param('channel', '', PARAM_ALPHA);
$ruletype = optional_param('ruletype', '', PARAM_ALPHANUMEXT);
$from    = optional_param('from',    '', PARAM_TEXT);
$to      = optional_param('to',      '', PARAM_TEXT);
$search  = optional_param('q',       '', PARAM_TEXT);
$page    = optional_param('p', 0, PARAM_INT);
$perpage = 50;

$where = ['1=1'];
$args  = [];

if ($status !== '') {
    $where[] = 'l.status = :status';
    $args['status'] = $status;
}
if ($channel !== '') {
    $where[] = 'l.channel = :channel';
    $args['channel'] = $channel;
}
if ($ruletype !== '') {
    $where[] = 'r.rule_type = :ruletype';
    $args['ruletype'] = $ruletype;
}
if ($from !== '') {
    $ts = strtotime($from . ' 00:00:00');
    if ($ts) { $where[] = 'l.timecreated >= :from_ts'; $args['from_ts'] = $ts; }
}
if ($to !== '') {
    $ts = strtotime($to . ' 23:59:59');
    if ($ts) { $where[] = 'l.timecreated <= :to_ts'; $args['to_ts'] = $ts; }
}
if ($search !== '') {
    $term = '%' . $DB->sql_like_escape($search) . '%';
    $where[] = '(' . $DB->sql_like('l.subject', ':s1', false) . ' OR '
        . $DB->sql_like('u.email', ':s2', false) . ')';
    $args['s1'] = $term; $args['s2'] = $term;
}

$wheresql = implode(' AND ', $where);

$total = (int) $DB->get_field_sql(
    "SELECT COUNT(*)
       FROM {local_airpay_notif_log} l
  LEFT JOIN {local_airpay_notif_rules} r ON r.id = l.ruleid
  LEFT JOIN {user} u ON u.id = l.userid
      WHERE $wheresql", $args);

$rows = $DB->get_records_sql(
    "SELECT l.*, r.name AS rule_name, r.rule_type,
            u.firstname, u.lastname, u.email
       FROM {local_airpay_notif_log} l
  LEFT JOIN {local_airpay_notif_rules} r ON r.id = l.ruleid
  LEFT JOIN {user} u ON u.id = l.userid
      WHERE $wheresql
   ORDER BY l.timecreated DESC
      LIMIT $perpage OFFSET " . ($page * $perpage),
    $args);

// Status counts for the filter UI badges.
$status_counts = $DB->get_records_sql(
    "SELECT status, COUNT(*) AS n FROM {local_airpay_notif_log}
       GROUP BY status ORDER BY n DESC");

$status_options = ['' => 'All'];
foreach ($status_counts as $sc) {
    $status_options[$sc->status] = $sc->status . ' (' . $sc->n . ')';
}

// All known rule_types (existing + 4 new in Phase 4 B.8).
$all_rule_types = [
    '' => 'All',
    'cert_expired'                 => 'Cert expired',
    'certificate_expiring'         => 'Certificate expiring (pre-expiry)',
    'compliance_overdue'           => 'Compliance overdue',
    'course_not_started'           => 'Course not started',
    'deadline_approaching'         => 'Deadline approaching',
    'enrolment_anniversary'        => 'Enrolment anniversary',
    'ilt_feedback_pending'         => 'ILT feedback pending',
    'inactive_user'                => 'Inactive user',
    'learning_path_stalled'        => 'Learning path stalled',
    'manager_nudge'                => 'Manager nudge',
    'manager_summary_weekly'       => 'Manager summary (weekly)',
    'monthly_summary'              => 'Monthly summary',
    'new_course'                   => 'New course',
    'peer_completion_celebration'  => 'Peer completion celebration',
    'quiz_low_score'               => 'Quiz low score',
    'streak_broken'                => 'Streak broken',
    'training_overdue'             => 'Training overdue (admin digest)',
];

$shape_rows = [];
foreach ($rows as $r) {
    $shape_rows[] = [
        'id'         => (int) $r->id,
        'rule_name'  => format_string($r->rule_name ?? '(deleted rule)'),
        'rule_type'  => (string) ($r->rule_type ?? ''),
        'user_name'  => trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
        'user_email' => (string) ($r->email ?? ''),
        'channel'    => $r->channel,
        'subject'    => format_string($r->subject ?? ''),
        'status'     => $r->status,
        'status_css' => match ($r->status) {
            'sent'    => 'badge bg-success',
            'sending' => 'badge bg-info',
            'failed'  => 'badge bg-danger',
            'queued'  => 'badge bg-secondary',
            default   => 'badge bg-secondary',
        },
        'placed_on'  => userdate($r->timecreated, '%d %b %Y %H:%M'),
        'read_on'    => $r->timeread ? userdate($r->timeread, '%d %b %Y %H:%M') : '',
        'detail_url' => (new moodle_url('/local/airpay_notifications/log_detail.php',
            ['id' => $r->id]))->out(false),
    ];
}

$data = [
    'total'         => $total,
    'page'          => $page,
    'perpage'       => $perpage,
    'rows'          => $shape_rows,
    'has_rows'      => !empty($shape_rows),

    'filter_status'   => $status,
    'filter_channel'  => $channel,
    'filter_ruletype' => $ruletype,
    'filter_from'     => $from,
    'filter_to'       => $to,
    'filter_search'   => $search,

    'status_options'    => array_map(fn($k, $v) => [
        'value' => $k, 'label' => $v, 'selected' => $k === $status
    ], array_keys($status_options), $status_options),

    'ruletype_options'  => array_map(fn($k, $v) => [
        'value' => $k, 'label' => $v, 'selected' => $k === $ruletype
    ], array_keys($all_rule_types), $all_rule_types),

    'channels' => [
        ['value' => '',      'label' => 'All',  'selected' => $channel === ''],
        ['value' => 'inapp', 'label' => 'In-app', 'selected' => $channel === 'inapp'],
        ['value' => 'email', 'label' => 'Email', 'selected' => $channel === 'email'],
        ['value' => 'push',  'label' => 'Push',  'selected' => $channel === 'push'],
    ],

    'has_prev' => $page > 0,
    'has_next' => ($page + 1) * $perpage < $total,
    'prev_url' => (new moodle_url('/local/airpay_notifications/logs.php',
        array_filter(['p' => max(0, $page - 1), 'status' => $status,
                      'channel' => $channel, 'ruletype' => $ruletype,
                      'from' => $from, 'to' => $to, 'q' => $search])))->out(false),
    'next_url' => (new moodle_url('/local/airpay_notifications/logs.php',
        array_filter(['p' => $page + 1, 'status' => $status,
                      'channel' => $channel, 'ruletype' => $ruletype,
                      'from' => $from, 'to' => $to, 'q' => $search])))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_notifications/logs', $data);
echo $OUTPUT->footer();
