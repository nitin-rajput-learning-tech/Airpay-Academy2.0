<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Authoring Studio — review page (the mandatory human-review gate).
 *
 * Shows a generated draft's cards + questions. The reviewer approves / edits /
 * rejects each item, then finalises. Only a finalised (approved) draft is
 * eligible for publish/voiceover. NOTHING generated is treated as publishable
 * until a human passes through here.
 *
 * Actions (POST, sesskey-guarded):
 *   reviewcard      — set a card's status (+ optional content edit)
 *   reviewquestion  — set a question's status (re-validated via question_type)
 *   finalise        — close the review (draft → approved | rejected)
 *
 * @package local_sentientia_authoring
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_authoring\draft_manager;
use local_sentientia_authoring\question_type;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_authoring');
}
require_capability('local/sentientia_authoring:review', $context);

$draftid = required_param('draftid', PARAM_INT);

$manageall = has_capability('local/sentientia_authoring:manage_all', $context);
$bundle = draft_manager::load_for_actor($draftid, $USER, $manageall);
if (!$bundle) {
    throw new moodle_exception('err_draft_not_found', 'local_sentientia_authoring');
}

$PAGE->set_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('review_page_title', 'local_sentientia_authoring'));
$PAGE->set_heading(get_string('review_page_heading', 'local_sentientia_authoring'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHA);

    if ($action === 'reviewcard') {
        $cardid = required_param('cardid', PARAM_INT);
        $status = required_param('status', PARAM_ALPHA);
        // Confirm the card belongs to this draft.
        $owned = false;
        foreach ($bundle->cards as $c) {
            if ((int) $c->id === $cardid) {
                $owned = true;
                break;
            }
        }
        if ($owned) {
            $updates = [];
            $note = trim(optional_param('reviewer_note', '', PARAM_TEXT));
            if ($note !== '') {
                $updates['reviewer_note'] = $note;
            }
            $body = optional_param('body', null, PARAM_RAW);
            if ($body !== null && trim($body) !== '') {
                $updates['body'] = $body;
            }
            draft_manager::review_card($cardid, $status, $updates);
        }
        redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]));
    }

    if ($action === 'reviewquestion') {
        $questionid = required_param('questionid', PARAM_INT);
        $status = required_param('status', PARAM_ALPHA);
        $owned = false;
        foreach ($bundle->questions as $q) {
            if ((int) $q->id === $questionid) {
                $owned = true;
                break;
            }
        }
        if ($owned) {
            $updates = [];
            $note = trim(optional_param('reviewer_note', '', PARAM_TEXT));
            if ($note !== '') {
                $updates['reviewer_note'] = $note;
            }
            $qtext = optional_param('qtext', null, PARAM_RAW);
            if ($qtext !== null && trim($qtext) !== '') {
                $updates['qtext'] = $qtext;
            }
            draft_manager::review_question($questionid, $status, $updates);
        }
        redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]));
    }

    if ($action === 'finalise') {
        $newstatus = draft_manager::finalise_review($draftid, (int) $USER->id);
        $msg = ($newstatus === draft_manager::STATUS_APPROVED)
            ? get_string('review_finalised_approved', 'local_sentientia_authoring')
            : get_string('review_finalised_rejected', 'local_sentientia_authoring');
        redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]), $msg);
    }

    if ($action === 'publish') {
        // Gate #3 closure (2026-08-05): the REAL course builder — an
        // approved draft becomes a hidden course (book of approved cards +
        // mastery quiz with gradepass from mastery_score). Gated on the
        // authoring publish flag; default OFF until ninja verification.
        $canpublish = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled(
                'sentientia.authoring.publish.enabled');
        if (!$canpublish) {
            redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]),
                get_string('publish_disabled', 'local_sentientia_authoring'),
                null, \core\output\notification::NOTIFY_WARNING);
        }
        $categoryid = optional_param('categoryid', 0, PARAM_INT);
        try {
            $result = course_builder::build($draftid, $USER, $manageall, $categoryid);
            redirect(new moodle_url('/course/view.php', ['id' => $result->courseid]),
                get_string('publish_success', 'local_sentientia_authoring', (object) [
                    'shortname'     => $result->shortname,
                    'cardcount'     => $result->cardcount,
                    'questioncount' => $result->questioncount,
                ]),
                null, \core\output\notification::NOTIFY_SUCCESS);
        } catch (\moodle_exception $e) {
            redirect(new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]),
                get_string('publish_failed', 'local_sentientia_authoring', $e->getMessage()),
                null, \core\output\notification::NOTIFY_ERROR);
        }
    }
}

