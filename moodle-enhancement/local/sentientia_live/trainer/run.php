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

if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.enabled')) {
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
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    try {
        $realtime_on = \local_sentientia_platform\feature_flags::is_enabled(
            'live.realtime.enabled');
    } catch (\Throwable $e) {
        $realtime_on = true;
    }
}
if ($realtime_on) {
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/trainer_sse', 'init',
        [['sessionid' => $id]]);
    // Phase E.5 — chart updater listens for response_added CustomEvent
    // dispatched by trainer_sse, mutates bar widths + counts in place.
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/chart_updater', 'init');
    // Phase E.5 — wordcloud_updater handles wordcloud-type panels
    // (mutates font-size buckets in place; no innerHTML). loader stays
    // small enough to attach unconditionally.
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/wordcloud_loader', 'init');
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/wordcloud_updater', 'init');
}

echo $OUTPUT->header();

// Phase E.11 — Mobile responsive overrides for the trainer run page.
// Scoped to .sentientia-trainer-runner so it doesn't pollute the rest
// of the page. Tightens display-2 + alert paddings + counter font on
// the 590px breakpoint (airpayux primary mobile bp per frontend.md).
echo '<style>
@media (max-width: 590px) {
  .sentientia-trainer-runner .display-2 { font-size: 3rem !important; letter-spacing: 0.05em !important; }
  .sentientia-trainer-runner .alert { flex-direction: column !important; align-items: flex-start !important; gap: 0.25rem; padding: 0.75rem !important; }
  .sentientia-trainer-runner .alert .fs-4 { font-size: 1.25rem !important; }
  .sentientia-trainer-runner .card.p-4 { padding: 1rem !important; }
}
</style>';
echo \html_writer::start_div('sentientia-trainer-runner');

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
// P0 #8 — role="status" + aria-live="polite" + aria-atomic="true" so
// the count change announced as "Audience: 5 online now" (full
// re-read of the label + number, not just the delta). The aria-label
// gives SR users a recognisable name for this landmark.
echo \html_writer::start_tag('div', [
    'class' => 'alert alert-info d-flex justify-content-between align-items-center',
    'role' => 'status',
    'aria-live' => 'polite',
    'aria-atomic' => 'true',
    'aria-label' => get_string('a11y_audience_count_region',
        'local_sentientia_live'),
]);
echo \html_writer::tag('div',
    '<strong>' . get_string('audience_count_label', 'local_sentientia_live')
    . ':</strong> <span id="sentientia-audience-count" class="fs-4 ms-2">'
    . (int) $audience_count . '</span> '
    . get_string('audience_online', 'local_sentientia_live'));
echo \html_writer::tag('div',
    '<small class="text-muted">' . get_string('total_slides_label',
        'local_sentientia_live', count($slides)) . '</small>');
echo \html_writer::end_tag('div');

// ── Response counter for the current slide (live-updated) ──
// P0 #8 — same aria-live polite + atomic pattern as the audience
// counter; SR announces "Responses received: 12" on each
// response_added SSE event (trainer_sse.js mutates the count span's
// textContent inside this region).
if ($sess->current_slide_id) {
    $response_count = \local_sentientia_live\response_recorder::count_for_slide(
        (int) $sess->current_slide_id);
    echo \html_writer::start_tag('div', [
        'class' => 'alert alert-secondary d-flex justify-content-between align-items-center',
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-atomic' => 'true',
        'aria-label' => get_string('a11y_response_count_region',
            'local_sentientia_live'),
    ]);
    echo \html_writer::tag('div',
        '<strong>' . get_string('response_count_label',
            'local_sentientia_live') . ':</strong> '
        . '<span id="sentientia-response-count" class="fs-4 ms-2">'
        . (int) $response_count . '</span>');
    echo \html_writer::end_tag('div');
}

// ── Current slide ──
echo \html_writer::tag('h3',
    get_string('current_slide_heading', 'local_sentientia_live'),
    ['class' => 'h5 mt-4']);

if ($current_slide) {
    if ($current_slide->type === 'multichoice') {
        // Phase E.4 — the matured multichoice type renders its own
        // result template (qt_multiple_choice_result) with the correct
        // answer marked (show_correct=true — trainer sees the tally
        // first; the audience never sees the correct answer until a
        // reveal). The bar-row DOM matches what chart_updater.js targets,
        // so bars update in place on each SSE response_added event.
        $mc = \local_sentientia_live\question_types\question_type_registry::get_by_slug('multichoice');
        echo $mc->render_result(
            $id, (int) $current_slide->id, /* show_correct */ true);
    } else {
        // Phase E.4 — render the live result panel for the current slide.
        // Phase E.6 — pass show_to_audience=false so quiz leaderboards
        // render for the trainer. Audience-side (audience/play.php) uses
        // the default true and gets the bar chart only.
        $panel = new \local_sentientia_live\output\result_panel(
            $current_slide, /* show_to_audience */ false);
        echo $OUTPUT->render_from_template(
            'local_sentientia_live/result_panel',
            $panel->export_for_template($OUTPUT));
    }

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
    // d-inline-block keeps the type badge fit-to-content + show the
    // localised label (multichoice -> "Multiple choice") not the slug.
    echo \html_writer::tag('span',
        s(get_string('slide_type_' . $current_slide->type,
            'local_sentientia_live')),
        ['class' => 'badge bg-secondary d-inline-block']);
    echo \html_writer::end_div();
} else {
    echo \html_writer::tag('div',
        get_string('no_current_slide', 'local_sentientia_live'),
        ['class' => 'alert alert-warning']);
}

// Phase E.3 / E.4 shipped — SSE projector + result panel + live
// counters all wired via trainer_sse.min.js. Audience count + response
// count update in place (textContent mutation); slide_changed +
// session_ended trigger full reload. Phase E.5+ will add explicit
// advance/back controls + a fullscreen mode.

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

echo \html_writer::end_div();  // .sentientia-trainer-runner

echo $OUTPUT->footer();
