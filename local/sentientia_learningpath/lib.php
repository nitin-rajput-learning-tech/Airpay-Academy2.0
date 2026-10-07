<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * Library callbacks of local_sentientia_learningpath.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve the cover image of a learning path (file area summaryfile, system context, item id = path id).
 *
 * ADR-032: BizLMS served this file through local_learningplan_pluginfile(). The learningplan import copies
 * the files to this plugin's own area, and this callback is what makes them reachable once the BizLMS code
 * is gone. A cover is not sensitive, but a path outside the caller's tenant (or with no tenant) is still
 * refused, like the path itself (ADR-031).
 *
 * @param stdClass|null $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args Item id, then the file path and name.
 * @param bool $forcedownload
 * @param array $options
 * @return bool False when there is nothing to serve; send_stored_file() ends the request when it serves.
 */
function local_sentientia_learningpath_pluginfile($course, $cm, $context, string $filearea, array $args,
                                                  bool $forcedownload, array $options = []): bool {
    global $DB;

    if ($context->contextlevel !== CONTEXT_SYSTEM || $filearea !== 'summaryfile') {
        return false;
    }
    require_login();

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    if ($itemid <= 0 || $filename === null || $filename === '') {
        return false;
    }

    $path = $DB->get_record('local_sentientia_learningpath', ['id' => $itemid], 'id, open_path');
    if (!$path) {
        return false;
    }
    try {
        \local_sentientia_learningpath\path_manager::assert_path_in_scope($path);
    } catch (\moodle_exception $e) {
        return false;
    }

    $file = get_file_storage()->get_file($context->id, 'local_sentientia_learningpath', 'summaryfile',
        $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
