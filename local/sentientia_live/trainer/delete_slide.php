<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Delete a slide — Phase E.1.j.
 *
 * GET ?id=N&sesskey=… — confirmation page; deletion on ?confirm=1.
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

$id      = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');

$slide = \local_sentientia_live\slide_manager::get($id);
if (!$slide) {
    redirect($dashboard,
        get_string('invalidslide', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

if (!\local_sentientia_live\session_manager::can_user_run(
        (int) $USER->id, (int) $slide->sessionid)) {
    redirect($dashboard,
        get_string('cannot_edit_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$editurl = new \moodle_url('/local/sentientia_live/trainer/edit.php',
    ['id' => (int) $slide->sessionid]);

if ($confirm) {
    \local_sentientia_live\slide_manager::delete($id);
    redirect($editurl,
        get_string('slide_deleted_notice', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_url('/local/sentientia_live/trainer/delete_slide.php',
    ['id' => $id, 'sesskey' => sesskey()]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('delete_slide_pagetitle',
    'local_sentientia_live'));
$PAGE->set_heading(get_string('delete_slide_heading',
    'local_sentientia_live'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('delete_slide_heading',
    'local_sentientia_live'));

echo $OUTPUT->confirm(
    get_string('delete_slide_confirm_html', 'local_sentientia_live', (object) [
        'title' => format_string($slide->title),
        'type'  => get_string('slide_type_' . $slide->type,
            'local_sentientia_live'),
    ]),
    new \moodle_url('/local/sentientia_live/trainer/delete_slide.php', [
        'id' => $id, 'sesskey' => sesskey(), 'confirm' => 1,
    ]),
    $editurl
);

echo $OUTPUT->footer();
