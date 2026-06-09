<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * End a live session — Phase E.1.i.
 *
 * GET ?id=N&sesskey=… — sesskey-protected handler. Looks up the session,
 * checks ownership (or manage_all capability), transitions live -> ended,
 * writes a session_ended event for any audience SSE listeners, redirects
 * back to the trainer dashboard.
 *
 * @package local_sentientia_live
 */

require(__DIR__ . '/../../../config.php');

require_login();
$context = \context_system::instance();
require_capability('local/sentientia_live:run', $context);
require_sesskey();

if (class_exists('\\local_sentientia_platform\\feature_flags')) {
    if (!\local_sentientia_platform\feature_flags::is_enabled('live.enabled')) {
        throw new \moodle_exception('errorfeatureoff', 'local_sentientia_live');
    }
}

$id = required_param('id', PARAM_INT);
$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');

if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $id)) {
    redirect($dashboard,
        get_string('cannot_run_session', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_ERROR);
}

$ok = \local_sentientia_live\session_manager::end_session($id);

if ($ok) {
    redirect($dashboard,
        get_string('session_ended_notice', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($dashboard,
        get_string('session_not_live_error', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_WARNING);
}
