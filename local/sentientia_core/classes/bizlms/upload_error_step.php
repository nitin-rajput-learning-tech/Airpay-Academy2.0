<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * local_courseerrors -> local_sentientia_admin_log (mapping doc, section 8).
 *
 * local_courseerrors is the error log of the BizLMS bulk course upload (local/courses/upload/processor.php).
 * One row per failed line, with the reason text, the uploader and the time. Every row is kept.
 *
 *   'local_courseerrors'    -> source
 *   'upload_error'          -> event
 *   'course'                -> module
 *   reason (CHAR 225, NULL allowed) -> description   (an empty string for NULL)
 *   userid (NULL allowed)   -> userid        (the uploader; NULL becomes 0)
 *   the uploader's open_path -> actor_path   (the tenant, see log_step::place())
 *   time                    -> timecreated and timemodified (the table has one time column)
 *
 * The reason text is built from the upload's own data (a course short name, a category name), not from
 * the uploader's name, so the row carries no first name.
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upload_error_step extends log_step {

    /** @var string Legacy table. */
    public const SOURCE = 'local_courseerrors';

    public function key(): string {
        return legacy_logs_importer::FEATURE . '.upload_errors';
    }

    public function sourcetable(): string {
        return self::SOURCE;
    }

    public function columns(): array {
        return ['reason', 'userid', 'time'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $source) {
            $warnings = [];
            [$description, $repaired] = self::clean_text($source->reason);
            if ($repaired) {
                $warnings[] = 'encoding_repaired';
            }
            // One source time. Both target columns take it, so neither is derived.
            $time = (int) $source->time;
            $actor = max(0, (int) $source->userid);

            $row = (object) [
                'source' => self::SOURCE,
                'event' => 'upload_error',
                'module' => 'course',
                'description' => $description,
                'itemref' => null,
                'userid' => $actor,
                'usermodified' => 0,
                'timecreated' => $time,
                'timemodified' => $time,
            ];
            $out[] = $this->place($ctx, (int) $source->id, $actor, $row, $warnings);
        }
        return $out;
    }
}
