<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived activity completion (course_modules_completion): whether the learner had completed one
 * activity of the course, and when that last changed.
 *
 * The first version of the legacy plugin did not record the course; those rows have course 0 and take it from
 * the course module. The viewed flag and the administrator who overrode the state stay in the payload.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.cmc';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::CMC;
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
            $cmid = (int) $row->coursemoduleid;
            $course = (int) $row->course > 0 ? (int) $row->course : $evidence->module_course($cmid);
            $out[] = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::ACTIVITY_COMPLETION, [
                    'cmid' => $cmid,
                    'state' => mapper::activity_state($row->completionstate),
                    'timeevent' => mapper::timestamp($row->timemodified),
                ]));
        }
        return $out;
    }
}
