<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Quiz — Generate page (Phase G.0 MVP).
 *
 * Course Author flow:
 *   GET  : show the form (course picker + title + source paste + count)
 *   POST : validate -> [CONFIRM] gate -> create pending draft ->
 *          call Anthropic (or mock) -> parse -> persist questions ->
 *          redirect to review.php?draftid=N
 *
 * All four gates are checked before any API call goes out:
 *   1. sentientia.aiquiz.enabled feature flag = ON
 *   2. Capability local/sentientia_aiquiz:generate
 *   3. Per-user daily token cap not exceeded
 *   4. The confirm checkbox in the form is ticked (the [CONFIRM] gate)
 *
 * The actual Anthropic call is dispatched via
 * anthropic_client::generate() which inspects sentientia.aiquiz.live_api
 * to decide between the mock client and the real curl call. Both paths
 * exercise the same parser + persistence — so mock-mode demos prove the
 * end-to-end wiring without spending money.
 *
 * @package local_sentientia_aiquiz
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_aiquiz\anthropic_client;
use local_sentientia_aiquiz\draft_manager;
use local_sentientia_aiquiz\prompt_builder;
use local_sentientia_aiquiz\response_parser;

require_login();
$context = context_system::instance();

// Gate 1 — feature flag.
if (class_exists('\\local_airpay_core\\feature_flags')
        && !\local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_aiquiz');
}

// Gate 2 — capability.
require_capability('local/sentientia_aiquiz:generate', $context);

$PAGE->set_url('/local/sentientia_aiquiz/generate.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('generate_page_title', 'local_sentientia_aiquiz'));
$PAGE->set_heading(get_string('generate_page_heading', 'local_sentientia_aiquiz'));

$maxquestions = (int)get_config('local_sentientia_aiquiz', 'max_questions');
if ($maxquestions <= 0) {
    $maxquestions = 10;
}
$maxquestions = min($maxquestions, prompt_builder::MAX_QUESTIONS);
$maxsourcewords = (int)get_config('local_sentientia_aiquiz', 'max_source_words');
if ($maxsourcewords <= 0) {
    $maxsourcewords = 4000;
}
$dailycap = (int)get_config('local_sentientia_aiquiz', 'daily_token_cap');
if ($dailycap <= 0) {
    $dailycap = 500000;
}
$defaultmodel = (string)get_config('local_sentientia_aiquiz', 'default_model');
if ($defaultmodel === '') {
    $defaultmodel = anthropic_client::DEFAULT_MODEL;
}

$errors = [];
$prefill = [
    'title'      => '',
    'courseid'   => 0,
    'sourcetext' => '',
    'num'        => min(10, $maxquestions),
    'model'      => $defaultmodel,
    'confirm'    => 0,
];

// ──────────────────────────────────────────────────────────────────
// POST handling
// ──────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $prefill['title']      = trim(optional_param('title', '', PARAM_TEXT));
    $prefill['courseid']   = optional_param('courseid', 0, PARAM_INT);
    $prefill['sourcetext'] = optional_param('sourcetext', '', PARAM_RAW);
    $prefill['num']        = optional_param('num', $prefill['num'], PARAM_INT);
    $prefill['model']      = trim(optional_param('model', $defaultmodel, PARAM_TEXT));
    $prefill['confirm']    = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

    if ($prefill['title'] === '') {
        $prefill['title'] = 'Draft ' . userdate(time(), '%Y-%m-%d %H:%M');
    }

    // Validate source text.
    $sourceerrors = prompt_builder::validate_source($prefill['sourcetext'], $maxsourcewords);
    foreach ($sourceerrors as $key) {
        if ($key === 'err_source_too_long') {
            $errors[] = get_string($key, 'local_sentientia_aiquiz') .
                ' (' . get_string('source_word_count', 'local_sentientia_aiquiz',
                    prompt_builder::word_count($prefill['sourcetext'])) . ')';
        } else {
            $errors[] = get_string($key, 'local_sentientia_aiquiz');
        }
    }

    // Validate question count.
    if ($prefill['num'] < prompt_builder::MIN_QUESTIONS || $prefill['num'] > $maxquestions) {
        $errors[] = get_string('err_invalid_count', 'local_sentientia_aiquiz', (object)[
            'min' => prompt_builder::MIN_QUESTIONS,
            'max' => $maxquestions,
        ]);
    }

    // Validate confirm checkbox — the [CONFIRM] gate.
    if (!$prefill['confirm']) {
        $errors[] = get_string('err_confirm_required', 'local_sentientia_aiquiz');
    }

    // Daily token cap.
    $tokensused = draft_manager::tokens_used_today((int)$USER->id);
    if ($tokensused >= $dailycap) {
        $errors[] = get_string('err_token_cap_reached', 'local_sentientia_aiquiz', (object)[
            'used' => $tokensused,
            'cap'  => $dailycap,
        ]);
    }

    if (empty($errors)) {
        // Persist the pending draft FIRST so a crash mid-call leaves an audit trail.
        $draftid = draft_manager::create_pending(
            (int)$USER->id,
            $prefill['courseid'],
            $prefill['title'],
            $prefill['sourcetext'],
            $prefill['model'],
            $prefill['num']
        );

        // Dispatch to mock or live based on sentientia.aiquiz.live_api flag.
        $result = anthropic_client::generate($prefill['sourcetext'], $prefill['num'], $prefill['model']);

        if ($result['mode'] === 'failed') {
            draft_manager::mark_failed($draftid, (string)$result['error']);
            redirect(new moodle_url('/local/sentientia_aiquiz/review.php', ['draftid' => $draftid]),
                get_string('err_api_failed', 'local_sentientia_aiquiz', s($result['error'])),
                null,
                \core\output\notification::NOTIFY_ERROR);
        }

        // Parse + persist.
        $questions = response_parser::parse($result['body']);
        draft_manager::persist_questions(
            $draftid,
            $questions,
            (int)$result['tokens_in'],
            (int)$result['tokens_out'],
            $result['mode']
        );

        redirect(new moodle_url('/local/sentientia_aiquiz/review.php', ['draftid' => $draftid]));
    }
}

