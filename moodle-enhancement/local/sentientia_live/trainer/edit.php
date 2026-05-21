<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Edit a live session — Phase E.1.i (minimal placeholder).
 *
 * GET ?id=N — shows the session settings form (re-using session_form
 * from the create page) for updates + a list of existing slides with
 * their type + position. The actual slide-add/edit interface (with
 * per-type subforms) lands in Phase E.1.j; this page links to a
 * "coming soon" notice for that flow.
 *
 * Lets trainers:
 *   - Rename a session
 *   - Tweak the audience settings (anonymous / late join / etc)
 *   - START a draft session (only available when state=draft AND
 *     slides count >= 1 — guard for "empty session" UX confusion)
 *   - VIEW (not edit) slides of an ended session
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:create', $context);

if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id = required_param('id', PARAM_INT);
$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');

$sess = \local_sentientia_live\session_manager::get($id);
if (!$sess) {
    redirect($dashboard,
        get_string('invalidsession', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $id)) {
    redirect($dashboard,
        get_string('cannot_edit_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_url('/local/sentientia_live/trainer/edit.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('edit_session_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(format_string($sess->title));

$is_editable = $sess->state === \local_sentientia_live\session_manager::STATE_DRAFT;
$is_live     = $sess->state === \local_sentientia_live\session_manager::STATE_LIVE;
$is_ended    = $sess->state === \local_sentientia_live\session_manager::STATE_ENDED;

// ── Settings form (only editable while in draft state) ──
$settings_array = \local_sentientia_live\session_manager::parse_settings($sess);
$form = new \local_sentientia_live\forms\session_form($PAGE->url->out(false), null, 'post', '', null,
    $is_editable);

$form->set_data([
    'id'                       => $id,
    'title'                    => $sess->title,
    'allow_anonymous'          => $settings_array['allow_anonymous'] ? 1 : 0,
    'show_results_to_audience' => $settings_array['show_results_to_audience'] ? 1 : 0,
    'allow_late_join'          => $settings_array['allow_late_join'] ? 1 : 0,
    'max_concurrent'           => $settings_array['max_concurrent'],
]);

if ($form->is_cancelled()) {
    redirect($dashboard);
}

if ($data = $form->get_data()) {
    if (!$is_editable) {
        redirect($PAGE->url,
            get_string('cannot_edit_live_session', 'local_sentientia_live'),
            null, \core\output\notification::NOTIFY_WARNING);
    }

    // Update title + settings in one transaction.
    global $DB;
    $new_settings = [
        'allow_anonymous'          => !empty($data->allow_anonymous),
        'show_results_to_audience' => !empty($data->show_results_to_audience),
        'allow_late_join'          => !empty($data->allow_late_join),
        'max_concurrent'           => (int) ($data->max_concurrent ?? 500),
    ];
    $new_settings = \local_sentientia_live\session_manager::sanitise_settings($new_settings);

    $DB->update_record('local_sentientia_live_sessions', (object) [
        'id'            => $id,
        'title'         => trim((string) $data->title),
        'settings_json' => json_encode($new_settings),
        'timemodified'  => time(),
    ]);

    redirect($PAGE->url,
        get_string('session_updated_notice', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($sess->title));

// ── State banner ──
$state_label = get_string('state_' . $sess->state, 'local_sentientia_live');
$code_pretty = substr($sess->code, 0, 3) . ' ' . substr($sess->code, 3);
$banner_class = $is_live ? 'alert-success' : ($is_ended ? 'alert-secondary' : 'alert-info');
echo \html_writer::start_div("alert {$banner_class} d-flex justify-content-between align-items-center");
echo \html_writer::tag('div',
    '<strong>' . get_string('state_label', 'local_sentientia_live') . ':</strong> '
    . '<span class="ms-2">' . s($state_label) . '</span>'
    . ' &middot; <strong>' . get_string('code_label', 'local_sentientia_live') . ':</strong> '
    . '<code class="ms-2 fs-5">' . s($code_pretty) . '</code>'
);
if ($is_draft = ($sess->state === \local_sentientia_live\session_manager::STATE_DRAFT)) {
    $slide_count = \local_sentientia_live\slide_manager::count_for_session($id);
    if ($slide_count > 0) {
        $start_url = new \moodle_url('/local/sentientia_live/trainer/start.php',
            ['id' => $id, 'sesskey' => sesskey()]);
        echo \html_writer::link($start_url->out(false),
            get_string('action_start_session', 'local_sentientia_live'),
            ['class' => 'btn btn-primary']);
    } else {
        echo \html_writer::tag('em',
            get_string('add_slide_to_start', 'local_sentientia_live'),
            ['class' => 'text-muted']);
    }
}
if ($is_live) {
    $run_url = new \moodle_url('/local/sentientia_live/trainer/run.php', ['id' => $id]);
    echo \html_writer::link($run_url->out(false),
        get_string('action_run', 'local_sentientia_live'),
        ['class' => 'btn btn-success']);
}
echo \html_writer::end_div();

// ── Slides section ──
echo \html_writer::start_div('d-flex justify-content-between align-items-center mt-4');
echo \html_writer::tag('h3',
    get_string('slides_heading', 'local_sentientia_live'),
    ['class' => 'h5 mb-0']);
if ($is_editable) {
    $add_url = new \moodle_url('/local/sentientia_live/trainer/add_slide.php',
        ['sessionid' => $id]);
    echo \html_writer::link($add_url->out(false),
        '<i class="fa fa-plus me-1"></i>' .
            get_string('action_add_slide', 'local_sentientia_live'),
        ['class' => 'btn btn-primary btn-sm']);
}
echo \html_writer::end_div();

$slides = array_values(\local_sentientia_live\slide_manager::list_for_session($id));
if (empty($slides)) {
    echo \html_writer::tag('p',
        get_string('no_slides_yet', 'local_sentientia_live'),
        ['class' => 'text-muted mt-3']);
} else {
    $total = count($slides);
    echo \html_writer::start_tag('div', ['class' => 'list-group mt-3']);
    foreach ($slides as $i => $slide) {
        $is_current = ((int) $sess->current_slide_id) === (int) $slide->id;

        echo \html_writer::start_div(
            'list-group-item d-flex justify-content-between align-items-center'
            . ($is_current ? ' border-primary border-2' : ''));

        // Left — number, title, type badge.
        echo \html_writer::start_div('flex-grow-1');
        echo \html_writer::tag('span',
            ((int) $slide->position) . '.',
            ['class' => 'text-muted me-2']);
        echo \html_writer::tag('strong', format_string($slide->title));
        echo ' ' . \html_writer::tag('span',
            get_string('slide_type_' . $slide->type, 'local_sentientia_live'),
            ['class' => 'badge bg-light text-dark ms-2']);
        if ($is_current) {
            echo ' ' . \html_writer::tag('span',
                get_string('badge_current_slide', 'local_sentientia_live'),
                ['class' => 'badge bg-primary ms-2']);
        }
        echo \html_writer::end_div();

        // Right — action buttons.
        if ($is_editable) {
            echo \html_writer::start_div('btn-group btn-group-sm ms-2');

            // Up.
            if ($i > 0) {
                $up_url = new \moodle_url(
                    '/local/sentientia_live/trainer/move_slide.php',
                    ['id' => (int) $slide->id, 'direction' => 'up',
                     'sesskey' => sesskey()]);
                echo \html_writer::link($up_url->out(false),
                    '<i class="fa fa-arrow-up"></i>',
                    ['class' => 'btn btn-outline-secondary',
                     'title' => get_string('action_move_up',
                        'local_sentientia_live')]);
            }
            // Down.
            if ($i < $total - 1) {
                $down_url = new \moodle_url(
                    '/local/sentientia_live/trainer/move_slide.php',
                    ['id' => (int) $slide->id, 'direction' => 'down',
                     'sesskey' => sesskey()]);
                echo \html_writer::link($down_url->out(false),
                    '<i class="fa fa-arrow-down"></i>',
                    ['class' => 'btn btn-outline-secondary',
                     'title' => get_string('action_move_down',
                        'local_sentientia_live')]);
            }
            // Edit.
            $edit_slide_url = new \moodle_url(
                '/local/sentientia_live/trainer/edit_slide.php',
                ['id' => (int) $slide->id]);
            echo \html_writer::link($edit_slide_url->out(false),
                get_string('action_edit', 'local_sentientia_live'),
                ['class' => 'btn btn-outline-primary']);
            // Delete.
            $del_url = new \moodle_url(
                '/local/sentientia_live/trainer/delete_slide.php',
                ['id' => (int) $slide->id, 'sesskey' => sesskey()]);
            echo \html_writer::link($del_url->out(false),
                '<i class="fa fa-trash"></i>',
                ['class' => 'btn btn-outline-danger',
                 'title' => get_string('action_delete_slide',
                    'local_sentientia_live')]);
            echo \html_writer::end_div();
        } else if ($is_live) {
            // While live: trainer can set this as current.
            if (!$is_current) {
                $set_url = new \moodle_url(
                    '/local/sentientia_live/trainer/set_current.php',
                    ['id' => (int) $slide->id, 'sesskey' => sesskey()]);
                echo \html_writer::link($set_url->out(false),
                    get_string('action_show_now', 'local_sentientia_live'),
                    ['class' => 'btn btn-sm btn-outline-primary']);
            }
        }

        echo \html_writer::end_div();
    }
    echo \html_writer::end_tag('div');
}

// ── Settings form (or read-only summary for ended sessions) ──
if ($is_editable) {
    echo \html_writer::tag('h3',
        get_string('settings_heading_inline', 'local_sentientia_live'),
        ['class' => 'h5 mt-4']);
    $form->display();
}

echo $OUTPUT->footer();
