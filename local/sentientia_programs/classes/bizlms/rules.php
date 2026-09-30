<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_programs\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * The BizLMS program rules the importer translates (mapping doc, section 16).
 *
 * Everything here is pure: values in, values out, no database, no globals. That keeps the
 * translation of BizLMS' criteria tables readable in one place and testable without a
 * database. The steps call it; nothing else does.
 *
 * BizLMS kept three kinds of "tracking" on its criteria tables, all of them
 * ALL | AND | OR, read as follows (PR classes/form/level_completion_form.php:56-58,
 * program_completion_form.php:51-53, classes/local/completion.php:194-215):
 *
 *  - local_bcl_cmplt_criteria.coursetracking, per level: ALL = every course of the level,
 *    AND = every course listed in courseids, OR = any course listed in courseids.
 *    Empty or NULL means ALL (program.php:276-277).
 *  - local_bc_completion_criteria.leveltracking, per program, with levelids: ALL = every
 *    level, AND = every listed level, OR = any listed level. Empty levelids means "no
 *    filter", so OR without levelids is "any level".
 *
 * @package    local_sentientia_programs
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rules {

    /** Level rule: every mandatory course completes the level. */
    public const RULE_ALL = 'all';

    /** Level rule: any one mandatory course completes the level. */
    public const RULE_ANY = 'any';

    /** Program status: draft. */
    public const PROGRAM_DRAFT = 0;

    /** Program status: active. */
    public const PROGRAM_ACTIVE = 1;

    /** Program status: archived. */
    public const PROGRAM_ARCHIVED = 2;

    /** Enrolment status: enrolled, nothing done yet. */
    public const USER_ENROLLED = 0;

    /** Enrolment status: in progress. */
    public const USER_INPROGRESS = 1;

    /** Enrolment status: completed. */
    public const USER_COMPLETED = 2;

    /**
     * The integers in a BizLMS comma list ("12, 13", "4,5").
     *
     * BizLMS joined course ids with ', ' (program.php:270) and level ids with ',' (program.php:322), and
     * nothing stopped a hand edit, so every item is trimmed and anything that is not a positive integer
     * is dropped. Order is kept and repeats are removed.
     *
     * @param string|null $csv
     * @return int[]
     */
    public static function csv_ids(?string $csv): array {
        $ids = [];
        foreach (explode(',', (string) $csv) as $item) {
            $item = trim($item);
            if ($item === '' || !ctype_digit($item) || (int) $item <= 0) {
                continue;
            }
            $ids[(int) $item] = (int) $item;
        }
        return array_values($ids);
    }

    /**
     * A tracking value in its canonical form: ALL, AND, OR, or '' when BizLMS stored nothing.
     *
     * @param string|null $raw
     * @return string
     */
    public static function tracking(?string $raw): string {
        return strtoupper(trim((string) $raw));
    }

    /**
     * Program-level completion_required from the program's criteria row.
     *
     * Only OR makes the program complete on ANY level (0). ALL, AND, an empty value and no criteria row at all
     * need every level that counts (1). Which levels count is each level's own completion_required flag.
     *
     * @param \stdClass|null $criteria local_bc_completion_criteria row (the one with the lowest id), or null.
     * @return int
     */
    public static function program_completion_required(?\stdClass $criteria): int {
        return $criteria !== null && self::tracking($criteria->leveltracking ?? '') === 'OR' ? 0 : 1;
    }

    /**
     * Does this level count towards the program? (levels.completion_required)
     *
     * 1 when the program criteria is ALL, absent, or names no levels. For AND and OR with a list, 1 only when the
     * legacy level id is in the list (program.php:324).
     *
     * @param \stdClass|null $criteria local_bc_completion_criteria row, or null.
     * @param int $legacylevelid The level's id in local_program_levels.
     * @return int
     */
    public static function level_required(?\stdClass $criteria, int $legacylevelid): int {
        if ($criteria === null) {
            return 1;
        }
        $tracking = self::tracking($criteria->leveltracking ?? '');
        if ($tracking !== 'AND' && $tracking !== 'OR') {
            return 1;
        }
        $listed = self::csv_ids($criteria->levelids ?? '');
        if (!$listed) {
            return 1;
        }
        return in_array($legacylevelid, $listed, true) ? 1 : 0;
    }

    /**
     * The level's completion rule (levels.completion_rule): any when BizLMS tracked it with OR.
     *
     * @param \stdClass|null $levelcriteria local_bcl_cmplt_criteria row (lowest id for the level), or null.
     * @return string RULE_ALL or RULE_ANY
     */
    public static function level_rule(?\stdClass $levelcriteria): string {
        return $levelcriteria !== null && self::tracking($levelcriteria->coursetracking ?? '') === 'OR'
            ? self::RULE_ANY : self::RULE_ALL;
    }

    /**
     * Is a course of the level mandatory? (courses.mandatory)
     *
     * 1 when tracking is ALL, empty or absent. For AND and OR with a list of course ids, 1 only for a listed course.
     * AND or OR with no list is 1 (BizLMS applied no filter).
     *
     * @param \stdClass|null $levelcriteria local_bcl_cmplt_criteria row, or null.
     * @param int $courseid
     * @return int
     */
    public static function course_mandatory(?\stdClass $levelcriteria, int $courseid): int {
        if ($levelcriteria === null) {
            return 1;
        }
        $tracking = self::tracking($levelcriteria->coursetracking ?? '');
        if ($tracking !== 'AND' && $tracking !== 'OR') {
            return 1;
        }
        $listed = self::csv_ids($levelcriteria->courseids ?? '');
        if (!$listed) {
            return 1;
        }
        return in_array($courseid, $listed, true) ? 1 : 0;
    }

    /**
     * The courses of a level that count towards its completion (the mandatory ones).
     *
     * @param \stdClass|null $levelcriteria
     * @param int[] $levelcourseids Valid course ids of the level.
     * @return int[]
     */
    public static function qualifying_courses(?\stdClass $levelcriteria, array $levelcourseids): array {
        $out = [];
        foreach ($levelcourseids as $courseid) {
            if (self::course_mandatory($levelcriteria, (int) $courseid) === 1) {
                $out[] = (int) $courseid;
            }
        }
        return $out;
    }

    /**
     * The program status the import gives a BizLMS program (mapping doc, "Status mapping").
     *
     * BizLMS status was 0 (new, set on create) or 2 (completed, which no code ever set). The switch that
     * mattered was visible (program.php:536-541), so: status 2 or visible 0 is inactive, everything else is
     * active. Inactive becomes Archived, or Draft when the owner decided so. A status outside 0 and 2 is only
     * reachable when the owner mapped it in the decisions file; it is handled by visible and reported.
     *
     * @param int $status local_program.status
     * @param int|null $visible local_program.visible (NULL is BizLMS' default, 1)
     * @param string $inactive The decision program.inactive: archived or draft.
     * @return array{0: int, 1: bool} [target status, true when the BizLMS status was not 0 or 2]
     */
    public static function program_status(int $status, ?int $visible, string $inactive): array {
        $known = $status === 0 || $status === 2;
        if ($status === 2) {
            return [self::PROGRAM_ARCHIVED, false];
        }
        if ($visible !== null && $visible === 0) {
            return [$inactive === 'draft' ? self::PROGRAM_DRAFT : self::PROGRAM_ARCHIVED, !$known];
        }
        return [self::PROGRAM_ACTIVE, !$known];
    }

    /**
     * When a level was completed, from the learner's real course completions.
     *
     * BizLMS' stored completiondate is the time of the last cron recalculation, not the day the learner finished
     * (completion.php:173,285-290,314-316). The real day comes from the core course completions of the
     * level's qualifying courses: the first one for an any-course level, the last for an all-course level. It is
     * capped at the stored date when there is one, because a course re-completed after an admin reset is later
     * than the real level completion. With no course completions at all the stored date is the fallback.
     *
     * @param int[] $times timecompleted of the learner's completed qualifying courses.
     * @param string $rule RULE_ALL or RULE_ANY.
     * @param int $stored local_bc_level_completions.completiondate (0 when unset).
     * @return int|null Null when neither source knows the date.
     */
    public static function level_completion_date(array $times, string $rule, int $stored): ?int {
        $times = array_values(array_filter(array_map('intval', $times), static fn(int $t): bool => $t > 0));
        if (!$times) {
            return $stored > 0 ? $stored : null;
        }
        $computed = $rule === self::RULE_ANY ? min($times) : max($times);
        return $stored > 0 ? min($computed, $stored) : $computed;
    }

    /**
     * Is a BizLMS integer flag set? NULL counts as not set.
     *
     * @param mixed $value
     * @return int 1 or 0
     */
    public static function flag($value): int {
        return $value !== null && (int) $value !== 0 ? 1 : 0;
    }

    /**
     * A timestamp the target requires: the source value when it is set, else the fallback.
     *
     * @param mixed $value The source column (0 and NULL mean unset).
     * @param int $fallback
     * @return int
     */
    public static function time_or($value, int $fallback): int {
        $value = (int) $value;
        return $value > 0 ? $value : $fallback;
    }

    /**
     * The level that a learner is "on" (users.currentlevelid), from the levels in order and the levels they completed.
     *
     * Not started: none. Completed: the last level. Otherwise the first level with no stored completion; when every
     * level has one (the status still says in progress) the last level.
     *
     * @param int $status The enrolment status.
     * @param int[] $levelids The program's levels in sortorder, then id.
     * @param array<int, bool> $completed Set of level ids the learner has a stored completion for.
     * @return int|null
     */
    public static function current_level(int $status, array $levelids, array $completed): ?int {
        if (!$levelids || $status === self::USER_ENROLLED) {
            return null;
        }
        $last = (int) end($levelids);
        if ($status === self::USER_COMPLETED) {
            return $last;
        }
        foreach ($levelids as $levelid) {
            if (empty($completed[(int) $levelid])) {
                return (int) $levelid;
            }
        }
        return $last;
    }
}
