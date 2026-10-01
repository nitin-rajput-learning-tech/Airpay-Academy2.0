<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\recompute_step;

/**
 * Second pass of the course_lookups feature: the reviewed UPDATE of core course.open_* columns.
 *
 * It works on the trail rows the load step imported (a recompute step runs over every imported row of the
 * feature, so it is idempotent by construction) and writes the values of each trail row's local_coursedetails
 * row into the empty open_* columns of its course, through the writer's reviewed core UPDATE:
 * set_field-style, never update_course(), so no course_updated event fires and course.timemodified does not move.
 * Only a column that is empty (NULL, '' or 0) is written, so a repeat run, and a column an administrator has
 * since filled, are left alone. The trail row records which columns were written.
 *
 * prerequisite_courses is never read here: it must not become a course completion criterion.
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_details_fill_step extends recompute_step {

    public function key(): string {
        return course_lookups_importer::FEATURE . '.fill';
    }

    public function targettable(): string {
        return course_lookups_importer::LEDGER;
    }

    public function recompute(array $targetids, context $ctx): array {
        $trail = $ctx->legacy->fetch(course_lookups_importer::LEDGER, $targetids, ['courseid', 'detailid', 'filledcols']);
        $detailids = [];
        $courseids = [];
        foreach ($trail as $entry) {
            $detailids[] = (int) $entry->detailid;
            $courseids[] = (int) $entry->courseid;
        }
        $details = $ctx->legacy->fetch('local_coursedetails', $detailids);
        $courses = $ctx->legacy->fetch('course', $courseids, course_details_plan::course_columns());

        $out = [];
        foreach ($trail as $trailid => $entry) {
            $detail = $details[(int) $entry->detailid] ?? null;
            $course = $courses[(int) $entry->courseid] ?? null;
            if ($detail === null || $course === null) {
                continue;
            }
            $plan = course_details_plan::build($detail, $course, $ctx);
            if (!$plan['fields']) {
                continue;
            }
            $out[] = outcome::update('course', (int) $entry->courseid, (object) $plan['fields']);

            $filled = array_filter(explode(',', (string) $entry->filledcols), 'strlen');
            $filled = array_values(array_unique(array_merge($filled, array_keys($plan['fields']))));
            sort($filled);
            $out[] = outcome::update(course_lookups_importer::LEDGER, (int) $trailid, (object) [
                'filledcols' => implode(',', $filled),
            ]);
        }
        return $out;
    }
}