// ──────────────────────────────────────────────────────────────────
// Mode badge — which way will this submission be routed?
// ──────────────────────────────────────────────────────────────────
$badge = null;
if (!class_exists('\\local_airpay_core\\feature_flags')) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_disabled_badge', 'local_sentientia_aiquiz')];
} else if (!\local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
    $badge = ['class' => 'alert-danger', 'text' => get_string('mode_disabled_badge', 'local_sentientia_aiquiz')];
} else if (!\local_airpay_core\feature_flags::is_enabled('sentientia.aiquiz.live_api')) {
    $badge = ['class' => 'alert-info', 'text' => get_string('mode_mock_badge', 'local_sentientia_aiquiz')];
} else {
    $apikey = get_config('local_sentientia_aiquiz', 'api_key');
    if (empty($apikey)) {
        $badge = ['class' => 'alert-warning', 'text' => get_string('mode_no_apikey_badge', 'local_sentientia_aiquiz')];
    } else {
        $badge = ['class' => 'alert-success', 'text' => get_string('mode_live_badge', 'local_sentientia_aiquiz')];
    }
}

// Course picker — courses the user can manage.
$courses = $DB->get_records_select('course',
    'visible = 1 AND id > 1', null, 'fullname ASC', 'id, fullname, shortname', 0, 200);
$courseoptions = [0 => get_string('generate_form_course_none', 'local_sentientia_aiquiz')];
foreach ($courses as $c) {
    $courseoptions[(int)$c->id] = format_string($c->fullname) . ' (' . format_string($c->shortname) . ')';
}

$tokensusedtoday = draft_manager::tokens_used_today((int)$USER->id);

// ──────────────────────────────────────────────────────────────────
// Render
// ──────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generate_page_heading', 'local_sentientia_aiquiz'));