$draft = $bundle->draft;

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($draft->title));

// Status + meta line.
echo html_writer::div(get_string('review_meta', 'local_sentientia_authoring', (object) [
    'status'    => s($draft->status),
    'mode'      => ($draft->tokens_in + $draft->tokens_out) > 0 ? 'live' : 'mock',
    'cards'     => (int) $draft->num_cards,
    'questions' => (int) $draft->num_questions,
    'mastery'   => (int) $draft->mastery_score,
]), 'alert alert-secondary');

if ($draft->status === draft_manager::STATUS_FAILED) {
    echo html_writer::div(get_string('review_failed', 'local_sentientia_authoring', s($draft->error_detail)),
        'alert alert-danger');
    echo html_writer::link(new moodle_url('/local/sentientia_authoring/studio.php'),
        get_string('review_back_to_studio', 'local_sentientia_authoring'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    die();
}

// Human-review reminder.
echo html_writer::div(get_string('review_gate_notice', 'local_sentientia_authoring'),
    'alert alert-warning', ['role' => 'note']);

// ── Cards ───────────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('review_cards_heading', 'local_sentientia_authoring'), 3);
foreach ($bundle->cards as $card) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', s($card->heading) . ' '
        . html_writer::tag('span', s($card->cardtype), ['class' => 'badge bg-info text-dark']),
        ['class' => 'card-title']);
    echo html_writer::tag('p', format_text($card->body, FORMAT_PLAIN), ['class' => 'card-text']);
    if ($card->cardtype === 'flip' && $card->flip_back !== null) {
        echo html_writer::tag('p',
            html_writer::tag('strong', get_string('review_flip_back', 'local_sentientia_authoring') . ' ')
            . s($card->flip_back), ['class' => 'card-text text-muted']);
    }
    if (!empty($card->narration)) {
        echo html_writer::tag('p',
            html_writer::tag('strong', get_string('review_narration', 'local_sentientia_authoring') . ' ')
            . s($card->narration), ['class' => 'card-text small text-muted']);
    }
    echo html_writer::tag('span', get_string('item_status_' . $card->status, 'local_sentientia_authoring'),
        ['class' => 'badge bg-secondary mb-2']);
    echo sentientia_authoring_item_buttons('reviewcard', 'cardid', (int) $card->id, $draftid);
    // Voiceover link (only when the TTS flag is ON).
    if (class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.tts')
            && !empty($card->narration)) {
        echo html_writer::div(html_writer::link(
            new moodle_url('/local/sentientia_authoring/voiceover.php',
                ['draftid' => $draftid, 'cardid' => (int) $card->id]),
            get_string('review_voiceover_link', 'local_sentientia_authoring'),
            ['class' => 'btn btn-outline-primary btn-sm mt-2']), 'mt-2');
    }
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// ── Questions ───────────────────────────────────────────────────────
echo $OUTPUT->heading(get_string('review_questions_heading', 'local_sentientia_authoring'), 3);
foreach ($bundle->questions as $q) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5',
        html_writer::tag('span', s($q->qtype), ['class' => 'badge bg-primary']) . ' ' . s($q->qtext),
        ['class' => 'card-title']);

    // Render options/pairs + correct answer per type.
    $answer = question_type::decode_answer($q->qtype, (string) $q->qanswer);
    $opts = json_decode((string) $q->qoptions_json, true);
    if ($q->qtype === question_type::TYPE_MATCH && is_array($opts)) {
        $rows = '';
        foreach ($opts as $pair) {
            $rows .= html_writer::tag('li', s($pair['left'] ?? '') . ' → ' . s($pair['right'] ?? ''));
        }
        echo html_writer::tag('ul', $rows);
    } else if (is_array($opts)) {
        $rows = '';
        foreach ($opts as $i => $opt) {
            $iscorrect = ($q->qtype === question_type::TYPE_MRQ)
                ? in_array($i, (array) $answer, true)
                : ((int) $answer === (int) $i);
            $mark = $iscorrect ? ' ' . html_writer::tag('span',
                get_string('review_correct_mark', 'local_sentientia_authoring'),
                ['class' => 'badge bg-success']) : '';
            $rows .= html_writer::tag('li', s($opt) . $mark);
        }
        echo html_writer::tag('ol', $rows);
    }

    if (!empty($q->qfeedback_correct)) {
        echo html_writer::tag('p', html_writer::tag('strong',
            get_string('review_feedback_correct', 'local_sentientia_authoring') . ' ') . s($q->qfeedback_correct),
            ['class' => 'small text-success']);
    }
    if (!empty($q->qfeedback_incorrect)) {
        echo html_writer::tag('p', html_writer::tag('strong',
            get_string('review_feedback_incorrect', 'local_sentientia_authoring') . ' ') . s($q->qfeedback_incorrect),
            ['class' => 'small text-danger']);
    }
    echo html_writer::tag('span', get_string('item_status_' . $q->status, 'local_sentientia_authoring'),
        ['class' => 'badge bg-secondary mb-2']);
    echo sentientia_authoring_item_buttons('reviewquestion', 'questionid', (int) $q->id, $draftid);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// ── Finalise ────────────────────────────────────────────────────────
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'finalise']);
echo html_writer::tag('button', get_string('review_finalise', 'local_sentientia_authoring'),
    ['type' => 'submit', 'class' => 'btn btn-success']);
