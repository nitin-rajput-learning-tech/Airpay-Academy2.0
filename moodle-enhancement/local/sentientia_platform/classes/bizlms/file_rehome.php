<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_platform\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Copies files from one file area to another so an imported row keeps the
 * files it had (for example an organisation logo). Meant for an importer's
 * finalise(), which runs outside any transaction and must be idempotent.
 *
 * It only ever copies: the source files stay where they are (the legacy
 * archive is never altered), and a file that already exists at the target is
 * left alone, so running it twice copies nothing the second time.
 *
 * @package    local_sentientia_platform
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class file_rehome {

    /**
     * Copy every file of one file area (and item) into another.
     *
     * @param array{contextid: int, component: string, filearea: string, itemid: int} $from
     * @param array{contextid: int, component: string, filearea: string, itemid: int} $to
     * @return int Number of files copied.
     */
    public static function copy_area(array $from, array $to): int {
        $fs = get_file_storage();
        $copied = 0;
        $files = $fs->get_area_files(
            $from['contextid'], $from['component'], $from['filearea'], $from['itemid'], 'id', false);
        foreach ($files as $file) {
            if ($fs->file_exists($to['contextid'], $to['component'], $to['filearea'], $to['itemid'],
                    $file->get_filepath(), $file->get_filename())) {
                continue;
            }
            $fs->create_file_from_storedfile([
                'contextid' => $to['contextid'],
                'component' => $to['component'],
                'filearea' => $to['filearea'],
                'itemid' => $to['itemid'],
                'filepath' => $file->get_filepath(),
                'filename' => $file->get_filename(),
            ], $file);
            $copied++;
        }
        return $copied;
    }
}
