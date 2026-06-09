<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Recommendations — Generate page (Phase H.0 MVP).
 *
 * Manager flow:
 *   GET  : show form (target user + count + [CONFIRM] checkbox)
 *   POST : validate -> [CONFIRM] gate -> build profile + candidates ->
 *          call Anthropic (or mock) -> parse -> persist batch -> show
 *          summary with links to the user's dashboard block.
 *
 * Four gates are checked before any API call goes out:
 *   1. sentientia.recommendations.enabled feature flag = ON
 *   2. Capability local/sentientia_recommendations:generate
 *   3. Per-customer daily token cap not exceeded
 *   4. The confirm checkbox is ticked (the [CONFIRM] gate)
 *
 * The actual Anthropic call is dispatched via
 * anthropic_client::generate() which inspects
 * sentientia.recommendations.live_api to decide between the mock client
 * and the real curl call.
 *
 * @package local_sentientia_recommendations
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_recommendations\anthropic_client;
use local_sentientia_recommendations\prompt_builder;
use local_sentientia_recommendations\response_parser;
use local_sentientia_recommendations\recommendation_engine;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.recommendations.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_recommendations');
}

require_capability('local/sentientia_recommendations:generate', $context);

$PAGE->set_url('/local/sentientia_recommendations/generate.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('generate_page_title', 'local_sentientia_recommendations'));
$PAGE->set_heading(get_string('generate_page_heading', 'local_sentientia_recommendations'));

$maxrecs = (int)get_config('local_sentientia_recommendations', 'max_recommendations');
if ($maxrecs <= 0) {
    $maxrecs = 5;
}
$maxrecs = min($maxrecs, prompt_builder::MAX_RECOMMENDATIONS);

$dailycap = (int)get_config('local_sentientia_recommendations', 'daily_cost_cap_tokens');
if ($dailycap <= 0) {
    $dailycap = 2000000;
}
$defaultmodel = (string)get_config('local_sentientia_recommendations', 'default_model');
if ($defaultmodel === '') {
    $defaultmodel = anthropic_client::DEFAULT_MODEL;
}

$errors = [];
$prefill = [
    'targetuserid' => (int)$USER->id,
    'num'          => min(5, $maxrecs),
    'model'        => $defaultmodel,
    'confirm'      => 0,
];
$result_summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    $prefill['targetuserid'] = optional_param('targetuserid', (int)$USER->id, PARAM_INT);
    $prefill['num']          = optional_param('num', $prefill['num'], PARAM_INT);
    $prefill['model']        = trim(optional_param('model', $defaultmodel, PARAM_TEXT));
    $prefill['confirm']      = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

    if ($prefill['num'] < prompt_builder::MIN_RECOMMENDATIONS || $prefill['num'] > $maxrecs) {
        $errors[] = get_string('err_invalid_count', 'local_sentientia_recommendations', (object)[
            'min' => prompt_builder::MIN_RECOMMENDATIONS,
            'max' => $maxrecs,
        ]);
    }

    if (!$prefill['confirm']) {
        $errors[] = get_string('err_confirm_required', 'local_sentientia_recommendations');
    }

    $tokensused = recommendation_engine::tokens_used_today_for_customer(1);
    if ($tokensused >= $dailycap) {
        $errors[] = get_string('err_cost_cap_reached', 'local_sentientia_recommendations', (object)[
            'used' => $tokensused,
            'cap'  => $dailycap,
        ]);
    }

    if (!$DB->record_exists('user', ['id' => $prefill['targetuserid'], 'deleted' => 0])) {
        $errors[] = get_string('err_user_not_found', 'local_sentientia_recommendations');
    }

    if (empty($errors)) {
        $profile = recommendation_engine::build_profile($prefill['targetuserid']);
        $candidates = recommendation_engine::build_candidate_list($profile, prompt_builder::MAX_CANDIDATE_COURSES);

        $validate = prompt_builder::validate_request($profile, $candidates, $prefill['num']);
        if (!empty($validate)) {
            foreach ($validate as $key) {
                $errors[] = get_string($key, 'local_sentientia_recommendations');
            }
        } else {
            $apiresult = anthropic_client::generate(
                $profile, $candidates, $prefill['num'], $prefill['model']);

            if ($apiresult['mode'] === 'failed') {
                $errors[] = get_string('err_api_failed', 'local_sentientia_recommendations', s($apiresult['error']));
            } else {
                $allowed = array_map(fn($c) => (int)$c->id, $candidates);
                $parsed  = response_parser::parse($apiresult['body'], $allowed);
                if (empty($parsed)) {
                    $errors[] = get_string('err_parser_zero', 'local_sentientia_recommendations');
                } else {
                    $batchid = recommendation_engine::persist_batch(
                        $prefill['targetuserid'],
                        $parsed,
                        (int)$apiresult['tokens_in'],
                        (int)$apiresult['tokens_out'],
                        $apiresult['mode'],
                        $prefill['model']
                    );
                    $result_summary = (object)[
                        'batchid'   => $batchid,
                        'count'     => count($parsed),
                        'mode'      => $apiresult['mode'],
                        'tokens_in' => (int)$apiresult['tokens_in'],
                        'tokens_out'=> (int)$apiresult['tokens_out'],
                    ];
                }
            }
        }
    }
}

