<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Edit an existing slide — Phase E.1.j.
 *
 * GET ?id=N — looks up slide, renders slide_form pre-populated with
 * the slide's current title + settings. On submit, calls
 * slide_manager::update() and returns to /trainer/edit.php for the
 * parent session.
 *
 * Type cannot be changed — that would invalidate any responses already
 * submitted. Delete + re-add if the type is wrong.
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

$slide = \local_sentientia_live\slide_manager::get($id);
if (!$slide) {
    redirect($dashboard,
        get_string('invalidslide', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$sess = \local_sentientia_live\session_manager::get((int) $slide->sessionid);
if (!$sess) {
    redirect($dashboard,
        get_string('invalidsession', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}
if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, (int) $sess->id)) {
    redirect($dashboard,
        get_string('cannot_edit_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$editurl = new \moodle_url('/local/sentientia_live/trainer/edit.php',
    ['id' => (int) $sess->id]);

if ($sess->state !== \local_sentientia_live\session_manager::STATE_DRAFT) {
    redirect($editurl,
        get_string('cannot_edit_live_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_WARNING);
}

$PAGE->set_url('/local/sentientia_live/trainer/edit_slide.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('edit_slide_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(format_string($sess->title));

$form = new \local_sentientia_live\forms\slide_form(
    $PAGE->url->out(false), [
        'type'      => $slide->type,
        'sessionid' => (int) $sess->id,
        'slideid'   => $id,
    ]
);

// Pre-fill from the current slide.
$current_settings = \local_sentientia_live\slide_manager::parse_settings($slide);
$prefill = [
    'sessionid' => (int) $sess->id,
    'slideid'   => $id,
    'type'      => $slide->type,
    'title'     => $slide->title,
];
switch ($slide->type) {
    case 'multichoice':
    case 'quiz':
        $prefill['options_text'] = $current_settings['options'] ?? [];
        if ($slide->type === 'quiz') {
            $prefill['correct_index_1based'] =
                ((int) ($current_settings['correct_index'] ?? 0)) + 1;
        }
        break;
    case 'rating':
        $prefill['scale_min'] = $current_settings['scale_min'] ?? 1;
        $prefill['scale_max'] = $current_settings['scale_max'] ?? 5;
        $prefill['scale_labels'] = implode('|',
            $current_settings['scale_labels'] ?? []);
        break;
    case 'ranking':
        $prefill['items_text'] = $current_settings['items'] ?? [];
        break;
    case 'wordcloud':
        $prefill['max_word_length'] = $current_settings['max_word_length'] ?? 50;
        $prefill['dedupe']          = !empty($current_settings['dedupe']) ? 1 : 0;
        break;
    case 'openended':
        $prefill['max_chars'] = $current_settings['max_chars'] ?? 280;
        break;
}
$form->set_data($prefill);

if ($form->is_cancelled()) {
    redirect($editurl);
}

if ($data = $form->get_data()) {
    $settings = \local_sentientia_live\forms\slide_form::build_settings_from_form_data(
        (array) $data, $slide->type);

    try {
        \local_sentientia_live\slide_manager::update(
            $id, (string) $data->title, $settings);
        redirect($editurl,
            get_string('slide_updated_notice', 'local_sentientia_live'),
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
echo $OUTPUT->heading(get_string('edit_slide_heading',
    'local_sentientia_live',
    get_string('slide_type_' . $slide->type, 'local_sentientia_live')));

$form->display();

echo $OUTPUT->footer();
