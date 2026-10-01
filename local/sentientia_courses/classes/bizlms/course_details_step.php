<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * local_coursedetails -> the fill trail (MAP), one group per course.
 *
 * BizLMS kept a second copy of some course settings in local_coursedetails. Sentientia reads the course
 * row's own open_* columns, so this step decides, per course, whether the legacy row has anything to
 * give; the fill step that follows writes the values into the empty open_* columns. The BizLMS table has
 * no unique key on courseid, so the rows of one course form a group:
 *
 *  - the course does not exist: every row is skipped (course_missing);
 *  - no row has a value that could fill an empty column (the usual case: BizLMS wrote the course columns
 *    directly): every row is archived (nothing_to_fill), and stays in the legacy table;
 *  - otherwise the FIRST row, by id, that can fill something wins and gets one trail row; the other rows of
 *    the course are merged into it (duplicate_course_row), because the target takes one fill per course. The
 *    first row is the one BizLMS's own get_field('local_coursedetails', ..., ['courseid' => ...]) returned.
 *
 * The trail row holds ids and the source timestamps only (a missing one is 0): the values themselves are read
 * from the legacy row when they are written, so the trail holds no personal data (coursecreator is a user id).
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_details_step extends step {

    public function key(): string {
        return course_lookups_importer::FEATURE . '.details';
    }

    public function sourcetable(): string {
        return 'local_coursedetails';
    }

    public function targettable(): string {
        return course_lookups_importer::LEDGER;
    }

    public function group_by(): array {
        return ['courseid'];
    }

    public function transform(array $rows, context $ctx): array {
        $courseid = (int) $rows[0]->courseid;
        $course = null;
        if ($courseid > 0 && $ctx->lookups->course_exists($courseid)) {
            $course = $ctx->legacy->fetch('course', [$courseid], course_details_plan::course_columns())[$courseid] ?? null;
        }
        if ($course === null) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::skip((int) $row->id, 'course_missing');
            }
            return $out;
        }

        $winner = null;
        $plan = null;
        foreach ($rows as $row) {
            $candidate = course_details_plan::build($row, $course, $ctx);
            if ($candidate['fields']) {
                $winner = $row;
                $plan = $candidate;
                break;
            }
        }
        if ($winner === null) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = outcome::archive((int) $row->id, 'nothing_to_fill');
            }
            return $out;
        }

        $out = [];
        $trail = outcome::insert((int) $winner->id, course_lookups_importer::LEDGER, (object) [
            'courseid' => $courseid,
            'detailid' => (int) $winner->id,
            'filledcols' => '',
            'timecreated' => (int) ($winner->timecreated ?? 0),
            'timemodified' => (int) ($winner->timemodified ?? 0),
        ]);
        foreach ($plan['warnings'] as $code) {
            $trail->warn($code);
        }
        $out[] = $trail;
        foreach ($rows as $row) {
            if ((int) $row->id !== (int) $winner->id) {
                $out[] = outcome::merge((int) $row->id, (int) $winner->id, 'duplicate_course_row');
            }
        }
        return $out;
    }
}
