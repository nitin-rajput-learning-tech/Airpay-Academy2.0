<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Single attempt detail — events timeline, identity result, recording links.
 */

require_once(__DIR__ . '/../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$id = required_param('id', PARAM_INT);
$ctx = context_system::instance();
$PAGE->set_context($ctx);
$PAGE->set_url(new moodle_url('/local/sentientia_proctoring/attempt.php', ['id' => $id]));
$PAGE->set_pagelayout('admin');
$PAGE->set_title('Proctored attempt #' . $id);
$PAGE->set_heading('Proctored attempt #' . $id);
require_capability('local/sentientia_proctoring:viewattempts', $ctx);

$session = $DB->get_record('local_sentientia_proctor_sessions',
    ['id' => $id], '*', MUST_EXIST);
// ── B2 fix: tenant equality on attempt detail page ────────────────────
\local_sentientia_platform\tenant::require_access((int) $session->costcenterid);

$events = $DB->get_records('local_sentientia_proctor_events',
    ['sessionid' => $session->id], 'timecreated ASC');
$recordings = $DB->get_records('local_sentientia_proctor_recordings',
    ['sessionid' => $session->id], 'chunk_idx ASC');
$identity = $session->identity_id
    ? $DB->get_record('local_sentientia_proctor_identity', ['id' => $session->identity_id])
    : null;
$user = $DB->get_record('user', ['id' => $session->userid], 'firstname, lastname, email');
$quiz = $DB->get_record('quiz', ['id' => $session->quizid], 'name');

$can_review = has_capability('local/sentientia_proctoring:review', $ctx);

$data = [
    'sessionid'      => (int) $session->id,
    'status'         => $session->status,
    'risk_score'     => number_format((float) ($session->risk_score ?? 0), 1),
    'auto_decision'  => (string) ($session->auto_decision ?? '—'),
    'human_decision' => (string) ($session->human_decision ?? '—'),
    'user_name'      => trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')),
    'user_email'     => (string) ($user->email ?? ''),
    'quiz_name'      => format_string($quiz->name ?? ''),
    'started_at'     => $session->timestarted ? userdate($session->timestarted) : '—',
    'finished_at'    => $session->timefinished ? userdate($session->timefinished) : '—',
    'identity_done'  => !empty($identity),
    'identity'       => $identity ? [
        'provider'    => $identity->provider,
        'match_score' => number_format((float) $identity->match_score, 1),
        'passed'      => (bool) $identity->passed,
    ] : null,
    'events' => array_map(fn($e) => [
        'time'       => userdate($e->timecreated, '%H:%M:%S'),
        'event_type' => $e->event_type,
        'severity'   => $e->severity,
        'severity_class' => match ($e->severity) {
            'critical' => 'badge bg-danger',
            'warn'     => 'badge bg-warning',
            default    => 'badge bg-secondary',
        },
    ], array_values($events)),
    'events_count'    => count($events),
    'recordings_count' => count($recordings),
    'can_review'      => $can_review && in_array($session->status, ['flagged', 'finished']),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sentientia_proctoring/attempt_detail', $data);
echo $OUTPUT->footer();
