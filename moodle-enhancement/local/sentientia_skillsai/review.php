<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Sentientia LMS Skills Intelligence — candidate review gate (P0.1.0 MVP).
 *
 * This is the MANDATORY human-review gate: an AI-proposed candidate never
 * becomes canonical taxonomy until a reviewer approves/edits it here AND
 * promotes it. The reviewer can:
 *   - approve / edit / reject each candidate (POST action=review)
 *   - edit the skill name / description / category / level inline
 *   - promote approved+edited candidates into the per-tenant taxonomy and
 *     finalise the job review (POST action=finalise)
 *
 * Gated by the master feature flag + :review capability + tenant access.
 *
 * @package local_sentientia_skillsai
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_skillsai\taxonomy_manager;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.skillsai.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_skillsai');
}

require_capability('local/sentientia_skillsai:review', $context);

$jobid = required_param('jobid', PARAM_INT);
$manageall = has_capability('local/sentientia_skillsai:manage_all', $context);

$loaded = taxonomy_manager::load_for_actor($jobid, $USER, $manageall);
if ($loaded === null) {
    throw new moodle_exception('err_job_not_found', 'local_sentientia_skillsai');
}
$job = $loaded->job;

// Defence-in-depth tenant check (capability answers "may review?", this
// answers "for the right tenant?"). Site admins / manage_all pass.
if (!$manageall && class_exists('\\local_sentientia_platform\\tenant')) {
    \local_sentientia_platform\tenant::require_access((int)$job->costcenterid);
}

