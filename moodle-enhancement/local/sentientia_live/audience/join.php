<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Audience join — Phase E.2.b.
 *
 * Two-step funnel:
 *   1. ?code=N (or first-time visit) — enter 6-digit code via form.
 *      We POST/GET code, look up via find_by_code, validate it's a live
 *      session, fall through to step 2.
 *   2. Enter display name — show form, on submit calls join_or_resume
 *      and redirects to play.php?join_token=X (for anonymous) or just
 *      ?sessionid=N (for logged-in users — sessionid is enough).
 *
 * Master flag: live.enabled. No further capability check — open to
 * any logged-in user. Anonymous joins are gated on session settings
 * + global live.allow_anonymous flag (enforced by can_user_join).
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$code = optional_param('code', '', PARAM_TEXT);
$display_name = optional_param('display_name', '', PARAM_TEXT);
$action = optional_param('action', '', PARAM_ALPHA);

$context = \context_system::instance();

// Determine the user identity early.
$userid = (!isloggedin() || isguestuser()) ? null : (int) $USER->id;
$user_default_name = $userid !== null ? fullname($USER) : '';

$PAGE->set_url('/local/sentientia_live/audience/join.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('login');   // minimal chrome — audience-facing
$PAGE->set_title(get_string('audience_join_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(get_string('audience_join_heading', 'local_sentientia_live'));

// ── Try to resolve session from the code, if provided ──
$sess = null;
$code_error = null;
if ($code !== '') {
    $sess = \local_sentientia_live\session_manager::find_by_code($code);
    if (!$sess) {
        $code_error = get_string('audience_invalid_code',
            'local_sentientia_live');
    }
}

// ── Step 2: code is valid, action=join → register participant ──
if ($sess && $action === 'join' && confirm_sesskey()) {
    if (!isloggedin() || isguestuser()) {
        // Anonymous — gate on can_user_join (which checks
        // session->allow_anonymous + live.allow_anonymous flag).
        if (!\local_sentientia_live\session_manager::can_user_join(
                null, (int) $sess->id)) {
            redirect($PAGE->url->out(false),
                get_string('audience_anonymous_not_allowed',
                    'local_sentientia_live'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    } else {
        if (!\local_sentientia_live\session_manager::can_user_join(
                $userid, (int) $sess->id)) {
            redirect($PAGE->url->out(false),
                get_string('audience_cannot_join',
                    'local_sentientia_live'),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    }

    $display = trim($display_name);
    if ($display === '') {
        $display = $user_default_name !== '' ? $user_default_name : '?';
    }

    try {
        $participant = \local_sentientia_live\participant_manager::join_or_resume(
            (int) $sess->id, $userid, $display);
    } catch (\moodle_exception $e) {
        redirect($PAGE->url->out(false), $e->getMessage(),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    // For logged-in users, sessionid alone is enough (server identifies
    // them via $USER). For anonymous, we need the bearer token in URL.
    $play_url = new \moodle_url('/local/sentientia_live/audience/play.php',
        $userid !== null
            ? ['sessionid' => (int) $sess->id]
            : ['sessionid' => (int) $sess->id,
               'token'     => $participant->join_token]
    );
    redirect($play_url);
}

// ── Step 1: render the form ──
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('audience_join_heading', 'local_sentientia_live'));

echo \html_writer::tag('p',
    get_string('audience_join_intro', 'local_sentientia_live'),
    ['class' => 'text-center text-muted mb-4']);

echo \html_writer::start_tag('form',
    ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring(),
     'class' => 'sentientia-live-join mx-auto', 'style' => 'max-width:420px;']);

if ($code_error) {
    echo \html_writer::tag('div', $code_error,
        ['class' => 'alert alert-danger']);
}

// 6-digit code input — big, centered, monospace.
echo \html_writer::start_div('mb-4 text-center');
echo \html_writer::tag('label',
    get_string('audience_code_label', 'local_sentientia_live'),
    ['for' => 'code_input', 'class' => 'form-label fs-5']);
echo \html_writer::empty_tag('input', [
    'type'  => 'text',
    'name'  => 'code',
    'id'    => 'code_input',
    'value' => $code,
    'class' => 'form-control form-control-lg text-center',
    'style' => 'font-family: monospace; font-size: 2.5rem; letter-spacing: 0.3em; max-width: 320px; margin: 0 auto;',
    'pattern'     => '[0-9 \-]{6,10}',
    'maxlength'   => '10',
    'inputmode'   => 'numeric',
    'autocomplete'=> 'one-time-code',
    'autofocus'   => 'autofocus',
    'placeholder' => '123 456',
    'required'    => 'required',
]);
echo \html_writer::end_div();

// If code is good, render the name field + join button. Otherwise just
// the "next" button (which resubmits with the code).
if ($sess) {
    // Session resolved! Show name input.
    echo \html_writer::tag('div',
        get_string('audience_session_found', 'local_sentientia_live',
            format_string($sess->title)),
        ['class' => 'alert alert-success text-center']);

    echo \html_writer::start_div('mb-3');
    echo \html_writer::tag('label',
        get_string('audience_displayname_label', 'local_sentientia_live'),
        ['for' => 'name_input', 'class' => 'form-label']);
    echo \html_writer::empty_tag('input', [
        'type'  => 'text',
        'name'  => 'display_name',
        'id'    => 'name_input',
        'value' => $user_default_name,
        'class' => 'form-control form-control-lg',
        'maxlength' => '80',
        'placeholder' => get_string('audience_displayname_placeholder',
            'local_sentientia_live'),
        'required' => 'required',
    ]);
    echo \html_writer::end_div();

    echo \html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'action', 'value' => 'join']);
    echo \html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo \html_writer::tag('button',
        get_string('audience_join_button', 'local_sentientia_live'),
        ['type' => 'submit', 'class' => 'btn btn-primary btn-lg w-100']);
} else {
    echo \html_writer::tag('button',
        get_string('audience_lookup_code', 'local_sentientia_live'),
        ['type' => 'submit', 'class' => 'btn btn-primary btn-lg w-100']);
}

echo \html_writer::end_tag('form');
echo $OUTPUT->footer();
