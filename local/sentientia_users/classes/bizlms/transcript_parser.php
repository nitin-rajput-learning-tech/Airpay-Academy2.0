<?php
// Copyright 2026 Airpay Payment Services
// License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_sentientia_users\bizlms;

defined('MOODLE_INTERNAL') || die();

/**
 * Reads the free text of a BizLMS transcript row (ADR-032, mapping doc section 10).
 *
 * local_transcript_history is a 2015-2016 spreadsheet load: every value is a CHAR(255) and nothing in BizLMS ever
 * wrote or read it. The importer keeps the raw text of each value and adds a parsed one where the text parses.
 * Nothing here guesses: text that does not parse gives NULL (or "unknown" for a status), never a default date.
 *
 * Pure: no database, no clock.
 *
 * @package    local_sentientia_users
 * @copyright  2026 Airpay Payment Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class transcript_parser {

    /** Date layouts the load used, tried in this order. d/m/Y comes first: the load is Indian, never m/d/Y. */
    public const DATE_FORMATS = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd-M-Y'];

    /** Excel day numbers read as a date: 1954 to 2118. A bare smaller number is a count, not a date. */
    private const SERIAL_MIN = 20000;

    /** @see self::SERIAL_MIN */
    private const SERIAL_MAX = 80000;

    /** Largest absolute value a NUMBER(10,2) column holds. */
    private const NUMBER_LIMIT = 100000000;

    /**
     * Statuses a row can end up with besides the lists the owner signed.
     */
    public const UNKNOWN = 'unknown';

    /**
     * A completion date as a timestamp at midnight in the given timezone.
     *
     * Accepts d/m/Y, d-m-Y, Y-m-d, d-M-Y and an Excel day number. A date that does not exist (31/02/2016) or text
     * that is none of these gives NULL.
     *
     * @param string|null $raw
     * @param \DateTimeZone $tz Moodle's effective server timezone.
     * @return int|null
     */
    public static function date(?string $raw, \DateTimeZone $tz): ?int {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^\d{5}(\.\d+)?$/', $raw)) {
            $serial = (int) floor((float) $raw);
            if ($serial >= self::SERIAL_MIN && $serial <= self::SERIAL_MAX) {
                // Excel counts days from 1899-12-30 (its 1900 leap-year bug is already inside that origin).
                $date = (new \DateTimeImmutable('1899-12-30 00:00:00', $tz))->modify('+' . $serial . ' days');
                return $date->getTimestamp();
            }
            return null;
        }
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw, $tz);
            $errors = \DateTimeImmutable::getLastErrors();
            $clean = $errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0);
            if ($date !== false && $clean) {
                $stamp = $date->getTimestamp();
                return ($stamp > 0 && $stamp <= 2147483647) ? $stamp : null;
            }
        }
        return null;
    }

    /**
     * A score: "85", "85%", "85.5". Anything else is NULL.
     *
     * @param string|null $raw
     * @return float|null
     */
    public static function score(?string $raw): ?float {
        $text = trim(str_replace('%', '', (string) $raw));
        if ($text === '' || !is_numeric($text)) {
            return null;
        }
        $value = round((float) $text, 2);
        return abs($value) < self::NUMBER_LIMIT ? $value : null;
    }

    /**
     * Training hours: a decimal ("2", "1.5") or hours:minutes ("1:30" is 1.5). Anything else is NULL.
     *
     * @param string|null $raw
     * @return float|null
     */
    public static function hours(?string $raw): ?float {
        $text = trim((string) $raw);
        if ($text === '') {
            return null;
        }
        if (preg_match('/^(\d{1,4}):([0-5]?\d)$/', $text, $m)) {
            return round((int) $m[1] + ((int) $m[2]) / 60, 2);
        }
        if (!is_numeric($text)) {
            return null;
        }
        $value = round((float) $text, 2);
        return ($value >= 0 && $value < self::NUMBER_LIMIT) ? $value : null;
    }

    /**
     * The normalised status of a raw status text, by the owner's signed list.
     *
     * The text is lower-cased and trimmed, then looked up in each list in the order the file gives them; a text in
     * none of them gets the file's "otherwise" value ("unknown"). The raw text is stored next to it, always.
     *
     * @param string|null $raw
     * @param array $map The signed users.transcript_status_map value: status => [raw texts], plus the
     *        "normalise" note and the "otherwise" status.
     * @return string
     */
    public static function status(?string $raw, array $map): string {
        $key = \core_text::strtolower(trim((string) $raw));
        foreach ($map as $status => $values) {
            if ($status === 'normalise' || $status === 'otherwise' || !is_array($values)) {
                continue;
            }
            if (in_array($key, array_map('strval', $values), true)) {
                return (string) $status;
            }
        }
        $otherwise = isset($map['otherwise']) ? (string) $map['otherwise'] : self::UNKNOWN;
        return $otherwise !== '' ? $otherwise : self::UNKNOWN;
    }

    /**
     * Every status a row can be given by this map, for the verify step.
     *
     * @param array $map
     * @return string[]
     */
    public static function statuses(array $map): array {
        $out = [];
        foreach ($map as $status => $values) {
            if ($status !== 'normalise' && $status !== 'otherwise' && is_array($values)) {
                $out[] = (string) $status;
            }
        }
        $out[] = isset($map['otherwise']) ? (string) $map['otherwise'] : self::UNKNOWN;
        return array_values(array_unique($out));
    }
}
