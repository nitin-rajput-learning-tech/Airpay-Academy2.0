<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * Step 1 of the enrolments importer: every course that has an enrolment on a BizLMS instance gets an ENABLED
 * manual enrol instance to move those learners onto (mapping doc, section 21, gap G6).
 *
 * The unit is the course (derived unit #enrol.courseid, group_by courseid; the map's sourceid is the course id,
 * a non-personal key): the BizLMS instances of one course that have at least one enrolment form one group, and the
 * group has exactly one outcome.
 *
 *  - the course is gone: skipped, course_missing;
 *  - the course already has an enabled manual instance: folded into the lowest-id one, manual_instance_exists.
 *    An existing instance is never changed;
 *  - otherwise a new row in {enrol} (a reviewed core INSERT: registry::CORE_WRITES_ALLOWED, enrol). A course with
 *    only a DISABLED manual instance gets a new enabled one beside it: the disabled one records that an
 *    administrator switched manual enrolment off, and the import does not undo that, but the learners must keep
 *    access. The map holds the new instance (imported, targettable enrol), which is how enrolments.enrolments finds
 *    it.
 *
 * The declared target is the ledger, because the registry accepts a step's target only from the plugin's own schema.
 * This step writes no ledger row: a created instance is recorded by its map row (provenance::is_imported('enrol', id)).
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class enrolments_instances_step extends step {

    /** Rows read per bounded lookup of the instances of one course. */
    private const COURSE_INSTANCES = 500;

    public function key(): string {
        return 'enrolments.instances';
    }

    public function sourcetable(): string {
        return enrolments_importer::UNIT_INSTANCES;
    }

    public function targettable(): string {
        return enrolments_importer::LEDGER;
    }

    public function group_by(): array {
        return ['courseid'];
    }

    public function columns(): array {
        return ['id', 'courseid', 'enrol', 'roleid', 'timecreated'];
    }

    public function source_filter(): array {
        return enrolments_importer::instance_filter();
    }

    /**
     * @param \stdClass[] $rows The BizLMS instances (with enrolments) of ONE course, ordered by id.
     * @param context $ctx
     * @return outcome[]
     */
    public function transform(array $rows, context $ctx): array {
        $first = reset($rows);
        $courseid = (int) $first->courseid;

        if ($courseid <= 0 || !$ctx->lookups->course_exists($courseid)) {
            return [outcome::skip($courseid, 'course_missing')];
        }

        $instances = $ctx->legacy->page('enrol', 0, self::COURSE_INSTANCES, ['id', 'courseid', 'enrol', 'status', 'sortorder'],
            ['t.courseid = :blmcourse', ['blmcourse' => $courseid]]);

        $sortorder = -1;
        foreach ($instances as $instance) {
            if ((string) $instance->enrol === enrolments_importer::MANUAL && (int) $instance->status === 0) {
                // The lowest id: the page is ordered by id.
                return [outcome::fold($courseid, 'enrol', (int) $instance->id, 'manual_instance_exists')];
            }
            $sortorder = max($sortorder, (int) $instance->sortorder);
        }

        // The role the BizLMS method gave its learners becomes the default role of the new instance. It only
        // matters for people an administrator enrols through it later: the learners keep the role assignments
        // they already hold. The lowest-id BizLMS instance decides, so the choice is the same on every run.
        $created = (int) $first->timecreated;
        foreach ($rows as $legacy) {
            $created = min($created, (int) $legacy->timecreated);
        }
        $new = (object) [
            'enrol' => enrolments_importer::MANUAL,
            'status' => 0,
            'courseid' => $courseid,
            // After the course's other instances, as the enrol API would place it.
            'sortorder' => $sortorder + 1,
            'roleid' => (int) $first->roleid,
            // A manual instance has no source timestamp: the earliest BizLMS instance of the course stands for the
            // moment learners began to be enrolled in it (reported as derived_timestamp).
            'timecreated' => $created,
            'timemodified' => $created,
        ];
        return [outcome::insert($courseid, 'enrol', $new)->warn('derived_timestamp')];
    }
}
