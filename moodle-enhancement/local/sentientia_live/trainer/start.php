<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Start a draft session — Phase E.1.i.
 *
 * GET ?id=N&sesskey=… — flips draft -> live and redirects to the live
 * runner. Refuses if the session has zero slides (UX guard).
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

if (!\local_sentientia_live\session_manager::can_user_run((int) $USER->id, $id)) {
    redirect(
        new \moodle_url('/local/sentientia_live/trainer/index.php'),
        get_string('cannot_run_session', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$slide_count = \local_sentientia_live\slide_manager::count_for_session($id);
if ($slide_count === 0) {
    redirect(
        new \moodle_url('/local/sentientia_live/trainer/edit.php', ['id' => $id]),
        get_string('add_slide_to_start', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$ok = \local_sentientia_live\session_manager::start_session($id);
if (!$ok) {
    redirect(
        new \moodle_url('/local/sentientia_live/trainer/edit.php', ['id' => $id]),
        get_string('session_not_startable_error', 'local_sentientia_live'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

redirect(
    new \moodle_url('/local/sentientia_live/trainer/run.php', ['id' => $id]),
    get_string('session_started_notice', 'local_sentientia_live'),
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
