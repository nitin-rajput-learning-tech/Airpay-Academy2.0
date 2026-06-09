<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS AI Quiz — Review page (Phase G.0 MVP).
 *
 * Reviewer flow:
 *   GET  ?draftid=N : show all questions in the draft with per-question
 *                     Approve / Edit / Reject controls + the draft's
 *                     status banner.
 *
 *   POST action=review_question : flip a single question's status
 *                                  (and optionally save edits)
 *   POST action=finalise        : mark the draft fully reviewed
 *   POST action=push            : push approved questions to mod_quiz
 *                                  (gated behind sentientia.aiquiz.auto_push)
 *
 * Multi-tenant safety: load_for_actor() refuses to return a draft that
 * belongs to a different customer/tenant unless the actor has
 * :manage_all. So a Public-tenant reviewer can't accidentally approve
 * an Airpay draft via URL guessing.
 *
 * @package local_sentientia_aiquiz
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_aiquiz\draft_manager;

require_login();
$context = context_system::instance();

// Gate — feature flag.
if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_aiquiz');
}

require_capability('local/sentientia_aiquiz:review', $context);

$draftid = optional_param('draftid', 0, PARAM_INT);
$manageall = has_capability('local/sentientia_aiquiz:manage_all', $context);

// ──────────────────────────────────────────────────────────────────
// LIST MODE — no draftid in URL => show the actor's drafts list.
// ──────────────────────────────────────────────────────────────────
if ($draftid <= 0) {
    $PAGE->set_url('/local/sentientia_aiquiz/review.php');
    $PAGE->set_context($context);
    $PAGE->set_pagelayout('standard');
    $PAGE->set_title(get_string('drafts_list_title', 'local_sentientia_aiquiz'));
    $PAGE->set_heading(get_string('drafts_list_title', 'local_sentientia_aiquiz'));

    $drafts = draft_manager::list_for_actor($USER, $manageall, 50);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('drafts_list_title', 'local_sentientia_aiquiz'));

    if (empty($drafts)) {
        echo html_writer::div(
            get_string('review_empty_state', 'local_sentientia_aiquiz'),
            'alert alert-info');
        echo html_writer::link(
            new moodle_url('/local/sentientia_aiquiz/generate.php'),
            get_string('nav_generate', 'local_sentientia_aiquiz'),
            ['class' => 'btn btn-primary']);
        echo $OUTPUT->footer();
        exit;
    }

    $table = new html_table();
    $table->head = [
        '#',
        get_string('generate_form_title', 'local_sentientia_aiquiz'),
        get_string('review_status', 'local_sentientia_aiquiz'),
        get_string('review_meta_owner', 'local_sentientia_aiquiz'),
        get_string('review_meta_generated_at', 'local_sentientia_aiquiz'),
        '',
    ];
    $table->attributes['class'] = 'generaltable';

    foreach ($drafts as $d) {
        $statuskey = 'review_status_' . $d->status;
        // Pushed draft displays the quiz id.
        if ($d->status === draft_manager::STATUS_PUSHED) {
            $statuslabel = get_string($statuskey, 'local_sentientia_aiquiz', (int)$d->pushed_quizid);
        } else {
            $statuslabel = get_string($statuskey, 'local_sentientia_aiquiz');
        }
        $owner = $DB->get_record('user', ['id' => $d->ownerid], 'firstname, lastname');
        $reviewlink = html_writer::link(
            new moodle_url('/local/sentientia_aiquiz/review.php', ['draftid' => $d->id]),
            get_string('nav_review', 'local_sentientia_aiquiz'),
            ['class' => 'btn btn-sm btn-outline-primary']);
        $table->data[] = [
            (int)$d->id,
            format_string($d->title),
            $statuslabel,
            $owner ? format_string($owner->firstname . ' ' . $owner->lastname) : '—',
            userdate($d->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $reviewlink,
        ];
    }
    echo html_writer::table($table);

    echo html_writer::link(
        new moodle_url('/local/sentientia_aiquiz/generate.php'),
        get_string('nav_generate', 'local_sentientia_aiquiz'),
        ['class' => 'btn btn-primary mt-3']);
    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────────────────────────
// DETAIL MODE — render a single draft + handle per-action POSTs.
// ──────────────────────────────────────────────────────────────────
$loaded = draft_manager::load_for_actor($draftid, $USER, $manageall);
if (!$loaded) {
    throw new moodle_exception('review_no_draft', 'local_sentientia_aiquiz');
}
$draft = $loaded->draft;
$questions = $loaded->questions;

$PAGE->set_url('/local/sentientia_aiquiz/review.php', ['draftid' => $draftid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('review_page_title', 'local_sentientia_aiquiz'));
$PAGE->set_heading(get_string('review_page_heading', 'local_sentientia_aiquiz', (int)$draft->id));

$notice = null;

// POST handlers.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHANUMEXT);

    if ($action === 'review_question') {
        $qid = required_param('qid', PARAM_INT);
        $newstatus = required_param('newstatus', PARAM_ALPHA);
        // Confirm the question belongs to this draft (defence-in-depth).
        $belongs = false;
        foreach ($questions as $q) {
            if ((int)$q->id === $qid) {
                $belongs = true;
                break;
            }
        }
        if (!$belongs) {
            throw new moodle_exception('review_no_draft', 'local_sentientia_aiquiz');
        }
        $updates = [];
        if ($newstatus === draft_manager::Q_STATUS_EDITED) {
            // Save the edited fields.
            $newqtext = trim(optional_param('qtext', '', PARAM_TEXT));
            if ($newqtext !== '') {
                $updates['qtext'] = $newqtext;
            }
            $newexpl = trim(optional_param('qexplanation', '', PARAM_TEXT));
            $updates['qexplanation'] = $newexpl;
            // Options come as opt0..opt3 + new_answer_index.
            $opts = [];
            for ($i = 0; $i < 4; $i++) {
                $opts[] = trim(optional_param('opt' . $i, '', PARAM_TEXT));
            }
            if (count(array_filter($opts, function($x) { return $x !== ''; })) === 4) {
                $updates['qoptions_json'] = json_encode($opts, JSON_UNESCAPED_UNICODE);
            }
            $newansidx = optional_param('new_answer_index', -1, PARAM_INT);
            if ($newansidx >= 0 && $newansidx <= 3) {
                $updates['qanswer'] = (string)$newansidx;
            }
        }
        $note = trim(optional_param('reviewer_note', '', PARAM_TEXT));
        if ($note !== '') {
            $updates['reviewer_note'] = $note;
        }

        draft_manager::review_question($qid, $newstatus, $updates);
        redirect($PAGE->url);
    }

    if ($action === 'finalise') {
        $newstatus = draft_manager::finalise_review($draftid, (int)$USER->id);
        $notice = ['msg' => "Draft finalised. New status: {$newstatus}", 'level' => \core\output\notification::NOTIFY_SUCCESS];
        redirect($PAGE->url, $notice['msg'], null, $notice['level']);
    }

    if ($action === 'push') {
        // Gate behind sentientia.aiquiz.auto_push.
        $autopush = class_exists('\\local_sentientia_platform\\feature_flags')
            && \local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.auto_push');
        if (!$autopush) {
            redirect($PAGE->url,
                get_string('review_push_disabled', 'local_sentientia_aiquiz'),
                null,
                \core\output\notification::NOTIFY_WARNING);
        }
        // Phase G.0: stub — leaves a structured log entry, marks the draft as
        // pushed pointing to quiz id 0 (no real quiz yet). Real mod_quiz
        // creation lands in Phase G.4.
        $approved = 0;
        foreach ($questions as $q) {
            if ($q->status === draft_manager::Q_STATUS_APPROVED
                || $q->status === draft_manager::Q_STATUS_EDITED) {
                $approved++;
            }
        }
        draft_manager::mark_pushed($draftid, 0);
        redirect($PAGE->url,
            get_string('review_push_success', 'local_sentientia_aiquiz', (object)[
                'quizid' => 0, 'count' => $approved,
            ]),
            null,
            \core\output\notification::NOTIFY_SUCCESS);
    }
}

// ──────────────────────────────────────────────────────────────────
// Render
// ──────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('review_page_heading', 'local_sentientia_aiquiz', (int)$draft->id));
echo html_writer::div(get_string('review_intro', 'local_sentientia_aiquiz'), 'mb-3 text-muted');

// Meta panel.
$owner = $DB->get_record('user', ['id' => $draft->ownerid], 'firstname, lastname, email');
$course = $draft->courseid > 0
    ? $DB->get_record('course', ['id' => $draft->courseid], 'fullname, shortname')
    : null;
$metarows = [
    [get_string('review_meta_owner', 'local_sentientia_aiquiz'),
        $owner ? format_string($owner->firstname . ' ' . $owner->lastname) : '—'],
    [get_string('review_meta_course', 'local_sentientia_aiquiz'),
        $course ? format_string($course->fullname) : get_string('generate_form_course_none', 'local_sentientia_aiquiz')],
    [get_string('review_meta_model', 'local_sentientia_aiquiz'), s($draft->model)],
    [get_string('review_meta_prompt', 'local_sentientia_aiquiz'), s($draft->prompt_version)],
    [get_string('review_meta_tokens', 'local_sentientia_aiquiz'),
        (int)$draft->tokens_in . ' / ' . (int)$draft->tokens_out],
    [get_string('review_meta_generated_at', 'local_sentientia_aiquiz'),
        $draft->generated_at > 0 ? userdate($draft->generated_at, get_string('strftimedatetimeshort', 'core_langconfig')) : '—'],
    [get_string('review_meta_mode', 'local_sentientia_aiquiz'),
        $draft->tokens_out > 0 ? 'live' : 'mock'],
];
$metatable = new html_table();
$metatable->attributes['class'] = 'generaltable';
foreach ($metarows as $r) {
    $row = new html_table_row([
        new html_table_cell(html_writer::tag('strong', s($r[0]))),
        new html_table_cell(s($r[1])),
    ]);
    $metatable->data[] = $row;
}
echo html_writer::table($metatable);

// Status banner.
$statuskey = 'review_status_' . $draft->status;
if ($draft->status === draft_manager::STATUS_PUSHED) {
    $statuslabel = get_string($statuskey, 'local_sentientia_aiquiz', (int)$draft->pushed_quizid);
} else {
    $statuslabel = get_string($statuskey, 'local_sentientia_aiquiz');
}
$statusclass = 'alert-info';
if ($draft->status === draft_manager::STATUS_APPROVED) {
    $statusclass = 'alert-success';
} else if ($draft->status === draft_manager::STATUS_FAILED || $draft->status === draft_manager::STATUS_REJECTED) {
    $statusclass = 'alert-danger';
} else if ($draft->status === draft_manager::STATUS_PUSHED) {
    $statusclass = 'alert-primary';
}
echo html_writer::div(
    html_writer::tag('strong', get_string('review_status', 'local_sentientia_aiquiz') . ': ') . s($statuslabel),
    'alert ' . $statusclass);

if (!empty($draft->error_detail)) {
    echo html_writer::div(s($draft->error_detail), 'alert alert-warning small');
}

// Questions list.
if (empty($questions)) {
    echo html_writer::div(get_string('review_no_questions', 'local_sentientia_aiquiz'), 'alert alert-warning');
} else {
    foreach ($questions as $idx => $q) {
        echo render_question_card($q, $idx + 1, $PAGE->url);
    }
}

// Finalise + push buttons.
if ($draft->status === draft_manager::STATUS_GENERATED && !empty($questions)) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class' => 'd-inline-block me-2',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'finalise']);
    echo html_writer::tag('button',
        get_string('review_finalise', 'local_sentientia_aiquiz'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo html_writer::div(get_string('review_finalise_help', 'local_sentientia_aiquiz'),
        'form-text text-muted mt-2');
}

if ($draft->status === draft_manager::STATUS_APPROVED) {
    $autopush = class_exists('\\local_sentientia_platform\\feature_flags')
        && \local_sentientia_platform\feature_flags::is_enabled('sentientia.aiquiz.auto_push');
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class' => 'd-inline-block me-2 mt-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'push']);
    $pushattrs = ['type' => 'submit', 'class' => 'btn btn-success'];
    if (!$autopush) {
        $pushattrs['disabled'] = 'disabled';
        $pushattrs['title'] = get_string('review_push_disabled', 'local_sentientia_aiquiz');
    }
    echo html_writer::tag('button',
        get_string('review_push_to_quiz', 'local_sentientia_aiquiz'),
        $pushattrs);
    echo html_writer::end_tag('form');
    if (!$autopush) {
        echo html_writer::div(get_string('review_push_disabled', 'local_sentientia_aiquiz'),
            'form-text text-muted mt-2');
    }
}

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/sentientia_aiquiz/review.php'),
        get_string('back_to_drafts', 'local_sentientia_aiquiz'),
        ['class' => 'btn btn-link mt-3']),
    'mt-3');

