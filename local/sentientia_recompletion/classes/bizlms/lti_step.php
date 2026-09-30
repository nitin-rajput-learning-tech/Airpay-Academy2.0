<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_recompletion\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;

/**
 * An archived LTI grade (the legacy copy of enrol_lti_users): the last grade sent back for the learner through
 * one enrolment LTI tool, and when they last accessed it.
 *
 * The row has no course column. The course is the one the tool's context belongs to, and is 0, with a warning,
 * when the tool is gone.
 *
 * @package    local_sentientia_recompletion
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lti_step extends archive_step {

    /**
     * {@inheritdoc}
     */
    public function key(): string {
        return sources::FEATURE . '.ltia';
    }

    /**
     * {@inheritdoc}
     */
    public function sourcetable(): string {
        return sources::LTIA;
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
            $tool = (int) $row->toolid;
            $course = $evidence->lti_course($tool);
            $outcome = outcome::insert($id, sources::ARCHIVE, self::archive_row($ctx, $row, $user, $course,
                mapper::LTI_GRADE, [
                    'instanceid' => $tool,
                    'grade' => mapper::bounded_number($row->lastgrade),
                    'timeevent' => mapper::timestamp($row->lastaccess),
                ]));
            if ($course === 0) {
                $outcome->warn('course_unknown');
            }
            $out[] = $outcome;
        }
        return $out;
    }
}
