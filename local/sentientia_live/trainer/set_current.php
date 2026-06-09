<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Set a slide as the current (audience-visible) slide — Phase E.1.j.
 *
 * GET ?id=N&sesskey=… — sets sessions.current_slide_id to N if it's
 * in the same session AND the session is in live state. Writes a
 * slide_changed event for any SSE listeners. Used by the trainer
 * runner to advance to a specific slide.
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

$slideid = required_param('id', PARAM_INT);

$dashboard = new \moodle_url('/local/sentientia_live/trainer/index.php');

$slide = \local_sentientia_live\slide_manager::get($slideid);
if (!$slide) {
    redirect($dashboard,
        get_string('invalidslide', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$sessionid = (int) $slide->sessionid;

if (!\local_sentientia_live\session_manager::can_user_run(
        (int) $USER->id, $sessionid)) {
    redirect($dashboard,
        get_string('cannot_run_session', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_ERROR);
}

$ok = \local_sentientia_live\session_manager::set_current_slide(
    $sessionid, $slideid);

$run_url = new \moodle_url('/local/sentientia_live/trainer/run.php',
    ['id' => $sessionid]);

if ($ok) {
    redirect($run_url,
        get_string('slide_made_current_notice', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_SUCCESS);
} else {
    redirect($run_url,
        get_string('slide_make_current_failed', 'local_sentientia_live'),
        null, \core\output\notification::NOTIFY_WARNING);
}