echo html_writer::end_tag('form');

// ── Publish (gate #3 course builder; approved drafts only) ─────────
if ($draft->status === draft_manager::STATUS_APPROVED) {
    $canpublish = class_exists('\\local_sentientia_platform\\feature_flags')
        && \local_sentientia_platform\feature_flags::is_enabled(
            'sentientia.authoring.publish.enabled');
    echo html_writer::start_tag('form', ['method' => 'post',
        'action' => $PAGE->url->out(false), 'class' => 'mt-3']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'publish']);
    $categories = core_course_category::make_categories_list('moodle/course:create');
    echo html_writer::label(get_string('publish_selectcategory', 'local_sentientia_authoring'),
        'authoring-pubcat', true, ['class' => 'me-2']);
    echo html_writer::select($categories, 'categoryid', '', ['' => 'choosedots'],
        ['id' => 'authoring-pubcat', 'class' => 'me-2']);
    $attrs = ['type' => 'submit', 'class' => 'btn btn-primary'];
    if (!$canpublish) {
        $attrs['disabled'] = 'disabled';
        $attrs['title'] = get_string('publish_disabled', 'local_sentientia_authoring');
    }
    echo html_writer::tag('button',
        get_string('publish_to_course', 'local_sentientia_authoring'), $attrs);
    echo html_writer::end_tag('form');
    if (!$canpublish) {
        echo html_writer::div(get_string('publish_disabled', 'local_sentientia_authoring'),
            'form-text text-muted mt-2');
    }
}

echo $OUTPUT->footer();

/**
 * Render the approve / edit-note / reject button group for one item.
 *
 * @param string $action  reviewcard | reviewquestion
 * @param string $idfield cardid | questionid
 * @param int    $itemid
 * @param int    $draftid
 * @return string HTML
 */
function sentientia_authoring_item_buttons(string $action, string $idfield, int $itemid, int $draftid): string {
    $out = html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/sentientia_authoring/review.php', ['draftid' => $draftid]))->out(false),
        'class'  => 'd-inline-flex gap-2 flex-wrap align-items-center',
    ]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $action]);
    $out .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $idfield, 'value' => $itemid]);
    $out .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reviewer_note',
        'class' => 'form-control form-control-sm', 'style' => 'max-width:240px;',
        'placeholder' => get_string('review_note_placeholder', 'local_sentientia_authoring')]);
    foreach ([
        'approved' => 'btn-success',
        'edited'   => 'btn-warning',
        'rejected' => 'btn-danger',
    ] as $status => $cls) {
        $out .= html_writer::tag('button',
            get_string('review_btn_' . $status, 'local_sentientia_authoring'),
            ['type' => 'submit', 'name' => 'status', 'value' => $status, 'class' => "btn btn-sm {$cls}"]);
    }
    $out .= html_writer::end_tag('form');
    return $out;
}
