<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Serve the classroom logo (ADR-032, classroom code fix 14).
 *
 * The BizLMS import copies each classroom's logo from the BizLMS file area into the system context,
 * component local_sentientia_classroom, file area classroomlogo, item id = the classroom id. The logo is
 * shown on the classroom overview (flag sentientia.classroom.import_history), so it is served only when the
 * flag is on, and only to a person who may see the classroom: a user who holds local/sentientia_classroom:view
 * for a classroom inside their tenant (ADR-031), or a learner on its roster.
 *
 * @param stdClass|null $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args itemid (the classroom id), then the file path and name
 * @param bool $forcedownload
 * @param array $options
 * @return bool false when there is nothing to serve to this person
 */
function local_sentientia_classroom_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
        array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'classroomlogo') {
        return false;
    }
    require_login();
    if (!\local_sentientia_classroom\session_manager::history_enabled()) {
        return false;
    }

    $classroomid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    if ($classroomid <= 0 || $filename === null || $filename === '') {
        return false;
    }

    $classroom = $DB->get_record('local_sentientia_classroom', ['id' => $classroomid]);
    if (!$classroom) {
        return false;
    }
    $allowed = false;
    if (has_capability('local/sentientia_classroom:view', $context)) {
        try {
            \local_sentientia_classroom\session_manager::assert_classroom_in_scope($classroom);
            $allowed = true;
        } catch (\moodle_exception $e) {
            $allowed = false;
        }
    }
    if (!$allowed) {
        $allowed = $DB->record_exists('local_sentientia_classroom_users',
            ['classroomid' => $classroomid, 'userid' => (int) $USER->id]);
    }
    if (!$allowed) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'local_sentientia_classroom', $filearea, $classroomid,
        $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
    return true;
}
