<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Audience play page — Phase E.2.c.
 *
 * Shows the current slide of a live session + a type-appropriate
 * response form. Audience submits → response_recorder writes → page
 * re-renders with a "thanks, waiting for next slide" panel.
 *
 * URL params:
 *   sessionid  required
 *   token      required for anonymous users (bearer auth)
 *
 * Until Phase E.3 ships the SSE realtime endpoint, this page uses
 * a meta-refresh tag (every 10 seconds) so audiences see the new
 * slide when the trainer advances. Light enough — at ~100 attendees
 * that's 10 hits/sec, well within MariaDB capacity.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$sessionid = required_param('sessionid', PARAM_INT);
$token     = optional_param('token', '', PARAM_ALPHANUMEXT);

// Resolve the participant — either via logged-in user or via bearer token.
$participant = null;
if (!isloggedin() || isguestuser()) {
    if ($token === '') {
        redirect(new \moodle_url('/local/sentientia_live/audience/join.php'),
            get_string('audience_must_join_first', 'local_sentientia_live'),
            null, \core\output\notification::NOTIFY_WARNING);
    }
    $participant = \local_sentientia_live\participant_manager::lookup_by_join_token($token);
    if (!$participant) {
        redirect(new \moodle_url('/local/sentientia_live/audience/join.php'),
            get_string('audience_token_invalid', 'local_sentientia_live'),
            null, \core\output\notification::NOTIFY_ERROR);
    }
} else {
    global $DB;
    $participant = $DB->get_record('local_sentientia_live_participants', [
        'sessionid' => $sessionid,
        'userid'    => $USER->id,
    ]);
    if (!$participant) {
        // Not yet joined — bounce back to join page with code prefilled.
        $sess = \local_sentientia_live\session_manager::get($sessionid);
        $code = $sess ? $sess->code : '';
        redirect(new \moodle_url('/local/sentientia_live/audience/join.php',
            ['code' => $code]),
            get_string('audience_must_join_first', 'local_sentientia_live'),
            null, \core\output\notification::NOTIFY_INFO);
    }
    $participant->id = (int) $participant->id;
}

