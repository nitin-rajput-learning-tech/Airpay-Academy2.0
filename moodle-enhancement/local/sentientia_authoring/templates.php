<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Authoring Studio — instructional-design template CRUD.
 *
 * Lists templates visible to the actor; lets them create / edit / archive
 * their own (built-ins are editable but not archivable). All writes go through
 * template_manager so tenant scope + timestamps are enforced.
 *
 * @package local_sentientia_authoring
 */

require(__DIR__ . '/../../config.php');

use local_sentientia_authoring\template_manager;

require_login();
$context = context_system::instance();

if (class_exists('\\local_sentientia_platform\\feature_flags')
        && !\local_sentientia_platform\feature_flags::is_enabled('sentientia.authoring.enabled')) {
    throw new moodle_exception('err_feature_off', 'local_sentientia_authoring');
}
require_capability('local/sentientia_authoring:managetemplates', $context);

$action = optional_param('action', 'list', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT);
$manageall = has_capability('local/sentientia_authoring:manage_all', $context);

$baseurl = new moodle_url('/local/sentientia_authoring/templates.php');
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('templates_page_title', 'local_sentientia_authoring'));
$PAGE->set_heading(get_string('templates_page_heading', 'local_sentientia_authoring'));

// ── Handle writes ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $postaction = required_param('postaction', PARAM_ALPHA);

    if ($postaction === 'save') {
        $name = trim(required_param('name', PARAM_TEXT));
        $body = required_param('body', PARAM_RAW);
        $desc = trim(optional_param('description', '', PARAM_TEXT));
        $editid = optional_param('id', 0, PARAM_INT);

        if ($editid > 0) {
            // Confirm visibility before edit.
            $existing = template_manager::load_for_actor($editid, $USER, $manageall);
            if (!$existing) {
                throw new moodle_exception('err_template_not_found', 'local_sentientia_authoring');
            }
            template_manager::update($editid, ['name' => $name, 'body' => $body, 'description' => $desc]);
            redirect($baseurl, get_string('templates_saved', 'local_sentientia_authoring'));
        }
        template_manager::create((int) $USER->id, $name, $body, $desc !== '' ? $desc : null);
        redirect($baseurl, get_string('templates_created', 'local_sentientia_authoring'));
    }

    if ($postaction === 'archive') {
        $archiveid = required_param('id', PARAM_INT);
        $existing = template_manager::load_for_actor($archiveid, $USER, $manageall);
        if (!$existing) {
            throw new moodle_exception('err_template_not_found', 'local_sentientia_authoring');
        }
        template_manager::archive($archiveid);
        redirect($baseurl, get_string('templates_archived', 'local_sentientia_authoring'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('templates_page_heading', 'local_sentientia_authoring'));
echo html_writer::div(html_writer::link(
    new moodle_url('/local/sentientia_authoring/studio.php'),
    get_string('nav_studio', 'local_sentientia_authoring'),
    ['class' => 'btn btn-outline-secondary btn-sm']), 'mb-3');

// ── Create / edit form ──────────────────────────────────────────────
if ($action === 'edit' || $action === 'new') {
    $editing = null;
    if ($action === 'edit' && $id > 0) {
        $editing = template_manager::load_for_actor($id, $USER, $manageall);
        if (!$editing) {
            throw new moodle_exception('err_template_not_found', 'local_sentientia_authoring');
        }
    }
    echo $OUTPUT->heading($editing
        ? get_string('templates_edit_heading', 'local_sentientia_authoring')
        : get_string('templates_new_heading', 'local_sentientia_authoring'), 3);

    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'mform']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'postaction', 'value' => 'save']);
    if ($editing) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int) $editing->id]);
    }

    echo html_writer::start_div('mb-3');
    echo html_writer::tag('label', get_string('templates_form_name', 'local_sentientia_authoring'),
        ['for' => 'tpl-name', 'class' => 'form-label']);
    echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'tpl-name', 'name' => 'name',
        'class' => 'form-control', 'value' => s($editing->name ?? ''), 'maxlength' => 200, 'required' => 'required']);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-3');
    echo html_writer::tag('label', get_string('templates_form_description', 'local_sentientia_authoring'),
        ['for' => 'tpl-desc', 'class' => 'form-label']);
    echo html_writer::empty_tag('input', ['type' => 'text', 'id' => 'tpl-desc', 'name' => 'description',
        'class' => 'form-control', 'value' => s($editing->description ?? '')]);
    echo html_writer::end_div();

    echo html_writer::start_div('mb-3');
    echo html_writer::tag('label', get_string('templates_form_body', 'local_sentientia_authoring'),
        ['for' => 'tpl-body', 'class' => 'form-label']);
    echo html_writer::tag('textarea', s($editing->body ?? ''),
        ['id' => 'tpl-body', 'name' => 'body', 'class' => 'form-control', 'rows' => 10, 'required' => 'required']);
    echo html_writer::div(get_string('templates_form_body_help', 'local_sentientia_authoring'), 'form-text text-muted');
    echo html_writer::end_div();

    echo html_writer::div(
        html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary me-2'])
        . html_writer::link($baseurl, get_string('cancel'), ['class' => 'btn btn-secondary']));
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    die();
}

// ── List ────────────────────────────────────────────────────────────
echo html_writer::div(html_writer::link(
    new moodle_url($baseurl, ['action' => 'new']),
    get_string('templates_new_button', 'local_sentientia_authoring'),
    ['class' => 'btn btn-primary']), 'mb-3');

$templates = template_manager::list_for_actor($USER, $manageall);
if (empty($templates)) {
    echo html_writer::div(get_string('templates_empty', 'local_sentientia_authoring'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('templates_col_name', 'local_sentientia_authoring'),
        get_string('templates_col_description', 'local_sentientia_authoring'),
        get_string('templates_col_actions', 'local_sentientia_authoring'),
    ];
    foreach ($templates as $t) {
        $name = format_string($t->name);
        if ((int) $t->is_builtin === 1) {
            $name .= ' ' . html_writer::tag('span',
                get_string('template_builtin_suffix', 'local_sentientia_authoring'),
                ['class' => 'badge bg-info text-dark']);
        }
        $actions = html_writer::link(new moodle_url($baseurl, ['action' => 'edit', 'id' => (int) $t->id]),
            get_string('edit'), ['class' => 'btn btn-sm btn-outline-secondary me-1']);
        if ((int) $t->is_builtin === 0) {
            $archiveform = html_writer::start_tag('form',
                ['method' => 'post', 'action' => $baseurl->out(false), 'class' => 'd-inline']);
            $archiveform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $archiveform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'postaction', 'value' => 'archive']);
            $archiveform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => (int) $t->id]);
            $archiveform .= html_writer::tag('button', get_string('templates_archive', 'local_sentientia_authoring'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-danger']);
            $archiveform .= html_writer::end_tag('form');
            $actions .= $archiveform;
        }
        $table->data[] = [$name, format_string((string) ($t->description ?? '')), $actions];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