$PAGE->set_url('/local/sentientia_skillsai/review.php', ['jobid' => $jobid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('review_page_title', 'local_sentientia_skillsai'));
$PAGE->set_heading(get_string('review_page_heading', 'local_sentientia_skillsai'));

// ── POST handling ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $action = optional_param('action', '', PARAM_ALPHA);

    if ($action === 'review') {
        $candidateid = required_param('candidateid', PARAM_INT);
        $verdict = required_param('verdict', PARAM_ALPHA);

        // Confirm the candidate belongs to this job (no cross-job tampering).
        $belongs = false;
        foreach ($loaded->candidates as $c) {
            if ((int)$c->id === $candidateid) {
                $belongs = true;
                break;
            }
        }
        if (!$belongs) {
            throw new moodle_exception('err_candidate_not_found', 'local_sentientia_skillsai');
        }

        $updates = [];
        $name = trim(optional_param('skillname', '', PARAM_TEXT));
        if ($name !== '') {
            $updates['skillname'] = $name;
        }
        $desc = trim(optional_param('skilldescription', '', PARAM_TEXT));
        $updates['skilldescription'] = $desc;
        $cat = trim(optional_param('category', '', PARAM_TEXT));
        if ($cat !== '') {
            $updates['suggested_category'] = $cat;
        }
        $lvl = optional_param('level', 0, PARAM_INT);
        if ($lvl >= 1 && $lvl <= 5) {
            $updates['suggested_level'] = $lvl;
        }
        $note = trim(optional_param('reviewer_note', '', PARAM_TEXT));
        $updates['reviewer_note'] = $note !== '' ? $note : null;

        taxonomy_manager::review_candidate($candidateid, $verdict, $updates);

        // Auto-promote when the reviewer approves/edits.
        if (in_array($verdict, [taxonomy_manager::C_APPROVED, taxonomy_manager::C_EDITED], true)) {
            taxonomy_manager::promote_candidate($candidateid, (int)$USER->id);
        }

        redirect(new moodle_url('/local/sentientia_skillsai/review.php', ['jobid' => $jobid]),
            get_string('review_saved', 'local_sentientia_skillsai'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'finalise') {
        taxonomy_manager::finalise_review($jobid, (int)$USER->id);
        redirect(new moodle_url('/local/sentientia_skillsai/index.php'),
            get_string('review_finalised', 'local_sentientia_skillsai'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

// Reload after any POST so the rendered state is current.
$loaded = taxonomy_manager::load_for_actor($jobid, $USER, $manageall);
$job = $loaded->job;

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($job->title));

// Failed job: show the error, no candidates.
if ($job->status === taxonomy_manager::STATUS_FAILED) {
    echo html_writer::div(
        get_string('review_job_failed', 'local_sentientia_skillsai', s((string)$job->error_detail)),
        'alert alert-danger', ['role' => 'alert']);
    echo html_writer::link(new moodle_url('/local/sentientia_skillsai/index.php'),
        get_string('back_to_queue', 'local_sentientia_skillsai'), ['class' => 'btn btn-secondary']);
    echo $OUTPUT->footer();
    return;
}

echo html_writer::div(get_string('review_intro', 'local_sentientia_skillsai'), 'mb-3 text-muted');

$categories = \local_sentientia_skillsai\response_parser::CATEGORIES;

foreach ($loaded->candidates as $cand) {
    $promoted = !empty($cand->taxonomyid);
    $statuslabel = get_string('candstatus_' . $cand->status, 'local_sentientia_skillsai');

    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');

    echo html_writer::tag('div',
        html_writer::tag('span', s($statuslabel), ['class' => 'badge bg-secondary me-2']) .
        ($promoted ? html_writer::tag('span', get_string('cand_promoted', 'local_sentientia_skillsai'),
            ['class' => 'badge bg-success']) : '') .
        html_writer::tag('span',
            get_string('cand_confidence', 'local_sentientia_skillsai', format_float($cand->confidence, 2)),
            ['class' => 'badge bg-light text-dark ms-2']),
        ['class' => 'mb-2']);

    // Evidence (read-only, grounding).
    if (!empty($cand->evidence)) {
        echo html_writer::tag('blockquote', s($cand->evidence),
            ['class' => 'blockquote small text-muted border-start ps-2']);
    }

    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $PAGE->url->out(false),
        'class'  => 'mb-0',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'review']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'candidateid', 'value' => (int)$cand->id]);

    // Editable name.
    echo html_writer::start_div('mb-2');
    echo html_writer::tag('label', get_string('cand_name', 'local_sentientia_skillsai'),
        ['class' => 'form-label small fw-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'skillname', 'class' => 'form-control',
        'value' => s($cand->skillname), 'maxlength' => 200,
    ]);
    echo html_writer::end_div();

    // Editable description.
    echo html_writer::start_div('mb-2');
    echo html_writer::tag('label', get_string('cand_description', 'local_sentientia_skillsai'),
        ['class' => 'form-label small fw-bold']);
    echo html_writer::tag('textarea', s((string)$cand->skilldescription),
        ['name' => 'skilldescription', 'class' => 'form-control', 'rows' => 2]);
    echo html_writer::end_div();

    // Category + level.
    echo html_writer::start_div('row');
    echo html_writer::start_div('col mb-2');
    echo html_writer::tag('label', get_string('cand_category', 'local_sentientia_skillsai'),
        ['class' => 'form-label small fw-bold']);
    $catoptions = array_combine($categories, $categories);
    echo html_writer::select($catoptions, 'category', $cand->suggested_category, false,
        ['class' => 'form-control']);
    echo html_writer::end_div();
    echo html_writer::start_div('col mb-2');
    echo html_writer::tag('label', get_string('cand_level', 'local_sentientia_skillsai'),
        ['class' => 'form-label small fw-bold']);
    echo html_writer::select([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5], 'level',
        (int)$cand->suggested_level, false, ['class' => 'form-control']);
    echo html_writer::end_div();
    echo html_writer::end_div();

    // Reviewer note.
    echo html_writer::start_div('mb-2');
    echo html_writer::tag('label', get_string('cand_note', 'local_sentientia_skillsai'),
        ['class' => 'form-label small fw-bold']);
    echo html_writer::empty_tag('input', [
        'type' => 'text', 'name' => 'reviewer_note', 'class' => 'form-control',
        'value' => s((string)$cand->reviewer_note),
    ]);
    echo html_writer::end_div();

    // Verdict buttons.
    echo html_writer::div(
        html_writer::tag('button', get_string('verdict_approve', 'local_sentientia_skillsai'),
            ['type' => 'submit', 'name' => 'verdict', 'value' => taxonomy_manager::C_APPROVED,
             'class' => 'btn btn-sm btn-success me-2']) .
        html_writer::tag('button', get_string('verdict_edit', 'local_sentientia_skillsai'),
            ['type' => 'submit', 'name' => 'verdict', 'value' => taxonomy_manager::C_EDITED,
             'class' => 'btn btn-sm btn-primary me-2']) .
        html_writer::tag('button', get_string('verdict_reject', 'local_sentientia_skillsai'),
            ['type' => 'submit', 'name' => 'verdict', 'value' => taxonomy_manager::C_REJECTED,
             'class' => 'btn btn-sm btn-outline-danger']),
        '');

    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// Finalise.
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'finalise']);
echo html_writer::div(
    html_writer::tag('button', get_string('review_finalise_submit', 'local_sentientia_skillsai'),
        ['type' => 'submit', 'class' => 'btn btn-primary me-2']) .
    html_writer::link(new moodle_url('/local/sentientia_skillsai/index.php'),
        get_string('back_to_queue', 'local_sentientia_skillsai'),
        ['class' => 'btn btn-secondary']),
    '');
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
