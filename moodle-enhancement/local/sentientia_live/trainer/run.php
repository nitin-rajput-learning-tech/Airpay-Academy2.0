<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Live session runner — Phase E.1.i (minimal placeholder).
 *
 * For a session in 'live' state, shows the current slide + audience
 * count + advance/back buttons. The real-time projector + SSE wiring
 * lands in Phase E.3 — this placeholder shows session info + the
 * current slide title so trainers can verify the session is actually
 * running, and offers a one-click "End session" button.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:run', $context);

if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id = required_param('id', PARAM_INT);

$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');

$sess = \local_sentientia_live\session_manager::get($id);
if (!$sess) {
    redirect($dashboard,
        get_string('invalidsession', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}
if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $id)) {
    redirect($dashboard,
        get_string('cannot_run_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

if ($sess->state !== \local_sentientia_live\session_manager::STATE_LIVE) {
    redirect(new \moodle_url('/local/sentientia_live/trainer/edit.php',
        ['id' => $id]),
        get_string('session_not_live_for_run', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_WARNING);
}

$PAGE->set_url('/local/sentientia_live/trainer/run.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('run_session_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(format_string($sess->title));

$audience_count = \local_sentientia_live\participant_manager::active_count_for_session($id);
$slides         = \local_sentientia_live\slide_manager::list_for_session($id);
$current_slide  = null;
if ($sess->current_slide_id) {
    $current_slide = \local_sentientia_live\slide_manager::get((int) $sess->current_slide_id);
}

$code_pretty = substr($sess->code, 0, 3) . ' ' . substr($sess->code, 3);

// Phase E.3.c — Wire the trainer SSE client so audience-count and
// response-count update in place without a full page reload.
$realtime_on = true;
if (class_exists('\\local_airpay_core\\feature_flags')) {
    try {
        $realtime_on = \local_airpay_core\feature_flags::is_enabled(
            'live.realtime.enabled');
    } catch (\Throwable $e) {
        $realtime_on = true;
    }
}
if ($realtime_on) {
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/trainer_sse', 'init',
        [['sessionid' => $id]]);
}

echo $OUTPUT->header();

// ── Big join code ──
echo \html_writer::start_div('text-center my-4');
echo \html_writer::tag('div',
    get_string('audience_join_at', 'local_sentientia_live'),
    ['class' => 'text-muted']);
echo \html_writer::tag('div',
    s($code_pretty),
    ['class' => 'display-2 fw-bold text-primary my-2',
     'style' => 'font-family: monospace; letter-spacing: 0.1em;']);
echo \html_writer::tag('div',
    get_string('audience_join_url_hint', 'local_sentientia_live',
        (new \moodle_url('/local/sentientia_live/audience/join.php'))->out(false)),
    ['class' => 'text-muted small']);
echo \html_writer::end_div();

// ── Audience counter (live-updated by trainer_sse module) ──
echo \html_writer::start_div('alert alert-info d-flex justify-content-between align-items-center');
echo \html_writer::tag('div',
    '<strong>' . get_string('audience_count_label', 'local_sentientia_live')
    . ':</strong> <span id="sentientia-audience-count" class="fs-4 ms-2">'
    . (int) $audience_count . '</span> '
    . get_string('audience_online', 'local_sentientia_live'));
echo \html_writer::tag('div',
    '<small class="text-muted">' . get_string('total_slides_label',
        'local_sentientia_live', count($slides)) . '</small>');
echo \html_writer::end_div();

// ── Response counter for the current slide (live-updated) ──
if ($sess->current_slide_id) {
    $response_count = \local_sentientia_live\response_recorder::count_for_slide(
        (int) $sess->current_slide_id);
    echo \html_writer::start_div('alert alert-secondary d-flex justify-content-between align-items-center');
    echo \html_writer::tag('div',
        '<strong>' . get_string('response_count_label',
            'local_sentientia_live') . ':</strong> '
        . '<span id="sentientia-response-count" class="fs-4 ms-2">'
        . (int) $response_count . '</span>');
    echo \html_writer::end_div();
}

// ── Current slide ──
echo \html_writer::tag('h3',
    get_string('current_slide_heading', 'local_sentientia_live'),
    ['class' => 'h5 mt-4']);

if ($current_slide) {
    echo \html_writer::start_div('card p-4 my-3');
    echo \html_writer::tag('div',
        get_string('slide_position_of', 'local_sentientia_live', (object) [
            'pos' => (int) $current_slide->position,
            'total' => count($slides),
        ]),
        ['class' => 'text-muted small']);
    echo \html_writer::tag('h4',
        format_string($current_slide->title),
        ['class' => 'h4 my-3']);
    echo \html_writer::tag('span',
        s($current_slide->type),
        ['class' => 'badge bg-secondary']);
    echo \html_writer::end_div();
} else {
    echo \html_writer::tag('div',
        get_string('no_current_slide', 'local_sentientia_live'),
        ['class' => 'alert alert-warning']);
}

// ── Live runner UI pending Phase E.3 ──
echo \html_writer::start_div('alert alert-info mt-4');
echo '<strong>' . get_string('live_runner_pending_title',
    'local_sentientia_live') . '</strong><br>';
echo get_string('live_runner_pending_body', 'local_sentientia_live');
echo \html_writer::end_div();

// ── End session button ──
$end_url = new \moodle_url('/local/sentientia_live/trainer/end.php',
    ['id' => $id, 'sesskey' => sesskey()]);
echo \html_writer::start_div('text-center mt-4');
echo \html_writer::link($end_url->out(false),
    get_string('action_end_session', 'local_sentientia_live'),
    ['class' => 'btn btn-outline-warning',
     'onclick' => "return confirm('" .
       addslashes(get_string('confirm_end_session', 'local_sentientia_live'))
       . "');"]);
echo \html_writer::end_div();

echo $OUTPUT->footer();
