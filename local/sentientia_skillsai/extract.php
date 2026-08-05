<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — Extract page (P0.1.0 MVP).
 *
 * L&D author flow:
 *   GET  : show the form (title + source-kind + source paste + language +
 *          prompt preview + [CONFIRM] checkbox).
 *   POST : validate -> [CONFIRM] gate -> create pending job -> call
 *          Anthropic (or mock) -> parse -> persist candidates ->
 *          redirect to review.php?jobid=N
 *
 * Four gates are checked before any API call goes out:
 *   1. sentientia.skillsai.enabled feature flag = ON
 *   2. Capability local/sentientia_skillsai:extract
 *   3. Per-user daily token cap not exceeded
 *   4. The confirm checkbox is ticked (the [CONFIRM] gate)
 *
 * The Anthropic call is dispatched via anthropic_client::extract() which
 * inspects sentientia.skillsai.live_api to pick mock vs live. Both paths
 * exercise the same parser + persistence — mock-mode demos prove the
 * end-to-end wiring without spending money.
 *
 * @package local_sentientia_skillsai
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_skillsai\anthropic_client;
use local_sentientia_skillsai\taxonomy_manager;
use local_sentientia_skillsai\prompt_builder;
use local_sentientia_skillsai\response_parser;

require_login();
$context = context_system::instance();

// Gate 1 — feature flag.
if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_skillsai');
}

// Gate 2 — capability.
require_capability('local/sentientia_skillsai:extract', $context);

