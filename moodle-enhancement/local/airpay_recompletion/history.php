<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Reset history — audit log of every reset event.
 *
 * @package local_airpay_recompletion
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/airpay_recompletion/history.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Recompletion history');
$PAGE->set_heading('Recompletion history');
require_capability('local/airpay_recompletion:view', $ctx);

$page = optional_param('p', 0, PARAM_INT);
$perpage = 50;

$total = (int) $DB->count_records('local_airpay_recompletion_history');
$rows = $DB->get_records_sql(
    "SELECT h.*, u.firstname, u.lastname, u.email,
            c.fullname AS course_name
       FROM {local_airpay_recompletion_history} h
  LEFT JOIN {user}   u ON u.id = h.userid
  LEFT JOIN {course} c ON c.id = h.courseid
      ORDER BY h.timecreated DESC
      LIMIT $perpage OFFSET " . ($page * $perpage));

$shape = [];
foreach ($rows as $r) {
    $shape[] = [
        'id'        => (int) $r->id,
        'user_name' => $r->userid > 0
            ? trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''))
            : '(redacted)',
        'user_email' => (string) ($r->email ?? ''),
        'course_name' => format_string($r->course_name ?? '(deleted)'),
        'reason'    => $r->reason,
        'reset_at'  => userdate($r->timecreated, '%d %b %Y %H:%M'),
        'previous'  => $r->previous_timecompleted
            ? userdate($r->previous_timecompleted, '%d %b %Y') : '—',
        'dryrun'    => (bool) $r->dryrun,
        'grades'    => (bool) $r->reset_grades,
        'attempts'  => (bool) $r->reset_attempts,
    ];
}

$data = [
    'rows'     => $shape,
    'has_rows' => !empty($shape),
    'total'    => $total,
    'has_prev' => $page > 0,
    'has_next' => ($page + 1) * $perpage < $total,
    'prev_url' => (new moodle_url('/local/airpay_recompletion/history.php',
        ['p' => max(0, $page - 1)]))->out(false),
    'next_url' => (new moodle_url('/local/airpay_recompletion/history.php',
        ['p' => $page + 1]))->out(false),
    'rules_url' => (new moodle_url('/local/airpay_recompletion/index.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_airpay_recompletion/history', $data);
echo $OUTPUT->footer();
