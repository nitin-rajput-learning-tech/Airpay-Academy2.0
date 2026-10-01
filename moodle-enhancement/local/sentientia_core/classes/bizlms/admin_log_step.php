<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_core\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * local_logs -> local_sentientia_admin_log (mapping doc, section 8).
 *
 * local_logs is BizLMS's admin audit trail. local_custom_logs() wrote one row each time a course was
 * created, updated or deleted (and forum and online-exam deletes borrowed it). Nothing in BizLMS read the
 * table back. Every row is kept, one target row per source row, in source order; none is merged or folded.
 *
 *   event                   -> event        verbatim
 *   module                  -> module       verbatim
 *   description             -> description  verbatim (it names the actor by first name; see the privacy provider)
 *   type                    -> itemref      (the course id, or NULL)
 *   usercreated             -> userid       (the actor)
 *   usermodified            -> usermodified (NULL becomes 0)
 *   the actor's open_path   -> actor_path   (the tenant, see log_step::place())
 *   timecreated             -> timecreated
 *   timemodified            -> timemodified (timecreated when the source has none: derived_timestamp)
 *
 * @package    local_sentientia_core
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class admin_log_step extends log_step {

    /** @var string Legacy table. */
    public const SOURCE = 'local_logs';

    public function key(): string {
        return legacy_logs_importer::FEATURE . '.admin_log';
    }

    public function sourcetable(): string {
        return self::SOURCE;
    }

    public function columns(): array {
        return ['event', 'module', 'description', 'type', 'timecreated', 'timemodified', 'usercreated', 'usermodified'];
    }

    public function transform(array $rows, context $ctx): array {
        $out = [];
        foreach ($rows as $source) {
            $warnings = [];
            [$description, $repaired] = self::clean_text($source->description);
            if ($repaired) {
                $warnings[] = 'encoding_repaired';
            }
            [$created, $modified, $derived] = self::timestamps($source->timecreated, $source->timemodified);
            if ($derived) {
                $warnings[] = 'derived_timestamp';
            }
            $actor = max(0, (int) $source->usercreated);

            $row = (object) [
                'source' => self::SOURCE,
                'event' => $ctx->text->fit($source->event, 225, 'event'),
                'module' => $ctx->text->fit($source->module, 225, 'module'),
                'description' => $description,
                'itemref' => $source->type === null ? null : $ctx->text->fit($source->type, 225, 'itemref'),
                'userid' => $actor,
                'usermodified' => max(0, (int) $source->usermodified),
                'timecreated' => $created,
                'timemodified' => $modified,
            ];
            $out[] = $this->place($ctx, (int) $source->id, $actor, $row, $warnings);
        }
        return $out;
    }
}
