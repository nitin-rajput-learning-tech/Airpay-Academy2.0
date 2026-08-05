<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Authoring Studio — generate page.
 *
 * Course Author flow:
 *   GET  : show the form (title + template picker + source paste + source
 *          type + language + card/question counts + mastery score + [CONFIRM]).
 *   POST : validate → [CONFIRM] gate → create pending draft → call Claude (or
 *          mock) → parse → persist cards+questions → redirect to review.php.
 *
 * Gates checked before any AI call:
 *   1. sentientia.authoring.enabled feature flag = ON
 *   2. Capability local/sentientia_authoring:generate
 *   3. Per-user daily token cap not exceeded
 *   4. The [CONFIRM] checkbox is ticked
 *
 * Anthropic is dispatched via course_generator::generate(), which inspects
 * sentientia.authoring.live_api to pick mock vs live. Default = mock, zero cost.
 * NOTHING generated is auto-published — review.php is the human-review gate.
 *
 * @package local_sentientia_authoring
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_authoring\course_generator;
use local_sentientia_authoring\draft_manager;
use local_sentientia_authoring\prompt_builder;
use local_sentientia_authoring\response_parser;
use local_sentientia_authoring\template_manager;
use local_sentientia_authoring\localizer;

require_login();
$context = context_system::instance();

// Gate 1 — feature flag.
if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_authoring');
}

// Gate 2 — capability.
require_capability('local/sentientia_authoring:generate', $context);

$PAGE->set_url('/local/sentientia_authoring/studio.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('studio_page_title', 'local_sentientia_authoring'));
$PAGE->set_heading(get_string('studio_page_heading', 'local_sentientia_authoring'));

$maxcards = (int) get_config('local_sentientia_authoring', 'max_cards');
$maxcards = ($maxcards > 0) ? min($maxcards, prompt_builder::MAX_CARDS) : 8;
$maxquestions = (int) get_config('local_sentientia_authoring', 'max_questions');
$maxquestions = ($maxquestions > 0) ? min($maxquestions, prompt_builder::MAX_QUESTIONS) : 10;
$maxsourcewords = (int) get_config('local_sentientia_authoring', 'max_source_words');
$maxsourcewords = ($maxsourcewords > 0) ? $maxsourcewords : 4000;
$dailycap = (int) get_config('local_sentientia_authoring', 'daily_token_cap');
$dailycap = ($dailycap > 0) ? $dailycap : 500000;
$defaultmodel = (string) get_config('local_sentientia_authoring', 'default_model');
if ($defaultmodel === '') {
    $defaultmodel = course_generator::DEFAULT_MODEL;
}
$defaultmastery = (int) get_config('local_sentientia_authoring', 'default_mastery_score');
$defaultmastery = ($defaultmastery >= 0 && $defaultmastery <= 100) ? $defaultmastery : 70;

$uilocale = (string) (current_language() ?? 'en');

$errors = [];
$prefill = [
    'title'      => '',
    'templateid' => 0,
    'sourcetype' => 'prompt',
    'sourcetext' => '',
    'language'   => (prompt_builder::version_for_locale($uilocale) === prompt_builder::VERSION_V2_HINDI) ? 'hi' : 'en',
    'numcards'   => min(4, $maxcards),
    'numq'       => min(5, $maxquestions),
    'mastery'    => $defaultmastery,
    'model'      => $defaultmodel,
    'confirm'    => 0,
];

$templates = template_manager::list_for_actor($USER, false);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $prefill['title']      = trim(optional_param('title', '', PARAM_TEXT));
    $prefill['templateid'] = optional_param('templateid', 0, PARAM_INT);
    $prefill['sourcetype'] = optional_param('sourcetype', 'prompt', PARAM_ALPHA);
    $prefill['sourcetext'] = optional_param('sourcetext', '', PARAM_RAW);
    $prefill['language']   = strtolower(trim(optional_param('language', $prefill['language'], PARAM_ALPHA)));
    $prefill['numcards']   = optional_param('numcards', $prefill['numcards'], PARAM_INT);
    $prefill['numq']       = optional_param('numq', $prefill['numq'], PARAM_INT);
    $prefill['mastery']    = optional_param('mastery', $prefill['mastery'], PARAM_INT);
    $prefill['model']      = trim(optional_param('model', $defaultmodel, PARAM_TEXT));
    $prefill['confirm']    = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

    if (!in_array($prefill['language'], ['en', 'hi'], true)) {
        $prefill['language'] = 'en';
    }
    if (!in_array($prefill['sourcetype'], ['prompt', 'doc', 'pdf'], true)) {
        $prefill['sourcetype'] = 'prompt';
    }
    if ($prefill['title'] === '') {
        $prefill['title'] = 'Module ' . userdate(time(), '%Y-%m-%d %H:%M');
    }

    // Validate source.
    foreach (prompt_builder::validate_source($prefill['sourcetext'], $maxsourcewords) as $key) {
        if ($key === 'err_source_too_long') {
            $errors[] = get_string($key, 'local_sentientia_authoring')
                . ' (' . get_string('source_word_count', 'local_sentientia_authoring',
                    prompt_builder::word_count($prefill['sourcetext'])) . ')';
        } else {
            $errors[] = get_string($key, 'local_sentientia_authoring');
        }
    }

    if ($prefill['numcards'] < prompt_builder::MIN_CARDS || $prefill['numcards'] > $maxcards) {
        $errors[] = get_string('err_invalid_cards', 'local_sentientia_authoring',
            (object) ['min' => prompt_builder::MIN_CARDS, 'max' => $maxcards]);
    }
    if ($prefill['numq'] < prompt_builder::MIN_QUESTIONS || $prefill['numq'] > $maxquestions) {
        $errors[] = get_string('err_invalid_questions', 'local_sentientia_authoring',
            (object) ['min' => prompt_builder::MIN_QUESTIONS, 'max' => $maxquestions]);
    }
    if ($prefill['mastery'] < 0 || $prefill['mastery'] > 100) {
        $errors[] = get_string('err_invalid_mastery', 'local_sentientia_authoring');
    }
    if (!$prefill['confirm']) {
        $errors[] = get_string('err_confirm_required', 'local_sentientia_authoring');
    }

    // Validate template (must be visible to actor).
    $templatebody = null;
    if ($prefill['templateid'] > 0) {
        $tpl = template_manager::load_for_actor($prefill['templateid'], $USER, false);
        if (!$tpl) {
            $errors[] = get_string('err_template_not_found', 'local_sentientia_authoring');
        } else {
            $templatebody = $tpl->body;
        }
    }

    $tokensused = draft_manager::tokens_used_today((int) $USER->id);
    if ($tokensused >= $dailycap) {
        $errors[] = get_string('err_token_cap_reached', 'local_sentientia_authoring',
            (object) ['used' => $tokensused, 'cap' => $dailycap]);
    }

    if (empty($errors)) {
        $version = prompt_builder::version_for_locale($prefill['language']);
        $promptversion = prompt_builder::resolve_prompt_version($version, $templatebody !== null);

        $draftid = draft_manager::create_pending(
            (int) $USER->id,
            $prefill['title'],
            $prefill['sourcetext'],
            $prefill['sourcetype'],
            $prefill['language'],
            $prefill['model'],
            $prefill['mastery'],
            $prefill['templateid'] > 0 ? $prefill['templateid'] : null,
            $promptversion
        );

        $result = course_generator::generate(
            $prefill['sourcetext'], $prefill['numcards'], $prefill['numq'],
            $prefill['model'], $version, $templatebody);

        if ($result['mode'] === 'failed') {
            draft_manager::mark_failed($draftid, (string) $result['error']);
            redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]),
                get_string('err_api_failed', 'local_sentientia_authoring', s($result['error'])),
                null, \core\output\notification::NOTIFY_ERROR);
        }

        $parsed = response_parser::parse($result['body']);
        draft_manager::persist_generation($draftid, $parsed->cards, $parsed->questions,
            (int) $result['tokens_in'], (int) $result['tokens_out'], $result['mode']);

        redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]));
    }
}