$PAGE->set_url('/local/sentientia_skillsai/extract.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('extract_page_title', 'local_sentientia_skillsai'));
$PAGE->set_heading(get_string('extract_page_heading', 'local_sentientia_skillsai'));

$maxskills = (int)get_config('local_sentientia_skillsai', 'max_skills');
if ($maxskills <= 0) {
    $maxskills = 15;
}
$maxskills = min($maxskills, prompt_builder::MAX_SKILLS);
$maxsourcewords = (int)get_config('local_sentientia_skillsai', 'max_source_words');
if ($maxsourcewords <= 0) {
    $maxsourcewords = 6000;
}
$dailycap = (int)get_config('local_sentientia_skillsai', 'daily_token_cap');
if ($dailycap <= 0) {
    $dailycap = 500000;
}
$defaultmodel = (string)get_config('local_sentientia_skillsai', 'default_model');
if ($defaultmodel === '') {
    $defaultmodel = anthropic_client::DEFAULT_MODEL;
}

$currentcustomer = class_exists('\\local_sentientia_platform\\customer')
    ? \local_sentientia_platform\customer::current()
    : 1;

$uilocale = (string)(current_language() ?? 'en');

$errors = [];
$prefill = [
    'title'      => '',
    'courseid'   => 0,
    'sourcekind' => 'manual',
    'sourcetext' => '',
    'language'   => prompt_builder::version_for_locale($uilocale) === prompt_builder::VERSION_V2_HINDI ? 'hi' : 'en',
    'confirm'    => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $prefill['title']      = trim(optional_param('title', '', PARAM_TEXT));
    $prefill['courseid']   = optional_param('courseid', 0, PARAM_INT);
    $prefill['sourcekind'] = optional_param('sourcekind', 'manual', PARAM_ALPHA);
    if (!in_array($prefill['sourcekind'], taxonomy_manager::SOURCE_KINDS, true)) {
        $prefill['sourcekind'] = 'manual';
    }
    $prefill['sourcetext'] = optional_param('sourcetext', '', PARAM_RAW);
    $prefill['language']   = strtolower(trim(optional_param('language', $prefill['language'], PARAM_ALPHA)));
    if (!in_array($prefill['language'], ['en', 'hi'], true)) {
        $prefill['language'] = 'en';
    }
    $prefill['confirm']    = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

    if ($prefill['title'] === '') {
        $prefill['title'] = 'Extraction ' . userdate(time(), '%Y-%m-%d %H:%M');
    }

    // Validate source text.
    foreach (prompt_builder::validate_source($prefill['sourcetext'], $maxsourcewords) as $key) {
        if ($key === 'err_source_too_long') {
            $errors[] = get_string($key, 'local_sentientia_skillsai') .
                ' (' . get_string('source_word_count', 'local_sentientia_skillsai',
                    prompt_builder::word_count($prefill['sourcetext'])) . ')';
        } else {
            $errors[] = get_string($key, 'local_sentientia_skillsai');
        }
    }

    // [CONFIRM] gate.
    if (!$prefill['confirm']) {
        $errors[] = get_string('err_confirm_required', 'local_sentientia_skillsai');
    }

    // Daily token cap.
    $tokensused = taxonomy_manager::tokens_used_today((int)$USER->id);
    if ($tokensused >= $dailycap) {
        $errors[] = get_string('err_token_cap_reached', 'local_sentientia_skillsai', (object)[
            'used' => $tokensused,
            'cap'  => $dailycap,
        ]);
    }

    if (empty($errors)) {
        $resolved = prompt_builder::resolve_for($currentcustomer, $prefill['language']);
        $promptctx = [
            'version'  => $resolved['version'],
            'template' => $resolved['template'],
        ];
        $promptversion = prompt_builder::resolve_prompt_version(
            $resolved['version'],
            $resolved['template'] !== null
        );

        // Persist the pending job FIRST so a crash mid-call leaves a trail.
        $jobid = taxonomy_manager::create_pending(
            (int)$USER->id,
            $prefill['courseid'],
            $prefill['title'],
            $prefill['sourcekind'],
            $prefill['sourcetext'],
            $defaultmodel,
            $promptversion
        );

        $result = anthropic_client::extract(
            $prefill['sourcetext'],
            $maxskills,
            $defaultmodel,
            $promptctx
        );

        if ($result['mode'] === 'failed') {
            taxonomy_manager::mark_failed($jobid, (string)$result['error']);
            redirect(new moodle_url('/local/sentientia_skillsai/review.php', ['jobid' => $jobid]),
                get_string('err_api_failed', 'local_sentientia_skillsai', s($result['error'])),
                null,
                \core\output\notification::NOTIFY_ERROR);
        }

        $skills = response_parser::parse($result['body']);
        taxonomy_manager::persist_candidates(
            $jobid,
            $skills,
            (int)$result['tokens_in'],
            (int)$result['tokens_out'],
            $result['mode']
        );

        redirect(new moodle_url('/local/sentientia_skillsai/review.php', ['jobid' => $jobid]));
    }
}

// Prompt preview.
$previewresolved = prompt_builder::resolve_for($currentcustomer, $prefill['language']);
$previewbody = prompt_builder::build_system_prompt(
    $previewresolved['version'],
    $previewresolved['template']
);
$previewlabel = prompt_builder::resolve_prompt_version(
    $previewresolved['version'],
    $previewresolved['template'] !== null
);

// Mode badge.
$badge = null;
if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_disabled_badge', 'local_sentientia_skillsai')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
    $badge = ['class' => 'alert-danger', 'text' => get_string('mode_disabled_badge', 'local_sentientia_skillsai')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.live_api')) {
    $badge = ['class' => 'alert-info', 'text' => get_string('mode_mock_badge', 'local_sentientia_skillsai')];
} else {
    $apikey = get_config('local_sentientia_skillsai', 'api_key');
    if (empty($apikey)) {
        $badge = ['class' => 'alert-warning', 'text' => get_string('mode_no_apikey_badge', 'local_sentientia_skillsai')];
    } else {
        $badge = ['class' => 'alert-success', 'text' => get_string('mode_live_badge', 'local_sentientia_skillsai')];
    }
}

$courses = $DB->get_records_select('course',
    'visible = 1 AND id > 1', null, 'fullname ASC', 'id, fullname, shortname', 0, 200);
$courseoptions = [0 => get_string('extract_form_course_none', 'local_sentientia_skillsai')];
foreach ($courses as $c) {
    $courseoptions[(int)$c->id] = format_string($c->fullname) . ' (' . format_string($c->shortname) . ')';
}

$kindoptions = [];
foreach (taxonomy_manager::SOURCE_KINDS as $k) {
    $kindoptions[$k] = get_string('sourcekind_' . $k, 'local_sentientia_skillsai');
}
$languageoptions = [
    'en' => get_string('extract_form_language_en', 'local_sentientia_skillsai'),
    'hi' => get_string('extract_form_language_hi', 'local_sentientia_skillsai'),
];

$tokensusedtoday = taxonomy_manager::tokens_used_today((int)$USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('extract_page_heading', 'local_sentientia_skillsai'));

if ($badge) {
    echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);
}