$badge = null;
if (!class_exists('\\local_sentientia_platform\\feature_flags')) {
    $badge = ['class' => 'alert-warning', 'text' => get_string('mode_disabled_badge', 'local_sentientia_recommendations')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.recommendations.enabled')) {
    $badge = ['class' => 'alert-danger', 'text' => get_string('mode_disabled_badge', 'local_sentientia_recommendations')];
} else if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.recommendations.live_api')) {
    $badge = ['class' => 'alert-info', 'text' => get_string('mode_mock_badge', 'local_sentientia_recommendations')];
} else {
    $apikey = get_config('local_sentientia_recommendations', 'api_key');
    if (empty($apikey)) {
        $badge = ['class' => 'alert-warning', 'text' => get_string('mode_no_apikey_badge', 'local_sentientia_recommendations')];
    } else {
        $badge = ['class' => 'alert-success', 'text' => get_string('mode_live_badge', 'local_sentientia_recommendations')];
    }
}

$tokensused = recommendation_engine::tokens_used_today_for_customer(1);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generate_page_heading', 'local_sentientia_recommendations'));

if ($badge) {
    echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);
}

echo html_writer::div(get_string('generate_intro', 'local_sentientia_recommendations'),
    'mb-3 text-muted');

echo html_writer::div(
    get_string('tokens_used_today', 'local_sentientia_recommendations', (object)[
        'used' => $tokensused, 'cap' => $dailycap,
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

if ($result_summary) {
    $msg = get_string('generate_success', 'local_sentientia_recommendations', $result_summary);
    echo html_writer::div(s($msg), 'alert alert-success');
}

echo html_writer::start_tag('form', [
    'method' => 'post', 'action' => $PAGE->url->out(false),
    'class'  => 'mform sentientia-rec-generate-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('generate_form_targetuser', 'local_sentientia_recommendations'),
    ['for' => 'sentientia-rec-targetuser', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'id' => 'sentientia-rec-targetuser',
    'name' => 'targetuserid', 'class' => 'form-control',
    'value' => (int)$prefill['targetuserid'], 'min' => 1, 'required' => 'required',
]);
echo html_writer::div(get_string('generate_form_targetuser_help', 'local_sentientia_recommendations'),
    'form-text text-muted');
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('generate_form_num', 'local_sentientia_recommendations'),
    ['for' => 'sentientia-rec-num', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'number', 'id' => 'sentientia-rec-num', 'name' => 'num',
    'class' => 'form-control', 'value' => (int)$prefill['num'],
    'min' => prompt_builder::MIN_RECOMMENDATIONS, 'max' => $maxrecs,
    'required' => 'required',
]);
echo html_writer::div(get_string('generate_form_num_help', 'local_sentientia_recommendations', $maxrecs),
    'form-text text-muted');
echo html_writer::end_div();

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('generate_form_model', 'local_sentientia_recommendations'),
    ['for' => 'sentientia-rec-model', 'class' => 'form-label']);
echo html_writer::empty_tag('input', [
    'type' => 'text', 'id' => 'sentientia-rec-model', 'name' => 'model',
    'class' => 'form-control', 'value' => s($prefill['model']),
]);
echo html_writer::end_div();

echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox', 'id' => 'sentientia-rec-confirm',
    'name' => 'confirm', 'class' => 'form-check-input', 'value' => '1',
] + ($prefill['confirm'] ? ['checked' => 'checked'] : []));
echo html_writer::tag('label',
    get_string('generate_confirm_label', 'local_sentientia_recommendations'),
    ['for' => 'sentientia-rec-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('generate_confirm_help', 'local_sentientia_recommendations'),
    'form-text');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('button',
        get_string('generate_submit', 'local_sentientia_recommendations'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2']) .
    html_writer::link(new moodle_url('/'),
        get_string('generate_cancel', 'local_sentientia_recommendations'),
        ['class' => 'btn btn-secondary']),
    'mb-3');

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
