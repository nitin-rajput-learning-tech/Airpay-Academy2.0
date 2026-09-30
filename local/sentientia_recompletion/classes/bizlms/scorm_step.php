<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived SCORM tracking value (scorm_scoes_track): one element the package reported for the learner.
 *
 * This is the largest of the archive tables. The status elements (lesson, completion and success status) are
 * promoted to state and the raw score to grade when it is a number in range; every other element, and the
 * value itself, stay in the payload, which holds the row exactly.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scorm_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.sst';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::SST;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(array $rows, context $ctx): array {
        $evidence = evidence::of($ctx);
        $out = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $user = (int) $row->userid;
            if (!self::known_user($ctx, $user)) {
                $out[] = self::orphan_user($id);
                continue;
            }
            $scorm = (int) $row->scormid;
            $course = (int) $row->course > 0 ? (int) $row->course : $evidence->scorm_course($scorm);
            $element = (string) $row->element;
            $value = (string) $row->value;
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::SCORM_TRACK, [
                    'instanceid' => $scorm,
                    'itemkey' => $element,
                    'state' => mapper::is_scorm_status($element) ? trim($value) : null,
                    'grade' => mapper::is_scorm_score($element) ? mapper::bounded_number($value) : null,
                    'timeevent' => mapper::timestamp($row->timemodified),
                ]));
        }
        return $out;
    }
}