echo html_writer::div(get_string('extract_intro', 'local_sentientia_skillsai'), 'mb-3 text-muted');
echo html_writer::div(
    get_string('tokens_used_today', 'local_sentientia_skillsai', (object)[
        'used' => $tokensusedtoday, 'cap' => $dailycap,
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

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class'  => 'mform sentientia-skillsai-extract-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Title.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('extract_form_title', 'local_sentientia_skillsai'),
    ['for' => 'skai-title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'skai-title', 'name' => 'title', 'class' => 'form-control',
    'value' => s($prefill['title']), 'maxlength' => 200, 'required' => 'required',
]);
echo html_writer::end_div();

// Course picker.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('extract_form_course', 'local_sentientia_skillsai'),
    ['for' => 'skai-courseid', 'class' => 'form-label']);
echo html_writer::select($courseoptions, 'courseid', (int)$prefill['courseid'], false,
    ['id' => 'skai-courseid', 'class' => 'form-control']);
echo html_writer::end_div();

// Source kind.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('extract_form_sourcekind', 'local_sentientia_skillsai'),
    ['for' => 'skai-sourcekind', 'class' => 'form-label']);
echo html_writer::select($kindoptions, 'sourcekind', $prefill['sourcekind'], false,
    ['id' => 'skai-sourcekind', 'class' => 'form-control']);
echo html_writer::end_div();

// Language.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('extract_form_language', 'local_sentientia_skillsai'),
    ['for' => 'skai-language', 'class' => 'form-label']);
echo html_writer::select($languageoptions, 'language', $prefill['language'], false,
    ['id' => 'skai-language', 'class' => 'form-control']);
echo html_writer::end_div();

// Source content.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('extract_form_source', 'local_sentientia_skillsai'),
    ['for' => 'skai-source', 'class' => 'form-label']);
echo html_writer::tag('textarea', s($prefill['sourcetext']), [
    'id' => 'skai-source', 'name' => 'sourcetext', 'class' => 'form-control',
    'rows' => 14, 'required' => 'required',
]);
echo html_writer::div(
    get_string('extract_form_source_help', 'local_sentientia_skillsai', $maxsourcewords),
    'form-text text-muted');
echo html_writer::end_div();

// Prompt preview.
echo html_writer::start_tag('details', ['class' => 'mb-3']);
echo html_writer::tag('summary',
    s(get_string('extract_prompt_preview_summary', 'local_sentientia_skillsai', (object)['version' => $previewlabel])),
    ['class' => 'fw-bold']);
echo html_writer::div(get_string('extract_prompt_preview_help', 'local_sentientia_skillsai'),
    'form-text text-muted mt-2 mb-2');
if ($previewresolved['template'] !== null) {
    echo html_writer::div(get_string('extract_prompt_preview_custom_badge', 'local_sentientia_skillsai'),
        'badge bg-info text-dark mb-2');
}
echo html_writer::tag('pre', s($previewbody),
    ['class' => 'small bg-light p-3 border rounded',
     'style' => 'white-space: pre-wrap; max-height: 320px; overflow-y: auto;']);
echo html_writer::end_tag('details');

// Confirm checkbox.
echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox', 'id' => 'skai-confirm', 'name' => 'confirm',
    'class' => 'form-check-input', 'value' => '1',
] + ($prefill['confirm'] ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('extract_confirm_label', 'local_sentientia_skillsai'),
    ['for' => 'skai-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('extract_confirm_help', 'local_sentientia_skillsai'), 'form-text');
echo html_writer::end_div();

// Buttons.
echo html_writer::div(
    html_writer::tag('button', get_string('extract_submit', 'local_sentientia_skillsai'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2']) .
    html_writer::link(new moodle_url('/local/sentientia_skillsai/index.php'),
        get_string('extract_cancel', 'local_sentientia_skillsai'),
        ['class' => 'btn btn-secondary']),
    'mb-3');

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
