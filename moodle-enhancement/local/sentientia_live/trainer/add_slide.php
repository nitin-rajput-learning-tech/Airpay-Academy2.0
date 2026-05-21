<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Add a new slide to a session — Phase E.1.j.
 *
 * Two-step flow:
 *   1. ?sessionid=N  (no type yet) → type picker page. Lists the 6
 *                    question types, gated on their respective feature
 *                    flags. Each card POSTs/links to step 2.
 *   2. ?sessionid=N&type=multichoice  →  shows slide_form for that
 *                    type. On submit, calls slide_manager::add() and
 *                    returns to /trainer/edit.php?id=N.
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

$sessionid = required_param('sessionid', PARAM_INT);
$type      = optional_param('type', '', PARAM_ALPHA);

$dashboard  = new \moodle_url('/local/sentientia_live/trainer/index.php');
$editurl    = new \moodle_url('/local/sentientia_live/trainer/edit.php',
    ['id' => $sessionid]);

$sess = \local_sentientia_live\session_manager::get($sessionid);
if (!$sess) {
    redirect($dashboard,
        get_string('invalidsession', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}
if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $sessionid)) {
    redirect($dashboard,
        get_string('cannot_edit_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}
if ($sess->state !== \local_sentientia_live\session_manager::STATE_DRAFT) {
    redirect($editurl,
        get_string('cannot_edit_live_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_WARNING);
}

$PAGE->set_url('/local/sentientia_live/trainer/add_slide.php',
    array_filter(['sessionid' => $sessionid, 'type' => $type]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_heading(format_string($sess->title));

// ── Step 1: no type yet — render the type picker ─────────────────────
if ($type === '') {
    $PAGE->set_title(get_string('add_slide_pagetitle', 'local_sentientia_live'));

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('add_slide_pick_type_heading',
        'local_sentientia_live'));
    echo \html_writer::tag('p',
        get_string('add_slide_pick_type_intro', 'local_sentientia_live'),
        ['class' => 'text-muted mb-4']);

    $available = [];
    foreach (\local_sentientia_live\slide_manager::VALID_TYPES as $t) {
        $flag = 'live.questiontype.' . $t;
        $is_on = true;
        if (class_exists('\\local_airpay_core\\feature_flags')) {
            try {
                $is_on = \local_airpay_core\feature_flags::is_enabled($flag);
            } catch (\Throwable $e) {
                $is_on = false;
            }
        }
        if ($is_on) {
            $available[] = $t;
        }
    }

    if (empty($available)) {
        echo \html_writer::tag('div',
            get_string('no_slide_types_enabled', 'local_sentientia_live'),
            ['class' => 'alert alert-warning']);
        echo \html_writer::link($editurl->out(false),
            get_string('back_to_session', 'local_sentientia_live'),
            ['class' => 'btn btn-secondary']);
    } else {
        echo \html_writer::start_div('row g-3');
        foreach ($available as $t) {
            $url = new \moodle_url('/local/sentientia_live/trainer/add_slide.php',
                ['sessionid' => $sessionid, 'type' => $t]);
            echo \html_writer::start_div('col-md-6 col-lg-4');
            echo \html_writer::start_div('card h-100');
            echo \html_writer::start_div('card-body');
            echo \html_writer::tag('h5',
                get_string('slide_type_' . $t, 'local_sentientia_live'),
                ['class' => 'card-title']);
            echo \html_writer::tag('p',
                get_string('slide_type_' . $t . '_desc',
                    'local_sentientia_live'),
                ['class' => 'card-text small text-muted']);
            echo \html_writer::link($url->out(false),
                get_string('use_this_type', 'local_sentientia_live'),
                ['class' => 'btn btn-primary btn-sm']);
            echo \html_writer::end_div();
            echo \html_writer::end_div();
            echo \html_writer::end_div();
        }
        echo \html_writer::end_div();
    }

    echo $OUTPUT->footer();
    exit;
}

// ── Step 2: type chosen — render the slide_form ─────────────────────
if (!in_array($type, \local_sentientia_live\slide_manager::VALID_TYPES, true)) {
    redirect($editurl,
        get_string('invalidslidetype', 'local_sentientia_live', $type),
        null, \core\output\notification::NOTIFY_ERROR);
}

$PAGE->set_title(get_string('add_slide_form_pagetitle', 'local_sentientia_live'));

$form = new \local_sentientia_live\forms\slide_form(
    $PAGE->url->out(false), [
        'type'      => $type,
        'sessionid' => $sessionid,
        'slideid'   => 0,
    ]
);

if ($form->is_cancelled()) {
    redirect($editurl);
}

if ($data = $form->get_data()) {
    $settings = \local_sentientia_live\forms\slide_form::build_settings_from_form_data(
        (array) $data, $type);

    try {
        $new_id = \local_sentientia_live\slide_manager::add(
            $sessionid,
            $type,
            (string) $data->title,
            $settings
        );

        // If this is the first slide of a draft session, default it to
        // current_slide_id so "start" has a slide to show.
        if ($sess->current_slide_id === null) {
            global $DB;
            $DB->set_field('local_sentientia_live_sessions',
                'current_slide_id', $new_id, ['id' => $sessionid]);
        }

        redirect($editurl,
            get_string('slide_added_notice', 'local_sentientia_live'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('add_slide_form_heading',
    'local_sentientia_live',
    get_string('slide_type_' . $type, 'local_sentientia_live')));

$form->display();

echo $OUTPUT->footer();
