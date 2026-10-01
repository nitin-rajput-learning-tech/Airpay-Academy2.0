<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Reset history — audit log of every reset event.
 *
 * ADR-032 (2026-09-30): the page can be narrowed to one course and one learner
 * (?courseid=&userid=, which the completion_reset event's URL already sent), marks
 * a row that came from the BizLMS import ("Legacy") and a reset time that was worked
 * out rather than read from the log ("~"), says "self" when the learner reset their
 * own completion, and, once the evidence view is switched on, links each reset to
 * what it deleted.
 *
 * @package local_sentientia_recompletion
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$component = 'local_sentientia_recompletion';
$ctx = context_system::instance();
$page = optional_param('p', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$filters = array_filter(['courseid' => $courseid, 'userid' => $userid]);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_recompletion/history.php', $filters));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('history_title', $component));
$PAGE->set_heading(get_string('history_title', $component));
require_capability('local/sentientia_recompletion:view', $ctx);

$perpage = 50;

// ADR-031: :view says WHAT, not WHERE. Cross-tenant callers see every row
// (the LEFT JOIN keeps redacted userid-0 rows); a scoped caller only rows
// about their own tenant's users; a caller with no tenant nothing. Until
// 2026-09-25 every tenant admin saw every tenant's reset history with names
// and emails.
if (\local_sentientia_platform\tenant::is_cross_tenant()) {
    $userjoin = 'LEFT JOIN {user} u ON u.id = h.userid';
    $usersql = '1=1';
    $userparams = [];
} else {
    $userjoin = 'JOIN {user} u ON u.id = h.userid';
    [$usersql, $userparams] = \local_sentientia_recompletion\rule_access::history_user_filter('u');
}
// The narrowing is applied on top of the tenant filter, never instead of it.
if ($courseid > 0) {
    $usersql .= ' AND h.courseid = :hfcourse';
    $userparams['hfcourse'] = $courseid;
}
if ($userid > 0) {
    $usersql .= ' AND h.userid = :hfuser';
    $userparams['hfuser'] = $userid;
}
$total = (int) $DB->count_records_sql(
    "SELECT COUNT(1)
       FROM {local_sentientia_recompletion_history} h
       $userjoin
      WHERE $usersql", $userparams);
// B8 fix: LIMIT $perpage OFFSET ... was interpolated into the SQL string.
// $perpage is a constant 50 here but the pattern is dangerous (one
// refactor away from accepting user input). Use the 5th/6th args of
// get_records_sql() for limitfrom + limitnum.
$rows = $DB->get_records_sql(
    "SELECT h.*, u.firstname, u.lastname, u.email, u.deleted AS user_deleted,
            c.fullname AS course_name
       FROM {local_sentientia_recompletion_history} h
       $userjoin
  LEFT JOIN {course} c ON c.id = h.courseid
      WHERE $usersql
      ORDER BY h.timecreated DESC, h.id DESC",
    $userparams, (int) ($page * $perpage), (int) $perpage);

$evidenceon = \local_sentientia_recompletion\evidence_report::enabled();
$shape = [];
foreach ($rows as $r) {
    $inferred = (int) $r->time_inferred === 1;
    $selfreset = $r->userid > 0 && $r->reset_by_userid !== null && (int) $r->reset_by_userid === (int) $r->userid;
    $shape[] = [
        'id'        => (int) $r->id,
        'user_name' => $r->userid > 0
            ? trim(($r->firstname ?? '') . ' ' . ($r->lastname ?? ''))
            : get_string('evidence_redacted', $component),
        'user_email' => (string) ($r->email ?? ''),
        // ADR-032: the import keeps the history of users who have since been deleted; say so.
        'user_deleted' => !empty($r->user_deleted),
        'course_name' => $r->course_name !== null
            ? \local_sentientia_recompletion\evidence_report::plain_name($r->course_name)
            : get_string('evidence_course_gone', $component),
        'reason'    => $r->reason,
        'self'      => $selfreset,
        'legacy'    => $r->source === 'legacy',
        'inferred'  => $inferred,
        'reset_at'  => ($inferred ? '~ ' : '') . userdate($r->timecreated, '%d %b %Y %H:%M'),
        'previous'  => $r->previous_timecompleted
            ? userdate($r->previous_timecompleted, '%d %b %Y') : '—',
        'dryrun'    => (bool) $r->dryrun,
        'grades'    => (bool) $r->reset_grades,
        'attempts'  => (bool) $r->reset_attempts,
        'evidence_url' => $evidenceon
            ? (new moodle_url('/local/sentientia_recompletion/history_detail.php', ['id' => $r->id]))->out(false) : '',
    ];
}

$data = [
    'rows'     => $shape,
    'has_rows' => !empty($shape),
    'total'    => $total,
    'has_prev' => $page > 0,
    'has_next' => ($page + 1) * $perpage < $total,
    'prev_url' => (new moodle_url('/local/sentientia_recompletion/history.php',
        $filters + ['p' => max(0, $page - 1)]))->out(false),
    'next_url' => (new moodle_url('/local/sentientia_recompletion/history.php',
        $filters + ['p' => $page + 1]))->out(false),
    'rules_url' => (new moodle_url('/local/sentientia_recompletion/index.php'))->out(false),
    'filtered'  => !empty($filters),
    'clear_url' => (new moodle_url('/local/sentientia_recompletion/history.php'))->out(false),
    'show_evidence' => $evidenceon,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_recompletion/history', $data);
echo $OUTPUT->footer();
