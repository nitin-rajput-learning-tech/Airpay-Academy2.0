<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Authoring Studio — TTS voiceover surface for a card's narration.
 *
 * Gated by BOTH sentientia.authoring.enabled AND sentientia.authoring.tts.
 * Mock-mode by default (sentientia.authoring.live_api OFF) — produces a
 * deterministic placeholder, zero cost. A live ElevenLabs call would
 * additionally require live_api ON, a configured key + voice id, AND the
 * per-action [CONFIRM] checkbox (enforced here). Per CLAUDE.md §10, ElevenLabs
 * is charged per character, so the cost estimate is shown before [CONFIRM].
 *
 * @package local_sentientia_authoring
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_authoring\draft_manager;
use local_sentientia_authoring\tts_client;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
        throw new moodle_exception('err_feature_off', 'local_sentientia_authoring');
    }
    if (!\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.tts')) {
        throw new moodle_exception('err_tts_off', 'local_sentientia_authoring');
    }
}
require_capability('local/sentientia_authoring:review', $context);

$draftid = required_param('draftid', PARAM_INT);
$cardid  = required_param('cardid', PARAM_INT);

$manageall = has_capability('local/sentientia_authoring:manage_all', $context);
$bundle = draft_manager::load_for_actor($draftid, $USER, $manageall);
if (!$bundle) {
    throw new moodle_exception('err_draft_not_found', 'local_sentientia_authoring');
}
// Find the card within the (already tenant-scoped) bundle.
$card = null;
foreach ($bundle->cards as $c) {
    if ((int) $c->id === $cardid) {
        $card = $c;
        break;
    }
}
if (!$card) {
    throw new moodle_exception('err_card_not_found', 'local_sentientia_authoring');
}

$narration = (string) ($card->narration ?? '');
$lang = (string) $bundle->draft->targetlang;

$PAGE->set_url('/local/sentientia_authoring/voiceover.php', ['draftid' => $draftid, 'cardid' => $cardid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('voiceover_page_title', 'local_sentientia_authoring'));
$PAGE->set_heading(get_string('voiceover_page_heading', 'local_sentientia_authoring'));

$reviewurl = new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $confirm = optional_param('confirm', 0, PARAM_INT) ? 1 : 0;

    if (trim($narration) === '') {
        redirect($reviewurl, get_string('voiceover_no_narration', 'local_sentientia_authoring'),
            null, \core\output\notification::NOTIFY_ERROR);
    }
    // [CONFIRM] gate — required regardless of mode so the workflow is identical.
    if (!$confirm) {
        redirect($PAGE->url, get_string('err_confirm_required', 'local_sentientia_authoring'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    $result = tts_client::synthesize($narration, $lang);
    draft_manager::record_voiceover($draftid, $cardid, $result, $lang);

    $msg = ($result['mode'] === 'mock')
        ? get_string('voiceover_done_mock', 'local_sentientia_authoring')
        : (($result['mode'] === 'failed')
            ? get_string('voiceover_failed', 'local_sentientia_authoring', s($result['error']))
            : get_string('voiceover_done_live', 'local_sentientia_authoring'));
    redirect($reviewurl, $msg);
}

// Mode badge.
$live = tts_client::is_live_ready();
$badge = $live
    ? ['class' => 'alert-success', 'text' => get_string('voiceover_mode_live', 'local_sentientia_authoring')]
    : ['class' => 'alert-info', 'text' => get_string('voiceover_mode_mock', 'local_sentientia_authoring')];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('voiceover_page_heading', 'local_sentientia_authoring'));
echo html_writer::div(s($badge['text']), 'alert ' . s($badge['class']), ['role' => 'status']);

echo html_writer::tag('h5', s($card->heading));
echo html_writer::tag('p', html_writer::tag('strong',
    get_string('review_narration', 'local_sentientia_authoring') . ' ') . s($narration), ['class' => 'text-muted']);

echo html_writer::div(get_string('voiceover_cost_estimate', 'local_sentientia_authoring', (object) [
    'chars' => mb_strlen(trim($narration)),
    'cost'  => $live ? number_format(tts_client::estimate_cost($narration), 4) : '0.0000',
]), 'small text-muted mb-3');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mform']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3 alert alert-warning');
echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'id' => 'vo-confirm', 'name' => 'confirm',
    'class' => 'form-check-input', 'value' => '1']);
echo html_writer::tag('label', get_string('voiceover_confirm_label', 'local_sentientia_authoring'),
    ['for' => 'vo-confirm', 'class' => 'form-check-label fw-bold']);
echo html_writer::end_div();
echo html_writer::div(get_string('voiceover_confirm_help', 'local_sentientia_authoring'), 'form-text');
echo html_writer::end_div();

echo html_writer::div(
    html_writer::tag('button', get_string('voiceover_submit', 'local_sentientia_authoring'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2'])
    . html_writer::link($reviewurl, get_string('cancel'), ['class' => 'btn btn-secondary']));
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
