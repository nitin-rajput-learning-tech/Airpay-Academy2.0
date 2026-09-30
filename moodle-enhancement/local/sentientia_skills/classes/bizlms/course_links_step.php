<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\outcome;
use local_sentientia_platform\bizlms\step;

/**
 * The skills a course teaches: course.open_skill and course.open_level -> local_sentientia_course_skills (MAP).
 *
 * BizLMS kept the link on the course row. The mapping doc derives it from the course table (#course.open_skill,
 * one map row per course). This step derives it per SKILL instead (#local_skill.courses): one group per legacy
 * skill, the skill's courses read from the course table through the context's reader. The outcome is the same
 * target rows; what differs is which table is the accounting unit, and that is deliberate. The course table is a
 * core table that more than one feature reads (exams derives from it too), and the registry gives a table one
 * owner, so claiming it here would make the two importers refuse to load together. local_skill is this
 * feature's own table, and the skill it resolves through the map is already imported by the step before.
 *
 * The group's first course (by id) is the primary row; the others are sub-rows keyed course:<course id>. A skill
 * no course teaches is archived (no_course_links), and one that was not imported cannot carry links (skipped).
 * Courses whose open_skill names no legacy skill at all have no group; the preflight counts them.
 *
 * teaches_level is the skill level of the course's difficulty level from the owner's map (1 when the course has
 * none or one the map does not name, the BizLMS default). timecreated is the course's own.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_links_step extends step {

    /** @var course_index */
    private course_index $courses;

    /** @var array<int, int>|null The owner's level map. */
    private ?array $proficiency = null;

    public function __construct() {
        $this->courses = new course_index();
    }

    public function key(): string {
        return 'skills.course_skills';
    }

    public function sourcetable(): string {
        return '#local_skill.courses';
    }

    public function targettable(): string {
        return 'local_sentientia_course_skills';
    }

    public function group_by(): array {
        // One group per skill row.
        return ['id'];
    }

    public function columns(): array {
        return ['id'];
    }

    public function preload(): array {
        return [['local_skill', '']];
    }

    public function transform(array $rows, context $ctx): array {
        $row = reset($rows);
        $skillid = (int) $row->id;

        $target = $ctx->map->resolve('local_skill', $skillid);
        if ($target === null) {
            return [outcome::skip($skillid, 'skill_not_imported')];
        }
        $links = $this->courses->courses_of_skill($ctx, $skillid);
        if (!$links) {
            return [outcome::archive($skillid, 'no_course_links')];
        }

        $map = $this->proficiency($ctx);
        $out = [];
        foreach ($links as $courseid => $link) {
            $fields = (object) [
                'courseid' => (int) $courseid,
                'skillid' => $target,
                'teaches_level' => level_map::proficiency($map, (int) $link['level']),
                'timecreated' => (int) $link['time'],
            ];
            $out[] = $out ? outcome::insert($skillid, $this->targettable(), $fields, 'course:' . $courseid)
                : outcome::insert($skillid, $this->targettable(), $fields);
        }
        return $out;
    }

    /**
     * @param context $ctx
     * @return array<int, int>
     */
    private function proficiency(context $ctx): array {
        if ($this->proficiency === null) {
            $this->proficiency = level_map::parse($ctx->decision('skills.level_proficiency'))['map'];
        }
        return $this->proficiency;
    }
}
