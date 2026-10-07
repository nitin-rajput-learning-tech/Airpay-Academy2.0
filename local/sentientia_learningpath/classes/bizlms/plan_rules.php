<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_learningpath\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Small, pure value rules the learningplan steps share (ADR-032, mapping section 17).
 *
 * Source values come back from the database as strings or null and BizLMS columns
 * are loose (NULL where the install says NOT NULL, 0 meaning "none"). The writer
 * refuses a value that does not fit its column instead of coercing it, so every
 * value is normalised here, once, and a value that cannot be kept is reported
 * by the caller as a warning code rather than written wrong.
 *
 * @package    local_sentientia_learningpath
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plan_rules {

    /** Largest value a tinyint column is sure to hold on every supported engine. */
    public const TINYINT_MAX = 127;

    /**
     * A 0/1 flag: any non-zero value is 1, NULL and '' are 0.
     *
     * @param mixed $value
     * @return int
     */
    public static function flag(mixed $value): int {
        return self::int_value($value) !== 0 ? 1 : 0;
    }

    /**
     * An integer, 0 for NULL, '' and anything that is not a whole number.
     *
     * @param mixed $value
     * @return int
     */
    public static function int_value(mixed $value): int {
        if ($value === null || $value === '' || is_bool($value) || !is_numeric($value)) {
            return 0;
        }
        return (int) $value;
    }

    /**
     * A positive integer, or NULL for NULL, 0, negatives and non-numbers (BizLMS stored 0 for "none").
     *
     * @param mixed $value
     * @return int|null
     */
    public static function positive_or_null(mixed $value): ?int {
        $int = self::int_value($value);
        return $int > 0 ? $int : null;
    }

    /**
     * A user id column: the id, or 0 when the source has none.
     *
     * @param mixed $value
     * @return int
     */
    public static function user_id(mixed $value): int {
        return max(0, self::int_value($value));
    }

    /**
     * An integer for a small target column, or NULL when it does not fit.
     *
     * @param mixed $value
     * @param int $max Largest value the column holds.
     * @return array{0: int|null, 1: bool} [value, fits]. NULL and '' give [null, true]: nothing was lost.
     */
    public static function small_int(mixed $value, int $max = self::TINYINT_MAX): array {
        if ($value === null || $value === '') {
            return [null, true];
        }
        $int = self::int_value($value);
        if ($int < 0 || $int > $max) {
            return [null, false];
        }
        return [$int, true];
    }

    /**
     * Text the target can store: valid UTF-8, NULL kept as NULL.
     *
     * @param mixed $value
     * @return array{0: string|null, 1: bool} [text, changed]. changed is true when invalid bytes were replaced.
     */
    public static function clean_text(mixed $value): array {
        if ($value === null) {
            return [null, false];
        }
        $text = (string) $value;
        if (mb_check_encoding($text, 'UTF-8')) {
            return [$text, false];
        }
        return [mb_convert_encoding($text, 'UTF-8', 'UTF-8'), true];
    }

    /**
     * Created and modified times of a source row.
     *
     * The target has no default clock: every time* column is set from the source. BizLMS
     * never set timemodified on create, so 0 means "never edited" and takes the created time.
     *
     * @param \stdClass $row
     * @param int $createdfallback Used when the row has no created time of its own (0 stays 0 when this is 0).
     * @return array{0: int, 1: int} [timecreated, timemodified]
     */
    public static function times(\stdClass $row, int $createdfallback = 0): array {
        $created = max(0, self::int_value($row->timecreated ?? 0));
        if ($created === 0) {
            $created = max(0, $createdfallback);
        }
        $modified = max(0, self::int_value($row->timemodified ?? 0));
        if ($modified === 0) {
            $modified = $created;
        }
        return [$created, $modified];
    }

    /**
     * The mandatory flag of a path course from BizLMS nextsetoperator.
     *
     * BizLMS lower-case 'and' is the course the learner must finish; 'or' and NULL are optional
     * (mapping section 17, classes/lib/lib.php:1006-1010). Some production rows carry 'AND', so the
     * comparison ignores case and surrounding spaces.
     *
     * @param mixed $operator
     * @return array{0: int, 1: bool} [mandatory, known]. known is false for a value that is neither and, or nor empty.
     */
    public static function mandatory_from_operator(mixed $operator): array {
        $value = strtolower(trim((string) $operator));
        if ($value === 'and') {
            return [1, true];
        }
        return [0, $value === 'or' || $value === ''];
    }
}