if ($badge) {
    echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);
}

echo html_writer::div(get_string('generate_intro', 'local_sentientia_aiquiz'), 'mb-3 text-muted');

echo html_writer::div(
    get_string('tokens_used_today', 'local_sentientia_aiquiz', (object)[
        'used' => $tokensusedtoday,
        'cap'  => $dailycap,
    ]),
    'small text-muted mb-3'
);

if (!empty($errors)) {
    $list = '';
    foreach ($errors as $e) {
        $list .= html_writer::tag('li', s($e));
    }
    echo html_writer::div(
        html_writer::tag('strong', get_string('error')) . html_writer::tag('ul', $list),
        'alert alert-danger', ['role' => 'alert']);
}

$formstart = html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class'  => 'mform sentientia-aiquiz-generate-form',
]);
echo $formstart;
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Title.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label',
    get_string('generate_form_title', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'sentientia-aiquiz-title',
    'name' => 'title',
    'class' => 'form-control',
    'value' => s($prefill['title']),
    'maxlength' => 200,
    'required' => 'required',
]);
echo html_writer::div(get_string('generate_form_title_help', 'local_sentientia_aiquiz'),
    'form-text text-muted');
echo html_writer::end_div();

// Course picker.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label',
    get_string('generate_form_course', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-courseid', 'class' => 'form-label']);
echo html_writer::select($courseoptions, 'courseid', (int)$prefill['courseid'], false, [
    'id' => 'sentientia-aiquiz-courseid',
    'class' => 'form-control',
]);
echo html_writer::div(get_string('generate_form_course_help', 'local_sentientia_aiquiz'),
    'form-text text-muted');
echo html_writer::end_div();

// Source content.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label',
    get_string('generate_form_source', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-source', 'class' => 'form-label']);
echo html_writer::tag('textarea',
    s($prefill['sourcetext']), [
    'id' => 'sentientia-aiquiz-source',
    'name' => 'sourcetext',
    'class' => 'form-control',
    'rows' => 14,
    'required' => 'required',
]);
echo html_writer::div(
    get_string('generate_form_source_help', 'local_sentientia_aiquiz', $maxsourcewords),
    'form-text text-muted');
echo html_writer::end_div();

// Number of questions.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label',
    get_string('generate_form_num', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-num', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'id' => 'sentientia-aiquiz-num',
    'name' => 'num',
    'class' => 'form-control',
    'value' => (int)$prefill['num'],
    'min' => prompt_builder::MIN_QUESTIONS,
    'max' => $maxquestions,
    'required' => 'required',
]);
echo html_writer::div(
    get_string('generate_form_num_help', 'local_sentientia_aiquiz', $maxquestions),
    'form-text text-muted');
echo html_writer::end_div();

// Model.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label',
    get_string('generate_form_model', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-model', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'id' => 'sentientia-aiquiz-model',
    'name' => 'model',
    'class' => 'form-control',
    'value' => s($prefill['model']),
]);
echo html_writer::div(get_string('generate_form_model_help', 'local_sentientia_aiquiz'),
    'form-text text-muted');
echo html_writer::end_div();

// Confirm checkbox.
echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'id' => 'sentientia-aiquiz-confirm',
    'name' => 'confirm',
    'class' => 'form-check-input',
    'value' => '1',
] + ($prefill['confirm'] ? ['checked' => 'checked'] : []));
echo html_writer::tag('label',
    get_string('generate_confirm_label', 'local_sentientia_aiquiz'),
    ['for' => 'sentientia-aiquiz-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('generate_confirm_help', 'local_sentientia_aiquiz'),
    'form-text');
echo html_writer::end_div();

// Buttons.
echo html_writer::div(
    html_writer::tag('button',
        get_string('generate_submit', 'local_sentientia_aiquiz'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2']) .
    html_writer::link(
        new moodle_url('/'),
        get_string('generate_cancel', 'local_sentientia_aiquiz'),
        ['class' => 'btn btn-secondary']),
    'mb-3');

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
