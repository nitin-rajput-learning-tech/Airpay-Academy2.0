<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Move a slide up or down in the session deck — Phase E.1.j.
 *
 * GET ?id=N&direction=up|down&sesskey=…
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:create', $context);
require_sesskey();

if (class_exists('\\local_airpay_core\\feature_flags')) {
    if (!\local_airpay_core\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id        = required_param('id', PARAM_INT);
$direction = required_param('direction', PARAM_ALPHA);

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

if ($direction === 'up') {
    \local_sentientia_live\slide_manager::move_up($id);
} elseif ($direction === 'down') {
    \local_sentientia_live\slide_manager::move_down($id);
}

redirect($editurl);