// Mode badge.
$badge = null;
if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_disabled_badge', 'local_sentientia_authoring')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.live_api')) {
    $badge = ['class' => 'alert-info', 'text' => get_string('mode_mock_badge', 'local_sentientia_authoring')];
} else if (empty(get_config('local_sentientia_authoring', 'anthropic_api_key'))) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_no_apikey_badge', 'local_sentientia_authoring')];
} else {
    $badge = ['class' => 'alert-success', 'text' => get_string('mode_live_badge', 'local_sentientia_authoring')];
}

$tokensusedtoday = draft_manager::tokens_used_today((int) $USER->id);
$templateoptions = [0 => get_string('studio_form_template_none', 'local_sentientia_authoring')];
foreach ($templates as $t) {
    $label = format_string($t->name);
    if ((int) $t->is_builtin === 1) {
        $label .= ' ' . get_string('template_builtin_suffix', 'local_sentientia_authoring');
    }
    $templateoptions[(int) $t->id] = $label;
}
$languageoptions = [
    'en' => get_string('language_en', 'local_sentientia_authoring'),
    'hi' => get_string('language_hi', 'local_sentientia_authoring'),
];
$sourcetypeoptions = [
    'prompt' => get_string('sourcetype_prompt', 'local_sentientia_authoring'),
    'doc'    => get_string('sourcetype_doc', 'local_sentientia_authoring'),
    'pdf'    => get_string('sourcetype_pdf', 'local_sentientia_authoring'),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studio_page_heading', 'local_sentientia_authoring'));

if ($badge) {
    echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);
}
echo html_writer::div(get_string('studio_intro', 'local_sentientia_authoring'), 'mb-3 text-muted');
echo html_writer::div(html_writer::link(
    new moodle_url('/local/sentientia_authoring/templates.php'),
    get_string('nav_templates', 'local_sentientia_authoring'),
    ['class' => 'btn btn-outline-secondary btn-sm']), 'mb-3');

