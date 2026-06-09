<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Translation — Translate page (Phase T.0 MVP).
 *
 * Admin flow:
 *   GET                : show the form (title + source + target lang)
 *   POST action=translate : validate -> [CONFIRM] gate -> create pending
 *                           -> run translation -> redirect to the diff view
 *   GET  ?rowid=N      : show source vs translation side-by-side diff with
 *                        Save / Discard buttons
 *   POST action=save   : accept the translation (status -> saved)
 *   POST action=discard: reject the translation (status -> discarded)
 *
 * Four gates are checked before any API call goes out:
 *   1. sentientia.translate.enabled feature flag = ON
 *   2. Capability local/sentientia_translate:translate
 *   3. Per-customer daily token cap not exceeded
 *   4. The confirm checkbox is ticked (the [CONFIRM] gate)
 *
 * @package local_sentientia_translate
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_translate\anthropic_client;
use local_sentientia_translate\brand_manager;
use local_sentientia_translate\prompt_builder;
use local_sentientia_translate\translate_engine;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_translate');
}

require_capability('local/sentientia_translate:translate', $context);

$rowid     = optional_param('rowid', 0, PARAM_INT);
$manageall = has_capability('local/sentientia_translate:manage_all', $context);

$PAGE->set_url('/local/sentientia_translate/translate.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('translate_page_title', 'local_sentientia_translate'));
$PAGE->set_heading(get_string('translate_page_heading', 'local_sentientia_translate'));

$maxsourcewords = (int)get_config('local_sentientia_translate', 'max_source_words');
if ($maxsourcewords <= 0) {
    $maxsourcewords = 4000;
}
$dailycap = (int)get_config('local_sentientia_translate', 'daily_cost_cap_tokens');
if ($dailycap <= 0) {
    $dailycap = 3000000;
}
$defaultmodel = (string)get_config('local_sentientia_translate', 'default_model');
if ($defaultmodel === '') {
    $defaultmodel = anthropic_client::DEFAULT_MODEL;
}

// ──────────────────────────────────────────────────────────────────
// DIFF MODE — rowid present: render source vs translation + save/discard.
// ──────────────────────────────────────────────────────────────────
if ($rowid > 0) {
    $row = translate_engine::load_for_actor($rowid, $USER, $manageall);
    if (!$row) {
        throw new moodle_exception('err_row_not_found', 'local_sentientia_translate');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_sesskey();
        $action = optional_param('action', '', PARAM_ALPHANUMEXT);
        if ($action === 'save') {
            translate_engine::accept($rowid, (int)$USER->id);
            redirect(new moodle_url('/local/sentientia_translate/translate.php', ['rowid' => $rowid]),
                get_string('saved_notice', 'local_sentientia_translate'),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'discard') {
            translate_engine::discard($rowid, (int)$USER->id);
            redirect(new moodle_url('/local/sentientia_translate/translate.php'),
                get_string('discarded_notice', 'local_sentientia_translate'),
                null, \core\output\notification::NOTIFY_INFO);
        }
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('diff_heading', 'local_sentientia_translate'));

    $statuslabel = get_string('status_' . $row->status, 'local_sentientia_translate');
    $statusclass = 'alert-info';
    if ($row->status === translate_engine::STATUS_SAVED) {
        $statusclass = 'alert-success';
    } else if ($row->status === translate_engine::STATUS_FAILED || $row->status === translate_engine::STATUS_DISCARDED) {
        $statusclass = 'alert-danger';
    }
    echo html_writer::div(
        html_writer::tag('strong', get_string('status_label', 'local_sentientia_translate') . ': ') . s($statuslabel),
        'alert ' . $statusclass);

    // Meta line.
    $langlabel = brand_manager::TARGET_LANGS[$row->targetlang] ?? $row->targetlang;
    echo html_writer::div(
        get_string('diff_meta', 'local_sentientia_translate', (object)[
            'lang'   => s($langlabel),
            'brands' => (int)$row->brand_terms_applied,
            'mode'   => s($row->mode),
        ]),
        'small text-muted mb-3');

    if (!empty($row->error_detail)) {
        echo html_writer::div(s($row->error_detail), 'alert alert-warning small');
    }

    // Side-by-side diff.
    echo html_writer::start_div('row sentientia-translate-diff');
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('h5', get_string('diff_source', 'local_sentientia_translate'));
    echo html_writer::tag('pre', s($row->sourcetext),
        ['class' => 'border rounded p-3 bg-light', 'style' => 'white-space:pre-wrap;']);
    echo html_writer::end_div();
    echo html_writer::start_div('col-md-6');
    echo html_writer::tag('h5', get_string('diff_translation', 'local_sentientia_translate'));
    echo html_writer::tag('pre', s((string)$row->translatedtext),
        ['class' => 'border rounded p-3', 'style' => 'white-space:pre-wrap;', 'lang' => s($row->targetlang)]);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Save / discard actions (only while in 'translated' state).
    if ($row->status === translate_engine::STATUS_TRANSLATED) {
        echo html_writer::start_div('mt-3');
        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'd-inline-block me-2',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rowid', 'value' => $rowid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
        echo html_writer::tag('button', get_string('action_save', 'local_sentientia_translate'),
            ['type' => 'submit', 'class' => 'btn btn-success']);
        echo html_writer::end_tag('form');

        echo html_writer::start_tag('form', [
            'method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'd-inline-block',
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rowid', 'value' => $rowid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'discard']);
        echo html_writer::tag('button', get_string('action_discard', 'local_sentientia_translate'),
            ['type' => 'submit', 'class' => 'btn btn-outline-danger']);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
    }

    echo html_writer::div(
        html_writer::link(new moodle_url('/local/sentientia_translate/translate.php'),
            get_string('back_to_translate', 'local_sentientia_translate'),
            ['class' => 'btn btn-link mt-3']),
        'mt-3');

    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────────────────────────
// FORM MODE — new translation.
// ──────────────────────────────────────────────────────────────────
$errors = [];
$prefill = [
    'title'      => '',
    'sourcetext' => '',
    'targetlang' => 'hi',
    'model'      => $defaultmodel,
    'confirm'    => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', 'translate', PARAM_ALPHANUMEXT);

    if ($action === 'translate') {
        $prefill['title']      = trim(optional_param('title', '', PARAM_TEXT));
        $prefill['sourcetext'] = optional_param('sourcetext', '', PARAM_RAW);
        $prefill['targetlang'] = optional_param('targetlang', 'hi', PARAM_ALPHA);
        $prefill['model']      = trim(optional_param('model', $defaultmodel, PARAM_TEXT));
        $prefill['confirm']    = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

        if ($prefill['title'] === '') {
            $prefill['title'] = 'Translation ' . userdate(time(), '%Y-%m-%d %H:%M');
        }

        $validate = prompt_builder::validate_request($prefill['sourcetext'], $prefill['targetlang'], $maxsourcewords);
        foreach ($validate as $key) {
            if ($key === 'err_source_too_long') {
                $errors[] = get_string($key, 'local_sentientia_translate') . ' (' .
                    get_string('source_word_count', 'local_sentientia_translate',
                        prompt_builder::word_count($prefill['sourcetext'])) . ')';
            } else {
                $errors[] = get_string($key, 'local_sentientia_translate');
            }
        }

        if (!$prefill['confirm']) {
            $errors[] = get_string('err_confirm_required', 'local_sentientia_translate');
        }

        $tokensused = translate_engine::tokens_used_today_for_customer(1);
        if ($tokensused >= $dailycap) {
            $errors[] = get_string('err_cost_cap_reached', 'local_sentientia_translate', (object)[
                'used' => $tokensused, 'cap' => $dailycap,
            ]);
        }

        if (empty($errors)) {
            $newrowid = translate_engine::create_pending(
                (int)$USER->id, $prefill['title'], $prefill['sourcetext'],
                $prefill['targetlang'], $prefill['model']);

            $result = translate_engine::run(
                $newrowid, $prefill['sourcetext'], $prefill['targetlang'], 1, $prefill['model']);

            if ($result->status === translate_engine::STATUS_FAILED) {
                redirect(new moodle_url('/local/sentientia_translate/translate.php', ['rowid' => $newrowid]),
                    get_string('err_api_failed', 'local_sentientia_translate', s((string)$result->error)),
                    null, \core\output\notification::NOTIFY_ERROR);
            }

            redirect(new moodle_url('/local/sentientia_translate/translate.php', ['rowid' => $newrowid]));
        }
    }
}

$badge = null;
if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_disabled_badge', 'local_sentientia_translate')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.enabled')) {
    $badge = ['class' => 'alert-danger', 'text' => get_string('mode_disabled_badge', 'local_sentientia_translate')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.translate.live_api')) {
    $badge = ['class' => 'alert-info', 'text' => get_string('mode_mock_badge', 'local_sentientia_translate')];
} else {
    $apikey = get_config('local_sentientia_translate', 'api_key');
    if (empty($apikey)) {
        $badge = ['class' => 'alert-warning', 'text' => get_string('mode_no_apikey_badge', 'local_sentientia_translate')];
    } else {
        $badge = ['class' => 'alert-success', 'text' => get_string('mode_live_badge', 'local_sentientia_translate')];
    }
}

$tokensused = translate_engine::tokens_used_today_for_customer(1);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('translate_page_heading', 'local_sentientia_translate'));

if ($badge) {
    echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);
}

echo html_writer::div(get_string('translate_intro', 'local_sentientia_translate'), 'mb-3 text-muted');
echo html_writer::div(
    get_string('tokens_used_today', 'local_sentientia_translate', (object)[
        'used' => $tokensused, 'cap' => $dailycap,
    ]),
    'small text-muted mb-3');

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
    'method' => 'post', 'action' => $PAGE->url->out(false),
    'class'  => 'mform sentientia-translate-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'translate']);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('form_title', 'local_sentientia_translate'),
    ['for' => 'sentientia-tr-title', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'sentientia-tr-title', 'name' => 'title',
    'class' => 'form-control', 'value' => s($prefill['title']), 'maxlength' => 200,
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('form_targetlang', 'local_sentientia_translate'),
    ['for' => 'sentientia-tr-lang', 'class' => 'form-label']);
$langoptions = [];
foreach (brand_manager::TARGET_LANGS as $code => $label) {
    $langoptions[$code] = get_string('lang_' . $code, 'local_sentientia_translate');
}
echo html_writer::select($langoptions, 'targetlang', $prefill['targetlang'], false,
    ['id' => 'sentientia-tr-lang', 'class' => 'form-control']);
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('form_source', 'local_sentientia_translate'),
    ['for' => 'sentientia-tr-source', 'class' => 'form-label']);
echo html_writer::tag('textarea', s($prefill['sourcetext']), [
    'id' => 'sentientia-tr-source', 'name' => 'sourcetext',
    'class' => 'form-control', 'rows' => 14, 'required' => 'required',
]);
echo html_writer::div(
    get_string('form_source_help', 'local_sentientia_translate', $maxsourcewords),
    'form-text text-muted');
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('form_model', 'local_sentientia_translate'),
    ['for' => 'sentientia-tr-model', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'sentientia-tr-model', 'name' => 'model',
    'class' => 'form-control', 'value' => s($prefill['model']),
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox', 'id' => 'sentientia-tr-confirm',
    'name' => 'confirm', 'class' => 'form-check-input', 'value' => '1',
] + ($prefill['confirm'] ? ['checked' => 'checked'] : []));
echo html_writer::tag('label', get_string('form_confirm_label', 'local_sentientia_translate'),
    ['for' => 'sentientia-tr-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('form_confirm_help', 'local_sentientia_translate'), 'form-text');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('button', get_string('form_submit', 'local_sentientia_translate'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2']) .
    html_writer::link(new moodle_url('/'),
        get_string('form_cancel', 'local_sentientia_translate'),
        ['class' => 'btn btn-secondary']),
    'mb-3');

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
