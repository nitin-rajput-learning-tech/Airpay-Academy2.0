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
    } else {
        $value_int = null;
        $value_text = null;

        switch ($current_slide->type) {
            case 'multichoice':
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
            \local_sentientia_live\response_recorder::submit(
                $slideid_submit,
                (int) $participant->id,
                $value_int,
                $value_text
            );
            $response_saved = true;
        } catch (\moodle_exception $e) {
            $response_error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();

// ── Session-ended state ──
if ($sess->state === \local_sentientia_live\session_manager::STATE_ENDED) {
    echo \html_writer::start_div('text-center my-5');
    echo \html_writer::tag('h2',
        get_string('audience_session_ended_heading', 'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('audience_session_ended_body', 'local_sentientia_live'),
        ['class' => 'text-muted']);
    echo \html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

// ── No current slide yet ──
if (!$sess->current_slide_id) {
    echo \html_writer::start_div('text-center my-5');
    echo \html_writer::tag('h2',
        get_string('audience_waiting_heading', 'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('audience_waiting_body', 'local_sentientia_live'),
        ['class' => 'text-muted']);
    echo \html_writer::tag('div',
        '<i class="fa fa-spinner fa-spin fa-2x text-muted"></i>',
        ['class' => 'mt-4']);
    echo \html_writer::end_div();
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
echo \html_writer::start_div('sentientia-audience-slide my-4 mx-auto',
    null, ['style' => 'max-width: 720px;']);
echo \html_writer::tag('h2',
    format_text($current_slide->title, FORMAT_PLAIN, ['filter' => false]),
    ['class' => 'mb-4 text-center']);

$has_responded = \local_sentientia_live\response_recorder::has_responded(
    (int) $current_slide->id, (int) $participant->id);
$session_settings = \local_sentientia_live\session_manager::parse_settings($sess);
$show_audience_results = $has_responded
    && !empty($session_settings['show_results_to_audience']);

if ($response_saved) {
    echo \html_writer::tag('div',
        '<i class="fa fa-check-circle fa-2x text-success me-2"></i>' .
            get_string('audience_response_saved', 'local_sentientia_live'),
        ['class' => 'alert alert-success text-center']);
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
    if ($show_audience_results) {
        echo \html_writer::tag('div',
            get_string('audience_already_responded', 'local_sentientia_live'),
            ['class' => 'alert alert-info text-center']);
        $panel = new \local_sentientia_live\output\result_panel($current_slide);
        echo $OUTPUT->render_from_template(
            'local_sentientia_live/result_panel',
            $panel->export_for_template($OUTPUT));
    } else {
        echo \html_writer::tag('div',
            get_string('audience_already_responded', 'local_sentientia_live'),
            ['class' => 'alert alert-info text-center']);
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
    render_response_form($current_slide, $sessionid, $token);
}

echo \html_writer::end_div();
echo $OUTPUT->footer();


/**
 * Render the response form appropriate to the slide's type.
 * Hand-written (not moodleform) so the audience-facing UI is minimal +
 * mobile-friendly. POST goes back to this same play.php URL.
 */
function render_response_form(\stdClass $slide, int $sessionid,
                                string $token): void {
    $settings = \local_sentientia_live\slide_manager::parse_settings($slide);

    $action_url = new \moodle_url('/local/sentientia_live/audience/play.php',
        array_filter(['sessionid' => $sessionid, 'token' => $token]));

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

        case 'wordcloud':
            $max_len = (int) ($settings['max_word_length'] ?? 50);
            echo \html_writer::empty_tag('input', [
                'type'      => 'text',
                'name'      => 'value_text',
                'class'     => 'form-control form-control-lg text-center mb-3',
                'maxlength' => $max_len,
                'placeholder' => get_string('wc_response_placeholder',
                    'local_sentientia_live'),
                'autofocus' => 'autofocus',
                'required'  => 'required',
            ]);
            break;

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
