<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Delete a live session — Phase E.1.i.
 *
 * GET ?id=N&sesskey=… — sesskey-protected. Shows a confirmation page;
 * actual deletion on POST with confirm=1. Cascades through slides /
 * participants / responses / events via session_manager::delete().
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:create', $context);
require_sesskey();

if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');
$sess = \local_sentientia_live\session_manager::get($id);

if (!$sess) {
    redirect($dashboard,
        get_string('invalidsession', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $id)) {
    redirect($dashboard,
        get_string('cannot_delete_session', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

if ($confirm) {
    \local_sentientia_live\session_manager::delete($id);
    redirect($dashboard,
        get_string('session_deleted_notice', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Confirmation screen.
$PAGE->set_url('/local/sentientia_live/trainer/delete.php',
    ['id' => $id, 'sesskey' => sesskey()]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('delete_session_pagetitle', 'local_sentientia_live'));
$PAGE->set_heading(get_string('delete_session_heading', 'local_sentientia_live'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete_session_heading', 'local_sentientia_live'));

$slide_count = \local_sentientia_live\slide_manager::count_for_session($id);
$participant_count = \local_sentientia_live\participant_manager::total_count_for_session($id);

echo $OUTPUT->confirm(
    get_string('delete_session_confirm_html', 'local_sentientia_live', (object) [
        'title'             => format_string($sess->title),
        'slide_count'       => $slide_count,
        'participant_count' => $participant_count,
    ]),
    new \moodle_url('/local/sentientia_live/trainer/delete.php', [
        'id' => $id, 'sesskey' => sesskey(), 'confirm' => 1,
    ]),
    $dashboard
);

echo $OUTPUT->footer();
