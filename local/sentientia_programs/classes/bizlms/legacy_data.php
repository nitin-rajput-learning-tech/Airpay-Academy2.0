<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

use local_sentientia_platform\bizlms\context;
use local_sentientia_platform\bizlms\legacy_reader;

defined('MOODLE_INTERNAL') || die();

/**
 * The BizLMS rows a program step needs besides the one it is transforming: the criteria, the courses of a
 * level, a learner's stored level completions and real course completions.
 *
 * Every read goes through the context's bounded legacy reader (keyset pages, no recordset), and every table is
 * read-only source data that cannot change during a run, so the answers are cached for the life of the step.
 * The tables involved are small (programs, levels, criteria), except the two that scale with learners, which are
 * only ever read for one program and one user at a time.
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_data {

    /** @var context */
    private context $ctx;

    /** @var array<int, \stdClass|null> programid => criteria row with the lowest id */
    private array $programcriteria = [];

    /** @var array<int, array<int, \stdClass>> programid => [levelid => criteria row with the lowest id] */
    private array $levelcriteria = [];

    /** @var array<int, int|null> levelid => programid of the level row */
    private array $levelprogram = [];

    /** @var array<int, int[]> levelid => valid course ids, without repeats, in source id order */
    private array $levelcourses = [];

    /** @var array<int, bool> levelid => has a completed level-completion row */
    private array $levelcompleted = [];

    /** @var array<int, int[]> programid => valid course ids of all its levels */
    private array $programcourses = [];

    /**
     * @param context $ctx
     */
    public function __construct(context $ctx) {
        $this->ctx = $ctx;
    }

    /**
     * The program's completion criteria: the row with the lowest id (BizLMS read the first it found and the table
     * has no unique key, so a duplicate is possible).
     *
     * @param int $programid
     * @return \stdClass|null
     */
    public function program_criteria(int $programid): ?\stdClass {
        if (!array_key_exists($programid, $this->programcriteria)) {
            $rows = $this->rows('local_bc_completion_criteria', ['programid', 'leveltracking', 'levelids'],
                't.programid = :prgpid', ['prgpid' => $programid]);
            $this->programcriteria[$programid] = $rows ? reset($rows) : null;
        }
        return $this->programcriteria[$programid];
    }

    /**
     * The completion criteria of one level of a program (lowest id wins, as for the program).
     *
     * @param int $programid
     * @param int $levelid
     * @return \stdClass|null
     */
    public function level_criteria(int $programid, int $levelid): ?\stdClass {
        if (!isset($this->levelcriteria[$programid])) {
            $this->levelcriteria[$programid] = [];
            $rows = $this->rows('local_bcl_cmplt_criteria', ['programid', 'levelid', 'coursetracking', 'courseids'],
                't.programid = :prgpid', ['prgpid' => $programid]);
            foreach ($rows as $row) {
                if (!isset($this->levelcriteria[$programid][(int) $row->levelid])) {
                    $this->levelcriteria[$programid][(int) $row->levelid] = $row;
                }
            }
        }
        return $this->levelcriteria[$programid][$levelid] ?? null;
    }

    /**
     * The program a level row belongs to, read from the level itself. A course row carries its own programid
     * that can disagree with the level's; the level wins.
     *
     * @param int $levelid
     * @return int|null Null when the level row does not exist.
     */
    public function level_program(int $levelid): ?int {
        if (!array_key_exists($levelid, $this->levelprogram)) {
            $rows = $this->ctx->legacy->fetch('local_program_levels', [$levelid], ['programid']);
            $this->levelprogram[$levelid] = isset($rows[$levelid]) ? (int) $rows[$levelid]->programid : null;
        }
        return $this->levelprogram[$levelid];
    }

    /**
     * The valid courses of a level, in the order BizLMS stored them: a course id above 1 that exists.
     * (Sentientia rejects the front page, course 1, as a level course.)
     *
     * @param int $levelid
     * @return int[]
     */
    public function level_course_ids(int $levelid): array {
        if (!isset($this->levelcourses[$levelid])) {
            $ids = [];
            $rows = $this->rows('local_program_level_courses', ['levelid', 'courseid'],
                't.levelid = :prglid', ['prglid' => $levelid]);
            foreach ($rows as $row) {
                $courseid = (int) $row->courseid;
                if ($courseid > 1 && $this->ctx->lookups->course_exists($courseid)) {
                    $ids[$courseid] = $courseid;
                }
            }
            $this->levelcourses[$levelid] = array_values($ids);
        }
        return $this->levelcourses[$levelid];
    }

    /**
     * Has any learner a completed level-completion row for this level? (A level with no course is kept when it has.)
     *
     * @param int $levelid
     * @return bool
     */
    public function level_has_completion(int $levelid): bool {
        if (!isset($this->levelcompleted[$levelid])) {
            $this->levelcompleted[$levelid] = $this->ctx->legacy->count('local_bc_level_completions',
                ['t.levelid = :prglid AND t.completion_status = 1', ['prglid' => $levelid]]) > 0;
        }
        return $this->levelcompleted[$levelid];
    }

    /**
     * Every valid course of every level of a program.
     *
     * @param int $programid
     * @return int[]
     */
    public function program_course_ids(int $programid): array {
        if (!isset($this->programcourses[$programid])) {
            $ids = [];
            $levels = $this->rows('local_program_levels', ['programid'], 't.programid = :prgpid',
                ['prgpid' => $programid]);
            foreach (array_keys($levels) as $levelid) {
                foreach ($this->level_course_ids((int) $levelid) as $courseid) {
                    $ids[$courseid] = $courseid;
                }
            }
            $this->programcourses[$programid] = array_values($ids);
        }
        return $this->programcourses[$programid];
    }

    /**
     * The source ids of a learner's enrolment rows in a program (more than one when BizLMS enrolled twice).
     *
     * @param int $programid
     * @param int $userid
     * @return int[]
     */
    public function enrolment_ids(int $programid, int $userid): array {
        return array_map('intval', array_keys($this->rows('local_program_users', ['programid', 'userid'],
            't.programid = :prgpid AND t.userid = :prguid', ['prgpid' => $programid, 'prguid' => $userid])));
    }

    /**
     * A learner's completed level-completion rows in a program, lowest id first.
     *
     * @param int $programid
     * @param int $userid
     * @return \stdClass[] Keyed by source id.
     */
    public function completed_levels(int $programid, int $userid): array {
        return $this->rows('local_bc_level_completions',
            ['programid', 'levelid', 'userid', 'completion_status', 'completiondate'],
            't.programid = :prgpid AND t.userid = :prguid AND t.completion_status = 1',
            ['prgpid' => $programid, 'prguid' => $userid]);
    }

    /**
     * The core completion time of each of these courses for a learner.
     *
     * @param int $userid
     * @param int[] $courseids
     * @return array<int, int> courseid => timecompleted, only for completed courses
     */
    public function course_completions(int $userid, array $courseids): array {
        global $DB;
        $out = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $courseids))), 500) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'prgcc');
            $params['prgcu'] = $userid;
            $rows = $this->rows('course_completions', ['userid', 'course', 'timecompleted'],
                "t.userid = :prgcu AND t.timecompleted > 0 AND t.course {$insql}", $params);
            foreach ($rows as $row) {
                $out[(int) $row->course] = (int) $row->timecompleted;
            }
        }
        return $out;
    }

    /**
     * When a level was completed by a learner (rules::level_completion_date over the real course completions).
     *
     * @param int $programid The level's program.
     * @param int $levelid
     * @param int $userid
     * @param int $stored The stored completiondate of the BizLMS row.
     * @return int|null
     */
    public function level_completion_date(int $programid, int $levelid, int $userid, int $stored): ?int {
        $criteria = $this->level_criteria($programid, $levelid);
        $rule = rules::level_rule($criteria);
        $qualifying = rules::qualifying_courses($criteria, $this->level_course_ids($levelid));
        $times = $qualifying ? array_values($this->course_completions($userid, $qualifying)) : [];
        return rules::level_completion_date($times, $rule, $stored);
    }

    /**
     * All rows of a table that match a filter, by keyset pages.
     *
     * @param string $table
     * @param string[] $columns
     * @param string $sql Condition on the alias t.
     * @param array $params
     * @return array<int, \stdClass> Keyed by id, ascending.
     */
    private function rows(string $table, array $columns, string $sql, array $params): array {
        $out = [];
        $after = 0;
        do {
            $page = $this->ctx->legacy->page($table, $after, legacy_reader::MAX_PAGE, $columns, [$sql, $params]);
            foreach ($page as $id => $row) {
                $out[(int) $id] = $row;
                $after = (int) $id;
            }
        } while (count($page) === legacy_reader::MAX_PAGE);
        return $out;
    }
}
