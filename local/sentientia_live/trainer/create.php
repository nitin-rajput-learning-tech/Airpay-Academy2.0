<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Create a new live session — Phase E.1.g.
 *
 * Renders session_form. On submit, creates a draft session via
 * session_manager::create() and redirects to trainer/edit.php so the
 * trainer can add slides.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:create', $context);

// Master flag gate.
if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$PAGE->set_url('/local/sentientia_live/trainer/create.php');
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('create_session_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(get_string('create_session_heading', 'local_sentientia_live'));

$dashboard_url = new \moodle_url('/local/sentientia_live/trainer/index.php');
$form = new \local_sentientia_live\forms\session_form(
    $PAGE->url->out(false)
);

if ($form->is_cancelled()) {
    redirect($dashboard_url);
}

if ($data = $form->get_data()) {
    // Build settings array from form fields.
    $settings = [
        'allow_anonymous'          => !empty($data->allow_anonymous),
        'show_results_to_audience' => !empty($data->show_results_to_audience),
        'allow_late_join'          => !empty($data->allow_late_join),
        'max_concurrent'           => (int) ($data->max_concurrent ?? 500),
    ];

    try {
        $sid = \local_sentientia_live\session_manager::create(
            (int) $USER->id,
            (string) $data->title,
            $settings
        );
    } catch (\moodle_exception $e) {
        echo $OUTPUT->header();
        echo $OUTPUT->notification($e->getMessage(), 'error');
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    redirect(
        new \moodle_url('/local/sentientia_live/trainer/edit.php',
            ['id' => $sid]),
        get_string('session_created_notice', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('create_session_heading', 'local_sentientia_live'));

echo \html_writer::tag('p',
    get_string('create_session_intro', 'local_sentientia_live'),
    ['class' => 'text-muted mb-4']);

$form->display();

echo $OUTPUT->footer();
