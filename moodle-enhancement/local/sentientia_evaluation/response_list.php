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
// ADR-031: names, emails and employee ids only for an evaluation in the
// caller's tenant (in place before this page is ever re-enabled - :view is
// not declared, so today it is dead for everyone).
\local_sentientia_evaluation\evaluation_manager::require_evaluation_access($evaluation);

// 2026-09-25: sticky - anonymous now, answered anonymously before, or with an
// anonymous question (evaluation_manager::identity_protected()). Unticking
// the evaluation's flag used to bring names, emails and employee ids back
// here, each beside its minute-exact submission time; for these evaluations
// the time is shown to the day only.
$is_anonymous = \local_sentientia_evaluation\evaluation_manager::identity_protected($evaluation);

// A supervisor evaluation is answered by one person about another; the import keeps that person in
// subject_userid. The column appears only when some response has one, and never on a protected evaluation.
$show_subject = \local_sentientia_evaluation\evaluation_manager::shows_subject($evaluation, $is_anonymous);

// Pull all responses with associated user info (and, for a supervisor evaluation, the person it is about).
$rows = $DB->get_records_sql(
    "SELECT r.id, r.userid, r.subject_userid, r.courseid, r.programid, r.classroomid,
            r.timesubmitted,
            u.firstname, u.lastname, u.email, u.open_employeeid,
            s.id AS subjectuserid, s.firstname AS subjectfirstname, s.lastname AS subjectlastname,
            s.deleted AS subjectdeleted
       FROM {local_sentientia_evaluation_responses} r
  LEFT JOIN {user} u ON u.id = r.userid
  LEFT JOIN {user} s ON s.id = r.subject_userid
      WHERE r.evaluationid = :eid
        AND r.timesubmitted > 0
   ORDER BY r.timesubmitted DESC",
    ['eid' => $evaluationid]);

$shape = [];
foreach ($rows as $r) {
    // '' when the response has no subject; '(deleted user)' when that account is gone or deleted.
    $subject_name = '';
    if ($show_subject && $r->subject_userid !== null) {
        $subject_name = ($r->subjectuserid !== null && empty($r->subjectdeleted))
            ? trim(($r->subjectfirstname ?? '') . ' ' . ($r->subjectlastname ?? ''))
            : '(deleted user)';
    }
    $shape[] = [
        'id'           => (int) $r->id,
        'subject_name' => $subject_name,
        'submitted_at' => \local_sentientia_evaluation\evaluation_manager::submitted_label(
            (int) $r->timesubmitted, $is_anonymous),
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
    'show_subject'  => $show_subject,
    'total'         => count($shape),
    'rows'          => $shape,
    'has_rows'      => !empty($shape),
    'aggregate_url' => (new moodle_url('/local/sentientia_evaluation/responses.php',
        ['id' => $evaluationid]))->out(false),
    'analysis_url'  => (new moodle_url('/local/sentientia_evaluation/analysis.php',
        ['id' => $evaluationid]))->out(false),
]);
echo $OUTPUT->footer();
