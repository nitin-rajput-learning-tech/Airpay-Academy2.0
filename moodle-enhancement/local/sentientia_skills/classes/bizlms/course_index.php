<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_skills\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * The BizLMS skill links of the course table: which course teaches which legacy skill at which legacy level.
 *
 * BizLMS kept them on the course row itself (course.open_skill, course.open_level). The course table is a core
 * table the restored database keeps, so the importer only reads it, through the context's bounded reader, and
 * never claims it: two features read it, and a table has one owner.
 *
 * Loaded once per step instance. A course with no open_skill, the site course and a site without the BizLMS
 * columns give an empty index.
 *
 * @package    local_sentientia_skills
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_index {

    /** Rows per page of the read. */
    private const PAGE = 2000;

    /** @var array<int, array{course: int, level: int, time: int}>|null legacy skill id => its courses, by course id. */
    private ?array $byskill = null;

    /** @var array<int, array{skill: int, level: int, time: int}>|null course id => its skill link. */
    private ?array $bycourse = null;

    /**
     * Load the index on first use.
     *
     * @param context $ctx
     * @return void
     */
    private function load(context $ctx): void {
        if ($this->byskill !== null) {
            return;
        }
        $this->byskill = [];
        $this->bycourse = [];
        if (!$ctx->legacy->exists('course') || !$ctx->legacy->has_column('course', 'open_skill')) {
            return;
        }
        $columns = ['id', 'open_skill', 'timecreated'];
        if ($ctx->legacy->has_column('course', 'open_level')) {
            $columns[] = 'open_level';
        }
        $after = 0;
        do {
            // The site course (id 1) is never a skill course.
            $page = $ctx->legacy->page('course', $after, self::PAGE, $columns, ['t.open_skill > 0 AND t.id > 1', []]);
            foreach ($page as $id => $row) {
                $skill = (int) $row->open_skill;
                $link = ['course' => (int) $id, 'level' => (int) ($row->open_level ?? 0), 'time' => (int) $row->timecreated];
                $this->byskill[$skill][(int) $id] = $link;
                $this->bycourse[(int) $id] = ['skill' => $skill, 'level' => $link['level'], 'time' => $link['time']];
                $after = (int) $id;
            }
        } while (count($page) === self::PAGE);
        foreach ($this->byskill as $skill => $links) {
            ksort($links);
            $this->byskill[$skill] = $links;
        }
    }

    /**
     * The courses that teach a legacy skill, by course id.
     *
     * @param context $ctx
     * @param int $legacyskillid
     * @return array<int, array{course: int, level: int, time: int}>
     */
    public function courses_of_skill(context $ctx, int $legacyskillid): array {
        $this->load($ctx);
        return $this->byskill[$legacyskillid] ?? [];
    }

    /**
     * The skill link of a course.
     *
     * @param context $ctx
     * @param int $courseid
     * @return array{skill: int, level: int, time: int}|null Null when the course teaches no legacy skill.
     */
    public function skill_of_course(context $ctx, int $courseid): ?array {
        $this->load($ctx);
        return $this->bycourse[$courseid] ?? null;
    }

    /**
     * Legacy skill ids that some course points at.
     *
     * @param context $ctx
     * @return int[]
     */
    public function linked_skill_ids(context $ctx): array {
        $this->load($ctx);
        return array_keys($this->byskill);
    }
}
