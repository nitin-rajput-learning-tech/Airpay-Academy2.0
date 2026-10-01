<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Small, pure helpers the users steps share (ADR-032, mapping doc section 10).
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class clean {

    /** Largest value an INT(10) column is sure to hold on every supported engine. */
    public const INT_MAX = 2147483647;

    /**
     * Repair text that is not valid UTF-8. The writer refuses a value that is not, which would stop a whole batch;
     * the original bytes stay in the legacy table, so the row is imported with the bad bytes replaced and the
     * caller adds an invalid_utf8 warning.
     *
     * @param string|null $value
     * @return array{0: string, 1: bool} [text, repaired]
     */
    public static function utf8(?string $value): array {
        $value = (string) $value;
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return [$value, false];
        }
        return [mb_scrub($value, 'UTF-8'), true];
    }

    /**
     * A source counter as a non-negative INT(10): NULL is 0 (the source columns are nullable), and a value no
     * INT(10) can hold is clamped so the writer does not refuse the row. The legacy counters are BIGINT-sized
     * (length 20) although BizLMS only ever wrote small numbers.
     *
     * @param mixed $value
     * @return array{0: int, 1: bool} [value, clamped]
     */
    public static function count(mixed $value): array {
        if ($value === null || $value === '') {
            return [0, false];
        }
        $number = (int) $value;
        if ($number < 0) {
            return [0, true];
        }
        if ($number > self::INT_MAX) {
            return [self::INT_MAX, true];
        }
        return [$number, false];
    }

    /**
     * A source timestamp as an INT(10): NULL, negative or out of range is 0, "no time", which is what the
     * source meant (BizLMS wrote NULL when it had none). Nothing invents a time.
     *
     * @param mixed $value
     * @return int
     */
    public static function time(mixed $value): int {
        $number = (int) $value;
        return ($number > 0 && $number <= self::INT_MAX) ? $number : 0;
    }

    /**
     * A source id or foreign id as a non-negative INT(10) (NULL is 0).
     *
     * @param mixed $value
     * @return int
     */
    public static function id(mixed $value): int {
        $number = (int) $value;
        return ($number > 0 && $number <= self::INT_MAX) ? $number : 0;
    }
}
