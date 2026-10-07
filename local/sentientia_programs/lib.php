<?php
defined('MOODLE_INTERNAL') || die();
// Certification programs (multi-level certification tracks with enrolments).
// The program tables, pages and web services live in classes/ and the top-level pages; this file holds the
// Moodle callbacks the plugin needs.

/**
 * Serve the files of this plugin (ADR-032 code fix 13): the program logo.
 *
 * File area: system context, component local_sentientia_programs, file area programlogo, item id = the program
 * id. The BizLMS import copies each program's logo there; BizLMS kept it under a category context.
 *
 * Who may fetch it is decided by program_manager::can_view_program_logo(): an admin reader needs the
 * capability, the tenant and the history flag; a learner needs an enrolment in an active, visible program of
 * their own tenant and the learner flag.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args [itemid, (subpath...,) filename]
 * @param bool $forcedownload
 * @param array $options
 * @return bool False when the file does not exist or the viewer may not see it (Moodle answers 404).
 */
function local_sentientia_programs_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload,
                                              array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM || $filearea !== 'programlogo') {
        return false;
    }
    require_login();

    $programid = (int) array_shift($args);
    if ($programid <= 0 || !\local_sentientia_programs\program_manager::can_view_program_logo($programid)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $file = get_file_storage()->get_file($context->id, 'local_sentientia_programs', $filearea, $programid,
        $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
    return true;
}
