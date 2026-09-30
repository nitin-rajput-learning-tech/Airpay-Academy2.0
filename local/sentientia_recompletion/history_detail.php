<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Recompletion evidence view (ADR-032): one reset, and what it deleted.
 *
 * Behind the default-OFF flag sentientia.recompletion.evidence_view. A reset deletes the learner's live
 * completion rows, so for every cycle before the current one this page is the proof that the person
 * completed it: the course completion, criteria, activity completions, quiz attempts and grades, SCORM
 * tracking, LTI grades and questionnaire answers that the BizLMS plugin archived, or that the engine archived
 * before it deleted them.
 *
 * ?id=<history id>                 the evidence of one reset
 * ?userid=<id>&courseid=<id>       evidence of one learner in one course that is tied to no reset
 *
 * Tenant scope is history.php's: a reader sees a row only when they are cross-tenant or the learner is inside
 * their tenant. A row they may not see is reported as not found.
 *
 * @package local_sentientia_recompletion
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $OUTPUT, $PAGE;

$ctx = context_system::instance();
$component = 'local_sentientia_recompletion';
$id = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_recompletion/history_detail.php',
    array_filter(['id' => $id, 'userid' => $userid, 'courseid' => $courseid])));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('evidence_title', $component));
$PAGE->set_heading(get_string('evidence_title', $component));
require_capability('local/sentientia_recompletion:view', $ctx);

$historyurl = new moodle_url('/local/sentientia_recompletion/history.php');
if (!\local_sentientia_recompletion\evidence_report::enabled()) {
    throw new moodle_exception('evidence_view_off', $component, $historyurl->out(false));
}

if ($id > 0) {
    $history = \local_sentientia_recompletion\evidence_report::visible_history($id);
    if ($history === null) {
        throw new moodle_exception('evidence_not_found', $component, $historyurl->out(false));
    }
    $header = \local_sentientia_recompletion\evidence_report::header($history);
    $sections = \local_sentientia_recompletion\evidence_report::sections_for_history($id);
    $unattached = false;
} else if ($userid > 0 && $courseid > 0) {
    $pair = \local_sentientia_recompletion\evidence_report::visible_pair($userid, $courseid);
    if ($pair === null) {
        throw new moodle_exception('evidence_not_found', $component, $historyurl->out(false));
    }
    $header = [
        'learner' => \local_sentientia_recompletion\evidence_report::learner_name($pair),
        'course' => $pair->coursename !== null ? format_string($pair->coursename)
            : get_string('evidence_course_gone', $component),
    ];
    $sections = \local_sentientia_recompletion\evidence_report::sections_for_pair($userid, $courseid);
    $unattached = true;
} else {
    throw new moodle_exception('evidence_not_found', $component, $historyurl->out(false));
}

$data = [
    'unattached' => $unattached,
    'learner' => $header['learner'],
    'course' => $header['course'],
    'reset_at' => $header['reset_at'] ?? '',
    'inferred' => !empty($header['inferred']),
    'reason' => $header['reason'] ?? '',
    'legacy' => !empty($header['legacy']),
    'reset_by' => $header['reset_by'] ?? '',
    'previous' => $header['previous'] ?? '',
    'grades_reset' => !empty($header['grades_reset']),
    'attempts_reset' => !empty($header['attempts_reset']),
    'sections' => $sections,
    'has_sections' => !empty($sections),
    'history_url' => $historyurl->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_recompletion/history_detail', $data);
echo $OUTPUT->footer();