echo html_writer::div(get_string('tokens_used_today', 'local_sentientia_authoring',
    (object) ['used' => $tokensusedtoday, 'cap' => $dailycap]), 'small text-muted mb-3');

if (!empty($errors)) {
    $list = '';
    foreach ($errors as $e) {
        $list .= html_writer::tag('li', s($e));
    }
    echo html_writer::div(html_writer::tag('strong', get_string('error'))
        . html_writer::tag('ul', $list), 'alert alert-danger', ['role' => 'alert']);
}

echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url->out(false),
    'class'  => 'mform sentientia-authoring-studio-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Title.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('studio_form_title', 'local_sentientia_authoring'),
    ['for' => 'authoring-title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'authoring-title', 'name' => 'title',
    'class' => 'form-control', 'value' => s($prefill['title']), 'maxlength' => 200, 'required' => 'required']);
echo html_writer::end_div();

// Template picker.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('studio_form_template', 'local_sentientia_authoring'),
    ['for' => 'authoring-template', 'class' => 'form-label']);
echo html_writer::select($templateoptions, 'templateid', (int) $prefill['templateid'], false,
    ['id' => 'authoring-template', 'class' => 'form-control']);
echo html_writer::div(get_string('studio_form_template_help', 'local_sentientia_authoring'), 'form-text text-muted');
echo html_writer::end_div();

// Source type + language (inline).
echo html_writer::start_div('row');
echo html_writer::start_div('col-md-6 mb-3');
echo html_writer::tag('label', get_string('studio_form_sourcetype', 'local_sentientia_authoring'),
    ['for' => 'authoring-sourcetype', 'class' => 'form-label']);
echo html_writer::select($sourcetypeoptions, 'sourcetype', $prefill['sourcetype'], false,
    ['id' => 'authoring-sourcetype', 'class' => 'form-control']);
echo html_writer::end_div();
echo html_writer::start_div('col-md-6 mb-3');
echo html_writer::tag('label', get_string('studio_form_language', 'local_sentientia_authoring'),
    ['for' => 'authoring-language', 'class' => 'form-label']);
echo html_writer::select($languageoptions, 'language', $prefill['language'], false,
    ['id' => 'authoring-language', 'class' => 'form-control']);
echo html_writer::div(get_string('studio_form_language_help', 'local_sentientia_authoring'), 'form-text text-muted');
echo html_writer::end_div();
echo html_writer::end_div();

// Source text.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('studio_form_source', 'local_sentientia_authoring'),
    ['for' => 'authoring-source', 'class' => 'form-label']);
echo html_writer::tag('textarea', s($prefill['sourcetext']), [
    'id' => 'authoring-source', 'name' => 'sourcetext', 'class' => 'form-control', 'rows' => 12, 'required' => 'required']);
echo html_writer::div(get_string('studio_form_source_help', 'local_sentientia_authoring', $maxsourcewords),
    'form-text text-muted');
echo html_writer::end_div();

// Counts + mastery (inline).
echo html_writer::start_div('row');
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('studio_form_numcards', 'local_sentientia_authoring'),
    ['for' => 'authoring-numcards', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'authoring-numcards', 'name' => 'numcards',
    'class' => 'form-control', 'value' => (int) $prefill['numcards'],
    'min' => prompt_builder::MIN_CARDS, 'max' => $maxcards, 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('studio_form_numq', 'local_sentientia_authoring'),
    ['for' => 'authoring-numq', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'authoring-numq', 'name' => 'numq',
    'class' => 'form-control', 'value' => (int) $prefill['numq'],
    'min' => prompt_builder::MIN_QUESTIONS, 'max' => $maxquestions, 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::start_div('col-md-4 mb-3');
echo html_writer::tag('label', get_string('studio_form_mastery', 'local_sentientia_authoring'),
    ['for' => 'authoring-mastery', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'number', 'id' => 'authoring-mastery', 'name' => 'mastery',
    'class' => 'form-control', 'value' => (int) $prefill['mastery'], 'min' => 0, 'max' => 100, 'required' => 'required']);
echo html_writer::end_div();
echo html_writer::end_div();

// Model.
echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('studio_form_model', 'local_sentientia_authoring'),
    ['for' => 'authoring-model', 'class' => 'form-label']);
echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'authoring-model', 'name' => 'model',
    'class' => 'form-control', 'value' => s($prefill['model'])]);
echo html_writer::end_div();

// Confirm gate.
echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'authoring-confirm', 'name' => 'confirm',
    'class' => 'form-check-input', 'value' => '1'] + ($prefill['confirm'] ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('studio_confirm_label', 'local_sentientia_authoring'),
    ['for' => 'authoring-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('studio_confirm_help', 'local_sentientia_authoring'), 'form-text');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('button', get_string('studio_submit', 'local_sentientia_authoring'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2'])
    . html_writer::link(new moodle_url('/'), get_string('cancel'), ['class' => 'btn btn-secondary']),
    'mb-3');

echo html_writer::end_tag('form');
echo $OUTPUT->footer();
