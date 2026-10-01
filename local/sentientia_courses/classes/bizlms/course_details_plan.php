<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_courses\bizlms;

defined('MOODLE_INTERNAL') || die();

use local_sentientia_platform\bizlms\context;

/**
 * What a BizLMS local_coursedetails row may write into a course's open_* columns.
 *
 * One pure function, used by the load step (to decide whether a row has anything to give), by the
 * fill step (to compute the update) and by the importer's preflight and verify, so they cannot
 * disagree.
 *
 * Rules (mapping doc, section 6):
 *  - only a course column that is NULL, empty or 0 is written, and only from a source value that
 *    means something (a positive integer, or for identifiedas a comma list of positive integers);
 *  - six columns are certain: cost, coursecompletiondays, coursecreator, identifiedas, requestcourseid
 *    and skill. proficiencylevel -> open_level and credits -> open_points are "candidates, verify on data"
 *    in the map, so they are written only when the owner's decision
 *    course_lookups.coursedetails_candidate_columns says fill (the default is leave);
 *  - costcenterid is ignored (course.open_path is authoritative); enrollstartdate, enrollenddate, duration and
 *    prerequisite_courses stay in the legacy table (decision course_lookups.coursedetails_unhomed_columns).
 *    prerequisite_courses is never turned into course completion criteria: nothing here touches them;
 *  - coursecreator names a person, so it is written only when that user exists (a deleted user still exists).
 *
 * @package    local_sentientia_courses
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class course_details_plan {

    /** Value kinds. */
    private const POSINT = 'posint';
    private const USER = 'user';
    private const CSV = 'csv';
    private const DIGITS = 'digits';

    /**
     * The certain columns: source column => [course column, kind].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CERTAIN = [
        'cost' => ['open_cost', self::POSINT],
        'coursecompletiondays' => ['open_coursecompletiondays', self::POSINT],
        'coursecreator' => ['open_coursecreator', self::USER],
        'identifiedas' => ['open_identifiedas', self::CSV],
        'requestcourseid' => ['open_requestcourseid', self::POSINT],
        'skill' => ['open_skill', self::POSINT],
    ];

    /**
     * The candidate columns, written only on the owner's say-so.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CANDIDATES = [
        'proficiencylevel' => ['open_level', self::POSINT],
        'credits' => ['open_points', self::DIGITS],
    ];

    /**
     * Every course column the plan may touch, to read from the course table.
     *
     * @return string[]
     */
    public static function course_columns(): array {
        $columns = [];
        foreach (array_merge(self::CERTAIN, self::CANDIDATES) as [$column]) {
            $columns[] = $column;
        }
        return $columns;
    }

    /**
     * Does the owner want the two candidate columns written?
     *
     * @param context $ctx
     * @return bool
     */
    public static function candidates_wanted(context $ctx): bool {
        return $ctx->decision('course_lookups.coursedetails_candidate_columns') === 'fill';
    }

    /**
     * Plan the fill of one course from one source row.
     *
     * @param \stdClass $detail The local_coursedetails row.
     * @param \stdClass|null $course The course row, carrying the open_* columns the table has; null when the course is gone.
     * @param context $ctx
     * @return array{fields: array<string, int|string>, warnings: string[], candidates: array<string, int|string>}
     *         fields: course column => value to write; warnings: codes; candidates: what the two candidate columns would
     *         have written, listed even when the owner left them (for the preflight counts).
     */
    public static function build(\stdClass $detail, ?\stdClass $course, context $ctx): array {
        $plan = ['fields' => [], 'warnings' => [], 'candidates' => []];
        if ($course === null) {
            return $plan;
        }
        $wanted = self::candidates_wanted($ctx);
        foreach (self::CERTAIN as $source => [$column, $kind]) {
            self::plan_column($plan['fields'], $plan['warnings'], $detail, $course, $ctx, $source, $column, $kind);
        }
        $candidatewarnings = [];
        foreach (self::CANDIDATES as $source => [$column, $kind]) {
            if ($wanted) {
                self::plan_column($plan['fields'], $plan['warnings'], $detail, $course, $ctx, $source, $column, $kind);
            } else {
                self::plan_column($plan['candidates'], $candidatewarnings, $detail, $course, $ctx, $source, $column, $kind);
            }
        }
        return $plan;
    }

    /**
     * Plan one column.
     *
     * @param array $fields Receives column => value.
     * @param string[] $warnings Receives warning codes.
     * @param \stdClass $detail
     * @param \stdClass $course
     * @param context $ctx
     * @param string $source local_coursedetails column.
     * @param string $column course column.
     * @param string $kind
     * @return void
     */
    private static function plan_column(array &$fields, array &$warnings, \stdClass $detail, \stdClass $course,
                                        context $ctx, string $source, string $column, string $kind): void {
        if (!property_exists($course, $column) || !self::is_empty($course->{$column})) {
            return;
        }
        $raw = $detail->{$source} ?? null;
        $value = self::value($raw, $kind, $ctx, $source, $warnings);
        if ($value !== null) {
            $fields[$column] = $value;
        }
    }

    /**
     * The value a source cell means, or null when it means nothing.
     *
     * @param mixed $raw
     * @param string $kind
     * @param context $ctx
     * @param string $source Source column, for the warning code.
     * @param string[] $warnings
     * @return int|string|null
     */
    private static function value(mixed $raw, string $kind, context $ctx, string $source, array &$warnings): int|string|null {
        if ($raw === null) {
            return null;
        }
        $text = trim((string) $raw);
        if ($text === '' || $text === '0') {
            return null;
        }
        switch ($kind) {
            case self::CSV:
                if (preg_match('/^[1-9][0-9]{0,9}(,[1-9][0-9]{0,9}){0,24}$/', $text) && strlen($text) <= 255) {
                    return $text;
                }
                $warnings[] = $source . '_not_a_list';
                return null;
            case self::DIGITS:
                if (preg_match('/^[0-9]{1,9}$/', $text) && (int) $text > 0) {
                    return (int) $text;
                }
                $warnings[] = $source . '_not_numeric';
                return null;
            case self::USER:
                if (preg_match('/^[0-9]{1,10}$/', $text) && (int) $text > 0 && (int) $text <= 2147483647) {
                    if ($ctx->lookups->user_exists((int) $text)) {
                        return (int) $text;
                    }
                    $warnings[] = $source . '_not_found';
                    return null;
                }
                return null;
            default:
                if (preg_match('/^[0-9]{1,18}$/', $text) && (int) $text > 0) {
                    return (int) $text;
                }
                return null;
        }
    }

    /**
     * Is a course column empty in the sense of the map: NULL, '' or 0?
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_empty(mixed $value): bool {
        return $value === null || trim((string) $value) === '' || trim((string) $value) === '0';
    }
}