echo $OUTPUT->footer();

/**
 * Render a single question card with per-question controls.
 *
 * @param \stdClass $q
 * @param int $number 1-based display index
 * @param \moodle_url $formaction
 * @return string HTML
 */
function render_question_card(\stdClass $q, int $number, \moodle_url $formaction): string {
    $options = [];
    if (!empty($q->qoptions_json)) {
        $decoded = json_decode($q->qoptions_json, true);
        if (is_array($decoded)) {
            $options = array_values($decoded);
        }
    }
    $answeridx = (int)$q->qanswer;

    $statuskey = 'review_q_status_' . $q->status;
    $statuslabel = get_string($statuskey, 'local_sentientia_aiquiz');
    $statusclass = 'badge bg-secondary';
    if ($q->status === \local_sentientia_aiquiz\draft_manager::Q_STATUS_APPROVED) {
        $statusclass = 'badge bg-success';
    } else if ($q->status === \local_sentientia_aiquiz\draft_manager::Q_STATUS_EDITED) {
        $statusclass = 'badge bg-info';
    } else if ($q->status === \local_sentientia_aiquiz\draft_manager::Q_STATUS_REJECTED) {
        $statusclass = 'badge bg-danger';
    }

    $out  = html_writer::start_div('card mb-3 sentientia-aiquiz-q', ['data-questionid' => (int)$q->id]);
    $out .= html_writer::start_div('card-header d-flex align-items-center justify-content-between');
    $out .= html_writer::tag('span',
        get_string('review_question_label', 'local_sentientia_aiquiz', $number),
        ['class' => 'fw-bold']);
    $out .= html_writer::tag('span', s($statuslabel), ['class' => $statusclass]);
    $out .= html_writer::end_div();

    $out .= html_writer::start_div('card-body');
    $out .= html_writer::tag('p', format_text((string)$q->qtext, FORMAT_PLAIN),
        ['class' => 'lead']);

    if (!empty($options)) {
        $out .= html_writer::start_tag('ol', ['class' => 'list-group list-group-numbered mb-2']);
        foreach ($options as $i => $opt) {
            $isans = ($i === $answeridx);
            $itemattrs = ['class' => 'list-group-item d-flex justify-content-between align-items-center'];
            if ($isans) {
                $itemattrs['class'] .= ' list-group-item-success';
            }
            $marker = $isans
                ? html_writer::tag('span',
                    get_string('review_question_answer', 'local_sentientia_aiquiz'),
                    ['class' => 'badge bg-success rounded-pill'])
                : '';
            $out .= html_writer::tag('li', s($opt) . ' ' . $marker, $itemattrs);
        }
        $out .= html_writer::end_tag('ol');
    }

    if (!empty($q->qexplanation)) {
        $out .= html_writer::div(
            html_writer::tag('strong',
                get_string('review_question_explanation', 'local_sentientia_aiquiz') . ': ') .
            format_text((string)$q->qexplanation, FORMAT_PLAIN),
            'small text-muted mt-2');
    }

    // Per-question action form (approve / edit / reject).
    $out .= html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $formaction->out(false),
        'class' => 'd-flex flex-wrap gap-2 mt-3 sentientia-aiquiz-q-actions',
    ]);
    $out .= html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $out .= html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'action', 'value' => 'review_question']);
    $out .= html_writer::empty_tag('input',
        ['type' => 'hidden', 'name' => 'qid', 'value' => (int)$q->id]);

    $approveattrs = ['type' => 'submit', 'name' => 'newstatus',
        'value' => \local_sentientia_aiquiz\draft_manager::Q_STATUS_APPROVED,
        'class' => 'btn btn-sm btn-success'];
    $rejectattrs = ['type' => 'submit', 'name' => 'newstatus',
        'value' => \local_sentientia_aiquiz\draft_manager::Q_STATUS_REJECTED,
        'class' => 'btn btn-sm btn-outline-danger'];
    $out .= html_writer::tag('button',
        get_string('review_action_approve', 'local_sentientia_aiquiz'),
        $approveattrs);
    $out .= html_writer::tag('button',
        get_string('review_action_reject', 'local_sentientia_aiquiz'),
        $rejectattrs);

    $out .= html_writer::end_tag('form');

    $out .= html_writer::end_div();
    $out .= html_writer::end_div();

    return $out;
}
