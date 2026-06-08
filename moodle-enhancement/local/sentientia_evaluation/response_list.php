<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Individual responses list — Phase 4 B.6.
 *
 * Companion to the aggregate responses.php view. Lists each submission
 * separately with a link to the drill-down detail page.
 *
 * @package local_sentientia_evaluation
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$evaluationid = required_param('id', PARAM_INT);
$evaluation = $DB->get_record('local_sentientia_evaluation',
    ['id' => $evaluationid], '*', MUST_EXIST);

$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_evaluation/response_list.php',
    ['id' => $evaluationid]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Responses — ' . format_string($evaluation->name));
$PAGE->set_heading('Individual responses — ' . format_string($evaluation->name));
require_capability('local/sentientia_evaluation:view', $ctx);

$is_anonymous = (int) ($evaluation->anonymous ?? 0) === 1;

// Pull all responses with associated user info.
$rows = $DB->get_records_sql(
    "SELECT r.id, r.userid, r.courseid, r.programid, r.classroomid,
            r.timesubmitted,
            u.firstname, u.lastname, u.email, u.open_employeeid
       FROM {local_sentientia_evaluation_responses} r
  LEFT JOIN {user} u ON u.id = r.userid
      WHERE r.evaluationid = :eid
   ORDER BY r.timesubmitted DESC",
    ['eid' => $evaluationid]);

$shape = [];
foreach ($rows as $r) {
    $shape[] = [
        'id'           => (int) $r->id,
        'submitted_at' => userdate($r->timesubmitted, '%d %b %Y %H:%M'),
        'user_name'    => $is_anonymous ? '(anonymous)'
                                        : trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')),
        'user_email'   => $is_anonymous ? '' : (string) ($r->email ?? ''),
        'employee_id'  => $is_anonymous ? '' : (string) ($r->open_employeeid ?? ''),
        'context'      => ($r->courseid > 0)    ? "course #$r->courseid"
                       : (($r->programid > 0)   ? "program #$r->programid"
                       : (($r->classroomid > 0) ? "classroom #$r->classroomid" : '—')),
        'detail_url'   => (new moodle_url('/local/sentientia_evaluation/response_detail.php',
            ['id' => $r->id]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_evaluation/response_list', [
    'eval_name'     => format_string($evaluation->name),
    'is_anonymous'  => $is_anonymous,
    'total'         => count($shape),
    'rows'          => $shape,
    'has_rows'      => !empty($shape),
    'aggregate_url' => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluationid]))->out(false),
    'analysis_url'  => (new moodle_url('/local/sentientia_evaluation/analysis.php',
        ['id' => $evaluationid]))->out(false),
]);
echo $OUTPUT->footer();