// Sanity — participant belongs to the requested session.
if ((int) $participant->sessionid !== $sessionid) {
    redirect(new \moodle_url('/local/sentientia_live/audience/join.php'),
        get_string('audience_token_invalid', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$sess = \local_sentientia_live\session_manager::get($sessionid);
if (!$sess) {
    throw new \moodle_exception('invalidsession', 'local_sentientia_live');
}

// Heartbeat so trainer's audience-count stays fresh.
\local_sentientia_live\participant_manager::heartbeat((int) $participant->id);

$PAGE->set_url('/local/sentientia_live/audience/play.php',
    array_filter(['sessionid' => $sessionid, 'token' => $token]));
$PAGE->set_context(\context_system::instance());
$PAGE->set_pagelayout('login');   // minimal chrome
$PAGE->set_title(format_string($sess->title));
$PAGE->set_heading(format_string($sess->title));

// Phase E.3 — SSE realtime. Loads the audience_sse AMD module which
// opens an EventSource against /local/sentientia_live/stream.php. The
// module falls back to meta-refresh polling if the server has SSE
// disabled OR the browser is missing EventSource OR the connection
// closes cleanly.
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
    $sse_opts = [
        'sessionid' => $sessionid,
        'token'     => $token,
    ];
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/audience_sse', 'init', [$sse_opts]);
    // Phase E.5 — chart updater listens for the response_added
    // CustomEvent that audience_sse dispatches, mutates bar widths +
    // counts in place without a page reload.
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/chart_updater', 'init');
    // Phase E.5 — wordcloud updater handles the wordcloud-type panel
    // (re-renders the cloud's font-size buckets in place when new
    // responses arrive). Loads cheap, so we attach unconditionally;
    // it's a no-op on non-wordcloud slides.
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/wordcloud_loader', 'init');
    $PAGE->requires->js_call_amd(
        'local_sentientia_live/wordcloud_updater', 'init');
} else {
    // Polling fallback when realtime is disabled site-wide.
    $PAGE->requires->js_amd_inline(
        "setTimeout(function() { window.location.reload(); }, 10000);");
}

// Tag the body with the current slide ID so the SSE init can detect
// page-load drift (user lingered while trainer advanced).
if (!empty($sess->current_slide_id)) {
    $PAGE->add_body_class('sentientia-live-audience');
    $PAGE->requires->js_amd_inline(
        "document.body.dataset.currentSlideId = '"
        . (int) $sess->current_slide_id . "';");
}

// ── Handle response submission ──
$response_error = null;
$response_saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $slideid_submit = required_param('slideid', PARAM_INT);
    $current_slide = \local_sentientia_live\slide_manager::get($slideid_submit);

    if (!$current_slide || (int) $current_slide->sessionid !== $sessionid) {
        $response_error = get_string('response_slide_mismatch',
            'local_sentientia_live');
    } else if ($current_slide->type === 'multichoice') {
        // Phase E.4 — persist multichoice through the matured question
        // type so its server-authoritative bounds check + delegation to
        // response_recorder::submit() own the write path.
        try {
            $qtype = \local_sentientia_live\question_types\question_type_registry::get_by_slug('multichoice');
            $qtype->persist_response((int) ($USER->id ?? 0), [
                'option_index'  => optional_param('value_int', null, PARAM_INT),
                'slideid'       => $slideid_submit,
                'participantid' => (int) $participant->id,
            ]);
            $response_saved = true;
        } catch (\moodle_exception $e) {
            $response_error = $e->getMessage();
        }
    } else {
        $value_int = null;
        $value_text = null;

        switch ($current_slide->type) {
            case 'quiz':
            case 'rating':
                $value_int = optional_param('value_int', null, PARAM_INT);
                break;
            case 'wordcloud':
            case 'openended':
                $value_text = optional_param('value_text', null, PARAM_TEXT);
                break;
            case 'ranking':
                $value_text = optional_param('value_text', null, PARAM_RAW);
                break;
        }

        try {
            if ($current_slide->type === 'wordcloud') {
                // Phase E.5 — route through the question-type so the
                // submission gets tokenised, profanity-filtered, and
                // appended to the participant's word list (respecting
                // max_responses_per_user from settings).
                $qtype = \local_sentientia_live\question_types\question_type_registry::get_by_slug('wordcloud');
                if ($qtype === null) {
                    throw new \moodle_exception('invalidslidetype',
                        'local_sentientia_live', '', 'wordcloud');
                }
                $qtype->persist_response((int) $participant->id, [
                    'slideid'    => $slideid_submit,
                    'value_text' => $value_text,
                ]);
            } else {
                \local_sentientia_live\response_recorder::submit(
                    $slideid_submit,
                    (int) $participant->id,
                    $value_int,
                    $value_text
                );
            }
            $response_saved = true;
        } catch (\moodle_exception $e) {
            $response_error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();

// ── Session-ended state ──
// P0 #8 — aria-live="assertive" so SR users get the urgent "session
// over" announcement the moment this state renders (typically right
// after audience_sse fires session_ended and we reload).
if ($sess->state === \local_sentientia_live\session_manager::STATE_ENDED) {
    echo \html_writer::start_tag('div', [
        'class' => 'text-center my-5',
        'role' => 'status',
        'aria-live' => 'assertive',
        'aria-atomic' => 'true',
        'aria-label' => get_string('a11y_session_ended_announce',
            'local_sentientia_live'),
    ]);
    echo \html_writer::tag('h2',
        get_string('audience_session_ended_heading', 'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('audience_session_ended_body', 'local_sentientia_live'),
        ['class' => 'text-muted']);
    echo \html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

// ── No current slide yet ──
// P0 #8 — aria-live="polite" — non-urgent waiting state, SR can read
// it after current speech finishes.
if (!$sess->current_slide_id) {
    echo \html_writer::start_tag('div', [
        'class' => 'text-center my-5',
        'role' => 'status',
        'aria-live' => 'polite',
        'aria-label' => get_string('a11y_waiting_for_question',
            'local_sentientia_live'),
    ]);
    echo \html_writer::tag('h2',
        get_string('audience_waiting_heading', 'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('audience_waiting_body', 'local_sentientia_live'),
        ['class' => 'text-muted']);
    echo \html_writer::tag('div',
        '<i class="fa fa-spinner fa-spin fa-2x text-muted" aria-hidden="true"></i>',
        ['class' => 'mt-4']);
    echo \html_writer::end_tag('div');
    echo $OUTPUT->footer();
    exit;
}

$current_slide = \local_sentientia_live\slide_manager::get(
    (int) $sess->current_slide_id);
if (!$current_slide) {
    echo \html_writer::tag('p',
        get_string('audience_current_slide_gone', 'local_sentientia_live'),
        ['class' => 'alert alert-warning']);
    echo $OUTPUT->footer();
    exit;
}

// ── Render the slide ──
// Use start_tag (not start_div) so we can set BOTH class and style —
// start_div's signature is (classes, attributes) — passing a style via
// a third arg is silently dropped.
// P0 #8 — role="region" with aria-label so SR users have an
// addressable landmark for the current question area. slide_changed
// fires a full page reload (per amd/src/audience_sse.js), so we don't
// need aria-live here — the new heading is announced naturally.
echo \html_writer::start_tag('div', [
    'class' => 'sentientia-audience-slide my-4 mx-auto',
    'style' => 'max-width: 720px;',
    'role' => 'region',
    'aria-label' => get_string('a11y_current_question_region',
        'local_sentientia_live'),
]);

// Phase E.5 UX nit — audience progress indicator. Shows "Question 1 of 5"
// so the audience knows where they are in the session.
$total_slides = \local_sentientia_live\slide_manager::count_for_session($sessionid);
echo \html_writer::tag('div',
    get_string('audience_slide_progress', 'local_sentientia_live',
        (object) [
            'pos'   => (int) $current_slide->position,
            'total' => $total_slides,
        ]
    ),
    ['class' => 'text-muted text-center small mb-2']);

echo \html_writer::tag('h2',
    format_text($current_slide->title, FORMAT_PLAIN, ['filter' => false]),
    ['class' => 'mb-4 text-center']);

$has_responded = \local_sentientia_live\response_recorder::has_responded(
    (int) $current_slide->id, (int) $participant->id);
$session_settings = \local_sentientia_live\session_manager::parse_settings($sess);
$show_audience_results = $has_responded
    && !empty($session_settings['show_results_to_audience']);

if ($response_saved) {
    // P0 #8 — aria-live="assertive" so the SR interrupts whatever it
    // was reading to confirm the vote landed. role="status" pairs
    // with aria-live to mark this as a state message.
    echo \html_writer::tag('div',
        '<i class="fa fa-check-circle fa-2x text-success me-2" aria-hidden="true"></i>' .
            get_string('audience_response_saved', 'local_sentientia_live'),
        ['class' => 'alert alert-success text-center',
         'role' => 'status',
         'aria-live' => 'assertive',
         'aria-atomic' => 'true']);
    if ($show_audience_results) {
        // Render the same result panel the trainer sees.
        $panel = new \local_sentientia_live\output\result_panel($current_slide);
        echo $OUTPUT->render_from_template(
            'local_sentientia_live/result_panel',
            $panel->export_for_template($OUTPUT));
    } else {
        echo \html_writer::tag('p',
            get_string('audience_waiting_next', 'local_sentientia_live'),
            ['class' => 'text-muted text-center']);
    }
} elseif ($has_responded) {
    // P0 #8 — already-responded state is informational, not urgent —
    // aria-live="polite". Two render branches keep the existing layout.
    if ($show_audience_results) {
        echo \html_writer::tag('div',
            get_string('audience_already_responded', 'local_sentientia_live'),
            ['class' => 'alert alert-info text-center',
             'role' => 'status',
             'aria-live' => 'polite',
             'aria-atomic' => 'true']);
        $panel = new \local_sentientia_live\output\result_panel($current_slide);
        echo $OUTPUT->render_from_template(
            'local_sentientia_live/result_panel',
            $panel->export_for_template($OUTPUT));
    } else {
        echo \html_writer::tag('div',
            get_string('audience_already_responded', 'local_sentientia_live'),
            ['class' => 'alert alert-info text-center',
             'role' => 'status',
             'aria-live' => 'polite',
             'aria-atomic' => 'true']);
        echo \html_writer::tag('p',
            get_string('audience_waiting_next', 'local_sentientia_live'),
            ['class' => 'text-muted text-center']);
    }
} else {
    if ($response_error) {
        echo \html_writer::tag('div', $response_error,
            ['class' => 'alert alert-danger']);
    }
    // Render type-specific response form.
    render_response_form($current_slide, $sessionid, $token, $participant);
}

echo \html_writer::end_tag('div');
echo $OUTPUT->footer();


/**
 * Render the response form appropriate to the slide's type.
 * Hand-written (not moodleform) so the audience-facing UI is minimal +
 * mobile-friendly. POST goes back to this same play.php URL.
 *
 * Phase E.5 — wordcloud delegates to the question type's render() so the
 * cap-aware UX (remaining-words hint + auto-disable at the cap) ships
 * from one place. Other types keep the hand-rolled branches below.
 */
function render_response_form(\stdClass $slide, int $sessionid,
                                string $token,
                                ?\stdClass $participant = null): void {
    $settings = \local_sentientia_live\slide_manager::parse_settings($slide);

    $action_url = new \moodle_url('/local/sentientia_live/audience/play.php',
        array_filter(['sessionid' => $sessionid, 'token' => $token]));

    // Phase E.4 — render the multichoice audience form through the
    // matured question type. The class template owns the COMPLETE form
    // (its own <form>, hidden sesskey + slideid, options as radio or
    // buttons, and the submit button), so we render it before opening
    // the shared inline <form> below and return early. The POST
    // contract (name="value_int", action_url, sesskey) is identical to
    // the inline path, so the POST handler at the top of this file is
    // unchanged. Other types stay on the inline form until they mature.
    if ($slide->type === 'multichoice') {
        $qtype = \local_sentientia_live\question_types\question_type_registry::get_by_slug('multichoice');
        if ($qtype !== null) {
            echo $qtype->render([
                'slide'          => $slide,
                'settings'       => $settings,
                'session'        => \local_sentientia_live\session_manager::get($sessionid),
                'aria_id_prefix' => 'mc_' . (int) $slide->id,
                'action_url'     => $action_url->out(false),
                'sesskey'        => sesskey(),
                'token'          => $token,
                'show_correct'   => false,
            ]);
            return;
        }
        // Defensive: registry couldn't resolve the type — fall through
        // to the generic inline form so the audience can still respond.
    }
    // Phase E.5 — word cloud owns its own form markup (input + remaining
    // hint + cap-disable) inside word_cloud::render(). Delegate and bail
    // before the generic <form> wrapper so we don't double-wrap.
    if ($slide->type === 'wordcloud') {
        $qtype = \local_sentientia_live\question_types\question_type_registry::get_by_slug('wordcloud');
        if ($qtype !== null) {
            echo $qtype->render([
                'slide'          => $slide,
                'settings'       => $settings,
                'participant'    => $participant,
                'action_url'     => $action_url,
                'sesskey'        => sesskey(),
                'aria_id_prefix' => 'wc_' . (int) $slide->id,
            ]);
            return;
        }
    }

    echo \html_writer::start_tag('form',
        ['method' => 'post', 'action' => $action_url->out(false)]);
    echo \html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo \html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'slideid', 'value' => (int) $slide->id]);

    switch ($slide->type) {
        case 'multichoice':
        case 'quiz':
            foreach (($settings['options'] ?? []) as $i => $opt) {
                echo \html_writer::start_div('form-check mb-2 p-3 border rounded',
                    null, ['style' => 'cursor: pointer;']);
                echo \html_writer::empty_tag('input', [
                    'type'  => 'radio',
                    'name'  => 'value_int',
                    'value' => $i,
                    'class' => 'form-check-input me-2',
                    'id'    => 'opt_' . $i,
                    'required' => 'required',
                ]);
                echo \html_writer::tag('label', s($opt),
                    ['for' => 'opt_' . $i, 'class' => 'form-check-label fs-5']);
                echo \html_writer::end_div();
            }
            break;

        case 'rating':
            $min = (int) ($settings['scale_min'] ?? 1);
            $max = (int) ($settings['scale_max'] ?? 5);
            $labels = $settings['scale_labels'] ?? [];
            echo \html_writer::start_div('d-flex justify-content-between gap-2 my-4 flex-wrap');
            for ($v = $min; $v <= $max; $v++) {
                $label_i = $v - $min;
                $label_text = $labels[$label_i] ?? null;
                echo \html_writer::start_div('text-center flex-grow-1');
                echo \html_writer::empty_tag('input', [
                    'type'  => 'radio',
                    'name'  => 'value_int',
                    'value' => $v,
                    'class' => 'btn-check',
                    'id'    => 'rate_' . $v,
                    'required' => 'required',
                ]);
                echo \html_writer::tag('label',
                    '<span class="fs-3">' . $v . '</span>' .
                    ($label_text ? '<br><span class="small">' . s($label_text) . '</span>' : ''),
                    ['for' => 'rate_' . $v,
                     'class' => 'btn btn-outline-primary w-100 py-3']);
                echo \html_writer::end_div();
            }
            echo \html_writer::end_div();
            break;

        // 'wordcloud' is handled by word_cloud::render() via the early
        // delegation above — no branch here on purpose.

        case 'openended':
            $max_chars = (int) ($settings['max_chars'] ?? 280);
            echo \html_writer::tag('textarea', '',
                ['name' => 'value_text',
                 'class' => 'form-control form-control-lg mb-3',
                 'rows' => 4,
                 'maxlength' => $max_chars,
                 'placeholder' => get_string('openended_response_placeholder',
                    'local_sentientia_live'),
                 'autofocus' => 'autofocus',
                 'required' => 'required']);
            break;

        case 'ranking':
            // Phase E.2 ships a fallback: numbered text inputs (audience
            // types 1, 2, 3, ... beside each item). Phase E.9 will swap
            // for proper drag-and-drop.
            echo \html_writer::tag('p',
                get_string('ranking_response_intro', 'local_sentientia_live'),
                ['class' => 'text-muted small']);
            $items = $settings['items'] ?? [];
            echo \html_writer::start_tag('div',
                ['id' => 'sentientia-ranking-inputs']);
            foreach ($items as $i => $item) {
                echo \html_writer::start_div('input-group mb-2');
                echo \html_writer::empty_tag('input', [
                    'type'  => 'number',
                    'name'  => 'rank_' . $i,
                    'min'   => 1,
                    'max'   => count($items),
                    'class' => 'form-control',
                    'style' => 'max-width: 80px;',
                    'placeholder' => '#',
                    'required' => 'required',
                ]);
                echo \html_writer::tag('span', s($item),
                    ['class' => 'input-group-text flex-grow-1']);
                echo \html_writer::end_div();
            }
            echo \html_writer::end_tag('div');
            // Build value_text JSON client-side from rank_* fields just
            // before submit. Hidden field absorbs the JSON.
            echo \html_writer::empty_tag('input',
                ['type' => 'hidden', 'name' => 'value_text', 'value' => '',
                 'id' => 'sentientia-ranking-json']);
            // Inline JS to assemble JSON on form submit (vanilla, no AMD).
            // Uses Moodle's setTimeout trick to make sure DOM is parsed.
            $PAGE_inline = "
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.querySelector('#sentientia-ranking-inputs').closest('form');
                if (!form) return;
                form.addEventListener('submit', function(e) {
                    var inputs = document.querySelectorAll('input[name^=\"rank_\"]');
                    var byPos = {};
                    inputs.forEach(function(inp) {
                        var idx = parseInt(inp.name.replace('rank_', ''), 10);
                        var pos = parseInt(inp.value, 10);
                        if (!isNaN(pos)) {
                            byPos[pos] = idx;
                        }
                    });
                    var ordered = [];
                    var positions = Object.keys(byPos).map(Number).sort(function(a, b) { return a - b; });
                    positions.forEach(function(p) { ordered.push(byPos[p]); });
                    document.getElementById('sentientia-ranking-json').value = JSON.stringify(ordered);
                });
            });
            ";
            global $PAGE;
            $PAGE->requires->js_init_code($PAGE_inline);
            break;
    }

    echo \html_writer::tag('button',
        get_string('audience_submit_response', 'local_sentientia_live'),
        ['type' => 'submit', 'class' => 'btn btn-primary btn-lg w-100 mt-3']);

    echo \html_writer::end_tag('form');
}
