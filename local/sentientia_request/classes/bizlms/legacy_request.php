<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_request\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * What the BizLMS request tables hold and how it maps (mapping doc, section 19).
 *
 * Constants and pure functions only: nothing here reads the database.
 *
 * @package    local_sentientia_request
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class legacy_request {

    /** The one table the feature writes. */
    public const TARGET = 'local_sentientia_request';

    /** BizLMS requests, one row per request (duplicates are legal and stay separate). */
    public const SOURCE_RECORDS = 'local_request_records';

    /** BizLMS learning-plan approvals (this feature owns the table: one owner per legacy table). */
    public const SOURCE_APPROVALS = 'local_learningplan_approval';

    /** BizLMS request comments (expected empty). */
    public const SOURCE_COMMENTS = 'local_request_comments';

    /** Value of local_sentientia_request.legacy_source on a row the import wrote. */
    public const MARK = 'bizlms';

    /** Largest value of a Moodle int(10) column. */
    public const INT_MAX = 2147483647;

    /**
     * compname => item_type (BizLMS requestapi.php:70-77,316-339). Any other value blocks the feature: the
     * web service took it as PARAM_RAW and create() stored whatever it was given.
     *
     * @var array<string, string>
     */
    public const COMPNAMES = [
        'elearning' => 'course',
        'learningplan' => 'path',
        'classroom' => 'classroom',
        'program' => 'program',
        'certification' => 'certification',
    ];

    /**
     * status => status. Nothing in local_request ever writes COMPLETE (mapping doc, "Status mapping"); any
     * other value blocks the feature.
     *
     * @var array<string, string>
     */
    public const RECORD_STATUSES = [
        'PENDING' => 'pending',
        'APPROVED' => 'approved',
        'REJECTED' => 'rejected',
    ];

    /**
     * approvestatus => status (BizLMS learningplan lib.php:476-485,511-523). 2 also covers "removed from plan".
     *
     * @var array<int, string>
     */
    public const APPROVAL_STATUSES = [
        0 => 'pending',
        1 => 'approved',
        2 => 'rejected',
    ];

    /**
     * item_type => legacy table whose map gives the Sentientia id. A course id is a core id and is not mapped;
     * a certification has no Sentientia entity yet (gap G3), so it keeps its legacy id.
     *
     * @var array<string, string>
     */
    public const ITEM_SOURCES = [
        'path' => 'local_learningplan',
        'classroom' => 'local_classroom',
        'program' => 'local_program',
    ];

    /**
     * Columns of local_request_records that nothing in BizLMS writes and nothing in Sentientia reads. They are
     * not copied; preflight counts the non-NULL values and stops for the owner if there are any.
     *
     * @var string[]
     */
    public const UNUSED_COLUMNS = ['compcode', 'compkey', 'req_type', 'req_values', 'c1', 'c2', 'c3'];

    /**
     * Which item types a person can still decide: request_manager::decide() enrols into a course or a path only.
     *
     * @var string[]
     */
    public const DECIDABLE = ['course', 'path'];

    /**
     * A non-negative integer that fits an int(10) column.
     *
     * @param mixed $value
     * @return int|null Null when it is not an integer, or does not fit.
     */
    public static function int10(mixed $value): ?int {
        if ($value === null || is_bool($value) || is_array($value) || is_object($value)) {
            return null;
        }
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::INT_MAX]]);
        return $int === false ? null : (int) $int;
    }

    /**
     * A Unix timestamp that fits an int(10) column. Zero is a value (it is what SQL COALESCE would return);
     * only NULL, junk and out-of-range numbers are missing.
     *
     * @param mixed $value
     * @return int|null
     */
    public static function timestamp(mixed $value): ?int {
        return self::int10($value);
    }

    /**
     * The first timestamp that is present, like SQL COALESCE.
     *
     * @param array<int|null> $candidates In priority order.
     * @return array{0: int, 1: bool} [value (0 when none), true when it is not the first candidate]
     */
    public static function coalesce_time(array $candidates): array {
        $first = true;
        foreach ($candidates as $candidate) {
            if ($candidate !== null) {
                return [$candidate, !$first];
            }
            $first = false;
        }
        return [0, true];
    }

    /**
     * The request a comment belongs to: CAST(instanceid AS INT) in MySQL. instanceid is a char(20) column.
     *
     * @param mixed $instanceid
     * @return int 0 when it names no request.
     */
    public static function comment_request_id(mixed $instanceid): int {
        $value = (int) trim((string) $instanceid);
        return $value > 0 ? $value : 0;
    }
}
